<?php
// login.php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Already logged in
if (!empty($_SESSION['admin_id'])) {
    redirect(ADMIN_URL . '/index.php');
}

$error    = '';
$redirect = $_GET['redirect'] ?? (ADMIN_URL . '/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $stmt = db()->prepare("SELECT * FROM admin_users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_last_active'] = time();

            db()->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")
               ->execute([$admin['id']]);

            logActivity('login');
            redirect($redirect);
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please enter both email and password.';
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
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#080a10;--card:#111420;--border:#1e2130;
  --accent:#4f8ef7;--accent2:#a78bfa;
  --text:#e8eaf0;--muted:#6b7280;--danger:#f87171;
  --font-h:'Syne',sans-serif;--font:'DM Sans',sans-serif;
}
body{background:var(--bg);color:var(--text);font-family:var(--font);
  display:flex;align-items:center;justify-content:center;min-height:100vh;
  background-image:radial-gradient(ellipse 80% 60% at 50% -10%,rgba(79,142,247,.12),transparent);
}
.wrap{width:100%;max-width:420px;padding:24px}
.logo{text-align:center;margin-bottom:36px}
.logo h1{font-family:var(--font-h);font-size:28px;font-weight:800;letter-spacing:-1px}
.logo h1 span{color:var(--accent)}
.logo p{color:var(--muted);font-size:13px;margin-top:4px}
.card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:34px 32px}
h2{font-family:var(--font-h);font-size:20px;font-weight:700;margin-bottom:6px}
.sub{color:var(--muted);font-size:13px;margin-bottom:28px}
.field{margin-bottom:18px}
label{display:block;font-size:12px;font-weight:500;color:var(--muted);
  text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px}
input{width:100%;background:#0d1018;border:1px solid var(--border);
  border-radius:8px;color:var(--text);font-family:var(--font);font-size:14px;
  padding:11px 14px;outline:none;transition:border-color .18s,box-shadow .18s}
input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,142,247,.12)}
.btn{width:100%;background:var(--accent);color:#fff;border:none;border-radius:8px;
  font-family:var(--font-h);font-size:14px;font-weight:700;letter-spacing:.4px;
  padding:12px;cursor:pointer;transition:all .18s;margin-top:8px}
.btn:hover{background:#6aa0ff;transform:translateY(-1px);box-shadow:0 6px 20px rgba(79,142,247,.35)}
.error{background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3);
  color:var(--danger);border-radius:8px;padding:11px 14px;font-size:13px;margin-bottom:18px}
.footer{text-align:center;margin-top:24px;color:var(--muted);font-size:12px}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">
    <h1>Accelon</h1>
    <p>Admin Management System</p>
  </div>
  <div class="card">
    <h2>Welcome back</h2>
    <p class="sub">Sign in to your admin account</p>

    <?php if ($error): ?>
    <div class="error">⚠ <?= e($error) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg']==='timeout'): ?>
    <div class="error">⚠ Session expired. Please log in again.</div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
      <div class="field">
        <label>Email Address</label>
        <input type="email" name="email" placeholder="admin@jobportal.com"
               value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
      </div>
   <div class="field">
  <label>Password</label>
  <div style="position:relative">
    <input type="password" name="password" id="passwordInput" placeholder="••••••••" required>
    <button type="button" onclick="togglePwd()" id="pwdToggle"
      style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
             background:none;border:none;cursor:pointer;padding:0;
             color:var(--muted);font-size:18px;line-height:1;width:auto">
      👁
    </button>
  </div>
</div>
      <button type="submit" class="btn">Sign In →</button>
    </form>
  </div>
  <p class="footer">© <?= date('Y') ?> <?= SITE_NAME ?></p>
</div>
<script>
function togglePwd(){
  var i=document.getElementById('passwordInput');
  var b=document.getElementById('pwdToggle');
  if(i.type==='password'){i.type='text';b.style.opacity='1';}
  else{i.type='password';b.style.opacity='.45';}
}
</script>
</body>
</html>
