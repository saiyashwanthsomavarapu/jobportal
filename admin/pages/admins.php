<?php
// admin/pages/admins.php
require_once dirname(__DIR__) . "/auth.php";
require_once dirname(__DIR__) . "/utils/classes.php";
/* Only superadmin can manage admin users */

if ($currentAdmin["role"] !== "superadmin") {
  flash("error", "Access denied. Superadmin only.");
  redirect(ADMIN_URL . "/index.php");
}

$pageTitle = "Admin Users";
$breadcrumbs = [["Dashboard", ADMIN_URL . "/index.php"], ["Admin Users", null]];

$errors = [];

/* Handle POST actions */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  csrf_verify();

  $action = $_POST["action"] ?? "";
  $targetId = (int) ($_POST["user_id"] ?? 0);

  /* CREATE / UPDATE */
  if ($action === "save") {
    $name = trim($_POST["name"] ?? "");
    $email = strtolower(trim($_POST["email"] ?? ""));
    $role = trim($_POST["role"] ?? "admin");
    $password = trim($_POST["password"] ?? "");
    $passConf = trim($_POST["password_confirm"] ?? "");

    // Validate
    if (!$name) {
      $errors[] = "Full Name is required.";
    }
    if (!$email) {
      $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors[] = "Invalid email address.";
    } elseif (
      substr($email, -strlen("@acceloninc.com")) !== "@acceloninc.com"
    ) {
      $errors[] = "Only @acceloninc.com email addresses are allowed.";
    }
    if (!in_array($role, ["superadmin", "admin", "editor"])) {
      $errors[] = "Invalid role.";
    }

    /* Strong password validator (shared logic) */
    $pwErrors = function ($pw) {
      $e = [];
      if (strlen($pw) < 8) {
        $e[] = "at least 8 characters";
      }
      if (!preg_match("/[A-Z]/", $pw)) {
        $e[] = "one uppercase letter";
      }
      if (!preg_match("/[0-9]/", $pw)) {
        $e[] = "one number";
      }
      if (!preg_match("/[^a-zA-Z0-9]/", $pw)) {
        $e[] = "one special character";
      }
      if (
        preg_match(
          "/(012|123|234|345|456|567|678|789|890|abc|bcd|cde|def|efg|fgh|ghi|hij|ijk|jkl|klm|lmn|mno|nop|opq|pqr|qrs|rst|stu|tuv|uvw|vwx|wxy|xyz)/i",
          $pw
        )
      ) {
        $e[] = "no sequential series (e.g. 123, abc)";
      }
      return $e;
    };

    if ($targetId === 0) {
      /* Create: password required */
      if (!$password) {
        $errors[] = "Password is required for new users.";
      } else {
        $pe = $pwErrors($password);
        if ($pe) {
          $errors[] =
            "Password must contain: " . implode(", ", $pe) . ".";
        } elseif ($password !== $passConf) {
          $errors[] = "Passwords do not match.";
        }
      }
    } else {
      /* Update: password optional (only if provided) */
      if ($password !== "") {
        $pe = $pwErrors($password);
        if ($pe) {
          $errors[] =
            "Password must contain: " . implode(", ", $pe) . ".";
        } elseif ($password !== $passConf) {
          $errors[] = "Passwords do not match.";
        }
      }
    }

    if (empty($errors)) {
      try {
        $pdo = db();

        /* Check email uniqueness */
        $dupCheck = $pdo->prepare(
          "SELECT id FROM admin_users WHERE email = ? AND id != ?"
        );
        $dupCheck->execute([$email, $targetId ?: 0]);
        if ($dupCheck->fetch()) {
          $errors[] =
            "Email address is already in use by another admin.";
        } else {
          if ($targetId === 0) {
            /* INSERT new admin — accounts start active; the status toggle manages pausing */
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare(
              "
                            INSERT INTO admin_users (name, email, password, role, is_active)
                            VALUES (?, ?, ?, ?, 1)
                        "
            )->execute([$name, $email, $hash, $role]);
            logActivity(
              "create_admin",
              "admin_user",
              (int) $pdo->lastInsertId(),
              $email
            );
            flash(
              "success",
              "Admin user <strong>" .
                e($name) .
                "</strong> created."
            );
          } else {
            /* Prevent demoting yourself */
            if (
              $targetId === (int) $_SESSION["admin_id"] &&
              $role !== "superadmin"
            ) {
              $errors[] = "You cannot change your own role.";
            } else {
              if ($password !== "") {
                /* Update with new password — status is managed by the toggle, so leave is_active untouched */
                $hash = password_hash(
                  $password,
                  PASSWORD_BCRYPT
                );
                $pdo->prepare(
                  "
                                    UPDATE admin_users
                                    SET name=?, email=?, password=?, role=?
                                    WHERE id=?
                                "
                )->execute([
                  $name,
                  $email,
                  $hash,
                  $role,
                  $targetId,
                ]);
              } else {
                /* Update without changing password */
                $pdo->prepare(
                  "
                                    UPDATE admin_users
                                    SET name=?, email=?, role=?
                                    WHERE id=?
                                "
                )->execute([
                  $name,
                  $email,
                  $role,
                  $targetId,
                ]);
              }
              logActivity(
                "update_admin",
                "admin_user",
                $targetId,
                $email
              );
              flash(
                "success",
                "Admin user <strong>" .
                  e($name) .
                  "</strong> updated."
              );
            }
          }

          if (empty($errors)) {
            redirect(ADMIN_URL . "/pages/admins.php");
          }
        }
      } catch (Exception $ex) {
        error_log("[admins] save failed: " . $ex->getMessage());
        $errors[] = "Something went wrong while saving. Please try again.";
      }
    }
  } elseif ($action === "toggle_active" && $targetId > 0) { /* TOGGLE ACTIVE */
    if ($targetId === (int) $_SESSION["admin_id"]) {
      flash("error", "You cannot deactivate your own account.");
    } else {
      try {
        $curr = db()->prepare(
          "SELECT is_active, name FROM admin_users WHERE id=?"
        );
        $curr->execute([$targetId]);
        $row = $curr->fetch();
        if ($row) {
          $newState = $row["is_active"] ? 0 : 1;
          db()
            ->prepare(
              "UPDATE admin_users SET is_active=? WHERE id=?"
            )
            ->execute([$newState, $targetId]);
          $label = $newState ? "activated" : "deactivated";
          logActivity($label . "_admin", "admin_user", $targetId);
          flash(
            "success",
            "User <strong>" .
              e($row["name"]) .
              "</strong> " .
              $label .
              "."
          );
        }
      } catch (Exception $ex) {
        error_log("[admins] toggle_active failed: " . $ex->getMessage());
        flash("error", "Could not update the account status. Please try again.");
      }
    }
    redirect(ADMIN_URL . "/pages/admins.php");
  } elseif ($action === "delete" && $targetId > 0) { /* DELETE */
    if ($targetId === (int) $_SESSION["admin_id"]) {
      flash("error", "You cannot delete your own account.");
    } else {
      try {
        $row = db()->prepare("SELECT name FROM admin_users WHERE id=?");
        $row->execute([$targetId]);
        $row = $row->fetch();
        db()
          ->prepare("DELETE FROM admin_users WHERE id=?")
          ->execute([$targetId]);
        logActivity(
          "delete_admin",
          "admin_user",
          $targetId,
          $row["name"] ?? ""
        );
        flash("success", "Admin user deleted.");
      } catch (Exception $ex) {
        error_log("[admins] delete failed: " . $ex->getMessage());
        flash("error", "Could not delete the user. Please try again.");
      }
    }
    redirect(ADMIN_URL . "/pages/admins.php");
  } elseif ($action === "reset_password" && $targetId > 0) { /* RESET PASSWORD (quick reset to a temp password) */
    $newPass = trim($_POST["new_password"] ?? "");
    $confirm = trim($_POST["confirm_password"] ?? "");
    $resetPwErrors = [];
    if (!$newPass) {
      $resetPwErrors[] = "Password is required.";
    } else {
      if (strlen($newPass) < 8) {
        $resetPwErrors[] = "at least 8 characters";
      }
      if (!preg_match("/[A-Z]/", $newPass)) {
        $resetPwErrors[] = "one uppercase letter";
      }
      if (!preg_match("/[0-9]/", $newPass)) {
        $resetPwErrors[] = "one number";
      }
      if (!preg_match("/[^a-zA-Z0-9]/", $newPass)) {
        $resetPwErrors[] = "one special character";
      }
      if (
        preg_match(
          "/(012|123|234|345|456|567|678|789|890|abc|bcd|cde|def|efg|fgh|ghi|hij|ijk|jkl|klm|lmn|mno|nop|opq|pqr|qrs|rst|stu|tuv|uvw|vwx|wxy|xyz)/i",
          $newPass
        )
      ) {
        $resetPwErrors[] = "no sequential series (e.g. 123, abc)";
      }
    }
    if (
      !empty($resetPwErrors) &&
      count($resetPwErrors) === 1 &&
      $resetPwErrors[0] === "Password is required."
    ) {
      flash("error", "Password is required.");
    } elseif (!empty($resetPwErrors)) {
      flash(
        "error",
        "Password must contain: " . implode(", ", $resetPwErrors) . "."
      );
    } elseif ($newPass !== $confirm) {
      flash("error", "Passwords do not match.");
    } else {
      try {
        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        db()
          ->prepare("UPDATE admin_users SET password=? WHERE id=?")
          ->execute([$hash, $targetId]);
        logActivity("reset_password", "admin_user", $targetId);
        flash("success", "Password reset successfully.");
      } catch (Exception $ex) {
        error_log("[admins] reset_password failed: " . $ex->getMessage());
        flash("error", "Could not reset the password. Please try again.");
      }
    }
    redirect(ADMIN_URL . "/pages/admins.php");
  }
}

