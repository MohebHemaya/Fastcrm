<?php

namespace App\Services;

use App\Models\Setting;

class WhatsAppTemplateService
{
    /**
     * Get all customizable WhatsApp message templates with their metadata.
     *
     * @return array<int, array{
     *     key: string,
     *     title_ar: string,
     *     description_ar: string,
     *     default_text: string,
     *     current_text: string,
     *     variables: array<int, array{tag: string, label_ar: string}>
     * }>
     */
    public function getTemplates(): array
    {
        $definitions = [
            [
                'key' => 'driver_invitation',
                'title_ar' => 'رسالة دعوة السائق (الترحيب بالأسطول)',
                'description_ar' => 'تُرسل تلقائيًا عند دعوة سائق جديد للاشتراك في شبكة النقل السريع وبدء استبيان البيانات.',
                'default_text' => "أهلاً بك يا {{اسم_السائق}}! معك شركة النقل السريع. نتشرف بدعوتك للانضمام لشبكة السائقين لدينا وتلقي رحلات مميزة. هل أنت مهتم بالانضمام إلينا؟ (للإلغاء أرسل: إيقاف)",
                'variables' => [
                    ['tag' => '{{اسم_السائق}}', 'label_ar' => 'اسم السائق المسجل'],
                ],
            ],
            [
                'key' => 'order_offer',
                'title_ar' => 'صيغة عرض الرحلة الافتراضية (أمر الشغل)',
                'description_ar' => 'الصيغة الأساسية لعروض الرحلات المرسلة للسائقين (يمكن تعديلها لكل أمر شغل على حدة).',
                'default_text' => "السلام عليكم كابتن {{اسم_السائق}}، متاح لدينا أمر شغل جديد رقم {{رقم_الطلب}}:\n- خط السير: من {{مكان_الانطلاق}} إلى {{وجهة_الوصول}}\n- الموعد: {{موعد_الرحلة}}\n- المقابل: {{المقابل_المادي}}\n- الحمولة: {{نوع_الحمولة}}\n{{الملاحظات}}\nبرجاء تأكيد رغبتك بالقبول أو الاعتذار.",
                'variables' => [
                    ['tag' => '{{اسم_السائق}}', 'label_ar' => 'اسم السائق'],
                    ['tag' => '{{رقم_الطلب}}', 'label_ar' => 'كود أمر الشغل'],
                    ['tag' => '{{مكان_الانطلاق}}', 'label_ar' => 'نقطة التحميل'],
                    ['tag' => '{{وجهة_الوصول}}', 'label_ar' => 'نقطة التسليم'],
                    ['tag' => '{{موعد_الرحلة}}', 'label_ar' => 'تاريخ ووقت الرحلة'],
                    ['tag' => '{{المقابل_المادي}}', 'label_ar' => 'أجرة السائق'],
                    ['tag' => '{{نوع_الحمولة}}', 'label_ar' => 'نوع الحمولة'],
                    ['tag' => '{{الملاحظات}}', 'label_ar' => 'ملاحظات المشرف إن وجدت'],
                    ['tag' => '{{عدد_الرحلات}}', 'label_ar' => 'عدد السيارات المطلوبة'],
                ],
            ],
            [
                'key' => 'order_confirmation',
                'title_ar' => 'تأكيد حجز مقعد أساسي',
                'description_ar' => 'تُرسل للسائق فور موافقته على الرحلة واكتمال حجز مقعد مؤكد ضمن العدد المطلوب.',
                'default_text' => "مبروك يا {{اسم_السائق}}! تم تأكيد تكليفك بالرحلة رقم {{رقم_الطلب}}.\n- خط السير: من {{مكان_الانطلاق}} إلى {{وجهة_الوصول}}\n- الموعد: {{موعد_الرحلة}}\n- المقابل: {{المقابل_المادي}}\nنتمنى لك رحلة موفقة!",
                'variables' => [
                    ['tag' => '{{اسم_السائق}}', 'label_ar' => 'اسم السائق'],
                    ['tag' => '{{رقم_الطلب}}', 'label_ar' => 'كود أمر الشغل'],
                    ['tag' => '{{مكان_الانطلاق}}', 'label_ar' => 'نقطة الانطلاق'],
                    ['tag' => '{{وجهة_الوصول}}', 'label_ar' => 'وجهة الوصول'],
                    ['tag' => '{{موعد_الرحلة}}', 'label_ar' => 'موعد الرحلة'],
                    ['tag' => '{{المقابل_المادي}}', 'label_ar' => 'المقابل المادي'],
                ],
            ],
            [
                'key' => 'order_backup_notice',
                'title_ar' => 'إشعار تسجيل سائق احتياطي',
                'description_ar' => 'تُرسل للسائق عند قبوله بعد اكتمال المقاعد الأساسية ووضعه على قائمة الانتظار والاحتياط.',
                'default_text' => "أهلاً يا {{اسم_السائق}}، تم اكتمال المقاعد الأساسية للرحلة رقم {{رقم_الطلب}}، وتم إدراجك كسائق احتياطي رقم ({{رقم_الترتيب}}). في حال اعتذار أي سائق أساسي سيتم ترقيتك تلقائيًا وتنبيهك فورًا.",
                'variables' => [
                    ['tag' => '{{اسم_السائق}}', 'label_ar' => 'اسم السائق'],
                    ['tag' => '{{رقم_الطلب}}', 'label_ar' => 'كود أمر الشغل'],
                    ['tag' => '{{رقم_الترتيب}}', 'label_ar' => 'ترتيب السائق في الاحتياط (مثلاً: 1، 2)'],
                ],
            ],
            [
                'key' => 'order_filled',
                'title_ar' => 'اعتذار باكتمال العدد بالكامل (طلب متأخر)',
                'description_ar' => 'تُرسل للسائق إذا ضغط قبول بعد اكتمال المقاعد الأساسية والاحتياطية بالكامل.',
                'default_text' => "شكرًا لتجاوبك يا {{اسم_السائق}}. نود إعلامك باكتمال العدد المطلوب بالكامل للرحلة رقم {{رقم_الطلب}}. فرص أخرى مميزة ستصلك قريبًا!",
                'variables' => [
                    ['tag' => '{{اسم_السائق}}', 'label_ar' => 'اسم السائق'],
                    ['tag' => '{{رقم_الطلب}}', 'label_ar' => 'كود أمر الشغل'],
                ],
            ],
            [
                'key' => 'backup_promoted',
                'title_ar' => 'ترقية سائق احتياطي إلى أساسي',
                'description_ar' => 'تُرسل للسائق الاحتياطي فور ترقيته بعد اعتذار أحد السائقين الأساسيين مع أزرار التأكيد.',
                'default_text' => "ترقية رحلة هامة 🚀\nكابتن {{اسم_السائق}}، نظرًا لاعتذار أحد السائقين، تم ترقيتك الآن لتكون السائق الأساسي للرحلة رقم {{رقم_الطلب}} المتجهة إلى {{وجهة_الوصول}} في موعد {{موعد_الرحلة}}.\nبرجاء تأكيد جاهزيتك للتحرك فورًا.",
                'variables' => [
                    ['tag' => '{{اسم_السائق}}', 'label_ar' => 'اسم السائق'],
                    ['tag' => '{{رقم_الطلب}}', 'label_ar' => 'كود أمر الشغل'],
                    ['tag' => '{{وجهة_الوصول}}', 'label_ar' => 'وجهة الوصول'],
                    ['tag' => '{{موعد_الرحلة}}', 'label_ar' => 'موعد الرحلة'],
                ],
            ],
            [
                'key' => 'trip_cancellation',
                'title_ar' => 'إشعار إلغاء الرحلة',
                'description_ar' => 'تُرسل لجميع السائقين المؤكدين والاحتياط عند قيام المشرف بإلغاء أمر الشغل.',
                'default_text' => "نأسف لإبلاغك يا {{اسم_السائق}} بإلغاء الرحلة رقم {{رقم_الطلب}} المقررة في {{موعد_الرحلة}}.\nالسبب: {{سبب_الإلغاء}}.\nشكرًا لتفهمك.",
                'variables' => [
                    ['tag' => '{{اسم_السائق}}', 'label_ar' => 'اسم السائق'],
                    ['tag' => '{{رقم_الطلب}}', 'label_ar' => 'كود أمر الشغل'],
                    ['tag' => '{{موعد_الرحلة}}', 'label_ar' => 'موعد الرحلة'],
                    ['tag' => '{{سبب_الإلغاء}}', 'label_ar' => 'سبب الإلغاء المدخل'],
                ],
            ],
        ];

        return array_map(function ($tpl) {
            $settingVal = Setting::get('msg_tpl_' . $tpl['key']);
            $tpl['current_text'] = ! empty($settingVal) ? (string) $settingVal : $tpl['default_text'];
            return $tpl;
        }, $definitions);
    }

