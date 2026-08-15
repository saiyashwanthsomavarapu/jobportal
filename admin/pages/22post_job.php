<?php
require_once dirname(__DIR__) . '/auth.php';

// ── Load job for editing ─────────────────────────────────────
$editId  = (int)($_GET['edit'] ?? 0);
$job     = null;
$isEdit  = false;

if ($editId > 0) {
    $stmt = db()->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->execute([$editId]);
    $job = $stmt->fetch();
    if (!$job) { flash('error','Job not found.'); redirect(ADMIN_URL.'/pages/jobs.php'); }
    $isEdit = true;
}

$pageTitle   = $isEdit ? 'Edit Job: '.($job['job_code'] ?? '') : 'Post a Job';
$breadcrumbs = [
    ['Dashboard', ADMIN_URL.'/index.php'],
    ['All Jobs',  ADMIN_URL.'/pages/jobs.php'],
    [$isEdit ? 'Edit Job' : 'Post a Job', null],
];

// ── Country data (states embedded) ───────────────────────────
$countryData = [
    'India'          => [
        'prefix' => 'IND',
        'tz'     => ['IST'],
        'types'  => ['Full-time','Contract','Contract-2-Hire'],
        'states' => ['Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Delhi',
                     'Goa','Gujarat','Haryana','Himachal Pradesh','Jharkhand','Karnataka',
                     'Kerala','Madhya Pradesh','Maharashtra','Manipur','Meghalaya','Mizoram',
                     'Nagaland','Odisha','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana',
                     'Tripura','Uttar Pradesh','Uttarakhand','West Bengal'],
    ],
    'United States'  => [
        'prefix' => 'USA',
        'tz'     => ['EST','PST','CST'],
        'types'  => ['W2 Contract','C2C Contract','Full-Time','W2/C2C Contract','Contract-2-Hire'],
        'states' => ['Alabama','Alaska','Arizona','Arkansas','California','Colorado','Connecticut',
                     'Delaware','Florida','Georgia','Hawaii','Idaho','Illinois','Indiana','Iowa',
                     'Kansas','Kentucky','Louisiana','Maine','Maryland','Massachusetts','Michigan',
                     'Minnesota','Mississippi','Missouri','Montana','Nebraska','Nevada',
                     'New Hampshire','New Jersey','New Mexico','New York','North Carolina',
                     'North Dakota','Ohio','Oklahoma','Oregon','Pennsylvania','Rhode Island',
                     'South Carolina','South Dakota','Tennessee','Texas','Utah','Vermont',
                     'Virginia','Washington','West Virginia','Wisconsin','Wyoming','Washington DC'],
    ],
    'Canada'         => [
        'prefix' => 'CAD',
        'tz'     => ['EST','PST','Any'],
        'types'  => ['Full-Time','W2 Contract','Independent Contract','W2/Independent Contract'],
        'states' => ['Alberta','British Columbia','Manitoba','New Brunswick',
                     'Newfoundland and Labrador','Northwest Territories','Nova Scotia',
                     'Nunavut','Ontario','Prince Edward Island','Quebec','Saskatchewan','Yukon'],
    ],
];

$workplaceTypes = ['Onsite','Hybrid','Remote'];

// ── Experience options ────────────────────────────────────────
// FROM: 0 – 10
$expFromOpts = array_map('strval', range(0, 10));
// TO: 4 – 15, then 15+  (per spec: TO starts from 4)
$expToOpts   = array_merge(array_map('strval', range(4, 15)), ['15+']);

// ── Salary options ────────────────────────────────────────────
$salaryTypes      = ['Annual','Monthly','Per Hour'];
$salaryCurrencies = ['USD','INR','CAD'];

// ── Parse stored "X - Y years" → from/to ─────────────────────
function parseExperience(string $exp): array {
    if (preg_match('/^(\d+)\s*[-–]\s*([\d]+\+?)\s*years?/i', trim($exp), $m))
        return ['from' => $m[1], 'to' => $m[2]];
    if (preg_match('/^([\d]+\+?)\s*years?/i', trim($exp), $m))
        return ['from' => '', 'to' => $m[1]];
    return ['from' => '', 'to' => ''];
}

