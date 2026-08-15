<?php
// admin/pages/admins.php
require_once dirname(__DIR__) . '/auth.php';

// ── Only superadmin can manage admin users ────────────────────
if ($currentAdmin['role'] !== 'superadmin') {
    flash('error', 'Access denied. Superadmin only.');
    redirect(ADMIN_URL . '/index.php');
}

$pageTitle   = 'Admin Users';
$breadcrumbs = [
    ['Dashboard', ADMIN_URL . '/index.php'],
    ['Admin Users', null],
];

$errors  = [];

// ─────────────────────────────────────────────────────────────
// Handle POST actions
// ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action   = $_POST['action']    ?? '';
    $targetId = (int)($_POST['user_id'] ?? 0);

    // ── CREATE / UPDATE ───────────────────────────────────────
    if ($action === 'save') {

        $name     = trim($_POST['name']     ?? '');
        $email    = strtolower(trim($_POST['email']    ?? ''));
        $role     = trim($_POST['role']     ?? 'admin');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $password = trim($_POST['password'] ?? '');
        $passConf = trim($_POST['password_confirm'] ?? '');

        // Validate
        if (!$name)  $errors[] = 'Full Name is required.';
        if (!$email) $errors[] = 'Email is required.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
        elseif (substr($email, -strlen('@acceloninc.com')) !== '@acceloninc.com') $errors[] = 'Only @acceloninc.com email addresses are allowed.';
        if (!in_array($role, ['superadmin', 'admin', 'editor'])) $errors[] = 'Invalid role.';

        // Strong password validator (shared logic)
        $pwErrors = function($pw) {
            $e = [];
            if (strlen($pw) < 8)                    $e[] = 'at least 8 characters';
            if (!preg_match('/[A-Z]/', $pw))         $e[] = 'one uppercase letter';
            if (!preg_match('/[0-9]/', $pw))         $e[] = 'one number';
            if (!preg_match('/[^a-zA-Z0-9]/', $pw)) $e[] = 'one special character';
            if (preg_match('/(012|123|234|345|456|567|678|789|890|abc|bcd|cde|def|efg|fgh|ghi|hij|ijk|jkl|klm|lmn|mno|nop|opq|pqr|qrs|rst|stu|tuv|uvw|vwx|wxy|xyz)/i', $pw))
                $e[] = 'no sequential series (e.g. 123, abc)';
            return $e;
        };

        if ($targetId === 0) {
            // Create: password required
            if (!$password) {
                $errors[] = 'Password is required for new users.';
            } else {
                $pe = $pwErrors($password);
                if ($pe) $errors[] = 'Password must contain: ' . implode(', ', $pe) . '.';
                elseif ($password !== $passConf) $errors[] = 'Passwords do not match.';
            }
        } else {
            // Update: password optional (only if provided)
            if ($password !== '') {
                $pe = $pwErrors($password);
                if ($pe) $errors[] = 'Password must contain: ' . implode(', ', $pe) . '.';
                elseif ($password !== $passConf) $errors[] = 'Passwords do not match.';
            }
        }

        if (empty($errors)) {
            try {
                $pdo = db();

                // Check email uniqueness
                $dupCheck = $pdo->prepare(
                    "SELECT id FROM admin_users WHERE email = ? AND id != ?"
                );
                $dupCheck->execute([$email, $targetId ?: 0]);
                if ($dupCheck->fetch()) {
                    $errors[] = 'Email address is already in use by another admin.';
                } else {

                    if ($targetId === 0) {
                        // INSERT new admin
                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $pdo->prepare("
                            INSERT INTO admin_users (name, email, password, role, is_active)
                            VALUES (?, ?, ?, ?, ?)
                        ")->execute([$name, $email, $hash, $role, $isActive]);
                        logActivity('create_admin', 'admin_user', (int)$pdo->lastInsertId(), $email);
                        flash('success', 'Admin user <strong>'.e($name).'</strong> created.');

                    } else {
                        // Prevent demoting yourself
                        if ($targetId === (int)$_SESSION['admin_id'] && $role !== 'superadmin') {
                            $errors[] = 'You cannot change your own role.';
                        } else {
                            if ($password !== '') {
                                // Update with new password
                                $hash = password_hash($password, PASSWORD_BCRYPT);
                                $pdo->prepare("
                                    UPDATE admin_users
                                    SET name=?, email=?, password=?, role=?, is_active=?
                                    WHERE id=?
                                ")->execute([$name, $email, $hash, $role, $isActive, $targetId]);
                            } else {
                                // Update without changing password
                                $pdo->prepare("
                                    UPDATE admin_users
                                    SET name=?, email=?, role=?, is_active=?
                                    WHERE id=?
                                ")->execute([$name, $email, $role, $isActive, $targetId]);
                            }
                            logActivity('update_admin', 'admin_user', $targetId, $email);
                            flash('success', 'Admin user <strong>'.e($name).'</strong> updated.');
                        }
                    }

                    if (empty($errors)) {
                        redirect(ADMIN_URL . '/pages/admins.php');
                    }
                }

            } catch (Exception $ex) {
                $errors[] = 'Database error: ' . $ex->getMessage();
            }
        }
    }

    // ── TOGGLE ACTIVE ─────────────────────────────────────────
    elseif ($action === 'toggle_active' && $targetId > 0) {
        if ($targetId === (int)$_SESSION['admin_id']) {
            flash('error', 'You cannot deactivate your own account.');
        } else {
            try {
                $curr = db()->prepare("SELECT is_active, name FROM admin_users WHERE id=?");
                $curr->execute([$targetId]);
                $row = $curr->fetch();
                if ($row) {
                    $newState = $row['is_active'] ? 0 : 1;
                    db()->prepare("UPDATE admin_users SET is_active=? WHERE id=?")
                       ->execute([$newState, $targetId]);
                    $label = $newState ? 'activated' : 'deactivated';
                    logActivity($label.'_admin', 'admin_user', $targetId);
                    flash('success', 'User <strong>'.e($row['name']).'</strong> '.$label.'.');
                }
            } catch (Exception $ex) {
                flash('error', 'Error: ' . $ex->getMessage());
            }
        }
        redirect(ADMIN_URL . '/pages/admins.php');
    }

    // ── DELETE ────────────────────────────────────────────────
    elseif ($action === 'delete' && $targetId > 0) {
        if ($targetId === (int)$_SESSION['admin_id']) {
            flash('error', 'You cannot delete your own account.');
        } else {
            try {
                $row = db()->prepare("SELECT name FROM admin_users WHERE id=?");
                $row->execute([$targetId]);
                $row = $row->fetch();
                db()->prepare("DELETE FROM admin_users WHERE id=?")->execute([$targetId]);
                logActivity('delete_admin', 'admin_user', $targetId, $row['name'] ?? '');
                flash('success', 'Admin user deleted.');
            } catch (Exception $ex) {
                flash('error', 'Cannot delete: ' . $ex->getMessage());
            }
        }
        redirect(ADMIN_URL . '/pages/admins.php');
    }

    // ── RESET PASSWORD (quick reset to a temp password) ───────
    elseif ($action === 'reset_password' && $targetId > 0) {
        $newPass = trim($_POST['new_password'] ?? '');
        $confirm = trim($_POST['confirm_password'] ?? '');
        $resetPwErrors = [];
        if (!$newPass) {
            $resetPwErrors[] = 'Password is required.';
        } else {
            if (strlen($newPass) < 8)                    $resetPwErrors[] = 'at least 8 characters';
            if (!preg_match('/[A-Z]/', $newPass))         $resetPwErrors[] = 'one uppercase letter';
            if (!preg_match('/[0-9]/', $newPass))         $resetPwErrors[] = 'one number';
            if (!preg_match('/[^a-zA-Z0-9]/', $newPass)) $resetPwErrors[] = 'one special character';
            if (preg_match('/(012|123|234|345|456|567|678|789|890|abc|bcd|cde|def|efg|fgh|ghi|hij|ijk|jkl|klm|lmn|mno|nop|opq|pqr|qrs|rst|stu|tuv|uvw|vwx|wxy|xyz)/i', $newPass))
                $resetPwErrors[] = 'no sequential series (e.g. 123, abc)';
        }
        if (!empty($resetPwErrors) && count($resetPwErrors) === 1 && $resetPwErrors[0] === 'Password is required.') {
            flash('error', 'Password is required.');
        } elseif (!empty($resetPwErrors)) {
            flash('error', 'Password must contain: ' . implode(', ', $resetPwErrors) . '.');
        } elseif ($newPass !== $confirm) {
            flash('error', 'Passwords do not match.');
        } else {
            try {
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                db()->prepare("UPDATE admin_users SET password=? WHERE id=?")
                   ->execute([$hash, $targetId]);
                logActivity('reset_password', 'admin_user', $targetId);
                flash('success', 'Password reset successfully.');
            } catch (Exception $ex) {
                flash('error', 'Error: ' . $ex->getMessage());
            }
        }
        redirect(ADMIN_URL . '/pages/admins.php');
    }
}

// ─────────────────────────────────────────────────────────────
// Load edit target (if ?edit= param)
// ─────────────────────────────────────────────────────────────
$editUser = null;
$editId   = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    $stmt = db()->prepare("SELECT * FROM admin_users WHERE id = ?");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch();
    if (!$editUser) {
        flash('error', 'User not found.');
        redirect(ADMIN_URL . '/pages/admins.php');
    }
}

// ─────────────────────────────────────────────────────────────
// Load password reset target
// ─────────────────────────────────────────────────────────────
$resetUser = null;
$resetId   = (int)($_GET['reset'] ?? 0);
if ($resetId > 0) {
    $stmt = db()->prepare("SELECT id, name, email FROM admin_users WHERE id = ?");
    $stmt->execute([$resetId]);
    $resetUser = $stmt->fetch();
}

// ─────────────────────────────────────────────────────────────
// Load all admin users
// ─────────────────────────────────────────────────────────────
try {
    $admins = db()->query(
        "SELECT * FROM admin_users ORDER BY role ASC, name ASC"
    )->fetchAll();
} catch (Exception $ex) {
    $admins = [];
}

// Role badge helper
function roleBadge(string $role): string {
    $map = [
        'superadmin' => ['color:#7c4df0;background:rgba(124,77,240,.1);border:1px solid rgba(124,77,240,.2)', '★ Superadmin'],
        'admin'      => ['color:#3b7ff5;background:rgba(59,127,245,.1);border:1px solid rgba(59,127,245,.2)', '⬡ Admin'],
        'editor'     => ['color:#1fad72;background:rgba(31,173,114,.1);border:1px solid rgba(31,173,114,.2)', '✎ Editor'],
    ];
    [$style, $label] = $map[$role] ?? ['color:#9aa0bb;background:#f0f2f7;border:1px solid #e2e6f0', ucfirst($role)];
    return '<span style="'.$style.';border-radius:20px;font-size:11px;font-weight:600;padding:3px 10px;display:inline-flex;align-items:center;gap:4px">'.e($label).'</span>';
}

include dirname(__DIR__) . '/includes/header.php';
?>

<style>
/* ── Modal overlay ── */
.modal-overlay{
  display:none;position:fixed;inset:0;background:rgba(26,31,54,.35);
  z-index:200;align-items:center;justify-content:center;
  backdrop-filter:blur(2px);
}
.modal-overlay.open{display:flex}
.modal-box{
  background:var(--card);border:1px solid var(--border);border-radius:16px;
  padding:28px 30px;width:100%;max-width:480px;
  box-shadow:0 12px 48px rgba(60,72,120,.18);
  position:relative;animation:modalIn .2s ease;
}
@keyframes modalIn{from{transform:translateY(-16px);opacity:0}to{transform:translateY(0);opacity:1}}
.modal-close{
  position:absolute;top:16px;right:16px;
  background:var(--bg);border:1px solid var(--border);border-radius:6px;
  width:28px;height:28px;cursor:pointer;font-size:16px;
  display:flex;align-items:center;justify-content:center;
  color:var(--muted);transition:all .15s;
}
.modal-close:hover{color:var(--danger);border-color:var(--danger)}
.modal-title{
  font-family:var(--font-h);font-size:15px;font-weight:700;
  color:var(--text);margin-bottom:20px;
}

/* ── Form panel ── */
.form-panel{
  background:var(--card);border:1px solid var(--border);
  border-radius:var(--r2);padding:24px 26px;margin-bottom:22px;
  box-shadow:0 2px 16px rgba(60,72,120,.10);
}
.panel-header{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid var(--border);
}
.panel-title{
  font-family:var(--font-h);font-size:14px;font-weight:700;color:var(--text);
}
.panel-subtitle{font-size:12px;color:var(--muted);margin-top:2px}

/* ── Password strength indicator ── */
.pw-strength{height:4px;border-radius:2px;margin-top:6px;background:var(--border);overflow:hidden;transition:all .3s}
.pw-strength-bar{height:100%;width:0;border-radius:2px;transition:all .3s}
.pw-hint{font-size:11px;margin-top:4px}

/* ── User cards in table ── */
.user-avatar{
  width:36px;height:36px;border-radius:50%;
  background:linear-gradient(135deg,var(--accent),var(--accent2));
  display:flex;align-items:center;justify-content:center;
  font-family:var(--font-h);font-size:14px;font-weight:700;
  color:#fff;flex-shrink:0;
}
.user-avatar.inactive{
  background:linear-gradient(135deg,#c0c6d9,#9aa0bb);
}
.you-tag{
  background:rgba(59,127,245,.1);color:var(--accent);
  border:1px solid rgba(59,127,245,.2);border-radius:20px;
  font-size:10px;font-weight:700;padding:1px 7px;letter-spacing:.3px;
  vertical-align:middle;margin-left:5px;
}
</style>

<div style="display:grid;grid-template-columns:380px 1fr;gap:22px;align-items:start">

  <!-- ══ LEFT: CREATE / EDIT FORM ══════════════════════════ -->
  <div class="form-panel">
    <div class="panel-header">
      <div>
        <div class="panel-title"><?= $editUser ? '✏️ Edit Admin User' : '＋ Add Admin User' ?></div>
        <div class="panel-subtitle">
          <?= $editUser
            ? 'Update details for ' . e($editUser['name'])
            : 'Create a new admin account' ?>
        </div>
      </div>
      <?php if ($editUser): ?>
      <a href="<?= ADMIN_URL ?>/pages/admins.php" class="btn btn-ghost btn-sm">Cancel</a>
      <?php endif; ?>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="flash flash-error" style="margin-bottom:16px">
      <div>✕ <?= implode('<br>', array_map('e', $errors)) ?></div>
    </div>
    <?php endif; ?>

    <form method="POST" id="adminForm" novalidate>
      <input type="hidden" name="action"  value="save">
      <input type="hidden" name="user_id" value="<?= $editUser ? $editUser['id'] : 0 ?>">

      <!-- Name -->
      <div class="field" style="margin-bottom:14px">
        <label>Full Name <span class="req">*</span></label>
        <input type="text" name="name" class="ctrl"
               placeholder="e.g. John Smith"
               value="<?= e($editUser['name'] ?? ($_POST['name'] ?? '')) ?>"
               required autofocus>
      </div>

      <!-- Email -->
      <div class="field" style="margin-bottom:14px">
        <label>Email Address <span class="req">*</span></label>
        <input type="email" name="email" class="ctrl"
               placeholder="e.g. john@acceloninc.com"
               value="<?= e($editUser['email'] ?? ($_POST['email'] ?? '')) ?>"
               required>
        <span class="field-hint" id="emailDomainHint" style="color:var(--muted)">Only <strong>@acceloninc.com</strong> addresses are allowed.</span>
      </div>

      <!-- Role -->
      <div class="field" style="margin-bottom:14px">
        <label>Role <span class="req">*</span></label>
        <select name="role" class="ctrl" required>
          <?php
          $savedRole = $editUser['role'] ?? ($_POST['role'] ?? 'admin');
          
          foreach (['superadmin'=>'★ Superadmin — Full access',
                    'admin'     =>'⬡ Admin — Manage jobs & clients'] as $val => $label):
         // foreach (['superadmin'=>'★ Superadmin — Full access',
            //        'admin'     =>'⬡ Admin — Manage jobs & clients',
                //    'editor'    =>'✎ Editor — Post & edit jobs only'] as $val => $label):
          ?>
          <option value="<?= $val ?>" <?= $savedRole===$val?'selected':'' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
        <span class="field-hint">Superadmin can manage users. Admin manages jobs/clients.</span>
      </div>

      <!-- Active toggle -->
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;
                  background:var(--bg);border:1px solid var(--border);
                  border-radius:var(--r);padding:10px 14px">
        <input type="checkbox" name="is_active" id="isActive" value="1"
               style="accent-color:var(--accent);width:16px;height:16px;cursor:pointer"
               <?= ($editUser ? $editUser['is_active'] : 1) ? 'checked' : '' ?>>
        <label for="isActive" style="font-size:13px;color:var(--text);cursor:pointer;
               text-transform:none;letter-spacing:0;font-weight:400">
          Account Active
          <span style="display:block;font-size:11px;color:var(--muted);margin-top:1px">
            Inactive users cannot log in
          </span>
        </label>
      </div>

      <!-- Divider -->
      <div style="border-top:1px solid var(--border);margin:18px 0 16px;
                  display:flex;align-items:center;gap:10px">
        <span style="font-size:11px;color:var(--muted);white-space:nowrap;font-weight:600;
                     text-transform:uppercase;letter-spacing:.7px">
          <?= $editUser ? 'Change Password (optional)' : 'Set Password' ?>
        </span>
        <div style="flex:1;height:1px;background:var(--border)"></div>
      </div>

      <!-- Password -->
      <div class="field" style="margin-bottom:10px">
        <label>
          Password <?= !$editUser ? '<span class="req">*</span>' : '' ?>
          <?= $editUser ? '<span style="font-size:10px;font-weight:400;color:var(--muted)">(leave blank to keep current)</span>' : '' ?>
        </label>
        <input type="password" name="password" id="pwField" class="ctrl"
               placeholder="Min. 8 characters"
               autocomplete="new-password"
               oninput="checkStrength(this.value)"
               <?= !$editUser ? 'required' : '' ?>>
        <div class="pw-strength"><div class="pw-strength-bar" id="pwBar"></div></div>
        <div class="pw-hint" id="pwHint" style="color:var(--muted)"></div>
      </div>

      <!-- Confirm Password -->
      <div class="field" style="margin-bottom:20px">
        <label>Confirm Password <?= !$editUser ? '<span class="req">*</span>' : '' ?></label>
        <input type="password" name="password_confirm" id="pwConfirm" class="ctrl"
               placeholder="Re-enter password"
               autocomplete="new-password"
               oninput="checkMatch()"
               <?= !$editUser ? 'required' : '' ?>>
        <div class="field-hint" id="matchHint"></div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
        <?= $editUser ? '💾 Update Admin User' : '＋ Create Admin User' ?>
      </button>

    </form>
  </div>

  <!-- ══ RIGHT: USERS TABLE ════════════════════════════════ -->
  <div>
    <div class="card" style="padding:0">

      <!-- Table header -->
      <div style="padding:16px 22px 14px;display:flex;align-items:center;
                  justify-content:space-between;border-bottom:1px solid var(--border)">
        <div>
          <span class="section-title" style="margin:0">Admin Users</span>
        </div>
        <span style="font-size:12px;color:var(--muted)"><?= count($admins) ?> total</span>
      </div>

      <div class="table-wrap" style="border:none;border-radius:0">
        <table>
          <thead>
            <tr>
              <th>User</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Last Login</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($admins)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:30px">
              No admin users found.
            </td></tr>
          <?php else: ?>
            <?php foreach ($admins as $u):
                $isSelf = (int)$u['id'] === (int)$_SESSION['admin_id'];
            ?>
            <tr>
              <!-- User -->
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <div class="user-avatar <?= !$u['is_active']?'inactive':'' ?>">
                    <?= strtoupper(substr($u['name'],0,1)) ?>
                  </div>
                  <div>
                    <strong style="font-size:13px">
                      <?= e($u['name']) ?>
                      <?php if ($isSelf): ?>
                      <span class="you-tag">You</span>
                      <?php endif; ?>
                    </strong>
                    <div style="font-size:11px;color:var(--muted)">
                      Since <?= date('M Y', strtotime($u['created_at'])) ?>
                    </div>
                  </div>
                </div>
              </td>

              <!-- Email -->
              <td class="td-muted" style="font-size:13px"><?= e($u['email']) ?></td>

              <!-- Role -->
              <td><?= roleBadge($u['role']) ?></td>

              <!-- Status -->
              <td>
                <?php if ($u['is_active']): ?>
                <span class="badge badge-published">Active</span>
                <?php else: ?>
                <span class="badge badge-closed">Inactive</span>
                <?php endif; ?>
              </td>

              <!-- Last Login -->
              <td class="td-muted" style="font-size:12px">
                <?= $u['last_login']
                    ? date('d M Y, g:i A', strtotime($u['last_login']))
                    : '<span style="color:var(--muted)">Never</span>' ?>
              </td>

              <!-- Actions -->
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap">

                  <!-- Edit -->
                  <a href="<?= ADMIN_URL ?>/pages/admins.php?edit=<?= $u['id'] ?>"
                     class="btn btn-ghost btn-sm" title="Edit">✏ Edit</a>

                  <!-- Reset Password -->
                  <button type="button" class="btn btn-ghost btn-sm"
                          onclick="openResetModal(<?= $u['id'] ?>, '<?= e(addslashes($u['name'])) ?>')"
                          title="Reset Password">🔑</button>

                  <!-- Toggle Active (not self) -->
                  <?php if (!$isSelf): ?>
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="action"  value="toggle_active">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-sm"
                            title="<?= $u['is_active']?'Deactivate':'Activate' ?>"
                            onclick="return confirm('<?= $u['is_active']?'Deactivate':'Activate' ?> this user?')">
                      <?= $u['is_active'] ? '⏸' : '▶' ?>
                    </button>
                  </form>

                  <!-- Delete -->
                  <form method="POST" style="display:inline"
                        onsubmit="return confirm('Permanently delete <?= e(addslashes($u['name'])) ?>? This cannot be undone.')">
                    <input type="hidden" name="action"  value="delete">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm" title="Delete">🗑</button>
                  </form>
                  <?php else: ?>
                  <span style="font-size:11px;color:var(--muted);padding:6px 4px">—</span>
                  <?php endif; ?>

                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Role legend -->
      <div style="padding:14px 22px;border-top:1px solid var(--border);
                  display:flex;gap:16px;flex-wrap:wrap">
        <span style="font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px">Roles:</span>
        <span style="font-size:12px;color:var(--muted)">★ <strong>Superadmin</strong> — Full access, manage users</span>
        <span style="font-size:12px;color:var(--muted)">⬡ <strong>Admin</strong> — Jobs, clients, settings</span>
    
    <!--    <span style="font-size:12px;color:var(--muted)">✎ <strong>Editor</strong> — Post & edit jobs only</span> -->
      </div>
    </div>
  </div>

</div>

<!-- ══ RESET PASSWORD MODAL ════════════════════════════════ -->
<div class="modal-overlay" id="resetModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeResetModal()">✕</button>
    <div class="modal-title">🔑 Reset Password</div>
    <p style="font-size:13px;color:var(--text2);margin-bottom:18px">
      Setting new password for: <strong id="resetUserName" style="color:var(--text)"></strong>
    </p>
    <form method="POST" id="resetForm">
      <input type="hidden" name="action"   value="reset_password">
      <input type="hidden" name="user_id"  id="resetUserId" value="">

      <div class="field" style="margin-bottom:14px">
        <label>New Password <span class="req">*</span></label>
        <input type="password" name="new_password" id="resetPw" class="ctrl"
               placeholder="Min. 8 characters" required
               oninput="checkResetStrength(this.value)">
        <div class="pw-strength"><div class="pw-strength-bar" id="resetPwBar"></div></div>
        <div class="pw-hint" id="resetPwHint" style="color:var(--muted)"></div>
      </div>

      <div class="field" style="margin-bottom:20px">
        <label>Confirm New Password <span class="req">*</span></label>
        <input type="password" name="confirm_password" id="resetPwConf" class="ctrl"
               placeholder="Re-enter new password" required
               oninput="checkResetMatch()">
        <div class="field-hint" id="resetMatchHint"></div>
      </div>

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center">
          🔑 Reset Password
        </button>
        <button type="button" class="btn btn-ghost" onclick="closeResetModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Password strength checker ─────────────────────────────────
function pwStrength(val) {
    if (!val) return { score: 0, label: '', color: '', hints: [] };
    const hints = [];
    let score = 0;
    if (val.length >= 8)  { score++; } else { hints.push('Min. 8 characters'); }
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val))            { score++; } else { hints.push('One uppercase letter'); }
    if (/[0-9]/.test(val))            { score++; } else { hints.push('One number'); }
    if (/[^a-zA-Z0-9]/.test(val))    { score++; } else { hints.push('One special character (!@#$…)'); }
    if (/(012|123|234|345|456|567|678|789|890|abc|bcd|cde|def|efg|fgh|ghi|hij|ijk|jkl|klm|lmn|mno|nop|opq|pqr|qrs|rst|stu|tuv|uvw|vwx|wxy|xyz)/i.test(val)) {
        score = Math.max(0, score - 1);
        hints.push('No sequential series (123, abc…)');
    }
    const levels = [
        { color: '#e04545', label: 'Too weak',   pct: '20%' },
        { color: '#e89e10', label: 'Weak',        pct: '40%' },
        { color: '#e89e10', label: 'Fair',        pct: '60%' },
        { color: '#1fad72', label: 'Strong',      pct: '80%' },
        { color: '#1fad72', label: 'Very strong', pct: '100%'},
    ];
    return { ...levels[Math.min(score, 4)], hints };
}

