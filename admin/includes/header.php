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

  <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <!-- <script src="https://cdn.tailwindcss.com"></script> -->
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css" />
  <script>
    // tailwind.config = {
    //   theme: {
    //     extend: {
    //       colors: {
    //         bg: '#f4f5f8',
    //         surface: '#ffffff',
    //         card2: '#f8f9fc',
    //         line: '#e7e9f0',
    //         line2: '#d8dbe6',
    //         accent: '#1A4C8F',
    //         'accent-dark': '#123863',
    //         'accent-soft': '#eaf1fa',
    //         ink: '#111827',
    //         ink2: '#5b6072',
    //         muted: '#9aa0b4',
    //         danger: '#dc3545',
    //         warn: '#d97706',
    //         success: '#0f9d63',
    //       },
    //       fontFamily: {
    //         head: ['Syne', 'sans-serif'],
    //         sans: ['DM Sans', 'sans-serif'],
    //       },
    //       boxShadow: {
    //         card: '0 1px 2px rgba(17,24,39,.04), 0 8px 24px -12px rgba(17,24,39,.08)',
    //         pop: '0 4px 14px rgba(26,76,143,.22)',
    //       },
    //     }
    //   }
    // }
  </script>
  <style type="text/tailwindcss">
    :root {
    --color-base-100: #ffffff;
    --color-base-200: #f8f9fc;
    --color-base-300: #e7e9f0;
    --color-base-content: #111827;

    --color-primary: #1A4C8F;
    --color-primary-content: #ffffff;

    --color-secondary: #5b6072;
    --color-secondary-content: #ffffff;

    --color-accent: #1A4C8F;
    --color-accent-content: #ffffff;

    --color-neutral: #111827;
    --color-neutral-content: #ffffff;

    --color-info: #2563eb;
    --color-success: #0f9d63;
    --color-warning: #d97706;
    --color-error: #dc3545;
  }
    @theme {
    --color-bg: #f4f5f8;
    --color-surface: #ffffff;
    --color-card2: #f8f9fc;
    --color-line: #e7e9f0;
    --color-line2: #d8dbe6;

    --color-accent: #1A4C8F;
    --color-accent-dark: #123863;
    --color-accent-soft: #eaf1fa;

    --color-ink: #111827;
    --color-ink2: #5b6072;
    --color-muted: #9aa0b4;

    --color-danger: #dc3545;
    --color-warn: #d97706;
    --color-success: #0f9d63;

    --font-head: "Syne", sans-serif;
    --font-sans: "DM Sans", sans-serif;

    --shadow-card:
    0 1px 2px rgba(17, 24, 39, .04),
    0 8px 24px -12px rgba(17, 24, 39, .08);

    --shadow-pop:
    0 4px 14px rgba(26, 76, 143, .22);
    }
    </style>
  <style>
    body {
      font-size: 14px;
      -webkit-font-smoothing: antialiased;
    }

    ::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }

    ::-webkit-scrollbar-track {
      background: transparent;
    }

    ::-webkit-scrollbar-thumb {
      background: #d8dbe6;
      border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: #9aa0b4;
    }

    .ql-toolbar {
      background: #f8f9fc !important;
      border: 1px solid #e7e9f0 !important;
      border-radius: 10px 10px 0 0 !important;
    }

    .ql-container {
      background: #ffffff !important;
      border: 1px solid #e7e9f0 !important;
      border-top: none !important;
      border-radius: 0 0 10px 10px !important;
      color: #111827 !important;
      font-family: 'DM Sans', sans-serif !important;
      font-size: 13.5px !important;
      min-height: 130px;
    }

    .ql-toolbar .ql-stroke {
      stroke: #5b6072 !important;
    }

    .ql-toolbar .ql-fill {
      fill: #5b6072 !important;
    }

    .ql-toolbar button:hover .ql-stroke,
    .ql-toolbar button.ql-active .ql-stroke {
      stroke: #1A4C8F !important;
    }

    .ql-toolbar button:hover .ql-fill,
    .ql-toolbar button.ql-active .ql-fill {
      fill: #1A4C8F !important;
    }

    .ql-toolbar .ql-picker-label {
      color: #5b6072 !important;
    }

    .ql-editor.ql-blank::before {
      color: #9aa0b4 !important;
      font-style: normal !important;
    }

    .ql-editor {
      color: #111827 !important;
      min-height: 130px;
    }

    .ql-picker-options {
      background: #ffffff !important;
      border-color: #e7e9f0 !important;
    }

    .ql-picker-item {
      color: #5b6072 !important;
    }

    .ql-hidden-ta {
      display: none;
    }
  </style>