/* Load edit target (if ?edit= param) */

$editUser = null;
$editId = (int) ($_GET["edit"] ?? 0);
if ($editId > 0) {
  $stmt = db()->prepare("SELECT * FROM admin_users WHERE id = ?");
  $stmt->execute([$editId]);
  $editUser = $stmt->fetch();
  if (!$editUser) {
    flash("error", "User not found.");
    redirect(ADMIN_URL . "/pages/admins.php");
  }
}

/* Load all admin users */

try {
  $admins = db()
    ->query("SELECT * FROM admin_users ORDER BY role ASC, name ASC")
    ->fetchAll();
} catch (Exception $ex) {
  $admins = [];
}

/* View state helpers */

$isPostFailure = $_SERVER["REQUEST_METHOD"] === "POST" && !empty($errors);
$autoOpenUserId = $isPostFailure
  ? (int) ($_POST["user_id"] ?? 0)
  : ($editUser ? (int) $editUser["id"] : 0);

$prefillName = $isPostFailure ? (string) ($_POST["name"] ?? "") : (string) ($editUser["name"] ?? "");
$prefillEmail = $isPostFailure ? (string) ($_POST["email"] ?? "") : (string) ($editUser["email"] ?? "");
$prefillRole = $isPostFailure ? (string) ($_POST["role"] ?? "admin") : (string) ($editUser["role"] ?? "admin");
$prefillSelf = $autoOpenUserId > 0 && $autoOpenUserId === (int) $_SESSION["admin_id"];

