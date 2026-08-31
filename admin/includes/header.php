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
    <aside id="sidebar"
      class="fixed left-0 top-0 z-[100]
           h-screen
           w-[256px]
           overflow-hidden
           border-r border-line
           bg-surface
           flex flex-col
           -translate-x-[calc(100%+24px)]
           transition-transform duration-300
           md:translate-x-0">

      <div class="px-5 pt-5 pb-4 border-b border-line flex-shrink-0">
        <a href="<?= ADMIN_URL ?>/index.php" class="flex items-center gap-2.5" aria-label="Accelon dashboard">
          <svg class="h-10 w-8 shrink-0" viewBox="0 0 40 46" fill="none" aria-hidden="true">
            <path d="M3 42 20 4l17 38" stroke="#2563eb" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="m10 34 18-11" stroke="#f59e0b" stroke-width="4" stroke-linecap="round" />
          </svg>
          <span class="font-head text-[22px] font-extrabold tracking-[-1px] text-gray-950">Accelon</span>
        </a>
        <div class="flex items-center gap-1.5 mt-2">
          <span class="w-1.5 h-1.5 rounded-full bg-accent"></span>
          <small class="text-[11px] font-semibold text-muted tracking-[1.2px] uppercase">Admin Panel</small>
        </div>
      </div>

      <nav class="py-4 px-3 flex-1 overflow-y-auto space-y-0.5">

        <p class="px-2.5 pt-2 pb-1.5 text-[11px] font-bold text-muted tracking-[1.1px] uppercase">Overview</p>

        <?php $isActive = basename($_SERVER['PHP_SELF']) === 'index.php'; ?>
        <a href="<?= ADMIN_URL ?>/index.php"
          class="group flex items-center gap-3 px-2.5 py-2.5 rounded-lg text-[16px] font-semibold transition-colors
                 <?= $isActive ? 'bg-accent-soft text-accent-dark' : 'text-ink2 hover:bg-[#e2eaf4] hover:text-ink' ?>">
          <svg class="h-[18px] w-[18px] flex-shrink-0 <?= $isActive ? 'text-accent' : 'text-muted group-hover:text-ink2' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75A1.5 1.5 0 015.25 5.25h4.5A1.5 1.5 0 0111.25 6.75v4.5a1.5 1.5 0 01-1.5 1.5h-4.5a1.5 1.5 0 01-1.5-1.5v-4.5zM12.75 6.75a1.5 1.5 0 011.5-1.5h4.5a1.5 1.5 0 011.5 1.5v4.5a1.5 1.5 0 01-1.5 1.5h-4.5a1.5 1.5 0 01-1.5-1.5v-4.5zM3.75 15.75a1.5 1.5 0 011.5-1.5h4.5a1.5 1.5 0 011.5 1.5v4.5a1.5 1.5 0 01-1.5 1.5h-4.5a1.5 1.5 0 01-1.5-1.5v-4.5zM12.75 15.75a1.5 1.5 0 011.5-1.5h4.5a1.5 1.5 0 011.5 1.5v4.5a1.5 1.5 0 01-1.5 1.5h-4.5a1.5 1.5 0 01-1.5-1.5v-4.5z" />
          </svg>
          Dashboard
        </a>

        <p class="px-2.5 pt-4 pb-1.5 text-[11px] font-bold text-muted tracking-[1.1px] uppercase">Jobs</p>

        <?php $isActive = basename($_SERVER['PHP_SELF']) === 'jobs.php'; ?>
        <a href="<?= ADMIN_URL ?>/pages/jobs.php"
          class="group flex items-center gap-3 px-2.5 py-2.5 rounded-lg text-[16px] font-semibold transition-colors
                 <?= $isActive ? 'bg-accent-soft text-accent-dark' : 'text-ink2 hover:bg-[#e2eaf4] hover:text-ink' ?>">
          <svg class="h-[18px] w-[18px] flex-shrink-0 <?= $isActive ? 'text-accent' : 'text-muted group-hover:text-ink2' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6V4.5A1.5 1.5 0 0110.5 3h3A1.5 1.5 0 0115 4.5V6m-9 0h12a1.5 1.5 0 011.5 1.5v10.5a1.5 1.5 0 01-1.5 1.5H6a1.5 1.5 0 01-1.5-1.5V7.5A1.5 1.5 0 016 6z" />
          </svg>
          <span class="flex-1">All Jobs</span>
          <?php
          try {
            $tc = db()->query("SELECT COUNT(*) FROM jobs WHERE status='published' AND (close_date IS NULL OR close_date >= CURDATE())")->fetchColumn();
            if ($tc > 0) echo '<span class="bg-accent text-white rounded-full text-[10px] font-bold px-[7px] py-px leading-[16px]">' . $tc . '</span>';
          } catch (Exception $e) {
          }
          ?>
        </a>

        <?php $isActive = basename($_SERVER['PHP_SELF']) === 'admins.php'; ?>
        <a href="<?= ADMIN_URL ?>/pages/admins.php"
          class="group flex items-center gap-3 px-2.5 py-2.5 rounded-lg text-[16px] font-semibold transition-colors
                 <?= $isActive ? 'bg-accent-soft text-accent-dark' : 'text-ink2 hover:bg-[#e2eaf4] hover:text-ink' ?>">
          <svg class="h-[18px] w-[18px] flex-shrink-0 <?= $isActive ? 'text-accent' : 'text-muted group-hover:text-ink2' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
          </svg>
          Admin
        </a>

        <?php $isActive = basename($_SERVER['PHP_SELF']) === 'clients.php'; ?>
        <a href="<?= ADMIN_URL ?>/pages/clients.php"
          class="group flex items-center gap-3 px-2.5 py-2.5 rounded-lg text-[16px] font-semibold transition-colors
                 <?= $isActive ? 'bg-accent-soft text-accent-dark' : 'text-ink2 hover:bg-[#e2eaf4] hover:text-ink' ?>">
          <svg class="h-[18px] w-[18px] flex-shrink-0 <?= $isActive ? 'text-accent' : 'text-muted group-hover:text-ink2' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21V6.75A1.5 1.5 0 015.25 5.25h6A1.5 1.5 0 0112.75 6.75V21M3.75 21h16.5M3.75 21H2.25M20.25 21V10.5a1.5 1.5 0 00-1.5-1.5h-3a1.5 1.5 0 00-1.5 1.5V21m3-15h.008v.008H18v-.008zm0 3h.008v.008H18v-.008zm0 3h.008v.008H18v-.008zM6.75 9h.008v.008H6.75V9zm0 3h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm3-6h.008v.008H9.75V9zm0 3h.008v.008H9.75V12zm0 3h.008v.008H9.75V15z" />
          </svg>
          Clients
        </a>

        <?php $isActive = basename($_SERVER['PHP_SELF']) === 'post_job.php'; ?>
        <a href="<?= ADMIN_URL ?>/pages/post_job.php"
          class="group flex items-center gap-3 px-2.5 py-2.5 rounded-lg text-[16px] font-semibold transition-colors
                 <?= $isActive ? 'bg-accent-soft text-accent-dark' : 'text-ink2 hover:bg-[#e2eaf4] hover:text-ink' ?>">
          <svg class="h-[18px] w-[18px] flex-shrink-0 <?= $isActive ? 'text-accent' : 'text-muted group-hover:text-ink2' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          Create Job
        </a>

        <a href="<?= ADMIN_URL ?>/pages/jobs.php?status=draft"
          class="group flex items-center gap-3 px-2.5 py-2.5 rounded-lg text-[16px] font-semibold text-ink2 hover:bg-[#e2eaf4] hover:text-ink transition-colors">
          <svg class="h-[18px] w-[18px] flex-shrink-0 text-muted group-hover:text-ink2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-9-3.75h-1.5m1.5 0V21a2.25 2.25 0 002.25 2.25h13.5A2.25 2.25 0 0021 21V9a2.25 2.25 0 00-.659-1.591l-4.75-4.75A2.25 2.25 0 0013.999.75H6.75A2.25 2.25 0 004.5 3v6" />
          </svg>
          <span class="flex-1">Drafts</span>
          <?php
          try {
            $dc = db()->query("SELECT COUNT(*) FROM jobs WHERE status='draft'")->fetchColumn();
            if ($dc > 0) echo '<span class="bg-warn text-white rounded-full text-[10px] font-bold px-[7px] py-px leading-[16px]">' . $dc . '</span>';
          } catch (Exception $e) {
          }
          ?>
        </a>

      </nav>

      <div class="px-3.5 py-3.5 border-t border-line flex-shrink-0">
        <div class="flex items-center gap-2.5 px-1.5 py-2 rounded-lg mb-1">
          <div class="w-8 h-8 rounded-full bg-accent flex items-center justify-center
                      font-head text-sm font-bold text-white flex-shrink-0">
            <?= strtoupper(substr($currentAdmin['name'], 0, 1)) ?>
          </div>
          <div class="min-w-0 flex-1">
            <strong class="block text-sm text-ink font-semibold truncate"><?= e($currentAdmin['name']) ?></strong>
            <span class="block text-xs text-ink2 capitalize truncate"><?= e($currentAdmin['role']) ?></span>
          </div>
          <a href="<?= ADMIN_URL ?>/logout.php" title="Sign out"
            class="flex-shrink-0 p-1.5 rounded-md text-muted hover:text-danger hover:bg-danger/[.06] transition-colors">
            <svg class="h-[17px] w-[17px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0110.5 3h6a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0116.5 21h-6a2.25 2.25 0 01-2.25-2.25V15m-3 0l-3-3m0 0l3-3m-3 3H15" />
            </svg>
          </a>
        </div>
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
          <a href="<?= ADMIN_URL ?>/pages/post_job.php"
            class="flex items-center gap-1.5 bg-accent text-white rounded-lg font-head text-[12px] font-bold
                   tracking-wide px-4 py-2 shadow-pop hover:bg-accent-dark transition-all">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Create Job
          </a>
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