<?php
/**
 * Service Token Reply Handler
 * Processes the reply form, sends email to the customer, logs the action,
 * and updates the token status.
 */
require_once __DIR__ . '/../includes/header.php';
requireRole(['core_admin', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: service_tokens.php");
    exit;
}

$tokenId       = (int)($_POST['token_id'] ?? 0);
$tokenNumber   = trim($_POST['token_number'] ?? '');
$customerEmail = trim($_POST['customer_email'] ?? '');
$replySubject  = $_POST['reply_subject'] ?? '';
$emailSubject  = trim($_POST['email_subject'] ?? '');
$replyBody     = trim($_POST['reply_body'] ?? '');
$postStatus    = $_POST['post_status'] ?? 'replied';
$ccEmail       = str_replace(["\r", "\n"], '', trim($_POST['cc_email'] ?? ''));

// Validate required fields
if (!$tokenId || !$customerEmail || !$emailSubject || !$replyBody) {
    die('<div style="text-align:center;padding:80px;font-family:Inter,sans-serif;background:#0f172a;color:#f8fafc;min-height:100vh;">
        <h1 style="font-size:48px;color:#ef4444;">Error</h1>
        <p style="color:#94a3b8;">Missing required fields. Please go back and fill in all fields.</p>
        <a href="service_tokens.php" style="color:#10b981;text-decoration:underline;">Return to Service Tokens</a>
    </div>');
}

// Build the HTML email body
$htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; background: #1e293b; border-radius: 16px; overflow: hidden; border: 1px solid rgba(255,255,255,0.06); }
        .header { background: linear-gradient(135deg, #059669, #10b981); padding: 24px 32px; }
        .header h1 { margin: 0; font-size: 20px; color: white; }
        .header p { margin: 8px 0 0; font-size: 12px; color: rgba(255,255,255,0.8); }
        .body { padding: 32px; }
        .body p { line-height: 1.7; color: #cbd5e1; font-size: 14px; margin: 0 0 16px; }
        .token-badge { display: inline-block; background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3); color: #34d399; padding: 6px 14px; border-radius: 8px; font-family: monospace; font-size: 14px; font-weight: bold; letter-spacing: 2px; }
        .footer { padding: 20px 32px; border-top: 1px solid rgba(255,255,255,0.06); text-align: center; }
        .footer p { color: #64748b; font-size: 11px; margin: 0; }
        .footer a { color: #10b981; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚡ Electava</h1>
            <p>Response to your service request</p>
        </div>
        <div class="body">
            <p style="margin-bottom:20px;">
                <span class="token-badge">' . htmlspecialchars($tokenNumber) . '</span>
            </p>
            ' . nl2br(htmlspecialchars($replyBody)) . '
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' Electava · <a href="https://electava.com">electava.com</a></p>
            <p style="margin-top:6px;">This is a response to your service request token ' . htmlspecialchars($tokenNumber) . '</p>
        </div>
    </div>
</body>
</html>';

// Build email headers
$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/html; charset=UTF-8\r\n";
$headers .= "From: Electava Team <noreply@electava.com>\r\n";
$headers .= "Reply-To: support@electava.com\r\n";
if ($ccEmail) {
    $headers .= "Cc: $ccEmail\r\n";
}

// Attempt to send the email
$mailSent = @mail($customerEmail, $emailSubject, $htmlBody, $headers);

// Update token status
$stmt = $pdo->prepare("UPDATE service_tokens SET status = ? WHERE id = ?");
$stmt->execute([$postStatus, $tokenId]);

// Log the reply in audit
$replyLog = json_encode([
    'category' => $replySubject,
    'subject' => $emailSubject,
    'to' => $customerEmail,
    'cc' => $ccEmail,
    'sent_by' => $_SESSION['full_name'] ?? $_SESSION['username'],
    'sent_at' => date('Y-m-d H:i:s'),
    'mail_status' => $mailSent ? 'sent' : 'queued_locally'
]);

logAudit($pdo, 'reply_service_token', 'service_token', $tokenId, $replyLog);

// Redirect back
header("Location: service_tokens.php?msg=reply_sent");
exit;
