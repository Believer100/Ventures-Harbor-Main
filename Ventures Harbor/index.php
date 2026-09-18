<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/lookups.php';

$result   = $mysqli->query(
    "SELECT * FROM ventures
     WHERE status = 'active'
       -- Funded silent-only listings stay visible while they still have time on the
       -- clock: out of room is not closed, and the card offers Join Waitlist. The SQL
       -- twin of vh_waitlist_is_open() — and of the same clause in api/ventures.php's
       -- list query. Change one, change all three.
       AND NOT (target_capital > 0 AND raised_capital >= target_capital AND partner_types = 'silent'
            AND (listing_ends_at IS NULL OR listing_ends_at <= NOW()))
     ORDER BY is_showcase DESC, id ASC LIMIT 6"
);
$ventures = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

require_once __DIR__ . '/config/membership.php';
require_once __DIR__ . '/config/waitlist.php';
vh_attach_capital_split($mysqli, $ventures);
// Both API list paths do this and the homepage did not, so vh_waitlist_is_open()
// read reserved_seat_capital as 0 here: an Asset holding a seat for its waitlist
// showed Co-Own on the homepage, offering a passer-by room that was already spoken
// for. The seat is only reserved between an exit and the claim, so it was easy to miss.
vh_attach_reserved_seats($mysqli, $ventures);

$wishlisted = [];
if (!empty($_SESSION['user_id'])) {
    $stmt = $mysqli->prepare("SELECT venture_id FROM venture_wishlist WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $wishlisted[(int)$row['venture_id']] = true;
    }
    $stmt->close();
}

$viewerStates = getViewerVentureStates($mysqli, (int)($_SESSION['user_id'] ?? 0));

$viewerStateLabels = [
    'founder'    => 'Your Venture',
    'member'     => '✓ Joined',
    'selected'   => 'Selected',
    'applied'    => 'Applied',
    'waitlisted' => 'On Waitlist',
];

if (!function_exists('ventureInitial')) {
    function ventureInitial($title) {
        $first = preg_split('/\s+/u', trim((string)$title))[0] ?? '';
        if (preg_match('/[\p{L}\p{N}]/u', $first, $m)) {
            return mb_strtoupper($m[0], 'UTF-8');
        }
        return '?';
    }
}
if (!function_exists('compactINR')) {
    function compactINR($n) {
        $n = (float)$n;
        if ($n >= 10000000) { $c = $n / 10000000; return '₹' . (fmod($c, 1) == 0 ? number_format($c, 0) : number_format($c, 1)) . 'Cr'; }
        if ($n >= 100000)   { $l = $n / 100000;   return '₹' . (fmod($l, 1) == 0 ? number_format($l, 0) : number_format($l, 1)) . 'L'; }
        if ($n >= 1000)     { $k = $n / 1000;     return '₹' . (fmod($k, 1) == 0 ? number_format($k, 0) : number_format($k, 1)) . 'K'; }
        return '₹' . number_format($n, 0, '.', ',');
    }
}
if (!function_exists('categoryLabel')) {
    function categoryLabel($slug) {
        $labels = [
            'franchise'   => 'Franchise',
            'food'        => 'Food & Beverage',
            'tech'        => 'Technology',
            'infra'       => 'Infrastructure',
            'education'   => 'Education',
            'healthcare'  => 'Healthcare',
            'logistics'   => 'Logistics',
            'agriculture' => 'Agriculture',
            'real_estate' => 'Real Estate',
        ];
        $key = strtolower(trim((string)$slug));

        return $labels[$key] ?? ucwords(str_replace(['_', '-'], ' ', $key));
    }
}

require_once __DIR__ . '/config/site-stats.php';
$stats = vh_site_stats($mysqli);

/* Filter chips derived from the ventures actually on screen.
   Ordered and labelled by $VH_ASSET_CLASSES so the homepage row reads exactly
   like Browse's, but a class with nothing to show is left out — this grid is a
   preview of a few listings, not the whole catalogue, and a pill that empties it
   would look broken. New classes appear here on their own as listings arrive. */
$presentClasses = [];
foreach ($ventures as $v) {
    // asset_class holds a comma-separated list since migration step 50, so a
    // listing counts toward every class it claims, not just the first.
    foreach (vh_parse_asset_classes($v['asset_class'] ?? '') as $c) {
        $presentClasses[$c] = true;
    }
}
$assetClassChips = array_filter(
    $VH_ASSET_CLASSES,
    function ($slug) use ($presentClasses) { return isset($presentClasses[$slug]); },
    ARRAY_FILTER_USE_KEY
);
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="icon" type="image/svg+xml" href="assets/img/logo-mark.svg">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ventures Harbor – Co-Own. Build. Scale. Fractional Ownership in High-Yield Assets.</title>
  <meta name="description" content="Ventures Harbor is the marketplace for fractional ownership in Real-World Assets. Co-own high-yield Assets alongside trusted partners, from ₹50K.">
  <meta name="keywords" content="Asset, startup, investment, fractional business ownership, India, pool capital">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="assets/css/vh.css?v=41">

  <link rel="stylesheet" href="assets/css/venture-card.css?v=45">
  <link rel="stylesheet" href="assets/css/tilt.css?v=32">
  <link rel="stylesheet" href="assets/css/border-glow.css?v=32">
  <link rel="stylesheet" href="assets/css/vh-nav.css?v=3">
</head>
<body>

<div class="toast-container" id="toastContainer"></div>

<!-- film grain -->
<div style="position:fixed;inset:0;z-index:80;pointer-events:none;opacity:.05;background-image:url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='160' height='160' filter='url(%23n)' opacity='0.7'/%3E%3C/svg%3E&quot;)"></div>

<?php
$navActive = '';
include __DIR__ . '/partials/header.php';
?>