// ── Process form ──────────────────────────────────────────────
$errors = [];
$old    = $isEdit ? $job : ($_POST ?: []);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Alphanumeric-only filter for salary values
    $alphaNum = fn(string $v) => preg_replace('/[^a-zA-Z0-9.\s]/', '', $v);

    $data = [
        'country'              => trim($_POST['country']              ?? ''),
        'job_code'             => trim($_POST['job_code_display']     ?? ''),
        'job_code_prefix'      => trim($_POST['job_code_prefix']      ?? ''),
        'job_code_number'      => (int)($_POST['job_code_number']     ?? 0),
        'client_code'          => trim($_POST['client_code']          ?? ''),
        'job_title'            => trim($_POST['job_title']            ?? ''),
        'city'                 => trim($_POST['city']                 ?? ''),
        'state_province'       => trim($_POST['state_province']       ?? ''),
        'postal_code'          => '',
        'workplace_type'       => trim($_POST['workplace_type']       ?? ''),
        'workplace_type_other' => trim($_POST['workplace_type_other'] ?? ''),
        'timezone'             => trim($_POST['timezone']             ?? ''),
        'job_type'             => trim($_POST['job_type']             ?? ''),
        'job_type_other'       => trim($_POST['job_type_other']       ?? ''),
        // Experience from two dropdowns
        'exp_from'             => trim($_POST['exp_from']  ?? ''),
        'exp_to'               => trim($_POST['exp_to']    ?? ''),
        'experience'           => '',   // assembled below
        // Salary fields (new dedicated columns)
        'salary_from'          => $alphaNum(trim($_POST['salary_from']     ?? '')),
        'salary_to'            => $alphaNum(trim($_POST['salary_to']       ?? '')),
        'salary_type'          => trim($_POST['salary_type']               ?? ''),
        'salary_currency'      => trim($_POST['salary_currency']           ?? ''),
        'salary_rate'          => '',   // assembled below (backward-compat column)
        'industry'             => '',
        'industry_other'       => '',
        'job_description'      => $_POST['job_description'] ?? '',
        'key_skills'           => $_POST['key_skills']      ?? '',
        'our_terms'            => $_POST['our_terms']       ?? '',
        'open_date'            => trim($_POST['open_date']  ?? ''),
        'close_date'           => trim($_POST['close_date'] ?? '') ?: null,
        'status'               => ($_POST['submit_action'] ?? '') === 'publish' ? 'published' : 'draft',
    ];

    // Build experience string
    if ($data['exp_from'] !== '' && $data['exp_to'] !== '') {
        $data['experience'] = $data['exp_from'] . ' - ' . $data['exp_to'] . ' years';
    }

    // Build combined salary_rate string for backward-compat + display
    // Format: "35 - 45 | INR | Annual"
    $salRange = ($data['salary_from'] !== '' && $data['salary_to'] !== '')
        ? $data['salary_from'] . ' - ' . $data['salary_to']
        : ($data['salary_from'] ?: $data['salary_to']);
    $data['salary_rate'] = trim(implode(' | ', array_filter([
        $salRange,
        $data['salary_currency'],
        $data['salary_type'],
    ])));

    // ── Validation ────────────────────────────────────────────
    if (!$data['country'])         $errors[] = 'Country is required.';
    if (!$data['client_code'])     $errors[] = 'Client Code is required.';
    if (!$data['job_title'])       $errors[] = 'Job Title is required.';
    if (!$data['city'])            $errors[] = 'City is required.';
    if (!$data['state_province'])  $errors[] = 'State / Province is required.';
    if (!$data['workplace_type'])  $errors[] = 'Workplace Type is required.';
    if (!$data['timezone'])        $errors[] = 'Time Zone is required.';
    if (!$data['job_type'])        $errors[] = 'Job Type is required.';
    if ($data['exp_from'] === '')  $errors[] = 'Experience "From" is required.';
    if ($data['exp_to']   === '')  $errors[] = 'Experience "To" is required.';
    if (!$data['salary_from'])     $errors[] = 'Salary / Rate "From" value is required.';
    if (!$data['salary_to'])       $errors[] = 'Salary / Rate "To" value is required.';
    if (!$data['salary_currency']) $errors[] = 'Salary Currency is required.';
    if (!$data['salary_type'])     $errors[] = 'Salary Type is required.';
    if (!$data['open_date'])       $errors[] = 'Open Date is required.';
    if (!$data['close_date'])      $errors[] = 'Close Date is required.';
    if (!$data['job_description'] || strip_tags($data['job_description']) === '')
                                   $errors[] = 'Job Description is required.';
    if (!$data['key_skills'] || strip_tags($data['key_skills']) === '')
                                   $errors[] = 'Requirements is required.';
    if (!$data['our_terms'] || strip_tags($data['our_terms']) === '')
                                   $errors[] = 'Terms and Benefits is required.';

    if (empty($errors)) {
        $pdo = db();

        // Unique slug
        $slug     = slugify($data['job_title']);
        $baseSlug = $slug;
        $slugCheck = $pdo->prepare(
            "SELECT id FROM jobs WHERE slug COLLATE utf8mb4_unicode_ci = ? AND id != ?"
        );
        $si = 1;
        while (true) {
            $slugCheck->execute([$slug, $editId ?: 0]);
            if (!$slugCheck->fetch()) break;
            $slug = $baseSlug . '-' . (++$si);
        }

        try {
            if ($isEdit) {
                $pdo->prepare("
                    UPDATE jobs SET
                      country=:country, job_code=:job_code,
                      job_code_prefix=:job_code_prefix, job_code_number=:job_code_number,
                      client_code=:client_code, job_title=:job_title, slug=:slug,
                      city=:city, state_province=:state_province, postal_code=:postal_code,
                      workplace_type=:workplace_type, workplace_type_other=:workplace_type_other,
                      timezone=:timezone, job_type=:job_type, job_type_other=:job_type_other,
                      experience=:experience,
                      salary_from=:salary_from, salary_to=:salary_to,
                      salary_type=:salary_type, salary_currency=:salary_currency,
                      salary_rate=:salary_rate,
                      industry=:industry, industry_other=:industry_other,
                      job_description=:job_description, key_skills=:key_skills, our_terms=:our_terms,
                      open_date=:open_date, close_date=:close_date, status=:status,
                      published_at=IF(:status_check='published' AND published_at IS NULL, NOW(), published_at)
                    WHERE id=:id
                ")->execute([
                    ':country'              => $data['country'],
                    ':job_code'             => $data['job_code'],
                    ':job_code_prefix'      => $data['job_code_prefix'],
                    ':job_code_number'      => $data['job_code_number'],
                    ':client_code'          => $data['client_code'],
                    ':job_title'            => $data['job_title'],
                    ':slug'                 => $slug,
                    ':city'                 => $data['city'],
                    ':state_province'       => $data['state_province'],
                    ':postal_code'          => $data['postal_code'],
                    ':workplace_type'       => $data['workplace_type'],
                    ':workplace_type_other' => $data['workplace_type_other'],
                    ':timezone'             => $data['timezone'],
                    ':job_type'             => $data['job_type'],
                    ':job_type_other'       => $data['job_type_other'],
                    ':experience'           => $data['experience'],
                    ':salary_from'          => $data['salary_from'],
                    ':salary_to'            => $data['salary_to'],
                    ':salary_type'          => $data['salary_type'],
                    ':salary_currency'      => $data['salary_currency'],
                    ':salary_rate'          => $data['salary_rate'],
                    ':industry'             => $data['industry'],
                    ':industry_other'       => $data['industry_other'],
                    ':job_description'      => $data['job_description'],
                    ':key_skills'           => $data['key_skills'],
                    ':our_terms'            => $data['our_terms'],
                    ':open_date'            => $data['open_date'],
                    ':close_date'           => $data['close_date'],
                    ':status'               => $data['status'],
                    ':status_check'         => $data['status'],
                    ':id'                   => $editId,
                ]);
                logActivity('edit_job', 'job', $editId, $data['job_code']);
                flash('success', 'Job updated successfully.');

            } else {
                // Auto-generate job code — always
                $prefix    = $countryData[$data['country']]['prefix'] ?? 'GEN';
                $generated = nextJobCode($prefix);
                $data['job_code']        = $generated['code'];
                $data['job_code_number'] = $generated['number'];
                $data['job_code_prefix'] = $prefix;

                $pdo->prepare("
                    INSERT INTO jobs
                      (country, job_code, job_code_prefix, job_code_number, client_code,
                       job_title, slug, city, state_province, postal_code,
                       workplace_type, workplace_type_other, timezone,
                       job_type, job_type_other, experience,
                       salary_from, salary_to, salary_type, salary_currency, salary_rate,
                       industry, industry_other,
                       job_description, key_skills, our_terms,
                       open_date, close_date, status, created_by, published_at)
                    VALUES
                      (:country, :job_code, :job_code_prefix, :job_code_number, :client_code,
                       :job_title, :slug, :city, :state_province, :postal_code,
                       :workplace_type, :workplace_type_other, :timezone,
                       :job_type, :job_type_other, :experience,
                       :salary_from, :salary_to, :salary_type, :salary_currency, :salary_rate,
                       :industry, :industry_other,
                       :job_description, :key_skills, :our_terms,
                       :open_date, :close_date, :status, :created_by,
                       IF(:status2='published', NOW(), NULL))
                ")->execute([
                    ':country'              => $data['country'],
                    ':job_code'             => $data['job_code'],
                    ':job_code_prefix'      => $data['job_code_prefix'],
                    ':job_code_number'      => $data['job_code_number'],
                    ':client_code'          => $data['client_code'],
                    ':job_title'            => $data['job_title'],
                    ':slug'                 => $slug,
                    ':city'                 => $data['city'],
                    ':state_province'       => $data['state_province'],
                    ':postal_code'          => $data['postal_code'],
                    ':workplace_type'       => $data['workplace_type'],
                    ':workplace_type_other' => $data['workplace_type_other'],
                    ':timezone'             => $data['timezone'],
                    ':job_type'             => $data['job_type'],
                    ':job_type_other'       => $data['job_type_other'],
                    ':experience'           => $data['experience'],
                    ':salary_from'          => $data['salary_from'],
                    ':salary_to'            => $data['salary_to'],
                    ':salary_type'          => $data['salary_type'],
                    ':salary_currency'      => $data['salary_currency'],
                    ':salary_rate'          => $data['salary_rate'],
                    ':industry'             => $data['industry'],
                    ':industry_other'       => $data['industry_other'],
                    ':job_description'      => $data['job_description'],
                    ':key_skills'           => $data['key_skills'],
                    ':our_terms'            => $data['our_terms'],
                    ':open_date'            => $data['open_date'],
                    ':close_date'           => $data['close_date'],
                    ':status'               => $data['status'],
                    ':created_by'           => $_SESSION['admin_id'],
                    ':status2'              => $data['status'],
                ]);
                $newId = (int)$pdo->lastInsertId();
                logActivity('create_job', 'job', $newId, $data['job_code']);
                flash('success', 'Job posted! Code: <strong>'.$data['job_code'].'</strong>');
            }
            redirect(ADMIN_URL.'/pages/jobs.php');

        } catch (Exception $e) {
            $errors[] = 'Database error: '.$e->getMessage();
        }
    }

    $old = array_merge((array)($old ?? []), $data);
}

