<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logo-mark.svg">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Contact Us – Ventures Harbor</title>
  <meta name="description" content="Get in touch with the Ventures Harbor team for queries, complaints, or support.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="../assets/css/vh.css?v=41">
  <link rel="stylesheet" href="../assets/css/border-glow.css?v=32">
  <link rel="stylesheet" href="../assets/css/gradient-waves.css?v=32">
  <style>
    /* Form controls, scoped to this page. vh.css is a layout/'look' system and
       carries no form styling, and the old main.css .form-control belongs to the
       stack this page just moved off. */
    .cf-field { display: flex; flex-direction: column; gap: 7px; }
    .cf-label {
      display: flex; align-items: center; justify-content: space-between;
      font-size: 13px; font-weight: 700; color: #33415C;
    }
    .cf-count { font-size: 12px; font-weight: 600; color: #7A8AA3; }
    .cf-input {
      width: 100%; padding: 13px 16px; border-radius: 14px;
      border: 1px solid #DCE3EC; background: #fff;
      font-family: inherit; font-size: 14.5px; color: #081421;
      transition: border-color .2s, box-shadow .2s;
    }
    .cf-input::placeholder { color: #9AA8BC; }
    .cf-input:focus {
      outline: 0; border-color: #2563EB;
      box-shadow: 0 0 0 4px rgba(37, 99, 235, .12);
    }
    textarea.cf-input { resize: vertical; min-height: 132px; line-height: 1.6; }
    .cf-submit {
      width: 100%; padding: 16px 28px; border: 0; border-radius: 999px;
      background: #2563EB; color: #fff;
      font-family: inherit; font-size: 15.5px; font-weight: 800; cursor: pointer;
      box-shadow: 0 12px 30px rgba(37, 99, 235, .32);
      transition: transform .25s, box-shadow .25s, background .25s;
    }
    .cf-submit:hover:not(:disabled) { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(37, 99, 235, .45); }
    .cf-submit:disabled { background: #93AEEA; box-shadow: none; cursor: progress; }
    .cf-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr)); gap: clamp(32px, 4vw, 64px); align-items: start; }
  </style>
  <link rel="stylesheet" href="../assets/css/vh-nav.css?v=3">
</head>
<body>

<div class="toast-container" id="toastContainer"></div>

<!-- film grain — same overlay as the homepage -->
<div style="position:fixed;inset:0;z-index:80;pointer-events:none;opacity:.05;background-image:url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='160' height='160' filter='url(%23n)' opacity='0.7'/%3E%3C/svg%3E&quot;)"></div>

<?php
$navActive = 'contact';
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
    <div data-anim="fade" style="display:inline-block;padding:7px 16px;border-radius:999px;border:1px solid rgba(245,197,24,.3);color:#F5C518;font-size:11.5px;font-weight:800;letter-spacing:.18em;text-transform:uppercase">We're Here to Help</div>
    <h1 style="margin:18px 0 0;font-size:clamp(34px,4.8vw,62px);line-height:1.06;letter-spacing:-.03em;font-weight:800;color:#fff">
      <span style="display:block;overflow:hidden"><span data-hero-line style="display:block">Contact</span></span>
      <span style="display:block;overflow:hidden"><span data-hero-line style="display:block;color:#3B82F6">Ventures Harbor</span></span>
    </h1>
    <p data-anim="fade" style="margin:18px auto 0;max-width:600px;font-size:15.5px;line-height:1.7;color:rgba(255,255,255,.78);text-wrap:pretty">Questions, complaints, or just want to say hi? Send us a message and our team will respond within 24–48 hours.</p>
  </div>
</header>

