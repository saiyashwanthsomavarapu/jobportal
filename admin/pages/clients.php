<?php
require_once dirname(__DIR__) . "/auth.php";
require_once dirname(__DIR__) . "/utils/classes.php";
require_once dirname(__DIR__) . "/DB/queries.php";


$pageTitle = "Clients";
$breadcrumbs = [["Dashboard", ADMIN_URL . "/index.php"], ["Clients", null]];

$errors = [];
$success = "";
$oldClientName = null;
$successMessage = "";
$errorMessage = "";
$warnMessage = "";
$canWrite = currentAdminCanWrite();
$page = max(1, (int) ($_GET['page'] ?? 1));
$requestedPerPage = (int) ($_GET['per_page'] ?? 10);
$perPage = in_array($requestedPerPage, [10, 15, 25, 50], true) ? $requestedPerPage : 10;
$totalClients = 0;
$totalPages = 1;
$offset = 0;

function clientsPageUrl(int $pg): string
{
  $params = $_GET;
  $params['page'] = max(1, $pg);
  unset($params['edit']);

  return '?' . http_build_query($params);
}

function renderClientsPagination(int $page, int $totalPages, int $totalRows): string
{
  ob_start(); ?>
  <p class="m-0 text-sm text-base-content/60"><?= number_format($totalRows) ?> total clients</p>
  <?php if ($totalPages > 1): ?>
    <div class="mt-5 flex items-center justify-end">
      <div class="join">
        <?php if ($page > 1): ?>
          <a href="<?= clientsPageUrl($page - 1) ?>"
            class="btn btn-sm join-item border-base-300 bg-base-100 text-base-content/70 hover:bg-base-200">‹ Prev</a>
        <?php endif; ?>

        <?php foreach (range(max(1, $page - 2), min($totalPages, $page + 2)) as $pg): ?>
          <a href="<?= clientsPageUrl($pg) ?>"
            class="btn btn-sm join-item <?= $pg === $page ? 'btn-primary !text-white' : 'border-base-300 bg-base-100 text-base-content/70 hover:bg-base-200' ?>">
            <?= $pg ?>
          </a>
        <?php endforeach; ?>

        <?php if ($page < $totalPages): ?>
          <a href="<?= clientsPageUrl($page + 1) ?>"
            class="btn btn-sm join-item border-base-300 bg-base-100 text-base-content/70 hover:bg-base-200">Next ›</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
<?php return ob_get_clean();
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
  $savedErrors = flash('clients_save_errors');
  if ($savedErrors) {
    $errors = json_decode($savedErrors, true) ?: [];
    $oldClientName = flash('clients_old_name') ?: null;
  }
  $successMessage = flash('success') ?: "";
  $errorMessage = flash('error') ?: "";
  $warnMessage = flash('warn') ?: "";
}

// ── Handle POST actions ───────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (!currentAdminCanWrite()) {
    flash('error', 'Read-only users cannot perform this action.');
    redirect(ADMIN_URL . '/pages/clients.php');
  }

  $action = $_POST["action"] ?? "";
  $clientId = (int) ($_POST["client_id"] ?? 0);
  $clientName = trim($_POST["client_name"] ?? "");


  if ($action === "save") {
    if (!$clientName) {
      $errors[] = "Client Name is required.";
    }

    if (empty($errors)) {
      try {
        if ($clientId > 0) {
          if (clientNameExistsForOtherClient($clientName, $clientId)) {
            $errors[] = 'Client name "' . $clientName . '" already exists.';
          } else {
            updateClientName($clientId, $clientName);
            flash('success', 'Client updated.');
            redirect(ADMIN_URL . '/pages/clients.php');
          }
        } else {
          // Check duplicate
          if (clientNameExists($clientName)) {
            $errors[] = 'Client name "' . $clientName . '" already exists.';
          } else {
            createClient($clientName, (int) $_SESSION['admin_id']);
            flash('success', 'Client <strong>' . $clientName . '</strong> created.');
            redirect(ADMIN_URL . '/pages/clients.php');
          }
        }
      } catch (Exception $e) {
        error_log('Client save failed: ' . $e->getMessage());
        $errors[] = "The client could not be saved. Please try again.";
      }
    }

    if (!empty($errors)) {
      flash('clients_save_errors', json_encode($errors));
      flash('clients_old_name', $clientName);
      redirect(
        $clientId > 0
          ? ADMIN_URL . '/pages/clients.php?edit=' . $clientId
          : ADMIN_URL . '/pages/clients.php'
      );
    }
  } elseif ($action === "delete" && $clientId > 0) {
    try {
      if (countJobsForClient($clientId) > 0) {
        flash('error', 'This client cannot be deleted because one or more jobs are linked to it.');
        redirect(ADMIN_URL . '/pages/clients.php');
      }
      deleteClientById($clientId);
      flash("success", "Client deleted.");
    } catch (Exception $e) {
      error_log('Client delete failed: ' . $e->getMessage());
      flash("error", "The client could not be deleted.");
    }
    redirect(ADMIN_URL . "/pages/clients.php");
  }
}

