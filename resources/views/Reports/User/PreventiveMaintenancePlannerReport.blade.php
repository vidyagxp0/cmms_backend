<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>{{ $header['report_title'] ?? 'Preventive Maintenance Planner' }}</title>
    <style>
        {!! file_get_contents(resource_path('views/Reports/CSS/PreventiveReport.css')) !!}

        /* ---------- page ---------- */
        /* top / bottom margin here; left / right margin is on .page-wrap because the @page side margins are ignored */
        @page {
            size: A4 landscape;
            margin: 8mm 0 16mm 0;
        }

        html,
        body {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
            color: #2a3a4d !important;
            font-family: DejaVu Sans, sans-serif !important;
            font-size: 8.5px !important;
            line-height: 1.35 !important;
        }

        .page-wrap {
            width: auto;
            margin: 0;
            padding: 0 8mm;
        }

        .report-content {
            width: 100%;
            margin: 0;
            padding: 0;
        }

        /* ---------- header (first page only) ---------- */
        .company-box {
            border: 1px solid #d3dfec;
            border-bottom: 0;
            background: #f4f8fc;
            text-align: center;
            padding: 13px 10px;
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
            color: #fff;
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: .6px;
            text-transform: uppercase;
            padding: 10px 10px;
            margin: 0;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 10px 0 16px;
        }

        .meta-table td {
            width: 50%;
            border: 1px solid #d3dfec;
            background: #f4f8fc;
            color: #2a3a4d;
            font-size: 8.5px;
            padding: 8px 10px;
            vertical-align: middle;
        }

        .meta-table td strong {
            color: #1f3f66;
            font-weight: bold;
        }

        /* ---------- footer (every page) ---------- */
        footer {
            position: fixed;
            left: 8mm;
            right: 8mm;
            bottom: -12mm;
            height: 10mm;
        }

        .footer-wrapper {
            border-top: 1px solid #d3dfec;
            padding-top: 4px;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-table td {
            border: 0;
            padding: 0;
            vertical-align: top;
            font-size: 7.5px;
            color: #5b6b7e;
        }

        .footer-left {
            text-align: left;
        }

        .footer-right {
            text-align: right;
        }

        .footer-company {
            color: #1f3f66;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .footer-meta {
            color: #5b6b7e;
            font-size: 7px;
        }

        /* ---------- section headings / field tables ---------- */
        .section {
            margin: 0 0 16px;
            padding: 0;
            page-break-inside: avoid;
        }

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
            margin: 0 0 10px;
            padding: 8px 10px;
            page-break-after: avoid;
        }

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
            padding: 7px 9px;
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
            background: #fff;
            color: #2a3a4d;
        }

        /* ---------- planner grid: one row per equipment, all months across ---------- */
        .planner-caption {
            color: #1f3f66;
            font-size: 8.5px;
            font-weight: bold;
            margin: 0 0 7px;
            padding: 0 2px;
            page-break-after: avoid;
        }

        .planner-chunk {
            width: 100%;
            margin: 0 0 16px;
            padding: 0;
            page-break-inside: auto;
        }

        .planner-chunk.planner-next {
            page-break-before: always;
        }

        .planner-table {
            width: 100%;
            margin: 0;
            padding: 0;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: auto;
        }

        .planner-table th,
        .planner-table td {
            border: 1px solid #c5d3e3;
            text-align: center;
            vertical-align: middle;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .planner-table th {
            background: #254870;
            color: #fff;
            font-size: 6px;
            line-height: 1.15;
            font-weight: bold;
            padding: 4px 1px;
        }

        .planner-table th.month-head {
            font-size: 6.5px;
            padding: 4px 0;
            letter-spacing: .3px;
        }

        .planner-table th.week-head {
            background: #4a7aab;
            font-size: 5.5px;
            padding: 2px 0;
            font-weight: normal;
        }

        .planner-table th.day-head {
            background: #3a6a9c;
            font-size: 5.5px;
            padding: 2px 0;
            font-weight: normal;
        }

        .planner-table td {
            background: #fff;
            color: #2a3a4d;
            font-size: 5.5px;
            line-height: 1.2;
            padding: 5px 1px;
        }

        .planner-table td.equipment-cell {
            background: #f4f8fc;
            color: #1f3f66;
            font-size: 6.5px;
            text-align: left;
            padding: 5px 4px;
        }

        .planner-table td.sr-cell {
            background: #f4f8fc;
            color: #1f3f66;
            font-size: 6.5px;
            padding: 5px 1px;
        }

        .planner-table td.schedule-cell {
            height: 16px;
        }

        .planner-table td.has-value {
            background: #eef5fb;
        }

        .planner-table .planned {
            color: #1f3f66;
            font-weight: bold;
        }

        .planner-table .actual {
            color: #247447;
            font-weight: bold;
        }

        .planner-table tr,
        .data-table tr {
            page-break-inside: avoid;
        }

        thead {
            display: table-header-group;
        }

        .empty-grid {
            color: #5b6b7e;
            background: #f4f8fc;
            border: 1px dashed #c5d3e3;
            padding: 12px;
            font-size: 8.5px;
            text-align: center;
        }

        /* ---------- attachments ---------- */
        .attachment-item {
            margin: 0 0 2px;
            padding: 0;
        }

        .attachment-link {
            color: #246da8;
            text-decoration: underline;
            word-wrap: break-word;
        }
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
                        <div class="footer-meta">{{ $header['report_title'] ?? '-' }} &nbsp;|&nbsp; Controlled Document
                        </div>
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

        <div class="report-content">
            @foreach($tabs ?? [] as $tab)
                @php $tabTitle = $tab['tab_title'] ?? '-'; @endphp
                <div class="section-heading">{{ $tabTitle }}</div>

                @foreach($tab['sections'] ?? [] as $section)
                    @php
                        $sectionType = $section['type'] ?? 'fields';
                        $sectionTitle = $section['title'] ?? '-';
                    @endphp

                    {{-- ================= planner grid section ================= --}}
                    @if($sectionType === 'planner')
                        @if(!empty($section['tables']) && !empty($section['weeks']))
                            @php
                                /* months with their week columns */
                                $monthGroups = [];
                                foreach ($section['weeks'] as $weekRow) {
                                    $groupKey = $weekRow['month_key'];
                                    if (!isset($monthGroups[$groupKey])) {
                                        $monthGroups[$groupKey] = ['name' => $weekRow['month_name'], 'weeks' => []];
                                    }
                                    $monthGroups[$groupKey]['weeks'][] = $weekRow;
                                }

                                /* column widths (%): the header cells carry the widths, dompdf ignores <col> widths */
                                $srWidth = 2;
                                $nameWidth = 8;
                                $codeWidth = 6;
                                $locationWidth = 7;
                                $remarkWidth = 6;
                                $fixedWidth = $srWidth + $nameWidth + $codeWidth + $locationWidth + $remarkWidth;
                                $weekCount = max(count($section['weeks']), 1);
                                $weekWidth = round((100 - $fixedWidth) / $weekCount, 3);
                            @endphp

                            @foreach($section['tables'] as $tableIndex => $table)
                                @php $tableEquipment = $table['equipment'] ?? []; @endphp

                                <div class="planner-chunk {{ $tableIndex > 0 ? 'planner-next' : '' }}">
                                    @if($tableIndex === 0)
                                        <div class="section-heading">{{ $sectionTitle }}</div>
                                    @endif

                                    <div class="planner-caption">
                                        Preventive Maintenance Planner
                                        @if(!empty($section['year']))
                                            &nbsp;|&nbsp; For the Year {{ $section['year'] }}
                                        @endif
                                        @if(count($section['tables']) > 1)
                                            &nbsp;|&nbsp; Equipment {{ $table['from'] }} - {{ $table['to'] }} of {{ $section['total'] }}
                                        @endif
                                    </div>

                                    <table class="planner-table">
                                        <colgroup>
                                            <col style="width: {{ $srWidth }}%">
                                            <col style="width: {{ $nameWidth }}%">
                                            <col style="width: {{ $codeWidth }}%">
                                            <col style="width: {{ $locationWidth }}%">
                                            @foreach($monthGroups as $month)
                                                @foreach($month['weeks'] as $weekRow)
                                                    <col style="width: {{ $weekWidth }}%">
                                                @endforeach
                                            @endforeach
                                            <col style="width: {{ $remarkWidth }}%">
                                        </colgroup>

                                        <thead>
                                            {{-- month row --}}
                                            <tr>
                                                <th rowspan="3" style="width: {{ $srWidth }}%">Sr.</th>
                                                <th rowspan="3" style="width: {{ $nameWidth }}%">Equipment Name</th>
                                                <th rowspan="3" style="width: {{ $codeWidth }}%">Equipment Code</th>
                                                <th rowspan="3" style="width: {{ $locationWidth }}%">Location</th>
                                                @foreach($monthGroups as $month)
                                                    <th class="month-head" colspan="{{ count($month['weeks']) }}">{{ $month['name'] }}</th>
                                                @endforeach
                                                <th rowspan="3" style="width: {{ $remarkWidth }}%">Remarks</th>
                                            </tr>

                                            {{-- week number row --}}
                                            <tr>
                                                @foreach($monthGroups as $month)
                                                    @foreach($month['weeks'] as $weekRow)
                                                        <th class="week-head" style="width: {{ $weekWidth }}%">W{{ $weekRow['number'] }}</th>
                                                    @endforeach
                                                @endforeach
                                            </tr>

                                            {{-- day of month row --}}
                                            <tr>
                                                @foreach($monthGroups as $month)
                                                    @foreach($month['weeks'] as $weekRow)
                                                        <th class="day-head" style="width: {{ $weekWidth }}%">{{ $weekRow['date'] ?: '-' }}</th>
                                                    @endforeach
                                                @endforeach
                                            </tr>
                                        </thead>

                                        <tbody>
                                            @foreach($tableEquipment as $equipmentKey => $equipment)
                                                <tr>
                                                    <td class="sr-cell">{{ str_pad($equipmentKey + 1, 2, '0', STR_PAD_LEFT) }}</td>
                                                    <td class="equipment-cell">{{ ($equipment['name'] ?? null) ?: '-' }}</td>
                                                    <td class="equipment-cell">{{ ($equipment['code'] ?? null) ?: '-' }}</td>
                                                    <td class="equipment-cell">{{ ($equipment['location'] ?? null) ?: '-' }}</td>

                                                    @foreach($monthGroups as $monthKey => $month)
                                                        @foreach($month['weeks'] as $weekRow)
                                                            @php
                                                                $cell = $equipment['schedule'][$monthKey][$weekRow['index']] ?? [];
                                                                $planned = $cell['planned'] ?? null;
                                                                $actual = $cell['actual'] ?? null;
                                                                $hasPlanned = $planned !== null && $planned !== '';
                                                                $hasActual = $actual !== null && $actual !== '';
                                                            @endphp
                                                            <td class="schedule-cell {{ ($hasPlanned || $hasActual) ? 'has-value' : '' }}">
                                                                @if($hasPlanned)
                                                                    <span class="planned">P:{{ $planned }}</span>
                                                                @endif
                                                                @if($hasActual)
                                                                    @if($hasPlanned)<br>@endif
                                                                    <span class="actual">A:{{ $actual }}</span>
                                                                @endif
                                                            </td>
                                                        @endforeach
                                                    @endforeach

                                                    <td class="equipment-cell">{{ ($equipment['remark'] ?? null) ?: '-' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endforeach
                        @else
                            <div class="section">
                                <div class="section-heading">{{ $sectionTitle }}</div>
                                <div class="empty-grid">No records available.</div>
                            </div>
                        @endif

                        {{-- ================= field section ================= --}}
                    @elseif(!empty($section['fields']))
                        <div class="section">
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
                                                                            <a href="{{ $attachmentUrl }}" target="_blank"
                                                                                class="attachment-link">{{ $attachmentName }}</a>
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
    </div>
</body>

</html>