<!-- ===== CONTACT ===== -->
<section id="contact" style="position:relative;overflow:hidden;background:#fff">
  <div style="position:absolute;bottom:-200px;left:-200px;width:520px;height:520px;border-radius:999px;background:radial-gradient(circle,rgba(245,197,24,.08),transparent 70%)"></div>
  <div style="max-width:1180px;margin:0 auto;padding:clamp(64px,8vw,110px) clamp(20px,4vw,48px)">
    <div class="cf-grid">

      <!-- Info column -->
      <div>
        <div data-anim="fade" style="display:inline-block;padding:8px 18px;border-radius:999px;background:#EFF4FF;color:#2563EB;font-size:12px;font-weight:800;letter-spacing:.18em;text-transform:uppercase">Get in Touch</div>
        <h2 data-anim="fade" style="margin:22px 0 0;font-size:clamp(34px,3.8vw,52px);line-height:1.06;letter-spacing:-.03em;font-weight:800;color:#081421">We read every<br><span style="color:#2563EB">single message.</span></h2>
        <p data-anim="fade" style="margin:20px 0 0;max-width:460px;font-size:16px;line-height:1.7;color:#5A6B85;text-wrap:pretty">Whether it's a question about joining an Asset, a complaint about a founder, or feedback on the platform — it reaches a real person.</p>

        <div style="margin-top:34px;display:flex;flex-direction:column;gap:16px">
          <div data-anim="card" data-border-glow class="vh-feature" style="padding:24px 22px;border-radius:20px;background:#F7F8FA;border:1px solid #EDF0F4;transition:transform .3s,box-shadow .3s,background .3s">
            <div style="width:44px;height:44px;border-radius:14px;background:#EFF4FF;color:#2563EB;display:grid;place-items:center"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-10 6L2 7"></path></svg></div>
            <h3 style="margin:16px 0 0;font-size:15.5px;font-weight:800;letter-spacing:-.01em;color:#081421">Email Us</h3>
            <p style="margin:6px 0 0;font-size:13.5px;line-height:1.6;color:#5A6B85;word-break:break-word" id="contactEmailDisplay">support@venturesharbor.com</p>
          </div>

          <div data-anim="card" data-border-glow class="vh-feature" style="padding:24px 22px;border-radius:20px;background:#F7F8FA;border:1px solid #EDF0F4;transition:transform .3s,box-shadow .3s,background .3s">
            <div style="width:44px;height:44px;border-radius:14px;background:rgba(245,197,24,.16);color:#8A6D00;display:grid;place-items:center"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg></div>
            <h3 style="margin:16px 0 0;font-size:15.5px;font-weight:800;letter-spacing:-.01em;color:#081421">Response Time</h3>
            <p style="margin:6px 0 0;font-size:13.5px;line-height:1.6;color:#5A6B85">24–48 hours on business days.</p>
          </div>

          <div data-anim="card" data-border-glow class="vh-feature" style="padding:24px 22px;border-radius:20px;background:#F7F8FA;border:1px solid #EDF0F4;transition:transform .3s,box-shadow .3s,background .3s">
            <div style="width:44px;height:44px;border-radius:14px;background:#EFF4FF;color:#2563EB;display:grid;place-items:center"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path></svg></div>
          </div>
        </div>
      </div>

      <!-- Form column -->
      <div data-anim="card" style="padding:clamp(26px,3vw,40px);border-radius:26px;background:#fff;border:1px solid #EDF0F4;box-shadow:0 8px 40px rgba(8,20,33,.07)">
        <form id="contactForm" style="display:flex;flex-direction:column;gap:18px">
          <div class="cf-field">
            <label class="cf-label" for="cfName">Your Name *</label>
            <input class="cf-input" id="cfName" placeholder="e.g. Priya Singh" required>
          </div>
          <div class="cf-field">
            <label class="cf-label" for="cfEmail">Email Address *</label>
            <input class="cf-input" type="email" id="cfEmail" placeholder="you@example.com" required>
          </div>
          <div class="cf-field">
            <label class="cf-label" for="cfSubject">Subject</label>
            <input class="cf-input" id="cfSubject" placeholder="e.g. Question about commitment fees">
          </div>
          <div class="cf-field">
            <label class="cf-label" for="cfMessage">Message * <span class="cf-count" id="cfMsgCount">0/1000</span></label>
            <textarea class="cf-input" id="cfMessage" rows="5" maxlength="1000" placeholder="Tell us about your query, complaint, or feedback..." required></textarea>
          </div>
          <button type="submit" class="cf-submit" id="cfSubmitBtn">Send Message</button>
        </form>
      </div>

    </div>
  </div>
</section>

