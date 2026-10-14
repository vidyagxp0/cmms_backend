<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $header['report_title'] ?? 'Preventive Maintenance Planner' }}</title>

    <style>
        {!! file_get_contents(resource_path('views/Reports/CSS/Report.css')) !!}

        /* report content */
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

        /* field / value table */
        .data-table {
            width: 100%;
            margin: 10px 0 0 0;
            padding: 0;
            border-collapse: collapse;
            table-layout: fixed;
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
            border: 1px solid #d9e2df;
            padding: 4px 5px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .data-table th {
            background: #eef6f2;
            font-size: 7px;
            font-weight: bold;
            text-align: left;
        }
        .data-table td {
            font-size: 7px;
        }
        .field-name {
            width: 35%;
            font-weight: 600;
        }
        .field-value {
            width: 65%;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* attachments */
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

        /* planner grid */
        .grid-section {
            margin-top: 10px;
        }
        .grid-title {
            margin: 8px 0 4px 0;
            padding: 6px 8px;
            font-size: 9px;
            font-weight: bold;
            background: #eef6f2;
            border-left: 3px solid #28735c;
            page-break-after: avoid;
        }

        /* planner equipment table */
        .planner-equipment-table {
            width: 100%;
            margin: 0;
            padding: 0;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: auto;
        }
        .planner-equipment-table th,
        .planner-equipment-table td {
            border: 1px solid #d9e2df;
            padding: 4px 4px;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .planner-equipment-table th {
            background: #eef6f2;
            font-size: 7px;
            font-weight: bold;
            text-align: center;
            line-height: 1.05;
        }
        .planner-equipment-table td {
            font-size: 7px;
            line-height: 1.1;
            text-align: center;
        }
        .planner-equipment-table th:nth-child(1),
        .planner-equipment-table td:nth-child(1) { width: 4%; }
        .planner-equipment-table th:nth-child(2),
        .planner-equipment-table td:nth-child(2) { width: 21%; }
        .planner-equipment-table th:nth-child(3),
        .planner-equipment-table td:nth-child(3) { width: 10%; }
        .planner-equipment-table th:nth-child(4),
        .planner-equipment-table td:nth-child(4) { width: 10%; }
        .planner-equipment-table th:nth-child(5),
        .planner-equipment-table td:nth-child(5) { width: 17%; }
        .planner-equipment-table th:nth-child(6),
        .planner-equipment-table td:nth-child(6) { width: 12%; }
        .planner-equipment-table th:nth-child(7),
        .planner-equipment-table td:nth-child(7) { width: 16%; }

        /* planner additional information table */
        .planner-info-table {
            width: 100%;
            margin: 4px 0 0 0;
            padding: 0;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: avoid;
        }
        .planner-info-table th,
        .planner-info-table td {
            border: 1px solid #d9e2df;
            padding: 4px 5px;
            font-size: 7px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .planner-info-table th {
            background: #eef6f2;
            font-weight: bold;
            text-align: left;
        }
        .planner-info-table td {
            text-align: left;
        }

        /* monthly schedule table */
        .monthly-title {
            margin: 6px 0 3px 0;
            padding: 5px 7px;
            font-size: 8px;
            font-weight: bold;
            background: #eef6f2;
            border-left: 3px solid #28735c;
            page-break-after: avoid;
        }
        .monthly-table {
            width: 100%;
            margin: 0;
            padding: 0;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: avoid;
        }
        .monthly-table th,
        .monthly-table td {
            border: 1px solid #d9e2df;
            padding: 4px 5px;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .monthly-table th {
            background: #eef6f2;
            font-size: 7px;
            font-weight: bold;
            text-align: center;
        }
        .monthly-table td {
            font-size: 7px;
            text-align: center;
        }
        .monthly-table th:first-child,
        .monthly-table td:first-child { width: 25%; }
        .monthly-table th:nth-child(2),
        .monthly-table td:nth-child(2) { width: 37.5%; }
        .monthly-table th:nth-child(3),
        .monthly-table td:nth-child(3) { width: 37.5%; }
    </style>
</head>

<body>

    {{-- report header --}}
    <header>
        <div class="header-wrapper">

            {{-- left logo --}}
            <div class="header-left">
                <div class="logo-box">
                    <div class="logo-box-inner">
                        <img src="{{ public_path('uploads/ReportLogo/shilpaimage.png') }}" alt="Shilpa Medicare">
                    </div>
                </div>
            </div>

            {{-- center content --}}
            <div class="header-center">
                <div class="company-name">
                    {{ $header['company_name'] ?? 'Shilpa Medicare Pvt Ltd' }}
                </div>
                <div class="document-title">
                    {{ $header['report_title'] ?? 'Preventive Maintenance Planner' }}
                </div>
                <div class="document-subtitle">
                    Process Record Report
                </div>
            </div>

            {{-- right logo --}}
            <div class="header-right">
                <div class="logo-box">
                    <div class="logo-box-inner">
                        <img src="{{ public_path('uploads/ReportLogo/vidyagxp_logo.png') }}" alt="VidyagxP">
                    </div>
                </div>
            </div>

        </div>
    </header>

    {{-- report footer --}}
    <footer>
        <div class="footer-wrapper">
            <table class="footer-table">
                <tr>
                    <td class="footer-left">
                        <div class="footer-company">
                            {{ $header['company_name'] ?? 'Shilpa Medicare Pvt Ltd' }}
                        </div>
                        <div class="footer-meta">
                            {{ $header['report_title'] ?? 'Preventive Maintenance Planner' }}
                            &nbsp;|&nbsp;
                            Controlled Document
                        </div>
                    </td>

                    <td class="footer-right">
                        <div>
                            Generated By:
                            <strong>{{ $footer['generated_by'] ?? 'System' }}</strong>
                        </div>
                        <div class="footer-meta">
                            Generated On:
                            {{ $footer['generated_at'] ?? '-' }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </footer>

    {{-- report content --}}
    <div class="report-content">

        @foreach($tabs ?? [] as $tab)

            <div class="tab-section">

                {{-- tab title --}}
                <div class="report-title">
                    {{ $tab['tab_title'] ?? '-' }}
                </div>

                {{-- tab sections --}}
                @foreach($tab['sections'] ?? [] as $section)

                    @if(!empty($section['fields']))

                        <div class="section">

                            <div class="section-heading section-header-block">
                                {{ $section['title'] ?? '-' }}
                            </div>

                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Field</th>
                                        <th>Value</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach($section['fields'] as $field)

                                        @php
                                            $value = $field['value'] ?? null;
                                            $fieldKey = strtolower($field['key'] ?? '');
                                            $fieldLabel = strtolower($field['label'] ?? '');
                                        @endphp

                                        <tr>
                                            <td class="field-name">
                                                {{ $field['label'] ?? '-' }}
                                            </td>

                                            <td class="field-value">

                                                {{-- attachment --}}
                                                @if(
                                                    str_contains($fieldKey, 'attachment') ||
                                                    str_contains($fieldLabel, 'attachment')
                                                )

                                                    @if(is_array($value) && !empty($value))

                                                        @foreach($value as $attachment)

                                                            @if(is_array($attachment))

                                                                @php
                                                                    $attachmentName = $attachment['name']
                                                                        ?? $attachment['file_name']
                                                                        ?? 'Attachment';
                                                                    $attachmentUrl = $attachment['url'] ?? null;
                                                                @endphp

                                                                <div class="attachment-item">
                                                                    @if($attachmentUrl)
                                                                        <a href="{{ $attachmentUrl }}" target="_blank" class="attachment-link">
                                                                            {{ $attachmentName }}
                                                                        </a>
                                                                    @else
                                                                        {{ $attachmentName }}
                                                                    @endif
                                                                </div>

                                                            @endif

                                                        @endforeach

                                                    @else
                                                        -
                                                    @endif

                                                {{-- array value --}}
                                                @elseif(is_array($value))

                                                    @if(isset($value['name']))
                                                        {{ $value['name'] }}
                                                    @elseif(empty($value))
                                                        -
                                                    @else
                                                        {{ json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
                                                    @endif

                                                {{-- normal value --}}
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

                {{-- preventive maintenance planner --}}
                @if(($tab['tab_title'] ?? '') === 'General Information')

                    @if(!empty($plannerGrid['rows'] ?? []))

                        <div class="section grid-section">

                            <div class="grid-title">
                                Preventive Maintenance Planner
                            </div>

                            @foreach($plannerGrid['rows'] ?? [] as $rowIndex => $row)

                                {{-- equipment information --}}
                                <table class="planner-equipment-table">
                                    <thead>
                                        <tr>
                                            <th>S.No</th>
                                            <th>Equipment Name</th>
                                            <th>Equipment Code</th>
                                            <th>Block</th>
                                            <th>Department</th>
                                            <th>Location</th>
                                            <th>Preventive Frequency</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <tr>
                                            <td>{{ $rowIndex + 1 }}</td>
                                            <td>{{ data_get($row, 'equipmentInstrumentName.value', '-') }}</td>
                                            <td>{{ data_get($row, 'equipmentInstrumentId.value', '-') }}</td>
                                            <td>{{ data_get($row, 'block.value', '-') }}</td>
                                            <td>{{ data_get($row, 'department.value', '-') }}</td>
                                            <td>{{ data_get($row, 'location.value', '-') }}</td>
                                            <td>{{ data_get($row, 'preventiveFrequency.value', '-') }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                {{-- additional planner information --}}
                                <table class="planner-info-table">
                                    <thead>
                                        <tr>
                                            <th>Previous Preventive Date</th>
                                            <th>Next Preventive Date</th>
                                            <th>Make</th>
                                            <th>Model</th>
                                            <th>Remark</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <tr>
                                            <td>{{ data_get($row, 'previousPreventiveDate.value', '-') ?: '-' }}</td>
                                            <td>{{ data_get($row, 'nextPreventiveDate.value', '-') ?: '-' }}</td>
                                            <td>{{ data_get($row, 'make.value', '-') ?: '-' }}</td>
                                            <td>{{ data_get($row, 'model.value', '-') ?: '-' }}</td>
                                            <td>{{ data_get($row, 'remark.value', '-') ?: '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                {{-- frequency schedule --}}
                                @if(!empty($row['schedule'] ?? []))

                                    <div class="monthly-title">
                                        {{ $row['frequencyLabel'] ?? 'Preventive Maintenance' }}
                                    </div>

                                    <table class="monthly-table">
                                        <thead>
                                            <tr>
                                                <th>Month</th>
                                                <th>Planned Date</th>
                                                <th>Execute Date</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            @foreach($row['schedule'] as $schedule)
                                                <tr>
                                                    <td>{{ $schedule['month'] ?? '-' }}</td>
                                                    <td>{{ !empty($schedule['plannedDate']) ? $schedule['plannedDate'] : '-' }}</td>
                                                    <td>{{ !empty($schedule['executeDate']) ? $schedule['executeDate'] : '-' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>

                                @endif

                            @endforeach

                        </div>

                    @endif

                @endif

            </div>

        @endforeach

    </div>

</body>
</html>