<!-- ===== HERO ===== -->
<header id="top" style="position:relative;overflow:hidden;background:#000">
  <div style="position:absolute;top:-220px;left:-220px;width:560px;height:560px;border-radius:999px;border:1px solid rgba(255,255,255,.06)"></div>
  <div style="position:absolute;top:-160px;left:-160px;width:440px;height:440px;border-radius:999px;border:1px solid rgba(255,255,255,.05)"></div>
  <div style="position:absolute;inset:0;overflow:hidden" aria-hidden="true">
    <img id="vh-hero-img" src="assets/img/vh-hero-lighthouse.jpg" alt="" loading="eager" fetchpriority="high" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center 45%;opacity:.95">
    <div id="vh-hero-scrim-x" style="position:absolute;inset:0;background:radial-gradient(120% 82% at 50% 40%,rgba(3,9,17,.5) 0%,rgba(3,9,17,.34) 45%,rgba(3,9,17,.12) 74%,rgba(3,9,17,0) 100%)"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.6) 0%,rgba(0,0,0,.08) 22%,rgba(0,0,0,.08) 64%,rgba(0,0,0,.75) 92%,#000 100%)"></div>
    <div id="vh-hero-dots" style="position:absolute;inset:0"></div>
  </div>

  <div style="position:relative;z-index:1;max-width:1240px;margin:0 auto;padding:clamp(140px,17vh,170px) clamp(20px,4vw,48px) clamp(56px,6vw,88px);text-align:center">
    <?php /* The client's own words, 8 Sep 2026, capitalisation included — he asks that
             his caps be kept ("please uppercase use kar liya karo when you write any
             line"), so do not "correct" this to sentence case. */ ?>
    <div data-anim="fade" style="font-size:clamp(16px,1.6vw,21px);font-weight:700;color:rgba(255,255,255,.88)">Anything That Can Create Financial Value Tomorrow Can Be Listed Here Today</div>

    <h1 class="vh-hero-title" style="margin:16px 0 0;color:#fff">
      <span style="display:block;overflow:hidden"><span data-hero-line style="display:block">Co-Own. Build. Scale.</span></span>
      <span style="display:block;overflow:hidden"><span data-hero-line style="display:block;color:#F5C518">Fractional Ownership <span style="color:#fff">in</span></span></span>
      <span style="display:block;overflow:hidden"><span data-hero-line style="display:block;color:#3B82F6">Valuable Real-World Opportunities.</span></span>
    </h1>
    <!-- <p data-anim="fade" style="margin:26px auto 0;max-width:630px;font-size:16.5px;line-height:1.75;color:rgba(255,255,255,.75);text-wrap:pretty">The Premier Marketplace for Accessing <span style="color:#3B82F6">Real-World Assets</span> and <span style="color:#3B82F6">High-Yield Opportunities</span> through Fractional Ownership.</p> -->
    <div data-anim="fade" style="margin-top:36px;display:flex;flex-wrap:wrap;justify-content:center;gap:14px">
      <a href="#ventures" class="vh-btn-blue" style="display:inline-flex;align-items:center;gap:10px;padding:16px 28px;border-radius:14px;background:#2563EB;color:#fff;font-size:15.5px;font-weight:700;box-shadow:0 10px 30px rgba(37,99,235,.35);transition:transform .25s,box-shadow .25s"><span id="heroCtaLabel">Explore Marketplace</span>
        <svg data-arrow width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transition:transform .25s"><path d="M5 12h14M13 6l6 6-6 6"></path></svg></a>
      <a href="users/auth.php" class="vh-btn-ghost" style="display:inline-flex;align-items:center;padding:16px 28px;border-radius:14px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);color:#fff;font-size:15.5px;font-weight:700;transition:border-color .25s,background .25s,transform .25s">Get Started Free</a>
    </div>

    <div style="margin-top:56px;display:flex;flex-wrap:wrap;justify-content:center;align-items:flex-start;gap:clamp(22px,4vw,48px)">
      <div data-badge style="display:flex;flex-direction:column;align-items:center;gap:10px;width:120px">
        <div style="width:46px;height:46px;border-radius:13px;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.04);display:grid;place-items:center;color:rgba(255,255,255,.9)"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"></path></svg></div>
        <div style="font-size:12.5px;line-height:1.45;font-weight:600;color:rgba(255,255,255,.78);text-align:center">Real-World Assets</div>
      </div>
      <div data-badge style="display:flex;flex-direction:column;align-items:center;gap:10px;width:120px">
        <div style="width:46px;height:46px;border-radius:13px;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.04);display:grid;place-items:center;color:rgba(255,255,255,.9)"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path></svg></div>
        <div style="font-size:12.5px;line-height:1.45;font-weight:600;color:rgba(255,255,255,.78);text-align:center">Transparent &amp; Liquid</div>
      </div>
      <div data-badge style="display:flex;flex-direction:column;align-items:center;gap:10px;width:120px">
        <div style="width:46px;height:46px;border-radius:13px;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.04);display:grid;place-items:center;color:rgba(255,255,255,.9)"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"></path><path d="m7 14 4-4 4 4 5-6"></path></svg></div>
        <div style="font-size:12.5px;line-height:1.45;font-weight:600;color:rgba(255,255,255,.78);text-align:center">Vetted High-Yield Opportunities</div>
      </div>
      <div data-badge style="display:flex;flex-direction:column;align-items:center;gap:10px;width:120px">
        <div style="width:46px;height:46px;border-radius:13px;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.04);display:grid;place-items:center;color:rgba(255,255,255,.9)"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg></div>
        <div style="font-size:12.5px;line-height:1.45;font-weight:600;color:rgba(255,255,255,.78);text-align:center">Co-Own &amp; Scale</div>
      </div>
    </div>
  </div>

  <div style="position:relative;z-index:1;border-top:1px solid rgba(255,255,255,.08)">
    <div style="max-width:1360px;margin:0 auto;padding:clamp(36px,4vw,56px) clamp(20px,4vw,48px);display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:32px">
      <?php foreach ($stats as $s): ?>
      <div style="text-align:center">
        <div style="font-size:clamp(40px,4vw,54px);font-weight:800;letter-spacing:-.03em;color:#fff"><span data-count="<?php echo htmlspecialchars((string)$s['value']); ?>"><?php echo htmlspecialchars((string)$s['value']); ?></span><span style="color:#F5C518"><?php echo htmlspecialchars($s['suffix']); ?></span></div>
        <div style="margin-top:4px;font-size:12.5px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:rgba(255,255,255,.45)"><?php echo htmlspecialchars($s['label']); ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</header>

