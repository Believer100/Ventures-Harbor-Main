<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/site-stats.php';

$stats = vh_site_stats($mysqli);
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logo-mark.svg">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>About Us – Ventures Harbor</title>
  <meta name="description" content="Learn about Ventures Harbor's mission to make fractional business ownership accessible to everyone.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="../assets/css/vh.css?v=41">
  <link rel="stylesheet" href="../assets/css/border-glow.css?v=32">
  <link rel="stylesheet" href="../assets/css/gradient-waves.css?v=32">
  <link rel="stylesheet" href="../assets/css/vh-nav.css?v=3">
</head>
<body>

<div class="toast-container" id="toastContainer"></div>

<!-- film grain — same overlay as the homepage -->
<div style="position:fixed;inset:0;z-index:80;pointer-events:none;opacity:.05;background-image:url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='160' height='160' filter='url(%23n)' opacity='0.7'/%3E%3C/svg%3E&quot;)"></div>

<!-- ===== NAV ===== -->

<?php
$navActive = 'about';
include __DIR__ . '/../partials/header.php';
?>

<!-- ===== HERO ===== -->
<header id="top" style="position:relative;overflow:hidden;background:#000">

  <div class="gradient-waves-container" data-gradient-waves aria-hidden="true"
       data-gw-horizon="#081421" data-gw-wave="#2563EB" data-gw-crest="#F5C518"
       data-gw-speed="0.32" data-gw-amplitude="2.5" data-gw-wave-scale="0.6"
       data-gw-tilt="1.11" data-gw-height="0" data-gw-fog-depth="30"
       data-gw-detail="medium" data-gw-brightness="1.0" data-gw-parallax="0.5"></div>

  <div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.62) 0%,rgba(0,0,0,.08) 45%,rgba(0,0,0,.55) 100%);pointer-events:none" aria-hidden="true"></div>

  <div style="position:relative;z-index:1;max-width:900px;margin:0 auto;padding:clamp(108px,13vh,146px) clamp(20px,4vw,48px) clamp(40px,4.5vw,60px);text-align:center">
    <div data-anim="fade" style="display:inline-block;padding:7px 16px;border-radius:999px;border:1px solid rgba(245,197,24,.3);color:#F5C518;font-size:11.5px;font-weight:800;letter-spacing:.18em;text-transform:uppercase">Our Story</div>
    <h1 style="margin:18px 0 0;font-size:clamp(34px,4.8vw,62px);line-height:1.06;letter-spacing:-.03em;font-weight:800;color:#fff">
      <span style="display:block;overflow:hidden"><span data-hero-line style="display:block">About</span></span>
      <span style="display:block;overflow:hidden"><span data-hero-line style="display:block;color:#3B82F6">Ventures Harbor</span></span>
    </h1>
    <p data-anim="fade" style="margin:18px auto 0;max-width:600px;font-size:15.5px;line-height:1.7;color:rgba(255,255,255,.78);text-wrap:pretty">We believe great businesses shouldn't die in someone's notes app for lack of capital, skills, or courage to start alone.</p>
  </div>

  <!-- ===== LIVE STATS ===== -->

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

<!-- ===== MISSION ===== -->

