<?php
/**
 * THE SITE HEADER — one component, every visitor-facing page.
 *
 * This is the PHP equivalent of a React component: `include` it and the same
 * markup renders everywhere, so the header can no longer drift page to page.
 * It replaced nine hand-written copies (four `#vh-nav` blocks and five
 * `.navbar navbar--light` blocks) that had quietly diverged in button padding,
 * font size and logo alignment — the "few pixels up and down" the client saw
 * walking between pages.
 *
 * Use it like this, BEFORE any output on the page:
 *
 *     <?php
 *     $navActive = 'browse';   // which link to mark current
 *     $navSolid  = true;       // light page, no dark hero behind the bar
 *     include __DIR__ . '/../partials/header.php';
 *     ?>
 *
 * Options (all optional — every one has a sane default):
 *
 *   $navActive  '' | 'browse' | 'how' | 'about' | 'contact'
 *               Marks the current link. Defaults to none.
 *   $navSolid   bool. TRUE pins the bar to its light/opaque state from the
 *               first paint, for pages that are white all the way up (venture
 *               detail, create, join, payment return). FALSE (default) starts
 *               transparent over a dark hero and turns light on scroll.
 *   $navSearch  bool. The search box. Defaults TRUE, but to FALSE when
 *               $navContext is set — a page with a breadcrumb is a task page,
 *               and the two together crowd the bar. Hidden below 1160px by
 *               vh-nav.css regardless.
 *   $navCta     bool, default TRUE. The blue "List Your Venture" button.
 *   $navContext raw HTML, default ''. A per-page slot rendered just after the
 *               logo, where the old hand-written navbars put their breadcrumb
 *               ("Browse / <asset>") or "← Back to Dashboard" link. It is
 *               echoed unescaped, so pass markup you control and escape any
 *               user data yourself. These pages are always $navSolid, so the
 *               bar behind it is white and ordinary dark link colours read fine.
 *
 * REQUIRES on the page: assets/css/vh-nav.css, assets/js/shared.js and
 * assets/js/vh-nav.js. The --vh-* custom properties vh-nav.css reads are
 * defined identically in both vh.css and theme.css, so either base sheet works.
 *
 * The signed-in / signed-out swap is not done here — the markup ships both and
 * vh-nav.js shows the right one from VH.auth, because these pages are cached
 * and a server-rendered auth state would be wrong for the next visitor.
 */

$navActive  = $navActive  ?? '';
$navSolid   = $navSolid   ?? false;
$navContext = $navContext ?? '';
$navSearch  = $navSearch  ?? ($navContext === '');
$navCta     = $navCta     ?? true;

/* Path back to the app root, so one markup block works from / and from /pages/.
 * The JS twin is VH.getBasePath() in shared.js — same four folder names, same
 * rule. Keep them in step. */
if (!function_exists('vh_base_path')) {
    function vh_base_path(): string
    {
        $path = $_SERVER['SCRIPT_NAME'] ?? '';
        return preg_match('#^(.*?)/(pages|admin|users|api)/#', $path) ? '../' : '';
    }
}
$b = vh_base_path();

/* One link list, so the desktop bar and the mobile drawer can never disagree.
 * "How It Works" is a section of the homepage, so off-homepage it has to be an
 * absolute jump back to index.php rather than a bare #how that goes nowhere. */
$onHome   = ($b === '');
$navLinks = [
    ['key' => 'browse',  'href' => $b . 'pages/browse.php',           'label' => 'Browse',        'drawer' => 'Browse Assets'],
    ['key' => 'how',     'href' => $onHome ? '#how' : $b . 'index.php#how', 'label' => 'How It Works', 'drawer' => 'How It Works'],
    ['key' => 'about',   'href' => $b . 'pages/about.php',            'label' => 'About',         'drawer' => 'About'],
    ['key' => 'contact', 'href' => $b . 'pages/contact.php',          'label' => 'Contact',       'drawer' => 'Contact'],
];

