<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login');
    exit;
}

$uid     = (int) $_SESSION['user_id'];
$role    = (int) ($_SESSION['role'] ?? 3);
$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

$user = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$user->execute([':id' => $uid]);
$user = $user->fetch();

$roleNames = [1 => 'Super Admin', 2 => 'Admin', 3 => 'User'];
$positions = [
    'President Director', 'Director', 'General Manager', 'Manager',
    'Deputy Manager', 'Assistant Manager', 'Supervisor', 'Staff',
    'Foreman', 'Assistant Foreman', 'Operator',
];
$successMessages = [
    'profile_updated'  => 'Profile updated successfully!',
    'password_changed' => 'Password changed successfully!',
];
$errorMessages = [
    'invalid_password'  => 'Current password is incorrect.',
    'password_mismatch' => 'New passwords do not match.',
    'password_short'    => 'New password must be at least 8 characters.',
    'update_failed'     => 'Update failed. Please try again.',
    'email_exists'      => 'This email is already used by another account.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Profile – KYT Yadin</title>
  <link rel="stylesheet" href="assets/style.css" />
  <link rel="stylesheet" href="assets/dashboard.css" />
  <style>
    .profile-wrap { max-width:640px; margin:32px auto; padding:0 16px; }
    .profile-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; margin-bottom:24px; }
    .profile-card__header { padding:20px 28px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:16px; }
    .profile-card__avatar { width:56px; height:56px; border-radius:50%; background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:700; flex-shrink:0; }
    .profile-card__title { font-size:17px; font-weight:700; color:var(--text-primary); margin:0 0 4px; }
    .profile-card__sub { font-size:13px; color:var(--text-secondary); margin:0; }
    .profile-card__body { padding:24px 28px; }
    .profile-card__section-title { font-size:13px; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin:0 0 16px; }
    .form-group { margin-bottom:16px; }
    .form-label { display:block; font-size:13px; font-weight:600; color:var(--text-primary); margin-bottom:6px; }
    .form-input, .form-select { width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:8px; font-size:14px; color:var(--text-primary); background:var(--bg); box-sizing:border-box; }
    .form-input:focus, .form-select:focus { outline:none; border-color:var(--primary); }
    .form-input[readonly] { background:#f5f5f5; color:var(--text-secondary); cursor:not-allowed; }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .btn-save { display:inline-flex; align-items:center; gap:8px; background:var(--primary); color:#fff; border:none; border-radius:8px; padding:10px 22px; font-size:14px; font-weight:600; cursor:pointer; }
    .btn-save:hover { opacity:0.88; }
    .alert { padding:12px 16px; border-radius:8px; font-size:14px; margin-bottom:20px; }
    .alert--success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
    .alert--error   { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
    .back-link { display:inline-flex; align-items:center; gap:6px; color:var(--text-secondary); text-decoration:none; font-size:14px; margin-bottom:20px; }
    .back-link:hover { color:var(--primary); }
    .divider { border:none; border-top:1px solid var(--border); margin:20px 0; }
  </style>
</head>
<body>
  <main class="main">
    <div class="profile-wrap">

      <a href="index" class="back-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Dashboard
      </a>

      <?php if ($success && isset($successMessages[$success])): ?>
        <div class="alert alert--success">✅ <?php echo $successMessages[$success]; ?></div>
      <?php endif; ?>
      <?php if ($error && isset($errorMessages[$error])): ?>
        <div class="alert alert--error">❌ <?php echo $errorMessages[$error]; ?></div>
      <?php endif; ?>

      <!-- Profile Info -->
      <div class="profile-card">
        <div class="profile-card__header">
          <div class="profile-card__avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
          <div>
            <p class="profile-card__title"><?php echo htmlspecialchars($user['name']); ?></p>
            <p class="profile-card__sub">
              <?php echo htmlspecialchars($user['employee_id']); ?> &nbsp;·&nbsp;
              <?php echo $roleNames[$user['role']] ?? 'User'; ?> &nbsp;·&nbsp;
              <?php echo htmlspecialchars($user['position'] ?? '—'); ?>
            </p>
          </div>
        </div>
        <div class="profile-card__body">
          <p class="profile-card__section-title">Personal Information</p>
          <form method="POST" action="profile_update">
            <?php echo csrf_field(); ?>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Employee ID</label>
                <input type="text" class="form-input" value="<?php echo htmlspecialchars($user['employee_id']); ?>" readonly />
              </div>
              <div class="form-group">
                <label class="form-label">Role</label>
                <input type="text" class="form-input" value="<?php echo $roleNames[$user['role']] ?? 'User'; ?>" readonly />
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Full Name</label>
              <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($user['name']); ?>" required />
            </div>
            <div class="form-group">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required />
            </div>
            <div class="form-group">
              <label class="form-label">Position</label>
              <select name="position" class="form-select">
                <option value="">— Select Position —</option>
                <?php foreach ($positions as $p): ?>
                  <option value="<?php echo $p; ?>" <?php echo ($user['position'] ?? '') === $p ? 'selected' : ''; ?>><?php echo $p; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn-save">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              Save Changes
            </button>
          </form>
        </div>
      </div>

      <!-- Change Password -->
      <div class="profile-card">
        <div class="profile-card__body">
          <p class="profile-card__section-title">Change Password</p>
          <form method="POST" action="profile_password">
            <?php echo csrf_field(); ?>
            <div class="form-group">
              <label class="form-label">Current Password</label>
              <input type="password" name="current_password" class="form-input" placeholder="Enter current password" required />
            </div>
            <hr class="divider" />
            <div class="form-group">
              <label class="form-label">New Password</label>
              <input type="password" name="new_password" class="form-input" placeholder="Min. 8 characters" required minlength="8" />
            </div>
            <div class="form-group">
              <label class="form-label">Confirm New Password</label>
              <input type="password" name="confirm_password" class="form-input" placeholder="Repeat new password" required minlength="8" />
            </div>
            <button type="submit" class="btn-save" style="background:#e67e22;">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              Change Password
            </button>
          </form>
        </div>
      </div>

    </div>
  </main>
</body>
</html>
