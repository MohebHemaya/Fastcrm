<?php

use App\Models\QuestionOption;
use App\Services\ArabicReplyNormalizer;

beforeEach(function () {
    $this->normalizer = new ArabicReplyNormalizer;
});

test('ArabicReplyNormalizer correctly normalizes text and removes diacritics', function (string $input, string $expected) {
    expect($this->normalizer->normalize($input))->toBe($expected);
})->with([
    ['مُهْتَمٌّ', 'مهتم'],
    ['إِيْقَافٌ', 'ايقاف'],
    ['أَهْلاً وَسَهْلاً', 'اهلا وسهلا'],
    ['آهٍ', 'اه'],
    ['نَعَمْ', 'نعم'],
    ['لَا', 'لا'],
    ['سَيَّارَةٌ', 'سياره'],
    ['شَاحِنَةٌ', 'شاحنه'],
    ['إلغاء', 'الغاء'],
    ['  تَمَامْ   ', 'تمام'],
    ['عَرَبِيَّةٌ', 'عربيه'],
    ['مَصْرِيٌّ', 'مصري'],
    ['سَنَتَيْنِ', 'سنتين'],
    ['أَكِيدْ', 'اكيد'],
    ['مُوافِقْ', 'موافق'],
]);

test('ArabicReplyNormalizer converts Eastern Arabic and Persian digits to ASCII', function (string $input, string $expected) {
    expect($this->normalizer->convertArabicDigits($input))->toBe($expected);
})->with([
    ['٠١٢٣٤٥٦٧٨٩', '0123456789'],
    ['۰۱۲۳۴۵۶۷۸۹', '0123456789'],
    ['٥ سنوات', '5 سنوات'],
    ['خبرة ١٠ سنين', 'خبرة 10 سنين'],
    ['١٢.٥', '12.5'],
    ['رقم ٧', 'رقم 7'],
]);

test('ArabicReplyNormalizer extracts numeric values properly', function (?string $input, ?float $expected) {
    expect($this->normalizer->extractNumber($input))->toBe($expected);
})->with([
    ['5', 5.0],
    ['٥', 5.0],
    ['١٢', 12.0],
    ['3.5', 3.5],
    ['٣,٥', 3.5],
    ['عندي 10 سنين خبرة', 10.0],
    ['حوالي ٧ سنوات', 7.0],
    ['لا يوجد', null],
    [null, null],
    ['', null],
]);

test('ArabicReplyNormalizer detects affirmative / yes variants (30+ table variants)', function (string $input, bool $expected) {
    expect($this->normalizer->isYes($input))->toBe($expected);
})->with([
    ['نعم', true],
    ['نَعَمْ', true],
    ['ايوه', true],
    ['أيوة', true],
    ['ايوة', true],
    ['اه', true],
    ['آه', true],
    ['اهه', true],
    ['تمام', true],
    ['تَمَامْ', true],
    ['موافق', true],
    ['مُوافِقْ', true],
    ['اكيد', true],
    ['أَكِيدْ', true],
    ['طبعا', true],
    ['طَبْعاً', true],
    ['حاضر', true],
    ['حَاضِرْ', true],
    ['اوكي', true],
    ['اوك', true],
    ['yes', true],
    ['YES', true],
    ['y', true],
    ['1', true],
    ['نعم مهتم', true],
    ['مهتم جدا', true],
    ['تمام يا فندم', true],
    ['ايوه مهتم', true],
    ['اكيد طبعا', true],
    ['موافق جدا', true],
    // Negative cases
    ['لا', false],
    ['مش عايز', false],
    ['لا شكرا', false],
    ['مش مناسب', false],
    ['no', false],
    ['2', false],
]);

test('ArabicReplyNormalizer detects negative / no variants (15+ table variants)', function (string $input, bool $expected) {
    expect($this->normalizer->isNo($input))->toBe($expected);
})->with([
    ['لا', true],
    ['لَا', true],
    ['لأ', true],
    ['لاء', true],
    ['مش عايز', true],
    ['مش موافق', true],
    ['غير موافق', true],
    ['لا شكرا', true],
    ['مش هقدر', true],
    ['مش مناسب', true],
    ['مش حابب', true],
    ['غير مهتم', true],
    ['no', true],
    ['NO', true],
    ['n', true],
    ['2', true],
    ['لا مش مهتم', true],
    // Negative cases
    ['نعم', false],
    ['ايوه', false],
    ['تمام', false],
    ['yes', false],
    ['1', false],
]);

