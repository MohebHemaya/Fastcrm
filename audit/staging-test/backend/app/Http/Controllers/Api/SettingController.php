<?php

namespace App\Http\Controllers\Api;

use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends ApiController
{
    /**
     * Get all settings.
     */
    public function index(): JsonResponse
    {
        $settings = Setting::all();

        $map = [];
        $definitions = [];

        foreach ($settings as $st) {
            $val = Setting::get($st->key);
            $map[$st->key] = $val;
            $definitions[] = [
                'key' => $st->key,
                'value' => $val,
                'type' => $st->type,
                'description_ar' => $st->description_ar,
            ];
        }

        return $this->success([
            'settings' => $map,
            'definitions' => $definitions,
        ]);
    }

    /**
     * Update settings.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.questionnaire_reminder_hours' => 'nullable|integer|min:1|max:168',
            'settings.questionnaire_timeout_days' => 'nullable|integer|min:1|max:30',
            'settings.send_rate_per_minute' => 'nullable|integer|min:1|max:300',
            'settings.invite_batch_size' => 'nullable|integer|min:1|max:500',
            'settings.invite_no_response_days' => 'nullable|integer|min:1|max:30',
        ], [
            'settings.questionnaire_reminder_hours.integer' => 'مهلة التذكير يجب أن تكون رقماً صحيحاً.',
            'settings.questionnaire_timeout_days.integer' => 'مهلة الاستبيان يجب أن تكون رقماً صحيحاً.',
            'settings.send_rate_per_minute.integer' => 'معدل الإرسال يجب أن يكون رقماً صحيحاً.',
        ]);

        $updatedKeys = [];
        foreach ($validated['settings'] as $k => $v) {
            if ($v !== null) {
                Setting::set($k, $v);
                $updatedKeys[] = $k;
            }
        }

        ActivityLog::record(
            action: 'update_settings',
            description: 'تم تحديث إعدادات النظام: '.implode(', ', $updatedKeys)
        );

        return $this->success(null, 'تم حفظ الإعدادات بنجاح');
    }
}
