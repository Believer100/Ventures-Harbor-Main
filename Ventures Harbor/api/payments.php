<?php

if (($_GET['action'] ?? $_POST['action'] ?? '') === 'callback') {
    ini_set('session.use_cookies', '0');
    ini_set('session.use_only_cookies', '0');
    ini_set('session.cache_limiter', '');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/payu.php';
require_once __DIR__ . '/../config/membership.php';
require_once __DIR__ . '/../config/venture-lifecycle.php';
require_once __DIR__ . '/../config/waitlist.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function vhPayuTxnId(mysqli $mysqli): string
{
    $stmt = $mysqli->prepare('SELECT id FROM payment_intents WHERE txn_id = ?');
    for ($i = 0; $i < 10; $i++) {
        $candidate = 'VH' . date('ymd') . random_int(100000, 999999);
        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $stmt->store_result();
        $taken = $stmt->num_rows > 0;
        $stmt->free_result();
        if (!$taken) {
            $stmt->close();
            return $candidate;
        }
    }
    $stmt->close();
    return 'VH' . date('ymd') . substr((string)microtime(true), -6) . random_int(10, 99);
}

function vhPayuCloseIntent(mysqli $mysqli, int $intentId, string $status, ?string $gatewayPaymentId, ?string $gatewayStatus, ?string $error, array $post): void
{
    $raw = json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $error = $error !== null ? mb_substr($error, 0, 255) : null;
    $stmt = $mysqli->prepare("
        UPDATE payment_intents
        SET status = ?, gateway_payment_id = ?, gateway_status = ?, error_message = ?,
            raw_response = ?, completed_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param('sssssi', $status, $gatewayPaymentId, $gatewayStatus, $error, $raw, $intentId);
    $stmt->execute();
    $stmt->close();
}

function vhPayuReturn(string $txnId, string $state): void
{
    $base = vh_payu_base_url();
    header('Location: ' . $base . '/pages/payment-return.php?txnid=' . urlencode($txnId) . '&state=' . urlencode($state));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'initiate') {
    header('Content-Type: application/json');
    vh_run_lifecycle_sweep($mysqli);

    $userId = requireAuth();
    $input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $cfg = vh_payu_config($mysqli);
    if (!$cfg['enabled']) {
        echo json_encode(['success' => false, 'message' => 'Online payments are not enabled.', 'gateway' => 'disabled']);
        exit;
    }
    if ($cfg['error']) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $cfg['error']]);
        exit;
    }

    $ventureId     = isset($input['venture_id']) ? (int)$input['venture_id'] : 0;
    $applicationId = isset($input['application_id']) ? (int)$input['application_id'] : 0;
    $role          = ($input['role'] ?? 'silent') === 'active' ? 'active' : 'silent';
    $investedAmount = isset($input['invested_amount']) ? (int)$input['invested_amount'] : 0;

    
    
    
    
    $purpose = ($input['purpose'] ?? 'membership') === 'venture_listing'
        ? 'venture_listing'
        : 'membership';

    // A claimed waitlist seat pays through the same gateway as any membership; the
    // seat only decides WHAT is being bought. Everything about it is re-read from
    // venture_slots below, never taken from the request.
    $slotId = isset($input['slot_id']) ? (int)$input['slot_id'] : 0;

    if ($purpose === 'venture_listing') {
        
        
        
        $stmt = $mysqli->prepare("SELECT id, title, status, founder_user_id, founder_contribution FROM ventures WHERE id = ?");
        $stmt->bind_param('i', $ventureId);
        $stmt->execute();
        $pending = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$pending) {
            echo json_encode(['success' => false, 'message' => 'Asset not found.']);
            exit;
        }
        if ((int)$pending['founder_user_id'] !== $userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only the founder can pay this listing fee.']);
            exit;
        }
        if ($pending['status'] !== 'pending_payment') {
            echo json_encode([
                'success' => false,
                'message' => $pending['status'] === 'active'
                    ? 'This Asset is already live — its listing fee has been paid.'
                    : 'This Asset is no longer awaiting a listing fee.',
            ]);
            exit;
        }

        $investedAmount = (int)$pending['founder_contribution'];
        $feeAmount      = vh_founder_listing_fee($investedAmount);
        if ($feeAmount < PAYU_MIN_AMOUNT) {
            echo json_encode(['success' => false, 'message' => 'This listing has no fee to pay.']);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT name, email, phone, role FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $phone = preg_replace('/\D+/', '', (string)($user['phone'] ?? ''));
        if (strlen($phone) < 10) {
            echo json_encode([
                'success' => false,
                'message' => 'Please add a valid phone number to your profile before paying — the payment gateway requires one.',
                'needsProfile' => true,
            ]);
            exit;
        }

        $venture = $pending;
        $chargedAmount = $feeAmount;
        $role = 'founder';
    }

    
    
    
    if ($purpose === 'membership') {

    // Paying for a seat this person is holding. vh_slot_payable_by() is the same
    // check api/waitlist.php's demo route makes, so the gateway cannot be used to
    // walk around the claim lock: no hold, no payment.
    if ($slotId > 0) {
        [$slotOk, $slotMsg, $slot] = vh_slot_payable_by($mysqli, $slotId, $userId);
        if (!$slotOk) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => $slotMsg]);
            exit;
        }
        $ventureId      = (int)$slot['venture_id'];
        $role           = $slot['role'] === 'active' ? 'active' : 'silent';
        $investedAmount = (int)$slot['invested_amount'];
        $applicationId  = 0;
    }

    
    
    
    if ($applicationId > 0) {
        $stmt = $mysqli->prepare("
            SELECT a.id, a.venture_id, a.user_id, a.role, a.invested_amount, a.status, v.min_investment
            FROM venture_applications a JOIN ventures v ON a.venture_id = v.id
            WHERE a.id = ?
        ");
        $stmt->bind_param('i', $applicationId);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$application || (int)$application['user_id'] !== $userId) {
            echo json_encode(['success' => false, 'message' => 'Application not found.']);
            exit;
        }
        if ($application['status'] !== 'selected') {
            echo json_encode(['success' => false, 'message' => 'This application is not ready for payment.']);
            exit;
        }

        $ventureId      = (int)$application['venture_id'];
        $role           = $application['role'] === 'active' ? 'active' : 'silent';
        $investedAmount = (int)$application['invested_amount'];
        if ($role === 'active' && $investedAmount <= 0) {
            $investedAmount = (int)$application['min_investment'];
        }
    }

    if (!$ventureId) {
        echo json_encode(['success' => false, 'message' => 'Missing Asset.']);
        exit;
    }
    if ($applicationId === 0 && $investedAmount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please enter an investment amount.']);
        exit;
    }

    $stmt = $mysqli->prepare("SELECT id, title, status, is_showcase, founder_user_id, target_capital, raised_capital, min_investment, silent_capital_limit, founder_contribution, partner_types FROM ventures WHERE id = ?");
    $stmt->bind_param('i', $ventureId);
    $stmt->execute();
    $venture = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$venture) {
        echo json_encode(['success' => false, 'message' => 'Asset not found.']);
        exit;
    }

    
    
    $venture = vh_capital_split($mysqli, $ventureId, $venture);
    if ($venture['status'] !== 'active') {
        echo json_encode(['success' => false, 'message' => vh_closed_message($venture['status'])]);
        exit;
    }
    if ((int)$venture['founder_user_id'] === $userId) {
        echo json_encode(['success' => false, 'message' => 'You are the founder of this Asset — you cannot join it.']);
        exit;
    }

    // Same reservation rule the demo route enforces in api/ventures.php's join:
    // room freed by an exit belongs to the waitlist until its seat is settled.
    // $slotId > 0 IS the waitlist route, so it is exempt.
    if ($slotId === 0) {
        $reservedSeats = vh_reserved_seat_capital($mysqli, $ventureId);
        if ($reservedSeats > 0) {
            $payLimits = vh_pledge_limits($venture, $role);
            if ($payLimits['remaining'] - $reservedSeats < $investedAmount) {
                http_response_code(409);
                echo json_encode([
                    'success'      => false,
                    'seatReserved' => true,
                    'message'      => 'The room left in this Asset belongs to its waitlist — a partner exited and '
                                    . 'everyone waiting was offered the seat.',
                ]);
                exit;
            }
        }
    }

    
    
    
    
    if ($applicationId === 0) {
        $openness = vh_venture_openness($venture);
        if (($role === 'active' && !$openness['accepts_active'])
            || ($role !== 'active' && !$openness['accepts_silent'])) {
            echo json_encode([
                'success'   => false,
                'message'   => vh_role_closed_message($venture, $role),
                'tryActive' => $role !== 'active' && $openness['accepts_active'],
            ]);
            exit;
        }
    }

    $stmt = $mysqli->prepare("SELECT name, email, phone, role FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }
    if (($user['role'] ?? '') === 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin accounts cannot join Assets.']);
        exit;
    }

    $stmt = $mysqli->prepare("SELECT id FROM venture_members WHERE venture_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $ventureId, $userId);
    $stmt->execute();
    $stmt->store_result();
    $isMember = $stmt->num_rows > 0;
    $stmt->close();
    if ($isMember) {
        echo json_encode(['success' => false, 'message' => 'You are already a member of this Asset.']);
        exit;
    }

    
    
    
    $phone = preg_replace('/\D+/', '', (string)($user['phone'] ?? ''));
    if (strlen($phone) < 10) {
        echo json_encode([
            'success' => false,
            'message' => 'Please add a valid phone number to your profile before paying — the payment gateway requires one.',
            'needsProfile' => true,
        ]);
        exit;
    }

    
    
    
    
    if ($applicationId > 0) {
        
        
        $limits = vh_pledge_limits($venture, $role === 'active' ? 'active' : 'silent');
        if ($role === 'active') {
            
            
            
            
            
            
            
            $investedAmount = vh_active_pledge_for($venture, $investedAmount);
            if ($investedAmount <= 0) {
                echo json_encode([
                    'success'         => false,
                    'noPaymentNeeded' => true,
                    'message'         => 'This Asset has already raised its full target, so there is no commitment fee to pay — you can confirm your place for free.',
                ]);
                exit;
            }
        } elseif ($limits['is_full']) {
            echo json_encode([
                'success' => false,
                'message' => 'This Asset reached its funding target while your application was open, so no further commitments can be accepted. Please contact the founder.',
            ]);
            exit;
        } elseif ($investedAmount > $limits['max']) {
            $investedAmount = $limits['max'];
        }
    } else {
        $pledgeError = vh_validate_pledge($venture, $investedAmount, $role === 'active' ? 'active' : 'silent');
        if ($pledgeError !== null) {
            echo json_encode(['success' => false, 'message' => $pledgeError]);
            exit;
        }
    }

    
    
    $feeAmount     = (int) round($investedAmount * VH_FEE_RATE);
    $chargedAmount = $feeAmount;

    } // end of the membership-only branch

    if ($chargedAmount < PAYU_MIN_AMOUNT) {
        echo json_encode([
            'success' => false,
            'message' => 'The commitment fee for this amount is below the ₹1 minimum the payment gateway accepts. Please increase your investment amount.',
        ]);
        exit;
    }

    $txnId = vhPayuTxnId($mysqli);
    $appIdForRow = $applicationId > 0 ? $applicationId : null;

    $slotIdForRow = $slotId > 0 ? $slotId : null;

    $stmt = $mysqli->prepare("
        INSERT INTO payment_intents
            (txn_id, user_id, venture_id, application_id, slot_id, role, invested_amount, fee_amount, amount,
             purpose, status, gateway, gateway_mode)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'created', 'payu', ?)
    ");
    $stmt->bind_param('siiiisiiiss', $txnId, $userId, $ventureId, $appIdForRow, $slotIdForRow, $role,
        $investedAmount, $feeAmount, $chargedAmount, $purpose, $cfg['mode']);
    if (!$stmt->execute()) {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not start the payment. Please try again.']);
        exit;
    }
    $stmt->close();

    $base = vh_payu_base_url();
    
    
    
    $callbackUrl = $base . '/api/payments.php?action=callback';

    $fields = [
        'key'         => $cfg['key'],
        'txnid'       => $txnId,
        'amount'      => vh_payu_amount($chargedAmount),
        'productinfo' => vh_payu_clean(
            ($purpose === 'venture_listing' ? 'Listing fee ' : 'Commitment fee ') . $venture['title'], 100),
        'firstname'   => vh_payu_clean((string)$user['name'], 60),
        'email'       => (string)$user['email'],
        'phone'       => $phone,
        'surl'        => $callbackUrl,
        'furl'        => $callbackUrl,
        
        
        'udf1'        => (string)$ventureId,
        'udf2'        => $role,
        'udf3'        => (string)($applicationId ?: ''),
        'udf4'        => (string)$userId,
        'udf5'        => $purpose,
    ];
    $fields['hash'] = vh_payu_request_hash($fields, $cfg['salt']);

    echo json_encode([
        'success'  => true,
        'endpoint' => $cfg['endpoint'],
        'fields'   => $fields,
        'txnId'    => $txnId,
        'amount'   => $chargedAmount,
        'mode'     => $cfg['mode'],
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'callback') {
    $post  = $_POST;
    $txnId = (string)($post['txnid'] ?? '');

    if ($txnId === '') {
        vhPayuReturn('', 'invalid');
    }

    $stmt = $mysqli->prepare("
        SELECT id, txn_id, user_id, venture_id, application_id, slot_id, role,
               invested_amount, fee_amount, amount, purpose, status, gateway_mode
        FROM payment_intents WHERE txn_id = ?
    ");
    $stmt->bind_param('s', $txnId);
    $stmt->execute();
    $intent = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$intent) {
        vhPayuReturn($txnId, 'invalid');
    }

    
    
    if ($intent['status'] !== 'created') {
        vhPayuReturn($txnId, $intent['status'] === 'completed' ? 'success' : 'failed');
    }

    $cfg = vh_payu_config($mysqli);

    
    
    if ($cfg['salt'] === '' || !vh_payu_verify_response($post, $cfg['salt'])) {
        vhPayuCloseIntent($mysqli, (int)$intent['id'], 'failed', null,
            (string)($post['status'] ?? ''),
            'Response signature did not verify — the reply was not trusted.', $post);
        error_log('[Ventures Harbor] PayU hash verification FAILED for txnid ' . $txnId);
        vhPayuReturn($txnId, 'invalid');
    }

    $gatewayStatus    = strtolower(trim((string)($post['status'] ?? '')));
    $gatewayPaymentId = (string)($post['mihpayid'] ?? '');
    $postedAmount     = (float)($post['amount'] ?? 0);
    $expectedAmount   = (float)$intent['amount'];

    if ($gatewayStatus !== 'success') {
        $reason = trim((string)($post['error_Message'] ?? $post['field9'] ?? '')) ?: 'Payment was not completed.';
        vhPayuCloseIntent($mysqli, (int)$intent['id'],
            $gatewayStatus === 'pending' ? 'created' : 'failed',
            $gatewayPaymentId ?: null, $gatewayStatus, $reason, $post);
        vhPayuReturn($txnId, $gatewayStatus === 'pending' ? 'pending' : 'failed');
    }

    
    
    
    if (abs($postedAmount - $expectedAmount) > 0.01) {
        vhPayuCloseIntent($mysqli, (int)$intent['id'], 'failed', $gatewayPaymentId ?: null, $gatewayStatus,
            'Amount mismatch: gateway reported ' . $postedAmount . ', expected ' . $expectedAmount . '.', $post);
        error_log('[Ventures Harbor] PayU AMOUNT MISMATCH for txnid ' . $txnId
            . ' (got ' . $postedAmount . ', expected ' . $expectedAmount . ')');
        vhPayuReturn($txnId, 'invalid');
    }

    
    
    
    $stmt = $mysqli->prepare("UPDATE payment_intents SET status = 'completed' WHERE id = ? AND status = 'created'");
    $stmt->bind_param('i', $intent['id']);
    $stmt->execute();
    $claimed = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$claimed) {
        vhPayuReturn($txnId, 'success');
    }

    vhPayuCloseIntent($mysqli, (int)$intent['id'], 'completed', $gatewayPaymentId ?: null, $gatewayStatus, null, $post);

    $stmt = $mysqli->prepare("SELECT title FROM ventures WHERE id = ?");
    $stmt->bind_param('i', $intent['venture_id']);
    $stmt->execute();
    $venture = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $ventureTitle = $venture['title'] ?? 'Venture';

    
    
    $purpose  = $intent['purpose'] ?? 'membership';
    $txnType  = $purpose === 'venture_listing' ? 'venture_listing_fee' : 'venture_investment';

    
    $stmt = $mysqli->prepare("
        INSERT INTO transactions
            (user_id, venture_id, venture_name, amount, principal_amount, fee_amount,
             type, status, txn_id, payment_gateway, gateway_order_id, gateway_payment_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?, 'payu', ?, ?)
    ");
    $stmt->bind_param('iisiiissss',
        $intent['user_id'], $intent['venture_id'], $ventureTitle,
        $intent['amount'], $intent['invested_amount'], $intent['fee_amount'],
        $txnType, $txnId, $txnId, $gatewayPaymentId);
    $stmt->execute();
    $stmt->close();

    if ($purpose === 'venture_listing') {
        
        
        
        vh_activate_pending_venture($mysqli, (int)$intent['venture_id']);
    } elseif (!empty($intent['slot_id'])) {
        // A claimed waitlist seat. vh_fill_slot() marks the seat under a guard, so
        // a duplicated callback cannot fill it twice, then grants the membership
        // through the one function that does that and stamps the member's own
        // 24-hour window — they joined without a meeting, so the venture-level one
        // can never open for them.
        vh_fill_slot(
            $mysqli,
            (int)$intent['slot_id'],
            (int)$intent['user_id'],
            (int)$intent['fee_amount'],
            $txnId
        );
    } else {
        vh_grant_membership($mysqli, [
            'user_id'         => (int)$intent['user_id'],
            'venture_id'      => (int)$intent['venture_id'],
            'role'            => $intent['role'],
            'invested_amount' => (int)$intent['invested_amount'],
            'fee_amount'      => (int)$intent['fee_amount'],
            'txn_id'          => $txnId,
            'application_id'  => (int)($intent['application_id'] ?? 0),
        ]);
    }

    vhPayuReturn($txnId, 'success');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'status') {
    header('Content-Type: application/json');
    $userId = requireAuth();
    $txnId  = (string)($_GET['txnid'] ?? '');

    $stmt = $mysqli->prepare("
        SELECT pi.txn_id, pi.status, pi.amount, pi.invested_amount, pi.fee_amount, pi.role,
               pi.purpose, pi.gateway_status, pi.error_message, pi.venture_id, pi.created_at,
               v.title AS venture_title
        FROM payment_intents pi
        JOIN ventures v ON v.id = pi.venture_id
        WHERE pi.txn_id = ? AND pi.user_id = ?
    ");
    $stmt->bind_param('si', $txnId, $userId);
    $stmt->execute();
    $intent = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$intent) {
        echo json_encode(['success' => false, 'message' => 'Payment not found.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'payment' => [
            'txnId'         => $intent['txn_id'],
            'status'        => $intent['status'],
            'amount'        => (int)$intent['amount'],
            'investedAmount'=> (int)$intent['invested_amount'],
            'feeAmount'     => (int)$intent['fee_amount'],
            'role'          => $intent['role'],
            'purpose'       => $intent['purpose'],
            'ventureId'     => (int)$intent['venture_id'],
            'ventureTitle'  => $intent['venture_title'],
            'errorMessage'  => $intent['error_message'],
            'createdAt'     => $intent['created_at'],
        ],
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'config') {
    header('Content-Type: application/json');
    $cfg = vh_payu_config($mysqli);
    echo json_encode([
        'success' => true,
        'gateway' => [
            'enabled' => $cfg['enabled'] && !$cfg['error'],
            'mode'    => $cfg['mode'],
            'name'    => 'PayU',
        ],
    ]);
    exit;
}

header('Content-Type: application/json');
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Bad request']);
