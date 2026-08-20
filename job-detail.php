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
    SELECT j.*, a.name AS posted_by
    FROM jobs j
    LEFT JOIN admin_users a ON a.id = j.created_by
    WHERE j.slug = ? AND j.status = 'published'
    LIMIT 1
");
$stmt->execute([$slug]);
$job = $stmt->fetch();

if (!$job) {
  http_response_code(404);
  echo '<h1>Job not found</h1>';
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
    WHERE id != ?
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

// Flag image helper
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
    return '<img src="/flags/' . $file . '" alt="' . htmlspecialchars($country) . '" class="flag-img">';
  }
  return '<span style="font-size:18px">🌐</span>';
}

// Workplace badge class
function wpClass(string $wp): string
{
  $w = strtolower($wp);
  return in_array($w, ['remote', 'hybrid', 'onsite']) ? $w : '';
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
$wp   = strtolower($job['workplace_type']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($job['job_title']) ?> — <?= e($job['job_code']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <!-- Tailwind CSS + DaisyUI -->
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- <script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          primary: '#1A4C8F',
          secondary: '#5b6a87',
          accent: '#1A4C8F',
          background: '#eef0f5',
          surface: '#ffffff',
        }
      }
    }
  }
</script> -->
  <link rel="stylesheet" href="mobified.css">

  <style>
    @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap');

    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    :root {
      --bg: #f0f2f7;
      --surface: #ffffff;
      --text-primary: #0d1117;
      --text-muted: #4a5568;
      --accent: #1A4C8F;
      --accent-light: #e8eef8;
      --accent-hover: #163d72;
      --border: #dde3ed;
      --row-hover: #f4f7fc;
      --font: 'Sora', "Helvetica Neue", Arial, sans-serif;
      --radius-card: 18px;
      --shadow-card: 0 2px 12px rgba(26, 76, 143, .07);
    }

    body {
      font-family: var(--font);
      background: var(--bg);
      color: var(--text-primary);
      min-height: 100vh;
    }

    .nav-brand img {
      max-width: 150px;
      padding: 22px 20px 18px;
      border-bottom: 1px solid var(--border);
      flex-shrink: 0;
    }

    .txt-centre {
      text-align: center;
    }

    .nav {
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      padding: 0 32px;
      height: 58px;
      display: flex;
      align-items: center;
      gap: 10px;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
    }

    .nav-brand {
      font-size: 15px;
      font-weight: 700;
      color: var(--accent);
      text-decoration: none;
      letter-spacing: -0.02em;
    }

    .nav-sep {
      color: var(--border);
      font-size: 18px;
    }

    .nav-crumb {
      font-size: 13px;
      color: var(--text-muted);
      text-decoration: none;
      transition: color .15s;
    }

    .nav-crumb:hover {
      color: var(--accent);
    }

    .nav-crumb.active {
      color: var(--text-primary);
      font-weight: 600;
    }

    .nav-actions {
      margin-left: auto;
      display: flex;
      gap: 10px;
      align-items: center;
    }

    .page {
      max-width: 1080px;
      margin: 0 auto;
      padding: 40px 28px 90px;
      display: grid;
      grid-template-columns: 1fr 308px;
      gap: 28px;
      align-items: start;
    }

    .main-col {
      min-width: 0;
    }

    .job-header {
      padding: 0;
      margin-bottom: 20px;
      position: relative;
      overflow: hidden;
    }

    .job-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--accent) 0%, #3a7bd5 100%);
      border-radius: var(--radius-card) var(--radius-card) 0 0;
    }

    .job-header h1 {
      font-size: clamp(22px, 3.2vw, 32px);
      font-weight: 700;
      line-height: 1.18;
      letter-spacing: -0.025em;
      color: var(--text-primary);
      margin-bottom: 6px;
    }

    .job-dept-label {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .1em;
      color: var(--accent);
      text-transform: uppercase;
      margin-bottom: 10px;
    }

    .meta-row {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 18px;
    }

    .meta-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 12.5px;
      font-weight: 500;
      color: var(--text-primary);
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 6px 13px;
      white-space: nowrap;
    }

    .meta-chip svg {
      width: 13px;
      height: 13px;
      flex-shrink: 0;
    }

    .meta-chip .flag-img {
      width: 20px;
      height: 14px;
      object-fit: cover;
      border-radius: 2px;
      flex-shrink: 0;
    }

    .type-badge {
      display: inline-block;
      font-size: 12px;
      font-weight: 600;
      padding: 5px 12px;
      border-radius: 20px;
      background: var(--accent-light);
      color: var(--accent);
    }

    .workplace-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      font-weight: 600;
      padding: 4px 11px;
      border-radius: 20px;
      color: #1a8a5a;
      background: #e6f7f1;
    }

    .workplace-badge.remote {
      color: #1a5fa8;
      background: #e6f0fb;
    }

    .workplace-badge.hybrid {
      color: #7a4f00;
      background: #fdf3dc;
    }

    .workplace-badge.onsite {
      color: #1a8a5a;
      background: #e6f7f1;
    }

    .content-section {
      margin-bottom: 20px;
    }

    .section-title {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .10em;
      color: var(--accent);
      text-transform: uppercase;
      margin-bottom: 18px;
      padding-bottom: 12px;
      border-bottom: 1px solid var(--border);
    }

    .rich-content {
      font-size: 14.5px;
      line-height: 1.78;
      color: var(--text-primary);
    }

    .rich-content p {
      margin-bottom: 14px;
    }

    .rich-content p:last-child {
      margin-bottom: 0;
    }

    .rich-content ul,
    .rich-content ol {
      padding-left: 22px;
      margin-bottom: 14px;
    }

    .rich-content li {
      margin-bottom: 7px;
      line-height: 1.65;
    }

    .rich-content strong {
      font-weight: 600;
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

    .skills-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 4px;
    }

    .skill-tag {
      display: inline-block;
      font-size: 12.5px;
      font-weight: 500;
      padding: 5px 13px;
      border-radius: 20px;
      background: var(--accent-light);
      color: var(--accent);
      border: 1px solid #c8d9f0;
    }

    .sidebar {
      position: sticky;
      top: 74px;
    }

    .sidebar-card {
      margin-bottom: 16px;
    }

    /* Tailwind + DaisyUI overrides */
    .card {
      border-radius: var(--radius-card);
      border: 1px solid var(--border);
      box-shadow: var(--shadow-card);
    }

    .card-content {
      padding: 32px 36px;
    }

    .apply-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      background: var(--accent);
      color: #fff;
      font-family: var(--font);
      font-size: 14.5px;
      font-weight: 700;
      padding: 15px 24px;
      border-radius: 10px;
      border: none;
      cursor: pointer;
      text-decoration: none;
      transition: background 0.2s, transform 0.1s;
      margin-bottom: 12px;
      letter-spacing: .01em;
    }

    .apply-btn:hover {
      background: var(--accent-hover);
    }

    .apply-btn:active {
      transform: scale(0.98);
    }

    .apply-btn svg {
      width: 16px;
      height: 16px;
      flex-shrink: 0;
    }

    /* DaisyUI button overrides */
    .btn {
      border-radius: 10px;
      font-weight: 700;
      letter-spacing: .01em;
    }

    .deadline-note {
      text-align: center;
      font-size: 12px;
      color: var(--text-muted);
      margin-bottom: 0;
      padding-top: 5%;
    }

    .deadline-note strong {
      color: #c0392b;
    }

    .sidebar-divider {
      border: none;
      border-top: 1px solid var(--border);
      margin: 18px 0;
    }

    .detail-row {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 10px 0;
      border-bottom: 1px solid var(--border);
    }

    .detail-row:last-child {
      border-bottom: none;
    }

    .detail-icon {
      width: 30px;
      height: 30px;
      border-radius: 8px;
      background: var(--accent-light);
      border: 1px solid #c8d9f0;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .detail-icon svg {
      width: 13px;
      height: 13px;
      color: var(--accent);
    }

    .detail-info {
      flex: 1;
      min-width: 0;
    }

    .detail-label {
      font-size: 10.5px;
      font-weight: 700;
      letter-spacing: .07em;
      color: var(--text-muted);
      margin-bottom: 2px;
    }

    .detail-value {
      font-size: 12px;
      font-weight: 500;
      color: var(--text-primary);
      line-height: 1.4;
    }

    .detail-value .flag-img {
      width: 18px;
      height: 12px;
      object-fit: cover;
      border-radius: 2px;
      vertical-align: middle;
      margin-right: 5px;
    }

    .days-pill {
      display: inline-block;
      font-size: 11px;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 12px;
      background: #fdf3dc;
      color: #7a4f00;
      margin-left: 6px;
      vertical-align: middle;
    }

    .days-pill.urgent {
      background: #fee2e2;
      color: #b91c1c;
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 13.5px;
      font-weight: 500;
      color: var(--text-muted);
      text-decoration: none;
      margin-bottom: 24px;
      transition: color 0.2s;
    }

    .back-link:hover {
      color: var(--accent);
    }

    .back-link svg {
      width: 16px;
      height: 16px;
    }

    .salary-highlight {
      display: flex;
      align-items: center;
      gap: 10px;
      background: linear-gradient(135deg, var(--accent-light) 0%, #edfaf4 100%);
      border: 1px solid #c8d9f0;
      border-radius: 12px;
      padding: 15px 18px;
      margin-bottom: 20px;
    }

    .salary-highlight svg {
      width: 18px;
      height: 18px;
      color: var(--accent);
      flex-shrink: 0;
    }

    .salary-label {
      font-size: 10.5px;
      font-weight: 700;
      letter-spacing: .07em;
      color: var(--text-muted);
      text-transform: uppercase;
      margin-bottom: 2px;
    }

    .salary-value {
      font-size: 16px;
      font-weight: 700;
      color: var(--text-primary);
    }

    .similar-jobs-header {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .10em;
      color: var(--accent);
      text-transform: uppercase;
      margin-bottom: 14px;
      padding-bottom: 10px;
      border-bottom: 1px solid var(--border);
    }

    .similar-job-item {
      display: block;
      text-decoration: none;
      padding: 12px 0;
      border-bottom: 1px solid var(--border);
      transition: background .15s;
    }

    .similar-job-item:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }

    .similar-job-item:hover .sjob-title {
      color: var(--accent);
    }

    .sjob-title {
      font-size: 13px;
      font-weight: 600;
      color: var(--text-primary);
      line-height: 1.35;
      margin-bottom: 5px;
      transition: color .15s;
    }

    .sjob-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 5px;
    }

    .sjob-chip {
      font-size: 8px;
      font-weight: 500;
      padding: 2px 8px;
      border-radius: 12px;
      background: var(--bg);
      color: var(--text-muted);
      border: 1px solid var(--border);
      white-space: nowrap;
    }

    .sjob-chip.wp-remote {
      color: #1a5fa8;
      background: #e6f0fb;
      border-color: #c8d9f0;
    }

    .sjob-chip.wp-hybrid {
      color: #7a4f00;
      background: #fdf3dc;
      border-color: #f0d9a0;
    }

    .sjob-chip.wp-onsite {
      color: #1a8a5a;
      background: #e6f7f1;
      border-color: #a8dfc8;
    }

    .sjob-arrow {
      font-size: 12px;
      color: var(--accent);
      margin-left: auto;
      flex-shrink: 0;
      align-self: center;
      opacity: 0.6;
    }

    .no-similar {
      font-size: 13px;
      color: var(--text-muted);
      text-align: center;
      padding: 10px 0;
    }

    @media (max-width: 820px) {
      .hidden-xs {
        display: none;
      }

      .page {
        grid-template-columns: 1fr;
      }

      .sidebar {
        position: static;
      }

      .job-header,
      .content-section {
        padding: 24px 20px;
      }

      .job-header {
        padding-left: 24px;
        padding-right: 24px;
      }
    }
  </style>
