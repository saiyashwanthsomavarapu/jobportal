<?php
require_once dirname(__DIR__) . '/auth.php';

// ── Load job for editing / cloning ───────────────────────────
$editId  = (int)($_GET['edit']  ?? 0);
$cloneId = (int)($_GET['clone'] ?? 0);
$job     = null;
$isEdit  = false;
$isClone = false;
$cloneNewCode   = '';
$cloneNewNumber = 0;

if ($editId > 0) {
    $stmt = db()->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->execute([$editId]);
    $job = $stmt->fetch();
    if (!$job) { flash('error','Job not found.'); redirect(ADMIN_URL.'/pages/jobs.php'); }
    $isEdit = true;
} elseif ($cloneId > 0) {
    $stmt = db()->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->execute([$cloneId]);
    $sourceJob = $stmt->fetch();
    if (!$sourceJob) { flash('error','Job to clone not found.'); redirect(ADMIN_URL.'/pages/jobs.php'); }
    $isClone = true;
    // Pre-generate the next job code for the clone so it shows in the locked field
   $cloneGenerated = nextJobCode('AC');
$cloneNewCode   = str_replace('-', '', $cloneGenerated['code']);
    $cloneNewNumber = $cloneGenerated['number'];
    // Use source job data to pre-fill the form (editable fields only)
    $job = $sourceJob;
}

$pageTitle   = $isEdit  ? 'Edit Job: '.($job['job_code'] ?? '')
             : ($isClone ? 'Clone Job &rarr; '.$cloneNewCode
             : 'Post a Job');
// Job Number label is now "Job Number" throughout the form (was "Job Code")
$breadcrumbs = [
    ['Dashboard', ADMIN_URL.'/index.php'],
    ['All Jobs',  ADMIN_URL.'/pages/jobs.php'],
    [$isEdit ? 'Edit Job' : ($isClone ? 'Clone Job' : 'Post a Job'), null],
];

// ── Load clients for dropdown ─────────────────────────────────
try {
    $clientsList = db()->query(
        "SELECT id, client_name, client_code FROM clients ORDER BY client_name ASC"
    )->fetchAll();
} catch (Exception $e) {
    $clientsList = [];
}

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
        'types'  => ['Full-Time','C2C Contract','T4 Contract','Contract-2-Hire','C2C/T4 Contract'],
        'states' => ['Alberta','British Columbia','Manitoba','New Brunswick',
                     'Newfoundland and Labrador','Northwest Territories','Nova Scotia',
                     'Nunavut','Ontario','Prince Edward Island','Quebec','Saskatchewan','Yukon'],
    ],
];

$workplaceTypes   = ['Onsite','Hybrid','Remote'];
$expFromOpts      = array_map('strval', range(0, 10));
$expToOpts        = array_merge(array_map('strval', range(4, 15)), ['15+']);
$salaryTypes      = ['Annual','Monthly','Per Hour','Yearly'];
$salaryCurrencies = ['USD','INR','CAD'];

// ── Parse stored "X - Y years" → from/to ─────────────────────
function parseExperience(string $exp): array {
    if (preg_match('/^(\d+)\s*[-–]\s*([\d]+\+?)\s*years?/i', trim($exp), $m))
        return ['from' => $m[1], 'to' => $m[2]];
    if (preg_match('/^([\d]+\+?)\s*years?/i', trim($exp), $m))
        return ['from' => '', 'to' => $m[1]];
    return ['from' => '', 'to' => ''];
}

// ── Parse stored client_code "ADSK-12345" → prefix + suffix ──
function parseClientCode(string $cc): array {
    // Format stored: "ADSK-12345" or plain "ADSK"
    if (preg_match('/^([A-Z0-9]+)-(\d{4,5})$/', $cc, $m))
        return ['prefix' => $m[1], 'suffix' => $m[2]];
    return ['prefix' => $cc, 'suffix' => ''];
}

