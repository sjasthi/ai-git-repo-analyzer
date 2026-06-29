-- Migration v2: add check_runs table to track per-check results per scan

USE repo_analyzer;

CREATE TABLE IF NOT EXISTS check_runs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    scan_id       INT         NOT NULL,
    check_name    VARCHAR(50) NOT NULL,
    status        VARCHAR(20) NOT NULL DEFAULT 'clean',
    finding_count INT         NOT NULL DEFAULT 0,
    FOREIGN KEY (scan_id) REFERENCES scans(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
