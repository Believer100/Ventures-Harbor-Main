<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
$currentUser = requirePageAuth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logo-mark.svg">
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Payment Statement – Ventures Harbor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/css/main.css?v=35"/>
  <link rel="stylesheet" href="../assets/css/components.css?v=38"/>
  <link rel="stylesheet" href="../assets/css/theme.css?v=32"/>
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=35"/>
    <link rel="stylesheet" href="../assets/css/vh-pages.css?v=43" />
</head>
<body class="dashboard-body">
<div class="toast-container" id="toastContainer"></div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <a href="../index.php" class="nav-logo"><img src="../assets/img/logo-mark.svg" alt="" class="logo-icon" width="30" height="30"><span class="logo-text">VENTURES HARBOR</span></a>
    <button class="sidebar-collapse-btn" id="sidebarCollapseBtn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
  </div>
  <nav class="sidebar-nav">
    <div class="sidebar-section">
      <a href="../admin/dashboard.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg><span class="sidebar-label">Overview</span></a>
      <a href="../pages/browse.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg><span class="sidebar-label">Browse Assets</span></a>
      <a href="../admin/saved.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg><span class="sidebar-label">Saved Assets</span></a>
      <a href="../admin/waitlist.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span class="sidebar-label">Waitlist</span></a>
      <a href="../pages/create-venture.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg><span class="sidebar-label">List Your Venture</span></a>
      <a href="../admin/meetup.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><span class="sidebar-label">Meetups</span></a>
    </div>
    <div class="sidebar-section">
      <p class="sidebar-section-title">Account</p>
      <a href="profile.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span class="sidebar-label">Profile</span></a>
      <a href="wallet.php" class="sidebar-nav-item active"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg><span class="sidebar-label">Payment Statement</span></a>
      <div class="sidebar-nav-item sidebar-nav-item--danger" id="sidebarLogout"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span class="sidebar-label">Logout</span></div>
    </div>
  </nav>
  <div class="sidebar-footer"><div class="sidebar-user"><div class="nav-avatar" id="sidebarAvatar">RK</div><div class="sidebar-user-info"><div class="sidebar-user-name" id="sidebarUserName">Rahul Kumar</div><div class="sidebar-user-role">Active Member</div></div></div></div>
</aside>

<div class="page-wrapper">
  <nav class="navbar navbar--light" id="navbar">
    <div class="nav-container">
      <button class="nav-hamburger" id="navHamburger"><span></span><span></span><span></span></button>
      <div style="flex:1"></div>
      <div class="nav-avatar-btn" id="avatarBtn" style="position:relative;"><div class="nav-avatar" id="navAvatar">RK</div><span class="nav-avatar-name" id="navAvatarName">Rahul</span></div>
      <div class="nav-dropdown" id="navDropdown">
        <a href="../admin/dashboard.php" class="nav-dropdown-item">Dashboard</a>
        <a href="profile.php" class="nav-dropdown-item">Profile</a>
        <a href="wallet.php" class="nav-dropdown-item">Payment Statement</a>
        <div class="nav-dropdown-divider"></div>
        <div class="nav-dropdown-item danger" id="navLogoutBtn">Logout</div>
      </div>
    </div>
  </nav>

  <main class="page-content">
    <div class="dash-welcome" style="margin-bottom:1.5rem;">
      <div>
        <h1 class="dash-welcome-title">Payment Statement</h1>
        <p class="dash-welcome-date">Your full commitment-fee payment and refund-request history.</p>
      </div>
    </div>

    <div id="refundActionBanner" class="hidden" style="margin-bottom:1.25rem;padding:1rem 1.15rem;background:#FFFBEB;border:1px solid #FDE68A;border-radius:10px;">
      <h4 style="margin:0 0 0.35rem;font-size:0.95rem;color:#78350F;">Action needed: tell us where to send your refund</h4>
      <p style="margin:0;font-size:0.85rem;line-height:1.6;color:#92400E;" id="refundActionText"></p>
    </div>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Full Statement</h3>
      </div>
      <div class="card-body">
        <div style="overflow-x:auto;">
          <table style="width:100%;min-width:640px;border-collapse:collapse;font-size:0.85rem;">
            <thead>
              <tr style="text-align:left;color:#7A8AA3;font-size:0.72rem;text-transform:uppercase;border-bottom:1px solid #E8ECF2;">
                <th style="padding:0.6rem 0.4rem;">Date</th>
                <th style="padding:0.6rem 0.4rem;">Description</th>
                <th style="padding:0.6rem 0.4rem;text-align:right;">Amount</th>
                <th style="padding:0.6rem 0.4rem;">Status</th>
                <th style="padding:0.6rem 0.4rem;">Invoice</th>
              </tr>
            </thead>
            <tbody id="statementTableBody"></tbody>
          </table>
          <p id="statementEmpty" class="hidden" style="text-align:center;color:#7A8AA3;font-size:0.85rem;padding:2rem 0;">No transactions yet.</p>
        </div>
      </div>
    </div>
  </main>
</div>

<div class="modal-overlay" id="refundBankModal">
  <div class="modal" style="max-width:520px">
    <button class="modal-close" onclick="VH.modal.close('refundBankModal')">×</button>
    <div class="modal-header"><h3 class="modal-title">Where should we send your refund?</h3></div>
    <div class="modal-body" style="padding:1.5rem">
      <p style="margin:0 0 1rem;font-size:0.88rem;line-height:1.6;color:#5A6B85;" id="refundBankIntro"></p>

      <div class="form-group">
        <label class="form-label">Account Holder Name *</label>
        <input class="form-control" id="rbHolder" placeholder="Name exactly as on the bank account"/>
      </div>
      <div class="form-group">
        <label class="form-label">Account Number *</label>
        <input class="form-control" id="rbNumber" inputmode="numeric" placeholder="e.g. 12345678901"/>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
        <div class="form-group">
          <label class="form-label">IFSC Code *</label>
          <input class="form-control" id="rbIfsc" placeholder="e.g. HDFC0001234" style="text-transform:uppercase"/>
        </div>
        <div class="form-group">
          <label class="form-label">Bank Name</label>
          <input class="form-control" id="rbBankName" placeholder="e.g. HDFC Bank"/>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Anything else we should know? (optional)</label>
        <textarea class="form-control form-textarea" id="rbNote" rows="2" placeholder="Optional note for the team processing your refund"></textarea>
      </div>
      <p style="margin:0;font-size:0.78rem;color:#7A8AA3;line-height:1.55;">
        Refunds are transferred manually by the Ventures Harbor team. You'll be notified once yours has been paid.
      </p>
    </div>
    <div class="modal-footer" style="padding:1rem 1.5rem;display:flex;justify-content:flex-end;gap:0.5rem;border-top:1px solid #F1F4F8">
      <button class="btn btn--secondary" onclick="VH.modal.close('refundBankModal')">Cancel</button>
      <button class="btn btn--primary" id="rbSaveBtn">Save Bank Details</button>
    </div>
  </div>
</div>

<script src="../assets/js/shared.js?v=70"></script>
<script src="../assets/js/wallet.js?v=38"></script>
</body>
</html>
