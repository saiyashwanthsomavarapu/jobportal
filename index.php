<?php
require_once __DIR__ . '/admin/config.php';

$pdo = db();

// Fetch published jobs
//$stmt = $pdo->query("SELECT * FROM v_published_jobs");
$stmt = $pdo->query("
    SELECT * 
    FROM jobs 
    WHERE status='published'
      AND (close_date IS NULL OR close_date >= CURDATE())
    ORDER BY open_date DESC, id DESC
");
$jobs = $stmt->fetchAll();

// Country name → flag emoji helper
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

  return '<span class="flag-emoji">🌐</span>';
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Jobs</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<meta name="robots" content="noindex">

<!-- sohne-var is a commercial Klim Type Foundry font; loading closest Google Fonts alternative that matches its character -->
<style>
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap');

*, *::before, *::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

:root {
  --bg: #eef0f5;
  --surface: #ffffff;
  --text-primary: #000;
  --text-muted: #5b6a87;
  --accent: #1A4C8F;
  --accent-hover: #000;
  --border: #dde1ea;
  --row-hover: #f4f6fb;
  --font: 'Sora', "sohne-var", "Helvetica Neue", "Arial", sans-serif;
}

body {
  font-family: var(--font);
  background: var(--bg);
  color: var(--text-primary);
  min-height: 100vh;
}

/* ── CONTAINER ── */
.container {
  width: 100%;
  max-width: 1260px;
  margin: 0 auto;
  padding: 72px 28px 80px;
}

/* ── HEADER ── */
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 40px;
  margin-bottom: 48px;
}

h1 {
  font-size: clamp(36px, 5vw, 52px);
  font-weight: 700;
  line-height: 1.1;
  letter-spacing: -0.02em;
  color: var(--text-primary);
  flex: 1;
}

/* ── QUICK LINKS ── */
.quick-links {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-top: 6px;
  flex-shrink: 0;
}

.quick-links a {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--accent);
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
  white-space: nowrap;
  border-left: 2px solid var(--border);
  padding-left: 12px;
  transition: border-color 0.2s, color 0.2s;
}

.quick-links a:hover {
  color: var(--accent-hover);
  border-left-color: var(--accent);
}

.quick-links a svg {
  width: 14px;
  height: 14px;
  flex-shrink: 0;
}

/* ── FILTER BAR ── */
.filters {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
  width: 100%;
  justify-content: center;
}

.filter-field {
  position: relative;
  display: flex;
  align-items: center;
}

.filter-field svg {
  position: absolute;
  left: 13px;
  color: #000;
  pointer-events: none;
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}

.filters input,
.filters select {
  font-family: var(--font);
  font-size: 14px;
  color: var(--text-primary);
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 11px 14px 11px 38px;
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
  -webkit-appearance: none;
  appearance: none;
}

/* ── SEARCH AUTOCOMPLETE ── */
.search-wrapper {
  position: relative;
}

.search-suggestions {
  display: none;
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  min-width: 100%;
  width: max-content;
  max-width: 360px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(10,37,64,0.12);
  z-index: 1000;
  overflow: hidden;
}

.search-suggestions.open {
  display: block;
}

.suggestion-item {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 10px 16px;
  font-size: 14px;
  color: var(--text-primary);
  cursor: pointer;
  transition: background 0.15s;
}

.suggestion-item:hover,
.suggestion-item.active {
  background: var(--row-hover);
  color: var(--accent);
}

.suggestion-item mark {
  background: none;
  color: var(--accent);
  font-weight: 600;
}

/* ── CUSTOM COUNTRY SELECT ── */
.custom-select {
  font-family: var(--font);
  font-size: 14px;
  color: #000;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 11px 36px 11px 38px;
  min-width: 175px;
  cursor: pointer;
  user-select: none;
  display: flex;
  align-items: center;
  gap: 8px;
  position: relative;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.custom-select:focus,
.custom-select.open {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(61, 90, 241, 0.12);
}

.custom-select-label {
  display: flex;
  align-items: center;
  gap: 7px;
  color: var(--text-primary);
}

.custom-dropdown {
  display: none;
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  min-width: 100%;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(10,37,64,0.12);
  z-index: 999;
  overflow: hidden;
}

.custom-dropdown.open {
  display: block;
}

.custom-option {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 10px 16px;
  font-size: 14px;
  color: var(--text-primary);
  cursor: pointer;
  transition: background 0.15s;
}

.custom-option:hover {
  background: var(--row-hover);
}

.custom-option.selected {
  background: #eef1fd;
  color: var(--accent);
  font-weight: 500;
}

.filters input {
  min-width: 320px;
}

.filters select {
  min-width: 175px;
  padding-right: 32px;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%235b6a87' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  cursor: pointer;
}

.filters input:focus,
.filters select:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(61, 90, 241, 0.12);
}