// ── Process form ──────────────────────────────────────────────
$errors = [];
$old    = ($isEdit || $isClone) ? $job : ($_POST ?: []);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $alphaNum = fn(string $v) => preg_replace('/[^a-zA-Z0-9.\s]/', '', $v);

    // Build client_code from dropdown + manual suffix
    $ccPrefix = strtoupper(trim($_POST['client_code_prefix'] ?? ''));
    $ccSuffix = trim($_POST['client_code_suffix'] ?? '');
    $ccSuffix = preg_replace('/[^0-9]/', '', $ccSuffix); // digits only

    // Final client_code stored as "ADSK-12345" or "ADSK" if no suffix
    $builtClientCode = '';
    if ($ccPrefix !== '') {
        $builtClientCode = $ccSuffix !== '' ? $ccPrefix . '-' . $ccSuffix : $ccPrefix;
    }

    // For edit mode, preserve original country and job_code from DB (cannot be changed)
  if ($isEdit) {
    // KEEP ORIGINAL DATE
    $openDateRaw = $job['open_date'] ?? date('Y-m-d');

    // Recalculate close date based on original open date
    try {
        $autoCloseDate = (new DateTime($openDateRaw))->modify('+28 days')->format('Y-m-d');
    } catch (Exception $e) {
        $autoCloseDate = date('Y-m-d', strtotime('+28 days'));
    }
} else {
    // NEW + CLONE → CURRENT DATE
    $openDateRaw = date('Y-m-d');
    $autoCloseDate = date('Y-m-d', strtotime('+28 days'));
}
   

    $data = [
        // Edit: preserve original country & code. Clone/New: country is editable; job_code generated fresh below.
        'country'              => $isEdit ? ($job['country'] ?? '') : trim($_POST['country'] ?? ''),
        'job_code'             => $isEdit ? ($job['job_code'] ?? '') : trim($_POST['job_code_display'] ?? ''),
        'job_code_prefix'      => $isEdit ? ($job['job_code_prefix'] ?? '') : trim($_POST['job_code_prefix'] ?? ''),
        'job_code_number'      => $isEdit ? ($job['job_code_number'] ?? 0) : (int)($_POST['job_code_number'] ?? 0),
        'client_code'          => $builtClientCode,
        'job_title'            => trim($_POST['job_title']            ?? ''),
        'city'                 => trim($_POST['city']                 ?? ''),
        'state_province'       => trim($_POST['state_province']       ?? ''),
        'postal_code'          => '',
        'workplace_type'       => trim($_POST['workplace_type']       ?? ''),
        'workplace_type_other' => trim($_POST['workplace_type_other'] ?? ''),
        'timezone'             => trim($_POST['timezone']             ?? ''),
        'job_type'             => trim($_POST['job_type']             ?? ''),
        'job_type_other'       => trim($_POST['job_type_other']       ?? ''),
        'exp_from'             => trim($_POST['exp_from']  ?? ''),
        'exp_to'               => trim($_POST['exp_to']    ?? ''),
        'experience'           => '',
        'salary_boe'           => isset($_POST['salary_boe']) ? 1 : 0,
        'salary_from'          => preg_replace('/[^0-9.]/', '', trim($_POST['salary_from']     ?? '')),
        'salary_to'            => preg_replace('/[^0-9.]/', '', trim($_POST['salary_to']       ?? '')),
        'salary_unit_from'     => trim($_POST['salary_unit_from']          ?? ''),
        'salary_unit_to'       => trim($_POST['salary_unit_to']            ?? ''),
        'salary_type'          => trim($_POST['salary_type']               ?? ''),
        'salary_currency'      => trim($_POST['salary_currency']           ?? ''),
        'salary_rate'          => '',
        'industry'             => '',
        'industry_other'       => '',
        'job_description'      => $_POST['job_description'] ?? '',
        'key_skills'           => $_POST['key_skills']      ?? '',
        'our_terms'            => $_POST['our_terms']       ?? '',
        'open_date'  => $openDateRaw,
'close_date' => $autoCloseDate,
        'status'               => ($_POST['submit_action'] ?? '') === 'publish' ? 'published' : 'draft',
    ];

    // Build experience string
    if ($data['exp_from'] !== '' && $data['exp_to'] !== '') {
        $data['experience'] = $data['exp_from'] . ' - ' . $data['exp_to'] . ' years';
    }

    // Build combined salary_rate
    if ($data['salary_boe']) {
        $data['salary_rate'] = 'Based on Experience';
        $data['salary_from'] = '';
        $data['salary_to']   = '';
    } else {
    $fromDisplay = $data['salary_from'] !== '' ? $data['salary_from'] . ($data['salary_unit_from'] !== '' ? ' '.$data['salary_unit_from'] : '') : '';
    $toDisplay   = $data['salary_to']   !== '' ? $data['salary_to']   . ($data['salary_unit_to']   !== '' ? ' '.$data['salary_unit_to']   : '') : '';
    $salRange = ($fromDisplay !== '' && $toDisplay !== '')
        ? $fromDisplay . ' - ' . $toDisplay
        : ($fromDisplay ?: $toDisplay);
    $data['salary_rate'] = trim(implode(' | ', array_filter([
        $salRange, $data['salary_currency'], $data['salary_type'],
    ])));
    }

    // ── Validation ────────────────────────────────────────────
    if (!$data['country'])         $errors[] = 'Country is required.';
    if (!$ccPrefix)                $errors[] = 'Client Code: please select a client.';
    if ($ccSuffix !== '' && (strlen($ccSuffix) < 4 || strlen($ccSuffix) > 5))
                                   $errors[] = 'Client Code suffix must be 4 or 5 digits.';
    if (!$data['job_title'])       $errors[] = 'Job Title is required.';
    if ($data['workplace_type'] !== 'Remote') {
    if (!$data['city']) $errors[] = 'City is required.';
    if (!$data['state_province']) $errors[] = 'State / Province is required.';
}
    if (!$data['workplace_type'])  $errors[] = 'Workplace Type is required.';
    if (!$data['timezone'])        $errors[] = 'Time Zone is required.';
    if (!$data['job_type'])        $errors[] = 'Job Type is required.';
    if ($data['exp_from'] === '')  $errors[] = 'Experience "From" is required.';
    if ($data['exp_to']   === '')  $errors[] = 'Experience "To" is required.';
    if (!$data['salary_boe']) {
    if (!$data['salary_from'])     $errors[] = 'Salary / Rate "From" value is required.';
    if (!$data['salary_to'])       $errors[] = 'Salary / Rate "To" value is required.';
    if (!$data['salary_currency']) $errors[] = 'Salary Currency is required.';
    if (!$data['salary_type'])     $errors[] = 'Salary Type is required.';
    }
    if (!$data['open_date'])       $errors[] = 'Open Date is required.';
    if (!$data['close_date'])      $errors[] = 'Close Date is required.';
    if (!$data['job_description'] || strip_tags($data['job_description']) === '')
                                   $errors[] = 'Job Description is required.';
    if (!$data['key_skills'] || strip_tags($data['key_skills']) === '')
                                   $errors[] = 'Requirements is required.';
    if (!$data['our_terms'] || strip_tags($data['our_terms']) === '')
                                   $errors[] = 'Why Join Us is required.';

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
                      salary_unit_from=:salary_unit_from, salary_unit_to=:salary_unit_to,
                      salary_type=:salary_type, salary_currency=:salary_currency,
                      salary_rate=:salary_rate, salary_boe=:salary_boe,
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
                    ':salary_unit_from'     => $data['salary_unit_from'],
                    ':salary_unit_to'       => $data['salary_unit_to'],
                    ':salary_type'          => $data['salary_type'],
                    ':salary_currency'      => $data['salary_currency'],
                    ':salary_rate'          => $data['salary_rate'],
                    ':salary_boe'           => $data['salary_boe'],
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
    // For clone: reuse the code pre-generated on GET (already shown to user, counter already incremented)
    // For new job: generate fresh code now
    if ($isClone && !empty($_POST['job_code_display'])) {
        $data['job_code']        = trim($_POST['job_code_display']);
        $data['job_code_number'] = (int)$_POST['job_code_number'];
        $data['job_code_prefix'] = 'AC';
    } else {
        $generated = nextJobCode('AC');
        $data['job_code'] = str_replace('-', '', $generated['code']);
        $data['job_code_number'] = $generated['number'];
        $data['job_code_prefix'] = 'AC';
    }

                $pdo->prepare("
                    INSERT INTO jobs
                      (country, job_code, job_code_prefix, job_code_number, client_code,
                       job_title, slug, city, state_province, postal_code,
                       workplace_type, workplace_type_other, timezone,
                       job_type, job_type_other, experience,
                       salary_from, salary_to, salary_unit_from, salary_unit_to, salary_type, salary_currency, salary_rate, salary_boe,
                       industry, industry_other,
                       job_description, key_skills, our_terms,
                       open_date, close_date, status, created_by, published_at)
                    VALUES
                      (:country, :job_code, :job_code_prefix, :job_code_number, :client_code,
                       :job_title, :slug, :city, :state_province, :postal_code,
                       :workplace_type, :workplace_type_other, :timezone,
                       :job_type, :job_type_other, :experience,
                       :salary_from, :salary_to, :salary_unit_from, :salary_unit_to, :salary_type, :salary_currency, :salary_rate, :salary_boe,
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
                    ':salary_unit_from'     => $data['salary_unit_from'],
                    ':salary_unit_to'       => $data['salary_unit_to'],
                    ':salary_type'          => $data['salary_type'],
                    ':salary_currency'      => $data['salary_currency'],
                    ':salary_rate'          => $data['salary_rate'],
                    ':salary_boe'           => $data['salary_boe'],
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
                if ($isClone) {
                    logActivity('clone_job', 'job', $newId, $data['job_code'].' (cloned from '.$cloneId.')');
                    flash('success', 'Job cloned! New Job Number: <strong>'.$data['job_code'].'</strong>');
                } else {
                    logActivity('create_job', 'job', $newId, $data['job_code']);
                    flash('success', 'Job posted! Code: <strong>'.$data['job_code'].'</strong>');
                }
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

// ── Resolve experience from/to ────────────────────────────────
$savedExpFrom = $savedExpTo = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $savedExpFrom = $_POST['exp_from'] ?? '';
    $savedExpTo   = $_POST['exp_to']   ?? '';
} elseif (($isEdit || $isClone) && !empty($job['experience'])) {
    $p = parseExperience($job['experience']);
    $savedExpFrom = $p['from']; $savedExpTo = $p['to'];
}

// ── Resolve salary from/to/type/currency/units ───────────────
$savedSalFrom = $savedSalTo = $savedSalType = $savedSalCur = '';
$savedSalUnitFrom = $savedSalUnitTo = '';
$savedSalBoe = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $savedSalFrom     = $_POST['salary_from']      ?? '';
    $savedSalTo       = $_POST['salary_to']        ?? '';
    $savedSalType     = $_POST['salary_type']      ?? '';
    $savedSalCur      = $_POST['salary_currency']  ?? '';
    $savedSalUnitFrom = $_POST['salary_unit_from'] ?? '';
    $savedSalUnitTo   = $_POST['salary_unit_to']   ?? '';
    $savedSalBoe      = isset($_POST['salary_boe']);
} elseif ($isEdit || $isClone) {
    $savedSalFrom = $job['salary_from']     ?? '';
    $savedSalTo   = $job['salary_to']       ?? '';
    $savedSalType = $job['salary_type']     ?? '';
    $savedSalCur  = $job['salary_currency'] ?? '';
    $savedSalBoe  = !empty($job['salary_boe']);
    // Try to restore unit from stored salary_rate if columns are bare numbers
    $savedSalUnitFrom = $job['salary_unit_from'] ?? '';
    $savedSalUnitTo   = $job['salary_unit_to']   ?? '';
    if ($savedSalFrom === '' && !empty($job['salary_rate'])) {
        $parts = array_map('trim', explode('|', $job['salary_rate']));
        if (!empty($parts[0])) {
            // e.g. "35 LPA - 45 LPA" or "80K - 100K"
            if (preg_match('/^([\d.]+)\s*(LPA|K)?\s*[-–]\s*([\d.]+)\s*(LPA|K)?$/i', $parts[0], $rm)) {
                $savedSalFrom     = trim($rm[1]);
                $savedSalUnitFrom = strtoupper(trim($rm[2] ?? ''));
                $savedSalTo       = trim($rm[3]);
                $savedSalUnitTo   = strtoupper(trim($rm[4] ?? ''));
            } elseif (preg_match('/^([\d.]+)\s*(LPA|K)?$/i', $parts[0], $rm)) {
                $savedSalFrom     = trim($rm[1]);
                $savedSalUnitFrom = strtoupper(trim($rm[2] ?? ''));
            } else {
                // Legacy: plain alphanumeric — keep as-is
                if (preg_match('/^([\w.\s]+?)\s*[-–]\s*([\w.\s]+)$/', $parts[0], $rm)) {
                    $savedSalFrom = trim($rm[1]); $savedSalTo = trim($rm[2]);
                } else { $savedSalFrom = trim($parts[0]); }
            }
        }
        if (!empty($parts[1])) $savedSalCur  = trim($parts[1]);
        if (!empty($parts[2])) $savedSalType = trim($parts[2]);
    }
}

