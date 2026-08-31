<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/utils/classes.php';

function currentJobFilters(): array
{
  return array_filter([
    'q' => trim($_GET['q'] ?? ''),
    'status' => $_GET['status'] ?? '',
    'country' => $_GET['country'] ?? '',
    'job_type' => $_GET['job_type'] ?? '',
    'workplace_type' => $_GET['workplace_type'] ?? '',
    'per_page' => $_GET['per_page'] ?? '',
  ], fn($value) => $value !== '' && $value !== null);
}

function jobsFlag(?string $country): string
{
  return match (strtolower(trim((string)$country))) {
    'united states', 'usa', 'us' => '🇺🇸',
    'canada' => '🇨🇦',
    'india' => '🇮🇳',
    default => '🌐',
  };
}

function jobsStatus(string $status, bool $expired): array
{
  if ($expired) return ['Closed', 'closed'];
  return match ($status) {
    'published' => ['Published', 'published'],
    'closed' => ['Closed', 'closed'],
    'archived' => ['Archived', 'archived'],
    default => ['Draft', 'draft'],
  };
}

function jobsStatusBadgeClass(string $statusClass): string
{
  return match ($statusClass) {
    'published' => 'badge-success badge-soft badge',
    'closed' => 'badge-error badge-soft badge',
    'archived' => 'badge-neutral badge-soft badge',
    default => 'badge-warning badge-soft badge',
  };
}

// Build URL with current filters preserved
function buildFilterUrl(string $base, array $extraParams = []): string
{
  $params = array_filter($_GET, fn($v) => $v !== '' && $v !== null);
  $params = array_merge($params, $extraParams);
  unset($params['page']); // Reset to page 1 when changing filters
  return $base . '?' . http_build_query($params);
}

function renderJobsTableBody(array $jobs): string
{
  ob_start();
  if (!$jobs) { ?>
    <tr>
      <td colspan="10" class="py-20">
        <div class="grid place-items-center gap-2 text-center text-base-content/60">
          <strong class="text-base font-semibold text-base-content">No jobs found</strong>
          <span class="text-sm">Try adjusting your filters or create a new job.</span>
          <a class="btn btn-primary btn-sm mt-2" href="<?= ADMIN_URL ?>/pages/post_job.php">Create Job</a>
        </div>
      </td>
    </tr>
    <?php
  } else {
    foreach ($jobs as $job): $expired = $job['status'] === 'published' && !empty($job['close_date']) && strtotime($job['close_date']) < strtotime('today');
      [$statusLabel, $statusClass] = jobsStatus($job['status'], $expired);
      $publicUrl = SITE_URL . '/job-detail.php?slug=' . urlencode($job['slug']);
      $editUrl = ADMIN_URL . '/pages/post_job.php?edit=' . (int)$job['id']; ?>
      <tr class="job-row group border-t border-base-300 transition-colors hover:bg-[#F28C28]" data-edit="<?= e($editUrl) ?>">
        <td class="w-11 px-3 text-center align-middle group-hover:bg-[#F28C28]"><input class="row-check checkbox checkbox-primary checkbox-sm mx-auto" type="checkbox" name="ids[]" value="<?= (int)$job['id'] ?>" aria-label="Select <?= e($job['job_title']) ?>"></td>
        <td class="min-w-64 px-4 py-3.5 align-middle group-hover:bg-[#F28C28]">
          <a href="<?= e($editUrl) ?>" target="_blank" rel="noopener">
            <strong class="block font-medium text-base-content group-hover:text-white"><?= e(ucwords($job['job_title'])) ?></strong>
            <small class="mt-1 block font-mono text-xs text-base-content/55 group-hover:text-white/80"><?= e($job['job_code']) ?> <i>·</i> Job Code: <?= e($job["client_code"]) ?></small>
          </a>
        </td>
        <td class="px-4 py-3.5 align-middle group-hover:bg-[#F28C28]">
          <span class="flex items-center gap-1.5 whitespace-nowrap text-base-content/80 group-hover:text-white">
            <b><?= jobsFlag($job['country']) ?></b>
            <?= e($job['country']) ?>
          </span>
        </td>
        <td class="px-4 py-3.5 align-middle group-hover:bg-[#F28C28]">
          <span class="badge badge-sm  <?= jobsStatusBadgeClass($statusClass) ?>"><?= e($statusLabel) ?></span>
        </td>
        <td class="px-4 py-3.5 align-middle group-hover:bg-[#F28C28]">
          <span class="badge badge-soft badge-sm whitespace-nowrap capitalize"><?= e($job['workplace_type']) ?></span>
        </td>
        <td class="px-4 py-3.5 align-middle text-base-content/65 group-hover:bg-[#F28C28] group-hover:text-white!"><?= e($job['experience'] ?: '—') ?></td>
        <td class="px-4 py-3.5 align-middle text-base-content/65 group-hover:bg-[#F28C28] group-hover:text-white!">
          <?= e($job['job_type'] ?: '—') ?>
        </td>
        <td class="px-4 py-3.5 align-middle text-base-content/80 group-hover:bg-[#F28C28] group-hover:text-white!">
          <?= e($job['posted_by'] ?: '—') ?>
        </td>
        <td class="px-4 py-3.5 align-middle whitespace-nowrap  group-hover:bg-[#F28C28] group-hover:text-white!">
          <?= $job['created_at'] ? date('M j, Y', strtotime($job['created_at'])) : '—' ?>
        </td>
        <td class="job-actions-cell right-0 z-30 w-14  px-2 py-3.5 text-right align-middle group-hover:bg-[#F28C28]" onclick="event.stopPropagation()">
          <div class="dropdown dropdown-bottom dropdown-end">
            <div tabindex="0" role="button" class="btn btn-sm btn-ghost m-1 p-2 bg-transparent border-none shadow-none outline-none focus:outline-none focus-visible:outline-none hover:bg-transparent text-base-content group-hover:text-white">
              <svg class="size-4" viewBox="0 0 24 24">
                <circle cx="5" cy="12" r="1" />
                <circle cx="12" cy="12" r="1" />
                <circle cx="19" cy="12" r="1" />
              </svg>
            </div>
            <ul tabindex="-1" class="dropdown-content menu bg-base-100 rounded-box z-50 w-52 p-2 shadow-sm">
              <li>
                <a href="<?= e($editUrl) ?>">Edit</a>
              </li>
              <?php if ($job['status'] !== 'published' || $expired): ?>
                <li>
                  <button type="button" onclick="buildFilterUrl(ADMIN_URL . '/pages/job_action.php', ['id' => $j['id'], 'a' => 'publish'])" data-id="<?= (int)$job['id'] ?>" data-value="publish" data-action="Publish" data-job="<?= e($job['job_title']) ?>">
                    Publish
                  </button>
                </li>
                <!-- <li>
                  <button type="button" onclick="moveToDraft(
                          '<?= e($job['id']) ?>',
                          '<?= e($job['job_title']) ?>',
                          '<?= buildFilterUrl(ADMIN_URL . '/pages/job_action.php', ['id' => $job['id'], 'a' => 'close']) ?>')" data-id="<?= (int)$job['id'] ?>" data-value="draft" data-action="Move to draft" data-job="<?= e($job['job_title']) ?>">
                    Move to draft
                  </button>
                </li> -->
              <?php endif; ?>
              <li>
                <button type="button" onclick="openCloneModal(
                          '<?= e($job['id']) ?>',
                          '<?= e($job['job_title']) ?>',
                          '<?= e(buildFilterUrl(ADMIN_URL . '/pages/post_job.php', ['clone' => $job['id']])) ?>'
                        )" data-value=" clone" data-action="Clone" data-job="<?= e($job['job_title']) ?>">
                  Clone
                </button>
              </li>
              <li>
                <button type="button" onclick="confirmJobAction('<?= e($job['id']) ?>',
                          '<?= e($job['job_title']) ?>',this)" data-id="<?= (int)$job['id'] ?>" data-value="close" data-action="Close" data-job="<?= e($job['job_title']) ?>">
                  Close
                </button>
              </li>
              <li>
                <button class="text-error" type="button" onclick="openDeleteModal(
                          '<?= e($job['id']) ?>',
                          '<?= e($job['job_title']) ?>',
                          '<?= e(buildFilterUrl(ADMIN_URL . '/pages/job_action.php', ['id' => $job['id'], 'a' => 'delete'])) ?>'
                        )" data-id="<?= (int)$job['id'] ?>" data-value="delete" data-action="Delete" data-job="<?= e($job['job_title']) ?>" data-danger="true">
                  Delete
                </button>
              </li>
            </ul>
          </div>
        </td>
      </tr>
  <?php endforeach;
  }
  return ob_get_clean();
}

