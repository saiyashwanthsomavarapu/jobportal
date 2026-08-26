<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/utils/classes.php';
// ── Helper Functions ────────────────────────────────────────────
// Get current filter parameters as array
function getCurrentFilters(): array
{
  $filters = array_filter([
    'status' => $_GET['status'] ?? '',
    'country' => $_GET['country'] ?? '',
    'job_type' => $_GET['job_type'] ?? '',
    'workplace' => $_GET['workplace'] ?? '',
    'q' => trim($_GET['q'] ?? ''),
  ], fn($v) => $v !== '' && $v !== null);
  return $filters;
}

// Build URL with current filters preserved
function buildFilterUrl(string $base, array $extraParams = []): string
{
  $params = array_filter($_GET, fn($v) => $v !== '' && $v !== null);
  $params = array_merge($params, $extraParams);
  unset($params['page']); // Reset to page 1 when changing filters
  return $base . '?' . http_build_query($params);
}

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

if ($fStatus) {
  $where[] = 'j.status = ?';
  $params[] = $fStatus;
}
if ($fCountry) {
  $where[] = 'j.country = ?';
  $params[] = $fCountry;
}
if ($fType) {
  $where[] = 'j.job_type = ?';
  $params[] = $fType;
}
if ($fWork) {
  $where[] = 'j.workplace_type = ?';
  $params[] = $fWork;
}
if ($fSearch) {
  $where[]  = '(j.job_title LIKE ? OR j.job_code LIKE ? OR c.client_name LIKE ?)';
  $like = '%' . $fSearch . '%';
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
}

$whereSQL = implode(' AND ', $where);

try {
  $pdo = db();
  $totalRows = $pdo->prepare("SELECT COUNT(DISTINCT j.id)
    FROM jobs j
    LEFT JOIN clients c
        ON (
            c.id = j.client_id
            OR c.client_code = REGEXP_REPLACE(j.client_code, '[^0-9]', '')
        )
    WHERE $whereSQL");
  $totalRows->execute($params);
  $totalRows = (int)$totalRows->fetchColumn();
  $totalPages = max(1, (int)ceil($totalRows / $perPage));
  $offset = ($page - 1) * $perPage;

  $stmt = $pdo->prepare(
    "SELECT
      j.*,
      REGEXP_REPLACE(j.client_code, '[^0-9]', '') AS client_code,
      a.name AS posted_by,
      c.client_name
    FROM jobs j
    LEFT JOIN admin_users a
      ON a.id = j.created_by
    LEFT JOIN clients c
            ON (
                c.id = j.client_id
                OR LEFT(j.client_code, 4) = c.client_code
            )
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
  error_log('[jobs] query failed: ' . $e->getMessage());
  flash('error', 'Could not load jobs. Please try again.');
  $jobs = [];
  $totalRows = 0;
  $totalPages = 1;
  $allTypes = [];
  $allWork = [];
}

// ── Global status counts (for the summary cards) ─────────────
$jobStats = ['published' => 0, 'draft' => 0, 'closed' => 0];
try {
  foreach (db()->query("SELECT status, COUNT(*) AS c FROM jobs GROUP BY status") as $r) {
    if (array_key_exists($r['status'], $jobStats)) {
      $jobStats[$r['status']] = (int) $r['c'];
    }
  }
} catch (Exception $e) {
  error_log('[jobs] stats query failed: ' . $e->getMessage());
}
$totalJobsAll = array_sum($jobStats);

// ── Bulk / single actions ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
  csrf_verify();

  $ids     = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
  $action  = $_POST['action'];

  if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    try {
      switch ($action) {
        case 'publish':
          db()->prepare("UPDATE jobs SET status='published', published_at=NOW() WHERE id IN ($placeholders)")
            ->execute($ids);
          flash('success', count($ids) . ' job(s) published.');
          break;
        case 'draft':
          db()->prepare("UPDATE jobs SET status='draft' WHERE id IN ($placeholders)")
            ->execute($ids);
          flash('success', count($ids) . ' job(s) moved to draft.');
          break;
        case 'close':
          db()->prepare("UPDATE jobs SET status='closed' WHERE id IN ($placeholders)")
            ->execute($ids);
          flash('success', count($ids) . ' job(s) closed.');
          break;
        case 'delete':
          db()->prepare("DELETE FROM jobs WHERE id IN ($placeholders)")
            ->execute($ids);
          flash('success', count($ids) . ' job(s) deleted.');
          break;
      }
      logActivity($action . '_jobs', 'job', null, 'IDs: ' . implode(',', $ids));
    } catch (Exception $e) {
      error_log('[jobs] bulk action failed: ' . $e->getMessage());
      flash('error', 'The action could not be completed. Please try again.');
    }
  }
  // Preserve all filters when redirecting back
  redirect(ADMIN_URL . '/pages/jobs.php?' . http_build_query(getCurrentFilters()));
}

