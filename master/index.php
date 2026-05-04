<?php
session_start();
require_once '../config/db.php';

// Only superadmin and admin can access
if (!isset($_SESSION['user_id']) || (int) $_SESSION['role'] > 2) {
  header('Location: ../index');
  exit;
}

$active_tab = $_GET['tab'] ?? 'departments';
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
$is_superadmin = (int) $_SESSION['role'] == 1;
$role = (int) ($_SESSION['role'] ?? 3);

// ── DEPARTMENTS ──────────────────────────────────
$departments = $pdo->query("
    SELECT d.*, u.name AS head_name
    FROM departments d
    LEFT JOIN users u ON d.head_id = u.id
    ORDER BY d.name
")->fetchAll();

// ── LOCATIONS ────────────────────────────────────
$locations = $pdo->query("
    SELECT l.* FROM locations l ORDER BY l.name
")->fetchAll();

// ── Users for head dropdown ───────────────────────
$users = $pdo->query("SELECT id, name, employee_id FROM users WHERE is_active = 1 ORDER BY name")->fetchAll();

// ── ALL USERS for users tab ────────────────────────
$all_users = $pdo->query("SELECT id, employee_id, name, email, position, role, is_active, created_at FROM users ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Master Data — KYT Yadin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="../assets/dashboard.css" />
  <link rel="icon" href="../assets/logo.png" />
  <link rel="stylesheet" href="../assets/master.css" />
</head>

<body>

  <aside class="sidebar">
    <div class="sidebar__logo">
      <img src="../assets/logo.png" alt="Yanmar" class="sidebar__logo-img" />
    </div>
    <nav class="sidebar__nav">
      <a href="../index" class="nav-item" data-tooltip="Dashboard">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="7" rx="1" />
          <rect x="14" y="3" width="7" height="7" rx="1" />
          <rect x="3" y="14" width="7" height="7" rx="1" />
          <rect x="14" y="14" width="7" height="7" rx="1" />
        </svg>
      </a>
      <a href="../hiyari/index" class="nav-item" data-tooltip="Hiyari Hatto">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
          <line x1="12" y1="9" x2="12" y2="13" />
          <line x1="12" y1="17" x2="12.01" y2="17" />
        </svg>
      </a>
      <?php if ($role <= 2): ?>
        <a href="../master/index" class="nav-item nav-item--active" data-tooltip="Master Data">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <line x1="8" y1="6" x2="21" y2="6" />
            <line x1="8" y1="12" x2="21" y2="12" />
            <line x1="8" y1="18" x2="21" y2="18" />
            <line x1="3" y1="6" x2="3.01" y2="6" />
            <line x1="3" y1="12" x2="3.01" y2="12" />
            <line x1="3" y1="18" x2="3.01" y2="18" />
          </svg>
        </a>
      <?php endif; ?>
    </nav>
    <div class="sidebar__bottom">
      <a href="../auth/logout" class="nav-item nav-item--logout" data-tooltip="Sign Out">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
          <polyline points="16 17 21 12 16 7" />
          <line x1="21" y1="12" x2="9" y2="12" />
        </svg>
      </a>
    </div>
  </aside>

  <main class="main">
    <header class="topbar">
      <div class="topbar__left">
        <h1 class="topbar__title">Master Data</h1>
        <p class="topbar__sub">Manage departments, locations<?php echo $is_superadmin ? ' and users' : ''; ?></p>
      </div>
      <div class="topbar__right">
        <div class="topbar__date"><?php echo date('l, d F Y'); ?></div>
        <?php if (isset($_SESSION['user_id'])): ?>
          <div style="position:relative">
            <div class="topbar__avatar" id="avatarBtn">
              <?php echo strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="avatar-dropdown" id="avatarDropdown">
              <div class="avatar-dropdown__info">
                <div class="avatar-dropdown__avatar">
                  <?php echo strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)); ?>
                </div>
                <div>
                  <div class="avatar-dropdown__name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?></div>
                  <div class="avatar-dropdown__id">ID: <?php echo htmlspecialchars($_SESSION['employee_id'] ?? '—'); ?>
                  </div>
                  <div class="avatar-dropdown__role">
                    <?php $roleNames = [1 => 'Super Admin', 2 => 'Admin', 3 => 'User'];
                    echo $roleNames[(int) $_SESSION['role']] ?? 'Staff'; ?>
                  </div>
                </div>
              </div>
              <div class="avatar-dropdown__divider"></div>
              <a href="../profile" class="avatar-dropdown__logout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                  <circle cx="12" cy="7" r="4" />
                </svg>
                My Profile
              </a>
              <div class="avatar-dropdown__divider"></div>
              <a href="../auth/logout" class="avatar-dropdown__logout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
                  <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                  <polyline points="16 17 21 12 16 7" />
                  <line x1="21" y1="12" x2="9" y2="12" />
                </svg>
                Sign Out
              </a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </header>

    <div class="content">

      <?php if ($success): ?>
        <div class="alert alert--success" id="alertMsg">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
            <polyline points="20 6 9 17 4 12" />
          </svg>
          <?php
          $msgs = [
            'dept_added' => 'Department added successfully!',
            'dept_updated' => 'Department updated successfully!',
            'dept_deleted' => 'Department deleted successfully!',
            'loc_added' => 'Location added successfully!',
            'loc_updated' => 'Location updated successfully!',
            'loc_deleted' => 'Location deleted successfully!',
            'user_added' => 'User added successfully!',
            'user_updated' => 'User updated successfully!',
            'user_deleted' => 'User deleted successfully!',
            'pw_reset' => 'Password reset to Welcome@1234 successfully!',
          ];
          echo $msgs[$success] ?? 'Saved successfully!';
          ?>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert--error" id="alertMsg">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
          <?php
          $errs = [
            'dept_exists' => 'Department code already exists!',
            'loc_exists' => 'Location code already exists!',
            'in_use' => 'Cannot delete — this record is in use!',
            'emp_exists' => 'Employee ID already exists!',
            'email_exists' => 'Email already exists!',
            'self_delete' => 'You cannot delete your own account!',
            'failed' => 'Something went wrong. Please try again.',
          ];
          echo $errs[$error] ?? 'An error occurred.';
          ?>
        </div>
      <?php endif; ?>

      <!-- ── TABS ── -->
      <div class="tabs">
        <button class="tab <?php echo $active_tab === 'departments' ? 'tab--active' : ''; ?>" data-tab="departments">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
          </svg>
          Departments
        </button>
        <button class="tab <?php echo $active_tab === 'locations' ? 'tab--active' : ''; ?>" data-tab="locations">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
            <circle cx="12" cy="10" r="3" />
          </svg>
          Locations
        </button>
        <?php if ($is_superadmin): ?>
          <button class="tab <?php echo $active_tab === 'users' ? 'tab--active' : ''; ?>" data-tab="users">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
            Users
          </button>
        <?php endif; ?>
      </div>

      <!-- ══════════════════════════════════
           DEPARTMENTS TAB
      ══════════════════════════════════ -->
      <div class="tab-panel <?php echo $active_tab === 'departments' ? 'tab-panel--active' : ''; ?>"
        id="tab-departments">
        <div class="master-layout">
          <div class="master-form-box">
            <div class="master-form-box__header">
              <h2 class="master-form-box__title">Add Department</h2>
            </div>
            <form method="POST" action="dept_store">
              <?php echo csrf_field(); ?>
              <div class="form-group">
                <label class="form-label">Department Code <span class="required">*</span></label>
                <input type="text" name="code" class="form-input" placeholder="e.g. PROD" required maxlength="10"
                  style="text-transform:uppercase" />
                <span class="form-hint">Max 10 characters</span>
              </div>
              <div class="form-group">
                <label class="form-label">Department Name <span class="required">*</span></label>
                <input type="text" name="name" class="form-input" placeholder="e.g. Production" required />
              </div>
              <div class="form-group">
                <label class="form-label">Department Head / PIC</label>
                <select name="head_id" class="form-select">
                  <option value="">-- Select PIC --</option>
                  <?php foreach ($users as $u): ?>
                    <option value="<?php echo $u['id']; ?>">
                      <?php echo htmlspecialchars($u['name'] . ' (' . $u['employee_id'] . ')'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-textarea" rows="3"
                  placeholder="Optional description..."></textarea>
              </div>
              <div class="form-group">
                <label class="toggle-label">
                  <input type="checkbox" name="is_active" value="1" checked class="toggle-input" />
                  <span class="toggle-switch"></span>
                  Active
                </label>
              </div>
              <button type="submit" class="btn-submit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                  <line x1="12" y1="5" x2="12" y2="19" />
                  <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                Add Department
              </button>
            </form>
          </div>
          <div class="master-list-box">
            <div class="master-list-box__header">
              <h2 class="master-list-box__title">Departments <span
                  class="table-count"><?php echo count($departments); ?> records</span></h2>
            </div>
            <?php if (empty($departments)): ?>
              <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                  <circle cx="9" cy="7" r="4" />
                </svg>
                <p>No departments yet</p>
              </div>
            <?php else: ?>
              <div class="table-wrap">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Code</th>
                      <th>Name</th>
                      <th>Head / PIC</th>
                      <th>Description</th>
                      <th>Status</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($departments as $d): ?>
                      <tr>
                        <td><span class="code-badge"><?php echo htmlspecialchars($d['code']); ?></span></td>
                        <td><strong><?php echo htmlspecialchars($d['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($d['head_name'] ?? '—'); ?></td>
                        <td class="td-desc"><?php echo htmlspecialchars($d['description'] ?? '—'); ?></td>
                        <td><span
                            class="badge <?php echo $d['is_active'] ? 'badge--closed' : 'badge--open'; ?>"><?php echo $d['is_active'] ? 'Active' : 'Inactive'; ?></span>
                        </td>
                        <td class="td-actions">
                          <button class="action-btn action-btn--edit"
                            onclick="openEditDept(<?php echo htmlspecialchars(json_encode($d)); ?>)" title="Edit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15"
                              height="15">
                              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                            </svg>
                          </button>
                          <form method="POST" action="dept_delete" style="display:inline"
                            onsubmit="return confirm('Delete this department?')">
                            <input type="hidden" name="id" value="<?php echo $d['id']; ?>" />
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="action-btn action-btn--delete" title="Delete">
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15"
                                height="15">
                                <polyline points="3 6 5 6 21 6" />
                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                <path d="M10 11v6" />
                                <path d="M14 11v6" />
                                <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                              </svg>
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div><!-- /departments tab -->

      <!-- ══════════════════════════════════
           LOCATIONS TAB
      ══════════════════════════════════ -->
      <div class="tab-panel <?php echo $active_tab === 'locations' ? 'tab-panel--active' : ''; ?>" id="tab-locations">
        <div class="master-layout">
          <div class="master-form-box">
            <div class="master-form-box__header">
              <h2 class="master-form-box__title">Add Location</h2>
            </div>
            <form method="POST" action="loc_store">
              <?php echo csrf_field(); ?>
              <div class="form-group">
                <label class="form-label">Location Code <span class="required">*</span></label>
                <input type="text" name="code" class="form-input" placeholder="e.g. LOC001" required maxlength="10"
                  style="text-transform:uppercase" />
                <span class="form-hint">Max 10 characters</span>
              </div>
              <div class="form-group">
                <label class="form-label">Location Name <span class="required">*</span></label>
                <input type="text" name="name" class="form-input" placeholder="e.g. Area A" required />
              </div>
              <div class="form-group">
                <label class="form-label">Department <span class="required">*</span></label>
                <select name="department_id" class="form-select" required>
                  <option value="">-- Select Department --</option>
                  <?php foreach ($departments as $d): ?>
                    <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">PIC Area</label>
                <select name="pic_area_id" class="form-select">
                  <option value="">-- Select PIC Area --</option>
                  <?php foreach ($all_users as $u): ?>
                    <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-textarea" rows="3"
                  placeholder="Optional description..."></textarea>
              </div>
              <div class="form-group">
                <label class="toggle-label">
                  <input type="checkbox" name="is_active" value="1" checked class="toggle-input" />
                  <span class="toggle-switch"></span>
                  Active
                </label>
              </div>
              <button type="submit" class="btn-submit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                  <line x1="12" y1="5" x2="12" y2="19" />
                  <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                Add Location
              </button>
            </form>
          </div>
          <div class="master-list-box">
            <div class="master-list-box__header">
              <h2 class="master-list-box__title">Locations <span class="table-count"><?php echo count($locations); ?>
                  records</span></h2>
            </div>
            <?php if (empty($locations)): ?>
              <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                  <circle cx="12" cy="10" r="3" />
                </svg>
                <p>No locations yet</p>
              </div>
            <?php else: ?>
              <div class="table-wrap">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Code</th>
                      <th>Name</th>
                      <th>PIC Location</th>
                      <th>Description</th>
                      <th>Status</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($locations as $l): ?>
                      <tr>
                        <td><span class="code-badge"><?php echo htmlspecialchars($l['code']); ?></span></td>
                        <td><strong><?php echo htmlspecialchars($l['name']); ?></strong></td>
                        <td>
                          <?php echo htmlspecialchars($l['pic_area_id']); ?>
                        </td>
                        <td class="td-desc"><?php echo htmlspecialchars($l['description'] ?? '—'); ?></td>
                        <td><span
                            class="badge <?php echo $l['is_active'] ? 'badge--closed' : 'badge--open'; ?>"><?php echo $l['is_active'] ? 'Active' : 'Inactive'; ?></span>
                        </td>
                        <td class="td-actions">
                          <button class="action-btn action-btn--edit"
                            onclick="openEditLoc(<?php echo htmlspecialchars(json_encode($l)); ?>)" title="Edit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15"
                              height="15">
                              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                            </svg>
                          </button>
                          <form method="POST" action="loc_delete" style="display:inline"
                            onsubmit="return confirm('Delete this location?')">
                            <input type="hidden" name="id" value="<?php echo $l['id']; ?>" />
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="action-btn action-btn--delete" title="Delete">
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15"
                                height="15">
                                <polyline points="3 6 5 6 21 6" />
                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                <path d="M10 11v6" />
                                <path d="M14 11v6" />
                                <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                              </svg>
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div><!-- /locations tab -->

      <!-- ══════════════════════════════════
           USERS TAB — Super Admin only
      ══════════════════════════════════ -->
      <?php if ($is_superadmin): ?>
        <div class="tab-panel <?php echo $active_tab === 'users' ? 'tab-panel--active' : ''; ?>" id="tab-users">
          <div class="master-layout">
            <div class="master-form-box">
              <div class="master-form-box__header">
                <h2 class="master-form-box__title">Add User</h2>
              </div>
              <form method="POST" action="user_store">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                  <label class="form-label">Employee ID <span class="required">*</span></label>
                  <input type="text" name="employee_id" class="form-input" placeholder="e.g. EMP001" required
                    maxlength="20" />
                </div>
                <div class="form-group">
                  <label class="form-label">Full Name <span class="required">*</span></label>
                  <input type="text" name="name" class="form-input" placeholder="e.g. John Doe" required />
                </div>
                <div class="form-group">
                  <label class="form-label">Email <span class="required">*</span></label>
                  <input type="email" name="email" class="form-input" placeholder="e.g. john@yanmar.com" required />
                </div>
                <div class="form-group">
                  <label class="form-label">Password <span class="required">*</span></label>
                  <input type="password" name="password" class="form-input" placeholder="Min. 8 characters" required
                    minlength="8" />
                </div>
                <div class="form-group">
                  <label class="form-label">Position</label>
                  <select name="position" class="form-select">
                    <option value="">-- Select Position --</option>
                    <option value="President Director">President Director</option>
                    <option value="Director">Director</option>
                    <option value="General Manager">General Manager</option>
                    <option value="Manager">Manager</option>
                    <option value="Deputy Manager">Deputy Manager</option>
                    <option value="Assistant Manager">Assistant Manager</option>
                    <option value="Supervisor">Supervisor</option>
                    <option value="Staff">Staff</option>
                    <option value="Foreman">Foreman</option>
                    <option value="Assistant Foreman">Assistant Foreman</option>
                    <option value="Operator">Operator</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Role <span class="required">*</span></label>
                  <select name="role" class="form-select" required>
                    <option value="">-- Select Role --</option>
                    <option value="1">Super Admin</option>
                    <option value="2">Admin</option>
                    <option value="3">User</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="toggle-label">
                    <input type="checkbox" name="is_active" value="1" checked class="toggle-input" />
                    <span class="toggle-switch"></span>
                    Active
                  </label>
                </div>
                <button type="submit" class="btn-submit">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                  </svg>
                  Add User
                </button>
              </form>
            </div>
            <div class="master-list-box">
              <div class="master-list-box__header">
                <h2 class="master-list-box__title">Users <span class="table-count"><?php echo count($all_users); ?>
                    records</span></h2>
              </div>
              <?php if (empty($all_users)): ?>
                <div class="empty-state">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                  </svg>
                  <p>No users yet</p>
                </div>
              <?php else: ?>
                <div class="table-wrap">
                  <table class="table">
                    <thead>
                      <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Position</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      $roleLabels = [1 => 'Super Admin', 2 => 'Admin', 3 => 'User'];
                      $roleBadges = [1 => 'badge--extreme', 2 => 'badge--high', 3 => 'badge--low'];
                      foreach ($all_users as $u): ?>
                        <tr>
                          <td><span class="code-badge"><?php echo htmlspecialchars($u['employee_id']); ?></span></td>
                          <td><strong><?php echo htmlspecialchars($u['name']); ?></strong></td>
                          <td style="font-size:12px;color:var(--text-secondary)"><?php echo htmlspecialchars($u['email']); ?>
                          </td>
                          <td style="font-size:12px;"><?php echo htmlspecialchars($u['position'] ?? '—'); ?></td>
                          <td><span
                              class="badge <?php echo $roleBadges[(int) $u['role']] ?? 'badge--low'; ?>"><?php echo $roleLabels[(int) $u['role']] ?? 'Staff'; ?></span>
                          </td>
                          <td><span
                              class="badge <?php echo $u['is_active'] ? 'badge--closed' : 'badge--open'; ?>"><?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?></span>
                          </td>
                          <td class="td-actions">
                            <button class="action-btn action-btn--edit"
                              onclick="openEditUser(<?php echo htmlspecialchars(json_encode($u)); ?>)" title="Edit">
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15"
                                height="15">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                              </svg>
                            </button>
                            <a href="user_reset_pw?id=<?php echo $u['id']; ?>" class="action-btn" style="color:var(--orange)"
                              onclick="return confirm('Reset password for <?php echo htmlspecialchars($u['name']); ?>?')"
                              title="Reset Password">
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15"
                                height="15">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                              </svg>
                            </a>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                              <form method="POST" action="user_delete" style="display:inline"
                                onsubmit="return confirm('Delete user <?php echo htmlspecialchars($u['name']); ?>?')">
                                <input type="hidden" name="id" value="<?php echo $u['id']; ?>" />
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="action-btn action-btn--delete" title="Delete">
                                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15"
                                    height="15">
                                    <polyline points="3 6 5 6 21 6" />
                                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                    <path d="M10 11v6" />
                                    <path d="M14 11v6" />
                                    <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                                  </svg>
                                </button>
                              </form>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div><!-- /users tab -->
      <?php endif; ?>

    </div><!-- /content -->
  </main>

  <!-- EDIT DEPARTMENT MODAL -->
  <div class="modal-overlay" id="editDeptModal">
    <div class="modal">
      <div class="modal__header">
        <h3 class="modal__title">Edit Department</h3>
        <button class="modal__close" onclick="closeModal('editDeptModal')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>
      <form method="POST" action="dept_update">
        <input type="hidden" name="id" id="editDeptId" />
        <?php echo csrf_field(); ?>
        <div class="form-group">
          <label class="form-label">Department Code <span class="required">*</span></label>
          <input type="text" name="code" id="editDeptCode" class="form-input" required maxlength="10"
            style="text-transform:uppercase" />
        </div>
        <div class="form-group">
          <label class="form-label">Department Name <span class="required">*</span></label>
          <input type="text" name="name" id="editDeptName" class="form-input" required />
        </div>
        <div class="form-group">
          <label class="form-label">Department Head / PIC</label>
          <select name="head_id" id="editDeptHead" class="form-select">
            <option value="">-- Select PIC --</option>
            <?php foreach ($users as $u): ?>
              <option value="<?php echo $u['id']; ?>">
                <?php echo htmlspecialchars($u['name'] . ' (' . $u['employee_id'] . ')'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" id="editDeptDesc" class="form-textarea" rows="3"></textarea>
        </div>
        <div class="form-group">
          <label class="toggle-label">
            <input type="checkbox" name="is_active" id="editDeptActive" value="1" class="toggle-input" />
            <span class="toggle-switch"></span>
            Active
          </label>
        </div>
        <div class="modal__actions">
          <button type="button" class="btn-cancel" onclick="closeModal('editDeptModal')">Cancel</button>
          <button type="submit" class="btn-submit">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- EDIT LOCATION MODAL -->
  <div class="modal-overlay" id="editLocModal">
    <div class="modal">
      <div class="modal__header">
        <h3 class="modal__title">Edit Location</h3>
        <button class="modal__close" onclick="closeModal('editLocModal')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>
      <form method="POST" action="loc_update">
        <input type="hidden" name="id" id="editLocId" />
        <?php echo csrf_field(); ?>
        <div class="form-group">
          <label class="form-label">Location Code <span class="required">*</span></label>
          <input type="text" name="code" id="editLocCode" class="form-input" required maxlength="10"
            style="text-transform:uppercase" />
        </div>
        <div class="form-group">
          <label class="form-label">Location Name <span class="required">*</span></label>
          <input type="text" name="name" id="editLocName" class="form-input" required />
        </div>
        <div class="form-group">
          <label class="form-label">Department</label>
          <select name="department_id" id="editLocDept" class="form-select">
            <option value="">-- Select Department --</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">PIC Area</label>
          <select name="pic_area_id" id="editLocPicArea" class="form-select">
            <option value="">-- Select PIC Area --</option>
            <?php foreach ($all_users as $u): ?>
              <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" id="editLocDesc" class="form-textarea" rows="3"></textarea>
        </div>
        <div class="form-group">
          <label class="toggle-label">
            <input type="checkbox" name="is_active" id="editLocActive" value="1" class="toggle-input" />
            <span class="toggle-switch"></span>
            Active
          </label>
        </div>
        <div class="modal__actions">
          <button type="button" class="btn-cancel" onclick="closeModal('editLocModal')">Cancel</button>
          <button type="submit" class="btn-submit">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- EDIT USER MODAL -->
  <div class="modal-overlay" id="editUserModal">
    <div class="modal">
      <div class="modal__header">
        <h3 class="modal__title">Edit User</h3>
        <button class="modal__close" onclick="closeModal('editUserModal')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>
      <form method="POST" action="user_update">
        <input type="hidden" name="id" id="editUserId" />
        <?php echo csrf_field(); ?>
        <div class="form-group">
          <label class="form-label">Employee ID <span class="required">*</span></label>
          <input type="text" name="employee_id" id="editUserEmpId" class="form-input" required maxlength="20" />
        </div>
        <div class="form-group">
          <label class="form-label">Full Name <span class="required">*</span></label>
          <input type="text" name="name" id="editUserName" class="form-input" required />
        </div>
        <div class="form-group">
          <label class="form-label">Email <span class="required">*</span></label>
          <input type="email" name="email" id="editUserEmail" class="form-input" required />
        </div>
        <div class="form-group">
          <label class="form-label">Position</label>
          <select name="position" id="editUserPosition" class="form-select">
            <option value="">-- Select Position --</option>
            <option value="President Director">President Director</option>
            <option value="Director">Director</option>
            <option value="General Manager">General Manager</option>
            <option value="Manager">Manager</option>
            <option value="Deputy Manager">Deputy Manager</option>
            <option value="Assistant Manager">Assistant Manager</option>
            <option value="Supervisor">Supervisor</option>
            <option value="Staff">Staff</option>
            <option value="Foreman">Foreman</option>
            <option value="Assistant Foreman">Assistant Foreman</option>
            <option value="Operator">Operator</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Role <span class="required">*</span></label>
          <select name="role" id="editUserRole" class="form-select" required>
            <option value="1">Super Admin</option>
            <option value="2">Admin</option>
            <option value="3">User</option>
          </select>
        </div>
        <div class="form-group">
          <label class="toggle-label">
            <input type="checkbox" name="is_active" id="editUserActive" value="1" class="toggle-input" />
            <span class="toggle-switch"></span>
            Active
          </label>
        </div>
        <div class="modal__actions">
          <button type="button" class="btn-cancel" onclick="closeModal('editUserModal')">Cancel</button>
          <button type="submit" class="btn-submit">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <script src="../assets/avatar.js"></script>
  <script src="../assets/master.js"></script>
</body>

</html>