<?php
session_start();
require_once 'config/db.php';

// ── Fetch overdue reports for first login modal ────
if (isset($_SESSION['user_id']) && ((int)($_SESSION['role'] ?? 0) <= 2 || in_array($_SESSION['position'] ?? '', ['President Director', 'Director', 'General Manager']))) {
    $stmt = $pdo->prepare("
        SELECT r.*,
               d.name AS dept_name,
               l.name AS loc_name,
               u.name AS reporter_name
        FROM hiyari_reports r
        LEFT JOIN departments d ON r.department_id = d.id
        LEFT JOIN locations l ON r.location_id = l.id
        LEFT JOIN users u ON r.created_by = u.id
        WHERE r.status = 'overdue'
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $overdue_reports = $stmt->fetchAll();
}

$role = (int) ($_SESSION['role'] ?? 0);

// ── Hiyari Hatto Summary ──────────────────────
$hiyari_summary = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'open') AS open,
        SUM(status = 'in_progress') AS in_progress,
        SUM(status = 'closed') AS closed,
        SUM(MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())) AS this_month,
        SUM(MONTH(created_at) = MONTH(NOW() - INTERVAL 1 MONTH) AND YEAR(created_at) = YEAR(NOW() - INTERVAL 1 MONTH)) AS last_month
    FROM hiyari_reports
    WHERE category = 'near_miss'
")->fetch();

// ── KYT Summary ───────────────────────────────
$kyt_summary = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'open') AS open,
        SUM(status = 'in_progress') AS in_progress,
        SUM(status = 'closed') AS closed,
        SUM(MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())) AS this_month,
        SUM(MONTH(created_at) = MONTH(NOW() - INTERVAL 1 MONTH) AND YEAR(created_at) = YEAR(NOW() - INTERVAL 1 MONTH)) AS last_month
    FROM hiyari_reports
    WHERE category IN ('unsafe_action', 'unsafe_condition')
")->fetch();

