<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
$currentUser = requirePageAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logo-mark.svg">
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Control Panel – Ventures Harbor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/css/main.css?v=35"/>
  <link rel="stylesheet" href="../assets/css/components.css?v=38"/>
  <link rel="stylesheet" href="../assets/css/theme.css?v=32"/>
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=35"/>
  <link rel="stylesheet" href="../assets/css/admin.css?v=33"/>
    <link rel="stylesheet" href="../assets/css/vh-pages.css?v=43" />
</head>
<body class="dashboard-body">
<div class="toast-container" id="toastContainer"></div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ── SIDEBAR ── -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <a href="../index.php" class="nav-logo">
      <img src="../assets/img/logo-mark.svg" alt="" class="logo-icon" width="30" height="30"><span class="logo-text">VENTURES HARBOR</span>
    </a>
    <button class="sidebar-collapse-btn" id="sidebarCollapseBtn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
  </div>
  <nav class="sidebar-nav">
    <div class="sidebar-section">
      <a href="admin.php" class="sidebar-nav-item active"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg><span class="sidebar-label">Admin Overview</span></a>
      <a href="admin.php#ventureApprovals" class="sidebar-nav-item" data-tab-link="ventureApprovals"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg><span class="sidebar-label">Asset Moderation</span></a>
      <a href="admin.php#cancelledVentures" class="sidebar-nav-item" data-tab-link="cancelledVentures"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg><span class="sidebar-label">Closed Assets</span></a>
      <a href="admin.php#showcase" class="sidebar-nav-item" data-tab-link="showcase"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.1 8.6 22 9.6 17 14.5 18.2 21.4 12 18.1 5.8 21.4 7 14.5 2 9.6 8.9 8.6 12 2"/></svg><span class="sidebar-label">Sample Listings</span></a>
      <a href="admin.php#userManagement" class="sidebar-nav-item" data-tab-link="userManagement"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg><span class="sidebar-label">User Directory</span></a>
      <a href="admin.php#earnings" class="sidebar-nav-item" data-tab-link="earnings"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg><span class="sidebar-label">Earnings</span></a>
      <a href="admin.php#refundRequests" class="sidebar-nav-item" data-tab-link="refundRequests"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg><span class="sidebar-label">Refund Requests</span></a>
      <a href="admin.php#contactMessages" class="sidebar-nav-item" data-tab-link="contactMessages"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><span class="sidebar-label">Contact Messages</span></a>
      <a href="admin.php#settings" class="sidebar-nav-item" data-tab-link="settings"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg><span class="sidebar-label">Settings</span></a>
      <a href="../users/profile.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span class="sidebar-label">My Account</span></a>
      <div class="sidebar-nav-item sidebar-nav-item--danger" id="sidebarLogout"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span class="sidebar-label">Logout</span></div>
    </div>
  </nav>
  <div class="sidebar-footer"><div class="sidebar-user"><div class="nav-avatar" id="sidebarAvatar">PA</div><div class="sidebar-user-info"><div class="sidebar-user-name" id="sidebarUserName">Platform Admin</div><div class="sidebar-user-role">Administrator</div></div></div></div>
</aside>

