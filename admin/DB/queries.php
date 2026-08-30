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
