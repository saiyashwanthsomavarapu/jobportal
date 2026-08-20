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
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css" />

  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            bg: '#0a0e14',
            surface: '#14171f',
            field: '#1b1f29',
            accent: '#2f6fc4',
            'accent-dark': '#1f4f91',
            danger: '#ff7a86',
          },
          fontFamily: {
            head: ['Syne', 'sans-serif'],
            sans: ['DM Sans', 'sans-serif'],
          },
          boxShadow: {
            card: '0 1px 2px rgba(0,0,0,.2), 0 8px 24px -12px rgba(0,0,0,.4)',
            pop: '0 4px 14px rgba(47,111,196,.35)',
          },
        }
      }
    }
  </script>
  <style>
    body {
      font-size: 14px;
      -webkit-font-smoothing: antialiased;
      background-color: #0a0e14 !important;
    }
  </style>
</head>

<body class="min-h-screen flex items-center justify-center px-4 text-white font-sans antialiased" style="background-color:#0a0e14 !important">

  <div class="w-full max-w-[400px]">

    <!-- Logo / brand -->
    <div class="text-center mb-7">
      <h1 class="font-head text-[26px] font-extrabold text-white tracking-tight">Accelon</h1>
      <p class="text-[12.5px] text-white/40 mt-1">Admin Management System</p>
    </div>

    <!-- Card -->
    <div class="rounded-2xl border border-white/[.07] p-8 shadow-card" style="background-color:#14171f !important">
      <h2 class="font-head text-[19px] font-bold text-white">Welcome back</h2>
      <p class="text-[13px] text-white/45 mt-1 mb-6">Sign in to your admin account</p>

      <?php if ($error): ?>
        <div class="flex items-start gap-2.5 px-4 py-3 rounded-xl mb-5 text-[13px] font-medium bg-danger/10 border border-danger/25 text-danger">
          <svg class="h-[18px] w-[18px] flex-shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span><?= e($error) ?></span>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET["msg"]) && $_GET["msg"] === "timeout"): ?>
        <div class="flex items-start gap-2.5 px-4 py-3 rounded-xl mb-5 text-[13px] font-medium bg-danger/10 border border-danger/25 text-danger">
          <svg class="h-[18px] w-[18px] flex-shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>Session expired. Please log in again.</span>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

        <div>
          <label class="block text-[11px] font-semibold text-white/50 uppercase tracking-wide mb-1.5">Email Address</label>
          <input type="email" name="email" placeholder="admin@jobportal.com"
            value="<?= e($_POST["email"] ?? "") ?>" required autofocus
            class="w-full border border-white/10 rounded-lg px-3.5 py-2.5 text-[13.5px] text-white
                        placeholder-white/25 outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition"
            style="background-color:#1b1f29 !important">
        </div>

        <div>
          <label class="block text-[11px] font-semibold text-white/50 uppercase tracking-wide mb-1.5">Password</label>
          <div class="relative">
            <input type="password" name="password" id="passwordInput" placeholder="••••••••" required
              class="w-full border border-white/10 rounded-lg px-3.5 py-2.5 pr-10 text-[13.5px] text-white
                          placeholder-white/25 outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition"
              style="background-color:#1b1f29 !important">
            <button type="button" onclick="togglePwd()" id="pwdToggle"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-white/40 hover:text-white transition-colors">
              <svg id="eyeOpen" class="h-[17px] w-[17px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <svg id="eyeClosed" class="h-[17px] w-[17px] hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.774 3.162 10.066 7.498a10.522 10.522 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
              </svg>
            </button>
          </div>
        </div>

        <button type="submit"
          class="w-full flex items-center justify-center gap-2 bg-accent text-white rounded-lg font-head
                text-[13.5px] font-bold tracking-wide py-3 shadow-pop hover:bg-accent-dark transition-all mt-2">
          Sign In
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25L21 12m0 0l-3.75 3.75M21 12H3" />
          </svg>
        </button>
      </form>
    </div>

    <p class="text-center text-[12px] text-white/30 mt-6">&copy; <?= date("Y") ?> <?= SITE_NAME ?></p>
  </div>

  <script>
    function togglePwd() {
      var i = document.getElementById('passwordInput');
      var eyeOpen = document.getElementById('eyeOpen');
      var eyeClosed = document.getElementById('eyeClosed');
      if (i.type === 'password') {
        i.type = 'text';
        eyeOpen.classList.add('hidden');
        eyeClosed.classList.remove('hidden');
      } else {
        i.type = 'password';
        eyeOpen.classList.remove('hidden');
        eyeClosed.classList.add('hidden');
      }
    }
  </script>
</body>

</html>