.filters input::placeholder {
  color: #000;
}

/* Buttons */
.btn-filter {
  font-family: var(--font);
  font-size: 14px;
  font-weight: 600;
  padding: 11px 22px;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  transition: background 0.2s, transform 0.1s;
  line-height: 1;
}

.btn-filter.secondary {
  background: var(--surface);
  color: #000;
  border: 1px solid var(--border);
}

.btn-filter.secondary:hover {
  background: var(--row-hover);
}

.btn-filter:active {
  transform: scale(0.98);
}

/* ── INFO TEXT ── */
.info-text {
  font-size: 14px;
  color: #000;
  margin-bottom: 28px;
  text-align: center;
}

/* ── TABLE CARD ── */
.table-card {
  background: var(--surface);
  border-radius: 14px;
  border: 1px solid var(--border);
  overflow: hidden;
}

.job-table {
  width: 100%;
  border-collapse: collapse;
}

.job-table thead th {
  font-size: 17px;
  font-weight: 600;
  letter-spacing: 0.06em;
  color: #fff;
  padding: 16px 24px;
  text-align: left;
  border-bottom: 1px solid var(--border);
  background: #1a4c8f;
}

.job-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background 0.15s;
  cursor: pointer;
}

.job-table tbody tr:last-child {
  border-bottom: none;
}

.job-table tbody tr:hover {
  background: var(--row-hover);
}

.job-table td {
  padding: 18px 24px;
  font-size: 16px;
  vertical-align: middle;
}

/* Job title link */
.job-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--accent);
  text-decoration: none;
  transition: color 0.15s;
}

.job-title:hover {
  color: var(--accent-hover);
  text-decoration: underline;
}

/* Location cell */
.location-cell {
  display: flex;
  align-items: center;
  gap: 7px;
  color: #000;
}

/* Flag images — uniform size, rounded, no distortion */
.flag-img {
  width: 24px;
  height: 16px;
  object-fit: cover;
  border-radius: 3px;
  flex-shrink: 0;
  display: inline-block;
  vertical-align: middle;
}

/* Keep the globe emoji fallback aligned */
.flag-emoji {
  font-size: 18px;
  line-height: 1;
  flex-shrink: 0;
}

/* Type badge */
.type-badge {
  display: inline-block;
  font-size: 12px;
  font-weight: 500;
  padding: 4px 10px;
  border-radius: 20px;
  background: #eef0f9;
  color: var(--text-primary);
}

/* Workplace badge */
.workplace-badge {
  display: inline-block;
  font-size: 16px;
  font-weight: 500;
  padding: 4px 10px;
  border-radius: 20px;
  color: #1a8a5a;
  background: #e6f7f1;
}

