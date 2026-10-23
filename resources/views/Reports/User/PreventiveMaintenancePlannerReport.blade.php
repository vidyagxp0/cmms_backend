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
        .planner-equipment-table td:nth-child(1) { width: 6%; }
        .planner-equipment-table th:nth-child(2),
        .planner-equipment-table td:nth-child(2) { width: 29%; }
        .planner-equipment-table th:nth-child(3),
        .planner-equipment-table td:nth-child(3) { width: 20%; }
        .planner-equipment-table th:nth-child(4),
        .planner-equipment-table td:nth-child(4) { width: 23%; }
        .planner-equipment-table th:nth-child(5),
        .planner-equipment-table td:nth-child(5) { width: 22%; }

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

        /* Monthly planner grid: one compact table per month, with P/A columns per week. */
        .month-block {
            margin: 5px 0 8px 0;
            page-break-inside: auto;
        }
        .month-heading {
            background: #e5f0eb;
            border: 1px solid #d9e2df;
            padding: 4px 5px;
            text-align: center;
            font-size: 7px;
            font-weight: bold;
            page-break-after: avoid;
        }
        .monthly-table {
            width: 100%;
            margin: 0;
            padding: 0;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: auto;
        }
        .monthly-table tr { page-break-inside: avoid; }
        .monthly-table th,
        .monthly-table td {
            border: 1px solid #d9e2df;
            padding: 3px 2px;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
            text-align: center;
        }
        .monthly-table th {
            background: #eef6f2;
            font-size: 5.5px;
            line-height: 1.1;
            font-weight: bold;
        }
        .monthly-table td {
            font-size: 6px;
            line-height: 1.1;
        }
        .monthly-table .equipment-col { width: 15%; }
        .monthly-table .code-col { width: 10%; }
        .monthly-table .location-col { width: 10%; }
        .monthly-table .week-heading { width: 6.5%; }
        .monthly-table .pa-heading { width: 3.25%; }
        .week-date {
            margin-top: 2px;
            font-size: 5px;
            font-weight: normal;
        }
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

                {{-- Preventive Maintenance Planner: render the title and table headers once --}}
                @if(($tab['tab_title'] ?? '') === 'General Information' && !empty($plannerGrid['rows'] ?? []))
                    <div class="section grid-section">
                        <div class="grid-title">Preventive Maintenance Planner</div>

                        <table class="planner-equipment-table">
                            <thead>
                                <tr>
                                    <th>S.No</th>
                                    <th>Equipment Name</th>
                                    <th>Equipment Code</th>
                                    <th>Location</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($plannerGrid['rows'] as $rowIndex => $row)
                                    <tr>
                                        <td>{{ $rowIndex + 1 }}</td>
                                        <td>{{ $row['equipmentInstrumentName'] ?: '-' }}</td>
                                        <td>{{ $row['equipmentInstrumentId'] ?: '-' }}</td>
                                        <td>{{ $row['location'] ?: '-' }}</td>
                                        <td>{{ $row['remark'] ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="monthly-title">Monthly Preventive Maintenance Schedule</div>

                        @foreach(($plannerGrid['monthlySchedules'] ?? []) as $monthSchedule)
                            <div class="month-block">
                                <div class="month-heading">{{ $monthSchedule['name'] ?? '-' }}</div>
                                <table class="monthly-table">
                                    <thead>
                                        <tr>
                                            <th rowspan="2" class="equipment-col">Equipment Name</th>
                                            <th rowspan="2" class="code-col">Equipment Code</th>
                                            <th rowspan="2" class="location-col">Location</th>
                                            @foreach(($monthSchedule['weeks'] ?? []) as $week)
                                                <th colspan="2" class="week-heading">
                                                    Week {{ $week['number'] ?? '-' }}
                                                    <div class="week-date">
                                                        @if(!empty($week['date']))
                                                            {{ \Carbon\Carbon::parse($week['date'])->format('d/m/Y') }}
                                                        @else
                                                            -
                                                        @endif
                                                    </div>
                                                </th>
                                            @endforeach
                                        </tr>
                                        <tr>
                                            @foreach(($monthSchedule['weeks'] ?? []) as $week)
                                                <th class="pa-heading">P</th>
                                                <th class="pa-heading">A</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse(($monthSchedule['rows'] ?? []) as $monthRow)
                                            <tr>
                                                <td>{{ $monthRow['equipmentInstrumentName'] ?: '-' }}</td>
                                                <td>{{ $monthRow['equipmentInstrumentId'] ?: '-' }}</td>
                                                <td>{{ $monthRow['location'] ?: '-' }}</td>
                                                @foreach(($monthRow['weeks'] ?? []) as $weekValue)
                                                    <td>{{ !empty($weekValue['planned']) ? $weekValue['planned'] : '-' }}</td>
                                                    <td>{{ !empty($weekValue['actual']) ? $weekValue['actual'] : '-' }}</td>
                                                @endforeach
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="13">No equipment rows available.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    </div>
                @endif

            </div>

        @endforeach

    </div>

</body>
</html>