<?php
require_once __DIR__ . '/../config/database.php';
$token = $_GET['token'] ?? '';
$isValid = false;
$error = '';

if ($token) {
    $stmt = $mysqli->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_expiry >= NOW()');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($user) {
        $isValid = true;
    } else {
        $error = 'Invalid or expired password reset link.';
    }
} else {
    $error = 'No reset token specified.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logo-mark.svg">
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reset Password – Ventures Harbor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/css/main.css?v=35"/>
  <link rel="stylesheet" href="../assets/css/components.css?v=38"/>
  <link rel="stylesheet" href="../assets/css/theme.css?v=32"/>
  <link rel="stylesheet" href="../assets/css/auth.css?v=33"/>
    <link rel="stylesheet" href="../assets/css/vh-pages.css?v=43" />
</head>
<body class="auth-body">
<div class="toast-container" id="toastContainer"></div>

<div class="auth-layout" style="justify-content: center;">
  <div class="auth-right" style="flex: 0 0 480px; max-width: 100%;">
    <div class="auth-card">
      <div class="auth-brand" style="margin-bottom: 2rem; justify-content: center;">
        <img src="../assets/img/logo-mark.svg" alt="" class="auth-brand-mark" width="34" height="34">
        <span class="auth-brand-text">VENTURES HARBOR</span>
      </div>

      <?php if (!$isValid): ?>
        <div class="auth-form-header text-center" style="margin-bottom: 2rem;">
          <div style="color:#B4690E;margin-bottom:1rem;"><svg class="vh-i" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><path d="M12 9v4M12 17h.01"/></svg></div>
          <h2>Link Expired</h2>
          <p style="color: #ef4444;"><?php echo htmlspecialchars($error); ?></p>
        </div>
        <div class="auth-switch text-center"><a href="auth.php" class="auth-link">← Back to Sign In</a></div>
      <?php else: ?>
        <div class="auth-form-header">
          <h2>Create New Password</h2>
          <p>Please enter your new 8-character password below</p>
        </div>
        <form id="resetPasswordForm" novalidate>
          <input type="hidden" id="resetToken" value="<?php echo htmlspecialchars($token); ?>" />
          <div class="form-group">
            <label class="form-label" for="newPassword">New Password</label>
            <div class="input-icon-wrap">
              <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <input class="form-control input-has-icon" type="password" id="newPassword" placeholder="Min. 8 characters" autocomplete="new-password"/>
            </div>
            <span class="form-error" id="newPassErr"></span>
          </div>
          <div class="form-group">
            <label class="form-label" for="confirmPassword">Confirm Password</label>
            <div class="input-icon-wrap">
              <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <input class="form-control input-has-icon" type="password" id="confirmPassword" placeholder="Re-enter password" autocomplete="new-password"/>
            </div>
            <span class="form-error" id="confirmPassErr"></span>
          </div>
          <button type="submit" class="btn btn--primary btn--lg btn--full" id="resetBtn">Reset Password</button>
        </form>
      <?php endif; ?>

    </div>
  </div>
</div>

<script src="../assets/js/shared.js?v=70"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('resetPasswordForm');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const token = document.getElementById('resetToken').value;
    const pass = document.getElementById('newPassword').value;
    const confirm = document.getElementById('confirmPassword').value;

    document.getElementById('newPassErr').textContent = '';
    document.getElementById('confirmPassErr').textContent = '';

    let valid = true;
    if (!pass || pass.length < 8) {
      document.getElementById('newPassErr').textContent = 'Password must be at least 8 characters';
      valid = false;
    }
    if (pass !== confirm) {
      document.getElementById('confirmPassErr').textContent = 'Passwords do not match';
      valid = false;
    }
    if (!valid) return;

    const btn = document.getElementById('resetBtn');
    btn.textContent = 'Resetting...';
    btn.disabled = true;

    try {
      const response = await fetch('../api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'reset',
          token: token,
          password: pass
        })
      });
      const res = await response.json();
      if (res.success) {
        VH.toast.success('Password reset successful! Redirecting...');
        setTimeout(() => {
          window.location.href = 'auth.php';
        }, 1500);
      } else {
        VH.toast.error(res.message || 'Reset failed.');
        btn.textContent = 'Reset Password';
        btn.disabled = false;
      }
    } catch(err) {
      console.error(err);
      VH.toast.error('Network error resetting password.');
      btn.textContent = 'Reset Password';
      btn.disabled = false;
    }
  });
});
</script>
</body>
</html>
