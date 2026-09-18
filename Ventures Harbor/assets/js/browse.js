/* VENTURES HARBOR — BROWSE PAGE LOGIC (browse.js) */

let currentFilters = {
  search: "",
  assetClass: "all",
  industries: [],
  state: "",
  minInvestment: 10000,
  partnerType: "all",
  statuses: ["active"],
  ownership: "all",
  profitFreq: "all",
  roiBand: "all",
  wishlisted: false,
};

const PROFIT_FREQ_FILTER_LABELS = {
  monthly: "Monthly",
  quarterly: "Quarterly",
  yearly: "Annually",
  none: "No regular distribution",
  unspecified: "Not specified",
};

const ROI_BAND_FILTER_LABELS = {
  "0-10": "Under 10%",
  "10-20": "10–20%",
  "20-30": "20–30%",
  "30+": "30%+",
  unspecified: "Not specified",
};

let currentView = "grid"; // 'grid' or 'list'
let allVentures = [];

let renderedCount = 0;

const BATCH_SIZE = 12;

let hasObserver = false;

document.addEventListener("DOMContentLoaded", () => {
  initInfiniteScroll();

  // The header is the shared component in partials/header.php. paintAuth() in
  // vh-nav.js fills in the avatar and shows/hides the signed-in pieces, so this
  // file no longer touches the header at all — it used to swap the avatar for a
  // Sign In link by hand, against ids that no longer exist on this page.

  // Arriving from the header's search box, which sends its term here as ?q=
  // because Browse is the only page that can actually search the catalogue.
  const q = new URLSearchParams(window.location.search).get("q");
  if (q) {
    currentFilters.search = q;
    const mainSearch = document.getElementById("mainSearch");
    if (mainSearch) mainSearch.value = q;
  }

  // Load initial listings
  loadVentures();

  // Range slider event listener
  const rangeInput = document.getElementById("minInvestRange");
  const rangeVal = document.getElementById("rangeVal");
  if (rangeInput && rangeVal) {
    rangeInput.addEventListener("input", (e) => {
      const val = parseInt(e.target.value);
      rangeVal.textContent = VH.card.money(val);
    });
  }

  // Mobile filters sliding drawer toggle
  const mobileFilterBtn = document.getElementById("mobileFilterBtn");
  const filterSidebar = document.getElementById("filterSidebar");
  const filterSidebarOverlay = document.getElementById("filterSidebarOverlay");
  const closeFilterSidebarBtn = document.getElementById("closeFilterSidebar");

  function openFilterDrawer() {
    filterSidebar.classList.add("open");
    if (filterSidebarOverlay) filterSidebarOverlay.classList.add("show");
    mobileFilterBtn.textContent = "✕ Close";
    document.body.style.overflow = "hidden";
  }
  function closeFilterDrawer() {
    filterSidebar.classList.remove("open");
    if (filterSidebarOverlay) filterSidebarOverlay.classList.remove("show");
    mobileFilterBtn.innerHTML = VH.icon("sliders", 15) + " Filters";
    document.body.style.overflow = "";
  }

  if (mobileFilterBtn && filterSidebar) {
    mobileFilterBtn.addEventListener("click", () => {
      filterSidebar.classList.contains("open")
        ? closeFilterDrawer()
        : openFilterDrawer();
    });
  }
  if (filterSidebarOverlay) {
    filterSidebarOverlay.addEventListener("click", closeFilterDrawer);
  }
  if (closeFilterSidebarBtn) {
    closeFilterSidebarBtn.addEventListener("click", closeFilterDrawer);
  }

  // Asset-class chips click listener
  const chips = document.querySelectorAll("#catChips .chip");
  chips.forEach((chip) => {
    chip.addEventListener("click", () => {
      chips.forEach((c) => c.classList.remove("active"));
      chip.classList.add("active");
      currentFilters.assetClass = chip.dataset.assetClass;
      loadVentures();
    });
  });

  // View toggle listeners
  const gridViewBtn = document.getElementById("gridViewBtn");
  const listViewBtn = document.getElementById("listViewBtn");
  const gridEl = document.getElementById("venturesGrid");
  const listEl = document.getElementById("venturesList");

  if (gridViewBtn && listViewBtn) {
    gridViewBtn.addEventListener("click", () => {
      gridViewBtn.classList.add("active");
      listViewBtn.classList.remove("active");
      currentView = "grid";
      syncViewVisibility();
    });

    listViewBtn.addEventListener("click", () => {
      listViewBtn.classList.add("active");
      gridViewBtn.classList.remove("active");
      currentView = "list";
      syncViewVisibility();
    });
  }

  // Sidebar "Apply Filters"
  const applyFiltersBtn = document.getElementById("applyFilters");
  if (applyFiltersBtn) {
    applyFiltersBtn.addEventListener("click", () => {
      // Read industry (searchable single-select input)
      const filterIndustryEl = document.getElementById("filterIndustry");
      const indVal = filterIndustryEl ? filterIndustryEl.value.trim() : "";
      currentFilters.industries = indVal ? [indVal] : [];

      // Read City
      currentFilters.state = document.getElementById("filterState").value;

      // Read Min Investment
      currentFilters.minInvestment = parseInt(rangeInput.value);

      const readSelect = (id, fallback) => {
        const el = document.getElementById(id);
        return el && el.value ? el.value : fallback;
      };
      currentFilters.partnerType = readSelect("filterPartnerType", "all");
      currentFilters.ownership = readSelect("filterOwnership", "all");
      currentFilters.profitFreq = readSelect("filterProfitFreq", "all");
      currentFilters.roiBand = readSelect("filterRoiBand", "all");

      // Read Statuses
      const statusChecks = document.querySelectorAll(
        "#filterStatusGroup input[type='checkbox']:checked",
      );
      currentFilters.statuses = Array.from(statusChecks).map((el) => el.value);

      // Read "Interested only" (the user's wishlist)
      const wishCheck = document.getElementById("filterWishlisted");
      currentFilters.wishlisted = !!(wishCheck && wishCheck.checked);

      // Close mobile filters sidebar if open
      if (filterSidebar && filterSidebar.classList.contains("open")) {
        closeFilterDrawer();
      }

      loadVentures();
      renderAppliedFilters();
    });
  }

  // Clear filters button
  const clearFiltersBtn = document.getElementById("clearFilters");
  if (clearFiltersBtn) {
    clearFiltersBtn.addEventListener("click", () => {
      resetFilters();
    });
  }

  // Reset Search / Empty state button
  const resetSearchBtn = document.getElementById("resetSearch");
  if (resetSearchBtn) {
    resetSearchBtn.addEventListener("click", () => {
      resetFilters();
    });
  }

  // Sort listener
  const sortBySelect = document.getElementById("sortBy");
  if (sortBySelect) {
    sortBySelect.addEventListener("change", () => {
      loadVentures();
    });
  }

  // The avatar dropdown belongs to the shared nav and is wired by home.js.

  document.addEventListener("vh:wishlist-changed", (e) => {
    const { ventureId, saved } = e.detail;
    const match = allVentures.find((v) => Number(v.id) === Number(ventureId));
    if (match) match.is_wishlisted = saved;
    if (currentFilters.wishlisted && !saved) loadVentures();
  });
});


