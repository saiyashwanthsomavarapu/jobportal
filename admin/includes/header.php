<?php
// includes/header.php
// Usage: include with $pageTitle already set
$pageTitle = $pageTitle ?? 'Dashboard';
if (!empty($referenceShell)) {
  include __DIR__ . '/reference-header.php';
  return;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($pageTitle) ?> — <?= SITE_NAME ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link href="<?= ADMIN_URL ?>/dashboard.css" rel="stylesheet">
  <?php include __DIR__ . "/theme.php"; ?>
</head>

<body data-theme="accelon" class="bg-[#f6f7fb] text-ink font-sans antialiased">
  <div class="flex min-h-screen">

    <!-- Mobile sidebar toggle -->
    <button id="sidebarToggle" type="button" aria-label="Open navigation" aria-expanded="false" onclick="toggleAdminSidebar()" class="md:hidden fixed top-3 left-3 z-[110] bg-accent text-white p-2 rounded-xl shadow-pop">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    </button>
    <div id="sidebarOverlay" onclick="closeAdminSidebar()" class="fixed inset-0 bg-ink/40 z-[95] hidden md:hidden backdrop-blur-[1px]"></div>

    <!-- ═══════ SIDEBAR ═══════ -->
    <aside class="sidebar" id="dashboardSidebar">
      <a class="brand" href="<?= ADMIN_URL ?>/index.php" aria-label="Accelon Consulting dashboard"><img src="https://www.accelonconsulting.com/wp-content/uploads/2025/07/Accelon-logo.webp" alt="Accelon Consulting" style="display:block;width:auto;height:50px;max-width:170px;padding-left:15px;object-fit:contain"></a>
      <nav class="nav" aria-label="Admin navigation">
        <a class="active" href="<?= ADMIN_URL ?>/index.php"><svg viewBox="0 0 24 24">
            <rect x="4" y="4" width="6" height="6" rx="1" />
            <rect x="14" y="4" width="6" height="6" rx="1" />
            <rect x="4" y="14" width="6" height="6" rx="1" />
            <rect x="14" y="14" width="6" height="6" rx="1" />
          </svg>Dashboard</a>
        <a href="<?= ADMIN_URL ?>/pages/jobs.php">
          <svg viewBox="0 0 24 24">
            <path d="M8 7V5.5A1.5 1.5 0 0 1 9.5 4h5A1.5 1.5 0 0 1 16 5.5V7M5 7h14a1 1 0 0 1 1 1v11H4V8a1 1 0 0 1 1-1Zm7 0v12" />
          </svg>
          Jobs
        </a>
        <a href="<?= ADMIN_URL ?>/pages/clients.php">
          <svg viewBox="0 0 24 24">
            <path d="M3.75 21V6.75A1.5 1.5 0 0 1 5.25 5.25h6A1.5 1.5 0 0 1 12.75 6.75V21M3.75 21h16.5M3.75 21H2.25M20.25 21V10.5a1.5 1.5 0 0 0-1.5-1.5h-3a1.5 1.5 0 0 0-1.5 1.5V21" />
          </svg>
          Clients
        </a>
        <?php if (currentAdminCanWrite()): ?>
          <a href="<?= ADMIN_URL ?>/pages/admins.php">
            <svg viewBox="0 0 24 24">
              <path d="M15.5 8a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0ZM5 20a7 7 0 0 1 14 0M18 5v6M15 8h6" />
            </svg>
            Admin
          </a>
        <?php endif; ?>
      </nav>
      <div class="sidebar-footer">
        <div class="admin-row">
          <span class="admin-avatar">
            <?= e(strtoupper(substr($currentAdmin['name'] ?? 'A', 0, 1))) ?>
          </span>
          <span class="admin-copy">
            <strong>
              <?= e($currentAdmin['name'] ?? 'Admin') ?>
            </strong>
            <small>
              <?= e(adminRoleLabel($currentAdmin['role'] ?? 'admin')) ?>
            </small>
          </span>
          <a class="signout" href="<?= ADMIN_URL ?>/logout.php" title="Sign out" aria-label="Sign out">
            <svg viewBox="0 0 24 24">
              <path d="M10 5H6v14h4m4-4 4-3-4-3m4 3H9" />
            </svg>
          </a>
        </div>
        <?php if (currentAdminCanWrite()): ?>
          <a class="btn btn-primary btn-sm w-full text-white!" href="<?= ADMIN_URL ?>/pages/post_job.php"><span>+</span> Create Job</a>
        <?php endif; ?>
      </div>
    </aside>


    <script>
      function setAdminSidebarOpen(isOpen) {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        var toggle = document.getElementById('sidebarToggle');

        if (sidebar) {
          sidebar.classList.toggle('is-open', isOpen);
          sidebar.style.translate = isOpen ? '0 0' : '';
          sidebar.style.transform = isOpen ? 'translateX(0)' : '';
        }

        if (overlay) {
          overlay.classList.toggle('hidden', !isOpen);
          overlay.classList.toggle('is-open', isOpen);
        }

        if (toggle) {
          toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }

        document.body.classList.toggle('overflow-hidden', isOpen);
      }

      function toggleAdminSidebar() {
        var sidebar = document.getElementById('sidebar');
        setAdminSidebarOpen(!sidebar || !sidebar.classList.contains('is-open'));
      }

      function closeAdminSidebar() {
        setAdminSidebarOpen(false);
      }

      document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
          closeAdminSidebar();
        }
      });

      window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
          closeAdminSidebar();
        }
      });
    </script>

    <!-- ═══════ MAIN COLUMN ═══════ -->
    <div class="flex-1 flex flex-col min-w-0 max-md:mt-0 min-h-screen md:ml-[256px]">

      <!-- TOPBAR -->
      <div class="sticky top-0 mb-0 max-md:mt-14 bg-surface border-b border-line
                  flex min-h-[98px] items-center px-7 lg:px-9 py-4 gap-5 z-[90]">
        <div class="flex-1 min-w-0">
          <div class="font-head text-[24px] font-bold text-ink tracking-tight truncate"><?= e($pageTitle) ?></div>
          <?php if (!empty($breadcrumbs)): ?>
            <div class="text-[15px] text-muted mt-1 flex items-center gap-1.5 flex-wrap">
              <?php foreach ($breadcrumbs as $i => [$label, $url]): ?>
                <?php if ($i > 0): ?><span class="text-line2">/</span><?php endif; ?>
                <?= $url ? '<a href="' . e($url) . '" class="text-accent hover:underline">' . e($label) . '</a>' : '<span>' . e($label) . '</span>' ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <form method="GET" action="<?= ADMIN_URL ?>/pages/jobs.php" role="search" class="hidden lg:flex items-center gap-2 bg-white border border-line rounded-xl px-4 py-3 w-[min(36vw,460px)] text-muted focus-within:border-accent focus-within:ring-2 focus-within:ring-accent/10">
          <button type="submit" class="shrink-0 text-muted transition hover:text-accent" aria-label="Search jobs">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
            </svg>
          </button>
          <input type="search" name="q" value="<?= basename($_SERVER['PHP_SELF']) === 'jobs.php' ? e(trim($_GET['q'] ?? '')) : '' ?>" placeholder="Search jobs, title, code or client..." class="bg-transparent border-none outline-none text-sm text-ink w-full placeholder-muted">
        </form>

        <div class="flex items-center gap-2 flex-shrink-0">
          <a href="<?= SITE_URL ?>/" target="_blank"
            class="hidden sm:flex items-center gap-1.5 bg-transparent text-ink2 border border-line2 rounded-lg font-head
                   text-[12px] font-bold tracking-wide px-3.5 py-2 hover:text-ink hover:border-muted hover:bg-card2 transition-all">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
            </svg>
            View Site
          </a>
          <?php if (currentAdminCanWrite()): ?>
            <a href="<?= ADMIN_URL ?>/pages/post_job.php"
              class="flex items-center gap-1.5 bg-accent text-white rounded-lg font-head text-[12px] font-bold
                     tracking-wide px-4 py-2 shadow-pop hover:bg-accent-dark transition-all">
              <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
              </svg>
              Create Job
            </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- MAIN BODY -->
      <div class="flex-1 bg-[#f6f7fb] px-7 py-7 lg:px-9 lg:py-8 max-md:px-4 max-md:py-5 overflow-y-auto">

        <?php
        // Show flash messages
        $flashIcons = [
          'success' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>',
          'error'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>',
          'warn'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        ];
        $flashStyles = [
          'success' => 'bg-success/[.08] border border-success/[.20] text-success',
          'error'   => 'bg-danger/[.08] border border-danger/[.20] text-danger',
          'warn'    => 'bg-warn/[.08] border border-warn/[.20] text-warn',
        ];
        foreach (['success', 'error', 'warn'] as $t) {
          $msg = flash($t);
          if ($msg) {
            echo '<div class="flex items-start gap-2.5 px-4 py-3 rounded-xl mb-5 text-sm font-medium ' . $flashStyles[$t] . '">'
              . '<svg class="h-[18px] w-[18px] flex-shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' . $flashIcons[$t] . '</svg>'
              . '<span>' . $msg . '</span></div>';
          }
        }
        ?>
        <!-- Page content continues below this include -->
