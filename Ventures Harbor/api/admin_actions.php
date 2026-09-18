<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/venture-lifecycle.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;
if (!$action) {
    $action = $input['action'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'stats') {
        requireAdmin();

        
        
        
        vh_run_lifecycle_sweep($mysqli, true);

        // Counts
        $totalV = $mysqli->query("SELECT COUNT(*) as count FROM ventures")->fetch_assoc()['count'];
        $totalUsers = $mysqli->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'")->fetch_assoc()['count'];

        
        
        
        
        
        
        
        
        
        $earningsCase = "CASE WHEN type = 'commitment_fee' THEN amount WHEN type IN ('venture_investment', 'venture_listing_fee') THEN fee_amount ELSE 0 END";
        $earningsRow = $mysqli->query("SELECT COALESCE(SUM($earningsCase),0) as total FROM transactions WHERE status = 'completed'")->fetch_assoc();
        $pendingEarningsRow = $mysqli->query("SELECT COALESCE(SUM($earningsCase),0) as total FROM transactions WHERE status = 'pending'")->fetch_assoc();
        $totalCapitalRow = $mysqli->query("SELECT COALESCE(SUM(raised_capital),0) as total FROM ventures")->fetch_assoc();

        // Refund requests awaiting admin action
        $pendingRefundsRow = $mysqli->query("SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM transactions WHERE type = 'refund_request' AND status = 'pending'")->fetch_assoc();

        
        
        $cancelledRow = $mysqli->query("SELECT COUNT(*) as cnt FROM ventures WHERE status = 'cancelled'")->fetch_assoc();
        $expiredRow = $mysqli->query("SELECT COUNT(*) as cnt FROM ventures WHERE status = 'expired'")->fetch_assoc();

        
        
        
        
        
        
        
        $ventures = $mysqli->query("
            SELECT v.id, v.title, v.founder_name as founderName, v.target_capital as targetCapital,
                   v.raised_capital as raisedCapital, v.progress_percent as progressPercent,
                   v.status, v.industry, v.location, v.founder_type as founderType,
                   v.partner_types as partnerTypes, v.min_investment as minInvestment,
                   v.days_left as daysLeft, v.created_at as createdAt, v.application_deadline as applicationDeadline,
                   v.listing_ends_at as listingEndsAt, v.extension_count as extensionCount,
                   v.expired_at as expiredAt,
                   (SELECT COUNT(*) FROM venture_members WHERE venture_id = v.id) as totalMembers,
                   (SELECT COUNT(*) FROM venture_members WHERE venture_id = v.id AND role = 'active') as activePartners,
                   (SELECT COUNT(*) FROM venture_members WHERE venture_id = v.id AND role = 'silent') as silentPartners,
                   (SELECT COUNT(*) FROM venture_applications WHERE venture_id = v.id AND status = 'pending') as pendingApplications
            FROM ventures v
            WHERE v.status <> 'cancelled' AND v.is_showcase = 0
            ORDER BY v.id DESC
        ")->fetch_all(MYSQLI_ASSOC);
        foreach ($ventures as &$v) {
            $v['id'] = (string)$v['id'];
            $v['targetCapital'] = (int)$v['targetCapital'];
            $v['raisedCapital'] = (int)$v['raisedCapital'];
            $v['progressPercent'] = (int)$v['progressPercent'];
            $v['minInvestment'] = (int)$v['minInvestment'];
            $v['daysLeft'] = (int)$v['daysLeft'];
            $v['extensionCount'] = (int)$v['extensionCount'];
            $v['totalMembers'] = (int)$v['totalMembers'];
            $v['activePartners'] = (int)$v['activePartners'];
            $v['silentPartners'] = (int)$v['silentPartners'];
            $v['pendingApplications'] = (int)$v['pendingApplications'];
        }
        unset($v);

        
        $usersList = $mysqli->query("
            SELECT u.id, u.name, COALESCE(u.previous_email, u.email) AS email, u.city, u.occupation, u.avatar,
                   u.is_deactivated, u.previous_email,
                   (SELECT COUNT(*) FROM venture_members WHERE user_id = u.id) as venturesJoined,
                   (SELECT SUM(invested_amount) FROM venture_members WHERE user_id = u.id) as totalInvested
            FROM users u
            WHERE u.role != 'admin'
            ORDER BY u.id ASC
        ")->fetch_all(MYSQLI_ASSOC);

        foreach ($usersList as &$u) {
            $u['id'] = (string)$u['id'];
            $u['avatar'] = $u['avatar'] ?: strtoupper(substr($u['name'], 0, 2));
            $u['venturesJoined'] = (int)$u['venturesJoined'];
            $u['totalInvested'] = (int)($u['totalInvested'] ?: 0);

            // Deleted accounts stay in this list — they still hold ventures,
            // memberships and money records an admin may need to look at. The
            // flags are what the row needs to say so, and to decide whether
            // Restore can still hand the address back.
            $u['isDeleted'] = (int)$u['is_deactivated'] === 1;
            $u['emailReleased'] = $u['previous_email'] !== null;
            unset($u['is_deactivated'], $u['previous_email']);
        }
        unset($u);

        echo json_encode([
            'success' => true,
            'stats' => [
                'totalVentures' => (int)$totalV,
                'totalUsers' => (int)$totalUsers,
                'totalEarnings' => (int)$earningsRow['total'],
                'pendingEarnings' => (int)$pendingEarningsRow['total'],
                'totalCapitalRaised' => (int)$totalCapitalRow['total'],
                'pendingRefundsCount' => (int)$pendingRefundsRow['cnt'],
                'pendingRefundsTotal' => (int)$pendingRefundsRow['total'],
                'cancelledVentures' => (int)$cancelledRow['cnt'],
                'expiredVentures' => (int)$expiredRow['cnt']
            ],
            'ventures' => $ventures,
            'users' => $usersList
        ]);
        exit;
    }

    if ($action === 'refund_requests') {
        requireAdmin();
        
        
        
        
        $refunds = $mysqli->query("
            SELECT t.id, t.amount, t.fee_amount, t.status, t.refund_type, t.venture_name, t.venture_id,
                   t.bank_account_name, t.bank_account_number, t.bank_ifsc, t.bank_name,
                   t.payout_note, t.admin_notes, t.txn_id, t.created_at,
                   u.name as userName, u.avatar as userAvatar,
                   -- A deleted account whose address has been claimed by a new
                   -- signup carries a deleted+<id>@…invalid placeholder. This
                   -- queue is where refunds are paid out from, so it must show
                   -- the address that can actually be written to.
                   COALESCE(u.previous_email, u.email) as userEmail
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            WHERE t.type = 'refund_request'
            ORDER BY FIELD(t.status, 'pending', 'completed', 'rejected', 'waived'), t.created_at ASC
        ")->fetch_all(MYSQLI_ASSOC);

        foreach ($refunds as &$r) {
            $r['id'] = (string)$r['id'];
            $r['amount'] = (int)$r['amount'];
            $r['fee_amount'] = (int)$r['fee_amount'];
            $r['refund_type'] = $r['refund_type'] ?: 'exit'; // pre-split rows were all exits
            $r['userAvatar'] = $r['userAvatar'] ?: strtoupper(substr($r['userName'], 0, 2));
            
            
            
            
            $r['awaiting_bank_details'] = $r['status'] === 'pending' && empty($r['bank_account_number']);
        }
        unset($r);

        echo json_encode(['success' => true, 'refunds' => $refunds]);
        exit;
    }

    
    
    
    if ($action === 'cancelled_ventures') {
        requireAdmin();
        vh_run_lifecycle_sweep($mysqli, true);

        $rows = $mysqli->query("
            SELECT v.id, v.title, v.founder_name AS founderName, v.founder_user_id AS founderUserId,
                   v.industry, v.location, v.target_capital AS targetCapital,
                   v.raised_capital AS raisedCapital, v.progress_percent AS progressPercent,
                   v.created_at AS createdAt, v.listing_ends_at AS listingEndsAt,
                   v.extension_count AS extensionCount, v.extended_at AS extendedAt,
                   v.expired_at AS expiredAt, v.cancelled_at AS cancelledAt,
                   v.cancelled_by AS cancelledBy, v.cancel_reason AS cancelReason,
                   (SELECT COUNT(*) FROM venture_members WHERE venture_id = v.id) AS totalMembers,
                   (SELECT COUNT(*) FROM transactions t
                     WHERE t.venture_id = v.id AND t.type = 'refund_request'
                       AND t.refund_type = 'venture_cancelled') AS refundCount,
                   (SELECT COALESCE(SUM(t.amount),0) FROM transactions t
                     WHERE t.venture_id = v.id AND t.type = 'refund_request'
                       AND t.refund_type = 'venture_cancelled') AS refundTotal,
                   (SELECT COUNT(*) FROM transactions t
                     WHERE t.venture_id = v.id AND t.type = 'refund_request'
                       AND t.refund_type = 'venture_cancelled' AND t.status = 'pending') AS refundPending,
                   (SELECT COUNT(*) FROM transactions t
                     WHERE t.venture_id = v.id AND t.type = 'refund_request'
                       AND t.refund_type = 'venture_cancelled' AND t.status = 'completed') AS refundCompleted,
                   (SELECT COUNT(*) FROM transactions t
                     WHERE t.venture_id = v.id AND t.type = 'refund_request'
                       AND t.refund_type = 'venture_cancelled' AND t.status = 'rejected') AS refundRejected,
                   (SELECT COUNT(*) FROM transactions t
                     WHERE t.venture_id = v.id AND t.type = 'refund_request'
                       AND t.refund_type = 'venture_cancelled' AND t.status = 'pending'
                       AND (t.bank_account_number IS NULL OR t.bank_account_number = '')) AS refundAwaitingBank
            FROM ventures v
            WHERE v.status = 'cancelled'
            ORDER BY v.cancelled_at DESC, v.id DESC
        ")->fetch_all(MYSQLI_ASSOC);

        foreach ($rows as &$row) {
            $row['id'] = (string)$row['id'];
            foreach (['targetCapital', 'raisedCapital', 'progressPercent', 'extensionCount', 'totalMembers',
                      'refundCount', 'refundTotal', 'refundPending', 'refundCompleted',
                      'refundRejected', 'refundAwaitingBank'] as $intField) {
                $row[$intField] = (int)$row[$intField];
            }
            
            
            $row['cancelledBy'] = $row['cancelledBy'] ?: 'unknown';
        }
        unset($row);

        echo json_encode(['success' => true, 'ventures' => $rows]);
        exit;
    }

    
    
    
    if ($action === 'showcase_ventures') {
        requireAdmin();

        $rows = $mysqli->query("
            SELECT v.id, v.title, v.founder_name AS founderName, v.industry, v.location,
                   v.target_capital AS targetCapital, v.min_investment AS minInvestment,
                   v.progress_percent AS progressPercent, v.created_at AS createdAt
            FROM ventures v
            WHERE v.is_showcase = 1
            ORDER BY v.id DESC
        ")->fetch_all(MYSQLI_ASSOC);

        foreach ($rows as &$row) {
            $row['id'] = (string)$row['id'];
            foreach (['targetCapital', 'minInvestment', 'progressPercent'] as $intField) {
                $row[$intField] = (int)$row[$intField];
            }
        }
        unset($row);

        echo json_encode([
            'success'   => true,
            'ventures'  => $rows,
            'max'       => VH_SHOWCASE_MAX,
            'slotsLeft' => VH_SHOWCASE_MAX - count($rows),
        ]);
        exit;
    }

    if ($action === 'contact_messages') {
        requireAdmin();
        $messages = $mysqli->query("
            SELECT cm.id, cm.name, cm.email, cm.subject, cm.message, cm.status, cm.admin_notes, cm.created_at,
                   cm.user_id, u.name AS accountName
            FROM contact_messages cm
            LEFT JOIN users u ON cm.user_id = u.id
            ORDER BY FIELD(cm.status, 'new', 'read', 'replied'), cm.created_at DESC
        ")->fetch_all(MYSQLI_ASSOC);

        foreach ($messages as &$m) {
            $m['id'] = (string)$m['id'];
        }
        unset($m);

        echo json_encode(['success' => true, 'messages' => $messages]);
        exit;
    }

    if ($action === 'settings') {
        requireAdmin();
        $rows = $mysqli->query("SELECT setting_key, setting_value FROM settings")->fetch_all(MYSQLI_ASSOC);
        $settings = [];
        foreach ($rows as $r) {
            $settings[$r['setting_key']] = $r['setting_value'];
        }
        echo json_encode(['success' => true, 'settings' => $settings]);
        exit;
    }

}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();

    
    
    
    
    
    if ($action === 'delete_showcase') {
        $ventureId = isset($input['id']) ? (int)$input['id'] : 0;
        if (!$ventureId) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT title, is_showcase FROM ventures WHERE id = ?");
        $stmt->bind_param("i", $ventureId);
        $stmt->execute();
        $venture = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$venture) {
            echo json_encode(['success' => false, 'message' => 'Listing not found.']);
            exit;
        }
        if (empty($venture['is_showcase'])) {
            echo json_encode([
                'success' => false,
                'message' => 'That is a real Asset, not a sample listing. Use Asset Moderation instead.'
            ]);
            exit;
        }

        
        
        
        $stmt = $mysqli->prepare("
            SELECT (SELECT COUNT(*) FROM venture_members WHERE venture_id = ?) AS members,
                   (SELECT COUNT(*) FROM transactions WHERE venture_id = ?) AS txns
        ");
        $stmt->bind_param("ii", $ventureId, $ventureId);
        $stmt->execute();
        $attached = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ((int)$attached['members'] > 0 || (int)$attached['txns'] > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'This sample listing has members or transactions attached and was not deleted. Report this — a sample should never have either.'
            ]);
            exit;
        }

        $stmt = $mysqli->prepare("DELETE FROM ventures WHERE id = ? AND is_showcase = 1");
        $stmt->bind_param("i", $ventureId);
        $stmt->execute();
        $deleted = $stmt->affected_rows > 0;
        $stmt->close();

        echo json_encode([
            'success'   => $deleted,
            'message'   => $deleted
                ? 'Sample listing "' . $venture['title'] . '" deleted.'
                : 'Could not delete that sample listing.',
            'slotsLeft' => VH_SHOWCASE_MAX - vh_showcase_count($mysqli),
        ]);
        exit;
    }

    if ($action === 'venture_status') {
        $ventureId = isset($input['id']) ? (int)$input['id'] : 0;
        $status = trim($input['status'] ?? ''); // 'active' or 'suspended'

        if (!$ventureId || !in_array($status, ['active', 'suspended'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
            exit;
        }

        
        
        
        $stmt = $mysqli->prepare("SELECT status FROM ventures WHERE id = ?");
        $stmt->bind_param("i", $ventureId);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($current && $current['status'] === 'cancelled') {
            echo json_encode([
                'success' => false,
                'message' => 'This Asset has been cancelled and its partners refunded — it cannot be reactivated or suspended.'
            ]);
            exit;
        }
        
        
        
        if ($current && $current['status'] === 'expired') {
            echo json_encode([
                'success' => false,
                'message' => 'This listing period has ended. Only the founder can extend it (once) or cancel it — '
                    . 'it will be cancelled automatically if they do neither.'
            ]);
            exit;
        }

        // Update venture status
        $stmt = $mysqli->prepare("UPDATE ventures SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $ventureId);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Failed to update Asset status.']);
            exit;
        }
        $stmt->close();

        // Retrieve founder ID to notify them
        $stmt = $mysqli->prepare("SELECT title, founder_user_id FROM ventures WHERE id = ?");
        $stmt->bind_param("i", $ventureId);
        $stmt->execute();
        $vData = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($vData && $vData['founder_user_id']) {
            $founderId = $vData['founder_user_id'];
            $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'venture', 'Listing Status Updated 📋', ?)");
            $notifMsg = $status === 'active' 
                ? "Your listing '" . $vData['title'] . "' has been APPROVED and is now active." 
                : "Your listing '" . $vData['title'] . "' has been SUSPENDED by the administrator.";
            $stmt->bind_param("is", $founderId, $notifMsg);
            $stmt->execute();
            $stmt->close();
        }

        echo json_encode(['success' => true, 'message' => 'Asset status updated.']);
        exit;
    }

    if ($action === 'update_settings') {
        
        
        
        
        
        $allowedKeys = ['site_name', 'support_email', 'commitment_fee_percent', 'payu_enabled', 'payu_mode'];
        $updates = $input['settings'] ?? [];

        if (!is_array($updates) || empty($updates)) {
            echo json_encode(['success' => false, 'message' => 'No settings provided.']);
            exit;
        }

        $stmt = $mysqli->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        foreach ($updates as $key => $value) {
            if (!in_array($key, $allowedKeys, true)) continue;
            $value = (string)$value;
            $stmt->bind_param("sss", $key, $value, $value);
            $stmt->execute();
        }
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Settings updated successfully.']);
        exit;
    }

    if ($action === 'update_refund_request') {
        $txnRowId = isset($input['id']) ? (int)$input['id'] : 0;
        $status = trim($input['status'] ?? '');
        $adminNotes = trim($input['admin_notes'] ?? '');
        $validStatuses = ['completed', 'rejected'];

        if (!$txnRowId || !in_array($status, $validStatuses, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid request or status.']);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT user_id, amount, status, refund_type, venture_name, bank_account_number FROM transactions WHERE id = ? AND type = 'refund_request'");
        $stmt->bind_param("i", $txnRowId);
        $stmt->execute();
        $txn = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$txn) {
            echo json_encode(['success' => false, 'message' => 'Refund request not found.']);
            exit;
        }
        if ($txn['status'] === 'waived') {
            echo json_encode(['success' => false, 'message' => 'This member exited without a refund — there is nothing to pay out.']);
            exit;
        }
        if ($txn['status'] !== 'pending') {
            echo json_encode(['success' => false, 'message' => 'This refund request has already been processed.']);
            exit;
        }
        
        
        
        
        
        
        if ($status === 'completed' && empty($txn['bank_account_number'])) {
            echo json_encode([
                'success' => false,
                'message' => 'This partner has not provided their bank details yet, so there is nowhere to transfer the refund. '
                    . 'They have been asked to add them from their Payment Statement page — this row can be completed once they do.'
            ]);
            exit;
        }

        
        
        $stmt = $mysqli->prepare("UPDATE transactions SET status = ?, admin_notes = ? WHERE id = ?");
        $stmt->bind_param("ssi", $status, $adminNotes, $txnRowId);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Failed to update refund request.']);
            exit;
        }
        $stmt->close();

        
        
        if ($txn['refund_type'] === 'account_deletion') {
            $context = ' (account deletion refund)';
        } elseif ($txn['refund_type'] === 'venture_cancelled') {
            $context = ' (refund for the cancelled Asset ' . $txn['venture_name'] . ')';
        } else {
            $context = ' (exit refund — ' . $txn['venture_name'] . ')';
        }

        $notifMsg = $status === 'completed'
            ? "Your refund of ₹" . vh_inr($txn['amount']) . $context . " has been paid via offline transfer." . ($adminNotes ? " Note: $adminNotes" : "")
            : "Your refund request for ₹" . vh_inr($txn['amount']) . $context . " was rejected." . ($adminNotes ? " Reason: $adminNotes" : "");
        $nstmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'payment', 'Refund Update 💸', ?)");
        $nstmt->bind_param("is", $txn['user_id'], $notifMsg);
        $nstmt->execute();
        $nstmt->close();

        echo json_encode(['success' => true, 'message' => 'Refund request updated successfully.']);
        exit;
    }

    if ($action === 'update_contact_message') {
        $msgId = isset($input['id']) ? (int)$input['id'] : 0;
        $status = trim($input['status'] ?? '');
        $adminNotes = trim($input['admin_notes'] ?? '');
        $validStatuses = ['new', 'read', 'replied'];

        if (!$msgId || !in_array($status, $validStatuses, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid message or status.']);
            exit;
        }

        $stmt = $mysqli->prepare("UPDATE contact_messages SET status = ?, admin_notes = ? WHERE id = ?");
        $stmt->bind_param("ssi", $status, $adminNotes, $msgId);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Failed to update message.']);
            exit;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Message updated.']);
        exit;
    }

    /**
     * Undo an account deletion. The only way back — nothing a visitor can do
     * restores a deleted account, since signing up on its address creates a
     * separate empty one.
     *
     * The address is the whole difficulty. While nobody has claimed it the row
     * still holds it and the restore is a flag flip. Once a new signup has taken
     * it, the row carries a placeholder and its real address belongs to somebody
     * else: two accounts cannot share one, so the restore has to be given a new
     * address rather than silently reviving an account nobody can log into.
     */
    if ($action === 'restore_account') {
        $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
        $newEmail = trim($input['email'] ?? '');

        if (!$userId) {
            echo json_encode(['success' => false, 'message' => 'Invalid user.']);
            exit;
        }

        $stmt = $mysqli->prepare('SELECT id, name, email, previous_email, is_deactivated FROM users WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }
        if ((int)$user['is_deactivated'] !== 1) {
            echo json_encode(['success' => false, 'message' => 'This account is already active.']);
            exit;
        }

        $released = $user['previous_email'] !== null;
        $targetEmail = $released ? ($newEmail !== '' ? $newEmail : (string)$user['previous_email']) : (string)$user['email'];

        if ($released && $newEmail !== '' && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address for the restored account.']);
            exit;
        }

        // Whoever holds the address now wins — a restore must never take an
        // email away from a live account.
        if ($targetEmail !== $user['email']) {
            $stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $stmt->bind_param('si', $targetEmail, $userId);
            $stmt->execute();
            $taken = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($taken) {
                echo json_encode([
                    'success' => false,
                    'emailTaken' => true,
                    'previousEmail' => $user['previous_email'],
                    'message' => $newEmail !== ''
                        ? 'That email already belongs to another account. Choose a different one.'
                        : 'This account\'s email (' . $user['previous_email'] . ') has since been taken by a new signup, '
                          . 'so the account can only be restored under a different address. Enter one to continue.',
                ]);
                exit;
            }
        }

        $stmt = $mysqli->prepare('UPDATE users SET is_deactivated = 0, email = ?, previous_email = NULL WHERE id = ? AND is_deactivated = 1');
        $stmt->bind_param('si', $targetEmail, $userId);
        $stmt->execute();
        $restored = $stmt->affected_rows > 0;
        $stmt->close();

        if (!$restored) {
            echo json_encode(['success' => false, 'message' => 'Could not restore the account. Please try again.']);
            exit;
        }

        $notifMsg = 'Your Ventures Harbor account has been restored by our team. '
                  . 'You can sign in again with ' . $targetEmail
                  . ($targetEmail !== $user['previous_email'] && $user['previous_email'] !== null
                        ? ' — your previous address is now in use by another account.' : '.');
        $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'account', 'Account Restored ✅', ?)");
        $stmt->bind_param('is', $userId, $notifMsg);
        $stmt->execute();
        $stmt->close();

        echo json_encode([
            'success' => true,
            'email'   => $targetEmail,
            'message' => 'Account restored. ' . htmlspecialchars($user['name']) . ' can sign in again with ' . $targetEmail . '.',
        ]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Bad request']);
