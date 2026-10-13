<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PLink Sustainability and Predictive Analytics Report</title>
    <style>
        @page {
            margin: 24mm 17mm 19mm 17mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #13264d;
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 9.5pt;
            line-height: 1.45;
            background: #ffffff;
        }

        .page-header {
            position: fixed;
            top: -18mm;
            left: 0;
            right: 0;
            height: 12mm;
            border-bottom: 1px solid #c8def3;
            color: #4d6d85;
            font-size: 8pt;
        }

        .fixed-meta-table {
            width: 100%;
            border-collapse: collapse;
        }

        .fixed-meta-table td {
            padding: 0;
            border: 0;
            vertical-align: middle;
        }

        .page-header .brand {
            color: #112d68;
            font-weight: 700;
            letter-spacing: 0.7px;
        }

        .page-header .document-name {
            text-align: right;
        }

        .page-footer {
            position: fixed;
            bottom: -12mm;
            left: 0;
            right: 0;
            height: 8mm;
            border-top: 1px solid #d7e7f5;
            color: #6d8199;
            font-size: 7.5pt;
            padding-top: 3mm;
        }

        .page-footer .right {
            text-align: right;
            padding-right: 34mm;
        }

        .cover {
            border: 1px solid #bedef6;
            border-top: 8px solid #112d68;
            padding: 25px 28px 24px;
            margin-bottom: 22px;
            background: #fbfdff;
        }

        .brand-row {
            margin-bottom: 20px;
        }

        .brand-mark {
            display: inline-block;
            background: #112d68;
            color: #ffffff;
            font-weight: 700;
            font-size: 16pt;
            letter-spacing: 1.4px;
            padding: 8px 13px;
        }

        .brand-subtitle {
            color: #4d6d85;
            font-size: 8.5pt;
            margin-top: 7px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        h1 {
            color: #112d68;
            font-size: 22pt;
            line-height: 1.2;
            margin: 0 0 10px;
        }

        .report-subtitle {
            color: #4d6d85;
            font-size: 10.5pt;
            margin-bottom: 20px;
        }

        .meta-table,
        .summary-table,
        .data-table,
        .forecast-summary {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-table td {
            border-top: 1px solid #d9e8f4;
            padding: 8px 7px;
            vertical-align: top;
        }

        .meta-table td:first-child {
            width: 31%;
            color: #4d6d85;
            font-weight: 700;
        }

        .section {
            margin-top: 20px;
            page-break-inside: avoid;
        }

        .section-title {
            color: #112d68;
            font-size: 13pt;
            font-weight: 700;
            margin: 0 0 4px;
            padding-bottom: 6px;
            border-bottom: 2px solid #2776c0;
        }

        .section-note {
            margin: 0 0 11px;
            color: #6c8099;
            font-size: 8.5pt;
        }

        .summary-table {
            table-layout: fixed;
            border-spacing: 0;
        }

        .summary-table td {
            width: 20%;
            padding: 12px 8px;
            border: 1px solid #d6e7f4;
            background: #f7fbfe;
            vertical-align: top;
            text-align: center;
        }

        .summary-value {
            color: #112d68;
            font-size: 17pt;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .summary-label {
            color: #5d748e;
            font-size: 7.8pt;
            line-height: 1.25;
        }

        .data-table {
            margin-top: 7px;
        }

        .data-table thead th {
            background: #112d68;
            color: #ffffff;
            font-size: 8pt;
            font-weight: 700;
            padding: 8px 7px;
            text-align: left;
            border: 1px solid #112d68;
        }

        .data-table tbody td {
            padding: 7px;
            border: 1px solid #d9e8f4;
            vertical-align: top;
            font-size: 8.4pt;
        }

        .data-table tbody tr:nth-child(even) td {
            background: #f7fbfe;
        }

        .num {
            text-align: right !important;
            white-space: nowrap;
        }

        .center {
            text-align: center !important;
        }

        .status {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 10px;
            font-size: 7.5pt;
            font-weight: 700;
            background: #e8f2fb;
            color: #112d68;
        }

        .status.warning {
            background: #fff4d8;
            color: #8a5900;
        }

        .status.critical {
            background: #fde8e8;
            color: #9c2f2f;
        }

        .empty-state {
            border: 1px solid #d8e8f5;
            background: #f8fbfe;
            color: #6c8099;
            padding: 11px 12px;
            font-size: 8.5pt;
        }

        .forecast-block {
            margin-top: 12px;
            padding: 11px 12px 12px;
            border: 1px solid #d4e5f3;
            border-left: 4px solid #2776c0;
            page-break-inside: avoid;
        }

        .forecast-title {
            color: #112d68;
            font-weight: 700;
            font-size: 10pt;
            margin-bottom: 3px;
        }

        .forecast-generated {
            color: #71869d;
            font-size: 7.8pt;
            margin-bottom: 8px;
        }

        .forecast-summary td {
            width: 50%;
            padding: 6px 7px;
            border: 1px solid #d9e8f4;
            background: #f7fbfe;
        }

        .forecast-summary .label {
            color: #6b8098;
            font-size: 7.5pt;
        }

        .forecast-summary .value {
            color: #112d68;
            font-weight: 700;
            font-size: 9pt;
        }

        .method-note {
            margin-top: 18px;
            padding: 11px 12px;
            border-left: 4px solid #8fa6b9;
            background: #f7fafc;
            color: #536b84;
            font-size: 8pt;
            page-break-inside: avoid;
        }

        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <table class="fixed-meta-table">
            <tr>
                <td class="brand">PLINK</td>
                <td class="document-name">Sustainability and Predictive Analytics Report</td>
            </tr>
        </table>
    </div>

    <div class="page-footer">
        <table class="fixed-meta-table">
            <tr>
                <td>PLink Recycling Management System - Administrative Report</td>
                <td class="right">Generated {{ $generatedAt->format('M d, Y h:i A') }}</td>
            </tr>
        </table>
    </div>

    <section class="cover">
        <div class="brand-row">
            <div class="brand-mark">PLINK</div>
            <div class="brand-subtitle">Recycling Management System</div>
        </div>

        <h1>Sustainability and Predictive Analytics Report</h1>
        <div class="report-subtitle">
            Formal administrative summary of recycling activity, student participation,
            reward activity, smart bin status, and stored Prophet forecasts.
        </div>

        <table class="meta-table">
            <tr>
                <td>Reporting Period</td>
                <td>{{ $data['range']['from'] ?? 'N/A' }} to {{ $data['range']['to'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Coverage</td>
                <td>{{ number_format((int) ($data['range']['days'] ?? 0)) }} day(s)</td>
            </tr>
            <tr>
                <td>Report ID</td>
                <td>{{ $report->analytics_report_id ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Date Generated</td>
                <td>{{ $generatedAt->format('F d, Y - h:i A') }}</td>
            </tr>
        </table>
    </section>

    @php
        $summary = $data['summary'] ?? [];
        $wasteTypes = collect($data['waste_types'] ?? [])->values();
        $topRecyclers = collect($data['top_recyclers'] ?? [])->take(5)->values();
        $sections = collect($data['section_performance'] ?? [])->take(10)->values();
        $rewards = collect($data['reward_breakdown'] ?? [])->take(8)->values();
        $compartments = collect($data['compartments']['current'] ?? [])->values();
        $predictive = collect($data['predictive'] ?? []);
    @endphp

    <section class="section">
        <div class="section-title">1. Executive Summary</div>
        <div class="section-note">Key performance indicators recorded during the selected reporting period.</div>

        <table class="summary-table">
            <tr>
                <td>
                    <div class="summary-value">{{ number_format((int) ($summary['total_items'] ?? 0)) }}</div>
                    <div class="summary-label">Recyclables Collected</div>
                </td>
                <td>
                    <div class="summary-value">{{ number_format((int) ($summary['participating_students'] ?? 0)) }}</div>
                    <div class="summary-label">Participating Students</div>
                </td>
                <td>
                    <div class="summary-value">{{ number_format((int) ($summary['total_points'] ?? 0)) }}</div>
                    <div class="summary-label">Points Awarded</div>
                </td>
                <td>
                    <div class="summary-value">{{ number_format((int) ($summary['rewards_redeemed'] ?? 0)) }}</div>
                    <div class="summary-label">Rewards Redeemed</div>
                </td>
                <td>
                    <div class="summary-value">{{ number_format((int) ($summary['transactions'] ?? 0)) }}</div>
                    <div class="summary-label">Completed Transactions</div>
                </td>
            </tr>
        </table>
    </section>

    <section class="section">
        <div class="section-title">2. Recyclable Type Breakdown</div>
        <div class="section-note">Accepted recyclable items classified during the reporting period.</div>

        @if ($wasteTypes->isEmpty())
            <div class="empty-state">No accepted recyclable items were recorded in this reporting period.</div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 8%;">No.</th>
                        <th>Recyclable Type</th>
                        <th style="width: 24%;">Material Category</th>
                        <th class="num" style="width: 18%;">Items</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($wasteTypes as $index => $item)
                        <tr>
                            <td class="center">{{ $index + 1 }}</td>
                            <td>{{ $item['label'] ?? $item['name'] ?? 'Unknown' }}</td>
                            <td>{{ ucfirst($item['material_category'] ?? 'N/A') }}</td>
                            <td class="num">{{ number_format((int) ($item['total_items'] ?? 0)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="section">
        <div class="section-title">3. Student and Section Performance</div>
        <div class="section-note">Leading recyclers and sections based on activity within the reporting period.</div>

        <div style="font-weight: 700; color: #112d68; margin: 8px 0 5px;">Top 5 Recyclers</div>
        @if ($topRecyclers->isEmpty())
            <div class="empty-state">No participating recyclers were recorded in this period.</div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 9%;">Rank</th>
                        <th>Student</th>
                        <th class="num" style="width: 20%;">Items</th>
                        <th class="num" style="width: 20%;">Points</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($topRecyclers as $index => $student)
                        <tr>
                            <td class="center">{{ $index + 1 }}</td>
                            <td>{{ $student['name'] ?? 'Unknown Student' }}</td>
                            <td class="num">{{ number_format((int) ($student['items_recycled'] ?? 0)) }}</td>
                            <td class="num">{{ number_format((int) ($student['points_earned'] ?? 0)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div style="font-weight: 700; color: #112d68; margin: 14px 0 5px;">Section Performance</div>
        @if ($sections->isEmpty())
            <div class="empty-state">No section performance data was recorded in this period.</div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 9%;">Rank</th>
                        <th>Section</th>
                        <th class="num" style="width: 18%;">Participants</th>
                        <th class="num" style="width: 18%;">Items</th>
                        <th class="num" style="width: 18%;">Points</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sections as $index => $section)
                        <tr>
                            <td class="center">{{ $index + 1 }}</td>
                            <td>{{ $section['name'] ?? 'Unknown Section' }}</td>
                            <td class="num">{{ number_format((int) ($section['participants'] ?? 0)) }}</td>
                            <td class="num">{{ number_format((int) ($section['total_items'] ?? 0)) }}</td>
                            <td class="num">{{ number_format((int) ($section['total_points'] ?? 0)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="section">
        <div class="section-title">4. Rewards and Smart Bin Status</div>
        <div class="section-note">Reward redemption activity and the latest reported compartment fullness.</div>

        <div style="font-weight: 700; color: #112d68; margin: 8px 0 5px;">Reward Redemptions</div>
        @if ($rewards->isEmpty())
            <div class="empty-state">No reward redemptions were recorded in this period.</div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Reward</th>
                        <th class="num" style="width: 28%;">Redemptions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rewards as $reward)
                        <tr>
                            <td>{{ $reward['name'] ?? 'Unknown Reward' }}</td>
                            <td class="num">{{ number_format((int) ($reward['redemptions'] ?? 0)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div style="font-weight: 700; color: #112d68; margin: 14px 0 5px;">Current Smart Bin Compartment Status</div>
        @if ($compartments->isEmpty())
            <div class="empty-state">No smart bin compartment information is currently available.</div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Smart Bin</th>
                        <th>Compartment</th>
                        <th>Material</th>
                        <th class="num" style="width: 18%;">Fullness</th>
                        <th style="width: 18%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($compartments as $compartment)
                        @php
                            $fill = (int) ($compartment['fill_percentage'] ?? 0);
                            $status = strtolower((string) ($compartment['status'] ?? 'unknown'));
                            $statusClass = $fill >= 100 ? 'critical' : ($fill >= 80 ? 'warning' : '');
                        @endphp
                        <tr>
                            <td>{{ $compartment['bin_name'] ?? ('Smart Bin ' . ($compartment['smart_bin_id'] ?? 'N/A')) }}</td>
                            <td>{{ $compartment['name'] ?? 'Unknown Compartment' }}</td>
                            <td>{{ ucfirst($compartment['material_category'] ?? 'N/A') }}</td>
                            <td class="num">{{ $fill }}%</td>
                            <td><span class="status {{ $statusClass }}">{{ ucfirst($status) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <div class="page-break"></div>

    <section class="section" style="margin-top: 0;">
        <div class="section-title">5. Predictive Analytics</div>
        <div class="section-note">
            Stored seven-day Prophet forecasts. Prediction intervals are displayed when available.
        </div>

        @if ($predictive->isEmpty())
            <div class="empty-state">No predictive analytics are currently available.</div>
        @else
            @foreach ($predictive as $forecast)
                @php
                    $forecastPoints = collect($forecast['forecast'] ?? [])->take(7)->values();
                    $unit = $forecast['unit'] ?? '';
                @endphp

                <div class="forecast-block">
                    <div class="forecast-title">{{ $forecast['title'] ?? 'Forecast' }}</div>
                    <div class="forecast-generated">
                        Forecast generated: {{ $forecast['forecast_generated_at'] ?? 'Not yet generated' }}
                    </div>

                    @if ($forecastPoints->isEmpty())
                        <div class="empty-state">
                            No stored forecast is available for this metric. Run the forecast from Reports & Analytics first.
                        </div>
                    @else
                        <table class="forecast-summary">
                            <tr>
                                <td>
                                    <div class="label">Next 7-day total</div>
                                    <div class="value">
                                        @if (($forecast['next_7_total'] ?? null) !== null)
                                            {{ number_format((float) $forecast['next_7_total'], 1) }} {{ $unit }}
                                        @else
                                            Not applicable
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="label">Forecast peak</div>
                                    <div class="value">
                                        @if (($forecast['peak_forecast'] ?? null) !== null)
                                            {{ number_format((float) $forecast['peak_forecast'], 1) }}{{ $unit === '%' ? '%' : ' ' . $unit }}
                                        @else
                                            Not applicable
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th class="num">Forecast</th>
                                    <th class="num">Lower Bound</th>
                                    <th class="num">Upper Bound</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($forecastPoints as $point)
                                    <tr>
                                        <td>{{ $point['ds'] ?? 'N/A' }}</td>
                                        <td class="num">{{ number_format((float) ($point['yhat'] ?? 0), 1) }}{{ $unit === '%' ? '%' : ' ' . $unit }}</td>
                                        <td class="num">
                                            {{ ($point['yhat_lower'] ?? null) !== null ? number_format((float) $point['yhat_lower'], 1) . ($unit === '%' ? '%' : ' ' . $unit) : 'N/A' }}
                                        </td>
                                        <td class="num">
                                            {{ ($point['yhat_upper'] ?? null) !== null ? number_format((float) $point['yhat_upper'], 1) . ($unit === '%' ? '%' : ' ' . $unit) : 'N/A' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @endforeach
        @endif
    </section>

    <div class="method-note">
        <strong>Report Note:</strong>
        Historical values are derived from completed PLink recycling and reward records within the selected reporting period.
        Predictive values are generated separately using the latest stored Prophet forecast and should be interpreted as planning estimates rather than guaranteed outcomes.
    </div>
</body>
</html>
