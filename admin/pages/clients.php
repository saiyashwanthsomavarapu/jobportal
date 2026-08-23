<?php
require_once dirname(__DIR__) . "/auth.php";
require_once dirname(__DIR__) . "/utils/classes.php";


$pageTitle = "Clients";
$breadcrumbs = [["Dashboard", ADMIN_URL . "/index.php"], ["Clients", null]];

$errors = [];
$success = "";

// ── Handle POST actions ───────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
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
          $dup = db()->prepare("SELECT id FROM clients WHERE client_name = ? AND id != ?");
          $dup->execute([strtolower($clientName), $clientId]);
          if ($dup->fetch()) {
            $errors[] = 'Client name "' . $clientName . '" already exists.';
          } else {
            db()->prepare("UPDATE clients SET client_name=?, updated_at=NOW() WHERE id=?")
              ->execute([strtolower($clientName), $clientId]);
            flash('success', 'Client updated.');
            redirect(ADMIN_URL . '/pages/clients.php');
          }
        } else {
          // Check duplicate
          $dup = db()->prepare("SELECT id FROM clients WHERE client_name = ?");
          $dup->execute([strtolower($clientName)]);
          if ($dup->fetch()) {
            $errors[] = 'Client name "' . $clientName . '" already exists.';
          } else {
            db()->prepare("INSERT INTO clients (client_name, created_by) VALUES (?,?,?)")
              ->execute([strtolower($clientName), $_SESSION['admin_id']]);
            flash('success', 'Client <strong>' . $clientName . '</strong> created.');
            redirect(ADMIN_URL . '/pages/clients.php');
          }
        }
      } catch (Exception $e) {
        $errors[] = "Database error: " . $e->getMessage();
      }
    }
  } elseif ($action === "delete" && $clientId > 0) {
    try {
      db()
        ->prepare("DELETE FROM clients WHERE id = ?")
        ->execute([$clientId]);
      flash("success", "Client deleted.");
    } catch (Exception $e) {
      flash("error", "Cannot delete: " . $e->getMessage());
    }
    redirect(ADMIN_URL . "/pages/clients.php");
  }
}

// ── Load client for editing ───────────────────────────────────
$editClient = null;
if (isset($_GET["edit"])) {
  $stmt = db()->prepare("SELECT * FROM clients WHERE id = ?");
  $stmt->execute([(int) $_GET["edit"]]);
  $editClient = $stmt->fetch();
}

// ── Load all clients ──────────────────────────────────────────
try {
  $clients = db()
    ->query(
      "SELECT c.*, a.name AS created_by_name
         FROM clients c LEFT JOIN admin_users a ON a.id = c.created_by
         ORDER BY c.client_name ASC"
    )
    ->fetchAll();
} catch (Exception $e) {
  $clients = [];
}

include dirname(__DIR__) . "/includes/header.php";
?>