$linkStyle = 'font-size:14.5px;font-weight:600;color:rgba(255,255,255,.85);white-space:nowrap;transition:color .35s';
?>
<!-- ===== SITE HEADER (partials/header.php — edit there, not here) ===== -->
<nav id="vh-nav"<?php echo $navSolid ? ' class="vh-nav--solid" data-nav-solid="1"' : ''; ?> style="position:fixed;top:0;left:0;right:0;z-index:70;transition:background .35s,box-shadow .35s,backdrop-filter .35s">
  <div class="vh-navrow" style="width:100%;padding:14px clamp(18px,2.6vw,40px);display:flex;align-items:center;gap:clamp(10px,1.6vw,26px)">
    <a href="<?php echo $b; ?>index.php" data-navlogo style="display:flex;align-items:center;gap:9px;font-weight:800;font-size:19px;line-height:1;letter-spacing:-.02em;color:#fff;text-decoration:none;flex:none"><img src="<?php echo $b; ?>assets/img/logo-mark.svg" alt="" width="32" height="32" style="display:block;flex-shrink:0"><span style="white-space:nowrap">Ventures Harbor</span></a>

    <?php if ($navContext !== ''): ?>
    <div class="vh-navcontext" style="min-width:0;flex:0 1 auto;font-size:0.85rem;color:#5A6B85;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo $navContext; ?></div>
    <?php endif; ?>

    <?php if ($navSearch): ?>
    <div id="vh-search" style="flex:0 1 300px;min-width:0;display:flex;align-items:center;gap:9px;padding:9px 16px;border-radius:999px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.08);transition:background .35s,border-color .35s">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" data-navlink style="color:rgba(255,255,255,.55);flex:none;transition:color .35s"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
      <input id="vh-search-input" type="text" placeholder="Search Assets, categories…" data-navlink style="font-size:13.5px;color:rgba(255,255,255,.55);transition:color .35s">
    </div>
    <?php endif; ?>

    <div style="flex:1"></div>

    <div class="vh-navlinks" style="display:flex;align-items:center;flex:none;gap:clamp(12px,1.4vw,26px)">
      <?php foreach ($navLinks as $l): ?>
      <a href="<?php echo htmlspecialchars($l['href']); ?>" data-navlink<?php echo $navActive === $l['key'] ? ' data-navcurrent aria-current="page"' : ''; ?> style="<?php echo $linkStyle; ?>"><?php echo htmlspecialchars($l['label']); ?></a>
      <?php endforeach; ?>
    </div>

    <?php if ($navCta): ?>
    <a href="<?php echo $b; ?>pages/create-venture.php" class="vh-cta-blue vh-navcta" style="display:inline-flex;align-items:center;flex:none;gap:7px;padding:11px 20px;border-radius:999px;background:#2563EB;color:#fff;font-size:14px;font-weight:800;white-space:nowrap;box-shadow:0 6px 18px rgba(37,99,235,.30);transition:transform .25s,box-shadow .25s">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"></path></svg>List Your Venture</a>
    <?php endif; ?>

    <!-- signed out -->
    <a href="<?php echo $b; ?>users/auth.php" class="vh-btn-ghost" data-auth="out" data-show="inline-flex" style="display:none;align-items:center;flex:none;padding:10px 20px;border-radius:999px;font-size:14px;font-weight:700;white-space:nowrap">Sign In</a>

    <!-- signed in -->
    <div class="vh-menu-wrap" data-auth="in" data-show="block" style="display:none;flex:none">
      <div id="vh-avatar" style="display:flex;align-items:center;gap:9px;padding:6px 12px 6px 6px;border-radius:999px;border:1px solid rgba(255,255,255,.14);transition:border-color .35s">
        <div data-user-initials style="width:30px;height:30px;border-radius:999px;background:#2563EB;color:#fff;display:grid;place-items:center;font-size:11.5px;font-weight:800">VH</div>
        <span data-navlink data-user-first style="font-size:13.5px;font-weight:700;color:rgba(255,255,255,.9);transition:color .35s">Account</span>
      </div>
      <div class="vh-menu" id="vh-account-menu">
        <a href="<?php echo $b; ?>admin/dashboard.php"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>Dashboard</a>
        <a href="<?php echo $b; ?>users/profile.php"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>Profile</a>
        <a href="<?php echo $b; ?>users/wallet.php"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="13" rx="2"></rect><path d="M2 11h20"></path></svg>Wallet</a>
        <a href="<?php echo $b; ?>pages/create-venture.php"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"></path></svg>List Your Venture</a>
        <div class="vh-menu-sep"></div>
        <button type="button" class="vh-menu-danger" data-logout><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>Logout</button>
      </div>
    </div>

    <button class="vh-burger" type="button" aria-label="Open menu" style="color:#fff"><span></span><span></span><span></span></button>
  </div>
</nav>

<!-- mobile drawer -->
<div class="vh-drawer" id="vh-drawer">
  <button type="button" class="vh-drawer-close" aria-label="Close menu">×</button>
  <?php foreach ($navLinks as $l): ?>
  <a href="<?php echo htmlspecialchars($l['href']); ?>"><?php echo htmlspecialchars($l['drawer']); ?></a>
  <?php endforeach; ?>
  <a href="<?php echo $b; ?>pages/create-venture.php" style="color:#3B82F6">List Your Venture</a>
  <a href="<?php echo $b; ?>users/auth.php" data-auth="out" data-show="block" style="display:none">Sign In / Sign Up</a>
  <a href="<?php echo $b; ?>admin/dashboard.php" data-auth="in" data-show="block" style="display:none">Dashboard</a>
  <a href="<?php echo $b; ?>users/profile.php" data-auth="in" data-show="block" style="display:none">Profile</a>
  <button type="button" data-auth="in" data-show="block" data-logout style="display:none;color:#FF6B6B">Logout</button>
</div>
