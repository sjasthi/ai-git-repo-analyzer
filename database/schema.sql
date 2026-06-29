CREATE DATABASE IF NOT EXISTS repo_analyzer;
USE repo_analyzer;

CREATE TABLE IF NOT EXISTS repositories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repo_url VARCHAR(500) NOT NULL,
    platform VARCHAR(20) NOT NULL DEFAULT 'GitHub',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_repositories_repo_url (repo_url)
);

CREATE TABLE IF NOT EXISTS scans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repository_id INT NOT NULL,
    scan_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    summary_score INT NULL,
    total_findings INT DEFAULT 0,
    total_skills INT DEFAULT 0,
    selected_checks_json LONGTEXT NULL,
    results_json LONGTEXT NULL,
    FOREIGN KEY (repository_id) REFERENCES repositories(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS findings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scan_id INT NOT NULL,
    category VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    severity VARCHAR(20) NOT NULL,
    FOREIGN KEY (scan_id) REFERENCES scans(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scan_id INT NOT NULL,
    skill_name VARCHAR(100) NOT NULL,
    proficiency_level VARCHAR(50) NOT NULL DEFAULT 'Intermediate',
    risk_level VARCHAR(20) NOT NULL,
    FOREIGN KEY (scan_id) REFERENCES scans(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS recommendations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scan_id INT NOT NULL,
    recommendation_text TEXT NOT NULL,
    priority VARCHAR(20) NOT NULL,
    FOREIGN KEY (scan_id) REFERENCES scans(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
