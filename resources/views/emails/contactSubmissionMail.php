<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Contact Form Submission</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f5f5f5; padding: 10px 20px; border-bottom: 1px solid #ddd; }
        .content { padding: 20px; }
        .footer { font-size: 12px; color: #888; text-align: center; margin-top: 30px; }
        .label { font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>New Contact Form Submission</h2>
        </div>
        <div class="content">
            <p>Hello Admin,</p>
            <p>You have received a new contact form submission. Details are as follows:</p>
            <p>
                <span class="label">Name:</span> <?= htmlspecialchars($contactSubmission->name ?? 'N/A') ?><br>
                <span class="label">Email:</span> <?= htmlspecialchars($contactSubmission->email ?? 'N/A') ?><br>
                <span class="label">Message:</span><br>
                <pre style="background:#f9f9f9;padding:10px;border-radius:4px;"><?= htmlspecialchars($contactSubmission->message ?? 'N/A') ?></pre>
            </p>
            <p>Please respond as soon as possible.</p>
        </div>
        <div class="footer">
            &copy; <?= date('Y') ?> Your Company. All rights reserved.
        </div>
    </div>
</body>
</html>