// ── Helpers ──────────────────────────────────────────────────
function pageUrl(int $pg): string
{
  $p = $_GET;
  $p['page'] = $pg;
  return '?' . http_build_query($p);
}

// Status badge color/label map.
function jobStatusBadge(string $status, bool $isExpired): string
{
  if ($isExpired) {
    return '<span class="badge badge-sm gap-1.5 border-base-300 bg-base-200 text-base-content/50"><span class="size-1.5 rounded-full bg-base-content/30"></span>Expired</span>';
  }
  $map = [
    'published' => ['border-success/25 bg-success/10 text-success', 'bg-success'],
    'draft'     => ['border-warning/25 bg-warning/10 text-warning', 'bg-warning'],
    'closed'    => ['border-base-300 bg-base-200 text-base-content/60', 'bg-base-content/40'],
    'archived'  => ['border-error/25 bg-error/10 text-error', 'bg-error'],
  ];
  [$classes, $dot] = $map[$status] ?? ['border-base-300 bg-base-200 text-base-content/60', 'bg-base-content/40'];
  return '<span class="badge badge-sm gap-1.5 border ' . $classes . '"><span class="size-1.5 rounded-full ' . $dot . '"></span>' . ucfirst(e($status)) . '</span>';
}

function wpBadgeClass(?string $wp): string
{
  $w = strtolower(trim((string) $wp));
  return match ($w) {
    'remote' => 'badge-info',
    'hybrid' => 'badge-warning',
    'onsite' => 'badge-success',
    default  => 'badge-neutral',
  };
}

$pageTitle   = 'All Jobs';
$jobsUrl = ADMIN_URL . '/pages/jobs.php' . (count(getCurrentFilters()) > 0 ? '?' . http_build_query(getCurrentFilters()) : '');
$breadcrumbs = [['Dashboard', ADMIN_URL . '/index.php'], ['All Jobs', $jobsUrl]];


include dirname(__DIR__) . '/includes/header.php';
?>

