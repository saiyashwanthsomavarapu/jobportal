<?php

require_once dirname(__DIR__) . "/auth.php";
require_once dirname(__DIR__) . "/utils/classes.php";

$pdo = db();

/* LOAD JOB FOR EDIT / CLONE */

$editId = (int) ($_GET["edit"] ?? 0);
$cloneId = (int) ($_GET["clone"] ?? 0);

$job = null;

$isEdit = false;
$isClone = false;

$cloneNewCode = "";
$cloneNewNumber = 0;

/* Edit */
if ($editId > 0) {
  $stmt = $pdo->prepare("
        SELECT *
        FROM jobs
        WHERE id = ?
    ");
  $stmt->execute([$editId]);
  $job = $stmt->fetch();

  if (!$job) {
    flash("error", "Job not found.");
    redirect(ADMIN_URL . "/pages/jobs.php");
  }
  $isEdit = true;
}
/* Clone */ elseif ($cloneId > 0) {
  $stmt = $pdo->prepare("
        SELECT *
        FROM jobs
        WHERE id = ?
    ");

  $stmt->execute([$cloneId]);

  $job = $stmt->fetch();

  if (!$job) {
    flash("error", "Job to clone not found.");
    redirect(ADMIN_URL . "/pages/jobs.php");
  }

  $isClone = true;

  // Generate code once so it can be displayed in the form.
  $cloneGenerated = nextJobCode("AC");

  $cloneNewCode = str_replace("-", "", $cloneGenerated["code"]);
  $cloneNewNumber = $cloneGenerated["number"];
}

/* PAGE INFORMATION */

if ($isEdit) {
  $pageTitle = "Edit Job: " . ($job["job_code"] ?? "");
} elseif ($isClone) {
  $pageTitle = "Clone Job &rarr; " . $cloneNewCode;
} else {
  $pageTitle = "Post a Job";
}

$breadcrumbs = [
  ["Dashboard", ADMIN_URL . "/index.php"],
  ["All Jobs", ADMIN_URL . "/pages/jobs.php"],
  [$isEdit ? "Edit Job" : ($isClone ? "Clone Job" : "Post a Job"), null],
];

/* LOAD CLIENTS */
try {
  $clientsList = $pdo
    ->query(
      "
            SELECT id, client_name, client_code
            FROM clients
            ORDER BY client_name ASC
        "
    )
    ->fetchAll();
} catch (Exception $e) {
  $clientsList = [];
}

/* CONFIGURATION */

$countryData = require __DIR__ . "/../config/countries.php";
$countries = $countryData;

$jobFields = require __DIR__ . "/../config/job-fields.php";

$workplaceTypes = ["Onsite", "Hybrid", "Remote"];

$expFromOpts = array_map("strval", range(0, 10));

$expToOpts = array_merge(array_map("strval", range(4, 15)), ["15+"]);

$salaryTypes = ["Annual", "Monthly", "Per Hour", "Yearly"];


/* HELPERS */

function parseExperience(string $experience): array
{
  $experience = trim($experience);

  if (
    preg_match(
      "/^(\d+)\s*[-–]\s*([\d]+\+?)\s*years?/i",
      $experience,
      $matches
    )
  ) {
    return [
      "from" => $matches[1],
      "to" => $matches[2],
    ];
  }

  if (preg_match("/^([\d]+\+?)\s*years?/i", $experience, $matches)) {
    return [
      "from" => "",
      "to" => $matches[1],
    ];
  }

  return [
    "from" => "",
    "to" => "",
  ];
}

function buildExperience(string $from, string $to): string
{
  if ($from === "" || $to === "") {
    return "";
  }

  return "{$from} - {$to} years";
}

function buildSalaryRate(array $data): string
{
  if (!empty($data["salary_boe"])) {
    return "Based on Experience";
  }

  $from = "";

  if ($data["salary_from"] !== "") {
    $from = $data["salary_from"];

    if ($data["salary_unit_from"] !== "") {
      $from .= " " . $data["salary_unit_from"];
    }
  }

  $to = "";

  if ($data["salary_to"] !== "") {
    $to = $data["salary_to"];

    if ($data["salary_unit_to"] !== "") {
      $to .= " " . $data["salary_unit_to"];
    }
  }

  if ($from !== "" && $to !== "") {
    $range = $from . " - " . $to;
  } else {
    $range = $from ?: $to;
  }

  return trim(
    implode(
      " | ",
      array_filter([
        $range,
        $data["salary_currency"],
        $data["salary_type"],
      ])
    )
  );
}

function generateJobCode(bool $isClone, array &$data): void
{
  if ($isClone && !empty($_POST["job_code_display"])) {
    $data["job_code"] = trim($_POST["job_code_display"]);
    $data["job_code_number"] = (int) ($_POST["job_code_number"] ?? 0);
    $data["job_code_prefix"] = "AC";
    return;
  }

  $generated = nextJobCode("AC");
  $data["job_code"] = str_replace("-", "", $generated["code"]);

  $data["job_code_number"] = $generated["number"];

  $data["job_code_prefix"] = "AC";
}

function makeUniqueSlug(PDO $pdo, string $title, int $excludeId = 0): string
{
  $baseSlug = slugify($title);
  $slug = $baseSlug;

  $stmt = $pdo->prepare("
        SELECT id
        FROM jobs
        WHERE slug COLLATE utf8mb4_unicode_ci = ?
          AND id != ?
        LIMIT 1
    ");

  $counter = 1;

  while (true) {
    $stmt->execute([$slug, $excludeId]);
    if (!$stmt->fetch()) {
      return $slug;
    }
    $counter++;
    $slug = $baseSlug . "-" . $counter;
  }
}

function buildJobParams(array $fields, array $data): array
{
  $params = [];
  foreach ($fields as $field) {
    $params[":{$field}"] = $data[$field] ?? null;
  }
  return $params;
}

function validateJob(array $data, string $clientSuffix): array
{
  $errors = [];

  if (!$data["country"]) {
    $errors[] = "Country is required.";
  }

  if (
    $clientSuffix !== "" &&
    (strlen($clientSuffix) < 4 || strlen($clientSuffix) > 5)
  ) {
    $errors[] = "Client Code suffix must be 4 or 5 digits.";
  }

  if (!$data["job_title"]) {
    $errors[] = "Job Title is required.";
  }

  if ($data["workplace_type"] !== "Remote") {
    if (!$data["city"]) {
      $errors[] = "City is required.";
    }

    if (!$data["state_province"]) {
      $errors[] = "State / Province is required.";
    }
  }

  if (!$data["workplace_type"]) {
    $errors[] = "Workplace Type is required.";
  }

  if (!$data["timezone"]) {
    $errors[] = "Time Zone is required.";
  }

  if (!$data["job_type"]) {
    $errors[] = "Job Type is required.";
  }

  if ($data["exp_from"] === "") {
    $errors[] = 'Experience "From" is required.';
  }

  if ($data["exp_to"] === "") {
    $errors[] = 'Experience "To" is required.';
  }

  if (!$data["salary_boe"]) {
    if (!$data["salary_from"]) {
      $errors[] = 'Salary / Rate "From" value is required.';
    }

    if (!$data["salary_to"]) {
      $errors[] = 'Salary / Rate "To" value is required.';
    }

    if (!$data["salary_currency"]) {
      $errors[] = "Salary Currency is required.";
    }

    if (!$data["salary_type"]) {
      $errors[] = "Salary Type is required.";
    }
  }

  if (!$data["open_date"]) {
    $errors[] = "Open Date is required.";
  }

  if (!$data["close_date"]) {
    $errors[] = "Close Date is required.";
  }

  // if (!$data['job_description'] || strip_tags($data['job_description']) === '') {
  //   $errors[] = 'Job Description is required.';
  // }
  // if (!$data['key_skills'] || strip_tags($data['key_skills']) === '') {
  //   $errors[] = 'Requirements is required.';
  // }

  if (!$data["our_terms"] || strip_tags($data["our_terms"]) === "") {
    $errors[] = "Why Join Us is required.";
  }

  return $errors;
}

// ============================================================
// FORM DATA
// ============================================================

$errors = [];

$old = $isEdit || $isClone ? $job : ($_POST ?: []);

// ============================================================
// PROCESS FORM
// ============================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  /* Client code */
  $ccSuffix = trim($_POST["client_code_suffix"] ?? "");

  // Digits only
  $ccSuffix = preg_replace("/[^0-9]/", "", $ccSuffix);



  /* Dates */

  if ($isEdit) {
    // Preserve original open date.
    $openDateRaw = $job["open_date"] ?? date("Y-m-d");

    try {
      $autoCloseDate = (new DateTime($openDateRaw))
        ->modify("+28 days")
        ->format("Y-m-d");
    } catch (Exception $e) {
      $autoCloseDate = date("Y-m-d", strtotime("+28 days"));
    }
  } else {
    // New + Clone
    $openDateRaw = date("Y-m-d");

    $autoCloseDate = date("Y-m-d", strtotime("+28 days"));
  }

  /* Build data */
  $data = [
    // Edit preserves country/job code.
    // New/Clone gets values from form.
    "country" => $isEdit ? $job["country"] ?? "" : trim($_POST["country"] ?? ""),
    "job_code" => $isEdit ? $job["job_code"] ?? "" : trim($_POST["job_code_display"] ?? ""),
    "job_code_prefix" => $isEdit ? $job["job_code_prefix"] ?? "" : trim($_POST["job_code_prefix"] ?? ""),
    "job_code_number" => $isEdit ? $job["job_code_number"] ?? 0 : (int) ($_POST["job_code_number"] ?? 0),
    "client_code" => $ccSuffix,
    "job_title" => trim($_POST["job_title"] ?? ""),
    "city" => trim($_POST["city"] ?? ""),
    "state_province" => trim($_POST["state_province"] ?? ""),
    "postal_code" => "",
    "workplace_type" => trim($_POST["workplace_type"] ?? ""),
    "workplace_type_other" => trim($_POST["workplace_type_other"] ?? ""),
    "timezone" => trim($_POST["timezone"] ?? ""),
    "job_type" => trim($_POST["job_type"] ?? ""),
    "job_type_other" => trim($_POST["job_type_other"] ?? ""),
    "exp_from" => trim($_POST["exp_from"] ?? null),
    "exp_to" => trim($_POST["exp_to"] ?? null),
    "experience" => "",
    "salary_boe" => isset($_POST["salary_boe"]) ? 1 : 0,
    "salary_from" => preg_replace(
      "/[^0-9.]/",
      "",
      trim($_POST["salary_from"] ?? "")
    ),
    "salary_to" => preg_replace(
      "/[^0-9.]/",
      "",
      trim($_POST["salary_to"] ?? "")
    ),
    "salary_unit_from" => trim($_POST["salary_unit_from"] ?? ""),
    "salary_unit_to" => trim($_POST["salary_unit_to"] ?? ""),
    "salary_type" => trim($_POST["salary_type"] ?? ""),
    "salary_currency" => trim($_POST["salary_currency"] ?? ""),
    "salary_rate" => "",
    "industry" => "",
    "industry_other" => "",
    "job_description" => $_POST["our_terms"] ?? "abdc",
    "key_skills" => $_POST["our_terms"] ?? "abcd",
    "our_terms" => $_POST["our_terms"] ?? "abdc",
    "open_date" => $openDateRaw,
    "close_date" => $autoCloseDate,
    "status" => ($_POST["submit_action"] ?? "") === "publish"
      ? "published"
      : "draft",
    "client_id" => trim($_POST["client_id"] ?? 0),
  ];

  /* Experience */

  $data["experience"] = buildExperience($data["exp_from"], $data["exp_to"]);

  /* Salary */

  if ($data["salary_boe"]) {
    $data["salary_rate"] = "Based on Experience";

    $data["salary_from"] = "";
    $data["salary_to"] = "";
  } else {
    $data["salary_rate"] = buildSalaryRate($data);
  }

  /*  Validation */
  $errors = validateJob($data, $ccSuffix);

  /* SAVE */

  if (empty($errors)) {
    try {
      /* Generate job code for New / Clone */
      if (!$isEdit) {
        generateJobCode($isClone, $data);
      }

      /* Unique slug */
      $slug = makeUniqueSlug($pdo, $data["job_title"], $editId);

      /* EDIT */
      if ($isEdit) {
        $set = implode(
          ', ',
          array_map(
            fn($field) => "{$field} = :{$field}",
            $jobFields
          )
        );

        $params = [];

        foreach ($jobFields as $field) {
          $params[":{$field}"] =
            $field === 'slug'
            ? $slug
            : ($data[$field] ?? null);
        }

        $params[':status_check'] = $data['status'];
        $params[':id'] = $editId;

        $sql = "
          UPDATE jobs
          SET
              {$set},
              published_at = IF(
                  :status_check = 'published'
                  AND published_at IS NULL,
                  NOW(),
                  published_at
              )
          WHERE id = :id
      ";

        $pdo->prepare($sql)->execute($params);

        // Activity log
        logActivity("edit_job", "job", $editId, $data["job_code"]);
        flash("success", "Job updated successfully.");
      }

      /* CREATE / CLONE */ else {
        // Build dynamic INSERT query
        $insertFields = array_merge($jobFields, ['slug', 'created_by', 'published_at']);
        $insertColumns = implode(', ', $insertFields);

        // Build placeholders
        $placeholders = [];
        foreach ($insertFields as $field) {
          if ($field === 'published_at') {
            $placeholders[] = "IF(:published_status = 'published', NOW(), NULL)";
          } else {
            $placeholders[] = ":{$field}";
          }
        }
        $insertValues = implode(', ', $placeholders);

        // Build parameters
        $params = buildJobParams($jobFields, $data);
        $params[":slug"] = $slug;
        $params[":created_by"] = $_SESSION["admin_id"];
        $params[":published_status"] = $data["status"];

        $sql = "INSERT INTO jobs ({$insertColumns}) VALUES ({$insertValues})";
        $pdo->prepare($sql)->execute($params);

        // IMPORTANT:
        // Use the same variable for both clone and create.
        $newId = (int) $pdo->lastInsertId();

        /* Clone */
        if ($isClone) {
          logActivity(
            "clone_job",
            "job",
            $newId,
            $data["job_code"] . " (cloned from " . $cloneId . ")"
          );

          flash(
            "success",
            "Job cloned! New Job Number: <strong>" .
              e($data["job_code"]) .
              "</strong>"
          );
        }

        /* New job */ else {
          logActivity("create_job", "job", $newId, $data["job_code"]);

          flash(
            "success",
            "Job posted! Code: <strong>" .
              e($data["job_code"]) .
              "</strong>"
          );
        }
      }

      /* Redirect after successful save */
      redirect(ADMIN_URL . "/pages/jobs.php");
    } catch (Exception $e) {
      $errors[] = "Database error: " . $e->getMessage();
    }
  }

  // Preserve entered values after validation error.
  $old = array_merge((array) $old, $data);
}

/* FORM VALUE HELPERS */

function old(string $key, $default = ""): string
{
  global $old;
  return e((string) ($old[$key] ?? $default));
}

function oldSel(string $key, string $value): string
{
  global $old;
  return isset($old[$key]) && (string) $old[$key] === $value
    ? "selected"
    : "";
}

/* EXPERIENCE VALUES */
$savedExpFrom = "";
$savedExpTo = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $savedExpFrom = $_POST["exp_from"] ?? "";
  $savedExpTo = $_POST["exp_to"] ?? "";
} elseif (($isEdit || $isClone) && !empty($job["experience"])) {
  $experience = parseExperience($job["experience"]);
  $savedExpFrom = $experience["from"];
  $savedExpTo = $experience["to"];
}

/* SALARY VALUES */
$savedSalFrom = "";
$savedSalTo = "";
$savedSalType = "";
$savedSalCur = "";
$savedSalUnitFrom = "";
$savedSalUnitTo = "";
$savedSalBoe = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $savedSalFrom = $_POST["salary_from"] ?? "";
  $savedSalTo = $_POST["salary_to"] ?? "";
  $savedSalType = $_POST["salary_type"] ?? "";
  $savedSalCur = $_POST["salary_currency"] ?? "";
  $savedSalUnitFrom = $_POST["salary_unit_from"] ?? "";
  $savedSalUnitTo = $_POST["salary_unit_to"] ?? "";
  $savedSalBoe = isset($_POST["salary_boe"]);
} elseif ($isEdit || $isClone) {
  $savedSalFrom = $job["salary_from"] ?? "";
  $savedSalTo = $job["salary_to"] ?? "";
  $savedSalType = $job["salary_type"] ?? "";
  $savedSalCur = $job["salary_currency"] ?? "";
  $savedSalBoe = !empty($job["salary_boe"]);
  $savedSalUnitFrom = $job["salary_unit_from"] ?? "";
  $savedSalUnitTo = $job["salary_unit_to"] ?? "";
  /* Restore salary information from legacy salary_rate */
  if ($savedSalFrom === "" && !empty($job["salary_rate"])) {
    $parts = array_map("trim", explode("|", $job["salary_rate"]));

    if (!empty($parts[0])) {
      $salaryText = $parts[0];
      // Example:
      // 35 LPA - 45 LPA
      // 80K - 100K
      if (
        preg_match(
          '/^([\d.]+)\s*(LPA|K)?\s*[-–]\s*([\d.]+)\s*(LPA|K)?$/i',
          $salaryText,
          $matches
        )
      ) {
        $savedSalFrom = trim($matches[1]);
        $savedSalUnitFrom = strtoupper(trim($matches[2] ?? ""));
        $savedSalTo = trim($matches[3]);
        $savedSalUnitTo = strtoupper(trim($matches[4] ?? ""));
      }

      // Example:
      // 50K
      elseif (
        preg_match('/^([\d.]+)\s*(LPA|K)?$/i', $salaryText, $matches)
      ) {
        $savedSalFrom = trim($matches[1]);
        $savedSalUnitFrom = strtoupper(trim($matches[2] ?? ""));
      }

      // Legacy format
      else {
        if (
          preg_match(
            '/^([\w.\s]+?)\s*[-–]\s*([\w.\s]+)$/',
            $salaryText,
            $matches
          )
        ) {
          $savedSalFrom = trim($matches[1]);
          $savedSalTo = trim($matches[2]);
        } else {
          $savedSalFrom = trim($salaryText);
        }
      }
    }

    if (!empty($parts[1])) {
      $savedSalCur = trim($parts[1]);
    }

    if (!empty($parts[2])) {
      $savedSalType = trim($parts[2]);
    }
  }
}