<!-- ── PAGE WRAPPER ── -->
<div class="page-wrapper">
  <nav class="navbar navbar--light" id="navbar">
    <div class="nav-container">
      <button class="nav-hamburger" id="navHamburger"><span></span><span></span><span></span></button>
      <div style="flex:1"></div>
      <div class="nav-avatar-btn" id="avatarBtn" style="position:relative;"><div class="nav-avatar" id="navAvatar">RK</div><span class="nav-avatar-name" id="navAvatarName">Rahul</span></div>
      <div class="nav-dropdown" id="navDropdown"><a href="../users/profile.php" class="nav-dropdown-item">My Account</a><div class="nav-dropdown-item danger" id="navLogoutBtn">Logout</div></div>
    </div>
  </nav>

  <main class="page-content">
    <div class="admin-header">
      <h1>Admin Control Panel</h1>
      <p class="text-muted">Approve Assets, process refund requests, and monitor platform metrics</p>
    </div>

    <!-- Admin stats -->
    <div class="admin-stats-grid">
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#e0f2fe;color:#0ea5e9"><svg class="vh-i" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M8 10h.01M16 10h.01M8 14h.01M16 14h.01"/></svg></div>
        <div class="stat-card-value" id="statTotalV">0</div>
        <div class="stat-card-label">Total Assets</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#f0fdf4;color:#16a34a"><svg class="vh-i" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
        <div class="stat-card-value" id="statTotalUsers">0</div>
        <div class="stat-card-label">Registered Members</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#fef9c3;color:#ca8a04">₹</div>
        <div class="stat-card-value" id="statEarnings">₹0</div>
        <div class="stat-card-label">Platform Earnings (Fees Collected)</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#f5f3ff;color:#7c3aed"><svg class="vh-i" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/></svg></div>
        <div class="stat-card-value" id="statPendingRefunds">0</div>
        <div class="stat-card-label">Pending Refund Requests</div>
      </div>
    </div>

    <!-- Tabs layout -->
    <div class="admin-tabs-container tabs-container">
      <div class="vd-tabs-bar">
        <button class="tab-btn active" data-tab="ventureApprovals">Asset Moderation</button>
        <button class="tab-btn" data-tab="cancelledVentures">Closed Assets (<span id="cancelledBadge">0</span>)</button>
        <button class="tab-btn" data-tab="showcase">Sample Listings (<span id="showcaseBadge">0</span>)</button>
        <button class="tab-btn" data-tab="userManagement">User Directory</button>
        <button class="tab-btn" data-tab="earnings">Earnings</button>
        <button class="tab-btn" data-tab="refundRequests">Refund Requests (<span id="refundsBadge">0</span>)</button>
        <button class="tab-btn" data-tab="contactMessages">Contact Messages (<span id="contactMsgBadge">0</span>)</button>
        <button class="tab-btn" data-tab="settings">Settings</button>
      </div>

      <!-- Venture moderation Tab -->
      <div class="tab-panel active" data-panel="ventureApprovals">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Moderate Active & Suspended Listings</h3></div>
          <div class="card-body">
            <p style="font-size:0.8rem;color:#5A6B85;margin:0 0 0.75rem;">
              <strong>Suspend</strong> hides a listing from the public Browse Assets page and blocks new members from joining — the founder is notified and can see it's suspended from their dashboard. <strong>Activate</strong> makes a suspended listing live again.
            </p>
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Asset</th>
                    <th>Founder</th>
                    <th>Area</th>
                    <th>Funding Goal</th>
                    <th>Current Funding</th>
                    <th>Active Partners</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="ventureTableBody"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Sample Listings Tab -->
      <div class="tab-panel" data-panel="showcase">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Sample Listings</h3>
            <a href="../pages/create-venture.php?showcase=1" class="btn btn--primary btn--sm" id="showcaseCreateBtn">+ Create Sample Listing</a>
          </div>
          <div class="card-body">
            <p style="font-size:0.8rem;color:#5A6B85;margin:0 0 0.75rem;">
              Worked examples, pinned to the top of Browse and the homepage, so a first-time visitor can see what a
              finished listing looks like before writing their own. A sample uses the <strong>same creation form</strong>
              a founder uses, carries a <strong>SAMPLE LISTING</strong> badge, <strong>cannot be joined</strong> by
              anyone, <strong>never expires</strong>, and costs nothing to publish. You can have up to
              <strong id="showcaseMax">3</strong> at a time &mdash; delete one to free a slot. Remove them once the
              platform has real Assets of its own.
            </p>
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Listing</th>
                    <th>Founder Shown</th>
                    <th>Industry</th>
                    <th>Target</th>
                    <th>Published</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="showcaseTableBody"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="tab-panel" data-panel="cancelledVentures">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Closed Assets</h3></div>
          <div class="card-body">
            <p style="font-size:0.8rem;color:#5A6B85;margin:0 0 0.75rem;">
              Assets that are no longer running &mdash; either the founder <strong>deleted</strong> one that
              partners had already joined, or the platform closed it automatically when its listing period ran
              out without full funding. Either way a commitment-fee refund is opened for every partner who paid,
              and the <strong>Refunds</strong> column tracks those payouts. They stay listed here until the money
              is actually transferred. <strong>Awaiting bank details</strong> means the partner still has to tell
              us where to send it; those rows cannot be marked paid yet.
            </p>
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Asset</th>
                    <th>Founder</th>
                    <th>Funding Reached</th>
                    <th>Partners</th>
                    <th>Closed</th>
                    <th>Reason</th>
                    <th>Refunds</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="cancelledTableBody"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- User Database Tab -->
      <div class="tab-panel" data-panel="userManagement">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Registered User Directory</h3>
            <input class="form-control" style="width:250px; font-size:0.8rem; height:32px; padding:0 0.5rem" type="text" id="userSearchInput" placeholder="Search members by name/email..." oninput="filterUserTable()"/>
          </div>
          <div class="card-body">
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>User Name</th>
                    <th>City / Occupation</th>
                    <th>Email</th>
                    <th>V. Joined</th>
                    <th>Capital Invested</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="userTableBody"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Earnings Tab -->
      <div class="tab-panel" data-panel="earnings">
        <div class="admin-stats-grid" style="margin-bottom:1.25rem;">
          <div class="stat-card">
            <div class="stat-card-icon" style="background:#fef9c3;color:#ca8a04">✓</div>
            <div class="stat-card-value" id="earnCollected">₹0</div>
            <div class="stat-card-label">Commitment Fees Collected</div>
          </div>
          <div class="stat-card">
            <div class="stat-card-icon" style="background:#fff7ed;color:#f97316"><svg class="vh-i" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 22h14M5 2h14"/><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"/><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"/></svg></div>
            <div class="stat-card-value" id="earnPending">₹0</div>
            <div class="stat-card-label">Fees Pending Settlement</div>
          </div>
          <div class="stat-card">
            <div class="stat-card-icon" style="background:#e0f2fe;color:#0ea5e9"><svg class="vh-i" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg></div>
            <div class="stat-card-value" id="earnCapital">₹0</div>
            <div class="stat-card-label">Total Capital Raised (All Assets)</div>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><h3 class="card-title">About These Numbers</h3></div>
          <div class="card-body" style="font-size:0.85rem;color:#5A6B85;line-height:1.6;">
            <p>Ventures Harbor earns a 0.5% commitment fee (configurable under Settings) when a member joins an Asset. "Collected" reflects completed payments; "Pending Settlement" reflects fees marked pending in the payment gateway.</p>
            <p style="margin-top:0.5rem;">With PayU enabled (see Settings), these figures come from payments the gateway has actually confirmed. Attempts that were abandoned or declined are recorded separately and never counted here.</p>
          </div>
        </div>
      </div>

      <!-- Refund Requests Tab -->
      <div class="tab-panel" data-panel="refundRequests">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Refund Requests</h3>
          </div>
          <div class="card-body">

            <div class="refund-filter-bar">
              <button class="refund-filter is-active" data-refund-filter="all">
                All <span class="refund-filter-count" id="refundCountAll">0</span>
              </button>
              <button class="refund-filter" data-refund-filter="account_deletion">
                <svg class="vh-i" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Account Deletion <span class="refund-filter-count" id="refundCountDeletion">0</span>
              </button>
              <button class="refund-filter" data-refund-filter="exit">
                <svg class="vh-i" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 4h3a2 2 0 0 1 2 2v14"/><path d="M2 20h3M13 20h9"/><path d="M13 4.562v16.157a1 1 0 0 1-1.242.97L5.75 20.43A2 2 0 0 1 4 18.5V5.562a2 2 0 0 1 1.515-1.94l6-1.5A1 1 0 0 1 13 3.06Z"/></svg> Asset Exit <span class="refund-filter-count" id="refundCountExit">0</span>
              </button>
              <button class="refund-filter" data-refund-filter="venture_cancelled">
                <svg class="vh-i" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg> Asset Closed <span class="refund-filter-count" id="refundCountCancelled">0</span>
              </button>
            </div>

            <p class="refund-filter-hint" id="refundFilterHint"></p>

            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>User</th>
                    <th>Type</th>
                    <th>Refund For</th>
                    <th>Amount</th>
                    <th>Requested</th>
                    <th>Bank Details</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="refundsTableBody"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Contact Messages Tab -->
      <div class="tab-panel" data-panel="contactMessages">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Contact Us Messages</h3></div>
          <div class="card-body">
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>From</th>
                    <th>Subject</th>
                    <th>Message</th>
                    <th>Received</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="contactMsgTableBody"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Settings Tab -->
      <div class="tab-panel" data-panel="settings">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Platform Settings</h3></div>
          <div class="card-body">
            <form id="settingsForm">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="form-group">
                  <label class="form-label">Site Name</label>
                  <input class="form-control" id="setSiteName" placeholder="Ventures Harbor"/>
                </div>
                <div class="form-group">
                  <label class="form-label">Support Email</label>
                  <input class="form-control" id="setSupportEmail" type="email" placeholder="support@venturesharbor.com"/>
                </div>
                <div class="form-group">
                  <label class="form-label">Commitment Fee (%) <span style="color:#7A8AA3;font-size:0.75rem">(flat rate — applies to all founders)</span></label>
                  <input class="form-control" id="setFeePercent" type="number" step="0.1" min="0" placeholder="0.5"/>
                </div>
              </div>

              <h4 style="margin:1.5rem 0 0.75rem;font-size:0.95rem;"><svg class="vh-i" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg> Payment Gateway (PayU)</h4>
              <div class="kyc-info-box" style="margin-bottom:1rem;">
                <strong>Disabled</strong> keeps the simulated demo payment flow — nothing is charged and joining an Asset
                completes instantly. <strong>Enabled</strong> sends partners to PayU to pay the commitment fee for real;
                a membership is only created once PayU confirms the payment.
                <br><br>
                The merchant Key and Salt are <strong>not</strong> set here. They are secrets — anyone holding the Salt can
                forge a "payment successful" reply — so they live in <code>config/payu.php</code> on the server and are
                never sent to a browser.
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="form-group">
                  <label class="form-label">PayU Status</label>
                  <select class="form-control form-select" id="setPayuEnabled">
                    <option value="0">Disabled (demo payments — nothing is charged)</option>
                    <option value="1">Enabled (route payments through PayU)</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">PayU Mode</label>
                  <select class="form-control form-select" id="setPayuMode">
                    <option value="test">Test credentials — test cards, no real money</option>
                    <option value="live">Live credentials — real money</option>
                  </select>
                </div>
              </div>
              <p style="font-size:0.78rem;color:#B45309;background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;padding:0.6rem 0.8rem;margin:0 0 0.5rem;">
                Switch to <strong>Live</strong> only after the live Key and Salt have been added to
                <code>config/payu.php</code> on the production server. With Live selected and no live credentials
                present, payments are refused outright rather than falling back to the test merchant.
              </p>

              <button type="submit" class="btn btn--primary btn--md" style="margin-top:1rem">Save Settings</button>
            </form>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>

