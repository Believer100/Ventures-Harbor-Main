<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/mailer.php';
// vh_account_deletion_blockers() — deleting an account is refused while the
// person still has a venture running or a refund of theirs unpaid.
require_once __DIR__ . '/../config/venture-lifecycle.php';

header('Content-Type: application/json');

$input = array_merge($_GET, $_POST);
$raw = file_get_contents('php://input');
if ($raw) {
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $input = array_merge($input, $json);
    }
}

$action = $input['action'] ?? '';

function respondUser(array $user, string $message = 'Success'): void
{
    echo json_encode([
        'success' => true,
        'message' => $message,
        'user' => formatUserPayload($user),
    ]);
    exit;
}

if ($action === 'me') {
    $user = getSessionUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }
    echo json_encode(['success' => true, 'user' => $user]);
    exit;
}

if ($action === 'logout') {
    clearUserSession();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
    exit;
}

if ($action === 'register') {
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $password = $input['password'] ?? '';

    $accountType = in_array($input['account_type'] ?? '', ['individual', 'company'], true)
        ? $input['account_type']
        : '';

    if (!$name || !$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'Name, email, and password are required.']);
        exit;
    }

    if ($accountType === '') {
        echo json_encode(['success' => false, 'message' => 'Please choose whether this is an individual or a company account.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email.']);
        exit;
    }

    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
        exit;
    }

    // Deleting an account is a soft delete — is_deactivated = 1, every record
    // kept, because refunds, transactions and any venture the person founded all
    // reference the user id and would be stranded by a real DELETE. That left
    // the email permanently spent: signing up again answered "an account with
    // this email already exists" and logging in answered "this account has been
    // deleted", so there was no way back onto the platform at all.
    //
    // A deleted account now RELEASES its address to whoever signs up next. The
    // old row keeps everything it had under an archived address (see
    // vh_archive_deleted_email); the signup gets a brand-new, empty account and
    // inherits nothing. Deletion is therefore final for the person who did it —
    // which is what the confirmation dialog on the profile page now says.
    $stmt = $mysqli->prepare('SELECT id, is_deactivated FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing && empty($existing['is_deactivated'])) {
        echo json_encode(['success' => false, 'message' => 'An account with this email already exists.']);
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $avatar = strtoupper(substr($name, 0, 2));
    $otp = (string)rand(100000, 999999);
    $otpExpiry = date('Y-m-d H:i:s', time() + 300);
    $role = 'user';
    $kycStatus = 'pending';

    // Freeing the address and taking it are one operation. Split, a failed
    // insert would leave the deleted row renamed and nobody holding the email.
    $mysqli->begin_transaction();
    try {
        if ($existing && !vh_archive_deleted_email($mysqli, (int)$existing['id'], $email)) {
            throw new RuntimeException('Could not release the email address from the deleted account.');
        }

        $stmt = $mysqli->prepare('INSERT INTO users (name, email, phone, password_hash, avatar, role, account_type, account_type_confirmed, kyc_status, otp_code, otp_expiry) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)');
        $stmt->bind_param('ssssssssss', $name, $email, $phone, $passwordHash, $avatar, $role, $accountType, $kycStatus, $otp, $otpExpiry);
        if (!$stmt->execute()) {
            $err = $mysqli->error;
            $stmt->close();
            throw new RuntimeException($err);
        }
        $userId = $stmt->insert_id;
        $stmt->close();

        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
        exit;
    }

    $subject = 'Verify your Ventures Harbor account';
    $inner = '<h1 style="margin:0 0 8px 0;font-family:\'Outfit\',Arial,sans-serif;font-size:22px;color:#0f172a;">Welcome aboard, ' . htmlspecialchars($name) . ' 👋</h1>'
           . '<p style="margin:0 0 4px 0;font-size:15px;line-height:1.6;color:#475569;">You\'re one step away from joining a community of collaborative entrepreneurs. Enter the code below to verify your email and activate your account.</p>'
           . renderOtpBlock($otp)
           . '<p style="margin:0;font-size:13.5px;line-height:1.6;color:#94a3b8;text-align:center;">This code expires in 5 minutes. If you didn\'t create this account, you can safely ignore this email.</p>';
    $body = renderEmailTemplate($inner, [
        'heading' => 'Verify your Ventures Harbor account',
        'preheader' => 'Your verification code is ' . $otp,
    ]);

    $emailSent = sendEmail($email, $subject, $body);

    echo json_encode([
        'success' => true,
        'message' => $emailSent
            ? 'Account created successfully. OTP code sent to your email.'
            : 'Account created. OTP could not be emailed — check logs/email_fallback.log for the code.',
        'user' => [
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'avatar' => $avatar,
            'role' => 'user',
            'city' => '',
            'occupation' => '',
            'bio' => '',
        ],
        'devOtp' => $emailSent ? null : $otp,
    ]);
    exit;
}