    /**
     * Render a template by key with provided replacement data.
     *
     * @param  string  $key
     * @param  array<string, mixed>  $replacements
     */
    public function render(string $key, array $replacements = []): string
    {
        $settingVal = Setting::get('msg_tpl_' . $key);

        if (empty($settingVal)) {
            // Find default
            $templates = $this->getTemplates();
            foreach ($templates as $t) {
                if ($t['key'] === $key) {
                    $settingVal = $t['default_text'];
                    break;
                }
            }
        }

        $text = (string) ($settingVal ?: '');

        // Standardize Arabic and English tags
        $mappedReplacements = [];
        foreach ($replacements as $tag => $val) {
            $cleanTag = trim($tag, '{} ');
            $valStr = (string) $val;
            $mappedReplacements['{{' . $cleanTag . '}}'] = $valStr;
            $mappedReplacements['{{ ' . $cleanTag . ' }}'] = $valStr;
        }

        return str_replace(array_keys($mappedReplacements), array_values($mappedReplacements), $text);
    }

    /**
     * Save updated templates to settings table.
     *
     * @param  array<string, string>  $templatesMap
     */
    public function saveTemplates(array $templatesMap): void
    {
        foreach ($templatesMap as $key => $text) {
            if (is_string($text)) {
                Setting::set('msg_tpl_' . $key, trim($text), 'string', "صيغة رسالة واتساب: {$key}");
            }
        }
    }

