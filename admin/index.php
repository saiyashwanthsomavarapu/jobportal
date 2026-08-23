<?php

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/utils/classes.php";

$pageTitle = "Dashboard";

$breadcrumbs = [["Dashboard", null]];

/* Dashboard Data */

$total = 0;
$published = 0;
$drafts = 0;
$closed = 0;
$thisMonth = 0;
$recent = [];
$byCountry = [];

try {
  $pdo = db();

  /* Job Statistics */

  $total = (int) $pdo
    ->query(
      "
        SELECT COUNT(*)
        FROM jobs
    "
    )
    ->fetchColumn();

  $published = (int) $pdo
    ->query(
      "
        SELECT COUNT(*)
        FROM jobs
        WHERE status = 'published'
          AND (
                close_date IS NULL
                OR close_date >= CURDATE()
              )
    "
    )
    ->fetchColumn();

  $drafts = (int) $pdo
    ->query(
      "
        SELECT COUNT(*)
        FROM jobs
        WHERE status = 'draft'
    "
    )
    ->fetchColumn();

  $closed = (int) $pdo
    ->query(
      "
        SELECT COUNT(*)
        FROM jobs
        WHERE status = 'closed'
          OR (
                status = 'published'
                AND close_date IS NOT NULL
                AND close_date < CURDATE()
              )
    "
    )
    ->fetchColumn();

  /* Recent Jobs */

  $recent = $pdo
    ->query(
      "
        SELECT
            j.*,
            REGEXP_REPLACE(j.client_code, '[^0-9]', '') AS client_code,
            a.name AS posted_by
        FROM jobs j
        LEFT JOIN admin_users a
            ON a.id = j.created_by
        ORDER BY j.created_at DESC
        LIMIT 8
    "
    )
    ->fetchAll();

  /* Published Jobs By Country */

  $byCountry = $pdo
    ->query(
      "
        SELECT
            country,
            COUNT(*) AS cnt
        FROM jobs
        WHERE status = 'published'
          AND country IS NOT NULL
          AND country != ''
        GROUP BY country
        ORDER BY cnt DESC
    "
    )
    ->fetchAll();

  /* Jobs Created This Month */

  $thisMonth = (int) $pdo
    ->query(
      "
        SELECT COUNT(*)
        FROM jobs
        WHERE
            MONTH(created_at) = MONTH(CURRENT_DATE())
            AND YEAR(created_at) = YEAR(CURRENT_DATE())
    "
    )
    ->fetchColumn();
} catch (Exception $e) {
  $total = 0;
  $published = 0;
  $drafts = 0;
  $closed = 0;
  $thisMonth = 0;

  $recent = [];
  $byCountry = [];
}

/* Helpers */

function dashStatusStyles(string $status): array
{
  return match ($status) {
    "published" => [
      "badge" => "border-emerald-200 bg-emerald-50 text-emerald-700",
      "dot" => "bg-emerald-500",
    ],
    "draft" => [
      "badge" => "border-amber-200 bg-amber-50 text-amber-700",
      "dot" => "bg-amber-500",
    ],
    "closed" => [
      "badge" => "border-gray-200 bg-gray-50 text-gray-600",
      "dot" => "bg-gray-400",
    ],
    "archived" => [
      "badge" => "border-red-200 bg-red-50 text-red-700",
      "dot" => "bg-red-500",
    ],
    default => [
      "badge" => "border-gray-200 bg-gray-50 text-gray-600",
      "dot" => "bg-gray-400",
    ],
  };
}

function timeAgo(string $datetime): string
{
  $timestamp = strtotime($datetime);

  if (!$timestamp) {
    return "—";
  }

  $diff = time() - $timestamp;

  if ($diff < 60) {
    return "just now";
  }

  if ($diff < 3600) {
    return floor($diff / 60) . "m ago";
  }

  if ($diff < 86400) {
    return floor($diff / 3600) . "h ago";
  }

  if ($diff < 86400 * 7) {
    return floor($diff / 86400) . "d ago";
  }

  return date("d M", $timestamp);
}

/* Greeting */

$hour = (int) date("G");

$greeting =
  $hour < 12
  ? "Good morning"
  : ($hour < 17
    ? "Good afternoon"
    : "Good evening");

$firstName = "Admin";

if (!empty($currentAdmin["name"])) {
  $firstName = explode(" ", trim($currentAdmin["name"]))[0];
}

/* Country Data */

$totalPublished = array_sum(
  array_map(fn($item) => (int) $item["cnt"], $byCountry)
);

$countryColors = [
  "#2563eb",
  "#10b981",
  "#f59e0b",
  "#8b5cf6",
  "#ef4444",
  "#06b6d4",
];

/* Include Header */

include __DIR__ . "/includes/header.php";
?>