</head>

<body>

  <nav class="nav">
    <a href="https://accelonconsulting.com" class="nav-brand"><img src="https://www.accelonconsulting.com/wp-content/uploads/2025/07/Accelon-logo.webp"></a>
    <span class="nav-sep">/</span>
    <a href="https://accelonconsulting.com/careers" class="nav-crumb">All Jobs</a>
    <span class="nav-sep">/</span>
    <span class="nav-crumb active"><?= e($job['job_title']) ?></span>
    <div class="nav-actions">
      <a href="https://accelonconsulting.com/careers" style="font-size:13px;color:#000;text-decoration:none;font-weight:500;">← View all roles</a>
    </div>
  </nav>

  <div class="page">

    <div class="main-col">

      <div class="card job-header">
        <div class="card-content">
          <?php if ($job['industry'] || $job['job_type']): ?>
            <div class="job-dept-label">
              <?= e($job['industry'] ?: '') ?><?= $job['industry'] && $job['job_type'] ? ' · ' : '' ?><?= e($job['job_type'] ?: '') ?>
            </div>
          <?php endif; ?>

          <h1><?= e($job['job_title']) ?></h1>

          <div class="meta-row">

            <?php if ($job['country']): ?>
              <div class="meta-chip" style="background:#245699;color:#fff;">
                <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.5" />
                  <path d="M2 10h16M10 2c-2 2-3 5-3 8s1 6 3 8M10 2c2 2 3 5 3 8s-1 6-3 8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" />
                </svg>
                <?= e($job['city']) ?><?= $job['city'] && $job['state_province'] ? ', ' : '' ?><?= e($job['state_province']) ?><?php if ($job['state_province'] != '') { ?>,<?php } ?> <?= e($job['country']) ?>
              </div>
            <?php endif; ?>

            <div class="meta-chip" style="background:#245699;color:#fff;">
              <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 2C6.686 2 4 4.686 4 8c0 4.418 6 10 6 10s6-5.582 6-10c0-3.314-2.686-6-6-6z" stroke="currentColor" stroke-width="1.5" />
                <circle cx="10" cy="8" r="2" stroke="currentColor" stroke-width="1.4" />
              </svg>
              <span><?= e($job['workplace_type']) ?></span>
            </div>

            <?php if ($job['client_code']): ?>
              <div class="meta-chip" style="background:#245699;color:#fff;">
                Job Code: <?= htmlspecialchars(explode('-', $job['client_code'])[1] ?? '', ENT_QUOTES, 'UTF-8') ?>
              </div>
            <?php endif; ?>

          </div>
        </div>
      </div>

      <?php if ($job['job_description']): ?>
        <div class="card content-section">
          <div class="card-content">
            <div class="section-title">Job Description</div>
            <div class="rich-content">
              <?= $job['job_description'] ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($job['key_skills']): ?>
        <div class="card content-section">
          <div class="card-content">
            <div class="section-title">Requirements</div>
            <div class="rich-content">
              <?= $job['key_skills'] ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($job['our_terms']): ?>
        <div class="card content-section">
          <div class="card-content">
            <div class="section-title">Why Join Us</div>
            <div class="rich-content">
              <?= $job['our_terms'] ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="card content-section hidden-xs" style="text-align:center; padding: 36px;">
        <p style="font-size:18px;font-weight:700;margin-bottom:8px;color:var(--text-primary);">We look forward to hearing from you</p>
        <p style="font-size:14px;color:#000;margin-bottom:24px;">If this role excites you, we're ready to review your application.</p>

        <div class="txt-centre">
          <?php if ($job['country'] == 'India') { ?>
            <div data-fillout-id="1sLDbgFUV2us" data-fillout-embed-type="popup" data-fillout-button-text="Apply Now !" data-fillout-dynamic-resize data-fillout-button-color="#1A4C8F" data-fillout-inherit-parameters data-fillout-popup-size="medium"></div>
            <script src="https://server.fillout.com/embed/v1/"></script>
          <?php } else { ?>
            <div
              data-fillout-id="tJm6TzPEVeus"
              data-fillout-embed-type="popup"
              data-fillout-button-text="Apply Now !"
              data-fillout-dynamic-resize
              data-fillout-button-color="#1A4C8F"
              data-fillout-inherit-parameters
              data-fillout-popup-size="medium">
            </div>
            <script src="https://server.fillout.com/embed/v1/"></script>
          <?php } ?>
        </div>
      </div>

    </div>

    <aside class="sidebar">

      <div class="card sidebar-card">
        <div class="card-content">
          <div class="txt-centre">
            <?php if ($job['country'] == 'India') { ?>
              <div data-fillout-id="1sLDbgFUV2us" data-fillout-embed-type="popup" data-fillout-button-text="Apply Now !" data-fillout-dynamic-resize data-fillout-button-color="#1A4C8F" data-fillout-inherit-parameters data-fillout-popup-size="medium"></div>
              <script src="https://server.fillout.com/embed/v1/"></script>
            <?php } else { ?>
              <div
                data-fillout-id="tJm6TzPEVeus"
                data-fillout-embed-type="popup"
                data-fillout-button-text="Apply Now !"
                data-fillout-dynamic-resize
                data-fillout-button-color="#1A4C8F"
                data-fillout-inherit-parameters
                data-fillout-popup-size="medium">
              </div>
              <script src="https://server.fillout.com/embed/v1/"></script>
            <?php } ?>
            <?php if ($days !== null): ?>
              <p class="deadline-note">
                Closes <?= fmtDate($job['close_date']) ?>
                <?php if ($days <= 5): ?>
                  — <strong><?= $days ?> day<?= $days != 1 ? 's' : '' ?> left!</strong>
                <?php else: ?>
                  &nbsp;(<?= $days ?> days left)
                <?php endif; ?>
              </p>
            <?php endif; ?>
          </div>
        </div>

        <div class="card sidebar-card" style="margin-top:5%;">
          <div class="card-content">

            <?php if ($job['experience']): ?>
              <div class="detail-row">
                <div class="detail-info">
                  <div class="detail-label">Work Experience</div>
                  <div class="detail-value"><?= e($job['experience']) ?></div>
                </div>
              </div>
            <?php endif; ?>

            <?php if ($job['salary_rate'] || !empty($job['salary_boe'])): ?>
              <div class="detail-row">
                <div class="detail-info">
                  <div class="detail-label">Salary / Rate</div>
                  <div class="detail-value"><?= !empty($job['salary_boe']) ? 'DOE' : e($job['salary_rate']) ?></div>
                </div>
              </div>
            <?php endif; ?>

            <div class="detail-row">
              <div class="detail-info">
                <div class="detail-label">Location</div>
                <div class="detail-value">
                  <?= countryFlag($job['country']) ?>
                  <?= e($job['city']) ?><?= $job['city'] && $job['state_province'] ? ', ' : '' ?><?= e($job['state_province']) ?> <?php if ($job['state_province'] != '') { ?>, <?php } ?> <span style="font-size:12px;color:#000"><?= e($job['country']) ?></span>
                </div>
              </div>
            </div>

            <div class="detail-row">
              <div class="detail-info">
                <div class="detail-label">Job Type</div>
                <div class="detail-value"><?= e($job['job_type']) ?></div>
              </div>
            </div>

            <?php if ($job['timezone']): ?>
              <div class="detail-row">
                <div class="detail-info">
                  <div class="detail-label">Timezone</div>
                  <div class="detail-value"><?= e($job['timezone']) ?></div>
                </div>
              </div>
            <?php endif; ?>

          </div>
        </div>

        <div class="card sidebar-card" style="background:#245699;">
          <div class="card-content">
            <div class="action-btns" style="text-align:center;">

              <button class="meta-chip" onclick="navigator.clipboard.writeText(window.location.href).then(()=>this.textContent='Copied!')"
                style="min-width:120px;max-width:120px;font-family:sora;"
                onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'"
                onmouseout="this.style.borderColor='var(--border)';this.style.color='#000'">
                <svg width="14" height="14" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <rect x="7" y="7" width="10" height="10" rx="2" stroke="currentColor" stroke-width="1.5" />
                  <path d="M13 7V5a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                </svg>
                Share Link
              </button>

              <button class="meta-chip" onclick="window.print()"
                style="margin-top:5%;min-width:120px;max-width:120px;font-family:sora;"
                onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'"
                onmouseout="this.style.borderColor='var(--border)';this.style.color='#000'">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M6 9V4H18V9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                  <rect x="6" y="14" width="12" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5" />
                  <path d="M6 17H4V11C4 9.89543 4.89543 9 6 9H18C19.1046 9 20 9.89543 20 11V17H18" stroke="currentColor" stroke-width="1.5" />
                </svg>
                Save as PDF
              </button>

            </div>
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