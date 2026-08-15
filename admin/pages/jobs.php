<?php
require_once dirname(__DIR__) . '/auth.php';

$pageTitle   = 'All Jobs';
$breadcrumbs = [['Dashboard', ADMIN_URL.'/index.php'], ['All Jobs', null]];

// ── Filter inputs ────────────────────────────────────────────
$fStatus   = $_GET['status']    ?? '';
$fCountry  = $_GET['country']   ?? '';
$fType     = $_GET['job_type']  ?? '';
$fWork     = $_GET['workplace'] ?? '';
$fSearch   = trim($_GET['q']    ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 15;

// ── Build query ──────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($fStatus)  { $where[] = 'j.status = ?';         $params[] = $fStatus; }
if ($fCountry) { $where[] = 'j.country = ?';         $params[] = $fCountry; }
if ($fType)    { $where[] = 'j.job_type = ?';         $params[] = $fType; }
if ($fWork)    { $where[] = 'j.workplace_type = ?';   $params[] = $fWork; }
if ($fSearch) {
    $where[]  = '(j.job_title LIKE ? OR j.job_code LIKE ? OR j.client_code LIKE ?)';
    $like = '%'.$fSearch.'%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$whereSQL = implode(' AND ', $where);

try {
    $pdo = db();
    $totalRows = $pdo->prepare("SELECT COUNT(*) FROM jobs j WHERE $whereSQL");
    $totalRows->execute($params);
    $totalRows = (int)$totalRows->fetchColumn();
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        "SELECT 
    j.*, 
    a.name AS posted_by,
    c.client_name
FROM jobs j
LEFT JOIN admin_users a 
    ON a.id = j.created_by
LEFT JOIN clients c 
    ON LEFT(j.client_code, 4) = c.client_code
WHERE $whereSQL
ORDER BY j.created_at DESC
LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $jobs = $stmt->fetchAll();

    // For filter dropdowns
    $allTypes = $pdo->query("SELECT DISTINCT job_type FROM jobs ORDER BY job_type")->fetchAll(PDO::FETCH_COLUMN);
    $allWork  = $pdo->query("SELECT DISTINCT workplace_type FROM jobs ORDER BY workplace_type")->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    $jobs=[]; $totalRows=0; $totalPages=1; $allTypes=[]; $allWork=[];
}

// ── Bulk / single actions ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $ids     = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
    $action  = $_POST['action'];

    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        try {
            switch ($action) {
                case 'publish':
                    db()->prepare("UPDATE jobs SET status='published', published_at=NOW() WHERE id IN ($placeholders)")
                       ->execute($ids);
                    flash('success', count($ids).' job(s) published.');
                    break;
                case 'draft':
                    db()->prepare("UPDATE jobs SET status='draft' WHERE id IN ($placeholders)")
                       ->execute($ids);
                    flash('success', count($ids).' job(s) moved to draft.');
                    break;
                case 'close':
                    db()->prepare("UPDATE jobs SET status='closed' WHERE id IN ($placeholders)")
                       ->execute($ids);
                    flash('success', count($ids).' job(s) closed.');
                    break;
                case 'delete':
                    db()->prepare("DELETE FROM jobs WHERE id IN ($placeholders)")
                       ->execute($ids);
                    flash('success', count($ids).' job(s) deleted.');
                    break;
            }
            logActivity($action.'_jobs','job', null, 'IDs: '.implode(',',$ids));
        } catch (Exception $e) {
            flash('error', 'Action failed: '.$e->getMessage());
        }
    }
    redirect(ADMIN_URL.'/pages/jobs.php?'.http_build_query(['status'=>$fStatus,'country'=>$fCountry,'q'=>$fSearch]));
}

// ── Helpers ──────────────────────────────────────────────────
function pageUrl(int $pg): string {
    $p = $_GET; $p['page'] = $pg;
    return '?' . http_build_query($p);
}

