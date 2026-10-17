<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class WhatsAppTemplateService
{
    /**
     * Get all customizable WhatsApp message templates with their metadata.
     * Cached forever until invalidated by saveTemplates().
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
        return Cache::rememberForever('msg_all_templates', function () {
            $definitions = $this->getDefaultDefinitions();

            return array_map(function ($tpl) {
                $settingVal = Setting::get('msg_tpl_'.$tpl['key']);
                $tpl['current_text'] = ! empty($settingVal) ? (string) $settingVal : $tpl['default_text'];

                return $tpl;
            }, $definitions);
        });
    }

    /**
     * Standard built-in default template definitions.
     *
     * @return array<int, array{
     *     key: string,
     *     title_ar: string,
     *     description_ar: string,
     *     default_text: string,
     *     variables: array<int, array{tag: string, label_ar: string}>
     * }>
     */
    public function getDefaultDefinitions(): array
    {
        return [
            [
                'key' => 'driver_invitation',
                'title_ar' => 'رسالة دعوة السائق (الترحيب بالأسطول)',
                'description_ar' => 'تُرسل تلقائيًا عند دعوة سائق جديد للاشتراك في شبكة النقل السريع وبدء استبيان البيانات.',
                'default_text' => 'أهلاً بك يا {{اسم_السائق|كابتن}}! معك شركة النقل السريع. نتشرف بدعوتك للانضمام لشبكة السائقين لدينا وتلقي رحلات مميزة. هل أنت مهتم بالانضمام إلينا؟ (للإلغاء أرسل: إيقاف)',
                'variables' => [
                    ['tag' => '{{اسم_السائق}}', 'label_ar' => 'اسم السائق المسجل'],
                ],
            ],
            [
                'key' => 'order_offer',
                'title_ar' => 'صيغة عرض الرحلة الافتراضية (أمر الشغل)',
                'description_ar' => 'الصيغة الأساسية لعروض الرحلات المرسلة للسائقين (يمكن تعديلها لكل أمر شغل على حدة).',
                'default_text' => "السلام عليكم كابتن {{اسم_السائق|كابتن}}، متاح لدينا أمر شغل جديد رقم {{رقم_الطلب}}:\n- خط السير: من {{مكان_الانطلاق}} إلى {{وجهة_الوصول}}\n- الموعد: {{موعد_الرحلة}}\n- المقابل: {{المقابل_المادي}}\n- الحمولة: {{نوع_الحمولة}}\n{{#الملاحظات}}- ملاحظات: {{الملاحظات}}\n{{/الملاحظات}}برجاء تأكيد رغبتك بالقبول أو الاعتذار.",
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
                'default_text' => "مبروك يا {{اسم_السائق|كابتن}}! تم تأكيد تكليفك بالرحلة رقم {{رقم_الطلب}}.\n- خط السير: من {{مكان_الانطلاق}} إلى {{وجهة_الوصول}}\n- الموعد: {{موعد_الرحلة}}\n- المقابل: {{المقابل_المادي}}\nنتمنى لك رحلة موفقة!",
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
                'default_text' => 'أهلاً يا {{اسم_السائق|كابتن}}، تم اكتمال المقاعد الأساسية للرحلة رقم {{رقم_الطلب}}، وتم إدراجك كسائق احتياطي رقم ({{رقم_الترتيب}}). في حال اعتذار أي سائق أساسي سيتم ترقيتك تلقائيًا وتنبيهك فورًا.',
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
                'default_text' => 'شكرًا لتجاوبك يا {{اسم_السائق|كابتن}}. نود إعلامك باكتمال العدد المطلوب بالكامل للرحلة رقم {{رقم_الطلب}}. فرص أخرى مميزة ستصلك قريبًا!',
                'variables' => [
                    ['tag' => '{{اسم_السائق}}', 'label_ar' => 'اسم السائق'],
                    ['tag' => '{{رقم_الطلب}}', 'label_ar' => 'كود أمر الشغل'],
                ],
            ],
            [
                'key' => 'backup_promoted',
                'title_ar' => 'ترقية سائق احتياطي إلى أساسي',
                'description_ar' => 'تُرسل للسائق الاحتياطي فور ترقيته بعد اعتذار أحد السائقين الأساسيين مع أزرار التأكيد.',
                'default_text' => "ترقية رحلة هامة 🚀\nكابتن {{اسم_السائق|كابتن}}، نظرًا لاعتذار أحد السائقين، تم ترقيتك الآن لتكون السائق الأساسي للرحلة رقم {{رقم_الطلب}} المتجهة إلى {{وجهة_الوصول}} في موعد {{موعد_الرحلة}}.\nبرجاء تأكيد جاهزيتك للتحرك فورًا.",
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
                'default_text' => "نأسف لإبلاغك يا {{اسم_السائق|كابتن}} بإلغاء الرحلة رقم {{رقم_الطلب}} المقررة في {{موعد_الرحلة}}.\nالسبب: {{سبب_الإلغاء}}.\nشكرًا لتفهمك.",
                'variables' => [
                    ['tag' => '{{اسم_السائق}}', 'label_ar' => 'اسم السائق'],
                    ['tag' => '{{رقم_الطلب}}', 'label_ar' => 'كود أمر الشغل'],
                    ['tag' => '{{موعد_الرحلة}}', 'label_ar' => 'موعد الرحلة'],
                    ['tag' => '{{سبب_الإلغاء}}', 'label_ar' => 'سبب الإلغاء المدخل'],
                ],
            ],
            [
                'key' => 'trip_reminder',
                'title_ar' => 'تذكير السائق بموعد الرحلة',
                'description_ar' => 'تُرسل تلقائيًا للسائقين المؤكدين قبل موعد انطلاق الرحلة لتأكيد الجاهزية ونقطة التجمع.',
                'default_text' => "تذكير بموعد رحلتك غدًا 🚗\n- كابتن: {{اسم_السائق|كابتن}}\n- الرحلة رقم: {{رقم_الطلب}}\n- الموعد: {{موعد_الرحلة}}\n- نقطة التجمع: {{مكان_الانطلاق}}\nنرجو التأكد من جاهزية المركبة ونظافتها والوصول قبل الموعد بـ 15 دقيقة على الأقل. نتمنى لك رحلة آمنة.",
                'variables' => [
                    ['tag' => '{{اسم_السائق}}', 'label_ar' => 'اسم السائق'],
                    ['tag' => '{{رقم_الطلب}}', 'label_ar' => 'كود أمر الشغل'],
                    ['tag' => '{{موعد_الرحلة}}', 'label_ar' => 'موعد الرحلة'],
                    ['tag' => '{{مكان_الانطلاق}}', 'label_ar' => 'نقطة التجمع والانطلاق'],
                ],
            ],
        ];
    }

    /**
     * Get the default text for a given template key.
     */
    public function getDefaultTemplateText(string $key): string
    {
        foreach ($this->getDefaultDefinitions() as $tpl) {
            if ($tpl['key'] === $key) {
                return $tpl['default_text'];
            }
        }

        return '';
    }

    /**
     * Render a template by key with provided replacement data, using cache.
     *
     * @param  array<string, mixed>  $replacements
     */
    public function render(string $key, array $replacements = []): string
    {
        $text = Cache::rememberForever('msg_tpl_'.$key, function () use ($key) {
            $settingVal = Setting::get('msg_tpl_'.$key);
            if (! empty($settingVal)) {
                return (string) $settingVal;
            }

            return $this->getDefaultTemplateText($key);
        });

        return $this->renderRaw((string) $text, $replacements);
    }

    /**
     * Render raw template text with smart interpolation, conditional blocks, and fallbacks.
     *
     * @param  array<string, mixed>  $replacements
     */
    public function renderRaw(string $templateText, array $replacements = []): string
    {
        $text = $templateText;

        // 1. Standardize replacements array with normalized keys
        $normalizedData = [];
        foreach ($replacements as $key => $val) {
            $cleanKey = trim((string) $key, '{} ');
            $valStr = (string) $val;
            $normalizedData[$cleanKey] = $valStr;

            if ($cleanKey === '0' || $cleanKey === '1' || $cleanKey === 'driver_name' || $cleanKey === 'اسم_السائق') {
                $normalizedData['اسم_السائق'] = $valStr;
                $normalizedData['driver_name'] = $valStr;
            } elseif ($cleanKey === 'order_code' || $cleanKey === 'code' || $cleanKey === 'رقم_الطلب') {
                $normalizedData['رقم_الطلب'] = $valStr;
                $normalizedData['order_code'] = $valStr;
            } elseif ($cleanKey === 'from_location' || $cleanKey === 'مكان_الانطلاق') {
                $normalizedData['مكان_الانطلاق'] = $valStr;
                $normalizedData['from_location'] = $valStr;
            } elseif ($cleanKey === 'to_location' || $cleanKey === 'وجهة_الوصول') {
                $normalizedData['وجهة_الوصول'] = $valStr;
                $normalizedData['to_location'] = $valStr;
            } elseif ($cleanKey === 'scheduled_at' || $cleanKey === 'موعد_الرحلة') {
                $normalizedData['موعد_الرحلة'] = $valStr;
                $normalizedData['scheduled_at'] = $valStr;
            } elseif ($cleanKey === 'pay_amount' || $cleanKey === 'المقابل_المادي') {
                $normalizedData['المقابل_المادي'] = $valStr;
                $normalizedData['pay_amount'] = $valStr;
            } elseif ($cleanKey === 'cargo_type' || $cleanKey === 'نوع_الحمولة') {
                $normalizedData['نوع_الحمولة'] = $valStr;
                $normalizedData['cargo_type'] = $valStr;
            } elseif ($cleanKey === 'notes' || $cleanKey === 'الملاحظات') {
                $normalizedData['الملاحظات'] = $valStr;
                $normalizedData['notes'] = $valStr;
            }
        }

        // 2. Handle conditional blocks: {{#tag}}...{{/tag}}
        $text = (string) preg_replace_callback('/\{\{#\s*([^\}]+?)\s*\}\}(.*?)\{\{\/\s*\1\s*\}\}/su', function ($matches) use ($normalizedData) {
            $tag = trim($matches[1]);
            $innerContent = $matches[2];
            $val = trim($normalizedData[$tag] ?? '');

            if ($val === '') {
                return '';
            }

            return $innerContent;
        }, $text);

        // 3. Handle variable fallbacks: {{ tag | fallback }} or {{ tag ?? fallback }}
        $text = (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\x{0600}-\x{06FF}]+)\s*(?:\||\?\?)\s*([^}]+?)\s*\}\}/u', function ($matches) use ($normalizedData) {
            $tag = trim($matches[1]);
            $fallback = trim($matches[2]);
            $val = trim($normalizedData[$tag] ?? '');

            return $val !== '' ? $val : $fallback;
        }, $text);

        // 4. Built-in smart fallbacks for standard tags when value is missing or empty
        $builtInDefaults = [
            'اسم_السائق' => 'كابتن',
            'driver_name' => 'كابتن',
            'المقابل_المادي' => 'حسب الاتفاق',
            'pay_amount' => 'حسب الاتفاق',
            'نوع_الحمولة' => 'عامة',
            'cargo_type' => 'عامة',
            'موعد_الرحلة' => 'حسب الاتفاق',
            'scheduled_at' => 'حسب الاتفاق',
            'مكان_الانطلاق' => 'حسب الاتفاق',
            'from_location' => 'حسب الاتفاق',
            'وجهة_الوصول' => 'حسب الاتفاق',
            'to_location' => 'حسب الاتفاق',
        ];

        // 5. Direct variable replacement: {{ tag }}
        $text = (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\x{0600}-\x{06FF}]+)\s*\}\}/u', function ($matches) use ($normalizedData, $builtInDefaults) {
            $tag = trim($matches[1]);
            if (isset($normalizedData[$tag]) && trim($normalizedData[$tag]) !== '') {
                return $normalizedData[$tag];
            }

            if ($tag === 'الملاحظات' || $tag === 'notes') {
                return '';
            }

            return $builtInDefaults[$tag] ?? '';
        }, $text);

        // 6. Clean up empty notes prefix lines and excessive newlines
        $text = (string) preg_replace('/^[\s\-\*•]*ملاحظات[\s\:\-]*\r?\n/mu', '', $text);
        $text = (string) preg_replace('/\n{3,}/u', "\n\n", $text);

        return trim($text);
    }

    /**
     * Provide realistic sample data for testing and previewing a template.
     *
     * @return array<string, string>
     */
    public function getSampleDataForTemplate(string $key): array
    {
        return [
            'اسم_السائق' => 'أحمد محمود',
            'driver_name' => 'أحمد محمود',
            'رقم_الطلب' => 'WO-2401',
            'order_code' => 'WO-2401',
            'مكان_الانطلاق' => 'مستودع 6 أكتوبر',
            'from_location' => 'مستودع 6 أكتوبر',
            'وجهة_الوصول' => 'ميناء الإسكندرية',
            'to_location' => 'ميناء الإسكندرية',
            'موعد_الرحلة' => now()->addDay()->format('Y-m-d 08:00'),
            'scheduled_at' => now()->addDay()->format('Y-m-d 08:00'),
            'المقابل_المادي' => '1,850.00 ج.م',
            'pay_amount' => '1,850.00 ج.م',
            'نوع_الحمولة' => 'مواد غذائية مبردة',
            'cargo_type' => 'مواد غذائية مبردة',
            'الملاحظات' => 'يرجى إحضار تصريح الدخول والبطاقة الضريبية',
            'notes' => 'يرجى إحضار تصريح الدخول والبطاقة الضريبية',
            'عدد_الرحلات' => '3',
            'trips_needed' => '3',
            'رقم_الترتيب' => '1',
            'سبب_الإلغاء' => 'تأجيل موعد الشحن بناءً على رغبة العميل',
        ];
    }

    /**
     * Save updated templates to settings table and invalidate caches.
     *
     * @param  array<string, string>  $templatesMap
     */
    public function saveTemplates(array $templatesMap): void
    {
        foreach ($templatesMap as $key => $text) {
            if (is_string($text)) {
                Setting::set('msg_tpl_'.$key, trim($text), 'string', "صيغة رسالة واتساب: {$key}");
                Cache::forget('msg_tpl_'.$key);
            }
        }
        Cache::forget('msg_all_templates');
    }

    /**
     * Invalidate all template caches.
     */
    public function clearCache(): void
    {
        Cache::forget('msg_all_templates');
        foreach ($this->getDefaultDefinitions() as $tpl) {
            Cache::forget('msg_tpl_'.$tpl['key']);
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
                'text' => '⏰ يرجى التواجد بموقع التحميل قبل موعد الرحلة بنصف ساعة لإتمام إجراءات التعتيق وتفقد البضاعة.',
            ],
            [
                'id' => 'payment_info',
                'title' => '💵 تفاصيل الأجرة',
                'text' => '💵 يتم تحويل الأجرة المتفق عليها بالكامل عبر فودافون كاش أو إنستاباي فور تسليم بوليصة الشحن واستلام إيصال العميل.',
            ],
            [
                'id' => 'contact_supervisor',
                'title' => '📞 تليفون المشرف',
                'text' => '📞 للتواصل المباشر مع مشرف التحميل الميداني: أ/ كابتن العمليات (01000000000).',
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
