<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>{{ $header['report_title'] ?? 'Preventive Maintenance Report' }}</title>
    <style>
        {!! file_get_contents(resource_path('views/Reports/CSS/Report.css')) !!}
        @page {
            size: A4 portrait;
            margin: 10mm 0 15mm 0;
        }

        html,
        body {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
            color: #2a3a4d !important;
            font-family: DejaVu Sans, sans-serif !important;
            font-size: 8px !important;
            line-height: 1.3 !important;
        }

        .page-wrap {
            padding: 0 5.5mm;
        }

        .company-box {
            border: 1px solid #d3dfec;
            border-bottom: 0;
            background: #f4f8fc;
            text-align: center;
            padding: 13px 10px;
        }

        .company-name {
            color: #1f3f66;
            font-size: 14px;
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
            padding: 11px 10px;
            margin-bottom: 11px;
        }

        .data-table,
        .grid-table,
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .section {
            margin: 0 0 10px;
        }

        .section-heading,
        .grid-title {
            background: #eaf1f9;
            color: #1f3f66;
            border: 1px solid #d3e1f0;
            border-left: 4px solid #3b78b3;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: .7px;
            text-transform: uppercase;
            margin: 0 0 7px;
            padding: 8px;
            page-break-after: avoid;
        }

        .data-table {
            margin: 0 0 6px;
        }

        .data-table td,
        .grid-table th,
        .grid-table td {
            border: 1px solid #d3dfec;
            padding: 6px 5px;
            vertical-align: middle;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .data-table td {
            font-size: 7.5px;
        }

        .field-name {
            width: 24%;
            background: #f4f8fc;
            color: #1f3f66;
            font-weight: bold;
        }

        .field-value {
            width: 26%;
            background: #fff;
        }

        .grid-section {
            margin: 0 0 11px;
        }

        .grid-table th {
            background: #254870;
            color: #fff;
            font-size: 7px;
            font-weight: bold;
            text-align: center;
        }

        .grid-table td {
            background: #fff;
            color: #2a3a4d;
            font-size: 6.8px;
            text-align: center;
        }

        .grid-table tbody tr:nth-child(even) td {
            background: #f4f8fc;
        }

        /* checklist: long texts read better left aligned */
        .checklist-table td {
            text-align: left;
            vertical-align: top;
            line-height: 1.45;
        }

        .checklist-table td.sr-cell {
            text-align: center;
        }

        .grid-table tr,
        .data-table tr {
            page-break-inside: avoid;
        }

        thead {
            display: table-header-group;
        }

        .attachment-item {
            margin: 0 0 2px;
        }

        .attachment-link {
            color: #246da8;
            text-decoration: underline;
            overflow-wrap: break-word;
        }

        footer {
            position: fixed;
            left: 5.5mm;
            right: 5.5mm;
            bottom: -11mm;
            height: 10mm;
        }

        .footer-wrapper {
            border-top: 1px solid #d3dfec;
            padding-top: 4px;
        }

        .footer-table td {
            border: 0;
            padding: 0;
            vertical-align: top;
            font-size: 7px;
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
        }

        .footer-meta {
            font-size: 6.5px;
            color: #5b6b7e;
        }

        .esign-section {
            padding: 20px 24px 0 24px;
        }

        .section-title {
            background: #eaf1f8;
            color: #1e4268;
            border-left: 4px solid #3674a5;
            border-top: 1px solid #d4e0ed;
            border-right: 1px solid #d4e0ed;
            border-bottom: 1px solid #d4e0ed;
            text-transform: uppercase;
            padding: 8px 10px;
            margin-top: 12px;
            margin-bottom: 10px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.4px;
        }

        .esign-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 8px;
            margin-bottom: 12px;
        }

        .esign-table th,
        .esign-table td {
            border: 1px solid #d3dce7;
            padding: 8px 9px;
            text-align: left;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .esign-table th {
            background: #24476b;
            color: #fff;
            font-size: 8px;
            font-weight: bold;
        }

        .esign-table td {
            background: #fff;
            color: #263449;
            font-size: 8px;
        }

        .esign-table tbody tr:nth-child(even) td {
            background: #f5f8fc;
        }

        .esign-status {
            color: #23734d;
            font-weight: bold;
        }

        .empty-message {
            color: #5b6b7e;
            background: #f4f8fc;
            text-align: center;
        }
    </style>
</head>

<body>
    <footer>
        <div class="footer-wrapper">
            <table class="footer-table">
                <tr>
                    <td class="footer-left">
                        <div class="footer-company">{{ $header['company_name'] ?? 'Shilpa Medicare Limited' }}</div>
                        <div class="footer-meta">{{ $header['report_title'] ?? 'Preventive Maintenance Report' }} |
                            Controlled Document</div>
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
        <div class="company-box">
            <div class="company-name">{{ $header['company_name'] ?? 'Shilpa Medicare Limited' }}</div>
        </div>
        <div class="report-banner">{{ $header['report_title'] ?? 'Preventive Maintenance Report' }}</div>

        @foreach($sections ?? [] as $section)
            {{-- checklist: Sr. No. + one column per checklist column --}}
            @if(($section['type'] ?? 'fields') === 'checklist')
                <div class="grid-section">
                    <div class="section-heading">{{ $section['title'] }}</div>
                    <table class="grid-table checklist-table">
                        <thead>
                            <tr>
                                @if($section['show_sr'])
                                    <th style="width:{{ $section['sr_width'] }}%">Sr. No.</th>
                                @endif
                                @foreach($section['columns'] as $column)
                                    <th style="width:{{ $column['width'] }}%">{{ $column['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($section['rows'] as $rowIndex => $row)
                                <tr>
                                    @if($section['show_sr'])
                                        <td class="sr-cell">{{ $rowIndex + 1 }}</td>
                                    @endif
                                    @foreach($section['columns'] as $column)
                                        <td>{!! nl2br(e($row[$column['label']] ?? '-')) !!}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            {{-- field tables: label | value | label | value (heading only when the section has a title) --}}
            @else
                <div class="section">
                    @if(!empty($section['title']))
                        <div class="section-heading">{{ $section['title'] }}</div>
                    @endif
                    <table class="data-table">
                        <tbody>
                            @foreach(array_chunk($section['fields'], 2) as $fieldPair)
                                <tr>
                                    @foreach($fieldPair as $field)
                                        @php
                                            $value = $field['value'] ?? null;
                                            $isAttachment = str_contains(strtolower((string) ($field['key'] ?? '')), 'attachment');
                                        @endphp
                                        <td class="field-name">{{ $field['label'] ?? '-' }}</td>
                                        <td class="field-value">
                                            @if($isAttachment)
                                                @forelse((array) $value as $attachment)
                                                    <div class="attachment-item">
                                                        {{-- opens the file in a new tab --}}
                                                        @if(!empty($attachment['url']))
                                                            <a class="attachment-link" href="{{ $attachment['url'] }}" target="_blank" rel="noopener">{{ $attachment['name'] }}</a>
                                                        @else
                                                            {{ $attachment['name'] }}
                                                        @endif
                                                    </div>
                                                @empty
                                                    -
                                                @endforelse
                                            @else
                                                {{ $value }}
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
    </div>

    <div class="esign-section">
        <div class="section-title">Electronic Signature History</div>

        <table class="esign-table">
            <thead>
                <tr>
                    <th style="width: 5%;">Sr. No.</th>
                    <th style="width: 17%;">Signed By</th>
                    <th style="width: 18%;">Activity</th>
                    <th style="width: 15%;">From Stage</th>
                    <th style="width: 15%;">To Stage</th>
                    <th style="width: 18%;">Comment</th>
                    <th style="width: 12%;">Date & Time</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($esign_history ?? [] as $index => $esign)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td><span class="esign-status">{{ $esign['user_name'] ?? '-' }}</span></td>
                        <td>{{ $esign['activity_name'] ?? '-' }}</td>
                        <td>{{ $esign['from_stage'] ?? '-' }}</td>
                        <td>{{ $esign['to_stage'] ?? '-' }}</td>
                        <td>{{ $esign['comment'] ?? '-' }}</td>
                        <td>{{ $esign['performed_at'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty-message">
                            No electronic signature history available.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>

</html>