<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login');
    exit;
}

$role = (int) ($_SESSION['role'] ?? 3);
$uid = (int) ($_SESSION['user_id'] ?? 0);

// ── Same filters as index.php ─────────────────
$filter_status = $_GET['status'] ?? '';
$filter_risk = $_GET['risk'] ?? '';
$filter_category = $_GET['category'] ?? '';
$filter_dept = $_GET['dept'] ?? '';
$filter_search = trim($_GET['search'] ?? '');
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_type = $_GET['type'] ?? 'hiyari';
$format = $_GET['format'] ?? 'excel'; // excel or csv

// Date range shortcuts
$today = date('Y-m-d');
$range = $_GET['date_range'] ?? '';
if ($range === 'today') {
    $filter_date_from = $today;
    $filter_date_to = $today;
} elseif ($range === 'week') {
    $filter_date_from = date('Y-m-d', strtotime('monday this week'));
    $filter_date_to = date('Y-m-d', strtotime('sunday this week'));
} elseif ($range === 'month') {
    $filter_date_from = date('Y-m-01');
    $filter_date_to = date('Y-m-t');
} elseif ($range === 'year') {
    $filter_date_from = date('Y-01-01');
    $filter_date_to = date('Y-12-31');
}

// ── Build query ───────────────────────────────
$where = ['1=1'];
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
if ($filter_date_from) {
    $where[] = "r.report_date >= :date_from";
    $params[':date_from'] = $filter_date_from;
}
if ($filter_date_to) {
    $where[] = "r.report_date <= :date_to";
    $params[':date_to'] = $filter_date_to;
}
if ($filter_search) {
    $where[] = "(r.report_number LIKE :search OR r.description LIKE :search)";
    $params[':search'] = '%' . $filter_search . '%';
}

if ($role >= 3) {
    $where[] = "(r.created_by = :uid OR r.id IN (SELECT report_id FROM corrective_actions WHERE pic_user_id = :uid2))";
    $params[':uid'] = $uid;
    $params[':uid2'] = $uid;
}

$whereStr = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT
        r.report_number,
        r.report_date,
        d.name        AS department,
        l.name        AS location,
        r.category,
        r.description,
        r.likelihood,
        r.severity,
        r.risk_level,
        r.status,
        u.name        AS reporter,
        r.created_at
    FROM hiyari_reports r
    LEFT JOIN departments d ON r.department_id = d.id
    LEFT JOIN locations   l ON r.location_id   = l.id
    LEFT JOIN users       u ON r.created_by    = u.id
    WHERE $whereStr
    ORDER BY r.created_at DESC
");
foreach ($params as $k => $v)
    $stmt->bindValue($k, $v);
$stmt->execute();
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Label maps ───────────────────────────────
$category_map = [
    'near_miss' => 'Near Miss',
    'unsafe_action' => 'Unsafe Act',
    'unsafe_condition' => 'Unsafe Condition',
];
$status_map = [
    'open' => 'Open',
    'in_progress' => 'In Progress',
    'closed' => 'Closed',
];
$risk_map = [
    'low' => 'Low',
    'medium' => 'Medium',
    'high' => 'High',
    'extreme' => 'Extreme',
];
$likelihood_map = [
    'A' => 'A - Almost Certain',
    'B' => 'B - Likely',
    'C' => 'C - Moderate',
    'D' => 'D - Unlikely',
    'E' => 'E - Rare',
];

$type_label = $filter_type === 'kyt' ? 'KYT' : 'Hiyari Hatto';
$filename = strtolower($filter_type) . '_reports_' . date('Ymd_His');

