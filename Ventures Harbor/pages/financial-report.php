<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
$currentUser = requirePageAuth();

$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $mysqli->prepare("
    SELECT r.*, v.title AS venture_title, v.raised_capital
    FROM venture_financial_reports r
    JOIN ventures v ON r.venture_id = v.id
    WHERE r.id = ?
");
$stmt->bind_param("i", $reportId);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report) {
    http_response_code(404);
    die('Report not found.');
}

$stmt = $mysqli->prepare("SELECT id FROM venture_members WHERE venture_id = ? AND user_id = ?");
$stmt->bind_param("ii", $report['venture_id'], $currentUser['id']);
$stmt->execute();
$stmt->store_result();
$isMember = $stmt->num_rows > 0;
$stmt->close();

if (!$isMember && $currentUser['role'] !== 'admin') {
    http_response_code(403);
    die('Only members of this Asset can view its financial reports.');
}

$stmt = $mysqli->prepare("SELECT category, amount FROM venture_financial_expenses WHERE report_id = ?");
$stmt->bind_param("i", $reportId);
$stmt->execute();
$expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$partnerShares = [];
$raisedCapital = (int)$report['raised_capital'];
if ($raisedCapital > 0) {
    $stmt = $mysqli->prepare("SELECT u.name, vm.role, vm.invested_amount FROM venture_members vm JOIN users u ON vm.user_id = u.id WHERE vm.venture_id = ? ORDER BY vm.invested_amount DESC");
    $stmt->bind_param("i", $report['venture_id']);
    $stmt->execute();
    $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($members as $m) {
        $pct = $m['invested_amount'] / $raisedCapital;
        $share = (int) round($report['profit_loss'] * $pct);
        $partnerShares[] = ['name' => $m['name'], 'role' => $m['role'], 'pct' => $pct * 100, 'share' => $share];
    }
}