$totalUsers = count($admins);
$activeUsers = count(array_filter($admins, fn($u) => !empty($u["is_active"])));
$pausedUsers = $totalUsers - $activeUsers;
$superUsers = count(array_filter($admins, fn($u) => ($u["role"] ?? "") === "superadmin"));

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
        Manage users
      </h2>
    </div>
    <div class="flex items-center gap-2">
      <button type="button" onclick="openUserModal()"
        class="<?= PRIMARY_BUTTON_CLASS ?> shadow-pop hover:-translate-y-px hover:opacity-100 hover:shadow-lg">
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Add user
      </button>
    </div>
  </section>

  <!-- ═══════════ TEAM MEMBERS LIST ═══════════ -->
  <section class="overflow-hidden rounded-2xl bg-base-100 shadow-card border border-base-300">

    <header class="flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-4">
      <div class="mr-auto flex items-center gap-3">
        <div class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
          <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
          </svg>
        </div>
        <div>
          <h3 class="font-head text-[15px] font-bold tracking-tight text-base-content">All users</h3>
          <p id="tableMeta" class="text-[11.5px] text-base-content/50">
            Showing <?= $totalUsers ?> of <?= $totalUsers ?> member<?= $totalUsers === 1 ? "" : "s" ?>
          </p>
        </div>
      </div>

      <label class="input input-sm h-9 w-64 max-w-full items-center gap-2 border-transparent bg-base-200 focus-within:border-primary focus-within:bg-base-100">
        <svg class="size-4 shrink-0 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
        </svg>
        <input id="userSearch" type="search" placeholder="Search name or email…"
          class="grow bg-transparent text-[13px] outline-none placeholder:text-base-content/35">
      </label>

      <!-- Segmented filter · role -->
      <div class="flex items-center gap-2">
        <span class="hidden text-[10.5px] font-bold uppercase tracking-[0.08em] text-base-content/40 lg:inline">Role</span>
        <div id="roleChips" role="group" aria-label="Filter by role"
          class="flex items-center gap-0.5 rounded-full bg-base-200 p-1">
          <button type="button" data-value="" aria-pressed="true"
            class="seg-btn rounded-full px-3 py-1 text-[11.5px] font-semibold transition-colors bg-base-100 text-base-content shadow-sm">All</button>
          <button type="button" data-value="superadmin" aria-pressed="false"
            class="seg-btn rounded-full px-3 py-1 text-[11.5px] font-semibold transition-colors text-base-content/55 hover:text-base-content">Superadmin</button>
          <button type="button" data-value="admin" aria-pressed="false"
            class="seg-btn rounded-full px-3 py-1 text-[11.5px] font-semibold transition-colors text-base-content/55 hover:text-base-content">Admin</button>
        </div>
      </div>

      <!-- Segmented filter · status -->
      <div class="flex items-center gap-2">
        <span class="hidden text-[10.5px] font-bold uppercase tracking-[0.08em] text-base-content/40 lg:inline">Status</span>
        <div id="statusChips" role="group" aria-label="Filter by status"
          class="flex items-center gap-0.5 rounded-full bg-base-200 p-1">
          <button type="button" data-value="" aria-pressed="true"
            class="seg-btn rounded-full px-3 py-1 text-[11.5px] font-semibold transition-colors bg-base-100 text-base-content shadow-sm">All</button>
          <button type="button" data-value="1" aria-pressed="false"
            class="seg-btn rounded-full px-3 py-1 text-[11.5px] font-semibold transition-colors text-base-content/55 hover:text-base-content">Active</button>
          <button type="button" data-value="0" aria-pressed="false"
            class="seg-btn rounded-full px-3 py-1 text-[11.5px] font-semibold transition-colors text-base-content/55 hover:text-base-content">Paused</button>
        </div>
      </div>
    </header>

    <div class="overflow-x-auto">
      <table class="<?= TABLE_CLASS ?>">
        <thead class="<?= TABLE_HEAD_CLASS ?>">
          <tr>
            <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Team member</th>
            <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Role</th>
            <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Account status</th>
            <th class="<?= TABLE_HEAD_ROW_CLASS ?>">Last signed in</th>
            <th class="<?= TABLE_HEAD_ROW_CLASS ?>"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody id="usersTbody">

          <?php if (empty($admins)): ?>

            <tr>
              <td colspan="5">
                <div class="py-14 text-center">
                  <div class="mx-auto grid size-16 place-items-center rounded-full border-2 border-dashed border-base-300 bg-base-200/50 text-base-content/40">
                    <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                    </svg>
                  </div>
                  <h4 class="mt-4 font-head text-[15px] font-bold text-base-content">No team members yet</h4>
                  <p class="mx-auto mt-1 max-w-xs text-[12.5px] leading-relaxed text-base-content/50">
                    Invite your first teammate by creating their sign-in account.
                  </p>
                  <button type="button" onclick="openUserModal()" class="<?= PRIMARY_BUTTON_CLASS ?> mt-4 shadow-pop">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Add user
                  </button>
                </div>
              </td>
            </tr>

          <?php else: ?>

            <tr id="noMatchRow" class="hidden">
              <td colspan="5" class="py-12 text-center text-[13px] text-base-content/50">
                No members match your search or filters.
              </td>
            </tr>

            <?php foreach ($admins as $u):
              $isSelf = (int) $u["id"] === (int) $_SESSION["admin_id"];
              $isActiveUser = !empty($u["is_active"]);

              /* role → [label, badge classes, icon path] */
              $roleMeta = [
                "superadmin" => [
                  "Superadmin",
                  "badge-neutral",
                  '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />',
                ],
                "admin" => [
                  "Admin",
                  "badge-soft badge-primary",
                  '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.098a2.25 2.25 0 01-2.25 2.25h-12a2.25 2.25 0 01-2.25-2.25V14.15M16.5 6.75V5.25A2.25 2.25 0 0014.25 3h-4.5A2.25 2.25 0 007.5 5.25v1.5m6 0V7.5c0 .828-.672 1.5-1.5 1.5h-3c-.828 0-1.5-.672-1.5-1.5V6.75m6 0h3.688c.622 0 1.19.368 1.441.94l1.5 3.438a2.25 2.25 0 01.17.894v2.028a2.25 2.25 0 01-2.25 2.25h-.75M4.5 13.55v2.028c0 .32.068.635.17.894l1.5 3.437a2.25 2.25 0 001.44 1.44h.75m6.64-7.85h3.71" />',
                ],
                "editor" => [
                  "Editor",
                  "badge-soft badge-success",
                  '<path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />',
                ],
              ];
              [$rl, $rb, $rIcon] = $roleMeta[$u["role"]]
                ?? [ucfirst($u["role"]), "badge-ghost", '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />'];
            ?>
              <tr class="border-b border-base-300/60 transition-colors last:border-b-0 hover:bg-base-200/60"
                data-search="<?= e(strtolower($u["name"] . " " . $u["email"])) ?>"
                data-role="<?= e($u["role"]) ?>"
                data-active="<?= $isActiveUser ? "1" : "0" ?>">

                <!-- Member -->
                <td class="px-5 py-3.5 align-middle">
                  <div class="flex items-center gap-3">
                    <div class="avatar avatar-placeholder" title="Member since <?= date("F Y", strtotime($u["created_at"])) ?>">
                      <div class="w-9 rounded-full font-head text-[13px] font-bold <?= $isActiveUser
                                                                                      ? "bg-primary text-primary-content"
                                                                                      : "bg-base-300 text-base-content" ?>">
                        <span><?= strtoupper(substr($u["name"], 0, 1)) ?></span>
                      </div>
                    </div>
                    <div class="min-w-0">
                      <strong class="block truncate text-[13px] font-semibold text-base-content">
                        <?= e($u["name"]) ?>
                        <?php if ($isSelf): ?>
                          <span class="badge badge-xs badge-ghost ml-1.5 align-middle font-bold">You</span>
                        <?php endif; ?>
                      </strong>
                      <span class="block truncate text-[11.5px] text-base-content/50"><?= e($u["email"]) ?></span>
                    </div>
                  </div>
                </td>

                <!-- Role -->
                <td class="px-5 py-3.5 align-middle">
                  <span class="badge badge-sm whitespace-nowrap <?= $rb ?>"><?= e($rl) ?></span>
                </td>

                <!-- Status toggle -->
                <td class="px-5 py-3.5 align-middle">
                  <form method="POST" class="inline-flex">
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="user_id" value="<?= $u["id"] ?>">
                    <?= csrf_field() ?>
                    <label class="inline-flex cursor-pointer items-center gap-2.5" title="<?= $isSelf ? "You can't pause your own account" : ($isActiveUser ? "Pause access — they won't be able to sign in" : "Restore access — they can sign in again") ?>">
                      <input type="checkbox" role="switch" aria-label="<?= $isActiveUser ? "Pause" : "Reactivate" ?> account of <?= e($u["name"]) ?>"
                        class="toggle toggle-sm <?= $isActiveUser ? "toggle-success" : "" ?>"
                        <?= $isSelf ? "disabled" : 'onchange="this.form.submit()"' ?>
                        <?= $isActiveUser ? "checked" : "" ?>>
                      <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-[11.5px] font-semibold <?= $isActiveUser
                                                                                                                                              ? "bg-success/10 text-success"
                                                                                                                                              : "bg-base-200 text-base-content/50" ?>">
                        <span class="status <?= $isActiveUser ? "status-success" : "" ?>"></span>
                        <?= $isActiveUser ? "Active" : "Paused" ?>
                      </span>
                    </label>
                  </form>
                </td>

                <!-- Last login -->
                <td class="whitespace-nowrap px-5 py-3.5 align-middle text-[12px] text-base-content/50">
                  <?php if (!empty($u["last_login"])): ?>
                    <span class="tabular-nums" title="<?= date("d M Y, g:i A", strtotime($u["last_login"])) ?>"><?= timeAgoStr($u["last_login"]) ?></span>
                  <?php else: ?>
                    <span class="italic">Never signed in</span>
                  <?php endif; ?>
                </td>

                <!-- Actions -->
                <td class="px-5 py-3.5 align-middle">
                  <div class="flex items-center justify-end gap-1">

                    <div class="tooltip tooltip-left" data-tip="Edit details">
                      <button type="button" aria-label="Edit <?= e($u["name"]) ?>"
                        onclick="openEditModal(this)"
                        data-id="<?= $u["id"] ?>"
                        data-name="<?= e($u["name"]) ?>"
                        data-email="<?= e($u["email"]) ?>"
                        data-role="<?= e($u["role"]) ?>"
                        data-self="<?= $isSelf ? "1" : "0" ?>"
                        class="btn btn-square btn-sm h-8 min-h-8 w-8 rounded-full border-transparent bg-transparent text-base-content/50 transition-all hover:bg-primary/10 hover:text-primary">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                        </svg>
                      </button>
                    </div>

                    <div class="tooltip tooltip-left" data-tip="Reset password">
                      <button type="button" aria-label="Reset password of <?= e($u["name"]) ?>"
                        onclick="openResetModal(<?= $u['id'] ?>, '<?= e(addslashes($u['name'])) ?>')"
                        class="btn btn-square btn-sm h-8 min-h-8 w-8 rounded-full border-transparent bg-transparent text-base-content/50 transition-all hover:bg-primary/10 hover:text-primary">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                        </svg>
                      </button>
                    </div>

                    <?php if (!$isSelf): ?>
                      <div class="tooltip tooltip-left" data-tip="Delete permanently">
                        <button type="button" aria-label="Delete <?= e($u["name"]) ?>"
                          onclick="openDeleteModal(<?= (int)$u['id'] ?>, '<?= e(addslashes($u['name'])) ?>')"
                          class="btn btn-square btn-sm h-8 min-h-8 w-8 rounded-full border-transparent bg-transparent text-base-content/50 transition-all hover:bg-error/10 hover:text-error">
                          <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                          </svg>
                        </button>
                      </div>
                    <?php else: ?>
                      <span class="px-1.5 text-[11px] italic text-base-content/40">That's you</span>
                    <?php endif; ?>

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

