<!DOCTYPE html>
<html>

<head>

    <meta charset="UTF-8">

    <title>
        {{ $header['report_title'] }}
    </title>


    <style>

        {!! file_get_contents(
            resource_path('views/Reports/CSS/Report.css')
        ) !!}


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


        /* grids */

        .grid-section {
            margin-top: 10px;
        }


        .grid-group {
            margin: 0 0 10px 0;
            padding: 0;

            page-break-inside: auto;
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

            padding: 5px 6px;

            vertical-align: top;

            word-wrap: break-word;
            overflow-wrap: break-word;
        }


        .grid-table th {

            font-size: 8px;
            font-weight: bold;

            background: #eef6f2;

            text-align: center;
        }


        .grid-table td {

            font-size: 8px;
        }


        .grid-number {

            width: 5%;

            text-align: center;

            vertical-align: middle !important;
        }


        .grid-value {

            font-size: 8px;

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

    </style>

</head>


<body>


    {{-- HEADER --}}

    <header>

        <div class="header-wrapper">

            <div class="header-left">

                <div class="logo-box">

                    <div class="logo-box-inner">

                        <img
                            src="{{ public_path(
                                'uploads/ReportLogo/shilpaimage.png'
                            ) }}"
                            alt="Logo"
                        >

                    </div>

                </div>

            </div>


            <div class="header-center">

                <div class="company-name">
                    {{ $header['company_name'] }}
                </div>

                <div class="document-title">
                    {{ $header['report_title'] }}
                </div>

                <div class="document-subtitle">
                    Process Record Report
                </div>

            </div>


            <div class="header-right">

                <div class="logo-box">

                    <div class="logo-box-inner">

                        <img
                            src="{{ public_path(
                                'uploads/ReportLogo/vidyagxp_logo.png'
                            ) }}"
                            alt="Logo"
                        >

                    </div>

                </div>

            </div>

        </div>

    </header>


    {{-- FOOTER --}}

    <footer>

        <div class="footer-wrapper">

            <table class="footer-table">

                <tr>

                    <td class="footer-left">

                        <div class="footer-company">
                            {{ $header['company_name'] }}
                        </div>

                        <div class="footer-meta">

                            {{ $header['report_title'] }}

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


    {{-- REPORT CONTENT --}}

    <div class="report-content">


        {{-- ===================================================== --}}
        {{-- GENERAL INFORMATION --}}
        {{-- ===================================================== --}}

        @foreach($tabs ?? [] as $tab)

            <div class="tab-section">


                <div class="report-title">

                    {{ $tab['tab_title'] ?? '-' }}

                </div>


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

                                    @foreach(
                                        $section['fields']
                                        as $field
                                    )

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


                                                {{-- ATTACHMENT --}}

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

                                                        @foreach(
                                                            $value
                                                            as $attachment
                                                        )

                                                            @if(
                                                                is_array(
                                                                    $attachment
                                                                )
                                                            )

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


                                                                <div
                                                                    class="attachment-item"
                                                                >

                                                                    @if(
                                                                        $attachmentUrl
                                                                    )

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


                                                {{-- NORMAL ARRAY --}}

                                                @elseif(
                                                    is_array($value)
                                                )

                                                    @if(
                                                        isset($value['name'])
                                                    )

                                                        {{ $value['name'] }}

                                                    @elseif(
                                                        empty($value)
                                                    )

                                                        -

                                                    @else

                                                        {{ json_encode(
                                                            $value,
                                                            JSON_UNESCAPED_UNICODE |
                                                            JSON_UNESCAPED_SLASHES
                                                        ) }}

                                                    @endif


                                                {{-- NORMAL VALUE --}}

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


                {{-- ================================================= --}}
                {{-- CALIBRATION MANAGEMENT GRIDS --}}
                {{-- ================================================= --}}

                @if(
                    ($tab['tab_title'] ?? '') ===
                    'General Information'
                )

                    @if(!empty($grids))

                        <div class="section grid-section">


                            {{-- CALIBRATION RESULTS --}}

                            @if(
                                isset($grids['calibrationResults'])
                            )

                                @php

                                    $grid =
                                        $grids[
                                            'calibrationResults'
                                        ];

                                    $rows =
                                        $grid['rows'] ?? [];

                                @endphp


                                <div class="grid-title">

                                    Calibration Results

                                </div>


                                @if(!empty($rows))

                                    @php

                                        /*
                                         * Get columns from first row.
                                         */
                                        $firstRow =
                                            $rows[0] ?? [];

                                        $columns = [];

                                        foreach (
                                            $firstRow
                                            as $key => $field
                                        ) {

                                            if (
                                                $key === 'row_id'
                                            ) {
                                                continue;
                                            }

                                            $columns[] = [
                                                'key' => $key,
                                                'label' =>
                                                    $field['label']
                                                    ?? $key,
                                            ];
                                        }

                                    @endphp


                                    <table class="grid-table">

                                        <thead>

                                            <tr>

                                                <th
                                                    class="grid-number"
                                                >
                                                    S.No
                                                </th>


                                                @foreach(
                                                    $columns
                                                    as $column
                                                )

                                                    <th
                                                        class="grid-column"
                                                    >

                                                        {{
                                                            $column['label']
                                                        }}

                                                    </th>

                                                @endforeach

                                            </tr>

                                        </thead>


                                        <tbody>

                                            @foreach(
                                                $rows
                                                as $rowIndex => $row
                                            )

                                                <tr>

                                                    <td
                                                        class="grid-number"
                                                    >

                                                        {{
                                                            $rowIndex + 1
                                                        }}

                                                    </td>


                                                    @foreach(
                                                        $columns
                                                        as $column
                                                    )

                                                        @php

                                                            $cell =
                                                                $row[
                                                                    $column['key']
                                                                ]
                                                                ?? null;

                                                            $cellValue =
                                                                is_array(
                                                                    $cell
                                                                )
                                                                    ? (
                                                                        $cell['value']
                                                                        ?? null
                                                                    )
                                                                    : $cell;

                                                        @endphp


                                                        <td
                                                            class="grid-value"
                                                        >

                                                            {{
                                                                $cellValue !== null &&
                                                                $cellValue !== ''
                                                                    ? $cellValue
                                                                    : '-'
                                                            }}

                                                        </td>

                                                    @endforeach

                                                </tr>

                                            @endforeach

                                        </tbody>

                                    </table>

                                @else

                                    <div class="empty-grid">
                                        No records available.
                                    </div>

                                @endif

                            @endif


                            {{-- MASTER INSTRUMENTS DETAILS --}}

                            @if(
                                isset($grids['testResults'])
                            )

                                @php

                                    $grid =
                                        $grids['testResults'];

                                    $rows =
                                        $grid['rows'] ?? [];

                                @endphp


                                <div class="grid-title">

                                    Master Instruments Details

                                </div>


                                @if(!empty($rows))

                                    @php

                                        $firstRow =
                                            $rows[0] ?? [];

                                        $columns = [];

                                        foreach (
                                            $firstRow
                                            as $key => $field
                                        ) {

                                            if (
                                                $key === 'row_id'
                                            ) {
                                                continue;
                                            }

                                            $columns[] = [
                                                'key' => $key,
                                                'label' =>
                                                    $field['label']
                                                    ?? $key,
                                            ];
                                        }

                                    @endphp


                                    <table class="grid-table">

                                        <thead>

                                            <tr>

                                                <th
                                                    class="grid-number"
                                                >
                                                    S.No
                                                </th>


                                                @foreach(
                                                    $columns
                                                    as $column
                                                )

                                                    <th
                                                        class="grid-column"
                                                    >

                                                        {{
                                                            $column['label']
                                                        }}

                                                    </th>

                                                @endforeach

                                            </tr>

                                        </thead>


                                        <tbody>

                                            @foreach(
                                                $rows
                                                as $rowIndex => $row
                                            )

                                                <tr>

                                                    <td
                                                        class="grid-number"
                                                    >

                                                        {{
                                                            $rowIndex + 1
                                                        }}

                                                    </td>


                                                    @foreach(
                                                        $columns
                                                        as $column
                                                    )

                                                        @php

                                                            $cell =
                                                                $row[
                                                                    $column['key']
                                                                ]
                                                                ?? null;

                                                            $cellValue =
                                                                is_array(
                                                                    $cell
                                                                )
                                                                    ? (
                                                                        $cell['value']
                                                                        ?? null
                                                                    )
                                                                    : $cell;

                                                        @endphp


                                                        <td
                                                            class="grid-value"
                                                        >

                                                            {{
                                                                $cellValue !== null &&
                                                                $cellValue !== ''
                                                                    ? $cellValue
                                                                    : '-'
                                                            }}

                                                        </td>

                                                    @endforeach

                                                </tr>

                                            @endforeach

                                        </tbody>

                                    </table>

                                @else

                                    <div class="empty-grid">
                                        No records available.
                                    </div>

                                @endif

                            @endif


                        </div>

                    @endif

                @endif


            </div>

        @endforeach


    </div>


</body>

</html>