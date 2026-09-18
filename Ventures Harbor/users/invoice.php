<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
$currentUser = requirePageAuth();

$txnId = isset($_GET['txn']) ? (int)$_GET['txn'] : 0;

$stmt = $mysqli->prepare("SELECT t.*, u.name as userName, u.email as userEmail FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
$stmt->bind_param("i", $txnId);
$stmt->execute();
$txn = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$txn || ((int)$txn['user_id'] !== (int)$currentUser['id'] && $currentUser['role'] !== 'admin')) {
    http_response_code(404);
    die('Invoice not found.');
}

$typeLabels = [
    'venture_investment' => 'Commitment Fee Payment',
    'commitment_fee' => 'Commitment Fee Payment',
    'refund_request' => 'Refund Request (Asset Exit)',

    'wallet_credit' => 'Wallet Credit (Asset Exit)',
    'wallet_withdrawal' => 'Wallet Withdrawal',
];
$title = $typeLabels[$txn['type']] ?? 'Transaction';
$statusLabels = [
    'completed' => ['Completed', '#16a34a'],
    'pending' => ['Pending', '#ca8a04'],
    'rejected' => ['Rejected', '#dc2626'],
    'refunded' => ['Refunded', '#2563EB'],
];
[$statusText, $statusColor] = $statusLabels[$txn['status']] ?? [ucfirst($txn['status']), '#5A6B85'];
$dateStr = date('d M Y, h:i A', strtotime($txn['created_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logo-mark.svg">
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Invoice <?= htmlspecialchars($txn['txn_id']) ?> – Ventures Harbor</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="../assets/css/vh-pages.css?v=43" />
  <style>
    * { box-sizing: border-box; }
    body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F1F4F8; margin: 0; padding: 2rem 1rem; color: #16233A; }
    .toolbar { max-width: 720px; margin: 0 auto 1rem; display: flex; justify-content: space-between; align-items: center; }
    .toolbar a { color: #2563EB; text-decoration: none; font-size: 0.88rem; font-weight: 600; }
    .toolbar button { background: #2563EB; color: #fff; border: none; padding: 0.55rem 1.1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; }
    .invoice { max-width: 720px; margin: 0 auto; background: #fff; border-radius: 14px; box-shadow: 0 4px 24px rgba(8,20,33,0.08); padding: 2.5rem; }
    .inv-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #E8ECF2; padding-bottom: 1.25rem; margin-bottom: 1.5rem; }
    .inv-brandwrap { display: flex; align-items: center; gap: 0.7rem; }
    .inv-mark { flex-shrink: 0; display: block; width: 40px; height: 40px; }
    .inv-brand { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.3rem; color: #2563EB; white-space: nowrap; }
    .inv-brand small { display: block; font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 500; font-size: 0.7rem; color: #7A8AA3; }
    .inv-meta { text-align: right; font-size: 0.82rem; color: #5A6B85; }
    .inv-meta strong { color: #16233A; }
    .inv-badge { display: inline-block; margin-top: 0.4rem; padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; color: #fff; }
    .inv-parties { display: flex; justify-content: space-between; margin-bottom: 1.75rem; font-size: 0.85rem; }
    .inv-parties h4 { margin: 0 0 0.3rem; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; color: #7A8AA3; }
    table.inv-items { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; }
    table.inv-items th { text-align: left; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.03em; color: #7A8AA3; border-bottom: 1px solid #E8ECF2; padding: 0.5rem 0.25rem; }
    table.inv-items td { padding: 0.65rem 0.25rem; border-bottom: 1px solid #F1F4F8; font-size: 0.9rem; }
    table.inv-items td:last-child, table.inv-items th:last-child { text-align: right; }
    .inv-total-row td { font-weight: 700; font-size: 1rem; border-top: 2px solid #E8ECF2; border-bottom: none; }
    .inv-note { font-size: 0.78rem; color: #7A8AA3; font-style: italic; margin-top: 0.5rem; }
    .inv-footer { margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #F1F4F8; font-size: 0.75rem; color: #7A8AA3; text-align: center; }
    @media print {
      body { background: #fff; padding: 0; }
      .toolbar { display: none; }
      .invoice { box-shadow: none; border-radius: 0; max-width: 100%; }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <a href="../admin/dashboard.php">← Back to Dashboard</a>
    <button onclick="window.print()"><svg class="vh-i" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg> Print / Save as PDF</button>
  </div>

  <div class="invoice">
    <div class="inv-head">
      <div class="inv-brandwrap">
        <img src="../assets/img/logo-mark.svg" alt="" class="inv-mark" width="40" height="40">
        <div class="inv-brand">VENTURES HARBOR<small>Fractional Business Ownership Platform</small></div>
      </div>
      <div class="inv-meta">
        <div>Invoice / Txn ID: <strong><?= htmlspecialchars($txn['txn_id']) ?></strong></div>
        <div>Date: <strong><?= htmlspecialchars($dateStr) ?></strong></div>
        <div class="inv-badge" style="background:<?= $statusColor ?>"><?= htmlspecialchars($statusText) ?></div>
      </div>
    </div>

    <div class="inv-parties">
      <div>
        <h4>Billed To</h4>
        <div><strong><?= htmlspecialchars($txn['userName']) ?></strong></div>
        <div><?= htmlspecialchars($txn['userEmail']) ?></div>
      </div>
      <div style="text-align:right">
        <h4>Record Type</h4>
        <div><strong><?= htmlspecialchars($title) ?></strong></div>
        <?php if ($txn['venture_name'] && $txn['venture_name'] !== 'Wallet Withdrawal'): ?>
          <div><?= htmlspecialchars($txn['venture_name']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <table class="inv-items">
      <thead><tr><th>Description</th><th>Amount</th></tr></thead>
      <tbody>
      <?php if ($txn['type'] === 'venture_investment'): ?>
        <tr><td>Investment Amount — <?= htmlspecialchars($txn['venture_name']) ?> <span style="color:#7A8AA3;">(recorded, settled offline)</span></td><td>₹<?= number_format((int)$txn['principal_amount']) ?></td></tr>
        <tr class="inv-total-row"><td>Commitment Fee (paid online)</td><td>₹<?= number_format((int)$txn['amount']) ?></td></tr>
      <?php elseif ($txn['type'] === 'refund_request'): ?>
        <tr><td>Refund Request — <?= htmlspecialchars($txn['venture_name']) ?></td><td>₹<?= number_format((int)$txn['principal_amount']) ?></td></tr>
        <?php if ($txn['payout_note']): ?>
        <tr><td colspan="2" class="inv-note">Payout details provided: <?= nl2br(htmlspecialchars($txn['payout_note'])) ?></td></tr>
        <?php endif; ?>
        <?php if ($txn['admin_notes']): ?>
        <tr><td colspan="2" class="inv-note">Admin note: <?= nl2br(htmlspecialchars($txn['admin_notes'])) ?></td></tr>
        <?php endif; ?>
        <tr><td colspan="2" class="inv-note">Note: the commitment fee paid at join is non-refundable and is not included in this refund. Refunds are processed manually, offline, by the admin team.</td></tr>
      <?php elseif ($txn['type'] === 'wallet_credit'): ?>
        <tr><td>Investment Principal Credited to Wallet — <?= htmlspecialchars($txn['venture_name']) ?></td><td>₹<?= number_format((int)$txn['principal_amount']) ?></td></tr>
        <tr class="inv-total-row"><td>Total Credited</td><td>₹<?= number_format((int)$txn['amount']) ?></td></tr>
        <tr><td colspan="2" class="inv-note">Note: the commitment fee paid at join is non-refundable and is not included in this credit.</td></tr>
      <?php elseif ($txn['type'] === 'wallet_withdrawal'): ?>
        <tr><td>Wallet Withdrawal Payout (offline transfer)</td><td>₹<?= number_format((int)$txn['amount']) ?></td></tr>
        <?php if ($txn['payout_note']): ?>
        <tr><td colspan="2" class="inv-note">Payout details provided: <?= nl2br(htmlspecialchars($txn['payout_note'])) ?></td></tr>
        <?php endif; ?>
        <?php if ($txn['admin_notes']): ?>
        <tr><td colspan="2" class="inv-note">Admin note: <?= nl2br(htmlspecialchars($txn['admin_notes'])) ?></td></tr>
        <?php endif; ?>
      <?php else: ?>
        <tr><td>Commitment Fee — <?= htmlspecialchars($txn['venture_name']) ?></td><td>₹<?= number_format((int)$txn['amount']) ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>

    <div class="inv-footer">
      This is a system-generated statement record from Ventures Harbor. Payments are currently processed in simulated/demo mode (no live payment gateway is active) and payouts are settled offline by the platform team.
    </div>
  </div>
</body>
</html>
