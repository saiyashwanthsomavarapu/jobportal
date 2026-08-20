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
  $where[]  = '(j.job_title LIKE ? OR j.job_code LIKE ? OR j.client_code LIKE ?)';
  $like = '%' . $fSearch . '%';
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
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
  $jobs = [];
  $totalRows = 0;
  $totalPages = 1;
  $allTypes = [];
  $allWork = [];
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
      flash('error', 'Action failed: ' . $e->getMessage());
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

// Status badge color/label map — used by the dark table below
function jobStatusBadge(string $status, bool $isExpired): string
{
  if ($isExpired) {
    return '<span class="inline-flex items-center gap-1.5 bg-gray-100 text-gray-400 border border-gray-200 rounded-full text-[11px] font-semibold px-2.5 py-1"><span class="w-1.5 h-1.5 rounded-full bg-white/30"></span>Expired</span>';
  }
  $map = [
    'published' => ['bg-emerald-600/15 text-emerald-600 border border-emerald-600/25', 'bg-emerald-600'],
    'draft'     => ['bg-amber-600/15 text-amber-600 border border-amber-600/25', 'bg-amber-600'],
    'closed'    => ['bg-gray-100 text-gray-400 border border-gray-200', 'bg-white/30'],
    'archived'  => ['bg-red-600/10 text-red-600 border border-red-600/20', 'bg-red-600'],
  ];
  [$classes, $dot] = $map[$status] ?? ['bg-gray-100 text-gray-400 border border-gray-200', 'bg-white/30'];
  return '<span class="inline-flex items-center gap-1.5 rounded-full text-[11px] font-semibold px-2.5 py-1 ' . $classes . '"><span class="w-1.5 h-1.5 rounded-full ' . $dot . '"></span>' . ucfirst(e($status)) . '</span>';
}

$pageTitle   = 'All Jobs';
$jobsUrl = ADMIN_URL . '/pages/jobs.php' . (count(getCurrentFilters()) > 0 ? '?' . http_build_query(getCurrentFilters()) : '');
$breadcrumbs = [['Dashboard', ADMIN_URL . '/index.php'], ['All Jobs', $jobsUrl]];

include dirname(__DIR__) . '/includes/header.php';
?>

<!-- ══════════════ DARK CANVAS ══════════════
     Same pattern as admins.php / clients.php: breaks out of the light shell's
     padding so this page renders as a self-contained dark panel while the
     sidebar/topbar stay light. Backgrounds carry both the Tailwind class AND
     a hard inline style so they render even if the Tailwind CDN script fails. -->
