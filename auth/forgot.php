<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}
$sent  = $_GET['sent']  ?? false;
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password — KYT Yadin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/forgot.css"/>
</head>
<body>
<div class="wrap">
  <div class="card">

    <a href="login.php" class="back-link">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
        <polyline points="15 18 9 12 15 6"/>
      </svg>
      Back to login
    </a>

    <div class="icon-wrap">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="26" height="26">
        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
      </svg>
    </div>

    <h1 class="title">Forgot Password</h1>
    <p class="sub">Enter your registered email address and we'll send you a link to reset your password.</p>

    <?php if ($sent): ?>
    <div class="alert alert--success">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
      Reset link sent! Please check your email.
    </div>
    <?php elseif ($error === 'notfound'): ?>
    <div class="alert alert--error">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      No account found with that email address.
    </div>
    <?php elseif ($error === 'fail'): ?>
    <div class="alert alert--error">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      Failed to send email. Please try again or contact administrator.
    </div>
    <?php endif; ?>

    <?php if (!$sent): ?>
    <form method="POST" action="forgot_process.php">
      <div class="form-group">
        <label class="form-label" for="email">Email Address</label>
        <input type="email" class="form-input" id="email" name="email"
          placeholder="your.email@yanmar.com" required autofocus/>
      </div>
      <button type="submit" class="btn-submit">Send Reset Link</button>
    </form>
    <?php endif; ?>

  </div>
  <div class="footer">&copy; <?php echo date('Y'); ?> Yanmar · KYT Yadin Safety System</div>
</div>
</body>
</html>