<div class="min-w-0 space-y-6">

  <!-- ═══════════ PAGE INTRO ═══════════ -->
  <section class="flex flex-wrap items-start justify-between gap-4">
    <div class="max-w-xl">
      <h2 class="font-head text-[21px] font-bold leading-tight tracking-tight text-base-content">
        Job postings
      </h2>
      <p class="mt-2 text-[13px] leading-relaxed text-base-content/60">
        Everything you've published, drafted or closed — filter, update in bulk,
        clone or remove postings from one place.
      </p>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= ADMIN_URL ?>/pages/post_job.php" class="<?= PRIMARY_BUTTON_CLASS ?> shadow-pop hover:-translate-y-px hover:opacity-100 hover:shadow-lg">
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Post a job
      </a>
    </div>
  </section>

  <!-- ═══════════ AT-A-GLANCE SUMMARY ═══════════ -->
  <section aria-label="Summary" class="grid grid-cols-2 gap-3 xl:grid-cols-4">

    <div class="stats border-base-300 bg-base-100 shadow-card transition duration-200 hover:-translate-y-0.5">
      <div class="stat px-4 py-3.5">
        <div class="stat-figure mb-0">
          <div class="grid size-9 place-items-center rounded-lg bg-primary/10 text-primary">
            <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.098a2.25 2.25 0 01-2.25 2.25h-12a2.25 2.25 0 01-2.25-2.25V14.15M16.5 6.75V5.25A2.25 2.25 0 0014.25 3h-4.5A2.25 2.25 0 007.5 5.25v1.5m6 0V7.5c0 .828-.672 1.5-1.5 1.5h-3c-.828 0-1.5-.672-1.5-1.5V6.75m6 0h3.688c.622 0 1.19.368 1.441.94l1.5 3.438a2.25 2.25 0 01.17.894v2.028a2.25 2.25 0 01-2.25 2.25h-.75M4.5 13.55v2.028c0 .32.068.635.17.894l1.5 3.437a2.25 2.25 0 001.44 1.44h.75m6.64-7.85h3.71" />
            </svg>
          </div>
        </div>
        <div class="stat-title text-[10.5px] font-semibold uppercase tracking-[0.08em] text-base-content/50">All jobs</div>
        <div class="stat-value font-head text-[20px] tabular-nums tracking-tight text-base-content"><?= $totalJobsAll ?></div>
        <div class="stat-desc text-[11px] text-base-content/45">every posting on file</div>
      </div>
    </div>

    <div class="stats border-base-300 bg-base-100 shadow-card transition duration-200 hover:-translate-y-0.5">
      <div class="stat px-4 py-3.5">
        <div class="stat-figure mb-0">
          <div class="grid size-9 place-items-center rounded-lg bg-success/10 text-success">
            <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
        <div class="stat-title text-[10.5px] font-semibold uppercase tracking-[0.08em] text-base-content/50">Published</div>
        <div class="stat-value font-head text-[20px] tabular-nums tracking-tight text-base-content"><?= $jobStats['published'] ?></div>
        <div class="stat-desc text-[11px] text-base-content/45">live on the careers page</div>
      </div>
    </div>

    <div class="stats border-base-300 bg-base-100 shadow-card transition duration-200 hover:-translate-y-0.5">
      <div class="stat px-4 py-3.5">
        <div class="stat-figure mb-0">
          <div class="grid size-9 place-items-center rounded-lg bg-warning/10 text-warning">
            <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-9-3.75h-1.5m1.5 0V21a2.25 2.25 0 002.25 2.25h13.5A2.25 2.25 0 0021 21V9a2.25 2.25 0 00-.659-1.591l-4.75-4.75A2.25 2.25 0 0013.999.75H6.75A2.25 2.25 0 004.5 3v6" />
            </svg>
          </div>
        </div>
        <div class="stat-title text-[10.5px] font-semibold uppercase tracking-[0.08em] text-base-content/50">Drafts</div>
        <div class="stat-value font-head text-[20px] tabular-nums tracking-tight text-base-content"><?= $jobStats['draft'] ?></div>
        <div class="stat-desc text-[11px] text-base-content/45">not published yet</div>
      </div>
    </div>

    <div class="stats border-base-300 bg-base-100 shadow-card transition duration-200 hover:-translate-y-0.5">
      <div class="stat px-4 py-3.5">
        <div class="stat-figure mb-0">
          <div class="grid size-9 place-items-center rounded-lg bg-neutral/10 text-neutral">
            <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
          </div>
        </div>
        <div class="stat-title text-[10.5px] font-semibold uppercase tracking-[0.08em] text-base-content/50">Closed</div>
        <div class="stat-value font-head text-[20px] tabular-nums tracking-tight text-base-content"><?= $jobStats['closed'] ?></div>
        <div class="stat-desc text-[11px] text-base-content/45">no longer accepting applications</div>
      </div>
    </div>

  </section>

  <!-- ══ FILTERS ══ -->
  <form
    method="GET"
    class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-card">
    <div class="flex flex-wrap items-center gap-3">

      <!-- Search -->
      <label class="input input-sm h-10 min-w-[220px] flex-1 items-center gap-2 border-transparent bg-base-200 focus-within:border-primary focus-within:bg-base-100">
        <svg class="size-4 shrink-0 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
        </svg>
        <input type="search" name="q" class="grow bg-transparent text-[13px] outline-none placeholder:text-base-content/35"
          placeholder="Search title, code or client…" value="<?= e($fSearch) ?>" />
      </label>

      <!-- Status -->
      <select name="status" aria-label="Filter by status" onchange="this.form.submit()"
        class="select select-sm h-10 min-h-10 w-auto min-w-[140px] border-transparent bg-base-200 text-[12.5px] font-medium focus:border-primary focus:bg-base-100 focus:outline-none">
        <option value="">All statuses</option>
        <?php foreach (['published', 'draft', 'closed', 'archived'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $fStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>

      <!-- Country -->
      <select name="country" aria-label="Filter by country" onchange="this.form.submit()"
        class="select select-sm h-10 min-h-10 w-auto min-w-[140px] border-transparent bg-base-200 text-[12.5px] font-medium focus:border-primary focus:bg-base-100 focus:outline-none">
        <option value="">All countries</option>
        <?php foreach (['India', 'United States', 'Canada'] as $c): ?>
          <option value="<?= e($c) ?>" <?= $fCountry === $c ? 'selected' : '' ?>><?= e($c) ?></option>
        <?php endforeach; ?>
      </select>

      <!-- Job Type -->
      <select name="job_type" aria-label="Filter by job type" onchange="this.form.submit()"
        class="select select-sm h-10 min-h-10 w-auto min-w-[140px] border-transparent bg-base-200 text-[12.5px] font-medium focus:border-primary focus:bg-base-100 focus:outline-none">
        <option value="">All job types</option>
        <?php foreach ($allTypes as $t): ?>
          <option value="<?= e($t) ?>" <?= $fType === $t ? 'selected' : '' ?>><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>

      <!-- Workplace -->
      <select name="workplace" aria-label="Filter by workplace" onchange="this.form.submit()"
        class="select select-sm h-10 min-h-10 w-auto min-w-[140px] border-transparent bg-base-200 text-[12.5px] font-medium focus:border-primary focus:bg-base-100 focus:outline-none">
        <option value="">All workplaces</option>
        <?php foreach ($allWork as $w): ?>
          <option value="<?= e($w) ?>" <?= $fWork === $w ? 'selected' : '' ?>><?= e($w) ?></option>
        <?php endforeach; ?>
      </select>

      <!-- Actions -->
      <div class="flex items-center gap-2">
        <button type="submit" class="<?= PRIMARY_BUTTON_CLASS ?>">
          <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
          </svg>
          Filter
        </button>
        <a href="<?= ADMIN_URL ?>/pages/jobs.php" id="clearFilters" class="<?= SECONDARY_BUTTON_CLASS ?>">Clear</a>
      </div>

    </div>
  </form>

  <!-- ══ BULK ACTIONS + TABLE ══ -->
  <form method="POST" id="bulkForm">
    <?= csrf_field() ?>
    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-card">

      <!-- Toolbar -->
      <div class="flex flex-col gap-3 border-b border-base-300 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="mr-auto flex items-center gap-3">
          <div class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
            <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.098a2.25 2.25 0 01-2.25 2.25h-12a2.25 2.25 0 01-2.25-2.25V14.15M16.5 6.75V5.25A2.25 2.25 0 0014.25 3h-4.5A2.25 2.25 0 007.5 5.25v1.5m6 0V7.5c0 .828-.672 1.5-1.5 1.5h-3c-.828 0-1.5-.672-1.5-1.5V6.75m6 0h3.688c.622 0 1.19.368 1.441.94l1.5 3.438a2.25 2.25 0 01.17.894v2.028a2.25 2.25 0 01-2.25 2.25h-.75M4.5 13.55v2.028c0 .32.068.635.17.894l1.5 3.437a2.25 2.25 0 001.44 1.44h.75m6.64-7.85h3.71" />
            </svg>
          </div>
          <div>
            <h2 class="font-head text-[15px] font-bold tracking-tight text-base-content">All jobs</h2>
            <p class="text-[11.5px] text-base-content/50">
              Showing <strong class="font-semibold text-base-content"><?= count($jobs) ?></strong>
              of <strong class="font-semibold text-base-content"><?= $totalRows ?></strong> jobs
            </p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <select name="action" id="bulkAction" aria-label="Bulk action"
            class="select select-sm h-9 min-h-9 border-transparent bg-base-200 text-[12.5px] font-medium focus:border-primary focus:bg-base-100 focus:outline-none">
            <option value="">Bulk action…</option>
            <option value="publish">Publish</option>
            <option value="draft">Move to draft</option>
            <option value="close">Close</option>
            <option value="delete">Delete</option>
          </select>
          <button type="button" onclick="openBulkActionModal()" id="bulkApplyBtn" disabled
            class="<?= SECONDARY_BUTTON_CLASS ?> disabled:opacity-50">
            Apply
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="<?= TABLE_CLASS ?>">
          <thead class="<?= TABLE_HEAD_CLASS ?>">
            <tr>
              <th class="w-10 border-b border-base-300 px-4 py-3">
                <input type="checkbox" id="checkAll" title="Select all" class="checkbox checkbox-sm rounded">
              </th>
              <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Job code</th>
              <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Client</th>
              <th class="min-w-[260px] border-b border-base-300 px-4 py-3 text-[11px] font-bold uppercase tracking-wide whitespace-nowrap">Title</th>
              <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Country</th>
              <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Type</th>
              <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Workplace</th>
              <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Experience</th>
              <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Open date</th>
              <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Status</th>
              <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Posted by</th>
              <th class="<?= TABLE_HEAD_ROW_CLASS ?>"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($jobs)): ?>
              <tr>
                <td colspan="12">
                  <div class="py-14 text-center">
                    <div class="mx-auto grid size-16 place-items-center rounded-full border-2 border-dashed border-base-300 bg-base-200/50 text-base-content/40">
                      <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.098a2.25 2.25 0 01-2.25 2.25h-12a2.25 2.25 0 01-2.25-2.25V14.15M16.5 6.75V5.25A2.25 2.25 0 0014.25 3h-4.5A2.25 2.25 0 007.5 5.25v1.5m6 0V7.5c0 .828-.672 1.5-1.5 1.5h-3c-.828 0-1.5-.672-1.5-1.5V6.75m6 0h3.688c.622 0 1.19.368 1.441.94l1.5 3.438a2.25 2.25 0 01.17.894v2.028a2.25 2.25 0 01-2.25 2.25h-.75M4.5 13.55v2.028c0 .32.068.635.17.894l1.5 3.437a2.25 2.25 0 001.44 1.44h.75m6.64-7.85h3.71" />
                      </svg>
                    </div>
                    <h4 class="mt-4 font-head text-[15px] font-bold text-base-content">
                      <?= $totalRows === 0 && count(getCurrentFilters()) > 0 ? 'No jobs match your filters' : 'No jobs yet' ?>
                    </h4>
                    <p class="mx-auto mt-1 max-w-xs text-[12.5px] leading-relaxed text-base-content/50">
                      <?= $totalRows === 0 && count(getCurrentFilters()) > 0
                        ? 'Try changing or clearing the filters above.'
                        : 'Post your first job to see it listed here.' ?>
                    </p>
                    <?php if (count(getCurrentFilters()) > 0): ?>
                      <a href="<?= ADMIN_URL ?>/pages/jobs.php" class="<?= SECONDARY_BUTTON_CLASS ?> mt-4">Clear filters</a>
                    <?php else: ?>
                      <a href="<?= ADMIN_URL ?>/pages/post_job.php" class="<?= PRIMARY_BUTTON_CLASS ?> mt-4 shadow-pop">Post a job</a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($jobs as $j): ?>
                <?php $isExpired = !empty($j['close_date']) && strtotime($j['close_date']) < strtotime('today'); ?>
                <?php
                $jobDetailUrl = ADMIN_URL . '/pages/post_job.php?edit=' . e($j['id']);
                if ($j['client_code']) {
                  $parts = explode('-', $j['client_code']);
                  $jobCode = $parts[1] ?? '';
                  if ($jobCode) {
                    $jobDetailUrl .= '&form_jobcode=' . e($jobCode);
                  }
                }
                ?>
                <tr class="cursor-pointer border-b border-base-300/60 transition-colors last:border-b-0 hover:bg-base-200/60"
                  onclick="window.open('<?= $jobDetailUrl ?>', '_blank')">
                  <td class="px-4 py-3.5 align-middle" onclick="event.stopPropagation()">
                    <input type="checkbox" name="ids[]" value="<?= $j['id'] ?>" class="row-check checkbox checkbox-sm rounded" aria-label="Select job <?= e($j['job_code']) ?>">
                  </td>
                  <td class="px-4 py-3.5 align-middle">
                    <code class="badge badge-soft badge-primary rounded-md font-mono text-[11px] font-semibold">
                      <?= e($j['job_code']) ?>
                    </code>
                    <?php if ($j['client_code']): ?>
                      <div class="mt-1 text-[11px] text-base-content/45"> Job code: <?= e($j['client_code']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-3.5 align-middle font-medium text-base-content/70"><?= e($j['client_name'] ?? '—') ?></td>
                  <td class="px-4 py-3.5 align-middle">
                    <strong class="block max-w-[320px] truncate text-[13px] font-semibold text-base-content"><?= e($j['job_title']) ?></strong>
                    <?php if ($j['city'] || $j['state_province']): ?>
                      <div class="mt-1 max-w-[320px] truncate text-[11px] text-base-content/45">
                        <?= e(implode(', ', array_filter([$j['city'], $j['state_province']]))) ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-3.5 align-middle text-base-content/65"><?= e($j['country']) ?></td>
                  <td class="px-4 py-3.5 align-middle text-base-content/65"><?= e($j['job_type']) ?></td>
                  <td class="px-4 py-3.5 align-middle">
                    <span class="badge badge-soft badge-sm whitespace-nowrap <?= wpBadgeClass($j['workplace_type']) ?> capitalize"><?= e($j['workplace_type']) ?></span>
                  </td>
                  <td class="px-4 py-3.5 align-middle text-base-content/65"><?= e($j['experience'] ?? '—') ?></td>
                  <td class="whitespace-nowrap px-4 py-3.5 align-middle text-[12px] tabular-nums text-base-content/55">
                    <?= $j['open_date'] ? date('d M Y', strtotime($j['open_date'])) : '—' ?>
                  </td>
                  <td class="px-4 py-3.5 align-middle">
                    <?= jobStatusBadge($j['status'], $isExpired) ?>
                  </td>
                  <td class="whitespace-nowrap px-4 py-3.5 align-middle">
                    <span class="inline-flex items-center gap-1.5 text-[12px] text-base-content/55">
                      <svg class="size-3.5 shrink-0 text-base-content/35" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                      </svg>
                      <?= e($j['posted_by'] ?? '—') ?>
                    </span>
                  </td>
                  <td class="px-4 py-3.5 align-middle" onclick="event.stopPropagation()">
                    <div class="flex items-center justify-end gap-1">
                      <?php if ($isExpired): ?>
                        <span class="tooltip tooltip-left" data-tip="Cannot edit — close date has passed">
                          <span class="btn btn-square btn-sm h-8 min-h-8 w-8 cursor-not-allowed rounded-full bg-base-200 text-base-content/30" aria-disabled="true">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                            </svg>
                          </span>
                        </span>
                      <?php else: ?>
                        <div class="tooltip tooltip-left" data-tip="Edit">
                          <a href="<?= buildFilterUrl(ADMIN_URL . '/pages/post_job.php', ['edit' => $j['id']]) ?>" title="Edit"
                            onclick="event.stopPropagation()" aria-label="Edit job"
                            class="btn btn-square btn-sm h-8 min-h-8 w-8 rounded-full border-transparent bg-transparent text-base-content/50 transition-all hover:bg-primary/10 hover:text-primary">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                            </svg>
                          </a>
                        </div>
                        <?php if ($j['status'] !== 'published'): ?>
                          <div class="tooltip tooltip-left" data-tip="Publish">
                            <a href="<?= buildFilterUrl(ADMIN_URL . '/pages/job_action.php', ['id' => $j['id'], 'a' => 'publish']) ?>" title="Publish"
                              onclick="event.stopPropagation()" aria-label="Publish job"
                              class="btn btn-square btn-sm h-8 min-h-8 w-8 rounded-full border-transparent bg-transparent text-base-content/50 transition-all hover:bg-success/10 hover:text-success">
                              <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z" />
                              </svg>
                            </a>
                          </div>
                        <?php else: ?>
                          <div class="tooltip tooltip-left" data-tip="Close">
                            <a href="<?= buildFilterUrl(ADMIN_URL . '/pages/job_action.php', ['id' => $j['id'], 'a' => 'close']) ?>" title="Close"
                              onclick="event.stopPropagation()" aria-label="Close job"
                              class="btn btn-square btn-sm h-8 min-h-8 w-8 rounded-full border-transparent bg-transparent text-base-content/50 transition-all hover:bg-warning/10 hover:text-warning">
                              <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <rect x="6" y="6" width="12" height="12" rx="1.5" />
                              </svg>
                            </a>
                          </div>
                        <?php endif; ?>
                      <?php endif; ?>
                      <div class="tooltip tooltip-left" data-tip="Clone">
                        <button
                          type="button"
                          title="Clone job"
                          aria-label="Clone job"
                          onclick="openCloneModal(
                          '<?= e($j['id']) ?>',
                          '<?= e($j['job_title']) ?>',
                          '<?= e(buildFilterUrl(ADMIN_URL . '/pages/post_job.php', ['clone' => $j['id']])) ?>'
                        )"
                          class="btn btn-square btn-sm h-8 min-h-8 w-8 rounded-full border-transparent bg-transparent text-base-content/50 transition-all hover:bg-primary/10 hover:text-primary">
                          <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                          </svg>
                        </button>
                      </div>
                      <div class="tooltip tooltip-left" data-tip="Delete">
                        <button
                          type="button"
                          title="Delete"
                          aria-label="Delete job"
                          onclick="openDeleteModal(
                          '<?= e($j['id']) ?>',
                          '<?= e($j['job_title']) ?>',
                          '<?= e(buildFilterUrl(ADMIN_URL . '/pages/job_action.php', ['id' => $j['id'], 'a' => 'delete'])) ?>'
                        )"
                          class="btn btn-square btn-sm h-8 min-h-8 w-8 rounded-full border-transparent bg-transparent text-base-content/50 transition-all hover:bg-error/10 hover:text-error">
                          <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                          </svg>
                        </button>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

      </div>
    </section>
  </form>

  <!-- Delete Modal -->
  <dialog id="deleteModal" class="modal">
    <div class="modal-box w-[calc(100%-2rem)] max-w-md rounded-3xl border border-base-300/70 bg-base-100 p-0 shadow-xl">
      <!-- Header -->
      <div class="flex items-center justify-between gap-3 px-5 pb-1 pt-5">
        <div class="min-w-0">
          <h3 class="font-head text-[16px] font-bold tracking-tight text-base-content">Delete job posting?</h3>
          <p class="mt-0.5 text-[11.5px] text-base-content/50">This action is permanent.</p>
        </div>
        <button
          type="button"
          onclick="deleteModal.close()"
          class="btn btn-sm btn-circle btn-ghost size-8 min-h-8 shrink-0 text-base-content/50 hover:bg-base-200 hover:text-base-content"
          aria-label="Close">
          <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      <div class="px-5 py-5">
        <div class="rounded-2xl border border-error/15 bg-error/5 p-4">
          <p class="text-[13px] leading-relaxed text-base-content">
            You're about to delete <strong id="deleteJobName" class="text-error"></strong>.
          </p>
          <ul class="mt-2.5 space-y-1.5 text-[12px] leading-relaxed text-base-content/70">
            <li class="flex items-start gap-2">
              <span class="mt-[7px] size-1 shrink-0 rounded-full bg-error/60" aria-hidden="true"></span>
              The posting is removed from the careers page immediately.
            </li>
            <li class="flex items-start gap-2">
              <span class="mt-[7px] size-1 shrink-0 rounded-full bg-error/60" aria-hidden="true"></span>
              This cannot be undone.
            </li>
          </ul>
        </div>
      </div>
      <div class="modal-action m-0 flex flex-col-reverse gap-2 border-t border-base-200/80 bg-base-200/40 px-5 py-4 sm:flex-row sm:justify-end">
        <button
          type="button"
          onclick="deleteModal.close()"
          class="btn h-10 min-h-10 w-full rounded-full border border-base-300 bg-base-100 px-5 text-sm font-semibold text-base-content/80 hover:bg-base-200 hover:text-base-content sm:w-auto">
          Cancel
        </button>
        <a
          id="confirmDeleteBtn"
          href="#"
          class="btn btn-error h-10 min-h-10 w-full rounded-full px-6 text-sm font-semibold sm:w-auto">
          Delete job
        </a>
      </div>
    </div>
    <form method="dialog" class="modal-backdrop bg-black/40 backdrop-blur-sm">
      <button>close</button>
    </form>
  </dialog>

  <!-- Clone Modal -->
  <dialog id="cloneModal" class="modal">
    <div class="modal-box w-[calc(100%-2rem)] max-w-md rounded-3xl border border-base-300/70 bg-base-100 p-0 shadow-xl">
      <div class="flex items-center justify-between gap-3 px-5 pb-1 pt-5">
        <div class="min-w-0">
          <h3 class="font-head text-[16px] font-bold tracking-tight text-base-content">Clone job posting</h3>
          <p class="mt-0.5 truncate text-[11.5px] text-base-content/50">
            Copy of <strong id="cloneJobName" class="text-primary"></strong>
          </p>
        </div>
        <button
          type="button"
          onclick="cloneModal.close()"
          class="btn btn-sm btn-circle btn-ghost size-8 min-h-8 shrink-0 text-base-content/50 hover:bg-base-200 hover:text-base-content"
          aria-label="Close">
          <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      <div class="px-5 py-5">
        <div class="rounded-2xl border border-primary/15 bg-primary/5 p-4">
          <p class="text-[13px] leading-relaxed text-base-content">
            A new draft job will be created with a fresh job number, copying all details from this posting.
          </p>
          <p class="mt-2 text-[11.5px] text-base-content/50">
            Nothing changes on the original job — you can edit the copy before publishing it.
          </p>
        </div>
      </div>
      <div class="modal-action m-0 flex flex-col-reverse gap-2 border-t border-base-200/80 bg-base-200/40 px-5 py-4 sm:flex-row sm:justify-end">
        <button
          type="button"
          onclick="cloneModal.close()"
          class="btn btn-ghost h-10 min-h-10 w-full rounded-full px-5 text-sm font-semibold text-base-content/70 hover:bg-base-200 hover:text-base-content sm:w-auto">
          Cancel
        </button>
        <a
          id="confirmCloneBtn"
          href="#"
          class="btn btn-primary h-10 min-h-10 w-full rounded-full px-6 text-sm font-semibold shadow-pop sm:w-auto">
          Clone job
        </a>
      </div>
    </div>

    <form method="dialog" class="modal-backdrop bg-black/40 backdrop-blur-sm">
      <button>close</button>
    </form>
  </dialog>

  <!-- Bulk action modal -->
  <dialog id="bulkActionModal" class="modal">
    <div
      class="modal-box w-[calc(100%-2rem)] max-w-md rounded-3xl border border-base-300/70 bg-base-100 p-0 shadow-xl">
      <!-- Header -->
      <div class="flex items-center justify-between gap-3 px-5 pb-1 pt-5">
        <div class="min-w-0">
          <h3 class="font-head text-[16px] font-bold tracking-tight text-base-content">Apply bulk action</h3>
          <p class="mt-0.5 text-[11.5px] text-base-content/50">Confirm before continuing.</p>
        </div>
        <button
          type="button"
          onclick="bulkActionModal.close()"
          class="btn btn-sm btn-circle btn-ghost size-8 min-h-8 shrink-0 text-base-content/50 hover:bg-base-200 hover:text-base-content"
          aria-label="Close">
          <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Body -->
      <div class="px-5 py-5">
        <div class="rounded-2xl border border-warning/20 bg-warning/5 p-4">
          <p class="text-[13px] leading-relaxed text-base-content">
            You're about to apply
            <strong id="selectedAction" class="capitalize text-warning"></strong>
            to <strong id="selectedJobCount" class="tabular-nums">0</strong>
            selected job<span id="selectedJobPlural">s</span>.
          </p>
          <p class="mt-2 text-[11.5px] leading-relaxed text-base-content/60">
            Please review your selection before continuing — this updates every selected posting at once.
          </p>
        </div>
      </div>

      <!-- Footer -->
      <div class="modal-action m-0 flex flex-col-reverse gap-2 border-t border-base-200/80 bg-base-200/40 px-5 py-4 sm:flex-row sm:justify-end">
        <button
          type="button"
          onclick="bulkActionModal.close()"
          class="btn btn-ghost h-10 min-h-10 w-full rounded-full px-5 text-sm font-semibold text-base-content/70 hover:bg-base-200 hover:text-base-content sm:w-auto">
          Cancel
        </button>
        <button
          type="button"
          onclick="submitBulkAction()"
          id="bulkConfirmBtn"
          class="btn btn-primary h-10 min-h-10 w-full rounded-full px-6 text-sm font-semibold shadow-pop sm:w-auto">
          Apply action
        </button>
      </div>
    </div>

    <!-- Backdrop -->
    <form method="dialog" class="modal-backdrop bg-black/40 backdrop-blur-sm">
      <button type="submit">close</button>
    </form>
  </dialog>

  <!-- ══ PAGINATION ══ -->
  <?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-end">
      <div class="join">
        <?php if ($page > 1): ?>
          <a href="<?= pageUrl($page - 1) ?>"
            class="btn btn-sm join-item border-base-300 bg-base-100 text-base-content/70 hover:bg-base-200">‹ Prev</a>
        <?php endif; ?>

        <?php
        $range = range(max(1, $page - 2), min($totalPages, $page + 2));
        foreach ($range as $pg):
        ?>
          <a href="<?= pageUrl($pg) ?>"
            class="btn btn-sm join-item <?= $pg === $page ? 'btn-primary' : 'border-base-300 bg-base-100 text-base-content/70 hover:bg-base-200' ?>">
            <?= $pg ?>
          </a>
        <?php endforeach; ?>

        <?php if ($page < $totalPages): ?>
          <a href="<?= pageUrl($page + 1) ?>"
            class="btn btn-sm join-item border-base-300 bg-base-100 text-base-content/70 hover:bg-base-200">Next ›</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

