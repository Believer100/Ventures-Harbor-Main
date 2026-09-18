<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logo-mark.svg">
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title id="pageTitle">Asset Detail – Ventures Harbor</title>
  <meta name="description" content="View full Asset details, founder info, members, and meetups on Ventures Harbor."/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/css/main.css?v=35"/>
  <link rel="stylesheet" href="../assets/css/components.css?v=38"/>
  <link rel="stylesheet" href="../assets/css/theme.css?v=32"/>
  <link rel="stylesheet" href="../vendor/cropperjs/cropper.min.css?v=31"/>
  <?php /* The sidebar capacity block is #vdCapacityBox, and it wears .vh-vcard so it
           draws the SAME dual bars as a listing card — renderCapacity() emits exactly
           the markup VH.card.capacityHTML() does. This stylesheet was never linked
           here, so every .vc-cap-* rule missed and the block rendered as bare text:
           "Silent Rs1,00,000 / Rs60,00,000 OPEN" with no bar at all. Safe to load —
           venture-card.css is scoped under .vh-vcard throughout. */ ?>
  <link rel="stylesheet" href="../assets/css/venture-card.css?v=45"/>
  <link rel="stylesheet" href="../assets/css/venture-detail.css?v=61"/>
  <link rel="stylesheet" href="../assets/css/venture-gallery.css?v=42"/>
    <link rel="stylesheet" href="../assets/css/vh-pages.css?v=43" />
  <link rel="stylesheet" href="../assets/css/vh-nav.css?v=3">
</head>
<body class="vd-loading">
<div class="toast-container" id="toastContainer"></div>
<?php
$navSolid = true;
$navContext = '<div class="vd-breadcrumb" id="breadcrumb"><a href="browse.php">Browse</a> / <span id="breadName">Asset</span></div>';
include __DIR__ . '/../partials/header.php';
?>

<div id="vdLifecycleBanner" class="vd-lifecycle-banner hidden"></div>

<div id="vdSampleBanner" class="hidden"></div>

<!-- Hero -->
<section class="vd-hero" id="vdHero">

  <div class="vd-hero-media hidden" id="vdHeroMedia">
    <div class="vd-hero-track" id="vgTrack" tabindex="0" role="group" aria-label="Asset media gallery"></div>

    <div class="vd-hero-scrim" aria-hidden="true"></div>
    <button class="vg-arrow vg-arrow--prev hidden" id="vgPrev" type="button" aria-label="Previous item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>
    </button>
    <button class="vg-arrow vg-arrow--next hidden" id="vgNext" type="button" aria-label="Next item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M9 6l6 6-6 6"/></svg>
    </button>
  </div>

  <button class="vd-edit-cover-btn hidden" id="manageGalleryBtn" type="button" title="Add photos and videos for this Asset">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
    <span>Manage gallery</span>
  </button>

  <div class="container vd-hero-container">
    <div class="vd-hero-inner">
      <div class="vd-title-row">
        <div class="vd-big-icon" id="vdIcon">
          <span id="vdIconContent"></span>
          <button class="vd-logo-edit-btn hidden" id="editLogoBtn" type="button" title="Change Asset logo">✎</button>
        </div>
        <input type="file" id="logoFileInput" accept="image/png,image/jpeg,image/webp" hidden/>
        <div class="vd-title-info">
          <h1 id="vdTitle"></h1>
          <div class="vd-title-meta">
            <span class="badge badge--navy" id="vdIndustry"></span>
            <!-- Asset classes. A listing may claim up to 3, including one it named
                 itself; a custom class never becomes a filter pill, so this is where
                 a visitor actually sees it. Filled by renderAssetClasses(). -->
            <span id="vdAssetClasses"></span>
            <span class="vd-meta-pill"><svg class="vh-i" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg> <span id="vdLocation"></span></span>
            <span class="vd-meta-pill"><svg class="vh-i" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 22h14M5 2h14"/><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"/><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"/></svg> <span id="vdDays"></span> days left</span>
          </div>
          <!-- Shown only while vd-hero--loading; JS clears that class once the venture arrives -->
          <div class="vd-skel-block" aria-hidden="true">
            <span class="skeleton vd-skel vd-skel--title"></span>
            <span class="vd-skel-row">
              <span class="skeleton vd-skel vd-skel--pill"></span>
              <span class="skeleton vd-skel vd-skel--chip"></span>
              <span class="skeleton vd-skel vd-skel--chip"></span>
            </span>
          </div>
        </div>
      </div>

      <div class="vd-hero-controls hidden" id="vdHeroControls">
        <div class="vg-dots" id="vgDots"></div>
        <div class="vd-hero-tools">
          <button class="vd-hero-tool hidden" id="vgSoundBtn" type="button" aria-label="Unmute video">
            <span id="vgSoundIcon"><svg class="vh-i" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg></span>
          </button>
          <button class="vd-hero-tool vd-hero-tool--wide" id="vgExpandBtn" type="button" aria-label="View full size">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
            <span class="vg-count" id="vgCount"></span>
          </button>
        </div>
      </div>

      <div class="vd-progress-section">
        <div class="vd-prog-labels">
          <span class="vd-raised">₹<span id="vdRaised"></span> raised</span>
          <span class="vd-prog-pct" id="vdPct"></span>
          <span class="vd-target">of ₹<span id="vdTarget"></span></span>
        </div>
        <div class="vd-prog-bar"><div class="vd-prog-fill" id="vdProgFill" style="width:0%"></div></div>
        <div class="vd-prog-sub">
          <span><svg class="vh-i" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> <strong id="vdMembers"></strong> Members</span>
          <span>Min. ₹<span id="vdMinInv"></span></span>
          <span>Founder: ₹<span id="vdFounderContrib"></span></span>
        </div>
        <!-- Shown only while vd-hero--loading; JS clears that class once the venture arrives -->
        <div class="vd-skel-block" aria-hidden="true">
          <span class="vd-skel-row vd-skel-row--between">
            <span class="skeleton vd-skel vd-skel--money"></span>
            <span class="skeleton vd-skel vd-skel--pct"></span>
            <span class="skeleton vd-skel vd-skel--money"></span>
          </span>
          <span class="skeleton vd-skel vd-skel--bar"></span>
          <span class="vd-skel-row vd-skel-row--sub">
            <span class="skeleton vd-skel vd-skel--chip"></span>
            <span class="skeleton vd-skel vd-skel--chip"></span>
            <span class="skeleton vd-skel vd-skel--chip"></span>
          </span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Main layout -->
