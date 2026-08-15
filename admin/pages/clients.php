<?php
require_once dirname(__DIR__) . '/auth.php';

$pageTitle   = 'Clients';
$breadcrumbs = [['Dashboard', ADMIN_URL.'/index.php'], ['Clients', null]];

$errors  = [];
$success = '';

// ── Handle POST actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action']      ?? '';
    $clientId    = (int)($_POST['client_id'] ?? 0);
    $clientName  = trim($_POST['client_name'] ?? '');
    $clientCode  = strtoupper(trim($_POST['client_code'] ?? ''));

    // Validate code: 2-6 uppercase alphanumeric characters
    $clientCode = preg_replace('/[^A-Z0-9]/', '', $clientCode);

    if ($action === 'save') {
        if (!$clientName)                          $errors[] = 'Client Name is required.';
        if (!$clientCode)                          $errors[] = 'Client Code is required.';
        if (strlen($clientCode) < 2 || strlen($clientCode) > 6)
                                                   $errors[] = 'Client Code must be 2–6 characters.';

        if (empty($errors)) {
            try {
                if ($clientId > 0) {
                    // Check duplicate code (excluding self)
                    $dup = db()->prepare("SELECT id FROM clients WHERE client_code = ? AND id != ?");
                    $dup->execute([$clientCode, $clientId]);
                    if ($dup->fetch()) { $errors[] = 'Client Code "'.$clientCode.'" already exists.'; }
                    else {
                        db()->prepare("UPDATE clients SET client_name=?, client_code=?, updated_at=NOW() WHERE id=?")
                           ->execute([$clientName, $clientCode, $clientId]);
                        flash('success', 'Client updated.');
                        redirect(ADMIN_URL.'/pages/clients.php');
                    }
                } else {
                    // Check duplicate
                    $dup = db()->prepare("SELECT id FROM clients WHERE client_code = ?");
                    $dup->execute([$clientCode]);
                    if ($dup->fetch()) { $errors[] = 'Client Code "'.$clientCode.'" already exists.'; }
                    else {
                        db()->prepare("INSERT INTO clients (client_name, client_code, created_by) VALUES (?,?,?)")
                           ->execute([$clientName, $clientCode, $_SESSION['admin_id']]);
                        flash('success', 'Client <strong>'.$clientCode.'</strong> created.');
                        redirect(ADMIN_URL.'/pages/clients.php');
                    }
                }
            } catch (Exception $e) {
                $errors[] = 'Database error: '.$e->getMessage();
            }
        }
    } elseif ($action === 'delete' && $clientId > 0) {
        try {
            db()->prepare("DELETE FROM clients WHERE id = ?")->execute([$clientId]);
            flash('success', 'Client deleted.');
        } catch (Exception $e) {
            flash('error', 'Cannot delete: '.$e->getMessage());
        }
        redirect(ADMIN_URL.'/pages/clients.php');
    }
}

// ── Load client for editing ───────────────────────────────────
$editClient = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editClient = $stmt->fetch();
}

// ── Load all clients ──────────────────────────────────────────
try {
    $clients = db()->query(
        "SELECT c.*, a.name AS created_by_name
         FROM clients c LEFT JOIN admin_users a ON a.id = c.created_by
         ORDER BY c.client_name ASC"
    )->fetchAll();
} catch (Exception $e) {
    $clients = [];
}

include dirname(__DIR__) . '/includes/header.php';
?>

<div style="display:grid;grid-template-columns:340px 1fr;gap:22px;align-items:start">

  <!-- ── CREATE / EDIT FORM ── -->
  <div class="card">
    <div class="section-title"><?= $editClient ? '✏ Edit Client' : '＋ New Client' ?></div>

    <?php if (!empty($errors)): ?>
    <div class="flash flash-error" style="margin-bottom:16px">
      <div>✕ <?= implode('<br>', array_map('e', $errors)) ?></div>
    </div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="action"    value="save">
      <input type="hidden" name="client_id" value="<?= $editClient ? $editClient['id'] : 0 ?>">

      <div class="field" style="margin-bottom:14px">
        <label>Client Name <span class="req">*</span></label>
        <input type="text" name="client_name" class="ctrl"
               placeholder="e.g. Autodesk"
               value="<?= e($editClient['client_name'] ?? ($_POST['client_name'] ?? '')) ?>"
               required autofocus>
      </div>

      <div class="field" style="margin-bottom:18px">
        <label>Client Code <span class="req">*</span></label>
        <input type="text" name="client_code" id="clientCodeInput" class="ctrl"
               placeholder="e.g. ADSK"
               maxlength="6"
               value="<?= e($editClient['client_code'] ?? ($_POST['client_code'] ?? '')) ?>"
               oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'')"
               required>
        <span class="field-hint">2–6 uppercase letters/digits. Auto-uppercased.</span>
      </div>

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary" style="flex:1">
          <?= $editClient ? '💾 Update Client' : '＋ Create Client' ?>
        </button>
        <?php if ($editClient): ?>
        <a href="<?= ADMIN_URL ?>/pages/clients.php" class="btn btn-ghost">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- ── CLIENTS TABLE ── -->
  <div class="card" style="padding:0">
    <div style="padding:16px 20px 12px;border-bottom:1px solid var(--border);
                display:flex;align-items:center;justify-content:space-between">
      <span class="section-title" style="margin:0">All Clients</span>
      <span style="font-size:12px;color:var(--muted)"><?= count($clients) ?> total</span>
    </div>
    <div class="table-wrap" style="border:none;border-radius:0">
      <table>
        <thead>
          <tr>
            <th>Client Name</th>
            <th>Client Code</th>
            <th>Created By</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($clients)): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:30px">
            No clients yet. Create your first client →
          </td></tr>
        <?php else: ?>
          <?php foreach ($clients as $c): ?>
          <tr>
            <td><strong><?= e($c['client_name']) ?></strong></td>
            <td>
              <code style="background:var(--card2);border-radius:5px;padding:3px 9px;
                           font-size:13px;color:var(--accent2);letter-spacing:.5px">
                <?= e($c['client_code']) ?>
              </code>
            </td>
            <td class="td-muted"><?= e($c['created_by_name'] ?? '—') ?></td>
            <td class="td-muted" style="font-size:12px">
              <?= date('d M Y', strtotime($c['created_at'])) ?>
            </td>
            <td>
              <div style="display:flex;gap:6px">
                <a href="<?= ADMIN_URL ?>/pages/clients.php?edit=<?= $c['id'] ?>"
                   class="btn btn-ghost btn-sm">✏ Edit</a>
                <form method="POST" style="display:inline"
                      onsubmit="return confirm('Delete client <?= e(addslashes($c['client_name'])) ?>?')">
                  <input type="hidden" name="action"    value="delete">
                  <input type="hidden" name="client_id" value="<?= $c['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">🗑</button>
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

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
