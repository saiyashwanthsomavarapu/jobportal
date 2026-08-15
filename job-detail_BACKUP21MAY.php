<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/admin/config.php';

$pdo = db();

// Get slug from URL
$slug = $_GET['slug'] ?? '';
if (!$slug) {
    header('Location: https://gustaine.com/jobportal');
    exit;
}

// Fetch the full job (not just view — we need all fields)
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

// Increment views
$pdo->prepare("UPDATE jobs SET views = views + 1 WHERE id = ?")->execute([$job['id']]);

// Helper: escape
// AFTER
if (!function_exists('e')) {
    function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
}

// Flag image helper
function countryFlag(string $country): string {
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
function wpClass(string $wp): string {
    $w = strtolower($wp);
    return in_array($w, ['remote','hybrid','onsite']) ? $w : '';
}

// Format date
function fmtDate($d) {
    if (!$d) return '—';
    return date('M j, Y', strtotime($d));
}

// Days remaining
function daysLeft($close) {
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
<style>
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:           #eef0f5;
  --surface:      #ffffff;
  --text-primary: #000;
  --text-muted:   #000;
  --accent:       #1A4C8F;
  --accent-hover: #2a42d8;
  --border:       #dde1ea;
  --row-hover:    #f4f6fb;
  --font: 'Sora', "Helvetica Neue", Arial, sans-serif;
}

body {
  font-family: var(--font);
  background: var(--bg);
  color: var(--text-primary);
  min-height: 100vh;
}

.txt-centre{
    text-align: center;
}


/* ── NAV ── */
.nav {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  padding: 0 28px;
  height: 56px;
  display: flex;
  align-items: center;
  gap: 10px;
  position: sticky;
  top: 0;
  z-index: 100;
}

.nav-brand {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-primary);
  text-decoration: none;
  letter-spacing: -0.02em;
}

.nav-sep { color: var(--border); font-size: 18px; }

.nav-crumb {
  font-size: 13px;
  color: #000;
  text-decoration: none;
}
.nav-crumb:hover { color: var(--accent); }

.nav-crumb.active { color: var(--text-primary); font-weight: 500; }

.nav-actions { margin-left: auto; display: flex; gap: 10px; align-items: center; }

/* ── LAYOUT ── */
.page {
  max-width: 1060px;
  margin: 0 auto;
  padding: 40px 28px 80px;
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 32px;
  align-items: start;
}

/* ── LEFT COLUMN ── */
.main-col { min-width: 0; }

/* ── JOB HEADER ── */
.job-header {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 36px 36px 28px;
  margin-bottom: 20px;
}

.job-header h1 {
  font-size: clamp(24px, 3.5vw, 34px);
  font-weight: 700;
  line-height: 1.15;
  letter-spacing: -0.02em;
  color: var(--text-primary);
  margin-bottom: 20px;
}

.meta-row {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  margin-bottom: 8px;
}

.meta-chip {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-size: 13px;
  color: #000;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 7px 13px;
  white-space: nowrap;
}

.meta-chip svg {
  width: 14px; height: 14px;
  flex-shrink: 0;
  color: #000;
}

.meta-chip .flag-img {
  width: 20px; height: 14px;
  object-fit: cover;
  border-radius: 2px;
  flex-shrink: 0;
}

/* type badge */
.type-badge {
  display: inline-block;
  font-size: 12px;
  font-weight: 600;
  padding: 5px 12px;
  border-radius: 20px;
  background: #eef0f9;
  color: var(--text-primary);
}

