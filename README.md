# AI Git Repo Analyzer

AI Git Repo Analyzer is a PHP and MySQL web application for scanning GitHub repositories and generating analysis reports across security, code quality, testing, architecture, dependency, performance, reliability, DevOps, documentation, and AI-readiness checks.

## Stack

- PHP
- MySQL
- HTML/CSS/JavaScript
- Bootstrap
- jQuery

It scans a repository against roughly 120 weighted checks across these modules:

- Security
- Code Quality
- Clean Code
- Architecture
- Complexity
- Performance
- Reliability
- Testing
- Documentation
- Dependencies
- DevOps
- AI Readiness

## Current Features

- Analyze a GitHub repository using its URL and a GitHub Personal Access Token (PAT)
- Run multiple repository checks from the web UI
- Store repositories, scans, findings, skills, and recommendations in MySQL
- View scan summaries in the dashboard
- Open detailed reports for saved scans in HTML, JSON, TXT, and DOC-style output
- Generate module summaries, scored findings, and recommendation priority guidance
- Use the built-in chat assistant endpoint for report follow-up questions

## Main Files

- `index.php` - main analysis UI
- `dashboard.php` - summary dashboard for saved scans
- `check_insecure_design.php` - detailed check report page
- `api/analyze.php` - main repository analysis endpoint
- `api/report.php` - saved scan report endpoint
- `api/token_status.php` - reports whether a GitHub token is already stored in `.env`
- `api/dashboard_stats.php` - dashboard summary API
- `api/chat_assistant.php` - report chat assistant API
- `api/health.php` - API and database connectivity check
- `config/database.php` - PDO database connection helper
- `config/env.php` - lightweight `.env` loader and writer
- `database/schema.sql` - base MySQL schema
- `database/migration_v2.sql` - optional migration for `check_runs`

## Requirements

- XAMPP or another local PHP 8+ and MySQL environment
- PHP with `pdo_mysql` and `curl` enabled
- MySQL database access
- A GitHub Personal Access Token with access to the target repository

## Prerequisites


- [XAMPP](https://www.apachefriends.org/) with PHP 8+, MySQL or MariaDB, and Apache, or an equivalent local PHP/MySQL setup
- A GitHub Personal Access Token with repository read access

## Environment Setup (.env)

The app reads its GitHub token and database settings from a `.env` file at the project root. That file is loaded automatically by `config/env.php`.

1. Copy the template:
   ```bash
   cp .env.example .env
   ```
2. Open `.env` and fill in your token:
   ```env
   GITHUB_TOKEN=ghp_your_token_here
   ```
3. Adjust the database values only if they differ from the defaults:
   - `DB_HOST` (default `127.0.0.1`)
   - `DB_PORT` (default `3306`)
   - `DB_NAME` (default `repo_analyzer`)
   - `DB_USER` (default `root`)
   - `DB_PASSWORD` (default empty)

`.env` is listed in `.gitignore` and is never committed. Only `.env.example` with blank placeholders is tracked.

You normally do not need to edit `.env` by hand for the token. Once a token is on file, the scan form can reuse it.

- First scan: enter your PAT in the form. On a successful scan, the app saves it to `.env` automatically.
- Later scans: leave the PAT field blank and the saved token from `.env` is reused automatically.
- If the token expires or is revoked: the next scan fails with a clear token error and the form prompts for a replacement token.

## Database Setup

There is no `database/init_db.php` script in the current project.

Initialize the database by importing `database/schema.sql` into MySQL. You can do that with phpMyAdmin or from a terminal.

Example using the MySQL CLI:

```bash
mysql -u root -p < database/schema.sql
```

If you need the per-check run tracking table and it is not already present, also import:

```bash
mysql -u root -p < database/migration_v2.sql
```

The base schema is enough to start. Additional report-related columns and support tables are created automatically on first use by `api/analyze.php`.

## Run The Application

Option A: XAMPP / Apache

Open the application in your browser:

- `http://localhost/ai-git-repo-analyzer/`
- If Apache uses a custom port, include it in the URL, for example `http://localhost:8090/ai-git-repo-analyzer/`

Option B: PHP built-in development server

```bash
php -S localhost:8010
```

Then open `http://localhost:8010/index.php`. MySQL still needs to be running.

Useful pages and endpoints:

- Main UI: `http://localhost/ai-git-repo-analyzer/`
- Dashboard: `http://localhost/ai-git-repo-analyzer/dashboard.php`
- Health check: `http://localhost/ai-git-repo-analyzer/api/health.php`

## Using The App

1. Open the app and paste a GitHub repository URL.
2. Enter your PAT only if one is not already stored.
3. Choose the checks to run, or leave the defaults selected.
4. Start the analysis and review the inline summary.
5. Open the generated full report and dashboard history as needed.

## API Overview

- `POST api/analyze.php` - runs a repository analysis and stores scan results
- `GET api/report.php` - returns or renders a saved scan report
- `GET api/dashboard_stats.php` - returns dashboard summary data
- `POST api/chat_assistant.php` - answers report-related questions from structured scan context
- `GET api/health.php` - verifies API and database connectivity

## Notes

- The current implementation is GitHub-focused. Do not advertise GitLab support unless the code is added.
- The PAT is used for GitHub API access and is stored in `.env`, not in the database.
- `config/database.php` reads database settings from environment variables and falls back to local defaults that work with XAMPP.
- Findings and recommendations are stored in MySQL and surfaced again through the report and dashboard views.
