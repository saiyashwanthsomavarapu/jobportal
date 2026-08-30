<?php
ob_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/admin/config.php';

$pdo = db();

// Get slug from URL
$slug = $_GET['slug'] ?? '';
if (!$slug) {
  header('Location: https://www.accelonconsulting.com/jobportal');
  exit;
}

$stmt = $pdo->prepare("
    SELECT j.*, a.name AS posted_by, REGEXP_REPLACE(j.client_code, '[^0-9]', '') AS client_code
    FROM jobs j
    LEFT JOIN admin_users a ON a.id = j.created_by
    WHERE j.slug = ? AND j.status = 'published'
    LIMIT 1
");
$stmt->execute([$slug]);
$job = $stmt->fetch();

if (!$job) {
  http_response_code(404);
  echo require_once __DIR__ . '/404.php';
  exit;
}

// ── Ensure form_jobcode is present AND path has no double slashes (single, safe redirect) ──
$rawPath = strtok($_SERVER['REQUEST_URI'], '?');

// Collapse repeated slashes (e.g. //jobportal//job-detail.php -> /jobportal/job-detail.php)
$cleanPath = preg_replace('#/{2,}#', '/', $rawPath);

$needsPathFix = ($cleanPath !== $rawPath);
$needsCodeFix = empty($_GET['form_jobcode']) && !empty($job['client_code']);

if ($needsPathFix || $needsCodeFix) {
  $query = $_GET;

  if ($needsCodeFix) {
    $parts   = explode('-', $job['client_code']);
    $jobCode = $parts[1] ?? '';
    if ($jobCode !== '') {
      $query['form_jobcode'] = $jobCode;
    }
  }

  $redirectUrl = $cleanPath . '?' . http_build_query($query);

  ob_end_clean();
  header('Location: ' . $redirectUrl, true, 301);
  exit;
}

// Increment views
$pdo->prepare("UPDATE jobs SET views = views + 1 WHERE id = ?")->execute([$job['id']]);


// ── SIMILAR JOBS (from v_published_jobs view, same country OR workplace_type OR job_type, excluding current) ──
$similarStmt = $pdo->prepare("
    SELECT id, job_title, slug, client_code, city, state_province, country, workplace_type, job_type, experience, salary_rate, salary_boe
    FROM jobs
    WHERE id != ? && status = 'published'
      AND (
          country = ?
          OR workplace_type = ?
          OR job_type = ?
      )
    ORDER BY published_at DESC
    LIMIT 4
");
$similarStmt->execute([
  $job['id'],
  $job['country'],
  $job['workplace_type'],
  $job['job_type'],
]);

$similarJobs = $similarStmt->fetchAll();

// Helper: escape
if (!function_exists('e')) {
  function e($v)
  {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
  }
}

// Flag image helper — returns a Tailwind-styled <img>, no bespoke CSS class needed
function countryFlag(string $country): string
{
  $map = [
    'united states' => 'us.jpg',
    'usa'           => 'us.jpg',
    'us'            => 'us.jpg',
    'india'         => 'india.jpg',
    'canada'        => 'canada.jpg',
  ];
  $file = $map[strtolower(trim($country))] ?? null;
  if ($file) {
    return '<img src="/flags/' . $file . '" alt="' . htmlspecialchars($country, ENT_QUOTES, 'UTF-8') . '" class="inline-block w-5 h-3.5 object-cover rounded-sm align-middle mr-1.5">';
  }
  return '<span class="align-middle mr-1">🌐</span>';
}

// Workplace type → daisyUI semantic badge color
function wpBadgeClass(?string $wp): string
{
  $w = strtolower(trim((string) $wp));
  return match ($w) {
    'remote' => 'badge-info',
    'hybrid' => 'badge-warning',
    'onsite' => 'badge-success',
    default  => 'badge-neutral',
  };
}

// Format date
function fmtDate($d)
{
  if (!$d) return '—';
  return date('M j, Y', strtotime($d));
}

// Days remaining
function daysLeft($close)
{
  if (!$close) return null;
  $diff = (strtotime($close) - time()) / 86400;
  return max(0, (int)ceil($diff));
}

$days = daysLeft($job['close_date']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="accelon">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($job['job_title']) ?> — <?= e($job['job_code']) ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Tailwind CSS v4 + daisyUI v5 (accelon theme lives in includes/theme.php) -->
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />

  <link rel="stylesheet" href="mobified.css">
  <?php include __DIR__ . "/theme.php"; ?>

  <style>
    body {
      font-family: var(--font-sans, "DM Sans", sans-serif);
    }

    .font-head {
      font-family: var(--font-head, "Syne", sans-serif);
    }

    /* Typography for CMS-authored rich text (job description / requirements / perks) */
    .rich-content :where(p) {
      margin-bottom: 1rem;
    }

    .rich-content :where(p:last-child) {
      margin-bottom: 0;
    }

    .rich-content :where(ul) {
      list-style: disc;
      padding-left: 1.25rem;
      margin: .3rem 0 1.1rem;
    }

    .rich-content :where(ol) {
      list-style: decimal;
      padding-left: 1.25rem;
      margin: .3rem 0 1.1rem;
    }

    .rich-content :where(ul, ol) :where(ul, ol) {
      margin: .3rem 0 .3rem;
    }

    .rich-content :where(li) {
      display: list-item;
      margin-bottom: .5rem;
      line-height: 1.7;
      padding-left: .15rem;
    }

    .rich-content :where(li)::marker {
      color: var(--color-primary);
    }

    .rich-content :where(strong) {
      font-weight: 700;
      color: var(--color-ink);
    }

    .rich-content li[class*="MsoList"] {
      list-style: disc;
      text-indent: 0 !important;
      padding-left: 0;
    }

    .rich-content span[style*="font-family: Symbol"],
    .rich-content span[style*="mso-"] {
      display: none;
    }

    /* Style the Fillout popup trigger like the branded Apply Now button */
    .apply-area [data-fillout-embed-type="popup"] {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: .5rem;
      width: 100% !important;
      height: 3.5rem !important;
      padding-inline: 1rem !important;
      background-color: var(--color-primary) !important;
      color: var(--color-primary-content) !important;
      border: none !important;
      border-radius: .75rem !important;
      font-family: inherit;
      font-size: 1rem !important;
      font-weight: 600 !important;
      line-height: 1.5rem;
      cursor: pointer;
      transition: background-color .16s ease;
    }

    .apply-area [data-fillout-embed-type="popup"]:hover {
      background-color: var(--color-accent-dark) !important;
    }
  </style>
</head>

<body class="bg-bg text-base-content min-h-screen">

  <div class="navbar bg-base-100 border-b border-base-300 sticky top-0 z-50 px-4 lg:px-8 print:hidden">
    <div class="navbar-start gap-3">
      <a href="https://accelonconsulting.com">
        <img src="https://www.accelonconsulting.com/wp-content/uploads/2025/07/Accelon-logo.webp" class="h-7 w-auto" alt="Accelon Consulting">
      </a>
      <div class="breadcrumbs hidden md:block text-sm">
        <ul>
          <li><a href="https://accelonconsulting.com/careers" class="text-ink2 hover:text-primary">All Jobs</a></li>
          <li><span class="text-ink font-semibold"><?= e($job['job_title']) ?></span></li>
        </ul>
      </div>
    </div>
    <div class="navbar-end">
      <a href="https://accelonconsulting.com/careers" class="btn btn-ghost btn-sm text-ink2">← View all roles</a>
    </div>
  </div>

  <!-- Main content -->
  <div class="max-w-6xl mx-auto px-4 lg:px-8 py-8 lg:py-10 grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

    <div class="lg:col-span-2 min-w-0 flex flex-col gap-5">

      <div class="card bg-surface border border-line shadow-[var(--shadow-card)] overflow-hidden">
        <div class="h-1 bg-linear-to-r from-primary to-secondary"></div>
        <div class="card-body p-6 md:p-9">

          <?php if ($job['industry'] || $job['job_type']): ?>
            <div class="font-head text-[11px] font-bold tracking-[.1em] text-primary uppercase mb-2">
              <?= e($job['job_type'] ?: '') ?>
            </div>
          <?php endif; ?>

          <h1 class="font-head text-2xl md:text-[32px] font-bold leading-tight tracking-tight text-ink">
            <?= e($job['job_title']) ?>
          </h1>

          <div class="flex flex-wrap gap-2 mt-4">

            <?php if ($job['country']): ?>
              <span class="badge badge-lg bg-primary border-primary/15 text-[#fff] gap-1.5 font-medium">
                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.5" />
                  <path d="M2 10h16M10 2c-2 2-3 5-3 8s1 6 3 8M10 2c2 2 3 5 3 8s-1 6-3 8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" />
                </svg>
                <?= e($job['city']) ?><?= $job['city'] && $job['state_province'] ? ', ' : '' ?><?= e($job['state_province']) ?><?php if ($job['state_province'] != '') { ?>,<?php } ?> <?= e($job['country']) ?>
              </span>
            <?php endif; ?>

            <span class="badge badge-lg bg-primary  <?= wpBadgeClass($job['workplace_type']) ?> gap-1.5 font-medium capitalize">
              <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 2C6.686 2 4 4.686 4 8c0 4.418 6 10 6 10s6-5.582 6-10c0-3.314-2.686-6-6-6z" stroke="currentColor" stroke-width="1.5" />
                <circle cx="10" cy="8" r="2" stroke="currentColor" stroke-width="1.4" />
              </svg>
              <?= e($job['workplace_type']) ?>
            </span>

            <?php if ($job['client_code']): ?>
              <span class="badge badge-lg badge-ghost text-ink2 font-medium">
                <svg
                  class="size-4 shrink-0 text-primary"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="1.7">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M8 6h8M8 10h8M8 14h5M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
                </svg>

                <span class="text-xs text-base-content/50">
                  Job Code:
                </span><span class="text-ink font-semibold"><?= e($job['client_code']) ?></span>
              </span>
            <?php endif; ?>

          </div>
        </div>
      </div>


      <section class="rounded-2xl border border-base-300 bg-base-100">
        <div class="grid grid-cols-2 divide-x divide-y divide-[#dbe3ef] sm:grid-cols-4 sm:divide-y-0">
          <?php if ($job['experience']): ?>
            <div class="px-4 py-4 sm:px-5">
              <div class="flex text-[10px] font-bold uppercase tracking-wider text-base-content/40">
                <svg class="me-2 w-3.5 h-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <rect x="3" y="6.5" width="14" height="9" rx="1.5" stroke="currentColor" stroke-width="1.4" />
                  <path d="M7 6.5V5a2 2 0 012-2h2a2 2 0 012 2v1.5M3 10.5h14" stroke="currentColor" stroke-width="1.4" />
                </svg>
                Experience
              </div>
              <div class="mt-1.5 text-sm font-semibold">
                <?= e($job['experience']) ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($job['salary_rate'] || !empty($job['salary_boe'])): ?>
            <div class="px-4 py-4 sm:px-5">
              <div class=" flex text-[10px] font-bold uppercase tracking-wider text-base-content/40">
                <svg
                  class="me-2 h-3.5 w-3.5"
                  viewBox="0 0 24 24"
                  fill="none"
                  xmlns="http://www.w3.org/2000/svg">
                  <path
                    d="M12 2v20M17 6.5C17 4.8 14.8 3.5 12 3.5S7 4.8 7 6.5 9.2 9.5 12 9.5s5 1.3 5 3-2.2 3-5 3-5-1.3-5-3"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round" />
                </svg>
                Salary
              </div>
              <div class="mt-1.5 text-sm font-semibold">
                <?= !empty($job['salary_boe'])
                  ? 'Based on Experience'
                  : e($job['salary_rate']) ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($job['job_type']): ?>
            <div class="px-4 py-4 sm:px-5">
              <div class="flex text-[10px] font-bold uppercase tracking-wider text-base-content/40">
                <svg class="me-2 w-3.5 h-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <rect x="3" y="4" width="14" height="13" rx="1.5" stroke="currentColor" stroke-width="1.4" />
                  <path d="M3 8h14M7 2.5v3M13 2.5v3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                </svg>
                Job Type
              </div>
              <div class="mt-1.5 text-sm font-semibold">
                <?= e($job['job_type']) ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($job['timezone']): ?>
            <div class="px-4 py-4 sm:px-5">
              <div class="flex text-[10px] font-bold uppercase tracking-wider text-base-content/40">
                <svg class="me-2 w-3.5 h-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <circle cx="10" cy="10" r="7.25" stroke="currentColor" stroke-width="1.4" />
                  <path d="M10 5.5V10l3 2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                </svg>
                Timezone
              </div>
              <div class="mt-1.5 text-sm font-semibold">
                <?= e($job['timezone']) ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- Job Description -->
      <?php if ($job['job_description']): ?>
        <div class="card bg-surface border border-line shadow-[var(--shadow-card)]">
          <div class="card-body p-6 md:p-9">
            <div class="flex items-center gap-2 pb-3 mb-4 border-b border-line">
              <span class="w-1 h-3.5 rounded-full bg-primary"></span>
              <span class="font-head text-[11px] font-bold tracking-[.1em] text-primary uppercase">Job Description</span>
            </div>
            <div class="rich-content text-[14.5px] leading-[1.78] text-ink"><?= $job['job_description'] ?></div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Requirements -->
      <?php if ($job['key_skills']): ?>
        <div class="card bg-surface border border-line shadow-[var(--shadow-card)]">
          <div class="card-body p-6 md:p-9">
            <div class="flex items-center gap-2 pb-3 mb-4 border-b border-line">
              <span class="w-1 h-3.5 rounded-full bg-primary"></span>
              <span class="font-head text-[11px] font-bold tracking-[.1em] text-primary uppercase">Requirements</span>
            </div>
            <div class="rich-content text-[14.5px] leading-[1.78] text-ink"><?= $job['key_skills'] ?></div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Why Join Us -->
      <?php if ($job['our_terms']): ?>
        <div class="card bg-surface border border-line shadow-[var(--shadow-card)]">
          <div class="card-body p-6 md:p-9">
            <div class="flex items-center gap-2 pb-3 mb-4 border-b border-line">
              <span class="w-1 h-3.5 rounded-full bg-primary"></span>
              <span class="font-head text-[11px] font-bold tracking-[.1em] text-primary uppercase">Why Join Us</span>
            </div>
            <div class="rich-content text-[14.5px] leading-[1.78] text-ink"><?= $job['our_terms'] ?></div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Apply Body -->
      <section class="hidden rounded-2xl border border-base-300 bg-base-100 px-6 py-10 text-center lg:block">
        <div class="mx-auto max-w-lg">
          <div class="mx-auto flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary">
            <svg
              class="size-5"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="1.8">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M12 21a9 9 0 100-18 9 9 0 000 18zm0 0c2.2-2.4 3.5-5.5 3.5-9S14.2 5.4 12 3m0 18c-2.2-2.4-3.5-5.5-3.5-9S9.8 5.4 12 3M3 12h18" />
            </svg>
          </div>
          <h2 class="mt-3 text-lg font-bold">
            We look forward to hearing from you
          </h2>
          <p class="mt-1 text-sm text-base-content/55">
            If this role excites you, we're ready to review your application.
          </p>
        </div>
      </section>

      <!-- Mobile section -->
      <section class="rounded-2xl bg-primary p-6 text-primary-content shadow-lg lg:hidden">
        <div class="text-center">
          <div class="mx-auto flex size-11 items-center justify-center rounded-xl bg-white/10">
            <svg
              class="size-5"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="1.8">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M5 4h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2zm4 4h6m-6 4h6m-6 4h3" />
            </svg>
          </div>
          <h2 class="mt-4 text-lg font-bold">
            Ready to apply?
          </h2>
          <p class="mx-auto mt-1 max-w-sm text-sm text-primary-content/70">
            Submit your application and take the next step in your career.
          </p>
          <div class="mt-5">
            <?php if ($job['country'] == 'India'): ?>
              <div
                data-fillout-id="1sLDbgFUV2us"
                data-fillout-embed-type="popup"
                data-fillout-button-text="Apply Now"
                data-fillout-dynamic-resize
                data-fillout-button-color="#1A4C8F"
                data-fillout-inherit-parameters
                data-fillout-popup-size="medium">
              </div>
              <script src="https://server.fillout.com/embed/v1/"></script>
            <?php else: ?>
              <div
                data-fillout-id="tJm6TzPEVeus"
                data-fillout-embed-type="popup"
                data-fillout-button-text="Apply Now"
                data-fillout-dynamic-resize
                data-fillout-button-color="#1A4C8F"
                data-fillout-inherit-parameters
                data-fillout-popup-size="medium">
              </div>
              <script src="https://server.fillout.com/embed/v1/"></script>
            <?php endif; ?>
          </div>
        </div>
      </section>


    </div>

    <aside class="lg:sticky lg:top-20 flex flex-col gap-4">
      <div class="card border border-primary/20 bg-base-100 shadow-md overflow-hidden">
        <!-- Apply Header -->
        <div class="bg-primary px-5 py-5 text-primary-content sm:px-6">
          <div class="flex items-center gap-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/15">
              <svg
                class="size-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.8">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M5 4h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2zm4 4h6m-6 4h6m-6 4h3" />
              </svg>
            </div>
            <div>
              <h2 class="font-bold text-base">
                Ready to apply?
              </h2>
              <p class="mt-0.5 text-xs text-primary-content/70">
                Take the next step in your career.
              </p>
            </div>
          </div>
        </div>
        <!-- Apply Body -->
        <div class="p-5 sm:p-6 flex flex-col items-center">
          <p class="mb-5 text-center text-sm leading-6 text-base-content/60">
            Submit your application and our recruitment team will review your profile.
          </p>
          <div class="apply-area">
            <?php if ($job['country'] == 'India'): ?>
              <div
                data-fillout-id="1sLDbgFUV2us"
                data-fillout-embed-type="popup"
                data-fillout-button-text="Apply Now"
                data-fillout-dynamic-resize
                data-fillout-button-color="#1A4C8F"
                data-fillout-inherit-parameters
                data-fillout-popup-size="medium">
              </div>
              <script src="https://server.fillout.com/embed/v1/"></script>
            <?php else: ?>
              <div
                data-fillout-id="tJm6TzPEVeus"
                data-fillout-embed-type="popup"
                data-fillout-button-text="Apply Now"
                data-fillout-dynamic-resize
                data-fillout-button-color="#1A4C8F"
                data-fillout-inherit-parameters
                data-fillout-popup-size="medium">
              </div>
              <script src="https://server.fillout.com/embed/v1/"></script>
            <?php endif; ?>
          </div>
          <?php if ($days !== null): ?>
            <div class="mt-5 flex items-center justify-center gap-2 border-t border-base-200 pt-4 text-xs">
              <svg
                class="size-4 text-base-content/40"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.8">
                <circle cx="12" cy="12" r="9" />
                <path
                  stroke-linecap="round"
                  d="M12 7v5l3 2" />
              </svg>
              <span class="text-base-content/55">
                Applications close <?= fmtDate($job['close_date']) ?>
              </span>
              <?php if ($days <= 5): ?>
                <span class="badge badge-error badge-sm">
                  <?= $days ?> day<?= $days != 1 ? 's' : '' ?> left
                </span>
              <?php else: ?>
                <span class="badge badge-ghost badge-sm">
                  <?= $days ?> days left
                </span>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- <div class="card bg-surface border border-line shadow-[var(--shadow-card)]">
        <div class="card-body p-5 divide-y divide-line">

          <?php if ($job['experience']): ?>
            <div class="py-3 first:pt-0 last:pb-0 flex items-center gap-3">
              <span class="w-8 h-8 shrink-0 rounded-lg bg-primary/8 text-primary flex items-center justify-center">
                <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <rect x="3" y="6.5" width="14" height="9" rx="1.5" stroke="currentColor" stroke-width="1.4" />
                  <path d="M7 6.5V5a2 2 0 012-2h2a2 2 0 012 2v1.5M3 10.5h14" stroke="currentColor" stroke-width="1.4" />
                </svg>
              </span>
              <div class="min-w-0">
                <div class="text-[10.5px] font-bold tracking-[.07em] text-muted uppercase mb-0.5">Work Experience</div>
                <div class="text-[13px] font-semibold text-ink"><?= e($job['experience']) ?></div>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($job['salary_rate'] || !empty($job['salary_boe'])): ?>
            <div class="py-3 first:pt-0 last:pb-0 flex items-center gap-3">
              <span class="w-8 h-8 shrink-0 rounded-lg bg-primary/8 text-primary flex items-center justify-center">
                <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M10 2.5v15M13.5 5.5c0-1.1-1.57-2-3.5-2s-3.5.9-3.5 2 1.57 1.5 3.5 1.5 3.5 .5 3.5 2-1.57 2-3.5 2-3.5-.9-3.5-2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                </svg>
              </span>
              <div class="min-w-0">
                <div class="text-[10.5px] font-bold tracking-[.07em] text-muted uppercase mb-0.5">Salary / Rate</div>
                <div class="text-[13px] font-semibold text-ink"><?= !empty($job['salary_boe']) ? 'DOE' : e($job['salary_rate']) ?></div>
              </div>
            </div>
          <?php endif; ?>
          
          <div class="py-3 first:pt-0 last:pb-0 flex items-center gap-3">
            <span class="w-8 h-8 shrink-0 rounded-lg bg-primary/8 text-primary flex items-center justify-center">
              <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 2C6.686 2 4 4.686 4 8c0 4.418 6 10 6 10s6-5.582 6-10c0-3.314-2.686-6-6-6z" stroke="currentColor" stroke-width="1.4" />
                <circle cx="10" cy="8" r="2" stroke="currentColor" stroke-width="1.3" />
              </svg>
            </span>
            <div class="min-w-0">
              <div class="text-[10.5px] font-bold tracking-[.07em] text-muted uppercase mb-0.5">Location</div>
              <div class="text-[13px] font-semibold text-ink">
                <?= countryFlag($job['country']) ?><?= e($job['city']) ?><?= $job['city'] && $job['state_province'] ? ', ' : '' ?><?= e($job['state_province']) ?><?php if ($job['state_province'] != '') { ?>, <?php } ?><span class="text-muted font-medium"><?= e($job['country']) ?></span>
              </div>
            </div>
          </div>

          <div class="py-3 first:pt-0 last:pb-0 flex items-center gap-3">
            <span class="w-8 h-8 shrink-0 rounded-lg bg-primary/8 text-primary flex items-center justify-center">
              <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="4" width="14" height="13" rx="1.5" stroke="currentColor" stroke-width="1.4" />
                <path d="M3 8h14M7 2.5v3M13 2.5v3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
              </svg>
            </span>
            <div class="min-w-0">
              <div class="text-[10.5px] font-bold tracking-[.07em] text-muted uppercase mb-0.5">Job Type</div>
              <div class="text-[13px] font-semibold text-ink"><?= e($job['job_type']) ?></div>
            </div>
          </div>

          <?php if ($job['timezone']): ?>
            <div class="py-3 first:pt-0 last:pb-0 flex items-center gap-3">
              <span class="w-8 h-8 shrink-0 rounded-lg bg-primary/8 text-primary flex items-center justify-center">
                <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <circle cx="10" cy="10" r="7.25" stroke="currentColor" stroke-width="1.4" />
                  <path d="M10 5.5V10l3 2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                </svg>
              </span>
              <div class="min-w-0">
                <div class="text-[10.5px] font-bold tracking-[.07em] text-muted uppercase mb-0.5">Timezone</div>
                <div class="text-[13px] font-semibold text-ink"><?= e($job['timezone']) ?></div>
              </div>
            </div>
          <?php endif; ?>

        </div>
      </div> -->

      <?php if ($similarJobs): ?>
        <div class="card bg-surface border border-line shadow-[var(--shadow-card)]">
          <div class="card-body p-5">
            <div class="flex items-center gap-2 pb-3 mb-3 border-b border-line">
              <span class="w-1 h-3.5 rounded-full bg-primary"></span>
              <span class="font-head text-[11px] font-bold tracking-[.1em] text-primary uppercase">Similar Roles</span>
            </div>
            <ul class="flex flex-col gap-2">
              <?php foreach ($similarJobs as $sj): ?>
                <li>
                  <a href="/job-detail.php?form_jobcode=<?= urlencode(preg_replace('/^[^-]+-/', '', $sj['client_code'])) ?>&slug=<?= urlencode($sj['slug']) ?>"
                    class="group flex items-center gap-2 rounded-xl border border-line p-3 transition-colors hover:border-primary/30 hover:bg-primary/5">
                    <div class="flex-1 min-w-0">
                      <div class="text-[13px] font-semibold text-ink group-hover:text-primary leading-snug mb-1.5"><?= e($sj['job_title']) ?></div>
                      <div class="flex flex-wrap gap-1">
                        <span class="badge badge-soft badge-xs <?= wpBadgeClass($sj['workplace_type']) ?> capitalize"><?= e($sj['workplace_type']) ?></span>
                        <?php if ($sj['city'] || $sj['country']): ?>
                          <span class="badge badge-xs badge-ghost text-muted"><?= e($sj['city'] ?: $sj['country']) ?></span>
                        <?php endif; ?>
                      </div>
                    </div>
                    <span class="text-primary/40 group-hover:text-primary group-hover:translate-x-0.5 transition-all self-center shrink-0">→</span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>

      <div class="card bg-primary shadow-[var(--shadow-pop)] print:hidden">
        <div class="card-body flex-row justify-center gap-3 p-5">

          <button
            class="btn btn-sm border-none bg-primary-content/10 text-primary-content hover:bg-primary-content/20"
            onclick="navigator.clipboard.writeText(window.location.href).then(()=>{this.querySelector('span').textContent='Copied!'})">
            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect x="7" y="7" width="10" height="10" rx="2" stroke="currentColor" stroke-width="1.5" />
              <path d="M13 7V5a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
            </svg>
            <span>Share Link</span>
          </button>

          <button
            class="btn btn-sm border-none bg-primary-content/10 text-primary-content hover:bg-primary-content/20"
            onclick="window.print()">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M6 9V4H18V9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
              <rect x="6" y="14" width="12" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5" />
              <path d="M6 17H4V11C4 9.89543 4.89543 9 6 9H18C19.1046 9 20 9.89543 20 11V17H18" stroke="currentColor" stroke-width="1.5" />
            </svg>
            <span>Save as PDF</span>
          </button>

        </div>
      </div>

    </aside>
  </div>

  <script>
    function notifyHeight() {
      const body = document.body;
      const html = document.documentElement;
      const height = Math.max(
        body.scrollHeight,
        body.offsetHeight,
        html.clientHeight,
        html.scrollHeight,
        html.offsetHeight
      ) + 50;
      window.parent.postMessage({
        type: 'resize',
        height: height
      }, '*');
    }

    window.addEventListener('load', notifyHeight);
    window.addEventListener('resize', notifyHeight);
    window.addEventListener('DOMContentLoaded', notifyHeight);
    setTimeout(notifyHeight, 300);
    setTimeout(notifyHeight, 800);
    setTimeout(notifyHeight, 1500);
  </script>
</body>

</html>