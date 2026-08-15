<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

if (!empty($_SESSION['admin_id'])) {
    try {
        logActivity('logout');
    } catch (Exception $e) {}
}

session_unset();
session_destroy();

// Clear cookie
setcookie(SESSION_NAME, '', time() - 3600, '/');

header('Location: ' . ADMIN_URL . '/login.php?msg=loggedout');
exit;
