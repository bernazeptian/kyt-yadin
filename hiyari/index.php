<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
  header('Location: ../auth/login');
  exit;
}

// ── Filters ─────────────────────────────────────
// ── Role-based access ────────────────────────
$role = (int) ($_SESSION['role'] ?? 3);
$uid = (int) ($_SESSION['user_id'] ?? 0);

$filter_status = $_GET['status'] ?? '';
$filter_risk = $_GET['risk'] ?? '';
$filter_category = $_GET['category'] ?? '';
$filter_dept = $_GET['dept'] ?? '';
$filter_search = trim($_GET['search'] ?? '');
$filter_date_range = $_GET['date_range'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_type = $_GET['type'] ?? 'hiyari';
$page = max(1, (int) ($_GET['page'] ?? 1));

// ── Resolve date range to from/to ────────────
$today = date('Y-m-d');
if ($filter_date_range === 'today') {
  $filter_date_from = $today;
  $filter_date_to = $today;
} elseif ($filter_date_range === 'week') {
  $filter_date_from = date('Y-m-d', strtotime('monday this week'));
  $filter_date_to = date('Y-m-d', strtotime('sunday this week'));
} elseif ($filter_date_range === 'month') {
  $filter_date_from = date('Y-m-01');
  $filter_date_to = date('Y-m-t');
} elseif ($filter_date_range === 'year') {
  $filter_date_from = date('Y-01-01');
  $filter_date_to = date('Y-12-31');
}
$per_page = 10;
$offset = ($page - 1) * $per_page;

// ── Build query ──────────────────────────────────
$where = ["1=1"];
$params = [];

if ($filter_type === 'kyt') {
  $where[] = "r.category IN ('unsafe_action', 'unsafe_condition')";
} else {
  $where[] = "r.category = 'near_miss'";
}

if ($filter_status) {
  $where[] = "r.status = :status";
  $params[':status'] = $filter_status;
}
if ($filter_risk) {
  $where[] = "r.risk_level = :risk";
  $params[':risk'] = $filter_risk;
}
if ($filter_category) {
  $where[] = "r.category = :category";
  $params[':category'] = $filter_category;
}
if ($filter_dept) {
  $where[] = "r.department_id = :dept";
  $params[':dept'] = (int) $filter_dept;
}
if ($filter_search) {
  $where[] = "(r.report_number LIKE :search OR r.description LIKE :search)";
  $params[':search'] = '%' . $filter_search . '%';
}
if ($filter_date_from) {
  $where[] = "r.report_date >= :date_from";
  $params[':date_from'] = $filter_date_from;
}
if ($filter_date_to) {
  $where[] = "r.report_date <= :date_to";
  $params[':date_to'] = $filter_date_to;
}

// Users can only see their own reports OR reports assigned to them
if ($role >= 3) {
  $where[] = "(r.created_by = :uid OR r.id IN (
        SELECT report_id FROM corrective_actions WHERE pic_user_id = :uid2
    ))";
  $params[':uid'] = $uid;
  $params[':uid2'] = $uid;
}

$whereStr = implode(" AND ", $where);

// Total count for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM hiyari_reports r WHERE $whereStr");
$countStmt->execute($params);
$total_records = $countStmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Fetch reports
$stmt = $pdo->prepare("
    SELECT r.*,
           d.name AS dept_name,
           l.name AS loc_name,
           u.name AS reporter_name
    FROM hiyari_reports r
    LEFT JOIN departments d ON r.department_id = d.id
    LEFT JOIN locations   l ON r.location_id   = l.id
    LEFT JOIN users       u ON r.created_by    = u.id
    WHERE $whereStr
    ORDER BY r.created_at DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $key => $val)
  $stmt->bindValue($key, $val);
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$reports = $stmt->fetchAll();

// Departments for filter dropdown
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();

