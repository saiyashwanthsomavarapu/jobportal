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
  $action = $_POST["action"] ?? "";
  $targetId = (int) ($_POST["user_id"] ?? 0);

  /* CREATE / UPDATE */
  if ($action === "save") {
    $name = trim($_POST["name"] ?? "");
    $email = strtolower(trim($_POST["email"] ?? ""));
    $role = trim($_POST["role"] ?? "admin");
    $isActive = isset($_POST["is_active"]) ? 1 : 0;
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
            /* INSERT new admin */
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare(
              "
                            INSERT INTO admin_users (name, email, password, role, is_active)
                            VALUES (?, ?, ?, ?, ?)
                        "
            )->execute([$name, $email, $hash, $role, $isActive]);
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
                /* Update with new password */
                $hash = password_hash(
                  $password,
                  PASSWORD_BCRYPT
                );
                $pdo->prepare(
                  "
                                    UPDATE admin_users
                                    SET name=?, email=?, password=?, role=?, is_active=?
                                    WHERE id=?
                                "
                )->execute([
                  $name,
                  $email,
                  $hash,
                  $role,
                  $isActive,
                  $targetId,
                ]);
              } else {
                /* Update without changing password */
                $pdo->prepare(
                  "
                                    UPDATE admin_users
                                    SET name=?, email=?, role=?, is_active=?
                                    WHERE id=?
                                "
                )->execute([
                  $name,
                  $email,
                  $role,
                  $isActive,
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
        $errors[] = "Database error: " . $ex->getMessage();
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
        flash("error", "Error: " . $ex->getMessage());
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
        flash("error", "Cannot delete: " . $ex->getMessage());
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
        flash("error", "Error: " . $ex->getMessage());
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

/* Load password reset target */

$resetUser = null;
$resetId = (int) ($_GET["reset"] ?? 0);
if ($resetId > 0) {
  $stmt = db()->prepare(
    "SELECT id, name, email FROM admin_users WHERE id = ?"
  );
  $stmt->execute([$resetId]);
  $resetUser = $stmt->fetch();
}

/* Load all admin users */

try {
  $admins = db()
    ->query("SELECT * FROM admin_users ORDER BY role ASC, name ASC")
    ->fetchAll();
} catch (Exception $ex) {
  $admins = [];
}

// Role badge helper — Tailwind classes, tokens matched to includes/header.php's tailwind.config
function roleBadge(string $role): string
{
  $map = [
    "superadmin" => "bg-accent-dark text-white",
    "admin" => "bg-blue-100 text-blue-700 border border-blue-300",
    "editor" => "bg-green-100 text-green-700 border border-green-300",
  ];
  $labels = [
    "superadmin" => "Superadmin",
    "admin" => "Admin",
    "editor" => "Editor",
  ];
  $classes = $map[$role] ?? "bg-slate-100 text-slate-600 border border-slate-300";
  $label = $labels[$role] ?? ucfirst($role);
  return '<span class="inline-flex items-center rounded-full text-[11px] font-semibold px-2.5 py-1 ' .
    $classes .
    '">' .
    e($label) .
    "</span>";
}

include dirname(__DIR__) . "/includes/header.php";
?>

<style>
  /* Modal show/hide toggle + entrance animation — same mechanism as before, now light-themed */
  .modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .4);
    z-index: 200;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(3px);
  }

  .modal-overlay.open {
    display: flex;
  }

  .modal-box {
    animation: modalIn .18s ease;
  }

  @keyframes modalIn {
    from {
      transform: translateY(-14px);
      opacity: 0;
    }

    to {
      transform: translateY(0);
      opacity: 1;
    }
  }

  /* Toggle switch */
  .toggle-track {
    transition: background-color .18s ease;
  }

  .toggle-thumb {
    transition: transform .18s ease;
  }
</style>

<!-- ══════════════ LIGHT CANVAS ══════════════
    Breaks out of the light shell's padding (header.php's main body uses px-7 py-6 / max-md:px-4 py-4)
    so this page can render as a self-contained light panel while the sidebar/topbar stay light. -->
