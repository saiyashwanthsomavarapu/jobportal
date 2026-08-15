<?php
// includes/header.php
// Usage: include with $pageTitle already set
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?> — <?= SITE_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet">
<style>
/* ════════════════════════════════════════════
   RESET & ROOT
════════════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f0f2f7;
  --surface:#ffffff;
  --card:#ffffff;
  --card2:#f7f8fb;
  --border:#e2e6f0;
  --border2:#d5daea;
  --accent:#1A4C8F;
  --accent-glow:rgba(59,127,245,.12);
  --accent2:#7c4df0;
  --accent3:#20b38a;
  --text:#1a1f36;
  --text2:#5a6282;
  --muted:#9aa0bb;
  --danger:#e04545;
  --warn:#e89e10;
  --success:#1fad72;
  --r:8px;
  --r2:12px;
  --sidebar:140px;
  --topbar:58px;
  /** --font-h:'Syne',sans-serif; **/
  --font:'DM Sans',sans-serif;
  --shadow:0 2px 16px rgba(60,72,120,.10);
}

html,body{height:100%}
body{background:var(--bg);color:var(--text);font-family:var(--font);
  font-size:14px;line-height:1.6;overflow-x:hidden}

a{color:inherit;text-decoration:none}
img{max-width:100%;display:block}
button,input,select,textarea{font-family:var(--font)}

/* ════════════════════════════════════════════
   SCROLLBAR
════════════════════════════════════════════ */
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:var(--bg)}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:10px}
::-webkit-scrollbar-thumb:hover{background:var(--muted)}

/* ════════════════════════════════════════════
   LAYOUT
════════════════════════════════════════════ */
.admin-layout{display:flex;min-height:100vh}

/* ── SIDEBAR ── */

.main-body form#jobForm {
    max-width: 1200px;
}


.sidebar{
  flex-shrink:0;
  background:#f0f2f7;
  border-right:1px solid var(--border);
  display:flex;flex-direction:column;
  position:fixed;top:0;left:0;height:100vh;
  overflow-y:auto;z-index:100;
  transition:transform .3s;
  box-shadow:2px 0 12px rgba(60,72,120,.06);
}
.sb-logo{
    max-width: 150px;
  padding:22px 20px 18px;
  border-bottom:1px solid var(--border);
  flex-shrink:0;
}
.sb-logo a{
  font-family:var(--font-h);font-size:20px;font-weight:800;
  letter-spacing:-0.5px;color:var(--text);display:block;
}
.sb-logo a span{color:var(--accent)}
.sb-logo small{display:block;font-size:11px;color:var(--muted);margin-top:2px;font-weight:400;letter-spacing:.5px;text-transform:uppercase}

