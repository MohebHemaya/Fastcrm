<?php

namespace App\Http\Controllers\Api;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\DriverAnswer;
use App\Models\QuestionOption;
use App\Services\QuestionnaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverAnswerController extends ApiController
{
    public function __construct(
        protected QuestionnaireService $questionnaireService,
    ) {}

    /**
     * Get answers for a specific driver.
     */
    public function index(int $driverId): JsonResponse
    {
        $driver = Driver::findOrFail($driverId);

        $currentAnswers = DriverAnswer::where('driver_id', $driver->id)
            ->where('is_current', true)
            ->with(['question.activeOptions', 'version', 'option'])
            ->get();

        $historyAnswers = DriverAnswer::where('driver_id', $driver->id)
            ->where('is_current', false)
            ->with(['question', 'version', 'option'])
            ->latest('answered_at')
            ->get();

        $nextQuestion = $this->questionnaireService->getNextUnansweredQuestion($driver);

        return $this->success([
            'driver_id' => $driver->id,
            'driver_status' => $driver->status->value,
            'driver_status_label' => $driver->status->label(),
            'needs_review' => (bool) $driver->needs_review,
            'review_reason' => $driver->review_reason,
            'next_question' => $nextQuestion ? [
                'id' => $nextQuestion->id,
                'key' => $nextQuestion->key,
                'text_ar' => $nextQuestion->currentVersion?->text_ar,
                'type' => $nextQuestion->type->value,
            ] : null,
            'answers' => $currentAnswers->map(fn (DriverAnswer $a) => [
                'id' => $a->id,
                'question_id' => $a->question_id,
                'question_key' => $a->question->key,
                'question_text' => $a->version->text_ar,
                'version' => $a->version->version,
                'type' => $a->version->type->value,
                'type_label' => $a->version->type->label(),
                'display_value' => $a->display_value,
                'value_text' => $a->value_text,
                'value_number' => $a->value_number,
                'option_id' => $a->option_id,
                'option_label' => $a->option?->label_ar,
                'is_skipped' => (bool) $a->is_skipped,
                'answered_at' => $a->answered_at?->toIso8601String(),
                'available_options' => $a->question->activeOptions->map(fn (QuestionOption $o) => [
                    'id' => $o->id,
                    'value' => $o->value,
                    'label_ar' => $o->label_ar,
                ]),
            ]),
            'history' => $historyAnswers->map(fn (DriverAnswer $a) => [
                'id' => $a->id,
                'question_id' => $a->question_id,
                'question_key' => $a->question->key,
                'question_text' => $a->version->text_ar,
                'version' => $a->version->version,
                'display_value' => $a->display_value,
                'is_skipped' => (bool) $a->is_skipped,
                'answered_at' => $a->answered_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Manually update an answer for a driver (Audited).
     */
    public function update(Request $request, int $driverId, int $answerId): JsonResponse
    {
        $driver = Driver::findOrFail($driverId);
        $answer = DriverAnswer::where('driver_id', $driver->id)->findOrFail($answerId);

        $validated = $request->validate([
            'value_text' => 'nullable|string|max:1000',
            'value_number' => 'nullable|numeric',
            'option_id' => 'nullable|integer|exists:question_options,id',
            'is_skipped' => 'nullable|boolean',
        ]);

        $oldValue = $answer->display_value;

        $option = null;
        if (! empty($validated['option_id'])) {
            $option = QuestionOption::find($validated['option_id']);
            $validated['value_text'] = $option?->label_ar;
        }

        $answer->update([
            'value_text' => $validated['value_text'] ?? $answer->value_text,
            'value_number' => $validated['value_number'] ?? $answer->value_number,
            'option_id' => $option ? $option->id : $answer->option_id,
            'is_skipped' => $validated['is_skipped'] ?? $answer->is_skipped,
            'answered_at' => now(),
        ]);

        $newValue = $answer->fresh()->display_value;

        ActivityLog::record(
            action: 'update_driver_answer',
            description: "تم تعديل إجابة السائق {$driver->name} ({$driver->phone}) لسؤال {$answer->question->key} من '{$oldValue}' إلى '{$newValue}'",
            subjectType: Driver::class,
            subjectId: $driver->id,
            properties: [
                'answer_id' => $answer->id,
                'question_id' => $answer->question_id,
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ]
        );

        return $this->success(null, 'تم تعديل الإجابة بنجاح');
    }

    /**
     * Resend current question (Admin action).
     */
    public function resendQuestion(int $driverId): JsonResponse
    {
        $driver = Driver::findOrFail($driverId);

        $msg = $this->questionnaireService->resendCurrentQuestion($driver);

        if (! $msg) {
            return $this->error('لا توجد أسئلة غير مجابة لهذا السائق.', 400);
        }

        ActivityLog::record(
            action: 'resend_questionnaire_question',
            description: "تم إعادة إرسال السؤال للسائق {$driver->name} ({$driver->phone})",
            subjectType: Driver::class,
            subjectId: $driver->id
        );

        return $this->success(null, 'تمت إعادة إرسال السؤال للسائق بنجاح');
    }

    /**
     * Request answer update from active driver (Admin action).
     */
    public function requestAnswerUpdate(int $driverId): JsonResponse
    {
        $driver = Driver::findOrFail($driverId);

        $sent = $this->questionnaireService->requestAnswerUpdate($driver);

        if (! $sent) {
            return $this->error('جميع الأسئلة الحالية مجابة بالفعل من قبل السائق، لا توجد أسئلة جديدة.', 400);
        }

        ActivityLog::record(
            action: 'request_answer_update',
            description: "تم طلب تحديث الإجابات من السائق {$driver->name} ({$driver->phone})",
            subjectType: Driver::class,
            subjectId: $driver->id
        );

        return $this->success(null, 'تم إرسال طلب تحديث الإجابات للسائق بنجاح');
    }
}
