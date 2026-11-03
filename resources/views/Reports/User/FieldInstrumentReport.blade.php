<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $header['report_title'] ?? 'Field Instrument Master Report' }} - {{ $instrument['id_no'] ?? ($instrument['id'] ?? '') }}</title>

    <style>
        /* ---------- page ---------- */
        /* top / bottom margin here; left / right margin is on .page-wrap because the @page side margins are ignored */
        @page { size: A4 landscape; margin: 8mm 0 16mm 0; }

        * { box-sizing: border-box; }

        html, body {
            width: 100%;
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #2a3a4d;
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5px;
            line-height: 1.35;
        }

        .page-wrap { width: auto; margin: 0; padding: 0 5.5mm; }

        /* ---------- header (first page only) ---------- */
        .company-box {
            border: 1px solid #d3dfec;
            border-bottom: 0;
            background: #f4f8fc;
            text-align: center;
            padding: 15px 10px 14px 10px;
            margin: 0;
        }
        .company-name {
            color: #1f3f66;
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin: 0;
            padding: 0;
        }
        .report-banner {
            background: #254870;
            color: #ffffff;
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: .6px;
            text-transform: uppercase;
            padding: 12px 10px;
            margin: 0;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 10px 0 16px 0;
        }
        .meta-table td {
            width: 50%;
            border: 1px solid #d3dfec;
            background: #f4f8fc;
            color: #2a3a4d;
            font-size: 8.5px;
            padding: 9px 10px;
            vertical-align: middle;
        }
        .meta-table td strong { color: #1f3f66; font-weight: bold; }

        /* ---------- footer (every page) ---------- */
        footer { position: fixed; left: 5.5mm; right: 5.5mm; bottom: -12mm; height: 10mm; }
        .footer-wrapper { border-top: 1px solid #d3dfec; padding-top: 4px; }
        .footer-table { width: 100%; border-collapse: collapse; }
        .footer-table td { border: 0; padding: 0; vertical-align: top; font-size: 7.5px; color: #5b6b7e; }
        .footer-left { text-align: left; }
        .footer-right { text-align: right; }
        .footer-company { color: #1f3f66; font-weight: bold; text-transform: uppercase; letter-spacing: .3px; }
        .footer-meta { color: #5b6b7e; font-size: 7px; }

        /* ---------- section headings ---------- */
        .section { margin: 0 0 16px 0; padding: 0; page-break-inside: avoid; }
        .section.allow-break { page-break-inside: auto; }
        .section-heading {
            background: #eaf1f9;
            color: #1f3f66;
            border: 1px solid #d3e1f0;
            border-left: 4px solid #3b78b3;
            border-radius: 5px;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: .6px;
            text-transform: uppercase;
            margin: 0 0 10px 0;
            padding: 8px 10px;
            page-break-after: avoid;
        }

        /* ---------- field tables (label | value | label | value) ---------- */
        .data-table { width: 100%; margin: 0; padding: 0; border-collapse: collapse; table-layout: fixed; }
        .data-table td {
            border: 1px solid #d3dfec;
            font-size: 8.5px;
            padding: 8px 10px;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .data-table .field-name { width: 24%; background: #f4f8fc; color: #1f3f66; font-weight: bold; }
        .data-table .field-value { width: 26%; background: #ffffff; color: #2a3a4d; }

        /* ---------- checklist grid ---------- */
        .grid-table { width: 100%; margin: 0; padding: 0; border-collapse: collapse; table-layout: fixed; }
        .grid-table th {
            background: #254870;
            color: #ffffff;
            border: 1px solid #c5d3e3;
            font-size: 7.5px;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            padding: 8px 5px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .grid-table td {
            background: #ffffff;
            color: #2a3a4d;
            border: 1px solid #d3dfec;
            font-size: 7.5px;
            text-align: center;
            vertical-align: middle;
            padding: 7px 5px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .grid-table td.left { text-align: left; }
        .grid-table tbody tr:nth-child(even) td { background: #f4f8fc; }
        .grid-legend { margin: 5px 0 0 0; font-size: 7.5px; color: #5b6b7e; }
        .empty-grid {
            color: #5b6b7e;
            background: #f4f8fc;
            border: 1px dashed #c5d3e3;
            padding: 12px;
            font-size: 8.5px;
            text-align: center;
        }

        /* ---------- page breaks ---------- */
        .data-table tr, .grid-table tr { page-break-inside: avoid; }
        thead { display: table-header-group; }
    </style>
</head>
<body>

    {{-- footer: repeats on every page --}}
    <footer>
        <div class="footer-wrapper">
            <table class="footer-table">
                <tr>
                    <td class="footer-left">
                        <div class="footer-company">{{ $header['company_name'] ?? 'Shilpa Medicare Pvt Ltd' }}</div>
                        <div class="footer-meta">{{ $header['report_title'] ?? '-' }} &nbsp;|&nbsp; Controlled Document</div>
                    </td>
                    <td class="footer-right">
                        <div>Generated By: <strong>{{ $footer['generated_by'] ?? 'System' }}</strong></div>
                        <div class="footer-meta">Generated On: {{ $footer['generated_at'] ?? '-' }}</div>
                    </td>
                </tr>
            </table>
        </div>
    </footer>

    <div class="page-wrap">

        {{-- header: first page only --}}
        <div class="company-box">
            <div class="company-name">{{ $header['company_name'] ?? 'Shilpa Medicare Pvt Ltd' }}</div>
        </div>
        <div class="report-banner">{{ $header['report_title'] ?? 'Field Instrument Master' }}</div>

        <table class="meta-table">
            <tr>
                <td><strong>Document Number:</strong> {{ $header['document_number'] ?? '-' }}</td>
                <td><strong>Document Type:</strong> {{ $header['document_type'] ?? '-' }}</td>
            </tr>
        </table>

        {{-- report content --}}
        @foreach($sections ?? [] as $section)
            @php
                $sectionType = $section['type'] ?? 'fields';
                $sectionTitle = $section['title'] ?? null;
            @endphp

            {{-- ================= field section (label | value | label | value) ================= --}}
            @if($sectionType === 'fields')
                <div class="section">
                    {{-- a section without a title (HOD / QA review lines) is printed without a heading --}}
                    @if(!empty($sectionTitle))
                        <div class="section-heading">{{ $sectionTitle }}</div>
                    @endif

                    <table class="data-table">
                        <tbody>
                            @foreach(array_chunk($section['fields'] ?? [], 2) as $fieldPair)
                                <tr>
                                    @foreach($fieldPair as $field)
                                        <td class="field-name">{{ $field['label'] ?? '-' }}</td>
                                        <td class="field-value">{{ $field['value'] ?? '-' }}</td>
                                    @endforeach

                                    @if(count($fieldPair) === 1)
                                        <td class="field-name"></td>
                                        <td class="field-value"></td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            {{-- ================= checklist ================= --}}
            @elseif($sectionType === 'checklist')
                @php
                    $checklist = $section['checklist'] ?? [];
                    $checklistRows = $checklist['rows'] ?? [];
                    $questionHeaders = $checklist['question_headers'] ?? [];
                    $dataHeaders = $checklist['data_headers'] ?? [];
                    $showSerial = $checklist['include_serial_number'] ?? true;
                    $showFrequency = $checklist['show_frequency'] ?? false;

                    /* column widths (%) sit on the header cells; question columns get more room than data columns */
                    $srWidth = $showSerial ? 5 : 0;
                    $frequencyWidth = $showFrequency ? 9 : 0;
                    $questionWeight = 2;
                    $dataWeight = 1.2;
                    $totalWeight = max((count($questionHeaders) * $questionWeight) + (count($dataHeaders) * $dataWeight), 1);
                    $remainingWidth = 100 - $srWidth - $frequencyWidth;
                    $questionWidth = round($remainingWidth * $questionWeight / $totalWeight, 2);
                    $dataWidth = round($remainingWidth * $dataWeight / $totalWeight, 2);
                @endphp

                <div class="section allow-break">
                    <div class="section-heading">{{ $sectionTitle ?: 'Checklist' }}</div>

                    @if(count($checklistRows) === 0)
                        <div class="empty-grid">No checklist configured.</div>
                    @else
                        <table class="grid-table">
                            <thead>
                                <tr>
                                    @if($showSerial)
                                        <th style="width: {{ $srWidth }}%">Sr. No.</th>
                                    @endif
                                    @foreach($questionHeaders as $columnHeader)
                                        <th style="width: {{ $questionWidth }}%">{{ $columnHeader }}</th>
                                    @endforeach
                                    @foreach($dataHeaders as $columnHeader)
                                        <th style="width: {{ $dataWidth }}%">{{ $columnHeader }}</th>
                                    @endforeach
                                    @if($showFrequency)
                                        <th style="width: {{ $frequencyWidth }}%">Frequency</th>
                                    @endif
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($checklistRows as $rowIndex => $row)
                                    <tr>
                                        @if($showSerial)
                                            <td>{{ $rowIndex + 1 }}</td>
                                        @endif
                                        @foreach(($row['questions'] ?? []) as $text)
                                            <td class="left">{{ $text }}</td>
                                        @endforeach
                                        @foreach(($row['cells'] ?? []) as $text)
                                            <td>{{ $text }}</td>
                                        @endforeach
                                        @if($showFrequency)
                                            <td>{{ $row['frequency'] ?? '-' }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="grid-legend">* Required field</div>
                    @endif
                </div>

            {{-- ================= notes ================= --}}
            @elseif($sectionType === 'notes')
                <div class="section">
                    <div class="section-heading">{{ $sectionTitle ?: 'Notes' }}</div>

                    <table class="data-table">
                        <tbody>
                            @foreach(($section['items'] ?? []) as $noteIndex => $item)
                                <tr>
                                    @if($section['include_serial_number'] ?? true)
                                        <td class="field-name" style="width: 5%; text-align: center;">{{ $noteIndex + 1 }}</td>
                                    @endif
                                    <td style="background: #ffffff;">{{ $item }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endforeach

    </div>{{-- /page-wrap --}}

</body>
</html>