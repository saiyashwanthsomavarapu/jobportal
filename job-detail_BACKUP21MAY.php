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
  $flags = ['united states' => 'us.jpg', 'usa' => 'us.jpg', 'us' => 'us.jpg', 'india' => 'india.jpg', 'canada' => 'canada.jpg'];
  $file = $flags[strtolower(trim($country))] ?? null;
  return $file ? '<img src="/flags/' . $file . '" alt="" class="inline-block w-5 h-4 object-cover rounded-sm align-middle">' : '<span aria-hidden="true">🌐</span>';
}

// Badge colour classes per workplace type, matching the screenshot palette
function workplaceBadgeClass(string $workplace): string
{
  return match (strtolower($workplace)) {
    'hybrid' => 'bg-amber-50 text-amber-700 border border-amber-200',
    'remote' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
    'onsite' => 'bg-orange-50 text-orange-700 border border-orange-200',
    default  => 'bg-gray-100 text-gray-600 border border-gray-200',
  };
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <meta name="description" content="Explore open roles at Accelon and find your next opportunity.">
  <title>Careers at Accelon</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
  <!-- <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script> -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['DM Sans', 'system-ui', 'sans-serif']
          },
          colors: {
            brand: {
              DEFAULT: '#0f9d78',
              50: '#e8f8f3',
              100: '#c9efe0',
              600: '#0f9d78',
              700: '#0c7f61',
            }
          }
        }
      }
    }
  </script>
  <style>
    body {
      font-family: 'DM Sans', system-ui, -apple-system, sans-serif;
    }

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
  </style>
</head>

