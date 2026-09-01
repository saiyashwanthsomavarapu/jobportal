<?php
// admin/pages/admins.php
require_once dirname(__DIR__) . "/auth.php";
require_once dirname(__DIR__) . "/utils/classes.php";
require_once dirname(__DIR__) . "/DB/queries.php";
/* Admins can manage standard accounts; only superadmins can manage superadmins. */
$currentAdminRole = (string) ($currentAdmin['role'] ?? '');
$canManageSuperadmins = $currentAdminRole === 'superadmin';

if (!in_array($currentAdminRole, ['superadmin', 'admin'], true)) {
  flash("error", "Access denied. Admin access is required.");
  redirect(ADMIN_URL . "/index.php");
}

$pageTitle = "Admin Users";
$breadcrumbs = [["Dashboard", ADMIN_URL . "/index.php"], ["Admin Users", null]];

$errors = [];
$oldForm = null;
$successMessage = "";
$errorMessage = "";
$warnMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "GET") {
  $savedErrors = flash('admins_save_errors');
  if ($savedErrors) {
    $errors = json_decode($savedErrors, true) ?: [];
    $oldForm = json_decode(flash('admins_old_form') ?: '{}', true) ?: null;
  }
  $successMessage = flash('success') ?: "";
  $errorMessage = flash('error') ?: "";
  $warnMessage = flash('warn') ?: "";
}

