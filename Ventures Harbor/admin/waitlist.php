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
  <title>Waitlist – Ventures Harbor</title>
  <meta name="description" content="Assets you are waiting on, and any seat currently open to claim."/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/css/main.css?v=35"/>
  <link rel="stylesheet" href="../assets/css/components.css?v=38"/>
  <link rel="stylesheet" href="../assets/css/theme.css?v=32"/>
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=35"/>
  <link rel="stylesheet" href="../assets/css/vh-pages.css?v=43"/>

  <link rel="stylesheet" href="../assets/css/venture-card.css?v=45"/>
  <link rel="stylesheet" href="../assets/css/waitlist.css?v=6"/>
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
      <a href="saved.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg><span class="sidebar-label">Saved Assets</span><span class="sidebar-badge" id="savedBadge" hidden>0</span></a>
      <a href="waitlist.php" class="sidebar-nav-item active"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span class="sidebar-label">Waitlist</span></a>
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
    <div class="wl-header">
      <div>
        <h1>Waitlist</h1>
        <p class="text-muted" id="wlSubtitle">Loading…</p>
      </div>
      <a href="../pages/browse.php" class="btn btn--primary btn--md">Browse Assets</a>
    </div>

    <?php /* Open seats lead the page. When one exists it is the only thing that
             matters on this screen, and it is time-limited — a claim is held for
             VH_SLOT_CLAIM_MINUTES and then returns to everybody else. */ ?>
    <section class="wl-slots hidden" id="wlSlotsSection">
      <div class="wl-section-head">
        <h2>Seats open now</h2>
        <p>A partner has exited. The first person to claim a seat holds it while they pay — no meeting is required.</p>
      </div>
      <div class="wl-slot-list" id="wlSlotList"></div>
    </section>

    <section class="wl-entries">
      <div class="wl-section-head">
        <h2>Assets you are waiting on</h2>
        <p>You are notified the moment a partner exits one of these — everyone waiting is told at the same time.</p>
        <?php /* THE RULE LINE IS TRANSIENT AND STARTS HIDDEN. It is shown once, for the
                 one Asset just added, and fades after a few seconds — "ek baar show
                 kardo when add any asset, then disappear in few seconds kardo line ko"
                 (client, 7 Sep 2026). wlFlashNote() in waitlist.js fills and reveals
                 it; wlNoteHTML() picks the sentence from that Asset's partner_types.

                 It carries NO text and NO no-JS fallback on purpose. A sentence sitting
                 here statically would be the very thing being removed: one line making a
                 claim over a list of Assets it may not describe. Everything it can say
                 depends on which Asset was added, which only JS knows. */ ?>
        <p class="wl-note wl-flash" id="wlFlash" role="status" aria-live="polite" hidden>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          <span></span>
        </p>
      </div>
      <div class="wl-entry-list" id="wlEntryList"></div>
    </section>

    <div class="wl-empty hidden" id="wlEmpty">
      <div class="wl-empty-icon">
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <h3>You are not waiting on anything</h3>
      <p>When an Asset is fully funded but its listing is still running, its Co-Own button becomes <strong>Join Waitlist</strong>. Join one and you will be told the moment a seat opens.</p>
      <a href="../pages/browse.php" class="btn btn--primary btn--md">Find Assets</a>
    </div>
  </main>
</div>

<?php /* How the race is settled, stated where it is acted on. The claim is a
         guarded UPDATE in vh_claim_slot(); this dialog is only the explanation. */ ?>
<div class="modal-overlay" id="claimModal">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <h3 id="claimTitle">Claim this seat</h3>
      <button class="modal-close" onclick="VH.modal.close('claimModal')">&times;</button>
    </div>
    <div class="modal-body" id="claimBody"></div>
  </div>
</div>

<script src="../assets/js/shared.js?v=70"></script>
<script src="../assets/js/waitlist.js?v=8"></script>
</body>
</html>
