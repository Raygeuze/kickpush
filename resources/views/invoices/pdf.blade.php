<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice INV{{ $invoice->id }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 12px;
            line-height: 1.4;
        }

        .wrapper {
            width: 100%;
        }

        .header {
            margin-bottom: 24px;
        }

        .title {
            font-size: 24px;
            margin: 0;
        }

        .muted {
            color: #4b5563;
        }

        .meta {
            margin-top: 8px;
            margin-bottom: 16px;
        }

        .bill-to {
            margin: 10px 0 16px;
            padding: 10px;
            border: 1px solid #d1d5db;
            background: #f9fafb;
        }

        .bill-to-title {
            font-weight: bold;
            margin-bottom: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        th, td {
            border: 1px solid #d1d5db;
            padding: 8px;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            text-align: left;
        }

        .amount {
            text-align: right;
            white-space: nowrap;
        }

        .totals {
            margin-top: 14px;
            text-align: right;
            font-size: 16px;
            font-weight: bold;
        }

        .notes {
            margin-top: 20px;
            font-size: 11px;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1 class="title">Invoice INV{{ $invoice->id }}</h1>
        <div class="meta muted">
            <div>Status: {{ ucfirst($invoice->status) }}</div>
            <div>Issued: {{ $invoice->issued_at ? $invoice->issued_at->format('Y-m-d') : '-' }}</div>
        </div>

        <div class="bill-to">
            <div class="bill-to-title">Bill To</div>
            @if($invoice->client)
                <div>{{ $invoice->client->name }}</div>
                <div>{{ $invoice->client->email ?: '-' }}</div>
                @if($invoice->client->notes)
                    <div>{{ $invoice->client->notes }}</div>
                @endif
            @else
                <div>No client assigned</div>
            @endif
        </div>
    </div>

    <table>
        <thead>
        <tr>
            <th style="width: 30%;">Item</th>
            <th style="width: 50%;">Description</th>
            <th style="width: 20%;" class="amount">Amount (AUD)</th>
        </tr>
        </thead>
        <tbody>
        @foreach($lineItems as $line)
            <tr>
                <td>{{ $line['label'] }}</td>
                <td>{{ $line['description'] ?: '-' }}</td>
                <td class="amount">A${{ number_format((float) $line['amount'], 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="totals">
        Total: A${{ number_format((float) $grandTotal, 2) }}
    </div>

    <div class="notes muted">
        <div>Total tracked time: {{ number_format($totalDurationSeconds / 3600, 2) }} hours</div>
        <div>Hourly rate applied: A${{ number_format((float) $hourlyRate, 2) }}/hr</div>
        <div>Payment due date: {{ $dueDate->format('Y-m-d') }}</div>
        @if($invoice->notes)
            <div style="margin-top: 8px;">Invoice notes: {{ $invoice->notes }}</div>
        @endif

        @php
            $hasPaymentInfo = $user
                && (
                    !empty($user->bank_account_name)
                    || !empty($user->bank_name)
                    || !empty($user->bsb_code)
                    || !empty($user->bank_account_number)
                );
        @endphp

        @if($hasPaymentInfo)
            <div style="margin-top: 12px; font-weight: 700; color: #111827;">Payment to be made to</div>
            @if(!empty($user->bank_account_name))
                <div>Account Name: {{ $user->bank_account_name }}</div>
            @endif
            @if(!empty($user->bank_name))
                <div>Bank Name: {{ $user->bank_name }}</div>
            @endif
            @if(!empty($user->bsb_code))
                <div>BSB Code: {{ $user->bsb_code }}</div>
            @endif
            @if(!empty($user->bank_account_number))
                <div>Account Number: {{ $user->bank_account_number }}</div>
            @endif
        @endif
    </div>
</div>
</body>
</html>
