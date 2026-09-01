<?php
require_once __DIR__ . '/admin/config.php';

$pdo = db();
$jobs = $pdo->query("SELECT *, REGEXP_REPLACE(client_code, '[^0-9]', '') AS clean_client_code FROM jobs WHERE status='published' AND (close_date IS NULL OR close_date >= CURDATE()) ORDER BY open_date DESC, id DESC")->fetchAll();
$jobTypes = array_values(array_unique(array_filter(array_column($jobs, 'job_type'))));
$workplaces = array_values(array_unique(array_filter(array_column($jobs, 'workplace_type'))));
$countries = array_values(array_unique(array_filter(array_column($jobs, 'country'))));
sort($jobTypes);
sort($workplaces);
sort($countries);
$remoteCount = count(array_filter($jobs, fn($job) => strtolower($job['workplace_type'] ?? '') === 'remote'));

function countryFlag(string $country): string
{
  $flags = ['united states' => 'us.jpg', 'usa' => 'us.jpg', 'us' => 'us.jpg', 'india' => 'india.jpg', 'canada' => 'canada.jpg', 'maxico' => 'us.jpg'];
  $file = $flags[strtolower(trim($country))] ?? null;
  return $file ? '<img src="/flags/' . $file . '" alt="" class="inline-block w-5 h-4 object-cover rounded-sm align-middle">' : '<span aria-hidden="true">🌐</span>';
}