function renderJobsPagination(int $page, int $totalPages, int $totalRows, int $perPage): string
{
  ob_start(); ?>
  <p class="m-0 text-sm text-base-content/60"><?= number_format($totalRows) ?> total jobs</p>
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
            class="btn btn-sm join-item <?= $pg === $page ? 'btn-primary !text-white' : 'border-base-300 bg-base-100 text-base-content/70 hover:bg-base-200' ?>">
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
<?php return ob_get_clean();
}

$fSearch = trim($_GET['q'] ?? '');
$fStatus = $_GET['status'] ?? '';
$fCountry = $_GET['country'] ?? '';
$fJobType = $_GET['job_type'] ?? '';
$fWorkplaceType = $_GET['workplace_type'] ?? '';
$hasFilters = $fSearch !== '' || $fStatus !== '' || $fCountry !== '' || $fJobType !== '' || $fWorkplaceType !== '';
$page = max(1, (int)($_GET['page'] ?? 1));
$requestedPerPage = (int)($_GET['per_page'] ?? 10);
$perPage = in_array($requestedPerPage, [10, 15, 25, 50], true) ? $requestedPerPage : 10;

$statusOptions = ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Pending Review', 'closed' => 'Closed'];
$countryOptions = ['United States', 'Canada', 'India'];
$jobTypeOptions = [];
$workplaceTypeOptions = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
  if (!validCsrfToken($_POST['csrf_token'] ?? null)) {
    flash('error', 'Your session expired. Please try again.');
    redirect(ADMIN_URL . '/pages/jobs.php');
  }
  $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
  $action = $_POST['action'];
  if ($ids && in_array($action, ['publish', 'draft', 'close', 'archive', 'delete'], true)) {
    try {
      $marks = implode(',', array_fill(0, count($ids), '?'));
      if ($action === 'delete') {
        db()->prepare("DELETE FROM jobs WHERE id IN ($marks)")->execute($ids);
      } else {
        $status = $action === 'publish' ? 'published' : ($action === 'close' ? 'closed' : $action);
        $sql = "UPDATE jobs SET status=?, updated_at=NOW()" . ($status === 'published' ? ', published_at=COALESCE(published_at,NOW())' : '') . " WHERE id IN ($marks)";
        db()->prepare($sql)->execute(array_merge([$status], $ids));
      }
      logActivity($action . '_jobs', 'job', null, 'IDs: ' . implode(',', $ids));
      flash('success', count($ids) . ' job' . (count($ids) === 1 ? '' : 's') . ' updated.');
    } catch (Exception $e) {
      flash('error', 'The selected jobs could not be updated.');
    }
  } else {
    flash('warn', 'Select at least one job and an action.');
  }
  redirect(ADMIN_URL . '/pages/jobs.php' . (currentJobFilters() ? '?' . http_build_query(currentJobFilters()) : ''));
}