<section id="mission" style="position:relative;overflow:hidden;background:#fff">
  <div style="position:absolute;bottom:-200px;left:-200px;width:520px;height:520px;border-radius:999px;background:radial-gradient(circle,rgba(245,197,24,.08),transparent 70%)"></div>
  <div style="max-width:1360px;margin:0 auto;padding:clamp(72px,9vw,130px) clamp(20px,4vw,48px);display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,440px),1fr));gap:clamp(40px,5vw,80px);align-items:center">
    <div>
      <div data-anim="fade" style="display:inline-block;padding:8px 18px;border-radius:999px;background:#EFF4FF;color:#2563EB;font-size:12px;font-weight:800;letter-spacing:.18em;text-transform:uppercase">Our Mission</div>
      <h2 data-anim="fade" style="margin:22px 0 0;font-size:clamp(42px,4.8vw,68px);line-height:1.04;letter-spacing:-.03em;font-weight:800;color:#081421">Fractional business ownership,<br><span style="color:#2563EB">built for India.</span></h2>
      <p data-anim="fade" style="margin:24px 0 0;max-width:520px;font-size:16.5px;line-height:1.7;color:#5A6B85;text-wrap:pretty">Ventures Harbor was created around a simple observation: most entrepreneurial ideas never launch — not because they're bad ideas, but because one person rarely has all the capital, operational bandwidth, and specialised skills an Asset needs on day one.</p>
      <p data-anim="fade" style="margin:16px 0 0;max-width:520px;font-size:16.5px;line-height:1.7;color:#5A6B85;text-wrap:pretty">So we built a platform where a founder can post a real Asset — a franchise, a café, a service business, a tech idea — and invite others to join as <strong style="color:#33415C">Partners</strong> through one simple system. A small, clearly-disclosed commitment fee keeps intent honest, and every Asset gets a real-world meetup before anyone is locked in.</p>
      <a data-anim="fade" href="browse.php" class="vh-btn-dark" style="margin-top:32px;display:inline-flex;align-items:center;gap:10px;padding:15px 30px;border-radius:999px;background:#081421;color:#fff;font-size:15px;font-weight:700;transition:transform .25s,box-shadow .25s">Explore Marketplace
        <svg data-arrow width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transition:transform .25s"><path d="M5 12h14M13 6l6 6-6 6"></path></svg></a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));gap:20px">

      <div data-anim="card" data-border-glow class="vh-feature" style="padding:28px 24px;border-radius:22px;background:#F7F8FA;border:1px solid #EDF0F4;transition:transform .3s,box-shadow .3s,background .3s">
        <div style="width:48px;height:48px;border-radius:15px;background:#EFF4FF;color:#2563EB;display:grid;place-items:center"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path></svg></div>
        <h3 style="margin:18px 0 0;font-size:16.5px;font-weight:800;letter-spacing:-.01em;color:#081421">Trust First</h3>
        <p style="margin:8px 0 0;font-size:13.5px;line-height:1.6;color:#5A6B85;text-wrap:pretty">Founder social profiles and experience for credibility, transparent fee structures, and no hidden terms.</p>
      </div>

      <div data-anim="card" data-border-glow class="vh-feature" style="padding:28px 24px;border-radius:22px;background:#F7F8FA;border:1px solid #EDF0F4;transition:transform .3s,box-shadow .3s,background .3s">
        <div style="width:48px;height:48px;border-radius:15px;background:rgba(245,197,24,.16);color:#8A6D00;display:grid;place-items:center"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"></path></svg></div>
        <h3 style="margin:18px 0 0;font-size:16.5px;font-weight:800;letter-spacing:-.01em;color:#081421">People Over Capital</h3>
        <p style="margin:8px 0 0;font-size:13.5px;line-height:1.6;color:#5A6B85;text-wrap:pretty">We match on skills and commitment first, money second.</p>
      </div>

      <div data-anim="card" data-border-glow class="vh-feature" style="padding:28px 24px;border-radius:22px;background:#F7F8FA;border:1px solid #EDF0F4;transition:transform .3s,box-shadow .3s,background .3s">
        <div style="width:48px;height:48px;border-radius:15px;background:rgba(245,197,24,.16);color:#8A6D00;display:grid;place-items:center"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg></div>
        <h3 style="margin:18px 0 0;font-size:16.5px;font-weight:800;letter-spacing:-.01em;color:#081421">Fair, Simple Fees</h3>
        <p style="margin:8px 0 0;font-size:13.5px;line-height:1.6;color:#5A6B85;text-wrap:pretty">A flat 0.5% commitment fee, disclosed upfront. No surprises.</p>
      </div>

      <div data-anim="card" data-border-glow class="vh-feature" style="padding:28px 24px;border-radius:22px;background:#F7F8FA;border:1px solid #EDF0F4;transition:transform .3s,box-shadow .3s,background .3s">
        <div style="width:48px;height:48px;border-radius:15px;background:#EFF4FF;color:#2563EB;display:grid;place-items:center"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg></div>
        <h3 style="margin:18px 0 0;font-size:16.5px;font-weight:800;letter-spacing:-.01em;color:#081421">Real-World Meetups</h3>
        <p style="margin:8px 0 0;font-size:13.5px;line-height:1.6;color:#5A6B85;text-wrap:pretty">Every Asset confirms with a partner meetup before commitment locks in.</p>
      </div>

    </div>
  </div>
</section>

<!-- ===== WHAT WE STAND FOR ===== -->

