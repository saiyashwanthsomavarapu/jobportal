<?php
// ============================================================
// auth.php  —  Include at top of every admin page
// ============================================================

require_once __DIR__ . '/config.php';

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path'     => '/',
        'secure'   => false,   // set true on HTTPS
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// ── Session timeout check ─────────────────────────────────────
if (isset($_SESSION['admin_last_active'])
    && (time() - $_SESSION['admin_last_active']) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    redirect(ADMIN_URL . '/login.php?msg=timeout');
}
$_SESSION['admin_last_active'] = time();

// ── Must be logged in ─────────────────────────────────────────
if (empty($_SESSION['admin_id'])) {
    redirect(ADMIN_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

// ── Load current admin user ───────────────────────────────────
$currentAdmin = db()->prepare("SELECT * FROM admin_users WHERE id = ? AND is_active = 1");
$currentAdmin->execute([$_SESSION['admin_id']]);
$currentAdmin = $currentAdmin->fetch();

if (!$currentAdmin) {
    session_unset(); session_destroy();
    redirect(ADMIN_URL . '/login.php');
}