<!-- ===== VENTURES ===== -->
<section id="ventures" style="position:relative;overflow:hidden;background:#fff">
  <div style="position:absolute;top:-180px;right:-180px;width:480px;height:480px;border-radius:999px;background:radial-gradient(circle,rgba(37,99,235,.07),transparent 70%)"></div>
  <div style="max-width:1360px;margin:0 auto;padding:clamp(72px,9vw,130px) clamp(20px,4vw,48px)">
    <div style="max-width:720px">
      <div data-anim="fade" style="font-size:12.5px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:#2563EB">Live on the platform</div>
      <h2 data-anim="fade" style="margin:14px 0 0;font-size:clamp(40px,4.6vw,64px);line-height:1.04;letter-spacing:-.03em;font-weight:800;color:#081421">Explore Real-World Assets</h2>
      <p data-anim="fade" style="margin:18px 0 0;font-size:16.5px;line-height:1.65;color:#5A6B85">Become a part of Valuable Real-World Opportunities through fractional ownership.</p>
    </div>

    <div data-anim="fade" style="margin-top:40px;display:flex;flex-wrap:wrap;gap:10px">
      <button type="button" data-filter="all" class="vh-filter" style="cursor:pointer;font-family:inherit;padding:11px 22px;border-radius:999px;font-size:14px;font-weight:700;border:1px solid #2563EB;background:#2563EB;color:#fff;transition:all .25s">All Assets</button>
      <?php foreach ($assetClassChips as $slug => $label): ?>
      <button type="button" data-filter="<?php echo htmlspecialchars($slug); ?>" class="vh-filter" style="cursor:pointer;font-family:inherit;padding:11px 22px;border-radius:999px;font-size:14px;font-weight:700;border:1px solid #DCE3EC;background:#fff;color:#33415C;transition:all .25s"><?php echo htmlspecialchars($label); ?></button>
      <?php endforeach; ?>
    </div>

    <div style="margin-top:36px;display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,340px),1fr));gap:26px">
      <?php foreach ($ventures as $v):
        $pct    = (int)$v['progress_percent'];

        $accent = '#16a34a';

        $cover  = $v['logo_url'] ?: $v['cover_image'];

        $isSample = !empty($v['is_showcase']);

        $isFull      = (int)$v['target_capital'] > 0 && (int)$v['raised_capital'] >= (int)$v['target_capital'];
        $takesActive = in_array($v['partner_types'] ?? 'both', ['active', 'both'], true);

        $buckets      = $v['capital_buckets'] ?? null;
        $hasCap       = !empty($buckets['has_cap']);
        $silentCapped = $hasCap && !empty($buckets['silent_full']) && !$isFull;
        // Full, but still inside its listing period — out of room, not closed.
        // vh_waitlist_is_open() is the same rule the API and VH.card.waitlistOpen()
        // answer to; keep all three in step.
        $waitlistOpen = vh_waitlist_is_open($v);

        $saved  = isset($wishlisted[(int)$v['id']]);
        // null when the visitor really can join this one.
        $viewerState         = $viewerStates[(int)$v['id']]['state'] ?? null;
        $viewerApplicationId = $viewerStates[(int)$v['id']]['application_id'] ?? 0;
      ?>

      <article data-anim="card" class="vh-card vh-vcard<?php echo $isSample ? ' vh-vcard--sample' : ''; ?>" data-venture-cat="<?php echo htmlspecialchars((string)($v['asset_class'] ?? '')); ?>" id="vc-<?php echo (int)$v['id']; ?>" style="--vc-accent:<?php echo $accent; ?>">
        <?php // Gold ribbon across the card's top edge — VH.card.showcaseRibbonHTML() is the JS twin. ?>
        <?php if ($isSample): ?>
        <div class="vc-sample-ribbon" title="An example listing published by Ventures Harbor — not a Real Asset">Sample listing &middot; example only</div>
        <?php endif; ?>
        <div class="vc-header">
          <div class="vc-cover">
            <?php if (!empty($cover)): ?>
              <img src="<?php echo htmlspecialchars($cover); ?>" alt="" loading="lazy"
                   onerror="this.parentNode.innerHTML='<span><?php echo htmlspecialchars(ventureInitial($v['title']), ENT_QUOTES); ?></span>'">
            <?php else: ?>
              <span><?php echo htmlspecialchars(ventureInitial($v['title'])); ?></span>
            <?php endif; ?>
          </div>
          <div class="vc-title-block">
            <h3 class="vc-title"><?php echo htmlspecialchars($v['title']); ?></h3>
            <div class="vc-byline"><?php echo htmlspecialchars($v['founder_name']); ?><span class="vc-sep">|</span><?php echo htmlspecialchars($v['industry']); ?></div>
            <div class="vc-chips">
              <?php 

                    ?>
              <?php 

                    ?>
              <?php
                $ptypeChips = [
                    'both'   => ['Partner', 'This Asset is open to partners.'],
                    'active' => ['Partner', 'This Asset is open to partners.'],
                    'silent' => ['Partner', 'This Asset is open to partners.'],
                ];
                /* The PHP twin of VH.card.partnerTypeChipHTML() in shared.js — read the
                   reasoning there. A FULL Asset reverts to stating what it IS (so a
                   `both` listing says "Active + Silent" again); only a silent-capped
                   one that is still raising flips to Active Only. Change both together. */
                $ptype = $v['partner_types'] ?? 'both';
                if (!$isFull && $silentCapped && $takesActive) { $ptype = 'active'; }
              ?>
              <?php if (isset($ptypeChips[$ptype])): ?>
              <?php $ptypeTitle = $isFull
                      ? 'This Asset is fully funded, so it is not taking new partners right now. '
                        . 'Join the waitlist to be told the moment a seat opens.'
                      : $ptypeChips[$ptype][1]; ?>
              <span class="vc-chip vc-chip--ptype vc-chip--ptype-<?php echo htmlspecialchars($ptype); ?>"
                    title="<?php echo htmlspecialchars($ptypeTitle); ?>"><?php echo htmlspecialchars($ptypeChips[$ptype][0]); ?></span>
              <?php endif; ?>
              <?php if (!empty($v['location'])): ?>
              <span class="vc-loc"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg><?php echo htmlspecialchars($v['location']); ?></span>
              <?php endif; ?>
              <?php /* No category chip — see VH.card.chipsHTML() in shared.js. */ ?>
            </div>
          </div>
        </div>

        <div class="vc-stats">
          <div class="vc-stat"><span class="vc-stat-value"><?php echo compactINR($v['target_capital']); ?></span><span class="vc-stat-label">Target</span></div>
          <div class="vc-stat vc-stat--center"><span class="vc-stat-value"><?php echo compactINR($v['min_investment']); ?></span><span class="vc-stat-label">Min. Ticket</span></div>
          <div class="vc-stat vc-stat--right"><span class="vc-stat-value"><?php echo compactINR($v['founder_contribution']); ?></span><span class="vc-stat-label">Founder Invested</span></div>
          <div class="vc-stat"><span class="vc-stat-value"><?php echo compactINR($v['raised_capital']); ?></span><span class="vc-stat-label">Total Raised</span></div>
          <?php /* PHP twin of VH.card.remaining() in shared.js — floored at zero so an
                   over-subscribed venture reads ₹0 instead of a negative figure. */ ?>
          <div class="vc-stat vc-stat--remaining"><span class="vc-stat-value"><?php echo compactINR(max(0, (float)$v['target_capital'] - (float)$v['raised_capital'])); ?></span><span class="vc-stat-label">Left to Raise</span></div>
          <div class="vc-stat vc-stat--funded"><span class="vc-stat-value"><?php echo $pct; ?>%</span><span class="vc-stat-label">Funded</span></div>
          <div class="vc-progress"><span class="vc-progress-fill" data-bar style="width:<?php echo $pct; ?>%"></span></div>
        </div>

        <?php 

              ?>
        <?php

          $sampleFigures = $isSample
              && ($v['showcase_raised_silent'] !== null || $v['showcase_raised_active'] !== null);
          $showCapacity = $hasCap || $sampleFigures;
          $silentTotal = $hasCap ? (int)$buckets['silent_cap']     : (int)$buckets['partner_pool'];
          $activeTotal = $hasCap ? (int)$buckets['active_reserve'] : (int)$buckets['partner_pool'];

          $vTypes = (string)($v['partner_types'] ?? 'both');
          $capRows = [];
          if ($vTypes === 'silent' || $vTypes === 'both') {
              $capRows[] = ['Silent', 'silent', (int)$buckets['raised_silent'], $silentTotal, (int)$buckets['silent_remaining'] <= 0];
          }
          if ($vTypes === 'active' || $vTypes === 'both') {
              $capRows[] = ['Active', 'active', (int)$buckets['raised_active'], $activeTotal, (int)$buckets['remaining'] <= 0];
          }
        ?>
        <?php if ($showCapacity && $capRows): ?>
        <div class="vc-capacity" title="<?php echo $isSample ? 'Example figures — a sample listing has no real partners.' : 'This founder reserved part of the target for active partners.'; ?>">
          <?php foreach ($capRows as [$label, $cls, $filled, $total, $full]):
            $barPct = $total > 0 ? min(100, (int)round(($filled / $total) * 100)) : 0;
          ?>
          <div class="vc-cap-row<?php echo $full ? ' vc-cap-row--full' : ''; ?>">
            <span class="vc-cap-label"><span class="vc-cap-dot vc-cap-dot--<?php echo $cls; ?>"></span><?php echo $label; ?></span>
            <span class="vc-cap-figs"><?php echo compactINR($filled); ?> / <?php echo compactINR($total); ?></span>
            <span class="vc-cap-state"><?php echo $full ? 'FULL' : 'OPEN'; ?></span>
            <span class="vc-cap-track"><span class="vc-cap-fill vc-cap-fill--<?php echo $cls; ?>" style="width:<?php echo $barPct; ?>%"></span></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php // VH.card.silentFullNoteHTML() is the JS twin. ?>
        <?php if ($silentCapped && !$isSample): ?>
        <div class="vc-cap-note">
          <strong>Silent Partnership Full</strong>
          Remaining investment: <?php echo compactINR((int)$buckets['remaining']); ?>.
          <?php echo $takesActive ? 'Only active partners can join this Asset.' : ''; ?>
        </div>
        <?php endif; ?>

        <div class="vc-meta">
          <span class="vc-meta-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path></svg><?php echo (int)$v['members_count']; ?> Members</span>
          <span class="vc-meta-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg><?php echo $isSample ? 'Example listing' : ((int)$v['days_left'] . ' Days Left'); ?></span>
          <?php

            $roiMin = $v['expected_roi_min'] ?? null;
            $roiMax = $v['expected_roi_max'] ?? null;
            if ($roiMin !== null && $roiMax !== null):
              $fmtRoi = function ($x) { $x = (float)$x; return fmod($x, 1) == 0 ? number_format($x, 0) : number_format($x, 1); };
              $roiRange = ((float)$roiMin === (float)$roiMax)
                  ? $fmtRoi($roiMin) . '%'
                  : $fmtRoi($roiMin) . '–' . $fmtRoi($roiMax) . '%';
          ?>
          <span class="vc-meta-item" title="Expected return per year, as stated by the founder"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg><?php echo htmlspecialchars($roiRange); ?> ROI</span>
          <?php endif; ?>
        </div>

        <div class="vc-actions" data-tilt-actions>
          <?php if ($isSample): ?>
            <?php 
                  ?>
            <a href="pages/join-venture.php?id=<?php echo (int)$v['id']; ?>" class="btn-join btn-join--sample" title="Walk through the join flow on this example — nothing is charged">Preview Join Flow</a>
          <?php elseif ($viewerState === 'selected'): ?>
            <?php // The founder picked them; the commitment fee is what's left. ?>
            <a href="pages/join-venture.php?id=<?php echo (int)$v['id']; ?>&amp;application_id=<?php echo (int)$viewerApplicationId; ?>" class="btn-join btn-join--pay">Pay &amp; Confirm</a>
          <?php elseif ($viewerState === 'waitlisted'): ?>
            <?php // Already waiting — send them to the page where the seat will appear. ?>
            <a href="admin/waitlist.php" class="btn-join btn-join--onwaitlist" title="You are on this Asset's waitlist — open your Waitlist page">On Waitlist</a>
          <?php elseif ($viewerState !== null): ?>
            <span class="btn-join btn-join--state btn-join--<?php echo htmlspecialchars($viewerState); ?>"><?php echo htmlspecialchars($viewerStateLabels[$viewerState] ?? 'Unavailable'); ?></span>
          <?php elseif ($isFull && $waitlistOpen): ?>
            <?php /* Out of room but the listing is still running — a partner may yet
                      leave, so offer the waitlist instead of a dead "Fully Funded". */ ?>
            <a href="admin/waitlist.php?join=<?php echo (int)$v['id']; ?>" class="btn-join btn-join--waitlist" title="This Asset is fully funded. Join the waitlist and you are notified the moment a partner exits.">Join Waitlist</a>
          <?php elseif ($isFull): ?>
            <?php /* No active-partner branch here on purpose: a funded Asset takes no more
                      applications, and vh_venture_openness() refuses them server-side.
                      Twin of VH.card.primaryActionHTML() — change both together. */ ?>
            <span class="btn-join btn-join--state btn-join--full" title="This Asset has raised its full target">Fully Funded</span>
          <?php elseif ($silentCapped && $takesActive): ?>
            <?php 
                  ?>
            <a href="pages/join-venture.php?id=<?php echo (int)$v['id']; ?>&amp;role=active" class="btn-join btn-join--active"
               title="Silent partnership is full. The remaining <?php echo compactINR((int)$buckets['remaining']); ?> is reserved for active partners.">Apply as Active Partner</a>
          <?php elseif ($silentCapped): ?>
            <span class="btn-join btn-join--state btn-join--full" title="This Asset has taken its full silent partnership">Silent Partnership Full</span>
          <?php else: ?>
            <a href="pages/join-venture.php?id=<?php echo (int)$v['id']; ?>" class="btn-join">Co-Own</a>
          <?php endif; ?>
          <a href="pages/venture-detail.php?id=<?php echo (int)$v['id']; ?>" class="btn-details">View Details</a>
          <button type="button" class="vc-wish" data-wish data-venture-id="<?php echo (int)$v['id']; ?>"
                  aria-pressed="<?php echo $saved ? 'true' : 'false'; ?>"
                  aria-label="<?php echo $saved ? 'Remove from your list' : 'Save to your list'; ?>"
                  title="<?php echo $saved ? 'Saved — click to remove' : 'Save to your list'; ?>">
            <svg width="17" height="17" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
          </button>
        </div>
      </article>
      <?php endforeach; ?>
    </div>

    <div data-anim="fade" style="margin-top:48px;text-align:center">
      <a href="pages/browse.php" class="vh-btn-outline-blue" style="display:inline-flex;align-items:center;gap:10px;padding:15px 32px;border-radius:999px;border:1.5px solid #2563EB;color:#2563EB;font-size:15px;font-weight:700;transition:background .25s,color .25s">Explore All Assets
        <svg data-arrow width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transition:transform .25s"><path d="M5 12h14M13 6l6 6-6 6"></path></svg></a>
    </div>
  </div>