<div class="min-w-0 space-y-6">
  <!-- HEADER -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <h1 class="mt-2 font-head text-2xl font-bold tracking-tight text-base-content sm:text-[26px]">
        <?= e($greeting) ?>, <?= e($firstName) ?>
      </h1>

      <p class="mt-1 text-sm text-base-content/55">
        Here's a quick overview of your job postings.
      </p>
    </div>
  </div>

  <!-- ACTION CARDS -->
  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <!-- TOTAL -->
    <a
      href="<?= ADMIN_URL ?>/pages/jobs.php"
      class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
      <div class="absolute inset-x-0 top-0 h-[3px] bg-blue-600"></div>
      <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">
            Total Jobs
          </p>
          <p class="mt-2 font-head text-[29px] font-extrabold leading-none text-gray-900">
            <?= number_format($total) ?>
          </p>
          <div class="mt-3 flex items-center gap-2">
            <span class="rounded-md bg-blue-50 px-2 py-1 text-[10px] font-semibold text-blue-700">
              +<?= number_format($thisMonth) ?>
            </span>
            <span class="text-[11px] text-gray-400">
              this month
            </span>
          </div>
        </div>

        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-[19px] w-[19px]"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.7">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2Z" />
          </svg>
        </div>
      </div>

      <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3">
        <span class="text-[10px] text-gray-400">
          All job postings
        </span>
        <span class="flex items-center gap-1 text-[10px] font-semibold text-blue-600 transition-all group-hover:gap-1.5">
          View all
          <span>→</span>
        </span>
      </div>
    </a>

    <!-- PUBLISHED -->
    <a
      href="<?= ADMIN_URL ?>/pages/jobs.php?status=published"
      class="group relative overflow-hidden rounded-2xl border border-gray-200 p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
      <div class="absolute inset-x-0 top-0 h-[3px] bg-emerald-500"></div>
      <div class="flex items-start justify-between gap-4">
        <div>
          <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">
            Published
          </p>
          <p class="mt-2 font-head text-[29px] font-extrabold leading-none text-gray-900">
            <?= number_format($published) ?>
          </p>
          <div class="mt-3 flex items-center gap-2 text-[11px] text-gray-400">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
            Live on site
          </div>
        </div>
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-[19px] w-[19px]"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.7">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653Z" />
          </svg>
        </div>
      </div>

      <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3">
        <span class="text-[10px] text-gray-400">
          Active postings
        </span>
        <span class="flex items-center gap-1 text-[10px] font-semibold text-emerald-600 transition-all group-hover:gap-1.5">
          Manage
          <span>→</span>
        </span>
      </div>
    </a>

    <!-- DRAFT -->
    <a
      href="<?= ADMIN_URL ?>/pages/jobs.php?status=draft"
      class="group relative overflow-hidden rounded-2xl border border-gray-200 p-5  shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-amber-200 hover:shadow-md">
      <div class="absolute inset-x-0 top-0 h-[3px] bg-amber-500"></div>
      <div class="flex items-start justify-between gap-4">
        <div>
          <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">
            Drafts
          </p>
          <p class="mt-2 font-head text-[29px] font-extrabold leading-none text-gray-900">
            <?= number_format($drafts) ?>
          </p>
          <div class="mt-3 flex items-center gap-2 text-[11px] text-gray-400">
            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
            Pending review
          </div>
        </div>
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-[19px] w-[19px]"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.7">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931Z" />
          </svg>
        </div>
      </div>
      <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3">
        <span class="text-[10px] text-gray-400">
          Needs attention
        </span>
        <span class="flex items-center gap-1 text-[10px] font-semibold text-amber-600 transition-all group-hover:gap-1.5">
          Review
          <span>→</span>
        </span>
      </div>
    </a>

    <!-- CLOSED -->
    <a
      href="<?= ADMIN_URL ?>/pages/jobs.php?status=closed"
      class="group relative overflow-hidden rounded-2xl border border-gray-200 p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-gray-300 hover:shadow-md">
      <div class="absolute inset-x-0 top-0 h-[3px] bg-gray-200"></div>
      <div class="flex items-start justify-between gap-4">
        <div>
          <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">
            Closed
          </p>
          <p class="mt-2 font-head text-[29px] font-extrabold leading-none text-gray-900">
            <?= number_format($closed) ?>
          </p>
          <div class="mt-3 flex items-center gap-2 text-[11px] text-gray-400">
            <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
            No longer active
          </div>
        </div>
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-50 text-gray-500">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-[19px] w-[19px]"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.7">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M6 18L18 6M6 6l12 12" />
          </svg>
        </div>
      </div>
      <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3">
        <span class="text-[10px] text-gray-400">
          Historical postings
        </span>
        <span class="flex items-center gap-1 text-[10px] font-semibold text-gray-500 transition-all group-hover:gap-1.5">
          View
          <span>→</span>
        </span>
      </div>
    </a>
  </div>

  <!-- RECENT JOBS + COUNTRY -->
  <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[1.6fr_1fr]">
    <!-- RECENT JOBS -->
    <div
      class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 sm:px-6">
        <div>
          <div class="font-head text-[15px] font-bold text-gray-900">
            Recent Jobs
          </div>
          <div class="mt-1 text-[11px] text-gray-500">
            Latest job postings
          </div>
        </div>
        <a
          href="<?= ADMIN_URL ?>/pages/jobs.php"
          class="group flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-[11px] font-semibold text-gray-500 transition hover:bg-gray-50 hover:text-gray-900">
          View All
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M9 5l7 7-7 7" />
          </svg>
        </a>
      </div>

      <?php if (empty($recent)): ?>
        <div class="flex min-h-[320px] items-center justify-center px-6 py-12">
          <div class="text-center">
            <div class="<?= SVG_DIV ?>">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="<?= SVG_ICON ?>"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.5">
                <circle
                  cx="12"
                  cy="12"
                  r="9" />
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M12 7v5l3 2" />
              </svg>
            </div>
            <p class="mt-3 text-[13px] font-medium text-gray-700">
              No jobs yet
            </p>
            <p class="mt-1 text-[11px] text-gray-400">
              Create your first job posting.
            </p>
            <a
              href="<?= ADMIN_URL ?>/pages/post_job.php"
              class="mt-4 inline-flex items-center rounded-lg bg-blue-600 px-3.5 py-2 text-[11px] font-semibold text-gray-900 hover:bg-blue-700">
              Post a Job
            </a>
          </div>
        </div>

      <?php else: ?>
        <div class="divide-y divide-gray-100">
          <?php foreach ($recent as $job): ?>
            <?php $statusStyles = dashStatusStyles((string) $job["status"]); ?>
            <a
              href="<?= ADMIN_URL ?>/pages/post_job.php?edit=<?= (int) $job["id"] ?>"
              class="group block px-5 py-4 transition hover:bg-gray-50 sm:px-6">
              <div class="flex items-start gap-3.5">
                <!-- Icon -->
                <div class="<?= SVG_DIV ?>">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="<?= SVG_ICON ?>"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.7">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2Z" />
                  </svg>
                </div>
                <!-- Content -->
                <div class="min-w-0 flex-1">
                  <div class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-2">
                      <h3 class="truncate text-[13px] font-semibold text-gray-800 transition">
                        <?= e($job["job_title"]) ?>
                      </h3>
                    </div>
                    <span class="shrink-0 text-[10px] text-gray-400">
                      <?= timeAgo($job["created_at"]) ?>
                    </span>
                  </div>
                  <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-gray-500">
                    <code class="badge badge-soft badge-primary rounded-md font-mono text-[11px] font-semibold">
                      <?= e($job['job_code']) ?>
                    </code>

                    <?php if (!empty($job["client_code"])): ?>
                      <span class="text-gray-300">
                        •
                      </span>
                      <span>
                        Job code: <?= e($job["client_code"]) ?>
                      </span>
                    <?php endif; ?>

                    <?php if (!empty($job["country"])): ?>
                      <span class="text-gray-300">
                        •
                      </span>
                      <span class="truncate">
                        <?= e($job["city"] ?? "") ?>
                        <?= !empty($job["city"]) ? ", " : "" ?>
                        <?= e($job["country"]) ?>
                      </span>
                    <?php endif; ?>

                  </div>
                  <div class="mt-2">
                    <span
                      class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[10px] font-semibold <?= $statusStyles["badge"] ?>">
                      <span class="h-1.5 w-1.5 rounded-full <?= $statusStyles["dot"] ?>"></span>
                      <?= e(ucfirst($job["status"])) ?>
                    </span>
                  </div>
                </div>
                <!-- Arrow -->
                <div class="hidden self-center sm:block">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4 text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-gray-500"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.8">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M9 5l7 7-7 7" />
                  </svg>
                </div>
              </div>
            </a>
          <?php endforeach; ?>

        </div>

      <?php endif; ?>
    </div>

    <!-- JOBS BY COUNTRY -->
    <div
      class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
      <div class="flex items-start justify-between">
        <div>
          <div class="font-head text-[15px] font-bold text-gray-900">
            Jobs by Country
          </div>
          <div class="mt-1 text-[11px] text-gray-500">
            Published job distribution
          </div>
        </div>

        <div class="<?= SVG_DIV ?>">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="<?= SVG_ICON ?>"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.6">
            <circle
              cx="12"
              cy="12"
              r="9" />
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M3 12h18M12 3c2.5 2.8 3.75 5.8 3.75 9S14.5 18.2 12 21c-2.5-2.8-3.75-5.8-3.75-9S9.5 5.8 12 3Z" />
          </svg>
        </div>
      </div>

      <?php if (empty($byCountry)): ?>
        <div class="flex min-h-[320px] items-center justify-center text-center">
          <div>
            <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-gray-50">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5 text-gray-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.5">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
              </svg>
            </div>
            <p class="mt-3 text-[13px] font-medium text-gray-700">
              No published jobs
            </p>
            <p class="mt-1 text-[11px] text-gray-400">
              Country distribution will appear here.
            </p>
          </div>
        </div>
      <?php else: ?>

        <div class="mt-6 space-y-5">
          <?php foreach (array_slice($byCountry, 0, 6) as $index => $row): ?>
            <?php
            $count = (int) $row["cnt"];

            $percentage =
              $totalPublished > 0
              ? round(($count / $totalPublished) * 100)
              : 0;

            $color = $countryColors[$index % count($countryColors)];
            ?>
            <div>
              <div class="mb-2 flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-2">
                  <span
                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                    style="background-color:<?= $color ?>">
                  </span>
                  <span class="truncate text-[12px] font-medium text-gray-700">
                    <?= e($row["country"]) ?>
                  </span>
                </div>
                <div class="shrink-0 text-[11px] text-gray-400">
                  <span class="font-semibold text-gray-800">
                    <?= number_format($count) ?>
                  </span>
                  <span class="mx-1 text-gray-300">
                    •
                  </span>
                  <?= $percentage ?>%
                </div>
              </div>
              <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                <div
                  class="h-full rounded-full transition-all"
                  style="width:<?= max(
                                  $percentage,
                                  $count > 0 ? 2 : 0
                                ) ?>%;background-color:<?= $color ?>;">
                </div>
              </div>
            </div>
          <?php endforeach; ?>

        </div>

        <?php if (count($byCountry) > 6): ?>
          <div class="mt-5 border-t border-gray-100 pt-4">
            <a
              href="<?= ADMIN_URL ?>/pages/jobs.php?status=published"
              class="group flex items-center justify-between">
              <span class="text-[11px] text-gray-400">
                <?= count($byCountry) - 6 ?> more countries
              </span>
              <span class="flex items-center gap-1 text-[10px] font-semibold text-blue-600 transition-all group-hover:gap-1.5">
                View published jobs
                <span>
                  →
                </span>
              </span>
            </a>
          </div>
        <?php endif; ?>
      <?php endif; ?>

    </div>
  </div>
  <!--  OPTIONAL QUICK ACTIONS -->
  <!-- <div>
    <div class="mb-3">
      <div class="font-head text-[14px] font-bold text-gray-900">
        Quick Actions
      </div>
      <div class="mt-1 text-[11px] text-gray-500">
        Common tasks
      </div>
    </div>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
      <a
        href="<?= ADMIN_URL ?>/pages/post_job.php"
        class="group rounded-xl border border-gray-200 p-4 transition hover:border-blue-200 hover:bg-gray-50"
        >
        <div class="flex items-center gap-3">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="1.7">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M12 5v14M5 12h14" />
            </svg>
          </div>
          <div>
            <div class="text-[12px] font-semibold text-gray-900">
              Create Job
            </div>
            <div class="mt-0.5 text-[10px] text-gray-400">
              Add a new opportunity
            </div>
          </div>
        </div>
      </a>

      <a
        href="<?= ADMIN_URL ?>/pages/jobs.php?status=draft"
        class="group rounded-xl border border-gray-200 p-4 transition hover:border-amber-200 hover:bg-gray-50"
        >
        <div class="flex items-center gap-3">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="1.7">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931Z" />
            </svg>
          </div>
          <div>
            <div class="text-[12px] font-semibold text-gray-900">
              Review Drafts
            </div>
            <div class="mt-0.5 text-[10px] text-gray-400">
              <?= number_format($drafts) ?> waiting for review
            </div>
          </div>
        </div>
      </a>

      <a
        href="<?= ADMIN_URL ?>/pages/jobs.php"
        class="group rounded-xl border border-gray-200 p-4 transition hover:border-gray-300 hover:bg-gray-50"
        >
        <div class="flex items-center gap-3">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-500">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="1.7">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </div>
          <div>
            <div class="text-[12px] font-semibold text-gray-900">
              Manage Jobs
            </div>
            <div class="mt-0.5 text-[10px] text-gray-400">
              Search and update postings
            </div>
          </div>
        </div>
      </a>
    </div>
  </div> -->
</div>

<?php include __DIR__ . "/includes/footer.php";
?>