<?php

namespace App\Services;

use App\Models\AnalyticsReport;

class SimplePdfReportService
{
    private array $pages = [];
    private array $current = [];
    private float $y = 750;

    public function render(array $data, AnalyticsReport $report): string
    {
        $this->pages = [];
        $this->current = [];
        $this->y = 750;

        // ============================================================
        // REPORT HEADER
        // ============================================================

        $this->text('PLINK', 20, true);

        $this->text(
            'Recycling Sustainability & Predictive Analytics Report',
            16,
            true
        );

        $this->text(
            sprintf(
                'Reporting period: %s to %s',
                $data['range']['from'] ?? 'N/A',
                $data['range']['to'] ?? 'N/A'
            ),
            10
        );

        $this->text(
            'Report ID: ' . ($report->analytics_report_id ?? 'N/A'),
            9
        );

        $this->gap(8);


        // ============================================================
        // EXECUTIVE SUMMARY
        // ============================================================

        $this->heading('Executive Summary');

        $summary = $data['summary'] ?? [];

        $this->row(
            'Total recyclable items',
            $summary['total_items'] ?? 0
        );

        $this->row(
            'Participating students',
            $summary['participating_students'] ?? 0
        );

        $this->row(
            'Points awarded',
            $summary['total_points'] ?? 0
        );

        $this->row(
            'Rewards redeemed',
            $summary['rewards_redeemed'] ?? 0
        );

        $this->row(
            'Completed recycling transactions',
            $summary['transactions'] ?? 0
        );


        // ============================================================
        // RECYCLABLE TYPE BREAKDOWN
        // ============================================================

        $this->heading('Recyclable Type Breakdown');

        $wasteTypes = collect(
            $data['waste_types'] ?? []
        )
            ->values()
            ->all();

        if (empty($wasteTypes)) {
            $this->text(
                'No accepted recyclable items were recorded in this period.',
                10
            );
        } else {
            foreach ($wasteTypes as $item) {
                $label =
                    $item['label']
                    ?? $item['name']
                    ?? 'Unknown';

                $totalItems =
                    $item['total_items']
                    ?? 0;

                $this->row(
                    $label,
                    $totalItems . ' items'
                );
            }
        }


        // ============================================================
        // TOP RECYCLERS
        // ============================================================

        $this->heading('Top 5 Recyclers');

        $topRecyclers = collect(
            $data['top_recyclers'] ?? []
        )
            ->take(5)
            ->values()
            ->all();

        if (empty($topRecyclers)) {
            $this->text(
                'No participating recyclers were recorded in this period.',
                10
            );
        } else {
            foreach (
                $topRecyclers as $index => $student
            ) {
                $this->text(
                    sprintf(
                        '%d. %s - %d points, %d items',
                        $index + 1,
                        $student['name'] ?? 'Unknown student',
                        $student['points_earned'] ?? 0,
                        $student['items_recycled'] ?? 0
                    ),
                    10
                );
            }
        }


        // ============================================================
        // SECTION PERFORMANCE
        // ============================================================

        $this->heading('Section Performance');

        $sections = collect(
            $data['section_performance'] ?? []
        )
            ->take(5)
            ->values()
            ->all();

        if (empty($sections)) {
            $this->text(
                'No section performance data was recorded in this period.',
                10
            );
        } else {
            foreach ($sections as $section) {
                $this->text(
                    sprintf(
                        '%s - %d items, %d participants, %d points',
                        $section['name'] ?? 'Unknown section',
                        $section['total_items'] ?? 0,
                        $section['participants'] ?? 0,
                        $section['total_points'] ?? 0
                    ),
                    10
                );
            }
        }


        // ============================================================
        // SMART BIN COMPARTMENT STATUS
        // ============================================================

        $this->heading(
            'Current Smart Bin Compartment Status'
        );

        $compartments = collect(
            $data['compartments']['current'] ?? []
        )
            ->values()
            ->all();

        if (empty($compartments)) {
            $this->text(
                'No smart bin compartment information is available.',
                10
            );
        } else {
            foreach ($compartments as $compartment) {
                $distance =
                    $compartment['distance_cm'] ?? null;

                $distanceText =
                    $distance !== null
                        ? ', ' . $distance . ' cm'
                        : '';

                $this->text(
                    sprintf(
                        '%s - %d%% full%s',
                        $compartment['name']
                            ?? 'Unknown compartment',
                        (int) (
                            $compartment['fill_percentage']
                            ?? 0
                        ),
                        $distanceText
                    ),
                    10
                );
            }
        }


        // ============================================================
        // PROPHET PREDICTIVE ANALYTICS
        // ============================================================

        $this->heading(
            'Prophet Predictive Analytics'
        );

        $predictive = collect(
            $data['predictive'] ?? []
        )
            ->values()
            ->all();

        if (empty($predictive)) {
            $this->text(
                'No predictive analytics are available.',
                10
            );
        } else {
            foreach ($predictive as $forecast) {
                $title =
                    $forecast['title']
                    ?? 'Forecast';

                $unit =
                    $forecast['unit']
                    ?? '';

                $this->text(
                    $title,
                    11,
                    true
                );

                $forecastPoints = collect(
                    $forecast['forecast'] ?? []
                )
                    ->take(7)
                    ->values()
                    ->all();

                if (empty($forecastPoints)) {
                    $this->text(
                        'No stored Prophet forecast is available. '
                        . 'Run the forecast from Reports & Analytics first.',
                        9
                    );

                    continue;
                }


                // ----------------------------------------------------
                // NEXT 7-DAY TOTAL
                // ----------------------------------------------------

                $next7Total =
                    $forecast['next_7_total']
                    ?? null;

                if ($next7Total !== null) {
                    $this->text(
                        sprintf(
                            'Next 7-day forecast total: %.1f %s',
                            (float) $next7Total,
                            $unit
                        ),
                        9
                    );
                }


                // ----------------------------------------------------
                // PEAK FORECAST
                // ----------------------------------------------------

                $peakForecast =
                    $forecast['peak_forecast']
                    ?? null;

                if ($peakForecast !== null) {
                    $this->text(
                        sprintf(
                            'Forecast peak: %.1f %s',
                            (float) $peakForecast,
                            $unit
                        ),
                        9
                    );
                }


                // ----------------------------------------------------
                // INDIVIDUAL FORECAST POINTS
                // ----------------------------------------------------

                foreach ($forecastPoints as $point) {
                    $interval = '';

                    $lower =
                        $point['yhat_lower']
                        ?? null;

                    $upper =
                        $point['yhat_upper']
                        ?? null;

                    if (
                        $lower !== null &&
                        $upper !== null
                    ) {
                        $interval = sprintf(
                            ' (range %.1f-%.1f)',
                            (float) $lower,
                            (float) $upper
                        );
                    }

                    $this->text(
                        sprintf(
                            '%s: %.1f %s%s',
                            $point['ds'] ?? 'Unknown date',
                            (float) (
                                $point['yhat']
                                ?? 0
                            ),
                            $unit,
                            $interval
                        ),
                        9
                    );
                }
            }
        }


        // ============================================================
        // FOOTER
        // ============================================================

        $this->gap(8);

        $this->text(
            'Generated by PLINK Reports & Analytics.',
            8
        );

        $this->finishPage();

        return $this->buildPdf();
    }