<!-- Refund Request Manage Modal -->
<div class="modal-overlay" id="refundModal">
  <div class="modal" style="max-width:500px">
    <button class="modal-close" onclick="VH.modal.close('refundModal')">×</button>
    <div class="modal-header"><h3 class="modal-title">Manage Refund Request</h3></div>
    <div class="modal-body" style="padding:1.5rem">
      <div class="refund-modal-type" id="refundModalTypeBanner">—</div>

      <div class="doc-preview-meta" style="margin-bottom:1rem;">
        <p>User: <strong id="refundModalUser">—</strong></p>
        <p id="refundModalContextRow">Refund for: <strong id="refundModalContext">—</strong></p>
        <p>Amount payable: <strong id="refundModalAmount">—</strong></p>
      </div>

      <div class="refund-bank-block" id="refundModalBankBlock">
        <h4 class="refund-bank-title">Bank Account Details</h4>
        <dl class="refund-bank-grid">
          <dt>Account Holder</dt><dd id="refundModalBankHolder">—</dd>
          <dt>Account Number</dt><dd id="refundModalBankNumber">—</dd>
          <dt>IFSC</dt><dd id="refundModalBankIfsc">—</dd>
          <dt>Bank</dt><dd id="refundModalBankName">—</dd>
        </dl>
        <p class="refund-bank-note" id="refundModalPayoutNoteRow">
          Note from user: <span id="refundModalPayoutNote">—</span>
        </p>
      </div>

      <div class="refund-waived-block hidden" id="refundModalAwaitingBlock" style="background:#fffbeb;border-color:#fde68a;color:#92400e;">
        This refund was opened automatically when the Asset was cancelled — the partner never
        filled in a form, so <strong>no bank details are on file yet</strong>. They have been notified
        and asked to add them from their Payment Statement page. Until then this refund cannot be
        marked as paid; you can still reject it if it shouldn't be honoured.
      </div>

      <div class="refund-waived-block hidden" id="refundModalWaivedBlock">
        This member chose <strong>Exit Without Refund</strong> — they left the Asset
        accepting that the commitment fee is forfeited. Nothing is payable and no
        action is required; this row is kept for the record only.
      </div>

      <div id="refundModalActionFields">
        <div class="form-group">
          <label class="form-label">Status</label>
          <select class="form-control form-select" id="refundModalStatus">
            <option value="pending">Pending</option>
            <option value="completed">Completed — paid offline</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Admin Notes</label>
          <textarea class="form-control form-textarea" id="refundModalNotes" rows="3" placeholder="Internal notes about how this was processed..."></textarea>
        </div>
      </div>
    </div>
    <div class="modal-footer" style="padding:1rem 1.5rem; display:flex; justify-content:flex-end; gap:0.5rem; border-top:1px solid #F1F4F8">
      <button class="btn btn--primary" id="refundModalSaveBtn">Save Changes</button>
    </div>
  </div>
