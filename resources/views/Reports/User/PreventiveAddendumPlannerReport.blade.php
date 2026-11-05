<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $header['report_title'] ?? 'Preventive Addendum Planner' }}</title>

    <style>
        @page {
            size: A4 landscape;
            margin: 105px 25px 65px 25px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #173b66;
            font-size: 9px;
            margin: 0;
        }

        header {
            position: fixed;
            top: -82px;
            left: 0;
            right: 0;
            height: 70px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: middle;
        }

        .logo {
            width: 125px;
            height: 42px;
            object-fit: contain;
        }

        .header-center {
            text-align: center;
        }

        .company-name {
            color: #008c9e;
            font-size: 15px;
            font-weight: bold;
        }

        .report-name {
            color: #173b66;
            font-size: 13px;
            font-weight: bold;
            margin-top: 4px;
        }

        .header-subtitle {
            font-size: 8px;
            color: #64748b;
            margin-top: 3px;
        }

        footer {
            position: fixed;
            bottom: -43px;
            left: 0;
            right: 0;
            height: 30px;
            border-top: 1px solid #b9cde0;
            padding-top: 6px;
            font-size: 8px;
            color: #64748b;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-table td:last-child {
            text-align: right;
        }

        .meta-table,
        .field-table,
        .planner-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .meta-table {
            margin-bottom: 10px;
        }

        .meta-table td {
            border: 1px solid #cbd9e8;
            padding: 7px 9px;
            background: #f3f7fb;
            font-size: 8px;
        }

        .section-heading {
            background: #e8f0f9;
            border: 1px solid #cbdced;
            border-left: 4px solid #347dbb;
            color: #174477;
            font-size: 10px;
            font-weight: bold;
            padding: 8px 10px;
            margin: 12px 0 7px;
            text-transform: uppercase;
            letter-spacing: .5px;
            page-break-after: avoid;
        }

        .field-table {
            margin-bottom: 8px;
        }

        .field-table td {
            border: 1px solid #d1deeb;
            padding: 7px 8px;
            vertical-align: top;
            overflow-wrap: break-word;
        }

        .field-label {
            width: 17%;
            background: #f1f6fb;
            color: #174477;
            font-weight: bold;
        }

        .field-value {
            width: 33%;
            background: #fff;
            color: #243b53;
        }

        .planner-caption {
            background: #f3f7fb;
            border: 1px solid #cbd9e8;
            padding: 7px 9px;
            margin-bottom: 5px;
            color: #174477;
            font-size: 9px;
            font-weight: bold;
        }

        .planner-table {
            margin-bottom: 10px;
            page-break-inside: avoid;
        }

        .planner-table thead {
            display: table-header-group;
        }

        .planner-table tr {
            page-break-inside: avoid;
        }

        .planner-table th {
            background: #dce9f6;
            color: #174477;
            border: 1px solid #aebfd2;
            padding: 5px 2px;
            text-align: center;
            font-size: 7px;
            font-weight: bold;
        }

        .planner-table td {
            border: 1px solid #c7d5e3;
            padding: 5px 3px;
            text-align: center;
            font-size: 7px;
            color: #243b53;
            vertical-align: middle;
            overflow-wrap: break-word;
        }

        .planner-table tbody tr:nth-child(even) {
            background: #f4f8fc;
        }

        .equipment-name {
            font-weight: bold;
            color: #174477;
            text-align: left !important;
        }

        .equipment-code,
        .equipment-location,
        .equipment-remark {
            text-align: left !important;
        }

        .month-cell {
            line-height: 1.6;
        }

        .planned {
            color: #174477;
            font-weight: bold;
        }

        .executed {
            color: #27805b;
            font-weight: bold;
        }

        .empty-state {
            text-align: center;
            padding: 14px;
            border: 1px dashed #b9cde0;
            color: #64748b;
            background: #f7fafd;
        }

        .small-label {
            font-size: 6px;
            color: #64748b;
        }

        .report-content {
            width: 100%;
        }
    </style>
</head>

<body>

