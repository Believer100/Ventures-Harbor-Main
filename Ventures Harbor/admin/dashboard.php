<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/venture-lifecycle.php';
$currentUser = requirePageUserOnly();

$maxListingDays   = vh_max_listing_days($mysqli);
$maxExtensionDays = vh_max_extension_days($mysqli);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logo-mark.svg">
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard – Ventures Harbor</title>
  <meta name="description" content="Your portfolio of co-owned Real-World Assets on Ventures Harbor."/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/css/main.css?v=35"/>
  <link rel="stylesheet" href="../assets/css/components.css?v=38"/>
  <link rel="stylesheet" href="../assets/css/theme.css?v=32"/>
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=35"/>
    <link rel="stylesheet" href="../assets/css/vh-pages.css?v=43" />
</head>
<style>
  .page-content{
    margin-left: 0px;
  }
</style>
<body class="dashboard-body">
<div class="toast-container" id="toastContainer"></div>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ── SIDEBAR ── -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <a href="../index.php" class="nav-logo">
      <img src="../assets/img/logo-mark.svg" alt="" class="logo-icon" width="30" height="30"><span class="logo-text">VENTURES HARBOR</span>
    </a>
    <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" title="Collapse sidebar">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
    </button>
  </div>
  <nav class="sidebar-nav">
    <div class="sidebar-section">
      <p class="sidebar-section-title">Main</p>
      <a href="dashboard.php" class="sidebar-nav-item active">
        <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        <span class="sidebar-label">Overview</span>
      </a>
      <a href="../pages/browse.php" class="sidebar-nav-item">
        <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <span class="sidebar-label">Browse Assets</span>
      </a>
      <a href="#my-ventures" class="sidebar-nav-item">
        <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
        <span class="sidebar-label">My Portfolio</span>
        <span class="sidebar-badge">2</span>
      </a>
      <a href="#joined-ventures" class="sidebar-nav-item">
        <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span class="sidebar-label">Co-Owned Assets</span>
        <span class="sidebar-badge">3</span>
      </a>
      <a href="saved.php" class="sidebar-nav-item">
        <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
        <span class="sidebar-label">Saved Assets</span>
      </a>
      <a href="waitlist.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span class="sidebar-label">Waitlist</span></a>
    </div>
    <div class="sidebar-section">
      <p class="sidebar-section-title">Actions</p>
      <a href="../pages/create-venture.php" class="sidebar-nav-item">
        <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        <span class="sidebar-label">List Your Venture</span>
      </a>
      <a href="meetup.php" class="sidebar-nav-item">
        <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span class="sidebar-label">Meetups</span>
        <span class="sidebar-badge sidebar-badge--gold">2</span>
      </a>
    </div>
    <div class="sidebar-section">
      <p class="sidebar-section-title">Account</p>
      <a href="../users/profile.php" class="sidebar-nav-item">
        <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <span class="sidebar-label">Profile</span>
      </a>
      <a href="../users/wallet.php" class="sidebar-nav-item">
        <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg>
        <span class="sidebar-label">Payment Statement</span>
      </a>
      <div class="sidebar-nav-item" id="sidebarSupport" style="cursor:pointer">
        <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span class="sidebar-label">Support</span>
      </div>
      <a href="admin.php" class="sidebar-nav-item" data-admin-only id="adminNavLink">
        <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <span class="sidebar-label">Admin Panel</span>
      </a>
      <div class="sidebar-nav-item sidebar-nav-item--danger" id="sidebarLogout">
        <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span class="sidebar-label">Logout</span>
      </div>
      <div class="sidebar-nav-item sidebar-nav-item--danger" id="sidebarDeleteAccount" style="cursor:pointer">
        <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        <span class="sidebar-label">Delete Account</span>
      </div>
    </div>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="nav-avatar" id="sidebarAvatar">RK</div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name" id="sidebarUserName">Rahul Kumar</div>
        <div class="sidebar-user-role" data-user-role>Active Member</div>
      </div>
    </div>
  </div>
</aside>