/* CLIENT CODE VALUES */

$savedCcSuffix = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $savedCcSuffix = trim($_POST["client_code_suffix"] ?? "");
} elseif (($isEdit || $isClone) && !empty($job["client_code"])) {
  // Preserve existing client code.
  // The original code did not actually parse it,
  // so behavior remains unchanged.
  $savedCcSuffix = $job["client_code"] ?? "";
}
// print_r($job);
/* HEADER */
include dirname(__DIR__) . "/includes/header.php";
?>

<!-- ══════════════ LIGHT CANVAS ══════════════
     header.php's shell (sidebar/topbar/body) is dark, and its shared tailwind.config
     defines "accent"/"danger"/"warn"/"success"/etc. as dark-theme colors. This page was
     converted to light theme on request, so every color class above uses literal hex
     values instead of those shared tokens (which would otherwise still render dark). -->
<div class="min-w-0 space-y-6">

  <?php if (!empty($errors)): ?>
    <div class="flex items-start gap-2.5 px-4 py-3.5 rounded-xl mb-6 text-[13px] font-medium bg-[#dc3545]/10 border border-[#dc3545]/25 text-[#dc3545]">
      <svg class="h-[18px] w-[18px] flex-shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <div>
        <p class="font-semibold text-[#111827] mb-1">Please fix the following:</p>
        <ul class="list-disc space-y-0.5 pl-4">
          <?php foreach ($errors as $err) {
            echo "<li>" . e($err) . "</li>";
          } ?>
        </ul>
      </div>
    </div>
  <?php endif; ?>

  <form method="POST" id="jobForm" class="space-y-5"
    action="<?= $isEdit
              ? "?edit=" . $editId
              : ($isClone
                ? "?clone=" . $cloneId
                : "") ?>">

    <?php if ($isClone): ?>
      <!-- Hidden flag so POST handler knows this is a clone submission -->
      <input type="hidden" name="clone_source_id" value="<?= $cloneId ?>">
      <div class="flex items-start gap-3 rounded-xl border border-[#d97706]/30 bg-[#d97706]/10 px-4 py-3.5 text-sm text-[#111827]">
        <svg class="h-5 w-5 text-[#d97706] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
        </svg>
        <div>
          <strong class="text-[#111827]">Cloning job <?= e(
                                                        $job["job_code"]
                                                      ) ?> — <?= e($job["job_title"]) ?></strong><br>
          <span class="text-xs text-[#5b6072]">A new Job Number <strong class="text-[#0f9d63]"><?= e(
                                                                                                  $cloneNewCode
                                                                                                ) ?></strong> has been reserved. All fields are pre-filled from the source — edit as needed, then save.</span>
        </div>
      </div>
    <?php endif; ?>

    <!-- ═══ CARD 1: CLIENT & ROLE ═══════════════════════════════
       Groups "who is this for" + "what is the role" — the two things a
       recruiter decides before anything else. Country moved in here too
       since it's part of defining the role, not a separate "step". -->
    <div class="rounded-2xl border border-base-300 bg-base-100 p-6 shadow-sm">
      <div class="mb-5 flex items-center gap-2.5 border-b border-[#e7e9f0] pb-3.5">
        <div class="<?= SVG_DIV ?>">
          <svg class="<?= SVG_ICON ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0" />
          </svg>
        </div>
        <div class="min-w-0">
          <h2 class="text-sm font-bold text-base-content">
            Client &amp; Role
          </h2>
          <p class="mt-0.5 text-xs text-base-content/50">
            Select the client and define the position you're hiring for.
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <!-- Job Number — auto, locked (AC prefix global counter). Intentionally hidden;
           the internal job number is assigned automatically and isn't a decision the
           recruiter makes, so it stays out of the visible form (unchanged from original). -->
        <div class="hidden flex-col gap-1.5">
          <label class="text-sm font-medium text-[#5b6072]">Job Number
            <?php if ($isClone): ?>
              <span class="text-[10px] font-normal text-[#d97706]"> ⧉ Cloned — new number assigned</span>
            <?php else: ?>
              <span class="text-[10px] font-normal text-[#0f9d63]"> ✓ Auto-generated</span>
            <?php endif; ?>
          </label>
          <div class="flex items-center gap-2">
            <div class="rounded-lg border border-[#e7e9f0] px-3 py-2.5 text-sm font-semibold text-[#5b6072]" style="background-color:#eef0f4 !important" id="jcPrefix">AC</div>
            <span class="text-lg font-bold text-[#9aa0b4]">-</span>
            <input type="text" class="<?= INPUT_CLASS ?>" id="jcNumDisplay"
              value="<?= $isEdit
                        ? e($job["job_code_number"])
                        : ($isClone
                          ? e($cloneNewNumber)
                          : "Auto") ?>"
              readonly tabindex="-1">
          </div>
          <input type="hidden" name="job_code_display" id="jobCodeDisplay"
            value="<?= $isEdit ? e($job["job_code"]) : ($isClone ? e($cloneNewCode) : "") ?>">
          <input type="hidden" name="job_code_prefix" id="jobCodePrefix"
            value="<?= $isEdit || $isClone ? "AC" : "AC" ?>">
          <input type="hidden" name="job_code_number" id="jobCodeNumber"
            value="<?= $isEdit ? e($job["job_code_number"]) : ($isClone ? e($cloneNewNumber) : "") ?>">
          <span class="text-xs text-[#9aa0b4]">Full number: <strong id="jcFull" class="text-[#0f9d63]">
              <?= $isEdit
                ? e($job["job_code"])
                : ($isClone
                  ? e($cloneNewCode)
                  : "Assigned on save") ?>
            </strong></span>
        </div>
        <fieldset class="fieldset">
          <legend class="fieldset-legend">
            Company or Client name
            <span class="text-error">*</span>
          </legend>
          <select name="client_id" id="client_id" class="<?= SELECT_CLASS ?>" onchange="onCountryChange()" required
            <?= $isEdit ? 'disabled' : '' ?>>
            <option value="">Select Client Name</option>

            <?php foreach ($clientsList as $client): ?>
              <option
                value="<?= e($client['id']) ?>"
                <?= oldSel('client_id', $client['id']) ?>>
                <?= e($client['client_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </fieldset>

        <!-- Client reference (was mislabeled "Job Code" — it's actually the client
          reference code, distinct from the internal Job Number above). -->
        <!-- Client Reference -->
        <fieldset class="fieldset">
          <legend class="fieldset-legend">
            Client Reference
            <span class="text-error">*</span>
            <span class="text-xs font-normal text-base-content/50">
              (internal only)
            </span>
          </legend>

          <input
            type="text"
            name="client_code_suffix"
            id="clientCodeSuffix"
            class="<?= INPUT_CLASS ?> "
            placeholder="12345"
            maxlength="5"
            value="<?= e($savedCcSuffix) ?>"
            oninput="this.value=this.value.replace(/[^0-9]/g,'');updateClientCodePreview()"
            title="4 or 5 digit number" />

          <label class="label">
            <span class="label-text-alt text-base-content/50">
              Enter a 4 or 5 digit client reference.
            </span>
          </label>
        </fieldset>


        <!-- Job Title -->
        <fieldset class="fieldset">
          <legend class="fieldset-legend">
            Job Title
            <span class="text-error">*</span>
          </legend>

          <input
            type="text"
            name="job_title"
            class="<?= INPUT_CLASS ?>"
            placeholder="e.g. Senior Full Stack Developer"
            value="<?= old('job_title') ?>"
            required />
        </fieldset>


        <!-- Country -->
        <fieldset class="fieldset">
          <legend class="fieldset-legend">
            Country
            <span class="text-error">*</span>
          </legend>

          <select name="country" id="country" class="<?= SELECT_CLASS ?>" onchange="onCountryChange()" required
            <?= $isEdit ? 'disabled' : '' ?>>
            <option value="">Select country</option>

            <?php foreach ($countryData as $c => $d): ?>
              <option
                value="<?= e($c) ?>"
                <?= oldSel('country', $c) ?>>
                <?= e($c) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <?php if ($isEdit): ?>
            <input
              type="hidden"
              name="country"
              value="<?= e($job['country'] ?? '') ?>">
          <?php endif; ?>
        </fieldset>
      </div>
    </div>

    <!-- ═══ CARD 2: LOCATION & WORK ARRANGEMENT ═══════════════════
       Workplace Type now lives WITH City/State/Timezone because it directly
       gates whether City/State are required (Remote hides them). Keeping
       the controlling field and the fields it controls in the same card
       makes that relationship visible instead of split across two cards. -->
    <div class="rounded-2xl border border-base-300 bg-base-100 p-6 shadow-sm">
      <div class="mb-6 flex items-center gap-3 border-b border-base-300 pb-4">
        <div class="<?= SVG_DIV ?>">
          <svg
            class="<?= SVG_ICON ?>"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.75">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
          </svg>
        </div>

        <div class="min-w-0">
          <h2 class="text-sm font-bold text-base-content">
            Location & Work Arrangement
          </h2>

          <p class="mt-0.5 text-xs text-base-content/50">
            Specify where the role is based and how the candidate will work.
          </p>
        </div>

      </div>


      <!-- Fields -->
      <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">

        <!-- Workplace Type -->
        <!-- Workplace Type -->
        <fieldset class="fieldset">
          <legend class="fieldset-legend">
            Workplace Type <span class="text-error">*</span>
          </legend>

          <select
            name="workplace_type"
            id="workplaceType"
            class="<?= SELECT_CLASS ?>"
            onchange="toggleLocationFields()"
            required>
            <option value="">Select type</option>

            <?php foreach ($workplaceTypes as $wt): ?>
              <option
                value="<?= e($wt) ?>"
                <?= oldSel('workplace_type', $wt) ?>>
                <?= e($wt) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="label text-xs text-base-content/50">
            Remote hides City/State below.
          </p>
        </fieldset>

        <!-- City -->
        <fieldset class="fieldset">
          <legend class="fieldset-legend">
            City <span class="text-error">*</span>
          </legend>
          <input
            type="text"
            name="city"
            id="city"
            class="<?= INPUT_CLASS ?>"
            placeholder="e.g. Bangalore"
            value="<?= old('city') ?>"
            required />
        </fieldset>


        <!-- State / Province -->
        <fieldset class="fieldset">
          <legend class="fieldset-legend">
            State / Province <span class="text-error">*</span>
          </legend>
          <select
            name="state_province"
            id="stateSelect"
            class="<?= SELECT_CLASS ?>"
            required>
            <option value="">Select country first</option>
          </select>
        </fieldset>


        <!-- Time Zone -->
        <fieldset class="fieldset">
          <legend class="fieldset-legend">
            Time Zone <span class="text-error">*</span>
          </legend>
          <select
            name="timezone"
            id="timezoneSelect"
            class="<?= SELECT_CLASS ?>"
            required>
            <option value="">Select country first</option>
          </select>
        </fieldset>
      </div>
    </div>

    <!-- ═══ CARD 3: ROLE REQUIREMENTS ══════════════════════════════
       Job Type and Experience are both "what kind of hire is this" —
       neither is about location, so pulling them out of the old Work
       Location card into their own group reads more coherently. -->
    <div class="w-full rounded-2xl border border-base-300 bg-base-100 shadow-sm">
      <!-- SECTION HEADER -->
      <div class="flex items-center gap-3 border-b border-base-300 px-6 py-5">
        <div class="<?= SVG_DIV ?>">
          <svg
            class="<?= SVG_ICON ?>"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.75">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M9 12.75L11.25 15 15 9.75
              M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div>
          <h2 class="text-sm font-bold text-base-content">
            Role Requirements
          </h2>
          <p class="mt-0.5 text-xs text-base-content/50">
            Define the role type and experience required for this position.
          </p>
        </div>
      </div>

      <!-- CONTENT -->
      <div class="p-6">
        <div class="grid w-full grid-cols-1 gap-6 lg:grid-cols-2">
          <!-- JOB TYPE -->
          <fieldset class="fieldset w-full">
            <legend class="fieldset-legend">
              Job Type
              <span class="text-error">*</span>
            </legend>
            <select
              name="job_type"
              id="jobTypeSelect"
              class="<?= SELECT_CLASS ?> h-11 w-full"
              onchange="toggleOther(this, 'otherJobType')"
              required>
              <option value="">
                Select job type
              </option>
            </select>
            <!-- OTHER JOB TYPE -->
            <div
              id="otherJobType"
              class="mt-3 hidden">
              <label
                for="jobTypeOther"
                class="mb-1.5 block text-xs font-medium text-base-content/60">
                Specify job type
              </label>
              <input
                type="text"
                name="job_type_other"
                id="jobTypeOther"
                class="<?= INPUT_CLASS ?> h-11 w-full"
                placeholder="e.g. Contract, Freelance"
                value="<?= old('job_type_other') ?>">
            </div>
          </fieldset>

          <!-- EXPERIENCE -->
          <fieldset class="fieldset w-full">
            <legend class="fieldset-legend">
              Experience
              <span class="text-error">*</span>
            </legend>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] ">
              <!-- MINIMUM -->
              <fieldset class="fieldset">
                <select name="exp_from" id="expFrom" class="<?= SELECT_CLASS ?> h-11 w-full" required onchange="updateExpPreview()">
                  <option value="">
                    Select min years
                  </option>
                  <?php foreach ($expFromOpts as $v): ?>
                    <option
                      value="<?= e($v) ?>"
                      <?= $savedExpFrom === $v ? 'selected' : '' ?>>
                      <?= e($v) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </fieldset>

              <!-- RANGE SEPARATOR -->
              <div class="hidden sm:flex items-center justify-center pb-1">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-base-200 text-base-content/40">
                  <span class="text-sm font-semibold">
                    –
                  </span>
                </div>
              </div>
              <!-- MAXIMUM -->
              <fieldset class="fieldset">
                <select
                  name="exp_to"
                  id="expTo"
                  class="<?= SELECT_CLASS ?> h-11 w-full"
                  required
                  onchange="updateExpPreview()">
                  <option value="">
                    Select max years
                  </option>
                  <?php foreach ($expToOpts as $v): ?>
                    <option
                      value="<?= e($v) ?>"
                      <?= $savedExpTo === $v ? 'selected' : '' ?>>
                      <?= e($v) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </fieldset>
            </div>

            <!-- EXPERIENCE PREVIEW -->
            <div class="mt-4 flex items-center gap-2 rounded-xl border border-primary/20 bg-primary/[.04] px-3.5 py-3">
              <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <svg
                  class="h-4 w-4"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="1.75">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M13 16h-1v-4h-1m1-4h.01
                    M21 12a9 9 0 11-18 0
                    9 9 0 0118 0z" />
                </svg>
              </div>
              <div class="min-w-0">
                <span class="block text-[11px] font-medium uppercase tracking-wide text-base-content/40">
                  Experience Preview
                </span>
                <strong
                  id="expPreview"
                  class="mt-0.5 block break-words text-sm font-semibold text-primary">
                  <?php if ($savedExpFrom !== '' && $savedExpTo !== ''): ?>
                    <?= e($savedExpFrom . ' - ' . $savedExpTo . ' years') ?>
                  <?php else: ?>
                    Select experience range
                  <?php endif; ?>
                </strong>
              </div>
            </div>
          </fieldset>
        </div>
      </div>
    </div>

    <!-- ═══ CARD 4: COMPENSATION ═══════════════════════════════════
       Salary now gets a full-width card of its own instead of sharing a
       2-column grid with two invisible date fields — the old layout left
       half the card visually empty since Open/Close Date are hidden
       inputs, not visible content. -->
    <div class="w-full rounded-2xl border border-base-300 bg-base-100 shadow-sm">
      <!-- SECTION HEADER -->
      <div class="flex items-center justify-between gap-4 border-b border-base-300 px-6 py-5">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
            <svg
              class="h-5 w-5"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="1.75">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659
                -1.106-.879-1.106-2.303 0-3.182s2.9-.879
                4.006 0l.415.33M21 12a9 9 0 11-18 0
                9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <h2 class="text-sm font-bold text-base-content">
              Compensation
            </h2>
            <p class="mt-0.5 text-xs text-base-content/50">
              Define the salary, rate and compensation structure.
            </p>
          </div>
        </div>
        <span class="badge badge-primary badge-soft hidden sm:inline-flex">
          Required
        </span>
      </div>

      <!-- CONTENT -->
      <div class="p-6">
        <!-- SALARY / RATE HEADER -->
        <div class="mb-5 flex items-start justify-between gap-4">
          <div>
            <h3 class="text-sm font-bold text-base-content">
              Salary / Rate
              <span class="text-error">*</span>
            </h3>
            <p class="mt-1 text-xs text-base-content/50">
              Provide a salary range or let compensation be based on experience.
            </p>
          </div>
          <span class="text-[11px] font-medium text-base-content/40">
            Numeric values
          </span>
        </div>

        <!-- BOE OPTION -->
        <label
          for="salaryBoe"
          class="
        group flex cursor-pointer items-center gap-4
        rounded-xl border p-4
        transition-all duration-200
        <?= $savedSalBoe
          ? 'border-success/40 bg-success/[.06]'
          : 'border-base-300 bg-base-200/30 hover:border-primary/30 hover:bg-primary/[.03]' ?>">
          <input
            type="checkbox"
            name="salary_boe"
            id="salaryBoe"
            value="1"
            class="checkbox checkbox-primary checkbox-sm sm:checkbox-md"
            onchange="toggleSalaryBoe()"
            <?= $savedSalBoe ? 'checked' : '' ?>>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-sm font-semibold text-base-content">
                Based on Experience
              </span>
              <span class="badge badge-success badge-sm">
                BOE
              </span>
            </div>
            <p class="mt-1 text-xs leading-relaxed text-base-content/50">
              Salary will be determined based on the candidate's
              experience, skills and qualifications.
            </p>
          </div>
          <svg class="hidden sm:block h-5 w-5 shrink-0<?= $savedSalBoe ? 'text-success' : 'text-base-content/20' ?>"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.75">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M9 12.75l2.25 2.25L15 10.5 M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </label>

        <!-- BOE MESSAGE -->
        <div id="salaryBoeText" class="<?= $savedSalBoe ? '' : 'hidden' ?> mt-3">
          <div class="alert alert-success alert-soft py-3 text-xs">
            <svg
              class="h-4 w-4 shrink-0"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M9 12.75l2.25 2.25L15 10.5
               M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>
              Salary will be based on experience.
            </span>
          </div>
        </div>

        <!-- SALARY RANGE -->
        <div id="salaryInputsWrap" class="<?= $savedSalBoe ? 'hidden' : '' ?> mt-7">
          <div class="mb-4 flex items-center justify-between">
            <div>
              <h4 class="text-sm font-semibold text-base-content">
                Salary Range
              </h4>
              <p class="mt-0.5 text-xs text-base-content/50">
                Enter the expected minimum and maximum compensation.
              </p>
            </div>
            <span class="hidden sm:block text-xs text-base-content/40">
              Optional range
            </span>
          </div>
          <div class="grid w-full grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_40px_minmax(0,1fr)] md:items-end">
            <!-- FROM -->
            <fieldset class="fieldset w-full">

              <legend class="fieldset-legend">
                Minimum
              </legend>
              <div class="flex w-full">
                <input type="number" name="salary_from" id="salaryFrom" class="<?= INPUT_CLASS ?> h-11 min-w-0 flex-1 rounded-r-none"
                  placeholder="35"
                  value="<?= e($savedSalFrom) ?>"
                  min="0"
                  step="any"
                  oninput="updateSalaryPreview()"
                  <?= $savedSalBoe ? '' : 'required' ?>>
                <select
                  name="salary_unit_from"
                  id="salaryUnitFrom"
                  class="<?= SELECT_CLASS ?> h-11 w-[105px] shrink-0 rounded-l-none border-l-0 sm:w-[120px]"
                  onchange="updateSalaryPreview()">
                  <option value="" <?= $savedSalUnitFrom === '' ? 'selected' : '' ?>> — </option>
                  <option value="L" <?= $savedSalUnitFrom === 'L' ? 'selected' : '' ?>> L — Lakhs </option>
                  <option value="K" <?= $savedSalUnitFrom === 'K' ? 'selected' : '' ?>> K — Thousands </option>
                </select>
              </div>
            </fieldset>

            <!-- RANGE SEPARATOR -->
            <div class="hidden md:flex items-center justify-center pb-1">
              <div class="flex h-8 w-8 items-center justify-center rounded-full bg-base-200 text-base-content/40">
                <span class="text-sm font-semibold">–</span>
              </div>
            </div>

            <!-- TO -->
            <fieldset class="fieldset w-full">
              <legend class="fieldset-legend">
                Maximum
              </legend>
              <div class="flex w-full">
                <input
                  type="number"
                  name="salary_to"
                  id="salaryTo"
                  class="<?= INPUT_CLASS ?> h-11 min-w-0 flex-1 rounded-r-none"
                  placeholder="45"
                  value="<?= e($savedSalTo) ?>"
                  min="0"
                  step="any"
                  oninput="updateSalaryPreview()"
                  <?= $savedSalBoe ? '' : 'required' ?>>
                <select
                  name="salary_unit_to"
                  id="salaryUnitTo"
                  class="<?= SELECT_CLASS ?> h-11 w-[105px] shrink-0 rounded-l-none border-l-0 sm:w-[120px]"
                  onchange="updateSalaryPreview()">
                  <option value="" <?= $savedSalUnitTo === '' ? 'selected' : '' ?>> — </option>
                  <option value="L" <?= $savedSalUnitTo === 'L' ? 'selected' : '' ?>> L — Lakhs </option>
                  <option value="K" <?= $savedSalUnitTo === 'K' ? 'selected' : '' ?>> K — Thousands </option>
                </select>
              </div>
            </fieldset>
          </div>
        </div>
        <!--  CURRENCY + TYPE -->
        <div
          id="salaryCurrencyTypeWrap"
          class="mt-7 border-t border-base-300 pt-6">
          <div class="grid w-full grid-cols-1 gap-5 md:grid-cols-2">
            <!-- CURRENCY -->
            <fieldset>
              <legend class="fieldset-legend"
                for="salaryCurrency"
                class="mb-1.5 block text-xs font-semibold text-base-content/70">
                Currency
                <span class="text-error">*</span>
              </legend>
              <select
                name="salary_currency"
                id="salaryCurrency"
                class="<?= SELECT_CLASS ?> h-11 w-full"
                required
                onchange="updateSalaryPreview()">
                <option value=""> Select currency </option>
              </select>
            </fieldset>

            <!-- SALARY TYPE -->
            <fieldset class="fieldset">
              <legend class="fieldset-legend"
                for="salaryType"
                class="mb-1.5 block text-xs font-semibold text-base-content/70">
                Salary Type
                <span class="text-error">*</span>
              </legend>
              <select
                name="salary_type"
                id="salaryType"
                class="<?= SELECT_CLASS ?> h-11 w-full"
                required
                onchange="updateSalaryPreview()">
                <option value=""> Select salary type </option>
                <?php foreach ($salaryTypes as $st): ?>
                  <option
                    value="<?= e($st) ?>"
                    <?= $savedSalType === $st ? 'selected' : '' ?>>
                    <?= e($st) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </fieldset>
          </div>
        </div>

        <!--  LIVE PREVIEW -->
        <div class=" mt-6 flex flex-col gap-2 rounded-xl border border-primary/20 bg-primary/[.04] px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-center gap-2">
            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
              <svg
                class="h-4 w-4"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.75">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M13 16h-1v-4h-1m1-4h.01
                  M21 12a9 9 0 11-18 0
                  9 9 0 0118 0z" />
              </svg>
            </div>
            <span class="text-xs font-medium text-base-content/50">
              Salary Preview
            </span>
          </div>
          <div
            id="salaryPreview"
            class="break-words text-sm font-bold text-primary sm:text-right">
            <?php
            if ($savedSalFrom !== '' || $savedSalTo !== '') {
              $fromDisp = $savedSalFrom !== ''
                ? $savedSalFrom . ($savedSalUnitFrom !== '' ? ' ' . $savedSalUnitFrom : '')
                : '';

              $toDisp = $savedSalTo !== ''
                ? $savedSalTo . ($savedSalUnitTo !== '' ? ' ' . $savedSalUnitTo : '')
                : '';

              $range = ($fromDisp !== '' && $toDisp !== '')
                ? $fromDisp . ' – ' . $toDisp
                : ($fromDisp ?: $toDisp);

              echo e(
                implode(
                  ' | ',
                  array_filter([
                    $range,
                    $savedSalCur,
                    $savedSalType
                  ])
                )
              );
            } else {
              echo '—';
            }
            ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ CARD 5: JOB CONTENT ═════════════════════════════════════ -->
    <div class="rounded-2xl border border-[#e7e9f0] p-6" style="background-color:#ffffff !important">
      <div class="mb-5 flex items-center gap-2.5 border-b border-[#e7e9f0] pb-3.5">
        <div class="<?= SVG_DIV ?>">
          <svg class="<?= SVG_ICON ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6M6.75 21h10.5a2.25 2.25 0 002.25-2.25V6.108c0-.397-.158-.779-.44-1.06L15.94 1.44A1.5 1.5 0 0014.878 1H6.75A2.25 2.25 0 004.5 3.25v15.5A2.25 2.25 0 006.75 21z" />
          </svg>
        </div>
        <span class="text-sm font-bold text-base-content">Job Content</span>
      </div>

      <div class="flex flex-col gap-6">
        <fieldset class=" fieldset flex flex-col gap-1.5">
          <legend class="fieldset-legend">Job Description <span class="text-[#dc3545]">*</span></legend>
          <textarea id="job_description" name="job_description" class="<?= TEXTAREA_CLASS ?>"><?= old("job_description") ?></textarea>
        </fieldset>

        <fieldset class="flex flex-col gap-1.5">
          <legend class=" fieldset-legend">Requirements <span class="text-[#dc3545]">*</span></legend>
          <textarea id="key_skills" name="key_skills" class="<?= TEXTAREA_CLASS ?>"><?= old("key_skills") ?></textarea>
        </fieldset>

        <fieldset class="flex flex-col gap-1.5">
          <legend class=" fieldset-legend">Why Join Us <span class="text-[#dc3545]">*</span></legend>
          <textarea id="our_terms" name="our_terms" class="<?= TEXTAREA_CLASS ?>"><?= old("our_terms") ?></textarea>
        </fieldset>
      </div>
    </div>

    <!-- ═══ ACTIONS ══════════════════════════════════════════════ -->
    <div class="card border border-base-300 bg-base-100">
      <div class="card-body p-5">
        <div class="flex flex-wrap items-center gap-3">
          <!-- Publish -->

          <button type="submit" name="submit_action" value="publish" class="btn btn-sm btn-primary shadow-sm">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M12 19l9 2-9-18-9 18 9-2zm0 0v-5" />
            </svg>
            <?php if ($isClone): ?>
              Publish
            <?php elseif ($isEdit): ?>
              Update &amp; Publish
            <?php else: ?>
              Publish Job
            <?php endif; ?>
          </button>
          <!-- <button type="submit" name="submit_action" value="publish" class="btn btn-primary gap-2">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z" />
            </svg>
            <?php if ($isClone): ?>
              Publish
            <?php elseif ($isEdit): ?>
              Update &amp; Publish
            <?php else: ?>
              Publish Job
            <?php endif; ?>
          </button> -->

          <button type="submit" name="submit_action" value="draft" class="btn btn-sm bg-base-100 border-base-300 hover:bg-base-200">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4"
              fill="currentColor"
              viewBox="0 0 24 24">
              <path d="M5 3a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7.828A2 2 0 0 0 20.414 6.4l-2.8-2.8A2 2 0 0 0 16.172 3H5zm2 2h8v4H7V5zm10 14H7v-6h10v6z" />
            </svg>
            <?= $isClone ? 'Draft' : 'Save as Draft' ?>
          </button>
          <!-- Save Draft -->
          <!-- <button type="submit" name="submit_action" value="draft" class="btn bg-white text-black border-[#e5e5e5]">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
            </svg>
            <?= $isClone ? 'Draft' : 'Save as Draft' ?>
          </button> -->

          <!-- Cancel -->
          <button type="button" class="btn btn-sm bg-base-100 border-base-300 hover:bg-base-200" onclick="window.location.href='<?= ADMIN_URL ?>/pages/jobs.php' ">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Cancel
          </button>
          <!-- <a
            href="<?= ADMIN_URL ?>/pages/jobs.php"
            class="btn btn-ghost gap-2">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </svg>
            Cancel
          </a> -->

          <!-- Delete -->
          <?php if ($isEdit): ?>
            <a href="<?= ADMIN_URL ?>/pages/job_action.php?id=<?= $editId ?>&a=delete" cass="btn btn-error btn-outline ml-auto gap-2" onclick="return confirm('Delete this job permanently?')">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
              </svg>
              Delete Job
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

  </form>

</div>
<!-- /light canvas -->

<!-- ══ Data from PHP → JS ══ -->
<script>
  console.log('Job Form: PHP → JS');
  const COUNTRY_DATA = <?= json_encode($countryData, JSON_UNESCAPED_UNICODE) ?>;
  const IS_EDIT = <?= json_encode($isEdit) ?>; // false for clone — country dropdown stays editable
  const IS_CLONE = <?= json_encode($isClone) ?>;
  const SAVED_COUNTRY = <?= json_encode(old("country", $isEdit || $isClone ? $job["country"] ?? "" : "")) ?>;
  const SAVED_STATE = <?= json_encode(old("state_province", $isEdit || $isClone ? $job["state_province"] ?? "" : "")) ?>;
  const SAVED_TIMEZONE = <?= json_encode(old("timezone", $isEdit || $isClone ? $job["timezone"] ?? "" : "")) ?>;
  const SAVED_JOB_TYPE = <?= json_encode(old("job_type", $isEdit || $isClone ? $job["job_type"] ?? "" : "")) ?>;
  const SAVED_WORKPLACE_TYPE = <?= json_encode(
                                  old("workplace_type", $isEdit || $isClone ? $job["workplace_type"] ?? "" : "")
                                ) ?>;
  const SAVED_SALARY_TYPE = <?= json_encode(old("salary_type", $isEdit || $isClone ? $job["salary_type"] ?? "" : "")) ?>;

  // ── BOE toggle ────────────────────────────────────────────────
  function toggleSalaryBoe() {
    const checked = document.getElementById('salaryBoe').checked;
    document.getElementById('salaryBoeText').style.display = checked ? 'block' : 'none';
    document.getElementById('salaryInputsWrap').style.display = checked ? 'none' : 'block';
    document.getElementById('salaryCurrencyTypeWrap').style.display = checked ? 'none' : 'block';

    // Toggle required on salary inputs so HTML5 validation doesn't fire
    const inputs = document.querySelectorAll(
      '#salaryInputsWrap input[type="number"], #salaryCurrency, #salaryType'
    );
    inputs.forEach(el => {
      if (checked) {
        el.removeAttribute('required');
      } else {
        el.setAttribute('required', 'required');
      }
    });

    // if (isBoe) {
    //   salaryFrom.value = '';
    //   salaryTo.value = '';
    // }

    // updateSalaryPreview();
  }
  // ── Client Code preview ───────────────────────────────────────
  function updateClientCodePreview() {
    const sel = document.getElementById('clientCodePrefix');
    const suffixEl = document.getElementById('clientCodeSuffix');
    const previewEl = document.getElementById('clientCodePreview');
    const nameHintEl = document.getElementById('clientNameHint');

    if (!sel || !suffixEl || !previewEl || !nameHintEl) {
      return; // Elements not found, skip update
    }

    const suffix = suffixEl.value.trim();
    const prefix = sel.value;
    const selOpt = sel.options[sel.selectedIndex];
    const name = selOpt ? (selOpt.dataset.name || '') : '';

    // Preview: "ADSK-12345" or "ADSK"
    const full = prefix ?
      (suffix ? prefix + '-' + suffix : prefix) :
      '—';

    previewEl.textContent = full;
    nameHintEl.textContent = name ? '(' + name + ')' : '';
  }

  function getCountryData(countryName) {
    if (!countryName) return {};
    if (COUNTRY_DATA[countryName]) return COUNTRY_DATA[countryName];

    const target = countryName.trim().toLowerCase();
    const matchKey = Object.keys(COUNTRY_DATA).find(
      k => k.trim().toLowerCase() === target
    );
    return matchKey ? COUNTRY_DATA[matchKey] : {};
  }

  // ── Country change ────────────────────────────────────────────
  function onCountryChange() {
    console.log('onCountryChange()');
    const countryEl = document.getElementById('country');
    if (!countryEl) {
      console.error('Country element not found');
      return;
    }
    const country = countryEl.value;
    const data = getCountryData(country);
    console.log('data:', data);
    const cityInput = document.getElementById('city');
    const stateSelect = document.getElementById('stateSelect');
    const timezoneSelect = document.getElementById('timezoneSelect');
    const jobTypeSelect = document.getElementById('jobTypeSelect');
    const currentySelect = document.getElementById('salaryCurrency');
    console.log('data.types:', data.types);
    // ------------------------------------------------------------
    // City
    // ------------------------------------------------------------
    const savedCity = <?= json_encode(
                        old("city", $isEdit || $isClone ? $job["city"] ?? "" : "")
                      ) ?>;

    if (country === SAVED_COUNTRY) {
      cityInput.value = savedCity;
    } else {
      cityInput.value = '';
    }

    /* State / Province */
    stateSelect.innerHTML = '<option value="">Select state</option>';

    Object.keys(data.states || {}).forEach(state => {
      const option = new Option(state, state);

      if (
        SAVED_STATE &&
        state.trim().toLowerCase() === SAVED_STATE.trim().toLowerCase()
      ) {
        option.selected = true;
      }

      stateSelect.appendChild(option);
    });

    /* Time Zone */
    timezoneSelect.innerHTML =
      '<option value="">Select timezone</option>';

    (data.timezones || []).forEach(timezone => {
      const option = new Option(timezone, timezone);

      if (
        SAVED_TIMEZONE &&
        timezone.trim().toLowerCase() === SAVED_TIMEZONE.trim().toLowerCase()
      ) {
        option.selected = true;
      }

      timezoneSelect.appendChild(option);
    });

    /* Job Type */
    jobTypeSelect.innerHTML =
      '<option value="">Select job type</option>';
    console.log('data.types:', data.types);
    (data.types || []).forEach(type => {
      const option = new Option(type, type);

      if (
        SAVED_JOB_TYPE &&
        type.trim().toLowerCase() === SAVED_JOB_TYPE.trim().toLowerCase()
      ) {
        option.selected = true;
      }

      jobTypeSelect.appendChild(option);
    });

    /* Currency */
    currentySelect.innerHTML = '<option value="">Select currency</option>';
    (data.currency || []).forEach(cur => {
      const option = new Option(cur, cur);
      if (SAVED_SALARY_TYPE && cur.trim().toLowerCase === SAVED_SALARY_TYPE.trim().toLowerCase()) {
        option.selected = true;
      }

      currentySelect.appendChild(option);
    });

    // Handle "Other"
    toggleOther(jobTypeSelect, 'otherJobType');

    // Restore workplace type
    const workplaceType = document.getElementById('workplaceType');

    if (SAVED_WORKPLACE_TYPE) {
      workplaceType.value = SAVED_WORKPLACE_TYPE;
    }

    toggleLocationFields();

    // New jobs only
    if (!IS_EDIT && !IS_CLONE) {
      const ed = tinymce.get('our_terms');

      if (ed) {
        const content = OUR_TERMS_PLACEHOLDERS[country];
        ed.setContent(content || '');
      }
    }
  }
  // ── Other reveal ─────────────────────────────────────────────
  function toggleOther(select, targetId) {
    // const div = document.getElementById(divId);
    // if (div) div.classList.toggle('hidden', sel.value !== 'Other');
    const target = document.getElementById(targetId);
    const input = target?.querySelector('input');

    if (!target) return;

    const isOther = select.value.toLowerCase() === 'other';

    target.classList.toggle('hidden', !isOther);

    if (input) {
      input.required = isOther;

      if (!isOther) {
        input.value = '';
      }
    }
  }

  // ── Experience preview ────────────────────────────────────────
  function updateExpPreview() {
    const from = document.getElementById('expFrom')?.value || '';
    const to = document.getElementById('expTo')?.value || '';
    const preview = document.getElementById('expPreview');
    if (!preview) return;
    preview.textContent =
      from && to ?
      `${from} - ${to} years` :
      'Select experience range';
  }

  // ── Salary preview ────────────────────────────────────────────
  function updateSalaryPreview() {
    const salaryFrom = document.getElementById('salaryFrom');
    const salaryTo = document.getElementById('salaryTo');
    const salaryUnitFrom = document.getElementById('salaryUnitFrom');
    const salaryUnitTo = document.getElementById('salaryUnitTo');
    const salaryCurrency = document.getElementById('salaryCurrency');
    const salaryType = document.getElementById('salaryType');
    const salaryPreview = document.getElementById('salaryPreview');

    if (!salaryFrom || !salaryTo || !salaryUnitFrom || !salaryUnitTo ||
      !salaryCurrency || !salaryType || !salaryPreview) {
      return; // Elements not found, skip update
    }

    const f = salaryFrom.value.trim();
    const t = salaryTo.value.trim();
    const unitF = salaryUnitFrom.value;
    const unitT = salaryUnitTo.value;
    const cur = salaryCurrency.value;
    const typ = salaryType.value;
    const fromStr = f ? (f + (unitF ? ' ' + unitF : '')) : '';
    const toStr = t ? (t + (unitT ? ' ' + unitT : '')) : '';
    const range = (fromStr && toStr) ? fromStr + ' – ' + toStr : (fromStr || toStr || '');
    const parts = [range, cur, typ].filter(Boolean);
    salaryPreview.textContent = parts.length ? parts.join(' | ') : '—';
  }

  // ── Date helpers ──────────────────────────────────────────────
  function toDisplay(ymd) {
    if (!ymd) return '';
    const d = new Date(ymd + 'T00:00:00');
    return d.toLocaleDateString('en-GB', {
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    });
  }

  // Auto-compute close date = open date + 28 days
  function autoFillCloseDate(openYmd) {
    if (!openYmd) {
      document.getElementById('closeDate').value = '';
      const fh = document.getElementById('closeDateFmt');
      if (fh) fh.textContent = '';
      return;
    }
    const d = new Date(openYmd + 'T00:00:00');
    d.setDate(d.getDate() + 28);
    const ymd = d.toISOString().slice(0, 10);
    document.getElementById('closeDate').value = ymd;
    const fh = document.getElementById('closeDateFmt');
    if (fh) fh.textContent = toDisplay(ymd);
  }

  function toggleLocationFields() {
    const typeEl = document.getElementById('workplaceType');
    const city = document.getElementById('city');
    const state = document.getElementById('stateSelect');

    if (!typeEl || !city || !state) {
      return; // Elements not found, skip update
    }

    const type = typeEl.value;

    if (type === 'Remote') {
      city.disabled = true;
      state.disabled = true;

      city.removeAttribute('required');
      state.removeAttribute('required');
    } else {
      city.disabled = false;
      state.disabled = false;

      city.setAttribute('required', 'required');
      state.setAttribute('required', 'required');
    }
  }
  // Close date is readonly — do NOT attach flatpickr to it
  const openDateEl = document.getElementById('openDate');
  if (openDateEl) {
    const openVal = openDateEl.value;
    if (openVal) {
      const openDateFmt = document.getElementById('openDateFmt');
      if (openDateFmt) {
        openDateFmt.textContent = toDisplay(openVal);
      }
      // Only auto-fill close date if it isn't already set (e.g. edit mode preserves existing)
      const closeDateEl = document.getElementById('closeDate');
      if (closeDateEl) {
        const existingClose = closeDateEl.value;
        if (!existingClose) {
          autoFillCloseDate(openVal);
        }
      }
    }
  }

  // // ── On load ───────────────────────────────────────────────────
  function initializeForm() {
    try {
      console.log('Form initialization started');
      console.log('SAVED_COUNTRY:', SAVED_COUNTRY);
      console.log('SAVED_WORKPLACE_TYPE:', SAVED_WORKPLACE_TYPE);

      const countrySelect = document.getElementById('country');
      if (countrySelect) {
        if (SAVED_COUNTRY) {
          countrySelect.value = SAVED_COUNTRY;
          console.log('Set country to:', SAVED_COUNTRY, 'current value:', countrySelect.value);
        }
      } else {
        console.error('Country select element not found!');
      }

      // Set workplace type
      const workplaceTypeSelect = document.getElementById('workplaceType');
      if (workplaceTypeSelect && SAVED_WORKPLACE_TYPE) {
        workplaceTypeSelect.value = SAVED_WORKPLACE_TYPE;
        console.log('Set workplace type to:', SAVED_WORKPLACE_TYPE);
      }

      onCountryChange(); // MUST run after setting country

      toggleLocationFields(); // FIXED

      updateExpPreview();
      updateSalaryPreview();
      updateClientCodePreview();
      toggleSalaryBoe();

      console.log('Form initialization completed');
    } catch (error) {
      console.error('Error during form initialization:', error);
    }
  }
  // Run when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeForm);
  } else {
    // DOM already loaded
    initializeForm();
  }

  document.addEventListener('DOMContentLoaded', initializeForm);