// Badge colour classes per workplace type, matching the theme palette
function workplaceBadgeClass(string $workplace): string
{
  return match (strtolower($workplace)) {
    'hybrid' => 'badge-soft badge-warning',
    'remote' => 'badge-soft badge-info',
    'onsite' => 'badge-soft badge-success',
    default  => 'badge-soft badge-neutral',
  };
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="accelon">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <meta name="description" content="Explore open roles at Accelon and find your next opportunity.">
  <title>Careers at Accelon</title>

  <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
  <?php include __DIR__ . "/theme.php"; ?>
  <style>
    .sr-only {
      position: absolute;
      width: 1px;
      height: 1px;
      padding: 0;
      margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
      border: 0;
    }

    .filter-select {
      box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
      transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
    }

    .filter-select:focus,
    .filter-select:focus-visible {
      border-color: var(--color-primary);
      box-shadow: 0 12px 30px rgba(15, 23, 42, 0.16), 0 0 0 3px color-mix(in srgb, var(--color-primary) 18%, transparent);
      outline: none;
      transform: translateY(-1px);
    }
  </style>
</head>

<body class="bg-bg text-ink min-h-screen flex flex-col">

  <!-- Header -->
  <header class="navbar bg-surface border-b border-line px-4 md:px-8">
    <div class="flex-1 flex items-center gap-2">
      <div class="bg-primary rounded-lg p-2 flex items-center justify-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary-content" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M20 7h-3V5a2 2 0 00-2-2H9a2 2 0 00-2 2v2H4a1 1 0 00-1 1v10a2 2 0 002 2h14a2 2 0 002-2V8a1 1 0 00-1-1zM9 5h6v2H9V5z" />
        </svg>
      </div>
      <span class="text-lg font-bold text-ink">Careers</span>
    </div>
    <nav class="flex-none" aria-label="Primary navigation">
      <a href="#open-roles" class="text-ink2 hover:text-primary text-sm font-medium">All Jobs</a>
    </nav>
  </header>

  <main class="flex-1">
    <!-- Hero -->
    <section class="bg-linear-to-b from-accent-soft to-surface border-b border-line px-4 md:px-8 py-16 text-center">
      <h1 class="font-head text-4xl md:text-5xl font-extrabold text-ink mb-3">Job Openings</h1>
      <p class="text-ink2 text-base md:text-lg">Explore opportunities across the USA, Canada, and India.</p>
    </section>

    <!-- Jobs section -->
    <section class="max-w-6xl mx-auto px-4 md:px-8 py-10" id="open-roles" aria-labelledby="jobs-title">

      <!-- Filter panel -->
      <div class="flex items-center gap-2 mb-3">
        <label class="input flex-1 min-w-[220px]" for="search">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="7"></circle>
            <path d="m20 20-4-4"></path>
          </svg>
          <span class="sr-only">Search jobs</span>
          <input id="search" type="search" placeholder="Search for a job" autocomplete="off" class="grow bg-transparent focus:outline-none text-sm">
        </label>

        <label class="relative flex-1 min-w-[220px]">
          <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-4 top-1/2 z-10 h-4 w-4 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="9"></circle>
            <path d="M3 12h18M12 3c2.5 2.5 3.7 5.5 3.7 9s-1.2 6.5-3.7 9c-2.5-2.5-3.7-5.5-3.7-9S9.5 5.5 12 3Z"></path>
          </svg>
          <span class="sr-only">Filter by country</span>
          <select id="country" class="filter-select select w-full min-h-12 pl-11 pr-10 text-sm">
            <option value="">Country</option>
            <?php foreach ($countries as $country): ?><option value="<?= e($country) ?>"><?= e($country) ?></option><?php endforeach; ?>
          </select>
        </label>

        <label class="relative flex-1 min-w-[220px]">
          <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-4 top-1/2 z-10 h-4 w-4 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <rect x="3" y="6" width="18" height="14" rx="2"></rect>
            <path d="M8 6V4h8v2M3 12h18"></path>
          </svg>
          <span class="sr-only">Filter by job type</span>
          <select id="jobType" class="filter-select select w-full min-h-12 pl-11 pr-10 text-sm">
            <option value="">Job Type</option>
            <?php foreach ($jobTypes as $type): ?><option value="<?= e($type) ?>"><?= e($type) ?></option><?php endforeach; ?>
          </select>
        </label>

        <label class="relative flex-1 min-w-[220px]">
          <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-4 top-1/2 z-10 h-4 w-4 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path d="M12 21s7-5.2 7-12a7 7 0 1 0-14 0c0 6.8 7 12 7 12Z"></path>
            <circle cx="12" cy="9" r="2.5"></circle>
          </svg>
          <span class="sr-only">Filter by workplace</span>
          <select id="workplace" class="filter-select select w-full min-h-12 pl-11 pr-10 text-sm">
            <option value="">Workplace</option>
            <?php foreach ($workplaces as $workplace): ?><option value="<?= e($workplace) ?>"><?= e($workplace) ?></option><?php endforeach; ?>
          </select>
        </label>

        <button class="btn btn-ghost btn-sm shrink-0 text-ink2" id="resetFilters" type="button">Reset</button>
      </div>

      <p id="resultText" class="italic text-sm text-muted mb-6" aria-live="polite">Showing roles across all locations and all teams.</p>

      <!-- Jobs table -->
      <div class="overflow-x-auto rounded-2xl border border-line">
        <table class="table" id="jobTable">
          <thead>
            <tr class="bg-primary text-primary-content">
              <th class="font-semibold text-primary-content first:rounded-tl-2xl">Job Code</th>
              <th class="font-semibold text-primary-content">Role</th>
              <th class="font-semibold text-primary-content">Job Type</th>
              <th class="font-semibold text-primary-content">Workplace</th>
              <th class="font-semibold text-primary-content">Location</th>
              <th class="font-semibold text-primary-content last:rounded-tr-2xl">Post Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($jobs as $job):
              $workplace = strtolower($job['workplace_type'] ?? '');
              $displayCode = $job['clean_client_code'] ?? '';
              $detailUrl = 'job-details.php?' . http_build_query(['slug' => $job['slug'], 'form_jobcode' => $job['clean_client_code']]);
              $location = trim(implode(', ', array_filter([$job['city'] ?? '', $job['state_province'] ?? ''])));
              if (!$location || $workplace === 'remote') $location = $job['country'] ?? $location;
            ?>
              <tr
                class="hover:bg-card2 cursor-pointer border-b border-line last:border-none"
                data-title="<?= e(strtolower($job['job_title'])) ?>"
                data-code="<?= e(strtolower($job['job_code'] ?? '')) ?>"
                data-country="<?= e(strtolower($job['country'] ?? '')) ?>"
                data-type="<?= e(strtolower($job['job_type'] ?? '')) ?>"
                data-workplace="<?= e($workplace) ?>"
                tabindex="0" role="link"
                onclick="window.location.href='<?= e($detailUrl) ?>'"
                onkeydown="if(event.key === 'Enter') this.click()">
                <td data-label="Job Code" class="text-ink2"><?= e($displayCode) ?></td>
                <td data-label="Role">
                  <a href="<?= e($detailUrl) ?>" class="text-primary font-semibold hover:underline" onclick="event.stopPropagation()"><?= e($job['job_title']) ?></a>
                </td>
                <td data-label="Job Type" class="text-ink2"><?= e($job['job_type']) ?></td>
                <td data-label="Workplace">
                  <span class="badge rounded-full font-medium <?= workplaceBadgeClass($workplace) ?>"><?= e($job['workplace_type']) ?></span>
                </td>
                <td data-label="Location">
                  <span class="flex items-center gap-2 text-ink2"><?= countryFlag($job['country'] ?? '') ?><span><?= e($location) ?></span></span>
                </td>
                <td data-label="Post Date" class="text-ink2 text-sm">
                  <time datetime="<?= e($job['open_date']) ?>"><?= date('j F Y', strtotime($job['open_date'])) ?></time>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div class="hidden flex-col items-center justify-center gap-2 py-16 text-center" id="emptyState">
          <span class="text-3xl">⌕</span>
          <h3 class="font-semibold text-ink">No matching roles</h3>
          <p class="text-sm text-ink2">Try changing or clearing one of your filters.</p>
          <button type="button" class="btn btn-sm btn-outline rounded-full mt-2" onclick="clearFilters()">Clear all filters</button>
        </div>
      </div>
    </section>
  </main>

  <footer class="border-t border-line px-4 md:px-8 py-8 flex flex-col md:flex-row items-center justify-between gap-3 max-w-6xl mx-auto text-sm text-ink2">
    <a class="flex items-center gap-2" href="/">
      <div class="bg-primary rounded-lg p-1.5 flex items-center justify-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary-content" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M20 7h-3V5a2 2 0 00-2-2H9a2 2 0 00-2 2v2H4a1 1 0 00-1 1v10a2 2 0 002 2h14a2 2 0 002-2V8a1 1 0 00-1-1zM9 5h6v2H9V5z" />
        </svg>
      </div>
      <span class="font-bold text-ink">Careers</span>
    </a>
    <p>Build what matters. Grow with people who care.</p>
    <span>&copy; <?= date('Y') ?> Accelon</span>
  </footer>

  <script>
    const rows = [...document.querySelectorAll('#jobTable tbody tr')];
    const search = document.getElementById('search');
    const country = document.getElementById('country');
    const jobType = document.getElementById('jobType');
    const workplace = document.getElementById('workplace');
    const resultText = document.getElementById('resultText');
    const emptyState = document.getElementById('emptyState');

    function applyFilters() {
      const query = search.value.trim().toLowerCase();
      const selectedCountry = country.value.toLowerCase();
      const selectedType = jobType.value.toLowerCase();
      const selectedWorkplace = workplace.value.toLowerCase();
      let visible = 0;
      rows.forEach(row => {
        const matches = (!query || row.dataset.title.includes(query) || row.dataset.code.includes(query)) && (!selectedCountry || row.dataset.country === selectedCountry) && (!selectedType || row.dataset.type === selectedType) && (!selectedWorkplace || row.dataset.workplace === selectedWorkplace);
        row.hidden = !matches;
        if (matches) visible++;
      });
      const anyFilterActive = query || selectedCountry || selectedType || selectedWorkplace;
      resultText.textContent = anyFilterActive ?
        `${visible} matching role${visible === 1 ? '' : 's'}` :
        'Showing roles across all locations and all teams.';
      emptyState.classList.toggle('hidden', visible !== 0);
      emptyState.classList.toggle('flex', visible === 0);
    }

    function clearFilters() {
      search.value = '';
      country.value = '';
      jobType.value = '';
      workplace.value = '';
      applyFilters();
      search.focus();
    }
    [search, country, jobType, workplace].forEach(control => control.addEventListener(control === search ? 'input' : 'change', applyFilters));
    document.getElementById('resetFilters').addEventListener('click', clearFilters);
  </script>
</body>

</html>