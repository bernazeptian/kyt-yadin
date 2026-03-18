<?php
session_start();
require_once '../config/db.php';

$token = $_GET['token'] ?? '';
$error = $_GET['error'] ?? '';

if (!$token) {
    header('Location: login.php');
    exit;
}

// Validate token
$stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = :token AND reset_expires > NOW() AND is_active = 1 LIMIT 1");
$stmt->execute([':token' => $token]);
$user = $stmt->fetch();

if (!$user) {
    $invalid = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reset Password — KYT Yadin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --primary: #1a73e8; --primary-dark: #1557b0; --primary-light: #e8f0fe;
      --red: #d93025; --red-light: #fce8e6;
      --green: #1e8e3e; --green-light: #e6f4ea;
      --border: #e0e0e0; --text: #202124; --text-sub: #5f6368; --bg: #f1f3f4;
      --font: 'Google Sans', 'Segoe UI', sans-serif;
    }
    body { font-family: var(--font); background: var(--bg); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
    .wrap { width: 100%; max-width: 420px; animation: fadeUp 0.4s ease; }
    .card { background: #fff; border-radius: 16px; padding: 40px 36px; box-shadow: 0 2px 8px rgba(0,0,0,0.06), 0 8px 32px rgba(0,0,0,0.08); }
    .icon-wrap { width: 52px; height: 52px; border-radius: 14px; background: var(--primary-light); display: flex; align-items: center; justify-content: center; margin-bottom: 20px; color: var(--primary); }
    .icon-wrap--red { background: var(--red-light); color: var(--red); }
    .title { font-size: 22px; font-weight: 700; color: var(--text); margin-bottom: 8px; }
    .sub   { font-size: 14px; color: var(--text-sub); margin-bottom: 28px; line-height: 1.5; }
    .alert { display: flex; align-items: center; gap: 8px; padding: 12px 14px; border-radius: 8px; font-size: 13px; font-weight: 500; margin-bottom: 20px; }
    .alert--error { background: var(--red-light); color: var(--red); border: 1px solid rgba(217,48,37,0.2); }
    .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
    .form-label { font-size: 13px; font-weight: 600; color: var(--text); }
    .pw-wrap { position: relative; }
    .form-input { padding: 11px 14px; border: 1.5px solid var(--border); border-radius: 8px; font-family: var(--font); font-size: 14px; color: var(--text); outline: none; width: 100%; transition: border-color 0.18s, box-shadow 0.18s; }
    .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,115,232,0.12); }
    .pw-wrap .form-input { padding-right: 44px; }
    .pw-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-sub); padding: 4px; display: flex; align-items: center; }
    .pw-toggle:hover { color: var(--primary); }
    .form-hint { font-size: 11px; color: var(--text-sub); }
    .btn-submit { width: 100%; padding: 12px; background: var(--primary); color: #fff; border: none; border-radius: 8px; font-family: var(--font); font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.18s, box-shadow 0.18s; }
    .btn-submit:hover { background: var(--primary-dark); box-shadow: 0 4px 12px rgba(26,115,232,0.3); }
    .btn-back { display: inline-block; margin-top: 16px; text-align: center; width: 100%; font-size: 13px; color: var(--primary); text-decoration: none; font-weight: 500; }
    .footer { text-align: center; font-size: 12px; color: var(--text-sub); margin-top: 24px; }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
    @media (max-width: 480px) { .card { padding: 28px 20px; } }
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">

    <?php if (!empty($invalid)): ?>
      <div class="icon-wrap icon-wrap--red">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="26" height="26">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
      </div>
      <h1 class="title">Link Expired</h1>
      <p class="sub">This password reset link is invalid or has expired. Please request a new one.</p>
      <a href="forgot.php" class="btn-submit" style="display:block;text-align:center;text-decoration:none;line-height:2.2;">Request New Link</a>

    <?php elseif ($error === 'mismatch'): ?>
      <div class="icon-wrap icon-wrap--red">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="26" height="26">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
      </div>
      <h1 class="title">Passwords Don't Match</h1>
      <p class="sub" style="margin-bottom:20px;">Please go back and try again.</p>
      <a href="reset.php?token=<?php echo htmlspecialchars($token); ?>" class="btn-submit" style="display:block;text-align:center;text-decoration:none;line-height:2.2;">Try Again</a>

    <?php else: ?>
      <div class="icon-wrap">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="26" height="26">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
      </div>
      <h1 class="title">Reset Password</h1>
      <p class="sub">Hello <strong><?php echo htmlspecialchars($user['name']); ?></strong>, enter your new password below.</p>

      <?php if ($error): ?>
      <div class="alert alert--error">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        Something went wrong. Please try again.
      </div>
      <?php endif; ?>

      <form method="POST" action="reset_process.php">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>"/>

        <div class="form-group">
          <label class="form-label">New Password</label>
          <div class="pw-wrap">
            <input type="password" class="form-input" name="password" id="pw1"
              placeholder="Min. 8 characters" required minlength="8"/>
            <button type="button" class="pw-toggle" onclick="togglePw('pw1', this)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
          <span class="form-hint">At least 8 characters</span>
        </div>

        <div class="form-group">
          <label class="form-label">Confirm New Password</label>
          <div class="pw-wrap">
            <input type="password" class="form-input" name="password_confirm" id="pw2"
              placeholder="Re-enter new password" required minlength="8"/>
            <button type="button" class="pw-toggle" onclick="togglePw('pw2', this)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-submit">Save New Password</button>
      </form>
      <a href="login.php" class="btn-back">Back to login</a>
    <?php endif; ?>

  </div>
  <div class="footer">&copy; <?php echo date('Y'); ?> Yanmar · KYT Yadin Safety System</div>
</div>

<script>
function togglePw(id, btn) {
  const input = document.getElementById(id);
  input.type  = input.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
