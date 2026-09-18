<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
$currentUser = requirePageUserOnly();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logo-mark.svg">
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Saved Assets – Ventures Harbor</title>
  <meta name="description" content="The Assets you marked as interested on Ventures Harbor."/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/css/main.css?v=35"/>
  <link rel="stylesheet" href="../assets/css/components.css?v=38"/>
  <link rel="stylesheet" href="../assets/css/theme.css?v=32"/>
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=35"/>
  <link rel="stylesheet" href="../assets/css/vh-pages.css?v=43"/>

  <link rel="stylesheet" href="../assets/css/venture-card.css?v=45"/>
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
      <p class="sidebar-section-title">Main</p>
      <a href="dashboard.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg><span class="sidebar-label">Overview</span></a>
      <a href="../pages/browse.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg><span class="sidebar-label">Browse Assets</span></a>
      <a href="dashboard.php#my-ventures" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg><span class="sidebar-label">My Ventures</span></a>
      <a href="dashboard.php#joined-ventures" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span class="sidebar-label">Joined Assets</span></a>
      <a href="saved.php" class="sidebar-nav-item active"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg><span class="sidebar-label">Saved Assets</span><span class="sidebar-badge" id="savedBadge" hidden>0</span></a>
      <a href="waitlist.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span class="sidebar-label">Waitlist</span></a>
    </div>
    <div class="sidebar-section">
      <p class="sidebar-section-title">Actions</p>
      <a href="../pages/create-venture.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg><span class="sidebar-label">List Your Venture</span></a>
      <a href="meetup.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><span class="sidebar-label">Meetups</span></a>
    </div>
    <div class="sidebar-section">
      <p class="sidebar-section-title">Account</p>
      <a href="../users/profile.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span class="sidebar-label">Profile</span></a>
      <a href="../users/wallet.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg><span class="sidebar-label">Payment Statement</span></a>
      <div class="sidebar-nav-item sidebar-nav-item--danger" id="sidebarLogout"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span class="sidebar-label">Logout</span></div>
    </div>
  </nav>
  <div class="sidebar-footer"><div class="sidebar-user"><div class="nav-avatar" id="sidebarAvatar">RK</div><div class="sidebar-user-info"><div class="sidebar-user-name" id="sidebarUserName">Rahul Kumar</div><div class="sidebar-user-role" data-user-role>Active Member</div></div></div></div>
</aside>

<!-- ── PAGE WRAPPER ── -->
<div class="page-wrapper">
  <nav class="navbar navbar--light" id="navbar">
    <div class="nav-container">
      <button class="nav-hamburger" id="navHamburger" aria-label="Toggle sidebar"><span></span><span></span><span></span></button>
      <div style="flex:1"></div>
      <div class="nav-avatar-btn" id="avatarBtn" style="position:relative;"><div class="nav-avatar" id="navAvatar">RK</div><span class="nav-avatar-name" id="navAvatarName">Rahul</span></div>
      <div class="nav-dropdown" id="navDropdown">
        <a href="dashboard.php" class="nav-dropdown-item">Dashboard</a>
        <a href="../users/profile.php" class="nav-dropdown-item">Profile</a>
        <div class="nav-dropdown-divider"></div>
        <div class="nav-dropdown-item danger" id="navLogoutBtn">Logout</div>
      </div>
    </div>
  </nav>

  <main class="page-content">
    <div class="saved-header">
      <div>
        <h1>Saved Assets</h1>
        <p class="text-muted" id="savedCount">Loading your list…</p>
      </div>
      <a href="../pages/browse.php" class="btn btn--primary btn--md">Browse Assets</a>
    </div>

    <div class="ventures-grid saved-grid" id="savedGrid"></div>

    <div class="saved-empty hidden" id="savedEmpty">
      <div class="saved-empty-icon">
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
      </div>
      <h3>Nothing saved yet</h3>
      <p>Tap the bookmark on any Asset card to keep it here for later.</p>
      <a href="../pages/browse.php" class="btn btn--primary btn--md">Find Assets</a>
    </div>
  </main>
</div>

<script src="../assets/js/shared.js?v=70"></script>
<script src="../assets/js/saved.js?v=33"></script>
</body>
</html>
