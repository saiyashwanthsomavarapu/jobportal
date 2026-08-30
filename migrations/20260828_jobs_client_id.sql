-- Replace the legacy text client-code relationship with a real foreign key.
-- Existing unmatched legacy codes remain in jobs.client_code for audit/backward compatibility.

ALTER TABLE jobs
  ADD COLUMN client_id INT UNSIGNED NULL AFTER job_code_number;

UPDATE jobs j
JOIN clients c
  ON c.client_code = SUBSTRING_INDEX(j.client_code, '-', 1)
SET j.client_id = c.id
WHERE j.client_id IS NULL;

ALTER TABLE jobs
  ADD INDEX idx_jobs_client_id (client_id),
  ADD CONSTRAINT fk_jobs_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT;

-- New application code no longer reads or writes this legacy value.
-- Clear it only where the relationship was migrated successfully.
UPDATE jobs SET client_code = NULL WHERE client_id IS NOT NULL;

ALTER TABLE clients
  DROP INDEX uq_client_code,
  DROP COLUMN client_code;
