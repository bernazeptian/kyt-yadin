<?php
session_start();
require_once '../config/db.php';
require_once '../config/mail.php';

if (!isset($_SESSION['user_id'])) {
  header('Location: ../auth/login');
  exit;
}

$id = (int) ($_GET['id'] ?? 0);
$role = (int) $_SESSION['role'];
$uid = (int) $_SESSION['user_id'];

if (!$id) {
  header('Location: index');
  exit;
}

// ── Fetch report ──────────────────────────────
$stmt = $pdo->prepare("
    SELECT r.*,
           d.name AS dept_name,
           l.name AS loc_name,
           u.name AS reporter_name,
           u.employee_id AS reporter_emp_id,
           pic_dept.name AS pic_dept_name,
           pic_dept.employee_id AS pic_dept_emp_id,
           pic_area.name AS pic_area_name,
           pic_area.employee_id AS pic_area_emp_id
    FROM hiyari_reports r
    LEFT JOIN departments d ON r.department_id = d.id
    LEFT JOIN locations   l ON r.location_id   = l.id
    LEFT JOIN users       u ON r.created_by    = u.id
    LEFT JOIN users pic_dept ON d.head_id      = pic_dept.id
    LEFT JOIN users pic_area ON l.pic_area_id  = pic_area.id
    WHERE r.id = :id
");
$stmt->execute([':id' => $id]);
$report = $stmt->fetch();

if (!$report) {
  header('Location: index');
  exit;
}

// Check if user is assigned to a corrective action on this report
$assigned_check = $pdo->prepare("
    SELECT COUNT(*) FROM corrective_actions 
    WHERE report_id = :report_id AND pic_user_id = :uid
");
$assigned_check->execute([':report_id' => $id, ':uid' => $uid]);
$is_assigned = (int) $assigned_check->fetchColumn() > 0;

// Access control — admin, reporter, or assigned user can view
$is_admin = $role <= 2;
$is_reporter = $uid === (int) $report['created_by'];

if (!$is_admin && !$is_reporter && !$is_assigned) {
  header('Location: index');
  exit;
}

// ── Fetch photos ──────────────────────────────
$photos = $pdo->prepare("SELECT * FROM hiyari_report_photos WHERE report_id = :id ORDER BY created_at");
$photos->execute([':id' => $id]);
$photos = $photos->fetchAll();

// ── Fetch comments ────────────────────────────
$comments = $pdo->prepare("
    SELECT c.*, u.name AS commenter_name, u.role AS commenter_role, u.employee_id AS commenter_emp_id
    FROM hiyari_report_comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.report_id = :id
    ORDER BY c.created_at ASC
");
$comments->execute([':id' => $id]);
$comments = $comments->fetchAll();

// ── Fetch corrective actions ──────────────────
$actions = $pdo->prepare("
    SELECT ca.*, u.name AS assigned_name,
           ca.action_desc AS description,
           ca.pic_user_id AS assigned_to
    FROM corrective_actions ca
    LEFT JOIN users u ON ca.pic_user_id = u.id
    WHERE ca.report_id = :id
    ORDER BY ca.created_at ASC
");
$actions->execute([':id' => $id]);
$actions = $actions->fetchAll();

// ── Fetch users for assign dropdown ──────────
$all_users = $pdo->query("SELECT id, name, employee_id FROM users WHERE is_active = 1 ORDER BY name")->fetchAll();

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// ── Risk config ───────────────────────────────
$risk_config = [
  'low' => ['label' => 'Low', 'class' => 'risk-low', 'color' => '#1e8e3e'],
  'medium' => ['label' => 'Medium', 'class' => 'risk-medium', 'color' => '#1a73e8'],
  'high' => ['label' => 'High', 'class' => 'risk-high', 'color' => '#f9ab00'],
  'extreme' => ['label' => 'Extreme', 'class' => 'risk-extreme', 'color' => '#d93025'],
];

$cat_labels = [
  'near_miss' => 'Near Miss',
  'unsafe_action' => 'Unsafe Act',
  'unsafe_condition' => 'Unsafe Condition',
];

$status_labels = [
    'pending_review' => 'Pending Review',
    'active'         => 'Active',
    'overdue'        => 'Overdue',
    'completed'      => 'Completed',
    'closed'         => 'Closed',
    'open'           => 'Open',
    'in_progress'    => 'In Progress',
];
$status_badges = [
    'pending_review' => 'badge--pending',
    'active'         => 'badge--open',
    'overdue'        => 'badge--extreme',
    'completed'      => 'badge--closed',
    'closed'         => 'badge--closed',
    'open'           => 'badge--open',
    'in_progress'    => 'badge--progress',
];
$role_labels = [1 => 'Super Admin', 2 => 'Admin', 3 => 'User'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars($report['report_number']); ?> — Hiyari Hatto</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="../assets/dashboard.css" />
  <link rel="icon" href="../assets/logo.png" />
  <link rel="stylesheet" href="../assets/view.css?v=3" />
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
      <a href="index" class="nav-item nav-item--active" data-tooltip="Hiyari Hatto">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
          <line x1="12" y1="9" x2="12" y2="13" />
          <line x1="12" y1="17" x2="12.01" y2="17" />
        </svg>
      </a>
      <?php if ($role <= 2): ?>
        <a href="../master/index" class="nav-item" data-tooltip="Master Data">
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
        <a href="index" class="back-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
            <polyline points="15 18 9 12 15 6" />
          </svg>
        </a>
        <div>
          <h1 class="topbar__title"><?php echo htmlspecialchars($report['report_number']); ?></h1>
          <p class="topbar__sub">Hiyari Hatto Report</p>
        </div>
      </div>
      <div class="topbar__right">
        <div class="topbar__date"><?php echo date('l, d F Y'); ?></div>
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
                  <?php echo $role_labels[$role] ?? 'Staff'; ?>
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
            'status_updated' => 'Status updated successfully!',
            'comment_added' => 'Comment added successfully!',
            'action_added' => 'Corrective action added successfully!',
            'action_updated' => 'Corrective action updated successfully!',
          ];
          echo $msgs[$success] ?? 'Saved!';
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
          $err_msgs = [
            'upload' => 'Photo upload failed due to server limits. Please try a smaller photo.',
            'invalid_file' => 'Invalid photo type or file exceeds 5MB.',
            'upload_failed' => 'Failed to save the photo on the server.',
            '1' => 'Something went wrong. Please try again.',
          ];
          echo $err_msgs[$error] ?? 'Something went wrong. Please try again.';
          ?>
        </div>
      <?php endif; ?>

      <div class="view-layout">

        <!-- ══════════════════════════════
             LEFT — Report Details
        ══════════════════════════════ -->
        <div class="view-left">

          <!-- ── Gunawan Review Panel (SuperAdmin only, pending_review) ── -->
          <?php if ($role === 1 && $report['status'] === 'pending_review'): ?>
          <div class="view-card" style="border:2px solid #e74c3c;">
            <div class="view-card__header" style="background:#fdf2f2;">
              <div class="view-card__icon view-card__icon--red">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
              </div>
              <h2 class="view-card__title" style="color:#e74c3c;">⏳ Pending Your Review & Approval</h2>
            </div>
            <div class="view-card__body">
              <p style="font-size:13px;color:#666;margin:0 0 16px;">Review and edit the report if needed, set the due date, then approve or reject it.</p>
              <form method="POST" action="approve" id="reviewForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="report_id" value="<?php echo $id; ?>" />
                <input type="hidden" name="action" id="reviewAction" value="approve" />
                <div class="form-group" style="margin-bottom:14px;">
                  <label class="form-label" style="font-size:13px;font-weight:600;">Edit Description (optional)</label>
                  <textarea name="description" class="form-textarea" rows="3" style="font-size:13px;"
                    placeholder="Leave blank to keep original..."><?php echo htmlspecialchars($report['description']); ?></textarea>
                </div>
                <?php if (!empty($report['safe_action'])): ?>
                <div class="form-group" style="margin-bottom:14px;">
                  <label class="form-label" style="font-size:13px;font-weight:600;">Edit Safe Action (optional)</label>
                  <textarea name="safe_action" class="form-textarea" rows="3" style="font-size:13px;"
                    placeholder="Leave blank to keep original..."><?php echo htmlspecialchars($report['safe_action']); ?></textarea>
                </div>
                <?php endif; ?>
                <div class="form-group" style="margin-bottom:14px;" id="dueDateGroup">
                  <label class="form-label" style="font-size:13px;font-weight:600;">Due Date <span style="color:#e74c3c;">*</span></label>
                  <input type="date" name="due_date" id="dueDateInput" class="form-input" min="<?php echo date('Y-m-d'); ?>" />
                  <span style="font-size:12px;color:#888;">Deadline for corrective actions</span>
                </div>
                <div class="form-group" style="margin-bottom:16px;display:none;" id="rejectReasonGroup">
                  <label class="form-label" style="font-size:13px;font-weight:600;">Reject Reason <span style="color:#e74c3c;">*</span></label>
                  <textarea name="reject_reason" id="rejectReason" class="form-textarea" rows="3"
                    placeholder="Explain why this report is being rejected..."></textarea>
                </div>
                <div style="display:flex;gap:10px;">
                  <button type="button" onclick="submitApprove()"
                    style="flex:1;display:inline-flex;align-items:center;justify-content:center;gap:8px;background:#27ae60;color:#fff;border:none;border-radius:8px;padding:10px 16px;font-size:14px;font-weight:600;cursor:pointer;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M9 11l3 3L22 4"/></svg>
                    Approve & Release
                  </button>
                  <button type="button" onclick="showReject()"
                    style="flex:1;display:inline-flex;align-items:center;justify-content:center;gap:8px;background:#e74c3c;color:#fff;border:none;border-radius:8px;padding:10px 16px;font-size:14px;font-weight:600;cursor:pointer;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    Reject Report
                  </button>
                </div>
                <div style="display:none;margin-top:12px;" id="rejectSubmitGroup">
                  <button type="button" onclick="submitReject()"
                    style="width:100%;display:inline-flex;align-items:center;justify-content:center;gap:8px;background:#e74c3c;color:#fff;border:none;border-radius:8px;padding:10px 16px;font-size:14px;font-weight:600;cursor:pointer;">
                    Confirm Rejection
                  </button>
                </div>
              </form>
              <script>
              function submitApprove() {
                const due = document.getElementById('dueDateInput').value;
                if (!due) { alert('Please set a due date before approving.'); return; }
                if (!confirm('Approve and release this report? All parties will be notified.')) return;
                document.getElementById('reviewAction').value = 'approve';
                document.getElementById('reviewForm').submit();
              }
              function showReject() {
                document.getElementById('dueDateGroup').style.display = 'none';
                document.getElementById('rejectReasonGroup').style.display = 'block';
                document.getElementById('rejectSubmitGroup').style.display = 'block';
              }
              function submitReject() {
                const reason = document.getElementById('rejectReason').value.trim();
                if (!reason) { alert('Please provide a reason for rejection.'); return; }
                if (!confirm('Reject this report? The reporter will be notified.')) return;
                document.getElementById('reviewAction').value = 'reject';
                document.getElementById('reviewForm').submit();
              }
              </script>
            </div>
          </div>
          <?php endif; ?>

          <!-- Pending Review Notice for non-superadmin -->
          <?php if ($role > 1 && $report['status'] === 'pending_review'): ?>
          <div class="view-card" style="border:1px solid #f39c12;">
            <div class="view-card__body" style="padding:16px 20px;">
              <p style="margin:0;font-size:13px;color:#856404;background:#fff3cd;padding:12px 16px;border-radius:8px;">
                ⏳ This report is currently <strong>Pending Review</strong> by management. It will be released once approved.
              </p>
            </div>
          </div>
          <?php endif; ?>

          <!-- Report Info Card -->
          <div class="view-card">
            <div class="view-card__header">
              <div class="view-card__icon view-card__icon--blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                  <polyline points="14 2 14 8 20 8" />
                </svg>
              </div>
              <h2 class="view-card__title">Report Information</h2>
              <span class="badge <?php echo $status_badges[$report['status']] ?? ''; ?> ms-auto">
                <?php echo $status_labels[$report['status']] ?? ucfirst($report['status']); ?>
              </span>
            </div>
            <div class="view-card__body">
              <div class="info-grid">
                <div class="info-item">
                  <span class="info-label">Report Number</span>
                  <span class="info-value report-num"><?php echo htmlspecialchars($report['report_number']); ?></span>
                </div>
                <div class="info-item">
                  <span class="info-label">Report Date</span>
                  <span class="info-value"><?php echo date('d F Y', strtotime($report['report_date'])); ?></span>
                </div>
                <div class="info-item">
                  <span class="info-label">Department</span>
                  <span class="info-value">
                    <?php echo htmlspecialchars($report['dept_name'] ?? '—'); ?>
                    <?php if (!empty($report['pic_dept_name'])): ?>
                      <br><small style="color:#888;font-size:12px;">
                        PIC: <?php echo htmlspecialchars($report['pic_dept_emp_id']); ?> — <?php echo htmlspecialchars($report['pic_dept_name']); ?>
                      </small>
                    <?php endif; ?>
                  </span>
                </div>
                <div class="info-item">
                  <span class="info-label">Location</span>
                  <span class="info-value">
                    <?php echo htmlspecialchars($report['loc_name'] ?? '—'); ?>
                    <?php if (!empty($report['pic_area_name'])): ?>
                      <br><small style="color:#888;font-size:12px;">
                        PIC: <?php echo htmlspecialchars($report['pic_area_emp_id']); ?> — <?php echo htmlspecialchars($report['pic_area_name']); ?>
                      </small>
                    <?php endif; ?>
                  </span>
                </div>
                <div class="info-item">
                  <span class="info-label">Category</span>
                  <span class="info-value">
                    <span class="badge badge--<?php echo $report['category']; ?>">
                      <?php echo $cat_labels[$report['category']] ?? $report['category']; ?>
                    </span>
                  </span>
                </div>
                <div class="info-item">
                  <span class="info-label">Reported By</span>
                  <span class="info-value"><?php echo htmlspecialchars($report['reporter_name'] ?? '—'); ?> <span
                      style="color:var(--text-secondary);font-size:12px">(<?php echo htmlspecialchars($report['reporter_emp_id'] ?? ''); ?>)</span></span>
                </div>
                <div class="info-item">
                  <span class="info-label">Submitted</span>
                  <span class="info-value"><?php echo date('d F Y, H:i', strtotime($report['created_at'])); ?></span>
                </div>
              </div>

              <div class="info-item info-item--full">
                <span class="info-label">Description</span>
                <p class="info-desc"><?php echo nl2br(htmlspecialchars($report['description'])); ?></p>
              </div>
              <?php if (!empty($report['safe_action'])): ?>
              <div class="info-item info-item--full">
                <span class="info-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="vertical-align:middle;margin-right:4px;color:#27ae60"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                  Safe Action (Tindakan Karantina)
                </span>
                <div style="background:#f0fff4;padding:12px 16px;border-radius:8px;border-left:4px solid #27ae60;font-size:14px;color:#333;line-height:1.6;margin-top:6px;">
                  <?php echo nl2br(htmlspecialchars($report['safe_action'])); ?>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Risk Assessment Card -->
          <div class="view-card">
            <div class="view-card__header">
              <div class="view-card__icon view-card__icon--red">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                  <line x1="12" y1="9" x2="12" y2="13" />
                  <line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
              </div>
              <h2 class="view-card__title">Risk Assessment</h2>
            </div>
            <div class="view-card__body">
              <div class="risk-display risk-display--<?php echo $report['risk_level']; ?>">
                <div class="risk-display__level"><?php echo strtoupper($report['risk_level']); ?></div>
                <div class="risk-display__meta">
                  <span>Likelihood: <strong><?php echo $report['likelihood'] ?? '—'; ?></strong></span>
                  <span>Severity: <strong><?php echo $report['severity'] ?? '—'; ?></strong></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Photos Card -->
          <?php if (!empty($photos)): ?>
            <div class="view-card">
              <div class="view-card__header">
                <div class="view-card__icon view-card__icon--yellow">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                    <rect x="3" y="3" width="18" height="18" rx="2" />
                    <circle cx="8.5" cy="8.5" r="1.5" />
                    <polyline points="21 15 16 10 5 21" />
                  </svg>
                </div>
                <h2 class="view-card__title">Photos <span class="table-count"><?php echo count($photos); ?></span></h2>
              </div>
              <div class="view-card__body">
                <div class="photo-grid">
                  <?php foreach ($photos as $p): ?>
                    <a href="../<?php echo htmlspecialchars($p['file_path']); ?>" target="_blank" class="photo-thumb-link">
                      <img src="../<?php echo htmlspecialchars($p['file_path']); ?>" alt="Photo" class="photo-thumb-img" />
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <!-- Corrective Actions Card -->
          <div class="view-card">
            <div class="view-card__header">
              <div class="view-card__icon view-card__icon--green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                  <polyline points="9 11 12 14 22 4" />
                  <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                </svg>
              </div>
              <h2 class="view-card__title">Corrective Actions <span
                  class="table-count"><?php echo count($actions); ?></span></h2>
            </div>
            <div class="view-card__body">
              <?php if (empty($actions)): ?>
                <p class="empty-text">No corrective actions yet.</p>
              <?php else: ?>
                <div class="actions-list">
                  <?php foreach ($actions as $a): ?>
                    <div class="action-item">
                      <div class="action-item__header">
                        <span class="action-item__num">#<?php echo $loop ?? ($loop = 1);
                        $loop++; ?></span>
                        <span
                          class="badge <?php echo $a['status'] === 'done' ? 'badge--closed' : ($a['status'] === 'progress' ? 'badge--progress' : 'badge--open'); ?>">
                          <?php echo $a['status'] === 'done' ? 'Done' : ($a['status'] === 'progress' ? 'In Progress' : 'Pending'); ?>
                        </span>
                        <?php if ($a['due_date']): ?>
                          <span class="action-item__due">Due: <?php echo date('d M Y', strtotime($a['due_date'])); ?></span>
                        <?php endif; ?>
                        <?php
                        // Admin can update any action
                        // Assigned user can only update their own action
                        $can_update_action = $is_admin || (int) $a['assigned_to'] === $uid;
                        ?>
                        <?php if ($can_update_action): ?>
                          <div class="action-item__btns">
                            <?php if ($a['status'] !== 'progress' && $a['status'] !== 'done'): ?>
                              <a href="action_update?id=<?php echo $a['id']; ?>&report_id=<?php echo $id; ?>&status=progress"
                                class="action-status-btn action-status-btn--progress"
                                onclick="return confirm('Mark as In Progress?')">
                                In Progress
                              </a>
                            <?php endif; ?>
                            <?php if ($a['status'] !== 'done'): ?>
                              <a href="action_update?id=<?php echo $a['id']; ?>&report_id=<?php echo $id; ?>&status=done"
                                class="action-status-btn action-status-btn--done" onclick="return confirm('Mark as Done?')">
                                ✓ Done
                              </a>
                            <?php endif; ?>
                            <?php if ($a['status'] === 'done' && $is_admin): ?>
                              <a href="action_update?id=<?php echo $a['id']; ?>&report_id=<?php echo $id; ?>&status=open"
                                class="action-status-btn action-status-btn--reopen"
                                onclick="return confirm('Reopen this action?')">
                                Reopen
                              </a>
                            <?php endif; ?>
                          </div>
                        <?php endif; ?>
                      </div>
                      <p class="action-item__desc"><?php echo nl2br(htmlspecialchars($a['description'])); ?></p>
                      <?php if (!empty($a['image_path'])): ?>
                        <a href="../<?php echo htmlspecialchars($a['image_path']); ?>" target="_blank">
                          <img src="../<?php echo htmlspecialchars($a['image_path']); ?>" alt="Action Image"
                            style="max-width:100%;max-height:200px;width:auto;height:auto;margin-top:8px;border-radius:8px;border:1px solid #e0e0e0;display:block;object-fit:contain;" />
                        </a>
                      <?php endif; ?>
                      <?php if ($a['assigned_name']): ?>
                        <span class="action-item__assign">Assigned to:
                          <strong><?php echo htmlspecialchars($a['assigned_name']); ?></strong></span>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if ($is_admin): ?>
                <form method="POST" action="action_store" class="add-action-form" enctype="multipart/form-data">
                  <input type="hidden" name="report_id" value="<?php echo $id; ?>" />
                  <?php echo csrf_field(); ?>
                  <div class="form-group">
                    <label class="form-label">Add Corrective Action</label>
                    <textarea name="description" class="form-textarea" rows="2"
                      placeholder="Describe the corrective action..." required></textarea>
                  </div>
                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">Assign To</label>
                      <select name="assigned_to" class="form-select">
                        <option value="">-- Select User --</option>
                        <?php foreach ($all_users as $u): ?>
                          <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Due Date</label>
                      <input type="date" name="due_date" class="form-input" />
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Attach Image <span style="font-weight:400;color:#888;">(optional, max
                        5MB)</span></label>
                    <input type="file" name="action_image" class="form-input"
                      accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewActionImage(this)" />
                    <img id="actionImagePreview" src="" alt="Preview"
                      style="display:none;margin-top:8px;max-height:160px;border-radius:6px;border:1px solid #ddd;" />
                  </div>
                  <button type="submit" class="btn-add">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                      <line x1="12" y1="5" x2="12" y2="19" />
                      <line x1="5" y1="12" x2="19" y2="12" />
                    </svg>
                    Add Action
                  </button>
                </form>
                <script>
                  function previewActionImage(input) {
                    const preview = document.getElementById('actionImagePreview');
                    if (input.files && input.files[0]) {
                      const reader = new FileReader();
                      reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
                      reader.readAsDataURL(input.files[0]);
                    } else {
                      preview.src = ''; preview.style.display = 'none';
                    }
                  }
                </script>
              <?php endif; ?>
            </div>
          </div>

        </div><!-- /view-left -->

        <!-- ══════════════════════════════
             RIGHT — Status + Comments
        ══════════════════════════════ -->
        <div class="view-right">

          <!-- Status Card — admin only -->
          <?php if ($is_admin): ?>
            <div class="view-card">
              <div class="view-card__header">
                <div class="view-card__icon view-card__icon--blue">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                    <polyline points="23 4 23 10 17 10" />
                    <polyline points="1 20 1 14 7 14" />
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                  </svg>
                </div>
                <h2 class="view-card__title">Update Status</h2>
              </div>
              <div class="view-card__body">
                <form method="POST" action="status_update">
                  <input type="hidden" name="report_id" value="<?php echo $id; ?>" />
                  <?php echo csrf_field(); ?>
                  <div class="status-btns">
                    <button type="submit" name="status" value="open"
                      class="status-btn <?php echo $report['status'] === 'open' ? 'status-btn--active status-btn--red' : ''; ?>">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                      </svg>
                      Open
                    </button>
                    <button type="submit" name="status" value="in_progress"
                      class="status-btn <?php echo $report['status'] === 'in_progress' ? 'status-btn--active status-btn--yellow' : ''; ?>">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <polyline points="23 4 23 10 17 10" />
                        <polyline points="1 20 1 14 7 14" />
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                      </svg>
                      In Progress
                    </button>
                    <button type="submit" name="status" value="closed"
                      class="status-btn <?php echo $report['status'] === 'closed' ? 'status-btn--active status-btn--green' : ''; ?>">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <polyline points="20 6 9 17 4 12" />
                      </svg>
                      Closed
                    </button>
                  </div>
                </form>
              </div>
            </div>
          <?php endif; ?>

          <!-- Comments Card -->
          <div class="view-card view-card--comments">
            <div class="view-card__header">
              <div class="view-card__icon view-card__icon--blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                </svg>
              </div>
              <h2 class="view-card__title">Comments <span class="table-count"><?php echo count($comments); ?></span>
              </h2>
            </div>
            <div class="view-card__body">

              <!-- Comments List -->
              <div class="comments-list" id="commentsList">
                <?php if (empty($comments)): ?>
                  <p class="empty-text">No comments yet.</p>
                <?php else: ?>
                  <?php foreach ($comments as $c):
                    $is_own = $c['user_id'] == $uid;
                    $is_admin_comment = (int) $c['commenter_role'] <= 2;
                    ?>
                    <div
                      class="comment <?php echo $is_own ? 'comment--own' : ''; ?> <?php echo $is_admin_comment ? 'comment--admin' : ''; ?>">
                      <div class="comment__avatar">
                        <?php echo strtoupper(substr($c['commenter_name'] ?? 'U', 0, 1)); ?>
                      </div>
                      <div class="comment__body">
                        <div class="comment__header">
                          <span class="comment__name"><?php echo htmlspecialchars($c['commenter_name'] ?? '—'); ?></span>
                          <?php if ($is_admin_comment): ?>
                            <span class="comment__role-badge">Admin</span>
                          <?php endif; ?>
                          <span class="comment__time"><?php echo date('d M Y, H:i', strtotime($c['created_at'])); ?></span>
                        </div>
                        <p class="comment__text"><?php echo nl2br(htmlspecialchars($c['comment'])); ?></p>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <!-- Add Comment — reporter, admin, or assigned user -->
              <?php if ($is_admin || $is_reporter || $is_assigned): ?>
                <form method="POST" action="comment_store" class="comment-form">
                  <input type="hidden" name="report_id" value="<?php echo $id; ?>" />
                  <?php echo csrf_field(); ?>
                  <div class="comment-input-wrap">
                    <div class="comment-form__avatar">
                      <?php echo strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)); ?>
                    </div>
                    <textarea name="comment" class="comment-input" rows="2"
                      placeholder="<?php echo $is_admin ? 'Add a comment or feedback...' : 'Add a comment...'; ?>"
                      required></textarea>
                  </div>
                  <div class="comment-form__footer">
                    <button type="submit" class="btn-add">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <line x1="22" y1="2" x2="11" y2="13" />
                        <polygon points="22 2 15 22 11 13 2 9 22 2" />
                      </svg>
                      Send
                    </button>
                  </div>
                </form>
              <?php endif; ?>

            </div>
          </div>

        </div><!-- /view-right -->
      </div><!-- /view-layout -->

    </div><!-- /content -->
  </main>

  <script src="../assets/avatar.js"></script>
  <script>
    // Auto dismiss alert
    const alertMsg = document.getElementById('alertMsg');
    if (alertMsg) {
      setTimeout(() => alertMsg.style.opacity = '0', 3000);
      setTimeout(() => alertMsg.style.display = 'none', 3500);
    }
    // Scroll to bottom of comments
    const commentsList = document.getElementById('commentsList');
    if (commentsList) commentsList.scrollTop = commentsList.scrollHeight;
  </script>
</body>

</html>