// ── Helpers ───────────────────────────────────────────────────
function old(string $key, $default = ''): string {
    global $old;
    return e((string)($old[$key] ?? $default));
}
function oldSel(string $key, string $val): string {
    global $old;
    return (isset($old[$key]) && (string)$old[$key] === $val) ? 'selected' : '';
}

// ── Resolve saved experience from/to ─────────────────────────
$savedExpFrom = $savedExpTo = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $savedExpFrom = $_POST['exp_from'] ?? '';
    $savedExpTo   = $_POST['exp_to']   ?? '';
} elseif ($isEdit && !empty($job['experience'])) {
    $p = parseExperience($job['experience']);
    $savedExpFrom = $p['from'];
    $savedExpTo   = $p['to'];
}

// ── Resolve saved salary values ───────────────────────────────
$savedSalFrom = $savedSalTo = $savedSalType = $savedSalCur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $savedSalFrom = $_POST['salary_from']     ?? '';
    $savedSalTo   = $_POST['salary_to']       ?? '';
    $savedSalType = $_POST['salary_type']     ?? '';
    $savedSalCur  = $_POST['salary_currency'] ?? '';
} elseif ($isEdit) {
    // Prefer new dedicated columns; fall back to parsing old salary_rate string
    $savedSalFrom = $job['salary_from']     ?? '';
    $savedSalTo   = $job['salary_to']       ?? '';
    $savedSalType = $job['salary_type']     ?? '';
    $savedSalCur  = $job['salary_currency'] ?? '';

    // Backward-compat: if new columns empty, parse legacy salary_rate
    if ($savedSalFrom === '' && !empty($job['salary_rate'])) {
        $parts = array_map('trim', explode('|', $job['salary_rate']));
        if (!empty($parts[0])) {
            if (preg_match('/^([\w.\s]+?)\s*-\s*([\w.\s]+)$/', $parts[0], $rm)) {
                $savedSalFrom = trim($rm[1]);
                $savedSalTo   = trim($rm[2]);
            } else {
                $savedSalFrom = trim($parts[0]);
            }
        }
        if (!empty($parts[1])) $savedSalCur  = trim($parts[1]);
        if (!empty($parts[2])) $savedSalType = trim($parts[2]);
    }
}

