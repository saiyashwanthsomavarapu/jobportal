<?php

require_once dirname(__DIR__) . "/auth.php";
require_once dirname(__DIR__) . "/DB/queries.php";
require_once dirname(__DIR__) . "/utils/classes.php";

if (!currentAdminCanWrite()) {
  flash("error", "Read-only users cannot create or edit jobs.");
  redirect(ADMIN_URL . "/pages/jobs.php");
}

/* LOAD JOB FOR EDIT / CLONE */

$editId = (int) ($_GET["edit"] ?? 0);
$cloneId = (int) ($_GET["clone"] ?? 0);

$job = null;

$isEdit = false;
$isClone = false;

$cloneNewCode = "";
$cloneNewNumber = 0;

if ($editId > 0) {
  $job = getJobById($editId);

  if (!$job) {
    flash("error", "Job not found.");
    redirect(ADMIN_URL . "/pages/jobs.php");
  }
  $isEdit = true;
}
/* Clone */ elseif ($cloneId > 0) {
  $job = getJobById($cloneId);

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
  $pageTitle = "Clone Job";
} else {
  $pageTitle = "Create Job";
}

$breadcrumbs = [
  ["Dashboard", ADMIN_URL . "/index.php"],
  ["All Jobs", ADMIN_URL . "/pages/jobs.php"],
  [$isEdit ? "Edit Job" : ($isClone ? "Clone Job" : "Create Job"), null],
];

/* LOAD CLIENTS */
try {
  $clientsList = getJobFormClients();
} catch (Exception $e) {
  $clientsList = [];
}

/* CONFIGURATION */

$countryData = require __DIR__ . "/../config/countries.php";
$countries = $countryData;
$countryFlags = [
  'India' => '🇮🇳',
  'United States' => '🇺🇸',
  'Canada' => '🇨🇦',
  'Mexico' => '🇲🇽',
];

$jobFields = require __DIR__ . "/../config/job-fields.php";

$workplaceTypes = ["Onsite", "Hybrid", "Remote"];

$expFromOpts = array_map("strval", range(0, 10));

$expToOpts = array_merge(array_map("strval", range(4, 15)), ["15+"]);

$salaryTypes = ["Annual", "Monthly", "Hourly", "Yearly"];


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

function richTextHasContent(string $html): bool
{
  $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '&nbsp;'], ' ', $html)));
  return trim($text) !== '';
}