<span class="hidden badge badge-success badge-outline badge-ghost bg-base-100 text-base-content shadow-sm"></span><!-- safelist for JS-injected classes -->

<!-- ═══════════ ADD / EDIT TEAM MEMBER MODAL ═══════════ -->
<dialog id="userModal" class="modal">
  <div class="modal-box w-[calc(100%-2rem)] max-w-lg rounded-3xl border border-base-300/70 bg-base-100 p-0 shadow-xl">

    <!-- Header -->
    <header class="flex items-center justify-between gap-3 px-5 pb-1 pt-5">
      <div class="min-w-0">
        <h3 id="userModalTitle" class="truncate font-head text-[16px] font-bold tracking-tight text-base-content">New User</h3>
      </div>
      <button type="button" onclick="userModal.close()"
        class="btn btn-sm btn-circle btn-ghost size-8 min-h-8 shrink-0 text-base-content/50 hover:bg-base-200 hover:text-base-content"
        aria-label="Close">
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </header>

    <?php if (!empty($errors)): ?>
      <div class="px-5 pt-3">
        <div role="alert" class="alert alert-error alert-soft py-2.5 text-[12.5px]">
          <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div><?= implode("<br>", array_map("e", $errors)) ?></div>
        </div>
      </div>
    <?php endif; ?>

    <form method="POST" id="adminForm" novalidate>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="user_id" id="formUserId" value="<?= $autoOpenUserId ?>">
      <?= csrf_field() ?>

      <div class="space-y-5 px-5 py-5">

        <!-- Details -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <fieldset class="min-w-0">
            <legend class="mb-1.5 block text-xs font-semibold text-base-content/70">
              Full name <span class="text-error">*</span>
            </legend>
            <input type="text" id="fName" name="name" required
              placeholder="John Smith"
              value="<?= e($prefillName) ?>"
              class="<?= INPUT_CLASS ?> validator" />
          </fieldset>

          <fieldset class="min-w-0">
            <legend class="mb-1.5 block text-xs font-semibold text-base-content/70">
              Work email <span class="text-error">*</span>
            </legend>
            <input type="email" id="fEmail" name="email" required
              placeholder="john@acceloninc.com"
              pattern="[A-Za-z0-9._%+\-]+@acceloninc\.com"
              value="<?= e($prefillEmail) ?>"
              class="<?= INPUT_CLASS ?> validator" />
            <p class="mt-1.5 text-[11px] leading-snug text-base-content/45">
              Only <span class="font-semibold text-base-content/60">@acceloninc.com</span> emails are allowed
            </p>
          </fieldset>
        </div>

        <!-- Role -->
        <div>
          <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-base-content/50">Access level</p>
          <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">

            <label class="group flex cursor-pointer items-center gap-2.5 rounded-2xl border border-base-300 bg-base-100 p-3 transition-all hover:border-base-300 has-[:checked]:border-primary has-[:checked]:bg-primary/[.07] has-[:checked]:shadow-sm">
              <input type="radio" name="role" value="admin" class="radio radio-primary radio-xs"
                <?= $prefillRole === "admin" ? "checked" : "" ?>>
              <span class="min-w-0">
                <span class="block text-[12.5px] font-bold text-base-content">Admin</span>
                <span class="block truncate text-[11px] text-base-content/50">Jobs, clients &amp; everyday work</span>
              </span>
            </label>

            <label class="group flex cursor-pointer items-center gap-2.5 rounded-2xl border border-base-300 bg-base-100 p-3 transition-all hover:border-base-300 has-[:checked]:border-primary has-[:checked]:bg-primary/[.07] has-[:checked]:shadow-sm">
              <input type="radio" name="role" value="superadmin" class="radio radio-primary radio-xs"
                <?= $prefillRole === "superadmin" ? "checked" : "" ?>>
              <span class="min-w-0">
                <span class="block text-[12.5px] font-bold text-base-content">Superadmin</span>
                <span class="block truncate text-[11px] text-base-content/50">Everything, incl. team members</span>
              </span>
            </label>

          </div>

          <p id="selfRoleNote" class="hidden items-start gap-2 rounded-xl bg-primary/10 px-3 py-2 mt-2.5 text-[11.5px] font-medium text-primary">
            <svg class="mt-px size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
            </svg>
            This is your own account — your access level can't be changed here.
          </p>
        </div>

        <!-- Password (collapsible when editing) -->
        <div id="pwSection" class="space-y-3">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <fieldset class="min-w-0">
              <legend class="mb-1.5 block text-xs font-semibold text-base-content/70">
                <span id="pwFieldLabel">Password</span><span id="pwStar1" class="text-error">*</span>
              </legend>
              <input type="password" name="password" id="pwField"
                class="<?= INPUT_CLASS ?>"
                placeholder="At least 8 characters"
                autocomplete="new-password" required>
            </fieldset>

            <fieldset class="min-w-0">
              <legend class="mb-1.5 block text-xs font-semibold text-base-content/70">
                Confirm<span id="pwStar2" class="text-error">*</span>
              </legend>
              <input type="password" name="password_confirm" id="pwConfirm"
                class="<?= INPUT_CLASS ?>"
                placeholder="Re-enter password"
                autocomplete="new-password" required>
              <p id="matchHint" class="mt-1 min-h-[16px] text-[11px] font-medium"></p>
            </fieldset>
          </div>

          <ul id="formRules" class="flex flex-wrap gap-1.5"></ul>
        </div>

        <label id="pwToggleWrap" class="hidden cursor-pointer items-center gap-2.5 text-[12.5px] font-medium text-base-content/70">
          <input type="checkbox" id="pwToggle" class="toggle toggle-sm">
          Set a new password
        </label>

      </div>
    </form>

    <!-- Actions -->
    <footer class="modal-action m-0 flex flex-col-reverse gap-2 border-t border-base-200/80 bg-base-200/40 px-5 py-4 sm:flex-row sm:justify-end">
      <button type="button" onclick="userModal.close()"
        class="btn btn-ghost h-10 min-h-10 w-full rounded-full px-5 text-sm font-semibold text-base-content/70 hover:bg-base-200 hover:text-base-content sm:w-auto">
        Cancel
      </button>
      <button type="submit" form="adminForm"
        class="btn btn-primary h-10 min-h-10 w-full rounded-full px-6 text-sm font-semibold shadow-pop sm:w-auto">
        <span id="userSubmitLabel">Create account</span>
      </button>
    </footer>
  </div>

  <form method="dialog" class="modal-backdrop bg-black/40 backdrop-blur-xs">
    <button type="submit">close</button>
  </form>
