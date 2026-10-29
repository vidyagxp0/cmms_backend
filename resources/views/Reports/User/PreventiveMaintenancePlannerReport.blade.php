<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>{{ $header['report_title'] ?? 'Preventive Maintenance Planner' }}</title>

    <style>
        {!! file_get_contents(resource_path('views/Reports/CSS/Report.css')) !!}

        /* ---------- page ---------- */
        @page {
            size: A4 landscape;
            margin: 4mm 0 15mm 0;
        }

        html, body {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
            color: #2a3a4d !important;
            font-family: DejaVu Sans, sans-serif !important;
            font-size: 8.5px !important;
            line-height: 1.3 !important;
        }

        /* left / right margin lives here so it works even if @page margins are ignored */
        .page-wrap {
            width: auto;
            margin: 0;
            padding: 0 5.5mm;
        }

        .report-content { width: 100%; margin: 0; padding: 0; }

        /* ---------- header (first page only) ---------- */
        .company-box {
            border: 1px solid #d3dfec;
            border-bottom: 0;
            background: #f4f8fc;
            text-align: center;
            padding: 17px 10px 16px 10px;
            margin: 0;
        }
        .company-name {
            color: #1f3f66;
            font-size: 15px;
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
            padding: 14px 10px;
            margin: 0;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 10px 0 12px 0;
        }
        .meta-table td {
            width: 50%;
            border: 1px solid #d3dfec;
            background: #f4f8fc;
            color: #2a3a4d;
            font-size: 8px;
            padding: 11px 9px;
            vertical-align: middle;
        }
        .meta-table td strong { color: #1f3f66; font-weight: bold; }

        /* ---------- footer (every page) ---------- */
        footer {
            position: fixed;
            left: 5.5mm;
            right: 5.5mm;
            bottom: -11mm;
            height: 10mm;
        }
        .footer-wrapper { border-top: 1px solid #d3dfec; padding-top: 4px; }
        .footer-table { width: 100%; border-collapse: collapse; }
        .footer-table td { border: 0; padding: 0; vertical-align: top; font-size: 7.5px; color: #5b6b7e; }
        .footer-left { text-align: left; }
        .footer-right { text-align: right; }
        .footer-company { color: #1f3f66; font-weight: bold; text-transform: uppercase; letter-spacing: .3px; }
        .footer-meta { color: #5b6b7e; font-size: 7px; }

        /* ---------- section headings ---------- */
        .section { margin: 0 0 12px 0; padding: 0; }
        .section-heading {
            background: #eaf1f9;
            color: #1f3f66;
            border: 1px solid #d3e1f0;
            border-left: 4px solid #3b78b3;
            border-radius: 5px;
            font-size: 10.5px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin: 0 0 11px 0;
            padding: 11px 10px 11px 9px;
            page-break-after: avoid;
        }

        /* ---------- field tables (label | value | label | value) ---------- */
        .data-table {
            width: 100%;
            margin: 0;
            padding: 0;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .data-table td {
            border: 1px solid #d3dfec;
            font-size: 8.5px;
            padding: 9px 8px;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .data-table .field-name {
            width: 24%;
            background: #f4f8fc;
            color: #1f3f66;
            font-weight: bold;
        }
        .data-table .field-value {
            width: 26%;
            background: #ffffff;
            color: #2a3a4d;
        }

        /* ---------- planner grid ---------- */
        .planner-caption {
            color: #1f3f66;
            font-size: 8.5px;
            font-weight: bold;
            margin: 0 0 6px 0;
            padding: 0 2px;
            page-break-after: avoid;
        }
        /* one block per table: never split a table header from its rows */
        .planner-chunk {
            margin: 0 0 10px 0;
            padding: 0;
            page-break-inside: avoid;
        }
        .planner-table {
            width: 100%;
            margin: 0;
            padding: 0;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: avoid;
        }
        .planner-table th {
            background: #254870;
            color: #ffffff;
            border: 1px solid #c5d3e3;
            font-size: 6.5px;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            padding: 5px 1px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .planner-table th.month-head { font-size: 8px; padding: 7px 2px; letter-spacing: .4px; }
        .planner-table th.week-head { background: #2f5a8a; font-size: 6.5px; }
        .planner-table th.date-head { background: #3a6a9c; font-size: 6px; font-weight: normal; }
        .planner-table th.pa-head { background: #4a7aab; font-size: 6.5px; }

        .planner-table td {
            background: #ffffff;
            color: #2a3a4d;
            border: 1px solid #d3dfec;
            font-size: 6.5px;
            text-align: center;
            vertical-align: middle;
            padding: 7px 1px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        /* Sr. No. / equipment name / code / location cells */
        .planner-table td.info { padding: 7px 3px; }
        .planner-table td.off { background: #eceff3; }
        .planner-table td.filled { background: #dcecf9; color: #1f3f66; font-weight: bold; font-size: 7px; }
        .empty-grid {
            color: #5b6b7e;
            background: #f4f8fc;
            border: 1px dashed #c5d3e3;
            padding: 10px;
            font-size: 8px;
            text-align: center;
        }

        /* ---------- attachments ---------- */
        .attachment-item { margin: 0 0 2px 0; padding: 0; }
        .attachment-link { color: #246da8; text-decoration: underline; word-wrap: break-word; }

        /* ---------- page breaks ---------- */
        .data-table tr, .planner-table tr { page-break-inside: avoid; }
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
        <div class="report-banner">{{ $header['report_title'] ?? 'Preventive Maintenance Planner' }}</div>

        <table class="meta-table">
            <tr>
                <td><strong>Document Number:</strong> {{ $header['document_number'] ?? '-' }}</td>
                <td><strong>Document Type:</strong> {{ $header['document_type'] ?? '-' }}</td>
            </tr>
        </table>

        {{-- report content --}}
        <div class="report-content">

            @foreach($tabs ?? [] as $tab)
                @php
                    $tabTitle = $tab['tab_title'] ?? '-';
                @endphp

                {{-- tab title uses the same heading style as sections --}}
                <div class="section-heading">{{ $tabTitle }}</div>

                @foreach($tab['sections'] ?? [] as $section)
                    @php
                        $sectionType = $section['type'] ?? 'fields';
                        $sectionTitle = $section['title'] ?? '-';
                    @endphp

                    {{-- ================= planner grid section ================= --}}
                    @if($sectionType === 'planner')
                        <div class="section">
                            <div class="section-heading">{{ $sectionTitle }}</div>

                            @if(!empty($section['rows']) && !empty($section['tables']))
                                <div class="planner-caption">
                                    Preventive Maintenance Planner
                                    @if(!empty($section['year']))
                                        &nbsp;|&nbsp; For the Year of {{ $section['year'] }}
                                    @endif
                                </div>

                                @foreach($section['tables'] as $table)
                                    @php
                                        $tableMonths = $table['months'] ?? [];
                                        $showEquipment = $table['show_equipment'] ?? true;
                                        $paColumns = max(count($tableMonths) * 10, 1);

                                        /* the first table has Sr. No. + name + code + location, the rest only Sr. No. */
                                        $srWidth = 3.5;
                                        $nameWidth = 12.5;
                                        $codeWidth = 10.5;
                                        $locationWidth = 10;
                                        $leftWidth = $showEquipment ? ($srWidth + $nameWidth + $codeWidth + $locationWidth) : $srWidth;
                                        $paWidth = round((100 - $leftWidth) / $paColumns, 3);
                                    @endphp

                                    <div class="planner-chunk">
                                        <table class="planner-table">
                                            <colgroup>
                                                <col style="width: {{ $srWidth }}%">
                                                @if($showEquipment)
                                                    <col style="width: {{ $nameWidth }}%">
                                                    <col style="width: {{ $codeWidth }}%">
                                                    <col style="width: {{ $locationWidth }}%">
                                                @endif
                                                @for($i = 0; $i < $paColumns; $i++)
                                                    <col style="width: {{ $paWidth }}%">
                                                @endfor
                                            </colgroup>

                                            <thead>
                                                {{-- month row --}}
                                                <tr>
                                                    <th rowspan="4" style="width: {{ $srWidth }}%">Sr. No.</th>
                                                    @if($showEquipment)
                                                        <th rowspan="4" style="width: {{ $nameWidth }}%">Equipment Name</th>
                                                        <th rowspan="4" style="width: {{ $codeWidth }}%">Equipment Code</th>
                                                        <th rowspan="4" style="width: {{ $locationWidth }}%">Location</th>
                                                    @endif
                                                    @foreach($tableMonths as $month)
                                                        <th colspan="10" class="month-head">{{ $month['name'] }}</th>
                                                    @endforeach
                                                </tr>

                                                {{-- week row --}}
                                                <tr>
                                                    @foreach($tableMonths as $month)
                                                        @foreach($month['weeks'] as $week)
                                                            <th colspan="2" class="week-head">Week {{ $week['number'] }}</th>
                                                        @endforeach
                                                    @endforeach
                                                </tr>

                                                {{-- date row --}}
                                                <tr>
                                                    @foreach($tableMonths as $month)
                                                        @foreach($month['weeks'] as $week)
                                                            <th colspan="2" class="date-head">{{ $week['date'] ?: '-' }}</th>
                                                        @endforeach
                                                    @endforeach
                                                </tr>

                                                {{-- P / A row --}}
                                                <tr>
                                                    @foreach($tableMonths as $month)
                                                        @foreach($month['weeks'] as $week)
                                                            <th class="pa-head" style="width: {{ $paWidth }}%">P</th>
                                                            <th class="pa-head" style="width: {{ $paWidth }}%">A</th>
                                                        @endforeach
                                                    @endforeach
                                                </tr>
                                            </thead>

                                            <tbody>
                                                @foreach($section['rows'] as $rowIndex => $row)
                                                    <tr>
                                                        <td class="info">{{ str_pad($rowIndex + 1, 2, '0', STR_PAD_LEFT) }}</td>

                                                        @if($showEquipment)
                                                            <td class="info">{{ ($row['name'] ?? null) ?: '-' }}</td>
                                                            <td class="info">{{ ($row['code'] ?? null) ?: '-' }}</td>
                                                            <td class="info">{{ ($row['location'] ?? null) ?: '-' }}</td>
                                                        @endif

                                                        @foreach($tableMonths as $month)
                                                            @foreach($month['weeks'] as $weekIndex => $week)
                                                                @php
                                                                    $cell = $row['schedule'][$month['key']][$weekIndex] ?? [];
                                                                    $planned = $cell['planned'] ?? null;
                                                                    $actual = $cell['actual'] ?? null;
                                                                    $hasPlanned = $planned !== null && $planned !== '';
                                                                    $hasActual = $actual !== null && $actual !== '';
                                                                    /* weeks without a date are disabled in the form */
                                                                    $isOff = empty($week['date']) && !$hasPlanned && !$hasActual;
                                                                @endphp

                                                                <td class="{{ $isOff ? 'off' : '' }} {{ $hasPlanned ? 'filled' : '' }}">{!! $hasPlanned ? e($planned) : '&nbsp;' !!}</td>
                                                                <td class="{{ $isOff ? 'off' : '' }} {{ $hasActual ? 'filled' : '' }}">{!! $hasActual ? e($actual) : '&nbsp;' !!}</td>
                                                            @endforeach
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endforeach
                            @else
                                <div class="empty-grid">No records available.</div>
                            @endif
                        </div>

                    {{-- ================= field section ================= --}}
                    @elseif(!empty($section['fields']))
                        <div class="section">
                            {{-- skip the section heading when it only repeats the tab title --}}
                            @if(strcasecmp(trim($sectionTitle), trim($tabTitle)) !== 0)
                                <div class="section-heading">{{ $sectionTitle }}</div>
                            @endif

                            <table class="data-table">
                                <tbody>
                                    @foreach(array_chunk($section['fields'], 2) as $fieldPair)
                                        <tr>
                                            @foreach($fieldPair as $field)
                                                @php
                                                    $value = $field['value'] ?? null;
                                                    $fieldKey = strtolower((string) ($field['key'] ?? ''));
                                                    $fieldLabel = strtolower((string) ($field['label'] ?? ''));
                                                    $isAttachment = str_contains($fieldKey, 'attachment') || str_contains($fieldLabel, 'attachment');
                                                @endphp

                                                <td class="field-name">{{ $field['label'] ?? '-' }}</td>
                                                <td class="field-value">
                                                    @if($isAttachment)
                                                        @if(is_array($value) && !empty($value))
                                                            @foreach($value as $attachment)
                                                                @if(is_array($attachment))
                                                                    @php
                                                                        $attachmentName = $attachment['name'] ?? $attachment['file_name'] ?? 'Attachment';
                                                                        $attachmentUrl = $attachment['url'] ?? null;
                                                                    @endphp
                                                                    <div class="attachment-item">
                                                                        @if($attachmentUrl)
                                                                            <a href="{{ $attachmentUrl }}" target="_blank" class="attachment-link">{{ $attachmentName }}</a>
                                                                        @else
                                                                            {{ $attachmentName }}
                                                                        @endif
                                                                    </div>
                                                                @elseif(is_string($attachment) && $attachment !== '')
                                                                    <div class="attachment-item">{{ $attachment }}</div>
                                                                @endif
                                                            @endforeach
                                                        @else
                                                            -
                                                        @endif
                                                    @elseif(is_array($value))
                                                        @if(isset($value['name']))
                                                            {{ $value['name'] }}
                                                        @elseif(empty($value))
                                                            -
                                                        @else
                                                            {{ json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
                                                        @endif
                                                    @else
                                                        {{ $value !== null && $value !== '' ? $value : '-' }}
                                                    @endif
                                                </td>
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
                    @endif
                @endforeach
            @endforeach

        </div>

    </div>{{-- /page-wrap --}}

</body>

</html>