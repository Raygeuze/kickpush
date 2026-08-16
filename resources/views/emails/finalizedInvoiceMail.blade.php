<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice INV{{ $invoice->id }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <p>Hi {{ $clientName ?: 'there' }},</p>

    <p>Please find attached your invoice <strong>INV{{ $invoice->id }}</strong>.</p>

    <p>
        Total: <strong>A${{ number_format((float) $grandTotal, 2) }}</strong><br>
        Due date: <strong>{{ $dueDate ? $dueDate->format('Y-m-d') : '-' }}</strong>
    </p>

    <p>
        If you have any questions, please reply to this email.
    </p>

    <p>Thanks,<br> Ray</p>
</body>
</html>