</section>

<!-- ===== HOW IT WORKS ===== -->
<section id="how" class="vh-how" style="position:relative;overflow:hidden">
  <div style="position:absolute;bottom:-260px;right:-200px;width:620px;height:620px;border-radius:999px;border:1px solid rgba(255,255,255,.05)"></div>
  <div style="max-width:1360px;margin:0 auto;padding:clamp(72px,9vw,130px) clamp(20px,4vw,48px)">
    <div style="text-align:center;max-width:640px;margin:0 auto">
      <div data-anim="fade" style="display:inline-block;padding:8px 18px;border-radius:999px;border:1px solid rgba(245,197,24,.3);color:#F5C518;font-size:12px;font-weight:800;letter-spacing:.18em;text-transform:uppercase">Simple Process</div>
      <h2 data-anim="fade" style="margin:20px 0 0;font-size:clamp(40px,4.6vw,64px);line-height:1.04;letter-spacing:-.03em;font-weight:800;color:#fff">How It Works</h2>
      <p data-anim="fade" style="margin:16px 0 0;font-size:16.5px;line-height:1.65;color:rgba(255,255,255,.55)">Four simple steps to start your entrepreneurial journey.</p>
    </div>
    <div style="position:relative;margin-top:clamp(48px,6vw,80px)">
      <div style="position:absolute;top:88px;left:4%;right:4%;height:1px;background:linear-gradient(90deg,transparent,rgba(245,197,24,.4) 20%,rgba(245,197,24,.4) 80%,transparent)"></div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr));gap:26px">

        <div data-anim="card" data-border-glow class="vh-step">
          <div style="font-size:64px;font-weight:800;letter-spacing:-.04em;line-height:1;color:rgba(255,255,255,.07)">01</div>
          <div class="vh-step-icon" style="background:#2563EB;color:#fff"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg></div>
          <h3 style="margin:20px 0 0;font-size:20px;font-weight:800;letter-spacing:-.01em;color:#fff">Discover Opportunities</h3>
          <p style="margin:10px 0 0;font-size:14.5px;line-height:1.65;color:rgba(255,255,255,.55);text-wrap:pretty">Explore ideas and opportunities across sectors that match your interest and expertise.</p>
        </div>

        <div data-anim="card" data-border-glow class="vh-step">
          <div style="font-size:64px;font-weight:800;letter-spacing:-.04em;line-height:1;color:rgba(255,255,255,.07)">02</div>
          <div class="vh-step-icon" style="background:#F5C518;color:#081421"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg></div>
          <h3 style="margin:20px 0 0;font-size:20px;font-weight:800;letter-spacing:-.01em;color:#fff">Invest &amp; Collaborate</h3>
          <p style="margin:10px 0 0;font-size:14.5px;line-height:1.65;color:rgba(255,255,255,.55);text-wrap:pretty">Pool capital and expertise. Pay a small 0.5% commitment fee to confirm your interest.</p>
        </div>

        <div data-anim="card" data-border-glow class="vh-step">
          <div style="font-size:64px;font-weight:800;letter-spacing:-.04em;line-height:1;color:rgba(255,255,255,.07)">03</div>
          <div class="vh-step-icon" style="background:#2563EB;color:#fff"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"></path><path d="m7 14 4-4 4 4 5-6"></path></svg></div>
          <h3 style="margin:20px 0 0;font-size:20px;font-weight:800;letter-spacing:-.01em;color:#fff">Build &amp; Scale</h3>
          <p style="margin:10px 0 0;font-size:14.5px;line-height:1.65;color:rgba(255,255,255,.55);text-wrap:pretty">Attend meetups, finalize legal agreements offline, and launch the Asset together.</p>
        </div>

        <div data-anim="card" data-border-glow class="vh-step">
          <div style="font-size:64px;font-weight:800;letter-spacing:-.04em;line-height:1;color:rgba(255,255,255,.07)">04</div>
          <div class="vh-step-icon" style="background:#F5C518;color:#081421"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6M18 9h1.5a2.5 2.5 0 0 0 0-5H18M4 22h16M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22M14 14.66V17c0 .55.47.98.97 1.21 1.18.54 2.03 2.03 2.03 3.79M18 2H6v7a6 6 0 0 0 12 0V2Z"></path></svg></div>
          <h3 style="margin:20px 0 0;font-size:20px;font-weight:800;letter-spacing:-.01em;color:#fff">Share Success</h3>
          <p style="margin:10px 0 0;font-size:14.5px;line-height:1.65;color:rgba(255,255,255,.55);text-wrap:pretty">Grow together — profits and equity shared among all contributors as agreed.</p>
        </div>

      </div>
    </div>
  </div>
