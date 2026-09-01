<?php

// $COUNT_BY_STATUS = "SELECT status, COUNT(*) AS cnt FROM jobs WHERE status IN ('published', 'draft', 'closed') GROUP BY status";

$COUNT_BY_STATUS = "SELECT COUNT(*) AS total,
                        SUM(status='published' AND (close_date IS NULL OR close_date >= CURDATE())) AS published,
                        SUM(status='draft') AS drafts,
                        SUM(status='closed' OR (status='published' AND close_date IS NOT NULL AND close_date < CURDATE())) AS closed
                    FROM jobs";

$RECENT_JOBS = "SELECT  j.*,
                    REGEXP_REPLACE(j.client_code, '[^0-9]', '') AS client_code,
                    a.name AS posted_by
                FROM jobs j
                    LEFT JOIN admin_users a 
                    ON a.id = j.created_by
                WHERE j.status = 'published'
                    ORDER BY j.created_at DESC
                LIMIT 8";

$JOBS_COUNT_BY_COUNTRY = "SELECT country, COUNT(*) AS cnt FROM jobs WHERE status = 'published' AND country IS NOT NULL AND country != '' GROUP BY country ORDER BY cnt DESC";


function getJobs($whereSql, $offset, $perPage)
{
    return "SELECT
      j.*,
      REGEXP_REPLACE(j.client_code, '[^0-9]', '') AS client_code,
      a.name AS posted_by,
      c.client_name
    FROM jobs j
    LEFT JOIN admin_users a
      ON a.id = j.created_by
    LEFT JOIN clients c
            ON (
                c.id = j.client_id
                OR LEFT(j.client_code, 4) = c.client_code
            )
    WHERE $whereSql
    ORDER BY j.created_at DESC
    LIMIT $perPage OFFSET $offset";
}

function getCountClients($whereSql)
{
    return "SELECT COUNT(DISTINCT j.id) FROM jobs j LEFT JOIN clients c ON c.id=j.client_id WHERE $whereSql";
}

function getAdminUserRoleById(int $userId): ?string
{
    $stmt = db()->prepare('SELECT role FROM admin_users WHERE id = ?');
    $stmt->execute([$userId]);
    $role = $stmt->fetchColumn();

    return $role === false ? null : (string) $role;
}

function adminEmailExistsForOtherUser(string $email, int $excludeUserId = 0): bool
{
    $stmt = db()->prepare('SELECT id FROM admin_users WHERE email = ? AND id != ?');
    $stmt->execute([$email, $excludeUserId]);

    return (bool) $stmt->fetch();
}

function createAdminUser(string $name, string $email, string $passwordHash, string $role, int $isActive): int
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO admin_users (name, email, password, role, is_active)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, $passwordHash, $role, $isActive]);

    return (int) $pdo->lastInsertId();
}

function updateAdminUserWithPassword(
    int $userId,
    string $name,
    string $email,
    string $passwordHash,
    string $role,
    int $isActive
): void {
    $stmt = db()->prepare(
        'UPDATE admin_users
         SET name = ?, email = ?, password = ?, role = ?, is_active = ?
         WHERE id = ?'
    );
    $stmt->execute([$name, $email, $passwordHash, $role, $isActive, $userId]);
}

function updateAdminUser(int $userId, string $name, string $email, string $role, int $isActive): void
{
    $stmt = db()->prepare(
        'UPDATE admin_users
         SET name = ?, email = ?, role = ?, is_active = ?
         WHERE id = ?'
    );
    $stmt->execute([$name, $email, $role, $isActive, $userId]);
}

function getAdminUserStatusById(int $userId): ?array
{
    $stmt = db()->prepare('SELECT is_active, name FROM admin_users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function setAdminUserActive(int $userId, int $isActive): void
{
    $stmt = db()->prepare('UPDATE admin_users SET is_active = ? WHERE id = ?');
    $stmt->execute([$isActive, $userId]);
}

function getAdminUserNameById(int $userId): ?string
{
    $stmt = db()->prepare('SELECT name FROM admin_users WHERE id = ?');
    $stmt->execute([$userId]);
    $name = $stmt->fetchColumn();

    return $name === false ? null : (string) $name;
}

function deleteAdminUserById(int $userId): void
{
    $stmt = db()->prepare('DELETE FROM admin_users WHERE id = ?');
    $stmt->execute([$userId]);
}

function updateAdminUserPassword(int $userId, string $passwordHash): void
{
    $stmt = db()->prepare('UPDATE admin_users SET password = ? WHERE id = ?');
    $stmt->execute([$passwordHash, $userId]);
}

function getAdminUserById(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function getAdminPasswordResetUserById(int $userId): ?array
{
    $stmt = db()->prepare('SELECT id, name, email, role FROM admin_users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function getAdminUsers(bool $includeSuperadmins): array
{
    $sql = $includeSuperadmins
        ? 'SELECT * FROM admin_users ORDER BY role ASC, name ASC'
        : "SELECT * FROM admin_users WHERE role <> 'superadmin' ORDER BY role ASC, name ASC";

    return db()->query($sql)->fetchAll();
}

function clientNameExistsForOtherClient(string $clientName, int $excludeClientId = 0): bool
{
    $stmt = db()->prepare('SELECT id FROM clients WHERE client_name = ? AND id != ?');
    $stmt->execute([strtolower($clientName), $excludeClientId]);

    return (bool) $stmt->fetch();
}

function clientNameExists(string $clientName): bool
{
    $stmt = db()->prepare('SELECT id FROM clients WHERE client_name = ?');
    $stmt->execute([strtolower($clientName)]);

    return (bool) $stmt->fetch();
}

function updateClientName(int $clientId, string $clientName): void
{
    $stmt = db()->prepare('UPDATE clients SET client_name = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([strtolower($clientName), $clientId]);
}

function createClient(string $clientName, int $createdBy): void
{
    $stmt = db()->prepare('INSERT INTO clients (client_name, created_by) VALUES (?, ?)');
    $stmt->execute([strtolower($clientName), $createdBy]);
}

function countJobsForClient(int $clientId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM jobs WHERE client_id = ?');
    $stmt->execute([$clientId]);

    return (int) $stmt->fetchColumn();
}

function deleteClientById(int $clientId): void
{
    $stmt = db()->prepare('DELETE FROM clients WHERE id = ?');
    $stmt->execute([$clientId]);
}

function getClientById(int $clientId): ?array
{
    $stmt = db()->prepare('SELECT * FROM clients WHERE id = ?');
    $stmt->execute([$clientId]);
    $client = $stmt->fetch();

    return $client ?: null;
}

function getClientsWithCreator(): array
{
    return db()
        ->query(
            'SELECT c.*, a.name AS created_by_name, COALESCE(jobs_linked.jobs_count, 0) AS jobs_count
             FROM clients c
             LEFT JOIN admin_users a ON a.id = c.created_by
             LEFT JOIN (
                SELECT client_id, COUNT(*) AS jobs_count
                FROM jobs
                GROUP BY client_id
             ) jobs_linked ON jobs_linked.client_id = c.id
             ORDER BY c.client_name ASC'
        )
        ->fetchAll();
}