/* Handle POST actions */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (!validCsrfToken($_POST['csrf_token'] ?? null)) {
    flash('error', 'Your session expired. Please try again.');
    redirect(ADMIN_URL . '/pages/admins.php');
  }
  $action = $_POST["action"] ?? "";
  $targetId = (int) ($_POST["user_id"] ?? 0);

  if ($targetId > 0 && !$canManageSuperadmins) {
    if (getAdminUserRoleById($targetId) === 'superadmin') {
      flash('error', 'Only a superadmin can manage a superadmin account.');
      redirect(ADMIN_URL . '/pages/admins.php');
    }
  }

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
    $allowedRoles = $canManageSuperadmins
      ? ["superadmin", "admin", "editor"]
      : ["admin", "editor"];
    if (!in_array($role, $allowedRoles, true)) {
      $errors[] = "Invalid role.";
    }
    if ($targetId === (int) $_SESSION['admin_id'] && !$isActive) {
      $errors[] = 'You cannot deactivate your own account.';
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
        /* Check email uniqueness */
        if (adminEmailExistsForOtherUser($email, $targetId ?: 0)) {
          $errors[] =
            "Email address is already in use by another admin.";
        } else {
          if ($targetId === 0) {
            /* INSERT new admin */
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $newAdminId = createAdminUser($name, $email, $hash, $role, $isActive);
            logActivity(
              "create_admin",
              "admin_user",
              $newAdminId,
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
              $role !== $currentAdminRole
            ) {
              $errors[] = "You cannot change your own role.";
            } else {
              if ($password !== "") {
                /* Update with new password */
                $hash = password_hash(
                  $password,
                  PASSWORD_BCRYPT
                );
                updateAdminUserWithPassword($targetId, $name, $email, $hash, $role, $isActive);
              } else {
                /* Update without changing password */
                updateAdminUser($targetId, $name, $email, $role, $isActive);
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
        error_log('Admin save failed: ' . $ex->getMessage());
        $errors[] = "The admin account could not be saved.";
      }
    }

    if (!empty($errors)) {
      flash('admins_save_errors', json_encode($errors));
      flash(
        'admins_old_form',
        json_encode([
          'name' => $name,
          'email' => $email,
          'role' => $role,
          'is_active' => $isActive,
        ])
      );
      redirect(
        $targetId > 0
          ? ADMIN_URL . "/pages/admins.php?edit=" . $targetId
          : ADMIN_URL . "/pages/admins.php"
      );
    }
  } elseif ($action === "toggle_active" && $targetId > 0) { /* TOGGLE ACTIVE */
    if ($targetId === (int) $_SESSION["admin_id"]) {
      flash("error", "You cannot deactivate your own account.");
    } else {
      try {
        $row = getAdminUserStatusById($targetId);
        if ($row) {
          $newState = $row["is_active"] ? 0 : 1;
          setAdminUserActive($targetId, $newState);
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
        error_log('Admin status update failed: ' . $ex->getMessage());
        flash("error", "The account status could not be updated.");
      }
    }
    redirect(ADMIN_URL . "/pages/admins.php");
  } elseif ($action === "delete" && $targetId > 0) { /* DELETE */
    if ($targetId === (int) $_SESSION["admin_id"]) {
      flash("error", "You cannot delete your own account.");
    } else {
      try {
        $name = getAdminUserNameById($targetId);
        deleteAdminUserById($targetId);
        logActivity(
          "delete_admin",
          "admin_user",
          $targetId,
          $name ?? ""
        );
        flash("success", "Admin user deleted.");
      } catch (Exception $ex) {
        error_log('Admin delete failed: ' . $ex->getMessage());
        flash("error", "The admin account could not be deleted.");
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
        updateAdminUserPassword($targetId, $hash);
        logActivity("reset_password", "admin_user", $targetId);
        flash("success", "Password reset successfully.");
      } catch (Exception $ex) {
        error_log('Password reset failed: ' . $ex->getMessage());
        flash("error", "The password could not be reset.");
      }
    }
    redirect(ADMIN_URL . "/pages/admins.php");
  }
}

/* Load edit target (if ?edit= param) */

$editUser = null;
$editId = (int) ($_GET["edit"] ?? 0);
if ($editId > 0) {
  $editUser = getAdminUserById($editId);
  if (!$editUser) {
    flash("error", "User not found.");
    redirect(ADMIN_URL . "/pages/admins.php");
  }
  if (!$canManageSuperadmins && $editUser['role'] === 'superadmin') {
    flash('error', 'Only a superadmin can manage a superadmin account.');
    redirect(ADMIN_URL . '/pages/admins.php');
  }
}

/* Load password reset target */

$resetUser = null;
$resetId = (int) ($_GET["reset"] ?? 0);
if ($resetId > 0) {
  $resetUser = getAdminPasswordResetUserById($resetId);
  if ($resetUser && !$canManageSuperadmins && $resetUser['role'] === 'superadmin') {
    flash('error', 'Only a superadmin can manage a superadmin account.');
    redirect(ADMIN_URL . '/pages/admins.php');
  }
}

/* Load all admin users */

try {
  $admins = getAdminUsers($canManageSuperadmins);
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

$isEditPage = (bool) $editUser;
$showAdminForm = $isEditPage || !empty($errors);

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
  <title>Admin Users — <?= e(SITE_NAME) ?> Recruitment</title>
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
      <a href="<?= ADMIN_URL ?>/pages/clients.php">
        <svg viewBox="0 0 24 24">
          <path d="M3.75 21V6.75A1.5 1.5 0 0 1 5.25 5.25h6A1.5 1.5 0 0 1 12.75 6.75V21M3.75 21h16.5M3.75 21H2.25M20.25 21V10.5a1.5 1.5 0 0 0-1.5-1.5h-3a1.5 1.5 0 0 0-1.5 1.5V21" />
        </svg>
        Clients
      </a>
      <a class="active" href="<?= ADMIN_URL ?>/pages/admins.php">
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
      <a class="btn btn-primary btn-sm w-full text-white!" href="<?= ADMIN_URL ?>/pages/post_job.php"><span>+</span> Create Job</a>
    </div>
  </aside>

  <main class="main  <?= $postJobPageClass ?>">
    <div class="min-h-screen bg-base-200/40 px-6 py-7 max-md:px-3.5 max-md:py-6">
      <header class="flex items-center justify-between gap-4 max-md:pl-12">
        <h1 class="m-0 text-2xl font-bold leading-tight text-base-content">Admin Users</h1>
      </header>

      <?php if (!empty($errors)): ?>
        <div class="flex items-start gap-2.5 px-4 py-3 rounded-xl mb-5 text-[13px] font-medium bg-red-50 border border-red-200 text-red-800">
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
        <div role="alert" class="alert <?= $alertClass ?> alert-soft mb-5 rounded-lg text-sm">
          <?= $message ?>
        </div>
      <?php endif; ?>
      <details class="<?= $postJobCardClass ?> border-t-4 border-t-success mt-5 collapse  collapse-arrow bg-base-100 border border-base-300" name="my-accordion-det-1" open>
        <summary class="<?= $postJobHeadingClass ?> collapse-title font-semibold">
          <div class="<?= $postJobIconBaseClass ?> bg-success/10 text-success">
            <svg class="<?= SVG_ICON ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21V6.75A1.5 1.5 0 015.25 5.25h6A1.5 1.5 0 0112.75 6.75V21M3.75 21h16.5M3.75 21H2.25M20.25 21V10.5a1.5 1.5 0 00-1.5-1.5h-3a1.5 1.5 0 00-1.5 1.5V21m3-15h.008v.008H18v-.008zm0 3h.008v.008H18v-.008zm0 3h.008v.008H18v-.008zM6.75 9h.008v.008H6.75V9zm0 3h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm3-6h.008v.008H9.75V9zm0 3h.008v.008H9.75V12zm0 3h.008v.008H9.75V15z" />
            </svg>
          </div>
          <h2 class="<?= $postJobHeadingTextClass ?>">Create User</h2>
        </summary>
        <div class="collapse-content text-sm">
          <form method="POST" id="adminForm" novalidate>

            <input type="hidden" name="action" value="save">
            <input type="hidden" name="user_id" value="<?= $editUser
                                                          ? $editUser["id"]
                                                          : 0 ?>">

            <!-- <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-5 items-start"> -->
            <div class="space-y-5 min-w-0 ">
              <!-- Profile card -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Full Name -->
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">
                    Full name <span class="text-error">*</span>
                  </legend>
                  <input
                    type="text"
                    name="name"
                    class="input w-full rounded-lg border-base-300 bg-base-100 text-sm
                          focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                    placeholder="e.g. John Smith"
                    value="<?= e($oldForm["name"] ?? ($editUser["name"] ?? "")) ?>"
                    required
                    autofocus />
                </fieldset>

                <!-- Email -->
                <fieldset class="fieldset">
                  <legend class="fieldset-legend ">
                    Email <span class="text-error">*</span>
                  </legend>

                  <input class="<?= INPUT_CLASS ?> validator" type="email" name="email" required placeholder="john@acceloninc.com" value="<?= e($oldForm["email"] ?? ($editUser["email"] ?? "")) ?>" />

                  <p class="label w-full max-w-full whitespace-normal break-words text-xs leading-5 text-base-content/50">
                    Only <strong class="text-slate-700">@acceloninc.com</strong> addresses are allowed.
                  </p>
                </fieldset>
                <!-- </div> -->

                <!-- <p class="mb-4 -mt-1 text-xs leading-relaxed text-slate-500 sm:text-[11.5px]">
                <?= $editUser
                  ? "Leave blank to keep the current password."
                  : "Set a temporary password for this account." ?>
              </p> -->

                <!-- Password fields -->
                <!-- <div class="grid grid-cols-1 gap-4 sm:grid-cols-2"> -->

                <!-- Password -->
                <fieldset class="fieldset ">
                  <legend class="fieldset-legend ">
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
                <fieldset class="fieldset ">
                  <legend class="fieldset-legend">
                    Confirm password

                    <?php if (!$editUser): ?>
                      <span class="text-error">*</span>
                    <?php endif; ?>
                  </legend>
                  <input type="password" name="password_confirm" id="pwConfirm" class="<?= INPUT_CLASS ?>" placeholder="Re-enter password" autocomplete="new-password" oninput="checkMatch()"
                    <?= !$editUser ? "required" : "" ?>>
                  <p id="matchHint" class="mt-1 min-h-[16px] text-[11px] leading-tight"></p>
                </fieldset>

                <fieldset class="fieldset">
                  <?php $savedRole = $oldForm["role"] ?? ($editUser["role"] ?? "admin"); ?>
                  <legend class="fieldset-legend">
                    Role <span class="text-error">*</span>
                  </legend>
                  <select
                    name="role"
                    class="<?= SELECT_CLASS ?>"
                    required>
                    <?php
                    foreach (
                      ($canManageSuperadmins
                        ? ["superadmin" => "Superadmin", "admin" => "Admin", "editor" => "Editor"]
                        : ["admin" => "Admin", "editor" => "Editor"]) as $val => $label
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
                <!-- Account Active -->
                <?php
                $isEditingSelf = $editUser && (int) $editUser['id'] === (int) $_SESSION['admin_id'];
                $accountActive = $oldForm !== null
                  ? (bool) $oldForm["is_active"]
                  : ($editUser
                    ? (bool) $editUser['is_active']
                    : true);
                ?>
                <fieldset class="fieldset">
                  <!-- <div class="min-w-0">
                      <div class="flex items-center gap-2">
                        <legend class="text-sm font-medium text-base-content">Login access</legend>
                        <span id="accountStatusBadge"
                          class="rounded-full px-2 py-0.5 text-[10px] font-semibold <?= $accountActive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                          <?= $accountActive ? 'Active' : 'Inactive' ?>
                        </span>
                      </div>

                      <p id="accountStatusHelp" class="mt-1 text-xs leading-5 text-base-content/60">
                        <?= $isEditingSelf
                          ? 'Your own login access cannot be disabled.'
                          : ($accountActive
                            ? 'The user can sign in. Turn this off to suspend access without deleting the account.'
                            : 'Sign-in is suspended, but the account and activity history are preserved.') ?>
                      </p>
                    </div> -->
                  <label class="inline-flex items-center <?= $isEditingSelf ? 'cursor-not-allowed opacity-60' : 'cursor-pointer' ?>">
                    <?php if ($isEditingSelf): ?><input type="hidden" name="is_active" value="1"><?php endif; ?>
                    <input
                      type="checkbox"
                      <?= $isEditingSelf ? '' : 'name="is_active"' ?>
                      id="isActive"
                      value="1"
                      class="toggle toggle-sm shrink-0 hidden"
                      aria-describedby="accountStatusHelp"
                      <?= $accountActive ? 'checked' : '' ?>
                      <?= $isEditingSelf ? 'disabled' : '' ?> />
                  </label>
                </fieldset>
                <!-- </div> -->
              </div>
            </div>

            <div class="flex items-center gap-2.5 mt-6">
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
                id="createUserButton"
                aria-expanded="true"
                class="btn btn-sm btn-primary shadow-sm"
                disabled>
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <?= $isEditPage ? "Update User" : "Create User" ?>
              </button>
            </div>
          </form>
        </div>

      </details>

      <div class="min-w-0 space-y-6">

        <!-- <?= $showAdminForm ? '' : 'hidden' ?> -->

        <!-- ══ USERS TABLE ══ -->
        <div class="card mt-5 overflow-visible rounded-xl border border-base-300 bg-base-100 shadow-xs">
          <div class="bulk-bar flex min-h-14 items-center justify-between gap-3 border-b border-base-300 px-4 py-2 text-sm max-sm:items-start max-sm:flex-col">
            <span>
              <strong><?= number_format(count($admins)) ?></strong> total admins
            </span>
          </div>

          <div class="overflow-x-auto">
            <table class="jobs-table table table-sm min-w-[820px]">
              <thead>
                <tr>
                  <th>User</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Status</th>
                  <!-- <th>Last login</th> -->
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($admins)): ?>
                  <tr>
                    <td colspan="6" class="py-20">
                      <div class="grid place-items-center gap-2 text-center text-base-content/60">
                        <strong class="text-base font-semibold text-base-content">No admin users found</strong>
                        <span class="text-sm">Create your first admin user to get started.</span>
                      </div>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php $adminCount = count($admins); ?>
                  <?php foreach ($admins as $index => $u):
                    $dropdownPlacement = $index >= max(0, $adminCount - 2) ? 'dropdown-top' : 'dropdown-bottom';

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
                    <tr class="group border-t border-base-300 transition-colors hover:bg-[#F28C28]">

                      <td class="px-4 py-3.5 align-middle group-hover:bg-[#F28C28]">
                        <div class="flex items-center gap-2.5">
                          <div class="w-9 h-9 rounded-full flex items-center justify-center font-head text-[13px] font-bold text-white flex-shrink-0
                            <?= $u["is_active"]
                              ? "bg-[#2f6fc4]"
                              : "bg-slate-300" ?>">
                            <?= strtoupper(substr($u["name"], 0, 1)) ?>
                          </div>
                          <div class="min-w-0">
                            <strong class="block text-[13px] font-semibold text-base-content group-hover:text-white">
                              <?= e($u["name"]) ?>
                              <?php if ($isSelf): ?>
                                <span class="inline-flex items-center bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-0.5 rounded-full ml-1.5 align-middle">You</span>
                              <?php endif; ?>
                            </strong>
                            <div class="text-[11px] text-base-content/55 group-hover:text-white/80"> Since <?= date("M Y", strtotime($u["created_at"])) ?> </div>
                          </div>
                        </div>
                      </td>

                      <td class="px-4 py-3.5 align-middle text-[13px] text-base-content/70 group-hover:bg-[#F28C28] group-hover:text-white"><?= e($u["email"]) ?></td>

                      <td class="px-4 py-3.5 align-middle group-hover:bg-[#F28C28]">
                        <span class="inline-flex items-center rounded-full text-[11px] font-semibold px-2.5 py-1 <?= $rc ?>"><?= e($rl) ?></span>
                      </td>

                      <td class="px-4 py-3.5 align-middle group-hover:bg-[#F28C28]">
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

                      <!-- <td class="px-4 py-3.5 align-middle text-[12px] text-base-content/60 group-hover:bg-[#F28C28] group-hover:text-white">
                        <?= $u["last_login"]
                          ? date("d M Y, g:i A", strtotime($u["last_login"]))
                          : '<span class="text-base-content/40">Never</span>' ?>
                      </td> -->
                      <td class="job-actions-cell right-0 z-30 w-14  text-right align-middle group-hover:bg-[#F28C28]" onclick="event.stopPropagation()">
                        <div class="dropdown <?= $dropdownPlacement ?> dropdown-end">
                          <div tabindex="0" role="button" class="btn btn-sm btn-ghost m-1 p-2 bg-transparent border-none shadow-none outline-none focus:outline-none focus-visible:outline-none hover:bg-transparent text-base-content group-hover:text-white">
                            <svg class="size-4" viewBox="0 0 24 24">
                              <circle cx="5" cy="12" r="1" />
                              <circle cx="12" cy="12" r="1" />
                              <circle cx="19" cy="12" r="1" />
                            </svg>
                          </div>
                          <ul tabindex="-1" class="dropdown-content menu bg-base-100 rounded-box z-50 w-52 p-2 shadow-sm">
                            <li>
                              <a href="<?= ADMIN_URL ?>/pages/admins.php?edit=<?= $u['id'] ?>" title="Edit">
                                Edit
                              </a>
                            </li>
                            <li>
                              <button type="button" onclick="openResetModal(<?= $u['id'] ?>, '<?= e(addslashes($u['name'])) ?>')">
                                Reset Password
                              </button>
                            </li>
                            <?php if (!$isSelf): ?>
                              <li>
                                <form method="POST" class="inline">

                                  <input type="hidden" name="action" value="toggle_active">
                                  <input type="hidden" name="user_id" value="<?= $u["id"] ?>">
                                  <div class="tooltip" data-tip="<?= $u["is_active"] ? "Deactivate" : "Activate" ?>">
                                    <button
                                      type="button"
                                      onclick="openToggleModal(<?= (int)$u['id'] ?>, <?= $u['is_active'] ? 'true' : 'false' ?>)">
                                      <?php if ($u["is_active"]): ?>
                                        Inactive
                                      <?php else: ?>
                                        Active
                                      <?php endif; ?>
                                    </button>
                                  </div>
                                </form>

                              </li>
                              <li>
                                <form method="POST" class="inline">

                                  <input type="hidden" name="action" value="delete">
                                  <input type="hidden" name="user_id" value="<?= $u["id"] ?>">
                                  <div class="tooltip" data-tip="Delete">
                                    <button type="button"
                                      onclick="openDeleteModal(
                            <?= (int)$u['id'] ?>,
                            <?= htmlspecialchars(json_encode($u['name']), ENT_QUOTES, 'UTF-8') ?>
                          )">
                                      Delete
                                    </button>
                                  </div>
                                </form>

                              </li>
                            <?php endif; ?>
                          </ul>
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
    </div>
  </main>

  <!-- RESET PASSWORD MODAL (light) -->
  <dialog id="resetModal" class="modal">
    <div class="modal-box w-[calc(100%-2rem)] max-w-lg rounded-box border border-base-300 bg-base-100 p-0 shadow-xl">
      <!-- Header -->
      <div class="flex items-center gap-4 border-b border-base-200 px-5 py-5 sm:px-6">
        <!-- Icon -->
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
              d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
          </svg>
        </div>

        <!-- Title -->
        <div class="min-w-0 flex-1">
          <h3 class="<?= MODAL_HEADING ?>">
            Reset password of <span
              id="resetUserName">
            </span>
          </h3>
        </div>

        <!-- Close -->
        <button
          type="button"
          onclick="resetModal.close()"
          class="btn btn-sm btn-circle btn-ghost size-8 min-h-8 shrink-0 text-base-content/50 hover:bg-base-200 hover:text-base-content"
          aria-label="Close">

          <svg
            class="size-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2"
            aria-hidden="true">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Body -->
      <div class="space-y-5 px-5 py-5 sm:px-6">
        <!-- Form -->
        <form method="POST" id="resetForm" class="space-y-4">


          <input type="hidden" name="action" value="reset_password">
          <input type="hidden" name="user_id" id="resetUserId" value="">


          <!-- New Password -->
          <div>
            <label
              for="resetPw"
              class="mb-1.5 block text-xs font-semibold text-base-content/70">
              New Password
              <span class="text-primary">*</span>
            </label>

            <input
              type="password"
              name="new_password"
              id="resetPw"
              class="input h-auto min-h-0 w-full rounded-lg border border-base-300 bg-base-100 px-3.5 py-2.5 text-[13.5px] text-base-content outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
              placeholder="Min. 8 characters"
              required
              oninput="checkResetStrength(this.value)">

            <!-- Password strength -->
            <div class="mt-2 h-1 overflow-hidden rounded-full bg-base-300">
              <div
                id="resetPwBar"
                class="h-full w-0 rounded-full bg-primary transition-all">
              </div>
            </div>

            <div
              id="resetPwHint"
              class="mt-1 text-[11px] text-base-content/50">
            </div>
          </div>


          <!-- Confirm Password -->
          <div>
            <label
              for="resetPwConf"
              class="mb-1.5 block text-xs font-semibold text-base-content/70">
              Confirm New Password
              <span class="text-primary">*</span>
            </label>

            <input
              type="password"
              name="confirm_password"
              id="resetPwConf"
              class="input h-auto min-h-0 w-full rounded-lg border border-base-300 bg-base-100 px-3.5 py-2.5 text-[13.5px] text-base-content outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
              placeholder="Re-enter new password"
              required
              oninput="checkResetMatch()">

            <div
              id="resetMatchHint"
              class="mt-1 text-[11px]">
            </div>
          </div>

        </form>
      </div>


      <!-- Actions -->
      <div
        class="modal-action m-0 flex flex-col-reverse gap-2 border-t border-base-200 bg-base-200/30 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">

        <!-- Cancel -->
        <button
          type="button"
          onclick="resetModal.close()"
          class="btn btn-ghost h-10 min-h-10 w-full rounded-lg px-5 text-sm font-semibold text-base-content/70 hover:bg-base-200 hover:text-base-content sm:w-auto">
          Cancel
        </button>

        <!-- Reset -->
        <button
          type="submit"
          form="resetForm"
          id="resetButton"
          class="btn btn-primary h-10 min-h-10 w-full rounded-lg px-5 text-sm font-semibold sm:w-auto"
          disabled>

          <svg
            class="size-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.75"
            aria-hidden="true">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
          </svg>

          Reset Password
        </button>

      </div>
    </div>


    <!-- Click outside to close -->
    <form
      method="dialog"
      class="modal-backdrop bg-black/40">
      <button type="submit">close</button>
    </form>

  </dialog>

  <dialog id="deleteModal" class="modal">
    <div class="modal-box w-[calc(100%-2rem)] max-w-lg rounded-box border border-base-300 bg-base-100 p-0 shadow-xl">
      <!-- Close button -->
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
            Delete user
          </h3>
        </div>
        <button
          type="button"
          onclick="deleteModal.close()"
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
                Are you sure you want to delete <strong id="deleteUserName" class="text-error"></strong> account?
              </p>
              <p class="mt-2 text-xs leading-5 text-base-content/60">
                This will permanently remove the user. This action cannot be undone.
              </p>
            </div>
          </div>
        </div>
      </div>
      <!-- Actions -->
      <div class="modal-action m-0 flex flex-col-reverse gap-2 border-t border-base-200 bg-base-200/30 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
        <button
          type="button"
          onclick="deleteModal.close()"
          class="btn btn-ghost px-4 py-2.5 min-h-0 h-auto rounded-lg border border-line text-ink2 text-[13px] font-semibold hover:bg-card2 hover:text-ink">
          Cancel
        </button>
        <form method="POST" id="confirmDeleteUserForm" class="w-full sm:w-auto">

          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="user_id" id="deleteUserId">
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

    <!-- Click outside modal to close -->
    <form
      method="dialog"
      class="modal-backdrop bg-black/40">
      <button type="submit">close</button>
    </form>
  </dialog>

  <dialog id="toggleModal" class="modal">
    <div class="modal-box w-[calc(100%-2rem)] max-w-lg rounded-box border border-base-300 bg-base-100 p-0 shadow-xl">
      <!-- Close button -->
      <div class="flex items-center gap-4 border-b border-base-200 px-5 py-5 sm:px-6">
        <div class="<?= SVG_DIV ?>" id="toggleIconWrap">
          <svg
            class="<?= SVG_ICON ?>"
            id="toggleIconAsk"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.75"
            style="display:none">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" />
          </svg>
          <svg
            class="<?= SVG_ICON ?>"
            id="toggleIconWarn"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
          </svg>
        </div>
        <div class="min-w-0 flex-1">
          <h3 class="<?= MODAL_HEADING ?>" id="toggleModalTitle">
            Confirm action
          </h3>
        </div>
        <button
          type="button"
          onclick="toggleModal.close()"
          class="btn btn-sm btn-circle btn-ghost size-8 min-h-8 shrink-0 text-base-content/50"
          aria-label="Close">
          <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      <!-- Body -->
      <div class="px-6 py-5">
        <div class="rounded-xl p-4">
          <div class="flex items-start gap-3">
            <div class="flex-1">
              <p class="text-sm font-medium text-base-content" id="toggleModalCopy"></p>
              <p class="mt-2 text-xs leading-5 text-base-content/60">
                You can change this at any time from the admins list.
              </p>
            </div>
          </div>
        </div>
      </div>
      <!-- Actions -->
      <div class="modal-action m-0 flex flex-col-reverse gap-2 border-t border-base-200 bg-base-200/30 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
        <button
          type="button"
          onclick="toggleModal.close()"
          class="btn btn-ghost px-4 py-2.5 min-h-0 h-auto rounded-lg border border-line text-ink2 text-[13px] font-semibold hover:bg-card2 hover:text-ink">
          Cancel
        </button>
        <form method="POST" id="confirmToggleForm" class="w-full sm:w-auto">

          <input type="hidden" name="action" value="toggle_active">
          <input type="hidden" name="user_id" id="toggleUserId">
          <button
            type="submit"
            id="toggleConfirmButton"
            class="btn h-10 min-h-10 w-full rounded-lg px-5 text-sm font-semibold sm:w-auto">
            Confirm
          </button>
        </form>
      </div>
    </div>

    <!-- Click outside modal to close -->
    <form
      method="dialog"
      class="modal-backdrop bg-black/40">
      <button type="submit">close</button>
    </form>
  </dialog>

  <dialog id="unsavedChangesModal" class="modal">
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
          id="stayOnAdminForm"
          class="btn btn-ghost px-4 py-2.5 min-h-0 h-auto rounded-lg border border-line text-ink2 text-[13px] font-semibold hover:bg-card2 hover:text-ink">
          Stay on page
        </button>
        <button
          type="button"
          id="leaveAdminForm"
          class="btn btn-warning h-10 min-h-10 rounded-lg px-5 text-sm font-semibold">
          Leave without saving
        </button>
      </div>
    </div>
    <form method="dialog" class="modal-backdrop bg-black/40">
      <button type="submit">close</button>
    </form>
  </dialog>

  <script>
    function updateAccountStatusPreview() {
      const toggle = document.getElementById('isActive');
      const badge = document.getElementById('accountStatusBadge');
      const help = document.getElementById('accountStatusHelp');
      if (!toggle || !badge || !help || toggle.disabled) return;

      const active = toggle.checked;
      badge.textContent = active ? 'Active' : 'Inactive';
      badge.className = `rounded-full px-2 py-0.5 text-[10px] font-semibold ${
      active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
    }`;
      help.textContent = active ?
        'The user can sign in. Turn this off to suspend access without deleting the account.' :
        'Sign-in is suspended, but the account and activity history are preserved.';
    }

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

    function isEmailValid(email) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) &&
        email.toLowerCase().endsWith('@acceloninc.com');
    }

    function adminFormSnapshot(form) {
      const fields = ['name', 'email', 'password', 'password_confirm', 'role'];
      return fields.map(name => {
        const field = form.querySelector(`[name="${name}"]`);
        return field ? `${name}:${field.value || ''}` : `${name}:`;
      }).concat([
        `is_active:${form.querySelector('[name="is_active"], #isActive')?.checked ? '1' : '0'}`
      ]).join('|');
    }

    const adminFormEl = document.getElementById('adminForm');
    const initialAdminFormSnapshot = adminFormEl ? adminFormSnapshot(adminFormEl) : '';
    let adminFormSubmitted = false;
    let pendingAdminNavigation = null;

    function hasUnsavedAdminChanges() {
      return !!adminFormEl &&
        !adminFormSubmitted &&
        adminFormSnapshot(adminFormEl) !== initialAdminFormSnapshot;
    }

    function openUnsavedChangesModal(nextAction) {
      const modal = document.getElementById('unsavedChangesModal');
      if (!modal) return;

      pendingAdminNavigation = nextAction;
      modal.showModal();
    }

    function updateFormState() {
      const form = document.getElementById('adminForm');
      const button = document.getElementById('createUserButton');
      if (!form || !button) return;

      const name = (form.querySelector('input[name="name"]')?.value || '').trim();
      const email = (form.querySelector('input[name="email"]')?.value || '').trim();
      const pw = form.querySelector('input[name="password"]')?.value || '';
      const cfm = form.querySelector('input[name="password_confirm"]')?.value || '';
      const userId = parseInt(form.querySelector('input[name="user_id"]')?.value || '0', 10);
      const emailField = form.querySelector('input[name="email"]');
      const pwField = form.querySelector('input[name="password"]');
      const confirmField = form.querySelector('input[name="password_confirm"]');
      const hasChanges = adminFormSnapshot(form) !== initialAdminFormSnapshot;

      emailField?.setCustomValidity(email === '' || isEmailValid(email) ? '' : 'Use an @acceloninc.com email address.');
      pwField?.setCustomValidity('');
      confirmField?.setCustomValidity('');

      let valid = name !== '' && isEmailValid(email);

      if (userId === 0) {
        const passwordValid = pw !== '' && pwStrength(pw).hints.length === 0;
        const passwordsMatch = cfm !== '' && pw === cfm;
        if (pw !== '' && !passwordValid) pwField?.setCustomValidity('Password does not meet the requirements.');
        if (cfm !== '' && !passwordsMatch) confirmField?.setCustomValidity('Passwords do not match.');
        valid = valid && passwordValid && passwordsMatch;
      } else if (pw !== '') {
        const passwordValid = pwStrength(pw).hints.length === 0;
        const passwordsMatch = cfm !== '' && pw === cfm;
        if (!passwordValid) pwField?.setCustomValidity('Password does not meet the requirements.');
        if (!passwordsMatch) confirmField?.setCustomValidity('Passwords do not match.');
        valid = valid && passwordValid && passwordsMatch;
      } else if (cfm !== '') {
        confirmField?.setCustomValidity('Enter a new password first.');
        valid = false;
      }

      valid = valid && form.checkValidity() && hasChanges;
      button.disabled = !valid;
      button.classList.toggle('btn-disabled', !valid);
    }

    adminFormEl?.addEventListener('input', updateFormState);
    adminFormEl?.addEventListener('change', updateFormState);
    adminFormEl?.addEventListener('submit', event => {
      updateFormState();
      const button = document.getElementById('createUserButton');
      if (button?.disabled) {
        event.preventDefault();
        adminFormEl.reportValidity();
        return;
      }
      adminFormSubmitted = true;
    });
    updateFormState();

    document.addEventListener('click', event => {
      const link = event.target.closest('a[href]');
      if (!link || !hasUnsavedAdminChanges()) return;

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
      openUnsavedChangesModal(() => {
        window.location.href = link.href;
      });
    });

    document.addEventListener('submit', event => {
      const form = event.target;
      if (form === adminFormEl || !hasUnsavedAdminChanges()) return;

      event.preventDefault();
      openUnsavedChangesModal(() => {
        adminFormSubmitted = true;
        HTMLFormElement.prototype.submit.call(form);
      });
    }, true);

    document.getElementById('stayOnAdminForm')?.addEventListener('click', () => {
      pendingAdminNavigation = null;
      document.getElementById('unsavedChangesModal')?.close();
    });

    document.getElementById('leaveAdminForm')?.addEventListener('click', () => {
      const nextAction = pendingAdminNavigation;
      adminFormSubmitted = true;
      pendingAdminNavigation = null;
      document.getElementById('unsavedChangesModal')?.close();

      if (typeof nextAction === 'function') {
        nextAction();
      }
    });

    window.addEventListener('beforeunload', event => {
      if (!hasUnsavedAdminChanges()) return;

      event.preventDefault();
      event.returnValue = '';
    });

    // function openResetModal(id, name) {
    //   const resetModal = document.getElementById('resetModal');
    //   const resetUserId = document.getElementById('resetUserId');
    //   const resetUserName = document.getElementById('resetUserName');
    //   const resetPw = document.getElementById('resetPw');
    //   const resetPwConf = document.getElementById('resetPwConf');
    //   const resetPwBar = document.getElementById('resetPwBar');
    //   const resetPwHint = document.getElementById('resetPwHint');
    //   const resetMatchHint = document.getElementById('resetMatchHint');

    //   if (resetModal && resetUserId && resetUserName) {
    //     resetUserId.value = id;
    //     resetUserName.textContent = name;

    //     if (resetPw) resetPw.value = '';
    //     if (resetPwConf) resetPwConf.value = '';
    //     if (resetPwBar) resetPwBar.style.cssText = 'width:0';
    //     if (resetPwHint) resetPwHint.textContent = '';
    //     if (resetMatchHint) resetMatchHint.textContent = '';

    //     resetModal.classList.add('open');

    //     if (resetPw) {
    //       setTimeout(() => resetPw.focus(), 100);
    //     }
    //   }
    // }

    function closeResetModal() {
      const modal = document.getElementById('resetModal');
      if (modal) {
        modal.classList.remove('open');
      }
    }

    function openDeleteModal(clientId, clientName) {
      document.getElementById('deleteUserId').value = clientId;
      document.getElementById('deleteUserName').textContent = clientName;

      document.getElementById('deleteModal').showModal();
    }

    function openToggleModal(userId, isActive) {
      const deactivating = isActive;
      document.getElementById('toggleUserId').value = userId;
      document.getElementById('toggleModalTitle').textContent = deactivating ? 'Deactivate account?' : 'Activate account?';
      document.getElementById('toggleModalCopy').textContent = deactivating ?
        'Are you sure you want to deactivate this admin account? The user will no longer be able to sign in.' :
        'Are you sure you want to activate this admin account? The user will be able to sign in again.';
      const confirm = document.getElementById('toggleConfirmButton');
      confirm.textContent = deactivating ? 'Deactivate' : 'Activate';
      confirm.classList.toggle('btn-error', deactivating);
      confirm.classList.toggle('btn-primary', !deactivating);
      document.getElementById('toggleIconAsk').style.display = deactivating ? 'none' : 'inline';
      document.getElementById('toggleIconWarn').style.display = deactivating ? 'inline' : 'none';
      document.getElementById('toggleModal').showModal();
    }

    // Initialize event listeners when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
      const resetModal = document.getElementById('resetModal');
      if (resetModal) {
        resetModal.addEventListener('click', function(e) {
          if (e.target === this) closeResetModal();
        });
      }

      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeResetModal();
      });

      <?php if ($editUser): ?>
        const adminForm = document.getElementById('adminForm');
        if (adminForm) {
          adminForm.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      <?php endif; ?>
    });

    function checkResetStrength(val) {
      const resetPwBar = document.getElementById('resetPwBar');
      const resetPwHint = document.getElementById('resetPwHint');

      if (!resetPwBar || !resetPwHint) return;

      const r = pwStrength(val);
      resetPwBar.style.cssText = `width:${r.pct||'0%'};background:${r.color}`;

      if (!val) {
        resetPwHint.innerHTML = '';
        checkResetMatch();
        return;
      }
      if (r.hints.length) {
        resetPwHint.innerHTML = '<span style="color:' + r.color + '">' + r.label + '</span>' +
          ' &mdash; still needs: <span style="color:#dc2626">' + r.hints.join(', ') + '</span>';
      } else {
        resetPwHint.innerHTML = '<span style="color:' + r.color + '">✓ ' + r.label + '</span>';
      }
      checkResetMatch();
    }

    function updateResetState() {
      const resetPw = document.getElementById('resetPw');
      const resetPwConf = document.getElementById('resetPwConf');
      const resetButton = document.getElementById('resetButton');
      if (!resetPw || !resetPwConf || !resetButton) return;

      const pw = resetPw.value;
      const cfm = resetPwConf.value;
      const valid = pw !== '' && cfm !== '' && pw === cfm && pwStrength(pw).hints.length === 0;
      resetButton.disabled = !valid;
    }

    function checkResetMatch() {
      const resetPw = document.getElementById('resetPw');
      const resetPwConf = document.getElementById('resetPwConf');
      const resetMatchHint = document.getElementById('resetMatchHint');

      if (!resetPw || !resetPwConf || !resetMatchHint) return;

      const pw = resetPw.value;
      const cfm = resetPwConf.value;

      if (!cfm) {
        resetMatchHint.textContent = '';
        updateResetState();
        return;
      }
      if (pw === cfm) {
        resetMatchHint.textContent = '✓ Match';
        resetMatchHint.style.color = '#16a34a';
      } else {
        resetMatchHint.textContent = '✕ No match';
        resetMatchHint.style.color = '#dc2626';
      }
      updateResetState();
    }

    function openResetModal(userId, userName) {
      const modal = document.getElementById('resetModal');

      document.getElementById('resetUserId').value = userId;
      document.getElementById('resetUserName').textContent = userName;

      // Reset form
      document.getElementById('resetForm').reset();

      // Restore hidden user ID after reset()
      document.getElementById('resetUserId').value = userId;

      // Reset password indicators
      document.getElementById('resetPwBar').style.width = '0%';
      document.getElementById('resetPwHint').textContent = '';
      document.getElementById('resetMatchHint').textContent = '';
      const resetButton = document.getElementById('resetButton');
      if (resetButton) resetButton.disabled = true;

      modal.showModal();
    }


    function closeResetModal() {
      const modal = document.getElementById('resetModal');

      if (modal.open) {
        modal.close();
      }
    }
  </script>

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