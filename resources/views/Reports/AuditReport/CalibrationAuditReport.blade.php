<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Process Record Audit Report</title>

    <style>
        @page {
            size: A4 portrait;
            margin: 112px 20px 65px 20px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #40584f;
            margin: 0;
        }

        /* Repeated header and footer for DomPDF */
        header {
            position: fixed;
            top: -96px;
            left: 0;
            right: 0;
            height: 76px;
        }

        footer {
            position: fixed;
            bottom: -49px;
            left: 0;
            right: 0;
            height: 38px;
            color: #65766f;
            font-size: 8px;
        }

        .header-box {
            width: 100%;
            height: 72px;
            border: 1px solid #cbded5;
            border-radius: 5px;
            border-collapse: collapse;
        }

        .header-box td {
            border: 1px solid #e0e9e4;
            padding: 5px;
            vertical-align: middle;
        }

        .logo-cell {
            width: 18%;
            text-align: center;
        }

        .title-cell {
            width: 64%;
            text-align: center;
        }

        .logo {
            max-width: 105px;
            max-height: 45px;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #315748;
            margin: 0 0 5px;
        }

        .process-name {
            font-size: 11px;
            font-weight: bold;
            color: #426b5a;
            margin-bottom: 3px;
        }

        .report-name {
            font-size: 9px;
            color: #71847b;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 1px solid #d5e0da;
        }

        .footer-table td {
            padding-top: 5px;
            vertical-align: top;
            width: 50%;
        }

        .footer-company {
            font-weight: bold;
            color: #426b5a;
            margin-bottom: 4px;
        }

        .footer-muted {
            color: #8a9992;
        }

        .footer-right {
            text-align: right;
        }

        .report-content {
            width: 100%;
        }

        .report-title {
            text-align: center;
            font-size: 17px;
            font-weight: bold;
            color: #315748;
            margin: 0 0 5px;
        }

        .report-meta {
            text-align: center;
            font-size: 9px;
            line-height: 1.6;
            margin-bottom: 14px;
            color: #526c61;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #315748;
            margin: 15px 0 7px;
            page-break-after: avoid;
        }

        .subsection-title {
            background: #edf5f0;
            border-left: 4px solid #477d65;
            padding: 8px 10px;
            font-size: 10px;
            font-weight: bold;
            color: #426b5a;
            margin: 8px 0;
            page-break-after: avoid;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .details-table {
            margin-bottom: 12px;
            page-break-inside: auto;
        }

        .details-table th,
        .details-table td {
            border: 1px solid #d9e4de;
            padding: 7px 8px;
            text-align: left;
            vertical-align: top;
        }

        .details-table th {
            width: 22%;
            background: #eff6f2;
            color: #49685a;
            font-weight: bold;
        }

        .details-table td {
            background: #ffffff;
            color: #40584f;
        }

        .audit-table {
            table-layout: fixed;
            margin-top: 7px;
        }

        .audit-table thead {
            display: table-header-group;
        }

        .audit-table th,
        .audit-table td {
            border: 1px solid #d7e2dc;
            padding: 6px 5px;
            text-align: left;
            vertical-align: top;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .audit-table th {
            background: #edf5f0;
            color: #49685a;
            font-size: 8px;
            font-weight: bold;
        }

        .audit-table td {
            background: #ffffff;
            color: #40584f;
            font-size: 7.5px;
            line-height: 1.45;
        }

        .audit-table tbody tr:nth-child(even) td {
            background: #f8fbf9;
        }

        .audit-table tr {
            page-break-inside: avoid;
        }

        .action {
            font-weight: bold;
        }

        .attachment-link {
            color: #356b8b;
            text-decoration: underline;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .attachment-item {
            margin-bottom: 3px;
        }

        .empty {
            border: 1px solid #d9e4de;
            background: #f8fbf9;
            text-align: center;
            padding: 18px;
            color: #65766f;
        }

        .wrap {
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .muted {
            color: #8a9992;
        }
    </style>
</head>

<body>

    {{-- Repeated page header --}}
    <header>
        <table class="header-box">
            <tr>
                <td class="logo-cell">
                    @if (!empty($company_logo))
                        <img class="logo" src="{{ $company_logo }}" alt="Company Logo">
                    @elseif (!empty($logo))
                        <img class="logo" src="{{ $logo }}" alt="Company Logo">
                    @else
                        <span class="footer-muted">Shilpa Medicare</span>
                    @endif
                </td>

                <td class="title-cell">
                    <div class="company-name">
                        {{ $company_name ?? 'Shilpa Medicare Pvt Ltd' }}
                    </div>

                    <div class="process-name">
                        {{ $record['process'] ?? 'Calibration Planner' }}
                    </div>

                    <div class="report-name">
                        Process Record Audit Report
                    </div>
                </td>

                <td class="logo-cell">
                    @if (!empty($vidyagxp_logo))
                        <img class="logo" src="{{ $vidyagxp_logo }}" alt="VidyaGxP Logo">
                    @else
                        <span class="footer-muted">VidyaGxP</span>
                    @endif
                </td>
            </tr>
        </table>
    </header>

    {{-- Repeated page footer --}}
    <footer>
        <table class="footer-table">
            <tr>
                <td>
                    <div class="footer-company">
                        {{ $company_name ?? 'Shilpa Medicare Pvt Ltd' }}
                    </div>

                    <div class="footer-muted">
                        {{ $record['process'] ?? 'Calibration Planner' }}
                        &nbsp;|&nbsp; Controlled Document
                    </div>
                </td>

                <td class="footer-right">
                    <div>
                        Generated By:
                        <strong>{{ $generated_by ?? ($record['initiator'] ?? '-') }}</strong>
                    </div>

                    <div class="footer-muted" style="margin-top: 4px;">
                        Generated On:
                        {{ $generated_at ?? now()->format('d-m-Y H:i:s') }}
                    </div>
                </td>
            </tr>
        </table>
    </footer>

    <main class="report-content">

        <div class="section-title">General Information</div>

        <div class="subsection-title">Process Record Detail</div>

        <table class="details-table">
            <tbody>
                <tr>
                    <th>Process</th>
                    <td>{{ $record['process'] ?? '-' }}</td>
                    
                    <th>Current Stage</th>
                    <td>{{ $record['stage'] ?? '-' }}</td>
                </tr>

                <tr>
                    <th>Department</th>
                    <td>{{ $record['department'] ?? '-' }}</td>
                    
                    <th>Initiator</th>
                    <td>{{ $record['initiator'] ?? '-' }}</td>
                </tr>

                <tr>
                    <th>Initiation Date</th>
                    <td>{{ $record['initiation_date'] ?? '-' }}</td>
                </tr>
            </tbody>
        </table>

        <div class="section-title">Audit History</div>

        @if (!empty($from_date) || !empty($to_date))
            <p>
                <strong>Date Range:</strong>
                {{ $from_date ?: 'Beginning' }}
                to
                {{ $to_date ?: 'Present' }}
            </p>
        @endif

        @if (!empty($search))
            <p><strong>Search:</strong> {{ $search }}</p>
        @endif

        @if (count($audits) > 0)

            <table class="audit-table">
                <thead>
                    <tr>
                        <th style="width: 4%;">S.No.</th>
                        <th style="width: 10%;">Action</th>
                        <th style="width: 17%;">Module / Field</th>
                        <th style="width: 14%;">Old Value</th>
                        <th style="width: 17%;">New Value</th>
                        <th style="width: 15%;">Comment / Reason</th>
                        <th style="width: 11%;">Responsible Person</th>
                        <th style="width: 12%;">Date &amp; Time</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($audits as $index => $audit)
                        <tr>
                            <td>{{ $index + 1 }}</td>

                            <td class="wrap action">
                                {{ $audit['action'] ?? '-' }}
                            </td>

                            <td class="wrap">
                                {{ $audit['field'] ?? $audit['module'] ?? '-' }}
                            </td>

                            {{-- Old Value --}}
                            <td class="wrap">
                                @php
                                    $oldValue = $audit['old_value'] ?? null;
                                    $oldDecoded = is_string($oldValue)
                                        ? json_decode($oldValue, true)
                                        : $oldValue;
                                @endphp

                                @if (is_array($oldDecoded))
                                    @php
                                        $oldItems = isset($oldDecoded['name']) || isset($oldDecoded['Name'])
                                            ? [$oldDecoded]
                                            : $oldDecoded;
                                        $renderedOld = false;
                                    @endphp

                                    @foreach ($oldItems as $oldItem)
                                        @if (is_array($oldItem))
                                            @php
                                                $fileName = $oldItem['name'] ?? $oldItem['Name'] ?? null;
                                                $fileUrl = $oldItem['url'] ?? $oldItem['Url'] ?? null;
                                            @endphp

                                            @if ($fileName)
                                                <div class="attachment-item">
                                                    @if ($fileUrl)
                                                        <a class="attachment-link" href="{{ $fileUrl }}">{{ $fileName }}</a>
                                                    @else
                                                        {{ $fileName }}
                                                    @endif
                                                </div>
                                                @php $renderedOld = true; @endphp
                                            @endif
                                        @elseif (is_scalar($oldItem))
                                            {{ $oldItem }}
                                            @php $renderedOld = true; @endphp
                                        @endif
                                    @endforeach

                                    @if (!$renderedOld)
                                        -
                                    @endif
                                @else
                                    {{ $oldValue !== null && $oldValue !== '' ? $oldValue : '-' }}
                                @endif
                            </td>

                            {{-- New Value --}}
                            <td class="wrap">
                                @php
                                    $newValue = $audit['new_value'] ?? null;
                                    $newDecoded = is_string($newValue)
                                        ? json_decode($newValue, true)
                                        : $newValue;
                                @endphp

                                @if (is_array($newDecoded))
                                    @php
                                        $newItems = isset($newDecoded['name']) || isset($newDecoded['Name'])
                                            ? [$newDecoded]
                                            : $newDecoded;
                                        $renderedNew = false;
                                    @endphp

                                    @foreach ($newItems as $newItem)
                                        @if (is_array($newItem))
                                            @php
                                                $fileName = $newItem['name'] ?? $newItem['Name'] ?? null;
                                                $fileUrl = $newItem['url'] ?? $newItem['Url'] ?? null;
                                            @endphp

                                            @if ($fileName)
                                                <div class="attachment-item">
                                                    @if ($fileUrl)
                                                        <a class="attachment-link" href="{{ $fileUrl }}">{{ $fileName }}</a>
                                                    @else
                                                        {{ $fileName }}
                                                    @endif
                                                </div>
                                                @php $renderedNew = true; @endphp
                                            @endif
                                        @elseif (is_scalar($newItem))
                                            {{ $newItem }}
                                            @php $renderedNew = true; @endphp
                                        @endif
                                    @endforeach

                                    @if (!$renderedNew)
                                        -
                                    @endif
                                @else
                                    {{ $newValue !== null && $newValue !== '' ? $newValue : '-' }}
                                @endif
                            </td>

                            {{-- Comment / Reason --}}
                            <td class="wrap">
                                @php
                                    $comment = $audit['remarks']
                                        ?? $audit['comment']
                                        ?? $audit['reason']
                                        ?? $audit['description']
                                        ?? $audit['comments']
                                        ?? $audit['old_value_comment']
                                        ?? null;

                                    if (is_string($comment)) {
                                        $comment = trim($comment);
                                    }
                                @endphp

                                {{ !empty($comment) ? $comment : '-' }}
                            </td>

                            <td class="wrap">
                                {{ $audit['responsible_person'] ?? '-' }}
                            </td>

                            <td class="wrap">
                                {{ $audit['date_time'] ?? $audit['created_at'] ?? '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

        @else

            <div class="empty">
                No audit history found for this process record.
            </div>

        @endif

    </main>

</body>

</html>