// ── Load client for editing ───────────────────────────────────
$editClient = null;
if (isset($_GET["edit"])) {
  $editClient = getClientById((int) $_GET["edit"]);
}

// ── Load clients ──────────────────────────────────────────────
try {
  $totalClients = countClients();
  $totalPages = max(1, (int) ceil($totalClients / $perPage));
  $page = min($page, $totalPages);
  $offset = ($page - 1) * $perPage;
  $clients = getClientsWithCreatorPaginated($offset, $perPage);
} catch (Exception $e) {
  $clients = [];
}

?>
<?php
$postJobPageClass = "mr-auto min-w-0 w-full max-w-[870px] space-y-6 font-['Plus_Jakarta_Sans',system-ui,sans-serif] [@media(min-width:821px)]:ml-[264px] [@media(min-width:821px)]:w-[calc(100vw-288px)] [@media(min-width:1056px)]:w-full";
$postJobCardClass = "scroll-mt-4 overflow-hidden rounded-xl  bg-base-100 p-5 shadow-sm";
$postJobHeadingClass = "flex items-center gap-2.5  border-base-300 pb-3.5";
$postJobHeadingTextClass = "text-base font-semibold leading-6 text-base-content";
$postJobIconBaseClass = "flex size-7 shrink-0 items-center justify-center rounded-lg";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Clients — <?= e(SITE_NAME) ?> Recruitment</title>
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
      <a href="<?= ADMIN_URL ?>/pages/jobs.php">
        <svg viewBox="0 0 24 24">
          <path d="M8 7V5.5A1.5 1.5 0 0 1 9.5 4h5A1.5 1.5 0 0 1 16 5.5V7M5 7h14a1 1 0 0 1 1 1v11H4V8a1 1 0 0 1 1-1Zm7 0v12" />
        </svg>Jobs
      </a>
      <a class="active" href="<?= ADMIN_URL ?>/pages/clients.php">
        <svg viewBox="0 0 24 24">
          <path d="M3.75 21V6.75A1.5 1.5 0 0 1 5.25 5.25h6A1.5 1.5 0 0 1 12.75 6.75V21M3.75 21h16.5M3.75 21H2.25M20.25 21V10.5a1.5 1.5 0 0 0-1.5-1.5h-3a1.5 1.5 0 0 0-1.5 1.5V21" />
        </svg>Clients
      </a>
      <?php if ($canWrite): ?>
        <a href="<?= ADMIN_URL ?>/pages/admins.php">
          <svg viewBox="0 0 24 24">
            <path d="M15.5 8a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0ZM5 20a7 7 0 0 1 14 0M18 5v6M15 8h6" />
          </svg>Admin
        </a>
      <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
      <div class="admin-row">
        <span class="admin-avatar"><?= e(strtoupper(substr($currentAdmin['name'] ?? 'A', 0, 1))) ?></span>
        <span class="admin-copy">
          <strong><?= e($currentAdmin['name'] ?? 'Admin') ?></strong>
          <small><?= e(adminRoleLabel($currentAdmin['role'] ?? 'admin')) ?></small>
        </span>
        <a class="signout" href="<?= ADMIN_URL ?>/logout.php" title="Sign out">
          <svg viewBox="0 0 24 24">
            <path d="M10 5H6v14h4m4-4 4-3-4-3m4 3H9" />
          </svg>
        </a>
      </div>
      <?php if ($canWrite): ?>
        <a class="btn btn-primary btn-sm w-full text-white!" href="<?= ADMIN_URL ?>/pages/post_job.php"><span>+</span> Create Job</a>
      <?php endif; ?>
    </div>
  </aside>

  <main class="main <?= $postJobPageClass ?>">



    <div class="min-h-screen bg-base-200/40 px-6 py-7 max-md:px-3.5 max-md:py-6">
      <header class="flex items-center justify-between gap-4 max-md:pl-12">
        <h1 class="m-0 text-2xl font-bold leading-tight text-base-content">Clients</h1>
      </header>

      <?php if (!empty($errors)): ?>
        <div class="flex items-start gap-2.5 px-4 py-3 rounded-xl mb-5 text-[13px] font-medium bg-red-50 border border-red-200 text-red-600">
          <svg class="h-[18px] w-[18px] flex-shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <div><?= implode("<br>", array_map("e", $errors)) ?></div>
        </div>
      <?php endif; ?>
      <?php if ($successMessage || $errorMessage || $warnMessage):
        $message = $successMessage ?: ($errorMessage ?: $warnMessage);
        $alertClass = $successMessage ? 'alert-success' : ($errorMessage ? 'alert-error' : 'alert-warning');
      ?>
        <div role="alert" class="alert <?= $alertClass ?> alert-soft mb-5 rounded-lg text-sm mt-5">
          <?= $message ?>
        </div>
      <?php endif; ?>

      <?php $isEditPage = (bool) $editClient; ?>

      <?php if ($canWrite): ?>
        <details class="<?= $postJobCardClass ?> border-t-4 border-t-success mt-5 collapse  collapse-arrow bg-base-100 border border-base-300" name="my-accordion-det-1" <?= ($isEditPage || !empty($errors)) ? 'open' : '' ?>>
          <summary class="<?= $postJobHeadingClass ?> collapse-title font-semibold">
            <div class="<?= $postJobIconBaseClass ?> bg-success/10 text-success">
              <svg class="<?= SVG_ICON ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21V6.75A1.5 1.5 0 015.25 5.25h6A1.5 1.5 0 0112.75 6.75V21M3.75 21h16.5M3.75 21H2.25M20.25 21V10.5a1.5 1.5 0 00-1.5-1.5h-3a1.5 1.5 0 00-1.5 1.5V21m3-15h.008v.008H18v-.008zm0 3h.008v.008H18v-.008zm0 3h.008v.008H18v-.008zM6.75 9h.008v.008H6.75V9zm0 3h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm3-6h.008v.008H9.75V9zm0 3h.008v.008H9.75V12zm0 3h.008v.008H9.75V15z" />
              </svg>
            </div>
            <h2 class="<?= $postJobHeadingTextClass ?>"><?= $isEditPage ? 'Edit Client' : 'Create Client' ?></h2>
          </summary>
          <div class="collapse-content text-sm">
            <form method="POST" id="clientForm">

              <input type="hidden" name="action" value="save">
              <input type="hidden" name="client_id" value="<?= $editClient
                                                              ? $editClient["id"]
                                                              : 0 ?>">

              <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

                <!-- Client Name -->
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">
                    Client Name
                    <span class="text-error">*</span>
                  </legend>
                  <input
                    type="text"
                    name="client_name"
                    class="<?= INPUT_CLASS ?>"
                    placeholder="e.g. Autodesk"
                    value="<?= e($oldClientName ?? ($editClient["client_name"] ?? "")) ?>"
                    required
                    pattern="[A-Za-z0-9 .&-]+"
                    title="Only letters, numbers, spaces, periods, ampersands, and hyphens are allowed."
                    oninput="this.value = this.value.replace(/[^A-Za-z0-9 .&-]/g, '')"
                    autofocus />
                  <p class="text-xs text-base-content/50">
                    Enter the official client/company name.
                  </p>
                </fieldset>
              </div>
              <div class="flex items-center gap-2.5 mt-6">
                <?php if ($isEditPage): ?>
                  <a href="<?= ADMIN_URL ?>/pages/clients.php"
                    class="flex items-center gap-1.5 text-[12.5px] font-semibold text-gray-900/70 border border-gray-200 rounded-lg px-3.5 py-2 hover:bg-gray-50 hover:text-gray-900 transition-colors">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Cancel
                  </a>
                <?php endif; ?>
                <button
                  type="submit"
                  form="clientForm"
                  id="saveClientButton"
                  class="btn btn-sm btn-primary btn-disabled shadow-sm"
                  disabled>
                  <?php if ($isEditPage): ?>
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    Update Client
                  <?php else: ?>
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Create Client
                  <?php endif; ?>
                </button>
              </div>
            </form>
          </div>
        </details>
      <?php endif; ?>


      <!-- ══ CLIENTS TABLE ══ -->
      <div class="card mt-5 overflow-visible rounded-xl border border-base-300 bg-base-100 shadow-xs">
        <div class="bulk-bar flex min-h-14 items-center justify-between gap-3 border-b border-base-300 px-4 py-2 text-sm  max-sm:items-start max-sm:flex-col">
          <span>
            <strong><?= number_format($totalClients) ?></strong> total clients
          </span>
        </div>

        <div class="overflow-x-auto">
          <table class="jobs-table table table-sm min-w-[720px]">
            <thead>
              <tr>
                <th>Client Name</th>
                <th>Created By</th>
                <th>Created</th>
                <?php if ($canWrite): ?>
                  <th><span class="sr-only">Actions</span></th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($clients)): ?>
                <tr>
                  <td colspan="<?= $canWrite ? 4 : 3 ?>" class="py-20">
                    <div class="grid place-items-center gap-2 text-center text-base-content/60">
                      <strong class="text-base font-semibold text-base-content">No clients found</strong>
                      <span class="text-sm">Create your first client to get started.</span>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php $clientCount = count($clients); ?>
                <?php foreach ($clients as $index => $c): ?>
                  <tr class="group border-t border-base-300 transition-colors hover:bg-[#F28C28]">
                    <td class=" group-hover:bg-[#F28C28]">
                      <strong class="block font-medium text-base-content group-hover:text-white">
                        <?= e($c["client_name"]) ?>
                      </strong>
                    </td>
                    <td class="group-hover:bg-[#F28C28] group-hover:text-white">
                      <?= e($c["created_by_name"] ?? "—") ?>
                    </td>
                    <td class=" group-hover:bg-[#F28C28] group-hover:text-white">
                      <?= date("M j, Y", strtotime($c["created_at"])) ?>
                    </td>
                    <?php if ($canWrite): ?>
                      <td class="job-actions-cell right-0 z-30 w-14 text-right align-middle group-hover:bg-[#F28C28]" onclick="event.stopPropagation()">
                        <button type="button" class="btn btn-sm btn-ghost m-1 p-2 bg-transparent border-none shadow-none outline-none focus:outline-none focus-visible:outline-none hover:bg-transparent text-base-content group-hover:text-white" onclick="openClientActions(event, this)" aria-label="Open actions">
                          <svg class="size-4" viewBox="0 0 24 24">
                            <circle cx="5" cy="12" r="1" />
                            <circle cx="12" cy="12" r="1" />
                            <circle cx="19" cy="12" r="1" />
                          </svg>
                        </button>
                        <ul class="client-actions-template hidden">
                          <li onclick="window.location.href = '<?= ADMIN_URL ?>/pages/clients.php?edit=<?= $c['id'] ?>'">
                            <button type="button" title="Edit">
                              Edit
                            </button>
                          </li>
                          <form method="POST" class="inline">
                            <li onclick="openDeleteModal(
                            <?= (int)$c['id'] ?>,
                            <?= htmlspecialchars(json_encode($c['client_name']), ENT_QUOTES, 'UTF-8') ?>,
                            <?= (int)($c['jobs_count'] ?? 0) ?>
                            )">
                              <input type="hidden" name="action" value="delete">
                              <input type="hidden" name="client_id" value="<?= e($c['id']) ?>">
                              <div class="tooltip" data-tip="Delete">
                                <button type="button">
                                  Delete
                                </button>
                              </div>
                            </li>
                          </form>
                        </ul>
                      </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <footer class="pagination flex items-center justify-between gap-3 py-4 text-sm text-base-content/60 max-md:items-start max-md:flex-col">
        <?= renderClientsPagination($page, $totalPages, $totalClients) ?>
      </footer>

      <!-- Delete Client Modal -->
      <dialog id="deleteClientModal" class="modal">
        <div class="modal-box w-[calc(100%-2rem)] max-w-lg rounded-box border border-base-300 bg-base-100 p-0 shadow-xl">

          <!-- Header -->
          <div class="flex items-center gap-4 border-b border-base-200 px-5 py-5 sm:px-6">

            <div class="<?= SVG_DIV_ERROR ?>">
              <svg
                class="<?= SVG_ICON ?>"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.75">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
              </svg>
            </div>

            <div class="min-w-0 flex-1">
              <h3 class="<?= MODAL_HEADING ?>">
                Delete client
              </h3>
            </div>

            <button
              type="button"
              onclick="deleteClientModal.close()"
              class="btn btn-sm btn-circle btn-ghost size-8 min-h-8 shrink-0 text-base-content/50"
              aria-label="Close">

              <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>

            </button>
          </div>

          <!-- Body -->
          <div class="px-6 py-5">
            <div class="rounded-xl  p-4">
              <div class="flex items-start gap-3">
                <div class="flex-1">
                  <p class="text-sm font-medium text-base-content">
                    <span id="deleteClientConfirmCopy">
                      Are you sure you want to delete <strong id="deleteClientName" class="text-error"></strong> client?
                    </span>
                    <span id="deleteClientBlockedCopy" class="hidden">
                      <strong id="blockedClientName" class="text-error"></strong> cannot be deleted because it has <strong id="blockedClientJobCount"></strong> mapped job<span id="blockedClientPlural">s</span>.
                    </span>
                  </p>
                  <p id="deleteClientHelpCopy" class="mt-2 text-xs leading-5 text-base-content/60">
                    This will permanently remove the job posting and all associated data. This action cannot be undone.
                  </p>
                  <p id="deleteClientBlockedHelpCopy" class="mt-2 hidden text-xs leading-5 text-base-content/60">
                    Remove or reassign the mapped jobs first, then try deleting this client again.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="modal-action m-0 flex flex-col-reverse gap-2 border-t border-base-200 bg-base-200/30 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
            <button
              type="button"
              onclick="deleteClientModal.close()"
              class="btn btn-ghost h-10 min-h-10 w-full rounded-lg px-5 text-sm font-semibold sm:w-auto">
              Cancel
            </button>
            <form method="POST" id="confirmDeleteClientForm" class="w-full sm:w-auto">

              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="client_id" id="deleteClientId">
              <button
                type="submit"
                id="confirmDeleteClientButton"
                class="btn btn-error h-10 min-h-10 w-full rounded-lg px-5 text-sm font-semibold text-error-content sm:w-auto">
                <svg
                  class="size-4"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="2">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-9 0h14" />
                </svg>
                Delete permanently
              </button>
            </form>
          </div>
        </div>
        <form method="dialog" class="modal-backdrop bg-black/40">
          <button>close</button>
        </form>
      </dialog>

      <dialog id="unsavedClientChangesModal" class="modal">
        <div class="modal-box w-[calc(100%-2rem)] max-w-md rounded-box border border-base-300 bg-base-100 p-0 shadow-xl">
          <div class="flex items-start gap-4 border-b border-base-200 px-5 py-5 sm:px-6">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-warning/10 text-warning">
              <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12V16.5z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 3.75 2.625 18.75A1.5 1.5 0 0 0 3.925 21h16.15a1.5 1.5 0 0 0 1.3-2.25L12.75 3.75a.866.866 0 0 0-1.5 0z" />
              </svg>
            </div>
            <div class="min-w-0">
              <h3 class="<?= MODAL_HEADING ?>">Unsaved changes</h3>
              <p class="mt-1 text-sm leading-5 text-base-content/60">
                Your changes are not saved. If you leave now, the information you entered will be lost.
              </p>
            </div>
          </div>
          <div class="modal-action m-0 flex flex-col-reverse gap-2 border-t border-base-200 bg-base-200/30 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
            <button
              type="button"
              id="stayOnClientForm"
              class="btn btn-ghost h-10 min-h-10 w-full rounded-lg px-5 text-sm font-semibold sm:w-auto">
              Stay on page
            </button>
            <button
              type="button"
              id="leaveClientForm"
              class="btn btn-warning h-10 min-h-10 w-full rounded-lg px-5 text-sm font-semibold sm:w-auto">
              Leave without saving
            </button>
          </div>
        </div>
        <form method="dialog" class="modal-backdrop bg-black/40">
          <button>close</button>
        </form>
      </dialog>

      <ul id="floatingClientActions" class="menu fixed z-[9999] hidden w-56 rounded-box border border-base-300 bg-base-100 p-2 shadow-2xl" onclick="event.stopPropagation()"></ul>

      <script>
        function closeClientActions() {
          const menu = document.getElementById('floatingClientActions');
          if (!menu) return;
          menu.classList.add('hidden');
          menu.innerHTML = '';
          delete menu.dataset.source;
        }

        function openClientActions(event, button) {
          event.stopPropagation();
          const menu = document.getElementById('floatingClientActions');
          const template = button.parentElement?.querySelector('.client-actions-template');
          if (!menu || !template) return;

          if (!button.dataset.actionSource) {
            button.dataset.actionSource = `client-actions-${Date.now()}-${Math.random().toString(16).slice(2)}`;
          }

          if (!menu.classList.contains('hidden') && menu.dataset.source === button.dataset.actionSource) {
            closeClientActions();
            return;
          }

          menu.innerHTML = template.innerHTML;
          menu.dataset.source = button.dataset.actionSource;
          menu.classList.remove('hidden');

          const rect = button.getBoundingClientRect();
          const menuWidth = menu.offsetWidth || 224;
          const menuHeight = menu.offsetHeight || 120;
          const gap = 8;
          const left = Math.min(window.innerWidth - menuWidth - gap, Math.max(gap, rect.right - menuWidth));
          const belowTop = rect.bottom + gap;
          const aboveTop = rect.top - menuHeight - gap;
          const top = belowTop + menuHeight <= window.innerHeight - gap ?
            belowTop :
            Math.max(gap, aboveTop);

          menu.style.left = `${left}px`;
          menu.style.top = `${top}px`;
        }

        document.addEventListener('click', closeClientActions);
        window.addEventListener('scroll', closeClientActions, true);
        window.addEventListener('resize', closeClientActions);

        function openDeleteModal(clientId, clientName, jobsCount = 0) {
          closeClientActions();
          const hasMappedJobs = Number(jobsCount) > 0;
          document.getElementById('deleteClientId').value = clientId;
          document.getElementById('deleteClientName').textContent = clientName;
          document.getElementById('blockedClientName').textContent = clientName;
          document.getElementById('blockedClientJobCount').textContent = jobsCount;
          document.getElementById('blockedClientPlural').classList.toggle('hidden', Number(jobsCount) === 1);
          document.getElementById('deleteClientConfirmCopy').classList.toggle('hidden', hasMappedJobs);
          document.getElementById('deleteClientBlockedCopy').classList.toggle('hidden', !hasMappedJobs);
          document.getElementById('deleteClientHelpCopy').classList.toggle('hidden', hasMappedJobs);
          document.getElementById('deleteClientBlockedHelpCopy').classList.toggle('hidden', !hasMappedJobs);

          const confirmButton = document.getElementById('confirmDeleteClientButton');
          confirmButton.disabled = hasMappedJobs;
          confirmButton.classList.toggle('btn-disabled', hasMappedJobs);

          document.getElementById('deleteClientModal').showModal();
        }

        const clientFormEl = document.getElementById('clientForm');
        const initialClientFormSnapshot = clientFormEl ? clientFormSnapshot(clientFormEl) : '';
        let clientFormSubmitted = false;
        let pendingClientNavigation = null;

        function clientFormSnapshot(form) {
          const clientName = form.querySelector('[name="client_name"]')?.value || '';
          return `client_name:${clientName}`;
        }

        function hasUnsavedClientChanges() {
          return !!clientFormEl &&
            !clientFormSubmitted &&
            clientFormSnapshot(clientFormEl) !== initialClientFormSnapshot;
        }

        function updateClientFormState() {
          const button = document.getElementById('saveClientButton');
          if (!clientFormEl || !button) return;

          const clientNameField = clientFormEl.querySelector('[name="client_name"]');
          const clientName = (clientNameField?.value || '').trim();
          const hasChanges = hasUnsavedClientChanges();
          const isValid = clientName !== '' && !!clientNameField?.checkValidity();

          button.disabled = !(hasChanges && isValid);
          button.classList.toggle('btn-disabled', button.disabled);
        }

        function openUnsavedClientChangesModal(nextAction) {
          const modal = document.getElementById('unsavedClientChangesModal');
          if (!modal) return;

          pendingClientNavigation = nextAction;
          modal.showModal();
        }

        clientFormEl?.addEventListener('input', updateClientFormState);
        clientFormEl?.addEventListener('change', updateClientFormState);
        updateClientFormState();

        clientFormEl?.addEventListener('submit', event => {
          updateClientFormState();
          const button = document.getElementById('saveClientButton');
          if (button?.disabled) {
            event.preventDefault();
            clientFormEl.reportValidity();
            return;
          }

          clientFormSubmitted = true;
        });

        document.addEventListener('click', event => {
          const link = event.target.closest('a[href]');
          if (!link || !hasUnsavedClientChanges()) return;

          const href = link.getAttribute('href') || '';
          const target = link.getAttribute('target');
          if (
            href === '' ||
            href.startsWith('#') ||
            href.startsWith('javascript:') ||
            target === '_blank' ||
            link.hasAttribute('download')
          ) {
            return;
          }

          event.preventDefault();
          openUnsavedClientChangesModal(() => {
            window.location.href = link.href;
          });
        });

        document.addEventListener('submit', event => {
          const form = event.target;
          if (
            form === clientFormEl ||
            form?.getAttribute('method')?.toLowerCase() === 'dialog' ||
            !hasUnsavedClientChanges()
          ) {
            return;
          }

          event.preventDefault();
          openUnsavedClientChangesModal(() => {
            clientFormSubmitted = true;
            HTMLFormElement.prototype.submit.call(form);
          });
        }, true);

        document.getElementById('stayOnClientForm')?.addEventListener('click', () => {
          pendingClientNavigation = null;
          document.getElementById('unsavedClientChangesModal')?.close();
        });

        document.getElementById('leaveClientForm')?.addEventListener('click', () => {
          const nextAction = pendingClientNavigation;
          clientFormSubmitted = true;
          pendingClientNavigation = null;
          document.getElementById('unsavedClientChangesModal')?.close();

          if (typeof nextAction === 'function') {
            nextAction();
          }
        });

        window.addEventListener('beforeunload', event => {
          if (!hasUnsavedClientChanges()) return;

          event.preventDefault();
          event.returnValue = '';
        });
      </script>

    </div>
  </main>

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
  </script>
</body>

</html>