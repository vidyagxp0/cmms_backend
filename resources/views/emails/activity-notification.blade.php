<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1e293b;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $summary }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;">

                    {{-- brand --}}
                    <tr>
                        <td style="padding:0 4px 16px;text-align:center;font-size:12px;font-weight:700;letter-spacing:2px;color:#254870;text-transform:uppercase;">
                            {{ $companyName }}
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">

                                {{-- hero --}}
                                <tr>
                                    <td style="background:#254870;padding:30px 32px;">
                                        <span style="display:inline-block;background:#3b6b9c;color:#ffffff;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:5px 12px;border-radius:999px;">Action required</span>
                                        <h1 style="margin:14px 0 0;font-size:22px;line-height:1.3;font-weight:700;color:#ffffff;">{{ $recordNumber }} needs your attention</h1>
                                        <p style="margin:6px 0 0;font-size:14px;color:#c9d8ea;">{{ $processName }}</p>
                                    </td>
                                </tr>

                                {{-- message --}}
                                <tr>
                                    <td style="padding:28px 32px 8px;">
                                        <p style="margin:0 0 10px;font-size:16px;font-weight:600;color:#0f172a;">Hello {{ $recipientName }},</p>
                                        <p style="margin:0;font-size:14px;line-height:1.7;color:#475569;">{{ $summary }}</p>
                                    </td>
                                </tr>

                                {{-- stage flow --}}
                                <tr>
                                    <td style="padding:18px 32px 8px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td align="center" style="background:#eef2f7;border-radius:10px;padding:12px 10px;font-size:13px;font-weight:600;color:#475569;">{{ $fromStage }}</td>
                                                <td align="center" width="36" style="font-size:18px;font-weight:700;color:#254870;">&rarr;</td>
                                                <td align="center" style="background:#254870;border-radius:10px;padding:12px 10px;font-size:13px;font-weight:700;color:#ffffff;">{{ $toStage }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- details --}}
                                <tr>
                                    <td style="padding:18px 32px 8px;">
                                        @php
                                            $rows = [
                                                'Process' => $processName,
                                                'Record' => $recordNumber,
                                                'Activity' => $activityName,
                                                'Performed by' => $performedBy,
                                                'Date & time' => $performedAt,
                                                'Your role' => $roleName,
                                            ];
                                        @endphp
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:12px;border-collapse:separate;border-spacing:0;">
                                            @foreach($rows as $label => $value)
                                                <tr>
                                                    <td style="padding:12px 16px;width:38%;font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:#64748b;{{ $loop->last ? '' : 'border-bottom:1px solid #eef2f7;' }}">{{ $label }}</td>
                                                    <td style="padding:12px 16px;font-size:14px;font-weight:600;color:#0f172a;{{ $loop->last ? '' : 'border-bottom:1px solid #eef2f7;' }}">{{ $value }}</td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>

                                {{-- comment --}}
                                @if(!empty($comment))
                                    <tr>
                                        <td style="padding:14px 32px 0;">
                                            <div style="background:#f8fafc;border-left:4px solid #3b78b3;border-radius:8px;padding:14px 16px;">
                                                <div style="font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:#64748b;">Comment</div>
                                                <div style="margin-top:6px;font-size:14px;line-height:1.6;color:#334155;">{{ $comment }}</div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif

                                {{-- button --}}
                                @if(!empty($link))
                                    <tr>
                                        <td align="center" style="padding:28px 32px 8px;">
                                            <a href="{{ $link }}" style="display:inline-block;background:#254870;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:14px 34px;border-radius:10px;">Open record</a>
                                        </td>
                                    </tr>
                                @endif

                                <tr><td style="height:28px;line-height:28px;font-size:0;">&nbsp;</td></tr>
                            </table>
                        </td>
                    </tr>

                    {{-- footer --}}
                    <tr>
                        <td style="padding:20px 12px 0;text-align:center;font-size:12px;line-height:1.7;color:#94a3b8;">
                            You received this email because you are assigned the role <strong style="color:#64748b;">{{ $roleName }}</strong>.<br>
                            This is an automated message, please do not reply.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>