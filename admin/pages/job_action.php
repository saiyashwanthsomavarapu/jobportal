<?php
require_once dirname(__DIR__) . "/auth.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Invalid request method. Please try again.');
    redirect(ADMIN_URL . '/pages/jobs.php');
}

if (!currentAdminCanWrite()) {
    flash('error', 'Read-only users cannot perform this action.');
    redirect(ADMIN_URL . '/pages/jobs.php');
}

$id = (int) ($_POST["id"] ?? 0);
$action = $_POST["a"] ?? "";

if (
    !$id ||
    !in_array($action, ["publish", "close", "draft", "delete", "clone"])
) {
    flash("error", "Invalid action.");
    redirect(ADMIN_URL . "/pages/jobs.php");
}

/* Verify job exists */
$stmt = db()->prepare("SELECT * FROM jobs WHERE id = ?");
$stmt->execute([$id]);
$job = $stmt->fetch();

if (!$job) {
    flash("error", "Job not found.");
    redirect(ADMIN_URL . "/pages/jobs.php");
}

try {
    $pdo = db();
    switch ($action) {
        case "publish":
            $pdo->prepare(
                "UPDATE jobs SET status='published', updated_at=NOW(), published_at=COALESCE(published_at,NOW()) WHERE id=?"
            )->execute([$id]);
            flash(
                "success",
                "Job <strong>" .
                    $job["job_code"] .
                    "</strong> is now published."
            );
            break;
        case "close":
            $pdo->prepare(
                "UPDATE jobs SET status='closed', updated_at=NOW() WHERE id=?"
            )->execute([$id]);
            flash(
                "success",
                "Job <strong>" . $job["job_code"] . "</strong> closed."
            );
            break;
        case "draft":
            $pdo->prepare("UPDATE jobs SET status='draft', updated_at=NOW() WHERE id=?")->execute(
                [$id]
            );
            flash("success", "Job moved to draft.");
            break;
        case "delete":
            $pdo->prepare("DELETE FROM jobs WHERE id=?")->execute([$id]);
            flash("success", "Job deleted.");
            break;
        case "clone":
            $generated = nextJobCode("AC");
            $newJobCode = str_replace("-", "", $generated["code"]);
            $baseSlug = slugify($job["job_title"] . " copy");
            $newSlug = $baseSlug;
            $counter = 2;
            $slugCheck = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE slug = ?");
            while (true) {
                $slugCheck->execute([$newSlug]);
                if (!(int) $slugCheck->fetchColumn()) {
                    break;
                }
                $newSlug = $baseSlug . "-" . $counter++;
            }

            $copyFields = [
                "job_title", "country", "state_province", "city", "postal_code",
                "workplace_type", "workplace_type_other", "timezone", "job_type", "job_type_other",
                "experience", "salary_rate", "salary_from", "salary_to", "salary_unit_from",
                "salary_unit_to", "salary_currency", "salary_type", "salary_boe", "industry",
                "industry_other", "job_description", "key_skills", "our_terms", "open_date", "close_date"
            ];
            $columns = array_merge(
                ["job_code", "job_code_prefix", "job_code_number", "slug", "status", "views", "created_by"],
                $copyFields
            );
            $placeholders = implode(",", array_fill(0, count($columns), "?"));
            $values = [
                $newJobCode,
                "AC",
                (int) $generated["number"],
                $newSlug,
                "draft",
                0,
                $_SESSION["admin_id"] ?? null,
            ];
            foreach ($copyFields as $field) {
                $values[] = $job[$field] ?? null;
            }
            $pdo->prepare(
                "INSERT INTO jobs (" . implode(",", $columns) . ") VALUES ($placeholders)"
            )->execute($values);
            $newId = (int) $pdo->lastInsertId();
            logActivity("clone_job", "job", $newId, $newJobCode . " (cloned from " . $job["job_code"] . ")");
            flash("success", "Draft copy <strong>" . e($newJobCode) . "</strong> created. Review it before publishing.");
            redirect(ADMIN_URL . "/pages/post_job.php?edit=" . $newId);
    }
    logActivity($action . "_job", "job", $id, $job["job_code"]);
} catch (Exception $e) {
    error_log('Job action failed: ' . $e->getMessage());
    flash("error", "The job action could not be completed.");
}

redirect(ADMIN_URL . "/pages/jobs.php");