</head>

<body class="bg-bg text-ink font-sans antialiased">
  <div class="flex min-h-screen p-3 gap-3 max-md:p-0 max-md:gap-0">

    <!-- Mobile sidebar toggle -->
    <button id="sidebarToggle" class="md:hidden fixed top-3 left-3 z-[110] bg-accent text-white p-2 rounded-xl shadow-pop">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    </button>
    <div id="sidebarOverlay" class="fixed inset-0 bg-ink/40 z-[95] hidden md:hidden backdrop-blur-[1px]"></div>

    <!-- ═══════ SIDEBAR ═══════ -->
    <aside id="sidebar"
      class="fixed md:static top-3 left-3 md:top-0 md:left-0 h-[calc(100vh-24px)] md:h-[calc(100vh-24px)]
             w-[240px] flex-shrink-0 bg-surface border border-line rounded-2xl shadow-card
             flex flex-col overflow-hidden z-[100]
             -translate-x-[calc(100%+24px)] md:translate-x-0 transition-transform duration-300">

      <div class="px-5 pt-5 pb-4 border-b border-line flex-shrink-0">
        <a href="<?= ADMIN_URL ?>/index.php" class="block">
          <img src="https://www.accelonconsulting.com/wp-content/uploads/2025/07/Accelon-logo.webp" class="max-w-[130px] block">
        </a>
        <div class="flex items-center gap-1.5 mt-2">
          <span class="w-1.5 h-1.5 rounded-full bg-accent"></span>
          <small class="text-[10.5px] font-semibold text-muted tracking-[1.2px] uppercase">Admin Panel</small>
        </div>
      </div>

      <nav class="py-4 px-3 flex-1 overflow-y-auto space-y-0.5">

        <p class="px-2.5 pt-2 pb-1.5 text-[10.5px] font-bold text-muted tracking-[1.1px] uppercase">Overview</p>

        <?php $isActive = basename($_SERVER['PHP_SELF']) === 'index.php'; ?>
        <a href="<?= ADMIN_URL ?>/index.php"
          class="group flex items-center gap-3 px-2.5 py-2 rounded-lg text-[13.5px] font-medium transition-colors
                 <?= $isActive ? 'bg-accent-soft text-accent-dark' : 'text-ink2 hover:bg-card2 hover:text-ink' ?>">
          <svg class="h-[18px] w-[18px] flex-shrink-0 <?= $isActive ? 'text-accent' : 'text-muted group-hover:text-ink2' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75A1.5 1.5 0 015.25 5.25h4.5A1.5 1.5 0 0111.25 6.75v4.5a1.5 1.5 0 01-1.5 1.5h-4.5a1.5 1.5 0 01-1.5-1.5v-4.5zM12.75 6.75a1.5 1.5 0 011.5-1.5h4.5a1.5 1.5 0 011.5 1.5v4.5a1.5 1.5 0 01-1.5 1.5h-4.5a1.5 1.5 0 01-1.5-1.5v-4.5zM3.75 15.75a1.5 1.5 0 011.5-1.5h4.5a1.5 1.5 0 011.5 1.5v4.5a1.5 1.5 0 01-1.5 1.5h-4.5a1.5 1.5 0 01-1.5-1.5v-4.5zM12.75 15.75a1.5 1.5 0 011.5-1.5h4.5a1.5 1.5 0 011.5 1.5v4.5a1.5 1.5 0 01-1.5 1.5h-4.5a1.5 1.5 0 01-1.5-1.5v-4.5z" />
          </svg>
          Dashboard
        </a>

        <p class="px-2.5 pt-4 pb-1.5 text-[10.5px] font-bold text-muted tracking-[1.1px] uppercase">Jobs</p>

        <?php $isActive = basename($_SERVER['PHP_SELF']) === 'jobs.php'; ?>
        <a href="<?= ADMIN_URL ?>/pages/jobs.php"
          class="group flex items-center gap-3 px-2.5 py-2 rounded-lg text-[13.5px] font-medium transition-colors
                 <?= $isActive ? 'bg-accent-soft text-accent-dark' : 'text-ink2 hover:bg-card2 hover:text-ink' ?>">
          <svg class="h-[18px] w-[18px] flex-shrink-0 <?= $isActive ? 'text-accent' : 'text-muted group-hover:text-ink2' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6V4.5A1.5 1.5 0 0110.5 3h3A1.5 1.5 0 0115 4.5V6m-9 0h12a1.5 1.5 0 011.5 1.5v10.5a1.5 1.5 0 01-1.5 1.5H6a1.5 1.5 0 01-1.5-1.5V7.5A1.5 1.5 0 016 6z" />
          </svg>
          <span class="flex-1">All Jobs</span>
          <?php
          try {
            $tc = db()->query("SELECT COUNT(*) FROM jobs WHERE status='published'")->fetchColumn();
            if ($tc > 0) echo '<span class="bg-accent text-white rounded-full text-[10px] font-bold px-[7px] py-px leading-[16px]">' . $tc . '</span>';
          } catch (Exception $e) {
          }
          ?>
        </a>

        <?php $isActive = basename($_SERVER['PHP_SELF']) === 'clients.php'; ?>
        <a href="<?= ADMIN_URL ?>/pages/clients.php"
          class="group flex items-center gap-3 px-2.5 py-2 rounded-lg text-[13.5px] font-medium transition-colors
                 <?= $isActive ? 'bg-accent-soft text-accent-dark' : 'text-ink2 hover:bg-card2 hover:text-ink' ?>">
          <svg class="h-[18px] w-[18px] flex-shrink-0 <?= $isActive ? 'text-accent' : 'text-muted group-hover:text-ink2' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21V6.75A1.5 1.5 0 015.25 5.25h6A1.5 1.5 0 0112.75 6.75V21M3.75 21h16.5M3.75 21H2.25M20.25 21V10.5a1.5 1.5 0 00-1.5-1.5h-3a1.5 1.5 0 00-1.5 1.5V21m3-15h.008v.008H18v-.008zm0 3h.008v.008H18v-.008zm0 3h.008v.008H18v-.008zM6.75 9h.008v.008H6.75V9zm0 3h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm3-6h.008v.008H9.75V9zm0 3h.008v.008H9.75V12zm0 3h.008v.008H9.75V15z" />
          </svg>
          Clients
        </a>

        <?php $isActive = basename($_SERVER['PHP_SELF']) === 'post_job.php'; ?>
        <a href="<?= ADMIN_URL ?>/pages/post_job.php"
          class="group flex items-center gap-3 px-2.5 py-2 rounded-lg text-[13.5px] font-medium transition-colors
                 <?= $isActive ? 'bg-accent-soft text-accent-dark' : 'text-ink2 hover:bg-card2 hover:text-ink' ?>">
          <svg class="h-[18px] w-[18px] flex-shrink-0 <?= $isActive ? 'text-accent' : 'text-muted group-hover:text-ink2' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          Post a Job
        </a>

        <a href="<?= ADMIN_URL ?>/pages/jobs.php?status=draft"
          class="group flex items-center gap-3 px-2.5 py-2 rounded-lg text-[13.5px] font-medium text-ink2 hover:bg-card2 hover:text-ink transition-colors">
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

        <p class="px-2.5 pt-4 pb-1.5 text-[10.5px] font-bold text-muted tracking-[1.1px] uppercase">Settings</p>

        <?php $isActive = basename($_SERVER['PHP_SELF']) === 'admins.php'; ?>
        <a href="<?= ADMIN_URL ?>/pages/admins.php"
          class="group flex items-center gap-3 px-2.5 py-2 rounded-lg text-[13.5px] font-medium transition-colors
                 <?= $isActive ? 'bg-accent-soft text-accent-dark' : 'text-ink2 hover:bg-card2 hover:text-ink' ?>">
          <svg class="h-[18px] w-[18px] flex-shrink-0 <?= $isActive ? 'text-accent' : 'text-muted group-hover:text-ink2' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
          </svg>
          Admin Users
        </a>
      </nav>

      <div class="px-3.5 py-3.5 border-t border-line flex-shrink-0">
        <div class="flex items-center gap-2.5 px-1.5 py-2 rounded-lg mb-1">
          <div class="w-8 h-8 rounded-full bg-accent flex items-center justify-center
                      font-head text-[13px] font-bold text-white flex-shrink-0">
            <?= strtoupper(substr($currentAdmin['name'], 0, 1)) ?>
          </div>
          <div class="min-w-0 flex-1">
            <strong class="block text-[13px] text-ink font-semibold truncate"><?= e($currentAdmin['name']) ?></strong>
            <span class="block text-[11px] text-ink2 capitalize truncate"><?= e($currentAdmin['role']) ?></span>
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

    <!-- ═══════ MAIN COLUMN ═══════ -->
    <div class="flex-1 flex flex-col min-w-0 md:ml-0 max-md:mt-0">

      <!-- TOPBAR -->
      <div class="sticky top-3 md:top-3 mb-3 max-md:mx-3 max-md:mt-16 bg-surface border border-line rounded-2xl shadow-card
                  flex items-center px-6 py-3.5 gap-4 z-[90]">
        <div class="flex-1 min-w-0">
          <div class="font-head text-[17px] font-bold text-ink tracking-tight truncate"><?= e($pageTitle) ?></div>
          <?php if (!empty($breadcrumbs)): ?>
            <div class="text-[12px] text-muted mt-0.5 flex items-center gap-1.5 flex-wrap">
              <?php foreach ($breadcrumbs as $i => [$label, $url]): ?>
                <?php if ($i > 0): ?><span class="text-line2">/</span><?php endif; ?>
                <?= $url ? '<a href="' . e($url) . '" class="text-accent hover:underline">' . e($label) . '</a>' : '<span>' . e($label) . '</span>' ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="hidden lg:flex items-center gap-2 bg-card2 border border-line rounded-lg px-3 py-2 w-64 text-muted">
          <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
          </svg>
          <input type="text" placeholder="Search..." class="bg-transparent border-none outline-none text-[13px] text-ink w-full placeholder-muted">
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">
          <a href="https://www.accelonconsulting.com/careers/" target="_blank"
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
            Post Job
          </a>
        </div>
      </div>

      <!-- MAIN BODY -->
      <div class="flex-1 bg-surface border border-line rounded-2xl shadow-card px-7 py-6 max-md:mx-3 max-md:mb-3 max-md:px-4 max-md:py-4 overflow-y-auto">

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
            echo '<div class="flex items-start gap-2.5 px-4 py-3 rounded-xl mb-5 text-[13.5px] font-medium ' . $flashStyles[$t] . '">'
              . '<svg class="h-[18px] w-[18px] flex-shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' . $flashIcons[$t] . '</svg>'
              . '<span>' . $msg . '</span></div>';
          }
        }
        ?>
        <!-- Page content continues below this include -->