<div class="vd-layout container">
  <!-- Left content -->
  <div class="vd-content">
    <!-- Tabs -->
    <div class="vd-tabs tabs-container">
      <div class="vd-tabs-bar">
        <button class="tab-btn active" data-tab="overview">Overview</button>
        <button class="tab-btn" data-tab="members">Members</button>
        <button class="tab-btn" data-tab="meetups">Meetups</button>
        <button class="tab-btn" data-tab="financials">Financials</button>
        <button class="tab-btn hidden" data-tab="applications" id="applicationsTabBtn">Applications</button>
        <button class="tab-btn" data-tab="qna" id="qnaTabBtn">Q&amp;A<span class="vd-tab-count hidden" id="qnaTabCount">0</span></button>
        <button class="tab-btn" data-tab="chat">Group Chat</button>
      </div>
      <div class="tab-panel active" data-panel="overview" id="panelOverview">

        <h3>About this Asset</h3>
        <p id="vdSummary" class="vd-summary"></p>
        <p id="vdDescription" class="vd-description"></p>
        <h4 id="vdUseOfFundsHeading" class="hidden">Use of Funds</h4>
        <p id="vdUseOfFunds" class="vd-requirements hidden"></p>

        <section class="vd-exit-terms" id="vdExitTerms">
          <div class="vd-exit-head">
            <span class="vd-exit-head-icon" aria-hidden="true"><svg class="vh-i" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><rect x="7" y="11" width="3" height="7" rx="1"/><rect x="12.5" y="7" width="3" height="11" rx="1"/><rect x="18" y="4" width="3" height="14" rx="1"/></svg></span>
            <div>
              <h4>Investment &amp; Exit Terms</h4>
              <p>What your capital is committed to, and how you get it back.</p>
            </div>
          </div>
          <div class="vd-exit-grid" id="vdExitGrid"></div>
          <p class="vd-exit-empty hidden" id="vdExitEmpty">The founder hasn't published investment and exit terms for this Asset yet. Ask about them in the Q&amp;A tab before you commit.</p>
        </section>

        <?php /* The public detail page now presents a single partner model. Requirement
                 sections stay available for the founder's own phrasing, but the labels are
                 kept generic until the database schema is brought in line with the new flow. */ ?>
        <h4 id="vdSilentReqHeading" class="hidden">Partner Requirements</h4>
        <p id="vdSilentRequirements" class="vd-requirements hidden"></p>
        <h4 id="vdSilentSkillsHeading" class="hidden">Required Skills</h4>
        <p id="vdSilentRequiredSkills" class="vd-requirements hidden"></p>
        <h4 id="vdReqHeading" class="hidden">Partner Requirements</h4>
        <p id="vdRequirements" class="vd-requirements hidden"></p>
        <h4 id="vdSkillsHeading" class="hidden">Required Skills</h4>
        <p id="vdRequiredSkills" class="vd-requirements hidden"></p>
        <h4 id="vdNoReqHeading" class="hidden">Requirements</h4>
        <p id="vdNoRequirements" class="vd-requirements hidden">No specific requirements listed.</p>
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <h4 style="margin-bottom:0">Asset Documents</h4>
          <button class="btn btn--outline btn--sm hidden" id="addVentureDocBtn" onclick="document.getElementById('ventureDocFileInput').click()">+ Add PDF</button>
        </div>
        <input type="file" id="ventureDocFileInput" accept="application/pdf" hidden/>
        <p style="color:#5A6B85;font-size:0.82rem;margin:0.2rem 0 0.75rem;">Business plan, financial projections, and other files shared by the founder.</p>
        <div class="vd-docs" id="vdVentureDocsList"></div>

      </div>
      <div class="tab-panel" data-panel="members" id="panelMembers">
        <?php /* The separate "Founder Info" tab was merged in here at the client's
                 request — one Members section that opens with the founder and then
                 lists the partners who joined, instead of two tabs describing the
                 same set of people. Nobody appears twice: the founder holds a
                 venture_members row of their own (role 'active', written by
                 `create`), and renderMembers() drops it, which is why the count
                 below is derived from the rendered list rather than from
                 ventures.members_count. */ ?>
        <h3 class="vd-members-heading">Founder</h3>
        <div class="vd-founder-card" id="founderCard" style="cursor:pointer" title="View full profile">
          <div class="founder-avatar-lg" id="founderAvLg"></div>
          <div class="founder-info">
            <div class="vd-founder-name-row">
              <h3 id="founderName"></h3>
              <span class="badge badge--info vd-founder-badge">Founder</span>
            </div>
            <p class="founder-city" id="founderCity"></p>
            <p class="founder-bio" id="founderBio"></p>
            <?php /* The two chips that used to sit here ("Automotive", "Franchise")
                     were hardcoded demo markup — nothing ever replaced them, so every
                     venture's founder showed the same two tags whatever its industry.
                     That is the same stray "Franchise" text the client asked to have
                     removed from the cards. The venture's real industry is already a
                     chip in the page header. */ ?>
            <div id="founderSocialLinks" style="margin-top:0.5rem;color:#2563EB;font-size:0.82rem;"></div>
          </div>
        </div>
        <?php /* The founder's "Past Experience" block used to sit here, between the
                 founder card and the Partners list. Removed at the client's request:
                 it is a paragraph of profile prose in the middle of a tab about who
                 holds what, and it pushed the partner list below the fold. The text
                 itself is not lost — it is still on the founder's own profile, and
                 still reachable from their name here. */ ?>

        <h3 class="vd-members-heading">Partners</h3>
        <p class="vd-members-count" id="membersCountTxt"></p>
        <div class="vd-members-grid" id="membersGrid"></div>
      </div>
      <div class="tab-panel" data-panel="meetups" id="panelMeetups">
        <div id="vdMeetupsList"></div>
      </div>
      <div class="tab-panel" data-panel="financials" id="panelFinancials">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem">
          <p style="color:#5A6B85;font-size:0.85rem;margin:0">Monthly revenue, expenses, profit/loss and per-partner share for this Asset.</p>
          <button class="btn btn--primary btn--sm hidden" id="addFinancialReportBtn" onclick="openFinancialReportModal()">+ Add Monthly Report</button>
        </div>
        <div id="vdFinancialsList"></div>
      </div>
      <div class="tab-panel" data-panel="applications" id="panelApplications">
        <p style="color:#5A6B85;font-size:0.85rem;">Partner interest for this Asset. Review and confirm the right fit.</p>
        <div id="vdApplicationsList"></div>
      </div>

      <div class="tab-panel" data-panel="qna" id="panelQna">
        <div class="vd-qna-intro">
          <p>Ask the founder anything about this Asset — how the money is used, what partners are expected to do, timelines. Questions and answers are public, so everyone considering this Asset benefits.</p>
        </div>

        <!-- Signed-in non-founder: ask a question -->
        <form class="vd-qna-ask hidden" id="qnaAskForm">
          <textarea class="form-control form-textarea" id="qnaInput" rows="3" maxlength="1000" placeholder="e.g. How will the ₹10L target be spent in the first six months?"></textarea>
          <div class="vd-qna-ask-row">
            <span class="vd-qna-counter" id="qnaCounter">0 / 1000</span>
            <button type="submit" class="btn btn--primary btn--sm" id="qnaAskBtn" disabled>Post Question</button>
          </div>
        </form>

        <!-- Signed out -->
        <div class="vd-qna-locked hidden" id="qnaSignedOut">
          <p>Sign in to ask the founder a question.</p>
          <a href="../users/auth.php" class="btn btn--primary btn--sm">Sign in</a>
        </div>

        <!-- Founder's own venture -->
        <div class="vd-qna-locked hidden" id="qnaFounderNote">
          <p id="qnaFounderNoteText">You founded this Asset — reply to questions below.</p>
        </div>

        <div id="qnaList" class="vd-qna-list"></div>
      </div>

      <div class="tab-panel" data-panel="chat" id="panelChat">
        <div id="chatLockedState" class="chat-locked hidden">
          <div class="chat-locked-icon"><svg class="vh-i" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
          <h4>Group Chat is for Members Only</h4>
          <p>You get access to the chat option once you join this Asset.</p>
          <a id="chatJoinLink" href="join-venture.php?id=1" class="btn btn--primary btn--sm">Join This Asset →</a>
        </div>
        <div id="chatActiveState" class="hidden">
          <div class="vd-chat-wrap">
            <div class="vd-chat-header">
              <span class="vd-chat-header-icon"><svg class="vh-i" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
              <div>
                <div class="vd-chat-header-title">Group Chat</div>
                <div class="vd-chat-header-sub" id="chatMemberCount">Members only</div>
              </div>
            </div>
            <div id="chatMessages" class="vd-chat-messages"></div>
            <div id="chatImagePreviewWrap" class="vd-chat-preview-wrap hidden">
              <div class="vd-chat-preview-thumb">
                <img id="chatImagePreview" src="" alt=""/>
                <button type="button" id="chatImageRemoveBtn" class="vd-chat-preview-remove">✕</button>
              </div>
              <span class="vd-chat-preview-label">Image ready to send</span>
            </div>
            <form id="chatForm" class="vd-chat-input-bar">
              <button type="button" class="vd-chat-attach-btn" id="chatAttachBtn" title="Attach an image"><svg class="vh-i" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.44 11.05 12.25 20.24a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg></button>
              <input type="file" id="chatImageInput" accept="image/*" hidden/>
              <input type="text" id="chatInput" class="vd-chat-input" placeholder="Type a message…" maxlength="1000" autocomplete="off"/>
              <button type="submit" class="vd-chat-send-btn" id="chatSendBtn" title="Send" disabled>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
              </button>
            </form>
          </div>
          <p class="vd-chat-footnote"><svg class="vh-i" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Only joined members of this Asset can see and post messages here.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Right sidebar -->
  <div class="vd-sidebar vh-slim-scroll" id="vdSidebar">
    <div class="card vd-join-card">
      <div class="card-body">
        <h3>Join This Asset</h3>
        <div class="vd-join-stats">
          <div><span>Min Investment</span><strong class="vd-skel-fill" id="jMinInv"></strong></div>
          <div><span>Target</span><strong class="vd-skel-fill" id="jTarget"></strong></div>
          <div><span>Members</span><strong class="vd-skel-fill vd-skel-fill--sm" id="jMembers"></strong></div>
          <div><span>Days Left</span><strong class="vd-skel-fill vd-skel-fill--sm" id="jDays"></strong></div>
        </div>
        <div class="vd-prog-bar" style="margin:0.75rem 0"><div class="vd-prog-fill" id="jProgFill" style="width:0%"></div></div>
        <p class="vd-funded-txt vd-skel-fill" id="jPct"></p>

        <div id="vdCapacityBox" class="vh-vcard hidden" style="margin:0 0 0.85rem"></div>
        <div id="vdDeadlineBox" class="hidden" style="margin:0 0 0.85rem;padding:0.7rem 0.85rem;background:#fffbeb;border:1px solid #fde68a;border-radius:10px">
          <p style="font-size:0.78rem;color:#92400e;margin:0"><svg class="vh-i" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 22h14M5 2h14"/><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"/><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"/></svg> Active-partner applications close on <strong id="vdDeadlineDate">—</strong></p>
        </div>
        <a id="joinVentureBtn" href="join-venture.php?id=1" class="btn btn--primary btn--lg btn--full">Join This Asset →</a>

        <button type="button" class="vd-interested" id="vdInterestedBtn" data-wish data-venture-id=""
                aria-pressed="false" aria-label="Mark as interested">
          <svg width="17" height="17" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21.2l7.7-7.7 1.1-1.1a5.5 5.5 0 0 0 0-7.8z"/></svg>
          <span data-wish-label>Interested</span>
        </button>

        <p class="vd-join-note" id="jFeeNote">0.5% commitment fee to confirm your intent</p>

        <div id="vdQuitSection" class="hidden" style="margin-top:0.85rem;padding-top:0.85rem;border-top:1px solid #F1F4F8">
          <p style="font-size:0.78rem;margin:0 0 0.5rem" id="vdQuitNote">—</p>
          <button class="btn btn--secondary btn--full" id="vdQuitBtn" onclick="quitVenture()">Exit Asset</button>
        </div>
      </div>
    </div>
    <div class="card" style="margin-top:1rem">
      <div class="card-body">
        <p style="font-weight:600;margin:0 0 0.75rem;font-size:0.88rem">Share Asset</p>
        <div style="display:flex;gap:0.5rem">
          <button class="btn btn--secondary btn--sm btn--full" onclick="navigator.clipboard.writeText(window.location.href);VH.toast.success('Link copied!')"><svg class="vh-i" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copy Link</button>
          <button class="btn btn--secondary btn--sm btn--full" onclick="window.open('https://wa.me/?text='+encodeURIComponent('Check out this venture: '+window.location.href),'_blank')"><svg class="vh-i" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"/></svg> WhatsApp</button>
        </div>
      </div>
    </div>
    <div id="relatedVentures" style="margin-top:1rem"></div>
  </div>