</div>

<!-- Venture Details (read-only) Modal -->
<div class="modal-overlay" id="ventureDetailModal">
  <div class="modal" style="max-width:560px">
    <button class="modal-close" onclick="VH.modal.close('ventureDetailModal')">×</button>
    <div class="modal-header"><h3 class="modal-title">Asset Details</h3></div>
    <div class="modal-body" style="padding:1.5rem" id="ventureDetailBody"></div>
    <div class="modal-footer" style="padding:1rem 1.5rem; display:flex; justify-content:flex-end; border-top:1px solid #F1F4F8">
      <button class="btn btn--secondary" onclick="VH.modal.close('ventureDetailModal')">Close</button>
    </div>
  </div>
</div>

<!-- Contact Message Manage Modal -->
<div class="modal-overlay" id="contactMsgModal">
  <div class="modal" style="max-width:520px">
    <button class="modal-close" onclick="VH.modal.close('contactMsgModal')">×</button>
    <div class="modal-header"><h3 class="modal-title">Contact Message</h3></div>
    <div class="modal-body" style="padding:1.5rem">
      <div class="doc-preview-meta" style="margin-bottom:1rem;">
        <p>From: <strong id="cmModalName">—</strong> (<span id="cmModalEmail">—</span>)</p>
        <p>Subject: <strong id="cmModalSubject">—</strong></p>
        <p>Received: <span id="cmModalDate" style="color:#5A6B85;">—</span></p>
        <p style="margin-top:0.75rem;padding:0.75rem;background:#F7F8FA;border-radius:8px;color:#33415C;" id="cmModalMessage">—</p>
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select class="form-control form-select" id="cmModalStatus">
          <option value="new">New</option>
          <option value="read">Read</option>
          <option value="replied">Replied</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Admin Notes</label>
        <textarea class="form-control form-textarea" id="cmModalNotes" rows="3" placeholder="Internal notes about how this was handled..."></textarea>
      </div>
    </div>
    <div class="modal-footer" style="padding:1rem 1.5rem; display:flex; justify-content:flex-end; gap:0.5rem; border-top:1px solid #F1F4F8">
      <button class="btn btn--primary" id="cmModalSaveBtn">Save Changes</button>
    </div>
  </div>
</div>

<script src="../assets/js/shared.js?v=70"></script>
<script src="../assets/js/admin.js?v=45"></script>
</body>
</html>
