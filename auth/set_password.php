<?php
session_start();
require_once '../config/db.php';

$token = trim($_GET['token'] ?? '');

if (!$token) {
  header('Location: login?error=invalid_token');
  exit;
}

$stmt = $pdo->prepare("
    SELECT id, name, employee_id, email
    FROM users
    WHERE setup_token = :token
      AND setup_token_expires > NOW()
      AND password IS NULL
    LIMIT 1
");
$stmt->execute([':token' => $token]);
$user = $stmt->fetch();

if (!$user) {
  header('Location: login?error=token_expired');
  exit;
}

$error = $_GET['error'] ?? '';
$errorMessages = [
  'mismatch' => 'Passwords do not match.',
  'short' => 'Password must be at least 8 characters.',
  'failed' => 'Something went wrong. Please try again.',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Set Your Password — YADIN Safety</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="../assets/login.css" />
  <link rel="icon" href="../assets/logo.png" />
  <style>
    .welcome-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #e8f5e9;
      color: #2e7d32;
      border: 1px solid #c8e6c9;
      border-radius: 20px;
      padding: 6px 14px;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 16px;
    }

    .user-info-card {
      background: #f8f9fa;
      border: 1px solid #e0e0e0;
      border-radius: 10px;
      padding: 14px 18px;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .user-info-avatar {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: #2563eb;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      font-weight: 700;
      flex-shrink: 0;
    }

    .user-info-name {
      font-weight: 700;
      font-size: 15px;
      color: #333;
      margin: 0 0 2px;
    }

    .user-info-id {
      font-size: 12px;
      color: #888;
      margin: 0;
    }

    .strength-bar {
      height: 4px;
      border-radius: 2px;
      background: #e0e0e0;
      margin-top: 8px;
      overflow: hidden;
    }

    .strength-fill {
      height: 100%;
      border-radius: 2px;
      transition: width 0.3s, background 0.3s;
      width: 0%;
    }

    .password-rules {
      font-size: 12px;
      color: #888;
      margin-top: 6px;
    }
  </style>
</head>

<body>
  <div class="login-wrapper">
    <div class="login-card">
      <div class="login-logo"><img src="../assets/logo.png" alt="YADIN" class="login-logo__img" /></div>
      <div style="text-align:center;margin-bottom:8px;">
        <span class="welcome-badge">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
            <polyline points="22 4 12 14.01 9 11.01" />
          </svg>
          Welcome to YADIN Safety System
        </span>
      </div>
      <h1 class="login-title">Set Your Password</h1>
      <p class="login-sub">Your account is ready. Please create a password to continue.</p>
      <div class="user-info-card">
        <div class="user-info-avatar">
          <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
        </div>
        <div>
          <p class="user-info-name">
            <?php echo htmlspecialchars($user['name']); ?>
          </p>
          <p class="user-info-id">
            <?php echo htmlspecialchars($user['employee_id']); ?> ·
            <?php echo htmlspecialchars($user['email']); ?>
          </p>
        </div>
      </div>
      <?php if ($error && isset($errorMessages[$error])): ?>
        <div class="alert alert--error" style="margin-bottom:16px;">
          <?php echo $errorMessages[$error]; ?>
        </div>
      <?php endif; ?>
      <form method="POST" action="set_password_process">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>" />
        <div class="form-group">
          <label class="form-label">New Password <span class="required">*</span></label>
          <input type="password" name="new_password" id="newPassword" class="form-input" placeholder="Min. 8 characters"
            required minlength="8" oninput="checkStrength(this.value)" />
          <div class="strength-bar">
            <div class="strength-fill" id="strengthFill"></div>
          </div>
          <p class="password-rules">At least 8 characters. Mix letters, numbers and symbols.</p>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password <span class="required">*</span></label>
          <input type="password" name="confirm_password" class="form-input" placeholder="Repeat your password" required
            minlength="8" />
        </div>
        <button type="submit" class="btn-submit">
          Set Password & Continue
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
            <line x1="5" y1="12" x2="19" y2="12" />
            <polyline points="12 5 19 12 12 19" />
          </svg>
        </button>
      </form>
    </div>
  </div>
  <script>
    function checkStrength(val) {
      const fill = document.getElementById('strengthFill');
      let score = 0;
      if (val.length >= 6) score++;
      if (val.length >= 10) score++;
      if (/[A-Z]/.test(val)) score++;
      if (/[0-9]/.test(val)) score++;
      if (/[^A-Za-z0-9]/.test(val)) score++;
      const colors = ['#e74c3c', '#e67e22', '#f39c12', '#2ecc71', '#27ae60'];
      const widths = ['20%', '40%', '60%', '80%', '100%'];
      fill.style.width = score > 0 ? widths[Math.min(score - 1, 4)] : '0%';
      fill.style.background = colors[score - 1] || '#e0e0e0';
    }
  </script>
</body>

</html>