$monthLabel = date('F Y', strtotime($report['report_month'] . '-01'));
$plPositive = (int)$report['profit_loss'] >= 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logo-mark.svg">
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Financial Report — <?= htmlspecialchars($report['venture_title']) ?> (<?= htmlspecialchars($monthLabel) ?>)</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="../assets/css/vh-pages.css?v=43" />
  <style>
    * { box-sizing: border-box; }
    body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F1F4F8; margin: 0; padding: 2rem 1rem; color: #16233A; }
    .toolbar { max-width: 780px; margin: 0 auto 1rem; display: flex; justify-content: space-between; align-items: center; }
    .toolbar a { color: #2563EB; text-decoration: none; font-size: 0.88rem; font-weight: 600; }
    .toolbar button { background: #2563EB; color: #fff; border: none; padding: 0.55rem 1.1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; }
    .report { max-width: 780px; margin: 0 auto; background: #fff; border-radius: 14px; box-shadow: 0 4px 24px rgba(8,20,33,0.08); padding: 2.5rem; }
    .r-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #E8ECF2; padding-bottom: 1.25rem; margin-bottom: 1.5rem; }
    .r-brandwrap { display: flex; align-items: center; gap: 0.7rem; }
    .r-mark { flex-shrink: 0; display: block; width: 40px; height: 40px; }
    .r-brand { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.3rem; color: #2563EB; white-space: nowrap; }
    .r-brand small { display: block; font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 500; font-size: 0.7rem; color: #7A8AA3; }
    .r-meta { text-align: right; font-size: 0.82rem; color: #5A6B85; }
    .r-meta strong { color: #16233A; }
    .r-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.75rem; }
    .r-stat { background: #F7F8FA; border: 1px solid #E8ECF2; border-radius: 10px; padding: 0.9rem 1rem; }
    .r-stat span { display: block; font-size: 0.7rem; color: #7A8AA3; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 0.25rem; }
    .r-stat strong { font-size: 1.15rem; }
    .r-section-title { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; color: #7A8AA3; margin: 1.5rem 0 0.5rem; }
    table.r-items { width: 100%; border-collapse: collapse; margin-bottom: 0.5rem; }
    table.r-items th { text-align: left; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.03em; color: #7A8AA3; border-bottom: 1px solid #E8ECF2; padding: 0.5rem 0.25rem; }
    table.r-items td { padding: 0.6rem 0.25rem; border-bottom: 1px solid #F1F4F8; font-size: 0.9rem; }
    table.r-items td:last-child, table.r-items th:last-child { text-align: right; }
    .r-narrative { font-size: 0.88rem; color: #33415C; line-height: 1.6; background: #F7F8FA; border-radius: 10px; padding: 0.9rem 1rem; margin-bottom: 0.5rem; }
    .r-footer { margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #F1F4F8; font-size: 0.75rem; color: #7A8AA3; text-align: center; }
    @media print {
      body { background: #fff; padding: 0; }
      .toolbar { display: none; }
      .report { box-shadow: none; border-radius: 0; max-width: 100%; }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <a href="venture-detail.php?id=<?= (int)$report['venture_id'] ?>">← Back to Asset</a>
    <button onclick="window.print()"><svg class="vh-i" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg> Print / Save as PDF</button>
  </div>

  <div class="report">
    <div class="r-head">
      <div class="r-brandwrap">
        <img src="../assets/img/logo-mark.svg" alt="" class="r-mark" width="40" height="40">
        <div class="r-brand">VENTURES HARBOR<small>Monthly Financial Report</small></div>
      </div>
      <div class="r-meta">
        <div><strong><?= htmlspecialchars($report['venture_title']) ?></strong></div>
        <div>Reporting Period: <strong><?= htmlspecialchars($monthLabel) ?></strong></div>
        <div>Generated: <?= date('d M Y') ?></div>
      </div>
    </div>

    <div class="r-stats">
      <div class="r-stat"><span>Total Revenue</span><strong style="color:#081421">₹<?= number_format((int)$report['total_revenue']) ?></strong></div>
      <div class="r-stat"><span>Total Expenses</span><strong style="color:#081421">₹<?= number_format((int)$report['total_expenses']) ?></strong></div>
      <div class="r-stat"><span><?= $plPositive ? 'Net Profit' : 'Net Loss' ?></span><strong style="color:<?= $plPositive ? '#16a34a' : '#dc2626' ?>">₹<?= number_format(abs((int)$report['profit_loss'])) ?></strong></div>
    </div>

    <?php /* Only reports published before the PDF change carry itemised rows —
             the form now takes a total plus an attached statement instead. */ ?>
    <?php if ($expenses): ?>
    <div class="r-section-title">Expense Breakdown</div>
    <table class="r-items">
      <thead><tr><th>Category</th><th>Amount</th></tr></thead>
      <tbody>
        <?php foreach ($expenses as $e): ?>
        <tr><td><?= htmlspecialchars($e['category']) ?></td><td>₹<?= number_format((int)$e['amount']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <?php if (!empty($report['pdf_path'])): ?>
    <div class="r-section-title">Attached Statement</div>
    <p style="font-size:0.88rem;">
      <a href="../<?= htmlspecialchars($report['pdf_path']) ?>" target="_blank" rel="noopener" style="color:#2563EB;">
        <svg class="vh-i" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.44 11.05 12.25 20.24a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg> <?= htmlspecialchars($report['pdf_name'] ?: 'Report PDF') ?>
      </a>
    </p>
    <?php endif; ?>

    <div class="r-section-title">Partner Profit/Loss Shares</div>
    <?php if ($partnerShares): ?>
    <table class="r-items">
      <thead><tr><th>Partner</th><th>Role</th><th>Capital Share</th><th>P/L Share</th></tr></thead>
      <tbody>
        <?php foreach ($partnerShares as $p): ?>
        <tr>
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td style="text-transform:capitalize;color:#5A6B85;"><?= htmlspecialchars($p['role']) ?></td>
          <td><?= number_format($p['pct'], 1) ?>%</td>
          <td style="color:<?= $p['share'] >= 0 ? '#16a34a' : '#dc2626' ?>">₹<?= number_format(abs($p['share'])) ?> <?= $p['share'] >= 0 ? '(profit)' : '(loss)' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <p style="color:#7A8AA3;font-size:0.85rem;">No capital raised yet — partner shares are not applicable.</p>
    <?php endif; ?>

    <?php if ($report['cash_flow_summary']): ?>
    <div class="r-section-title">Cash Flow Summary</div>
    <div class="r-narrative"><?= nl2br(htmlspecialchars($report['cash_flow_summary'])) ?></div>
    <?php endif; ?>

    <?php if ($report['balance_sheet_summary']): ?>
    <div class="r-section-title">Balance Sheet (Simple)</div>
    <div class="r-narrative"><?= nl2br(htmlspecialchars($report['balance_sheet_summary'])) ?></div>
    <?php endif; ?>

    <div class="r-footer">
      This report is system-generated from records published by the Asset founder on Ventures Harbor and is visible only to this Asset's members.
    </div>
  </div>
</body>
</html>