</script>

<script src="https://cdn.tiny.cloud/1/yqey0x686cm618eysfgj0l4o0chy4vr9kthimc8hgtp2bhqn/tinymce/8/tinymce.min.js"
  referrerpolicy="origin" crossorigin="anonymous"></script>
<script>
  document.getElementById('jobForm').addEventListener('submit', function() {
    tinymce.triggerSave();
  });
  // ══════════════════════════════════════════════════════════════════════
  // OUR_TERMS_PLACEHOLDERS
  // Pre-fills the "Why Join Us" TinyMCE editor for NEW posts only.
  // Edit/Clone posts are never affected.
  //
  // ── HOW TO UPDATE ──────────────────────────────────────────────────
  //  • Each key must exactly match a country name in $countryData (PHP).
  //  • Use standard HTML: <p>, <ul>, <li>, <strong> etc.
  //  • To add a new country: add a new key below before the closing };
  //  • To disable for a country: set its value to '' (empty string).
  // ── COUNTRIES CURRENTLY CONFIGURED ────────────────────────────────
  //    India | United States | Canada
  // ══════════════════════════════════════════════════════════════════════
  const OUR_TERMS_PLACEHOLDERS = {

    // ── INDIA ──────────────────────────────────────────────────────────
    'India': `
    <p><strong>Accelon Consulting</strong> is a global workforce solutions partner and AI enablement leader, but above all, we are a people‑first organization. Our dual commitment is simple: advancing careers and enabling organizations to thrive in a connected, intelligent future. We know our professionals bring specialized expertise, strategic insight, and immediate impact to every engagement. You’re not just filling a seat - you’re solving problems, leading change, and driving results. Because of that, we treat every consultant like the professional they are. Our culture is built on respect, transparency, and long‑term growth, offering opportunities to work on high‑impact projects with leading global enterprises. At Accelon, people are supported, achievements are recognized, and careers are built with purpose. To learn more about our culture and what it’s like to be part of our team, visit the <a href="https://www.accelonconsulting.com/life-at-accelon/">Life at Accelon</a> section on our website.</p>
    <ul>
      <li>Free Health Insurance – Comprehensive coverage for you and your dependents.</li>
      <li>Work-Life Balance – Flexible work arrangements and realistic workloads to help you maintain a healthy balance.</li>
      <li>Healthy Work Culture – A collaborative, respectful, and growth-oriented environment.</li>
      <li>Provident Fund (PF) and Gratuity benefits as per statutory norms.</li>
    </ul>
  `,

    // ── UNITED STATES ──────────────────────────────────────────────────
    'United States': `
    <p><strong>Accelon Consulting</strong> is a global workforce solutions partner and AI enablement leader, but above all, we are a people‑first organization. Our dual commitment is simple: advancing careers and enabling organizations to thrive in a connected, intelligent future. We know our professionals bring specialized expertise, strategic insight, and immediate impact to every engagement. You’re not just filling a seat - you’re solving problems, leading change, and driving results. Because of that, we treat every consultant like the professional they are. Our culture is built on respect, transparency, and long‑term growth, offering opportunities to work on high‑impact projects with leading global enterprises. At Accelon, people are supported, achievements are recognized, and careers are built with purpose. To learn more about our culture and what it’s like to be part of our team, visit the <a href="https://www.accelonconsulting.com/life-at-accelon/">Life at Accelon</a> section on our website.</p>
    <ul>
      <li>We offer healthcare plans, which you may opt into at your own expense.</li>
      <li>Sick leave is provided under the state laws applicable to your residence.</li>
      <li>Work–Life Balance — Flexible work arrangements and realistic workloads that support your well‑being.</li>
      <li>Healthy Work Culture — A collaborative, respectful, and growth‑oriented environment where people thrive.</li>
    </ul>
  `,

    // ── CANADA ─────────────────────────────────────────────────────────
    'Canada': `
    <p><strong>Accelon Consulting</strong> is a global workforce solutions partner and AI enablement leader, but above all, we are a people‑first organization. Our dual commitment is simple: advancing careers and enabling organizations to thrive in a connected, intelligent future. We know our professionals bring specialized expertise, strategic insight, and immediate impact to every engagement. You’re not just filling a seat - you’re solving problems, leading change, and driving results. Because of that, we treat every consultant like the professional they are. Our culture is built on respect, transparency, and long‑term growth, offering opportunities to work on high‑impact projects with leading global enterprises. At Accelon, people are supported, achievements are recognized, and careers are built with purpose. To learn more about our culture and what it’s like to be part of our team, visit the <a href="https://www.accelonconsulting.com/life-at-accelon/">Life at Accelon</a> section on our website.</p>
    <ul>
      <li>We offer healthcare plans, which you may opt into at your own expense.</li>
      <li>Sick leave is provided under the province laws applicable to your residence.</li>
      <li>Work–Life Balance — Flexible work arrangements and realistic workloads that support your well‑being.</li>
      <li>Healthy Work Culture — A collaborative, respectful, and growth‑oriented environment where people thrive.</li>
    </ul>
  `,

  }; // ← Add new countries above this line

  tinymce.init({
    selector: '#job_description, #key_skills, #our_terms',
    height: 300,
    menubar: false,
    plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
    toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist | link image media table | removeformat',
    font_family_formats: "Lato=Lato,sans-serif;Tahoma=tahoma,arial,helvetica,sans-serif;Arial=arial,helvetica,sans-serif",
    font_size_formats: "8pt 9pt 10pt 11pt 12pt 14pt 16pt 18pt 24pt 36pt",
    content_style: `
    body {
      font-family: Lato, sans-serif;
      font-size: 10pt;
    }
  `,
    setup: function(editor) {
      editor.on('change', function() {
        editor.save();
      });

      editor.on('init', function() {
        if (IS_EDIT || IS_CLONE) return; // edit/clone → never touch
        if (editor.id !== 'our_terms') return; // only our_terms gets prefilled

        const country = document.getElementById('country').value;
        if (!country) return;

        const content = OUR_TERMS_PLACEHOLDERS[country];
        if (content) editor.setContent(content);
      });
    }
  });
</script>

<?php include dirname(__DIR__) . "/includes/footer.php"; ?>