<!-- ===== CTA ===== -->
<section id="cta" data-splash-cursor data-splash-color="#F5C518" style="position:relative;overflow:hidden;background:#000">
  <div style="position:absolute;top:-140px;right:8%;width:300px;height:300px;border-radius:999px;background:radial-gradient(circle,rgba(245,197,24,.16),transparent 70%)"></div>
  <div style="position:absolute;bottom:-160px;left:4%;width:340px;height:340px;border-radius:999px;background:radial-gradient(circle,rgba(37,99,235,.18),transparent 70%)"></div>
  <div style="max-width:920px;margin:0 auto;padding:clamp(72px,9vw,130px) clamp(20px,4vw,48px);text-align:center;position:relative;z-index:1">
    <div data-anim="fade" style="display:inline-block;padding:8px 18px;border-radius:999px;border:1px solid rgba(245,197,24,.3);color:#F5C518;font-size:12px;font-weight:800;letter-spacing:.18em;text-transform:uppercase">While You Wait</div>
    <h2 data-anim="fade" style="margin:26px 0 0;font-size:clamp(36px,4.8vw,66px);line-height:1.04;letter-spacing:-.035em;font-weight:800;color:#fff">Browse Assets <span style="color:#F5C518">looking for partners</span></h2>
    <p data-anim="fade" style="margin:24px 0 0;font-size:17px;line-height:1.65;color:rgba(255,255,255,.6)">No need to wait on a reply to start exploring what's live on the platform.</p>
    <div data-anim="fade" style="margin-top:40px;display:flex;flex-wrap:wrap;justify-content:center;gap:16px">
      <a href="browse.php" class="vh-cta-gold" style="display:inline-flex;align-items:center;gap:10px;padding:18px 38px;border-radius:999px;background:#F5C518;color:#081421;font-size:16px;font-weight:800;box-shadow:0 12px 34px rgba(245,197,24,.35);transition:transform .25s,box-shadow .25s">Browse Assets
        <svg data-arrow width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transition:transform .25s"><path d="M5 12h14M13 6l6 6-6 6"></path></svg></a>
      <a href="about.php" class="vh-btn-ghost-lg" style="display:inline-flex;align-items:center;padding:18px 38px;border-radius:999px;border:1px solid rgba(245,197,24,.4);color:#F5C518;font-size:16px;font-weight:700;transition:border-color .25s,background .25s,transform .25s">About Us</a>
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
<script>
  /* Contact form. Unchanged in behaviour from the previous version — same ids,
     same character counter, same POST to api/contact.php?action=submit. The nav
     and account menu are handled by home.js now, so only the form lives here. */
  document.addEventListener("DOMContentLoaded", function () {
    var nameEl = document.getElementById("cfName");
    var emailEl = document.getElementById("cfEmail");
    var msgEl = document.getElementById("cfMessage");
    var countEl = document.getElementById("cfMsgCount");
    var form = document.getElementById("contactForm");
    var submitBtn = document.getElementById("cfSubmitBtn");

    // Prefill from the signed-in user, and again when shared.js reconciles the
    // localStorage cache against the real PHP session (it may arrive later, or
    // strip a stale user the session no longer backs).
    var currentUser = window.VH && VH.auth ? VH.auth.getUser() : null;

    function prefill(user) {
      currentUser = user || null;
      if (!user) return;
      if (nameEl && !nameEl.value) nameEl.value = user.name || "";
      if (emailEl && !emailEl.value) emailEl.value = user.email || "";
    }
    prefill(currentUser);
    document.addEventListener("vh:auth-changed", function (e) {
      prefill(e.detail && e.detail.user);
    });

    if (msgEl && countEl) {
      msgEl.addEventListener("input", function () {
        countEl.textContent = msgEl.value.length + "/1000";
      });
    }

    if (!form) return;
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var name = nameEl.value.trim();
      var email = emailEl.value.trim();
      var subject = document.getElementById("cfSubject").value.trim();
      var message = msgEl.value.trim();

      if (!name || !email || !message) {
        VH.toast.error("Please fill in your name, email, and message.");
        return;
      }

      submitBtn.textContent = "Sending...";
      submitBtn.disabled = true;

      fetch("../api/contact.php?action=submit", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name: name, email: email, subject: subject, message: message })
      })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.success) {
            VH.toast.success(res.message || "Message sent!");
            form.reset();
            if (countEl) countEl.textContent = "0/1000";
            // reset() wipes the prefill too, so put it back for a signed-in user.
            if (currentUser) {
              nameEl.value = currentUser.name || "";
              emailEl.value = currentUser.email || "";
            }
          } else {
            VH.toast.error(res.message || "Failed to send message.");
          }
        })
        .catch(function (err) {
          console.error(err);
          VH.toast.error("Network error sending message.");
        })
        .finally(function () {
          submitBtn.textContent = "Send Message";
          submitBtn.disabled = false;
        });
    });
  });
</script>
</body>
</html>