    // ================================================================
    // SECTION HEADING
    // ================================================================

    private function heading(
        string $text
    ): void {
        $this->gap(10);

        $this->text(
            $text,
            13,
            true
        );

        $this->gap(3);
    }


    // ================================================================
    // LABEL / VALUE ROW
    // ================================================================

    private function row(
        string $label,
        string|int|float $value
    ): void {
        $this->text(
            $label . ': ' . $value,
            10
        );
    }


    // ================================================================
    // VERTICAL GAP
    // ================================================================

    private function gap(
        float $points
    ): void {
        $this->y -= $points;

        $this->ensureSpace(24);
    }


    // ================================================================
    // DRAW TEXT
    // ================================================================

    private function text(
        string $text,
        float $size = 10,
        bool $bold = false
    ): void {
        $maxChars = max(
            30,
            (int) floor(
                88 * (
                    10 / max($size, 1)
                )
            )
        );

        $lines = $this->wrap(
            $text,
            $maxChars
        );

        foreach ($lines as $line) {
            $this->ensureSpace(
                $size + 8
            );

            $font =
                $bold
                    ? 'F2'
                    : 'F1';

            $escaped =
                $this->escape(
                    $line
                );

            $this->current[] =
                sprintf(
                    "BT /%s %.1f Tf 54 %.1f Td (%s) Tj ET",
                    $font,
                    $size,
                    $this->y,
                    $escaped
                );

            $this->y -=
                $size + 5;
        }
    }


    // ================================================================
    // PAGE SPACE MANAGEMENT
    // ================================================================

    private function ensureSpace(
        float $needed
    ): void {
        if (
            $this->y - $needed < 45
        ) {
            $this->finishPage();

            $this->y = 750;
        }
    }


    // ================================================================
    // FINISH CURRENT PAGE
    // ================================================================

    private function finishPage(): void
    {
        if (!empty($this->current)) {
            $this->pages[] =
                implode(
                    "\n",
                    $this->current
                );

            $this->current = [];
        }
    }