<body class="bg-white text-gray-800">

  <!-- Header -->
  <header class="navbar bg-white border-b border-gray-200 px-4 md:px-8">
    <div class="flex-1 flex items-center gap-2">
      <div class="bg-brand-600 rounded-lg p-2 flex items-center justify-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M20 7h-3V5a2 2 0 00-2-2H9a2 2 0 00-2 2v2H4a1 1 0 00-1 1v10a2 2 0 002 2h14a2 2 0 002-2V8a1 1 0 00-1-1zM9 5h6v2H9V5z" />
        </svg>
      </div>
      <span class="text-lg font-bold text-gray-900">Careers</span>
    </div>
    <nav class="flex-none" aria-label="Primary navigation">
      <a href="#open-roles" class="text-gray-500 hover:text-gray-700 text-sm font-medium">All Jobs</a>
    </nav>
  </header>

  <main>
    <!-- Hero -->
    <section class="bg-gradient-to-b from-brand-50/60 to-white border-b border-gray-200 px-4 md:px-8 py-16 text-center">
      <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 mb-3">Job Openings</h1>
      <p class="text-gray-500 text-base md:text-lg">Explore opportunities across the USA, Canada, and India.</p>
    </section>

    <!-- Jobs section -->
    <section class="max-w-6xl mx-auto px-4 md:px-8 py-10" id="open-roles" aria-labelledby="jobs-title">

      <!-- Filter panel -->
      <div class="flex flex-wrap items-center gap-3 mb-3">
        <label class="input input-bordered flex items-center gap-2 rounded-full flex-1 min-w-[220px]" for="search">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="7"></circle>
            <path d="m20 20-4-4"></path>
          </svg>
          <span class="sr-only">Search jobs</span>
          <input id="search" type="search" list="job-titles" placeholder="Search for a job" autocomplete="off" class="grow bg-transparent focus:outline-none text-sm">
        </label>
        <datalist id="job-titles"><?php foreach ($jobs as $job): ?><option value="<?= e($job['job_title']) ?>"></option><?php endforeach; ?></datalist>

        <label class="select select-bordered flex items-center gap-2 rounded-full">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="9"></circle>
            <path d="M3 12h18M12 3c2.5 2.5 3.7 5.5 3.7 9s-1.2 6.5-3.7 9c-2.5-2.5-3.7-5.5-3.7-9S9.5 5.5 12 3Z"></path>
          </svg>
          <span class="sr-only">Filter by country</span>
          <select id="country" class="bg-transparent focus:outline-none text-sm pr-1">
            <option value="">Country</option>
            <?php foreach ($countries as $country): ?><option value="<?= e($country) ?>"><?= e($country) ?></option><?php endforeach; ?>
          </select>
        </label>

        <label class="select select-bordered flex items-center gap-2 rounded-full">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <rect x="3" y="6" width="18" height="14" rx="2"></rect>
            <path d="M8 6V4h8v2M3 12h18"></path>
          </svg>
          <span class="sr-only">Filter by job type</span>
          <select id="jobType" class="bg-transparent focus:outline-none text-sm pr-1">
            <option value="">Job Type</option>
            <?php foreach ($jobTypes as $type): ?><option value="<?= e($type) ?>"><?= e($type) ?></option><?php endforeach; ?>
          </select>
        </label>

        <label class="select select-bordered flex items-center gap-2 rounded-full">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path d="M12 21s7-5.2 7-12a7 7 0 1 0-14 0c0 6.8 7 12 7 12Z"></path>
            <circle cx="12" cy="9" r="2.5"></circle>
          </svg>
          <span class="sr-only">Filter by workplace</span>
          <select id="workplace" class="bg-transparent focus:outline-none text-sm pr-1">
            <option value="">Workplace</option>
            <?php foreach ($workplaces as $workplace): ?><option value="<?= e($workplace) ?>"><?= e($workplace) ?></option><?php endforeach; ?>
          </select>
        </label>

        <button class="btn btn-ghost btn-sm rounded-full text-gray-500" id="resetFilters" type="button">Reset</button>
      </div>

      <p id="resultText" class="italic text-sm text-gray-400 mb-6" aria-live="polite">Showing roles across all locations and all teams.</p>

      <!-- Jobs table -->
      <div class="overflow-x-auto rounded-2xl border border-gray-200">
        <table class="table" id="jobTable">
          <thead>
            <tr class="bg-brand-600 text-white">
              <th class="font-semibold text-white first:rounded-tl-2xl">Job Code</th>
              <th class="font-semibold text-white">Role</th>
              <th class="font-semibold text-white">Job Type</th>
              <th class="font-semibold text-white">Workplace</th>
              <th class="font-semibold text-white">Location</th>
              <th class="font-semibold text-white last:rounded-tr-2xl">Post Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($jobs as $job):
              $workplace = strtolower($job['workplace_type'] ?? '');
              $displayCode = $job['job_code'] ?? '';
              $detailUrl = 'job-detail.php?' . http_build_query(['slug' => $job['slug'], 'form_jobcode' => $job['clean_client_code']]);
              $location = trim(implode(', ', array_filter([$job['city'] ?? '', $job['state_province'] ?? ''])));
              if (!$location || $workplace === 'remote') $location = $job['country'] ?? $location;
            ?>
              <tr
                class="hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-none"
                data-title="<?= e(strtolower($job['job_title'])) ?>"
                data-code="<?= e(strtolower($job['job_code'] ?? '')) ?>"
                data-country="<?= e(strtolower($job['country'] ?? '')) ?>"
                data-type="<?= e(strtolower($job['job_type'] ?? '')) ?>"
                data-workplace="<?= e($workplace) ?>"
                tabindex="0" role="link"
                onclick="window.location.href='<?= e($detailUrl) ?>'"
                onkeydown="if(event.key === 'Enter') this.click()">
                <td data-label="Job Code" class="text-gray-400 font-mono text-sm"><?= e($displayCode) ?></td>
                <td data-label="Role">
                  <a href="<?= e($detailUrl) ?>" class="text-brand-600 font-semibold hover:underline" onclick="event.stopPropagation()"><?= e($job['job_title']) ?></a>
                </td>
                <td data-label="Job Type" class="text-gray-700"><?= e($job['job_type']) ?></td>
                <td data-label="Workplace">
                  <span class="badge rounded-full font-medium <?= workplaceBadgeClass($workplace) ?>"><?= e($job['workplace_type']) ?></span>
                </td>
                <td data-label="Location">
                  <span class="flex items-center gap-2 text-gray-700"><?= countryFlag($job['country'] ?? '') ?><span><?= e($location) ?></span></span>
                </td>
                <td data-label="Post Date" class="text-gray-500 text-sm">
                  <time datetime="<?= e($job['open_date']) ?>"><?= date('j F Y', strtotime($job['open_date'])) ?></time>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div class="hidden flex-col items-center justify-center gap-2 py-16 text-center" id="emptyState">
          <span class="text-3xl">⌕</span>
          <h3 class="font-semibold text-gray-800">No matching roles</h3>
          <p class="text-sm text-gray-500">Try changing or clearing one of your filters.</p>
          <button type="button" class="btn btn-sm btn-outline rounded-full mt-2" onclick="clearFilters()">Clear all filters</button>
        </div>
      </div>
    </section>
  </main>

  <footer class="border-t border-gray-200 px-4 md:px-8 py-8 flex flex-col md:flex-row items-center justify-between gap-3 max-w-6xl mx-auto text-sm text-gray-500">
    <a class="flex items-center gap-2" href="/">
      <div class="bg-brand-600 rounded-lg p-1.5 flex items-center justify-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M20 7h-3V5a2 2 0 00-2-2H9a2 2 0 00-2 2v2H4a1 1 0 00-1 1v10a2 2 0 002 2h14a2 2 0 002-2V8a1 1 0 00-1-1zM9 5h6v2H9V5z" />
        </svg>
      </div>
      <span class="font-bold text-gray-900">Careers</span>
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