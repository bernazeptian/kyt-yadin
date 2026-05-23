<?php
session_start();
require_once '../config/db.php';
if (isset($_SESSION['user_id'])) {
  header('Location: ../index');
  exit;
}
$error = $_GET['error'] ?? '';
$errors = [
  'invalid'       => 'Invalid Employee ID or password.',
  'inactive'      => 'Your account is inactive. Contact administrator.',
  'required'      => 'Please fill in all fields.',
  'ratelimit'     => 'Too many failed attempts. Please try again in 15 minutes.',
  'token_expired' => 'Your setup link has expired. Please contact the administrator to resend it.',
  'invalid_token' => 'Invalid setup link. Please contact the administrator.',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login — KYT Yadin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="../assets/login.css" />
</head>

<body>

  <div class="login-wrap">
    <div class="login-card">

      <div class="login-logo">
        <img src="../assets/logo.png" alt="Yanmar" />
        <div>
          <div class="login-logo__text">Safety Form Yadin</div>
          <div class="login-logo__sub">Yanmar Safety System</div>
        </div>
      </div>

      <h1 class="login-title">Welcome back</h1>
      <p class="login-sub">Sign in with your Employee ID</p>

      <?php if ($error && isset($errors[$error])): ?>
        <div class="alert">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
          <?php echo $errors[$error]; ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="login_process">
        <?php echo csrf_field(); ?>

        <div class="form-group">
          <label class="form-label" for="employee_id">Employee ID</label>
          <input type="text" class="form-input" id="employee_id" name="employee_id" placeholder="e.g. EMP001"
            value="<?php echo htmlspecialchars($_POST['employee_id'] ?? ''); ?>" autocomplete="username" required
            autofocus />
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="pw-wrap">
            <input type="password" class="form-input" id="password" name="password" placeholder="Enter your password"
              autocomplete="current-password" required />
            <button type="button" class="pw-toggle" id="pwToggle" title="Show/hide password">
              <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18"
                height="18">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
            </button>
          </div>
        </div>

        <a href="forgot" class="forgot-link">Forgot password?</a>

        <button type="submit" class="btn-submit">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
            <polyline points="10 17 15 12 10 7" />
            <line x1="15" y1="12" x2="3" y2="12" />
          </svg>
          Sign In
        </button>

      </form>
    </div>

    <div class="login-footer">
      &copy; <?php echo date('Y'); ?> Yanmar · KYT Yadin Safety System
    </div>
  </div>

  <script>
    // Toggle password visibility
    const pwToggle = document.getElementById('pwToggle');
    const pwInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    pwToggle.addEventListener('click', () => {
      const isHidden = pwInput.type === 'password';
      pwInput.type = isHidden ? 'text' : 'password';
      eyeIcon.innerHTML = isHidden
        ? `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`
        : `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
    });
  </script>

</body>

</html>