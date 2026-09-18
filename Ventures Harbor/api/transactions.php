<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'list') {
        $userId = requireAuth();

        $stmt = $mysqli->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 50");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $txns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $data = [];
        foreach ($txns as $t) {
            $data[] = [
                'id' => (string)$t['id'],
                'userId' => (string)$t['user_id'],
                'ventureId' => $t['venture_id'] !== null ? (string)$t['venture_id'] : null,
                'ventureName' => $t['venture_name'],
                'amount' => (int)$t['amount'],
                'principalAmount' => (int)$t['principal_amount'],
                'feeAmount' => (int)$t['fee_amount'],
                'type' => $t['type'],
                'status' => $t['status'],
                'refundType' => $t['refund_type'],

                'needsBankDetails' => $t['type'] === 'refund_request'
                    && $t['status'] === 'pending'
                    && empty($t['bank_account_number']),
                'bankAccountLast4' => !empty($t['bank_account_number'])
                    ? substr($t['bank_account_number'], -4)
                    : null,
                'payoutNote' => $t['payout_note'],
                'adminNotes' => $t['admin_notes'],
                'date' => date('Y-m-d', strtotime($t['created_at'])),
                'txnId' => $t['txn_id']
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? $_POST;
    if (!$action) {
        $action = $input['action'] ?? '';
    }

    if ($action === 'submit_refund_bank_details') {
        $userId = requireAuth();
        $txnRowId = isset($input['id']) ? (int)$input['id'] : 0;

        if (!$txnRowId) {
            echo json_encode(['success' => false, 'message' => 'Invalid refund reference.']);
            exit;
        }

        $stmt = $mysqli->prepare("
            SELECT id, status, amount, venture_name, bank_account_number
            FROM transactions
            WHERE id = ? AND user_id = ? AND type = 'refund_request'
        ");
        $stmt->bind_param("ii", $txnRowId, $userId);
        $stmt->execute();
        $txn = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$txn) {
            echo json_encode(['success' => false, 'message' => 'Refund request not found.']);
            exit;
        }
        if ($txn['status'] !== 'pending') {
            echo json_encode([
                'success' => false,
                'message' => 'This refund has already been processed, so its payout details can no longer be changed.'
            ]);
            exit;
        }

        [$bank, $bankError] = validateBankDetails($input);
        if ($bankError) {
            echo json_encode(['success' => false, 'message' => $bankError]);
            exit;
        }

        $payoutNote = trim($input['payout_note'] ?? '');
        $payoutNote = $payoutNote !== '' ? mb_substr($payoutNote, 0, 500) : null;

        $stmt = $mysqli->prepare("
            UPDATE transactions
            SET bank_account_name = ?, bank_account_number = ?, bank_ifsc = ?, bank_name = ?, payout_note = ?
            WHERE id = ? AND user_id = ? AND type = 'refund_request' AND status = 'pending'
        ");
        $stmt->bind_param(
            "sssssii",
            $bank['bank_account_name'], $bank['bank_account_number'], $bank['bank_ifsc'], $bank['bank_name'],
            $payoutNote, $txnRowId, $userId
        );
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Could not save your bank details. Please try again.']);
            exit;
        }
        $stmt->close();

        $wasUpdate = !empty($txn['bank_account_number']);

        echo json_encode([
            'success' => true,
            'message' => $wasUpdate
                ? 'Your bank details have been updated.'
                : 'Bank details saved. Your refund of ₹' . vh_inr((int)$txn['amount'])
                    . ' is now queued for transfer by the Ventures Harbor team.'
        ]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Bad request']);