<header>
    <table class="header-table">
        <tr>
            <td style="width: 22%; text-align: left;">
                <img class="logo"
                     src="{{ public_path('uploads/ReportLogo/shilpaimage.png') }}"
                     alt="Shilpa Medicare">
            </td>

            <td class="header-center" style="width: 56%;">
                <div class="company-name">
                    {{ $header['company_name'] ?? 'Shilpa Medicare Pvt Ltd' }}
                </div>

                <div class="report-name">
                    {{ $header['report_title'] ?? 'Preventive Addendum Planner' }}
                </div>

                <div class="header-subtitle">
                    Process Record Report
                </div>
            </td>

            <td style="width: 22%; text-align: right;">
                <img class="logo"
                     src="{{ public_path('uploads/ReportLogo/vidyagxp_logo.png') }}"
                     alt="VidyaGxP">
            </td>
        </tr>
    </table>
</header>

<footer>
    <table class="footer-table">
        <tr>
            <td>
                {{ $header['company_name'] ?? 'Shilpa Medicare Pvt Ltd' }}
                &nbsp; | &nbsp; Controlled Document
            </td>
            <td>
                Generated By: {{ $footer['generated_by'] ?? 'System' }}
                &nbsp; | &nbsp;
                Generated On: {{ $footer['generated_at'] ?? '-' }}
            </td>
        </tr>
    </table>
</footer>

<div class="report-content">

    <table class="meta-table">
        <tr>
            <td>
                <strong>Document Number:</strong>
                {{ $header['document_number'] ?? '-' }}
            </td>
            <td>
                <strong>Document Type:</strong>
                {{ $header['document_type'] ?? '-' }}
            </td>
        </tr>
    </table>

    @foreach($sections ?? [] as $section)

        <div class="section-heading">
            {{ $section['title'] ?? '-' }}
        </div>

        @if(($section['type'] ?? '') === 'fields')

            @php
                $fields = array_values($section['fields'] ?? []);
            @endphp

            @if(count($fields))
                <table class="field-table">
                    @foreach(array_chunk($fields, 2) as $fieldPair)
                        <tr>
                            @foreach($fieldPair as $field)
                                <td class="field-label">
                                    {{ $field['label'] ?? '-' }}
                                </td>
                                <td class="field-value">
                                    {{ $field['value'] ?? '-' }}
                                </td>
                            @endforeach

                            @if(count($fieldPair) === 1)
                                <td class="field-label"></td>
                                <td class="field-value"></td>
                            @endif
                        </tr>
                    @endforeach
                </table>
            @else
                <div class="empty-state">No information available.</div>
            @endif

        @elseif(($section['type'] ?? '') === 'planner')

            <div class="planner-caption">
                Preventive Maintenance Schedule
                @if(!empty($section['year']))
                    &nbsp; | &nbsp; Year: {{ $section['year'] }}
                @endif
                &nbsp; | &nbsp;
                Total Equipment: {{ count($section['rows'] ?? []) }}
            </div>

            @if(!empty($section['rows']))

                <table class="planner-table">
                    <thead>
                        <tr>
                            <th style="width: 3%;">Sr.</th>
                            <th style="width: 12%;">Equipment Name</th>
                            <th style="width: 9%;">Equipment Code</th>
                            <th style="width: 8%;">Location</th>

                            @foreach($section['months'] ?? [] as $month)
                                <th style="width: 4.5%;">
                                    {{ substr($month['name'], 0, 3) }}
                                </th>
                            @endforeach

                            <th style="width: 9%;">Remark</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($section['rows'] as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>

                                <td class="equipment-name">
                                    {{ $row['name'] ?? '-' }}
                                </td>

                                <td class="equipment-code">
                                    {{ $row['code'] ?? '-' }}
                                </td>

                                <td class="equipment-location">
                                    {{ $row['location'] ?? '-' }}
                                </td>

                                @foreach($section['months'] ?? [] as $month)
                                    @php
                                        $monthKey = $month['key'];
                                        $plannedDate = $row['planned'][$monthKey] ?? '-';
                                        $executedDate = $row['executed'][$monthKey] ?? '-';
                                    @endphp

                                    <td class="month-cell">
                                        <span class="planned">P:</span>
                                        {{ $plannedDate }}
                                        <br>
                                        <span class="executed">E:</span>
                                        {{ $executedDate }}
                                    </td>
                                @endforeach

                                <td class="equipment-remark">
                                    {{ $row['remark'] ?? '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            @else
                <div class="empty-state">
                    No equipment records available.
                </div>
            @endif

        @endif

    @endforeach

</div>

</body>
</html>