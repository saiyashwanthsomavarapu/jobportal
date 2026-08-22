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

  <!-- Tailwind CSS + DaisyUI -->
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
  <link href="/theme.css" rel="stylesheet" type="text/css" />

  <!-- Google Fonts matching admin panel -->
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">

  <!-- <style>
    body {
      font-family: "DM Sans", sans-serif;
      background-color: #f4f5f8;
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem;
    }

    .page-header h1 {
      font-family: "Syne", sans-serif;
      font-size: 2rem;
      font-weight: 700;
      color: #111827;
      margin-bottom: 1.5rem;
    }

    .filters {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      justify-content: center;
      margin-bottom: 1rem;
    }

    .filter-field {
      position: relative;
    }

    .custom-select {
      background: white;
      border: 1px solid #e5e5e5;
      border-radius: 0.5rem;
      padding: 0.75rem 1rem;
      min-width: 175px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.2s;
    }

    .custom-select:hover {
      border-color: #3d6ba8;
    }

    .custom-select:focus {
      border-color: #3d6ba8;
      box-shadow: 0 0 0 2px rgba(61, 107, 168, 0.2);
    }

    .custom-dropdown {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid #e5e5e5;
      border-radius: 0.5rem;
      margin-top: 0.25rem;
      box-shadow: 0 4px 14px rgba(61, 107, 168, 0.22);
      z-index: 50;
      display: none;
    }

    .custom-dropdown:not(.hidden) {
      display: block;
    }

    .custom-option {
      padding: 0.75rem 1rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: background 0.2s;
    }

    .custom-option:hover {
      background: #f2f2f2;
    }

    .custom-option.selected {
      background: #e3f2fd;
      color: #3d6ba8;
      font-weight: 500;
    }

    .info-text {
      text-align: center;
      color: #525252;
      font-size: 0.875rem;
      margin-bottom: 1.5rem;
    }

    .job-table {
      width: 100%;
      border-collapse: collapse;
    }

    .job-table th {
      text-align: left;
      padding: 1rem;
      background: #f2f2f2;
      font-weight: 600;
      color: #111827;
      border-bottom: 2px solid #e5e5e5;
    }

    .job-table td {
      padding: 1rem;
      border-bottom: 1px solid #e5e5e5;
    }

    .job-table tbody tr {
      cursor: pointer;
      transition: background 0.2s;
    }

    .job-table tbody tr:hover {
      background: #f2f2f2;
    }

    .job-title {
      color: #3d6ba8;
      font-weight: 500;
      text-decoration: none;
    }

    .job-title:hover {
      text-decoration: underline;
    }

    .workplace-badge {
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
      background: #f2f2f2;
      color: #525252;
    }

    .workplace-badge.remote {
      background: #dcfce7;
      color: #166534;
    }

    .workplace-badge.hybrid {
      background: #fef9c3;
      color: #854d0e;
    }

    .workplace-badge.onsite {
      background: #dbeafe;
      color: #1e40af;
    }

    .location-cell {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .flag-img {
      width: 20px;
      height: 14px;
      object-fit: cover;
      border-radius: 2px;
    }

    .flag-emoji {
      font-size: 1.25rem;
    }

    .search-suggestions {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid #e5e5e5;
      border-radius: 0.5rem;
      margin-top: 0.25rem;
      box-shadow: 0 4px 14px rgba(61, 107, 168, 0.22);
      z-index: 50;
      display: none;
      max-height: 300px;
      overflow-y: auto;
    }

    .search-suggestions.open {
      display: block;
    }

    .suggestion-item {
      padding: 0.75rem 1rem;
      cursor: pointer;
      transition: background 0.2s;
    }

    .suggestion-item:hover,
    .suggestion-item.active {
      background: #f2f2f2;
      color: #3d6ba8;
    }
  </style> -->

</head>

<body data-theme="accelon">

  <div class="container">
    <!-- HEADER -->
    <div class="page-header">
      <h1>Job Openings</h1>
    </div>

    <!-- FILTERS -->
    <div class="flex flex-wrap items-center justify-center gap-3 mb-4 w-full filters">

      <!-- Search with autocomplete -->
      <div class="filter-field search-wrapper relative join">
        <div class="join-item bg-white border border-gray-300 rounded-l-lg flex items-center justify-center px-4">
          <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-600">
            <circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.6" />
            <path d="M13 13l3.5 3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          </svg>
        </div>
        <input
          type="text"
          id="search"
          placeholder="Search for a job"
          autocomplete="off"
          oninput="onSearchInput()"
          onkeydown="onSearchKeydown(event)"
          onfocus="onSearchFocus()"
          class="input input-bordered join-item rounded-r-lg w-80 bg-white border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
        <div class="search-suggestions" id="searchSuggestions"></div>
      </div>

      <!-- Country -->
      <div class="filter-field relative">
        <div class="custom-select bg-white border border-gray-300 rounded-lg px-4 py-3 min-w-[175px] cursor-pointer select-none flex items-center gap-2 relative transition-all duration-200 hover:border-blue-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200" id="countrySelect" onclick="toggleCountryDropdown()">
          <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:#000;pointer-events:none;">
            <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.6" />
            <path d="M2 10h16M10 2c-2 2-3 5-3 8s1 6 3 8M10 2c2 2 3 5 3 8s-1 6-3 8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
          </svg>
          <span class="custom-select-label" id="countryLabel">Country</span>
          <svg viewBox="0 0 12 12" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);width:12px;height:12px;pointer-events:none;" xmlns="http://www.w3.org/2000/svg">
            <path fill="#5b6a87" d="M6 8L1 3h10z" />
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
      <div class="filter-field relative">
        <div class="join">
          <div class="join-item bg-white border border-gray-300 rounded-l-lg flex items-center justify-center px-4">
            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-600">
              <rect x="3" y="5" width="14" height="11" rx="2" stroke="currentColor" stroke-width="1.6" />
              <path d="M7 5V4a1 1 0 011-1h4a1 1 0 011 1v1" stroke="currentColor" stroke-width="1.6" />
            </svg>
          </div>
          <select id="jobType" onchange="applyFilters()" class="select select-bordered join-item rounded-r-lg bg-white border-gray-300 min-w-[175px] focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
            <option value="">Job Type</option>
            <?php
            $types = $pdo->query("SELECT DISTINCT job_type FROM jobs")->fetchAll();
            foreach ($types as $t) {
              echo "<option value='{$t['job_type']}'>{$t['job_type']}</option>";
            }
            ?>
          </select>
        </div>
      </div>

      <!-- Workplace Type -->
      <div class="filter-field relative">
        <div class="join">
          <div class="join-item bg-white border border-gray-300 rounded-l-lg flex items-center justify-center px-4">
            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-600">
              <path d="M10 2C6.686 2 4 4.686 4 8c0 4.418 6 10 6 10s6-5.582 6-10c0-3.314-2.686-6-6-6z" stroke="currentColor" stroke-width="1.6" />
              <circle cx="10" cy="8" r="2" stroke="currentColor" stroke-width="1.5" />
            </svg>
          </div>
          <select id="workplace" onchange="applyFilters()" class="select select-bordered join-item rounded-r-lg bg-white border-gray-300 min-w-[175px] focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
            <option value="">Workplace</option>
            <?php
            $wps = $pdo->query("SELECT DISTINCT workplace_type FROM jobs")->fetchAll();
            foreach ($wps as $w) {
              echo "<option value='{$w['workplace_type']}'>{$w['workplace_type']}</option>";
            }
            ?>
          </select>
        </div>
      </div>

      <button class="btn btn-outline btn-primary bg-white border-gray-300 text-gray-700 hover:bg-gray-50 hover:border-gray-400 font-semibold rounded-lg px-6 py-3 transition-all duration-200" onclick="clearFilters()">Reset</button>

    </div>

    <!-- INFO TEXT -->
    <p class="info-text" id="resultText">
      Showing roles across all locations and all teams.
    </p>

    <!-- TABLE -->
    <div class="overflow-x-auto bg-white rounded-xl border border-gray-200 shadow-sm">
      <table class="job-table table" id="jobTable">
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
            $wpClass = in_array($wp, ['remote', 'hybrid', 'onsite']) ? $wp : '';
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
              onclick="location.href='job-detail.php?slug=<?= e($job['slug']) ?>&form_jobcode=<?= htmlspecialchars(explode('-', $job['client_code'])[1] ?? '', ENT_QUOTES, 'UTF-8') ?>'">
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
      const box = document.getElementById('searchSuggestions');

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
        const idx = t.toLowerCase().indexOf(query);
        const before = t.slice(0, idx);
        const match = t.slice(idx, idx + query.length);
        const after = t.slice(idx + query.length);
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
      const box = document.getElementById('searchSuggestions');
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
      const s = document.getElementById('search').value.toLowerCase();
      const c = document.getElementById('country').value.toLowerCase();
      const jt = document.getElementById('jobType').value.toLowerCase();
      const wp = document.getElementById('workplace').value.toLowerCase();

      let visibleCount = 0;

      rows.forEach(row => {
        const title = row.dataset.title;
        const code = row.dataset.code;
        const client = row.dataset.client;
        const country = row.dataset.country;
        const type = row.dataset.type;
        const workplace = row.dataset.workplace;

        const matchSearch = !s || title.includes(s) || code.includes(s) || client.includes(s);
        const matchCountry = !c || country === c;
        const matchType = !jt || type === jt;
        const matchWP = !wp || workplace === wp;

        const show = (matchSearch && matchCountry && matchType && matchWP);
        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
      });

      updateResultText(visibleCount);
    }

    function updateResultText(count) {
      const country = document.getElementById('country').value;
      const type = document.getElementById('jobType').value;
      const wp = document.getElementById('workplace').value;

      let parts = [];
      if (country) parts.push(country);
      if (type) parts.push(type);
      if (wp) parts.push(wp);

      let text = '';
      if (parts.length === 0) {
        text = 'Showing roles across all locations and all teams.';
      } else {
        text = `Showing ${count} role${count !== 1 ? 's' : ''} in ${parts.join(', ')}.`;
      }

      document.getElementById('resultText').innerText = text;
    }

    function clearFilters() {
      document.getElementById('search').value = '';
      document.getElementById('country').value = '';
      document.getElementById('searchSuggestions').classList.remove('open');
      selectCountry('', 'Location', '');
      document.getElementById('jobType').value = '';
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
      dd.classList.toggle('hidden');
      cs.classList.toggle('border-blue-500');
      cs.classList.toggle('ring-2');
      cs.classList.toggle('ring-blue-200');
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
        opt.classList.toggle('bg-blue-50', opt.dataset.value === value);
        opt.classList.toggle('text-blue-600', opt.dataset.value === value);
        opt.classList.toggle('font-medium', opt.dataset.value === value);
      });

      document.getElementById('countryDropdown').classList.add('hidden');
      document.getElementById('countrySelect').classList.remove('border-blue-500');
      document.getElementById('countrySelect').classList.remove('ring-2');
      document.getElementById('countrySelect').classList.remove('ring-blue-200');

      // Auto-filter on country change
      applyFilters();
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
      if (!e.target.closest('#countrySelect') && !e.target.closest('#countryDropdown')) {
        document.getElementById('countryDropdown').classList.add('hidden');
        document.getElementById('countrySelect').classList.remove('border-blue-500');
        document.getElementById('countrySelect').classList.remove('ring-2');
        document.getElementById('countrySelect').classList.remove('ring-blue-200');
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