<div class="min-w-0 space-y-6">

  <?php
  $isEditPage = (bool) $editUser;
  $crumbLabel = $isEditPage ? "Edit User" : "Add User";
  ?>

  <!-- Top row: breadcrumb + actions -->
  <div class="flex items-center justify-between flex-wrap gap-3 mb-6">
    <div class="text-[13px] text-slate-500 flex items-center gap-2">
      <a href="<?= ADMIN_URL ?>/pages/admins.php" class="hover:text-slate-700 transition-colors">Admin Users</a>
      <span class="text-slate-300">/</span>
      <span class="text-slate-900"><?= e($crumbLabel) ?></span>
    </div>
    <div class="flex items-center gap-2.5">
      <?php if ($isEditPage): ?>
        <a href="<?= ADMIN_URL ?>/pages/admins.php"
          class="flex items-center gap-1.5 text-[12.5px] font-semibold text-slate-600 border border-slate-300 rounded-lg px-3.5 py-2 hover:bg-slate-100 hover:text-slate-900 transition-colors">
          <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
          Cancel
        </a>
      <?php endif; ?>
      <button type="submit" form="adminForm"
        class="flex items-center gap-1.5 bg-[#2f6fc4] text-white text-[12.5px] font-bold rounded-lg px-4 py-2 hover:bg-[#3a7cd6] transition-colors shadow-[0_4px_16px_rgba(47,111,196,.25)]">
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v6m3-3h-6M6.75 21v-2.25a3.375 3.375 0 013.375-3.375h1.5m6.375-3.375a4.125 4.125 0 11-8.25 0 4.125 4.125 0 018.25 0zM3 21v-2.25a3.375 3.375 0 013.375-3.375h1.5" />
        </svg>
        <?= $isEditPage ? "Update User" : "Create User" ?>
      </button>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="flex items-start gap-2.5 px-4 py-3 rounded-xl mb-5 text-[13px] font-medium bg-red-50 border border-red-200 text-red-800">
      <svg class="h-[18px] w-[18px] flex-shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
      </svg>
      <div><?= implode("<br>", array_map("e", $errors)) ?></div>
    </div>
  <?php endif; ?>

  <form method="POST" id="adminForm" novalidate>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="user_id" value="<?= $editUser
                                                  ? $editUser["id"]
                                                  : 0 ?>">

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-5 items-start">

      <!-- ══ LEFT COLUMN ══ -->
      <div class="space-y-5 min-w-0">

        <!-- Profile card -->
        <div class="rounded-2xl border border-base-300 bg-base-100 p-6">
          <div class="flex items-center gap-2.5 mb-5">
            <div class="<?= SVG_DIV ?>">
              <svg class="<?= SVG_ICON ?>" fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </div>
            <span class="font-head text-[15px] font-bold text-slate-900">Profile</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Full Name -->
            <fieldset class="fieldset">
              <legend class="fieldset-legend text-sm font-medium">
                Full name <span class="text-error">*</span>
              </legend>

              <input
                type="text"
                name="name"
                class="input w-full rounded-lg border-base-300 bg-base-100 text-sm
             focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                placeholder="e.g. John Smith"
                value="<?= e($editUser["name"] ?? ($_POST["name"] ?? "")) ?>"
                required
                autofocus />
            </fieldset>


            <!-- Email -->
            <fieldset class="fieldset">
              <legend class="fieldset-legend text-sm font-medium">
                Email <span class="text-error">*</span>
              </legend>

              <input
                type="email"
                name="email"
                class="<?= INPUT_CLASS ?>"
                placeholder="john@acceloninc.com"
                value="<?= e($editUser["email"] ?? ($_POST["email"] ?? "")) ?>"
                required />

              <p class="label w-full max-w-full whitespace-normal break-words text-xs leading-5 text-base-content/50">
                Only <strong class="text-slate-700">@acceloninc.com</strong> addresses are allowed.
              </p>
            </fieldset>
          </div>
        </div>

        <!-- Access card -->
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4 sm:p-5 lg:p-6">

          <!-- Section Header -->
          <div class="mb-4 sm:mb-5 flex items-center gap-2.5">
            <div class="<?= SVG_DIV ?>">
              <svg class="<?= SVG_ICON ?>"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.75">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
              </svg>
            </div>

            <span class="font-head text-sm sm:text-[15px] font-bold text-slate-900">
              Access
            </span>
          </div>

          <p class="mb-4 -mt-1 text-xs leading-relaxed text-slate-500 sm:text-[11.5px]">
            <?= $editUser
              ? "Leave blank to keep the current password."
              : "Set a temporary password for this account." ?>
          </p>

          <!-- Password fields -->
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

            <!-- Password -->
            <fieldset class="fieldset min-w-0">
              <legend class="fieldset-legend text-xs sm:text-sm">
                <?= $editUser ? "New password" : "Password" ?>

                <?php if (!$editUser): ?>
                  <span class="text-error">*</span>
                <?php endif; ?>
              </legend>

              <input
                type="password"
                name="password"
                id="pwField"
                class="<?= INPUT_CLASS ?>"
                placeholder="Min. 8 characters"
                autocomplete="new-password"
                oninput="checkStrength(this.value)"
                <?= !$editUser ? "required" : "" ?>>

              <!-- Password strength -->
              <div class="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-slate-200">
                <div
                  id="pwBar"
                  class="h-full w-0 rounded-full bg-primary transition-all">
                </div>
              </div>

              <p
                id="pwHint"
                class="mt-1 min-h-[16px] text-[11px] leading-tight text-base-content/60">
              </p>
            </fieldset>

            <!-- Confirm Password -->
            <fieldset class="fieldset min-w-0">
              <legend class="fieldset-legend text-xs sm:text-sm">
                Confirm password

                <?php if (!$editUser): ?>
                  <span class="text-error">*</span>
                <?php endif; ?>
              </legend>

              <input
                type="password"
                name="password_confirm"
                id="pwConfirm"
                class="<?= INPUT_CLASS ?>"
                placeholder="Re-enter password"
                autocomplete="new-password"
                oninput="checkMatch()"
                <?= !$editUser ? "required" : "" ?>>

              <p
                id="matchHint"
                class="mt-1 min-h-[16px] text-[11px] leading-tight">
              </p>
            </fieldset>

          </div>
        </div>

      </div>

      <!-- ══ RIGHT RAIL ══ -->
      <div class="space-y-5 min-w-0">

        <!-- Account Settings Card -->
        <section class="card border border-base-300 bg-base-100 shadow-sm">
          <div class="card-body p-4 sm:p-6">

            <!-- Header -->
            <div class="flex items-center gap-3 mb-5">
              <div class="<?= SVG_DIV ?>">
                <svg class="<?= SVG_ICON ?>"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="1.75">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </div>

              <h2 class="text-sm sm:text-[15px] font-bold text-base-content">
                Account settings
              </h2>
            </div>

            <!-- Account Active -->
            <fieldset class="mb-5">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                <div class="min-w-0">
                  <legend class="text-sm font-medium text-base-content">
                    Account active
                  </legend>

                  <p class="mt-0.5 text-xs text-base-content/60">
                    Inactive users can't log in
                  </p>
                </div>

                <label class="inline-flex cursor-pointer items-center">
                  <input
                    type="checkbox"
                    name="is_active"
                    id="isActive"
                    value="1"
                    class="toggle toggle-sm shrink-0"
                    <?= ($editUser
                      ? $editUser["is_active"]
                      : 1)
                      ? "checked"
                      : "" ?> />
                </label>

              </div>
            </fieldset>

            <!-- Role -->
            <fieldset class="form-control w-full">
              <?php
              $savedRole = $editUser["role"] ?? ($_POST["role"] ?? "admin");
              ?>

              <legend class="mb-1.5 text-xs sm:text-[12.5px] text-base-content/70">
                Role
              </legend>

              <select
                name="role"
                class="<?= SELECT_CLASS ?>"
                required>
                <?php
                foreach (
                  [
                    "superadmin" => "Superadmin",
                    "admin"      => "Admin",
                    // "editor" => "Editor",
                  ] as $val => $label
                ):
                ?>
                  <option
                    value="<?= htmlspecialchars($val) ?>"
                    <?= $savedRole === $val ? "selected" : "" ?>>
                    <?= htmlspecialchars($label) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </fieldset>

          </div>
        </section>


        <!-- Role Guide Card -->
        <section class="card border border-base-300 bg-base-100 shadow-sm">
          <div class="card-body p-4 sm:p-6">

            <!-- Header -->
            <div class="flex items-center gap-3 mb-4">
              <div class="<?= SVG_DIV ?>">
                <svg class="<?= SVG_ICON ?>"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="1.75">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
              </div>

              <h2 class="text-sm sm:text-[15px] font-bold text-base-content">
                Role guide
              </h2>
            </div>
            <!-- Role Information -->
            <div class="space-y-3">
              <!-- Superadmin -->
              <div class="flex items-start gap-3">
                <span
                  class="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary"
                  aria-hidden="true"></span>
                <p class="text-xs sm:text-[12.5px] leading-5 text-base-content/70">
                  <strong class="font-semibold text-base-content">
                    Superadmin
                  </strong>
                  <span aria-hidden="true"> — </span>
                  full access, can manage other admin users
                </p>
              </div>
              <!-- Admin -->
              <div class="flex items-start gap-3">
                <span
                  class="mt-1.5 size-1.5 shrink-0 rounded-full bg-base-content/40"
                  aria-hidden="true"></span>
                <p class="text-xs sm:text-[12.5px] leading-5 text-base-content/70">
                  <strong class="font-semibold text-base-content">
                    Admin
                  </strong>
                  <span aria-hidden="true"> — </span>
                  manages jobs, clients, and settings
                </p>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </form>

  <!-- ══ USERS TABLE ══ -->
  <div class="bg-slate-50 border border-slate-200 rounded-2xl overflow-hidden mt-5" style="background-color:#f8fafc !important">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
      <span class="font-head text-[15px] font-bold text-slate-900">All Admin Users</span>
      <span class="text-[12px] text-slate-500"><?= count($admins) ?> total</span>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-[13px]">
        <thead class="bg-slate-100">
          <tr>
            <th class="text-left px-6 py-3 text-[11px] font-semibold text-slate-600 uppercase tracking-wide whitespace-nowrap">User</th>
            <th class="text-left px-6 py-3 text-[11px] font-semibold text-slate-600 uppercase tracking-wide whitespace-nowrap">Email</th>
            <th class="text-left px-6 py-3 text-[11px] font-semibold text-slate-600 uppercase tracking-wide whitespace-nowrap">Role</th>
            <th class="text-left px-6 py-3 text-[11px] font-semibold text-slate-600 uppercase tracking-wide whitespace-nowrap">Status</th>
            <th class="text-left px-6 py-3 text-[11px] font-semibold text-slate-600 uppercase tracking-wide whitespace-nowrap">Last login</th>
            <th class="text-left px-6 py-3 text-[11px] font-semibold text-slate-600 uppercase tracking-wide whitespace-nowrap">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($admins)): ?>
            <tr>
              <td colspan="6" class="text-center text-slate-500 py-8">No admin users found.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($admins as $u):

              $isSelf = (int) $u["id"] === (int) $_SESSION["admin_id"];
              $roleColors = [
                "superadmin" => "bg-[#2f6fc4] text-white",
                "admin" =>
                "bg-slate-100 text-slate-700 border border-slate-300",
                "editor" =>
                "bg-green-100 text-green-700 border border-green-300",
              ];
              $roleLabels = [
                "superadmin" => "Superadmin",
                "admin" => "Admin",
                "editor" => "Editor",
              ];
              $rc =
                $roleColors[$u["role"]] ??
                "bg-slate-100 text-slate-500 border border-slate-300";
              $rl = $roleLabels[$u["role"]] ?? ucfirst($u["role"]);
            ?>
              <tr class="border-b border-slate-200 last:border-b-0 hover:bg-slate-100 transition-colors">

                <td class="px-6 py-3.5 align-middle">
                  <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center font-head text-[13px] font-bold text-white flex-shrink-0
                            <?= $u["is_active"]
                              ? "bg-[#2f6fc4]"
                              : "bg-slate-300" ?>">
                      <?= strtoupper(substr($u["name"], 0, 1)) ?>
                    </div>
                    <div class="min-w-0">
                      <strong class="text-[13px] text-slate-900 font-semibold">
                        <?= e($u["name"]) ?>
                        <?php if ($isSelf): ?>
                          <span class="inline-flex items-center bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-0.5 rounded-full ml-1.5 align-middle">You</span>
                        <?php endif; ?>
                      </strong>
                      <div class="text-[11px] text-slate-500"> Since <?= date("M Y", strtotime($u["created_at"])) ?> </div>
                    </div>
                  </div>
                </td>

                <td class="px-6 py-3.5 align-middle text-slate-600 text-[13px]"><?= e($u["email"]) ?></td>

                <td class="px-6 py-3.5 align-middle">
                  <span class="inline-flex items-center rounded-full text-[11px] font-semibold px-2.5 py-1 <?= $rc ?>"><?= e($rl) ?></span>
                </td>

                <td class="px-6 py-3.5 align-middle">
                  <?php if ($u["is_active"]): ?>
                    <span class="inline-flex items-center gap-1.5 bg-green-100 text-green-700 border border-green-300 rounded-full text-[11px] font-semibold px-2.5 py-1">
                      <span class="w-1.5 h-1.5 rounded-full bg-green-600"></span> Active
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-500 border border-slate-300 rounded-full text-[11px] font-semibold px-2.5 py-1">
                      <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Inactive
                    </span>
                  <?php endif; ?>
                </td>

                <td class="px-6 py-3.5 align-middle text-[12px] text-slate-500">
                  <?= $u["last_login"]
                    ? date("d M Y, g:i A", strtotime($u["last_login"]))
                    : '<span class="text-slate-400">Never</span>' ?>
                </td>

                <td class="px-6 py-3.5 align-middle">
                  <div class="flex items-center gap-1.5 flex-wrap">

                    <a href="<?= ADMIN_URL ?>/pages/admins.php?edit=<?= $u["id"] ?>" title="Edit"
                      class="flex items-center justify-center w-8 h-8 rounded-lg border border-slate-300 text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors">
                      <svg class="h-[15px] w-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                      </svg>
                    </a>

                    <button type="button" title="Reset Password"
                      onclick="openResetModal(<?= $u["id"] ?>, '<?= e(addslashes($u["name"])) ?>')"
                      class="flex items-center justify-center w-8 h-8 rounded-lg border border-slate-300 text-slate-500 hover:text-[#2f6fc4] hover:bg-blue-50 transition-colors">
                      <svg class="h-[15px] w-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                      </svg>
                    </button>

                    <?php if (!$isSelf): ?>
                      <form method="POST" class="inline">
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="user_id" value="<?= $u["id"] ?>">
                        <button type="submit" title="<?= $u["is_active"]
                                                        ? "Deactivate"
                                                        : "Activate" ?>"
                          onclick="return confirm('<?= $u["is_active"] ? "Deactivate" : "Activate" ?> this user?')"
                          class="flex items-center justify-center w-8 h-8 rounded-lg border border-slate-300 text-slate-500 hover:text-amber-600 hover:bg-amber-50 transition-colors">
                          <?php if ($u["is_active"]): ?>
                            <svg class="h-[15px] w-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5" />
                            </svg>
                          <?php else: ?>
                            <svg class="h-[15px] w-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z" />
                            </svg>
                          <?php endif; ?>
                        </button>
                      </form>

                      <form method="POST" class="inline"
                        onsubmit="return confirm('Permanently delete <?= e(addslashes($u["name"])) ?>? This cannot be undone.')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" value="<?= $u["id"] ?>">
                        <button type="submit" title="Delete"
                          class="flex items-center justify-center w-8 h-8 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition-colors">
                          <svg class="h-[15px] w-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                          </svg>
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="text-[11px] text-slate-400 px-1">—</span>
                    <?php endif; ?>

                  </div>
                </td>
              </tr>
            <?php
            endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- RESET PASSWORD MODAL (light) -->