    /**
     * Get canned quick replies for live chat.
     *
     * @return array<int, array{id: string, title: string, text: string}>
     */
    public function getQuickReplies(): array
    {
        $saved = Setting::get('msg_quick_replies');
        if (is_array($saved) && count($saved) > 0) {
            return $saved;
        }

        return [
            [
                'id' => 'location',
                'title' => '📍 لوكيشن المستودع',
                'text' => "📍 تفضل رابط موقع المستودع على خرائط جوجل: https://maps.google.com/?q=30.0444,31.2357\nيرجى التوجه للبوابة رقم 3.",
            ],
            [
                'id' => 'arrival_time',
                'title' => '⏰ موعد الحضور',
                'text' => "⏰ يرجى التواجد بموقع التحميل قبل موعد الرحلة بنصف ساعة لإتمام إجراءات التعتيق وتفقد البضاعة.",
            ],
            [
                'id' => 'payment_info',
                'title' => '💵 تفاصيل الأجرة',
                'text' => "💵 يتم تحويل الأجرة المتفق عليها بالكامل عبر فودافون كاش أو إنستاباي فور تسليم بوليصة الشحن واستلام إيصال العميل.",
            ],
            [
                'id' => 'contact_supervisor',
                'title' => '📞 تليفون المشرف',
                'text' => "📞 للتواصل المباشر مع مشرف التحميل الميداني: أ/ كابتن العمليات (01000000000).",
            ],
        ];
    }

    /**
     * Save quick replies to settings table.
     *
     * @param  array<int, array{id: string, title: string, text: string}>  $replies
     */
    public function saveQuickReplies(array $replies): void
    {
        Setting::set('msg_quick_replies', $replies, 'json', 'الردود السريعة الجاهزة في محادثات الواتساب');
    }
}
