<?php
require_once __DIR__ . '/../config/session.php';
$sessionUser = getSessionUser();
if ($sessionUser) {
    $target = ($sessionUser['role'] ?? '') === 'admin' ? '../admin/admin.php' : '../admin/dashboard.php';
    header('Location: ' . $target);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logo-mark.svg">
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sign In / Sign Up – Ventures Harbor</title>
  <meta name="description" content="Join Ventures Harbor – the fractional business ownership platform. Sign in or create your free account."/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/css/main.css?v=35"/>
  <link rel="stylesheet" href="../assets/css/components.css?v=38"/>
  <link rel="stylesheet" href="../assets/css/theme.css?v=32"/>
  <link rel="stylesheet" href="../assets/css/auth.css?v=33"/>
</head>
<body class="auth-body">
<div class="toast-container" id="toastContainer"></div>

<video class="auth-bg-video" id="authBgVideo"
       autoplay muted loop playsinline preload="auto"
       disablepictureinpicture aria-hidden="true" tabindex="-1">
  <source src="../uploads/cinematicNew.mp4" type="video/mp4">
</video>

<main class="auth-shell">
  <div class="auth-col">

    <a href="../index.php" class="auth-brand">
      <img src="../assets/img/logo-mark.svg" alt="" width="26" height="26">
      <span>Ventures Harbor</span>
    </a>

    <div class="auth-card">

      <!-- Segmented control -->
      <div class="auth-tabs" id="authTabs">
        <button type="button" class="auth-tab active" data-tab="signin" id="tabSignin">Sign in</button>
        <button type="button" class="auth-tab" data-tab="signup" id="tabSignup">Create account</button>
      </div>

      <!-- ── SIGN IN ── -->
      <div class="auth-form-panel" id="panelSignin">
        <div class="auth-head">
          <h1>Welcome back</h1>
          <p>Sign in to continue to your dashboard.</p>
        </div>

        <form id="signinForm" novalidate>
          <div class="form-group">
            <label class="auth-label" for="siEmail">Email address</label>
            <input class="auth-input" type="email" id="siEmail" placeholder="you@example.com" autocomplete="email"/>
            <span class="form-error" id="siEmailErr"></span>
          </div>

          <div class="form-group">
            <div class="auth-label-row">
              <label class="auth-label" for="siPassword">Password</label>
              <a href="#" class="auth-link-sm" id="forgotPassLink">Forgot password?</a>
            </div>
            <div class="auth-input-wrap">
              <input class="auth-input auth-input--pad" type="password" id="siPassword" placeholder="••••••••" autocomplete="current-password"/>
              <button type="button" class="auth-eye" id="siEye" tabindex="-1" aria-label="Show password">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <span class="form-error" id="siPassErr"></span>
          </div>

          <label class="auth-check">
            <input type="checkbox" id="rememberMe"/>
            <span>Remember me on this device</span>
          </label>

          <button type="submit" class="auth-btn" id="signinBtn">Sign in</button>
        </form>

        <div class="auth-divider"><span>or continue with</span></div>

        <div class="auth-social">
          <button type="button" class="auth-social-btn" id="googleSignin" disabled title="Google sign-in is coming soon">
            <svg width="17" height="17" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
            <span>Google</span>
          </button>
          <button type="button" class="auth-social-btn" id="appleSignin" disabled title="Apple sign-in is coming soon">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.05 12.54c-.02-2.2 1.8-3.26 1.88-3.31-1.02-1.5-2.61-1.7-3.18-1.73-1.36-.14-2.65.8-3.34.8-.69 0-1.75-.78-2.87-.76-1.48.02-2.84.86-3.6 2.18-1.53 2.66-.39 6.6 1.1 8.76.73 1.06 1.6 2.25 2.75 2.2 1.1-.04 1.52-.71 2.85-.71 1.33 0 1.7.71 2.87.69 1.18-.02 1.93-1.08 2.65-2.14.83-1.22 1.18-2.41 1.2-2.47-.03-.01-2.3-.88-2.31-3.51zM14.9 5.6c.6-.74 1.01-1.75.9-2.77-.87.04-1.94.59-2.57 1.32-.56.64-1.06 1.68-.93 2.67.98.08 1.98-.5 2.6-1.22z"/></svg>
            <span>Apple</span>
          </button>
        </div>
        <p class="auth-social-note">Social sign-in is coming soon — use your email for now.</p>

        <p class="auth-switch">Don't have an account? <a href="#" id="switchToSignup" class="auth-link">Create one</a></p>
      </div>

      <!-- ── SIGN UP ── -->
      <div class="auth-form-panel hidden" id="panelSignup">
        <div class="auth-head">
          <h1>Create your account</h1>
          <p>Join the Future of Fractional Ownership.</p>
        </div>

        <form id="signupForm" novalidate>

          <div class="form-group">
            <label class="auth-label" for="suName"><span id="suNameLabel">Full name</span></label>
            <input class="auth-input" type="text" id="suName" placeholder="Your full name" autocomplete="name"/>
            <span class="form-error" id="suNameErr"></span>
          </div>

          <div class="form-group">
            <label class="auth-label" for="suEmail">Email address</label>
            <input class="auth-input" type="email" id="suEmail" placeholder="you@example.com" autocomplete="email"/>
            <span class="form-error" id="suEmailErr"></span>
          </div>

          <div class="auth-row-2">
            <div class="form-group">
              <label class="auth-label" for="suPassword">Password</label>
              <div class="auth-input-wrap">
                <input class="auth-input auth-input--pad" type="password" id="suPassword" placeholder="Min. 8 characters" autocomplete="new-password"/>
                <button type="button" class="auth-eye" id="suEye" tabindex="-1" aria-label="Show password">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
              <span class="form-error" id="suPassErr"></span>
            </div>
            <div class="form-group">
              <label class="auth-label" for="suConfirm">Confirm password</label>
              <input class="auth-input" type="password" id="suConfirm" placeholder="Re-enter password" autocomplete="new-password"/>
              <span class="form-error" id="suConfirmErr"></span>
            </div>
          </div>

          <div class="password-strength" id="passStrength">
            <div class="ps-bar"><div class="ps-seg" id="ps1"></div><div class="ps-seg" id="ps2"></div><div class="ps-seg" id="ps3"></div><div class="ps-seg" id="ps4"></div></div>
            <span class="ps-label" id="psLabel">Password strength</span>
          </div>

          <label class="auth-check">
            <input type="checkbox" id="suTerms"/>
            <span>I agree to the <a href="#" class="auth-link">Terms of Service</a> and <a href="#" class="auth-link">Privacy Policy</a></span>
          </label>
          <span class="form-error" id="suTermsErr"></span>

          <button type="submit" class="auth-btn" id="signupBtn">Create account</button>
        </form>

        <div class="auth-divider"><span>or continue with</span></div>

        <div class="auth-social">
          <button type="button" class="auth-social-btn" id="googleSignup" disabled title="Google sign-in is coming soon">
            <svg width="17" height="17" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
            <span>Google</span>
          </button>
          <button type="button" class="auth-social-btn" disabled title="Apple sign-in is coming soon">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.05 12.54c-.02-2.2 1.8-3.26 1.88-3.31-1.02-1.5-2.61-1.7-3.18-1.73-1.36-.14-2.65.8-3.34.8-.69 0-1.75-.78-2.87-.76-1.48.02-2.84.86-3.6 2.18-1.53 2.66-.39 6.6 1.1 8.76.73 1.06 1.6 2.25 2.75 2.2 1.1-.04 1.52-.71 2.85-.71 1.33 0 1.7.71 2.87.69 1.18-.02 1.93-1.08 2.65-2.14.83-1.22 1.18-2.41 1.2-2.47-.03-.01-2.3-.88-2.31-3.51zM14.9 5.6c.6-.74 1.01-1.75.9-2.77-.87.04-1.94.59-2.57 1.32-.56.64-1.06 1.68-.93 2.67.98.08 1.98-.5 2.6-1.22z"/></svg>
            <span>Apple</span>
          </button>
        </div>
        <p class="auth-social-note">Social sign-in is coming soon — use your email for now.</p>

        <p class="auth-switch">Already have an account? <a href="#" id="switchToSignin" class="auth-link">Sign in</a></p>
      </div>

      <!-- ── VERIFY EMAIL ── -->
      <div class="auth-form-panel hidden" id="panelVerify">
        <div class="auth-head auth-head--center">
          <div class="auth-icon-badge">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </div>
          <h1>Check your email</h1>
          <p>We sent a 6-digit code to <strong id="verifyEmail">you@example.com</strong></p>
        </div>

        <form id="verifyForm" novalidate>
          <div class="otp-group" id="otpGroup">
            <input class="otp-input" type="text" maxlength="1" inputmode="numeric" id="otp0" aria-label="Digit 1"/>
            <input class="otp-input" type="text" maxlength="1" inputmode="numeric" id="otp1" aria-label="Digit 2"/>
            <input class="otp-input" type="text" maxlength="1" inputmode="numeric" id="otp2" aria-label="Digit 3"/>
            <input class="otp-input" type="text" maxlength="1" inputmode="numeric" id="otp3" aria-label="Digit 4"/>
            <input class="otp-input" type="text" maxlength="1" inputmode="numeric" id="otp4" aria-label="Digit 5"/>
            <input class="otp-input" type="text" maxlength="1" inputmode="numeric" id="otp5" aria-label="Digit 6"/>
          </div>
          <span class="form-error form-error--center" id="otpErr"></span>

          <div class="otp-timer">Resend code in <span id="otpCountdown">0:60</span></div>
          <button type="submit" class="auth-btn" id="verifyBtn">Verify and continue</button>
        </form>

        <p class="auth-switch"><a href="#" id="backToSignin" class="auth-link">Back to sign in</a></p>
      </div>

      <!-- ── FORGOT PASSWORD ── -->
      <div class="auth-form-panel hidden" id="panelForgot">
        <div class="auth-head">
          <h1>Reset your password</h1>
          <p>Enter your email and we'll send you a reset link.</p>
        </div>

        <form id="forgotForm" novalidate>
          <div class="form-group">
            <label class="auth-label" for="feEmail">Email address</label>
            <input class="auth-input" type="email" id="feEmail" placeholder="you@example.com" autocomplete="email"/>
            <span class="form-error" id="feEmailErr"></span>
          </div>
          <button type="submit" class="auth-btn" id="forgotBtn">Send reset link</button>
        </form>

        <p class="auth-switch"><a href="#" id="forgotBackToSignin" class="auth-link">Back to sign in</a></p>
      </div>

    </div><!-- /auth-card -->

    <a href="../index.php" class="auth-home-link">← Back to home</a>

  </div>
</main>

<script src="../assets/js/shared.js?v=70"></script>
<script src="../assets/js/auth.js?v=32"></script>
<script src="../assets/js/auth-fx.js?v=32"></script>
</body>
</html>