function validateJob(array $data, bool $draftOnly = false): array
{
  $errors = [];

  if (!$data["country"]) {
    $errors[] = "Country is required.";
  }

  if (empty($data['client_id'])) {
    $errors[] = 'Company or Client name is required.';
  }

  if (!$data["client_code"]) {
    $errors[] = "Client Reference is required.";
  } elseif (!preg_match('/^\d{4,5}$/', (string) $data["client_code"])) {
    $errors[] = "Client Reference must be 4 or 5 digits.";
  }

  if (!$data["job_title"]) {
    $errors[] = "Job Title is required.";
  }

  // Drafts intentionally allow incomplete employment, compensation and content fields.
  if ($draftOnly) {
    return $errors;
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

  if (!$data["job_type"]) {
    $errors[] = "Job Type is required.";
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

  if (!richTextHasContent((string) $data['job_description'])) {
    $errors[] = 'Job Description is required.';
  }
  if (!richTextHasContent((string) $data['key_skills'])) {
    $errors[] = 'Required Skills are required.';
  }

  if (!richTextHasContent((string) $data["our_terms"])) {
    $errors[] = "Benefits are required.";
  }

  return $errors;
}

function extractReferenceFields(string $html): array
{
  if (!preg_match('/<!--job-reference:([A-Za-z0-9+\/=]+)-->/s', $html, $match)) return [];
  $decoded = base64_decode($match[1], true);
  if ($decoded === false) return [];
  $fields = json_decode($decoded, true);
  return is_array($fields) ? $fields : [];
}

function stripReferenceFields(string $html): string
{
  return trim((string) preg_replace('/\s*<!--job-reference:[A-Za-z0-9+\/=]+-->\s*/s', '', $html));
}

function attachReferenceFields(string $html, array $fields): string
{
  $clean = stripReferenceFields($html);
  $encoded = base64_encode(json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  return $clean . "\n<!--job-reference:" . $encoded . "-->";
}

// ============================================================
// FORM DATA
// ============================================================

$errors = [];

$referenceMeta = ($isEdit || $isClone) ? extractReferenceFields((string) ($job['our_terms'] ?? '')) : [];
if ($isEdit || $isClone) {
  $job['our_terms'] = stripReferenceFields((string) ($job['our_terms'] ?? ''));
}
$old = $isEdit || $isClone ? array_merge($job, $referenceMeta) : ($_POST ?: []);
if ($isClone && $_SERVER["REQUEST_METHOD"] !== "POST") {
  $old["client_id"] = "";
  $old["client_code"] = "";
}

// ============================================================
// PROCESS FORM
// ============================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $ccSuffix = trim($_POST["client_code_suffix"] ?? "");

  // Digits only
  $ccSuffix = preg_replace("/[^0-9]/", "", $ccSuffix);


  $submitAction = $_POST['submit_action'] ?? 'save';
  $isDraftSubmission = $submitAction === 'draft';

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
    // The job code stays locked while all form dropdowns remain editable.
    "country" => trim($_POST["country"] ?? ($isEdit ? ($job["country"] ?? "") : "")),
    "job_code" => $isEdit ? $job["job_code"] ?? "" : trim($_POST["job_code_display"] ?? ""),
    "job_code_prefix" => $isEdit ? $job["job_code_prefix"] ?? "" : trim($_POST["job_code_prefix"] ?? ""),
    "job_code_number" => $isEdit ? $job["job_code_number"] ?? 0 : (int) ($_POST["job_code_number"] ?? 0),
    "job_title" => trim($_POST["job_title"] ?? ""),
    "city" => trim($_POST["city"] ?? ""),
    "client_code" => $ccSuffix,
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
    "reference_fields" => [
      "visa_sponsorship_available" => isset($_POST["visa_sponsorship_available"]) ? 1 : 0,
      "equal_opportunity_statement" => $_POST["equal_opportunity_statement"] ?? ""
    ],
    "job_description" => $_POST["job_description"] ?? "",
    "key_skills" => $_POST["key_skills"] ?? "",
    "our_terms" => $_POST["our_terms"] ?? "",
    "open_date" => $openDateRaw,
    "close_date" => $autoCloseDate,
    "status" => ($_POST["submit_action"] ?? "") === "publish"
      ? "published"
      : (($_POST["submit_action"] ?? "") === "draft"
        ? "draft"
        : ($isEdit ? ($job["status"] ?? "draft") : "draft")),
    "client_id" => trim($_POST["client_id"] ?? 0),
  ];

  /* Experience */

  $data["experience"] = buildExperience($data["exp_from"], $data["exp_to"]);

  /* Salary */

  if (in_array($data["country"], ["United States", "Canada"], true)) {
    $data["salary_unit_from"] = "";
    $data["salary_unit_to"] = "";
  }

  if ($data["salary_boe"]) {
    $data["salary_rate"] = "Based on Experience";

    $data["salary_from"] = "";
    $data["salary_to"] = "";
  } else {
    $data["salary_rate"] = buildSalaryRate($data);
  }

  /*  Validation */
  $errors = validateJob($data, $isDraftSubmission);

  if (
    !empty($data["client_code"]) &&
    jobClientCodeExistsForOtherJob((string) $data["client_code"], $isEdit ? $editId : 0)
  ) {
    $errors[] = "Client Reference already exists. Please enter a unique value.";
  }

  /* SAVE */

  if (empty($errors)) {
    try {
      $data["our_terms"] = attachReferenceFields($data["our_terms"], $data["reference_fields"]);
      /* Generate job code for New / Clone */
      if (!$isEdit) {
        generateJobCode($isClone, $data);
      }

      /* Unique slug */
      $slug = makeUniqueJobSlug($data["job_title"], $editId);

      /* EDIT */
      if ($isEdit) {
        updateJobById($editId, $jobFields, $data, $slug);

        // Activity log
        logActivity("edit_job", "job", $editId, $data["job_code"]);
        flash("success", "Job updated successfully.");
      } else { /* CREATE / CLONE */
        $newId = createJob($jobFields, $data, $slug, (int) $_SESSION["admin_id"]);

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

          $successMessage = $isDraftSubmission
            ? "Draft saved! Code: <strong>" . e($data["job_code"]) . "</strong>"
            : "Job posted! Code: <strong>" . e($data["job_code"]) . "</strong>";
          flash("success", $successMessage);
        }
      }

      /* Redirect after successful save */
      redirect(ADMIN_URL . "/pages/jobs.php");
    } catch (Exception $e) {
      error_log('Job save failed: ' . $e->getMessage());
      $errors[] = "The job could not be saved. Please try again.";
    }
  }

  // Preserve entered values after validation error.
  $data["our_terms"] = stripReferenceFields((string) ($data["our_terms"] ?? ""));
  $old = array_merge((array) $old, $_POST, $data, $data['reference_fields'] ?? []);
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
$savedSalUnitFrom = "L";
$savedSalUnitTo = "L";
$savedSalBoe = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $savedSalFrom = $_POST["salary_from"] ?? "";
  $savedSalTo = $_POST["salary_to"] ?? "";
  $savedSalType = $_POST["salary_type"] ?? "";
  $savedSalCur = $_POST["salary_currency"] ?? "";
  $savedSalUnitFrom = $_POST["salary_unit_from"] ?? "L";
  $savedSalUnitTo = $_POST["salary_unit_to"] ?? "L";
  $savedSalBoe = isset($_POST["salary_boe"]);
} elseif ($isEdit || $isClone) {
  $savedSalFrom = $job["salary_from"] ?? "";
  $savedSalTo = $job["salary_to"] ?? "";
  $savedSalType = $job["salary_type"] ?? "";
  $savedSalCur = $job["salary_currency"] ?? "";
  $savedSalBoe = !empty($job["salary_boe"]);
  $savedSalUnitFrom = $job["salary_unit_from"] ?? "L";
  $savedSalUnitTo = $job["salary_unit_to"] ?? "L";

  /* Restore salary information from legacy salary_rate */
  if ($savedSalFrom === "" && !empty($job["salary_rate"])) {
    $parts = array_map("trim", explode("|", $job["salary_rate"]));

    if (!empty($parts[0])) {
      $salaryText = $parts[0];
      if (
        preg_match(
          '/^([\d.]+)\s*(LPA|L|K|T)?\s*[-–]\s*([\d.]+)\s*(LPA|L|K|T)?$/i',
          $salaryText,
          $matches
        )
      ) { // Example: 35 LPA - 45 LPA or 80K - 100K
        $savedSalFrom = trim($matches[1]);
        $savedSalUnitFrom = strtoupper(trim($matches[2] ?? ""));
        if ($savedSalUnitFrom === 'LPA') $savedSalUnitFrom = 'L';
        if ($savedSalUnitFrom === 'K') $savedSalUnitFrom = 'T';
        $savedSalTo = trim($matches[3]);
        $savedSalUnitTo = strtoupper(trim($matches[4] ?? ""));
        if ($savedSalUnitTo === 'LPA') $savedSalUnitTo = 'L';
        if ($savedSalUnitTo === 'K') $savedSalUnitTo = 'T';
      } elseif (
        preg_match('/^([\d.]+)\s*(LPA|L|K|T)?$/i', $salaryText, $matches)
      ) { // Example: 50K
        $savedSalFrom = trim($matches[1]);
        $savedSalUnitFrom = strtoupper(trim($matches[2] ?? ""));
        if ($savedSalUnitFrom === 'LPA') $savedSalUnitFrom = 'L';
        if ($savedSalUnitFrom === 'K') $savedSalUnitFrom = 'T';
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
} elseif ($isEdit && !empty($job["client_code"])) {
  // Preserve existing client code.
  // The original code did not actually parse it,
  // so behavior remains unchanged.
  $savedCcSuffix = $job["client_code"] ?? "";
}
/* HEADER */
// $referenceShell = true;
$referenceBodyClass = trim(($referenceBodyClass ?? '') . ' post-job-shell-page');
include dirname(__DIR__) . "/includes/reference-header.php";
?>
<?php
$postJobPageClass = "mr-auto min-w-0 w-full max-w-[768px] space-y-6 font-['Plus_Jakarta_Sans',system-ui,sans-serif] [@media(min-width:821px)]:ml-[264px] [@media(min-width:821px)]:w-[calc(100vw-288px)] [@media(min-width:1056px)]:w-full";
$postJobCardClass = "scroll-mt-4 overflow-hidden rounded-xl  bg-base-100 p-5 shadow-sm";
$postJobHeadingClass = "mb-5 flex items-center gap-2.5 border-b border-base-300 pb-3.5";
$postJobHeadingTextClass = "text-base font-semibold leading-6 text-base-content";
$postJobIconBaseClass = "flex size-7 shrink-0 items-center justify-center rounded-lg";
?>

<style>
  body.post-job-shell-page .form-page-header,
  body.post-job-shell-page .edit-job-page-header {
    min-height: 78px;
    margin-left: 264px;
    width: calc(100vw - 288px);
    max-width: 768px;
    display: flex;
    align-items: center;
  }

  body.post-job-shell-page .edit-job-page-header {
    justify-content: space-between;
    gap: 16px;
  }

  body.post-job-shell-page .form-page-header>div {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 9px;
  }

  body.post-job-shell-page .edit-job-title-block {
    min-width: 0;
  }

  body.post-job-shell-page .edit-job-title-row {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  body.post-job-shell-page .form-page-header a,
  body.post-job-shell-page .form-page-header span {
    color: var(--muted);
    font-size: 12px;
  }

  body.post-job-shell-page .form-page-header h1,
  body.post-job-shell-page .edit-job-page-header h1 {
    margin: 0;
    color: var(--ink);
    font-size: 24px;
    font-weight: 700;
    line-height: 1.2;
  }

  body.post-job-shell-page .edit-job-page-header p {
    margin: 4px 0 0;
    color: var(--muted);
    font-size: 12px;
  }

  body.post-job-shell-page .edit-status-badge {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 0 10px;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: #fff;
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
  }

  body.post-job-shell-page .edit-status-published {
    border-color: color-mix(in srgb, var(--blue) 24%, white);
    background: #e8f1fd;
    color: var(--blue);
  }

  body.post-job-shell-page .edit-status-draft {
    border-color: #ecd49c;
    background: #fff9e8;
    color: #9b6500;
  }

  body.post-job-shell-page .edit-status-closed,
  body.post-job-shell-page .edit-status-archived {
    border-color: var(--line);
    background: #eef2f6;
    color: #637188;
  }

  /* body.post-job-shell-page .archive-job-button {
    height: 38px;
    padding: 0 14px;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: #fff;
    color: var(--ink);
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
  }

  body.post-job-shell-page .archive-job-button:hover {
    background: #f1f5f9;
  } */

  body.post-job-shell-page input[type="number"]::-webkit-outer-spin-button,
  body.post-job-shell-page input[type="number"]::-webkit-inner-spin-button {
    margin: 0;
    -webkit-appearance: none;
  }

  body.post-job-shell-page input[type="number"] {
    appearance: textfield;
    -moz-appearance: textfield;
  }

  @media (min-width: 1056px) {

    body.post-job-shell-page .form-page-header,
    body.post-job-shell-page .edit-job-page-header {
      width: 768px;
    }
  }

  @media (max-width: 820px) {

    body.post-job-shell-page .form-page-header,
    body.post-job-shell-page .edit-job-page-header {
      min-height: 72px;
      margin-left: 0;
      width: auto;
      max-width: none;
      padding: 0 14px 0 64px;
    }

    body.post-job-shell-page .edit-job-page-header {
      align-items: flex-start;
      flex-direction: column;
      justify-content: center;
      gap: 10px;
      padding-top: 12px;
      padding-bottom: 12px;
    }
  }
</style>

<div class="<?= $postJobPageClass ?>">

  <?php if (!empty($errors)): ?>
    <div class="mb-6 flex items-start gap-2.5 rounded-xl border border-error/25 bg-error/10 px-4 py-3.5 text-[13px] font-medium text-error">
      <svg class="h-[18px] w-[18px] flex-shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <div>
        <p class="mb-1 font-semibold text-base-content">Please fix the following:</p>
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
      <div class="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning/10 px-4 py-3.5 text-sm text-base-content">
        <svg class="h-5 w-5 text-warning flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
        </svg>
        <div>
          <strong class="text-base-content">Cloning job <?= e($job["job_code"]) ?> — <?= e($job["job_title"]) ?></strong><br>
          <span class="text-xs text-base-content/60">A new Job Number <strong class="text-success"><?= e($cloneNewCode) ?></strong> has been reserved. All fields are pre-filled from the source — edit as needed, then save.</span>
        </div>
      </div>
    <?php endif; ?>

    <!-- ═══ REFERENCE CARD 1: COUNTRY & BASICS ═════════════════ -->
    <div id="country-basics" class="<?= $postJobCardClass ?> border-t-4 border-primary">
      <div class="<?= $postJobHeadingClass ?>">
        <div class="<?= $postJobIconBaseClass ?> bg-primary/10 text-primary">
          <svg class="<?= SVG_ICON ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <circle cx="12" cy="12" r="9"></circle>
            <path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"></path>
          </svg>
        </div>
        <h2 class="<?= $postJobHeadingTextClass ?>">Country &amp; Basics</h2>
      </div>

      <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <fieldset class="fieldset">
          <legend class="fieldset-legend">Country <span class="text-error">*</span></legend>
          <select name="country" id="country" class="<?= SELECT_CLASS ?> cursor-pointer" onchange="onCountryChange()" required>
            <option value="">Select country</option>
            <?php foreach ($countryData as $c => $d): ?>
              <option value="<?= e($c) ?>" <?= oldSel('country', $c) ?>><?= e(($countryFlags[$c] ?? '') . ' ' . $c) ?></option>
            <?php endforeach; ?>
          </select>
        </fieldset>

        <fieldset class="fieldset">
          <legend class="fieldset-legend">
            Company or Client name
            <span class="text-error">*</span>
          </legend>
          <select name="client_id" id="client_id" class="<?= SELECT_CLASS ?>" onchange="onCountryChange()" required>
            <option value="">Select client name</option>

            <?php foreach ($clientsList as $client): ?>
              <option
                value="<?= e($client['id']) ?>"
                <?= oldSel('client_id', $client['id']) ?>>
                <?= e($client['client_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </fieldset>

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
            value="<?= e(preg_replace("/[^0-9]/", "", $savedCcSuffix)) ?>"
            oninput="this.value=this.value.replace(/[^0-9]/g,'');updateClientCodePreview()"
            pattern="\d{4,5}"
            required
            title="4 or 5 digit number" />

          <label class="label">
            <span class="label-text-alt text-base-content/50">
              Enter a 4 or 5 digit client reference.
            </span>
          </label>
        </fieldset>

        <fieldset class="fieldset ">
          <legend class="fieldset-legend">Job Title <span class="text-error">*</span></legend>
          <input type="text" name="job_title" class="<?= INPUT_CLASS ?>" placeholder="e.g. UX Designer" value="<?= old('job_title') ?>" required>
        </fieldset>
      </div>
    </div>

    <!-- ═══ REFERENCE CARD 2: EMPLOYMENT DETAILS ════════════════ -->
    <div id="employment-details" class="<?= $postJobCardClass ?> border-t-4 border-t-success">
      <div class="<?= $postJobHeadingClass ?>">
        <div class="<?= $postJobIconBaseClass ?> bg-success/10 text-success">
          <svg class="<?= SVG_ICON ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path d="M8 7V5.5A1.5 1.5 0 0 1 9.5 4h5A1.5 1.5 0 0 1 16 5.5V7M5 7h14a1 1 0 0 1 1 1v11H4V8a1 1 0 0 1 1-1Z"></path>
            <path d="M9 11v4M15 11v4"></path>
          </svg>
        </div>
        <h2 class="<?= $postJobHeadingTextClass ?>">Employment Details</h2>
      </div>

      <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <fieldset class="fieldset">
          <legend class="fieldset-legend">Employment Type <span class="text-error">*</span></legend>
          <select name="job_type" id="jobTypeSelect" class="<?= SELECT_CLASS ?> cursor-pointer" onchange="toggleOther(this, 'otherJobType')" required>
            <option value="">Select employment type</option>
            <?php
            $initialCountry = (string) ($old['country'] ?? '');
            foreach (($countryData[$initialCountry]['types'] ?? []) as $employmentType):
            ?>
              <option value="<?= e($employmentType) ?>" <?= oldSel('job_type', $employmentType) ?>><?= e($employmentType) ?></option>
            <?php endforeach; ?>
          </select>
          <div id="otherJobType" class="mt-3 hidden">
            <input type="text" name="job_type_other" id="jobTypeOther" class="<?= INPUT_CLASS ?>" placeholder="Specify employment type" value="<?= old('job_type_other') ?>">
          </div>
        </fieldset>

        <fieldset class="fieldset">
          <legend class="fieldset-legend">Work Mode <span class="text-error">*</span></legend>
          <select name="workplace_type" id="workplaceType" class="<?= SELECT_CLASS ?> cursor-pointer" onchange="toggleLocationFields()" required>
            <option value="">Select work mode</option>
            <?php foreach ($workplaceTypes as $wt): ?><option value="<?= e($wt) ?>" <?= oldSel('workplace_type', $wt) ?>><?= e($wt) ?></option><?php endforeach; ?>
          </select>
        </fieldset>

        <fieldset class="fieldset sm:col-span-2">
          <legend class="fieldset-legend">Experience <span class="text-error">*</span></legend>
          <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <fieldset class="fieldset">
              <!-- <legend class="fieldset-legend text-sm font-semibold text-base-content">Minimum</legend> -->
              <select name="exp_from" id="expFrom" class="<?= SELECT_CLASS ?> cursor-pointer" required>
                <option value="">Select minimum years</option>
                <?php foreach ($expFromOpts as $v): ?>
                  <option value="<?= e($v) ?>" <?= $savedExpFrom === $v ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
              </select>
            </fieldset>

            <fieldset class="fieldset">
              <!-- <legend class="fieldset-legend text-sm font-semibold text-base-content">Maximum</legend> -->
              <select name="exp_to" id="expTo" class="<?= SELECT_CLASS ?> cursor-pointer" required>
                <option value="">Select maximum years</option>
                <?php foreach ($expToOpts as $v): ?>
                  <option value="<?= e($v) ?>" <?= $savedExpTo === $v ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
              </select>
            </fieldset>
          </div>
          <!-- <p id="expPreview" class="label text-xs text-base-content/50"><?= ($savedExpFrom !== '' && $savedExpTo !== '') ? e($savedExpFrom . ' - ' . $savedExpTo . ' years') : 'Select experience range' ?></p> -->
        </fieldset>

        <fieldset class="fieldset">
          <legend class="fieldset-legend">City <span class="text-error">*</span></legend>
          <input type="text" name="city" id="city" class="<?= INPUT_CLASS ?>" placeholder="e.g. Vancouver" value="<?= old('city') ?>">
        </fieldset>
        <fieldset class="fieldset">
          <legend class="fieldset-legend">Province / State <span class="text-error">*</span> </legend>
          <select name="state_province" id="stateSelect" class="<?= SELECT_CLASS ?>" onchange="autoSelectTimezone()">
            <option value="">Select state</option>
          </select>
        </fieldset>


        <fieldset class="fieldset">
          <legend class="fieldset-legend">
            Time Zone <span class="text-error">*</span>
          </legend>
          <select
            name="timezone"
            id="timezoneSelect"
            class="<?= SELECT_CLASS ?>"
            required>
            <option value="">Select timezone</option>
            <option value="Any" <?= oldSel('timezone', 'Any') ?>>Any</option>
          </select>
        </fieldset>
      </div>
    </div>

    <!-- ═══ REFERENCE CARD 3: COMPENSATION ═════════════════════ -->
    <div id="compensation" class="<?= $postJobCardClass ?> border-t-4 border-t-[#fbbf24]">
      <div class="<?= $postJobHeadingClass ?>">
        <div class="<?= $postJobIconBaseClass ?> bg-warning/10 text-warning">
          <svg class="<?= SVG_ICON ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path d="M12 3v18M16 7.5C16 5.6 14.2 4 12 4S8 5.3 8 7s1.3 2.7 4 3.5 4 1.8 4 3.5-1.8 3-4 3-4-1.6-4-3.5"></path>
          </svg>
        </div>
        <h2 class="<?= $postJobHeadingTextClass ?>">Compensation</h2>
      </div>

      <label
        for="salaryBoe"
        class="mb-5 flex cursor-pointer items-center justify-between gap-4 rounded-lg border p-4 transition-colors <?= $savedSalBoe ? 'border-success/40 bg-success/10' : 'border-base-300 bg-base-200/30 hover:border-primary/30 hover:bg-primary/5' ?>">
        <span class="min-w-0">
          <strong class="block text-sm font-semibold text-base-content">Based on Experience</strong>
          <small class="mt-1 block text-xs leading-5 text-base-content/50">Enable when compensation should be discussed based on the candidate profile.</small>
        </span>
        <input
          type="checkbox"
          name="salary_boe"
          id="salaryBoe"
          value="1"
          class="toggle toggle-primary shrink-0"
          onchange="toggleSalaryBoe()"
          <?= $savedSalBoe ? 'checked' : '' ?>>
      </label>

      <div id="salaryBoeText" class="<?= $savedSalBoe ? '' : 'hidden' ?> mb-5">
        <div class="alert alert-success alert-soft py-3 text-xs">
          <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l2.25 2.25L15 10.5 M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>Salary will be based on experience.</span>
        </div>
      </div>

      <div id="salaryInputsWrap" class="<?= $savedSalBoe ? 'hidden' : '' ?> grid grid-cols-1 gap-5 sm:grid-cols-2">
        <fieldset class="fieldset">
          <legend class="fieldset-legend text-sm font-semibold text-base-content">Minimum</legend>
          <div class="join w-full overflow-hidden rounded-lg border border-base-300 bg-base-100 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
            <input type="number" name="salary_from" id="salaryFrom" class="input join-item h-11 min-h-11 min-w-0 flex-1 rounded-none border-0 bg-base-100 px-3 text-sm text-base-content placeholder:text-base-content/35 focus:outline-none focus:ring-0" min="0" step="any" placeholder="35" value="<?= e($savedSalFrom) ?>" oninput="updateSalaryPreview()" <?= $savedSalBoe ? '' : 'required' ?>>
            <select name="salary_unit_from" id="salaryUnitFrom" class="select join-item relative z-10 h-11 min-h-11 !w-[120px] !min-w-[120px] shrink-0 cursor-pointer rounded-none border-y-0 border-r-0 border-l border-base-300 bg-base-100 px-3 !pr-8 text-sm text-base-content focus:border-l focus:border-base-300 focus:outline-none focus:ring-0" onchange="updateSalaryPreview()" title="Salary unit">
              <option value="" <?= $savedSalUnitFrom === '' ? 'selected' : '' ?>>—</option>
              <option value="L" <?= $savedSalUnitFrom === 'L' ? 'selected' : '' ?>>L</option>
            </select>
            <span class="sr-only">L means Lakhs. T means Thousands.</span>
          </div>
        </fieldset>
        <fieldset class="fieldset">
          <legend class="fieldset-legend text-sm font-semibold text-base-content">Maximum</legend>
          <div class="join w-full overflow-hidden rounded-lg border border-base-300 bg-base-100 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
            <input type="number" name="salary_to" id="salaryTo" class="input join-item h-11 min-h-11 min-w-0 flex-1 rounded-none border-0 bg-base-100 px-3 text-sm text-base-content placeholder:text-base-content/35 focus:outline-none focus:ring-0" placeholder="45"
              value="<?= e(preg_replace("/[^0-9]/", "", $savedSalTo)) ?>"
              oninput="this.value=this.value.replace(/[^0-9]/g,'');updateSalaryPreview()"
              <?= $savedSalBoe ? '' : 'required' ?>>
            <select name="salary_unit_to" id="salaryUnitTo" class="select join-item relative z-10 h-11 min-h-11 !w-[120px] !min-w-[120px] shrink-0 cursor-pointer rounded-none border-y-0 border-r-0 border-l border-base-300 bg-base-100 px-3 !pr-8 text-sm text-base-content focus:border-l focus:border-base-300 focus:outline-none focus:ring-0" onchange="updateSalaryPreview()" title="Salary unit">
              <option value="" <?= $savedSalUnitTo === '' ? 'selected' : '' ?>>—</option>
              <option value="L" <?= $savedSalUnitTo === 'L' ? 'selected' : '' ?>>L</option>
            </select>
            <span class="sr-only">L means Lakhs. T means Thousands.</span>
          </div>
        </fieldset>
        <fieldset class="fieldset sm:col-span-2">
          <legend class="fieldset-legend">Currency</legend>
          <?php
          $countryCurrency = (string) (($countryData[(string) ($old['country'] ?? '')]['currency'][0] ?? ''));
          $defaultCurrency = $savedSalCur !== '' ? $savedSalCur : $countryCurrency;
          ?>
          <input type="text" id="salaryCurrencyDisplay" class="<?= INPUT_CLASS ?> cursor-default bg-base-200 font-semibold text-base-content/80" value="<?= e($defaultCurrency) ?>" placeholder="Select country first" readonly tabindex="-1">
          <input type="hidden" name="salary_currency" id="salaryCurrency" value="<?= e($defaultCurrency) ?>">
        </fieldset>
      </div>
      <div id="salaryCurrencyTypeWrap" class="<?= $savedSalBoe ? 'hidden' : '' ?> mt-5 grid grid-cols-1 gap-5">
        <fieldset class="fieldset">
          <legend class="fieldset-legend">Pay Type <span class="text-error">*</span></legend>
          <select name="salary_type" id="salaryType" class="<?= SELECT_CLASS ?> cursor-pointer" required onchange="updateSalaryPreview()">
            <?php foreach ($salaryTypes as $st): ?>
              <option value="<?= e($st) ?>" <?= ($savedSalType ?: 'Annual') === $st ? 'selected' : '' ?>><?= e($st) ?></option>
            <?php endforeach; ?>
          </select>
        </fieldset>
      </div>
    </div>


    <!-- ═══ CARD 5: JOB CONTENT ═════════════════════════════════════ -->
    <div id="job-description-section" class="<?= $postJobCardClass ?> border-t-4 border-t-[#e7e9f0]">
      <div class="<?= $postJobHeadingClass ?>">
        <div class="<?= $postJobIconBaseClass ?> bg-secondary/10 text-secondary">
          <svg class="<?= SVG_ICON ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6M6.75 21h10.5a2.25 2.25 0 002.25-2.25V6.108c0-.397-.158-.779-.44-1.06L15.94 1.44A1.5 1.5 0 0014.878 1H6.75A2.25 2.25 0 004.5 3.25v15.5A2.25 2.25 0 006.75 21z" />
          </svg>
        </div>
        <span class="<?= $postJobHeadingTextClass ?>">Job Description</span>
      </div>

      <div class="flex flex-col gap-6">
        <fieldset class="fieldset flex flex-col gap-1.5">
          <legend class="fieldset-legend">Description <span class="text-error">*</span></legend>
          <textarea id="job_description" name="job_description" class="<?= TEXTAREA_CLASS ?>"><?= old("job_description") ?></textarea>
        </fieldset>


        <fieldset class="fieldset flex flex-col gap-1.5">
          <legend class="fieldset-legend">Required Skills <span class="text-error">*</span></legend>
          <textarea id="key_skills" name="key_skills" class="<?= TEXTAREA_CLASS ?>"><?= old("key_skills") ?></textarea>
        </fieldset>

        <fieldset class="fieldset flex flex-col gap-1.5">
          <legend class="fieldset-legend">Benefits <span class="text-error">*</span></legend>
          <textarea id="our_terms" name="our_terms" class="<?= TEXTAREA_CLASS ?>"><?= old("our_terms") ?></textarea>
        </fieldset>
      </div>
    </div>

    <!-- <div id="unitedStatesSpecificCard" class="<?= (($old['country'] ?? '') === 'United States') ? '' : 'hidden' ?> <?= $postJobCardClass ?> border-t-4 border-t-error">
      <div class="<?= $postJobHeadingClass ?>">
        <div class="<?= $postJobIconBaseClass ?> bg-error/10 text-error">
          <svg class="<?= SVG_ICON ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path d="M6 21V4m0 1c4-3 8 3 12 0v9c-4 3-8-3-12 0" />
          </svg>
        </div>
        <h2 class="<?= $postJobHeadingTextClass ?>">United States–Specific Fields</h2>
      </div>

      <section class="space-y-4">
        <label class="flex items-center justify-between gap-4 rounded-lg border border-base-300 p-4">
          <span><strong class="block text-sm">Visa Sponsorship Available</strong><small class="text-base-content/50">Enable when sponsorship can be offered.</small></span>
          <input type="checkbox" name="visa_sponsorship_available" value="1" class="toggle toggle-primary" <?= !empty($old['visa_sponsorship_available']) ? 'checked' : '' ?>>
        </label>
        <fieldset class="fieldset">
          <legend class="fieldset-legend">Equal Opportunity Statement</legend><textarea name="equal_opportunity_statement" rows="4" class="<?= TEXTAREA_CLASS ?>"><?= old('equal_opportunity_statement') ?></textarea>
        </fieldset>
      </section>
    </div> -->

    <!-- ═══ ACTIONS ══════════════════════════════════════════════ -->
    <div class="bg-transparent">
      <div class="p-0">
        <div class="<?= $isEdit ? 'block' : 'grid grid-cols-1 gap-3 sm:grid-cols-2' ?>">
          <?php if ($isEdit): ?>
            <button type="submit" name="submit_action" value="save" id="saveJobButton" class="job-action-button btn btn-primary btn-disabled h-[46px] min-h-[46px] w-full rounded-lg text-base font-medium" disabled>Save Changes</button>
          <?php else: ?>
            <!-- Publish -->

            <button type="submit" name="submit_action" value="publish" id="publishJobButton" class="job-action-button btn btn-primary btn-disabled h-11 min-h-11 w-full rounded-lg shadow-sm sm:col-span-2" disabled>
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

            <button type="submit" name="submit_action" value="draft" id="draftJobButton" formnovalidate class="job-action-button btn btn-disabled h-10 min-h-10 w-full rounded-lg border-base-300 bg-base-100 hover:bg-base-200" disabled>
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-4 w-4"
                fill="currentColor"
                viewBox="0 0 24 24">
                <path d="M5 3a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7.828A2 2 0 0 0 20.414 6.4l-2.8-2.8A2 2 0 0 0 16.172 3H5zm2 2h8v4H7V5zm10 14H7v-6h10v6z" />
              </svg>
              <?= $isClone ? 'Draft' : 'Save as Draft' ?>
            </button>


            <!-- Cancel -->
            <button type="button" class="btn h-10 min-h-10 w-full rounded-lg border-base-300 bg-base-100 hover:bg-base-200" onclick="window.location.href='<?= ADMIN_URL ?>/pages/jobs.php' ">
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


          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Delete Job Modal -->
    <dialog id="deleteJobModal" class="modal">
      <div class="modal-box w-[calc(100%-2rem)] max-w-md rounded-xl border border-base-300 bg-base-100 p-0 shadow-xl">

        <!-- Header -->
        <div class="flex items-center gap-4 border-b border-base-200 px-5 py-5 sm:px-6">
          <div class="<?= SVG_DIV_ERROR ?>">
            <svg class="<?= SVG_ICON ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
            </svg>
          </div>
          <div class="min-w-0 flex-1">
            <h3 class="<?= MODAL_HEADING ?>">
              Delete job
            </h3>
          </div>
          <button
            type="button"
            onclick="closeDeleteJobModal()"
            class="btn btn-sm btn-circle btn-ghost size-8 min-h-8 shrink-0 text-base-content/50"
            aria-label="Close">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Body -->
        <div class="px-6 py-5">
          <div class="rounded-xl  p-4">
            <div class="flex items-start gap-3">
              <div class="flex-1">
                <p class="text-sm font-medium text-base-content">
                  Are you sure you want to permanently delete this job?
                </p>
                <p class="mt-2 text-xs leading-5 text-base-content/60">
                  All associated job information will be removed.
                </p>
              </div>
            </div>
          </div>
        </div>
        <!-- Footer -->
        <div class="modal-action m-0 flex flex-col-reverse gap-2 border-t border-base-200 bg-base-200/30 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
          <button
            type="button"
            onclick="closeDeleteJobModal()"
            class="btn btn-ghost h-10 min-h-10 w-full rounded-lg px-5 text-sm font-semibold sm:w-auto">
            Cancel
          </button>
          <input type="hidden" name="id" value="<?= (int)$editId ?>" form="jobForm">
          <button
            id="confirmDeleteJobBtn"
            type="submit"
            name="a"
            value="delete"
            form="jobForm"
            formaction="<?= ADMIN_URL ?>/pages/job_action.php"
            formmethod="POST"
            class="btn btn-error h-10 min-h-10 w-full rounded-lg px-5 text-sm font-semibold text-error-content sm:w-auto">
            <svg
              class="size-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2"
              aria-hidden="true">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6V9m-5-5h4a1 1 0 011 1v2H9V5a1 1 0 011-1z" />
            </svg>
            Delete permanently
          </button>
        </div>
      </div>
      <!-- Backdrop -->
      <form method="dialog" class="modal-backdrop bg-black/40">
        <button type="submit">close</button>
      </form>
    </dialog>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

  </form>

  <dialog id="unsavedJobChangesModal" class="modal">
    <div class="modal-box w-[calc(100%-2rem)] max-w-md rounded-box border border-base-300 bg-base-100 p-0 shadow-xl">
      <div class="flex items-start gap-4 border-b border-base-200 px-5 py-5 sm:px-6">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-warning/10 text-warning">
          <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12V16.5z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 3.75 2.625 18.75A1.5 1.5 0 0 0 3.925 21h16.15a1.5 1.5 0 0 0 1.3-2.25L12.75 3.75a.866.866 0 0 0-1.5 0z" />
          </svg>
        </div>
        <div class="min-w-0">
          <h3 class="<?= MODAL_HEADING ?>">Unsaved changes</h3>
          <p class="mt-1 text-sm leading-5 text-base-content/60">
            Your changes are not saved. If you leave now, the information you entered will be lost.
          </p>
        </div>
      </div>
      <div class="modal-action m-0 flex flex-col-reverse gap-2 border-t border-base-200 bg-base-200/30 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
        <button
          type="button"
          id="stayOnJobForm"
          class="btn btn-ghost h-10 min-h-10 w-full rounded-lg px-5 text-sm font-semibold sm:w-auto">
          Stay on page
        </button>
        <button
          type="button"
          id="leaveJobForm"
          class="btn btn-warning h-10 min-h-10 w-full rounded-lg px-5 text-sm font-semibold sm:w-auto">
          Leave without saving
        </button>
      </div>
    </div>
    <form method="dialog" class="modal-backdrop bg-black/40">
      <button type="submit">close</button>
    </form>
  </dialog>

</div>

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
  const SALARY_UNITS = <?= json_encode(old('salary_currency', $isEdit || $isClone ? $job["salary_currency"] ?? "" : "")) ?>;

  console.log("data::", <?= json_encode($job) ?>);

  function openDeleteJobModal() {
    const modal = document.getElementById('deleteJobModal');
    modal.showModal();
  }

  function closeDeleteJobModal() {
    document.getElementById('deleteJobModal')?.close();
  }
  // ── BOE toggle ────────────────────────────────────────────────
  function toggleSalaryBoe() {
    const salaryBoe = document.getElementById('salaryBoe');
    if (!salaryBoe) return;
    const checked = salaryBoe.checked;
    document.getElementById('salaryBoeText')?.classList.toggle('hidden', !checked);
    document.getElementById('salaryInputsWrap')?.classList.toggle('hidden', checked);
    document.getElementById('salaryCurrencyTypeWrap')?.classList.toggle('hidden', checked);

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

  function toggleSalaryUnitFields(countryName = '') {
    const country = countryName || document.getElementById('country')?.value || '';
    const shouldDisableUnits = country === 'United States' || country === 'Canada';
    const unitSelects = [
      document.getElementById('salaryUnitFrom'),
      document.getElementById('salaryUnitTo')
    ];

    unitSelects.forEach(select => {
      if (!select) return;
      select.disabled = shouldDisableUnits;
      select.classList.toggle('bg-base-200', shouldDisableUnits);
      select.classList.toggle('text-base-content/50', shouldDisableUnits);
      select.classList.toggle('cursor-not-allowed', shouldDisableUnits);
      select.classList.toggle('cursor-pointer', !shouldDisableUnits);

      if (shouldDisableUnits) {
        select.value = '';
      }
    });

    updateSalaryPreview();
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

  function setTimezoneValue(timezone) {
    const timezoneSelect = document.getElementById('timezoneSelect');
    if (!timezoneSelect || !timezone) return;

    const normalizedTimezone = String(timezone).trim();
    const existingOption = [...timezoneSelect.options].find(option =>
      option.value.trim().toLowerCase() === normalizedTimezone.toLowerCase()
    );

    if (!existingOption) {
      timezoneSelect.appendChild(new Option(normalizedTimezone, normalizedTimezone));
    }

    timezoneSelect.value = existingOption ? existingOption.value : normalizedTimezone;
  }

  function autoSelectTimezone() {
    const country = document.getElementById('country')?.value || '';
    const state = document.getElementById('stateSelect')?.value || '';
    const timezoneSelect = document.getElementById('timezoneSelect');
    const data = getCountryData(country);
    const timezones = data.timezones || [];

    if (!timezoneSelect) return;
    if (timezoneSelect.value === 'Any') return;

    if (timezones.length === 1) {
      setTimezoneValue(timezones[0]);
      return;
    }

    const stateTimezone = state && data.states ? data.states[state] : '';
    if (stateTimezone) {
      setTimezoneValue(stateTimezone);
      return;
    }

    if (country !== SAVED_COUNTRY) {
      timezoneSelect.value = '';
    }
  }

  // ── Country change ────────────────────────────────────────────
  function onCountryChange() {
    console.log("onCountryChange");
    const countryEl = document.getElementById('country');
    if (!countryEl) {
      console.error('Country element not found');
      return;
    }
    const country = countryEl.value;
    const data = getCountryData(country);
    const cityInput = document.getElementById('city');
    const stateSelect = document.getElementById('stateSelect');
    const timezoneSelect = document.getElementById('timezoneSelect');
    const jobTypeSelect = document.getElementById('jobTypeSelect');
    const currentySelect = document.getElementById('salaryCurrency');
    const currencyDisplay = document.getElementById('salaryCurrencyDisplay');
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
    if (timezoneSelect) {
      timezoneSelect.innerHTML = '<option value="">Select timezone</option><option value="Any">Any</option>';
      if (
        SAVED_TIMEZONE &&
        SAVED_TIMEZONE.trim().toLowerCase() === 'any' &&
        country === SAVED_COUNTRY
      ) {
        timezoneSelect.value = 'Any';
      }

      (data.timezones || []).forEach(timezone => {
        if (String(timezone).trim().toLowerCase() === 'any') return;
        const option = new Option(timezone, timezone);

        if (
          SAVED_TIMEZONE &&
          timezone.trim().toLowerCase() === SAVED_TIMEZONE.trim().toLowerCase()
        ) {
          option.selected = true;
        }

        timezoneSelect.appendChild(option);
      });

      autoSelectTimezone();
    }

    /* Job Type */
    jobTypeSelect.innerHTML =
      '<option value="">Select employment type</option>';

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
    const defaultCurrency = (data.currency || [])[0] || '';
    if (currentySelect) currentySelect.value = defaultCurrency;
    if (currencyDisplay) currencyDisplay.value = defaultCurrency;
    toggleSalaryUnitFields(country);

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
      const content = OUR_TERMS_PLACEHOLDERS[country];
      setRichTextContent('our_terms', content || '');
    }
    toggleReferenceCountryFields(country);
  }

  function toggleReferenceCountryFields(country) {
    const card = document.getElementById('unitedStatesSpecificCard');
    if (!card) return;

    const showUnitedStatesFields = country === 'United States';
    card.classList.toggle('hidden', !showUnitedStatesFields);
    card.querySelectorAll('input, textarea, select').forEach(control => {
      control.disabled = !showUnitedStatesFields;
    });
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
  // function updateExpPreview() {
  //   const from = document.getElementById('expFrom')?.value || '';
  //   const to = document.getElementById('expTo')?.value || '';
  //   const preview = document.getElementById('expPreview');
  //   if (!preview) return;
  //   preview.textContent =
  //     from && to ?
  //     `${from} - ${to} years` :
  //     'Select experience range';
  // }

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

  function updateClientCodePreview() {
    if (typeof updateJobActionState === 'function') {
      updateJobActionState();
    }
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
      city.value = '';
      state.value = '';
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
      console.log("SAVED_CURRENCY:", SALARY_UNITS);
      onCountryChange();
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

      const currentySelect = document.getElementById('salaryCurrency');
      if (currentySelect && SALARY_UNITS) {
        if (SALARY_UNITS) {
          currentySelect.value = SALARY_UNITS;
          const currencyDisplay = document.getElementById('salaryCurrencyDisplay');
          if (currencyDisplay) currencyDisplay.value = SALARY_UNITS;
          console.log('Set currency to:', SALARY_UNITS, 'current value:', currentySelect.value);
        }
      }

      // MUST run after setting country

      toggleLocationFields(); // FIXED
      toggleSalaryUnitFields(countrySelect?.value || '');

      // updateExpPreview();
      // updateSalaryPreview();
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
</script>

<script>
  // ══════════════════════════════════════════════════════════════════════
  // OUR_TERMS_PLACEHOLDERS
  // Pre-fills the "Why Join Us" rich-text editor for NEW posts only.
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

  if (window.tinymce) tinymce.init({
    selector: '#job_description, #key_skills, #our_terms',
    height: 300,
    menubar: false,
    plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
    toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist | link image media table | removeformat',
    font_family_formats: "Lato=Lato,sans-serif;Tahoma=tahoma,arial,helvetica,sans-serif;Arial=arial,helvetica,sans-serif",
    font_size_formats: "8pt 9pt 10pt 11pt 12pt 14pt 16pt 18pt 24pt 36pt",
    content_style: `
      body {
        font - family: Lato, sans - serif;
        font - size: 10 pt;
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

  const richTextEditors = {};

  function setRichTextContent(id, html) {
    const textarea = document.getElementById(id);
    if (textarea) textarea.value = html || '';
    const editor = richTextEditors[id];
    if (editor) {
      // setContents updates the document without changing Quill's selection.
      // dangerouslyPasteHTML also calls setSelection internally, which was
      // pulling the viewport down to the Benefits editor.
      const content = editor.clipboard.convert(html || '');
      editor.setContents(content, 'silent');
    }
  }

  function syncRichTextEditors() {
    Object.entries(richTextEditors).forEach(([id, editor]) => {
      const textarea = document.getElementById(id);
      if (textarea) textarea.value = editor.root.innerHTML;
    });
  }

  function initializeRichTextEditors() {
    if (typeof Quill === 'undefined') return;
    ['job_description', 'key_skills', 'our_terms'].forEach(id => {
      const textarea = document.getElementById(id);
      if (!textarea || richTextEditors[id]) return;
      const editorHost = document.createElement('div');
      editorHost.className = 'rich-text-editor';
      textarea.insertAdjacentElement('beforebegin', editorHost);
      textarea.hidden = true;
      const editor = new Quill(editorHost, {
        theme: 'snow',
        placeholder: id === 'job_description' ?
          'Describe the role...' : (id === 'key_skills' ? 'Add required skills...' : 'Add formatted content...'),
        modules: {
          toolbar: [
            [{
              header: [1, 2, 3, false]
            }],
            ['bold', 'italic', 'underline'],
            [{
              list: 'ordered'
            }, {
              list: 'bullet'
            }],
            ['link', 'clean']
          ]
        }
      });
      const initialContent = editor.clipboard.convert(textarea.value || '');
      editor.setContents(initialContent, 'silent');
      const toolbar = editorHost.previousElementSibling;
      if (toolbar?.classList.contains('ql-toolbar')) {
        toolbar.classList.add('!rounded-t-md', '!border-base-300', '!bg-base-200');
      }
      editorHost.classList.add('!min-h-[210px]', '!rounded-b-md', '!border-base-300', '!bg-base-100', 'focus-within:!border-primary', 'focus-within:!ring-2', 'focus-within:!ring-primary/20');
      editor.root.classList.add('!min-h-[210px]', '!text-sm', '!leading-6', '!text-base-content');
      editor.on('text-change', () => {
        textarea.value = editor.root.innerHTML;
        updateJobActionState();
      });
      richTextEditors[id] = editor;
    });
    if (!IS_EDIT && !IS_CLONE) {
      const country = document.getElementById('country')?.value;
      if (country && OUR_TERMS_PLACEHOLDERS[country]) setRichTextContent('our_terms', OUR_TERMS_PLACEHOLDERS[country]);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeRichTextEditors);
  } else {
    initializeRichTextEditors();
  }
  const jobForm = document.getElementById('jobForm');

  jobForm?.addEventListener('submit', event => {
    // Always copy the editor contents into their textareas before saving. This
    // also preserves partially completed requirements when saving a draft.
    syncRichTextEditors();

    if (event.submitter?.value !== 'publish') return;

    const requiredEditors = [{
        id: 'job_description',
        label: 'Job Description'
      },
      {
        id: 'key_skills',
        label: 'Required Skills'
      },
      {
        id: 'our_terms',
        label: 'Benefits'
      }
    ];
    const missingEditors = [];

    requiredEditors.forEach(field => {
      const editor = richTextEditors[field.id];
      const editorContainer = editor?.root.closest('.ql-container');
      const hasContent = editor ?
        editor.getText().trim().length > 0 :
        document.getElementById(field.id)?.value.trim().length > 0;

      editorContainer?.classList.toggle('!border-error', !hasContent);
      editorContainer?.classList.toggle('!ring-1', !hasContent);
      editorContainer?.classList.toggle('!ring-error', !hasContent);
      editor?.root.setAttribute('aria-invalid', hasContent ? 'false' : 'true');
      if (!hasContent) missingEditors.push(field);
    });

    if (!missingEditors.length) return;

    event.preventDefault();
    const message = `Please complete: ${missingEditors.map(field => field.label).join(', ')}.`;
    let notice = document.getElementById('publishValidationNotice');

    if (!notice) {
      notice = document.createElement('div');
      notice.id = 'publishValidationNotice';
      notice.className = 'sticky top-3 z-30 rounded-xl border border-error/30 bg-error/10 px-4 py-3 text-[13px] font-semibold text-error shadow-sm';
      notice.setAttribute('role', 'alert');
      jobForm.prepend(notice);
    }

    notice.textContent = message;
    notice.hidden = false;

    const firstEditor = richTextEditors[missingEditors[0].id];
    firstEditor?.root.closest('#job-description-section')?.scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    });
    firstEditor?.focus();
  });

  let initialJobFormSnapshot = '';
  let jobFormReadyForDirtyCheck = false;
  let jobFormSubmitted = false;
  let pendingJobNavigation = null;

  function syncJobEditorsForSnapshot() {
    syncRichTextEditors();
    if (window.tinymce?.triggerSave) {
      tinymce.triggerSave();
    }
  }

  function jobFormSnapshot(form) {
    syncJobEditorsForSnapshot();
    const data = new FormData(form);
    const pairs = [];

    for (const [name, value] of data.entries()) {
      if (name === 'submit_action' || name === 'a') continue;
      pairs.push(`${name}:${value}`);
    }

    return pairs.sort().join('|');
  }

  function hasUnsavedJobChanges() {
    return !!jobForm &&
      jobFormReadyForDirtyCheck &&
      !jobFormSubmitted &&
      jobFormSnapshot(jobForm) !== initialJobFormSnapshot;
  }

  function richTextFieldHasContent(id) {
    const editor = richTextEditors[id];
    if (editor) return editor.getText().trim().length > 0;
    return (document.getElementById(id)?.value || '').trim().length > 0;
  }

  function controlsAreValid(selector) {
    if (!jobForm) return false;
    return [...jobForm.querySelectorAll(selector)]
      .filter(control => !control.disabled && control.type !== 'hidden')
      .every(control => control.checkValidity());
  }

  function draftRequiredFieldsComplete() {
    return controlsAreValid('#country, #client_id, #clientCodeSuffix, input[name="job_title"]');
  }

  function publishRequiredFieldsComplete() {
    syncRichTextEditors();
    return controlsAreValid('input[required], select[required], textarea[required]') &&
      richTextFieldHasContent('job_description') &&
      richTextFieldHasContent('key_skills') &&
      richTextFieldHasContent('our_terms');
  }

  function setJobActionButtonState(button, enabled) {
    if (!button) return;
    button.disabled = !enabled;
    button.classList.toggle('btn-disabled', !enabled);
  }

  function updateJobActionState() {
    const hasChanges = hasUnsavedJobChanges();
    setJobActionButtonState(document.getElementById('saveJobButton'), hasChanges);
    setJobActionButtonState(document.getElementById('draftJobButton'), hasChanges && draftRequiredFieldsComplete());
    setJobActionButtonState(document.getElementById('publishJobButton'), hasChanges && publishRequiredFieldsComplete());
  }

  function markJobFormClean() {
    if (!jobForm) return;
    initialJobFormSnapshot = jobFormSnapshot(jobForm);
    jobFormReadyForDirtyCheck = true;
    updateJobActionState();
  }

  function openUnsavedJobChangesModal(nextAction) {
    const modal = document.getElementById('unsavedJobChangesModal');
    if (!modal) return;

    pendingJobNavigation = nextAction;
    modal.showModal();
  }

  updateJobActionState();
  window.addEventListener('load', () => {
    window.setTimeout(markJobFormClean, 150);
  });
  if (document.readyState === 'complete') {
    window.setTimeout(markJobFormClean, 150);
  }

  jobForm?.addEventListener('input', updateJobActionState);
  jobForm?.addEventListener('change', updateJobActionState);

  jobForm?.addEventListener('submit', event => {
    updateJobActionState();

    if (event.submitter?.name === 'a') {
      jobFormSubmitted = true;
      return;
    }

    if (event.submitter?.value === 'draft' && !draftRequiredFieldsComplete()) {
      event.preventDefault();
      return;
    }

    if (event.submitter?.value === 'publish' && !publishRequiredFieldsComplete()) {
      event.preventDefault();
      return;
    }

    if (!hasUnsavedJobChanges()) {
      event.preventDefault();
      return;
    }

    if (!event.defaultPrevented) {
      jobFormSubmitted = true;
    }
  });

  document.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (!link || !hasUnsavedJobChanges()) return;

    const href = link.getAttribute('href') || '';
    const target = link.getAttribute('target');
    if (
      href === '' ||
      href.startsWith('#') ||
      href.startsWith('javascript:') ||
      target === '_blank' ||
      link.hasAttribute('download')
    ) {
      return;
    }

    event.preventDefault();
    openUnsavedJobChangesModal(() => {
      window.location.href = link.href;
    });
  });

  document.addEventListener('submit', event => {
    const form = event.target;
    if (
      form === jobForm ||
      form?.getAttribute('method')?.toLowerCase() === 'dialog' ||
      !hasUnsavedJobChanges()
    ) {
      return;
    }

    event.preventDefault();
    openUnsavedJobChangesModal(() => {
      jobFormSubmitted = true;
      HTMLFormElement.prototype.submit.call(form);
    });
  }, true);

  document.getElementById('stayOnJobForm')?.addEventListener('click', () => {
    pendingJobNavigation = null;
    document.getElementById('unsavedJobChangesModal')?.close();
  });

  document.getElementById('leaveJobForm')?.addEventListener('click', () => {
    const nextAction = pendingJobNavigation;
    jobFormSubmitted = true;
    pendingJobNavigation = null;
    document.getElementById('unsavedJobChangesModal')?.close();

    if (typeof nextAction === 'function') {
      nextAction();
    }
  });

  window.addEventListener('beforeunload', event => {
    if (!hasUnsavedJobChanges()) return;

    event.preventDefault();
    event.returnValue = '';
  });
</script>

<?php include dirname(__DIR__) . "/includes/footer.php"; ?>