<div class="modal-overla" id="resetModal">
  <div class="modal-box bg-white border border-slate-200 rounded-2xl shadow-2xl p-7 w-full max-w-[440px] relative mx-4 text-slate-900" style="background-color:#ffffff !important">
    <button onclick="closeResetModal()"
      class="absolute top-4 right-4 w-7 h-7 flex items-center justify-center rounded-md border border-slate-300 text-slate-500 hover:text-red-600 hover:border-red-300 transition-colors">
      <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
    <div class="font-head text-[15px] font-bold mb-5 flex items-center gap-2 text-slate-900">
      <svg class="h-4 w-4 text-[#2f6fc4]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
      </svg>
      Reset Password
    </div>
    <p class="text-[13px] text-slate-600 mb-4">
      Setting new password for: <strong id="resetUserName" class="text-slate-900"></strong>
    </p>
    <form method="POST" id="resetForm" class="space-y-3.5">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="user_id" id="resetUserId" value="">

      <div>
        <label class="block text-[12px] text-slate-600 mb-1.5">New Password <span class="text-[#2f6fc4]">*</span></label>
        <input type="password" name="new_password" id="resetPw"
          class="w-full bg-white border border-slate-300 rounded-lg px-3.5 py-2.5 text-[13.5px] text-slate-900 placeholder-slate-400 outline-none focus:border-[#2f6fc4] focus:ring-2 focus:ring-[#2f6fc4]/20 transition" style="background-color:#ffffff !important"
          placeholder="Min. 8 characters" required
          oninput="checkResetStrength(this.value)">
        <div class="h-1 rounded-full bg-slate-200 overflow-hidden mt-1.5">
          <div class="h-full w-0 rounded-full transition-all bg-[#2f6fc4]" id="resetPwBar"></div>
        </div>
        <div class="text-[11px] text-slate-500 mt-1" id="resetPwHint"></div>
      </div>

      <div>
        <label class="block text-[12px] text-slate-600 mb-1.5">Confirm New Password <span class="text-[#2f6fc4]">*</span></label>
        <input type="password" name="confirm_password" id="resetPwConf"
          class="w-full bg-white border border-slate-300 rounded-lg px-3.5 py-2.5 text-[13.5px] text-slate-900 placeholder-slate-400 outline-none focus:border-[#2f6fc4] focus:ring-2 focus:ring-[#2f6fc4]/20 transition" style="background-color:#ffffff !important"
          placeholder="Re-enter new password" required
          oninput="checkResetMatch()">
        <div class="text-[11px] mt-1" id="resetMatchHint"></div>
      </div>

      <div class="flex gap-2.5 pt-1">
        <button type="submit"
          class="flex-1 flex items-center justify-center gap-2 bg-[#2f6fc4] text-white rounded-lg font-head text-[13px] font-bold tracking-wide py-2.5 shadow-[0_4px_16px_rgba(47,111,196,.25)] hover:bg-[#3a7cd6] transition-all">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
          </svg>
          Reset Password
        </button>
        <button type="button" onclick="closeResetModal()"
          class="px-4 py-2.5 rounded-lg border border-slate-300 text-slate-600 text-[13px] font-semibold hover:bg-slate-100 hover:text-slate-900 transition-colors">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  function pwStrength(val) {
    if (!val) return {
      score: 0,
      label: '',
      color: '',
      hints: []
    };
    const hints = [];
    let score = 0;
    if (val.length >= 8) {
      score++;
    } else {
      hints.push('Min. 8 characters');
    }
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val)) {
      score++;
    } else {
      hints.push('One uppercase letter');
    }
    if (/[0-9]/.test(val)) {
      score++;
    } else {
      hints.push('One number');
    }
    if (/[^a-zA-Z0-9]/.test(val)) {
      score++;
    } else {
      hints.push('One special character (!@#$…)');
    }
    if (/(012|123|234|345|456|567|678|789|890|abc|bcd|cde|def|efg|fgh|ghi|hij|ijk|jkl|klm|lmn|mno|nop|opq|pqr|qrs|rst|stu|tuv|uvw|vwx|wxy|xyz)/i.test(val)) {
      score = Math.max(0, score - 1);
      hints.push('No sequential series (123, abc…)');
    }
    const levels = [{
        color: '#dc2626',
        label: 'Too weak',
        pct: '20%'
      },
      {
        color: '#d97706',
        label: 'Weak',
        pct: '40%'
      },
      {
        color: '#d97706',
        label: 'Fair',
        pct: '60%'
      },
      {
        color: '#16a34a',
        label: 'Strong',
        pct: '80%'
      },
      {
        color: '#16a34a',
        label: 'Very strong',
        pct: '100%'
      },
    ];
    return {
      ...levels[Math.min(score, 4)],
      hints
    };
  }

  function checkStrength(val) {
    const r = pwStrength(val);
    document.getElementById('pwBar').style.cssText = `width:${r.pct||'0%'};background:${r.color}`;
    const hintEl = document.getElementById('pwHint');
    if (!val) {
      hintEl.innerHTML = '';
      checkMatch();
      return;
    }
    if (r.hints.length) {
      hintEl.innerHTML = '<span style="color:' + r.color + '">' + r.label + '</span>' +
        ' &mdash; still needs: <span style="color:#dc2626">' + r.hints.join(', ') + '</span>';
    } else {
      hintEl.innerHTML = '<span style="color:' + r.color + '">✓ ' + r.label + '</span>';
    }
    checkMatch();
  }

  function checkMatch() {
    const pw = document.getElementById('pwField').value;
    const cfm = document.getElementById('pwConfirm').value;
    const el = document.getElementById('matchHint');
    if (!cfm) {
      el.textContent = '';
      return;
    }
    if (pw === cfm) {
      el.textContent = '✓ Passwords match';
      el.style.color = '#16a34a';
    } else {
      el.textContent = '✕ Passwords do not match';
      el.style.color = '#dc2626';
    }
  }

  function openResetModal(id, name) {
    document.getElementById('resetUserId').value = id;
    document.getElementById('resetUserName').textContent = name;
    document.getElementById('resetPw').value = '';
    document.getElementById('resetPwConf').value = '';
    document.getElementById('resetPwBar').style.cssText = 'width:0';
    document.getElementById('resetPwHint').textContent = '';
    document.getElementById('resetMatchHint').textContent = '';
    document.getElementById('resetModal').classList.add('open');
    setTimeout(() => document.getElementById('resetPw').focus(), 100);
  }

  function closeResetModal() {
    document.getElementById('resetModal').classList.remove('open');
  }
  document.getElementById('resetModal').addEventListener('click', function(e) {
    if (e.target === this) closeResetModal();
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeResetModal();
  });

  function checkResetStrength(val) {
    const r = pwStrength(val);
    document.getElementById('resetPwBar').style.cssText = `width:${r.pct||'0%'};background:${r.color}`;
    const hintEl = document.getElementById('resetPwHint');
    if (!val) {
      hintEl.innerHTML = '';
      checkResetMatch();
      return;
    }
    if (r.hints.length) {
      hintEl.innerHTML = '<span style="color:' + r.color + '">' + r.label + '</span>' +
        ' &mdash; still needs: <span style="color:#dc2626">' + r.hints.join(', ') + '</span>';
    } else {
      hintEl.innerHTML = '<span style="color:' + r.color + '">✓ ' + r.label + '</span>';
    }
    checkResetMatch();
  }

  function checkResetMatch() {
    const pw = document.getElementById('resetPw').value;
    const cfm = document.getElementById('resetPwConf').value;
    const el = document.getElementById('resetMatchHint');
    if (!cfm) {
      el.textContent = '';
      return;
    }
    if (pw === cfm) {
      el.textContent = '✓ Match';
      el.style.color = '#16a34a';
    } else {
      el.textContent = '✕ No match';
      el.style.color = '#dc2626';
    }
  }

  <?php if ($editUser): ?>
    document.addEventListener('DOMContentLoaded', () => {
      document.getElementById('adminForm').scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    });
  <?php endif; ?>
</script>

<?php include dirname(__DIR__) . "/includes/footer.php"; ?>