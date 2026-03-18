<?php
// ═══════════════════════════════════════════
// ROLE CONSTANTS & HELPERS
// ═══════════════════════════════════════════

define('ROLE_SUPERADMIN', 1);
define('ROLE_ADMIN',      2);
define('ROLE_USER',       3);

$ROLE_LABELS = [
    1 => 'Super Admin',
    2 => 'Admin',
    3 => 'User',
];

/**
 * Check if current user has minimum role level
 * Lower number = higher access
 */
function hasRole(int $minRole): bool {
    return isset($_SESSION['role']) && (int)$_SESSION['role'] <= $minRole;
}

/**
 * Check if user is Super Admin
 */
function isSuperAdmin(): bool {
    return isset($_SESSION['role']) && (int)$_SESSION['role'] === ROLE_SUPERADMIN;
}

/**
 * Check if user is Admin or above
 */
function isAdmin(): bool {
    return isset($_SESSION['role']) && (int)$_SESSION['role'] <= ROLE_ADMIN;
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect if not logged in
 */
function requireLogin(string $redirect = '../auth/login'): void {
    if (!isLoggedIn()) {
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Redirect if not admin
 */
function requireAdmin(string $redirect = '../index'): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Redirect if not super admin
 */
function requireSuperAdmin(string $redirect = '../index'): void {
    requireLogin();
    if (!isSuperAdmin()) {
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Check if user can view a specific report
 * Users can only view their own reports
 */
function canViewReport(int $report_created_by): bool {
    if (!isLoggedIn()) return false;
    if (isAdmin()) return true;
    return (int)$_SESSION['user_id'] === $report_created_by;
}

/**
 * Check if user can comment on a report
 */
function canComment(int $report_created_by): bool {
    if (!isLoggedIn()) return false;
    if (isAdmin()) return true;
    return (int)$_SESSION['user_id'] === $report_created_by;
}