<!-- ── PAGE WRAPPER ── -->
<div class="page-wrapper" id="pageWrapper">
  <!-- Navbar -->
  <nav class="navbar navbar--light" id="navbar">
    <div class="nav-container">
      <button class="nav-hamburger" id="navHamburger" aria-label="Toggle sidebar">
        <span></span><span></span><span></span>
      </button>
      <div class="nav-search">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" placeholder="Search Assets, listings..." id="dashSearch"/>
      </div>
      <div style="flex:1"></div>
      <div style="display:flex;align-items:center;gap:0.75rem;">
        <div style="position:relative;">
          <button class="nav-notif-btn" id="notifBtn" title="Notifications">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="notif-badge" id="notifBadge">3</span>
          </button>
          <div class="nav-dropdown" id="notifDropdown" style="min-width:320px;right:0;top:calc(100% + 0.6rem);">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:0.4rem 0.6rem 0.6rem;border-bottom:1px solid #F1F4F8;margin-bottom:0.3rem;">
              <strong style="font-size:0.85rem;color:#081421;">Notifications</strong>
              <button class="btn btn--ghost btn--sm" id="notifDropdownMarkAll" style="padding:2px 8px;font-size:0.72rem;">Mark all read</button>
            </div>
            <div id="notifDropdownList" style="max-height:340px;overflow-y:auto;"></div>
            <div class="nav-dropdown-divider"></div>
            <a href="#" id="notifDropdownViewAll" class="nav-dropdown-item" style="justify-content:center;font-weight:600;color:#2563EB;">View All Notifications</a>
          </div>
        </div>
        <div class="nav-avatar-btn" id="avatarBtn" style="position:relative;">
          <div class="nav-avatar" id="navAvatar">RK</div>
          <span class="nav-avatar-name" id="navAvatarName">Rahul</span>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
        <div class="nav-dropdown" id="navDropdown">
          <a href="../users/profile.php" class="nav-dropdown-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Profile</a>
          <a href="../users/wallet.php" class="nav-dropdown-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg> Payment Statement</a>
          <a href="../pages/create-venture.php" class="nav-dropdown-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg> List Your Venture</a>
          <div class="nav-dropdown-divider"></div>
          <div class="nav-dropdown-item danger" id="navLogoutBtn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Logout</div>
        </div>
      </div>
    </div>
  </nav>

  <!-- ── MAIN CONTENT ── -->
  <main class="page-content">

    <!-- Welcome Header -->
    <div class="dash-welcome">
      <div>
        <h1 class="dash-welcome-title">Good afternoon, <span id="welcomeName">Rahul</span>!</h1>
        <p class="dash-welcome-date" id="welcomeDate">Friday, 27 June 2026 · Mumbai</p>
      </div>
      <div class="dash-welcome-actions">
        <a href="../pages/create-venture.php" class="btn btn--primary btn--md">+ List Your Venture</a>
      </div>
    </div>

    <!-- Stats Row -->
    <div class="dash-stats" id="dashStats" style="grid-template-columns:repeat(3,1fr)">
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#EFF4FF;color:#2563EB">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
        </div>
        <div class="stat-card-value" id="statMyVentures">0</div>
        <div class="stat-card-label">My Ventures</div>
        <div class="stat-card-delta stat-card-delta--up">↑ +1 this month</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#dcfce7;color:#16a34a">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-card-value" id="statJoined">0</div>
        <div class="stat-card-label">Co-Owned Assets</div>
        <div class="stat-card-delta stat-card-delta--up">↑ +1 this month</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#fef9c3;color:#ca8a04">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="stat-card-value" id="statInvested">₹0</div>
        <div class="stat-card-label">Capital Invested</div>
        <div class="stat-card-delta stat-card-delta--up">Across 3 Assets</div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="dash-quick-actions">
      <a href="../pages/browse.php" class="dash-qa-card">
        <div class="dash-qa-icon" style="background:linear-gradient(135deg,#2563EB,#2563EB)">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        </div>
        <h4>Browse Marketplace</h4>
        <p>Explore high-yield Real-World Assets</p>
        <span class="dash-qa-arrow">→</span>
      </a>
      <a href="../pages/create-venture.php" class="dash-qa-card">
        <div class="dash-qa-icon" style="background:linear-gradient(135deg,#16a34a,#22c55e)">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        </div>
        <h4>List Your Venture</h4>
        <p>Raise capital and find co-owners for your Venture</p>
        <span class="dash-qa-arrow">→</span>
      </a>
      <a href="meetup.php" class="dash-qa-card">
        <div class="dash-qa-icon" style="background:linear-gradient(135deg,#7c3aed,#a855f7)">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <h4>Schedule Meetup</h4>
        <p>Manage your upcoming meetings</p>
        <span class="dash-qa-arrow">→</span>
      </a>
    </div>

    <!-- Two-column layout -->
    <div class="dash-main-grid">
      <!-- Left column -->
      <div class="dash-col-left">

        <div class="card hidden" id="my-applications">
          <div class="card-header">
            <h3 class="card-title">My Applications</h3>
          </div>
          <div class="card-body" id="myApplicationsList"></div>
        </div>

        <!-- My Listed Ventures -->
        <div class="card" id="my-ventures">
          <div class="card-header">
            <h3 class="card-title">My Listed Ventures</h3>
            <a href="../pages/create-venture.php" class="btn btn--outline btn--sm">+ List Your Venture</a>
          </div>
          <div class="card-body" id="myVenturesList"></div>
        </div>

        <!-- Co-Owned Assets -->
        <div class="card" id="joined-ventures">
          <div class="card-header">
            <h3 class="card-title">Co-Owned Assets</h3>
            <a href="../pages/browse.php" class="btn btn--ghost btn--sm">Browse More →</a>
          </div>
          <div class="card-body" id="joinedVenturesList"></div>
        </div>

      </div>

      <!-- Right column -->
      <div class="dash-col-right">

        <!-- Upcoming Meetups -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Upcoming Meetups</h3>
            <a href="meetup.php" class="btn btn--ghost btn--sm">View All →</a>
          </div>
          <div class="card-body" id="upcomingMeetupsList"></div>
        </div>

        <!-- Notifications -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Notifications <span class="badge badge--danger" id="unreadCount">3</span></h3>
            <button class="btn btn--ghost btn--sm" id="markAllRead">Mark all read</button>
          </div>
          <div class="card-body" id="notifList"></div>
        </div>

        <!-- Activity Feed -->
        <div class="card">
          <div class="card-header"><h3 class="card-title">Recent Activity</h3></div>
          <div class="card-body" id="activityFeed"></div>
        </div>

      </div>
    </div>

  </main>
