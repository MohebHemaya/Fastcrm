<?php

namespace App\Services;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

class PdfService
{
    /**
     * Create and configure an mPDF instance with Arabic Amiri font and RTL support.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function createMpdf(array $overrides = []): Mpdf
    {
        $defaultConfig = (new ConfigVariables)->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new FontVariables)->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $customFontDir = storage_path('fonts');
        $tempDir = storage_path('app/mpdf_temp');

        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $config = array_merge([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_header' => 8,
            'margin_footer' => 8,
            'fontDir' => array_merge($fontDirs, [$customFontDir]),
            'fontdata' => $fontData + [
                'amiri' => [
                    'R' => 'Amiri-Regular.ttf',
                    'B' => 'Amiri-Bold.ttf',
                ],
            ],
            'default_font' => 'amiri',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ], $overrides);

        $mpdf = new Mpdf($config);
        $mpdf->SetDirectionality('rtl');

        return $mpdf;
    }

    /**
     * Helper to wrap phone numbers, order codes, or English alphanumeric strings
     * in an LTR isolation run inside RTL text to avoid BiDi reversal.
     */
    public static function ltr(string $text): string
    {
        return '<span dir="ltr" style="unicode-bidi: embed; display: inline-block;">'.htmlspecialchars($text).'</span>';
    }
}
