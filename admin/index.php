<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/DB/queries.php';

$total = $published = $drafts = $closed = 0;
$recent = $byCountry = [];

try {
  $pdo = db();

  // Dashboard stats
  $counts = $pdo->query($COUNT_BY_STATUS)->fetch() ?: [];

  $total = (int)($counts['total'] ?? 0);
  $published = (int)($counts['published'] ?? 0);
  $drafts = (int)($counts['drafts'] ?? 0);
  $closed = (int)($counts['closed'] ?? 0);

  // Recent jobs
  $recent = $pdo->query($RECENT_JOBS)->fetchAll();
  // Jobs by country
  $byCountry = $pdo->query($JOBS_COUNT_BY_COUNTRY)->fetchAll();
} catch (Exception $e) {
  // Preserve a useful dashboard shell when the database is temporarily unavailable.
  $total = $published = $drafts = $closed = 0;
}

function dashboardFlag(?string $country): string
{
  return match (strtolower(trim((string)$country))) {
    'united states', 'usa', 'us' => '🇺🇸',
    'canada' => '🇨🇦',
    'india' => '🇮🇳',
    default => '🌐',
  };
}

function dashboardStatus(array $job): array
{
  $status = strtolower((string)($job['status'] ?? 'draft'));
  if ($status === 'published' && !empty($job['close_date']) && strtotime($job['close_date']) < strtotime('today')) $status = 'closed';
  return match ($status) {
    'published' => ['Published', 'status-published'],
    'closed' => ['Closed', 'status-closed'],
    'archived' => ['Pending Review', 'status-pending'],
    default => ['Draft', 'status-draft'],
  };
}

