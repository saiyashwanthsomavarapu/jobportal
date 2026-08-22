<?php
// login.php
require_once __DIR__ . "/config.php";

if (session_status() === PHP_SESSION_NONE) {
  session_name(SESSION_NAME);
  session_start();
}

// Already logged in
if (!empty($_SESSION["admin_id"])) {
  redirect(ADMIN_URL . "/index.php");
}

$error = "";
$redirect = $_GET["redirect"] ?? ADMIN_URL . "/index.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $email = trim($_POST["email"] ?? "");
  $password = trim($_POST["password"] ?? "");

  if ($email && $password) {
    $stmt = db()->prepare(
      "SELECT * FROM admin_users WHERE email = ? AND is_active = 1"
    );
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin["password"])) {
      session_regenerate_id(true);
      $_SESSION["admin_id"] = $admin["id"];
      $_SESSION["admin_name"] = $admin["name"];
      $_SESSION["admin_role"] = $admin["role"];
      $_SESSION["admin_last_active"] = time();

      db()
        ->prepare(
          "UPDATE admin_users SET last_login = NOW() WHERE id = ?"
        )
        ->execute([$admin["id"]]);

      logActivity("login");
      redirect($redirect);
    } else {
      $error = "Invalid email or password.";
    }
  } else {
    $error = "Please enter both email and password.";
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Login — <?= SITE_NAME ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <?php include __DIR__ . "/includes/theme.php"; ?>
</head>

<body data-theme="accelon" style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; background-color: #f4f5f8; font-family: 'DM Sans', sans-serif;">

  <div style="width: 100%; max-width: 400px;">

    <!-- Logo / brand -->
    <div style="text-align: center; margin-bottom: 1.75rem;">
      <h1 style="font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 800; color: #111827; letter-spacing: -0.5px;">Accelon</h1>
      <p style="font-size: 12.5px; color: #9aa0b4; margin-top: 4px;">Admin Management System</p>
    </div>

    <!-- Card -->
    <div style="border-radius: 1rem; border: 1px solid #e7e9f0; padding: 2rem; box-shadow: 0 1px 2px rgba(17, 24, 39, .04), 0 8px 24px -12px rgba(17, 24, 39, .08); background: #ffffff;">
      <h2 style="font-family: 'Syne', sans-serif; font-size: 19px; font-weight: 700; color: #111827; margin-bottom: 4px;">Welcome back</h2>
      <p style="font-size: 13px; color: #5b6072; margin-top: 4px; margin-bottom: 1.5rem;">Sign in to your admin account</p>

      <?php if ($error): ?>
        <div style="display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px; border-radius: 12px; margin-bottom: 1.25rem; font-size: 13.5px; font-weight: 500; background: rgba(220, 53, 69, 0.08); border: 1px solid rgba(220, 53, 69, 0.2); color: #dc3545;">
          <svg style="height: 18px; width: 18px; flex-shrink: 0; margin-top: 1px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span><?= e($error) ?></span>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET["msg"]) && $_GET["msg"] === "timeout"): ?>
        <div style="display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px; border-radius: 12px; margin-bottom: 1.25rem; font-size: 13.5px; font-weight: 500; background: rgba(220, 53, 69, 0.08); border: 1px solid rgba(220, 53, 69, 0.2); color: #dc3545;">
          <svg style="height: 18px; width: 18px; flex-shrink: 0; margin-top: 1px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>Session expired. Please log in again.</span>
        </div>
      <?php endif; ?>

      <form method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

        <div>
          <label style="display: block; font-size: 11px; font-weight: 600; color: #9aa0b4; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">Email Address</label>
          <input type="email" name="email" placeholder="admin@jobportal.com"
            value="<?= e($_POST["email"] ?? "") ?>" required autofocus
            style="width: 100%; border: 1px solid #e7e9f0; border-radius: 0.5rem; padding: 10px 14px; font-size: 13.5px; color: #111827; background: #f8f9fc; outline: none; transition: all 0.2s;">
        </div>

        <div>
          <label style="display: block; font-size: 11px; font-weight: 600; color: #9aa0b4; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">Password</label>
          <div style="position: relative;">
            <input type="password" name="password" id="passwordInput" placeholder="••••••••" required
              style="width: 100%; border: 1px solid #e7e9f0; border-radius: 0.5rem; padding: 10px 14px; padding-right: 40px; font-size: 13.5px; color: #111827; background: #f8f9fc; outline: none; transition: all 0.2s;">
            <button type="button" onclick="togglePwd()" id="pwdToggle"
              style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); padding: 6px; border-radius: 6px; color: #9aa0b4; background: none; border: none; cursor: pointer; transition: color 0.2s;">
              <svg id="eyeOpen" style="height: 17px; width: 17px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <svg id="eyeClosed" style="height: 17px; width: 17px; display: none;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.774 3.162 10.066 7.498a10.522 10.522 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
              </svg>
            </button>
          </div>
        </div>

        <button type="submit"
          style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; background: #1A4C8F; color: #ffffff; border-radius: 0.5rem; font-family: 'Syne', sans-serif; font-size: 13.5px; font-weight: 700; letter-spacing: 0.05em; padding: 12px; box-shadow: 0 4px 14px rgba(26, 76, 143, 0.22); transition: all 0.2s; margin-top: 8px; border: none; cursor: pointer;">
          Sign In
          <svg style="height: 16px; width: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25L21 12m0 0l-3.75 3.75M21 12H3" />
          </svg>
        </button>
      </form>
    </div>

    <p style="text-align: center; font-size: 12px; color: #9aa0b4; margin-top: 1.5rem;">&copy; <?= date("Y") ?> <?= SITE_NAME ?></p>
  </div>

  <script>
    function togglePwd() {
      var i = document.getElementById('passwordInput');
      var eyeOpen = document.getElementById('eyeOpen');
      var eyeClosed = document.getElementById('eyeClosed');
      if (i.type === 'password') {
        i.type = 'text';
        eyeOpen.style.display = 'none';
        eyeClosed.style.display = 'block';
      } else {
        i.type = 'password';
        eyeOpen.style.display = 'block';
        eyeClosed.style.display = 'none';
      }
    }
  </script>
</body>

</html>