</section>

<!-- ===== WHY ===== -->
<section id="why" style="position:relative;overflow:hidden;background:#fff">
  <div style="position:absolute;bottom:-200px;left:-200px;width:520px;height:520px;border-radius:999px;background:radial-gradient(circle,rgba(245,197,24,.08),transparent 70%)"></div>
  <div style="max-width:1360px;margin:0 auto;padding:clamp(72px,9vw,130px) clamp(20px,4vw,48px);display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,440px),1fr));gap:clamp(40px,5vw,80px);align-items:center">
    <div>
      <div data-anim="fade" style="display:inline-block;padding:8px 18px;border-radius:999px;background:#EFF4FF;color:#2563EB;font-size:12px;font-weight:800;letter-spacing:.18em;text-transform:uppercase">Why Ventures Harbor?</div>
      <h2 data-anim="fade" style="margin:22px 0 0;font-size:clamp(42px,4.8vw,68px);line-height:1.04;letter-spacing:-.03em;font-weight:800;color:#081421">The courage to act,<br><span style="color:#2563EB">not just dream.</span></h2>
      <p data-anim="fade" style="margin:24px 0 0;max-width:520px;font-size:16.5px;line-height:1.7;color:#5A6B85;text-wrap:pretty">Most entrepreneurial ideas die before they begin — not from lack of vision, but from lack of capital, networks, or confidence. Ventures Harbor changes that equation entirely.</p>
      <p data-anim="fade" style="margin:16px 0 0;max-width:520px;font-size:16.5px;line-height:1.7;color:#5A6B85;text-wrap:pretty">We connect ambitious people with verified Assets, enabling collaborative investment and execution so no idea has to die in the planning stage.</p>
      <a data-anim="fade" href="#ventures" class="vh-btn-dark" style="margin-top:32px;display:inline-flex;align-items:center;gap:10px;padding:15px 30px;border-radius:999px;background:#081421;color:#fff;font-size:15px;font-weight:700;transition:transform .25s,box-shadow .25s">Start Exploring
        <svg data-arrow width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transition:transform .25s"><path d="M5 12h14M13 6l6 6-6 6"></path></svg></a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));gap:20px">

      <div data-anim="card" data-border-glow class="vh-feature" style="padding:28px 24px;border-radius:22px;background:#F7F8FA;border:1px solid #EDF0F4;transition:transform .3s,box-shadow .3s,background .3s">
        <div style="width:48px;height:48px;border-radius:15px;background:#EFF4FF;color:#2563EB;display:grid;place-items:center"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path><path d="m9 12 2 2 4-4"></path></svg></div>
        <h3 style="margin:18px 0 0;font-size:16.5px;font-weight:800;letter-spacing:-.01em;color:#081421">Vetted &amp; Verified</h3>
        <p style="margin:8px 0 0;font-size:13.5px;line-height:1.6;color:#5A6B85;text-wrap:pretty">Every Asset goes through rigorous vetting before listing.</p>
      </div>

      <div data-anim="card" data-border-glow class="vh-feature" style="padding:28px 24px;border-radius:22px;background:#F7F8FA;border:1px solid #EDF0F4;transition:transform .3s,box-shadow .3s,background .3s">
        <div style="width:48px;height:48px;border-radius:15px;background:rgba(245,197,24,.16);color:#8A6D00;display:grid;place-items:center"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"></path></svg></div>
        <h3 style="margin:18px 0 0;font-size:16.5px;font-weight:800;letter-spacing:-.01em;color:#081421">Community-Driven</h3>
        <p style="margin:8px 0 0;font-size:13.5px;line-height:1.6;color:#5A6B85;text-wrap:pretty">Join a vibrant community of entrepreneurs, investors, and experts.</p>
      </div>

      <div data-anim="card" data-border-glow class="vh-feature" style="padding:28px 24px;border-radius:22px;background:#F7F8FA;border:1px solid #EDF0F4;transition:transform .3s,box-shadow .3s,background .3s">
        <div style="width:48px;height:48px;border-radius:15px;background:rgba(245,197,24,.16);color:#8A6D00;display:grid;place-items:center"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"></path><circle cx="12" cy="12" r="3"></circle></svg></div>
        <h3 style="margin:18px 0 0;font-size:16.5px;font-weight:800;letter-spacing:-.01em;color:#081421">Transparent Returns</h3>
        <p style="margin:8px 0 0;font-size:13.5px;line-height:1.6;color:#5A6B85;text-wrap:pretty">Full transparency on equity, profit-sharing, and investment terms.</p>
      </div>

      <div data-anim="card" data-border-glow class="vh-feature" style="padding:28px 24px;border-radius:22px;background:#F7F8FA;border:1px solid #EDF0F4;transition:transform .3s,box-shadow .3s,background .3s">
        <div style="width:48px;height:48px;border-radius:15px;background:#EFF4FF;color:#2563EB;display:grid;place-items:center"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"></circle><path d="M15.48 12.83 17 22l-5-3-5 3 1.52-9.17"></path></svg></div>
        <h3 style="margin:18px 0 0;font-size:16.5px;font-weight:800;letter-spacing:-.01em;color:#081421">Performance on Record</h3>
        <p style="margin:8px 0 0;font-size:13.5px;line-height:1.6;color:#5A6B85;text-wrap:pretty">Founders publish monthly financial reports — partners follow real numbers, not projections.</p>
      </div>

    </div>
  </div>
