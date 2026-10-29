<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $header['report_title'] ?? 'Calibration Planner Report' }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 16px 20px 34px 20px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9px;
            line-height: 1.45;
            color: #263449;
            background: #fff;
            margin: 0;
        }

        .document-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            border: 1px solid #d5deea;
            border-radius: 5px;
        }

        .document-header td {
            border: 1px solid #d5deea;
            padding: 9px 12px;
            vertical-align: middle;
        }

        .company-name {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 0.6px;
            color: #15375b;
            padding: 12px 8px !important;
            background: #f5f8fc;
        }

        .report-title {
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.3px;
            color: #fff;
            background: #24476b;
            padding: 10px 8px !important;
            text-transform: uppercase;
        }

        .document-meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .document-meta td {
            border: 1px solid #d5deea;
            background: #f7f9fc;
            padding: 7px 9px;
            font-size: 8.5px;
            width: 50%;
            color: #34445a;
        }

        .document-meta strong {
            color: #15375b;
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

        .details-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 10px;
        }

        /* Space between section heading and its content */
        .section-title + table,
        .section-title + .section-content {
            margin-top: 8px;
        }

        /* Space between sections */
        .report-section {
            margin-bottom: 14px;
        }

        .details-table th,
        .details-table td {
            border: 1px solid #d8e0e9;
            padding: 7px 9px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .details-table th {
            width: 24%;
            background: #f3f6fa;
            color: #344b65;
            text-align: left;
            font-weight: bold;
        }

        .details-table td {
            width: 26%;
            background: #fff;
            color: #263449;
        }

        .instrument-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 0;
            margin-bottom: 12px;
        }

        .instrument-table th,
        .instrument-table td {
            border: 1px solid #d3dce7;
            padding: 6px 4px;
            text-align: center;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .instrument-table th {
            background: #24476b;
            color: #fff;
            font-weight: bold;
            font-size: 7.5px;
            line-height: 1.4;
        }

        .instrument-table td {
            background: #fff;
            color: #34445a;
            font-size: 7.5px;
            line-height: 1.45;
        }

        .instrument-table tbody tr:nth-child(even) td {
            background: #f5f8fc;
        }

        .instrument-table .serial { width: 4%; }
        .instrument-table .instrument-name { width: 12%; }
        .instrument-table .instrument-id { width: 10%; }
        .instrument-table .make { width: 7%; }
        .instrument-table .model { width: 9%; }
        .instrument-table .location { width: 10%; }
        .instrument-table .least-count { width: 6%; }
        .instrument-table .accuracy { width: 7%; }
        .instrument-table .instrument-range { width: 7%; }
        .instrument-table .operating-range { width: 7%; }
        .instrument-table .test-points { width: 8%; }
        .instrument-table .category { width: 5%; }
        .instrument-table .planned-date { width: 7%; }
        .instrument-table .done-date { width: 7%; }
        .instrument-table .remarks { width: 7%; }

        .review-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 10px;
        }

        .review-table th,
        .review-table td {
            border: 1px solid #d8e0e9;
            padding: 8px 10px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .review-table th {
            width: 27%;
            background: #f3f6fa;
            color: #344b65;
            text-align: left;
            font-weight: bold;
        }

        .review-table td {
            background: #fff;
            color: #263449;
        }

        .empty-message {
            border: 1px solid #d8e0e9;
            padding: 12px;
            text-align: center;
            color: #64748b;
            background: #f8fafc;
        }

        .footer {
            position: fixed;
            bottom: -22px;
            left: 0;
            right: 0;
            border-top: 1px solid #cbd5e1;
            padding-top: 6px;
            font-size: 7.5px;
            line-height: 1.5;
            color: #52647a;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-table td {
            width: 50%;
        }

        .footer-table strong {
            color: #24476b;
            letter-spacing: 0.3px;
        }

        .footer-right {
            text-align: right;
        }

        .page-number:after {
            content: counter(page);
        }

        .muted {
            color: #64748b;
        }

        tr {
            page-break-inside: avoid;
        }

        .monthly-value {
            text-align: left !important;
            font-size: 7.5px !important;
        }
    </style>
</head>
<body>

<table class="document-header">
    <tr>
        <td class="company-name">
            {{ $header['company_name'] ?? 'SHILPA MEDICARE LIMITED' }}
        </td>
    </tr>
    <tr>
        <td class="report-title">
            {{ $header['report_title'] ?? 'CALIBRATION PLANNER' }}
        </td>
    </tr>
</table>

{{-- Document metadata: only Document Number and Document Type --}}
<table class="document-meta">
    <tr>
        <td>
            <strong>Document Number:</strong>
            {{ $record['document_number'] ?? '-' }}
        </td>
        <td>
            <strong>Document Type:</strong>
            {{ $record['document_type'] ?? '-' }}
        </td>
    </tr>
</table>

<div class="section-title">General Information</div>

<table class="details-table">
    @php
        $generalFields = $tabs[0]['sections'][0]['fields'] ?? [];
        $generalRows = array_chunk($generalFields, 2);
    @endphp

    @forelse ($generalRows as $row)
        <tr>
            @foreach ($row as $field)
                <th>{{ $field['label'] ?? '-' }}</th>
                <td>
                    {{ is_scalar($field['value'] ?? null) ? ($field['value'] ?? '-') : '-' }}
                </td>
            @endforeach

            @if (count($row) === 1)
                <th></th>
                <td></td>
            @endif
        </tr>
    @empty
        <tr>
            <td colspan="4">No general information available.</td>
        </tr>
    @endforelse
</table>

@php
    $calibrationFields = $tabs[0]['sections'][1]['fields'] ?? [];

    $commentFields = array_values(array_filter(
        $calibrationFields,
        function ($field) {
            $key = strtolower(trim($field['key'] ?? ''));
            $label = strtolower(trim($field['label'] ?? ''));

            return in_array($key, ['comment', 'comments', 'attachment'])
                || in_array($label, ['comments', 'comment', 'attachment']);
        }
    ));

    $calibrationFields = array_values(array_filter(
        $calibrationFields,
        function ($field) {
            $key = strtolower(trim($field['key'] ?? ''));
            $label = strtolower(trim($field['label'] ?? ''));

            return !in_array($key, ['comment', 'comments', 'attachment'])
                && !in_array($label, ['comments', 'comment', 'attachment']);
        }
    ));

    $calibrationRows = array_chunk($calibrationFields, 2);
@endphp

<div class="section-title">Calibration Information</div>

<table class="details-table">
    @forelse ($calibrationRows as $row)
        <tr>
            @foreach ($row as $field)
                <th>{{ $field['label'] ?? '-' }}</th>
                <td>
                    {{ is_scalar($field['value'] ?? null) ? ($field['value'] ?? '-') : '-' }}
                </td>
            @endforeach

            @if (count($row) === 1)
                <th></th>
                <td></td>
            @endif
        </tr>
    @empty
        <tr>
            <td colspan="4">No calibration information available.</td>
        </tr>
    @endforelse
</table>

<div class="section-title">Instrument Calibration Planner</div>

<table class="instrument-table">
    <thead>
        <tr>
            <th class="serial">Sr. No.</th>
            <th class="instrument-name">Instrument Name</th>
            <th class="instrument-id">Instrument ID No.</th>
            <th class="make">Make</th>
            <th class="model">Sr. No. / Model No.</th>
            <th class="location">Location</th>
            <th class="least-count">Least Count</th>
            <th class="accuracy">Accuracy</th>
            <th class="instrument-range">Instrument Range</th>
            <th class="operating-range">Operating Range</th>
            <th class="test-points">Calibration Test Points</th>
            <th class="category">Category</th>
            <th class="planned-date">Planned Date</th>
            <th class="done-date">Calibration Done On</th>
            <th class="remarks">Remarks</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($grid_records ?? [] as $index => $grid)
            <tr>
                <td>{{ $index + 1 }}</td>

                <td>
                    {{ $grid['equipmentInstrumentName']['value']
                        ?? $grid['instrumentName']['value']
                        ?? $grid['equipmentName']['value']
                        ?? '-' }}
                </td>

                <td>
                    {{ $grid['equipmentInstrumentId']['value']
                        ?? $grid['instrumentId']['value']
                        ?? $grid['instrument_id_no']['value']
                        ?? '-' }}
                </td>

                <td>{{ $grid['make']['value'] ?? '-' }}</td>

                <td>
                    {{ $grid['model']['value']
                        ?? $grid['modelNo']['value']
                        ?? '-' }}
                </td>

                <td>{{ $grid['location']['value'] ?? '-' }}</td>

                <td>
                    {{ $grid['leastCount']['value']
                        ?? $grid['least_count']['value']
                        ?? '-' }}
                </td>

                <td>{{ $grid['accuracy']['value'] ?? '-' }}</td>

                <td>
                    {{ $grid['instrumentrange']['value']
                        ?? $grid['instrumentRange']['value']
                        ?? '-' }}
                </td>

                <td>
                    {{ $grid['operatingrange']['value']
                        ?? $grid['operatingRange']['value']
                        ?? '-' }}
                </td>

                <td>
                    {{ $grid['calibrationTestPoints']['value']
                        ?? $grid['calibrationTestPoint']['value']
                        ?? '-' }}
                </td>

                <td>{{ $grid['category']['value'] ?? '-' }}</td>

                <td>
                    {{ $grid['calibrationNextDueDate']['value']
                        ?? $grid['plannedDate']['value']
                        ?? $grid['nextCalibrationDate']['value']
                        ?? '-' }}
                </td>

                <td>
                    {{ $grid['calibrationDoneDate']['value'] ?? '-' }}
                </td>

                <td>
                    {{ $grid['remark']['value'] ?? '-' }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="15" class="empty-message">
                    No instrument records available.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

{{-- Comments and Attachment are displayed below the instrument grid --}}
@if (count($commentFields) > 0)
    <div class="section-title">Comments &amp; Attachments</div>

    <table class="review-table">
        @foreach ($commentFields as $field)
            <tr>
                <th>{{ $field['label'] ?? '-' }}</th>
                <td>
                    @php
                        $value = $field['value'] ?? null;
                    @endphp

                    @if (is_array($value))
                        @if (count($value) > 0)
                            @foreach ($value as $attachment)
                                @if (is_array($attachment))
                                    {{ $attachment['name'] ?? $attachment['filename'] ?? $attachment['original_name'] ?? '-' }}
                                @elseif (is_scalar($attachment))
                                    {{ $attachment }}
                                @else
                                    -
                                @endif
                                @if (!$loop->last)
                                    ,
                                @endif
                            @endforeach
                        @else
                            -
                        @endif
                    @elseif (is_scalar($value))
                        {{ ($value !== '' && $value !== null) ? $value : '-' }}
                    @else
                        -
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
@endif

{{-- Existing review sections remain unchanged --}}
@foreach (array_slice($tabs ?? [], 1) as $tab)
    @foreach ($tab['sections'] ?? [] as $section)
        <div class="section-title">
            {{ $section['title'] ?? $tab['tab_title'] ?? 'Review' }}
        </div>

        <table class="review-table">
            @forelse ($section['fields'] ?? [] as $field)
                <tr>
                    <th>{{ $field['label'] ?? '-' }}</th>
                    <td>
                        {{ is_scalar($field['value'] ?? null) ? ($field['value'] ?? '-') : '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">No review information available.</td>
                </tr>
            @endforelse
        </table>
    @endforeach
@endforeach

<div class="footer">
    <table class="footer-table">
        <tr>
            <td>
                <strong>SHILPA MEDICARE LIMITED</strong><br>
                <span class="muted">Calibration Planner | Controlled Document</span>
            </td>
            <td class="footer-right">
                Generated By: {{ $footer['generated_by'] ?? '-' }}<br>
                Generated On: {{ $footer['generated_at'] ?? '-' }}
            </td>
        </tr>
    </table>
</div>

</body>
</html>