// Summary counts
$summary_condition = ($filter_type === 'kyt') ? "category IN ('unsafe_action', 'unsafe_condition')" : "category = 'near_miss'";
$summary = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'open') AS open,
        SUM(status = 'in_progress') AS in_progress,
        SUM(status = 'closed') AS closed,
        SUM(risk_level = 'extreme') AS extreme,
        SUM(risk_level = 'high') AS high
    FROM hiyari_reports
    WHERE $summary_condition
")->fetch();

$success = isset($_GET['success']) && $_GET['success'] == '1';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo $filter_type === 'kyt' ? 'Kiken Yochi Reports' : 'Hiyari Hatto Reports'; ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="../assets/dashboard.css?v=3" />
  <link rel="icon" href="../assets/logo.png" />
  <link rel="stylesheet" href="../assets/hiyari-index.css?v=3" />
</head>

<body>

  <!-- ═══════════════════════════════════════
       SIDEBAR
  ═══════════════════════════════════════ -->
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
      <a href="../hiyari/index" class="nav-item nav-item--active" data-tooltip="Create Report">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
          <line x1="12" y1="9" x2="12" y2="13" />
          <line x1="12" y1="17" x2="12.01" y2="17" />
        </svg>
      </a>
      <?php if ($role <= 2 && isset($_SESSION['user_id'])): ?>
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
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="../auth/logout" class="nav-item nav-item--logout" data-tooltip="Sign Out">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <polyline points="16 17 21 12 16 7" />
            <line x1="21" y1="12" x2="9" y2="12" />
          </svg>
        </a>
      <?php else: ?>
        <a href="auth/login" class="nav-item" data-tooltip="Sign In">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
            <polyline points="10 17 15 12 10 7" />
            <line x1="15" y1="12" x2="3" y2="12" />
          </svg>
        </a>
      <?php endif; ?>
    </div>
  </aside>

  <!-- ═══════════════════════════════════════
       MAIN
  ═══════════════════════════════════════ -->
  <main class="main">

    <header class="topbar">
      <div class="topbar__left">
        <div>
          <h1 class="topbar__title"><?php echo $filter_type === 'kyt' ? 'KYT Reports' : 'Hiyari Hatto Reports'; ?></h1>
          <p class="topbar__sub">
            <?php echo $filter_type === 'kyt' ? 'Unsafe act & unsafe condition tracking' : 'Near miss tracking'; ?>
          </p>
        </div>
      </div>
      <div class="topbar__right">
        <div class="topbar__date"><?php echo date('l, d F Y'); ?></div>
        <?php
        $export_params = http_build_query([
          'type' => $filter_type,
          'status' => $filter_status,
          'risk' => $filter_risk,
          'category' => $filter_category,
          'dept' => $filter_dept,
          'search' => $filter_search,
          'date_from' => $filter_date_from,
          'date_to' => $filter_date_to,
        ]);
        ?>
        <a href="export?format=excel&<?php echo $export_params; ?>" class="btn-new" style="background:#27ae60;"
          title="Export to Excel">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <polyline points="14 2 14 8 20 8" />
            <line x1="12" y1="18" x2="12" y2="12" />
            <line x1="9" y1="15" x2="15" y2="15" />
          </svg>
          Excel
        </a>
        <a href="export?format=csv&<?php echo $export_params; ?>" class="btn-new" style="background:#2980b9;"
          title="Export to CSV">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <polyline points="14 2 14 8 20 8" />
            <line x1="16" y1="13" x2="8" y2="13" />
            <line x1="16" y1="17" x2="8" y2="17" />
            <polyline points="10 9 9 9 8 9" />
          </svg>
          CSV
        </a>
        <a href="<?php echo $filter_type === 'kyt' ? 'create_kiken' : 'create_hiyari'; ?>" class="btn-new">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
          </svg>
          New Report
        </a>
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
              <div class="avatar-dropdown__id">ID:
                <?php echo htmlspecialchars($_SESSION['employee_id'] ?? '—'); ?>
              </div>
              <div class="avatar-dropdown__role">
                <?php $rn = [1 => 'Super Admin', 2 => 'Admin', 3 => 'User'];
                echo $rn[$role] ?? 'User'; ?>
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
    </header>

    <div class="content">

      <?php if ($success): ?>
        <div class="alert alert--success">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
            <polyline points="22 4 12 14.01 9 11.01" />
          </svg>
          Report submitted successfully!
        </div>
      <?php endif; ?>

      <!-- ── View Tabs ── -->
      <div class="tabs" style="margin-bottom: 24px;">
        <a href="?type=hiyari" class="tab <?php echo $filter_type !== 'kyt' ? 'tab--active' : ''; ?>"
          style="text-decoration:none;<?php echo $filter_type !== 'kyt' ? 'border-bottom-color:#e74c3c;color:#e74c3c;' : ''; ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
            <line x1="12" y1="9" x2="12" y2="13" />
            <line x1="12" y1="17" x2="12.01" y2="17" />
          </svg>
          Hiyari Hatto (Near Miss)
        </a>
        <a href="?type=kyt" class="tab <?php echo $filter_type === 'kyt' ? 'tab--active' : ''; ?>"
          style="text-decoration:none;<?php echo $filter_type === 'kyt' ? 'border-bottom-color:#f39c12;color:#f39c12;' : ''; ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
            <path d="M9 11l3 3L22 4" />
            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
          </svg>
          Kiken Yochi (Hazard Prediction)
        </a>
      </div>

      <!-- ── Summary Mini Cards ── -->
      <div class="summary-row">
        <a href="index?type=<?php echo $filter_type; ?>"
          class="mini-card <?php echo !$filter_status ? 'mini-card--active' : ''; ?>">
          <span class="mini-card__val"><?php echo $summary['total']; ?></span>
          <span class="mini-card__label">Total</span>
        </a>
        <a href="?type=<?php echo $filter_type; ?>&status=open"
          class="mini-card mini-card--red <?php echo $filter_status === 'open' ? 'mini-card--active' : ''; ?>">
          <span class="mini-card__val"><?php echo $summary['open']; ?></span>
          <span class="mini-card__label">Open</span>
        </a>
        <a href="?type=<?php echo $filter_type; ?>&status=in_progress"
          class="mini-card mini-card--yellow <?php echo $filter_status === 'in_progress' ? 'mini-card--active' : ''; ?>">
          <span class="mini-card__val"><?php echo $summary['in_progress']; ?></span>
          <span class="mini-card__label">In Progress</span>
        </a>
        <a href="?type=<?php echo $filter_type; ?>&status=closed"
          class="mini-card mini-card--green <?php echo $filter_status === 'closed' ? 'mini-card--active' : ''; ?>">
          <span class="mini-card__val"><?php echo $summary['closed']; ?></span>
          <span class="mini-card__label">Closed</span>
        </a>
        <a href="?type=<?php echo $filter_type; ?>&risk=extreme"
          class="mini-card mini-card--extreme <?php echo $filter_risk === 'extreme' ? 'mini-card--active' : ''; ?>">
          <span class="mini-card__val"><?php echo $summary['extreme']; ?></span>
          <span class="mini-card__label">Extreme</span>
        </a>
        <a href="?type=<?php echo $filter_type; ?>&risk=high"
          class="mini-card mini-card--orange <?php echo $filter_risk === 'high' ? 'mini-card--active' : ''; ?>">
          <span class="mini-card__val"><?php echo $summary['high']; ?></span>
          <span class="mini-card__label">High Risk</span>
        </a>
      </div>

      <!-- ── Search & Filters ── -->
      <div class="filter-bar">
        <form method="GET" class="filter-form" id="filterForm">
          <div class="search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"
              class="search-icon">
              <circle cx="11" cy="11" r="8" />
              <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
            <input type="text" name="search" class="search-input" placeholder="Search report number or description..."
              value="<?php echo htmlspecialchars($filter_search); ?>" />
          </div>
          <div class="filter-selects">
            <input type="hidden" name="type" value="<?php echo htmlspecialchars($filter_type); ?>" />
            <select name="status" class="filter-select" onchange="this.form.submit()">
              <option value="">All Status</option>
              <option value="open" <?php echo $filter_status === 'open' ? 'selected' : '' ?>>Open</option>
              <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : '' ?>>In Progress
              </option>
              <option value="closed" <?php echo $filter_status === 'closed' ? 'selected' : '' ?>>Closed</option>
            </select>
            <select name="risk" class="filter-select" onchange="this.form.submit()">
              <option value="">All Risk</option>
              <option value="extreme" <?php echo $filter_risk === 'extreme' ? 'selected' : '' ?>>Extreme</option>
              <option value="high" <?php echo $filter_risk === 'high' ? 'selected' : '' ?>>High</option>
              <option value="medium" <?php echo $filter_risk === 'medium' ? 'selected' : '' ?>>Medium</option>
              <option value="low" <?php echo $filter_risk === 'low' ? 'selected' : '' ?>>Low</option>
            </select>
            <select name="category" class="filter-select" onchange="this.form.submit()">
              <option value="">All Category</option>
              <?php if ($filter_type === 'hiyari'): ?>
                <option value="near_miss" <?php echo $filter_category === 'near_miss' ? 'selected' : '' ?>>Near Miss
                </option>
              <?php else: ?>
                <option value="unsafe_action" <?php echo $filter_category === 'unsafe_action' ? 'selected' : '' ?>>Unsafe
                  Act</option>
                <option value="unsafe_condition" <?php echo $filter_category === 'unsafe_condition' ? 'selected' : '' ?>>
                  Unsafe Condition</option>
              <?php endif; ?>
            </select>
            <select name="dept" class="filter-select" onchange="this.form.submit()">
              <option value="">All Departments</option>
              <?php foreach ($departments as $d): ?>
                <option value="<?php echo $d['id']; ?>" <?php echo $filter_dept == $d['id'] ? 'selected' : '' ?>>
                  <?php echo htmlspecialchars($d['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>

            <!-- Date Range Quick Filter -->
            <select name="date_range" class="filter-select" onchange="toggleCustomDate(this.value); this.form.submit()">
              <option value="">All Dates</option>
              <option value="today" <?php echo $filter_date_range === 'today' ? 'selected' : '' ?>>Today</option>
              <option value="week" <?php echo $filter_date_range === 'week' ? 'selected' : '' ?>>This Week</option>
              <option value="month" <?php echo $filter_date_range === 'month' ? 'selected' : '' ?>>This Month</option>
              <option value="year" <?php echo $filter_date_range === 'year' ? 'selected' : '' ?>>This Year</option>
              <option value="custom" <?php echo $filter_date_range === 'custom' ? 'selected' : '' ?>>Custom Range</option>
            </select>

            <!-- Custom Date Range — shown only when custom is selected -->
            <div class="date-range-wrap" id="customDateWrap"
              style="<?php echo $filter_date_range === 'custom' ? 'display:flex' : 'display:none'; ?>">
              <input type="date" name="date_from" class="filter-select"
                value="<?php echo htmlspecialchars($filter_date_from); ?>" placeholder="From" />
              <span style="color:var(--text-secondary);padding:0 4px;line-height:36px">→</span>
              <input type="date" name="date_to" class="filter-select"
                value="<?php echo htmlspecialchars($filter_date_to); ?>" placeholder="To" />
              <button type="submit" class="btn-apply">Apply</button>
            </div>

            <?php if ($filter_status || $filter_risk || $filter_category || $filter_dept || $filter_search || $filter_date_range): ?>
              <a href="index?type=<?php echo $filter_type; ?>" class="filter-clear">✕ Clear</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- ── Reports Table ── -->
      <div class="table-box">
        <div class="table-box__header">
          <div>
            <span class="table-box__title">Reports</span>
            <span class="table-count"><?php echo $total_records; ?> records</span>
          </div>
          <a href="<?php echo $filter_type === 'kyt' ? 'create_kiken' : 'create_hiyari'; ?>" class="table-box__link">
            + New Report
          </a>
        </div>

        <?php if (empty($reports)): ?>
          <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
              <line x1="16" y1="13" x2="8" y2="13" />
              <line x1="16" y1="17" x2="8" y2="17" />
              <polyline points="10 9 9 9 8 9" />
            </svg>
            <p>No reports found</p>
            <a href="<?php echo $filter_type === 'kyt' ? 'create_kiken' : 'create_hiyari'; ?>" class="btn-new"
              style="margin-top:12px">Create First Report</a>
          </div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>Report No.</th>
                  <th>Date</th>
                  <th>Department</th>
                  <th>Location</th>
                  <th>Category</th>
                  <th>Risk</th>
                  <th>Status</th>
                  <th>Reporter</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($reports as $r):
                  $catLabels = ['near_miss' => 'Near Miss', 'unsafe_action' => 'Unsafe Act', 'unsafe_condition' => 'Unsafe Condition'];
                  ?>
                  <tr>
                    <td><span class="report-num"><?php echo htmlspecialchars($r['report_number']); ?></span></td>
                    <td><?php echo date('d M Y', strtotime($r['report_date'])); ?></td>
                    <td><?php echo htmlspecialchars($r['dept_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($r['loc_name'] ?? '—'); ?></td>
                    <td>
                      <span class="badge badge--category badge--<?php echo $r['category']; ?>">
                        <?php echo $catLabels[$r['category']] ?? $r['category']; ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge badge--<?php echo $r['risk_level']; ?>">
                        <?php echo ucfirst($r['risk_level']); ?>
                      </span>
                    </td>
                    <td>
                      <?php
                      $statusClass = ['open' => 'badge--open', 'in_progress' => 'badge--progress', 'closed' => 'badge--closed'];
                      $statusLabel = ['open' => 'Open', 'in_progress' => 'In Progress', 'closed' => 'Closed'];
                      ?>
                      <span class="badge <?php echo $statusClass[$r['status']] ?? ''; ?>">
                        <?php echo $statusLabel[$r['status']] ?? ucfirst($r['status']); ?>
                      </span>
                    </td>
                    <td><?php echo htmlspecialchars($r['reporter_name'] ?? '—'); ?></td>
                    <td>
                      <a href="view?id=<?php echo $r['id']; ?>" class="action-link">View</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- ── Pagination ── -->
          <?php if ($total_pages > 1): ?>
            <div class="pagination">
              <span class="pagination__info">
                Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $per_page, $total_records); ?> of
                <?php echo $total_records; ?>
              </span>
              <div class="pagination__btns">
                <?php if ($page > 1): ?>
                  <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-btn">←
                    Prev</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                  <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                    class="page-btn <?php echo $i === $page ? 'page-btn--active' : ''; ?>">
                    <?php echo $i; ?>
                  </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                  <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-btn">Next
                    →</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

        <?php endif; ?>
      </div><!-- /table-box -->

    </div><!-- /content -->
  </main>

  <script>
    // Submit search on Enter
    document.querySelector('.search-input').addEventListener('keydown', function (e) {
      if (e.key === 'Enter') document.getElementById('filterForm').submit();
    });

    // Auto-dismiss success alert
    const alertEl = document.querySelector('.alert--success');
    if (alertEl) setTimeout(() => alertEl.style.opacity = '0', 3000);

    // Toggle custom date range inputs
    function toggleCustomDate(val) {
      const wrap = document.getElementById('customDateWrap');
      if (wrap) wrap.style.display = val === 'custom' ? 'flex' : 'none';
    }
  </script>
  <script src="../assets/avatar.js"></script>
</body>

</html>