.workplace-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  padding: 5px 12px;
  border-radius: 20px;
  color: #1a8a5a;
  background: #e6f7f1;
}
.workplace-badge.remote { color: #1a5fa8; background: #e6f0fb; }
.workplace-badge.hybrid { color: #7a4f00; background: #fdf3dc; }
.workplace-badge.onsite { color: #1a8a5a; background: #e6f7f1; }

/* ── CONTENT SECTIONS ── */
.content-section {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 32px 36px;
  margin-bottom: 20px;
}

.section-title {
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.07em;
  color: #000;
  margin-bottom: 20px;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--border);
}

/* rich text content from TinyMCE */
.rich-content {
  font-size: 15px;
  line-height: 1.75;
  color: var(--text-primary);
}

.rich-content p { margin-bottom: 14px; }
.rich-content p:last-child { margin-bottom: 0; }

.rich-content ul, .rich-content ol {
  padding-left: 22px;
  margin-bottom: 14px;
}

.rich-content li {
  margin-bottom: 8px;
  line-height: 1.65;
}

.rich-content strong { font-weight: 600; }

/* strip Word cruft — override inline styles from TinyMCE paste */
.rich-content li[class*="MsoList"] {
  list-style: disc;
  text-indent: 0 !important;
  padding-left: 0;
}
.rich-content span[style*="font-family: Symbol"],
.rich-content span[style*="mso-"] {
  display: none;
}

/* Skills tags */
.skills-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 4px;
}

.skill-tag {
  display: inline-block;
  font-size: 13px;
  font-weight: 500;
  padding: 6px 14px;
  border-radius: 20px;
  background: #eef1fd;
  color: var(--accent);
  border: 1px solid #d5dcf9;
}

/* ── RIGHT SIDEBAR ── */
.sidebar { position: sticky; top: 72px; }

.sidebar-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 28px 24px;
  margin-bottom: 16px;
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
  font-size: 15px;
  font-weight: 700;
  padding: 16px 24px;
  border-radius: 10px;
  border: none;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.2s, transform 0.1s;
  margin-bottom: 12px;
}

.apply-btn:hover { background: var(--accent-hover); }
.apply-btn:active { transform: scale(0.98); }

.apply-btn svg { width: 16px; height: 16px; flex-shrink: 0; }

.deadline-note {
  text-align: center;
  font-size: 12px;
  color: #000;
  margin-bottom: 0;
  padding-top:5%;
}

.deadline-note strong { color: #c0392b; }

.sidebar-divider {
  border: none;
  border-top: 1px solid var(--border);
  margin: 20px 0;
}

.detail-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid var(--border);
}

.detail-row:last-child { border-bottom: none; }