include dirname(__DIR__) . '/includes/header.php';
?>

<?php if (!empty($errors)): ?>
<div class="flash flash-error">
  <div>✕ Please fix the following:
    <ul><?php foreach ($errors as $err) echo '<li>'.e($err).'</li>'; ?></ul>
  </div>
</div>
<?php endif; ?>

<form method="POST" id="jobForm">

<!-- ═══ CARD 1: JOB IDENTITY ════════════════════════════════ -->
<div class="card">
  <div class="section-title">① Job Identity</div>
  <div class="form-grid g3">

    <!-- Country -->
    <div class="field">
      <label>Country <span class="req">*</span></label>
      <select name="country" id="country" class="ctrl" onchange="onCountryChange()" required>
        <option value="">— Select Country —</option>
        <?php foreach ($countryData as $c => $d): ?>
        <option value="<?= e($c) ?>" <?= oldSel('country',$c) ?>><?= e($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Job Code — auto, fully locked -->
    <div class="field">
      <label>Job Code
        <span style="font-size:10px;font-weight:400;color:var(--success)"> ✓ Auto-generated</span>
      </label>
      <div class="jc-row">
        <div class="jc-prefix" id="jcPrefix"><?= $isEdit ? e($job['job_code_prefix']) : '—' ?></div>
        <span class="jc-sep">-</span>
        <input type="text" class="ctrl" id="jcNumDisplay"
               value="<?= $isEdit ? e($job['job_code_number']) : 'Auto' ?>"
               readonly tabindex="-1"
               style="background:rgba(62,207,142,.07);border-color:rgba(62,207,142,.25);
                      color:var(--success);cursor:not-allowed">
      </div>
      <input type="hidden" name="job_code_display" id="jobCodeDisplay"
             value="<?= $isEdit ? e($job['job_code']) : '' ?>">
      <input type="hidden" name="job_code_prefix"  id="jobCodePrefix"
             value="<?= $isEdit ? e($job['job_code_prefix']) : '' ?>">
      <input type="hidden" name="job_code_number"  id="jobCodeNumber"
             value="<?= $isEdit ? e($job['job_code_number']) : '' ?>">
      <span class="field-hint">Full code: <strong id="jcFull" style="color:var(--success)">
        <?= $isEdit ? e($job['job_code']) : 'Assigned on save' ?>
      </strong></span>
    </div>

    <!-- Client Code -->
    <div class="field">
      <label>Client Code <span class="req">*</span>
        <span style="font-size:10px;font-weight:400;color:var(--muted)">(internal only)</span>
      </label>
      <input type="text" name="client_code" class="ctrl" placeholder="e.g. CLI-2024-007"
             value="<?= old('client_code') ?>" required>
    </div>

    <!-- Job Title -->
    <div class="field gc3">
      <label>Job Title <span class="req">*</span></label>
      <input type="text" name="job_title" class="ctrl"
             placeholder="e.g. Senior Full Stack Developer"
             value="<?= old('job_title') ?>" required>
    </div>

  </div>
</div>

<!-- ═══ CARD 2: WORK LOCATION ═══════════════════════════════ -->
<div class="card">
  <div class="section-title">② Work Location</div>
  <div class="form-grid g4">

    <div class="field">
      <label>City <span class="req">*</span></label>
    <!--  <input type="text" name="city" class="ctrl" placeholder="e.g. Bangalore"
             value="<?= old('city') ?>" required> -->
             
             <input type="text" name="city" id="city" class="ctrl" placeholder="e.g. Bangalore"
       value="<?= old('city') ?>" required>
    </div>

    <div class="field">
      <label>State / Province <span class="req">*</span></label>
      <select name="state_province" id="stateSelect" class="ctrl" required>
        <option value="">— Select Country First —</option>
      </select>
    </div>

    <div class="field">
      <label>Time Zone <span class="req">*</span></label>
      <select name="timezone" id="timezoneSelect" class="ctrl" required>
        <option value="">— Select Country First —</option>
      </select>
    </div>

    <div class="field gc2">
      <label>Workplace Type <span class="req">*</span></label>
      <select name="workplace_type" id="workplaceType" class="ctrl"
              onchange="toggleOther(this,'otherWorkplace')" required>
        <option value="">— Select —</option>
        <?php foreach ($workplaceTypes as $wt): ?>
        <option value="<?= e($wt) ?>" <?= oldSel('workplace_type',$wt) ?>><?= e($wt) ?></option>
        <?php endforeach; ?>
   
     <!--   <option value="Other" <?= oldSel('workplace_type','Other') ?>>Other</option>  -->
      </select>
    <!--    <div class="other-wrap" id="otherWorkplace">
        <input type="text" name="workplace_type_other" class="ctrl"
               placeholder="Specify workplace type"
               value="<?= old('workplace_type_other') ?>">
      </div> -->
      
    </div>

  </div>
</div>

<!-- ═══ CARD 3: JOB DETAILS ══════════════════════════════════ -->
<div class="card">
  <div class="section-title">③ Job Details</div>
  <div class="form-grid g3">

    <!-- Job Type -->
    <div class="field">
      <label>Job Type <span class="req">*</span></label>
      <select name="job_type" id="jobTypeSelect" class="ctrl"
              onchange="toggleOther(this,'otherJobType')" required>
        <option value="">— Select Country First —</option>
      </select>
      <div class="other-wrap" id="otherJobType">
        <input type="text" name="job_type_other" class="ctrl"
               placeholder="Specify job type"
               value="<?= old('job_type_other') ?>">
      </div>
    </div>

    <!-- ── EXPERIENCE: From / To ── -->
    <div class="field">
      <label>Experience <span class="req">*</span></label>

      <div style="display:flex;align-items:flex-end;gap:10px">

        <!-- FROM: 0 to 10 -->
        <div style="flex:1">
          <div style="font-size:10px;color:var(--muted);text-transform:uppercase;
                      letter-spacing:.5px;margin-bottom:4px">From (yrs)</div>
          <select name="exp_from" id="expFrom" class="ctrl" required
                  onchange="updateExpPreview()">
            <option value="">—</option>
            <?php foreach ($expFromOpts as $v): ?>
            <option value="<?= $v ?>" <?= $savedExpFrom===$v?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <span style="color:var(--muted);font-size:20px;font-weight:300;padding-bottom:4px">–</span>

        <!-- TO: 4 to 15, 15+ -->
        <div style="flex:1">
          <div style="font-size:10px;color:var(--muted);text-transform:uppercase;
                      letter-spacing:.5px;margin-bottom:4px">To (yrs)</div>
          <select name="exp_to" id="expTo" class="ctrl" required
                  onchange="updateExpPreview()">
            <option value="">—</option>
            <?php foreach ($expToOpts as $v): ?>
            <option value="<?= $v ?>" <?= $savedExpTo===$v?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>

      </div>
      <span class="field-hint">
        Preview: <strong id="expPreview" style="color:var(--accent)">
          <?= ($savedExpFrom!==''&&$savedExpTo!=='')
              ? e($savedExpFrom).' - '.e($savedExpTo).' years' : '—' ?>
        </strong>
      </span>
    </div>

    <!-- ── SALARY / RATE block ── -->
    <div class="field">
      <label>Salary / Rate <span class="req">*</span>
        <span style="font-size:10px;font-weight:400;color:var(--muted)">
          (alphanumeric · e.g. 35 LPA, 135K, 80)
        </span>
      </label>

      <!-- From – To range row -->
      <div style="display:flex;align-items:flex-end;gap:10px;margin-bottom:8px">

        <div style="flex:1">
          <div style="font-size:10px;color:var(--muted);text-transform:uppercase;
                      letter-spacing:.5px;margin-bottom:4px">From</div>
          <input type="text" name="salary_from" id="salaryFrom" class="ctrl"
                 placeholder="35 LPA / 80K / 45"
                 value="<?= e($savedSalFrom) ?>"
                 pattern="[a-zA-Z0-9.\s]+" title="Alphanumeric only (letters, digits, dots)"
                 oninput="this.value=this.value.replace(/[^a-zA-Z0-9.\s]/g,'');updateSalaryPreview()"
                 required>
        </div>

        <span style="color:var(--muted);font-size:20px;font-weight:300;padding-bottom:4px">–</span>

        <div style="flex:1">
          <div style="font-size:10px;color:var(--muted);text-transform:uppercase;
                      letter-spacing:.5px;margin-bottom:4px">To</div>
          <input type="text" name="salary_to" id="salaryTo" class="ctrl"
                 placeholder="45 LPA / 100K / 60"
                 value="<?= e($savedSalTo) ?>"
                 pattern="[a-zA-Z0-9.\s]+" title="Alphanumeric only (letters, digits, dots)"
                 oninput="this.value=this.value.replace(/[^a-zA-Z0-9.\s]/g,'');updateSalaryPreview()"
                 required>
        </div>

      </div>

      <!-- Currency + Type row -->
      <div style="display:flex;gap:10px">

        <div style="flex:1">
          <div style="font-size:10px;color:var(--muted);text-transform:uppercase;
                      letter-spacing:.5px;margin-bottom:4px">Currency <span style="color:var(--accent)">*</span></div>
          <select name="salary_currency" id="salaryCurrency" class="ctrl" required
                  onchange="updateSalaryPreview()">
            <option value="">— Select —</option>
            <?php foreach ($salaryCurrencies as $cur): ?>
            <option value="<?= $cur ?>" <?= $savedSalCur===$cur?'selected':'' ?>><?= $cur ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="flex:1">
          <div style="font-size:10px;color:var(--muted);text-transform:uppercase;
                      letter-spacing:.5px;margin-bottom:4px">Type <span style="color:var(--accent)">*</span></div>
          <select name="salary_type" id="salaryType" class="ctrl" required
                  onchange="updateSalaryPreview()">
            <option value="">— Select —</option>
            <?php foreach ($salaryTypes as $st): ?>
            <option value="<?= $st ?>" <?= $savedSalType===$st?'selected':'' ?>><?= $st ?></option>
            <?php endforeach; ?>
          </select>
        </div>

      </div>

      <span class="field-hint" style="margin-top:6px">
        Preview: <strong id="salaryPreview" style="color:var(--accent)">
          <?php
          if ($savedSalFrom !== '' || $savedSalTo !== '') {
              $r = ($savedSalFrom!==''&&$savedSalTo!=='')
                    ? e($savedSalFrom).' – '.e($savedSalTo)
                    : e($savedSalFrom ?: $savedSalTo);
              $parts = array_filter([$r, e($savedSalCur), e($savedSalType)]);
              echo implode(' | ', $parts);
          } else { echo '—'; }
          ?>
        </strong>
      </span>
    </div>

  <!-- Open Date -->
<div class="field">
  <label>Open Date <span class="req">*</span></label>
  <input type="text" name="open_date" id="openDate" class="ctrl"
         placeholder="e.g. 10 March 2026"
         value="<?= old('open_date', date('Y-m-d')) ?>" required readonly>
  <span class="field-hint" id="openDateFmt"></span>
</div>

<!-- Close Date -->
<div class="field">
  <label>Close Date <span class="req">*</span></label>
  <input type="text" name="close_date" id="closeDate" class="ctrl"
         placeholder="e.g. 10 March 2026"
         value="<?= old('close_date') ?>" required readonly>
</div>

  </div>
</div>

<!-- ═══ CARD 4: RICH CONTENT ═════════════════════════════════ -->
<div class="card">
  <div class="section-title">④ Content</div>
  <div style="display:flex;flex-direction:column;gap:22px">

    <div class="field">
      <label>Job Description <span class="req">*</span></label>
      <textarea id="job_description" name="job_description"><?= old('job_description') ?></textarea>
    </div>

    <div class="field">
      <label>Requirements <span class="req">*</span></label>
      <textarea id="key_skills" name="key_skills"><?= old('key_skills') ?></textarea>
    </div>

    <div class="field">
      <label>Terms and Benefits <span class="req">*</span></label>
      <textarea id="our_terms" name="our_terms"><?= old('our_terms') ?></textarea>
    </div>

  </div>
</div>

<!-- ═══ ACTIONS ══════════════════════════════════════════════ -->
<div class="card" style="padding:18px 24px">
  <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <button type="submit" name="submit_action" value="publish" class="btn btn-primary">
      🚀 <?= $isEdit ? 'Update & Publish' : 'Publish Job' ?>
    </button>
    <button type="submit" name="submit_action" value="draft" class="btn btn-draft">
      💾 Save as Draft
    </button>
    <a href="<?= ADMIN_URL ?>/pages/jobs.php" class="btn btn-ghost">← Cancel</a>
    <?php if ($isEdit): ?>
    <a href="<?= ADMIN_URL ?>/pages/job_action.php?id=<?= $editId ?>&a=delete"
       class="btn btn-danger" style="margin-left:auto"
       onclick="return confirm('Delete this job permanently?')">🗑 Delete Job</a>
    <?php endif; ?>
  </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

</form>

<!-- ══ Country / state data from PHP → JS ══ -->
<script>
const COUNTRY_DATA   = <?= json_encode($countryData, JSON_UNESCAPED_UNICODE) ?>;
const IS_EDIT        = <?= json_encode($isEdit) ?>;
const SAVED_COUNTRY  = <?= json_encode(old('country',        $isEdit?($job['country']       ??''):'')) ?>;
const SAVED_STATE    = <?= json_encode(old('state_province', $isEdit?($job['state_province']??''):'')) ?>;
const SAVED_TIMEZONE = <?= json_encode(old('timezone',       $isEdit?($job['timezone']      ??''):'')) ?>;
const SAVED_JOB_TYPE = <?= json_encode(old('job_type',       $isEdit?($job['job_type']      ??''):'')) ?>;

// ── Country change ────────────────────────────────────────────
function onCountryChange() {
  const country = document.getElementById('country').value;
  const data    = COUNTRY_DATA[country];
  const prefix  = data?.prefix ?? '—';

  // Clear or restore city
  const cityInput = document.getElementById('city') // add id="city" to city input if not present
  const savedCity = <?= json_encode(old('city', $isEdit ? ($job['city'] ?? '') : '')) ?>;
  cityInput.value = (country === SAVED_COUNTRY) ? savedCity : '';

  // Job code prefix display (read-only)
  document.getElementById('jcPrefix').textContent = prefix;
  if (!IS_EDIT) {
    document.getElementById('jobCodePrefix').value  = prefix;
    document.getElementById('jcFull').textContent   = 'Assigned on save';
    document.getElementById('jcNumDisplay').value   = 'Auto';
    document.getElementById('jobCodeDisplay').value = '';
  }

  // States
  const stSel = document.getElementById('stateSelect');
  stSel.innerHTML = '<option value="">— Select State —</option>';
  (data?.states ?? []).forEach(s => {
    const o = new Option(s, s);
    if (s === SAVED_STATE) o.selected = true;
    stSel.appendChild(o);
  });

  // Timezones
  const tzSel = document.getElementById('timezoneSelect');
  tzSel.innerHTML = '<option value="">— Select —</option>';
  (data?.tz ?? []).forEach(t => {
    const o = new Option(t, t);
    if (t === SAVED_TIMEZONE) o.selected = true;
    tzSel.appendChild(o);
  });

  // Job Types
  const jtSel = document.getElementById('jobTypeSelect');
  jtSel.innerHTML = '<option value="">— Select —</option>';
  (data?.types ?? []).forEach(t => {
    const o = new Option(t, t);
    if (t === SAVED_JOB_TYPE) o.selected = true;
    jtSel.appendChild(o);
  });
 // const oth = new Option('Other','Other');
  // if (SAVED_JOB_TYPE === 'Other') oth.selected = true;
 // jtSel.appendChild(oth);
  toggleOther(document.getElementById('jobTypeSelect'), 'otherJobType');
}

// ── Other reveal ─────────────────────────────────────────────
function toggleOther(sel, divId) {
  const div = document.getElementById(divId);
  if (div) div.classList.toggle('show', sel.value === 'Other');
}



// ── Experience preview ────────────────────────────────────────
function updateExpPreview() {
  const f = document.getElementById('expFrom').value;
  const t = document.getElementById('expTo').value;
  document.getElementById('expPreview').textContent =
    (f !== '' && t !== '') ? f + ' - ' + t + ' years' : '—';
}

// ── Salary preview ────────────────────────────────────────────
function updateSalaryPreview() {
  const f   = document.getElementById('salaryFrom').value.trim();
  const t   = document.getElementById('salaryTo').value.trim();
  const cur = document.getElementById('salaryCurrency').value;
  const typ = document.getElementById('salaryType').value;
  const range = (f && t) ? f + ' – ' + t : (f || t || '');
  const parts = [range, cur, typ].filter(Boolean);
  document.getElementById('salaryPreview').textContent = parts.length ? parts.join(' | ') : '—';
}

// ── Open date display ─────────────────────────────────────────
// ── Flatpickr date pickers ────────────────────────────────────
function toDisplay(ymd) {
  if (!ymd) return '';
  const d = new Date(ymd + 'T00:00:00');
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
}

const openPicker = flatpickr('#openDate', {
  dateFormat: 'Y-m-d',
  altInput: true,
  altFormat: 'j F Y',
  defaultDate: document.getElementById('openDate').value || '<?= date('Y-m-d') ?>',
  onChange: function(selectedDates, dateStr) {
    document.getElementById('openDateFmt').textContent = toDisplay(dateStr);
  }
});

const closePicker = flatpickr('#closeDate', {
  dateFormat: 'Y-m-d',
  altInput: true,
  altFormat: 'j F Y',
  defaultDate: document.getElementById('closeDate').value || null,
});

// Show formatted hint for open date on load
const openVal = document.getElementById('openDate').value;
if (openVal) document.getElementById('openDateFmt').textContent = toDisplay(openVal);

// ── On load: restore all dropdowns ───────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  if (SAVED_COUNTRY) {
    document.getElementById('country').value = SAVED_COUNTRY;
    onCountryChange();
  }
  toggleOther(document.getElementById('workplaceType'), 'otherWorkplace');
  updateExpPreview();
  updateSalaryPreview();
});
</script>

<script src="https://cdn.tiny.cloud/1/yqey0x686cm618eysfgj0l4o0chy4vr9kthimc8hgtp2bhqn/tinymce/8/tinymce.min.js"
        referrerpolicy="origin" crossorigin="anonymous"></script>
<script>
document.getElementById('jobForm').addEventListener('submit', function () {
  tinymce.triggerSave();
});
tinymce.init({
  selector: '#job_description, #key_skills, #our_terms',
  height: 300,
  menubar: false,
  plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
  toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist | link image media table | removeformat',
  setup: function (editor) {
    editor.on('change', function () { editor.save(); });
  }
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
