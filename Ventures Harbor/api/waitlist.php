<?php
/*
 * Waitlist and vacated-seat endpoints.
 *
 * The rules live in config/waitlist.php; this file is the wire. Note that every
 * write here re-derives its own facts from the database — the seat's role, its
 * amount and therefore its fee all come off the venture_slots row, never from the
 * request, so a crafted POST cannot buy a ₹40L seat for a ₹1 fee.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/waitlist.php';
require_once __DIR__ . '/../config/payu.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
if (!$action) {
    $action = $input['action'] ?? '';
}

vh_run_lifecycle_sweep($mysqli);
vh_release_expired_slots($mysqli);

/** The venture row every action here needs, with the columns openness reads. */
function wlLoadVenture(mysqli $mysqli, int $ventureId): ?array
{
    $stmt = $mysqli->prepare("
        SELECT id, title, status, is_showcase, partner_types, target_capital, raised_capital,
               founder_contribution, founder_user_id, min_investment, silent_capital_limit,
               listing_ends_at, members_count
          FROM ventures WHERE id = ?
    ");
    $stmt->bind_param('i', $ventureId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/* ---------------------------------------------------------------- GET ---- */

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // What the Co-Own / Join Waitlist button needs to know about one Asset.
    if ($action === 'status') {
        $userId    = requireAuth();
        $ventureId = isset($_GET['venture_id']) ? (int)$_GET['venture_id'] : 0;

        $venture = wlLoadVenture($mysqli, $ventureId);
        if (!$venture) {
            echo json_encode(['success' => false, 'message' => 'Asset not found.']);
            exit;
        }

        $rows = [$venture];
        vh_attach_capital_split($mysqli, $rows);
        $venture = $rows[0];

        echo json_encode([
            'success'         => true,
            'waitlist_open'   => vh_waitlist_is_open($venture),
            'waitlist_status' => vh_waitlist_status($mysqli, $ventureId, $userId),
            'waitlist_count'  => vh_waitlist_count($mysqli, $ventureId),
        ]);
        exit;
    }

    // The Waitlist page: every Asset this person is waiting on, and every seat
    // currently on offer to them.
    if ($action === 'mine') {
        $userId = requireAuth();

        $stmt = $mysqli->prepare("
            SELECT w.id, w.venture_id, w.status, w.created_at,
                   v.title, v.industry, v.location, v.target_capital, v.raised_capital,
                   v.members_count, v.progress_percent, v.listing_ends_at, v.status AS venture_status,
                   v.founder_name, v.icon_bg, v.icon_color, v.cover_image, v.logo_url,
                   -- What KIND of seat this Asset can ever open, so the page does not
                   -- describe a silent route on a listing that has no silent role.
                   v.partner_types
              FROM venture_waitlist w
              JOIN ventures v ON v.id = w.venture_id
             WHERE w.user_id = ?
             ORDER BY w.created_at DESC
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($entries as &$e) {
            $endsAt = $e['listing_ends_at'];
            $e['days_left']      = $endsAt ? max(0, (int)ceil((strtotime($endsAt) - time()) / 86400)) : 0;
            $e['waiting_count']  = vh_waitlist_count($mysqli, (int)$e['venture_id']);
        }
        unset($e);

        // Seats on offer: open, or held by this very user (so a claimant still
        // sees their own hold and its countdown after a reload).
        $stmt = $mysqli->prepare("
            SELECT s.*, v.title, v.industry, v.location, v.founder_name
              FROM venture_slots s
              JOIN ventures v ON v.id = s.venture_id
              JOIN venture_waitlist w ON w.venture_id = s.venture_id AND w.user_id = ?
             WHERE w.status = 'waiting'
               AND (s.status = 'open' OR (s.status = 'claiming' AND s.claimed_by_user_id = ?))
             ORDER BY s.created_at ASC
        ");
        $stmt->bind_param('ii', $userId, $userId);
        $stmt->execute();
        $slots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($slots as &$s) {
            $s['is_mine']       = (int)$s['claimed_by_user_id'] === $userId && $s['status'] === 'claiming';
            // Measured by the database. PHP's clock is 3.5h off MySQL's here, which
            // was rendering a 10-minute hold as "3h 40m left to pay".
            $s['seconds_left']  = vh_slot_seconds_left($mysqli, (int)$s['id']);
            $s['claim_minutes'] = VH_SLOT_CLAIM_MINUTES;

            /* An active seat is applied for, not claimed, so the page needs to know
               which kind it is looking at and where this person stands with it. */
            $s['is_founder_choice'] = vh_slot_is_founder_choice($s);
            $s['pay_days']          = VH_SLOT_ACTIVE_PAY_DAYS;
            $s['my_application']    = '';
            $s['applicant_count']   = 0;

            if ($s['is_founder_choice']) {
                $q = $mysqli->prepare(
                    "SELECT status FROM venture_applications WHERE slot_id = ? AND user_id = ? LIMIT 1"
                );
                $q->bind_param('ii', $s['id'], $userId);
                $q->execute();
                $mine = $q->get_result()->fetch_assoc();
                $q->close();
                $s['my_application'] = $mine ? (string)$mine['status'] : '';

                $q = $mysqli->prepare(
                    "SELECT COUNT(*) c FROM venture_applications WHERE slot_id = ? AND status = 'pending'"
                );
                $q->bind_param('i', $s['id']);
                $q->execute();
                $s['applicant_count'] = (int)($q->get_result()->fetch_assoc()['c'] ?? 0);
                $q->close();
            }
        }
        unset($s);

        echo json_encode([
            'success' => true,
            'entries' => $entries,
            'slots'   => $slots,
        ]);
        exit;
    }

    // One seat, for the claim/pay screen.
    if ($action === 'slot') {
        $userId = requireAuth();
        $slotId = isset($_GET['slot_id']) ? (int)$_GET['slot_id'] : 0;

        $stmt = $mysqli->prepare("
            SELECT s.*, v.title, v.industry, v.location, v.founder_name, v.min_investment
              FROM venture_slots s JOIN ventures v ON v.id = s.venture_id
             WHERE s.id = ?
        ");
        $stmt->bind_param('i', $slotId);
        $stmt->execute();
        $slot = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$slot) {
            echo json_encode(['success' => false, 'message' => 'That seat no longer exists.']);
            exit;
        }

        $slot['is_mine']      = (int)$slot['claimed_by_user_id'] === $userId && $slot['status'] === 'claiming';
        $slot['seconds_left'] = vh_slot_seconds_left($mysqli, (int)$slot['id']);
        $slot['on_waitlist']  = vh_waitlist_status($mysqli, (int)$slot['venture_id'], $userId) === 'waiting';

        echo json_encode(['success' => true, 'slot' => $slot, 'claim_minutes' => VH_SLOT_CLAIM_MINUTES]);
        exit;
    }
}

/* --------------------------------------------------------------- POST ---- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($action === 'join') {
        $userId    = requireAuth();
        $ventureId = isset($input['venture_id']) ? (int)$input['venture_id'] : 0;

        $venture = wlLoadVenture($mysqli, $ventureId);
        if (!$venture) {
            echo json_encode(['success' => false, 'message' => 'Asset not found.']);
            exit;
        }

        $rows = [$venture];
        vh_attach_capital_split($mysqli, $rows);
        $venture = $rows[0];

        if (!vh_waitlist_is_open($venture)) {
            echo json_encode([
                'success' => false,
                'message' => 'This Asset is not taking a waitlist right now. It either still has room to Co-Own, or its listing has ended.',
            ]);
            exit;
        }

        if ((int)$venture['founder_user_id'] === $userId) {
            echo json_encode(['success' => false, 'message' => 'You founded this Asset — you cannot waitlist for it.']);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT id FROM venture_members WHERE venture_id = ? AND user_id = ?");
        $stmt->bind_param('ii', $ventureId, $userId);
        $stmt->execute();
        $stmt->store_result();
        $isMember = $stmt->num_rows > 0;
        $stmt->close();

        if ($isMember) {
            echo json_encode(['success' => false, 'message' => 'You are already a partner in this Asset.']);
            exit;
        }

        // ON DUPLICATE re-opens a row this person previously left, rather than
        // failing on the unique key or stacking a second row beside it.
        $stmt = $mysqli->prepare("
            INSERT INTO venture_waitlist (venture_id, user_id, status)
            VALUES (?, ?, 'waiting')
            ON DUPLICATE KEY UPDATE status = 'waiting'
        ");
        $stmt->bind_param('ii', $ventureId, $userId);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            echo json_encode(['success' => false, 'message' => 'Could not join the waitlist.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'You are on the waitlist. If a partner exits, everyone waiting is notified at the same moment — '
                       . 'the first to claim the seat takes it.',
            'waitlist_count' => vh_waitlist_count($mysqli, $ventureId),
        ]);
        exit;
    }

    if ($action === 'leave') {
        $userId    = requireAuth();
        $ventureId = isset($input['venture_id']) ? (int)$input['venture_id'] : 0;

        $stmt = $mysqli->prepare("UPDATE venture_waitlist SET status = 'left' WHERE venture_id = ? AND user_id = ? AND status = 'waiting'");
        $stmt->bind_param('ii', $ventureId, $userId);
        $stmt->execute();
        $stmt->close();

        // Holding a seat and then leaving the waitlist gives the seat back, or it
        // would sit locked for the full window with nobody able to take it.
        $stmt = $mysqli->prepare("
            UPDATE venture_slots
               SET status = 'open', claimed_by_user_id = NULL, claim_expires_at = NULL
             WHERE venture_id = ? AND claimed_by_user_id = ? AND status = 'claiming'
        ");
        $stmt->bind_param('ii', $ventureId, $userId);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'You have left the waitlist.']);
        exit;
    }

    if ($action === 'claim') {
        $userId = requireAuth();
        $slotId = isset($input['slot_id']) ? (int)$input['slot_id'] : 0;

        [$ok, $message, $slot] = vh_claim_slot($mysqli, $slotId, $userId);

        if (!$ok) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }

        echo json_encode([
            'success'      => true,
            'message'      => $message,
            'slot_id'      => (int)$slot['id'],
            'seconds_left' => vh_slot_seconds_left($mysqli, (int)$slot['id']),
            'fee_amount'   => (int)$slot['fee_amount'],
        ]);
        exit;
    }

    /* ── ACTIVE SEATS ───────────────────────────────────────────────────────
       Applied for, then chosen by the founder. Multipart, not JSON: a resume is
       attached. $input already falls back to $_POST when php://input is empty,
       so the shared reader above handles that without a special case. */

    if ($action === 'apply_slot') {
        $userId = requireAuth();
        $slotId = isset($input['slot_id']) ? (int)$input['slot_id'] : 0;

        /* Everything is validated BEFORE the file is written, so a refusal cannot
           leave an orphan in uploads/resumes/. The apply path in api/ventures.php
           unlinks after the fact because it has to; here the order is free. */
        [$slotOk, $slotWhy] = vh_slot_open_for_applications($mysqli, $slotId, $userId);
        if (!$slotOk) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => $slotWhy]);
            exit;
        }

        $whatsapp = vh_normalize_whatsapp($input['whatsapp_number'] ?? null);
        if (!$whatsapp) {
            echo json_encode(['success' => false, 'message' =>
                'Please give a WhatsApp number the founder can reach you on.']);
            exit;
        }

        if (!isset($_FILES['resume']) || $_FILES['resume']['error'] === UPLOAD_ERR_NO_FILE) {
            echo json_encode(['success' => false, 'message' =>
                'Please attach your resume (PDF, DOC or DOCX) — the founder chooses from these.']);
            exit;
        }
        $resumeFile = $_FILES['resume'];
        if ($resumeFile['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Resume upload failed. Please try again.']);
            exit;
        }
        $resumeExt = strtolower(pathinfo($resumeFile['name'], PATHINFO_EXTENSION));
        if (!in_array($resumeExt, ['pdf', 'doc', 'docx'], true)) {
            echo json_encode(['success' => false, 'message' => 'Resume must be a PDF, DOC or DOCX file.']);
            exit;
        }
        if ($resumeFile['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Resume must be smaller than 5MB.']);
            exit;
        }
        $resumeDir = __DIR__ . '/../uploads/resumes';
        if (!is_dir($resumeDir) && !mkdir($resumeDir, 0755, true) && !is_dir($resumeDir)) {
            echo json_encode(['success' => false, 'message' =>
                'Server could not create the resumes folder. Check uploads permissions.']);
            exit;
        }
        $resumeNewName = 'seat_' . $slotId . '_' . $userId . '_' . time() . '.' . $resumeExt;
        if (!move_uploaded_file($resumeFile['tmp_name'], $resumeDir . '/' . $resumeNewName)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded resume.']);
            exit;
        }

        [$ok, $message, $appId] = vh_apply_for_slot($mysqli, $slotId, $userId, [
            'message'         => trim((string)($input['message'] ?? '')),
            'resume_path'     => 'uploads/resumes/' . $resumeNewName,
            'resume_name'     => substr((string)$resumeFile['name'], 0, 200),
            'whatsapp_number' => $whatsapp,
        ]);

        if (!$ok) {
            @unlink($resumeDir . '/' . $resumeNewName);
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }

        echo json_encode(['success' => true, 'message' => $message, 'application_id' => $appId]);
        exit;
    }

    if ($action === 'select_candidate') {
        $founderId = requireAuth();
        $slotId    = isset($input['slot_id']) ? (int)$input['slot_id'] : 0;
        $appId     = isset($input['application_id']) ? (int)$input['application_id'] : 0;

        [$ok, $message, $slot] = vh_select_slot_candidate($mysqli, $slotId, $appId, $founderId);
        if (!$ok) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }

        echo json_encode([
            'success'      => true,
            'message'      => $message,
            'seconds_left' => vh_slot_seconds_left($mysqli, (int)$slot['id']),
        ]);
        exit;
    }

    if ($action === 'reopen_slot') {
        $founderId = requireAuth();
        $slotId    = isset($input['slot_id']) ? (int)$input['slot_id'] : 0;

        [$ok, $message, $notified] = vh_reopen_slot($mysqli, $slotId, $founderId);
        if (!$ok) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }

        echo json_encode(['success' => true, 'message' => $message, 'notified' => $notified]);
        exit;
    }

    if ($action === 'release') {
        $userId = requireAuth();
        $slotId = isset($input['slot_id']) ? (int)$input['slot_id'] : 0;

        $released = vh_release_slot($mysqli, $slotId, $userId);
        echo json_encode([
            'success' => $released,
            'message' => $released ? 'You gave the seat back. Anyone waiting can claim it now.' : 'You are not holding that seat.',
        ]);
        exit;
    }

    /*
     * Pay for a held seat, demo route.
     *
     * The live-mode guard is the same one join/confirm_application/create use —
     * vh_payu_allows_manual_payment(). With PayU on and live, a seat is confirmed
     * by the verified callback in api/payments.php and by nothing else.
     */
    if ($action === 'pay') {
        $userId = requireAuth();
        $slotId = isset($input['slot_id']) ? (int)$input['slot_id'] : 0;

        [$ok, $message, $slot] = vh_slot_payable_by($mysqli, $slotId, $userId);
        if (!$ok) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }

        // Recomputed from the stored seat, never taken from the request.
        $investedAmount = (int)$slot['invested_amount'];
        $feeAmount      = (int)round($investedAmount * VH_FEE_RATE);

        if ($feeAmount > 0) {
            $payuCfg = vh_payu_config($mysqli);
            if (!vh_payu_allows_manual_payment($payuCfg)) {
                http_response_code(402);
                echo json_encode([
                    'success'         => false,
                    'gatewayRequired' => true,
                    'message'         => vh_payu_manual_blocked_message($payuCfg),
                ]);
                exit;
            }
        }

        $ventureId = (int)$slot['venture_id'];
        $venture   = wlLoadVenture($mysqli, $ventureId);
        $txnId     = vh_generate_txn_id($mysqli);

        $stmt = $mysqli->prepare("
            INSERT INTO transactions
                (user_id, venture_id, venture_name, amount, principal_amount, fee_amount, type, status, txn_id, payment_gateway)
            VALUES (?, ?, ?, ?, ?, ?, 'venture_investment', 'completed', ?, 'manual')
        ");
        $stmt->bind_param('iisiiis', $userId, $ventureId, $venture['title'], $feeAmount, $investedAmount, $feeAmount, $txnId);
        $stmt->execute();
        $stmt->close();

        $result = vh_fill_slot($mysqli, $slotId, $userId, $feeAmount, $txnId);

        if (empty($result['success'])) {
            echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Could not confirm the seat.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'The seat is yours — you are now a partner in ' . $venture['title'] . '. '
                       . 'You joined without a meeting, so your 24-hour refund window starts now.',
        ]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Bad request']);
