<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/lookups.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logo-mark.svg">
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Browse Assets – Ventures Harbor</title>
  <meta name="description" content="Browse all active Assets on Ventures Harbor. Filter by industry, location, capital, and partner type."/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/css/main.css?v=35"/>
  <link rel="stylesheet" href="../assets/css/components.css?v=38"/>
  <link rel="stylesheet" href="../assets/css/theme.css?v=32"/>
  <link rel="stylesheet" href="../assets/css/browse.css?v=38"/>
    <link rel="stylesheet" href="../assets/css/vh-pages.css?v=43" />

  <link rel="stylesheet" href="../assets/css/venture-card.css?v=45"/>
  <link rel="stylesheet" href="../assets/css/tilt.css?v=32" />
  <link rel="stylesheet" href="../assets/css/vh-nav.css?v=3">
</head>
<body>
<div class="toast-container" id="toastContainer"></div>
<!-- Site nav — now the shared component in partials/header.php, which every
     visitor-facing page includes. Styles: assets/css/vh-nav.css, behaviour:
     assets/js/vh-nav.js.

     Dashboard and Legal are signed-in only: both admin/dashboard.php and
     visitor only ever produced a bounce to the login page. data-auth="in" is the
     existing mechanism paintAuth() in vh-nav.js already drives. -->
<?php
$navActive = 'browse';
include __DIR__ . '/../partials/header.php';
?>

<!-- Hero Search -->
<section class="browse-hero">

  <div class="browse-hero-art" aria-hidden="true">
    <div class="browse-hero-scanner" data-vh-scanner
         data-color1="#123C8C"
         data-color2="#F5C518"
         data-color3="#FFFFFF"
         data-speed="0.42"
         data-sweep-speed="0.22"
         data-sweep-width="1.5"
         data-sweep-falloff="6"
         data-scale="0.9"
         data-frequency="2"
         data-ripple="0.24"
         data-band-density="12"
         data-line-sharpness="5.5"
         data-glow="0.3"
         data-scan-direction="vertical"
         data-color-spread="0.3"
         data-brightness="1.45"
         data-contrast="1.15"
         data-softness="1.4"
         data-vignette="0.3"
         data-scanline="true"
         data-grain="true"
         data-grain-intensity="0.04"
         data-opacity="1.0"
         data-mouse-interaction="true"
         data-mouse-radius="0.5"
         data-mouse-strength="0.5"></div>

    <div class="browse-hero-scrim"></div>
  </div>
  <div class="browse-hero-inner">
    <h1>Explore Marketplace</h1>
    <p>Discover and Co-own Valuable Real-World Opportunities.</p>
    <div class="browse-search-box">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7A8AA3" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="text" id="mainSearch" placeholder="Search Assets, Industries, Locations…"/>
      <button class="btn btn--primary" onclick="applySearch()">Search</button>
    </div>
    <div class="browse-chips" id="catChips">
      <button class="chip active" data-asset-class="all">All Assets</button>
      <?php foreach ($VH_ASSET_CLASSES as $slug => $label): ?>
      <button class="chip" data-asset-class="<?php echo htmlspecialchars($slug); ?>"><?php echo htmlspecialchars($label); ?></button>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Main layout -->