    // ================================================================
    // TEXT WRAPPING
    // ================================================================

    private function wrap(
        string $text,
        int $maxChars
    ): array {
        $cleaned = preg_replace(
            '/\s+/',
            ' ',
            trim($text)
        );

        $cleaned =
            $cleaned ?? '';

        $wrapped = wordwrap(
            $cleaned,
            $maxChars,
            "\n",
            true
        );

        if ($wrapped === '') {
            return [''];
        }

        return explode(
            "\n",
            $wrapped
        );
    }


    // ================================================================
    // PDF STRING ESCAPING
    // ================================================================

    private function escape(
        string $text
    ): string {
        $text = str_replace(
            [
                '\\',
                '(',
                ')',
            ],
            [
                '\\\\',
                '\\(',
                '\\)',
            ],
            $text
        );

        return preg_replace(
            '/[^\x20-\x7E]/',
            '-',
            $text
        ) ?? $text;
    }


    // ================================================================
    // BUILD RAW PDF
    // ================================================================

    private function buildPdf(): string
    {
        $objects = [];


        // ------------------------------------------------------------
        // PDF CATALOG
        // ------------------------------------------------------------

        $objects[1] =
            '<< /Type /Catalog /Pages 2 0 R >>';


        // ------------------------------------------------------------
        // FONTS
        // ------------------------------------------------------------

        $fontNormalId = 3;
        $fontBoldId = 4;

        $objects[$fontNormalId] =
            '<< /Type /Font '
            . '/Subtype /Type1 '
            . '/BaseFont /Helvetica >>';

        $objects[$fontBoldId] =
            '<< /Type /Font '
            . '/Subtype /Type1 '
            . '/BaseFont /Helvetica-Bold >>';


        // ------------------------------------------------------------
        // PAGES
        // ------------------------------------------------------------

        $pageIds = [];

        $nextId = 5;

        foreach (
            $this->pages as $content
        ) {
            $pageId =
                $nextId++;

            $contentId =
                $nextId++;

            $pageIds[] =
                $pageId;

            $stream =
                $content . "\n";

            $objects[$contentId] =
                "<< /Length "
                . strlen($stream)
                . " >>\n"
                . "stream\n"
                . $stream
                . "endstream";

            $objects[$pageId] =
                sprintf(
                    '<< /Type /Page '
                    . '/Parent 2 0 R '
                    . '/MediaBox [0 0 612 792] '
                    . '/Resources << '
                    . '/Font << '
                    . '/F1 %d 0 R '
                    . '/F2 %d 0 R '
                    . '>> '
                    . '>> '
                    . '/Contents %d 0 R '
                    . '>>',
                    $fontNormalId,
                    $fontBoldId,
                    $contentId
                );
        }


        // ------------------------------------------------------------
        // PAGE TREE
        // ------------------------------------------------------------

        $kids = implode(
            ' ',
            array_map(
                fn ($id) =>
                    $id . ' 0 R',
                $pageIds
            )
        );

        $objects[2] =
            sprintf(
                '<< /Type /Pages '
                . '/Kids [%s] '
                . '/Count %d >>',
                $kids,
                count($pageIds)
            );

        ksort($objects);


        // ------------------------------------------------------------
        // PDF HEADER
        // ------------------------------------------------------------

        $pdf =
            "%PDF-1.4\n";

        $offsets = [
            0 => 0,
        ];


        // ------------------------------------------------------------
        // PDF OBJECTS
        // ------------------------------------------------------------

        foreach (
            $objects as $id => $object
        ) {
            $offsets[$id] =
                strlen($pdf);

            $pdf .=
                $id
                . " 0 obj\n"
                . $object
                . "\nendobj\n";
        }


        // ------------------------------------------------------------
        // XREF TABLE
        // ------------------------------------------------------------

        $xref =
            strlen($pdf);

        $maxId =
            max(
                array_keys(
                    $objects
                )
            );

        $pdf .=
            "xref\n"
            . "0 "
            . ($maxId + 1)
            . "\n";

        $pdf .=
            "0000000000 65535 f \n";

        for (
            $id = 1;
            $id <= $maxId;
            $id++
        ) {
            $offset =
                $offsets[$id]
                ?? 0;

            $pdf .= sprintf(
                '%010d 00000 n ',
                $offset
            ) . "\n";
        }


        // ------------------------------------------------------------
        // PDF TRAILER
        // ------------------------------------------------------------

        $pdf .=
            "trailer\n"
            . "<< /Size "
            . ($maxId + 1)
            . " /Root 1 0 R >>\n";

        $pdf .=
            "startxref\n"
            . $xref
            . "\n%%EOF";

        return $pdf;
    }
}