.detail-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: var(--bg);
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.detail-icon svg { width: 14px; height: 14px; color: #000; }

.detail-info { flex: 1; min-width: 0; }
.detail-label { font-size: 11px; font-weight: 600; letter-spacing: 0.06em; color: #000; margin-bottom: 3px; }
.detail-value { font-size: 13px; font-weight: 500; color: var(--text-primary); line-height: 1.4; }

.detail-value .flag-img {
  width: 18px; height: 12px;
  object-fit: cover;
  border-radius: 2px;
  vertical-align: middle;
  margin-right: 5px;
}

/* days remaining pill */
.days-pill {
  display: inline-block;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 12px;
  background: #fdf3dc;
  color: #7a4f00;
  margin-left: 6px;
  vertical-align: middle;
}

.days-pill.urgent { background: #fee2e2; color: #b91c1c; }

/* ── BACK LINK ── */
.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 500;
  color: #000;
  text-decoration: none;
  margin-bottom: 24px;
  transition: color 0.2s;
}
.back-link:hover { color: var(--accent); }
.back-link svg { width: 16px; height: 16px; }

/* ── SALARY HIGHLIGHT ── */
.salary-highlight {
  display: flex;
  align-items: center;
  gap: 8px;
  background: linear-gradient(135deg, #eef1fd 0%, #f0fdf4 100%);
  border: 1px solid #d5dcf9;
  border-radius: 10px;
  padding: 14px 16px;
  margin-bottom: 20px;
}

.salary-highlight svg { width: 18px; height: 18px; color: var(--accent); flex-shrink: 0; }
.salary-label { font-size: 11px; font-weight: 700; letter-spacing: 0.07em; color: #000; }
.salary-value { font-size: 16px; font-weight: 700; color: var(--text-primary); }

/* ── RESPONSIVE ── */
@media (max-width: 820px) {
    .hidden-xs{
        display:none;
    }
  .page { grid-template-columns: 1fr; }
  .sidebar { position: static; }
  .job-header, .content-section { padding: 24px 20px; }
}
</style>
</head>
<body>

<!-- NAV -->
<nav class="nav">
  <a href="https://gustaine.com/jobportal" class="nav-brand">JobPortal</a>
  <span class="nav-sep">/</span>
  <a href="https://gustaine.com/jobportal" class="nav-crumb">All Jobs</a>
  <span class="nav-sep">/</span>
  <span class="nav-crumb active"><?= e($job['job_title']) ?></span>
  <div class="nav-actions">
    <a href="https://gustaine.com/jobportal" style="font-size:13px;color:#000;text-decoration:none;font-weight:500;">← View all roles</a>
  </div>
</nav>

<div class="page">

  <!-- ═══ LEFT: MAIN CONTENT ═══ -->
  <div class="main-col">

    <!-- JOB HEADER CARD -->
    <div class="job-header">
    
      <h1><?= e($job['job_title']) ?></h1>

      <div class="meta-row">

        <!-- Location -->
        <div class="meta-chip">
          <?= countryFlag($job['country']) ?>
          <?= e($job['city']) ?><?= $job['city'] && $job['state_province'] ? ', ' : '' ?><?= e($job['state_province']) ?>
        </div>

        <!-- Job Type -->
        <div class="meta-chip">
          <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="3" y="5" width="14" height="11" rx="2" stroke="currentColor" stroke-width="1.5"/>
            <path d="M7 5V4a1 1 0 011-1h4a1 1 0 011 1v1" stroke="currentColor" stroke-width="1.5"/>
          </svg>
          <?= e($job['job_type']) ?>
        </div>

        <!-- Workplace -->
        <div class="meta-chip">
          <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M10 2C6.686 2 4 4.686 4 8c0 4.418 6 10 6 10s6-5.582 6-10c0-3.314-2.686-6-6-6z" stroke="currentColor" stroke-width="1.5"/>
            <circle cx="10" cy="8" r="2" stroke="currentColor" stroke-width="1.4"/>
          </svg>
          <span class="workplace-badge <?= wpClass($job['workplace_type']) ?>">
            <?= e($job['workplace_type']) ?>
          </span>
        </div>

        <!-- Experience -->
        <?php if ($job['experience']): ?>
        <div class="meta-chip">
          <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.5"/>
            <path d="M10 6v4l3 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
          <?= e($job['experience']) ?>
        </div>
        <?php endif; ?>

        <!-- Timezone -->
        <?php if ($job['timezone']): ?>
        <div class="meta-chip">
          <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.5"/>
            <path d="M2 10h16M10 2c-2 2-3 5-3 8s1 6 3 8M10 2c2 2 3 5 3 8s-1 6-3 8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
          </svg>
          <?= e($job['timezone']) ?>
        </div>
        <?php endif; ?>

      </div>
    </div>

    <!-- SALARY -->
    <?php if ($job['salary_rate']): ?>
    <div class="salary-highlight">
      <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.5"/>
        <path d="M10 6v1.5M10 12.5V14M7.5 8.5c0-1.1.895-2 2-2h1a2 2 0 010 4h-1a2 2 0 000 4h1a2 2 0 002-2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
      </svg>
      <div>
        <div class="salary-label">Salary / Rate</div>
        <div class="salary-value"><?= e($job['salary_rate']) ?></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- JOB DESCRIPTION -->
    <?php if ($job['job_description']): ?>
    <div class="content-section">
      <div class="section-title">Job Description</div>
      <div class="rich-content">
        <?= $job['job_description'] ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- KEY SKILLS / REQUIREMENTS -->
    <?php if ($job['key_skills']): ?>
    <div class="content-section">
      <div class="section-title">Requirements</div>
      <div class="rich-content">
        <?= $job['key_skills'] ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Why Join Us -->
    <?php if ($job['our_terms']): ?>
    <div class="content-section">
      <div class="section-title">Why Join Us</div>
      <div class="rich-content">
        <?= $job['our_terms'] ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- APPLY CTA (bottom) -->
    <div class="content-section hidden-xs" style="text-align:center; padding: 36px;">
         
      <p style="font-size:18px;font-weight:700;margin-bottom:8px;color:var(--text-primary);">We look forward to hearing from you</p>
      <p style="font-size:14px;color:#000;margin-bottom:24px;">Apply only if you fully meet the criteria listed above.</p>
      
     <!-- <a href="mailto:careers@jobportal.com?subject=Application: <?= urlencode($job['job_title']) ?> (<?= urlencode($job['job_code']) ?>)" class="apply-btn" style="max-width:280px;margin:0 auto;">
        <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M3 5h14a1 1 0 011 1v8a1 1 0 01-1 1H3a1 1 0 01-1-1V6a1 1 0 011-1z" stroke="currentColor" stroke-width="1.5"/>
          <path d="M2 6l8 6 8-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        Apply for this role
      </a> -->
      
       <div class="txt-centre">
        <div data-fillout-id="tJm6TzPEVeus" data-fillout-embed-type="popup" data-fillout-button-text="Apply Now !" data-fillout-dynamic-resize data-fillout-button-color="#1A4C8F" data-fillout-inherit-parameters data-fillout-popup-size="medium"></div><script src="https://server.fillout.com/embed/v1/"></script>
        </div>
    </div>

  </div>

  <!-- ═══ RIGHT: SIDEBAR ═══ -->
  <aside class="sidebar">

    <!-- APPLY CARD -->
    <div class="sidebar-card">
        <div class="txt-centre">
        <div data-fillout-id="tJm6TzPEVeus" data-fillout-embed-type="popup" data-fillout-button-text="Apply Now !" data-fillout-dynamic-resize data-fillout-button-color="#1A4C8F" data-fillout-inherit-parameters data-fillout-popup-size="medium"></div><script src="https://server.fillout.com/embed/v1/"></script>
        </div>
    <!--    
      <a href="mailto:careers@jobportal.com?subject=Application: <?= urlencode($job['job_title']) ?> (<?= urlencode($job['job_code']) ?>)" class="apply-btn">
        <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M10 3l7 14H3L10 3z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M10 10v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        Apply for this role
      </a> -->
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

    <!-- JOB DETAILS CARD -->
    <div class="sidebar-card">
        
         <!-- Job Code -->
      <div class="detail-row">
        <div class="detail-icon">
          <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="2" y="2" width="16" height="16" rx="3" stroke="currentColor" stroke-width="1.5"/>
            <path d="M6 10h8M6 7h5M6 13h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="detail-info">
          <div class="detail-label">Job Code</div>
          <div class="detail-value" style="font-family:monospace;font-size:13px;"><?= htmlspecialchars(explode('-', $job['client_code'])[1] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </div>

      <!-- Office / Location -->
      <div class="detail-row">
        <div class="detail-icon">
          <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M10 2C6.686 2 4 4.686 4 8c0 4.418 6 10 6 10s6-5.582 6-10c0-3.314-2.686-6-6-6z" stroke="currentColor" stroke-width="1.5"/>
            <circle cx="10" cy="8" r="2" stroke="currentColor" stroke-width="1.4"/>
          </svg>
        </div>
        <div class="detail-info">
          <div class="detail-label">Location</div>
          <div class="detail-value">
            <?= countryFlag($job['country']) ?>
            <?= e($job['city']) ?><?= $job['city'] && $job['state_province'] ? ', ' : '' ?><?= e($job['state_province']) ?>
            <br><span style="font-size:12px;color:#000"><?= e($job['country']) ?></span>
          </div>
        </div>
      </div>

      <!-- Workplace Type -->
      <div class="detail-row">
        <div class="detail-icon">
          <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="2" y="7" width="16" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/>
            <path d="M6 7V5a4 4 0 018 0v2" stroke="currentColor" stroke-width="1.5"/>
          </svg>
        </div>
        <div class="detail-info">
          <div class="detail-label">Workplace</div>
          <div class="detail-value">
            <span class="workplace-badge <?= wpClass($job['workplace_type']) ?>"><?= e($job['workplace_type']) ?></span>
          </div>
        </div>
      </div>

      <!-- Job Type -->
      <div class="detail-row">
        <div class="detail-icon">
          <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="3" y="5" width="14" height="11" rx="2" stroke="currentColor" stroke-width="1.5"/>
            <path d="M7 5V4a1 1 0 011-1h4a1 1 0 011 1v1" stroke="currentColor" stroke-width="1.5"/>
          </svg>
        </div>
        <div class="detail-info">
          <div class="detail-label">Job Type</div>
          <div class="detail-value"><?= e($job['job_type']) ?></div>
        </div>
      </div>

      <!-- Experience -->
      <?php if ($job['experience']): ?>
      <div class="detail-row">
        <div class="detail-icon">
          <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.5"/>
            <path d="M10 6v4l3 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="detail-info">
          <div class="detail-label">Experience</div>
          <div class="detail-value"><?= e($job['experience']) ?></div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Timezone -->
      <?php if ($job['timezone']): ?>
      <div class="detail-row">
        <div class="detail-icon">
          <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.5"/>
            <path d="M2 10h16M10 2c-2 2-3 5-3 8s1 6 3 8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="detail-info">
          <div class="detail-label">Timezone</div>
          <div class="detail-value"><?= e($job['timezone']) ?></div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Salary / Rate -->
      <?php if ($job['salary_rate']): ?>
      <div class="detail-row">
        <div class="detail-icon">
          <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.5"/>
            <path d="M10 6v1.5M10 12.5V14M7.5 8.5c0-1.1.895-2 2-2h1a2 2 0 010 4h-1a2 2 0 000 4h1a2 2 0 002-2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="detail-info">
          <div class="detail-label">Salary / Rate</div>
          <div class="detail-value"><?= e($job['salary_rate']) ?></div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Open Date -->
      <div class="detail-row">
        <div class="detail-icon">
          <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="3" y="4" width="14" height="13" rx="2" stroke="currentColor" stroke-width="1.5"/>
            <path d="M3 8h14M7 2v3M13 2v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="detail-info">
          <div class="detail-label">Open Date</div>
          <div class="detail-value"><?= fmtDate($job['open_date']) ?></div>
        </div>
      </div>

      <!-- Close Date -->
      <?php if ($job['close_date']): ?>
      <div class="detail-row">
        <div class="detail-icon">
          <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="3" y="4" width="14" height="13" rx="2" stroke="currentColor" stroke-width="1.5"/>
            <path d="M3 8h14M7 2v3M13 2v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            <path d="M7.5 12.5l2 2 3-3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div class="detail-info">
          <div class="detail-label">Close Date</div>
          <div class="detail-value">
            <?= fmtDate($job['close_date']) ?>
            <?php if ($days !== null): ?>
              <span class="days-pill <?= $days <= 3 ? 'urgent' : '' ?>">
                <?= $days ?> day<?= $days != 1 ? 's' : '' ?> left
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

     

    </div><!-- /sidebar-card -->

    <!-- SHARE / SAVE -->
    <div class="sidebar-card" style="padding: 20px 24px;">
      <p style="font-size:12px;font-weight:700;letter-spacing:0.07em;color:#000;margin-bottom:12px;">Share this role</p>
      <div style="display:flex;gap:8px;">
        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>"
           target="_blank" rel="noopener"
           style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;font-size:13px;font-weight:600;color:#000;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:10px;text-decoration:none;transition:border-color 0.2s,color 0.2s;"
           onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'"
           onmouseout="this.style.borderColor='var(--border)';this.style.color='#000'">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
            <path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z"/>
            <circle cx="4" cy="4" r="2" fill="currentColor"/>
          </svg>
          LinkedIn
        </a>
        <button onclick="navigator.clipboard.writeText(window.location.href).then(()=>this.textContent='Copied!')"
                style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;font-size:13px;font-weight:600;color:#000;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:10px;cursor:pointer;font-family:var(--font);transition:border-color 0.2s,color 0.2s;"
                onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'"
                onmouseout="this.style.borderColor='var(--border)';this.style.color='#000'">
          <svg width="14" height="14" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="7" y="7" width="10" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/>
            <path d="M13 7V5a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
          Copy link
        </button>
      </div>
    </div>

  </aside>
</div>

</body>
</html>