$stats = [
  ['Total Jobs', $total, 'blue', 'briefcase', ADMIN_URL . '/pages/jobs.php'],
  ['Published', $published, 'green', 'globe', ADMIN_URL . '/pages/jobs.php?status=published'],
  ['Drafts', $drafts, 'yellow', 'document', ADMIN_URL . '/pages/jobs.php?status=draft'],
  ['Closed', $closed, 'gray', 'archive', ADMIN_URL . '/pages/jobs.php?status=closed'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard — <?= e(SITE_NAME) ?> Recruitment</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css">
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css">
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <?php include __DIR__ . '/includes/theme.php'; ?>
  <link href="<?= ADMIN_URL ?>/dashboard.css" rel="stylesheet">
  <style>
    .stats-grid .stat-card {
      min-height: 98px;
      padding: 22px;
      justify-content: space-between;
      gap: 20px
    }

    .stats-grid .stat-card div span {
      font-size: 16px
    }

    .stats-grid .stat-card strong {
      margin-top: 8px;
      font-size: 32px
    }

    .stats-grid .stat-icon {
      width: 42px;
      height: 42px;
      flex: 0 0 42px;
      border-radius: 11px;
      margin-top: 10px
    }

    .stats-grid .stat-icon svg {
      width: 40px
    }

    @media(max-width:620px) {
      .stats-grid .stat-card {
        min-height: 94px;
        padding: 16px;
        gap: 12px
      }

      .stats-grid .stat-card div span {
        font-size: 13px
      }

      .stats-grid .stat-card strong {
        margin-top: 6px;
        font-size: 27px
      }

      .stats-grid .stat-icon {
        width: 34px;
        height: 34px;
        flex-basis: 34px;
        border-radius: 9px
      }

      .stats-grid .stat-icon svg {
        width: 18px
      }
    }
  </style>
</head>

<body data-theme="accelon">
  <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Open navigation" aria-expanded="false"><svg viewBox="0 0 24 24">
      <path d="M4 7h16M4 12h16M4 17h16" />
    </svg></button>
  <div class="sidebar-overlay" id="sidebarOverlay"></div>
  <aside class="sidebar" id="dashboardSidebar">
    <a class="brand" href="<?= ADMIN_URL ?>/index.php" aria-label="Accelon Consulting dashboard"><img src="https://www.accelonconsulting.com/wp-content/uploads/2025/07/Accelon-logo.webp" alt="Accelon Consulting" style="display:block;width:auto;height:50px;max-width:170px;padding-left:15px;object-fit:contain"></a>
    <nav class="nav" aria-label="Admin navigation">
      <a class="active" href="<?= ADMIN_URL ?>/index.php"><svg viewBox="0 0 24 24">
          <rect x="4" y="4" width="6" height="6" rx="1" />
          <rect x="14" y="4" width="6" height="6" rx="1" />
          <rect x="4" y="14" width="6" height="6" rx="1" />
          <rect x="14" y="14" width="6" height="6" rx="1" />
        </svg>Dashboard</a>
      <a href="<?= ADMIN_URL ?>/pages/jobs.php"><svg viewBox="0 0 24 24">
          <path d="M8 7V5.5A1.5 1.5 0 0 1 9.5 4h5A1.5 1.5 0 0 1 16 5.5V7M5 7h14a1 1 0 0 1 1 1v11H4V8a1 1 0 0 1 1-1Zm7 0v12" />
        </svg>Jobs</a>
      <a href="<?= ADMIN_URL ?>/pages/clients.php">
        <svg viewBox="0 0 24 24">
          <path d="M3.75 21V6.75A1.5 1.5 0 0 1 5.25 5.25h6A1.5 1.5 0 0 1 12.75 6.75V21M3.75 21h16.5M3.75 21H2.25M20.25 21V10.5a1.5 1.5 0 0 0-1.5-1.5h-3a1.5 1.5 0 0 0-1.5 1.5V21" />
        </svg>Clients
      </a>
      <a href="<?= ADMIN_URL ?>/pages/admins.php"><svg viewBox="0 0 24 24">
          <path d="M15.5 8a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0ZM5 20a7 7 0 0 1 14 0M18 5v6M15 8h6" />
        </svg>Admin</a>
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
            <?= e(ucfirst($currentAdmin['role'] ?? 'admin')) ?>
          </small>
        </span>
        <a class="signout" href="<?= ADMIN_URL ?>/logout.php" title="Sign out" aria-label="Sign out">
          <svg viewBox="0 0 24 24">
            <path d="M10 5H6v14h4m4-4 4-3-4-3m4 3H9" />
          </svg>
        </a>
      </div>
      <a class="btn btn-primary btn-sm w-full text-white!" href="<?= ADMIN_URL ?>/pages/post_job.php"><span>+</span> Create Job</a>
    </div>
  </aside>
  <main class="main">
    <header class="page-header">
      <h1>Dashboard</h1>
    </header>
    <div class="dashboard-content">
      <section class="stats-grid" aria-label="Job statistics">
        <?php foreach ($stats as [$label, $value, $tone, $icon, $url]): ?>
          <a class="stat-card" href="<?= e($url) ?>">
            <div>
              <span>
                <?= e($label) ?>
              </span>
              <strong>
                <?= number_format($value) ?>
              </strong>
            </div>
            <span class="stat-icon tone-<?= $tone ?>">
              <?php if ($icon === 'briefcase'): ?>
                <svg viewBox="0 0 24 24">
                  <path d="M8 7V5.5A1.5 1.5 0 0 1 9.5 4h5A1.5 1.5 0 0 1 16 5.5V7M5 7h14v12H5V7Zm7 0v12" />
                </svg>
              <?php elseif ($icon === 'globe'): ?>
                <svg viewBox="0 0 24 24">
                  <circle cx="12" cy="12" r="8" />
                  <path d="M4 12h16M12 4c2 2.3 3 5 3 8s-1 5.7-3 8c-2-2.3-3-5-3-8s1-5.7 3-8Z" />
                </svg>
              <?php elseif ($icon === 'document'): ?>
                <svg viewBox="0 0 24 24">
                  <path d="M7 4h7l4 4v12H7V4Zm7 0v4h4M10 12h5m-5 3h5" />
                </svg>
              <?php elseif ($icon === 'clock'): ?>
                <svg viewBox="0 0 24 24">
                  <circle cx="12" cy="12" r="8" />
                  <path d="M12 7v5l3 2" />
                </svg>
              <?php else: ?>
                <svg viewBox="0 0 24 24">
                  <path d="M5 8h14l-1 11H6L5 8Zm-1-3h16v3H4V5Zm6 7h4" />
                </svg>
              <?php endif; ?>
            </span>
          </a>
        <?php endforeach; ?>
      </section>
      <section class="dashboard-grid">
        <div class="recent-panel card overflow-visible rounded-xl border border-base-300 bg-base-100 shadow-xs">
          <div class="bulk-bar flex min-h-14 items-center justify-between gap-3 border-b border-base-300 px-4 py-2 text-sm max-sm:items-start max-sm:flex-col">
            <span>
              <strong><?= number_format(count($recent)) ?></strong> recent jobs
            </span>
            <a class="btn btn-sm btn-ghost text-primary" href="<?= ADMIN_URL ?>/pages/jobs.php">View all <span>→</span></a>
          </div>
          <div class="overflow-x-auto">
            <table class="jobs-table table table-sm min-w-[850px]">
              <thead>
                <tr>
                  <th>Job</th>
                  <th>Location</th>
                  <th>Type</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$recent): ?>
                  <tr>
                    <td colspan="5" class="py-20">
                      <div class="grid place-items-center gap-2 text-center text-base-content/60">
                        <strong class="text-base font-semibold text-base-content">No jobs yet</strong>
                        <span class="text-sm">New job postings will appear here.</span>
                        <a class="btn btn-primary btn-sm mt-2" href="<?= ADMIN_URL ?>/pages/post_job.php">Create Job</a>
                      </div>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($recent as $job): [$statusLabel, $statusClass] = dashboardStatus($job); ?>
                    <tr
                      tabindex="0"
                      role="link"
                      class="group cursor-pointer border-t border-base-300 transition-colors hover:bg-[#F28C28]"
                      onclick="window.location.href='<?= ADMIN_URL ?>/pages/post_job.php?edit=<?= (int)$job['id'] ?>#page-top'"
                      onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click()}">
                      <td class="min-w-64 px-4 py-3.5 align-middle group-hover:bg-[#F28C28]">
                        <strong class="block font-medium text-base-content group-hover:text-white"><?= e($job['job_title']) ?></strong>
                        <small class="mt-1 flex items-center gap-1.5 font-mono text-xs text-base-content/55 group-hover:text-white/80">
                          <code><?= e($job['job_code']) ?></code>
                          <i>·</i> Job Code: <?= e($job["client_code"] ?: 'General') ?>
                        </small>
                      </td>
                      <td class="px-4 py-3.5 align-middle group-hover:bg-[#F28C28]">
                        <span class="flex items-center gap-1.5 whitespace-nowrap text-base-content/80 group-hover:text-white">
                          <b><?= dashboardFlag($job['country']) ?></b>
                          <?= e($job['city'] ?: $job['country']) ?>
                        </span>
                        <small class="mt-1 block text-xs text-base-content/55 group-hover:text-white/80"><?= e($job['workplace_type'] ?: '—') ?></small>
                      </td>
                      <td class="px-4 py-3.5 align-middle group-hover:bg-[#F28C28]">
                        <span class="text-base-content/80 group-hover:text-white"><?= e($job['job_type'] ?: '—') ?></span>
                        <?php if ((int)$job['views'] > 0): ?>
                          <small class="mt-1 flex items-center gap-1 text-xs text-base-content/55 group-hover:text-white/80">
                            <svg class="size-3.5" viewBox="0 0 24 24">
                              <circle cx="9" cy="8" r="3" />
                              <path d="M3.5 19c.5-4 2.3-6 5.5-6s5 2 5.5 6M16 9.5c2.4.3 3.7 1.9 4 4.5" />
                            </svg>
                            <?= (int)$job['views'] ?>
                          </small>
                        <?php endif; ?>
                      </td>
                      <td class="px-4 py-3.5 align-middle group-hover:bg-[#F28C28]">
                        <span class="badge badge-sm <?= $statusClass === 'status-published'
                                                      ? 'badge-success badge-soft'
                                                      : ($statusClass === 'status-closed'
                                                        ? 'badge-error badge-soft'
                                                        : ($statusClass === 'status-pending'
                                                          ? 'badge-neutral badge-soft'
                                                          : 'badge-warning badge-soft')) ?>"><?= e($statusLabel) ?></span>
                      </td>
                      <td class="px-4 py-3.5 align-middle whitespace-nowrap group-hover:bg-[#F28C28]">
                        <time class="text-base-content/80 group-hover:text-white" datetime="<?= e($job['created_at']) ?>"><?= date('M j, Y', strtotime($job['created_at'])) ?></time>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <aside class="panel country-panel">
          <div class="panel-heading">
            <h2>Jobs by Country</h2>
          </div>
          <?php if (!$byCountry): ?><div class="empty-country">No jobs with a country yet.</div><?php else: ?><div class="countries">
              <?php foreach ($byCountry as $row): $count = (int)$row['cnt'];
                                                                                                  $percentage = $total ? round($count / $total * 100) : 0; ?>
                <a href="<?= ADMIN_URL ?>/pages/jobs.php?country=<?= urlencode($row['country']) ?>">
                  <div><span><b><?= dashboardFlag($row['country']) ?></b><?= e($row['country'] === 'United States' ? 'USA' : $row['country']) ?></span><small><?= number_format($count) ?> job<?= $count === 1 ? '' : 's' ?></small></div><span class="country-track"><i style="width:<?= max(4, min(100, $percentage)) ?>%"></i></span>
                </a>
              <?php endforeach; ?>
            </div><?php endif; ?>
        </aside>
      </section>
    </div>
  </main>
  <script>
    const menu = document.getElementById('mobileMenu'),
      sidebar = document.getElementById('dashboardSidebar'),
      overlay = document.getElementById('sidebarOverlay');

    function setMenu(open) {
      sidebar.classList.toggle('open', open);
      overlay.classList.toggle('open', open);
      menu.setAttribute('aria-expanded', String(open));
    }
    menu.addEventListener('click', () => setMenu(!sidebar.classList.contains('open')));
    overlay.addEventListener('click', () => setMenu(false));
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') setMenu(false)
    });
  </script>
</body>

</html>