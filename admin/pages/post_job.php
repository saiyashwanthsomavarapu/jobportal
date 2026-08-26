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
  } elseif (mb_strlen($data["job_title"]) > 150) {
    $errors[] = "Job Title must be 150 characters or fewer.";
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

  /* Range sanity: "From" must not exceed "To" ("15+" counts as 15) */
  if (
    $data["exp_from"] !== "" &&
    $data["exp_to"] !== "" &&
    (int) $data["exp_from"] > (int) $data["exp_to"]
  ) {
    $errors[] = 'Experience "From" cannot be greater than "To".';
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

    /* Range sanity: "From" must not exceed "To" */
    if (
      $data["salary_from"] !== "" &&
      $data["salary_to"] !== "" &&
      (float) $data["salary_from"] > (float) $data["salary_to"]
    ) {
      $errors[] = 'Salary "From" cannot be greater than "To".';
    }
  }

  if (!$data["open_date"]) {
    $errors[] = "Open Date is required.";
  }

  if (!$data["close_date"]) {
    $errors[] = "Close Date is required.";
  }

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
  csrf_verify();

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
    "job_description" => $_POST["our_terms"] ?? "",
    "key_skills" => $_POST["our_terms"] ?? "",
    "our_terms" => $_POST["our_terms"] ?? "",
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
      } else { /* CREATE / CLONE */
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
      error_log("[post_job] save failed: " . $e->getMessage());
      $errors[] = "Something went wrong while saving. Please try again.";
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
      if (
        preg_match(
          '/^([\d.]+)\s*(LPA|K)?\s*[-–]\s*([\d.]+)\s*(LPA|K)?$/i',
          $salaryText,
          $matches
        )
      ) { // Example: 35 LPA - 45 LPA or 80K - 100K
        $savedSalFrom = trim($matches[1]);
        $savedSalUnitFrom = strtoupper(trim($matches[2] ?? ""));
        $savedSalTo = trim($matches[3]);
        $savedSalUnitTo = strtoupper(trim($matches[4] ?? ""));
      } elseif (
        preg_match('/^([\d.]+)\s*(LPA|K)?$/i', $salaryText, $matches)
      ) { // Example: 50K
        $savedSalFrom = trim($matches[1]);
        $savedSalUnitFrom = strtoupper(trim($matches[2] ?? ""));
      } else { // Legacy format
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
  $savedCcSuffix = $job["client_code"] ?? "";
}
/* HEADER */
include dirname(__DIR__) . "/includes/header.php";
?>

<div class="min-w-0 space-y-5">

  <?php if (!empty($errors)): ?>
    <div role="alert" class="alert alert-error alert-soft items-start">
      <svg class="mt-0.5 size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <div class="min-w-0">
        <p class="font-semibold">Please fix the following:</p>
        <ul class="mt-1 list-disc space-y-0.5 pl-4 text-[12.5px]">
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

    <?= csrf_field() ?>

    <?php if ($isClone): ?>
      <!-- Hidden flag so POST handler knows this is a clone submission -->
      <input type="hidden" name="clone_source_id" value="<?= $cloneId ?>">
      <div class="flex items-start gap-3 rounded-2xl border border-warning/25 bg-warning/[.07] px-4 py-3.5">
        <svg class="mt-0.5 size-4.5 shrink-0 text-warning" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
        </svg>
        <div class="min-w-0 text-[13px] leading-relaxed">
          <strong class="text-base-content">Cloning <?= e($job["job_code"]) ?> — <?= e($job["job_title"]) ?></strong><br>
          <span class="text-[12px] text-base-content/60">New job number <strong class="text-success"><?= e($cloneNewCode) ?></strong> has been reserved. All fields are pre-filled from the source — edit as needed, then save.</span>
        </div>
      </div>
    <?php endif; ?>

    <!-- Hidden job-number plumbing (auto-assigned, not a user decision) -->
    <input type="hidden" name="job_code_display" id="jobCodeDisplay"
      value="<?= $isEdit ? e($job["job_code"]) : ($isClone ? e($cloneNewCode) : "") ?>">
    <input type="hidden" name="job_code_prefix" id="jobCodePrefix" value="AC">
    <input type="hidden" name="job_code_number" id="jobCodeNumber"
      value="<?= $isEdit ? e($job["job_code_number"]) : ($isClone ? e($cloneNewNumber) : "") ?>">

    <!-- ═══ CARD 1 · CLIENT & ROLE ═══ -->
    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-card">
      <header class="flex items-center gap-3 border-b border-base-300 px-5 py-3.5">
        <div class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
          <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0" />
          </svg>
        </div>
        <div class="min-w-0">
          <h2 class="text-[13.5px] font-bold text-base-content">Client &amp; Role</h2>
          <p class="text-[11.5px] text-base-content/50">Who is this role for, and what is the position?</p>
        </div>
      </header>

      <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
        <div class="min-w-0">
          <label for="client_id" class="mb-1.5 block text-xs font-semibold text-base-content/70">
            Company or Client name <span class="text-error">*</span>
          </label>
          <select name="client_id" id="client_id" class="<?= SELECT_CLASS ?>" required>
            <option value="">Select client</option>
            <?php foreach ($clientsList as $client): ?>
              <option value="<?= e($client['id']) ?>" <?= oldSel('client_id', $client['id']) ?>>
                <?= e($client['client_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="min-w-0">
          <label for="clientCodeSuffix" class="mb-1.5 block text-xs font-semibold text-base-content/70">
            Client Reference <span class="text-error">*</span>
            <span class="font-normal text-base-content/45">(internal only)</span>
          </label>
          <input
            type="text"
            name="client_code_suffix"
            id="clientCodeSuffix"
            class="<?= INPUT_CLASS ?> validator"
            placeholder="12345"
            maxlength="5"
            pattern="\d{4,5}"
            value="<?= e(preg_replace("/[^0-9]/", "", $savedCcSuffix)) ?>"
            oninput="this.value=this.value.replace(/[^0-9]/g,'')"
            title="4 or 5 digit number"
            required />
          <p class="mt-1.5 text-[11px] leading-snug text-base-content/45">4 or 5 digit client reference.</p>
        </div>

        <div class="min-w-0">
          <label for="jobTitle" class="mb-1.5 block text-xs font-semibold text-base-content/70">
            Job Title <span class="text-error">*</span>
          </label>
          <input
            type="text"
            name="job_title"
            id="jobTitle"
            class="<?= INPUT_CLASS ?> validator"
            placeholder="e.g. Senior Full Stack Developer"
            value="<?= old('job_title') ?>"
            maxlength="150"
            required />
        </div>

        <div class="min-w-0">
          <label for="country" class="mb-1.5 block text-xs font-semibold text-base-content/70">
            Country <span class="text-error">*</span>
            <?php if ($isEdit): ?><span class="font-normal text-base-content/45">(locked)</span><?php endif; ?>
          </label>
          <select name="country" id="country" class="<?= SELECT_CLASS ?>" onchange="onCountryChange()" required
            <?= $isEdit ? 'disabled' : '' ?>>
            <option value="">Select country</option>
            <?php foreach ($countryData as $c => $d): ?>
              <option value="<?= e($c) ?>" <?= oldSel('country', $c) ?>>
                <?= e($c) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($isEdit): ?>
            <input type="hidden" name="country" value="<?= e($job['country'] ?? '') ?>">
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- ═══ CARD 2 · LOCATION & WORK ARRANGEMENT ═══ -->
    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-card">
      <header class="flex items-center gap-3 border-b border-base-300 px-5 py-3.5">
        <div class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
          <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
          </svg>
        </div>
        <div class="min-w-0">
          <h2 class="text-[13.5px] font-bold text-base-content">Location &amp; Work Arrangement</h2>
          <p class="text-[11.5px] text-base-content/50">Where the role is based and how the candidate will work.</p>
        </div>
      </header>

      <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="min-w-0">
          <label for="workplaceType" class="mb-1.5 block text-xs font-semibold text-base-content/70">
            Workplace Type <span class="text-error">*</span>
          </label>
          <select
            name="workplace_type"
            id="workplaceType"
            class="<?= SELECT_CLASS ?>"
            onchange="toggleLocationFields()"
            required>
            <option value="">Select type</option>
            <?php foreach ($workplaceTypes as $wt): ?>
              <option value="<?= e($wt) ?>" <?= oldSel('workplace_type', $wt) ?>>
                <?= e($wt) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="mt-1.5 text-[11px] leading-snug text-base-content/45">Remote hides City &amp; State.</p>
        </div>

        <div class="min-w-0">
          <label for="city" class="mb-1.5 block text-xs font-semibold text-base-content/70">
            City <span class="text-error">*</span>
          </label>
          <input
            type="text"
            name="city"
            id="city"
            class="<?= INPUT_CLASS ?> validator"
            placeholder="e.g. Bangalore"
            value="<?= old('city') ?>"
            required />
        </div>

        <div class="min-w-0">
          <label for="stateSelect" class="mb-1.5 block text-xs font-semibold text-base-content/70">
            State / Province <span class="text-error">*</span>
          </label>
          <select
            name="state_province"
            id="stateSelect"
            class="<?= SELECT_CLASS ?>"
            onchange="onStateChange()"
            required>
            <option value="">Select country first</option>
          </select>
          <p class="mt-1.5 text-[11px] leading-snug text-base-content/45">Time zone is picked automatically.</p>
        </div>

        <div class="min-w-0">
          <label for="timezoneSelect" class="mb-1.5 block text-xs font-semibold text-base-content/70">
            Time Zone <span class="text-error">*</span>
          </label>
          <select
            name="timezone"
            id="timezoneSelect"
            class="<?= SELECT_CLASS ?>"
            required>
            <option value="">Select country first</option>
          </select>
        </div>
      </div>
    </section>

    <!-- ═══ CARD 3 · ROLE REQUIREMENTS ═══ -->
    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-card">
      <header class="flex items-center gap-3 border-b border-base-300 px-5 py-3.5">
        <div class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
          <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="min-w-0">
          <h2 class="text-[13.5px] font-bold text-base-content">Role Requirements</h2>
          <p class="text-[11.5px] text-base-content/50">Employment type and experience range for this position.</p>
        </div>
      </header>

      <div class="grid grid-cols-1 gap-4 p-5 lg:grid-cols-2">
        <!-- Job Type -->
        <div class="min-w-0">
          <label for="jobTypeSelect" class="mb-1.5 block text-xs font-semibold text-base-content/70">
            Job Type <span class="text-error">*</span>
          </label>
          <select
            name="job_type"
            id="jobTypeSelect"
            class="<?= SELECT_CLASS ?>"
            onchange="toggleOther(this, 'otherJobType')"
            required>
            <option value="">Select job type</option>
          </select>
          <div id="otherJobType" class="mt-3 hidden">
            <label for="jobTypeOther" class="mb-1.5 block text-xs font-semibold text-base-content/70">
              Specify job type <span class="text-error">*</span>
            </label>
            <input
              type="text"
              name="job_type_other"
              id="jobTypeOther"
              class="<?= INPUT_CLASS ?>"
              placeholder="e.g. Contract, Freelance"
              value="<?= old('job_type_other') ?>">
          </div>
        </div>

        <!-- Experience -->
        <div class="min-w-0">
          <span class="mb-1.5 block text-xs font-semibold text-base-content/70">
            Experience <span class="text-error">*</span>
          </span>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
            <select name="exp_from" id="expFrom" class="<?= SELECT_CLASS ?>" required onchange="updateExpPreview()">
              <option value="">Min. years</option>
              <?php foreach ($expFromOpts as $v): ?>
                <option value="<?= e($v) ?>" <?= $savedExpFrom === $v ? 'selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>

            <div class="hidden items-center justify-center sm:flex">
              <div class="flex size-7 items-center justify-center rounded-full bg-base-200 text-[12px] font-semibold text-base-content/40">–</div>
            </div>

            <select name="exp_to" id="expTo" class="<?= SELECT_CLASS ?>" required onchange="updateExpPreview()">
              <option value="">Max. years</option>
              <?php foreach ($expToOpts as $v): ?>
                <option value="<?= e($v) ?>" <?= $savedExpTo === $v ? 'selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mt-2 flex items-center justify-between gap-2 rounded-lg bg-base-200/60 px-3 py-2">
            <span class="text-[10.5px] font-semibold uppercase tracking-wide text-base-content/45">Preview</span>
            <strong id="expPreview" class="text-[12.5px] font-bold text-primary">
              <?php if ($savedExpFrom !== '' && $savedExpTo !== ''): ?>
                <?= e($savedExpFrom . ' - ' . $savedExpTo . ' years') ?>
              <?php else: ?>
                Select experience range
              <?php endif; ?>
            </strong>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══ CARD 4 · COMPENSATION ═══ -->
    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-card">
      <header class="flex items-center justify-between gap-3 border-b border-base-300 px-5 py-3.5">
        <div class="flex items-center gap-3">
          <div class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
            <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="min-w-0">
            <h2 class="text-[13.5px] font-bold text-base-content">Compensation</h2>
            <p class="text-[11.5px] text-base-content/50">Salary range, or mark it as based on experience.</p>
          </div>
        </div>
        <span class="badge badge-soft badge-primary badge-sm hidden sm:inline-flex">Required</span>
      </header>

      <div class="space-y-4 p-5">
        <!-- BOE option -->
        <label
          for="salaryBoe"
          class="flex cursor-pointer items-center gap-3.5 rounded-xl border p-3.5 transition-all
          <?= $savedSalBoe
            ? 'border-success/40 bg-success/[.06]'
            : 'border-base-300 bg-base-200/30 hover:border-primary/30 hover:bg-primary/[.03]' ?>">
          <input
            type="checkbox"
            name="salary_boe"
            id="salaryBoe"
            value="1"
            class="toggle toggle-sm toggle-success"
            onchange="toggleSalaryBoe()"
            <?= $savedSalBoe ? 'checked' : '' ?>>
          <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-center gap-2">
              <span class="text-[13px] font-semibold text-base-content">Based on Experience</span>
              <span class="badge badge-success badge-xs font-bold">BOE</span>
            </span>
            <span class="mt-0.5 block text-[11.5px] leading-relaxed text-base-content/50">
              Salary is determined by the candidate's experience, skills and qualifications.
            </span>
          </span>
        </label>

        <!-- BOE message -->
        <div id="salaryBoeText" class="<?= $savedSalBoe ? '' : 'hidden' ?>">
          <div class="alert alert-success alert-soft py-2.5 text-[12px]">
            <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l2.25 2.25L15 10.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Salary will be shown as "Based on Experience".</span>
          </div>
        </div>

        <!-- Salary range -->
        <div id="salaryInputsWrap" class="<?= $savedSalBoe ? 'hidden' : '' ?> space-y-4">
          <div class="grid w-full grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] md:items-end">
            <fieldset class="min-w-0">
              <legend class="mb-1.5 block text-xs font-semibold text-base-content/70">Minimum</legend>
              <div class="flex w-full">
                <input type="number" name="salary_from" id="salaryFrom" class="<?= INPUT_CLASS ?> min-w-0 flex-1 rounded-r-none"
                  placeholder="35"
                  value="<?= e($savedSalFrom) ?>"
                  min="0"
                  step="any"
                  oninput="updateSalaryPreview()"
                  <?= $savedSalBoe ? '' : 'required' ?>>
                <select
                  name="salary_unit_from"
                  id="salaryUnitFrom"
                  class="<?= SELECT_CLASS ?> w-[105px] shrink-0 rounded-l-none border-l-0"
                  onchange="updateSalaryPreview()">
                  <option value="" <?= $savedSalUnitFrom === '' ? 'selected' : '' ?>> — </option>
                  <option value="L" <?= $savedSalUnitFrom === 'L' ? 'selected' : '' ?>> L — Lakhs </option>
                  <option value="K" <?= $savedSalUnitFrom === 'K' ? 'selected' : '' ?>> K — Thousands </option>
                </select>
              </div>
            </fieldset>

            <div class="hidden items-center justify-center md:flex">
              <div class="flex size-7 items-center justify-center rounded-full bg-base-200 text-[12px] font-semibold text-base-content/40">–</div>
            </div>

            <fieldset class="min-w-0">
              <legend class="mb-1.5 block text-xs font-semibold text-base-content/70">Maximum</legend>
              <div class="flex w-full">
                <input type="number" name="salary_to" id="salaryTo" class="<?= INPUT_CLASS ?> min-w-0 flex-1 rounded-r-none"
                  placeholder="45"
                  value="<?= e($savedSalTo) ?>"
                  min="0"
                  step="any"
                  oninput="updateSalaryPreview()"
                  <?= $savedSalBoe ? '' : 'required' ?>>
                <select
                  name="salary_unit_to"
                  id="salaryUnitTo"
                  class="<?= SELECT_CLASS ?> w-[105px] shrink-0 rounded-l-none border-l-0"
                  onchange="updateSalaryPreview()">
                  <option value="" <?= $savedSalUnitTo === '' ? 'selected' : '' ?>> — </option>
                  <option value="L" <?= $savedSalUnitTo === 'L' ? 'selected' : '' ?>> L — Lakhs </option>
                  <option value="K" <?= $savedSalUnitTo === 'K' ? 'selected' : '' ?>> K — Thousands </option>
                </select>
              </div>
            </fieldset>
          </div>

          <!-- Currency + Type -->
          <div class="grid grid-cols-1 gap-4 border-t border-base-300 pt-4 sm:grid-cols-2">
            <fieldset class="min-w-0">
              <legend class="mb-1.5 block text-xs font-semibold text-base-content/70">
                Currency <span class="text-error">*</span>
              </legend>
              <select
                name="salary_currency"
                id="salaryCurrency"
                class="<?= SELECT_CLASS ?>"
                required
                onchange="updateSalaryPreview()">
                <option value="">Select currency</option>
              </select>
            </fieldset>

            <fieldset class="min-w-0">
              <legend class="mb-1.5 block text-xs font-semibold text-base-content/70">
                Salary Type <span class="text-error">*</span>
              </legend>
              <select
                name="salary_type"
                id="salaryType"
                class="<?= SELECT_CLASS ?>"
                required
                onchange="updateSalaryPreview()">
                <option value="">Select salary type</option>
                <?php foreach ($salaryTypes as $st): ?>
                  <option value="<?= e($st) ?>" <?= $savedSalType === $st ? 'selected' : '' ?>>
                    <?= e($st) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </fieldset>
          </div>

          <!-- Live preview -->
          <div class="flex flex-col gap-1.5 rounded-lg bg-base-200/60 px-3.5 py-2.5 sm:flex-row sm:items-center sm:justify-between">
            <span class="text-[10.5px] font-semibold uppercase tracking-wide text-base-content/45">Salary preview</span>
            <div id="salaryPreview" class="break-words text-[12.5px] font-bold text-primary sm:text-right">
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
    </section>

    <!-- ═══ CARD 5 · JOB CONTENT ═══ -->
    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-card">
      <header class="flex items-center gap-3 border-b border-base-300 px-5 py-3.5">
        <div class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
          <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6M6.75 21h10.5a2.25 2.25 0 002.25-2.25V6.108c0-.397-.158-.779-.44-1.06L15.94 1.44A1.5 1.5 0 0014.878 1H6.75A2.25 2.25 0 004.5 3.25v15.5A2.25 2.25 0 006.75 21z" />
          </svg>
        </div>
        <div class="min-w-0">
          <h2 class="text-[13.5px] font-bold text-base-content">Job Content</h2>
          <p class="text-[11.5px] text-base-content/50">What candidates will read on the careers page.</p>
        </div>
      </header>

      <div class="flex flex-col gap-5 p-5">
        <div>
          <label for="job_description" class="mb-1.5 block text-xs font-semibold text-base-content/70">
            Job Description
          </label>
          <textarea id="job_description" name="job_description" class="<?= TEXTAREA_CLASS ?>"><?= old("job_description") ?></textarea>
        </div>

        <div>
          <label for="key_skills" class="mb-1.5 block text-xs font-semibold text-base-content/70">
            Requirements
          </label>
          <textarea id="key_skills" name="key_skills" class="<?= TEXTAREA_CLASS ?>"><?= old("key_skills") ?></textarea>
        </div>

        <div>
          <label for="our_terms" class="mb-1.5 block text-xs font-semibold text-base-content/70">
            Why Join Us <span class="text-error">*</span>
          </label>
          <textarea id="our_terms" name="our_terms" class="<?= TEXTAREA_CLASS ?>"><?= old("our_terms") ?></textarea>
          <p class="mt-1.5 text-[11px] text-base-content/45">Auto-filled per country for new jobs — edit as needed.</p>
        </div>
      </div>
    </section>

    <!-- ═══ STICKY ACTIONS ═══ -->
    <div class="sticky bottom-3 z-30 flex flex-wrap items-center gap-2 rounded-2xl border border-base-300 bg-base-100/95 px-4 py-3 shadow-pop backdrop-blur">
      <span class="mr-auto hidden text-[11.5px] text-base-content/50 sm:block">
        <span class="text-error">*</span> Required fields
      </span>

      <button type="submit" name="submit_action" value="publish" class="btn btn-primary btn-sm h-9 min-h-9 rounded-full px-5 text-[12.5px] font-semibold shadow-pop">
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
        </svg>
        <?php if ($isClone): ?>
          Publish
        <?php elseif ($isEdit): ?>
          Update &amp; Publish
        <?php else: ?>
          Publish Job
        <?php endif; ?>
      </button>

      <button type="submit" name="submit_action" value="draft" class="btn btn-sm h-9 min-h-9 rounded-full border-base-300 bg-base-100 px-5 text-[12.5px] font-semibold hover:bg-base-200">
        <svg class="size-4" fill="currentColor" viewBox="0 0 24 24">
          <path d="M5 3a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7.828A2 2 0 0 0 20.414 6.4l-2.8-2.8A2 2 0 0 0 16.172 3H5zm2 2h8v4H7V5zm10 14H7v-6h10v6z" />
        </svg>
        <?= $isClone ? 'Draft' : 'Save as Draft' ?>
      </button>

      <a href="<?= ADMIN_URL ?>/pages/jobs.php" class="btn btn-sm h-9 min-h-9 rounded-full border-base-300 bg-base-100 px-5 text-[12.5px] font-semibold hover:bg-base-200">
        Cancel
      </a>

      <?php if ($isEdit): ?>
        <button
          type="button"
          onclick="openDeleteJobModal()"
          class="btn btn-sm ml-auto h-9 min-h-9 gap-2 rounded-full border-error/30 bg-transparent px-4 text-[12.5px] font-semibold text-error hover:border-error hover:bg-error hover:text-error-content">
          <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
          </svg>
          Delete Job
        </button>
      <?php endif; ?>
    </div>

    <!-- Delete Job Modal -->
    <dialog id="deleteJobModal" class="modal">
      <div class="modal-box w-[calc(100%-2rem)] max-w-md rounded-3xl border border-base-300/70 bg-base-100 p-0 shadow-xl">

        <div class="flex items-center justify-between gap-3 px-5 pb-1 pt-5">
          <div class="min-w-0">
            <h3 class="font-head text-[16px] font-bold tracking-tight text-base-content">Delete job?</h3>
            <p class="mt-0.5 text-[11.5px] text-base-content/50">This action is permanent.</p>
          </div>
          <button
            type="button"
            onclick="closeDeleteJobModal()"
            class="btn btn-sm btn-circle btn-ghost size-8 min-h-8 shrink-0 text-base-content/50 hover:bg-base-200 hover:text-base-content"
            aria-label="Close">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="px-5 py-5">
          <div class="rounded-2xl border border-error/15 bg-error/5 p-4">
            <p class="text-[13px] leading-relaxed text-base-content">
              This permanently removes the posting <strong class="text-error"><?= e($job["job_code"] ?? "") ?></strong> and all associated information.
            </p>
            <p class="mt-2 text-[11.5px] text-base-content/50">
              Consider closing it instead if you only want to take it offline.
            </p>
          </div>
        </div>

        <div class="modal-action m-0 flex flex-col-reverse gap-2 border-t border-base-200/80 bg-base-200/40 px-5 py-4 sm:flex-row sm:justify-end">
          <button
            type="button"
            onclick="closeDeleteJobModal()"
            class="btn h-10 min-h-10 w-full rounded-full border border-base-300 bg-base-100 px-5 text-sm font-semibold text-base-content/80 hover:bg-base-200 hover:text-base-content sm:w-auto">
            Cancel
          </button>
          <a
            id="confirmDeleteJobBtn"
            href="#"
            class="btn btn-error h-10 min-h-10 w-full rounded-full px-6 text-sm font-semibold sm:w-auto">
            Delete permanently
          </a>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop bg-black/40 backdrop-blur-sm">
        <button type="submit">close</button>
      </form>
    </dialog>

  </form>

</div>

<!-- ══ Data from PHP → JS ══ -->
<script>
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
  const SAVED_CURRENCY = <?= json_encode(old('salary_currency', $isEdit || $isClone ? $job["salary_currency"] ?? "" : "")) ?>;

  function openDeleteJobModal() {
    document.getElementById('confirmDeleteJobBtn').href =
      '<?= ADMIN_URL ?>/pages/job_action.php?id=<?= (int)$editId ?>&a=delete';
    document.getElementById('deleteJobModal').showModal();
  }

  function closeDeleteJobModal() {
    document.getElementById('deleteJobModal')?.close();
  }

  // ── BOE toggle ────────────────────────────────────────────────
  function toggleSalaryBoe() {
    const checked = document.getElementById('salaryBoe').checked;
    document.getElementById('salaryBoeText').style.display = checked ? 'block' : 'none';
    document.getElementById('salaryInputsWrap').style.display = checked ? 'none' : 'block';

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
    const countryEl = document.getElementById('country');
    if (!countryEl) return;

    const country = countryEl.value;
    const data = getCountryData(country);
    const cityInput = document.getElementById('city');
    const stateSelect = document.getElementById('stateSelect');
    const timezoneSelect = document.getElementById('timezoneSelect');
    const jobTypeSelect = document.getElementById('jobTypeSelect');
    const currencySelect = document.getElementById('salaryCurrency');

    // City (restore saved value only for the matching country)
    const savedCity = <?= json_encode(
                        old("city", $isEdit || $isClone ? $job["city"] ?? "" : "")
                      ) ?>;
    cityInput.value = country === SAVED_COUNTRY ? savedCity : '';

    // State / Province
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

    // Time Zone
    timezoneSelect.innerHTML = '<option value="">Select timezone</option>';
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

    // Auto-select timezone when there's only one option (e.g. India → IST)
    if (!SAVED_TIMEZONE && (data.timezones || []).length === 1) {
      timezoneSelect.value = data.timezones[0];
    }

    // Job Type
    jobTypeSelect.innerHTML = '<option value="">Select job type</option>';
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

    // Currency (fixed: case-insensitive comparison now actually runs)
    currencySelect.innerHTML = '<option value="">Select currency</option>';
    (data.currency || []).forEach(cur => {
      const option = new Option(cur, cur);
      if (
        SAVED_CURRENCY &&
        cur.trim().toLowerCase() === SAVED_CURRENCY.trim().toLowerCase()
      ) {
        option.selected = true;
      }
      currencySelect.appendChild(option);
    });
    if (SAVED_CURRENCY) {
      currencySelect.value = SAVED_CURRENCY;
    }

    // Handle "Other"
    toggleOther(jobTypeSelect, 'otherJobType');

    // Restore workplace type
    const workplaceType = document.getElementById('workplaceType');
    if (SAVED_WORKPLACE_TYPE) {
      workplaceType.value = SAVED_WORKPLACE_TYPE;
    }

    toggleLocationFields();

    // Pre-fill "Why Join Us" for new jobs only
    if (!IS_EDIT && !IS_CLONE) {
      const ed = tinymce.get('our_terms');
      if (ed) {
        const content = OUR_TERMS_PLACEHOLDERS[country];
        ed.setContent(content || '');
      }
    }

    // Auto-select timezone from state mapping on new jobs
    if (!SAVED_TIMEZONE) onStateChange();
  }

  // ── State → timezone auto-select ──────────────────────────────
  function onStateChange() {
    const countryEl = document.getElementById('country');
    const stateEl   = document.getElementById('stateSelect');
    const tzEl      = document.getElementById('timezoneSelect');
    if (!countryEl || !stateEl || !tzEl || !stateEl.value) return;

    const data = getCountryData(countryEl.value);
    const mapped = data.states?.[stateEl.value];
    if (mapped && tzEl.querySelector('option[value="' + mapped + '"]')) {
      tzEl.value = mapped;
    }
  }

  // ── Other reveal ─────────────────────────────────────────────
  function toggleOther(select, targetId) {
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
      return;
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

  function toggleLocationFields() {
    const typeEl = document.getElementById('workplaceType');
    const city = document.getElementById('city');
    const state = document.getElementById('stateSelect');

    if (!typeEl || !city || !state) return;

    const isRemote = typeEl.value === 'Remote';

    city.disabled = isRemote;
    state.disabled = isRemote;

    if (isRemote) {
      city.removeAttribute('required');
      state.removeAttribute('required');
    } else {
      city.setAttribute('required', 'required');
      state.setAttribute('required', 'required');
    }
  }

  // ── Init ──────────────────────────────────────────────────────
  function initializeForm() {
    try {
      onCountryChange();

      const countrySelect = document.getElementById('country');
      if (countrySelect && SAVED_COUNTRY) {
        countrySelect.value = SAVED_COUNTRY;
      }

      const workplaceTypeSelect = document.getElementById('workplaceType');
      if (workplaceTypeSelect && SAVED_WORKPLACE_TYPE) {
        workplaceTypeSelect.value = SAVED_WORKPLACE_TYPE;
      }

      const currencySelect = document.getElementById('salaryCurrency');
      if (currencySelect && SAVED_CURRENCY) {
        currencySelect.value = SAVED_CURRENCY;
      }

      // MUST run after setting country
      toggleLocationFields();
      updateExpPreview();
      updateSalaryPreview();
      toggleSalaryBoe();
    } catch (error) {
      console.error('Form initialization failed:', error);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeForm);
  } else {
    initializeForm();
  }

  // Scroll to the first field that fails browser validation
  document.getElementById('jobForm').addEventListener('invalid', function(e) {
    const el = e.target;
    clearTimeout(window.__invalidScroll);
    window.__invalidScroll = setTimeout(function() {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      el.focus({ preventScroll: true });
    }, 60);
  }, true);
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
    'Mexico': `
    <p><strong>Accelon Consulting</strong> is a global workforce solutions partner and AI enablement leader, but above all, we are a people‑first organization. Our dual commitment is simple: advancing careers and enabling organizations to thrive in a connected, intelligent future. We know our professionals bring specialized expertise, strategic insight, and immediate impact to every engagement. You’re not just filling a seat - you’re solving problems, leading change, and driving results. Because of that, we treat every consultant like the professional they are. Our culture is built on respect, transparency, and long‑term growth, offering opportunities to work on high‑impact projects with leading global enterprises. At Accelon, people are supported, achievements are recognized, and careers are built with purpose. To learn more about our culture and what it’s like to be part of our team, visit the <a href="https://www.accelonconsulting.com/life-at-accelon/">Life at Accelon</a> section on our website.</p>
    <ul>
      <li>We offer healthcare plans, which you may opt into at your own expense.</li>
      <li>Sick leave is provided under the province laws applicable to your residence.</li>
      <li>Work–Life Balance — Flexible work arrangements and realistic workloads that support your well‑being.</li>
      <li>Healthy Work Culture — A collaborative, respectful, and growth‑oriented environment where people thrive.</li>
    </ul>`,


  }; // ← Add new countries above this line

  tinymce.init({
    selector: '#job_description, #key_skills, #our_terms',
    height: 300,
    menubar: false,
    plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
    toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist | link image media table | removeformat',
    font_family_formats: "Lato=Lato,sans-serif;Tahoma=tahoma,arial,helvetica,sans-serif;Arial=arial,helvetica,sans-serif",
    font_size_formats: "8pt 9pt 10pt 11pt 12pt 14pt 16pt 18pt 24pt 36pt",
    content_style: 'body { font-family: Lato, sans-serif; font-size: 10pt; }',
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
