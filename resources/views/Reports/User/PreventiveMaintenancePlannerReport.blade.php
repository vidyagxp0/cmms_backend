<!DOCTYPE html>
<html>

<head>

    <meta charset="UTF-8">

    <title>
        {{ $header['report_title'] ?? 'Preventive Maintenance Planner' }}
    </title>

    <style>

        {!! file_get_contents(resource_path('views/Reports/CSS/Report.css')) !!}


        /* =========================================================
         * REPORT CONTENT
         * ========================================================= */

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


        /* =========================================================
         * NORMAL DATA TABLE
         * ========================================================= */

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


        /* =========================================================
         * ATTACHMENTS
         * ========================================================= */

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


        /* =========================================================
         * GRID
         * ========================================================= */

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


        .grid-table {
            width: 100%;
            margin: 0;
            padding: 0;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: auto;
        }


        .grid-table th,
        .grid-table td {
            border: 1px solid #d9e2df;
            padding: 3px 3px;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }


        .grid-table th {
            font-size: 6px;
            font-weight: bold;
            background: #eef6f2;
            text-align: center;
            line-height: 1.05;
        }


        .grid-table td {
            font-size: 6px;
            line-height: 1.05;
        }


        .grid-number {
            width: 3.5%;
            text-align: center;
            vertical-align: middle !important;
        }


        .grid-value {
            font-size: 6px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }


        .grid-column {
            width: auto;
        }


        .empty-grid {
            padding: 8px;
            font-size: 8px;
            text-align: center;
            border: 1px solid #d9e2df;
        }


        /* =========================================================
         * PREVENTIVE MAINTENANCE PLANNER GRID
         * ========================================================= */

        .planner-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            page-break-inside: auto;
        }


        .planner-table th,
        .planner-table td {
            border: 1px solid #d9e2df;
            padding: 2px 2px;
            vertical-align: middle;
            text-align: center;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }


        .planner-table th {
            background: #eef6f2;
            font-size: 5.5px;
            font-weight: bold;
            line-height: 1.05;
        }


        .planner-table td {
            font-size: 5.5px;
            line-height: 1.05;
        }


        .planner-main-header {
            vertical-align: middle !important;
        }


        .planner-sno {
            width: 3.5%;
        }


        .planner-equipment {
            width: 9%;
        }


        .planner-code {
            width: 6.5%;
        }


        .planner-block {
            width: 6.5%;
        }


        .planner-department {
            width: 6.5%;
        }


        .planner-location {
            width: 6.5%;
        }


        .planner-frequency {
            width: 7%;
        }


        .planner-month {
            width: 3.95%;
        }


        .planner-date {
            width: 3.95%;
        }


        /* =========================================================
         * PLANNER ADDITIONAL INFORMATION
         * ========================================================= */

        .planner-info-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            table-layout: fixed;
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


        /* =========================================================
         * PAGE BREAK
         * ========================================================= */

        .keep-section {
            page-break-inside: avoid;
        }

    </style>

</head>


