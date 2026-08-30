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

$sideBar = [
    'Work Experience'   => $job['experience'] ?? '',
    'Salary / Rate'     => $job['salary_rate'] ?? '',
    'Location'          => ['flag' => '🇺🇸', 'text' => $job['city'] . ', ' . $job['state_province'] . ', ' . $job['country']],
    'Job Type'          => $job['job_type'],
    'Work Mode'         => $job['workplace_type'],
    // 'Openings'          => '5',
    // 'Visa Sponsorship'  => 'Available',
    'Notice Period'     => 'Immediate',
];

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
/**
 * Job Detail Page — Multi-Country Recruitment Job Portal
 * Built with PHP + Tailwind CSS + daisyUI
 *
 * All job data below is stored in a single associative array so this
 * page can easily be swapped to pull from a database later.
 */

?>
<!DOCTYPE html>
<html lang="en" data-theme="accelon">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Country Recruitment Job Portal | Careers</title>

    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <?php include __DIR__ . "/theme.php"; ?>

    <style>
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

        /* Style the Fillout popup trigger like the .btn "Apply Now" button */
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

<body class="bg-bg text-ink">

    <!-- Top Navbar -->
    <div class="navbar bg-surface border-b border-line px-4 md:px-8">
        <div class="flex-1 flex items-center gap-2">
            <div class="bg-primary rounded-lg p-2 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary-content" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7h-3V5a2 2 0 00-2-2H9a2 2 0 00-2 2v2H4a1 1 0 00-1 1v10a2 2 0 002 2h14a2 2 0 002-2V8a1 1 0 00-1-1zM9 5h6v2H9V5z" />
                </svg>
            </div>
            <span class="text-lg font-bold text-ink">Careers</span>
        </div>
        <div class="flex-none">
            <a href="#" class="text-ink2 hover:text-primary text-sm font-medium">All Jobs</a>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 md:px-8 py-6">

        <!-- Back link -->
        <a href="index.php" class="inline-flex items-center gap-2 text-ink2 hover:text-primary text-sm mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to all jobs
        </a>

        <!-- Header Card -->
        <div class="card bg-surface border-t-4 border-primary rounded-2xl shadow-sm mb-8">
            <div class="card-body p-6">
                <p class="text-primary text-xs font-bold tracking-wider uppercase mb-1">
                    <!-- <?php echo htmlspecialchars($job['type']); ?> -->
                    <?= e($job['job_type'] ?: '') ?>
                </p>
                <h1 class="text-3xl font-extrabold text-ink mb-3">
                    <!-- <?php echo htmlspecialchars($job['title']); ?> -->
                    <?= e($job['job_title']) ?>
                </h1>

                <div class="flex flex-wrap gap-2 mb-4">
                    <span class="badge badge-lg bg-accent-soft text-primary border-none font-medium gap-1 py-3">
                        <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.5" />
                            <path d="M2 10h16M10 2c-2 2-3 5-3 8s1 6 3 8M10 2c2 2 3 5 3 8s-1 6-3 8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" />
                        </svg>
                        <!-- <?php echo $job['country_flag']; ?> -->
                        <?= e($job['city']) ?><?= $job['city'] && $job['state_province'] ? ', ' : '' ?><?= e($job['state_province']) ?><?php if ($job['state_province'] != '') { ?>,<?php } ?> <?= e($job['country']) ?>
                    </span>
                    <span class="badge badge-lg bg-accent-soft text-primary border-none font-medium gap-1 py-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <?= e($job['workplace_type']) ?>
                    </span>
                    <span class="badge badge-lg bg-accent-soft text-primary border-none font-medium gap-1 py-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7h-3V5a2 2 0 00-2-2H9a2 2 0 00-2 2v2H4a1 1 0 00-1 1v10a2 2 0 002 2h14a2 2 0 002-2V8a1 1 0 00-1-1zM9 5h6v2H9V5z" />
                        </svg>
                        Job Code: <?= e($job['client_code']) ?>
                    </span>
                </div>

                <div class="divider my-0"></div>

                <div class="flex gap-6 pt-4">
                    <button class="flex items-center gap-2 text-ink2 hover:text-primary text-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342a4 4 0 100-2.684m0 2.684a4 4 0 000 -2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a4 4 0 108-1.658 4 4 0 00-8 1.658zm0 12.632a4 4 0 108-1.658 4 4 0 00-8 1.658z" />
                        </svg>
                        Share
                    </button>
                    <button class="flex items-center gap-2 text-ink2 hover:text-primary text-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.017-1.837-2.185a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.017 1.837-2.185a48.055 48.055 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" />
                        </svg>
                        Print
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Left Column -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Job Description -->
                <?php if ($job['job_description']): ?>
                    <div class="card bg-surface border border-line rounded-2xl">
                        <div class="card-body p-6">
                            <h2 class="text-primary text-sm font-bold tracking-wider uppercase mb-2">Job Description</h2>
                            <div class="divider my-0"></div>
                            <div class="rich-content text-[14.5px] leading-[1.78] text-ink pt-3">
                                <?= $job['job_description'] ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Requirements -->
                <!-- <div class="card bg-surface border border-line rounded-2xl">
                    <div class="card-body p-6">
                        <h2 class="text-primary text-sm font-bold tracking-wider uppercase mb-2">Requirements</h2>
                        <div class="divider my-0"></div>
                        <ul class="list-disc list-outside pl-5 space-y-2 pt-3 text-ink2">
                            <?php foreach ($job['requirements'] as $item): ?>
                                <li><?php echo $item; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div> -->

                <!-- Required Skills -->
                <!-- <div class="card bg-surface border border-line rounded-2xl">
                    <div class="card-body p-6">
                        <h2 class="text-primary text-sm font-bold tracking-wider uppercase mb-2">Required Skills</h2>
                        <div class="divider my-0"></div>
                        <div class="flex flex-wrap gap-2 pt-3">
                            <?php foreach ($job['required_skills'] as $skill): ?>
                                <span class="badge badge-lg bg-card2 text-ink2 border-none font-normal py-3 px-3">
                                    <?php echo htmlspecialchars($skill); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div> -->

                <!-- Key Skills -->
                <?php if ($job['key_skills']): ?>
                    <div class="card bg-surface border border-line rounded-2xl">
                        <div class="card-body p-6">
                            <h2 class="text-primary text-sm font-bold tracking-wider uppercase mb-2">Requirements</h2>
                            <div class="divider my-0"></div>
                            <div class="rich-content text-[14.5px] leading-[1.78] text-ink pt-3">
                                <?= $job['key_skills'] ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Why Join Us -->
                <?php if ($job['our_terms']): ?>
                    <div class="card bg-surface border border-line rounded-2xl">
                        <div class="card-body p-6">
                            <h2 class="text-primary text-sm font-bold tracking-wider uppercase mb-2">Why Join Us</h2>
                            <div class="divider my-0"></div>
                            <div class="rich-content text-[14.5px] leading-[1.78] text-ink">
                                <?= $job['our_terms'] ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Equal Opportunity -->
                <!-- <div class="card bg-surface border border-line rounded-2xl">
                    <div class="card-body p-6">
                        <h2 class="text-primary text-sm font-bold tracking-wider uppercase mb-2">Equal Opportunity</h2>
                        <div class="divider my-0"></div>
                        <p class="text-ink2 leading-relaxed pt-3">
                            <?php echo htmlspecialchars($job['equal_opportunity']); ?>
                        </p>
                    </div>
                </div> -->

            </div>

            <!-- Right Sidebar -->
            <div class="lg:col-span-1">
                <div class="sticky top-6 space-y-4">
                    <button class="btn w-full bg-primary hover:bg-accent-dark text-primary-content border-none rounded-xl text-base font-semibold h-14">
                        Apply Now !
                    </button>

                    <div class="apply-are">
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
                    <div class="card bg-surface border border-line rounded-2xl">
                        <div class="card-body p-6 divide-y divide-line">
                            <?php
                            $first = true;
                            foreach ($sideBar as $label => $value):
                            ?>
                                <div class="<?php echo $first ? 'pb-4' : 'py-4'; ?>">
                                    <p class="text-sm font-semibold text-ink mb-1"><?php echo htmlspecialchars($label); ?></p>
                                    <?php if (is_array($value)): ?>
                                        <p class="text-sm text-ink2 flex items-center gap-1.5">
                                            <?php echo $value['flag']; ?> <?php echo htmlspecialchars($value['text']); ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="text-sm text-ink2"><?php echo htmlspecialchars($value); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php
                                $first = false;
                            endforeach;
                            ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

</body>

</html>