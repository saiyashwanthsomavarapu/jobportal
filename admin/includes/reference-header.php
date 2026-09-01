<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e(strip_tags(html_entity_decode($pageTitle))) ?> — <?= e(SITE_NAME) ?> Recruitment</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css">
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css">
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <?php include __DIR__ . '/theme.php'; ?>
  <link href="<?= ADMIN_URL ?>/dashboard.css" rel="stylesheet">
  <!-- <link href="<?= ADMIN_URL ?>/form-shell.css" rel="stylesheet"><link href="<?= ADMIN_URL ?>/rich-editor.css" rel="stylesheet"> -->
</head>

<body id="page-top" data-theme="accelon" class="<?= e($referenceBodyClass ?? '') ?>">
  <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Open navigation" aria-expanded="false"><svg viewBox="0 0 24 24">
      <path d="M4 7h16M4 12h16M4 17h16" />
    </svg>
  </button>
  <div class="sidebar-overlay" id="sidebarOverlay"></div>
  <aside class="sidebar" id="dashboardSidebar">
    <a class="brand" href="<?= ADMIN_URL ?>/index.php" aria-label="Accelon Consulting dashboard"><img src="https://www.accelonconsulting.com/wp-content/uploads/2025/07/Accelon-logo.webp" alt="Accelon Consulting" style="display:block;width:auto;height:50px;padding-left:15px;max-width:170px;object-fit:contain"></a>
    <nav class="nav" aria-label="Admin navigation"><a href="<?= ADMIN_URL ?>/index.php"><svg viewBox="0 0 24 24">
          <rect x="4" y="4" width="6" height="6" rx="1" />
          <rect x="14" y="4" width="6" height="6" rx="1" />
          <rect x="4" y="14" width="6" height="6" rx="1" />
          <rect x="14" y="14" width="6" height="6" rx="1" />
        </svg>
        Dashboard
      </a>
      <a class="<?= basename($_SERVER['PHP_SELF']) === 'jobs.php' || basename($_SERVER['PHP_SELF']) === 'post_job.php' ? 'active' : '' ?>" href="<?= ADMIN_URL ?>/pages/jobs.php">
        <svg viewBox="0 0 24 24">
          <path d="M8 7V5.5A1.5 1.5 0 0 1 9.5 4h5A1.5 1.5 0 0 1 16 5.5V7M5 7h14a1 1 0 0 1 1 1v11H4V8a1 1 0 0 1 1-1Zm7 0v12" />
        </svg>Jobs
      </a>
      <a class="<?= basename($_SERVER['PHP_SELF']) === 'jobs.php' || basename($_SERVER['PHP_SELF']) === 'clients.php' ? 'active' : '' ?>" href="<?= ADMIN_URL ?>/pages/clients.php">
        <svg viewBox="0 0 24 24">
          <path d="M3.75 21V6.75A1.5 1.5 0 0 1 5.25 5.25h6A1.5 1.5 0 0 1 12.75 6.75V21M3.75 21h16.5M3.75 21H2.25M20.25 21V10.5a1.5 1.5 0 0 0-1.5-1.5h-3a1.5 1.5 0 0 0-1.5 1.5V21" />
        </svg> Clients
      </a>
      <a class="<?= basename($_SERVER['PHP_SELF']) === 'admins.php' ? 'active' : '' ?>" href="<?= ADMIN_URL ?>/pages/admins.php">
        <svg viewBox="0 0 24 24">
          <path d="M15.5 8a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0ZM5 20a7 7 0 0 1 14 0M18 5v6M15 8h6" />
        </svg>Admin
      </a>
    </nav>
    <div class="sidebar-footer">
      <div class="admin-row">
        <span class="admin-avatar">
          <?= e(strtoupper(substr($currentAdmin['name'] ?? 'A', 0, 1))) ?></span>
        <span class="admin-copy">
          <strong>
            <?= e($currentAdmin['name'] ?? 'Admin') ?>
          </strong>
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
  <main class="form-main">
    <?php if (!empty($isEdit) && !empty($job)): ?>
      <?php
      $editStatus = strtolower($job['status'] ?? 'draft');
      $editStatusLabels = ['published' => 'Published', 'draft' => 'Draft', 'closed' => 'Closed', 'archived' => 'Pending Review'];
      $editStatusLabel = $editStatusLabels[$editStatus] ?? ucfirst($editStatus);
      ?>
      <header class="edit-job-page-header">
        <div class="edit-job-title-block">
          <div class="edit-job-title-row">
            <h1>Edit Job</h1><span class="edit-status-badge edit-status-<?= e($editStatus) ?>"><?= e($editStatusLabel) ?></span>
          </div>
          <p><?= e($job['job_code'] ?? '') ?></p>
        </div>
        <?php if ($editStatus !== 'archived'): ?>
          <form method="POST" action="<?= ADMIN_URL ?>/pages/job_action.php">

            <input type="hidden" name="id" value="<?= (int) $job['id'] ?>">
            <button class="archive-job-button" type="submit" name="a" value="archive">Archive</button>
          </form>
        <?php endif; ?>
      </header>
    <?php elseif (empty($hideReferencePageHeader)): ?>
      <?php
      $shellSectionLabel = $shellSectionLabel ?? 'Jobs';
      $shellSectionUrl = $shellSectionUrl ?? (ADMIN_URL . '/pages/jobs.php');
      ?>
      <header class="form-page-header">
        <div><a href="<?= e($shellSectionUrl) ?>"><?= e($shellSectionLabel) ?></a><span>/</span>
          <h1><?= $pageTitle ?></h1>
        </div>
      </header>
    <?php endif; ?>
    <div class="form-page-content">
      <?php foreach (['success' => 'success', 'error' => 'error', 'warn' => 'warn'] as $key => $kind): $message = flash($key);
        if ($message): ?><div class="form-notice form-notice-<?= $kind ?>"><?= $message ?></div><?php endif;
                                                                                            endforeach; ?>