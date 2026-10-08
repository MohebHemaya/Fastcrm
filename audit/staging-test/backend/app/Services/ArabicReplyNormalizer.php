<?php

namespace App\Services;

use App\Models\QuestionOption;
use Illuminate\Support\Collection;

class ArabicReplyNormalizer
{
    /**
     * Map of Eastern Arabic (and Persian) numerals to Western Arabic numerals.
     */
    protected static array $easternDigits = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /**
     * Standard normalized yes keywords.
     */
    protected static array $yesKeywords = [
        'ايوه', 'ايوة', 'اه', 'اهه', 'نعم', 'تمام', 'موافق', 'اكيد',
        'اوكي', 'اوك', 'طبعا', 'حاضر', 'yes', 'y', '1', 'نعم مهتم', 'مهتم',
    ];

    /**
     * Standard normalized no keywords.
     */
    protected static array $noKeywords = [
        'لا', 'لأ', 'لاء', 'مش عايز', 'مش موافق', 'غير موافق',
        'لا شكرا', 'مش هقدر', 'مش مناسب', 'مش حابب', 'no', 'n', '2', 'غير مهتم',
    ];

    /**
     * Clean and normalize Arabic text (strip diacritics, standardize Alef, Teh Marbuta, Yeh).
     */
    public function normalize(string $text): string
    {
        $str = mb_strtolower(trim($text), 'UTF-8');

        // Remove Arabic diacritics (tashkeel & tatweel)
        $str = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $str) ?? $str;

        // Normalize Alef forms (أ, إ, آ, ٱ -> ا)
        $str = preg_replace('/[إأآٱ]/u', 'ا', $str) ?? $str;

        // Normalize Teh Marbuta (ة -> ه)
        $str = preg_replace('/ة/u', 'ه', $str) ?? $str;

        // Normalize Alef Maksura (ى -> ي)
        $str = preg_replace('/ى/u', 'ي', $str) ?? $str;

        // Normalize Eastern digits to ASCII digits
        $str = strtr($str, self::$easternDigits);

        // Remove punctuation and special characters
        $str = preg_replace('/[^\p{L}\p{N}\s]/u', '', $str) ?? $str;