</div>

<div class="modal-overlay" id="supportModal">
  <div class="modal">
    <button class="modal-close" onclick="VH.modal.close('supportModal')">×</button>
    <div class="modal-header">
      <h3 class="modal-title">Contact Support</h3>
    </div>
    <div style="padding:0 1.5rem 1rem;">
      <p style="color:#5A6B85;font-size:0.86rem;">Send a message straight to the Ventures Harbor admin team. We usually reply within 24–48 hours.</p>
      <div class="form-group">
        <label class="form-label">Subject</label>
        <input class="form-control" id="supportSubject" placeholder="What do you need help with?"/>
      </div>
      <div class="form-group">
        <label class="form-label">Message *</label>
        <textarea class="form-control" id="supportMessage" rows="5" placeholder="Describe your issue or question in detail..."></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn--secondary" onclick="VH.modal.close('supportModal')">Cancel</button>
      <button class="btn btn--primary" id="sendSupportBtn">Send to Admin</button>
    </div>
  </div>
</div>

<!-- Delete (deactivate) Account Modal -->
<div class="modal-overlay" id="deleteAccountModal">
  <div class="modal">
    <button class="modal-close" onclick="VH.modal.close('deleteAccountModal')">×</button>
    <div class="modal-header">
      <h3 class="modal-title">Delete Account</h3>
    </div>
    <div style="padding:0 1.5rem 1rem;">
      <?php // Twin of the dialog in users/profile.php — both sidebars carry a
            // Delete Account entry, so the warning has to be in both. Change
            // them together. ?>
      <p class="delete-final-warning">
        <strong>This cannot be undone.</strong>
        Once you delete your account there is <strong>no way to restore it</strong>. Signing up again with
        the same email creates a brand-new, empty account — your Ventures, partnerships and history do
        not come back with it.
      </p>
      <?php // Twin of the block in users/profile.php — change them together. ?>
      <div class="delete-consequences">
        <p>Are you sure you want to delete your account?</p>
        <ul style="color:#44557A;font-size:0.86rem;line-height:1.65;margin:0 0 1rem;padding-left:1.1rem;">
          <li>You will be signed out immediately and <strong>will not be able to log back in</strong>.</li>
          <li>Your partnerships and payment records are <strong>kept for legal and accounting purposes</strong> — you just won't have access to them.</li>
          <li>If you have a refund coming, request it below — you cannot come back and ask for it later.</li>
        </ul>
      </div>

      <div class="delete-refund-block" id="deleteRefundBlock">
        <label class="delete-refund-toggle">
          <input type="checkbox" id="deleteRequestRefund">
          <span>
            <strong>Also request a refund of my commitment fees</strong>
            <span class="delete-refund-amount" id="deleteRefundAmount"></span>
          </span>
        </label>
        <div id="deleteBankFields" class="exit-bank-fields hidden">
          <h4 class="exit-bank-title">Bank Account Details</h4>
          <div class="form-group">
            <label class="form-label">Account Holder Name *</label>
            <input class="form-control" id="deleteBankHolder" placeholder="Name exactly as on the bank account"/>
          </div>
          <div class="form-group">
            <label class="form-label">Account Number *</label>
            <input class="form-control" id="deleteBankNumber" inputmode="numeric" placeholder="e.g. 12345678901"/>
          </div>
          <div class="exit-bank-row">
            <div class="form-group">
              <label class="form-label">IFSC Code *</label>
              <input class="form-control" id="deleteBankIfsc" placeholder="e.g. HDFC0001234" style="text-transform:uppercase"/>
            </div>
            <div class="form-group">
              <label class="form-label">Bank Name</label>
              <input class="form-control" id="deleteBankName" placeholder="e.g. HDFC Bank"/>
            </div>
          </div>
          <p class="delete-refund-hint">
            Our team reviews every refund request and transfers the approved amount
            offline to this account.
          </p>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Type <strong>DELETE</strong> to confirm</label>
        <input class="form-control" id="deleteAccountConfirm" placeholder="DELETE"/>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn--secondary" onclick="VH.modal.close('deleteAccountModal')">No, keep my account</button>
      <button class="btn btn--danger" id="confirmDeleteAccountBtn">Yes, delete permanently</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="exitModal">
  <div class="modal" style="max-width:560px">
    <button class="modal-close" onclick="VH.modal.close('exitModal')">×</button>
    <div class="modal-header">
      <h3 class="modal-title">Exit Asset</h3>
    </div>
    <div style="padding:0 1.5rem 1rem;">
      <p>You're about to leave <strong id="exitVentureName">this Asset</strong>.</p>

      <div class="exit-status-banner" id="exitStatusBanner">Checking your refund eligibility…</div>

      <div id="exitOptionsWrap" class="hidden">
        <p class="exit-options-label">Choose how you want to leave:</p>

        <label class="exit-option" id="exitOptionRefundWrap" for="exitOptionRefund">
          <input type="radio" name="exitRefundChoice" id="exitOptionRefund" value="refund">
          <span class="exit-option-body">
            <span class="exit-option-title">Exit &amp; claim my refund
              <span class="exit-option-pill exit-option-pill--good" id="exitRefundPill">Full fee back</span>
            </span>
            <span class="exit-option-desc" id="exitOptionRefundDesc">
              You're within 24 hours of the meetup, so your full commitment fee
              is refundable. Our team transfers it offline to the bank account below.
            </span>
          </span>
        </label>

        <label class="exit-option" id="exitOptionNoRefundWrap" for="exitOptionNoRefund">
          <input type="radio" name="exitRefundChoice" id="exitOptionNoRefund" value="no_refund">
          <span class="exit-option-body">
            <span class="exit-option-title">Exit without refund
              <span class="exit-option-pill exit-option-pill--warn">Fee forfeited</span>
            </span>
            <span class="exit-option-desc" id="exitOptionNoRefundDesc">
              Leave the Asset now and accept that the commitment fee will not be
              returned. No bank details needed and nothing to wait for.
            </span>
          </span>
        </label>

        <!-- Only shown for the refund path -->
        <div id="exitBankFields" class="exit-bank-fields hidden">
          <h4 class="exit-bank-title">Bank Account Details</h4>
          <div class="form-group">
            <label class="form-label">Account Holder Name *</label>
            <input class="form-control" id="exitBankHolder" placeholder="Name exactly as on the bank account"/>
          </div>
          <div class="form-group">
            <label class="form-label">Account Number *</label>
            <input class="form-control" id="exitBankNumber" inputmode="numeric" placeholder="e.g. 12345678901"/>
          </div>
          <div class="exit-bank-row">
            <div class="form-group">
              <label class="form-label">IFSC Code *</label>
              <input class="form-control" id="exitBankIfsc" placeholder="e.g. HDFC0001234" style="text-transform:uppercase"/>
            </div>
            <div class="form-group">
              <label class="form-label">Bank Name</label>
              <input class="form-control" id="exitBankName" placeholder="e.g. HDFC Bank"/>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Anything else we should know? (optional)</label>
            <textarea class="form-control" id="exitPayoutNote" rows="2" placeholder="Optional note for the admin team"></textarea>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn--secondary" onclick="VH.modal.close('exitModal')">Cancel</button>
      <button class="btn btn--danger" id="confirmExitBtn" disabled>Confirm Exit</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="deleteVentureModal">
  <div class="modal" style="max-width:560px">
    <button class="modal-close" onclick="VH.modal.close('deleteVentureModal')">×</button>
    <div class="modal-header">
      <h3 class="modal-title">Delete Asset</h3>
    </div>
    <div style="padding:0 1.5rem 1rem;">
      <p style="margin:0 0 0.75rem;">Delete <strong id="delVentureName">this Asset</strong>?</p>

      <div id="delImpactBox" style="border-radius:10px;padding:0.9rem 1rem;font-size:0.85rem;line-height:1.65;">
        <span id="delImpactText">Checking what this will affect…</span>
      </div>

      <div class="form-group" style="margin-top:1rem;">
        <label class="form-label" id="delReasonLabel">Reason for deleting</label>
        <textarea class="form-control" id="delReason" rows="3" maxlength="255"
                  placeholder="e.g. Not enough capital raised to proceed"></textarea>
        <p style="font-size:0.78rem;color:#7A8AA3;margin:0.4rem 0 0;" id="delReasonHelp"></p>
      </div>

      <div id="delExtendWrap" class="hidden" style="margin-top:1rem;padding-top:1rem;border-top:1px solid #E8ECF2;">
        <h4 style="margin:0 0 0.4rem;font-size:0.9rem;">Not ready to delete it?</h4>
        <p style="font-size:0.82rem;color:#5A6B85;line-height:1.6;margin:0 0 0.6rem;">
          This listing ran out of time without being fully funded. You can give it one more run instead —
          this is the <strong>only extension</strong> available for this Asset, and it is capped at
          <strong><?php echo (int)$maxExtensionDays; ?> days</strong>
          (a first listing runs up to <?php echo (int)$maxListingDays; ?>).
        </p>
        <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
          <select class="form-control form-select" id="delExtendDays" style="max-width:180px">
<?php

  $steps = array_values(array_unique(array_filter(
      [7, 10, (int)$maxExtensionDays],
      fn($d) => $d > 0 && $d <= (int)$maxExtensionDays
  )));
  sort($steps);
  foreach ($steps as $d):
?>
            <option value="<?php echo (int)$d; ?>"<?php echo $d === (int)$maxExtensionDays ? ' selected' : ''; ?>><?php echo (int)$d; ?> more days</option>
<?php endforeach; ?>
          </select>
          <button class="btn btn--primary btn--sm" id="delExtendBtn">Extend Listing Instead</button>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn--secondary" onclick="VH.modal.close('deleteVentureModal')">Keep Asset</button>
      <button class="btn" style="background:#dc2626;color:#fff;border:1px solid #dc2626" id="delConfirmBtn">Delete Asset</button>
    </div>
  </div>
</div>

<script src="../assets/js/shared.js?v=70"></script>
<script src="../assets/js/dashboard.js?v=52"></script>
</body>
</html>