<!-- ══════════════ LIGHT CANVAS ══════════════
     Same pattern as admins.php: breaks out of the light shell's padding
     (header.php's main body uses px-7 py-6 / max-md:px-4 py-4) so this page
     renders as a self-contained light panel while the sidebar/topbar stay light.
     Background colors carry both a Tailwind arbitrary-value class AND a hard
     inline style, so they render even if the Tailwind CDN script fails to load. -->
<div class="min-w-0 space-y-6">

  <?php $isEditPage = (bool) $editClient; ?>

  <!-- Top row: breadcrumb + actions -->
  <div class="flex items-center justify-end flex-wrap gap-3 mb-6">
    <!-- <div class="text-[13px] text-gray-500 flex items-center gap-2">
      <a href="<?= ADMIN_URL ?>/pages/clients.php" class="hover:text-gray-900 transition-colors">Clients</a>
      <span class="text-gray-300">/</span>
      <span class="text-gray-900"><?= $isEditPage
                                    ? "Edit Client"
                                    : "New Client" ?></span>
    </div> -->
    <div class="flex items-center gap-2.5">
      <?php if ($isEditPage): ?>
        <a href="<?= ADMIN_URL ?>/pages/clients.php"
          class="flex items-center gap-1.5 text-[12.5px] font-semibold text-gray-900/70 border border-gray-200 rounded-lg px-3.5 py-2 hover:bg-gray-50 hover:text-gray-900 transition-colors">
          <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
          Cancel
        </a>
      <?php endif; ?>
      <button type="submit" form="clientForm" class="btn btn-sm btn-primary shadow-sm">
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
  </div>

  <?php if (!empty($errors)): ?>
    <div class="flex items-start gap-2.5 px-4 py-3 rounded-xl mb-5 text-[13px] font-medium bg-red-50 border border-red-200 text-red-600">
      <svg class="h-[18px] w-[18px] flex-shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
      </svg>
      <div><?= implode("<br>", array_map("e", $errors)) ?></div>
    </div>
  <?php endif; ?>

  <form method="POST" id="clientForm">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="client_id" value="<?= $editClient
                                                    ? $editClient["id"]
                                                    : 0 ?>">

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-5 items-start">

      <!-- ══ LEFT: Client Details ══ -->
      <div class="rounded-2xl p-6 border border-gray-200" style="background-color:#ffffff !important">
        <div class="flex items-center gap-2.5 mb-5">
          <div class="<?= SVG_DIV ?>">
            <svg class="<?= SVG_ICON ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21V6.75A1.5 1.5 0 015.25 5.25h6A1.5 1.5 0 0112.75 6.75V21M3.75 21h16.5M3.75 21H2.25M20.25 21V10.5a1.5 1.5 0 00-1.5-1.5h-3a1.5 1.5 0 00-1.5 1.5V21m3-15h.008v.008H18v-.008zm0 3h.008v.008H18v-.008zm0 3h.008v.008H18v-.008zM6.75 9h.008v.008H6.75V9zm0 3h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm3-6h.008v.008H9.75V9zm0 3h.008v.008H9.75V12zm0 3h.008v.008H9.75V15z" />
            </svg>
          </div>
          <span class="font-head text-[15px] font-bold">Client</span>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

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
              value="<?= e($editClient["client_name"] ?? ($_POST["client_name"] ?? "")) ?>"
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
      </div>

      <!-- ══ RIGHT RAIL ══ -->
      <div class="space-y-5 min-w-0">

        <!-- Stats card -->
        <div class="rounded-2xl p-6 border border-gray-200" style="background-color:#ffffff !important">
          <div class="flex items-center gap-2.5 mb-1">
            <div class="<?= SVG_DIV ?>">
              <svg class="<?= SVG_ICON ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
              </svg>
            </div>
            <span class="font-head text-[15px] font-bold">Overview</span>
          </div>
          <div class="flex items-baseline gap-2 mt-3">
            <span class="font-head text-[28px] font-extrabold text-gray-900 leading-none"><?= count(
                                                                                            $clients
                                                                                          ) ?></span>
            <span class="text-[12.5px] text-gray-500">total clients</span>
          </div>
        </div>



      </div>
    </div>
  </form>

  <!-- ══ CLIENTS TABLE ══ -->
  <div class="rounded-2xl overflow-hidden mt-5 border border-gray-200 shadow-sm">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
      <span class="font-head text-[15px] font-bold">All Clients</span>
      <span class="badge badge-ghost badge-sm font-medium">
        <?= count($clients) ?> total
      </span>
    </div>

    <div class="overflow-x-auto">
      <table class="<?= TABLE_CLASS ?>">
        <thead class="<?= TABLE_HEAD_CLASS ?>">
          <tr>
            <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Client Name</th>
            <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Created By</th>
            <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Created</th>
            <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($clients)): ?>
            <tr>
              <td colspan="5" class="text-center text-gray-500 py-10">
                <div class="flex flex-col items-center gap-2">
                  <svg class="h-8 w-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21V6.75A1.5 1.5 0 015.25 5.25h6A1.5 1.5 0 0112.75 6.75V21M3.75 21h16.5M3.75 21H2.25M20.25 21V10.5a1.5 1.5 0 00-1.5-1.5h-3a1.5 1.5 0 00-1.5 1.5V21" />
                  </svg>
                  No clients yet. Create your first client &rarr;
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($clients as $c): ?>
              <tr class="border-b border-gray-100 last:border-b-0 hover:bg-gray-50 transition-colors">
                <td class="px-6 py-3.5 align-middle">
                  <strong class="text-[13px] text-gray-900 font-semibold"><?= e(
                                                                            $c["client_name"]
                                                                          ) ?></strong>
                </td>

                <td class="px-4 py-3.5 align-middle font-medium text-base-content/70"><?= e(
                                                                                        $c["created_by_name"] ?? "—"
                                                                                      ) ?></td>
                <td class="px-4 py-3.5 align-middle font-medium text-base-content/70">
                  <?= date("d M Y", strtotime($c["created_at"])) ?>
                </td>
                <td class="px-6 py-3.5 align-middle">
                  <div class="flex items-center gap-1.5">
                    <div class="tooltip" data-tip="Edit">
                      <a href="<?= ADMIN_URL ?>/pages/clients.php?edit=<?= $c["id"] ?>" title="Edit"
                        class="btn btn-sm btn-square h-8 min-h-8 w-8 rounded-lg btn-outline border-base-300 bg-base-100 text-base-content/60 hover:border-secondary hover:bg-secondary hover:text-secondary-content">
                        <svg class="h-[15px] w-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                        </svg>
                      </a>
                    </div>
                    <form method="POST" class="inline">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="client_id" value="<?= e($c['id']) ?>">
                      <div class="tooltip" data-tip="Delete">
                        <button
                          type="button"
                          onclick="openDeleteModal(
                          <?= (int)$c['id'] ?>,
                          <?= htmlspecialchars(json_encode($c['client_name']), ENT_QUOTES, 'UTF-8') ?>
                        )"
                          class="btn btn-sm btn-square h-8 min-h-8 w-8 rounded-lg btn-outline btn-error">

                          <svg
                            class="h-[15px] w-[15px]"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.75"
                            aria-hidden="true">
                            <path
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                          </svg>
                        </button>
                      </div>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

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
              Are you sure you want to delete <strong id="deleteClientName" class="text-error"></strong> client?
            </p>
            <p class="mt-2 text-xs leading-5 text-base-content/60">
              This will permanently remove the job posting and all associated data. This action cannot be undone.
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

<script>
  function openDeleteModal(clientId, clientName) {
    document.getElementById('deleteClientId').value = clientId;
    document.getElementById('deleteClientName').textContent = clientName;

    document.getElementById('deleteClientModal').showModal();
  }
</script>
<?php include dirname(__DIR__) . "/includes/footer.php"; ?>