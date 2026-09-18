/* VENTURES HARBOR — SITE HEADER BEHAVIOUR
 *
 * The JS half of partials/header.php: the dark-over-hero → light-on-scroll
 * repaint, the account dropdown, the mobile burger + drawer, and the nav search.
 *
 * This used to live inside home.js, which meant the header only worked on pages
 * willing to load the whole homepage bundle (hero dots, category filters, GSAP
 * scroll animations). That is why venture-detail, join-venture, create-venture
 * and payment-return each carried a different hand-written `.navbar` instead —
 * and why the header changed shape as you walked through the site. Split out,
 * any page can wear the real header for the cost of one small file.
 *
 * Load order: shared.js (for VH.auth), then this. home.js is NOT required.
 *
 * A page that is white all the way to the top sets data-nav-solid="1" on the
 * nav ($navSolid in the partial); the bar then paints its light state from the
 * first frame instead of starting transparent and flashing dark.
 */
(function () {
  'use strict';

  var GOLD = '#F5C518';

  var nav = document.getElementById('vh-nav');
  if (!nav) return;

  var search = document.getElementById('vh-search');
  var avatar = document.getElementById('vh-avatar');
  var logo = document.querySelector('[data-navlogo]');
  var burger = document.querySelector('.vh-burger');
  var navlinks = document.querySelectorAll('[data-navlink]');

  // Pages with no dark hero behind the bar stay solid at every scroll position.
  var alwaysSolid = nav.getAttribute('data-nav-solid') === '1';

  /* ---------- 1. Dark over the hero, light glass once scrolled ---------- */
  function onScroll() {
    var on = alwaysSolid || window.scrollY > 40;
    nav.classList.toggle('vh-nav--solid', on);
    nav.style.background = on ? 'rgba(255,255,255,.88)' : 'transparent';
    nav.style.backdropFilter = on ? 'blur(16px)' : 'none';
    nav.style.webkitBackdropFilter = on ? 'blur(16px)' : 'none';
    nav.style.boxShadow = on
      ? '0 1px 0 rgba(8,20,33,.08), 0 8px 30px rgba(8,20,33,.06)'
      : 'none';
    navlinks.forEach(function (el) {
      // The current page's link keeps a brand colour instead of the muted one,
      // so the header says where you are without a second row of chrome.
      if (el.hasAttribute('data-navcurrent')) {
        el.style.color = on ? '#2563EB' : GOLD;
        return;
      }
      el.style.color = on ? '#33415C' : 'rgba(255,255,255,.85)';
    });
    if (logo) logo.style.color = on ? '#081421' : '#fff';
    if (search) {
      search.style.background = on ? 'rgba(8,20,33,.05)' : 'rgba(255,255,255,.08)';
      search.style.borderColor = on ? 'rgba(8,20,33,.08)' : 'rgba(255,255,255,.08)';
    }
    if (avatar) avatar.style.borderColor = on ? 'rgba(8,20,33,.14)' : 'rgba(255,255,255,.14)';
    if (burger) {
      burger.style.color = on ? '#081421' : '#fff';
      burger.style.borderColor = on ? 'rgba(8,20,33,.14)' : 'rgba(255,255,255,.18)';
    }
  }
  if (!alwaysSolid) window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---------- 2. Auth-aware nav ---------- */
  function paintAuth(user) {
    document.querySelectorAll('[data-auth="out"]').forEach(function (el) {
      el.style.display = user ? 'none' : (el.dataset.show || 'flex');
    });
    document.querySelectorAll('[data-auth="in"]').forEach(function (el) {
      el.style.display = user ? (el.dataset.show || 'flex') : 'none';
    });

    if (user) {
      var initials = user.avatar || String(user.name || '')
        .split(' ').map(function (n) { return n[0]; }).join('').slice(0, 2).toUpperCase();
      document.querySelectorAll('[data-user-initials]').forEach(function (el) { el.textContent = initials; });
      document.querySelectorAll('[data-user-first]').forEach(function (el) {
        el.textContent = String(user.name || 'Account').split(' ')[0];
      });
    }
    onScroll(); // re-tint the freshly shown nav pieces
  }

  paintAuth(window.VH && VH.auth ? VH.auth.getUser() : null);
  document.addEventListener('vh:auth-changed', function (e) {
    paintAuth(e.detail && e.detail.user);
  });

  /* ---------- 3. Account dropdown ---------- */
  var trigger = document.getElementById('vh-avatar');
  var menu = document.getElementById('vh-account-menu');
  if (trigger && menu) {
    trigger.style.cursor = 'pointer';
    trigger.addEventListener('click', function () {
      menu.classList.toggle('open');
    });
    // Close on a click anywhere outside the menu or its trigger.
    //
    // This deliberately does NOT call stopPropagation() inside the menu, which
    // is what it used to do to keep itself open. VH.auth.bindLogout() listens
    // on `document`, so swallowing the click here meant the Logout item in
    // this dropdown never reached its handler — the homepage was the one page
    // where logging out did nothing. Checking where the click landed keeps the
    // menu open without blocking every delegated listener on the page.
    document.addEventListener('click', function (e) {
      if (menu.contains(e.target) || trigger.contains(e.target)) return;
      menu.classList.remove('open');
    });
  }

  /* ---------- 4. Mobile drawer ---------- */
  var drawer = document.getElementById('vh-drawer');
  if (burger && drawer) {
    burger.addEventListener('click', function () {
      drawer.classList.add('open');
      document.body.style.overflow = 'hidden';
    });
    drawer.addEventListener('click', function (e) {
      if (e.target === drawer || e.target.closest('.vh-drawer-close') || e.target.tagName === 'A') {
        drawer.classList.remove('open');
        document.body.style.overflow = '';
      }
    });
  }

  /* ---------- 5. Nav search ---------- */
  // The box is on every page now, so Enter needs to mean something everywhere.
  // On the homepage the grid is right there, so jump to it and let home.js's
  // live filter do the work. Anywhere else, hand the term to Browse, which
  // reads ?q= on load.
  var searchInput = document.getElementById('vh-search-input');
  if (searchInput) {
    searchInput.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      var localGrid = document.getElementById('ventures');
      if (localGrid) {
        localGrid.scrollIntoView({ behavior: 'smooth' });
        return;
      }
      var q = searchInput.value.trim();
      var base = window.VH && VH.getBasePath ? VH.getBasePath() : '';
      window.location.href = base + 'pages/browse.php' + (q ? '?q=' + encodeURIComponent(q) : '');
    });
  }
})();
