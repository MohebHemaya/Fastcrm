<?php

namespace Database\Seeders;

use App\Enums\QuestionType;
use App\Models\Question;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            [
                'key' => 'driver_name',
                'sort_order' => 1,
                'is_active' => true,
                'is_required' => true,
                'is_filter' => false,
                'type' => QuestionType::TEXT,
                'text_ar' => 'ما هو اسمك بالكامل يا كابتن؟',
                'options' => [],
            ],
            [
                'key' => 'vehicle_type',
                'sort_order' => 2,
                'is_active' => true,
                'is_required' => true,
                'is_filter' => true,
                'type' => QuestionType::SINGLE_CHOICE,
                'text_ar' => 'ما هو نوع سيارتك؟',
                'options' => [
                    ['value' => 'pickup', 'label_ar' => 'بيك أب / ربع نقل', 'sort_order' => 1],
                    ['value' => 'jumbo', 'label_ar' => 'جامبو / نص نقل', 'sort_order' => 2],
                    ['value' => 'van', 'label_ar' => 'فان مغلق', 'sort_order' => 3],
                    ['value' => 'truck', 'label_ar' => 'نقل ثقيل / تريلا', 'sort_order' => 4],
                ],
            ],
            [
                'key' => 'has_refrigeration',
                'sort_order' => 3,
                'is_active' => true,
                'is_required' => true,
                'is_filter' => true,
                'type' => QuestionType::YES_NO,
                'text_ar' => 'هل السيارة مجهزة بصندوق تبريد / تجميد؟',
                'options' => [],
            ],
            [
                'key' => 'years_experience',
                'sort_order' => 4,
                'is_active' => true,
                'is_required' => true,
                'is_filter' => false,
                'type' => QuestionType::NUMBER,
                'text_ar' => 'كم سنة خبرتك في مجال النقل والشحن؟ (أرسل رقم فقط)',
                'options' => [],
            ],
            [
                'key' => 'daily_availability',
                'sort_order' => 5,
                'is_active' => true,
                'is_required' => true,
                'is_filter' => true,
                'type' => QuestionType::SINGLE_CHOICE,
                'text_ar' => 'ما هي الفترة المفضلة للعمل؟',
                'options' => [
                    ['value' => 'morning', 'label_ar' => 'صباحي', 'sort_order' => 1],
                    ['value' => 'evening', 'label_ar' => 'مسائي', 'sort_order' => 2],
                    ['value' => 'any', 'label_ar' => 'في أي وقت', 'sort_order' => 3],
                ],
            ],
            [
                'key' => 'preferred_routes',
                'sort_order' => 6,
                'is_active' => true,
                'is_required' => false,
                'is_filter' => false,
                'type' => QuestionType::TEXT,
                'text_ar' => 'ما هي المحافظات أو المناطق التي تفضل العمل بها؟ (أرسل نصاً حراً)',
                'options' => [],
            ],
        ];

        foreach ($questions as $q) {
            $question = Question::updateOrCreate(
                ['key' => $q['key']],
                [
                    'sort_order' => $q['sort_order'],
                    'is_active' => $q['is_active'],
                    'is_required' => $q['is_required'],
                    'is_filter' => $q['is_filter'],
                    'type' => $q['type'],
                ]
            );

            if (! $question->current_version_id) {
                $question->recordVersion($q['text_ar'], $q['type'], $q['options']);
            }
        }
    }
}