if ($action === 'resend_otp') {
    $email = trim($input['email'] ?? '');
    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Email is required.']);
        exit;
    }

    $stmt = $mysqli->prepare('SELECT id, name FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'No account found for this email.']);
        exit;
    }

    $otp = (string)rand(100000, 999999);
    $otpExpiry = date('Y-m-d H:i:s', time() + 300);

    $stmt = $mysqli->prepare('UPDATE users SET otp_code = ?, otp_expiry = ? WHERE id = ?');
    $stmt->bind_param('ssi', $otp, $otpExpiry, $user['id']);
    $stmt->execute();
    $stmt->close();

    $subject = 'Your Ventures Harbor verification code';
    $inner = '<h1 style="margin:0 0 8px 0;font-family:\'Outfit\',Arial,sans-serif;font-size:22px;color:#0f172a;">Hi ' . htmlspecialchars($user['name']) . ',</h1>'
           . '<p style="margin:0 0 4px 0;font-size:15px;line-height:1.6;color:#475569;">Here\'s the fresh verification code you requested.</p>'
           . renderOtpBlock($otp)
           . '<p style="margin:0;font-size:13.5px;line-height:1.6;color:#94a3b8;text-align:center;">This code expires in 5 minutes. If you didn\'t request this, you can safely ignore this email.</p>';
    $body = renderEmailTemplate($inner, [
        'heading' => 'Your verification code',
        'preheader' => 'Your new verification code is ' . $otp,
    ]);

    $emailSent = sendEmail($email, $subject, $body);

    echo json_encode([
        'success' => true,
        'message' => $emailSent ? 'A new verification code has been sent.' : 'OTP regenerated — check logs/email_fallback.log.',
        'devOtp' => $emailSent ? null : $otp,
    ]);
    exit;
}

if ($action === 'verify_otp') {
    $email = trim($input['email'] ?? '');
    $code = trim($input['code'] ?? '');

    if (!$email || !$code) {
        echo json_encode(['success' => false, 'message' => 'Email and verification code are required.']);
        exit;
    }

    $stmt = $mysqli->prepare('SELECT id, name, email, phone, avatar, avatar_url, role, account_type, account_type_confirmed, company_registration_no, company_founded_year, company_size, kyc_status, city, occupation, bio, age FROM users WHERE email = ? AND otp_code = ? AND otp_expiry >= ?');
    $now = date('Y-m-d H:i:s');
    $stmt->bind_param('sss', $email, $code, $now);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code.']);
        exit;
    }

    // is_deactivated is deliberately NOT touched here. A deleted account is
    // never brought back by anything a visitor can do — signing up on its
    // address creates a separate, empty account — so only an admin's Restore
    // Account action can clear that flag.
    $stmt = $mysqli->prepare('UPDATE users SET otp_code = NULL, otp_expiry = NULL WHERE id = ?');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $stmt->close();

    setUserSession($user);

    respondUser($user, 'Verification successful.');
}

if ($action === 'login') {
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (!$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
        exit;
    }

    $stmt = $mysqli->prepare('SELECT id, name, email, phone, password_hash, avatar, avatar_url, role, account_type, account_type_confirmed, company_registration_no, company_founded_year, company_size, kyc_status, city, occupation, bio, age, is_deactivated FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    if (!empty($user['is_deactivated'])) {
        echo json_encode([
            'success' => false,
            'deletedAccount' => true,
            'message' => 'This account was deleted and cannot be restored. You can sign up again with this email — '
                       . 'that creates a new, empty account rather than bringing this one back.',
        ]);
        exit;
    }

    setUserSession($user);
    respondUser($user, 'Login successful.');
}