function resetFilters() {
  // Reset inputs visually
  const rangeInput = document.getElementById("minInvestRange");
  if (rangeInput) rangeInput.value = 10000;
  const rangeVal = document.getElementById("rangeVal");
  if (rangeVal) rangeVal.textContent = "₹10K";

  const filterState = document.getElementById("filterState");
  if (filterState) filterState.value = "";

  const filterIndustry = document.getElementById("filterIndustry");
  if (filterIndustry) filterIndustry.value = "";

  document
    .querySelectorAll(".filter-group input[type='checkbox']")
    .forEach((el) => {
      // Active status checkbox remains checked by default
      if (el.value === "active") {
        el.checked = true;
      } else {
        el.checked = false;
      }
    });

  ["filterPartnerType", "filterOwnership", "filterProfitFreq", "filterRoiBand"].forEach(
    (id) => {
      const el = document.getElementById(id);
      if (el) el.value = "all";
    },
  );

  // Reset category chips
  const chips = document.querySelectorAll("#catChips .chip");
  chips.forEach((c) => c.classList.remove("active"));
  if (chips[0]) chips[0].classList.add("active");

  const mainSearch = document.getElementById("mainSearch");
  if (mainSearch) mainSearch.value = "";

  // Reset state variables
  currentFilters = {
    search: "",
    assetClass: "all",
    industries: [],
    city: "",
    minInvestment: 10000,
    partnerType: "all",
    statuses: ["active"],
    ownership: "all",
    profitFreq: "all",
    roiBand: "all",
    wishlisted: false,
  };

  loadVentures();
  renderAppliedFilters();
}

