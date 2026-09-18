<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

require_once __DIR__ . '/../config/lookups.php';
$currentUser = requirePageAuth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logo-mark.svg">
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Profile – Ventures Harbor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/css/main.css?v=35"/>
  <link rel="stylesheet" href="../assets/css/components.css?v=38"/>
  <link rel="stylesheet" href="../assets/css/theme.css?v=32"/>
  <link rel="stylesheet" href="../vendor/cropperjs/cropper.min.css?v=31"/>
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=35"/>
  <link rel="stylesheet" href="../assets/css/profile.css?v=33"/>
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
      <a href="profile.php" class="sidebar-nav-item active"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span class="sidebar-label">Profile</span></a>
      <div class="sidebar-nav-item sidebar-nav-item--danger" id="sidebarLogout"><svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span class="sidebar-label">Logout</span></div>
      <div class="sidebar-nav-item sidebar-nav-item--danger" id="sidebarDeleteAccount" style="cursor:pointer">
        <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        <span class="sidebar-label">Delete Account</span>
      </div>
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
      <div class="nav-dropdown" id="navDropdown"><a href="../admin/dashboard.php" class="nav-dropdown-item">Dashboard</a><div class="nav-dropdown-item danger" id="navLogoutBtn">Logout</div></div>
    </div>
  </nav>

  <main class="page-content">
    <div class="profile-layout">
      <!-- Profile Header -->
      <div class="profile-header-card card">
        <div class="profile-header-body">
          <div class="profile-avatar-wrap">
            <div class="profile-avatar-circle" id="profileAvCircle">RK</div>
            <button class="profile-avatar-edit-btn" id="avatarEditBtn" type="button" onclick="document.getElementById('avatarInput').click()" title="Add or change profile photo" aria-label="Add or change profile photo">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
            </button>
            <input type="file" id="avatarInput" accept="image/*" hidden/>
          </div>
          <div class="profile-header-info">
            <h2 class="profile-name" id="profileName">Rahul Kumar</h2>
            <p class="profile-occ">
              <span id="profileOccText">Entrepreneur</span>
              <span class="profile-occ-dot">·</span>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="profile-pin-icon"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              <span id="profileCityText">Mumbai</span>
            </p>
            <div class="profile-social-links hidden" id="profileSocialLinks"></div>
            <div class="profile-quick-stats">
              <span><strong id="statCreated">2</strong> Created</span>
              <span class="profile-stat-dot">·</span>
              <span><strong id="statJoined">3</strong> Joined</span>
              <span class="profile-stat-dot">·</span>
              <span><strong id="statInvested">₹4.5L</strong> Invested</span>
            </div>
          </div>
          <a href="../admin/dashboard.php" class="btn btn--secondary btn--sm profile-dashboard-btn">← Dashboard</a>
        </div>
      </div>

      <!-- Content -->
      <div class="profile-content">
        <div class="profile-tabs tabs-container">
          <div class="vd-tabs-bar">
            <button class="tab-btn active" data-tab="profileInfo">Profile</button>
            <button class="tab-btn" data-tab="activity">Activity</button>
          </div>

          <!-- Profile Tab -->
          <div class="tab-panel active" data-panel="profileInfo">
            <div class="card">
              <div class="card-header"><h3 class="card-title" id="pfCardTitle">Personal Details</h3></div>
              <div class="card-body">
                <form id="profileForm">

                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                    <div class="form-group"><label class="form-label" id="pfNameLabel">Full Name</label><input class="form-control" id="pfName" placeholder="Full Name"/></div>
                    <div class="form-group"><label class="form-label">Email <span style="color:#7A8AA3;font-size:0.75rem">(Cannot change)</span></label><input class="form-control" id="pfEmail" disabled style="background:#F7F8FA"/></div>
                    <div class="form-group"><label class="form-label">Phone</label><input class="form-control" id="pfPhone" placeholder="+91 98765 43210"/></div>
                    <div class="form-group" data-acct="individual"><label class="form-label">Age</label><input class="form-control" id="pfAge" type="number" placeholder="30"/></div>
                    <div class="form-group">
                      <label class="form-label">City</label>
                      <input class="form-control" id="pfCity" list="pfCityOptions"
                             autocomplete="address-level2"
                             placeholder="Start typing, or pick from the list"/>
                      <datalist id="pfCityOptions">
                        <?php foreach ($VH_CITIES as $city): ?>
                          <option value="<?php echo htmlspecialchars($city); ?>"></option>
                        <?php endforeach; ?>
                      </datalist>
                    </div>
                    <div class="form-group" data-acct="individual" style="grid-column:1/-1"><label class="form-label">Occupation</label><input class="form-control" id="pfOccupation" placeholder="Entrepreneur, Investor, Developer..."/></div>

                    <div class="form-group"><label class="form-label" id="pfWebsiteLabel">Website <span style="color:#7A8AA3;font-size:0.75rem">(optional)</span></label><input class="form-control" id="pfWebsite" type="url" placeholder="https://..."/></div>
                    <div class="form-group" style="grid-column:1/-1"><label class="form-label" id="pfSkillsLabel">Skills <span style="color:#7A8AA3;font-size:0.75rem" id="pfSkillsHint">(comma-separated — shown to founders when you apply as a Partner)</span></label><input class="form-control" id="pfSkills" placeholder="e.g. Marketing, Sales, Excel"/></div>
                    <div class="form-group" style="grid-column:1/-1"><label class="form-label" id="pfBioLabel">Bio <span class="char-counter" id="bioCount">0/250</span></label><textarea class="form-control form-textarea" id="pfBio" rows="3" maxlength="250" placeholder="Tell other members about yourself..."></textarea></div>
                  </div>

                  <h4 style="margin:1.5rem 0 0.75rem;font-size:0.95rem;"><svg class="vh-i" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg> Social Media Profile Links</h4>
                  <p style="font-size:0.8rem;color:#5A6B85;margin:-0.5rem 0 0.75rem;">Shown to other members so they can evaluate your background and credibility before joining or partnering with your Venture.</p>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                    <div class="form-group"><label class="form-label">LinkedIn</label><input class="form-control" id="pfLinkedin" type="url" placeholder="https://linkedin.com/in/..."/></div>
                    <div class="form-group"><label class="form-label">Twitter / X</label><input class="form-control" id="pfTwitter" type="url" placeholder="https://twitter.com/..."/></div>
                    <div class="form-group"><label class="form-label">Instagram</label><input class="form-control" id="pfInstagram" type="url" placeholder="https://instagram.com/..."/></div>
                  </div>

                  <h4 style="margin:1.5rem 0 0.75rem;font-size:0.95rem;" data-acct="individual"><svg class="vh-i" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg> Past Experience</h4>
                  <div class="form-group" data-acct="individual">
                    <label class="form-label" id="pfExperienceLabel">Prior Assets, roles, and achievements <span class="char-counter" id="experienceCount">0/500</span></label>
                    <textarea class="form-control form-textarea" id="pfPastExperience" rows="4" maxlength="500" placeholder="e.g. Founded and exited a cloud kitchen chain in 2023; 8 years in digital marketing..."></textarea>
                  </div>

                  <button type="submit" class="btn btn--primary btn--md">Save Changes</button>
                </form>
              </div>
            </div>
          </div>

          <!-- Activity Tab -->
          <div class="tab-panel" data-panel="activity">
            <div class="card">
              <div class="card-header"><h3 class="card-title">Recent Activity</h3></div>
              <div class="card-body" id="activityList"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Delete (deactivate) Account Modal -->
<div class="modal-overlay" id="deleteAccountModal">
  <div class="modal">
    <button class="modal-close" onclick="VH.modal.close('deleteAccountModal')">×</button>
    <div class="modal-header">
      <h3 class="modal-title">Delete Account</h3>
    </div>
    <div style="padding:0 1.5rem 1rem;">
      <?php // Deletion is final: signing up again with this email creates a new,
            // empty account — it does not bring this one back. The dialog has to
            // say so before the fact, not the login screen afterwards. ?>
      <p class="delete-final-warning">
        <strong>This cannot be undone.</strong>
        Once you delete your account there is <strong>no way to restore it</strong>. Signing up again with
        the same email creates a brand-new, empty account — your Ventures, partnerships and history do
        not come back with it.
      </p>
      <?php // Hidden wholesale when the account can't be deleted yet — asking
            // "are you sure?" under a notice saying you can't is nonsense. ?>
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

<script src="../assets/js/shared.js?v=70"></script>
<script src="../vendor/cropperjs/cropper.min.js?v=31"></script>
<script src="../assets/js/image-cropper.js?v=31"></script>
<script src="../assets/js/profile.js?v=40"></script>
</body>
</html>