$where = ['1=1'];
$params = [];
if ($fStatus === 'published') $where[] = "(j.status='published' AND (j.close_date IS NULL OR j.close_date >= CURDATE()))";
elseif ($fStatus === 'closed') $where[] = "(j.status='closed' OR (j.status='published' AND j.close_date IS NOT NULL AND j.close_date < CURDATE()))";
elseif ($fStatus) {
  $where[] = 'j.status=?';
  $params[] = $fStatus;
}
if ($fCountry) {
  $where[] = 'j.country=?';
  $params[] = $fCountry;
}
if ($fJobType) {
  $where[] = 'j.job_type=?';
  $params[] = $fJobType;
}
if ($fWorkplaceType) {
  $where[] = 'j.workplace_type=?';
  $params[] = $fWorkplaceType;
}
if ($fSearch) {
  $where[] = '(j.job_title LIKE ? OR j.job_code LIKE ? OR c.client_name LIKE ?)';
  $like = '%' . $fSearch . '%';
  array_push($params, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$jobs = [];
$totalRows = 0;
$totalPages = 1;
try {
  $pdo = db();
  $countryOptions = $pdo->query("SELECT DISTINCT country FROM jobs WHERE country IS NOT NULL AND country != '' ORDER BY country")->fetchAll(PDO::FETCH_COLUMN) ?: $countryOptions;
  $jobTypeOptions = $pdo->query("SELECT DISTINCT job_type FROM jobs WHERE job_type IS NOT NULL AND job_type != '' ORDER BY job_type")->fetchAll(PDO::FETCH_COLUMN) ?: [];
  $workplaceTypeOptions = $pdo->query("SELECT DISTINCT workplace_type FROM jobs WHERE workplace_type IS NOT NULL AND workplace_type != '' ORDER BY workplace_type")->fetchAll(PDO::FETCH_COLUMN) ?: [];

  $counter = $pdo->prepare("SELECT COUNT(DISTINCT j.id) FROM jobs j LEFT JOIN clients c ON c.id=j.client_id WHERE $whereSql");
  $counter->execute($params);
  $totalRows = (int)$counter->fetchColumn();
  $totalPages = max(1, (int)ceil($totalRows / $perPage));
  $page = min($page, $totalPages);
  $offset = ($page - 1) * $perPage;
  $query = $pdo->prepare("SELECT
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
    WHERE $whereSql
    ORDER BY j.created_at DESC
    LIMIT $perPage OFFSET $offset");
  $query->execute($params);
  $jobs = $query->fetchAll();
} catch (Exception $e) {
  error_log('Jobs page query failed: ' . $e->getMessage());
  $offset = 0;
}

if (($_GET['ajax'] ?? '') === '1') {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'count' => $totalRows,
    'page' => $page,
    'totalPages' => $totalPages,
    'rows' => renderJobsTableBody($jobs),
    'pagination' => renderJobsPagination($page, $totalPages, $totalRows, $perPage),
  ]);
  exit;
}

function pageUrl(int $pg): string
{
  $p = $_GET;
  $p['page'] = $pg;
  return '?' . http_build_query($p);
}

$successMessage = flash('success');
$errorMessage = flash('error');
$warnMessage = flash('warn');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Jobs — <?= e(SITE_NAME) ?> Recruitment</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css">
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css">
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <?php include dirname(__DIR__) . '/includes/theme.php'; ?>
  <link href="<?= ADMIN_URL ?>/dashboard.css" rel="stylesheet">
</head>

<body data-theme="accelon">
  <button class="mobile-menu btn btn-square btn-sm md:hidden" id="mobileMenu" type="button" aria-label="Open navigation" aria-expanded="false"><svg class="size-5" viewBox="0 0 24 24">
      <path d="M4 7h16M4 12h16M4 17h16" />
    </svg></button>
  <div class="sidebar-overlay" id="sidebarOverlay"></div>
  <aside class="sidebar" id="dashboardSidebar">
    <a class="brand" href="<?= ADMIN_URL ?>/index.php" aria-label="Accelon Consulting dashboard"><img src="https://www.accelonconsulting.com/wp-content/uploads/2025/07/Accelon-logo.webp" alt="Accelon Consulting" style="display:block;width:auto;height:50px;padding-left:15px;;max-width:170px;object-fit:contain"></a>
    <nav class="nav" aria-label="Admin navigation">
      <a href="<?= ADMIN_URL ?>/index.php">
        <svg viewBox="0 0 24 24">
          <rect x="4" y="4" width="6" height="6" rx="1" />
          <rect x="14" y="4" width="6" height="6" rx="1" />
          <rect x="4" y="14" width="6" height="6" rx="1" />
          <rect x="14" y="14" width="6" height="6" rx="1" />
        </svg>Dashboard
      </a>
      <a class="active" href="<?= ADMIN_URL ?>/pages/jobs.php">
        <svg viewBox="0 0 24 24">
          <path d="M8 7V5.5A1.5 1.5 0 0 1 9.5 4h5A1.5 1.5 0 0 1 16 5.5V7M5 7h14a1 1 0 0 1 1 1v11H4V8a1 1 0 0 1 1-1Zm7 0v12" />
        </svg>Jobs
      </a>
      <a href="<?= ADMIN_URL ?>/pages/admins.php">
        <svg viewBox="0 0 24 24">
          <path d="M15.5 8a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0ZM5 20a7 7 0 0 1 14 0M18 5v6M15 8h6" />
        </svg>Admin
      </a>
    </nav>
    <div class="sidebar-footer">
      <div class="admin-row">
        <span class="admin-avatar"><?= e(strtoupper(substr($currentAdmin['name'] ?? 'A', 0, 1))) ?></span>
        <span class="admin-copy">
          <strong><?= e($currentAdmin['name'] ?? 'Admin') ?></strong>
          <small><?= e(ucfirst($currentAdmin['role'] ?? 'admin')) ?></small>
        </span>
        <a class="signout" href="<?= ADMIN_URL ?>/logout.php" title="Sign out">
          <svg viewBox="0 0 24 24">
            <path d="M10 5H6v14h4m4-4 4-3-4-3m4 3H9" />
          </svg>
        </a>
      </div>
      <a class="new-job btn btn-primary btn-sm w-full" href="<?= ADMIN_URL ?>/pages/post_job.php"><span>+</span> Create Job</a>
    </div>
  </aside>

  <main class="main">
    <div class="min-h-screen bg-base-200/40 px-6 py-7 max-md:px-3.5 max-md:py-6">
      <header class="flex items-center justify-between gap-4 max-md:pl-12">
        <h1 class="m-0 text-2xl font-bold leading-tight text-base-content">Jobs</h1>
        <!-- <a class="btn btn-primary btn-sm rounded-lg" href="<?= ADMIN_URL ?>/pages/post_job.php"><span class="text-lg leading-none">+</span> Create Job</a> -->
      </header>
      <?php if ($successMessage || $errorMessage || $warnMessage): $message = $successMessage ?: ($errorMessage ?: $warnMessage);
        $type = $successMessage ? 'success' : ($errorMessage ? 'error' : 'warn');
        $alertClass = $successMessage ? 'alert-success' : ($errorMessage ? 'alert-error' : 'alert-warning'); ?><div role="alert" class="alert <?= $alertClass ?> alert-soft mt-4 rounded-lg text-sm"><?= $message ?></div><?php endif; ?>

      <form method="GET" class="mt-6 grid grid-cols-[minmax(220px,1.35fr)_repeat(4,minmax(118px,1fr))_auto] items-center gap-3 max-lg:grid-cols-3 max-md:grid-cols-1" id="jobsFilterForm" action="<?= ADMIN_URL ?>/pages/jobs.php">
        <input type="hidden" name="per_page" value="<?= $perPage ?>">
        <fieldset class="fieldset ">
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
            <input id="jobSearch" type="search" name="q" value="<?= e($fSearch) ?>" placeholder="Search jobs..." autocomplete="off" class="border-none bg-transparent text-sm outline-none placeholder:text-base-content/35" />
          </label>
        </fieldset>
        <!-- <label class="input input-sm h-10 min-h-10 w-full rounded-lg border-base-300 bg-base-100   focus-within:ring-2 focus-within:ring-primary/15">
          <svg class="size-4 shrink-0 text-base-content/40" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-4-4" />
          </svg>
          <input id="jobSearch" type="search" name="q" value="<?= e($fSearch) ?>" placeholder="Search jobs..." autocomplete="off" class="border-none bg-transparent text-sm outline-none placeholder:text-base-content/35">
        </label> -->
        <fieldset class="fieldset ">

          <select name="status" aria-label="Status" data-auto-filter class="select select-sm h-10 min-h-10 w-full rounded-lg border-base-300 bg-base-100 text-sm">
            <option value="">All Statuses</option>
            <?php foreach ($statusOptions as $value => $label): ?>
              <option value="<?= $value ?>" <?= $fStatus === $value ? 'selected' : '' ?>>
                <?= $label ?>
              </option>
            <?php endforeach; ?>
          </select>
        </fieldset>


        <!-- Country -->
        <fieldset class="fieldset">
          <select name="country" aria-label="Country" data-auto-filter class="select select-sm h-10 min-h-10 w-full rounded-lg border-base-300 bg-base-100 text-sm">
            <option value="">All Countries</option><?php foreach ($countryOptions as $country): ?><option value="<?= e($country) ?>" <?= $fCountry === $country ? 'selected' : '' ?>><?= jobsFlag($country) ?> <?= e($country) ?></option><?php endforeach; ?>
          </select>
        </fieldset>


        <!-- Job Type -->
        <fieldset class="fieldset">
          <select name="job_type" aria-label="Job Type" data-auto-filter class="select select-sm h-10 min-h-10 w-full rounded-lg border-base-300 bg-base-100 text-sm">
            <option value="">All Job Types</option><?php foreach ($jobTypeOptions as $jobType): ?><option value="<?= e($jobType) ?>" <?= $fJobType === $jobType ? 'selected' : '' ?>><?= e($jobType) ?></option><?php endforeach; ?>
          </select>
        </fieldset>


        <!-- Workplace -->
        <fieldset class="fieldset">
          <select name="workplace_type" aria-label="Workplace" data-auto-filter class="select select-sm h-10 min-h-10 w-full rounded-lg border-base-300 bg-base-100 text-sm">
            <option value="">All Workplaces</option><?php foreach ($workplaceTypeOptions as $workplaceType): ?><option value="<?= e($workplaceType) ?>" <?= $fWorkplaceType === $workplaceType ? 'selected' : '' ?>><?= e($workplaceType) ?></option><?php endforeach; ?>
          </select>
        </fieldset>

        <!-- <select name="status" aria-label="Status" data-auto-filter class="select select-sm h-10 min-h-10 w-full rounded-lg border-base-300 bg-base-100 text-sm">
          <option value="">All Statuses</option><?php foreach ($statusOptions as $value => $label): ?><option value="<?= $value ?>" <?= $fStatus === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
        </select>
        <select name="country" aria-label="Country" data-auto-filter class="select select-sm h-10 min-h-10 w-full rounded-lg border-base-300 bg-base-100 text-sm">
          <option value="">All Countries</option><?php foreach ($countryOptions as $country): ?><option value="<?= e($country) ?>" <?= $fCountry === $country ? 'selected' : '' ?>><?= jobsFlag($country) ?> <?= e($country) ?></option><?php endforeach; ?>
        </select>
        <select name="job_type" aria-label="Job Type" data-auto-filter class="select select-sm h-10 min-h-10 w-full rounded-lg border-base-300 bg-base-100 text-sm">
          <option value="">All Job Types</option><?php foreach ($jobTypeOptions as $jobType): ?><option value="<?= e($jobType) ?>" <?= $fJobType === $jobType ? 'selected' : '' ?>><?= e($jobType) ?></option><?php endforeach; ?>
        </select>
        <select name="workplace_type" aria-label="Workplace" data-auto-filter class="select select-sm h-10 min-h-10 w-full rounded-lg border-base-300 bg-base-100 text-sm">
          <option value="">All Workplaces</option><?php foreach ($workplaceTypeOptions as $workplaceType): ?><option value="<?= e($workplaceType) ?>" <?= $fWorkplaceType === $workplaceType ? 'selected' : '' ?>><?= e($workplaceType) ?></option><?php endforeach; ?>
        </select> -->
        <a class="btn btn-sm h-10 min-h-10 rounded-lg border-base-300 bg-base-100 px-5 text-base-content/70 hover:bg-base-200" href="<?= ADMIN_URL ?>/pages/jobs.php">Clear</a>
      </form>

      <form method="POST" id="bulkForm">
        <?= csrfField() ?>
        <div class="card mt-5 overflow-visible rounded-xl border border-base-300 bg-base-100 shadow-xs">
          <div class="bulk-bar flex min-h-14 items-center justify-between gap-3 border-b border-base-300 px-4 py-2 text-sm  max-sm:items-start max-sm:flex-col">
            <span>
              <strong><?= number_format($totalRows) ?></strong> total jobs
            </span>
            <div class="flex items-center gap-2">
              <select name="action" id="bulkAction" class="select select-sm h-9 min-h-9 rounded-lg border-base-300 bg-base-100 text-xs">
                <option value="">Bulk Actions</option>
                <option value="publish">Publish</option>
                <option value="draft">Move to Draft</option>
                <option value="close">Close</option>
                <option value="delete">Delete</option>
              </select>
              <button type="button" id="bulkApplyBtn" onclick="openBulkActionModal()"
                class="btn btn-sm h-9 min-h-9 rounded-lg border-base-300 bg-base-100 text-xs" disabled>Apply</button>
            </div>
          </div>
          <div class="overflow-x-auto pb-48">
            <table class="jobs-table table table-sm min-w-[940px]">
              <thead>
                <tr>
                  <th class="w-11 px-3 text-center"><input id="checkAll" type="checkbox" class="checkbox checkbox-primary checkbox-sm mx-auto" aria-label="Select all jobs"></th>
                  <th>Job</th>
                  <th>Country</th>
                  <th>Status</th>
                  <th>Workplace</th>
                  <th>Experience</th>
                  <th>Type</th>
                  <th>Posted By</th>
                  <th>Created</th>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?= renderJobsTableBody($jobs) ?>
              </tbody>
            </table>
          </div>
        </div>
      </form>

      <footer class="pagination flex items-center justify-between gap-3 py-4 text-sm text-base-content/60 max-md:items-start max-md:flex-col">
        <?= renderJobsPagination($page, $totalPages, $totalRows, $perPage) ?>

      </footer>
    </div>
  </main>
  <!-- <div class="toast toast-end hidden" id="copyToast" role="status">
    <div class="alert bg-neutral text-neutral-content shadow-lg">Job link copied</div>
  </div> -->
  <!-- <ul id="floatingJobMenu" class="menu fixed z-[9999] hidden w-40 rounded-box border border-base-300 bg-base-100 p-2 shadow-xl" onclick="event.stopPropagation()"></ul> -->

  <!-- Delete Modal -->
  <dialog id="deleteModal" class="modal">
    <div class="modal-box w-[calc(100%-2rem)] max-w-lg rounded-box border border-base-300 bg-base-100 p-0 shadow-xl">
      <!-- Header -->
      <div class="flex items-center gap-4 border-b border-base-200 px-5 py-5 sm:px-6">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-error/10 text-error">
          <svg
            class="<?= SVG_ICON ?>"
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
          <h3 class="<?= MODAL_HEADING ?>">
            Delete job posting
          </h3>
          <p class="mt-1 text-sm text-base-content/60">
            This action cannot be undone
          </p>
        </div>
        <button
          type="button"
          onclick="deleteModal.close()"
          class="btn btn-sm btn-circle btn-ghost size-9 min-h-9 shrink-0 text-base-content/50 hover:bg-base-200"
          aria-label="Close">
          <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      <div class="px-6 py-5">
        <div class="rounded-xl  p-4">
          <div class="flex items-start gap-3">
            <div class="flex-1">
              <p class="text-sm font-medium text-base-content">
                Are you sure you want to delete <strong id="deleteJobName" class="text-error"></strong>?
              </p>
              <p class="mt-2 text-xs leading-5 text-base-content/60">
                This will permanently remove the job posting and all associated data. This action cannot be undone.
              </p>
            </div>
          </div>
        </div>
      </div>
      <div class="flex flex-col-reverse gap-3 border-t border-base-200 bg-base-200/30 px-6 py-4 sm:flex-row sm:justify-end">
        <button
          type="button"
          onclick="deleteModal.close()"
          class="btn btn-ghost h-11 min-h-11 w-full rounded-xl px-6 text-sm font-semibold sm:w-auto hover:bg-base-200">
          Cancel
        </button>
        <a
          id="confirmDeleteBtn"
          href="#"
          class="btn btn-error h-11 min-h-11 w-full rounded-xl px-6 text-sm font-semibold !text-white sm:w-auto shadow-lg">
          <svg class="size-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-9 0h14" />
          </svg>
          Delete job
        </a>
      </div>
    </div>
    <form method="dialog" class="modal-backdrop bg-black/40">
      <button>close</button>
    </form>
  </dialog>

  <!-- Clone Modal -->
  <dialog id="cloneModal" class="modal">
    <div class="modal-box w-[calc(100%-2rem)] max-w-lg rounded-2xl border border-base-300 bg-base-100 p-0 shadow-2xl">
      <div class="flex items-center gap-4 border-b border-base-200 px-6 py-5">
        <div class="<?= SVG_DIV ?>">
          <svg class="<?= SVG_ICON ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
          </svg>
        </div>
        <div class="min-w-0 flex-1">
          <h3 class="<?= MODAL_HEADING ?>">
            Clone job posting
          </h3>
          <p class="mt-1 text-sm text-base-content/60">
            Create a copy of this job
          </p>
        </div>
        <button
          type="button"
          onclick="cloneModal.close()"
          class="btn btn-sm btn-circle btn-ghost size-9 min-h-9 shrink-0 text-base-content/50 hover:bg-base-200"
          aria-label="Close">
          <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      <div class="px-6 py-5">
        <div class="rounded-xl p-4">
          <div class="flex items-start gap-3">
            <div class="flex-1">
              <p class="text-sm font-medium text-base-content">
                Clone <strong id="cloneJobName" class="text-primary"></strong>?
              </p>
              <p class="mt-2 text-xs leading-5 text-base-content/60">
                A new job with a unique Job Number will be created based on this job's details.
              </p>
            </div>
          </div>
        </div>
      </div>
      <div class="flex flex-col-reverse gap-3 border-t border-base-200 bg-base-200/30 px-6 py-4 sm:flex-row sm:justify-end">
        <button
          type="button"
          onclick="cloneModal.close()"
          class="btn btn-ghost h-11 min-h-11 w-full rounded-xl px-6 text-sm font-semibold sm:w-auto hover:bg-base-200">
          Cancel
        </button>
        <a
          id="confirmCloneBtn"
          href="#"
          class="btn btn-primary h-11 min-h-11 w-full rounded-xl px-6 text-sm font-semibold !text-white sm:w-auto shadow-lg">
          <svg class="size-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
          </svg>
          Clone job
        </a>
      </div>
    </div>

    <form method="dialog" class="modal-backdrop bg-black/40">
      <button>close</button>
    </form>
  </dialog>

  <!-- Bulk action modal -->
  <dialog id="bulkActionModal" class="modal">
    <div
      class="modal-box w-[calc(100%-2rem)] max-w-md rounded-xl border border-base-300 bg-base-100 p-0 shadow-xl">
      <!-- Header -->
      <div class="flex items-center gap-4 border-b border-base-200 px-5 py-5 sm:px-6">
        <div class="<?= SVG_DIV ?>">
          <svg
            class="<?= SVG_ICON ?>"
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
          <h3 class="<?= MODAL_HEADING ?>">
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
      <div class="px-6 py-5">
        <div class="rounded-xl p-4">
          <div class="flex items-start gap-3">
            <div class="flex-1">
              <p class="text-sm font-medium text-base-content">
                Are you sure you want to jobs <strong id="selectedAction"></strong> ?
              </p>
              <p class=" mt-2 text-xs leading-5 text-base-content/60">
                You are about to apply the selected action to
                <strong id="selectedJobCount" class="font-semibold text-base-content">
                  0
                </strong>
                selected job(s).
                A new job with a unique Job Number will be created based on this job's details.
              </p>
            </div>
          </div>
          <div class="alert mt-4 border-warning/20 bg-warning/5 text-base-content">
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
          class="btn btn-primary h-11 min-h-11 w-full rounded-xl px-6 text-sm font-semibold !text-white sm:w-auto shadow-lg">
          <svg
            class="size-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
          </svg>
          Apply action
        </button>
      </div>
    </div>

    <!-- Backdrop -->
    <form method="dialog" class="modal-backdrop bg-black/40">
      <button type="submit">close</button>
    </form>
  </dialog>

  <!-- Close modal -->
  <dialog class="modal" id="jobActionDialog">
    <div class="modal-box w-[calc(100%-2rem)] max-w-md rounded-xl border border-base-300 bg-base-100 p-0 shadow-xl">
      <div class="flex items-center gap-4 border-b border-base-200 px-5 py-5 sm:px-6">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-error/10 text-error">
          <svg
            class="<?= SVG_ICON ?>"
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
          <h3 class="<?= MODAL_HEADING ?>" id="actionDialogTitle">
            Close job
          </h3>
          <!-- <p class="mt-0.5 text-xs text-base-content/50">
            Confirm the action for the selected jobs.
          </p> -->
        </div>
        <button
          type="button"
          onclick="document.getElementById('jobActionDialog').close()"
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

      <div class="px-6 py-5">
        <div class="rounded-xl  p-4">
          <div class="flex items-start gap-3">
            <div class="flex-1">
              <p class="text-sm font-medium text-base-content">
                Are you sure you want to <span id="actionDialogAction">close</span> <strong id="closeJobName" class="text-error"></strong>?
              </p>
              <p class="mt-2 text-xs leading-5 text-base-content/60">
                This will permanently remove the job posting and all associated data. This action cannot be undone.
              </p>
            </div>
          </div>
        </div>
      </div>
      <div class="flex flex-col-reverse gap-3 border-t border-base-200 bg-base-200/30 px-6 py-4 sm:flex-row sm:justify-end">
        <form method="POST" action="<?= ADMIN_URL ?>/pages/job_action.php" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="id" id="actionJobId">
          <input type="hidden" name="a" id="actionJobValue">
          <button
            type="button"
            onclick="document.getElementById('jobActionDialog').close()"
            class="btn btn-ghost h-10 min-h-10 w-full rounded-lg px-5 text-sm font-semibold sm:w-auto">
            Cancel
          </button>
          <button
            type="submit"
            id="actionDialogConfirm"
            class="btn btn-error h-11 min-h-11 w-full rounded-xl px-6 text-sm font-semibold !text-white sm:w-auto shadow-lg">
            <svg
              class="size-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
            </svg>
            Close
          </button>
        </form>
      </div>
    </div>
    <form method="dialog" class="modal-backdrop bg-black/40">
      <button aria-label="Close dialog" type="submit>">close</button>
    </form>
  </dialog>
  <!-- ══ PAGINATION ══ -->

  <script>
    const menu = document.getElementById('mobileMenu'),
      sidebar = document.getElementById('dashboardSidebar'),
      overlay = document.getElementById('sidebarOverlay');

    function setMenu(open) {
      sidebar.classList.toggle('open', open);
      overlay.classList.toggle('open', open);
      menu.setAttribute('aria-expanded', String(open))
    }
    menu.addEventListener('click', () => setMenu(!sidebar.classList.contains('open')));
    overlay.addEventListener('click', () => setMenu(false));

    function updateBulkButton() {
      document.getElementById('bulkApplyBtn').disabled = !document.querySelectorAll('.row-check:checked').length;
    }
    document.querySelectorAll('.row-check').forEach(box => box.addEventListener('change', updateBulkButton));
    document.getElementById('checkAll')?.addEventListener('change', function() {
      document.querySelectorAll('.row-check').forEach(box => box.checked = this.checked);
      updateBulkButton()
    });

    document.querySelector('.jobs-table tbody')?.addEventListener('click', function(event) {
      const row = event.target.closest('.job-row');
      if (!row || !row.dataset.edit) return;
      if (event.target.closest('a, button, input, select, summary, details, .job-actions-cell, .dropdown, .dropdown-content')) return;
      window.open(row.dataset.edit, '_blank', 'noopener')
    });

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

      const selectedAction = document.getElementById('bulkAction').value;
      if (selected.length === 0) {
        return;
      }
      if (!selectedAction) {
        return;
      }

      document.getElementById('selectedJobCount').textContent = selected.length;
      document.getElementById('selectedAction').textContent = selectedAction;

      document.getElementById('bulkActionModal').showModal();
    }

    function submitBulkAction() {
      const selected = document.querySelectorAll(
        'input[name="ids[]"]:checked'
      );

      if (selected.length === 0) {
        bulkActionModal.close();
        return;
      }

      // Submit the existing form
      document.getElementById('bulkForm').submit();
    }

    function moveToDraft(id, jobName, moveToDraftUrl) {
      const dialog = document.getElementById('jobActionDialog');
      document.getElementById('actionDialogTitle').textContent = 'Move to draft?';
      document.getElementById('actionDialogAction').textContent = 'move to draft';
      document.getElementById('closeJobName').textContent = jobName;
      document.getElementById('actionJobId').value = id;
      document.getElementById('actionJobValue').value = 'draft';
      const confirm = document.getElementById('actionDialogConfirm');
      confirm.textContent = 'Move to draft';
      confirm.classList.remove('btn-primary');
      confirm.classList.add('btn-error');
      dialog.showModal()
    }

    function openJobActions(event, button) {
      event.stopPropagation();
      const menu = document.getElementById('floatingJobMenu');
      const template = button.nextElementSibling;
      if (!template) return;

      if (!menu.classList.contains('hidden') && menu.dataset.source === button.id) {
        // closeActionMenus();
        return
      }

      menu.innerHTML = template.innerHTML;
      menu.dataset.source = button.id;
      menu.classList.remove('hidden');

      const rect = button.getBoundingClientRect();
      const menuRect = menu.getBoundingClientRect();
      const menuWidth = menuRect.width || 160;
      const menuHeight = menuRect.height || 204;
      const left = Math.min(window.innerWidth - menuWidth - 8, Math.max(8, rect.right - menuWidth));
      const aboveTop = rect.top - menuHeight - 8;
      const belowTop = rect.bottom + 8;
      const top = aboveTop >= 8 ? aboveTop : Math.min(window.innerHeight - menuHeight - 8, belowTop);
      menu.style.left = left + 'px';
      menu.style.top = Math.max(8, top) + 'px'
    }

    function closeActionMenus() {

    }

    function confirmJobAction(id, jobName, button) {
      // closeActionMenus();
      const dialog = document.getElementById('jobActionDialog'),
        danger = button.dataset.danger === 'true',
        action = button.dataset.action;
      const jobNameElement = document.getElementById('closeJobName');

      jobNameElement.textContent = jobName;
      document.getElementById('actionJobId').value = button.dataset.id;
      document.getElementById('actionJobValue').value = button.dataset.value;
      document.getElementById('actionDialogTitle').textContent = action + '?';
      document.getElementById('actionDialogAction').textContent = action.toLowerCase();

      const confirm = document.getElementById('actionDialogConfirm');
      confirm.textContent = action;
      confirm.classList.toggle('btn-primary', !danger);
      confirm.classList.toggle('btn-error', danger);
      dialog.showModal()
    }

    function jobsQuery(page, perPage) {
      const form = document.getElementById('jobsFilterForm'),
        data = new URLSearchParams();
      data.set('q', document.getElementById('jobSearch').value.trim());
      data.set('status', form.elements['status']?.value || '');
      data.set('country', form.elements['country']?.value || '');
      data.set('job_type', form.elements['job_type']?.value || '');
      data.set('workplace_type', form.elements['workplace_type']?.value || '');
      data.set('per_page', perPage || document.querySelector('.pagination select')?.value || form.elements['per_page']?.value || '10');
      data.set('page', page || 1);
      data.set('ajax', '1');
      return data
    }

    function reloadJobs(page, perPage) {
      const url = '<?= ADMIN_URL ?>/pages/jobs.php?' + jobsQuery(page, perPage).toString();
      fetch(url)
        .then(response => {
          if (!response.ok) throw new Error('request failed');
          return response.json()
        })
        .then(data => {
          document.querySelector('.jobs-table tbody').innerHTML = data.rows;
          document.querySelector('.bulk-bar strong').textContent = Number(data.count).toLocaleString();
          const pagination = document.querySelector('.pagination');
          pagination.innerHTML = data.pagination;
          if (perPage) pagination.querySelector('select').value = String(perPage);
          document.querySelectorAll('.row-check').forEach(box => box.addEventListener('change', updateBulkButton));
          updateBulkButton()
        })
        .catch(() => {})
    }

    document.querySelector('.pagination')?.addEventListener('click', event => {
      const link = event.target.closest('a[data-page]');
      if (!link || link.classList.contains('disabled')) return;
      event.preventDefault();
      reloadJobs(parseInt(link.dataset.page, 10))
    });

    const filterForm = document.getElementById('jobsFilterForm'),
      jobSearch = document.getElementById('jobSearch');
    let searchTimer;
    document.querySelectorAll('[data-auto-filter]').forEach(select => select.addEventListener('change', () => reloadJobs(1)));
    jobSearch.addEventListener('input', () => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => reloadJobs(1), 350)
    });
    // document.addEventListener('click', event => {
    //   if (!event.target.closest('#floatingJobMenu, .job-actions-trigger')) closeActionMenus()
    // });
    // document.addEventListener('keydown', event => {
    //   if (event.key === 'Escape') closeActionMenus()
    // });
    // window.addEventListener('resize', () => closeActionMenus());
    // window.addEventListener('scroll', () => closeActionMenus(), true);
  </script>
</body>

</html>