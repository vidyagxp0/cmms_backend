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
        }

        .meta-table,
        .data-table,
        .grid-table,
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .meta-table {
            margin: 9px 0 11px;
        }

        .meta-table td {
            width: 50%;
            border: 1px solid #d3dfec;
            background: #f4f8fc;
            padding: 8px;
            font-size: 7.5px;
        }

        .meta-table strong {
            color: #1f3f66;
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
            margin: 0 0 9px;
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

        .grid-table tr,
        .data-table tr {
            page-break-inside: avoid;
        }

        thead {
            display: table-header-group;
        }

        .review-row {
            margin-top: 7px;
            page-break-inside: avoid;
        }

        .review-table {
            margin: 0;
        }

        .review-table td {
            padding: 7px 6px;
        }

        .attachment-item {
            margin: 0 0 2px;
        }

        .attachment-link {
            color: #246da8;
            text-decoration: underline;
            overflow-wrap: break-word;
        }

        .empty-grid {
            color: #5b6b7e;
            background: #f4f8fc;
            border: 1px dashed #c5d3e3;
            padding: 9px;
            text-align: center;
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

        .esign-note {
            margin-top: 7px;
            padding: 7px 9px;
            background: #f3f6fa;
            border: 1px solid #d8e0e9;
            color: #52647a;
            font-size: 7.5px;
        }

        .esign-section {
            padding: 20px 24px 0 24px;
            /* top right bottom left */
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
        <table class="meta-table">
            <tr>
                <td><strong>Document Number:</strong> {{ $header['document_number'] ?? '-' }}</td>
                <td><strong>Document Type:</strong> {{ $header['document_type'] ?? '-' }}</td>
            </tr>
        </table>

        @foreach($tabs ?? [] as $tab)
            @foreach($tab['sections'] ?? [] as $section)
                @if(($section['type'] ?? 'fields') === 'grid')
                    <div class="grid-section">
                        <div class="grid-title">{{ $section['title'] ?? 'Preventive Maintenance Planner' }}</div>
                        @if(!empty($section['rows']) && !empty($section['columns']))
                            <table class="grid-table">
                                <thead>
                                    <tr>
                                        <th style="width:{{ $section['sr_width'] ?? 4 }}%">Sr. No.</th>
                                        @foreach($section['columns'] as $column)
                                            <th style="width:{{ $column['width'] ?? 8 }}%">{{ $column['label'] }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($section['rows'] as $rowIndex => $row)
                                        <tr>
                                            <td>{{ $rowIndex + 1 }}</td>
                                            @foreach($section['columns'] as $column)
                                                <td>{{ $row[$column['key']] ?? '-' }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="empty-grid">No preventive maintenance records available.</div>
                        @endif
                    </div>
                @elseif(!empty($section['fields']))
                    <div class="section">
                        <div class="section-heading">{{ $section['title'] ?? '-' }}</div>
                        <table class="data-table">
                            <tbody>
                                @foreach(array_chunk($section['fields'], 2) as $fieldPair)
                                    <tr>
                                        @foreach($fieldPair as $field)
                                            @php
                                                $value = $field['value'] ?? null;
                                                $key = strtolower((string) ($field['key'] ?? ''));
                                                $label = strtolower((string) ($field['label'] ?? ''));
                                                $isAttachment = str_contains($key, 'attachment') || str_contains($label, 'attachment');
                                            @endphp
                                            <td class="field-name">{{ $field['label'] ?? '-' }}</td>
                                            <td class="field-value">
                                                @if($isAttachment)
                                                    @if(is_array($value) && count($value))
                                                        @foreach($value as $attachment)
                                                            @if(is_array($attachment))
                                                                @php
                                                                    $attachmentName = $attachment['name'] ?? $attachment['file_name'] ?? 'Attachment';
                                                                    $attachmentUrl = $attachment['url'] ?? null;
                                                                @endphp
                                                                <div class="attachment-item">
                                                                    @if($attachmentUrl)<a class="attachment-link"
                                                                        href="{{ $attachmentUrl }}">{{ $attachmentName }}</a>
                                                                    @else{{ $attachmentName }}@endif
                                                                </div>
                                                            @elseif(is_string($attachment) && $attachment !== '')
                                                                <div class="attachment-item">{{ $attachment }}</div>
                                                            @endif
                                                        @endforeach
                                                    @else - @endif
                                                @elseif(is_array($value))
                                                    {{ empty($value) ? '-' : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
                                                @else
                                                    {{ $value !== null && $value !== '' ? $value : '-' }}
                                                @endif
                                            </td>
                                        @endforeach
                                        @if(count($fieldPair) === 1)
                                            <td class="field-name"></td>
                                        <td class="field-value"></td>@endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endforeach
        @endforeach

        @foreach($reviewRows ?? [] as $reviewRow)
        <div class="review-row">
            <table class="data-table review-table">
                <tr>
                    <td class="field-name">{{ $reviewRow[0]['label'] ?? '-' }}</td>
                    <td class="field-value">{{ $reviewRow[0]['value'] ?? '-' }}</td>
                    <td class="field-name">{{ $reviewRow[1]['label'] ?? '-' }}</td>
                    <td class="field-value">
                        @php($attachments = $reviewRow[1]['value'] ?? [])
                        @if(is_array($attachments) && count($attachments))
                            @foreach($attachments as $attachment)
                                @if(is_array($attachment))
                                    @if(!empty($attachment['url']))<a class="attachment-link"
                                        href="{{ $attachment['url'] }}">{{ $attachment['name'] ?? $attachment['file_name'] ?? 'Attachment' }}</a>
                                    @else{{ $attachment['name'] ?? $attachment['file_name'] ?? 'Attachment' }}@endif
                                @else{{ $attachment }}@endif
                            @endforeach
                        @else{{ is_string($attachments) && $attachments !== '' ? $attachments : '-' }}@endif
                    </td>
                </tr>
            </table>
        </div>
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

                        <td>
                            <span class="esign-status">
                                {{ $esign['user_name'] ?? '-' }}
                            </span>
                        </td>

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