</section>

<!-- ===== STORIES ===== -->
<section id="stories" style="background:#F7F8FA">
  <div style="max-width:1360px;margin:0 auto;padding:clamp(72px,9vw,130px) clamp(20px,4vw,48px)">
    <div style="text-align:center;max-width:640px;margin:0 auto">
      <div data-anim="fade" style="display:inline-block;padding:8px 18px;border-radius:999px;background:rgba(245,197,24,.14);color:#8A6D00;font-size:12px;font-weight:800;letter-spacing:.18em;text-transform:uppercase">Real Stories</div>
      <h2 data-anim="fade" style="margin:20px 0 0;font-size:clamp(40px,4.6vw,64px);line-height:1.04;letter-spacing:-.03em;font-weight:800;color:#081421">Found each other here</h2>
      <p data-anim="fade" style="margin:16px 0 0;font-size:16.5px;line-height:1.65;color:#5A6B85">Entrepreneurs and partners who found each other on Ventures Harbor.</p>
    </div>
    <div style="margin-top:clamp(44px,5vw,64px);display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:26px">

      <figure data-anim="card" data-border-glow class="vh-quote" style="margin:0;display:flex;flex-direction:column;gap:24px;padding:38px 34px;border-radius:26px;background:#fff;border:1px solid #EDF0F4;box-shadow:0 2px 10px rgba(8,20,33,.03);transition:transform .3s,box-shadow .3s">
        <div style="font-size:64px;line-height:.6;font-weight:800;color:#F5C518;font-family:Georgia,serif">&ldquo;</div>
        <blockquote style="margin:0;font-size:16px;line-height:1.75;color:#33415C;text-wrap:pretty">I had a café concept but no one to run daily operations with. Within three weeks of listing on Ventures Harbor I found two partners who matched exactly what I needed.</blockquote>
        <figcaption style="margin-top:auto;display:flex;align-items:center;gap:14px">
          <div style="width:46px;height:46px;border-radius:999px;background:#2563EB;color:#fff;display:grid;place-items:center;font-size:14px;font-weight:800">PS</div>
          <div>
            <div style="font-size:15px;font-weight:800;color:#081421">Priya Singh</div>
            <div style="font-size:12.5px;font-weight:500;color:#7A8AA3">Founder, Café Startup · Pune</div>
          </div>
        </figcaption>
      </figure>

      <figure data-anim="card" data-border-glow class="vh-quote" style="margin:0;display:flex;flex-direction:column;gap:24px;padding:38px 34px;border-radius:26px;background:#fff;border:1px solid #EDF0F4;box-shadow:0 2px 10px rgba(8,20,33,.03);transition:transform .3s,box-shadow .3s">
        <div style="font-size:64px;line-height:.6;font-weight:800;color:#F5C518;font-family:Georgia,serif">&ldquo;</div>
        <blockquote style="margin:0;font-size:16px;line-height:1.75;color:#33415C;text-wrap:pretty">As a partner, the monthly financial reports and transparent commitment-fee structure gave me the confidence to invest without ever meeting the founder in person first.</blockquote>
        <figcaption style="margin-top:auto;display:flex;align-items:center;gap:14px">
          <div style="width:46px;height:46px;border-radius:999px;background:#081421;color:#fff;display:grid;place-items:center;font-size:14px;font-weight:800">RG</div>
          <div>
            <div style="font-size:15px;font-weight:800;color:#081421">Rohan Gupta</div>
            <div style="font-size:12.5px;font-weight:500;color:#7A8AA3">Partner · Bangalore</div>
          </div>
        </figcaption>
      </figure>

      <figure data-anim="card" data-border-glow class="vh-quote" style="margin:0;display:flex;flex-direction:column;gap:24px;padding:38px 34px;border-radius:26px;background:#fff;border:1px solid #EDF0F4;box-shadow:0 2px 10px rgba(8,20,33,.03);transition:transform .3s,box-shadow .3s">
        <div style="font-size:64px;line-height:.6;font-weight:800;color:#F5C518;font-family:Georgia,serif">&ldquo;</div>
        <blockquote style="margin:0;font-size:16px;line-height:1.75;color:#33415C;text-wrap:pretty">The 24-hour quit window after our first meetup removed all the awkwardness of &ldquo;what if it doesn&rsquo;t work out&rdquo; conversations. It just works, quietly, in the background.</blockquote>
        <figcaption style="margin-top:auto;display:flex;align-items:center;gap:14px">
          <div style="width:46px;height:46px;border-radius:999px;background:#2563EB;color:#fff;display:grid;place-items:center;font-size:14px;font-weight:800">AM</div>
          <div>
            <div style="font-size:15px;font-weight:800;color:#081421">Aman Mehra</div>
            <div style="font-size:12.5px;font-weight:500;color:#7A8AA3">Founder, Cloud Kitchen · Delhi</div>
          </div>
        </figcaption>
      </figure>

    </div>
  </div>
