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
    'archived' => ['Pending Review', 'archived'],
    default => ['Draft', 'draft'],
  };
}

function renderJobsTableBody(array $jobs): string
{
  ob_start();
  if (!$jobs) { ?>
    <tr>
      <td colspan="10">
        <div class="no-jobs">
          <strong>No jobs found</strong>
          <span>Try adjusting your filters or create a new job.</span>
          <a href="<?= ADMIN_URL ?>/pages/post_job.php">Create Job</a>
        </div>
      </td>
    </tr>
    <?php
  } else {
    foreach ($jobs as $job): $expired = $job['status'] === 'published' && !empty($job['close_date']) && strtotime($job['close_date']) < strtotime('today');
      [$statusLabel, $statusClass] = jobsStatus($job['status'], $expired);
      $publicUrl = SITE_URL . '/job-detail.php?slug=' . urlencode($job['slug']);
      $editUrl = ADMIN_URL . '/pages/post_job.php?edit=' . (int)$job['id']; ?>
      <tr class="job-row" data-edit="<?= e($editUrl) ?>">
        <td class="check-column"><input class="row-check" type="checkbox" name="ids[]" value="<?= (int)$job['id'] ?>" aria-label="Select <?= e($job['job_title']) ?>"></td>
        <td class="job-cell">
          <a href="<?= e($editUrl) ?>" target="_blank" rel="noopener">
            <strong><?= e(ucwords($job['job_title'])) ?></strong>
            <small><?= e($job['job_code']) ?> <i>·</i> Job Code: <?= e($job["client_code"]) ?></small>
          </a>
        </td>
        <td>
          <span class="country-value">
            <b><?= jobsFlag($job['country']) ?></b>
            <?= e($job['country']) ?>
          </span>
        </td>
        <td>
          <span class="job-status <?= $statusClass ?>"><?= e($statusLabel) ?></span>
        </td>
        <td class="px-4 py-3.5 align-middle">
          <span class="badge badge-soft badge-sm whitespace-nowrap <?= e($job['workplace_type']) ?> capitalize"><?= e($job['workplace_type']) ?></span>
        </td>
        <td class="px-4 py-3.5 align-middle text-base-content/65"><?= e($job['experience'] ?: '—') ?></td>
        <td class="muted-cell">
          <?= e($job['job_type'] ?: '—') ?>
        </td>
        <td>
          <?= e($job['posted_by'] ?: '—') ?>
        </td>
        <td class="muted-cell created-cell">
          <?= $job['created_at'] ? date('M j, Y', strtotime($job['created_at'])) : '—' ?>
        </td>
        <td class="actions-column">
          <div class="actions-menu">
            <button class="menu-trigger" type="button" aria-label="Actions for <?= e($job['job_title']) ?>" aria-expanded="false" onclick="toggleActionMenu(event,this)">
              <svg viewBox="0 0 24 24">
                <circle cx="5" cy="12" r="1" />
                <circle cx="12" cy="12" r="1" />
                <circle cx="19" cy="12" r="1" />
              </svg>
            </button>
            <div class="menu-popover" hidden>
              <a href="<?= e($editUrl) ?>">Edit</a>
              <button type="button" data-url="<?= e($publicUrl) ?>" onclick="copyJobLink(this)">Copy link</button>
              <?php if ($job['status'] !== 'published' || $expired): ?>
                <button type="button" onclick="confirmJobAction(this)" data-id="<?= (int)$job['id'] ?>" data-value="publish" data-action="Publish" data-job="<?= e($job['job_title']) ?>">Publish</button>
              <?php else: ?>
                <button type="button" onclick="confirmJobAction(this)" data-id="<?= (int)$job['id'] ?>" data-value="draft" data-action="Move to draft" data-job="<?= e($job['job_title']) ?>">Move to draft</button>
              <?php endif; ?>
              <button type="button" onclick="confirmJobAction(this)" data-id="<?= (int)$job['id'] ?>" data-value="clone" data-action="Clone" data-job="<?= e($job['job_title']) ?>">Clone</button>
              <button type="button" onclick="confirmJobAction(this)" data-id="<?= (int)$job['id'] ?>" data-value="close" data-action="Close" data-job="<?= e($job['job_title']) ?>">Close</button>
              <button class="danger" type="button" onclick="confirmJobAction(this)" data-id="<?= (int)$job['id'] ?>" data-value="delete" data-action="Delete" data-job="<?= e($job['job_title']) ?>" data-danger="true">Delete</button>
            </div>
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
  <p><?= number_format($totalRows) ?> total jobs</p>
  <div><a class="<?= $page <= 1 ? 'disabled' : '' ?>" data-page="<?= $page - 1 ?>" aria-label="Previous page">‹</a><span>Page <?= $page ?> of <?= $totalPages ?></span><a class="<?= $page >= $totalPages ? 'disabled' : '' ?>" data-page="<?= $page + 1 ?>" aria-label="Next page">›</a><select aria-label="Rows per page" onchange="reloadJobs(1, this.value)"><?php foreach ([10, 15, 25, 50] as $size): ?><option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?> / page</option><?php endforeach; ?></select></div>
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
  <link href="<?= ADMIN_URL ?>/dashboard.css" rel="stylesheet">
  <link href="<?= ADMIN_URL ?>/jobs.css" rel="stylesheet">
  <link href="<?= ADMIN_URL ?>/jobs-actions.css" rel="stylesheet">
</head>

<body>
  <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Open navigation" aria-expanded="false"><svg viewBox="0 0 24 24">
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
      <a class="new-job" href="<?= ADMIN_URL ?>/pages/post_job.php"><span>+</span> Create Job</a>
    </div>
  </aside>

  <main class="main jobs-main">
    <div class="jobs-content">
      <header class="jobs-heading">
        <h1>Jobs</h1>
        <a class="primary-action" href="<?= ADMIN_URL ?>/pages/post_job.php"><span>+</span> Create Job</a>
      </header>
      <?php if ($successMessage || $errorMessage || $warnMessage): $message = $successMessage ?: ($errorMessage ?: $warnMessage);
        $type = $successMessage ? 'success' : ($errorMessage ? 'error' : 'warn'); ?><div class="notice notice-<?= $type ?>"><?= $message ?></div><?php endif; ?>

      <form method="GET" class="filters" id="jobsFilterForm" action="<?= ADMIN_URL ?>/pages/jobs.php">
        <input type="hidden" name="per_page" value="<?= $perPage ?>">
        <label class="search-box"><svg viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-4-4" />
          </svg><input id="jobSearch" type="search" name="q" value="<?= e($fSearch) ?>" placeholder="Search jobs..." autocomplete="off"></label>
        <select name="status" aria-label="Status" data-auto-filter>
          <option value="">All Statuses</option><?php foreach ($statusOptions as $value => $label): ?><option value="<?= $value ?>" <?= $fStatus === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
        </select>
        <select name="country" aria-label="Country" data-auto-filter>
          <option value="">All Countries</option><?php foreach ($countryOptions as $country): ?><option value="<?= e($country) ?>" <?= $fCountry === $country ? 'selected' : '' ?>><?= jobsFlag($country) ?> <?= e($country) ?></option><?php endforeach; ?>
        </select>
        <select name="job_type" aria-label="Job Type" data-auto-filter>
          <option value="">All Job Types</option><?php foreach ($jobTypeOptions as $jobType): ?><option value="<?= e($jobType) ?>" <?= $fJobType === $jobType ? 'selected' : '' ?>><?= e($jobType) ?></option><?php endforeach; ?>
        </select>
        <select name="workplace_type" aria-label="Workplace" data-auto-filter>
          <option value="">All Workplaces</option><?php foreach ($workplaceTypeOptions as $workplaceType): ?><option value="<?= e($workplaceType) ?>" <?= $fWorkplaceType === $workplaceType ? 'selected' : '' ?>><?= e($workplaceType) ?></option><?php endforeach; ?>
        </select>
        <a class="clear-button" href="<?= ADMIN_URL ?>/pages/jobs.php">Clear</a>
      </form>

      <form method="POST" id="bulkForm" onsubmit="return confirmBulkAction()">
        <?= csrfField() ?>
        <div class="table-card">
          <div class="bulk-bar">
            <span>
              <strong><?= number_format($totalRows) ?></strong> total jobs
            </span>
            <div>
              <select name="action" id="bulkAction">
                <option value="">Bulk Actions</option>
                <option value="publish">Publish</option>
                <option value="draft">Move to Draft</option>
                <option value="close">Close</option>
                <option value="delete">Delete</option>
              </select>
              <button type="submit" id="bulkApplyBtn" disabled>Apply</button>
            </div>
          </div>
          <div class="jobs-table-wrap">
            <table class="jobs-table">
              <thead>
                <tr>
                  <th class="check-column"><input id="checkAll" type="checkbox" aria-label="Select all jobs"></th>
                  <th>Job</th>
                  <th>Country</th>
                  <th>Status</th>
                  <th>Workplace</th>
                  <th>Experience</th>
                  <th>Type</th>
                  <th>Posted By</th>
                  <th>Created</th>
                  <th class="actions-column"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?= renderJobsTableBody($jobs) ?>
              </tbody>
            </table>
          </div>
        </div>
      </form>

      <footer class="pagination">
        <?= renderJobsPagination($page, $totalPages, $totalRows, $perPage) ?>
      </footer>
    </div>
  </main>
  <div class="copy-toast" id="copyToast" role="status">Job link copied</div>
  <dialog class="action-dialog" id="jobActionDialog">
    <div class="action-dialog-card">
      <div class="action-dialog-icon" id="actionDialogIcon">?</div>
      <h2 id="actionDialogTitle">Confirm action</h2>
      <p id="actionDialogCopy"></p>
      <div><button type="button" onclick="document.getElementById('jobActionDialog').close()">Cancel</button>
        <form method="POST" action="<?= ADMIN_URL ?>/pages/job_action.php" style="display:inline"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="id" id="actionJobId"><input type="hidden" name="a" id="actionJobValue"><button id="actionDialogConfirm" type="submit">Confirm</button></form>
      </div>
    </div>
    <form method="dialog" class="action-dialog-backdrop"><button aria-label="Close dialog">close</button></form>
  </dialog>
  <dialog class="action-dialog" id="bulkDialog">
    <div class="action-dialog-card">
      <div class="action-dialog-icon" id="bulkDialogIcon">?</div>
      <h2 id="bulkDialogTitle">Confirm action</h2>
      <p id="bulkDialogCopy"></p>
      <div><button type="button" onclick="document.getElementById('bulkDialog').close()">Cancel</button>
        <button type="button" id="bulkDialogConfirm">Confirm</button>
      </div>
    </div>
    <form method="dialog" class="action-dialog-backdrop"><button aria-label="Close dialog">close</button></form>
  </dialog>
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
      if (event.target.closest('a, button, input, select')) return;
      window.open(row.dataset.edit, '_blank', 'noopener')
    });

    function confirmBulkAction() {
      const count = document.querySelectorAll('.row-check:checked').length,
        action = document.getElementById('bulkAction').value;
      if (!count || !action) {
        alert('Select at least one job and an action.');
        return false
      }
      const labels = {
        publish: 'Publish',
        draft: 'Move to draft',
        archive: 'Move to pending review',
        close: 'Close',
        delete: 'Delete'
      };
      const label = labels[action] || action,
        danger = action === 'delete';
      document.getElementById('bulkDialogTitle').textContent = label + ' jobs?';
      document.getElementById('bulkDialogCopy').textContent = 'Apply “' + label + '” to ' + count + ' selected job' + (count === 1 ? '' : 's') + '?';
      document.getElementById('bulkDialogIcon').textContent = danger ? '!' : '?';
      const confirm = document.getElementById('bulkDialogConfirm');
      confirm.textContent = label;
      confirm.classList.toggle('danger', danger);
      document.getElementById('bulkDialog').showModal();
      return false
    }
    document.getElementById('bulkDialogConfirm')?.addEventListener('click', () => {
      document.getElementById('bulkForm').submit();
      document.getElementById('bulkDialog').close()
    });
    async function copyJobLink(button) {
      try {
        await navigator.clipboard.writeText(button.dataset.url)
      } catch (e) {
        const area = document.createElement('textarea');
        area.value = button.dataset.url;
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        area.remove()
      }
      const toast = document.getElementById('copyToast');
      toast.classList.add('show');
      setTimeout(() => toast.classList.remove('show'), 1800)
    }

    function closeActionMenus(except = null) {
      document.querySelectorAll('.menu-popover:not([hidden])').forEach(popover => {
        if (popover !== except) {
          popover.hidden = true;
          popover.previousElementSibling?.setAttribute('aria-expanded', 'false')
        }
      })
    }

    function toggleActionMenu(event, button) {
      event.stopPropagation();
      const popover = button.nextElementSibling,
        willOpen = popover.hidden;
      closeActionMenus(popover);
      if (willOpen) {
        const rect = button.getBoundingClientRect();
        popover.style.top = (rect.bottom + 4) + 'px';
        popover.style.left = Math.max(8, rect.right - 145) + 'px'
      }
      popover.hidden = !willOpen;
      button.setAttribute('aria-expanded', String(willOpen))
    }

    function confirmJobAction(button) {
      closeActionMenus();
      const dialog = document.getElementById('jobActionDialog'),
        danger = button.dataset.danger === 'true',
        action = button.dataset.action;
      document.getElementById('actionDialogTitle').textContent = action + ' job?';
      document.getElementById('actionDialogCopy').textContent = action + ' “' + button.dataset.job + '”?';
      document.getElementById('actionDialogIcon').textContent = danger ? '!' : '?';
      document.getElementById('actionJobId').value = button.dataset.id;
      document.getElementById('actionJobValue').value = button.dataset.value;
      const confirm = document.getElementById('actionDialogConfirm');
      confirm.textContent = action;
      confirm.classList.toggle('danger', danger);
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
          document.querySelector('.bulk-bar strong').textContent = Number(data.count).toLocaleString() + ' total jobs';
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
    document.addEventListener('click', event => {
      if (!event.target.closest('.actions-menu')) closeActionMenus()
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') closeActionMenus()
    });
    window.addEventListener('resize', () => closeActionMenus());
    window.addEventListener('scroll', () => closeActionMenus(), true);
  </script>
</body>

</html>