<div class="browse-layout">
  <!-- Filter Sidebar -->

  <aside class="browse-filters" id="filterSidebar">
    <div class="filter-header">
      <h3>Filters</h3>
      <div style="display:flex;align-items:center;gap:0.5rem;">
        <button class="btn btn--ghost btn--sm" id="clearFilters">Clear All</button>
        <button class="btn btn--ghost btn--sm filter-close-btn" id="closeFilterSidebar" style="font-size:1.1rem;padding:2px 8px;">✕</button>
      </div>
    </div>
    <div class="browse-filters-body vh-slim-scroll">
    <div class="filter-group">
      <h4>Industry</h4>
      <select class="form-control form-select" id="filterIndustry">
        <option value="">All Industries</option>
        <?php foreach ($VH_INDUSTRIES as $ind): ?>
          <option value="<?php echo htmlspecialchars($ind); ?>"><?php echo htmlspecialchars($ind); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-group">
      <h4>Location (State)</h4>
      <select class="form-control form-select" id="filterState">
        <option value="">All States</option>
        <?php foreach ($VH_STATES as $st): ?>
          <option value="<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($VH_STATE_LABELS[$st] ?? $st); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-group">
      <h4>Min Investment</h4>
      <input type="range" class="range-slider" id="minInvestRange" min="10000" max="500000" step="10000" value="10000"/>
      <div class="filter-range-labels"><span>₹10K</span><span id="rangeVal">₹10K</span></div>
    </div>

    <div class="filter-group">
      <h4>Partnership</h4>
      <select class="form-control form-select" id="filterPartnerType">
        <option value="all">All</option>
        <option value="partner">Partner</option>
      </select>
    </div>
    <div class="filter-group">
      <h4>Profit Distribution</h4>
      <select class="form-control form-select" id="filterProfitFreq">
        <option value="all">Any Frequency</option>
        <option value="monthly">Monthly</option>
        <option value="quarterly">Quarterly</option>
        <option value="yearly">Annually</option>
        <option value="none">No Regular Distribution</option>
        <option value="unspecified">Not Specified</option>
      </select>
    </div>

    <div class="filter-group">
      <h4>Expected ROI</h4>
      <select class="form-control form-select" id="filterRoiBand">
        <option value="all">Any ROI</option>
        <option value="0-10">Under 10%</option>
        <option value="10-20">10–20%</option>
        <option value="20-30">20–30%</option>
        <option value="30+">30%+</option>
        <option value="unspecified">Not Specified</option>
      </select>
    </div>

    <div class="filter-group">
      <h4>My List</h4>
      <label class="filter-check"><input type="checkbox" id="filterWishlisted"> Interested only</label>
    </div>
    <div class="filter-group" id="filterStatusGroup">
      <h4>Status</h4>
      <label class="filter-check"><input type="checkbox" value="active" checked> Active</label>
      <label class="filter-check"><input type="checkbox" value="nearly_funded"> Nearly Funded</label>
      <label class="filter-check"><input type="checkbox" value="just_listed"> Just Listed</label>
    </div>
    </div><!-- /.browse-filters-body -->
    <button class="btn btn--primary btn--full btn--md" id="applyFilters">Apply Filters</button>
  </aside>

  <!-- Content -->
  <div class="browse-content">
    <div class="browse-toolbar">
      <p id="resultsCount">Showing <strong>12</strong> active Assets</p>
      <div class="browse-toolbar-right">
        <select class="form-control form-select" id="sortBy" style="width:auto;">
          <option value="newest">Newest First</option>
          <option value="most_funded">Most Funded</option>
          <option value="ending_soon">Ending Soon</option>
          <option value="capital_low">Capital: Low–High</option>
          <option value="capital_high">Capital: High–Low</option>
        </select>
        <div class="view-toggle">
          <button class="view-btn active" id="gridViewBtn" title="Grid view">⊞</button>
          <button class="view-btn" id="listViewBtn" title="List view">☰</button>
        </div>
      </div>
    </div>
    <div class="applied-filters" id="appliedFilters"></div>
    <div class="ventures-grid" id="venturesGrid"></div>
    <div class="ventures-list hidden" id="venturesList"></div>
    <div class="empty-state hidden" id="emptyState">
      <div style="color:#94A3B8"><svg class="vh-i" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg></div>
      <h3>No Assets found</h3>
      <p>Try adjusting your filters or search query</p>
      <button class="btn btn--primary" id="resetSearch">Clear Filters</button>
    </div>

    <div class="browse-scroll-sentinel" id="scrollSentinel">
      <button class="btn btn--secondary btn--md hidden" id="loadMoreBtn">Load more Assets</button>
      <div class="browse-scroll-status" id="scrollStatus" role="status" aria-live="polite"></div>
    </div>
  </div>
</div>

<div class="browse-filters-overlay" id="filterSidebarOverlay"></div>
<button class="browse-filter-fab" id="mobileFilterBtn"><svg class="vh-i" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg> Filters</button>

<script src="../assets/js/shared.js?v=70"></script>
<script src="../assets/js/vh-nav.js?v=1"></script>
<!-- Drives the shared nav: paintAuth() shows/hides the data-auth pieces and
     onScroll() switches it from dark-over-hero to light glass. Without it the
     nav renders but never reacts. Same pairing About and Contact use. -->
<script src="../assets/js/home.js?v=45"></script>
<script src="../assets/js/scanner-bg.js?v=1"></script>
<script src="../assets/js/browse.js?v=45"></script>
<script src="../assets/js/tilt.js?v=31"></script>
</body>
</html>
