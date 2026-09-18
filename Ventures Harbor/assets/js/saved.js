/* VENTURES HARBOR — SAVED VENTURES PAGE (saved.js) */

let savedVentures = [];

document.addEventListener("DOMContentLoaded", () => {
  const user = VH.auth.getUser();
  if (user) updateHeaderAndSidebar(user);

  loadSaved();

  document.addEventListener("vh:wishlist-changed", (e) => {
    if (e.detail.saved) return;
    savedVentures = savedVentures.filter(
      (v) => Number(v.id) !== Number(e.detail.ventureId),
    );
    render();
  });

  const navHamburger = document.getElementById("navHamburger");
  const sidebar = document.getElementById("sidebar");
  const sidebarOverlay = document.getElementById("sidebarOverlay");
  const sidebarCollapseBtn = document.getElementById("sidebarCollapseBtn");

  if (navHamburger && sidebar) {
    navHamburger.addEventListener("click", () => {
      sidebar.classList.add("open");
      if (sidebarOverlay) sidebarOverlay.classList.add("active");
    });
  }
  if (sidebarOverlay) {
    sidebarOverlay.addEventListener("click", () => {
      sidebar.classList.remove("open");
      sidebarOverlay.classList.remove("active");
    });
  }
  if (sidebarCollapseBtn) {
    sidebarCollapseBtn.addEventListener("click", () => {
      sidebar.classList.toggle("sidebar--collapsed");
      const pageWrapper = document.querySelector(".page-wrapper");
      if (pageWrapper) pageWrapper.classList.toggle("sidebar-collapsed");
    });
  }

  const avatarBtn = document.getElementById("avatarBtn");
  const navDropdown = document.getElementById("navDropdown");
  if (avatarBtn && navDropdown) {
    avatarBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      navDropdown.classList.toggle("open");
    });
    document.addEventListener("click", () => navDropdown.classList.remove("open"));
  }
});

function updateHeaderAndSidebar(user) {
  const sidebarAvatar = document.getElementById("sidebarAvatar");
  const sidebarUserName = document.getElementById("sidebarUserName");
  const navAvatar = document.getElementById("navAvatar");
  const navAvatarName = document.getElementById("navAvatarName");

  if (sidebarAvatar) VH.renderAvatarInto(sidebarAvatar, user);
  if (sidebarUserName) sidebarUserName.textContent = user.name;
  if (navAvatar) VH.renderAvatarInto(navAvatar, user);
  if (navAvatarName) navAvatarName.textContent = user.name.split(" ")[0];
}

async function loadSaved() {
  const grid = document.getElementById("savedGrid");
  const count = document.getElementById("savedCount");

  try {
    const response = await fetch(
      VH.getBasePath() + "api/ventures.php?action=wishlist_ventures",
      { credentials: "same-origin" },
    );
    const data = await response.json();

    if (response.status === 401) {
      VH.auth.redirectToLogin();
      return;
    }
    if (!data.success) {
      if (count) count.textContent = "Could not load your saved Assets.";
      if (grid) grid.innerHTML = "";
      return;
    }

    savedVentures = data.data || [];
    render();
  } catch (e) {
    if (count) count.textContent = "Could not load your saved Assets.";
    if (grid) grid.innerHTML = "";
  }
}

function render() {
  const grid = document.getElementById("savedGrid");
  const empty = document.getElementById("savedEmpty");
  const count = document.getElementById("savedCount");
  const badge = document.getElementById("savedBadge");
  const n = savedVentures.length;

  if (count) {
    count.textContent = n === 0
      ? "You haven't saved any Assets yet"
      : `${n} Asset${n === 1 ? "" : "s"} you marked as interested`;
  }
  if (badge) {
    badge.textContent = n;
    badge.hidden = n === 0;
  }

  if (grid) {
    grid.innerHTML = savedVentures.map(VH.card.gridHTML).join("");
    grid.classList.toggle("hidden", n === 0);
  }
  if (empty) empty.classList.toggle("hidden", n !== 0);
}
