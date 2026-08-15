<?php
/**
 * jobs-embed.php
 * Place this file at: gustaine.com/jobportal/jobs-embed.php
 *
 * Returns published jobs as JSON with CORS headers so any external
 * website can fetch and render them via the embed widget.
 */

require_once __DIR__ . '/admin/config.php';

// ── CORS: allow any origin ────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── QUERY ─────────────────────────────────────────────────────────────────────
try {
    $pdo  = db();
    $stmt = $pdo->query("
        SELECT
            id,
            job_title,
            slug,
            client_code,
            job_type,
            workplace_type,
            city,
            state_province,
            country,
            open_date,
            close_date
        FROM jobs
        WHERE status = 'published'
          AND (close_date IS NULL OR close_date >= CURDATE())
        ORDER BY open_date DESC, id DESC
    ");
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($jobs as &$job) {
        $parts = explode('-', $job['client_code'] ?? '');
        $job['job_code'] = $parts[1] ?? '';

        // Detail URL — matches your existing job-detail.php link structure exactly
        $job['detail_url'] = 'https://www.accelonconsulting.com/jobportal/job-detail.php'
            . '?form_jobcode='  . rawurlencode($job['job_code'])
            . '&slug='          . rawurlencode($job['slug']);
    }
    unset($job);

    echo json_encode(['success' => true, 'jobs' => $jobs], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}