<body>


    {{-- =========================================================
     * STANDARD REPORT HEADER
     * ========================================================= --}}

    <header>

        <div class="header-wrapper">


            {{-- LEFT LOGO --}}

            <div class="header-left">

                <div class="logo-box">

                    <div class="logo-box-inner">

                        <img
                            src="{{ public_path('uploads/ReportLogo/shilpaimage.png') }}"
                            alt="Shilpa Medicare"
                        >

                    </div>

                </div>

            </div>


            {{-- CENTER CONTENT --}}

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


            {{-- RIGHT LOGO --}}

            <div class="header-right">

                <div class="logo-box">

                    <div class="logo-box-inner">

                        <img
                            src="{{ public_path('uploads/ReportLogo/vidyagxp_logo.png') }}"
                            alt="VidyagxP"
                        >

                    </div>

                </div>

            </div>


        </div>

    </header>



    {{-- =========================================================
     * STANDARD REPORT FOOTER
     * ========================================================= --}}

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

                            <strong>

                                {{ $footer['generated_by'] ?? 'System' }}

                            </strong>

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



    {{-- =========================================================
     * REPORT CONTENT
     * ========================================================= --}}

    <div class="report-content">


        @foreach($tabs ?? [] as $tab)

            <div class="tab-section">


                {{-- =================================================
                 * TAB TITLE
                 * ================================================= --}}

                <div class="report-title">

                    {{ $tab['tab_title'] ?? '-' }}

                </div>



                {{-- =================================================
                 * TAB SECTIONS
                 * ================================================= --}}

                @foreach($tab['sections'] ?? [] as $section)

                    @if(!empty($section['fields']))

                        <div class="section">


                            <div class="section-heading section-header-block">

                                {{ $section['title'] ?? '-' }}

                            </div>


                            <table class="data-table">

                                <thead>

                                    <tr>

                                        <th>
                                            Field
                                        </th>

                                        <th>
                                            Value
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    @foreach($section['fields'] as $field)

                                        <tr>

                                            <td class="field-name">

                                                {{ $field['label'] ?? '-' }}

                                            </td>


                                            <td class="field-value">

                                                @php

                                                    $value =
                                                        $field['value']
                                                        ?? null;

                                                    $fieldKey =
                                                        $field['key']
                                                        ?? '';

                                                    $fieldLabel =
                                                        strtolower(
                                                            $field['label']
                                                            ?? ''
                                                        );

                                                @endphp


                                                {{-- =================================================
                                                 * ATTACHMENT
                                                 * ================================================= --}}

                                                @if(
                                                    $fieldKey === 'attachment' ||
                                                    str_contains(
                                                        strtolower($fieldKey),
                                                        'attachment'
                                                    ) ||
                                                    str_contains(
                                                        $fieldLabel,
                                                        'attachment'
                                                    )
                                                )

                                                    @if(
                                                        is_array($value) &&
                                                        !empty($value)
                                                    )

                                                        @foreach($value as $attachment)

                                                            @if(is_array($attachment))

                                                                @php

                                                                    $attachmentName =
                                                                        $attachment['name']
                                                                        ??
                                                                        $attachment['file_name']
                                                                        ??
                                                                        'Attachment';

                                                                    $attachmentUrl =
                                                                        $attachment['url']
                                                                        ?? null;

                                                                @endphp


                                                                <div class="attachment-item">

                                                                    @if($attachmentUrl)

                                                                        <a
                                                                            href="{{ $attachmentUrl }}"
                                                                            target="_blank"
                                                                            class="attachment-link"
                                                                        >
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


                                                {{-- =================================================
                                                 * NORMAL ARRAY
                                                 * ================================================= --}}

                                                @elseif(is_array($value))

                                                    @if(isset($value['name']))

                                                        {{ $value['name'] }}

                                                    @elseif(empty($value))

                                                        -

                                                    @else

                                                        {{
                                                            json_encode(
                                                                $value,
                                                                JSON_UNESCAPED_UNICODE |
                                                                JSON_UNESCAPED_SLASHES
                                                            )
                                                        }}

                                                    @endif


                                                {{-- =================================================
                                                 * NORMAL VALUE
                                                 * ================================================= --}}

                                                @else

                                                    {{
                                                        $value !== null &&
                                                        $value !== ''
                                                            ? $value
                                                            : '-'
                                                    }}

                                                @endif

                                            </td>

                                        </tr>

                                    @endforeach

                                </tbody>

                            </table>


                        </div>

                    @endif

                @endforeach



                {{-- =================================================
                 * PREVENTIVE MAINTENANCE PLANNER
                 * ================================================= --}}

                @if(
                    ($tab['tab_title'] ?? '') ===
                    'General Information'
                )


                    @if(!empty($plannerGrid['rows'] ?? []))

                        @php

                            $months =
                                $plannerGrid['months']
                                ?? [
                                    'Jan',
                                    'Feb',
                                    'Mar',
                                    'Apr',
                                    'May',
                                    'Jun',
                                    'Jul',
                                    'Aug',
                                    'Sep',
                                    'Oct',
                                    'Nov',
                                    'Dec',
                                ];

                        @endphp


                        <div class="section grid-section">


                            <div class="grid-title">

                                Preventive Maintenance Planner

                            </div>


                            {{-- =================================================
                             * MAIN PLANNER GRID
                             * ================================================= --}}

                            <table class="grid-table planner-table">


                                <thead>


                                    {{-- FIRST HEADER ROW --}}

                                    <tr>


                                        <th
                                            class="planner-sno planner-main-header"
                                            rowspan="2"
                                        >
                                            S.No
                                        </th>


                                        <th
                                            class="planner-equipment planner-main-header"
                                            rowspan="2"
                                        >
                                            Equipment Name
                                        </th>


                                        <th
                                            class="planner-code planner-main-header"
                                            rowspan="2"
                                        >
                                            Equipment Code
                                        </th>


                                        <th
                                            class="planner-block planner-main-header"
                                            rowspan="2"
                                        >
                                            Block
                                        </th>


                                        <th
                                            class="planner-department planner-main-header"
                                            rowspan="2"
                                        >
                                            Department
                                        </th>


                                        <th
                                            class="planner-location planner-main-header"
                                            rowspan="2"
                                        >
                                            Location
                                        </th>


                                        <th
                                            class="planner-frequency planner-main-header"
                                            rowspan="2"
                                        >
                                            Preventive Frequency
                                        </th>


                                        @foreach($months as $month)

                                            <th
                                                class="planner-month"
                                                colspan="2"
                                            >
                                                {{ $month }}
                                            </th>

                                        @endforeach


                                    </tr>



                                    {{-- SECOND HEADER ROW --}}

                                    <tr>

                                        @foreach($months as $month)

                                            <th class="planner-date">
                                                Planned Date
                                            </th>

                                            <th class="planner-date">
                                                Execute Date
                                            </th>

                                        @endforeach

                                    </tr>


                                </thead>


                                <tbody>


                                    @foreach(
                                        $plannerGrid['rows'] ?? []
                                        as $rowIndex => $row
                                    )

                                        <tr>


                                            {{-- S.NO --}}

                                            <td class="grid-number">

                                                {{ $rowIndex + 1 }}

                                            </td>


                                            {{-- EQUIPMENT NAME --}}

                                            <td>

                                                {{
                                                    data_get(
                                                        $row,
                                                        'equipmentInstrumentName.value',
                                                        '-'
                                                    )
                                                }}

                                            </td>


                                            {{-- EQUIPMENT CODE --}}

                                            <td>

                                                {{
                                                    data_get(
                                                        $row,
                                                        'equipmentInstrumentId.value',
                                                        '-'
                                                    )
                                                }}

                                            </td>


                                            {{-- BLOCK --}}

                                            <td>

                                                {{
                                                    data_get(
                                                        $row,
                                                        'block.value',
                                                        '-'
                                                    )
                                                }}

                                            </td>


                                            {{-- DEPARTMENT --}}

                                            <td>

                                                {{
                                                    data_get(
                                                        $row,
                                                        'department.value',
                                                        '-'
                                                    )
                                                }}

                                            </td>


                                            {{-- LOCATION --}}

                                            <td>

                                                {{
                                                    data_get(
                                                        $row,
                                                        'location.value',
                                                        '-'
                                                    )
                                                }}

                                            </td>


                                            {{-- FREQUENCY --}}

                                            <td>

                                                {{
                                                    data_get(
                                                        $row,
                                                        'preventiveFrequency.value',
                                                        '-'
                                                    )
                                                }}

                                            </td>


                                            @php

                                                $monthlyPreventive =
                                                    $row['monthlyPreventive']
                                                    ?? [];

                                            @endphp


                                            {{-- =================================================
                                             * MONTHLY PLANNER DATA
                                             * ================================================= --}}

                                            @foreach($months as $month)

                                                @php

                                                    $monthData =
                                                        $monthlyPreventive[
                                                            $month
                                                        ]
                                                        ?? [];

                                                    $plannedDate =
                                                        $monthData[
                                                            'plannedDate'
                                                        ]
                                                        ?? null;

                                                    $executeDate =
                                                        $monthData[
                                                            'executeDate'
                                                        ]
                                                        ?? null;

                                                @endphp


                                                <td>

                                                    {{
                                                        $plannedDate
                                                            ?: '-'
                                                    }}

                                                </td>


                                                <td>

                                                    {{
                                                        $executeDate
                                                            ?: '-'
                                                    }}

                                                </td>

                                            @endforeach


                                        </tr>

                                    @endforeach


                                </tbody>


                            </table>



                            {{-- =================================================
                             * ADDITIONAL PLANNER INFORMATION
                             * ================================================= --}}

                            @foreach(
                                $plannerGrid['rows'] ?? []
                                as $row
                            )

                                <table class="planner-info-table">


                                    <thead>

                                        <tr>

                                            <th>
                                                Frequency Start Date
                                            </th>

                                            <th>
                                                Previous Preventive Date
                                            </th>

                                            <th>
                                                Next Preventive Date
                                            </th>

                                            <th>
                                                Make
                                            </th>

                                            <th>
                                                Model
                                            </th>

                                            <th>
                                                Remark
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>

                                        <tr>


                                            {{-- FREQUENCY START DATE --}}

                                            <td>

                                                {{
                                                    data_get(
                                                        $row,
                                                        'preventiveFrequencyStartDate',
                                                        '-'
                                                    )
                                                    ?: '-'
                                                }}

                                            </td>


                                            {{-- PREVIOUS DATE --}}

                                            <td>

                                                {{
                                                    data_get(
                                                        $row,
                                                        'previousPreventiveDate.value',
                                                        '-'
                                                    )
                                                    ?: '-'
                                                }}

                                            </td>


                                            {{-- NEXT DATE --}}

                                            <td>

                                                {{
                                                    data_get(
                                                        $row,
                                                        'nextPreventiveDate.value',
                                                        '-'
                                                    )
                                                    ?: '-'
                                                }}

                                            </td>


                                            {{-- MAKE --}}

                                            <td>

                                                {{
                                                    data_get(
                                                        $row,
                                                        'make.value',
                                                        '-'
                                                    )
                                                }}

                                            </td>


                                            {{-- MODEL --}}

                                            <td>

                                                {{
                                                    data_get(
                                                        $row,
                                                        'model.value',
                                                        '-'
                                                    )
                                                }}

                                            </td>


                                            {{-- REMARK --}}

                                            <td>

                                                {{
                                                    data_get(
                                                        $row,
                                                        'remark.value',
                                                        '-'
                                                    )
                                                }}

                                            </td>


                                        </tr>

                                    </tbody>


                                </table>

                            @endforeach


                        </div>


                    @else

                        <div class="section grid-section">


                            <div class="grid-title">

                                Preventive Maintenance Planner

                            </div>


                            <div class="empty-grid">

                                No records available.

                            </div>


                        </div>

                    @endif


                @endif


            </div>

        @endforeach


    </div>


</body>

</html>