// Global search handler
window.applySearch = function () {
  const mainSearch = document.getElementById("mainSearch");
  if (mainSearch) {
    currentFilters.search = mainSearch.value.trim();
    loadVentures();
  }
};

async function loadVentures() {
  const gridEl = document.getElementById("venturesGrid");
  const listEl = document.getElementById("venturesList");
  const emptyState = document.getElementById("emptyState");
  const resultsCount = document.getElementById("resultsCount");
  const sortBy = document.getElementById("sortBy")
    ? document.getElementById("sortBy").value
    : "newest";

  if (gridEl)
    gridEl.innerHTML =
      '<div style="text-align:center;grid-column:1/-1;padding:3rem 0;color:#94a3b8;">Loading Assets...</div>';
  if (listEl)
    listEl.innerHTML =
      '<div style="text-align:center;padding:3rem 0;color:#94a3b8;">Loading Assets...</div>';

  // Construct query parameters
  const params = new URLSearchParams();
  if (currentFilters.assetClass && currentFilters.assetClass !== "all")
    params.append("asset_class", currentFilters.assetClass);
  if (currentFilters.state) params.append("state", currentFilters.state);
  if (currentFilters.minInvestment > 10000)
    params.append("min_investment", currentFilters.minInvestment);
  if (currentFilters.partnerType !== "all")
    params.append("pType", currentFilters.partnerType);
  if (currentFilters.search) params.append("search", currentFilters.search);
  if (sortBy) params.append("sort", sortBy);
  if (currentFilters.profitFreq && currentFilters.profitFreq !== "all")
    params.append("profit_freq", currentFilters.profitFreq);
  if (currentFilters.roiBand && currentFilters.roiBand !== "all")
    params.append("roi_band", currentFilters.roiBand);

  if (currentFilters.industries.length > 0) {
    params.append("industry", currentFilters.industries[0]);
  }

  if (currentFilters.statuses.length > 0) {
    params.append("status", currentFilters.statuses.join(","));
  }

  if (currentFilters.wishlisted) params.append("wishlisted", "1");

  try {
    const response = await fetch(`../api/ventures.php?${params.toString()}`);
    const res = await response.json();

    if (res.success && res.data) {
      allVentures = res.data;

      if (allVentures.length === 0) {
        if (gridEl) gridEl.innerHTML = "";
        if (listEl) listEl.innerHTML = "";
        if (gridEl) gridEl.classList.add("hidden");
        if (listEl) listEl.classList.add("hidden");
        if (emptyState) emptyState.classList.remove("hidden");
        if (resultsCount)
          resultsCount.innerHTML = `Showing <strong>0</strong> Active Assets`;
        renderedCount = 0;
        updateScrollControls();
        return;
      }

      if (emptyState) emptyState.classList.add("hidden");

      renderFromStart();
    }
  } catch (err) {
    console.error(err);
    if (gridEl)
      gridEl.innerHTML =
        '<div style="text-align:center;grid-column:1/-1;padding:3rem 0;color:#ef4444;">Failed to load listings.</div>';
  }
}

function renderMore() {
  const gridEl = document.getElementById("venturesGrid");
  const listEl = document.getElementById("venturesList");

  const next = allVentures.slice(renderedCount, renderedCount + BATCH_SIZE);
  if (next.length === 0) return;

  appendGridView(next, gridEl);
  appendListView(next, listEl);
  renderedCount += next.length;

  syncViewVisibility();
  updateResultsCount();
  updateScrollControls();
}

function renderFromStart() {
  const gridEl = document.getElementById("venturesGrid");
  const listEl = document.getElementById("venturesList");
  if (gridEl) gridEl.innerHTML = "";
  if (listEl) listEl.innerHTML = "";
  renderedCount = 0;
  renderMore();
}

function syncViewVisibility() {
  const gridEl = document.getElementById("venturesGrid");
  const listEl = document.getElementById("venturesList");
  if (currentView === "grid") {
    if (gridEl) gridEl.classList.remove("hidden");
    if (listEl) listEl.classList.add("hidden");
  } else {
    if (listEl) listEl.classList.remove("hidden");
    if (gridEl) gridEl.classList.add("hidden");
  }
}