// ── Resolve saved client code prefix + suffix ─────────────────
$savedCcPrefix = '';
$savedCcSuffix = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $savedCcPrefix = strtoupper(trim($_POST['client_code_prefix'] ?? ''));
    $savedCcSuffix = trim($_POST['client_code_suffix'] ?? '');
} elseif (($isEdit || $isClone) && !empty($job['client_code'])) {
    $parsed = parseClientCode($job['client_code']);
    $savedCcPrefix = $parsed['prefix'];
    $savedCcSuffix = $parsed['suffix'];
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

<form method="POST" id="jobForm"
      action="<?= $isEdit ? '?edit='.$editId : ($isClone ? '?clone='.$cloneId : '') ?>">

<?php if ($isClone): ?>
<!-- Hidden flag so POST handler knows this is a clone submission -->
<input type="hidden" name="clone_source_id" value="<?= $cloneId ?>">
<div class="flash" style="background:rgba(255,190,11,.08);border:1px solid rgba(255,190,11,.3);color:var(--text);display:flex;gap:10px;align-items:flex-start">
  <span style="font-size:18px">⧉</span>
  <div>
    <strong>Cloning job <?= e($job['job_code']) ?> — <?= e($job['job_title']) ?></strong><br>
    <span style="font-size:12px;color:var(--muted)">A new Job Number <strong style="color:var(--success)"><?= e($cloneNewCode) ?></strong> has been reserved. All fields are pre-filled from the source — edit as needed, then save.</span>
  </div>
</div>
<?php endif; ?>

<!-- ═══ CARD 1: JOB IDENTITY ════════════════════════════════ -->
<div class="card">
  <div class="section-title">① Location</div>
  <div class="form-grid g3">
      
        <!-- Job Number — auto, locked (AC prefix global counter) -->
    <div class="field" style="display:none;">
      <label>Job Number
        <?php if ($isClone): ?>
        <span style="font-size:10px;font-weight:400;color:var(--warning)"> ⧉ Cloned — new number assigned</span>
        <?php else: ?>
        <span style="font-size:10px;font-weight:400;color:var(--success)"> ✓ Auto-generated</span>
        <?php endif; ?>
      </label>
      <div class="jc-row">
        <div class="jc-prefix" id="jcPrefix">AC</div>
        <span class="jc-sep">-</span>
        <input type="text" class="ctrl" id="jcNumDisplay"
               value="<?= $isEdit ? e($job['job_code_number']) : ($isClone ? e($cloneNewNumber) : 'Auto') ?>"
               readonly tabindex="-1"
               style="background:rgba(62,207,142,.07);border-color:rgba(62,207,142,.25);
                      color:var(--success);cursor:not-allowed">
      </div>
      <input type="hidden" name="job_code_display" id="jobCodeDisplay"
             value="<?= $isEdit ? e($job['job_code']) : ($isClone ? e($cloneNewCode) : '') ?>">
      <input type="hidden" name="job_code_prefix"  id="jobCodePrefix"
             value="<?= ($isEdit || $isClone) ? 'AC' : 'AC' ?>">
      <input type="hidden" name="job_code_number"  id="jobCodeNumber"
             value="<?= $isEdit ? e($job['job_code_number']) : ($isClone ? e($cloneNewNumber) : '') ?>">
      <span class="field-hint">Full number: <strong id="jcFull" style="color:var(--success)">
        <?= $isEdit ? e($job['job_code']) : ($isClone ? e($cloneNewCode) : 'Assigned on save') ?>
      </strong></span>
    </div>

    <!-- Country -->
    <div class="field">
      <label>Country <span class="req">*</span></label>
      <select name="country" id="country" class="ctrl" onchange="onCountryChange()" required
              <?= $isEdit ? 'disabled style="opacity:.6;cursor:not-allowed;pointer-events:none"' : '' ?>>
        <option value="">— Select Country —</option>
        <?php foreach ($countryData as $c => $d): ?>
        <option value="<?= e($c) ?>" <?= oldSel('country',$c) ?>><?= e($c) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($isEdit): ?>
      <!-- Hidden field to submit country value since disabled fields are not submitted -->
      <input type="hidden" name="country" value="<?= e($job['country'] ?? '') ?>">
      <?php endif; ?>
    </div>



  </div>
</div>

<div class="card">
  <div class="section-title">① Job Identity</div>
  <div class="form-grid g3">
      
 

    <!-- ── CLIENT CODE: dropdown + manual suffix ── -->
    <div class="field">
      <label>Job Code <span class="req">*</span>
        <span style="font-size:10px;font-weight:400;color:var(--muted)">(internal only)</span>
      </label>
   <!-- Preview + hint -->
      <span class="field-hint" style="margin-top:5px">
        Result: <strong id="clientCodePreview" style="color:var(--accent2)">
          <?php
          if ($savedCcPrefix !== '') {
              echo e($savedCcSuffix !== '' ? $savedCcPrefix.'-'.$savedCcSuffix : $savedCcPrefix);
          } else { echo '—'; }
          ?>
        </strong>
        <span style="color:var(--muted);margin-left:8px" id="clientNameHint">
          <?php
          if ($savedCcPrefix !== '') {
              foreach ($clientsList as $cl) {
                  if ($cl['client_code'] === $savedCcPrefix) {
                      echo '('.e($cl['client_name']).')';
                      break;
                  }
              }
          }
          ?>
        </span>
      </span>
    <!--   <span class="field-hint">Suffix: 4–5 digits optional (e.g. 1234 or 12345)</span>  -->
      <!-- Row: [Dropdown] - [Manual digits input] -->
      <div style="display:flex;align-items:center;gap:8px">

        <!-- Client code dropdown -->
        <select name="client_code_prefix" id="clientCodePrefix" class="ctrl"
                style="flex:1.2"
                onchange="updateClientCodePreview()" required>
          <option value="">— Select Client —</option>
          <?php foreach ($clientsList as $cl): ?>
          <option value="<?= e($cl['client_code']) ?>"
            data-name="<?= e($cl['client_name']) ?>"
            <?= $savedCcPrefix === $cl['client_code'] ? 'selected' : '' ?>>
           <!-- <?= e($cl['client_code']) ?> — <?= e($cl['client_name']) ?>  -->
            <?= e($cl['client_code']) ?>
          </option>
          <?php endforeach; ?>
        </select>

        <span style="color:var(--muted);font-weight:700;font-size:16px;flex-shrink:0">-</span>

        <!-- Manual 4–5 digit suffix -->
        <input type="text" name="client_code_suffix" id="clientCodeSuffix" class="ctrl"
               style="flex:.8;letter-spacing:1px"
               placeholder="12345"
               maxlength="5"
               value="<?= e($savedCcSuffix) ?>"
               oninput="this.value=this.value.replace(/[^0-9]/g,'');updateClientCodePreview()"
               title="4 or 5 digit number">
      </div>

   
    </div>

    <!-- Job Title -->
    <div class="field">
      <label>Job Title <span class="req">*</span></label>
         <!-- Preview + hint -->
      <span class="field-hint" style="margin-top:5px;visibility:hidden;">
        Result: <strong id="clientCodePreview" style="color:var(--accent2)">
          <?php
          if ($savedCcPrefix !== '') {
              echo e($savedCcSuffix !== '' ? $savedCcPrefix.'-'.$savedCcSuffix : $savedCcPrefix);
          } else { echo '—'; }
          ?>
        </strong>
        <span style="color:var(--muted);margin-left:8px" id="clientNameHint">
          <?php
          if ($savedCcPrefix !== '') {
              foreach ($clientsList as $cl) {
                  if ($cl['client_code'] === $savedCcPrefix) {
                      echo '('.e($cl['client_name']).')';
                      break;
                  }
              }
          }
          ?>
        </span>
      </span>
      <input type="text" name="job_title" class="ctrl"
             placeholder="e.g. Senior Full Stack Developer"
             value="<?= old('job_title') ?>" required>
    </div>
    
     <!-- Experience From / To -->
    <div class="field">
      <label>Experience <span class="req">*</span></label>
      <div style="display:flex;align-items:flex-end;gap:10px">
        <div style="flex:1">
          <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">From (yrs)</div>
          <select name="exp_from" id="expFrom" class="ctrl" required onchange="updateExpPreview()">
            <option value="">—</option>
            <?php foreach ($expFromOpts as $v): ?>
            <option value="<?= $v ?>" <?= $savedExpFrom===$v?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <span style="color:var(--muted);font-size:20px;font-weight:300;padding-bottom:4px">–</span>
        <div style="flex:1">
          <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">To (yrs)</div>
          <select name="exp_to" id="expTo" class="ctrl" required onchange="updateExpPreview()">
            <option value="">—</option>
            <?php foreach ($expToOpts as $v): ?>
            <option value="<?= $v ?>" <?= $savedExpTo===$v?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <span class="field-hint">Preview: <strong id="expPreview" style="color:var(--accent)">
        <?= ($savedExpFrom!==''&&$savedExpTo!=='') ? e($savedExpFrom).' - '.e($savedExpTo).' years' : '—' ?>
      </strong></span>
    </div>
    
      <div class="field">
      <label>Workplace Type <span class="req">*</span></label>
      <select name="workplace_type" id="workplaceType" class="ctrl" onchange="toggleLocationFields()" required>
        <option value="">— Select —</option>
        <?php foreach ($workplaceTypes as $wt): ?>
        <option value="<?= e($wt) ?>" <?= oldSel('workplace_type',$wt) ?>><?= e($wt) ?></option>
        <?php endforeach; ?>
      </select>
    </div>



  </div>
</div>

<!-- ═══ CARD 2: WORK LOCATION ═══════════════════════════════ -->
<div class="card">
  <div class="section-title">② Work Location</div>
  <div class="form-grid g4">

    <div class="field">
      <label>City <span class="req">*</span></label>
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

    <!-- Job Type -->
    <div class="field">
      <label>Job Type <span class="req">*</span></label>
      <select name="job_type" id="jobTypeSelect" class="ctrl"
              onchange="toggleOther(this,'otherJobType')" required>
        <option value="">— Select Country First —</option>
      </select>
      <div class="other-wrap" id="otherJobType">
        <input type="text" name="job_type_other" class="ctrl"
               placeholder="Specify job type" value="<?= old('job_type_other') ?>">
      </div>
    </div>
  

  </div>
</div>



<!-- ═══ CARD 3: JOB DETAILS ══════════════════════════════════ -->
<div class="card">
  <div class="section-title">③ Job Details</div>
  <div class="form-grid g2">

    <!-- Salary / Rate -->
    <div class="field">
      <label>Salary / Rate with Unit<span class="req">*</span>
        <span style="font-size:10px;font-weight:400;color:var(--muted)">(numeric · e.g. 35 LPA, 80 K)</span>
      </label>

      <!-- BOE Checkbox -->
      <label style="display:inline-flex;align-items:center;gap:8px;margin-bottom:10px;cursor:pointer;font-weight:500;font-size:13px">
        <input type="checkbox" name="salary_boe" id="salaryBoe" value="1"
               onchange="toggleSalaryBoe()"
               <?= $savedSalBoe ? 'checked' : '' ?>
               style="width:16px;height:16px;accent-color:var(--accent);cursor:pointer">
        DOE <span style="color:var(--muted);font-weight:400">(Depends on Experience)</span>
      </label>

      <!-- BOE display text (shown when checkbox is checked) -->
      <div id="salaryBoeText" style="display:<?= $savedSalBoe ? 'block' : 'none' ?>;padding:10px 14px;background:rgba(62,207,142,.08);border:1px solid rgba(62,207,142,.25);border-radius:6px;color:var(--success);font-weight:600;font-size:14px">
        Depends on Experience
      </div>

      <!-- Salary inputs (hidden when BOE checked) -->
      <div id="salaryInputsWrap" style="display:<?= $savedSalBoe ? 'none' : 'block' ?>">
      <div style="display:flex;align-items:flex-end;gap:10px;margin-bottom:8px">
        <!-- FROM -->
        <div style="flex:1">
          <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">From</div>
          <div style="display:flex;gap:6px">
            <input type="number" name="salary_from" id="salaryFrom" class="ctrl"
                   placeholder="35"
                   value="<?= e($savedSalFrom) ?>"
                   min="0" step="any"
                   oninput="updateSalaryPreview()"
                   style="flex:1.2;min-width:0"
                   required>
            <select name="salary_unit_from" id="salaryUnitFrom" class="ctrl"
                    onchange="updateSalaryPreview()"
                    style="flex:.9;min-width:0">
              <option value="" <?= $savedSalUnitFrom==='' ? 'selected' : '' ?>>—</option>
              <option value="L" <?= $savedSalUnitFrom==='L' ? 'selected' : '' ?>>L (Lakhs)</option>
              <option value="K"   <?= $savedSalUnitFrom==='K'   ? 'selected' : '' ?>>K (Thousands)</option>
            </select>
          </div>
        </div>
        <span style="color:var(--muted);font-size:20px;font-weight:300;padding-bottom:4px">–</span>
        <!-- TO -->
        <div style="flex:1">
          <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">To</div>
          <div style="display:flex;gap:6px">
            <input type="number" name="salary_to" id="salaryTo" class="ctrl"
                   placeholder="45"
                   value="<?= e($savedSalTo) ?>"
                   min="0" step="any"
                   oninput="updateSalaryPreview()"
                   style="flex:1.2;min-width:0"
                   required>
            <select name="salary_unit_to" id="salaryUnitTo" class="ctrl"
                    onchange="updateSalaryPreview()"
                    style="flex:.9;min-width:0">
              <option value="" <?= $savedSalUnitTo==='' ? 'selected' : '' ?>>—</option>
              <option value="L" <?= $savedSalUnitTo==='L' ? 'selected' : '' ?>>L (Lakhs)</option>
              <option value="K"   <?= $savedSalUnitTo==='K'   ? 'selected' : '' ?>>K (Thousands)</option>
            </select>
          </div>
        </div>
      </div>
     
    </div><!-- end from/to row -->
    
  <div id="salaryCurrencyTypeWrap">
  
    <div class="field">
     <div style="display:flex;gap:10px">
        <div style="flex:1">
          <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Currency <span style="color:var(--accent)">*</span></div>
           <span class="field-hint" style="margin-top:6px">
        Preview: <strong id="salaryPreview" style="color:var(--accent)">
          <?php
          if ($savedSalFrom !== '' || $savedSalTo !== '') {
              $fromDisp = $savedSalFrom !== '' ? $savedSalFrom . ($savedSalUnitFrom !== '' ? ' '.e($savedSalUnitFrom) : '') : '';
              $toDisp   = $savedSalTo   !== '' ? $savedSalTo   . ($savedSalUnitTo   !== '' ? ' '.e($savedSalUnitTo)   : '') : '';
              $r = ($fromDisp !== '' && $toDisp !== '')
                    ? e($fromDisp).' – '.e($toDisp)
                    : e($fromDisp ?: $toDisp);
              $pp = array_filter([$r, e($savedSalCur), e($savedSalType)]);
              echo implode(' | ', $pp);
          } else { echo '—'; }
          ?>
        </strong>
      </span>
          <select name="salary_currency" id="salaryCurrency" class="ctrl" required onchange="updateSalaryPreview()">
            <option value="">— Select —</option>
            <?php foreach ($salaryCurrencies as $cur): ?>
            <option value="<?= $cur ?>" <?= $savedSalCur===$cur?'selected':'' ?>><?= $cur ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="flex:1">
          <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Type <span style="color:var(--accent)">*</span></div>
          <span class="field-hint" style="margin-top:6px;visibility:hidden;">
        Preview: <strong id="salaryPreview" style="color:var(--accent)">
          <?php
          if ($savedSalFrom !== '' || $savedSalTo !== '') {
              $fromDisp = $savedSalFrom !== '' ? $savedSalFrom . ($savedSalUnitFrom !== '' ? ' '.e($savedSalUnitFrom) : '') : '';
              $toDisp   = $savedSalTo   !== '' ? $savedSalTo   . ($savedSalUnitTo   !== '' ? ' '.e($savedSalUnitTo)   : '') : '';
              $r = ($fromDisp !== '' && $toDisp !== '')
                    ? e($fromDisp).' – '.e($toDisp)
                    : e($fromDisp ?: $toDisp);
              $pp = array_filter([$r, e($savedSalCur), e($savedSalType)]);
              echo implode(' | ', $pp);
          } else { echo '—'; }
          ?>
        </strong>
      </span>
          <select name="salary_type" id="salaryType" class="ctrl" required onchange="updateSalaryPreview()">
            <option value="">— Select —</option>
            <?php foreach ($salaryTypes as $st): ?>
            <option value="<?= $st ?>" <?= $savedSalType===$st?'selected':'' ?>><?= $st ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
     
      </div><!-- /salaryInputsWrap -->
      </div><!-- /salary .field -->

  </div>

    <!-- Open Date -->
    <div class="field" style="display:none">
      <label>Open Date <span class="req">*</span></label>
      <input class="ctrl" type="text" id="openDate" name="open_date"
       value="<?= e($isEdit ? ($job['open_date'] ?? date('Y-m-d')) : date('Y-m-d')) ?>"
       readonly>
      <span class="field-hint" id="openDateFmt"></span>
    </div>

    <div class="field" style="display:none">
      <label>Close Date
        <span style="font-size:10px;font-weight:400;color:var(--muted)"> ✓ Auto (Open + 28 days)</span>
      </label>
      <input type="text" class="ctrl" name="close_date" id="closeDate"
       value="<?= e($isEdit ? ($job['close_date'] ?? '') : date('Y-m-d', strtotime('+28 days'))) ?>"
       readonly>
     
      <span class="field-hint" id="closeDateFmt" style="color:var(--success)"></span>
    </div>
    <!-- Close Date — auto-filled as Open Date + 28 days, not editable -->
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
      <label>Why Join Us <span class="req">*</span></label>
      <textarea id="our_terms" name="our_terms"><?= old('our_terms') ?></textarea>
    </div>

  </div>
</div>

<!-- ═══ ACTIONS ══════════════════════════════════════════════ -->
<div class="card" style="padding:18px 24px">
  <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <button type="submit" name="submit_action" value="publish" class="btn btn-primary">
      <?php if ($isClone): ?>Publish<?php elseif ($isEdit): ?>🚀 Update & Publish<?php else: ?>🚀 Publish Job<?php endif; ?>
    </button>
    <button type="submit" name="submit_action" value="draft" class="btn btn-draft">
      <?= $isClone ? 'Draft' : '💾 Save as Draft' ?>
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

<!-- ══ Data from PHP → JS ══ -->
<script>
const COUNTRY_DATA   = <?= json_encode($countryData, JSON_UNESCAPED_UNICODE) ?>;
const IS_EDIT        = <?= json_encode($isEdit) ?>; // false for clone — country dropdown stays editable
const IS_CLONE       = <?= json_encode($isClone) ?>;
const SAVED_COUNTRY = <?= json_encode(
    $_POST['country'] ?? ($job['country'] ?? '')
) ?>;
const SAVED_STATE    = <?= json_encode(old('state_province', ($isEdit||$isClone)?($job['state_province']??''):'')) ?>;
const SAVED_TIMEZONE = <?= json_encode(old('timezone',       ($isEdit||$isClone)?($job['timezone']      ??''):'')) ?>;
const SAVED_JOB_TYPE = <?= json_encode($_POST['job_type'] ?? ($job['job_type'] ?? '')) ?>;

// ── BOE toggle ────────────────────────────────────────────────
function toggleSalaryBoe() {
  const checked = document.getElementById('salaryBoe').checked;
  document.getElementById('salaryBoeText').style.display           = checked ? 'block' : 'none';
  document.getElementById('salaryInputsWrap').style.display        = checked ? 'none'  : 'block';
  document.getElementById('salaryCurrencyTypeWrap').style.display  = checked ? 'none'  : 'block';

  // Toggle required on salary inputs so HTML5 validation doesn't fire
  const inputs = document.querySelectorAll(
    '#salaryInputsWrap input[type="number"], #salaryCurrency, #salaryType'
  );
  inputs.forEach(el => {
    if (checked) { el.removeAttribute('required'); }
    else         { el.setAttribute('required', 'required'); }
  });
}

// ── Client Code preview ───────────────────────────────────────
function updateClientCodePreview() {
  const sel    = document.getElementById('clientCodePrefix');
  const suffix = document.getElementById('clientCodeSuffix').value.trim();
  const prefix = sel.value;
  const selOpt = sel.options[sel.selectedIndex];
  const name   = selOpt ? (selOpt.dataset.name || '') : '';

  // Preview: "ADSK-12345" or "ADSK"
  const full = prefix
    ? (suffix ? prefix + '-' + suffix : prefix)
    : '—';

  document.getElementById('clientCodePreview').textContent = full;
  document.getElementById('clientNameHint').textContent    = name ? '(' + name + ')' : '';
}

// ── Country change ────────────────────────────────────────────
function onCountryChange() {
  const country = document.getElementById('country').value;
  const data    = COUNTRY_DATA[country];

  const cityInput = document.getElementById('city');
  const savedCity = <?= json_encode(old('city', ($isEdit || $isClone) ? ($job['city'] ?? '') : '')) ?>;
  cityInput.value = (country === SAVED_COUNTRY) ? savedCity : '';

  // Job Number prefix is always AC — do not change it based on country
  // (jcPrefix stays "AC" at all times)

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
 // Job Types
const jtSel = document.getElementById('jobTypeSelect');
jtSel.innerHTML = '<option value="">— Select —</option>';

(data?.types ?? []).forEach(t => {
  const o = new Option(t, t);

  // 🔥 FIX: select during creation (more reliable)
  if (t === SAVED_JOB_TYPE) {
    o.selected = true;
  }

  jtSel.appendChild(o);
});

// 🔥 EXTRA SAFETY (in case mismatch happens)
setTimeout(() => {
  if (SAVED_JOB_TYPE && jtSel.value === '') {
    jtSel.value = SAVED_JOB_TYPE;
  }
}, 50);

toggleOther(jtSel, 'otherJobType');

// 🔥 Force select AFTER options are added
if (SAVED_JOB_TYPE) {
  jtSel.value = SAVED_JOB_TYPE;
}
  toggleOther(document.getElementById('jobTypeSelect'), 'otherJobType');

  // ── Re-fill our_terms when country changes (new posts only) ──────────
  // Always replaces on country change — still placeholder, not user content.
  // Skipped entirely for edit/clone.
  if (!IS_EDIT && !IS_CLONE) {
    const ed = tinymce.get('our_terms');
    if (ed) {
      const content = OUR_TERMS_PLACEHOLDERS[country];
      ed.setContent(content || '');
    }
  }
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
  const f       = document.getElementById('salaryFrom').value.trim();
  const t       = document.getElementById('salaryTo').value.trim();
  const unitF   = document.getElementById('salaryUnitFrom').value;
  const unitT   = document.getElementById('salaryUnitTo').value;
  const cur     = document.getElementById('salaryCurrency').value;
  const typ     = document.getElementById('salaryType').value;
  const fromStr = f ? (f + (unitF ? ' ' + unitF : '')) : '';
  const toStr   = t ? (t + (unitT ? ' ' + unitT : '')) : '';
  const range   = (fromStr && toStr) ? fromStr + ' – ' + toStr : (fromStr || toStr || '');
  const parts   = [range, cur, typ].filter(Boolean);
  document.getElementById('salaryPreview').textContent = parts.length ? parts.join(' | ') : '—';
}

// ── Date helpers ──────────────────────────────────────────────
function toDisplay(ymd) {
  if (!ymd) return '';
  const d = new Date(ymd + 'T00:00:00');
  return d.toLocaleDateString('en-GB', {day:'numeric', month:'long', year:'numeric'});
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
  const type = document.getElementById('workplaceType').value;
  const city = document.getElementById('city');
  const state = document.getElementById('stateSelect');

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
const openVal = document.getElementById('openDate').value;
if (openVal) {
  document.getElementById('openDateFmt').textContent = toDisplay(openVal);
  // Only auto-fill close date if it isn't already set (e.g. edit mode preserves existing)
  const existingClose = document.getElementById('closeDate').value;

if (!existingClose) {
  autoFillCloseDate(openVal);
}
}

// ── On load ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {

  if (SAVED_COUNTRY) {
    document.getElementById('country').value = SAVED_COUNTRY;
  }

  onCountryChange(); // MUST run after setting country

  toggleLocationFields(); // FIXED

  updateExpPreview();
  updateSalaryPreview();
  updateClientCodePreview();
  toggleSalaryBoe();

});
</script>

<script src="https://cdn.tiny.cloud/1/yqey0x686cm618eysfgj0l4o0chy4vr9kthimc8hgtp2bhqn/tinymce/8/tinymce.min.js"
        referrerpolicy="origin" crossorigin="anonymous"></script>
<script>
document.getElementById('jobForm').addEventListener('submit', function () {
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

  font_family_formats: "Tahoma=tahoma,arial,helvetica,sans-serif; Lato=Lato,sans-serif; Arial=arial,helvetica,sans-serif",

  font_size_formats: "8pt 9pt 10pt 11pt 12pt 14pt 16pt 18pt 24pt 36pt",

  content_style: `
    body {
      font-family: Tahoma, Lato, sans-serif;
      font-size: 10pt;
    }
  `,

  setup: function (editor) {
    editor.on('change', function () {
      editor.save();
    });

    editor.on('init', function () {
      if (IS_EDIT || IS_CLONE) return;          // edit/clone → never touch
      if (editor.id !== 'our_terms') return;    // only our_terms gets prefilled

      const country = document.getElementById('country').value;
      if (!country) return;

      const content = OUR_TERMS_PLACEHOLDERS[country];
      if (content) editor.setContent(content);
    });
  }
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>