</dialog>

<!-- ═══════════ RESET PASSWORD MODAL ═══════════ -->
<dialog id="resetModal" class="modal">
  <div class="modal-box w-[calc(100%-2rem)] max-w-md rounded-3xl border border-base-300/70 bg-base-100 p-0 shadow-xl">

    <div class="flex items-center justify-between gap-3 px-5 pb-1 pt-5">
      <div class="min-w-0">
        <h3 class="font-head text-[16px] font-bold tracking-tight text-base-content">Reset password</h3>
        <p class="truncate text-[11.5px] text-base-content/50">
          Temporary sign-in password for <strong class="text-base-content/70" id="resetUserName"></strong>
        </p>
      </div>
      <button type="button" onclick="resetModal.close()"
        class="btn btn-sm btn-circle btn-ghost size-8 min-h-8 shrink-0 text-base-content/50 hover:bg-base-200 hover:text-base-content"
        aria-label="Close">
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

      <form method="POST" id="resetForm">
        <div class="space-y-4 px-5 py-5">
          <input type="hidden" name="action" value="reset_password">
          <input type="hidden" name="user_id" id="resetUserId" value="">
          <?= csrf_field() ?>

        <div>
          <label for="resetPw" class="mb-1.5 block text-xs font-semibold text-base-content/70">
            New password <span class="text-error">*</span>
          </label>
          <input type="password" name="new_password" id="resetPw" required
            class="<?= INPUT_CLASS ?>"
            placeholder="At least 8 characters" autocomplete="new-password">
        </div>

        <div>
          <label for="resetPwConf" class="mb-1.5 block text-xs font-semibold text-base-content/70">
            Confirm new password <span class="text-error">*</span>
          </label>
          <input type="password" name="confirm_password" id="resetPwConf" required
            class="<?= INPUT_CLASS ?>"
            placeholder="Re-enter password" autocomplete="new-password">
          <p id="resetMatchHint" class="mt-1 min-h-[16px] text-[11px] font-medium"></p>
        </div>

        <ul id="resetRules" class="flex flex-wrap gap-1.5"></ul>

        <p class="rounded-xl bg-base-200 px-3 py-2 text-[11.5px] leading-relaxed text-base-content/60">
          Share it privately (call or message) — never over plain email.
        </p>
      </div>
    </form>

    <div class="modal-action m-0 flex flex-col-reverse gap-2 border-t border-base-200/80 bg-base-200/40 px-5 py-4 sm:flex-row sm:justify-end">
      <button type="button" onclick="resetModal.close()"
        class="btn btn-ghost h-10 min-h-10 w-full rounded-full px-5 text-sm font-semibold text-base-content/70 hover:bg-base-200 hover:text-base-content sm:w-auto">
        Cancel
      </button>
      <button type="submit" form="resetForm"
        class="btn btn-primary h-10 min-h-10 w-full rounded-full px-6 text-sm font-semibold shadow-pop sm:w-auto">
        Update password
      </button>
    </div>
  </div>

  <form method="dialog" class="modal-backdrop bg-black/40 backdrop-blur-sm">
    <button type="submit">close</button>
  </form>
