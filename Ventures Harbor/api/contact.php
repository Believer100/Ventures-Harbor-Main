<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/mailer.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'submit') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? $_POST;

    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $subject = trim($input['subject'] ?? '');
    $message = trim($input['message'] ?? '');

    if (!$name || !$email || !$message) {
        echo json_encode(['success' => false, 'message' => 'Name, email, and message are required.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }
    if (strlen($message) > 5000) {
        echo json_encode(['success' => false, 'message' => 'Message is too long (max 5000 characters).']);
        exit;
    }

    $sessionUser = getSessionUser();
    $userId = $sessionUser['id'] ?? null;

    $stmt = $mysqli->prepare("INSERT INTO contact_messages (name, email, subject, message, user_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $name, $email, $subject, $message, $userId);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to send your message. Please try again.']);
        exit;
    }
    $stmt->close();

    $settingRow = $mysqli->query("SELECT setting_value FROM settings WHERE setting_key = 'support_email'")->fetch_assoc();
    $supportEmail = $settingRow['setting_value'] ?? 'support@venturesharbor.com';
    $inner = '<h1 style="margin:0 0 16px 0;font-family:\'Outfit\',Arial,sans-serif;font-size:20px;color:#0f172a;">New contact message</h1>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14.5px;color:#334155;">'
        . '<tr><td style="padding:6px 0;width:90px;color:#94a3b8;">From</td><td style="padding:6px 0;">' . htmlspecialchars($name) . ' &lt;' . htmlspecialchars($email) . '&gt;</td></tr>'
        . '<tr><td style="padding:6px 0;color:#94a3b8;">Subject</td><td style="padding:6px 0;">' . htmlspecialchars($subject ?: '(none)') . '</td></tr>'
        . '</table>'
        . '<div style="margin-top:16px;padding:16px 18px;background:#f2f6fb;border-radius:10px;font-size:14.5px;line-height:1.6;color:#334155;">' . nl2br(htmlspecialchars($message)) . '</div>';
    $emailBody = renderEmailTemplate($inner, [
        'heading' => 'New Contact Message',
        'preheader' => 'New message from ' . $name,
    ]);
    @sendEmail($supportEmail, 'New Contact Message: ' . ($subject ?: 'Ventures Harbor Inquiry'), $emailBody);

    echo json_encode(['success' => true, 'message' => "Thanks for reaching out! Our team will get back to you within 24-48 hours."]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Bad request']);
