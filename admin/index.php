<?php
require_once __DIR__ . '/auth.php';

$pageTitle  = 'Dashboard';
$breadcrumbs = [['Dashboard', null]];

// ── Stats ─────────────────────────────────────────────────
try {
    $pdo = db();
    $total      = $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
    $published  = $pdo->query("SELECT COUNT(*) FROM jobs WHERE status='published' AND (close_date IS NULL OR close_date >= CURDATE())")->fetchColumn();
    $drafts     = $pdo->query("SELECT COUNT(*) FROM jobs WHERE status='draft'")->fetchColumn();
    $closed     = $pdo->query("SELECT COUNT(*) FROM jobs WHERE status='closed' OR (status='published' AND close_date IS NOT NULL AND close_date < CURDATE())")->fetchColumn();

    // Recent 8 jobs
    $recent = $pdo->query(
        "SELECT j.*, a.name AS posted_by
         FROM jobs j LEFT JOIN admin_users a ON a.id = j.created_by
         WHERE (j.close_date IS NULL OR j.close_date >= CURDATE())
         ORDER BY j.created_at DESC LIMIT 8"
    )->fetchAll();

    // Country breakdown
    $byCountry = $pdo->query(
        "SELECT country, COUNT(*) AS cnt FROM jobs WHERE status='published' GROUP BY country"
    )->fetchAll();

    // This month
    $thisMonth = $pdo->query(
        "SELECT COUNT(*) FROM jobs WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())"
    )->fetchColumn();

} catch (Exception $e) {
    $total=$published=$drafts=$closed=$thisMonth=0; $recent=[]; $byCountry=[];
}

include __DIR__ . '/includes/header.php';
?>

<!-- Stats row -->
<div class="stats-row">
  <div class="stat-card blue">
    <div class="stat-icon">📋</div>
    <div class="stat-label">Total Jobs</div>
    <div class="stat-value"><?= $total ?></div>
    <div class="stat-sub"><?= $thisMonth ?> added this month</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">🚀</div>
    <div class="stat-label">Published</div>
    <div class="stat-value"><?= $published ?></div>
    <div class="stat-sub">Live on site</div>
  </div>
  <div class="stat-card orange">
    <div class="stat-icon">📝</div>
    <div class="stat-label">Drafts</div>
    <div class="stat-value"><?= $drafts ?></div>
    <div class="stat-sub">Pending review</div>
  </div>
  <div class="stat-card purple">
    <div class="stat-icon">🔒</div>
    <div class="stat-label">Closed</div>
    <div class="stat-value"><?= $closed ?></div>
    <div class="stat-sub">No longer active</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start">

  <!-- Recent Jobs table -->
  <div class="card" style="padding:0">
    <div style="padding:18px 22px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border)">
      <span class="section-title" style="margin:0">Recent Job Postings</span>
      <a href="<?= ADMIN_URL ?>/pages/jobs.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="table-wrap" style="border:none;border-radius:0">
      <table>
        <thead>
          <tr>
            <th>Job Code</th>
            <th>Client Code</th>
            <th>Country</th>
            <th>Title</th>
            <th>Status</th>
            <th>Date</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($recent)): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:30px">No jobs yet. <a href="<?= ADMIN_URL ?>/pages/post_job.php" style="color:var(--accent)">Post your first job →</a></td></tr>
        <?php else: ?>
          <?php foreach ($recent as $job): ?>
          <tr>
            <td><code style="background:var(--card2);border-radius:4px;padding:2px 7px;font-size:12px;color:var(--accent)"><?= e($job['job_code']) ?></code></td>
            <td class="td-muted"><?= e($job['client_code']) ?></td>
            <td class="td-muted"><?= e($job['country']) ?></td>
            <td><strong><?= e($job['job_title']) ?></strong><br><span style="font-size:11px;color:var(--muted)"><?= e($job['city'] ?? '') ?><?= $job['city']?', ':'' ?><?= e($job['country']) ?></span></td>
            <td><span class="badge badge-<?= e($job['status']) ?>"><?= ucfirst(e($job['status'])) ?></span></td>
            <td class="td-muted" style="font-size:12px"><?= date('d M Y', strtotime($job['created_at'])) ?></td>
            <td>
              <a href="<?= ADMIN_URL ?>/pages/post_job.php?edit=<?= $job['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Right sidebar -->
  <div>
    <div class="card">
      <div class="section-title">By Country</div>
      <?php
      $flags = ['India'=>'🇮🇳','USA'=>'🇺🇸','Canada'=>'🇨🇦'];
      if (empty($byCountry)): ?>
        <p style="color:var(--muted);font-size:13px">No published jobs yet.</p>
      <?php else: ?>
        <?php foreach ($byCountry as $row): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
          <span><?= $flags[$row['country']] ?? '🌐' ?> <?= e($row['country']) ?></span>
          <span class="badge badge-published"><?= $row['cnt'] ?></span>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="section-title">Quick Actions</div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <a href="<?= ADMIN_URL ?>/pages/post_job.php" class="btn btn-primary" style="justify-content:center">＋ Post New Job</a>
        <a href="<?= ADMIN_URL ?>/pages/jobs.php?status=draft" class="btn btn-draft" style="justify-content:center">📝 Review Drafts</a>
        <a href="<?= ADMIN_URL ?>/pages/jobs.php" class="btn btn-ghost" style="justify-content:center">≡ Manage All Jobs</a>
      </div>
    </div>
  </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>