function checkStrength(val) {
    const r = pwStrength(val);
    document.getElementById('pwBar').style.cssText = `width:${r.pct||'0%'};background:${r.color}`;
    const hintEl = document.getElementById('pwHint');
    if (!val) { hintEl.innerHTML = ''; hintEl.style.color = 'var(--muted)'; checkMatch(); return; }
    if (r.hints.length) {
        hintEl.innerHTML = '<span style="color:'+r.color+'">'+r.label+'</span>'
            + ' &mdash; still needs: <span style="color:var(--danger)">' + r.hints.join(', ') + '</span>';
    } else {
        hintEl.innerHTML = '<span style="color:'+r.color+'">✓ '+r.label+'</span>';
    }
    checkMatch();
}

function checkMatch() {
    const pw  = document.getElementById('pwField').value;
    const cfm = document.getElementById('pwConfirm').value;
    const el  = document.getElementById('matchHint');
    if (!cfm) { el.textContent = ''; return; }
    if (pw === cfm) { el.textContent = '✓ Passwords match'; el.style.color = 'var(--success)'; }
    else            { el.textContent = '✕ Passwords do not match'; el.style.color = 'var(--danger)'; }
}

// ── Reset modal ───────────────────────────────────────────────
function openResetModal(id, name) {
    document.getElementById('resetUserId').value    = id;
    document.getElementById('resetUserName').textContent = name;
    document.getElementById('resetPw').value        = '';
    document.getElementById('resetPwConf').value    = '';
    document.getElementById('resetPwBar').style.cssText = 'width:0';
    document.getElementById('resetPwHint').textContent  = '';
    document.getElementById('resetMatchHint').textContent = '';
    document.getElementById('resetModal').classList.add('open');
    setTimeout(() => document.getElementById('resetPw').focus(), 100);
}
function closeResetModal() {
    document.getElementById('resetModal').classList.remove('open');
}
// Close on backdrop click
document.getElementById('resetModal').addEventListener('click', function(e) {
    if (e.target === this) closeResetModal();
});
// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeResetModal();
});