<section id="principles" style="position:relative;overflow:hidden;background:#081421;background-image:radial-gradient(900px 500px at 50% -10%,rgba(37,99,235,.14),transparent 60%)">
  <div style="position:absolute;bottom:-260px;right:-200px;width:620px;height:620px;border-radius:999px;border:1px solid rgba(255,255,255,.05)"></div>
  <div style="max-width:1360px;margin:0 auto;padding:clamp(72px,9vw,130px) clamp(20px,4vw,48px)">
    <div style="text-align:center;max-width:640px;margin:0 auto">
      <div data-anim="fade" style="display:inline-block;padding:8px 18px;border-radius:999px;border:1px solid rgba(245,197,24,.3);color:#F5C518;font-size:12px;font-weight:800;letter-spacing:.18em;text-transform:uppercase">Our Principles</div>
      <h2 data-anim="fade" style="margin:20px 0 0;font-size:clamp(40px,4.6vw,64px);line-height:1.04;letter-spacing:-.03em;font-weight:800;color:#fff">What We Stand For</h2>
      <p data-anim="fade" style="margin:16px 0 0;font-size:16.5px;line-height:1.65;color:rgba(255,255,255,.55)">The rules baked into every Asset on the platform</p>
    </div>
    <div style="position:relative;margin-top:clamp(48px,6vw,80px)">
      <div style="position:absolute;top:88px;left:4%;right:4%;height:1px;background:linear-gradient(90deg,transparent,rgba(245,197,24,.4) 20%,rgba(245,197,24,.4) 80%,transparent)"></div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:26px">

        <div data-anim="card" data-border-glow class="vh-step vh-principle" style="position:relative;padding:30px 26px;border-radius:24px;background:#0C1826;border:1px solid rgba(255,255,255,.08);transition:transform .3s,border-color .3s,background .3s">
          <div style="font-size:64px;font-weight:800;letter-spacing:-.04em;line-height:1;color:rgba(255,255,255,.07)">01</div>
          <div style="margin-top:-26px;width:56px;height:56px;border-radius:18px;background:#2563EB;color:#fff;display:grid;place-items:center;box-shadow:0 10px 26px rgba(0,0,0,.35)"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg></div>
          <h3 style="margin:20px 0 0;font-size:20px;font-weight:800;letter-spacing:-.01em;color:#fff">Transparent Members</h3>
          <p style="margin:10px 0 0;font-size:14.5px;line-height:1.65;color:rgba(255,255,255,.55);text-wrap:pretty">Every member shares their social profiles and past experience so others can evaluate their credibility before investing or founding an Asset together.</p>
        </div>

        <div data-anim="card" data-border-glow class="vh-step vh-principle" style="position:relative;padding:30px 26px;border-radius:24px;background:#0C1826;border:1px solid rgba(255,255,255,.08);transition:transform .3s,border-color .3s,background .3s">
          <div style="font-size:64px;font-weight:800;letter-spacing:-.04em;line-height:1;color:rgba(255,255,255,.07)">02</div>
          <div style="margin-top:-26px;width:56px;height:56px;border-radius:18px;background:#F5C518;color:#081421;display:grid;place-items:center;box-shadow:0 10px 26px rgba(0,0,0,.35)"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg></div>
          <h3 style="margin:20px 0 0;font-size:20px;font-weight:800;letter-spacing:-.01em;color:#fff">Meetup Before Lock-In</h3>
          <p style="margin:10px 0 0;font-size:14.5px;line-height:1.65;color:rgba(255,255,255,.55);text-wrap:pretty">A 24-hour exit window after the first partner meetup protects both sides.</p>
        </div>

        <div data-anim="card" data-border-glow class="vh-step vh-principle" style="position:relative;padding:30px 26px;border-radius:24px;background:#0C1826;border:1px solid rgba(255,255,255,.08);transition:transform .3s,border-color .3s,background .3s">
          <div style="font-size:64px;font-weight:800;letter-spacing:-.04em;line-height:1;color:rgba(255,255,255,.07)">03</div>
          <div style="margin-top:-26px;width:56px;height:56px;border-radius:18px;background:#2563EB;color:#fff;display:grid;place-items:center;box-shadow:0 10px 26px rgba(0,0,0,.35)"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg></div>
          <h3 style="margin:20px 0 0;font-size:20px;font-weight:800;letter-spacing:-.01em;color:#fff">Monthly Transparency</h3>
          <p style="margin:10px 0 0;font-size:14.5px;line-height:1.65;color:rgba(255,255,255,.55);text-wrap:pretty">Revenue, expenses, and each partner's profit share, published every month.</p>
        </div>

      </div>
    </div>
  </div>
</section>

<!-- ===== CTA ===== -->