test('ArabicReplyNormalizer matches options correctly', function () {
    $options = [
        (new QuestionOption)->forceFill(['id' => 1, 'value' => 'pickup', 'label_ar' => 'ربع نقل']),
        (new QuestionOption)->forceFill(['id' => 2, 'value' => 'jumbo', 'label_ar' => 'جامبو']),
        (new QuestionOption)->forceFill(['id' => 3, 'value' => 'truck', 'label_ar' => 'تريلا']),
    ];

    expect($this->normalizer->matchOption('ربع نقل', $options)?->id)->toBe(1);
    expect($this->normalizer->matchOption('رَبْعُ نَقْلٍ', $options)?->id)->toBe(1);
    expect($this->normalizer->matchOption('pickup', $options)?->id)->toBe(1);
    expect($this->normalizer->matchOption('معايا ربع نقل', $options)?->id)->toBe(1);
    expect($this->normalizer->matchOption('جامبو', $options)?->id)->toBe(2);
    expect($this->normalizer->matchOption('جَامْبُو', $options)?->id)->toBe(2);
    expect($this->normalizer->matchOption('1', $options)?->id)->toBe(1);
    expect($this->normalizer->matchOption('2', $options)?->id)->toBe(2);
    expect($this->normalizer->matchOption('3', $options)?->id)->toBe(3);
    expect($this->normalizer->matchOption('طيارة', $options))->toBeNull();
});

test('ArabicReplyNormalizer handles look-alike options and ambiguous replies without guessing', function () {
    // 1. "نقل ثقيل" vs "نقل خفيف"
    $truckOptions = [
        (new QuestionOption)->forceFill(['id' => 10, 'value' => 'heavy', 'label_ar' => 'نقل ثقيل']),
        (new QuestionOption)->forceFill(['id' => 11, 'value' => 'light', 'label_ar' => 'نقل خفيف']),
    ];

    // "نقل" is ambiguous (equally close to both) -> must NOT guess, returns null
    expect($this->normalizer->matchOption('نقل', $truckOptions))->toBeNull();
    // "ثقيل" and "خفيف" are unambiguous -> accepted
    expect($this->normalizer->matchOption('ثقيل', $truckOptions)?->id)->toBe(10);
    expect($this->normalizer->matchOption('خفيف', $truckOptions)?->id)->toBe(11);

    // 2. "جيزة" vs "الجيزة الجديدة"
    $gizaOptions = [
        (new QuestionOption)->forceFill(['id' => 20, 'value' => 'giza', 'label_ar' => 'جيزة']),
        (new QuestionOption)->forceFill(['id' => 21, 'value' => 'new_giza', 'label_ar' => 'الجيزة الجديدة']),
    ];

    expect($this->normalizer->matchOption('جيزة', $gizaOptions)?->id)->toBe(20);
    expect($this->normalizer->matchOption('الجيزة الجديدة', $gizaOptions)?->id)->toBe(21);
    expect($this->normalizer->matchOption('الجديدة', $gizaOptions)?->id)->toBe(21);

    // 3. "ربع نقل" vs "نص نقل"
    $pickupOptions = [
        (new QuestionOption)->forceFill(['id' => 30, 'value' => 'quarter', 'label_ar' => 'ربع نقل']),
        (new QuestionOption)->forceFill(['id' => 31, 'value' => 'half', 'label_ar' => 'نص نقل']),
    ];

    // "نقل" is ambiguous -> returns null
    expect($this->normalizer->matchOption('نقل', $pickupOptions))->toBeNull();
    // "ربع" and "نص" are unambiguous -> accepted
    expect($this->normalizer->matchOption('ربع', $pickupOptions)?->id)->toBe(30);
    expect($this->normalizer->matchOption('نص', $pickupOptions)?->id)->toBe(31);
});

test('ArabicReplyNormalizer handles misspellings and low-confidence replies explicitly', function () {
    $options = [
        (new QuestionOption)->forceFill(['id' => 10, 'value' => 'heavy', 'label_ar' => 'نقل ثقيل']),
        (new QuestionOption)->forceFill(['id' => 11, 'value' => 'light', 'label_ar' => 'نقل خفيف']),
    ];

    // "تقيل" alone has misspelling (ت instead of ث) and lacks "نقل", below minimum confidence -> returns null
    expect($this->normalizer->matchOption('تقيل', $options))->toBeNull();

    // "نقل تقيل" has sufficient word & character confidence (>80%) and unambiguous delta (>15%) -> matches 10
    expect($this->normalizer->matchOption('نقل تقيل', $options)?->id)->toBe(10);
});
