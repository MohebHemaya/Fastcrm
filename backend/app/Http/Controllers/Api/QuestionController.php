<?php

namespace App\Http\Controllers\Api;

use App\Enums\QuestionType;
use App\Models\ActivityLog;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestionController extends ApiController
{
    /**
     * List all questions ordered by sort_order.
     */
    public function index(): JsonResponse
    {
        $questions = Question::ordered()
            ->with(['currentVersion', 'activeOptions', 'versions'])
            ->withCount('answers')
            ->get();

        $data = $questions->map(fn (Question $q) => $this->formatQuestion($q));

        return $this->success($data);
    }

    /**
     * Store a new question.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|max:100|regex:/^[a-z0-9_]+$/|unique:questions,key',
            'type' => 'required|string|in:single_choice,yes_no,number,text',
            'text_ar' => 'required|string|max:1000',
            'is_required' => 'boolean',
            'is_filter' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'options' => 'nullable|array',
            'options.*.value' => 'required_with:options|string|max:50',
            'options.*.label_ar' => 'required_with:options|string|max:100',
        ], [
            'key.required' => 'رمز السؤال (المفتاح) مطلوب.',
            'key.unique' => 'رمز السؤال مستخدم بالفعل، يرجى اختيار رمز آخر.',
            'key.regex' => 'رمز السؤال يجب أن يحتوي على أحرف إنجليزية وأرقام وشرطة سفلية فقط.',
            'text_ar.required' => 'نص السؤال بالعربية مطلوب.',
            'type.in' => 'نوع السؤال غير صالح.',
        ]);

        $this->validateOptions($validated['type'], $validated['options'] ?? []);

        $type = QuestionType::from($validated['type']);

        $question = DB::transaction(function () use ($validated, $type) {
            $question = Question::create([
                'key' => $validated['key'],
                'sort_order' => $validated['sort_order'] ?? (Question::max('sort_order') + 1),
                'is_active' => $validated['is_active'] ?? true,
                'is_required' => $validated['is_required'] ?? true,
                'is_filter' => $validated['is_filter'] ?? false,
                'type' => $type,
            ]);

            $options = $validated['options'] ?? [];
            $question->recordVersion($validated['text_ar'], $type, $options);

            return $question->load(['currentVersion', 'activeOptions', 'versions']);
        });

        ActivityLog::record(
            action: 'create_question',
            description: "تم إنشاء السؤال: {$validated['text_ar']} ({$question->key})",
            subjectType: Question::class,
            subjectId: $question->id,
            properties: ['key' => $question->key, 'type' => $question->type->value]
        );

        return $this->success($this->formatQuestion($question), 'تم إضافة السؤال بنجاح', 201);
    }

    /**
     * Show a specific question with options and version history.
     */
    public function show(int $id): JsonResponse
    {
        $question = Question::with(['currentVersion', 'activeOptions', 'versions.answers', 'options'])
            ->withCount('answers')
            ->findOrFail($id);

        return $this->success($this->formatQuestion($question, includeVersions: true));
    }

    /**
     * Update an existing question. Creates a new version if text, type, or options changed.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $question = Question::with(['currentVersion', 'activeOptions'])->findOrFail($id);

        $validated = $request->validate([
            'key' => "required|string|max:100|regex:/^[a-z0-9_]+$/|unique:questions,key,{$id}",
            'type' => 'required|string|in:single_choice,yes_no,number,text',
            'text_ar' => 'required|string|max:1000',
            'is_required' => 'boolean',
            'is_filter' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'options' => 'nullable|array',
            'options.*.value' => 'required_with:options|string|max:50',
            'options.*.label_ar' => 'required_with:options|string|max:100',
        ], [
            'key.required' => 'رمز السؤال مطلوب.',
            'key.unique' => 'رمز السؤال مستخدم بالفعل.',
            'text_ar.required' => 'نص السؤال بالعربية مطلوب.',
        ]);

        $this->validateOptions($validated['type'], $validated['options'] ?? []);

        $type = QuestionType::from($validated['type']);
        $currentVersion = $question->currentVersion;

        $hasChanged = ! $currentVersion
            || $currentVersion->text_ar !== $validated['text_ar']
            || $currentVersion->type !== $type
            || $this->haveOptionsChanged($question->activeOptions->toArray(), $validated['options'] ?? []);

        DB::transaction(function () use ($question, $validated, $type, $hasChanged) {
            $question->update([
                'key' => $validated['key'],
                'sort_order' => $validated['sort_order'] ?? $question->sort_order,
                'is_active' => $validated['is_active'] ?? $question->is_active,
                'is_required' => $validated['is_required'] ?? $question->is_required,
                'is_filter' => $validated['is_filter'] ?? $question->is_filter,
                'type' => $type,
            ]);

            if ($hasChanged) {
                $question->recordVersion($validated['text_ar'], $type, $validated['options'] ?? []);
            }
        });

        ActivityLog::record(
            action: 'update_question',
            description: "تم تحديث السؤال: {$validated['text_ar']} ({$question->key})",
            subjectType: Question::class,
            subjectId: $question->id,
            properties: ['new_version' => $hasChanged]
        );

        $question->load(['currentVersion', 'activeOptions', 'versions']);

        return $this->success($this->formatQuestion($question), 'تم تحديث السؤال بنجاح');
    }

    /**
     * Toggle question active state.
     */
    public function toggleActive(int $id): JsonResponse
    {
        $question = Question::findOrFail($id);
        if ($question->is_system) {
            return $this->error('لا يمكن تعديل أو تعطيل معايير النظام الأساسية.', 422);
        }
        $question->update(['is_active' => ! $question->is_active]);

        $statusStr = $question->is_active ? 'تفعيل' : 'تعطيل';
        ActivityLog::record(
            action: 'toggle_question_active',
            description: "تم {$statusStr} السؤال: {$question->key}",
            subjectType: Question::class,
            subjectId: $question->id
        );

        return $this->success([
            'id' => $question->id,
            'is_active' => $question->is_active,
        ], "تم {$statusStr} السؤال بنجاح");
    }

    /**
     * Reorder questions.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|integer|exists:questions,id',
            'orders.*.sort_order' => 'required|integer',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['orders'] as $item) {
                Question::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
            }
        });

        ActivityLog::record(
            action: 'reorder_questions',
            description: 'تم إعادة ترتيب الأسئلة في الاستبيان'
        );

        return $this->success(null, 'تم حفظ ترتيب الأسئلة بنجاح');
    }

    /**
     * Soft deactivate a question.
     */
    public function destroy(int $id): JsonResponse
    {
        $question = Question::findOrFail($id);
        if ($question->is_system) {
            return $this->error('لا يمكن حذف أو تعطيل معايير النظام الأساسية.', 422);
        }
        $question->update(['is_active' => false]);

        ActivityLog::record(
            action: 'deactivate_question',
            description: "تم تعطيل السؤال: {$question->key}",
            subjectType: Question::class,
            subjectId: $question->id
        );

        return $this->success(null, 'تم تعطيل السؤال بنجاح');
    }

    /**
     * Validate options limits for single_choice questions.
     */
    protected function validateOptions(string $type, array $options): void
    {
        if ($type !== 'single_choice') {
            return;
        }

        $count = count($options);
        if ($count < 2 || $count > 10) {
            throw ValidationException::withMessages([
                'options' => 'سؤال الاختيار من متعدد يتطلب بين 2 إلى 10 خيارات.',
            ]);
        }

        if ($count <= 3) {
            // Button limit: 20 chars
            foreach ($options as $opt) {
                $label = $opt['label_ar'] ?? '';
                if (mb_strlen($label) > 20) {
                    throw ValidationException::withMessages([
                        'options' => "نص الخيار '{$label}' يتجاوز 20 حرفاً (الحد الأقصى للأزرار هو 20 حرفاً).",
                    ]);
                }
            }
        } else {
            // List item limit: 24 chars
            foreach ($options as $opt) {
                $label = $opt['label_ar'] ?? '';
                if (mb_strlen($label) > 24) {
                    throw ValidationException::withMessages([
                        'options' => "نص الخيار '{$label}' يتجاوز 24 حرفاً (الحد الأقصى للقائمة هو 24 حرفاً).",
                    ]);
                }
            }
        }
    }

    /**
     * Check if options array changed compared to existing active options.
     */
    protected function haveOptionsChanged(array $existing, array $incoming): bool
    {
        if (count($existing) !== count($incoming)) {
            return true;
        }

        foreach ($incoming as $idx => $opt) {
            $ex = $existing[$idx] ?? null;
            if (! $ex || ($ex['value'] ?? '') !== ($opt['value'] ?? '') || ($ex['label_ar'] ?? '') !== ($opt['label_ar'] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format a question model for API response.
     */
    protected function formatQuestion(Question $q, bool $includeVersions = false): array
    {
        $currentVersion = $q->currentVersion;

        $data = [
            'id' => $q->id,
            'key' => $q->key,
            'type' => $q->type->value,
            'type_label' => $q->type->label(),
            'text_ar' => $currentVersion ? $currentVersion->text_ar : '',
            'version' => $currentVersion ? $currentVersion->version : 1,
            'sort_order' => $q->sort_order,
            'is_active' => (bool) $q->is_active,
            'is_required' => (bool) $q->is_required,
            'is_filter' => (bool) $q->is_filter,
            'is_system' => (bool) $q->is_system,
            'options' => $q->key === 'whatsapp_group'
                ? \App\Models\WhatsAppGroup::orderBy('name')->get()->map(fn ($g) => [
                    'id' => $g->id,
                    'value' => (string) $g->id,
                    'label_ar' => $g->name,
                    'sort_order' => $g->id,
                ])
                : $q->activeOptions->map(fn (QuestionOption $opt) => [
                    'id' => $opt->id,
                    'value' => $opt->value,
                    'label_ar' => $opt->label_ar,
                    'sort_order' => $opt->sort_order,
                ]),
            'options_count' => $q->key === 'whatsapp_group' ? \App\Models\WhatsAppGroup::count() : $q->activeOptions->count(),
            'answers_count' => $q->answers_count ?? 0,
            'versions_count' => $q->versions->count(),
            'created_at' => $q->created_at?->toIso8601String(),
            'updated_at' => $q->updated_at?->toIso8601String(),
        ];

        if ($includeVersions) {
            $data['versions'] = $q->versions->map(fn (QuestionVersion $v) => [
                'id' => $v->id,
                'version' => $v->version,
                'text_ar' => $v->text_ar,
                'type' => $v->type->value,
                'options_snapshot' => $v->options_snapshot,
                'answers_count' => $v->answers->count(),
                'created_at' => $v->created_at?->toIso8601String(),
            ]);
        }

        return $data;
    }
}