// ── Most Reported Area (Hiyari Hatto) ─────────
$uid = (int) ($_SESSION['user_id'] ?? 0);
$area_where = $role >= 3 ? "AND r.created_by = $uid" : "";
$most_reported_areas = $pdo->query("
    SELECT l.name AS location, d.name AS department, COUNT(r.id) AS total
    FROM hiyari_reports r
    LEFT JOIN locations l ON r.location_id = l.id
    LEFT JOIN departments d ON r.department_id = d.id
    WHERE r.category = 'near_miss' $area_where
    GROUP BY r.location_id
    ORDER BY total DESC
    LIMIT 10
")->fetchAll();

// ── Reports by Department (Hiyari) ────────────
$dept_data = $pdo->query("
    SELECT d.name, COUNT(r.id) AS total
    FROM departments d
    LEFT JOIN hiyari_reports r ON r.department_id = d.id AND r.category = 'near_miss'
    GROUP BY d.id, d.name
    ORDER BY total DESC
    LIMIT 6
")->fetchAll();

// ── Reports by Department (KYT) ───────────────
$kyt_dept_data = $pdo->query("
    SELECT d.name, COUNT(r.id) AS total
    FROM departments d
    LEFT JOIN hiyari_reports r ON r.department_id = d.id AND r.category IN ('unsafe_action', 'unsafe_condition')
    GROUP BY d.id, d.name
    ORDER BY total DESC
    LIMIT 6
")->fetchAll();

// ── Recent Hiyari Reports ─────────────────────
$reports = $pdo->query("
    SELECT r.*,
           d.name AS dept_name,
           l.name AS loc_name,
           u.name AS reporter_name
    FROM hiyari_reports r
    LEFT JOIN departments d ON r.department_id = d.id
    LEFT JOIN locations   l ON r.location_id   = l.id
    LEFT JOIN users       u ON r.created_by    = u.id
    WHERE r.category = 'near_miss'
    ORDER BY r.created_at DESC
    LIMIT 5
")->fetchAll();

// ── Recent KYT Reports ────────────────────────
$kyt_reports = $pdo->query("
    SELECT r.*,
           d.name AS dept_name,
           l.name AS loc_name,
           u.name AS reporter_name
    FROM hiyari_reports r
    LEFT JOIN departments d ON r.department_id = d.id
    LEFT JOIN locations   l ON r.location_id   = l.id
    LEFT JOIN users       u ON r.created_by    = u.id
    WHERE r.category IN ('unsafe_action', 'unsafe_condition')
    ORDER BY r.created_at DESC
    LIMIT 5
")->fetchAll();

// ── Calculate trends ──────────────────────────
function calcTrend($current, $last)
{
  if ($last == 0)
    return ['pct' => ($current > 0 ? 100 : 0), 'dir' => 'up'];
  $pct = round((($current - $last) / $last) * 100);
  return ['pct' => abs($pct), 'dir' => $pct >= 0 ? 'up' : 'down'];
}

$hiyari_trend = calcTrend((int) ($hiyari_summary['this_month'] ?? 0), (int) ($hiyari_summary['last_month'] ?? 0));
$kyt_trend = calcTrend((int) ($kyt_summary['this_month'] ?? 0), (int) ($kyt_summary['last_month'] ?? 0));

$trend_pct = $hiyari_trend['pct'];
$trend_dir = $hiyari_trend['dir'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard — Safety Form PT. Yanmar Diesel Indonesia</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="assets/dashboard.css" />
  <link rel="stylesheet" href="assets/notifications.css" />
  <link rel="icon" href="assets/logo.png" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar__logo">
      <img src="assets/logo.png" alt="Yanmar" class="sidebar__logo-img" />
    </div>
    <nav class="sidebar__nav">
      <a href="index" class="nav-item nav-item--active" data-tooltip="Dashboard">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="7" rx="1" />
          <rect x="14" y="3" width="7" height="7" rx="1" />
          <rect x="3" y="14" width="7" height="7" rx="1" />
          <rect x="14" y="14" width="7" height="7" rx="1" />
        </svg>
      </a>
      <a href="hiyari/index" class="nav-item" data-tooltip="Create Report">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
          <line x1="12" y1="9" x2="12" y2="13" />
          <line x1="12" y1="17" x2="12.01" y2="17" />
        </svg>
      </a>
      <?php if ($role <= 2 && isset($_SESSION['user_id'])): ?>
        <a href="master/index" class="nav-item" data-tooltip="Master Data">
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
        <a href="auth/logout" class="nav-item nav-item--logout" data-tooltip="Sign Out">
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

  <!-- MAIN -->
  <main class="main">
    <header class="topbar">
      <div class="topbar__left">
        <h1 class="topbar__title">Dashboard</h1>
        <?php if (isset($_SESSION['name'])): ?>
          <p class="topbar__sub">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong></p>
        <?php else: ?>
          <p class="topbar__sub">Welcome to Safety Form PT. Yanmar Diesel Indonesia</p>
        <?php endif; ?>
      </div>
      <div class="topbar__right">
        <div class="topbar__date"><?php echo date('l, d F Y'); ?></div>

        <?php if (isset($_SESSION['user_id'])): ?>
          <!-- Notification Bell -->
          <div style="position:relative">
            <div class="notif-btn" id="notifBtn">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
              </svg>
              <span class="notif-badge" id="notifBadge">0</span>
            </div>
            <div class="notif-dropdown" id="notifDropdown">
              <div class="notif-header">
                <span class="notif-header__title">Notifications</span>
                <button class="notif-mark-all" id="markAllRead">Mark all read</button>
              </div>
              <div class="notif-list" id="notifList">
                <p class="notif-empty">Loading...</p>
              </div>
            </div>
          </div>

          <!-- Avatar -->
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
                    <?php
                    $roleNames = [1 => 'Super Admin', 2 => 'Admin', 3 => 'User'];
                    echo $roleNames[$role] ?? 'User';
                    ?>
                  </div>
                </div>
              </div>
              <div class="avatar-dropdown__divider"></div>
              <a href="profile" class="avatar-dropdown__logout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                  <circle cx="12" cy="7" r="4" />
                </svg>
                My Profile
              </a>
              <div class="avatar-dropdown__divider"></div>
              <a href="auth/logout" class="avatar-dropdown__logout">
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

      <?php if (($_GET['welcome'] ?? '') === '1'): ?>
        <div
          style="background:#d4edda;border:1px solid #c3e6cb;border-radius:10px;padding:14px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px;color:#155724;font-size:14px;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
            <polyline points="22 4 12 14.01 9 11.01" />
          </svg>
          <span>🎉 Welcome, <strong><?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?></strong>! Your account is set
            up and ready to use.</span>
        </div>
      <?php endif; ?>
      <div class="tabs">
        <button class="tab tab--active" data-tab="hiyari">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
            <line x1="12" y1="9" x2="12" y2="13" />
            <line x1="12" y1="17" x2="12.01" y2="17" />
          </svg>
          Hiyari Hatto (Near Miss)
        </button>
        <button class="tab" data-tab="kyt">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
            <path d="M9 11l3 3L22 4" />
            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
          </svg>
          Kiken Yochi (Hazard Prediction)
        </button>
      </div>

      <!-- HIYARI HATTO TAB -->
      <div class="tab-panel tab-panel--active" id="tab-hiyari">

        <section class="cards">
          <div class="card card--blue" style="--delay:0.05s">
            <div class="card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
              </svg>
            </div>
            <div class="card__body">
              <span class="card__label">Total Reports</span>
              <span class="card__value" data-count="<?php echo (int) $hiyari_summary['total']; ?>">0</span>
            </div>
            <div class="card__trend card__trend--<?php echo $trend_dir === 'up' ? 'up' : 'warn'; ?>">
              <?php echo $trend_dir === 'up' ? '↑' : '↓'; ?> <?php echo $trend_pct; ?>% this month
            </div>
          </div>

          <div class="card card--red" style="--delay:0.10s">
            <div class="card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
              </svg>
            </div>
            <div class="card__body">
              <span class="card__label">Open</span>
              <span class="card__value" data-count="<?php echo (int) $hiyari_summary['open']; ?>">0</span>
            </div>
            <div class="card__trend card__trend--warn">Needs attention</div>
          </div>

          <div class="card card--yellow" style="--delay:0.15s">
            <div class="card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="23 4 23 10 17 10" />
                <polyline points="1 20 1 14 7 14" />
                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
              </svg>
            </div>
            <div class="card__body">
              <span class="card__label">In Progress</span>
              <span class="card__value" data-count="<?php echo (int) $hiyari_summary['in_progress']; ?>">0</span>
            </div>
            <div class="card__trend card__trend--neutral">Being reviewed</div>
          </div>

          <div class="card card--green" style="--delay:0.20s">
            <div class="card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12" />
              </svg>
            </div>
            <div class="card__body">
              <span class="card__label">Closed</span>
              <span class="card__value" data-count="<?php echo (int) $hiyari_summary['closed']; ?>">0</span>
            </div>
            <div class="card__trend card__trend--up">↑ 8% this month</div>
          </div>
        </section>

        <section class="charts">
          <div class="chart-box" style="--delay:0.25s">
            <div class="chart-box__header">
              <h2 class="chart-box__title">Most Reported Area</h2>
              <span class="chart-box__badge">Top 10</span>
            </div>
            <div class="chart-box__body" style="padding:0;">
              <?php if (empty($most_reported_areas)): ?>
                <p style="text-align:center;color:#aaa;padding:32px;font-size:14px;">No reports yet</p>
              <?php else: ?>
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                  <thead>
                    <tr style="background:#f8f9fa;">
                      <th style="padding:10px 14px;text-align:left;font-weight:600;color:#555;">#</th>
                      <th style="padding:10px 14px;text-align:left;font-weight:600;color:#555;">Location</th>
                      <th style="padding:10px 14px;text-align:left;font-weight:600;color:#555;">Department</th>
                      <th style="padding:10px 14px;text-align:right;font-weight:600;color:#555;">Reports</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($most_reported_areas as $i => $area): ?>
                      <tr style="border-top:1px solid #f0f0f0;">
                        <td style="padding:10px 14px;color:#aaa;font-weight:700;">
                          <?php echo $i + 1; ?>
                        </td>
                        <td style="padding:10px 14px;font-weight:600;color:#333;">
                          <?php echo htmlspecialchars($area['location'] ?? '—'); ?>
                        </td>
                        <td style="padding:10px 14px;color:#666;">
                          <?php echo htmlspecialchars($area['department'] ?? '—'); ?>
                        </td>
                        <td style="padding:10px 14px;text-align:right;">
                          <span
                            style="background:#fdf2f2;color:#e74c3c;font-weight:700;padding:3px 10px;border-radius:12px;font-size:12px;">
                            <?php echo $area['total']; ?>
                          </span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>
          </div>
          <div class="chart-box" style="--delay:0.30s">
            <div class="chart-box__header">
              <h2 class="chart-box__title">Reports by Department</h2>
              <span class="chart-box__badge">All Time</span>
            </div>
            <div class="chart-box__body">
              <canvas id="hiyariDeptChart"></canvas>
            </div>
          </div>
        </section>

        <section class="table-box" style="--delay:0.35s">
          <div class="table-box__header">
            <h2 class="table-box__title">Recent Hiyari Hatto Reports</h2>
            <a href="hiyari/index" class="table-box__link">View all →</a>
          </div>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>Report No.</th>
                  <th>Date</th>
                  <th>Department</th>
                  <th>Category</th>
                  <th>Risk</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($reports)): ?>
                  <tr>
                    <td colspan="7" style="text-align:center;color:var(--text-hint);padding:24px">No reports yet</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($reports as $r):
                    $catLabels = ['near_miss' => 'Near Miss', 'unsafe_action' => 'Unsafe Act', 'unsafe_condition' => 'Unsafe Condition'];
                    $statusClass = ['open' => 'badge--blue', 'in_progress' => 'badge--progress', 'closed' => 'badge--closed', 'overdue' => 'badge--red'];
                    $statusLabel = ['open' => 'Open', 'in_progress' => 'In Progress', 'closed' => 'Closed', 'overdue' => 'Overdue'];
                    ?>
                    <tr>
                      <td><span class="report-num"><?php echo htmlspecialchars($r['report_number']); ?></span></td>
                      <td><?php echo date('d M Y', strtotime($r['report_date'])); ?></td>
                      <td><?php echo htmlspecialchars($r['dept_name'] ?? '—'); ?></td>
                      <td><?php echo $catLabels[$r['category']] ?? $r['category']; ?></td>
                      <td><span
                          class="badge badge--<?php echo $r['risk_level']; ?>"><?php echo ucfirst($r['risk_level']); ?></span>
                      </td>
                      <td><span
                          class="badge <?php echo $statusClass[$r['status']] ?? ''; ?>"><?php echo $statusLabel[$r['status']] ?? ucfirst($r['status']); ?></span>
                      </td>
                      <td><a href="hiyari/view?id=<?php echo $r['id']; ?>" class="action-link">View</a></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>

      </div><!-- /tab-hiyari -->

      <!-- KYT TAB -->
      <div class="tab-panel" id="tab-kyt">
        <section class="cards">
          <div class="card card--blue" style="--delay:0.05s">
            <div class="card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4" />
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
              </svg>
            </div>
            <div class="card__body">
              <span class="card__label">Total Activities</span>
              <span class="card__value" data-count="<?php echo (int) $kyt_summary['total']; ?>">0</span>
            </div>
            <div class="card__trend card__trend--<?php echo $kyt_trend['dir'] === 'up' ? 'up' : 'warn'; ?>">
              <?php echo $kyt_trend['dir'] === 'up' ? '↑' : '↓'; ?> <?php echo $kyt_trend['pct']; ?>% this month
            </div>
          </div>
          <div class="card card--green" style="--delay:0.10s">
            <div class="card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12" />
              </svg>
            </div>
            <div class="card__body">
              <span class="card__label">Completed</span>
              <span class="card__value" data-count="<?php echo (int) $kyt_summary['closed']; ?>">0</span>
            </div>
            <div class="card__trend card__trend--up">Finished activities</div>
          </div>
          <div class="card card--yellow" style="--delay:0.15s">
            <div class="card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
              </svg>
            </div>
            <div class="card__body">
              <span class="card__label">In Progress / Open</span>
              <span class="card__value"
                data-count="<?php echo (int) ($kyt_summary['open'] + $kyt_summary['in_progress']); ?>">0</span>
            </div>
            <div class="card__trend card__trend--warn">Needs action</div>
          </div>
          <div class="card card--blue" style="--delay:0.20s">
            <div class="card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="20" x2="18" y2="10" />
                <line x1="12" y1="20" x2="12" y2="4" />
                <line x1="6" y1="20" x2="6" y2="14" />
              </svg>
            </div>
            <div class="card__body">
              <span class="card__label">Completion Rate</span>
              <?php
              $kytTotal = (int) $kyt_summary['total'];
              $kytClosed = (int) $kyt_summary['closed'];
              $kytRate = $kytTotal > 0 ? round(($kytClosed / $kytTotal) * 100) : 0;
              ?>
              <span class="card__value" data-count="<?php echo $kytRate; ?>">0</span>
              <span class="card__unit">%</span>
            </div>
            <div class="card__trend card__trend--neutral">All time overall</div>
          </div>
        </section>

        <section class="charts">
          <div class="chart-box" style="--delay:0.25s">
            <div class="chart-box__header">
              <h2 class="chart-box__title">Completion Rate</h2>
              <span class="chart-box__badge">All Time</span>
            </div>
            <div class="chart-box__body">
              <canvas id="kytCompletionChart"></canvas>
            </div>
          </div>
          <div class="chart-box" style="--delay:0.30s">
            <div class="chart-box__header">
              <h2 class="chart-box__title">Kiken Yochi by Department</h2>
              <span class="chart-box__badge">All Time</span>
            </div>
            <div class="chart-box__body">
              <canvas id="kytDeptChart"></canvas>
            </div>
          </div>
        </section>

        <section class="table-box" style="--delay:0.35s">
          <div class="table-box__header">
            <h2 class="table-box__title">Recent Kiken Yochi Activities</h2>
            <a href="hiyari/index?type=kyt" class="table-box__link">View all →</a>
          </div>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>Report No.</th>
                  <th>Date</th>
                  <th>Department</th>
                  <th>Category</th>
                  <th>Risk</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($kyt_reports)): ?>
                  <tr>
                    <td colspan="7" style="text-align:center;color:var(--text-hint);padding:24px">No Kiken Yochi
                      activities yet
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($kyt_reports as $r):
                    $catLabels = ['near_miss' => 'Near Miss', 'unsafe_action' => 'Unsafe Act', 'unsafe_condition' => 'Unsafe Condition'];
                    $statusClass = ['open' => 'badge--open', 'in_progress' => 'badge--progress', 'closed' => 'badge--closed'];
                    $statusLabel = ['open' => 'Open', 'in_progress' => 'In Progress', 'closed' => 'Closed'];
                    ?>
                    <tr>
                      <td><span class="report-num"><?php echo htmlspecialchars($r['report_number']); ?></span></td>
                      <td><?php echo date('d M Y', strtotime($r['report_date'])); ?></td>
                      <td><?php echo htmlspecialchars($r['dept_name'] ?? '—'); ?></td>
                      <td><?php echo $catLabels[$r['category']] ?? $r['category']; ?></td>
                      <td><span
                          class="badge badge--<?php echo $r['risk_level']; ?>"><?php echo ucfirst($r['risk_level']); ?></span>
                      </td>
                      <td><span
                          class="badge <?php echo $statusClass[$r['status']] ?? ''; ?>"><?php echo $statusLabel[$r['status']] ?? ucfirst($r['status']); ?></span>
                      </td>
                      <td><a href="hiyari/view?id=<?php echo $r['id']; ?>" class="action-link">View</a></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>

      </div><!-- /tab-kyt -->

    </div><!-- /content -->
  </main>

  <!-- Pass PHP data to JS -->
  <script>
    window.HIYARI_DATA = {
      near_miss: <?php echo (int) ($category_data['near_miss'] ?? 0); ?>,
      unsafe_action: <?php echo (int) ($category_data['unsafe_action'] ?? 0); ?>,
      unsafe_condition: <?php echo (int) ($category_data['unsafe_condition'] ?? 0); ?>,
      dept_labels: <?php echo json_encode(array_column($dept_data, 'name')); ?>,
      dept_values: <?php echo json_encode(array_map('intval', array_column($dept_data, 'total'))); ?>,
    };
    window.KYT_DATA = {
      completed: <?php echo (int) $kyt_summary['closed']; ?>,
      pending: <?php echo (int) ($kyt_summary['open'] + $kyt_summary['in_progress']); ?>,
      dept_labels: <?php echo json_encode(array_column($kyt_dept_data, 'name')); ?>,
      dept_values: <?php echo json_encode(array_map('intval', array_column($kyt_dept_data, 'total'))); ?>,
    };
    const BASE_URL = '/kyt-yadin';
  </script>
  <script src="assets/avatar.js"></script>
  <script src="assets/notifications.js"></script>
  <script src="assets/dashboard.js"></script>
  <!-- Overdue Reports Modal — First Login -->
<?php if (!empty($overdue_reports)): ?>
<div style="display:flex;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:12px;width:90%;max-width:500px;box-shadow:0 20px 60px rgba(0,0,0,0.3);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;">
    <!-- Header -->
    <div style="padding:20px;border-bottom:1px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center;flex-shrink:0;">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:40px;height:40px;border-radius:50%;background:#f39c12;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:20px;">⚠️</div>
        <h3 style="margin:0;font-size:16px;font-weight:600;color:#333;">Overdue Reports Alert</h3>
      </div>
      <button onclick="this.closest('div[style*=fixed]').remove()" style="background:none;border:none;cursor:pointer;font-size:28px;color:#999;padding:0;width:32px;height:32px;display:flex;align-items:center;justify-content:center;">×</button>
    </div>
    
    <!-- Body -->
    <div style="padding:20px;overflow-y:auto;flex:1;">
      <p style="color:#f39c12;font-weight:600;margin:0 0 16px;font-size:14px;">
        You have <?php echo count($overdue_reports); ?> report(s) that are overdue for review:
      </p>
      <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($overdue_reports as $report): ?>
          <a href="hiyari/view?id=<?php echo $report['id']; ?>" 
             style="display:block;padding:12px;background:#fff3e0;border:1px solid #ffe0b2;border-radius:8px;text-decoration:none;color:#333;transition:all 0.2s;cursor:pointer;">
            <div style="font-weight:600;color:#e67e22;margin-bottom:4px;font-size:14px;">
              <?php echo htmlspecialchars($report['report_number']); ?>
            </div>
            <div style="font-size:12px;color:#666;">
              <?php echo htmlspecialchars($report['dept_name'] ?? '—'); ?> • 
              <?php echo date('d M Y', strtotime($report['created_at'])); ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    
    <!-- Footer -->
    <div style="padding:16px 20px;border-top:1px solid #e0e0e0;display:flex;gap:10px;justify-content:flex-end;flex-shrink:0;">
      <button onclick="this.closest('div[style*=fixed]').remove()" style="padding:10px 20px;background:#f5f5f5;border:1px solid #ddd;border-radius:6px;cursor:pointer;font-weight:500;font-size:14px;">Close</button>
      <a href="hiyari/index" style="padding:10px 20px;background:#1a73e8;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:500;text-decoration:none;display:inline-block;font-size:14px;">Review All</a>
    </div>
  </div>
</div>
<?php endif; ?>
</body>

</html>