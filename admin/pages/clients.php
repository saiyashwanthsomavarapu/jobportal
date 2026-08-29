<?php
require_once dirname(__DIR__) . "/auth.php";
require_once dirname(__DIR__) . "/utils/classes.php";


$pageTitle = "Clients";
$breadcrumbs = [["Dashboard", ADMIN_URL . "/index.php"], ["Clients", null]];

$errors = [];

// ── Handle POST actions ───────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  csrf_verify();

  $action = $_POST["action"] ?? "";
  $clientId = (int) ($_POST["client_id"] ?? 0);
  $clientName = trim($_POST["client_name"] ?? "");


  if ($action === "save") {
    if (!$clientName) {
      $errors[] = "Client Name is required.";
    }
    if ($clientName && mb_strlen($clientName) > 150) {
      $errors[] = "Client name must be 150 characters or fewer.";
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
            db()->prepare("INSERT INTO clients (client_name, created_by) VALUES (?,?)")
              ->execute([strtolower($clientName), $_SESSION['admin_id']]);
            flash('success', 'Client <strong>' . e($clientName) . '</strong> created.');
            redirect(ADMIN_URL . '/pages/clients.php');
          }
        }
      } catch (Exception $e) {
        error_log("[clients] save failed: " . $e->getMessage());
        $errors[] = "Something went wrong while saving. Please try again.";
      }
    }
  } elseif ($action === "delete" && $clientId > 0) {
    try {
      // Block deletion while job postings still reference this client
      $jobs = db()->prepare("SELECT COUNT(*) FROM jobs WHERE client_id = ?");
      $jobs->execute([$clientId]);
      $jobCount = (int) $jobs->fetchColumn();

      if ($jobCount > 0) {
        flash(
          "error",
          "This client has " . $jobCount . " job posting" . ($jobCount === 1 ? "" : "s") .
            ". Reassign or delete those jobs first."
        );
      } else {
        db()
          ->prepare("DELETE FROM clients WHERE id = ?")
          ->execute([$clientId]);
        flash("success", "Client deleted.");
      }
    } catch (Exception $e) {
      error_log("[clients] delete failed: " . $e->getMessage());
      flash("error", "Could not delete the client. Please try again.");
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
  if (!$editClient) {
    flash("error", "Client not found.");
    redirect(ADMIN_URL . "/pages/clients.php");
  }
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

// ── View state helpers ────────────────────────────────────────

$isPostFailure = $_SERVER["REQUEST_METHOD"] === "POST" && !empty($errors);
$autoOpenClientId = $isPostFailure
  ? (int) ($_POST["client_id"] ?? 0)
  : ($editClient ? (int) $editClient["id"] : 0);

$prefillName = $isPostFailure ? (string) ($_POST["client_name"] ?? "") : (string) ($editClient["client_name"] ?? "");

$totalClients = count($clients);
$monthClients = 0;
$yearClients = 0;
foreach ($clients as $c) {
  $t = strtotime($c["created_at"]);
  if ($t >= strtotime("first day of this month 00:00:00")) {
    $monthClients++;
  }
  if (date("Y", $t) === date("Y")) {
    $yearClients++;
  }
}

$JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

/* Human-friendly relative time, e.g. "3h ago" */
function timeAgoStr(?string $dt): string
{
  if (!$dt) {
    return "";
  }
  $diff = time() - (int) strtotime($dt);
  if ($diff < 60) {
    return "just now";
  }
  if ($diff < 3600) {
    return floor($diff / 60) . "m ago";
  }
  if ($diff < 86400) {
    return floor($diff / 3600) . "h ago";
  }
  if ($diff < 604800) {
    return floor($diff / 86400) . "d ago";
  }
  if ($diff < 2629800) {
    return floor($diff / 604800) . "w ago";
  }
  if ($diff < 31557600) {
    return floor($diff / 2629800) . "mo ago";
  }
  return floor($diff / 31557600) . "y ago";
}

include dirname(__DIR__) . "/includes/header.php";
?>

<div class="min-w-0 space-y-6">

  <!-- ═══════════ PAGE INTRO + PRIMARY ACTION ═══════════ -->
  <section class="flex flex-wrap items-start justify-between gap-4">
    <div class="max-w-xl">
      <h2 class=" text-[21px] font-bold leading-tight tracking-tight text-base-content">
        Clients
      </h2>
      <p class="mt-2 text-[13px] leading-relaxed text-base-content/60">
        The companies you recruit for. Every job posting is linked to a client,
        so keep this list up to date.
      </p>
    </div>
    <div class="flex items-center gap-2">
      <button type="button" onclick="openClientModal()"
        class="<?= PRIMARY_BUTTON_CLASS ?> shadow-pop hover:-translate-y-px hover:opacity-100 hover:shadow-lg">
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Add client
      </button>
    </div>
  </section>

  <!-- ═══════════ CLIENTS LIST ═══════════ -->
  <section class="overflow-hidden rounded-2xl bg-base-100 shadow-card border border-base-300">

    <header class="flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-4">
      <div class="mr-auto flex items-center gap-3">
        <div class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
          <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21V6.75A1.5 1.5 0 015.25 5.25h6A1.5 1.5 0 0112.75 6.75V21M3.75 21h16.5M3.75 21H2.25M20.25 21V10.5a1.5 1.5 0 00-1.5-1.5h-3a1.5 1.5 0 00-1.5 1.5V21m3-15h.008v.008H18v-.008zm0 3h.008v.008H18v-.008zm0 3h.008v.008H18v-.008zM6.75 9h.008v.008H6.75V9zm0 3h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm3-6h.008v.008H9.75V9zm0 3h.008v.008H9.75V12zm0 3h.008v.008H9.75V15z" />
          </svg>
        </div>
        <div>
          <h3 class="text-[15px] font-bold tracking-tight text-base-content">All clients</h3>
          <p id="tableMeta" class="text-[11.5px] text-base-content/50">
            Showing <?= $totalClients ?> of <?= $totalClients ?> client<?= $totalClients === 1 ? "" : "s" ?>
          </p>
        </div>
      </div>

      <label class="input input-sm h-9 w-64 max-w-full items-center gap-2 border-transparent bg-base-200 focus-within:border-primary focus-within:bg-base-100">
        <svg class="size-4 shrink-0 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
        </svg>
        <input id="clientSearch" type="search" placeholder="Search clients…"
          class="grow bg-transparent text-[13px] outline-none placeholder:text-base-content/35">
      </label>
    </header>

    <div class="overflow-x-auto">
      <table class="<?= TABLE_CLASS ?>">
        <thead class="<?= TABLE_HEAD_CLASS ?>">
          <tr>
            <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Client</th>
            <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Added by</th>
            <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Added</th>
            <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Actions</th>
          </tr>
        </thead>
        <tbody id="clientsTbody">

          <?php if (empty($clients)): ?>

            <tr>
              <td colspan="4">
                <div class="py-14 text-center">
                  <div class="mx-auto grid size-16 place-items-center rounded-full border-2 border-dashed border-base-300 bg-base-200/50 text-base-content/40">
                    <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21V6.75A1.5 1.5 0 015.25 5.25h6A1.5 1.5 0 0112.75 6.75V21M3.75 21h16.5M3.75 21H2.25M20.25 21V10.5a1.5 1.5 0 00-1.5-1.5h-3a1.5 1.5 0 00-1.5 1.5V21" />
                    </svg>
                  </div>
                  <h4 class="mt-4  text-[15px] font-bold text-base-content">No clients yet</h4>
                  <p class="mx-auto mt-1 max-w-xs text-[12.5px] leading-relaxed text-base-content/50">
                    Add your first client to start posting jobs for them.
                  </p>
                  <button type="button" onclick="openClientModal()" class="<?= PRIMARY_BUTTON_CLASS ?> mt-4 shadow-pop">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Add client
                  </button>
                </div>
              </td>
            </tr>

          <?php else: ?>

            <tr id="noMatchRow" class="hidden">
              <td colspan="4" class="py-12 text-center text-[13px] text-base-content/50">
                No clients match your search.
              </td>
            </tr>

            <?php foreach ($clients as $c): ?>
              <tr class="border-b border-base-300/60 transition-colors last:border-b-0 hover:bg-base-200/60"
                data-search="<?= e(strtolower($c["client_name"])) ?>">

                <!-- Client -->
                <td class="px-5 py-3.5 align-middle">
                  <div class="flex items-center gap-3">
                    <div class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary/10  text-[13px] font-bold text-primary">
                      <?= strtoupper(substr($c["client_name"], 0, 1)) ?>
                    </div>
                    <div class="min-w-0">
                      <strong class="block truncate text-[13px] font-semibold text-base-content"><?= e($c["client_name"]) ?></strong>
                      <span class="block text-[11.5px] text-base-content/50">Client account</span>
                    </div>
                  </div>
                </td>

                <!-- Added by -->
                <td class="px-5 py-3.5 align-middle">
                  <span class="inline-flex items-center gap-1.5 text-[12.5px] text-base-content/70">
                    <svg class="size-3.5 shrink-0 text-base-content/35" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    <?= e($c["created_by_name"] ?? "—") ?>
                  </span>
                </td>

                <!-- Added -->
                <td class="whitespace-nowrap px-5 py-3.5 align-middle text-[12px] text-base-content/50">
                  <span class="tabular-nums" title="<?= date("d M Y", strtotime($c["created_at"])) ?>"><?= timeAgoStr($c["created_at"]) ?></span>
                </td>

                <!-- Actions -->
                <td class="px-5 py-3.5 align-middle">
                  <div class="flex items-center justify-start gap-1">

                    <div class="tooltip tooltip-left" data-tip="Edit details">
                      <button type="button" aria-label="Edit <?= e($c["client_name"]) ?>"
                        onclick="openEditClientModal(this)"
                        data-id="<?= $c["id"] ?>"
                        data-name="<?= e($c["client_name"]) ?>"
                        class="<?= EDIT_BUTTON ?>">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                        </svg>
                      </button>
                    </div>

                    <div class="tooltip tooltip-left" data-tip="Delete permanently">
                      <button type="button" aria-label="Delete <?= e($c["client_name"]) ?>"
                        onclick="openDeleteModal(<?= (int)$c['id'] ?>, '<?= e(addslashes($c['client_name'])) ?>')"
                        class="<?= DELETE_BUTTON ?>">
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

</div>

<!-- ═══════════ ADD / EDIT CLIENT MODAL ═══════════ -->
<dialog id="clientModal" class="modal">
  <div class="modal-box w-[calc(100%-2rem)] max-w-md rounded-3xl border border-base-300/70 bg-base-100 p-0 shadow-xl">

    <!-- Header -->
    <header class="flex items-center justify-between gap-3 px-5 pb-1 pt-5">
      <div class="min-w-0">
        <h3 id="clientModalTitle" class="truncate text-[16px] font-bold tracking-tight text-base-content">New client</h3>
        <p id="clientModalSub" class="truncate text-[11.5px] text-base-content/50">Add a company you recruit for.</p>
      </div>
      <button type="button" onclick="clientModal.close()"
        class="btn btn-sm btn-circle btn-ghost size-8 min-h-8 shrink-0 text-base-content/50 hover:bg-base-200 hover:text-base-content"
        aria-label="Close">
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </header>

    <?php if (!empty($errors)): ?>
      <div class="px-5 pt-4">
        <div role="alert" class="alert alert-error alert-soft py-2.5 text-[12.5px]">
          <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div><?= implode("<br>", array_map("e", $errors)) ?></div>
        </div>
      </div>
    <?php endif; ?>

    <form method="POST" id="clientForm" novalidate>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="client_id" id="formClientId" value="<?= $autoOpenClientId ?>">
      <?= csrf_field() ?>

      <div class="space-y-4 px-5 py-5">
        <fieldset class="min-w-0">
          <legend class="mb-1.5 block text-xs font-semibold text-base-content/70">
            Client name <span class="text-error">*</span>
          </legend>
          <input type="text" id="cName" name="client_name" required
            placeholder="e.g. Autodesk"
            pattern="[A-Za-z0-9 .&\-]+"
            value="<?= e($prefillName) ?>"
            oninput="this.value = this.value.replace(/[^A-Za-z0-9 .&\-]/g, '')"
            class="<?= INPUT_CLASS ?> validator" />
          <p class="mt-1.5 text-[11px] leading-snug text-base-content/45">
            Enter the official company name — letters, numbers, spaces, dots, &amp; and hyphens only.
          </p>
        </fieldset>
      </div>
    </form>

    <!-- Actions -->
    <footer class="modal-action m-0 flex flex-col-reverse gap-2 border-t border-base-200/80 bg-base-200/40 px-5 py-4 sm:flex-row sm:justify-end">
      <button type="button" onclick="clientModal.close()"
        class="btn btn-ghost h-10 min-h-10 w-full rounded-full px-5 text-sm font-semibold text-base-content/70 hover:bg-base-200 hover:text-base-content sm:w-auto">
        Cancel
      </button>
      <button type="submit" form="clientForm"
        class="btn btn-primary h-10 min-h-10 w-full rounded-full px-6 text-sm font-semibold shadow-pop sm:w-auto">
        <span id="clientSubmitLabel">Create client</span>
      </button>
    </footer>
  </div>

  <form method="dialog" class="modal-backdrop bg-black/40 backdrop-blur-sm">
    <button type="submit">close</button>
  </form>
</dialog>

<!-- ═══════════ DELETE CONFIRMATION MODAL ═══════════ -->
<dialog id="deleteClientModal" class="modal">
  <div class="modal-box w-[calc(100%-2rem)] max-w-md rounded-3xl border border-base-300/70 bg-base-100 p-0 shadow-xl">

    <div class="flex items-center justify-between gap-3 px-5 pb-1 pt-5">
      <div class="min-w-0">
        <h3 class=" text-[16px] font-bold tracking-tight text-base-content">Delete client?</h3>
        <p class="mt-0.5 text-[11.5px] text-base-content/50">This action is permanent.</p>
      </div>
      <button type="button" onclick="deleteClientModal.close()"
        class="btn btn-sm btn-circle btn-ghost size-8 min-h-8 shrink-0 text-base-content/50 hover:bg-base-200 hover:text-base-content"
        aria-label="Close">
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <div class="px-5 py-5">
      <div class="rounded-2xl border border-error/15 bg-error/5 p-4">
        <p class="text-[13px] leading-relaxed text-base-content">
          You're about to delete <strong id="deleteClientName" class="text-error"></strong>.
        </p>
        <ul class="mt-2.5 space-y-1.5 text-[12px] leading-relaxed text-base-content/70">
          <li class="flex items-start gap-2">
            <span class="mt-[7px] size-1 shrink-0 rounded-full bg-error/60" aria-hidden="true"></span>
            The client is removed from the list immediately.
          </li>
          <li class="flex items-start gap-2">
            <span class="mt-[7px] size-1 shrink-0 rounded-full bg-error/60" aria-hidden="true"></span>
            This cannot be undone.
          </li>
        </ul>
      </div>
    </div>

    <div class="modal-action m-0 flex flex-col-reverse gap-2 border-t border-base-200/80 bg-base-200/40 px-5 py-4 sm:flex-row sm:justify-end">
      <button type="button" onclick="deleteClientModal.close()"
        class="btn h-10 min-h-10 w-full rounded-full border border-base-300 bg-base-100 px-5 text-sm font-semibold text-base-content/80 hover:bg-base-200 hover:text-base-content sm:w-auto">
        Cancel
      </button>
      <form method="POST" id="confirmDeleteClientForm" class="w-full sm:w-auto">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="client_id" id="deleteClientId">
        <?= csrf_field() ?>
        <button type="submit"
          class="btn btn-error h-10 min-h-10 w-full rounded-full px-6 text-sm font-semibold sm:w-auto">
          Delete permanently
        </button>
      </form>
    </div>
  </div>

  <form method="dialog" class="modal-backdrop bg-black/40 backdrop-blur-sm">
    <button type="submit">close</button>
  </form>
</dialog>

<script>
  /* ── Add / Edit client modal ─────────────────────────────── */
  function openClientModal() {
    document.getElementById('clientForm').reset();
    document.getElementById('formClientId').value = '0';
    document.getElementById('clientModalTitle').textContent = 'New client';
    document.getElementById('clientModalSub').textContent = 'Add a company you recruit for.';
    document.getElementById('clientSubmitLabel').textContent = 'Create client';
    clientModal.showModal();
    setTimeout(function() {
      document.getElementById('cName').focus();
    }, 80);
  }

  function openEditClientModal(btn) {
    var d = btn.dataset;
    var form = document.getElementById('clientForm');
    form.reset();

    document.getElementById('formClientId').value = d.id;
    document.getElementById('cName').value = d.name || '';

    document.getElementById('clientModalTitle').textContent = 'Edit client';
    document.getElementById('clientModalSub').textContent = d.name || '';
    document.getElementById('clientSubmitLabel').textContent = 'Save changes';

    clientModal.showModal();
    setTimeout(function() {
      document.getElementById('cName').focus();
    }, 80);
  }

  /* ── Delete modal ────────────────────────────────────────── */
  function openDeleteModal(clientId, clientName) {
    document.getElementById('deleteClientId').value = clientId;
    document.getElementById('deleteClientName').textContent = clientName;
    document.getElementById('deleteClientModal').showModal();
  }

  /* ── Table search ────────────────────────────────────────── */
  function bindTableSearch() {
    var searchEl = document.getElementById('clientSearch');
    var rows = Array.prototype.slice.call(document.querySelectorAll('#clientsTbody tr[data-search]'));
    var meta = document.getElementById('tableMeta');
    if (!rows.length) return;

    function apply() {
      var q = searchEl && searchEl.value ? searchEl.value.trim().toLowerCase() : '';
      var shown = 0;
      rows.forEach(function(tr) {
        var ok = !q || tr.dataset.search.indexOf(q) !== -1;
        tr.classList.toggle('hidden', !ok);
        if (ok) shown++;
      });
      var noMatch = document.getElementById('noMatchRow');
      if (noMatch) noMatch.classList.toggle('hidden', shown > 0);
      if (meta) meta.textContent = 'Showing ' + shown + ' of ' + rows.length + ' client' + (rows.length === 1 ? '' : 's');
    }

    if (searchEl) searchEl.addEventListener('input', apply);
    apply();
  }

  /* ── Init ────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function() {
    bindTableSearch();

    <?php if ($isPostFailure || $editClient): ?>
      /* Reopen the modal after a failed save, or when arriving via ?edit= link.
         Field values were already rendered server-side. */
      document.getElementById('formClientId').value = <?= $autoOpenClientId ?>;
      document.getElementById('clientModalTitle').textContent = <?= $autoOpenClientId > 0 ? "'Edit client'" : "'New client'" ?>;
      document.getElementById('clientModalSub').textContent = <?= $autoOpenClientId > 0
                                                                ? json_encode($prefillName, $JSON_FLAGS)
                                                                : "'Add a company you recruit for.'" ?>;
      document.getElementById('clientSubmitLabel').textContent = <?= $autoOpenClientId > 0 ? "'Save changes'" : "'Create client'" ?>;
      document.getElementById('clientModal').showModal();
    <?php endif; ?>
  });
</script>
<?php include dirname(__DIR__) . "/includes/footer.php"; ?>