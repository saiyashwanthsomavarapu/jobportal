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
                OR c.client_code = REGEXP_REPLACE(j.client_code, '[^0-9]', '')
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
  print_r($e);
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

// Status badge color/label map.
function jobStatusBadge(string $status, bool $isExpired): string
{
  if ($isExpired) {
    return '<span class="badge badge-sm gap-1.5 border-base-300 bg-base-200 text-base-content/50"><span class="size-1.5 rounded-full bg-base-content/30"></span>Expired</span>';
  }
  $map = [
    'published' => ['badge-success badge-soft', 'bg-success'],
    'draft'     => ['badge-warning badge-soft', 'bg-warning'],
    'closed'    => ['border-base-300 bg-base-200 text-base-content/60', 'bg-base-content/40'],
    'archived'  => ['badge-error badge-soft', 'bg-error'],
  ];
  [$classes, $dot] = $map[$status] ?? ['border-base-300 bg-base-200 text-base-content/60', 'bg-base-content/40'];
  return '<span class="badge badge-sm gap-1.5 ' . $classes . '"><span class="size-1.5 rounded-full ' . $dot . '"></span>' . ucfirst(e($status)) . '</span>';
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

<!-- ══════════════ DARK CANVAS ══════════════
     Same pattern as admins.php / clients.php: breaks out of the light shell's
     padding so this page renders as a self-contained dark panel while the
     sidebar/topbar stay light. Backgrounds carry both the Tailwind class AND
     a hard inline style so they render even if the Tailwind CDN script fails. -->
<div class="min-w-0 space-y-6">
  <!-- ══ FILTERS ══ -->
  <form
    method="GET"
    class="mb-5 rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm sm:p-5">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:gap-3">
      <!-- Search -->
      <fieldset class="fieldset min-w-0 flex-1 xl:min-w-[240px]">
        <label class="<?= INPUT_CLASS ?>">
          <svg class="h-[1em] opacity-50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <g
              stroke-linejoin="round"
              stroke-linecap="round"
              stroke-width="2.5"
              fill="none"
              stroke="currentColor">
              <circle cx="11" cy="11" r="8"></circle>
              <path d="m21 21-4.3-4.3"></path>
            </g>
          </svg>
          <input type="search" name="q" class="grow" placeholder="Search Title / Code / Client" value="<?= e($fSearch) ?>" />
        </label>
      </fieldset>
      <!-- Status -->
      <fieldset class="fieldset w-full xl:w-[180px] 2xl:w-[200px]">

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
        <select
          name="job_type"
          id="jobType"
          class="<?= SELECT_CLASS ?>">
          <option value="">All Job Types</option>

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
          class="<?= PRIMARY_BUTTON_CLASS ?>">
          <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
          </svg>
          Filter
        </button>
        <!-- Clear -->
        <button
          type="button"
          onclick="window.location.href='<?= ADMIN_URL ?>/pages/jobs.php'"
          id="clearFilters"
          class="<?= SECONDARY_BUTTON_CLASS ?>">
          Clear
        </button>

      </div>


    </div>
  </form>

  <!-- ══ BULK ACTIONS + TABLE ══ -->
  <form method="POST" id="bulkForm">
    <section class="overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-sm">

      <!-- Toolbar -->
      <div class="flex flex-col gap-3 border-b border-base-300 bg-base-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
        <div class="min-w-0">
          <h2 class="font-head text-[15px] font-bold text-base-content">Jobs</h2>
          <p class="mt-0.5 text-xs text-base-content/55">
            Showing <strong class="font-semibold text-base-content"><?= count($jobs) ?></strong>
            of <strong class="font-semibold text-base-content"><?= $totalRows ?></strong> jobs
          </p>
        </div>
        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
          <select name="action"
            class="<?= SELECT_CLASS ?>">
            <option value="">Bulk Action…</option>
            <option value="publish">Publish</option>
            <option value="draft">Move to Draft</option>
            <option value="close">Close</option>
            <option value="delete">Delete</option>
          </select>
          <button
            type="button"
            onclick="openBulkActionModal()"
            class="<?= SECONDARY_BUTTON_CLASS ?>">
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
                <input type="checkbox" id="checkAll" title="Select all" class="checkbox checkbox-primary checkbox-sm rounded">
              </th>
              <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Job Code</th>
              <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Client Name</th>
              <th class="min-w-[260px] border-b border-base-300 px-4 py-3 text-[11px] font-bold uppercase tracking-wide whitespace-nowrap">Title</th>
              <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Country</th>
              <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Job Type</th>
              <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Workplace</th>
              <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Experience</th>
              <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Open Date</th>
              <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Status</th>
              <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Posted By</th>
              <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($jobs)): ?>
              <tr>
                <td colspan="12" class="py-12 text-center text-base-content/55">
                  No jobs found. <a href="<?= ADMIN_URL ?>/pages/post_job.php" class="link link-primary font-semibold">Post one &rarr;</a>
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
                <tr class="border-b border-slate-200 last:border-b-0 hover:bg-slate-100 transition-colors"
                  onclick="window.open('<?= $jobDetailUrl ?>', '_blank')">
                  <td class="px-4 py-3.5 align-middle" onclick="event.stopPropagation()">
                    <input type="checkbox" name="ids[]" value="<?= $j['id'] ?>" class="row-check checkbox checkbox-primary checkbox-sm rounded">
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
                    <span class="badge badge-soft badge-sm   <?= wpBadgeClass($j['workplace_type']) ?> capitalize"><?= e($j['workplace_type']) ?></span>
                    <!-- <?= e($j['workplace_type']) ?> -->
                  </td>
                  <td class="px-4 py-3.5 align-middle text-base-content/65"><?= e($j['experience'] ?? '—') ?></td>
                  <td class="px-4 py-3.5 align-middle text-[12px] text-base-content/55">
                    <?= $j['open_date'] ? date('d M Y', strtotime($j['open_date'])) : '—' ?>
                  </td>
                  <td class="px-4 py-3.5 align-middle">
                    <?= jobStatusBadge($j['status'], $isExpired) ?>
                  </td>
                  <td class="px-4 py-3.5 align-middle text-[12px] text-base-content/55"><?= e($j['posted_by'] ?? '—') ?></td>
                  <td class="px-4 py-3.5 align-middle" onclick="event.stopPropagation()">
                    <div class="flex items-center gap-1.5">
                      <?php if ($isExpired): ?>
                        <span title="Cannot edit — close date has passed"
                          class="btn btn-sm btn-square h-8 min-h-8 w-8 rounded-lg btn-disabled">
                          <svg class="size-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                          </svg>
                        </span>
                      <?php else: ?>
                        <div class="tooltip" data-tip="Edit">
                          <a href="<?= buildFilterUrl(ADMIN_URL . '/pages/post_job.php', ['edit' => $j['id']]) ?>" title="Edit"
                            onclick="event.stopPropagation()"
                            class="btn btn-sm btn-square h-8 min-h-8 w-8 rounded-lg btn-outline border-base-300 bg-base-100 text-base-content/60 hover:border-primary hover:bg-primary hover:text-primary-content">
                            <svg class="size-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                            </svg>
                          </a>
                        </div>
                        <?php if ($j['status'] !== 'published'): ?>
                          <div class="tooltip" data-tip="Publish">
                            <a href="<?= buildFilterUrl(ADMIN_URL . '/pages/job_action.php', ['id' => $j['id'], 'a' => 'publish']) ?>" title="Publish"
                              onclick="event.stopPropagation()"
                              class="btn btn-sm btn-square h-8 min-h-8 w-8 rounded-lg btn-primary text-primary-content shadow-sm">
                              <svg class="size-[14px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z" />
                              </svg>
                            </a>
                          </div>
                        <?php else: ?>
                          <div class="tooltip" data-tip="Close">
                            <a href="<?= buildFilterUrl(ADMIN_URL . '/pages/job_action.php', ['id' => $j['id'], 'a' => 'close']) ?>" title="Close"
                              onclick="event.stopPropagation()"
                              class="btn btn-sm btn-square h-8 min-h-8 w-8 rounded-lg btn-outline btn-warning">
                              <svg class="size-[14px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <rect x="6" y="6" width="12" height="12" rx="1.5" />
                              </svg>
                            </a>
                          </div>
                        <?php endif; ?>
                      <?php endif; ?>
                      <div class="tooltip" data-tip="Clone">
                        <button
                          type="button"
                          title="Clone Job"
                          aria-label="Clone job"
                          onclick="openCloneModal(
                          '<?= e($j['id']) ?>',
                          '<?= e($j['job_title']) ?>',
                          '<?= e(buildFilterUrl(ADMIN_URL . '/pages/post_job.php', ['clone' => $j['id']])) ?>'
                        )"

                          class="btn btn-sm btn-square h-8 min-h-8 w-8 rounded-lg btn-outline border-base-300 bg-base-100 text-base-content/60 hover:border-secondary hover:bg-secondary hover:text-secondary-content">
                          <svg class="size-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                          </svg>
                        </button>
                      </div>
                      <div class="tooltip" data-tip="Delete">
                        <button
                          type="button"
                          title="Delete"
                          aria-label="Delete job"
                          onclick="openDeleteModal(
                          '<?= e($j['id']) ?>',
                          '<?= e($j['job_title']) ?>',
                          '<?= e(buildFilterUrl(ADMIN_URL . '/pages/job_action.php', ['id' => $j['id'], 'a' => 'delete'])) ?>'
                        )"
                          class="btn btn-sm btn-square h-8 min-h-8 w-8 rounded-lg btn-outline btn-error">
                          <svg
                            class="size-[15px]"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.75"
                            aria-hidden="true">
                            <path
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
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
        <dialog id="deleteModal" class="modal">
          <div class="modal-box w-[calc(100%-2rem)] max-w-lg rounded-box border border-base-300 bg-base-100 p-0 shadow-xl">
            <div class="flex items-center gap-4 border-b border-base-200 px-5 py-5 sm:px-6">
              <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-error/10 text-error">
                <svg
                  class="size-[15px]"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="1.75"
                  aria-hidden="true">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <h3 class="font-head text-lg font-bold leading-6 text-base-content">
                  Delete job posting
                </h3>
              </div>
              <button
                type="button"
                onclick="deleteModal.close()"
                class="btn btn-sm btn-circle btn-ghost size-8 min-h-8 shrink-0 text-base-content/50"
                aria-label="Close">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <div class="space-y-4 px-5 py-5 sm:px-6">
              <div class="alert border-error/20 bg-error/5 text-base-content">
                <svg class="size-5 shrink-0 text-error" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
                <span class="text-sm">
                  This action cannot be undone.
                </span>
              </div>

              <div class="rounded-box border border-base-300 bg-base-200/50 p-4">
                <div class="flex items-start gap-3">
                  <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-base-100 text-base-content/50 shadow-sm">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M20 7h-4V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2H4a2 2 0 00-2 2v9a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2zM8 7h8" />
                    </svg>
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-base-content/45">
                      Selected job
                    </p>
                    <p id="deleteJobName" class="mt-1 truncate text-sm font-semibold text-base-content"></p>
                    <p class="mt-1 text-xs leading-5 text-base-content/55">
                      Related job metadata and listing content will be removed.
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div class="modal-action m-0 flex flex-col-reverse gap-2 border-t border-base-200 bg-base-200/30 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
              <button
                type="button"
                onclick="deleteModal.close()"
                class="btn btn-ghost h-10 min-h-10 w-full rounded-lg px-5 text-sm font-semibold sm:w-auto">
                Cancel
              </button>

              <a
                id="confirmDeleteBtn"
                href="#"
                class="btn btn-error h-10 min-h-10 w-full rounded-lg px-5 text-sm font-semibold text-error-content sm:w-auto">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-9 0h14" />
                </svg>
                Delete permanently
              </a>
            </div>
          </div>

          <form method="dialog" class="modal-backdrop bg-black/40 backdrop-blur-[2px]">
            <button>close</button>
          </form>

        </dialog>

        <dialog id="cloneModal" class="modal">
          <div class="modal-box w-[calc(100%-2rem)] max-w-lg rounded-box border border-base-300 bg-base-100 p-0 shadow-xl">
            <div class="flex items-center gap-4 border-b border-base-200 px-5 py-5 sm:px-6">
              <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-secondary/10 text-secondary">
                <svg class="size-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <h3 class="text-lg font-semibold leading-6 text-base-content">
                  Clone job posting
                </h3>
              </div>
              <button
                type="button"
                onclick="cloneModal.close()"
                class="btn btn-sm btn-circle btn-ghost size-8 min-h-8 shrink-0 text-base-content/50"
                aria-label="Close">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <div class="space-y-4 px-5 py-5 sm:px-6">
              <div class="rounded-box border border-base-300 bg-base-200/50 p-4">
                <div class="flex items-start gap-3">
                  <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-base-100 text-base-content/50 shadow-sm">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M20 7h-4V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2H4a2 2 0 00-2 2v9a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2zM8 7h8" />
                    </svg>
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-base-content/45">
                      Selected job
                    </p>
                    <p id="cloneJobName" class="mt-1 truncate text-sm font-semibold text-base-content"></p>
                    <p class="mt-1 text-xs leading-5 text-base-content/55">
                      Clone this job? A new Job Number will be assigned.
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div class="modal-action m-0 flex flex-col-reverse gap-2 border-t border-base-200 bg-base-200/30 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
              <button
                type="button"
                onclick="cloneModal.close()"
                class="btn btn-ghost h-10 min-h-10 w-full rounded-lg px-5 text-sm font-semibold sm:w-auto">
                Cancel
              </button>

              <a
                id="confirmCloneBtn"
                href="#"
                class="btn btn-primary h-10 min-h-10 w-full rounded-lg px-5 text-sm font-semibold text-error-content sm:w-auto">
                Clone job
              </a>
            </div>
          </div>

          <form method="dialog" class="modal-backdrop bg-black/40 backdrop-blur-[2px]">
            <button>close</button>
          </form>

        </dialog>
      </div>
    </section>
  </form>

  <dialog id="bulkActionModal" class="modal">
    <div
      class="modal-box w-[calc(100%-2rem)] max-w-md rounded-xl border border-base-300 bg-base-100 p-0 shadow-xl">

      <!-- Header -->
      <div class="flex items-center gap-4 border-b border-base-200 px-5 py-5 sm:px-6">

        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
          <svg
            class="size-5"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.75"
            aria-hidden="true">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a3 3 0 006 0M9 5a3 3 0 013-3 3 3 0 013 3" />
          </svg>
        </div>

        <div class="min-w-0 flex-1">
          <h3 class="font-head text-lg font-bold leading-6 text-base-content">
            Apply action
          </h3>

          <p class="mt-0.5 text-xs text-base-content/50">
            Confirm the action for the selected jobs.
          </p>
        </div>

        <button
          type="button"
          onclick="bulkActionModal.close()"
          class="btn btn-sm btn-circle btn-ghost size-8 min-h-8 shrink-0 text-base-content/50"
          aria-label="Close">

          <svg
            class="size-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>

      </div>

      <!-- Body -->
      <div class="space-y-4 px-5 py-5 sm:px-6">

        <div class="rounded-lg border border-base-300 bg-base-200/50 p-4">
          <div class="flex items-start gap-3">

            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-base-100 text-base-content/50 shadow-sm">
              <svg
                class="size-4"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.8">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M9 12l2 2 4-4m5.5-1.5A7.5 7.5 0 1112 4.5a7.5 7.5 0 019.5 4.5z" />
              </svg>
            </div>

            <div class="min-w-0 flex-1">
              <p class="text-sm font-semibold text-base-content">
                Selected jobs
              </p>

              <p class="mt-0.5 text-xs text-base-content/55">
                You are about to apply the selected action to
                <strong id="selectedJobCount" class="font-semibold text-base-content">
                  0
                </strong>
                selected job(s).
              </p>
            </div>

          </div>
        </div>

        <div class="alert border-warning/20 bg-warning/5 text-base-content">
          <svg
            class="size-5 shrink-0 text-warning"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.8">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
          </svg>

          <span class="text-xs leading-5">
            Please review your selection before continuing.
          </span>
        </div>

      </div>

      <!-- Footer -->
      <div class="modal-action m-0 flex flex-col-reverse gap-2 border-t border-base-200 bg-base-200/30 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">

        <button
          type="button"
          onclick="bulkActionModal.close()"
          class="btn btn-ghost h-10 min-h-10 w-full rounded-lg px-5 text-sm font-semibold sm:w-auto">
          Cancel
        </button>

        <button
          type="button"
          onclick="submitBulkAction()"
          class="btn btn-primary h-10 min-h-10 w-full rounded-lg px-5 text-sm font-semibold sm:w-auto">

          <svg
            class="size-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.8">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M5 12h14M13 6l6 6-6 6" />
          </svg>

          Apply action
        </button>

      </div>

    </div>

    <!-- Backdrop -->
    <form method="dialog" class="modal-backdrop bg-black/40 backdrop-blur-[2px]">
      <button type="submit">close</button>
    </form>
  </dialog>
  <!-- ══ PAGINATION ══ -->
  <?php if ($totalPages > 1): ?>
    <div class="mt-5 flex items-center justify-end">
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
  function openDeleteModal(id, jobName, deleteUrl) {
    const modal = document.getElementById('deleteModal');
    const jobNameElement = document.getElementById('deleteJobName');
    const confirmButton = document.getElementById('confirmDeleteBtn');

    jobNameElement.textContent = jobName;
    confirmButton.href = deleteUrl;

    modal.showModal();
  }

  function openCloneModal(id, jobName, deleteUrl) {
    const modal = document.getElementById('cloneModal');
    const jobNameElement = document.getElementById('cloneJobName');
    const confirmButton = document.getElementById('confirmCloneBtn');

    jobNameElement.textContent = jobName;
    confirmButton.href = deleteUrl;

    modal.showModal();
  }

  function openBulkActionModal() {
    const selected = document.querySelectorAll(
      'input[name="ids[]"]:checked'
    );

    if (selected.length === 0) {
      return;
    }

    document.getElementById('selectedJobCount').textContent = selected.length;

    document.getElementById('bulkActionModal').showModal();
  }

  function submitBulkAction() {
    const selected = document.querySelectorAll(
      'input[name="selected_jobs[]"]:checked'
    );

    if (selected.length === 0) {
      bulkActionModal.close();
      return;
    }

    // Submit the existing form
    document.getElementById('bulkActionForm').submit();
  }

  // Check all checkbox
  document.getElementById('checkAll')?.addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
  });
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>