</section>

<!-- ===== CTA ===== -->

<section id="cta" data-splash-cursor data-splash-color="#F5C518" style="position:relative;overflow:hidden;background:#000">
  <div style="position:absolute;top:-140px;right:8%;width:300px;height:300px;border-radius:999px;background:radial-gradient(circle,rgba(245,197,24,.16),transparent 70%)"></div>
  <div style="max-width:920px;margin:0 auto;padding:clamp(90px,11vw,160px) clamp(20px,4vw,48px);text-align:center;position:relative;z-index:1">
    <div data-anim="fade" style="display:inline-block;padding:8px 18px;border-radius:999px;border:1px solid rgba(245,197,24,.3);color:#F5C518;font-size:12px;font-weight:800;letter-spacing:.18em;text-transform:uppercase">Ready to Start?</div>
    <h2 data-anim="fade" style="margin:26px 0 0;font-size:clamp(44px,6vw,84px);line-height:1.04;letter-spacing:-.035em;font-weight:800;color:#fff">Join a Growing Community of <span style="color:#F5C518">Entrepreneurs</span> Building Together</h2>
    <p data-anim="fade" style="margin:24px 0 0;font-size:17px;line-height:1.65;color:rgba(255,255,255,.6)">Create your free account and start exploring Valuable Real-World Opportunities today.</p>
    <div data-anim="fade" style="margin-top:44px;display:flex;flex-wrap:wrap;justify-content:center;gap:16px">
      <a href="users/auth.php" class="vh-btn-blue-lg" style="display:inline-flex;align-items:center;gap:10px;padding:18px 38px;border-radius:999px;background:#2563EB;color:#fff;font-size:16px;font-weight:800;box-shadow:0 12px 34px rgba(37,99,235,.4);transition:transform .25s,box-shadow .25s">Create Free Account
        <svg data-arrow width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transition:transform .25s"><path d="M5 12h14M13 6l6 6-6 6"></path></svg></a>
      <a href="pages/browse.php" class="vh-btn-ghost-lg" style="display:inline-flex;align-items:center;padding:18px 38px;border-radius:999px;border:1px solid rgba(255,255,255,.22);color:#fff;font-size:16px;font-weight:700;transition:border-color .25s,background .25s,transform .25s">Browse Assets</a>
    </div>
  </div>
