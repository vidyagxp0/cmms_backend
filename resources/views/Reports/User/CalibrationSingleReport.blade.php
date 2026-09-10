<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>{{ $header['report_title'] }}</title>

    <style>
        {!! file_get_contents(resource_path('views/Reports/CSS/Report.css')) !!}

        .report-content {
            width: 100%;
            margin: 0;
            padding: 0;
        }

        .tab-section {
            width: 100%;
            margin: 10px 0 0 0;
            padding: 0;
            page-break-before: auto;
            page-break-after: auto;
            page-break-inside: auto;
        }

        .report-title {
            margin: 10px 0 5px 0;
            padding: 0;
            page-break-after: avoid;
        }

        .section {
            width: 100%;
            margin: 0 0 10px 0;
            padding: 0;
            page-break-before: auto;
            page-break-after: auto;
            page-break-inside: auto;
        }

        .section-heading {
            margin: 0;
            padding: 6px 8px;
            page-break-after: avoid;
        }

        .section-header-block {
            page-break-after: avoid;
        }

        .data-table {
            width: 100%;
            margin: 10px 0 0 0;
            padding: 0;
            border-collapse: collapse;
            page-break-before: auto;
            page-break-after: auto;
            page-break-inside: auto;
        }

        .data-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        .data-table td,
        .data-table th {
            page-break-inside: avoid;
        }

        .field-value {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .array-value {
            margin: 0;
            padding: 0;
        }

        .array-item {
            margin: 0 0 2px 0;
            padding: 0;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .grid-section {
            margin-top: 10px;
        }

        .grid-group {
            margin: 0 0 8px 0;
            padding: 0;
            page-break-inside: auto;
        }

        .grid-table {
            width: 100%;
            margin-top: 10px;
            padding: 0;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: auto;
        }

        .grid-table th,
        .grid-table td {
            border: 1px solid #d9e2df;
            padding: 5px 6px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .grid-table th {
            font-size: 8px;
            font-weight: bold;
            background: #eef6f2;
        }

        .grid-table td {
            font-size: 8px;
        }

        .grid-label {
            font-size: 7.5px;
            font-weight: bold;
        }

        .grid-value {
            font-size: 8px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .grid-number {
            width: 5%;
            text-align: center;
            vertical-align: middle !important;
        }

        .grid-column {
            width: 23.75%;
        }

        .monthly-title {
            margin: 5px 0 2px 0;
            font-size: 8px;
            font-weight: bold;
        }

        .monthly-table {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        .monthly-table th,
        .monthly-table td {
            border: 1px solid #d9e2df;
            padding: 4px 5px;
            font-size: 8px;
        }

        .monthly-table th {
            font-weight: bold;
            background: #eef6f2;
        }

        .monthly-table tr {
            page-break-inside: avoid;
        }

        .grid-break {
            page-break-before: auto;
        }

        .attachment-item {
            margin: 0 0 3px 0;
            padding: 0;
        }

        .attachment-link {
            color: #1d5f8f;
            text-decoration: underline;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
    </style>
</head>

<body>

    {{-- Header --}}
    <header>
        <div class="header-wrapper">
            <div class="header-left">
                <div class="logo-box">
                    <div class="logo-box-inner">
                        <img src="{{ public_path('uploads/ReportLogo/shilpaimage.png') }}" alt="Logo">
                    </div>
                </div>
            </div>

            <div class="header-center">
                <div class="company-name">Shilpa Medicare Pvt Ltd</div>
                <div class="document-title">{{ $header['report_title'] }}</div>
                <div class="document-subtitle">Process Record Report</div>
            </div>

            <div class="header-right">
                <div class="logo-box">
                    <div class="logo-box-inner">
                        <img src="{{ public_path('uploads/ReportLogo/vidyagxp_logo.png') }}" alt="Logo">
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- Footer --}}
    <footer>
        <div class="footer-wrapper">
            <table class="footer-table">
                <tr>
                    <td class="footer-left">
                        <div class="footer-company">Shilpa Medicare Pvt Ltd</div>
                        <div class="footer-meta">
                            {{ $header['report_title'] }} &nbsp;|&nbsp; Controlled Document
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

    {{-- Report Content --}}
    <div class="report-content">
        @foreach($tabs ?? [] as $tab)
            <div class="tab-section">

                {{-- Tab --}}
                <div class="report-title">{{ $tab['tab_title'] ?? '-' }}</div>

                @foreach($tab['sections'] ?? [] as $section)
                    @if(!empty($section['fields']))
                        <div class="section">

                            {{-- Section --}}
                            <div class="section-heading section-header-block">{{ $section['title'] ?? '-' }}</div>

                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Field</th>
                                        <th>Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($section['fields'] as $field)
                                        <tr>
                                            <td class="field-name">{{ $field['label'] ?? '-' }}</td>
                                            <td class="field-value">
                                                @php
                                                    $value = $field['value'] ?? null;
                                                    $fieldKey = $field['key'] ?? '';
                                                    $fieldLabel = strtolower($field['label'] ?? '');
                                                @endphp

                                                @if($fieldKey === 'attachment' || str_contains($fieldKey, 'attachment') || str_contains($fieldLabel, 'attachment'))
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
                                                        <div class="array-value">
                                                            @foreach($value as $item)
                                                                <div class="array-item">
                                                                    @if(is_array($item))
                                                                        @if(isset($item['name']))
                                                                            {{ $item['name'] }}
                                                                        @elseif(isset($item['file_name']))
                                                                            {{ $item['file_name'] }}
                                                                        @else
                                                                            {{ json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
                                                                        @endif
                                                                    @else
                                                                        {{ $item }}
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                @else
                                                    {{ $value !== null && $value !== '' ? $value : '-' }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endforeach

                @if(($tab['tab_title'] ?? '') === 'General Information' && !empty($grid_records))
                    <div class="section grid-section">
                        <div class="section-heading section-header-block">Calibration Planner</div>

                        @foreach($grid_records as $rowIndex => $row)
                            @php
                                $gridFields = [];

                                foreach ($row as $key => $field) {
                                    if ($key === 'monthlyCalibration') {
                                        continue;
                                    }

                                    if (!is_array($field)) {
                                        continue;
                                    }

                                    $gridFields[] = [
                                        'key' => $key,
                                        'label' => $field['label'] ?? $key,
                                        'value' => $field['value'] ?? null,
                                    ];
                                }

                                // Four columns per table; remaining columns continue in next table.
                                $gridChunks = array_chunk($gridFields, 4);
                            @endphp

                            {{-- Normal grid fields --}}
                            @foreach($gridChunks as $chunkIndex => $chunk)
                                <div class="grid-group">
                                    <table class="grid-table">
                                        <thead>
                                            <tr>
                                                @if($chunkIndex === 0)
                                                    <th class="grid-number">S.No</th>
                                                @endif

                                                @foreach($chunk as $field)
                                                    <th class="grid-column">{{ $field['label'] }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                @if($chunkIndex === 0)
                                                    <td class="grid-number">{{ $rowIndex + 1 }}</td>
                                                @endif

                                                @foreach($chunk as $field)
                                                    <td class="grid-value">
                                                        @php $value = $field['value'] ?? null; @endphp

                                                        @if(is_array($value))
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
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            @endforeach

                            {{-- Monthly Calibration --}}
                            @php $monthlyData = $row['monthlyCalibration']['value'] ?? []; @endphp

                            @if(is_array($monthlyData) && !empty($monthlyData))
                                <div class="monthly-title">Monthly Calibration</div>

                                <table class="monthly-table">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Scheduler Date</th>
                                            <th>Calibration Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($monthlyData as $month => $monthData)
                                            <tr>
                                                <td>{{ $month }}</td>
                                                <td>{{ !empty($monthData['schedulerDate']) ? $monthData['schedulerDate'] : '-' }}</td>
                                                <td>{{ !empty($monthData['calibrationDate']) ? $monthData['calibrationDate'] : '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        @endforeach
                    </div>
                @endif

            </div>
        @endforeach
    </div>

</body>

</html>