</dialog>

<!-- ═══════════ DELETE CONFIRMATION MODAL ═══════════ -->
<dialog id="deleteModal" class="modal">
  <div class="modal-box w-[calc(100%-2rem)] max-w-md rounded-3xl border border-base-300/70 bg-base-100 p-0 shadow-xl">

    <div class="flex items-center justify-between gap-3 px-5 pb-1 pt-5">
      <div class="min-w-0">
        <h3 class="font-head text-[16px] font-bold tracking-tight text-base-content">Remove team member?</h3>
        <p class="mt-0.5 text-[11.5px] text-base-content/50">This action is permanent.</p>
      </div>
      <button type="button" onclick="deleteModal.close()"
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
          You're about to delete <strong id="deleteUserName" class="text-error"></strong>'s account.
        </p>
        <ul class="mt-2.5 space-y-1.5 text-[12px] leading-relaxed text-base-content/70">
          <li class="flex items-start gap-2">
            <span class="mt-[7px] size-1 shrink-0 rounded-full bg-error/60" aria-hidden="true"></span>
            They lose admin access immediately.
          </li>
          <li class="flex items-start gap-2">
            <span class="mt-[7px] size-1 shrink-0 rounded-full bg-error/60" aria-hidden="true"></span>
            This cannot be undone.
          </li>
        </ul>
        <p class="mt-3 text-[11px] text-base-content/50">
          Just away temporarily? Pause them instead with the status switch.
        </p>
      </div>
    </div>

    <div class="modal-action m-0 flex flex-col-reverse gap-2 border-t border-base-200/80 bg-base-200/40 px-5 py-4 sm:flex-row sm:justify-end">
      <button type="button" onclick="deleteModal.close()"
        class="btn h-10 min-h-10 w-full rounded-full border border-base-300 bg-base-100 px-5 text-sm font-semibold text-base-content/80 hover:bg-base-200 hover:text-base-content sm:w-auto">
        Cancel
      </button>
      <form method="POST" id="confirmDeleteUserForm" class="w-full sm:w-auto">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="user_id" id="deleteUserId">
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
  /* ── Password rules & live checklist (compact chips) ─────── */
  var PW_SEQ_RE = /(012|123|234|345|456|567|678|789|890|abc|bcd|cde|def|efg|fgh|ghi|hij|ijk|jkl|klm|lmn|mno|nop|opq|pqr|qrs|rst|stu|tuv|uvw|vwx|wxy|xyz)/i;

  var PW_RULES = [{
      label: '8+ characters',
      test: function(v) {
        return v.length >= 8;
      }
    },
    {
      label: 'UPPERCASE letter',
      test: function(v) {
        return /[A-Z]/.test(v);
      }
    },
    {
      label: 'Number',
      test: function(v) {
        return /[0-9]/.test(v);
      }
    },
    {
      label: 'Symbol',
      test: function(v) {
        return /[^A-Za-z0-9]/.test(v);
      }
    },
    {
      label: 'No 123 / abc',
      test: function(v) {
        return !PW_SEQ_RE.test(v);
      }
    }
  ];

  function renderChecklist(ulId, val) {
    var ul = document.getElementById(ulId);
    if (!ul) return;
    ul.innerHTML = '';
    PW_RULES.forEach(function(r) {
      var ok = val.length > 0 && r.test(val);
      var li = document.createElement('li');
      li.className = 'badge badge-sm gap-1 font-medium ' +
        (ok ? 'badge-success badge-outline' :
          'badge-ghost text-base-content/50');
      li.innerHTML =
        '<svg class="size-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">' +
        (ok ?
          '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>' :
          '<circle cx="12" cy="12" r="8.5"/><path stroke-linecap="round" d="M12 8v4m0 4h.01"/>') +
        '</svg><span>' + r.label + '</span>';
      ul.appendChild(li);
    });
  }

  function setMatchHint(pwEl, cfEl, hintId) {
    var el = document.getElementById(hintId);
    if (!el || !cfEl) return;
    if (!cfEl.value) {
      el.textContent = '';
      return;
    }
    var ok = pwEl.value === cfEl.value;
    el.textContent = ok ? '\u2713 Passwords match' : '\u2715 Passwords don\u2019t match yet';
    el.className = 'mt-1 min-h-[16px] text-[11px] font-medium ' + (ok ? 'text-success' : 'text-error');
  }

  function bindPasswordPair(pwId, confId, ulId, hintId) {
    var pw = document.getElementById(pwId);
    var cf = document.getElementById(confId);
    if (!pw) return;

    function sync() {
      renderChecklist(ulId, pw.value);
      setMatchHint(pw, cf, hintId);
    }
    pw.addEventListener('input', sync);
    if (cf) cf.addEventListener('input', sync);
    sync();
  }

  /* ── Add / Edit member modal ─────────────────────────────── */
  function setRoleLock(lock) {
    var note = document.getElementById('selfRoleNote');
    if (note) {
      note.classList.toggle('hidden', !lock);
      note.classList.toggle('flex', lock);
    }
    document.querySelectorAll('#adminForm input[name="role"]').forEach(function(r) {
      r.disabled = lock;
    });
  }

  function clearPwVisuals() {
    renderChecklist('formRules', '');
    var mh = document.getElementById('matchHint');
    if (mh) mh.textContent = '';
  }

  function setPasswordVisible(show) {
    document.getElementById('pwSection').classList.toggle('hidden', !show);
    document.getElementById('pwToggle').checked = show;
  }

  function setUserModalUI(isEdit, name, email) {
    document.getElementById('userModalTitle').textContent = isEdit ? 'Edit user' : 'New user';
    document.getElementById('userSubmitLabel').textContent = isEdit ? 'Save changes' : 'Create account';

    document.getElementById('pwFieldLabel').textContent = isEdit ? 'New password' : 'Password';
    ['pwStar1', 'pwStar2'].forEach(function(id) {
      document.getElementById(id).classList.toggle('hidden', isEdit);
    });

    var pw = document.getElementById('pwField');
    var cf = document.getElementById('pwConfirm');
    pw.required = !isEdit;
    cf.required = !isEdit;

    document.getElementById('pwToggleWrap').classList.toggle('hidden', !isEdit);
    setPasswordVisible(!isEdit);

    var pwVal = isEdit ? '' : pw.value;
    pw.value = pwVal;
    cf.value = '';

    setRoleLock(false);
    clearPwVisuals();
  }

  function showModalFromTop(modal) {
    modal.showModal();
    var body = modal.querySelector('.overflow-y-auto');
    if (body) body.scrollTop = 0;
  }

  function openUserModal() {
    document.getElementById('adminForm').reset();
    document.getElementById('formUserId').value = '0';
    setUserModalUI(false, '', '');
    showModalFromTop(userModal);
    setTimeout(function() {
      document.getElementById('fName').focus();
    }, 80);
  }

  function openEditModal(btn) {
    var d = btn.dataset;
    var form = document.getElementById('adminForm');
    form.reset();

    document.getElementById('formUserId').value = d.id;
    document.getElementById('fName').value = d.name || '';
    document.getElementById('fEmail').value = d.email || '';
    var r = form.querySelector('input[name="role"][value="' + d.role + '"]');
    if (r) r.checked = true;

    setUserModalUI(true, d.name || '', d.email || '');
    setRoleLock(d.self === '1');

    showModalFromTop(userModal);
    setTimeout(function() {
      document.getElementById('fName').focus();
    }, 80);
  }

  /* ── Reset & delete modals ───────────────────────────────── */
  function openResetModal(userId, userName) {
    var modal = document.getElementById('resetModal');
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetUserName').textContent = userName;
    document.getElementById('resetForm').reset();
    document.getElementById('resetUserId').value = userId;
    modal.showModal();
    bindPasswordPair('resetPw', 'resetPwConf', 'resetRules', 'resetMatchHint');
    setTimeout(function() {
      document.getElementById('resetPw').focus();
    }, 80);
  }

  function openDeleteModal(userId, userName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
    document.getElementById('deleteModal').showModal();
  }

  /* ── Table search & segmented filters ────────────────────── */
  function bindTableFilters() {
    var searchEl = document.getElementById('userSearch');
    var rows = Array.prototype.slice.call(document.querySelectorAll('#usersTbody tr[data-search]'));
    var meta = document.getElementById('tableMeta');
    if (!rows.length) return;

    var state = {
      q: '',
      role: '',
      status: ''
    };
    var ACTIVE = ['bg-base-100', 'text-base-content', 'shadow-sm'];
    var IDLE = ['text-base-content/55'];

    function setSeg(group, btn) {
      group.querySelectorAll('.seg-btn').forEach(function(b) {
        var on = b === btn;
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
        ACTIVE.forEach(function(c) {
          b.classList.toggle(c, on);
        });
        IDLE.forEach(function(c) {
          b.classList.toggle(c, !on);
        });
      });
    }

    function wire(groupId, key) {
      var group = document.getElementById(groupId);
      if (!group) return;
      group.querySelectorAll('.seg-btn').forEach(function(b) {
        b.addEventListener('click', function() {
          setSeg(group, b);
          state[key] = b.dataset.value || '';
          apply();
        });
      });
    }

    function apply() {
      var q = state.q.trim().toLowerCase();
      var shown = 0;
      rows.forEach(function(tr) {
        var ok = (!q || tr.dataset.search.indexOf(q) !== -1) &&
          (!state.role || tr.dataset.role === state.role) &&
          (state.status === '' || tr.dataset.active === state.status);
        tr.classList.toggle('hidden', !ok);
        if (ok) shown++;
      });
      var noMatch = document.getElementById('noMatchRow');
      if (noMatch) noMatch.classList.toggle('hidden', shown > 0);
      if (meta) meta.textContent = 'Showing ' + shown + ' of ' + rows.length + ' member' + (rows.length === 1 ? '' : 's');
    }

    wire('roleChips', 'role');
    wire('statusChips', 'status');

    if (searchEl) searchEl.addEventListener('input', function() {
      state.q = this.value || '';
      apply();
    });

    apply();
  }

  /* ── Init ────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function() {
    bindPasswordPair('pwField', 'pwConfirm', 'formRules', 'matchHint');

    var pwToggle = document.getElementById('pwToggle');
    if (pwToggle) {
      pwToggle.addEventListener('change', function() {
        if (this.checked) {
          document.getElementById('pwSection').classList.remove('hidden');
          setTimeout(function() {
            document.getElementById('pwField').focus();
          }, 60);
        } else {
          var pw = document.getElementById('pwField');
          var cf = document.getElementById('pwConfirm');
          pw.value = '';
          cf.value = '';
          document.getElementById('pwSection').classList.add('hidden');
          clearPwVisuals();
        }
      });
    }

    bindTableFilters();

    <?php if ($isPostFailure || $editUser): ?>
      /* Reopen the modal after a failed save, or when arriving via ?edit= link.
         Field values were already rendered server-side. */
      setUserModalUI(<?= $autoOpenUserId > 0 ? "true" : "false" ?>,
        <?= json_encode($prefillName, $JSON_FLAGS) ?>,
        <?= json_encode($prefillEmail, $JSON_FLAGS) ?>);
      <?php if ($prefillSelf): ?>
        setRoleLock(true);
      <?php endif; ?>
      <?php if ($isPostFailure && (int) ($_POST["user_id"] ?? 0) > 0): ?>
        /* Failed edit save with password errors → reveal the password block again */
        setPasswordVisible(true);
      <?php endif; ?>
      showModalFromTop(document.getElementById('userModal'));
    <?php endif; ?>
  });
</script>

<?php include dirname(__DIR__) . "/includes/footer.php"; ?>