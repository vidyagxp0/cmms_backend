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

        .details-table,
        .review-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 10px;
        }

        .details-table th,
        .details-table td,
        .review-table th,
        .review-table td {
            border: 1px solid #d8e0e9;
            padding: 7px 9px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .details-table th,
        .review-table th {
            width: 24%;
            background: #f3f6fa;
            color: #344b65;
            text-align: left;
            font-weight: bold;
        }

        .details-table td,
        .review-table td {
            width: 26%;
            background: #fff;
            color: #263449;
        }

        .review-table {
            margin-bottom: 7px;
        }

        .attachment-link {
            color: #1d4ed8;
            text-decoration: underline;
        }

        .instrument-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 12px;
        }

        .instrument-table th,
        .instrument-table td {
            border: 1px solid #d3dce7;
            padding: 6px 3px;
            text-align: center;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .instrument-table th {
            background: #24476b;
            color: #fff;
            font-weight: bold;
            font-size: 7px;
            line-height: 1.4;
        }

        .instrument-table td {
            background: #fff;
            color: #34445a;
            font-size: 7px;
            line-height: 1.45;
        }

        .instrument-table tbody tr:nth-child(even) td {
            background: #f5f8fc;
        }

        /* column widths add up to 100% */
        .instrument-table .serial { width: 3%; }
        .instrument-table .instrument-name { width: 9%; }
        .instrument-table .instrument-id { width: 8%; }
        .instrument-table .make { width: 5%; }
        .instrument-table .model { width: 7%; }
        .instrument-table .location { width: 7%; }
        .instrument-table .least-count { width: 5%; }
        .instrument-table .accuracy { width: 5%; }
        .instrument-table .instrument-range { width: 6%; }
        .instrument-table .operating-range { width: 5%; }
        .instrument-table .test-points { width: 6%; }
        .instrument-table .category { width: 5%; }
        .instrument-table .planned-date { width: 6%; }
        .instrument-table .done-date { width: 6%; }
        .instrument-table .next-planned-date { width: 6%; }
        .instrument-table .next-done-date { width: 6%; }
        .instrument-table .remarks { width: 5%; }

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

        .muted {
            color: #64748b;
        }

        tr {
            page-break-inside: avoid;
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
    @forelse (array_chunk($general_fields ?? [], 2) as $row)
        <tr>
            @foreach ($row as $field)
                <th>{{ $field['label'] }}</th>
                <td>{{ $field['value'] }}</td>
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

<div class="section-title">Calibration Information</div>

<table class="details-table">
    @forelse (array_chunk($calibration_fields ?? [], 2) as $row)
        <tr>
            @foreach ($row as $field)
                <th>{{ $field['label'] }}</th>
                <td>{{ $field['value'] }}</td>
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
            <th class="next-planned-date">Next Planned Date</th>
            <th class="next-done-date">Next Calibration Done On</th>
            <th class="remarks">Remarks</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($grid_records ?? [] as $index => $grid)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $grid['instrument_name'] }}</td>
                <td>{{ $grid['instrument_id'] }}</td>
                <td>{{ $grid['make'] }}</td>
                <td>{{ $grid['model'] }}</td>
                <td>{{ $grid['location'] }}</td>
                <td>{{ $grid['least_count'] }}</td>
                <td>{{ $grid['accuracy'] }}</td>
                <td>{{ $grid['instrument_range'] }}</td>
                <td>{{ $grid['operating_range'] }}</td>
                <td>{{ $grid['test_points'] }}</td>
                <td>{{ $grid['category'] }}</td>
                <td>{{ $grid['planned_date'] }}</td>
                <td>{{ $grid['done_date'] }}</td>
                <td>{{ $grid['next_planned_date'] }}</td>
                <td>{{ $grid['next_done_date'] }}</td>
                <td>{{ $grid['remark'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="17" class="empty-message">
                    No instrument records available.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

{{-- Initiator comment, HOD review, User Department review and QA review blocks --}}
@foreach ($review_groups ?? [] as $fields)
    <table class="review-table">
        <tbody>
            @foreach (array_chunk($fields, 2) as $row)
                <tr>
                    @foreach ($row as $field)
                        <th>{{ $field['label'] }}</th>
                        <td>
                            @if (is_array($field['attachments']))
                                {{-- attachment fields: clickable links that open the file in a new tab --}}
                                @forelse ($field['attachments'] as $attachment)
                                    @if (!empty($attachment['url']))
                                        <a class="attachment-link" href="{{ $attachment['url'] }}" target="_blank" rel="noopener">{{ $attachment['name'] }}</a>
                                    @else
                                        {{ $attachment['name'] }}
                                    @endif
                                    @if (!$loop->last), @endif
                                @empty
                                    -
                                @endforelse
                            @else
                                {{ $field['value'] }}
                            @endif
                        </td>
                    @endforeach

                    @if (count($row) === 1)
                        <th></th>
                        <td></td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@endforeach

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
                <td><span class="esign-status">{{ $esign['user_name'] }}</span></td>
                <td>{{ $esign['activity_name'] }}</td>
                <td>{{ $esign['from_stage'] }}</td>
                <td>{{ $esign['to_stage'] }}</td>
                <td>{{ $esign['comment'] }}</td>
                <td>{{ $esign['performed_at'] }}</td>
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