</div>

<script>
  /* ── Row selection & bulk UI ─────────────────────────────── */
  function selectedJobCount() {
    return document.querySelectorAll('input[name="ids[]"]:checked').length;
  }

  function updateBulkUi() {
    var btn = document.getElementById('bulkApplyBtn');
    if (btn) btn.disabled = selectedJobCount() === 0;
  }

  function openDeleteModal(id, jobName, deleteUrl) {
    document.getElementById('deleteJobName').textContent = jobName;
    document.getElementById('confirmDeleteBtn').href = deleteUrl;
    deleteModal.showModal();
  }

  function openCloneModal(id, jobName, cloneUrl) {
    document.getElementById('cloneJobName').textContent = jobName;
    document.getElementById('confirmCloneBtn').href = cloneUrl;
    cloneModal.showModal();
  }

  function openBulkActionModal() {
    var count = selectedJobCount();
    var actionEl = document.getElementById('bulkAction');
    if (count === 0 || !actionEl.value) return;

    var labels = { publish: 'Publish', draft: 'Move to draft', close: 'Close', delete: 'Delete' };
    document.getElementById('selectedJobCount').textContent = count;
    document.getElementById('selectedJobPlural').textContent = count === 1 ? '' : 's';
    document.getElementById('selectedAction').textContent = labels[actionEl.value] || actionEl.value;

    bulkActionModal.showModal();
  }

  function submitBulkAction() {
    bulkActionModal.close();
    document.getElementById('bulkForm').submit();
  }

  // Check all + live bulk button state
  document.getElementById('checkAll')?.addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
    updateBulkUi();
  });
  document.addEventListener('change', function(e) {
    if (e.target.classList && e.target.classList.contains('row-check')) updateBulkUi();
  });

  updateBulkUi();
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