function getRefundableFeeTotal(mysqli $mysqli, int $userId): int
{
    $stmt = $mysqli->prepare("
        SELECT COALESCE(SUM(fee_amount), 0) AS paid
        FROM transactions
        WHERE user_id = ? AND type = 'venture_investment' AND status = 'completed'
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $paid = (int) $stmt->get_result()->fetch_assoc()['paid'];
    $stmt->close();

    $stmt = $mysqli->prepare("
        SELECT COALESCE(SUM(amount), 0) AS claimed
        FROM transactions
        WHERE user_id = ? AND type = 'refund_request' AND status IN ('pending', 'completed')
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $claimed = (int) $stmt->get_result()->fetch_assoc()['claimed'];
    $stmt->close();

    return max(0, $paid - $claimed);
}

if ($action === 'refundable_total') {
    $userId = requireAuth();
    echo json_encode(['success' => true, 'refundable' => getRefundableFeeTotal($mysqli, $userId)]);
    exit;
}

if ($action === 'deletion_eligibility') {
    $userId = requireAuth();
    $blockers = vh_account_deletion_blockers($mysqli, $userId);
    echo json_encode([
        'success'  => true,
        'blockers' => $blockers,
        'message'  => $blockers['can_delete'] ? null : vh_account_deletion_message($blockers),
    ]);
    exit;
}

if ($action === 'deactivate') {
    $userId = requireAuth();

    // A founder cannot walk away from a listing that is still running: partners
    // would be left committed to a venture nobody can close, decide applications
    // on, or answer for. The dialog calls deletion_eligibility first and says so
    // up front — this is the check that actually holds, since the dialog is only
    // a browser.
    $blockers = vh_account_deletion_blockers($mysqli, $userId);
    if (!$blockers['can_delete']) {
        http_response_code(409);
        echo json_encode([
            'success'  => false,
            'blocked'  => true,
            'blockers' => $blockers,
            'message'  => vh_account_deletion_message($blockers),
        ]);
        exit;
    }

    $wantsRefund = !empty($input['request_refund']);
    $bank = null;
    $refundAmount = 0;

    if ($wantsRefund) {
        $refundAmount = getRefundableFeeTotal($mysqli, $userId);
        if ($refundAmount <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'You have no refundable commitment fees on record, so there is nothing to refund. Uncheck the refund option to continue deleting your account.',
            ]);
            exit;
        }

        [$bank, $bankError] = validateBankDetails($input);
        if ($bankError) {
            echo json_encode(['success' => false, 'message' => $bankError]);
            exit;
        }
    }

    $stmt = $mysqli->prepare('UPDATE users SET is_deactivated = 1 WHERE id = ?');
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Could not delete your account. Please try again.']);
        exit;
    }
    $stmt->close();

    $stmt = $mysqli->prepare('SELECT name, email FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $who = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($who) {
        $subject = 'Account deleted';
        $body = 'User "' . $who['name'] . '" (' . $who['email'] . ', id ' . $userId . ') deleted their account. '
              . 'The account is deactivated; all records are retained.'
              . ($wantsRefund ? ' A refund request for ₹' . vh_inr($refundAmount) . ' was filed with it.' : '');
        $stmt = $mysqli->prepare("INSERT INTO contact_messages (name, email, subject, message, user_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssi', $who['name'], $who['email'], $subject, $body, $userId);
        $stmt->execute();
        $stmt->close();
    }

    if ($wantsRefund) {
        $txnId = 'VH' . rand(10000000, 99999999);
        $label = 'Account deletion refund';
        $payoutNote = trim($input['payout_note'] ?? '');
        $zero = 0;
        $stmt = $mysqli->prepare("
            INSERT INTO transactions
                (user_id, venture_id, venture_name, amount, principal_amount, fee_amount,
                 type, status, refund_type, txn_id, payment_gateway,
                 bank_account_name, bank_account_number, bank_ifsc, bank_name, payout_note)
            VALUES (?, NULL, ?, ?, ?, ?, 'refund_request', 'pending', 'account_deletion', ?, 'manual', ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            'isiiissssss',
            $userId, $label, $refundAmount, $zero, $refundAmount, $txnId,
            $bank['bank_account_name'], $bank['bank_account_number'], $bank['bank_ifsc'], $bank['bank_name'], $payoutNote
        );
        $stmt->execute();
        $stmt->close();
    }

    clearUserSession();
    echo json_encode([
        'success' => true,
        'message' => $wantsRefund
            ? 'Your account has been deleted and your refund request for ₹' . vh_inr($refundAmount) . ' has been submitted for admin review.'
            : 'Your account has been deleted.',
        'refundRequested' => $refundAmount,
    ]);
    exit;
}

if ($action === 'forgot') {
    $email = trim($input['email'] ?? '');

    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Email is required.']);
        exit;
    }

    $stmt = $mysqli->prepare('SELECT id, name FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['success' => true, 'message' => 'If this email exists in our system, we have sent a reset link.']);
        exit;
    }

    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', time() + 900);

    $stmt = $mysqli->prepare('UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?');
    $stmt->bind_param('ssi', $token, $expiry, $user['id']);
    $stmt->execute();
    $stmt->close();

    $basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
    $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
          . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/users/reset-password.php?token=' . $token;

    $subject = 'Reset your Ventures Harbor password';
    $inner = '<h1 style="margin:0 0 8px 0;font-family:\'Outfit\',Arial,sans-serif;font-size:22px;color:#0f172a;">Reset your password</h1>'
           . '<p style="margin:0 0 4px 0;font-size:15px;line-height:1.6;color:#475569;">Hello ' . htmlspecialchars($user['name']) . ', we received a request to reset your Ventures Harbor password. Click the button below to choose a new one.</p>'
           . renderEmailButton('Reset Password', $link)
           . '<p style="margin:0;font-size:13.5px;line-height:1.6;color:#94a3b8;text-align:center;">This link is valid for 15 minutes. If you didn\'t request a reset, you can safely ignore this email.</p>';
    $body = renderEmailTemplate($inner, [
        'heading' => 'Reset your Ventures Harbor password',
        'preheader' => 'Reset your password — this link expires in 15 minutes',
    ]);

    sendEmail($email, $subject, $body);

    echo json_encode(['success' => true, 'message' => 'Password reset link sent to your email.']);
    exit;
}

if ($action === 'reset') {
    $token = trim($input['token'] ?? '');
    $password = $input['password'] ?? '';

    if (!$token || !$password) {
        echo json_encode(['success' => false, 'message' => 'Token and new password are required.']);
        exit;
    }

    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
        exit;
    }

    $stmt = $mysqli->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_expiry >= NOW()');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired password reset link.']);
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?');
    $stmt->bind_param('si', $passwordHash, $user['id']);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Password reset successfully. You can now login.']);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid request.']);