.sb-nav{padding:14px 0;flex:1}
.sb-section{
  font-size:10px;letter-spacing:1.2px;text-transform:uppercase;
  color:#000;padding:14px 20px 5px;font-weight:600;
}
.sb-item{
  display:flex;align-items:center;gap:11px;
  padding:9px 20px;color:#000;font-size:13px;font-weight:400;
  transition:all .16s;border-left:3px solid transparent;
  position:relative;cursor:pointer;
}
.sb-item:hover{color:var(--text);background:rgba(59,127,245,.06);border-left-color:var(--border2)}
.sb-item.active{color:#000;background:rgba(59,127,245,.08);border-left-color:var(--accent);font-weight:500}
.sb-icon{font-size:15px;width:20px;text-align:center;flex-shrink:0}
.sb-badge{
  margin-left:auto;background:var(--accent);color:#fff;
  border-radius:20px;font-size:10px;font-weight:700;
  padding:1px 7px;letter-spacing:.3px;
}
.sb-badge.draft{background:var(--warn)}

.sb-footer{
  padding:14px 20px;border-top:1px solid var(--border);
  font-size:12px;color:var(--muted);
}
.sb-user{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.sb-avatar{
  width:32px;height:32px;border-radius:50%;
  background:linear-gradient(135deg,var(--accent),var(--accent2));
  display:flex;align-items:center;justify-content:center;
  font-family:var(--font-h);font-size:13px;font-weight:700;color:#fff;flex-shrink:0;
}
.sb-user-info strong{display:block;font-size:13px;color:#000;font-weight:500}
.sb-user-info span{font-size:11px;color:#000;text-transform:capitalize}
.sb-logout{
  display:flex;align-items:center;gap:7px;color:#000;
  font-size:12px;padding:6px 0;transition:color .15s;cursor:pointer;
}
.sb-logout:hover{color:var(--danger)}

/* ── TOPBAR ── */
.topbar{
  position:fixed;top:0;left:var(--sidebar);right:0;height:var(--topbar);
  background:var(--surface);border-bottom:1px solid var(--border);
  display:flex;align-items:center;padding:0 28px;gap:16px;z-index:90;
  box-shadow:0 2px 8px rgba(60,72,120,.06);
}
.topbar-title{
  font-family:var(--font-h);font-size:16px;font-weight:700;color:var(--text);flex:1;
}
.topbar-breadcrumb{font-size:12px;color:var(--muted)}
.topbar-breadcrumb a{color:var(--accent)}
.topbar-breadcrumb a:hover{text-decoration:underline}
.topbar-actions{display:flex;align-items:center;gap:10px}
.topbar-btn{
  display:flex;align-items:center;gap:6px;
  background:var(--accent);color:#fff;border:none;border-radius:var(--r);
  font-family:var(--font-h);font-size:12px;font-weight:700;letter-spacing:.3px;
  padding:8px 16px;cursor:pointer;transition:all .18s;
}
.topbar-btn:hover{background:#5a94ff;transform:translateY(-1px)}
.topbar-btn.outline{background:transparent;color:var(--text2);border:1px solid var(--border2)}
.topbar-btn.outline:hover{color:var(--text);border-color:var(--muted);transform:none}

/* ── MAIN CONTENT ── */
.main{
  margin-left:var(--sidebar);
  padding-top:var(--topbar);
  min-height:100vh;
  display:flex;flex-direction:column;
}
.main-body{
  padding:28px 30px;flex:1;
}

/* ════════════════════════════════════════════
   FLASH MESSAGES
════════════════════════════════════════════ */
.flash{
  display:flex;align-items:flex-start;gap:10px;
  padding:12px 16px;border-radius:var(--r);margin-bottom:20px;font-size:13.5px;
}
.flash-success{background:rgba(31,173,114,.08);border:1px solid rgba(31,173,114,.22);color:var(--success)}
.flash-error{background:rgba(224,69,69,.08);border:1px solid rgba(224,69,69,.25);color:var(--danger)}
.flash-warn{background:rgba(232,158,16,.08);border:1px solid rgba(232,158,16,.25);color:var(--warn)}
.flash ul{margin:4px 0 0 16px}

/* ════════════════════════════════════════════
   CARDS & SECTIONS
════════════════════════════════════════════ */
.card{
  background:var(--card);border:1px solid var(--border);
  border-radius:var(--r2);padding:24px 26px;margin-bottom:22px;
  box-shadow:var(--shadow);
}
.card-sm{padding:18px 20px}
.section-title{
  font-family:var(--font-h);font-size:11px;font-weight:700;
  text-transform:uppercase;letter-spacing:1.2px;color:var(--accent);
  margin-bottom:20px;display:flex;align-items:center;gap:10px;
}
.section-title::after{content:'';flex:1;height:1px;background:var(--border)}

/* ════════════════════════════════════════════
   FORM ELEMENTS
════════════════════════════════════════════ */
.form-grid{display:grid;gap:16px 20px}
.g2{grid-template-columns:1fr 1fr}
.g3{grid-template-columns:1fr 1fr 1fr}
.g4{grid-template-columns:1fr 1fr 1fr 1fr}
.gc2{grid-column:span 2}
.gc3{grid-column:span 3}
.gc4{grid-column:1/-1}

.field{display:flex;flex-direction:column;gap:5px}
.field label{
  font-size:11px;font-weight:600;color:var(--text2);
  text-transform:uppercase;letter-spacing:.7px;
}
.req{color:var(--accent);margin-left:2px}
.field-hint{font-size:11px;color:var(--muted);margin-top:2px}

.ctrl{
  background:var(--bg);border:1px solid var(--border);
  border-radius:var(--r);color:var(--text);
  font-family:var(--font);font-size:13.5px;
  padding:9px 12px;width:100%;outline:none;
  transition:border-color .18s,box-shadow .18s;
  -webkit-appearance:none;
}
.ctrl:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
.ctrl:disabled,.ctrl[readonly]{opacity:.5;cursor:not-allowed}
.ctrl.error-field{border-color:var(--danger)}
select.ctrl option{background:var(--surface)}
textarea.ctrl{resize:vertical;min-height:80px}

/* Custom select arrow */
select.ctrl{
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%239aa0bb' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
  background-repeat:no-repeat;background-position:right 12px center;
  padding-right:34px;
}

/* Job code compound row */
.jc-row{display:flex;align-items:center;gap:8px}
.jc-prefix{
  background:rgba(59,127,245,.08);border:1px solid rgba(59,127,245,.25);
  border-radius:var(--r);color:var(--accent);
  font-family:var(--font-h);font-weight:700;font-size:14px;
  padding:9px 14px;white-space:nowrap;letter-spacing:1px;min-width:72px;text-align:center;
}
.jc-sep{color:var(--muted);font-weight:700;font-size:16px;flex-shrink:0}

/* "Other" reveal field */
.other-wrap{margin-top:7px;display:none}
.other-wrap.show{display:block}
.other-wrap .ctrl{border-color:var(--accent2)}
.other-wrap .ctrl:focus{box-shadow:0 0 0 3px rgba(124,77,240,.12)}

/* Quick-pick tags */
.qtags{display:flex;flex-wrap:wrap;gap:5px;margin-top:6px}
.qtag{
  background:rgba(59,127,245,.06);color:var(--text2);
  border:1px solid var(--border2);border-radius:20px;
  font-size:11px;padding:2px 10px;cursor:pointer;transition:all .15s;
}
.qtag:hover{background:rgba(59,127,245,.14);color:var(--accent);border-color:rgba(59,127,245,.3)}

/* ── Quill overrides ── */
.ql-toolbar{
  background:var(--card2)!important;border:1px solid var(--border)!important;
  border-radius:var(--r) var(--r) 0 0!important;
}
.ql-container{
  background:var(--bg)!important;border:1px solid var(--border)!important;
  border-top:none!important;border-radius:0 0 var(--r) var(--r)!important;
  color:var(--text)!important;font-family:var(--font)!important;
  font-size:13.5px!important;min-height:130px;
}
.ql-toolbar .ql-stroke{stroke:var(--text2)!important}
.ql-toolbar .ql-fill{fill:var(--text2)!important}
.ql-toolbar button:hover .ql-stroke,.ql-toolbar button.ql-active .ql-stroke{stroke:var(--accent)!important}
.ql-toolbar button:hover .ql-fill,.ql-toolbar button.ql-active .ql-fill{fill:var(--accent)!important}
.ql-toolbar .ql-picker-label{color:var(--text2)!important}
.ql-editor.ql-blank::before{color:var(--muted)!important;font-style:normal!important}
.ql-editor{color:var(--text)!important;min-height:130px}
.ql-picker-options{background:var(--surface)!important;border-color:var(--border)!important}
.ql-picker-item{color:var(--text2)!important}
.ql-hidden-ta{display:none}

/* ════════════════════════════════════════════
   BUTTONS
════════════════════════════════════════════ */
.btn{
  display:inline-flex;align-items:center;gap:7px;
  border:none;border-radius:var(--r);cursor:pointer;
  font-family:var(--font-h);font-size:13px;font-weight:700;letter-spacing:.3px;
  padding:10px 22px;transition:all .18s;white-space:nowrap;
}
.btn-primary{background:var(--accent);color:#fff;box-shadow:0 4px 14px rgba(59,127,245,.22)}
.btn-primary:hover{background:#5a94ff;box-shadow:0 6px 20px rgba(59,127,245,.35);transform:translateY(-1px)}
.btn-draft{background:rgba(124,77,240,.08);color:var(--accent2);border:1px solid rgba(124,77,240,.25)}
.btn-draft:hover{background:rgba(124,77,240,.16);transform:translateY(-1px)}
.btn-ghost{background:transparent;color:var(--text2);border:1px solid var(--border2)}
.btn-ghost:hover{color:var(--text);border-color:var(--muted)}
.btn-danger{background:rgba(224,69,69,.08);color:var(--danger);border:1px solid rgba(224,69,69,.22)}
.btn-danger:hover{background:rgba(224,69,69,.16)}
.btn-sm{padding:6px 14px;font-size:11px}

/* ════════════════════════════════════════════
   TABLES
════════════════════════════════════════════ */
.table-wrap{overflow-x:auto;border-radius:var(--r2);border:1px solid var(--border);box-shadow:var(--shadow)}
table{width:100%;border-collapse:collapse;font-size:13px}
thead{background:var(--card2)}
thead th{
  padding:11px 14px;text-align:left;font-size:11px;font-weight:600;
  color:var(--text2);text-transform:uppercase;letter-spacing:.8px;
  border-bottom:1px solid var(--border);white-space:nowrap;
}
tbody tr{border-bottom:1px solid var(--border);transition:background .12s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:rgba(59,127,245,.03)}
td{padding:12px 14px;vertical-align:middle;color:var(--text)}
.td-muted{color:var(--text2)}

/* Status badges */
.badge{
  display:inline-flex;align-items:center;gap:4px;
  border-radius:20px;font-size:11px;font-weight:600;
  padding:3px 10px;letter-spacing:.3px;white-space:nowrap;
}
.badge::before{content:'';width:5px;height:5px;border-radius:50%;flex-shrink:0}
.badge-published{background:rgba(31,173,114,.1);color:var(--success)}
.badge-published::before{background:var(--success)}
.badge-draft{background:rgba(232,158,16,.1);color:var(--warn)}
.badge-draft::before{background:var(--warn)}
.badge-closed{background:rgba(154,160,187,.15);color:var(--muted)}
.badge-closed::before{background:var(--muted)}
.badge-archived{background:rgba(224,69,69,.08);color:var(--danger)}
.badge-archived::before{background:var(--danger)}

/* Country badges */
.flag{font-size:16px;vertical-align:middle}
.country-ind{color:#e07b00}
.country-usa{color:#2a4ea6}
.country-cad{color:#c0251a}

/* ════════════════════════════════════════════
   STAT CARDS
════════════════════════════════════════════ */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.stat-card{
  background:var(--card);border:1px solid var(--border);border-radius:var(--r2);
  padding:20px 22px;position:relative;overflow:hidden;
  box-shadow:var(--shadow);
}
.stat-card::before{
  content:'';position:absolute;top:0;left:0;right:0;height:3px;
  background:linear-gradient(90deg,var(--c1),var(--c2));
}
.stat-card.blue{--c1:#3b7ff5;--c2:#7ab3ff}
.stat-card.purple{--c1:#7c4df0;--c2:#b48fff}
.stat-card.green{--c1:#1fad72;--c2:#5cd9a0}
.stat-card.orange{--c1:#e89e10;--c2:#fbbf24}
.stat-label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px}
.stat-value{font-family:var(--font-h);font-size:28px;font-weight:800;color:var(--text);line-height:1}
.stat-sub{font-size:12px;color:var(--text2);margin-top:6px}
.stat-icon{
  position:absolute;right:18px;top:50%;transform:translateY(-50%);
  font-size:32px;opacity:.1;
}

/* ════════════════════════════════════════════
   PAGINATION
════════════════════════════════════════════ */
.pagination{display:flex;gap:5px;align-items:center;margin-top:16px;justify-content:flex-end}
.pg{
  padding:6px 12px;border-radius:var(--r);font-size:13px;
  border:1px solid var(--border);color:var(--text2);background:var(--card);
  cursor:pointer;transition:all .15s;
}
.pg:hover{border-color:var(--accent);color:var(--accent)}
.pg.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.pg.disabled{opacity:.3;cursor:not-allowed}

/* ════════════════════════════════════════════
   FILTERS BAR
════════════════════════════════════════════ */
.filters{
  background:var(--card);border:1px solid var(--border);
  border-radius:var(--r2);padding:16px 20px;margin-bottom:20px;
  display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;
  box-shadow:var(--shadow);
}
.filter-field{display:flex;flex-direction:column;gap:4px;min-width:140px}
.filter-field label{font-size:11px;color:var(--muted);font-weight:500}
.filter-field .ctrl{padding:7px 10px;font-size:13px}
.filter-actions{display:flex;gap:8px;align-items:flex-end;margin-left:auto}

/* ════════════════════════════════════════════
   RESPONSIVE
════════════════════════════════════════════ */
@media(max-width:1024px){
  .g3,.g4{grid-template-columns:1fr 1fr}
  .gc3{grid-column:span 2}
  .stats-row{grid-template-columns:1fr 1fr}
}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .main{margin-left:0}
  .topbar{left:0}
  .g2,.g3,.g4{grid-template-columns:1fr}
  .gc2,.gc3,.gc4{grid-column:span 1}
  .main-body{padding:18px 16px}
  .stats-row{grid-template-columns:1fr 1fr}
}
</style>
</head>
<body>
<div class="admin-layout">

<!-- ═══════ SIDEBAR ═══════ -->
<aside class="sidebar" id="sidebar">
  <div class="sb-logo">
    <a href="<?= ADMIN_URL ?>/index.php">
        <img src="https://www.accelonconsulting.com/wp-content/uploads/2025/07/Accelon-logo.webp">
        </a>
    <small>Admin Panel</small>
  </div>

  <nav class="sb-nav">
    <span class="sb-section">Overview</span>
    <a href="<?= ADMIN_URL ?>/index.php"
       class="sb-item <?= (basename($_SERVER['PHP_SELF'])==='index.php')?'active':'' ?>">
      <span class="sb-icon">⊞</span> Dashboard
    </a>

    <span class="sb-section">Jobs</span>
    <a href="<?= ADMIN_URL ?>/pages/jobs.php"
       class="sb-item <?= (basename($_SERVER['PHP_SELF'])==='jobs.php')?'active':'' ?>">
      <span class="sb-icon">≡</span> All Jobs
      <?php
        try {
          $tc = db()->query("SELECT COUNT(*) FROM jobs WHERE status='published'")->fetchColumn();
          if ($tc > 0) echo '<span class="sb-badge">'.$tc.'</span>';
        } catch(Exception $e){}
      ?>
    </a>
     <a href="<?= ADMIN_URL ?>/pages/clients.php"
       class="sb-item <?= (basename($_SERVER['PHP_SELF'])==='clients.php')?'active':'' ?>">
      <span class="sb-icon">🏢</span> Clients
    </a>
    <a href="<?= ADMIN_URL ?>/pages/post_job.php"
       class="sb-item <?= (basename($_SERVER['PHP_SELF'])==='post_job.php')?'active':'' ?>">
      <span class="sb-icon">＋</span> Post a Job
    </a>
    <a href="<?= ADMIN_URL ?>/pages/jobs.php?status=draft"
       class="sb-item">
      <span class="sb-icon">◎</span> Drafts
      <?php
        try {
          $dc = db()->query("SELECT COUNT(*) FROM jobs WHERE status='draft'")->fetchColumn();
          if ($dc > 0) echo '<span class="sb-badge draft">'.$dc.'</span>';
        } catch(Exception $e){}
      ?>
    </a>

    <span class="sb-section">Settings</span>
   
    <a href="<?= ADMIN_URL ?>/pages/admins.php"
       class="sb-item <?= (basename($_SERVER['PHP_SELF'])==='admins.php')?'active':'' ?>">
      <span class="sb-icon">👤</span> Admin Users
    </a>
  </nav>

  <div class="sb-footer">
    <div class="sb-user">
      <div class="sb-avatar"><?= strtoupper(substr($currentAdmin['name'],0,1)) ?></div>
      <div class="sb-user-info">
        <strong><?= e($currentAdmin['name']) ?></strong>
        <span><?= e($currentAdmin['role']) ?></span>
      </div>
    </div>
    <a href="<?= ADMIN_URL ?>/logout.php" class="sb-logout">
      ⏻ Sign out
    </a>
  </div>
</aside>

<!-- ═══════ TOPBAR ═══════ -->
<div class="topbar">
  <div>
    <div class="topbar-title"><?= e($pageTitle) ?></div>
    <?php if (!empty($breadcrumbs)): ?>
    <div class="topbar-breadcrumb">
      <?php foreach ($breadcrumbs as $i => [$label,$url]): ?>
        <?= $i>0 ? ' › ' : '' ?>
        <?= $url ? '<a href="'.e($url).'">'.e($label).'</a>' : e($label) ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="topbar-actions">
    <a href="https://www.accelonconsulting.com/careers/" target="_blank" class="topbar-btn outline">↗ View Site</a>
    <a href="<?= ADMIN_URL ?>/pages/post_job.php" class="topbar-btn">＋ Post Job</a>
  </div>
</div>

<!-- ═══════ MAIN ═══════ -->
<div class="main">
<div class="main-body">

<?php
// Show flash messages
foreach (['success','error','warn'] as $t) {
    $msg = flash($t);
    if ($msg) echo '<div class="flash flash-'.$t.'">'.($t==='success'?'✓':($t==='error'?'✕':'⚠')).' '.$msg.'</div>';
}
?>