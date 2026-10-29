<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>{{ $header['report_title'] ?? 'Field Instrument Calibration Addendum' }}</title>

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
        .section-heading, .grid-title {
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
        .data-table, .grid-table {
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

        /* ---------- grids ---------- */
        .grid-section { margin: 0 0 12px 0; }
        .grid-table th {
            background: #254870;
            color: #ffffff;
            border: 1px solid #c5d3e3;
            font-size: 7.5px;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            padding: 11px 4px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .grid-table td {
            background: #ffffff;
            color: #2a3a4d;
            border: 1px solid #d3dfec;
            font-size: 7px;
            text-align: center;
            vertical-align: middle;
            padding: 9px 4px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .grid-table tbody tr:nth-child(even) td { background: #f4f8fc; }
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
        <div class="report-banner">{{ $header['report_title'] ?? 'Field Instrument Calibration Addendum' }}</div>

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

                    {{-- ================= grid section ================= --}}
                    @if($sectionType === 'grid')
                        <div class="section grid-section">
                            <div class="grid-title">{{ $sectionTitle }}</div>

                            @if(!empty($section['rows']) && !empty($section['columns']))
                                <table class="grid-table">
                                    <thead>
                                        <tr>
                                            <th style="width: {{ $section['sr_width'] ?? 4 }}%">Sr. No.</th>
                                            @foreach($section['columns'] as $column)
                                                <th style="width: {{ $column['width'] ?? 6 }}%">{{ $column['label'] }}</th>
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