</div>

<div class="vg-modal" id="vgManageModal">
  <div class="vg-modal-card">
    <div class="vg-modal-head">
      <div>
        <h3>Asset gallery</h3>
        <p class="vg-hint" style="margin-top:.25rem">Photos and videos partners see on this listing. The first image is used as the cover on browse cards and behind the title.</p>
      </div>
      <button class="vg-modal-close" type="button" data-vg-close aria-label="Close">×</button>
    </div>

    <div class="vg-manage-grid" id="vgManageGrid"></div>

    <div class="vg-add-row">
      <button class="btn btn--secondary btn--sm" type="button" id="vgAddFilesBtn">+ Add photos / video</button>
      <span class="vg-count" id="vgRemaining" style="align-self:center"></span>
    </div>
    <input type="file" id="vgFileInput" accept="image/png,image/jpeg,image/webp,image/gif,video/mp4,video/webm,video/quicktime" multiple hidden/>

    <div class="vg-embed-row">
      <input class="form-control" type="url" id="vgEmbedUrl" placeholder="or paste a YouTube, Instagram or Vimeo link"/>
      <button class="btn btn--secondary btn--sm" type="button" id="vgAddEmbedBtn">Add link</button>
    </div>

    <p class="vg-hint">
      Images up to 8MB (JPG, PNG, WebP, GIF). Video files up to 20MB (MP4, WebM, MOV) — for anything longer,
      upload it to YouTube or Vimeo and paste the link instead, which streams better and costs no storage.
      YouTube and Vimeo videos and Instagram posts or reels play inside the gallery; a YouTube channel or
      Instagram profile is added as a link that opens in a new tab, since neither can be played in place.
      Up to <span id="vgMaxNote">25</span> items per Asset.
    </p>
  </div>
</div>

<!-- Full-size image viewer -->
<div class="vg-lightbox" id="vgLightbox">
  <button class="vg-lightbox-close" type="button" aria-label="Close">×</button>
  <img src="" alt="">
</div>

<script src="../assets/js/shared.js?v=70"></script>
<script src="../assets/js/vh-nav.js?v=1"></script>
<script src="../vendor/cropperjs/cropper.min.js?v=31"></script>
<script src="../assets/js/image-cropper.js?v=31"></script>

<script src="../assets/js/venture-gallery.js?v=44"></script>
<script src="../assets/js/venture-detail.js?v=77"></script>

<script src="../assets/js/venture-qna.js?v=35"></script>
</body>
</html>