include dirname(__DIR__) . '/includes/header.php';
?>

<!-- Filters -->
<form method="GET" class="filters">
  <div class="filter-field">
    <label>Search</label>
    <input type="text" name="q" class="ctrl" placeholder="Title / Code / Client"
           value="<?= e($fSearch) ?>" style="min-width:200px">
  </div>
  <div class="filter-field">
    <label>Status</label>
    <select name="status" class="ctrl">
      <option value="">All Status</option>
      <?php foreach (['published','draft','closed','archived'] as $s): ?>
      <option value="<?= $s ?>" <?= $fStatus===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filter-field">
    <label>Country</label>
    <select name="country" class="ctrl">
      <option value="">All Countries</option>
      <?php foreach (['India','United States','Canada'] as $c): ?>
      <option value="<?= $c ?>" <?= $fCountry===$c?'selected':'' ?>><?= $c ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filter-field">
    <label>Job Type</label>
    <select name="job_type" class="ctrl">
      <option value="">All Types</option>
      <?php foreach ($allTypes as $t): ?>
      <option value="<?= e($t) ?>" <?= $fType===$t?'selected':'' ?>><?= e($t) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filter-field">
    <label>Workplace</label>
    <select name="workplace" class="ctrl">
      <option value="">All</option>
      <?php foreach ($allWork as $w): ?>
      <option value="<?= e($w) ?>" <?= $fWork===$w?'selected':'' ?>><?= e($w) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filter-actions">
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="<?= ADMIN_URL ?>/pages/jobs.php" class="btn btn-ghost btn-sm">Clear</a>
  </div>
</form>

<!-- Bulk Action form + Table -->
<form method="POST" id="bulkForm">
<div class="card" style="padding:0">

  <!-- Table toolbar -->
  <div style="padding:14px 18px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);gap:12px;flex-wrap:wrap">
    <span style="font-size:13px;color:var(--text2)">
      Showing <strong style="color:var(--text)"><?= count($jobs) ?></strong>
      of <strong style="color:var(--text)"><?= $totalRows ?></strong> jobs
    </span>
    <div style="display:flex;gap:8px;align-items:center">
      <select name="action" class="ctrl" style="padding:7px 10px;font-size:12px;width:auto">
        <option value="">Bulk Action…</option>
        <option value="publish">Publish</option>
        <option value="draft">Move to Draft</option>
        <option value="close">Close</option>
        <option value="delete">Delete</option>
      </select>
      <button type="submit" class="btn btn-ghost btn-sm"
              onclick="return confirm('Apply this action to selected jobs?')">Apply</button>
      <a href="<?= ADMIN_URL ?>/pages/post_job.php" class="btn btn-primary btn-sm">＋ New Job</a>
    </div>
  </div>

  <!-- Table -->
  <div class="table-wrap" style="border:none;border-radius:0">
    <table>
      <thead>
        <tr>
          <th><input type="checkbox" id="checkAll" title="Select all"
               style="accent-color:var(--accent);cursor:pointer"></th>
          <th>Job Code</th>
             <th>Client Name</th>
          <th>Title</th>
          <th>Country</th>
          <th>Job Type</th>
          <th>Workplace</th>
          <th>Experience</th>
          <th>Open Date</th>
          <th>Status</th>
          <th>Posted By</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($jobs)): ?>
        <tr>
          <td colspan="11" style="text-align:center;color:var(--muted);padding:40px">
            No jobs found. <a href="<?= ADMIN_URL ?>/pages/post_job.php" style="color:var(--accent)">Post one →</a>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($jobs as $j): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= $j['id'] ?>"
               style="accent-color:var(--accent);cursor:pointer" class="row-check"></td>
          <td>
            <code style="background:var(--card2);border-radius:4px;padding:2px 8px;font-size:12px;color:var(--accent)">
              <?= e($j['job_code']) ?>
            </code>
            <?php if ($j['client_code']): ?>
            <br><span style="font-size:11px;color:var(--muted)"><?= e($j['client_code']) ?></span>
            <?php endif; ?>
          </td>
          <td class="td-muted">
  <?= e($j['client_name'] ?? '—') ?>
