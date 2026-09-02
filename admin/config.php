<?php
// ============================================================
// config.php  —  Central configuration
// Place this ONE level ABOVE your web root ideally, or protect
// it via .htaccess (deny from all)
// ============================================================

define('DB_HOST',     'localhost');
define('DB_NAME',     'tsqwpswmrm');
define('DB_USER',     'tsqwpswmrm');          // ← change
//define('DB_PASS',     '888@Jobportal');              // ← change
define('DB_PASS',     'hUAQgP99nS');

define('DB_CHARSET',  'utf8mb4');

define('SITE_NAME',   'Accelon');
define('SITE_URL',    'http://localhost:8000');  // ← change
define('ADMIN_URL',   SITE_URL . '/admin');

define('TIMEZONE',    'Asia/Kolkata');  // ← change to your server TZ
date_default_timezone_set(TIMEZONE);

// ── Session ──────────────────────────────────────────────────
define('SESSION_NAME',    'jp_admin_sess');
define('SESSION_TIMEOUT', 3600); // 1 hour

// ── Upload paths (not used yet, placeholder) ─────────────────
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');

// ─────────────────────────────────────────────────────────────
// DB CONNECTION  (PDO, singleton)
// ─────────────────────────────────────────────────────────────
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {

        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        try {

            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('The service is temporarily unavailable. Please try again later.');
        }
    }

    return $pdo;
}

// ─────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $key, ?string $msg = null): ?string
{
    if ($msg !== null) {
        $_SESSION['flash'][$key] = $msg;
        return null;
    }
    $val = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $val;
}




function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Generate next job code for a given prefix.
 * Uses DB counter with a transaction to avoid race conditions.
 */
function nextJobCode(string $prefix): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "SELECT last_code FROM job_code_counter WHERE prefix = ? FOR UPDATE"
        );
        $stmt->execute([$prefix]);
        $row  = $stmt->fetch();
        $next = ($row['last_code'] ?? 1000) + 1;
        $pdo->prepare("UPDATE job_code_counter SET last_code = ? WHERE prefix = ?")
            ->execute([$next, $prefix]);
        $pdo->commit();
        return ['code' => $prefix . '-' . $next, 'number' => $next];
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Log admin activity
 */
function logActivity(string $action, ?string $entity = null, ?int $entityId = null, ?string $notes = null): void
{
    try {
        db()->prepare(
            "INSERT INTO activity_log (admin_id, action, entity, entity_id, notes)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([$_SESSION['admin_id'] ?? null, $action, $entity, $entityId, $notes]);
    } catch (Exception $e) { /* silent */
    }
}