.workplace-badge.remote   { color: #1a5fa8; background: #e6f0fb; }
.workplace-badge.hybrid   { color: #7a4f00; background: #fdf3dc; }
.workplace-badge.onsite   { color: #1a8a5a; background: #e6f7f1; }

/* ── RESPONSIVE ── */
@media (max-width: 760px) {
  .page-header { flex-direction: column; gap: 24px; }
  .quick-links { flex-direction: row; flex-wrap: wrap; }
  .filters input { min-width: 100%; }
  .filters select { min-width: calc(50% - 5px); }
}
</style>

</head>
<body>

<div class="container">

  <!-- HEADER -->
  <div class="page-header">
    <h1>Job Openings</h1>

  </div>

  <!-- FILTERS -->
  <div class="filters">

    <!-- Search with autocomplete -->
    <div class="filter-field search-wrapper">
      <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.6"/>
        <path d="M13 13l3.5 3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
      </svg>
      <input
        type="text"
        id="search"
        placeholder="Search for a job"
        autocomplete="off"
        oninput="onSearchInput()"
        onkeydown="onSearchKeydown(event)"
        onfocus="onSearchFocus()"
      >
      <div class="search-suggestions" id="searchSuggestions"></div>
    </div>

    <!-- Country -->
    <div class="filter-field" style="position:relative;">
      <div class="custom-select" id="countrySelect" onclick="toggleCountryDropdown()">
        <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:#000;pointer-events:none;">
          <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.6"/>
          <path d="M2 10h16M10 2c-2 2-3 5-3 8s1 6 3 8M10 2c2 2 3 5 3 8s-1 6-3 8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
        </svg>
        <span class="custom-select-label" id="countryLabel">Country</span>
        <svg viewBox="0 0 12 12" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);width:12px;height:12px;pointer-events:none;" xmlns="http://www.w3.org/2000/svg">
          <path fill="#5b6a87" d="M6 8L1 3h10z"/>
        </svg>
      </div>

      <div class="custom-dropdown" id="countryDropdown">
        <div class="custom-option selected" data-value="" onclick="selectCountry('', 'Location', '')">
          Country
        </div>
        <div class="custom-option" data-value="India" onclick="selectCountry('India', 'India', '/flags/india.jpg')">
          <img src="/flags/india.jpg" class="flag-img" alt="India"> India
        </div>
        <div class="custom-option" data-value="United States" onclick="selectCountry('United States', 'United States', '/flags/us.jpg')">
          <img src="/flags/us.jpg" class="flag-img" alt="United States"> United States
        </div>
        <div class="custom-option" data-value="Canada" onclick="selectCountry('Canada', 'Canada', '/flags/canada.jpg')">
          <img src="/flags/canada.jpg" class="flag-img" alt="Canada"> Canada
        </div>
      </div>

      <!-- Hidden input so applyFilters() still works -->
      <input type="hidden" id="country" value="">
    </div>

    <!-- Job Type -->
    <div class="filter-field">
      <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="3" y="5" width="14" height="11" rx="2" stroke="currentColor" stroke-width="1.6"/>
        <path d="M7 5V4a1 1 0 011-1h4a1 1 0 011 1v1" stroke="currentColor" stroke-width="1.6"/>
      </svg>
      <select id="jobType" onchange="applyFilters()">
        <option value="">Job Type</option>
        <?php
        $types = $pdo->query("SELECT DISTINCT job_type FROM jobs")->fetchAll();
        foreach ($types as $t) {
          echo "<option value='{$t['job_type']}'>{$t['job_type']}</option>";
        }
        ?>
      </select>
    </div>

    <!-- Workplace Type -->
    <div class="filter-field">
      <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M10 2C6.686 2 4 4.686 4 8c0 4.418 6 10 6 10s6-5.582 6-10c0-3.314-2.686-6-6-6z" stroke="currentColor" stroke-width="1.6"/>
        <circle cx="10" cy="8" r="2" stroke="currentColor" stroke-width="1.5"/>
      </svg>
      <select id="workplace" onchange="applyFilters()">
        <option value="">Workplace</option>
        <?php
        $wps = $pdo->query("SELECT DISTINCT workplace_type FROM jobs")->fetchAll();
        foreach ($wps as $w) {
          echo "<option value='{$w['workplace_type']}'>{$w['workplace_type']}</option>";
        }
        ?>
      </select>
    </div>

    <button class="btn-filter secondary" onclick="clearFilters()">Reset</button>

  </div>

  <!-- INFO TEXT -->
  <p class="info-text" id="resultText">
    Showing roles across all locations and all teams.
  </p>

  <!-- TABLE -->
  <div class="table-card">
    <table class="job-table" id="jobTable">
      <thead>
        <tr>
          <th>Job Code</th>
          <th>Role</th>
          <th>Job Type</th>
          <th>Workplace</th>
          <th>Location</th>
          <th>Post Date</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($jobs as $job):
        $wp = strtolower($job['workplace_type']);
        $wpClass = in_array($wp, ['remote','hybrid','onsite']) ? $wp : '';
      ?>
      <tr
        data-title="<?= strtolower($job['job_title']) ?>"
        data-code="<?= strtolower($job['job_code']) ?>"
        data-client="<?= strtolower($job['client_code'] ?? '') ?>"
        data-country="<?= strtolower($job['country']) ?>"
        data-state="<?= strtolower($job['state_province'] ?? '') ?>"
        data-city="<?= strtolower($job['city'] ?? '') ?>"
        data-type="<?= strtolower($job['job_type']) ?>"
        data-workplace="<?= strtolower($job['workplace_type']) ?>"
        data-fulltitle="<?= htmlspecialchars($job['job_title'], ENT_QUOTES) ?>"
        onclick="location.href='job-detail.php?slug=<?= e($job['slug']) ?>&form_jobcode=<?= htmlspecialchars(explode('-', $job['client_code'])[1] ?? '', ENT_QUOTES, 'UTF-8') ?>'"
      >
        <td>
  <span>
    <?= htmlspecialchars(explode('-', $job['client_code'])[1] ?? '', ENT_QUOTES, 'UTF-8') ?>
  </span>
  
</td>
        <td>
          <a class="job-title" href="job-detail.php?slug=<?= e($job['slug']) ?>&form_jobcode=<?= htmlspecialchars(explode('-', $job['client_code'])[1] ?? '', ENT_QUOTES, 'UTF-8') ?>&form_jobtitle=<?= e($job['job_title']) ?>" onclick="event.stopPropagation()">
            <?= e($job['job_title']) ?>
          </a>
        </td>

        <td>
          <span><?= e($job['job_type']) ?></span>
        </td>

        <td>
          <span class="workplace-badge <?= $wpClass ?>"><?= e($job['workplace_type']) ?></span>
        </td>
        
        <td>
          <div class="location-cell">
            <span class="flag-emoji"><?= countryFlag($job['country']) ?></span>
            <span><?= e($job['city']) ?><?= $job['city'] ? ',' : '' ?> <?= e($job['state_province']) ?> <?php 
if (e($job['workplace_type']) == "Remote") { 
    echo e($job['country']);
}
?></span>
          </div>
        </td>
        
        <td>
          <span class="<?= $wpClass ?>">
  <?= date('d F Y', strtotime($job['open_date'])) ?>
</span>
        </td>

      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<!-- FILTER SCRIPT -->
<script>
const rows = document.querySelectorAll('#jobTable tbody tr');

// ── BUILD SUGGESTIONS LIST FROM TABLE ROWS ──
const allTitles = [];
rows.forEach(row => {
  const title = row.dataset.fulltitle || row.dataset.title;
  if (title && !allTitles.includes(title)) {
    allTitles.push(title);
  }
});

let activeSuggestionIndex = -1;

function onSearchInput() {
  applyFilters();
  showSuggestions();
}

function onSearchFocus() {
  showSuggestions();
}

function showSuggestions() {
  const query = document.getElementById('search').value.trim().toLowerCase();
  const box   = document.getElementById('searchSuggestions');

  if (!query) {
    box.classList.remove('open');
    box.innerHTML = '';
    activeSuggestionIndex = -1;
    return;
  }

  const matches = allTitles.filter(t => t.toLowerCase().includes(query)).slice(0, 7);

  if (matches.length === 0) {
    box.classList.remove('open');
    box.innerHTML = '';
    return;
  }

  box.innerHTML = matches.map((t, i) => {
    const idx   = t.toLowerCase().indexOf(query);
    const before = t.slice(0, idx);
    const match  = t.slice(idx, idx + query.length);
    const after  = t.slice(idx + query.length);
    const highlighted = `${before}${match}${after}`;
    return `<div class="suggestion-item" data-index="${i}" onmousedown="pickSuggestion('${escapeHtml(t)}')">${highlighted}</div>`;
  }).join('');

  box.classList.add('open');
  activeSuggestionIndex = -1;
}

function pickSuggestion(title) {
  document.getElementById('search').value = title;
  document.getElementById('searchSuggestions').classList.remove('open');
  applyFilters();
}

function onSearchKeydown(e) {
  const box   = document.getElementById('searchSuggestions');
  const items = box.querySelectorAll('.suggestion-item');

  if (!box.classList.contains('open') || items.length === 0) return;

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    activeSuggestionIndex = Math.min(activeSuggestionIndex + 1, items.length - 1);
    highlightSuggestion(items);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    activeSuggestionIndex = Math.max(activeSuggestionIndex - 1, -1);
    highlightSuggestion(items);
  } else if (e.key === 'Enter') {
    if (activeSuggestionIndex >= 0 && items[activeSuggestionIndex]) {
      e.preventDefault();
      pickSuggestion(items[activeSuggestionIndex].textContent);
    } else {
      box.classList.remove('open');
    }
  } else if (e.key === 'Escape') {
    box.classList.remove('open');
  }
}

function highlightSuggestion(items) {
  items.forEach((item, i) => {
    item.classList.toggle('active', i === activeSuggestionIndex);
  });
}

function escapeRegex(str) {
  return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function escapeHtml(str) {
  return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

// ── FILTER LOGIC ──
function applyFilters() {
  const s  = document.getElementById('search').value.toLowerCase();
  const c  = document.getElementById('country').value.toLowerCase();
  const jt = document.getElementById('jobType').value.toLowerCase();
  const wp = document.getElementById('workplace').value.toLowerCase();

  let visibleCount = 0;

  rows.forEach(row => {
    const title     = row.dataset.title;
    const code      = row.dataset.code;
    const client    = row.dataset.client;
    const country   = row.dataset.country;
    const type      = row.dataset.type;
    const workplace = row.dataset.workplace;

    const matchSearch  = !s  || title.includes(s) || code.includes(s) || client.includes(s);
    const matchCountry = !c  || country === c;
    const matchType    = !jt || type === jt;
    const matchWP      = !wp || workplace === wp;

    const show = (matchSearch && matchCountry && matchType && matchWP);
    row.style.display = show ? '' : 'none';
    if (show) visibleCount++;
  });

  updateResultText(visibleCount);
}

function updateResultText(count) {
  const country = document.getElementById('country').value;
  const type    = document.getElementById('jobType').value;
  const wp      = document.getElementById('workplace').value;

  let parts = [];
  if (country) parts.push(country);
  if (type)    parts.push(type);
  if (wp)      parts.push(wp);

  let text = '';
  if (parts.length === 0) {
    text = 'Showing roles across all locations and all teams.';
  } else {
    text = `Showing ${count} role${count !== 1 ? 's' : ''} in ${parts.join(', ')}.`;
  }

  document.getElementById('resultText').innerText = text;
}

function clearFilters() {
  document.getElementById('search').value    = '';
  document.getElementById('country').value   = '';
  document.getElementById('searchSuggestions').classList.remove('open');
  selectCountry('', 'Location', '');
  document.getElementById('jobType').value   = '';
  document.getElementById('workplace').value = '';
  applyFilters();
}

window.onload = function() {
  updateResultText(rows.length);
};

// ── COUNTRY DROPDOWN ──
function toggleCountryDropdown() {
  const dd = document.getElementById('countryDropdown');
  const cs = document.getElementById('countrySelect');
  dd.classList.toggle('open');
  cs.classList.toggle('open');
}

function selectCountry(value, label, flagSrc) {
  document.getElementById('country').value = value;

  const labelEl = document.getElementById('countryLabel');
  if (flagSrc) {
    labelEl.innerHTML = `<img src="${flagSrc}" class="flag-img" alt="${label}" style="margin-right:4px;"> ${label}`;
    labelEl.style.color = 'var(--text-primary)';
  } else {
    labelEl.innerHTML = label;
    labelEl.style.color = '#000';
  }

  document.querySelectorAll('#countryDropdown .custom-option').forEach(opt => {
    opt.classList.toggle('selected', opt.dataset.value === value);
  });

  document.getElementById('countryDropdown').classList.remove('open');
  document.getElementById('countrySelect').classList.remove('open');

  // Auto-filter on country change
  applyFilters();
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
  if (!e.target.closest('#countrySelect') && !e.target.closest('#countryDropdown')) {
    document.getElementById('countryDropdown').classList.remove('open');
    document.getElementById('countrySelect').classList.remove('open');
  }
  if (!e.target.closest('.search-wrapper')) {
    document.getElementById('searchSuggestions').classList.remove('open');
  }
});

</script>
<?php

echo '<script>window.location.href="https://www.accelonconsulting.com/careers/";</script>';
?>

</body>
</html>