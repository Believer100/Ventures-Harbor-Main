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
  <title>Meetups – Ventures Harbor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/css/main.css?v=35"/>
  <link rel="stylesheet" href="../assets/css/components.css?v=38"/>
  <link rel="stylesheet" href="../assets/css/theme.css?v=32"/>
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=35"/>
  <link rel="stylesheet" href="../assets/css/meetup.css?v=32"/>
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
      <a href="dashboard.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg><span class="sidebar-label">Overview</span></a>
      <a href="../pages/browse.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg><span class="sidebar-label">Browse Assets</span></a>
      <a href="saved.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg><span class="sidebar-label">Saved Assets</span></a>
      <a href="waitlist.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span class="sidebar-label">Waitlist</span></a>
      <a href="../pages/create-venture.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg><span class="sidebar-label">List Your Venture</span></a>
      <a href="meetup.php" class="sidebar-nav-item active"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><span class="sidebar-label">Meetups</span></a>
      <a href="../users/profile.php" class="sidebar-nav-item"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span class="sidebar-label">Profile</span></a>
      <div class="sidebar-nav-item sidebar-nav-item--danger" id="sidebarLogout"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span class="sidebar-label">Logout</span></div>
    </div>
  </nav>
  <div class="sidebar-footer"><div class="sidebar-user"><div class="nav-avatar" id="sidebarAvatar">RK</div><div class="sidebar-user-info"><div class="sidebar-user-name" id="sidebarUserName">Rahul Kumar</div><div class="sidebar-user-role">Active Member</div></div></div></div>
</aside>

<!-- ── PAGE WRAPPER ── -->
<div class="page-wrapper">
  <nav class="navbar navbar--light" id="navbar">
    <div class="nav-container">
      <button class="nav-hamburger" id="navHamburger"><span></span><span></span><span></span></button>
      <div style="flex:1"></div>
      <div class="nav-avatar-btn" id="avatarBtn" style="position:relative;"><div class="nav-avatar" id="navAvatar">RK</div><span class="nav-avatar-name" id="navAvatarName">Rahul</span></div>
      <div class="nav-dropdown" id="navDropdown"><a href="dashboard.php" class="nav-dropdown-item">Dashboard</a><div class="nav-dropdown-item danger" id="navLogoutBtn">Logout</div></div>
    </div>
  </nav>

  <main class="page-content">
    <div class="meetup-header">
      <div>
        <h1>Asset Meetups</h1>
        <p class="text-muted">Schedule and manage face-to-face or online collaborations</p>
      </div>
      <?php /* Scheduling is the founder's, not the investor's — "remove schedule meetup
               from investor side". The button starts HIDDEN and is revealed only once
               fetchVenturesForDropdown() finds at least one Asset this person founded,
               the same fail-closed shape as VH.deleteAccountRefund.gate(): a investor
               must never see it, and a slow request must not be what decides that. */ ?>
      <div>
        <button class="btn btn--primary hidden" id="mScheduleBtn" onclick="VH.modal.open('scheduleModal')">+ Schedule Meetup</button>
      </div>
    </div>

    <!-- Meetups layout -->
    <div class="meetup-layout">
      <!-- Calendar sidebar -->
      <div class="meetup-sidebar">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Mini Calendar</h3></div>
          <div class="card-body">
            <div class="mini-calendar" id="miniCalendar"></div>
          </div>
        </div>

        <div class="card" style="margin-top:1.5rem">
          <div class="card-header"><h3 class="card-title">Meetup Stats</h3></div>
          <div class="card-body">
            <div class="meetup-stats-row"><span>Total Meetups</span><strong id="statTotalMeetups">10</strong></div>
            <div class="meetup-stats-row"><span>Upcoming</span><strong id="statUpcomingMeetups" style="color:#2563EB">7</strong></div>
            <div class="meetup-stats-row"><span>Attended</span><strong id="statAttendedMeetups" style="color:#16a34a">3</strong></div>
          </div>
        </div>
      </div>

      <!-- Meetups listings -->
      <div class="meetup-list-container">
        <div class="meetup-tabs tabs-container">
          <div class="vd-tabs-bar">
            <button class="tab-btn active" data-tab="upcoming">Upcoming Meetups</button>
            <button class="tab-btn" data-tab="past">Past History</button>
          </div>

          <!-- Upcoming panel -->
          <div class="tab-panel active" data-panel="upcoming" id="upcomingPanel"></div>

          <!-- Past panel -->
          <div class="tab-panel" data-panel="past" id="pastPanel"></div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Schedule Meetup Modal -->
<div class="modal-overlay" id="scheduleModal">
  <div class="modal" style="max-width:550px">
    <button class="modal-close" onclick="VH.modal.close('scheduleModal')">×</button>
    <div class="modal-header">
      <h3 class="modal-title">Schedule a Meetup</h3>
    </div>
    <form id="scheduleForm" style="padding:0 1.5rem 1.5rem">
      <div class="form-group">
        <label class="form-label">Select Venture *</label>
        <select class="form-control form-select" id="mFormVenture" required></select>
      </div>
      <div class="form-group">
        <label class="form-label">Meetup Title *</label>
        <input class="form-control" id="mFormTitle" placeholder="e.g. Q2 Financial Planning" required/>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
        <div class="form-group">
          <label class="form-label">Date *</label>
          <input class="form-control" id="mFormDate" type="date" required/>
        </div>
        <div class="form-group">
          <label class="form-label">Time *</label>
          <input class="form-control" id="mFormTime" type="time" required/>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Location Type</label>
        <div class="cv-mode-cards">
          <label class="cv-mode-card" id="mLocPhysicalCard"><input type="radio" name="mLocType" value="physical" checked onclick="toggleLocFields()"/><span><svg class="vh-i" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg> Physical</span></label>
          <label class="cv-mode-card"><input type="radio" name="mLocType" value="online" onclick="toggleLocFields()"/><span><svg class="vh-i" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10"/></svg> Online</span></label>
        </div>
        <p class="mt-online-note hidden" id="mLocModeNote"></p>
      </div>
      <div class="form-group" id="physicalLocGroup">
        <label class="form-label">Physical Address *</label>
        <input class="form-control" id="mFormLoc" placeholder="e.g. BKC Commercial Hub, Mumbai"/>
      </div>
      <div class="form-group hidden" id="onlineLocGroup">
        <label class="form-label">Meeting Link *</label>
        <input class="form-control" id="mFormLink" placeholder="e.g. https://meet.google.com/abc-def-ghi"/>
      </div>
      <div class="form-group">
        <label class="form-label">Discussion Notes</label>
        <textarea class="form-control form-textarea" id="mFormNotes" rows="3" placeholder="What should attendees bring or review?"></textarea>
      </div>
      <div class="modal-footer" style="padding:1.5rem 0 0; border-top:1px solid #F1F4F8; display:flex; justify-content:flex-end; gap:0.5rem">
        <button type="button" class="btn btn--secondary" onclick="VH.modal.close('scheduleModal')">Cancel</button>
        <button type="submit" class="btn btn--primary">Schedule Meetup</button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/shared.js?v=70"></script>
<script src="../assets/js/meetup.js?v=36"></script>
</body>
</html>