function updateResultsCount() {
  const resultsCount = document.getElementById("resultsCount");
  if (!resultsCount) return;
  const total = allVentures.length;
  resultsCount.innerHTML =
    renderedCount >= total
      ? `Showing <strong>${total}</strong> Active Assets`
      : `Showing <strong>${renderedCount}</strong> of <strong>${total}</strong> Active Assets`;
}

function updateScrollControls() {
  const btn = document.getElementById("loadMoreBtn");
  const status = document.getElementById("scrollStatus");
  const done = renderedCount >= allVentures.length;

  if (btn) btn.classList.toggle("hidden", done || hasObserver);
  if (status) {
    // Shown once the whole list is on screen, however short it is. It used to
    // require more than BATCH_SIZE listings, on the assumption this was the
    // footer of a long scrolled list — so a young catalogue never saw it. The
    // one case still excluded is an empty result set: the line would otherwise
    // land under the "no Assets found" state, and under every filter that
    // matches nothing, where it reads as a taunt rather than an invitation.
    status.textContent = done
      ? allVentures.length > 0
        ? "The journey doesn’t end here."
        : ""
      : "Loading more…";
  }
}

function initInfiniteScroll() {
  const sentinel = document.getElementById("scrollSentinel");
  const btn = document.getElementById("loadMoreBtn");

  if (btn) btn.addEventListener("click", renderMore);

  if (!sentinel || typeof IntersectionObserver === "undefined") {
    hasObserver = false;
    updateScrollControls();
    return;
  }

  hasObserver = true;

  const observer = new IntersectionObserver(
    (entries) => {
      if (entries.some((e) => e.isIntersecting)) renderMore();
    },
    { rootMargin: "400px 0px" },
  );
  observer.observe(sentinel);
}

function appendGridView(ventures, container) {
  if (!container) return;
  container.insertAdjacentHTML("beforeend", ventures.map(VH.card.gridHTML).join(""));
}

function appendListView(ventures, container) {
  if (!container) return;
  container.insertAdjacentHTML("beforeend", ventures.map(VH.card.rowHTML).join(""));
}

function renderAppliedFilters() {
  const list = document.getElementById("appliedFilters");
  if (!list) return;

  let items = [];
  // Read the label off the chip rather than prettifying the slug, so the pill
  // wording lives only in $VH_ASSET_CLASSES and cannot drift from what was clicked.
  if (currentFilters.assetClass && currentFilters.assetClass !== "all") {
    const chip = document.querySelector(
      `#catChips .chip[data-asset-class="${currentFilters.assetClass}"]`,
    );
    items.push(`Asset Class: ${chip ? chip.textContent.trim() : currentFilters.assetClass}`);
  }
  if (currentFilters.state) items.push(`State: ${currentFilters.state}`);
  if (currentFilters.minInvestment > 10000)
    items.push(
      `Min Invest: ₹${(currentFilters.minInvestment / 1000).toFixed(0)}K+`,
    );
  if (currentFilters.partnerType !== "all")
    items.push(`Partner: ${currentFilters.partnerType}`);
  if (currentFilters.profitFreq && currentFilters.profitFreq !== "all")
    items.push(`Profit: ${PROFIT_FREQ_FILTER_LABELS[currentFilters.profitFreq]}`);
  if (currentFilters.roiBand && currentFilters.roiBand !== "all")
    items.push(`ROI: ${ROI_BAND_FILTER_LABELS[currentFilters.roiBand]}`);
  if (currentFilters.wishlisted) items.push("Interested only");
  currentFilters.industries.forEach((i) => items.push(`Industry: ${i}`));

  if (items.length === 0) {
    list.innerHTML = "";
    return;
  }

  let html =
    '<div style="display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem;font-size:0.75rem;color:#64748b;"><span>Applied Filters:</span>';
  items.forEach((item) => {
    html += `<span class="badge" style="background:#e2e8f0;color:#475569;display:flex;align-items:center;gap:0.3rem;padding:4px 8px;">${item}</span>`;
  });
  html +=
    '<button class="btn btn--ghost btn--sm" onclick="resetFilters()" style="padding:2px 8px;font-size:0.72rem;">Clear All</button></div>';
  list.innerHTML = html;
}
