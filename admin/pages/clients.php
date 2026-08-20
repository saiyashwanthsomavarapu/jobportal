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
  $clientCode = strtoupper(trim($_POST["client_code"] ?? ""));

  // Validate code: 2-6 uppercase alphanumeric characters
  $clientCode = preg_replace("/[^A-Z0-9]/", "", $clientCode);

  if ($action === "save") {
    if (!$clientName) {
      $errors[] = "Client Name is required.";
    }
    if (!$clientCode) {
      $errors[] = "Client Code is required.";
    }
    if (strlen($clientCode) < 2 || strlen($clientCode) > 6) {
      $errors[] = "Client Code must be 2–6 characters.";
    }

    if (empty($errors)) {
      try {
        if ($clientId > 0) {
          // Check duplicate code (excluding self)
          $dup = db()->prepare(
            "SELECT id FROM clients WHERE client_code = ? AND id != ?"
          );
          $dup->execute([$clientCode, $clientId]);
          if ($dup->fetch()) {
            $errors[] =
              'Client Code "' . $clientCode . '" already exists.';
          } else {
            db()
              ->prepare(
                "UPDATE clients SET client_name=?, client_code=?, updated_at=NOW() WHERE id=?"
              )
              ->execute([$clientName, $clientCode, $clientId]);
            flash("success", "Client updated.");
            redirect(ADMIN_URL . "/pages/clients.php");
          }
        } else {
          // Check duplicate
          $dup = db()->prepare(
            "SELECT id FROM clients WHERE client_code = ?"
          );
          $dup->execute([$clientCode]);
          if ($dup->fetch()) {
            $errors[] =
              'Client Code "' . $clientCode . '" already exists.';
          } else {
            db()
              ->prepare(
                "INSERT INTO clients (client_name, client_code, created_by) VALUES (?,?,?)"
              )
              ->execute([
                $clientName,
                $clientCode,
                $_SESSION["admin_id"],
              ]);
            flash(
              "success",
              "Client <strong>" .
                $clientCode .
                "</strong> created."
            );
            redirect(ADMIN_URL . "/pages/clients.php");
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
      <button ype="submit" form="clientForm" class="btn btn-sm btn-primary shadow-sm">
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
          <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
            <svg class="h-4 w-4 text-gray-900/80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21V6.75A1.5 1.5 0 015.25 5.25h6A1.5 1.5 0 0112.75 6.75V21M3.75 21h16.5M3.75 21H2.25M20.25 21V10.5a1.5 1.5 0 00-1.5-1.5h-3a1.5 1.5 0 00-1.5 1.5V21m3-15h.008v.008H18v-.008zm0 3h.008v.008H18v-.008zm0 3h.008v.008H18v-.008zM6.75 9h.008v.008H6.75V9zm0 3h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm3-6h.008v.008H9.75V9zm0 3h.008v.008H9.75V12zm0 3h.008v.008H9.75V15z" />
            </svg>
          </div>
          <span class="font-head text-[15px] font-bold">Client Details</span>
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
              value="<?= e(
                        $editClient["client_name"] ?? ($_POST["client_name"] ?? "")
                      ) ?>"
              required
              autofocus />
            <p class="text-xs text-base-content/50">
              Enter the official client/company name.
            </p>
          </fieldset>

          <!-- Client Code -->
          <!-- <fieldset class="fieldset">
            <legend class="fieldset-legend">
              Client Code
              <span class="text-error">*</span>
            </legend>

            <input
              type="text"
              name="client_code"
              id="clientCodeInput"
              class="input w-full uppercase tracking-wide"
              placeholder="e.g. ADSK"
              maxlength="6"
              value="<?= e(
                        $editClient["client_code"] ?? ($_POST["client_code"] ?? "")
                      ) ?>"
              oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'')"
              required />
            <p class="label">
              2–6 characters, letters and numbers only.
            </p>
          </fieldset> -->

        </div>
        <p class="text-[11.5px] text-gray-400 mt-2.5">Client codes must be <strong class="text-gray-900/55">2–6 uppercase letters/digits</strong> and unique across all clients.</p>
      </div>

      <!-- ══ RIGHT RAIL ══ -->
      <div class="space-y-5 min-w-0">

        <!-- Stats card -->
        <div class="rounded-2xl p-6 border border-gray-200" style="background-color:#ffffff !important">
          <div class="flex items-center gap-2.5 mb-1">
            <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
              <svg class="h-4 w-4 text-gray-900/80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
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

        <!-- Code guidelines card -->
        <div class="rounded-2xl p-6 border border-gray-200" style="background-color:#ffffff !important">
          <div class="flex items-center gap-2.5 mb-4">
            <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
              <svg class="h-4 w-4 text-gray-900/80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
              </svg>
            </div>
            <span class="font-head text-[15px] font-bold">Code guidelines</span>
          </div>
          <div class="space-y-3">
            <div class="flex items-start gap-2.5">
              <span class="mt-0.5 flex-shrink-0 w-1.5 h-1.5 rounded-full bg-blue-600"></span>
              <p class="text-[12.5px] text-gray-900/55">2–6 characters, letters and digits only</p>
            </div>
            <div class="flex items-start gap-2.5">
              <span class="mt-0.5 flex-shrink-0 w-1.5 h-1.5 rounded-full bg-blue-600"></span>
              <p class="text-[12.5px] text-gray-900/55">Auto-uppercased as you type</p>
            </div>
            <div class="flex items-start gap-2.5">
              <span class="mt-0.5 flex-shrink-0 w-1.5 h-1.5 rounded-full bg-blue-600"></span>
              <p class="text-[12.5px] text-gray-900/55">Must be unique across all clients</p>
            </div>
          </div>
        </div>

      </div>
    </div>
  </form>

  <!-- ══ CLIENTS TABLE ══ -->
  <div class="rounded-2xl overflow-hidden mt-5 border border-gray-200" style="background-color:#ffffff !important">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
      <span class="font-head text-[15px] font-bold">All Clients</span>
      <span class="text-[12px] text-gray-500"><?= count(
                                                $clients
                                              ) ?> total</span>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-[13px]">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left px-6 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Client Name</th>
            <th class="text-left px-6 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Client Code</th>
            <th class="text-left px-6 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Created By</th>
            <th class="text-left px-6 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Created</th>
            <th class="text-left px-6 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
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
                <td class="px-6 py-3.5 align-middle">
                  <code class="bg-blue-50 text-blue-600 rounded-md px-2.5 py-1 text-[12.5px] font-semibold tracking-wide">
                    <?= e($c["client_code"]) ?>
                  </code>
                </td>
                <td class="px-6 py-3.5 align-middle text-gray-900/55 text-[13px]"><?= e(
                                                                                    $c["created_by_name"] ?? "—"
                                                                                  ) ?></td>
                <td class="px-6 py-3.5 align-middle text-[12px] text-gray-900/45">
                  <?= date("d M Y", strtotime($c["created_at"])) ?>
                </td>
                <td class="px-6 py-3.5 align-middle">
                  <div class="flex items-center gap-1.5">
                    <a href="<?= ADMIN_URL ?>/pages/clients.php?edit=<?= $c["id"] ?>" title="Edit"
                      class="flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 text-gray-500 hover:text-gray-900 hover:bg-gray-100 transition-colors">
                      <svg class="h-[15px] w-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                      </svg>
                    </a>
                    <form method="POST" class="inline"
                      onsubmit="return confirm('Delete client <?= e(
                                                                addslashes($c["client_name"])
                                                              ) ?>?')">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="client_id" value="<?= $c["id"] ?>">
                      <button type="submit" title="Delete"
                        class="flex items-center justify-center w-8 h-8 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition-colors">
                        <svg class="h-[15px] w-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                      </button>
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

  <!-- ═══ CLIENTS TABLE (Responsive: table on desktop, cards on mobile) ═══ -->
  <div class="card bg-base-100 border border-base-300 shadow-sm mt-5 overflow-hidden">

    <!-- Header -->
    <div class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-base-300">
      <h2 class="font-head text-[15px] font-bold text-base-content">All Clients</h2>
      <span class="badge badge-ghost badge-sm font-medium">
        <?= count($clients) ?> total
      </span>
    </div>

    <?php if (empty($clients)): ?>
      <!-- Empty State -->
      <div class="flex flex-col items-center justify-center gap-3 py-16 px-6 text-center">
        <div class="flex size-14 items-center justify-center rounded-full bg-base-200">
          <svg class="h-7 w-7 text-base-content/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21V6.75A1.5 1.5 0 015.25 5.25h6A1.5 1.5 0 0112.75 6.75V21M3.75 21h16.5M3.75 21H2.25M20.25 21V10.5a1.5 1.5 0 00-1.5-1.5h-3a1.5 1.5 0 00-1.5 1.5V21" />
          </svg>
        </div>
        <p class="text-sm text-base-content/50">No clients yet. Create your first client &rarr;</p>
      </div>

    <?php else: ?>

      <!-- ── DESKTOP / TABLET: table (hidden below md) ── -->
      <div class="hidden md:block overflow-x-auto">
        <table class="table">
          <thead>
            <tr class="bg-base-200/60">
              <th class="text-[11px] font-semibold text-base-content/50 uppercase tracking-wide">Client Name</th>
              <th class="text-[11px] font-semibold text-base-content/50 uppercase tracking-wide">Client Code</th>
              <th class="text-[11px] font-semibold text-base-content/50 uppercase tracking-wide">Created By</th>
              <th class="text-[11px] font-semibold text-base-content/50 uppercase tracking-wide">Created</th>
              <th class="text-[11px] font-semibold text-base-content/50 uppercase tracking-wide text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($clients as $c): ?>
              <tr class="hover:bg-base-200/40 transition-colors">
                <td class="align-middle">
                  <div class="flex items-center gap-3">
                    <div class="avatar placeholder shrink-0">
                      <div class="bg-primary/10 text-primary rounded-full w-8">
                        <span class="text-[12px] font-bold">
                          <?= strtoupper(substr($c["client_name"], 0, 1)) ?>
                        </span>
                      </div>
                    </div>
                    <strong class="text-[13px] text-base-content font-semibold">
                      <?= e($c["client_name"]) ?>
                    </strong>
                  </div>
                </td>
                <td class="align-middle">
                  <code class="badge badge-info badge-soft font-semibold tracking-wide text-[12px]">
                    <?= e($c["client_code"]) ?>
                  </code>
                </td>
                <td class="align-middle text-[13px] text-base-content/55">
                  <?= e($c["created_by_name"] ?? "—") ?>
                </td>
                <td class="align-middle text-[12px] text-base-content/45 whitespace-nowrap">
                  <?= date("d M Y", strtotime($c["created_at"])) ?>
                </td>
                <td class="align-middle">
                  <div class="flex items-center justify-end gap-1.5">
                    <a href="<?= ADMIN_URL ?>/pages/clients.php?edit=<?= $c["id"] ?>"
                      title="Edit"
                      class="btn btn-square btn-sm btn-ghost border border-base-300 text-base-content/60 hover:text-base-content">
                      <svg class="h-[15px] w-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                      </svg>
                    </a>
                    <form method="POST" class="inline"
                      onsubmit="return confirm('Delete client <?= e(addslashes($c["client_name"])) ?>?')">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="client_id" value="<?= $c["id"] ?>">
                      <button type="submit" title="Delete"
                        class="btn btn-square btn-sm btn-ghost border border-error/30 text-error hover:bg-error/10">
                        <svg class="h-[15px] w-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- ── MOBILE: stacked cards (hidden md and up) ── -->
      <div class="md:hidden divide-y divide-base-300">
        <?php foreach ($clients as $c): ?>
          <div class="p-4 flex flex-col gap-3">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3 min-w-0">
                <div class="avatar placeholder shrink-0">
                  <div class="bg-primary/10 text-primary rounded-full w-9">
                    <span class="text-[13px] font-bold">
                      <?= strtoupper(substr($c["client_name"], 0, 1)) ?>
                    </span>
                  </div>
                </div>
                <div class="min-w-0">
                  <strong class="block text-[14px] text-base-content font-semibold truncate">
                    <?= e($c["client_name"]) ?>
                  </strong>
                  <code class="badge badge-info badge-soft badge-sm font-semibold tracking-wide text-[11px] mt-1">
                    <?= e($c["client_code"]) ?>
                  </code>
                </div>
              </div>

              <div class="flex items-center gap-1.5 shrink-0">
                <a href="<?= ADMIN_URL ?>/pages/clients.php?edit=<?= $c["id"] ?>"
                  title="Edit"
                  class="btn btn-square btn-sm btn-ghost border border-base-300 text-base-content/60">
                  <svg class="h-[14px] w-[14px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                  </svg>
                </a>
                <form method="POST" class="inline"
                  onsubmit="return confirm('Delete client <?= e(addslashes($c["client_name"])) ?>?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="client_id" value="<?= $c["id"] ?>">
                  <button type="submit" title="Delete"
                    class="btn btn-square btn-sm btn-ghost border border-error/30 text-error">
                    <svg class="h-[14px] w-[14px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                  </button>
                </form>
              </div>
            </div>

            <div class="flex items-center justify-between text-[12px] text-base-content/50 pl-12">
              <span>By <?= e($c["created_by_name"] ?? "—") ?></span>
              <span><?= date("d M Y", strtotime($c["created_at"])) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>

  </div>

</div>

<?php include dirname(__DIR__) . "/includes/footer.php"; ?>