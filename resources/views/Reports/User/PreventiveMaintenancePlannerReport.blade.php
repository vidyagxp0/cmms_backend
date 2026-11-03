<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>{{ $header['report_title'] ?? 'Preventive Maintenance Planner' }}</title>
    <style>
        /* ---------- page ---------- */
        /* top / bottom margin here; left / right margin is on .page-wrap because the @page side margins are ignored */
        @page {
            size: A4 landscape;
            margin: 8mm 0 16mm 0;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            margin: 0;
            padding: 0;
            background: #fff;
            color: #2a3a4d;
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5px;
            line-height: 1.35;
        }

        .page-wrap {
            width: auto;
            margin: 0;
            padding: 0 8mm;
        }

        /* ---------- header (first page only) ---------- */
        .company-box {
            border: 1px solid #d3dfec;
            border-bottom: 0;
            background: #f4f8fc;
            text-align: center;
            padding: 13px 10px;
        }

        .company-name {
            color: #1f3f66;
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .report-banner {
            background: #254870;
            color: #fff;
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: .6px;
            text-transform: uppercase;
            padding: 10px;
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
            font-size: 7px;
        }

        /* ---------- section headings / field tables ---------- */
        .section {
            margin: 0 0 16px;
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

        /* ---------- planner grid (as in the form): Planned / Executed rows, one date per month ---------- */
        .planner-caption {
            color: #1f3f66;
            font-size: 8.5px;
            font-weight: bold;
            margin: 0 0 6px;
            padding: 0 2px;
            page-break-after: avoid;
        }

        /* every block holds one page of equipment: the next block always starts on a new page */
        .planner-chunk {
            margin: 0 0 12px;
        }

        .planner-chunk.planner-next {
            page-break-before: always;
        }

        .planner-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .planner-table th,
        .planner-table td {
            border: 1px solid #c5d3e3;
            text-align: center;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .planner-table th {
            background: #254870;
            color: #fff;
            font-size: 6px;
            line-height: 1.15;
            font-weight: bold;
            padding: 5px 2px;
            text-transform: uppercase;
        }

        .planner-table th.month-head {
            text-transform: none;
            letter-spacing: 0;
        }

        .planner-table td {
            background: #fff;
            color: #2a3a4d;
            font-size: 6px;
            line-height: 1.1;
            padding: 3px 1px;
        }

        /* Sr. No., equipment name, code, location and remark span the Planned + Executed rows */
        .planner-table td.info-cell {
            background: #f4f8fc;
            color: #1f3f66;
            padding: 3px 3px;
        }

        .planner-table td.info-cell.left {
            text-align: left;
        }

        .planner-table td.label-cell {
            font-weight: bold;
        }

        .planner-table td.label-planned {
            background: #eaf1f9;
            color: #1f3f66;
        }

        .planner-table td.label-executed {
            background: #eef7f1;
            color: #247447;
        }

        .planner-table td.date-planned.has-value {
            background: #eef5fb;
            color: #1f3f66;
            font-weight: bold;
        }

        .planner-table td.date-executed.has-value {
            background: #e9f6ee;
            color: #247447;
            font-weight: bold;
        }

        .planner-table tbody,
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

        @foreach($tabs ?? [] as $tab)
            @php $tabTitle = $tab['tab_title'] ?? '-'; @endphp
            <div class="section-heading">{{ $tabTitle }}</div>

            @foreach($tab['sections'] ?? [] as $section)
                @php
                    $sectionType = $section['type'] ?? 'fields';
                    $sectionTitle = $section['title'] ?? null;
                @endphp

                {{-- ================= planner grid ================= --}}
                @if($sectionType === 'planner')
                    @if(!empty($section['tables']))
                        @php
                            $plannerMonths = $section['months'] ?? [];

                            /* column widths (%): the header cells carry the widths */
                            $srWidth = 3;
                            $nameWidth = 14;
                            $codeWidth = 8;
                            $locationWidth = 7;
                            $labelWidth = 7;
                            $remarkWidth = 7;
                            $fixedWidth = $srWidth + $nameWidth + $codeWidth + $locationWidth + $labelWidth + $remarkWidth;
                            $monthWidth = round((100 - $fixedWidth) / max(count($plannerMonths), 1), 3);
                        @endphp

                        @foreach($section['tables'] as $tableIndex => $table)
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
                                    <thead>
                                        <tr>
                                            <th style="width: {{ $srWidth }}%">Sr. No.</th>
                                            <th style="width: {{ $nameWidth }}%">Equipment Name</th>
                                            <th style="width: {{ $codeWidth }}%">Equipment Code</th>
                                            <th style="width: {{ $locationWidth }}%">Location</th>
                                            <th style="width: {{ $labelWidth }}%">Calibration Dates</th>
                                            @foreach($plannerMonths as $month)
                                                <th class="month-head" style="width: {{ $monthWidth }}%">{{ $month['name'] }}</th>
                                            @endforeach
                                            <th style="width: {{ $remarkWidth }}%">Remark</th>
                                        </tr>
                                    </thead>

                                    {{-- every equipment has a Planned row and an Executed row --}}
                                    @foreach($table['equipment'] ?? [] as $equipmentKey => $equipment)
                                        <tbody>
                                            <tr>
                                                <td class="info-cell" rowspan="2">{{ str_pad($equipmentKey + 1, 2, '0', STR_PAD_LEFT) }}</td>
                                                <td class="info-cell left" rowspan="2">{{ ($equipment['name'] ?? null) ?: '-' }}</td>
                                                <td class="info-cell left" rowspan="2">{{ ($equipment['code'] ?? null) ?: '-' }}</td>
                                                <td class="info-cell left" rowspan="2">{{ ($equipment['location'] ?? null) ?: '-' }}</td>

                                                <td class="label-cell label-planned">Planned</td>
                                                @foreach($plannerMonths as $month)
                                                    @php $plannedDate = $equipment['planned'][$month['key']] ?? null; @endphp
                                                    <td class="date-planned {{ $plannedDate ? 'has-value' : '' }}">{!! $plannedDate ? e($plannedDate) : '&nbsp;' !!}</td>
                                                @endforeach

                                                <td class="info-cell left" rowspan="2">{{ ($equipment['remark'] ?? null) ?: '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="label-cell label-executed">Executed</td>
                                                @foreach($plannerMonths as $month)
                                                    @php $executedDate = $equipment['executed'][$month['key']] ?? null; @endphp
                                                    <td class="date-executed {{ $executedDate ? 'has-value' : '' }}">{!! $executedDate ? e($executedDate) : '&nbsp;' !!}</td>
                                                @endforeach
                                            </tr>
                                        </tbody>
                                    @endforeach
                                </table>
                            </div>
                        @endforeach
                    @else
                        <div class="section">
                            <div class="section-heading">{{ $sectionTitle }}</div>
                            <div class="empty-grid">No records available.</div>
                        </div>
                    @endif

                {{-- ================= field section (label | value | label | value) ================= --}}
                @elseif(!empty($section['fields']))
                    <div class="section">
                        {{-- a section without a title (comments / review lines) is printed without a heading --}}
                        @if(!empty($sectionTitle) && strcasecmp(trim($sectionTitle), trim($tabTitle)) !== 0)
                            <div class="section-heading">{{ $sectionTitle }}</div>
                        @endif

                        <table class="data-table">
                            <tbody>
                                @foreach(array_chunk($section['fields'], 2) as $fieldPair)
                                    <tr>
                                        @foreach($fieldPair as $field)
                                            @php
                                                $value = $field['value'] ?? null;
                                                $isAttachment = str_contains(strtolower((string) ($field['key'] ?? '')), 'attachment')
                                                    || str_contains(strtolower((string) ($field['label'] ?? '')), 'attachment');
                                            @endphp

                                            <td class="field-name">{{ $field['label'] ?? '-' }}</td>
                                            <td class="field-value">
                                                @if($isAttachment && is_array($value) && !empty($value))
                                                    @foreach($value as $attachment)
                                                        <div class="attachment-item">
                                                            @if(!empty($attachment['url']))
                                                                <a href="{{ $attachment['url'] }}" target="_blank" class="attachment-link">{{ $attachment['name'] ?? 'Attachment' }}</a>
                                                            @else
                                                                {{ $attachment['name'] ?? 'Attachment' }}
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                @elseif(is_array($value))
                                                    -
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
</body>

</html>