<div class="min-w-0 space-y-6">

  <!-- Top row: breadcrumb + primary action -->
  <div class="flex items-center justify-end flex-wrap gap-3 mb-6">
    <!-- <div class="text-[13px] text-gray-500 flex items-center gap-2">
      <a href="<?= ADMIN_URL ?>/index.php" class="hover:text-white transition-colors">Dashboard</a>
      <span class="text-white/25">/</span>
      <span class="text-white">All Jobs</span>
      <?php if (count(getCurrentFilters()) > 0): ?>
        <span class="text-white/25">(filtered)</span>
      <?php endif; ?>
    </div> -->
    <!-- <a href="<?= buildFilterUrl(ADMIN_URL . '/pages/post_job.php') ?>"
      class="flex items-center gap-1.5 bg-blue-600 text-gray-900 text-[12.5px] font-bold rounded-lg px-4 py-2 hover:bg-[#3a7cd6] transition-colors shadow-sm">
      <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
      </svg>
      New Job
    </a> -->
  </div>

  <!-- ══ FILTERS ══ -->
  <form
    method="GET"
    class="mb-5 rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm sm:p-5">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:gap-3">

      <!-- Search -->
      <fieldset class="fieldset min-w-0 flex-1 xl:min-w-[240px]">
        <legend class="fieldset-legend text-xs font-medium">
          Search
        </legend>

        <input
          type="text"
          name="q"
          class="<?= INPUT_CLASS ?>"
          placeholder="Title / Code / Client"
          value="<?= e($fSearch) ?>" />
      </fieldset>


      <!-- Status -->
      <fieldset class="fieldset w-full xl:w-[180px] 2xl:w-[200px]">
        <legend class="fieldset-legend text-xs font-medium">
          Status
        </legend>

        <select
          name="status"
          id="status"
          class="<?= SELECT_CLASS ?>">
          <option value="">All Status</option>

          <?php foreach (['published', 'draft', 'closed', 'archived'] as $s): ?>
            <option
              value="<?= e($s) ?>"
              <?= $fStatus === $s ? 'selected' : '' ?>>
              <?= ucfirst($s) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </fieldset>


      <!-- Country -->
      <fieldset class="fieldset w-full xl:w-[180px] 2xl:w-[200px]">
        <legend class="fieldset-legend text-xs font-medium">
          Country
        </legend>

        <select
          name="country"
          id="country"
          class="<?= SELECT_CLASS ?>">
          <option value="">All Countries</option>

          <?php foreach (['India', 'United States', 'Canada'] as $c): ?>
            <option
              value="<?= e($c) ?>"
              <?= $fCountry === $c ? 'selected' : '' ?>>
              <?= e($c) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </fieldset>


      <!-- Job Type -->
      <fieldset class="fieldset w-full xl:w-[180px] 2xl:w-[200px]">
        <legend class="fieldset-legend text-xs font-medium">
          Job Type
        </legend>

        <select
          name="job_type"
          id="jobType"
          class="<?= SELECT_CLASS ?>">
          <option value="">All Types</option>

          <?php foreach ($allTypes as $t): ?>
            <option
              value="<?= e($t) ?>"
              <?= $fType === $t ? 'selected' : '' ?>>
              <?= e($t) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </fieldset>


      <!-- Workplace -->
      <fieldset class="fieldset w-full xl:w-[180px] 2xl:w-[200px]">
        <legend class="fieldset-legend text-xs font-medium">
          Workplace
        </legend>

        <select
          name="workplace"
          id="workplace"
          class="<?= SELECT_CLASS ?>">
          <option value="">All Workplaces</option>

          <?php foreach ($allWork as $w): ?>
            <option
              value="<?= e($w) ?>"
              <?= $fWork === $w ? 'selected' : '' ?>>
              <?= e($w) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </fieldset>


      <!-- Actions -->
      <div class="flex w-full shrink-0 items-center gap-2 xl:w-auto">

        <!-- Filter -->
        <button
          type="submit"
          class="btn btn-primary shrink-0 gap-1.5">
          <svg
            class="size-3.5 shrink-0"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2"
            aria-hidden="true">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
          </svg>

          Filter
        </button>


        <!-- Clear -->
        <a
          href="<?= ADMIN_URL ?>/pages/jobs.php"
          id="clearFilters"
          class="btn btn-outline shrink-0">
          Clear
        </a>


        <!-- Active filters -->
        <?php $filterCount = count(getCurrentFilters()); ?>

        <?php if ($filterCount > 0): ?>
          <span
            class="badge badge-ghost shrink-0 whitespace-nowrap px-2.5 text-[11px] text-base-content/60">
            <?= $filterCount ?>
            filter<?= $filterCount > 1 ? 's' : '' ?> active
          </span>
        <?php endif; ?>

      </div>

      <div class="flex items-center justify-end gap-3">
        <!-- Cancel -->
        <button type="button" class="btn btn-sm bg-base-100 border-base-300 hover:bg-base-200">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-4 w-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
          Cancel
        </button>

        <!-- Save Draft -->
        <button type="button" class="btn btn-sm bg-base-100 border-base-300 hover:bg-base-200">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-4 w-4"
            fill="currentColor"
            viewBox="0 0 24 24">
            <path d="M5 3a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7.828A2 2 0 0 0 20.414 6.4l-2.8-2.8A2 2 0 0 0 16.172 3H5zm2 2h8v4H7V5zm10 14H7v-6h10v6z" />
          </svg>
          Save draft
        </button>

        <!-- Publish -->
        <button type="submit" class="btn btn-sm btn-primary shadow-sm">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-4 w-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M12 19l9 2-9-18-9 18 9-2zm0 0v-5" />
          </svg>
          Publish product
        </button>
      </div>
    </div>
  </form>

  <!-- ══ BULK ACTIONS + TABLE ══ -->
  <form method="POST" id="bulkForm">
    <div class="rounded-2xl overflow-hidden border border-gray-200">

      <!-- Toolbar -->
      <div class="flex items-center justify-between gap-3 flex-wrap px-6 py-4 border-b border-gray-200">
        <span class="text-[13px] text-gray-500">
          Showing <strong class="text-white"><?= count($jobs) ?></strong>
          of <strong class="text-white"><?= $totalRows ?></strong> jobs
        </span>
        <div class="flex items-center gap-2">
          <select name="action"
            class="border border-gray-200 rounded-lg px-3 py-2 text-[12.5px] text-gray-900 outline-none focus:border-blue-600 transition appearance-none
                       bg-[url('data:image/svg+xml,%3Csvg_xmlns=%22http://www.w3.org/2000/svg%22_width=%2212%22_height=%228%22_viewBox=%220_0_12_8%22%3E%3Cpath_fill=%22%23ffffff88%22_d=%22M1_1l5_5_5-5%22/%3E%3C/svg%3E')] bg-no-repeat bg-[right_10px_center] pr-8">
            <option value="" class="bg-gray-50">Bulk Action…</option>
            <option value="publish" class="bg-gray-50">Publish</option>
            <option value="draft" class="bg-gray-50">Move to Draft</option>
            <option value="close" class="bg-gray-50">Close</option>
            <option value="delete" class="bg-gray-50">Delete</option>
          </select>
          <button type="submit"
            onclick="return confirm('Apply this action to selected jobs?')"
            class="text-[12.5px] font-semibold text-gray-700 border border-gray-200 rounded-lg px-3.5 py-2 hover:bg-gray-50 hover:text-white transition-colors">
            Apply
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 border-b border-gray-200">
                <input type="checkbox" id="checkAll" title="Select all" class="w-4 h-4 cursor-pointer" style="accent-color:#2f6fc4">
              </th>
              <th class="text-left px-4 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-200 whitespace-nowrap">Job Code</th>
              <th class="text-left px-4 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-200 whitespace-nowrap">Client Name</th>
              <th class="text-left px-4 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-200 whitespace-nowrap">Title</th>
              <th class="text-left px-4 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-200 whitespace-nowrap">Country</th>
              <th class="text-left px-4 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-200 whitespace-nowrap">Job Type</th>
              <th class="text-left px-4 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-200 whitespace-nowrap">Workplace</th>
              <th class="text-left px-4 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-200 whitespace-nowrap">Experience</th>
              <th class="text-left px-4 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-200 whitespace-nowrap">Open Date</th>
              <th class="text-left px-4 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-200 whitespace-nowrap">Status</th>
              <th class="text-left px-4 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-200 whitespace-nowrap">Posted By</th>
              <th class="text-left px-4 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-200 whitespace-nowrap">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($jobs)): ?>
              <tr>
                <td colspan="12" class="text-center text-gray-400 py-10">
                  No jobs found. <a href="<?= ADMIN_URL ?>/pages/post_job.php" class="text-blue-600 hover:underline">Post one &rarr;</a>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($jobs as $j): ?>
                <?php $isExpired = !empty($j['close_date']) && strtotime($j['close_date']) < strtotime('today'); ?>
                <tr class="border-b border-gray-100 last:border-b-0 hover:bg-gray-50 transition-colors">
                  <td class="px-4 py-3 align-middle">
                    <input type="checkbox" name="ids[]" value="<?= $j['id'] ?>" class="row-check w-4 h-4 cursor-pointer" style="accent-color:#2f6fc4">
                  </td>
                  <td class="px-4 py-3 align-middle">
                    <code class="bg-blue-600/15 text-blue-600 rounded-md px-2 py-0.5 text-[12px] font-semibold tracking-wide">
                      <?= e($j['job_code']) ?>
                    </code>
                    <?php if ($j['client_code']): ?>
                      <div class="text-[11px] text-gray-400 mt-0.5"><?= e($j['client_code']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-3 align-middle text-gray-600"><?= e($j['client_name'] ?? '—') ?></td>
                  <td class="px-4 py-3 align-middle">
                    <strong class="text-[13px] text-gray-900 font-semibold"><?= e($j['job_title']) ?></strong>
                    <?php if ($j['city'] || $j['state_province']): ?>
                      <div class="text-[11px] text-gray-400 mt-0.5">
                        <?= e(implode(', ', array_filter([$j['city'], $j['state_province']]))) ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-3 align-middle text-gray-600"><?= e($j['country']) ?></td>
                  <td class="px-4 py-3 align-middle text-gray-600"><?= e($j['job_type']) ?></td>
                  <td class="px-4 py-3 align-middle text-gray-600"><?= e($j['workplace_type']) ?></td>
                  <td class="px-4 py-3 align-middle text-gray-600"><?= e($j['experience'] ?? '—') ?></td>
                  <td class="px-4 py-3 align-middle text-[12px] text-gray-500">
                    <?= $j['open_date'] ? date('d M Y', strtotime($j['open_date'])) : '—' ?>
                  </td>
                  <td class="px-4 py-3 align-middle">
                    <?= jobStatusBadge($j['status'], $isExpired) ?>
                  </td>
                  <td class="px-4 py-3 align-middle text-[12px] text-gray-500"><?= e($j['posted_by'] ?? '—') ?></td>
                  <td class="px-4 py-3 align-middle">
                    <div class="flex items-center gap-1.5">
                      <?php if ($isExpired): ?>
                        <span title="Cannot edit — close date has passed"
                          class="flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 text-gray-300 cursor-not-allowed">
                          <svg class="h-[15px] w-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                          </svg>
                        </span>
                      <?php else: ?>
                        <a href="<?= buildFilterUrl(ADMIN_URL . '/pages/post_job.php', ['edit' => $j['id']]) ?>" title="Edit"
                          class="flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 text-gray-500 hover:text-white hover:bg-gray-100 transition-colors">
                          <svg class="h-[15px] w-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                          </svg>
                        </a>
                        <?php if ($j['status'] !== 'published'): ?>
                          <a href="<?= buildFilterUrl(ADMIN_URL . '/pages/job_action.php', ['id' => $j['id'], 'a' => 'publish']) ?>" title="Publish"
                            class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-600 text-gray-900 hover:bg-[#3a7cd6] transition-colors">
                            <svg class="h-[14px] w-[14px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z" />
                            </svg>
                          </a>
                        <?php else: ?>
                          <a href="<?= buildFilterUrl(ADMIN_URL . '/pages/job_action.php', ['id' => $j['id'], 'a' => 'close']) ?>" title="Close"
                            class="flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 text-gray-500 hover:text-amber-600 hover:bg-amber-600/10 transition-colors">
                            <svg class="h-[14px] w-[14px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                              <rect x="6" y="6" width="12" height="12" rx="1.5" />
                            </svg>
                          </a>
                        <?php endif; ?>
                      <?php endif; ?>

                      <a href="<?= buildFilterUrl(ADMIN_URL . '/pages/post_job.php', ['clone' => $j['id']]) ?>" title="Clone Job"
                        onclick="return confirm('Clone this job? A new Job Number will be assigned.')"
                        class="flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 text-gray-500 hover:text-white hover:bg-gray-100 transition-colors">
                        <svg class="h-[15px] w-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                        </svg>
                      </a>

                      <a href="<?= buildFilterUrl(ADMIN_URL . '/pages/job_action.php', ['id' => $j['id'], 'a' => 'delete']) ?>" title="Delete"
                        onclick="return confirm('Delete this job permanently?')"
                        class="flex items-center justify-center w-8 h-8 rounded-lg border border-red-600/25 text-red-600 hover:bg-red-600/10 transition-colors">
                        <svg class="h-[15px] w-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                      </a>
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

  <!-- ══ PAGINATION ══ -->
  <?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-end gap-1.5 mt-5">
      <?php if ($page > 1): ?>
        <a href="<?= pageUrl($page - 1) ?>"
          class="px-3 py-1.5 rounded-lg text-[13px] border border-gray-200 text-gray-600 hover:text-white hover:bg-gray-100 transition-colors">‹ Prev</a>
      <?php endif; ?>

      <?php
      $range = range(max(1, $page - 2), min($totalPages, $page + 2));
      foreach ($range as $pg):
      ?>
        <a href="<?= pageUrl($pg) ?>"
          class="px-3 py-1.5 rounded-lg text-[13px] transition-colors <?= $pg === $page ? 'bg-blue-600 text-gray-900' : 'border border-gray-200 text-gray-600 hover:text-white hover:bg-gray-100' ?>">
          <?= $pg ?>
        </a>
      <?php endforeach; ?>

      <?php if ($page < $totalPages): ?>
        <a href="<?= pageUrl($page + 1) ?>"
          class="px-3 py-1.5 rounded-lg text-[13px] border border-gray-200 text-gray-600 hover:text-white hover:bg-gray-100 transition-colors">Next ›</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</div>

<script>
  // Check all checkbox
  document.getElementById('checkAll')?.addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
  });
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>