<section id="cta" data-splash-cursor data-splash-color="#F5C518" style="position:relative;overflow:hidden;background:#000">
  <div style="position:absolute;top:-140px;right:8%;width:300px;height:300px;border-radius:999px;background:radial-gradient(circle,rgba(245,197,24,.16),transparent 70%)"></div>
  <div style="position:absolute;bottom:-160px;left:4%;width:340px;height:340px;border-radius:999px;background:radial-gradient(circle,rgba(37,99,235,.18),transparent 70%)"></div>
  <div style="max-width:920px;margin:0 auto;padding:clamp(90px,11vw,160px) clamp(20px,4vw,48px);text-align:center;position:relative;z-index:1">
    <div data-anim="fade" style="display:inline-block;padding:8px 18px;border-radius:999px;border:1px solid rgba(245,197,24,.3);color:#F5C518;font-size:12px;font-weight:800;letter-spacing:.18em;text-transform:uppercase">Ready to Start?</div>
    <h2 data-anim="fade" style="margin:26px 0 0;font-size:clamp(40px,5.4vw,76px);line-height:1.04;letter-spacing:-.035em;font-weight:800;color:#fff">Ready to build <span style="color:#F5C518">something together?</span></h2>
    <p data-anim="fade" style="margin:24px 0 0;font-size:17px;line-height:1.65;color:rgba(255,255,255,.6)">Create your free account and explore Assets looking for partners like you.</p>
    <div data-anim="fade" style="margin-top:44px;display:flex;flex-wrap:wrap;justify-content:center;gap:16px">
      <a href="../users/auth.php" class="vh-cta-gold" style="display:inline-flex;align-items:center;gap:10px;padding:18px 38px;border-radius:999px;background:#F5C518;color:#081421;font-size:16px;font-weight:800;box-shadow:0 12px 34px rgba(245,197,24,.35);transition:transform .25s,box-shadow .25s">Create Free Account
        <svg data-arrow width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transition:transform .25s"><path d="M5 12h14M13 6l6 6-6 6"></path></svg></a>
      <a href="contact.php" class="vh-btn-ghost-lg" style="display:inline-flex;align-items:center;padding:18px 38px;border-radius:999px;border:1px solid rgba(245,197,24,.4);color:#F5C518;font-size:16px;font-weight:700;transition:border-color .25s,background .25s,transform .25s">Contact Us</a>
    </div>
  </div>
</section>

<!-- ===== FOOTER ===== -->
<footer id="footer" style="background:#0D0D0D;border-top:1px solid rgba(255,255,255,.06)">
  <div style="max-width:1360px;margin:0 auto;padding:clamp(56px,7vw,90px) clamp(20px,4vw,48px)">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));gap:40px">
      <div style="grid-column:span 1;max-width:320px">
        <div style="display:flex;align-items:center;gap:10px;font-size:16px;font-weight:800;letter-spacing:.03em;color:#fff"><img src="../assets/img/logo-mark.svg" alt="" width="30" height="30" style="display:block;flex-shrink:0"><span style="white-space:nowrap">VENTURES HARBOR</span></div>
        <div style="margin-top:14px;font-size:14.5px;font-weight:700;color:#F5C518">Co-Own. Build. Scale.</div>
        <p style="margin:12px 0 0;font-size:13.5px;line-height:1.65;color:rgba(255,255,255,.5);text-wrap:pretty">Fractional ownership platform connecting capital, ideas and talent to Real-World Assets across India.</p>
      </div>

      <div>
        <div style="font-size:12px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:rgba(255,255,255,.4)">Platform</div>
        <div style="margin-top:18px;display:flex;flex-direction:column;gap:12px">
          <a href="browse.php" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">Browse Assets</a>
          <a href="create-venture.php" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">List Your Venture</a>
          <a href="../admin/dashboard.php" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">Dashboard</a>
          <a href="../admin/meetup.php" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">Meetups</a>
        </div>
      </div>

      <div>
        <div style="font-size:12px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:rgba(255,255,255,.4)">Account</div>
        <div style="margin-top:18px;display:flex;flex-direction:column;gap:12px">
          <a href="../users/auth.php" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">Sign In / Sign Up</a>
          <a href="../users/profile.php" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">Profile</a>
          <a href="../users/wallet.php" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">Wallet</a>
        </div>
      </div>

      <div>
        <div style="font-size:12px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:rgba(255,255,255,.4)">Support</div>
        <div style="margin-top:18px;display:flex;flex-direction:column;gap:12px">
          <a href="about.php" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">About Us</a>
          <a href="contact.php" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">Contact Us</a>
          <a href="about.php#privacy" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">Privacy Policy</a>
          <a href="about.php#terms" class="vh-flink" style="font-size:14px;font-weight:500;color:rgba(255,255,255,.65);transition:color .25s">Terms of Service</a>
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
<script src="../assets/js/shared.js?v=70"></script>
<script src="../assets/js/vh-nav.js?v=1"></script>
<script src="../assets/js/home.js?v=45"></script>
<script src="../assets/js/splash-cursor.js?v=31"></script>
<script src="../assets/js/border-glow.js?v=31"></script>
<script src="../assets/js/gradient-waves.js?v=32"></script>
</body>
</html>
