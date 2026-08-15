<?php
require_once dirname(__DIR__) . '/auth.php';

$id     = (int)($_GET['id'] ?? 0);
$action = $_GET['a'] ?? '';

if (!$id || !in_array($action, ['publish','close','draft','archive','delete'])) {
    flash('error', 'Invalid action.');
    redirect(ADMIN_URL . '/pages/jobs.php');
}

// Verify job exists
$stmt = db()->prepare("SELECT id, job_code, status FROM jobs WHERE id = ?");
$stmt->execute([$id]);
$job = $stmt->fetch();

if (!$job) {
    flash('error', 'Job not found.');
    redirect(ADMIN_URL . '/pages/jobs.php');
}

try {
    $pdo = db();
    switch ($action) {
        case 'publish':
            $pdo->prepare("UPDATE jobs SET status='published', published_at=COALESCE(published_at,NOW()) WHERE id=?")
               ->execute([$id]);
            flash('success', 'Job <strong>'.$job['job_code'].'</strong> is now published.');
            break;
        case 'close':
            $pdo->prepare("UPDATE jobs SET status='closed' WHERE id=?")->execute([$id]);
            flash('success', 'Job <strong>'.$job['job_code'].'</strong> closed.');
            break;
        case 'draft':
            $pdo->prepare("UPDATE jobs SET status='draft' WHERE id=?")->execute([$id]);
            flash('success', 'Job moved to draft.');
            break;
        case 'archive':
            $pdo->prepare("UPDATE jobs SET status='archived' WHERE id=?")->execute([$id]);
            flash('success', 'Job archived.');
            break;
        case 'delete':
            $pdo->prepare("DELETE FROM jobs WHERE id=?")->execute([$id]);
            flash('success', 'Job deleted.');
            break;
    }
    logActivity($action.'_job', 'job', $id, $job['job_code']);
} catch (Exception $e) {
    flash('error', 'Action failed: ' . $e->getMessage());
}

redirect(ADMIN_URL . '/pages/jobs.php');