// ── CSV Export ────────────────────────────────
if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Report No.', 'Date', 'Department', 'Location', 'Category', 'Description', 'Likelihood', 'Severity', 'Risk Level', 'Status', 'Reporter', 'Created At']);
    foreach ($reports as $r) {
        fputcsv($out, [
            $r['report_number'],
            $r['report_date'],
            $r['department'] ?? '—',
            $r['location'] ?? '—',
            $category_map[$r['category']] ?? $r['category'],
            $r['description'],
            $likelihood_map[$r['likelihood']] ?? $r['likelihood'],
            $r['severity'],
            strtoupper($risk_map[$r['risk_level']] ?? $r['risk_level']),
            $status_map[$r['status']] ?? $r['status'],
            $r['reporter'] ?? '—',
            $r['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

// ── Excel (HTML table method) ─────────────────
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
header('Cache-Control: max-age=0');

$risk_colors = [
    'low' => '#27ae60',
    'medium' => '#f39c12',
    'high' => '#e67e22',
    'extreme' => '#c0392b',
];
$status_colors = [
    'open' => '#e74c3c',
    'in_progress' => '#f39c12',
    'closed' => '#27ae60',
];

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>';
echo '<table border="0" cellpadding="0" cellspacing="0" style="font-family:Arial;font-size:11pt;margin-bottom:8px;">';
echo '<tr><td style="font-size:16pt;font-weight:bold;color:#2c3e50;">' . $type_label . ' Reports</td></tr>';
echo '<tr><td style="color:#7f8c8d;font-size:10pt;">Exported on ' . date('d F Y, H:i') . ' &nbsp;|&nbsp; Total: ' . count($reports) . ' records</td></tr>';
echo '</table>';
echo '<br>';

echo '<table border="1" cellpadding="6" cellspacing="0" style="font-family:Arial;font-size:10pt;border-collapse:collapse;width:100%;">';

// Header row
$headers = ['Report No.', 'Date', 'Department', 'Location', 'Category', 'Description', 'Likelihood', 'Severity', 'Risk Level', 'Status', 'Reporter'];
echo '<tr style="background:#2c3e50;color:#fff;font-weight:bold;">';
foreach ($headers as $h) {
    echo '<td style="padding:8px 10px;white-space:nowrap;">' . $h . '</td>';
}
echo '</tr>';

// Data rows
foreach ($reports as $i => $r) {
    $bg = $i % 2 === 0 ? '#ffffff' : '#f8f9fa';
    $risk = $r['risk_level'] ?? 'low';
    $status = $r['status'] ?? 'open';
    $risk_color = $risk_colors[$risk] ?? '#888';
    $stat_color = $status_colors[$status] ?? '#888';

    echo '<tr style="background:' . $bg . ';">';
    echo '<td style="padding:6px 10px;font-weight:bold;white-space:nowrap;">' . htmlspecialchars($r['report_number']) . '</td>';
    echo '<td style="padding:6px 10px;white-space:nowrap;">' . date('d M Y', strtotime($r['report_date'])) . '</td>';
    echo '<td style="padding:6px 10px;">' . htmlspecialchars($r['department'] ?? '—') . '</td>';
    echo '<td style="padding:6px 10px;">' . htmlspecialchars($r['location'] ?? '—') . '</td>';
    echo '<td style="padding:6px 10px;">' . htmlspecialchars($category_map[$r['category']] ?? $r['category']) . '</td>';
    echo '<td style="padding:6px 10px;max-width:300px;">' . htmlspecialchars($r['description']) . '</td>';
    echo '<td style="padding:6px 10px;text-align:center;">' . htmlspecialchars($likelihood_map[$r['likelihood']] ?? $r['likelihood']) . '</td>';
    echo '<td style="padding:6px 10px;text-align:center;">' . htmlspecialchars($r['severity']) . '</td>';
    echo '<td style="padding:6px 10px;text-align:center;"><span style="background:' . $risk_color . ';color:#fff;padding:3px 8px;border-radius:4px;font-size:9pt;font-weight:bold;">' . strtoupper($risk_map[$risk] ?? $risk) . '</span></td>';
    echo '<td style="padding:6px 10px;text-align:center;"><span style="background:' . $stat_color . ';color:#fff;padding:3px 8px;border-radius:4px;font-size:9pt;">' . htmlspecialchars($status_map[$status] ?? $status) . '</span></td>';
    echo '<td style="padding:6px 10px;">' . htmlspecialchars($r['reporter'] ?? '—') . '</td>';
    echo '</tr>';
}

echo '</table></body></html>';
exit;
