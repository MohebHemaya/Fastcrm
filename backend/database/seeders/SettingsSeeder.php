<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'key' => 'questionnaire_reminder_hours',
                'value' => 24,
                'type' => 'integer',
                'description_ar' => 'عدد الساعات قبل إرسال رسالة تذكير بالاستبيان للسائق',
            ],
            [
                'key' => 'questionnaire_timeout_days',
                'value' => 3,
                'type' => 'integer',
                'description_ar' => 'عدد الأيام قبل تحويل السائق إلى حالة لم يرد في حال عدم إكمال الاستبيان',
            ],
            [
                'key' => 'send_rate_per_minute',
                'value' => 30,
                'type' => 'integer',
                'description_ar' => 'أقصى معدل إرسال رسائل في الدقيقة للواتساب',
            ],
            [
                'key' => 'invite_batch_size',
                'value' => 50,
                'type' => 'integer',
                'description_ar' => 'حجم دفعة إرسال الدعوات للسائقين الجدد',
            ],
            [
                'key' => 'invite_no_response_days',
                'value' => 3,
                'type' => 'integer',
                'description_ar' => 'عدد الأيام لانتهاء مهلة الدعوة وتحويل السائق إلى لم يرد',
            ],
        ];

        foreach ($defaults as $d) {
            Setting::set($d['key'], $d['value'], $d['type'], $d['description_ar']);
        }
    }
}