function checkResetStrength(val) {
    const r = pwStrength(val);
    document.getElementById('resetPwBar').style.cssText = `width:${r.pct||'0%'};background:${r.color}`;
    const hintEl = document.getElementById('resetPwHint');
    if (!val) { hintEl.innerHTML = ''; hintEl.style.color = 'var(--muted)'; checkResetMatch(); return; }
    if (r.hints.length) {
        hintEl.innerHTML = '<span style="color:'+r.color+'">'+r.label+'</span>'
            + ' &mdash; still needs: <span style="color:var(--danger)">' + r.hints.join(', ') + '</span>';
    } else {
        hintEl.innerHTML = '<span style="color:'+r.color+'">✓ '+r.label+'</span>';
    }
    checkResetMatch();
}
function checkResetMatch() {
    const pw  = document.getElementById('resetPw').value;
    const cfm = document.getElementById('resetPwConf').value;
    const el  = document.getElementById('resetMatchHint');
    if (!cfm) { el.textContent = ''; return; }
    if (pw === cfm) { el.textContent = '✓ Match'; el.style.color = 'var(--success)'; }
    else            { el.textContent = '✕ No match'; el.style.color = 'var(--danger)'; }
}

// ── Scroll to form on edit ────────────────────────────────────
<?php if ($editUser): ?>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('adminForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
});
<?php endif; ?>
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>