</section>

<!-- ===== FOOTER ===== -->
<footer id="footer" style="background:#0D0D0D;border-top:1px solid rgba(255,255,255,.06)">
  <div style="max-width:1360px;margin:0 auto;padding:clamp(56px,7vw,90px) clamp(20px,4vw,48px)">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));gap:40px">
      <div style="grid-column:span 1;max-width:320px">

        <div style="display:flex;align-items:center;gap:10px;font-size:16px;font-weight:800;letter-spacing:.03em;color:#fff"><img src="assets/img/logo-mark.svg" alt="" width="30" height="30" style="display:block;flex-shrink:0"><span style="white-space:nowrap">VENTURES HARBOR</span></div>
        <div style="margin-top:14px;font-size:14.5px;font-weight:700;color:#F5C518">Co-Own. Build. Scale.</div>
        <p style="margin:12px 0 0;font-size:13.5px;line-height:1.65;color:rgba(255,255,255,.5);text-wrap:pretty">Fractional ownership platform connecting capital, ideas and talent to Valuable Real-World Opportunities across India.</p>
      </div>

      <div>
        <div style="font-size:12px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:rgba(255,255,255,.4)">Platform</div>
        <div style="margin-top:18px;display:flex;flex-direction:column;gap:12px">
          <a href="pages/browse.php" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">Browse Assets</a>
          <a href="pages/create-venture.php" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">List Your Venture</a>
          <a href="admin/dashboard.php" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">Dashboard</a>
          <a href="admin/meetup.php" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">Meetups</a>
        </div>
      </div>

      <div>
        <div style="font-size:12px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:rgba(255,255,255,.4)">Account</div>
        <div style="margin-top:18px;display:flex;flex-direction:column;gap:12px">
          <a href="users/auth.php" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">Sign In / Sign Up</a>
          <a href="users/profile.php" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">Profile</a>
          <a href="users/wallet.php" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">Wallet</a>
        </div>
      </div>

      <div>
        <div style="font-size:12px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:rgba(255,255,255,.4)">Support</div>
        <div style="margin-top:18px;display:flex;flex-direction:column;gap:12px">
          <a href="pages/about.php" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">About Us</a>
          <a href="pages/contact.php" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">Contact Us</a>
          <a href="pages/about.php#privacy" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">Privacy Policy</a>
          <a href="pages/about.php#terms" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">Terms of Service</a>
        </div>
      </div>

    </div>

    <div style="margin-top:56px;padding-top:26px;border-top:1px solid rgba(255,255,255,.08);display:flex;flex-wrap:wrap;justify-content:space-between;gap:16px;align-items:center">
      <div style="font-size:13px;color:rgba(255,255,255,.45)">© <?php echo date('Y'); ?> Ventures Harbor. All rights reserved.</div>
      <div style="display:flex;gap:12px">
        <a href="#footer" class="vh-social" aria-label="Twitter" style="width:36px;height:36px;border-radius:999px;border:1px solid rgba(255,255,255,.12);display:grid;place-items:center;color:rgba(255,255,255,.6);transition:border-color .25s,color .25s"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z"></path></svg></a>
        <a href="#footer" class="vh-social" aria-label="LinkedIn" style="width:36px;height:36px;border-radius:999px;border:1px solid rgba(255,255,255,.12);display:grid;place-items:center;color:rgba(255,255,255,.6);transition:border-color .25s,color .25s"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect width="4" height="12" x="2" y="9"></rect><circle cx="4" cy="4" r="2"></circle></svg></a>
        <a href="#footer" class="vh-social" aria-label="Instagram" style="width:36px;height:36px;border-radius:999px;border:1px solid rgba(255,255,255,.12);display:grid;place-items:center;color:rgba(255,255,255,.6);transition:border-color .25s,color .25s"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="20" x="2" y="2" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><path d="M17.5 6.5h.01"></path></svg></a>
      </div>
    </div>
  </div>
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
<script src="assets/js/shared.js?v=70"></script>
<script src="assets/js/vh-nav.js?v=1"></script>
<script src="assets/js/home.js?v=45"></script>
<script src="assets/js/tilt.js?v=31"></script>
<script src="assets/js/splash-cursor.js?v=31"></script>
<script src="assets/js/border-glow.js?v=31"></script>
</body>
</html>