</td>
          <td>
            <strong style="font-size:13px"><?= e($j['job_title']) ?></strong>
            <?php if ($j['city'] || $j['state_province']): ?>
            <br><span style="font-size:11px;color:var(--muted)">
              <?= e(implode(', ', array_filter([$j['city'], $j['state_province']]))) ?>
            </span>
            <?php endif; ?>
          </td>
          <td class="td-muted"><?= e($j['country']) ?></td>
          <td class="td-muted"><?= e($j['job_type']) ?></td>
          <td class="td-muted"><?= e($j['workplace_type']) ?></td>
          <td class="td-muted"><?= e($j['experience'] ?? '—') ?></td>
          <td class="td-muted" style="font-size:12px">
            <?= $j['open_date'] ? date('d M Y', strtotime($j['open_date'])) : '—' ?>
          </td>
          <?php
            $isExpired = !empty($j['close_date']) && strtotime($j['close_date']) < strtotime('today');
          ?>
          <td>
            <?php if ($isExpired): ?>
              <span class="badge badge-closed" title="Close date passed: <?= e($j['close_date']) ?>">Expired</span>
            <?php else: ?>
              <span class="badge badge-<?= e($j['status']) ?>"><?= ucfirst(e($j['status'])) ?></span>
            <?php endif; ?>
          </td>
          <td class="td-muted" style="font-size:12px"><?= e($j['posted_by'] ?? '—') ?></td>
          <td>
            <div style="display:flex;gap:6px">
              <?php if ($isExpired): ?>
              <button class="btn btn-ghost btn-sm" disabled title="Cannot edit — close date has passed"
                      style="opacity:0.4;cursor:not-allowed">✏</button>
              <?php else: ?>
              <a href="<?= ADMIN_URL ?>/pages/post_job.php?edit=<?= $j['id'] ?>"
                 class="btn btn-ghost btn-sm" title="Edit">✏</a>
              <?php if ($j['status'] !== 'published'): ?>
              <a href="<?= ADMIN_URL ?>/pages/job_action.php?id=<?= $j['id'] ?>&a=publish"
                 class="btn btn-primary btn-sm" title="Publish">▶</a>
              <?php else: ?>
              <a href="<?= ADMIN_URL ?>/pages/job_action.php?id=<?= $j['id'] ?>&a=close"
                 class="btn btn-ghost btn-sm" title="Close">■</a>
              <?php endif; ?>
              <?php endif; ?>
              <a href="<?= ADMIN_URL ?>/pages/post_job.php?clone=<?= $j['id'] ?>"
                 class="btn btn-ghost btn-sm" title="Clone Job"
                 onclick="return confirm('Clone this job? A new Job Number will be assigned.')">⧉</a>
              <a href="<?= ADMIN_URL ?>/pages/job_action.php?id=<?= $j['id'] ?>&a=delete"
                 class="btn btn-danger btn-sm" title="Delete"
                 onclick="return confirm('Delete this job permanently?')">🗑</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</form>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php if ($page > 1): ?>
  <a href="<?= pageUrl($page-1) ?>" class="pg">‹ Prev</a>
  <?php endif; ?>
  <?php
  $range = range(max(1,$page-2), min($totalPages,$page+2));
  foreach ($range as $pg):
  ?>
  <a href="<?= pageUrl($pg) ?>" class="pg <?= $pg===$page?'active':'' ?>"><?= $pg ?></a>
  <?php endforeach; ?>
  <?php if ($page < $totalPages): ?>
  <a href="<?= pageUrl($page+1) ?>" class="pg">Next ›</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<script>
// Check all checkbox
document.getElementById('checkAll')?.addEventListener('change', function(){
  document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>