        // Collapse multiple spaces
        return trim(preg_replace('/\s+/', ' ', $str) ?? $str);
    }

    /**
     * Convert Arabic/Persian digits in string to ASCII digits.
     */
    public function convertArabicDigits(string $text): string
    {
        return strtr($text, self::$easternDigits);
    }

    /**
     * Extract a numeric value from string (supporting Arabic numerals, decimal points, negative).
     */
    public function extractNumber(?string $text): ?float
    {
        if ($text === null) {
            return null;
        }

        $converted = $this->convertArabicDigits(trim($text));

        // Find numbers with optional decimal point (support comma or dot)
        $clean = str_replace(',', '.', $converted);
        if (preg_match('/-?\d+(\.\d+)?/', $clean, $matches)) {
            return (float) $matches[0];
        }

        return null;
    }

    /**
     * Check if the response indicates affirmative / yes.
     */
    public function isYes(string $text): bool
    {
        $norm = $this->normalize($text);

        if (in_array($norm, self::$yesKeywords, true)) {
            return true;
        }

        foreach (self::$yesKeywords as $kw) {
            if ($norm === $kw || str_starts_with($norm, $kw.' ') || str_ends_with($norm, ' '.$kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the response indicates negative / no.
     */
    public function isNo(string $text): bool
    {
        $norm = $this->normalize($text);

        if (in_array($norm, self::$noKeywords, true)) {
            return true;
        }

        foreach (self::$noKeywords as $kw) {
            if ($norm === $kw || str_starts_with($norm, $kw.' ') || str_ends_with($norm, ' '.$kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Minimum confidence score (0-100) required to consider a fuzzy option match.
     */
    public const FUZZY_MIN_SCORE = 80.0;

    /**
     * Minimum score difference between best and second-best candidate to be considered unambiguous.
     */
    public const FUZZY_MIN_DELTA = 15.0;

    /**
     * Match a user's free text response against a question's options.
     * Only returns an option if the match is exact or high confidence AND unambiguous.
     *
     * @param  Collection<int, QuestionOption>|array<QuestionOption>  $options
     */
    public function matchOption(string $reply, iterable $options): ?QuestionOption
    {
        $normalizedReply = $this->normalize($reply);
        if ($normalizedReply === '') {
            return null;
        }

        $optionsList = is_array($options) ? array_values($options) : $options->values()->all();
        if (empty($optionsList)) {
            return null;
        }

        // 1. Direct exact match on option value or normalized label
        $exactMatches = [];
        foreach ($optionsList as $option) {
            $val = mb_strtolower(trim($option->value));
            $normLabel = $this->normalize($option->label_ar);
            if ($val === $normalizedReply || $normLabel === $normalizedReply) {
                $exactMatches[] = $option;
            }
        }

        if (count($exactMatches) === 1) {
            return $exactMatches[0];
        }
        if (count($exactMatches) > 1) {
            return null; // Ambiguous: multiple options match exactly
        }

        // 2. Match by 1-based index (e.g. user typed "1" for option 1)
        if (ctype_digit($normalizedReply)) {
            $idx = ((int) $normalizedReply) - 1;
            if (isset($optionsList[$idx])) {
                return $optionsList[$idx];
            }
        }

        // 3. Compute fuzzy similarity scores for all options
        $scored = [];
        foreach ($optionsList as $option) {
            $normLabel = $this->normalize($option->label_ar);
            $score = $this->calculateSimilarityScore($normalizedReply, $normLabel);
            $scored[] = [
                'option' => $option,
                'score' => $score,
            ];
        }

        // Sort descending by score
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $best = $scored[0];
        $secondBest = $scored[1] ?? null;

        // Check minimum confidence threshold
        if ($best['score'] < self::FUZZY_MIN_SCORE) {
            return null; // Low confidence
        }

        // Check ambiguity threshold against second best candidate
        if ($secondBest !== null) {
            $delta = $best['score'] - $secondBest['score'];
            if ($delta < self::FUZZY_MIN_DELTA) {
                return null; // Ambiguous: candidates are too close
            }
        }

        return $best['option'];
    }

    /**
     * Calculate similarity score (0.0 to 100.0) between normalized reply and option label.
     */
    public function calculateSimilarityScore(string $reply, string $label): float
    {
        if ($reply === '' || $label === '') {
            return 0.0;
        }

        if ($reply === $label) {
            return 100.0;
        }

        $replyWords = array_values(array_filter(explode(' ', $reply)));
        $labelWords = array_values(array_filter(explode(' ', $label)));

        if (empty($replyWords) || empty($labelWords)) {
            return 0.0;
        }

        $matchedReplyWords = 0;
        $replyWordSimilarities = [];
        foreach ($replyWords as $rw) {
            $maxWordSim = 0.0;
            foreach ($labelWords as $lw) {
                if ($rw === $lw) {
                    $sim = 1.0;
                } else {
                    similar_text($rw, $lw, $pct);
                    $sim = $pct / 100.0;
                }
                if ($sim > $maxWordSim) {
                    $maxWordSim = $sim;
                }
            }
            $replyWordSimilarities[] = $maxWordSim;
            if ($maxWordSim >= 0.75) {
                $matchedReplyWords++;
            }
        }

        $matchedLabelWords = 0;
        $labelWordSimilarities = [];
        foreach ($labelWords as $lw) {
            $maxWordSim = 0.0;
            foreach ($replyWords as $rw) {
                if ($lw === $rw) {
                    $sim = 1.0;
                } else {
                    similar_text($lw, $rw, $pct);
                    $sim = $pct / 100.0;
                }
                if ($sim > $maxWordSim) {
                    $maxWordSim = $sim;
                }
            }
            $labelWordSimilarities[] = $maxWordSim;
            if ($maxWordSim >= 0.75) {
                $matchedLabelWords++;
            }
        }

        $avgReplySim = array_sum($replyWordSimilarities) / count($replyWords);
        $avgLabelSim = array_sum($labelWordSimilarities) / count($labelWords);

        // Exact word subset boost (e.g. user said "ثقيل" for "نقل ثقيل", or "ربع" for "ربع نقل")
        if ($matchedReplyWords === count($replyWords) && $avgReplySim >= 0.99) {
            return 80.0 + 20.0 * $avgLabelSim;
        }

        // All label words present in user reply (e.g. "معايا ربع نقل" for "ربع نقل")
        if ($matchedLabelWords === count($labelWords)) {
            return 85.0 + 15.0 * $avgReplySim;
        }

        return ($avgLabelSim * 0.6 + $avgReplySim * 0.4) * 100.0;
    }
}
