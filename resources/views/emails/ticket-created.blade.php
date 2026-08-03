<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Ticket Created - {{ $ticket->ticket_no }}</title>
    <style type="text/css">
        body {
            margin: 0;
            padding: 0;
            background: #f4f7fb;
            font-family: Arial, Helvetica, sans-serif;
            color: #1e293b;
        }

        table, td {
            border-collapse: collapse;
        }

        .email-shell {
            width: 100%;
            background: #f4f7fb;
            padding: 32px 16px;
        }

        .email-card {
            width: 100%;
            max-width: 680px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
        }

        .email-header {
            background: linear-gradient(135deg, #0f172a 0%, #172554 100%);
            padding: 36px 40px;
        }

        .brand-wrap {
            display: table;
            width: 100%;
        }

        .brand-logo-cell,
        .brand-text-cell {
            display: table-cell;
            vertical-align: middle;
        }

        .brand-logo-cell {
            width: 240px;
            padding-right: 22px;
        }

        .brand-logo-frame {
            display: inline-block;
            width: 190px;
            padding: 14px 18px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.08);
            text-align: center;
        }

        .brand-logo {
            display: block;
            width: 170px;
            max-width: 170px;
            height: auto;
            max-height: none;
            margin: 0 auto;
            object-fit: contain;
            height: auto;
            border-radius: 12px;
        }

        .email-brand-name {
            color: #ffffff;
            font-size: 22px;
            font-weight: 700;
            line-height: 1.2;
            margin: 0;
        }

        .email-brand-subtitle {
            color: rgba(255, 255, 255, 0.72);
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin: 8px 0 0;
        }

        .email-hero {
            margin-top: 30px;
            padding-top: 28px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
        }

        .email-kicker {
            color: #cbd5e1;
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin: 0 0 10px;
            font-weight: 700;
        }

        .email-title {
            color: #ffffff;
            font-size: 26px;
            line-height: 1.2;
            margin: 0;
            font-weight: 800;
        }

        .email-body {
            padding: 32px;
        }

        .email-intro {
            font-size: 16px;
            line-height: 1.7;
            margin: 0 0 16px;
            color: #334155;
        }

        .summary-box {
            margin: 24px 0;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #f8fafc;
            overflow: hidden;
        }

        .summary-head {
            background: linear-gradient(135deg, #eff6ff 0%, #eef2ff 100%);
            padding: 14px 18px;
            border-bottom: 1px solid #dbeafe;
            font-size: 14px;
            font-weight: 700;
            color: #1d4ed8;
        }

        .summary-content {
            padding: 18px;
        }

        .summary-table {
            width: 100%;
        }

        .summary-table td {
            padding: 11px 0;
            vertical-align: top;
            font-size: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .summary-table tr:last-child td {
            border-bottom: none;
        }

        .summary-label {
            width: 180px;
            color: #475569;
            font-weight: 700;
        }

        .summary-value {
            color: #0f172a;
        }

        .email-note {
            margin: 0;
            font-size: 15px;
            line-height: 1.7;
            color: #475569;
        }

        .email-footer {
            padding: 24px 32px 28px;
            background: linear-gradient(135deg, #0f172a 0%, #172554 100%);
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            text-align: center;
        }

        .footer-brand {
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 8px;
        }

        .footer-copy {
            margin: 0;
            color: rgba(255, 255, 255, 0.78);
            font-size: 14px;
            line-height: 1.7;
        }

        @media only screen and (max-width: 600px) {
            .email-shell {
                padding: 20px 10px;
            }

            .email-header,
            .email-body,
            .email-footer {
                padding-left: 22px !important;
                padding-right: 22px !important;
            }

            .brand-wrap,
            .brand-logo-cell,
            .brand-text-cell {
                display: block;
                width: 100%;
            }

            .brand-logo-cell {
                padding-right: 0 !important;
                padding-bottom: 16px;
            }

            .email-brand-name {
                font-size: 20px;
            }

            .email-brand-subtitle {
                font-size: 11px;
            }

            .email-title {
                font-size: 22px;
            }

            .summary-label,
            .summary-value,
            .summary-table td {
                display: block;
                width: 100%;
            }

            .summary-label {
                padding-bottom: 2px !important;
                border-bottom: none !important;
            }

            .summary-value {
                padding-top: 0 !important;
            }
        }
    </style>
</head>
<body>
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" class="email-shell">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" class="email-card">
                    <tr>
                        <td class="email-header">
                            <div class="brand-wrap">
                                @if($companyLogoUrl)
                                    <div class="brand-logo-cell">
                                        <div class="brand-logo-frame">
                                            <img src="{{ $companyLogoUrl }}" alt="{{ $companyName }} Logo" class="brand-logo" width="170">
                                        </div>
                                    </div>
                                @endif
                                <div class="brand-text-cell">
                                    <p class="email-brand-name">{{ $companyName }}</p>
                                    <p class="email-brand-subtitle">Support Ticket Notification</p>
                                </div>
                            </div>

                            <div class="email-hero">
                                <p class="email-kicker">Support Ticket</p>
                                <h1 class="email-title">New Support Ticket Created</h1>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td class="email-body">
                            <p class="email-intro">Hello {{ $ticket->customer->name ?? 'there' }},</p>
                            <p class="email-intro">A new support ticket has been created with the following details:</p>

                            <div class="summary-box">
                                <div class="summary-head">Ticket Details</div>
                                <div class="summary-content">
                                    <table role="presentation" class="summary-table">
                                        <tr>
                                            <td class="summary-label">Customer Name</td>
                                            <td class="summary-value"><strong>{{ $ticket->customer->name }}</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="summary-label">Ticket Name</td>
                                            <td class="summary-value">{{ $ticket->ticket_name }}</td>
                                        </tr>
                                        <tr>
                                            <td class="summary-label">Priority</td>
                                            <td class="summary-value">
                                                <strong style="color: {{ match($ticket->priority) {
                                                    'Urgent' => '#dc2626',
                                                    'High'   => '#ea580c',
                                                    'Medium' => '#d97706',
                                                    'Low'    => '#059669',
                                                    default  => '#6b7280'
                                                } }};">{{ $ticket->priority }}</strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="summary-label">Current Status</td>
                                            <td class="summary-value"><strong>{{ $ticket->status }}</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="summary-label">Created At</td>
                                            <td class="summary-value">{{ $ticket->created_at?->format('d M, Y h:i A') }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <p class="email-note">
                                <strong>Description:</strong><br>
                                {!! nl2br(e($ticket->description ?? 'No description provided')) !!}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td class="email-footer">
                            <p class="footer-brand">{{ $companyName }}</p>
                            <p class="footer-copy">© 2026 Copyright - Fablead Developers Technolab</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
