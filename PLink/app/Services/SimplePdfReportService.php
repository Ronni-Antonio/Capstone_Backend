<?php

namespace App\Services;

use App\Models\AnalyticsReport;
use Barryvdh\DomPDF\Facade\Pdf;

class SimplePdfReportService
{
    /**
     * Render the formal PLink sustainability report as a PDF.
     *
     * The report styling is intentionally kept in a Blade view so the
     * document can be maintained like a normal printable business report
     * instead of manually drawing raw PDF commands.
     */
    public function render(array $data, AnalyticsReport $report): string
    {
        $pdf = Pdf::loadView('reports.sustainability', [
            'data' => $data,
            'report' => $report,
            'generatedAt' => now(),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
            ]);

        // Render first so the canvas exists, then add formal page numbering.
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();
        $font = $fontMetrics->getFont('DejaVu Sans', 'normal');

        $canvas->page_text(
            470,
            815,
            'Page {PAGE_NUM} of {PAGE_COUNT}',
            $font,
            8,
            [0.30, 0.40, 0.55]
        );

        return $pdf->output();
    }
}
