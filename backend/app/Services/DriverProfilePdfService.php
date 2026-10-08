<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Question;
use App\Models\WorkOrderRecipient;

class DriverProfilePdfService
{
    public function __construct(
        protected PdfService $pdfService
    ) {}

    /**
     * Generate HTML representation of one-page driver profile.
     */
    public function generateHtml(Driver $driver): string
    {
        $driver->loadMissing(['whatsappGroups', 'metrics', 'answers.option']);

        // Format questions with answers
        $questions = Question::where('is_active', true)->with('currentVersion')->orderBy('sort_order', 'asc')->get();
        $answersMap = [];
        foreach ($driver->answers as $ans) {
            $answersMap[$ans->question_id] = $ans->display_value;
        }

        $questionsWithAnswers = [];
        foreach ($questions as $q) {
            $questionsWithAnswers[] = [
                'question' => $q->currentVersion ? $q->currentVersion->text_ar : $q->key,
                'answer' => $answersMap[$q->id] ?? '—',
            ];
        }

        // Recent 5 trips
        $recentTrips = WorkOrderRecipient::where('driver_id', $driver->id)
            ->whereIn('status', ['confirmed', 'backup', 'cancelled'])
            ->with('workOrder')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        return view('pdf.driver-profile', [
            'driver' => $driver,
            'questionsWithAnswers' => $questionsWithAnswers,
            'recentTrips' => $recentTrips,
            'printedAt' => now()->format('Y-m-d H:i'),
        ])->render();
    }

    /**
     * Generate PDF binary content.
     */
    public function generatePdf(Driver $driver): string
    {
        $html = $this->generateHtml($driver);

        $mpdf = $this->pdfService->createMpdf([
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
        ]);

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }
}
