# AI Git Repo Analyzer

Web application for GitHub/GitLab repository analysis using:

- PHP (server/API)
- MySQL (database)
- HTML/CSS/JavaScript/jQuery/Bootstrap (frontend)

It scans a repository against ~120 checks across 12 weighted modules (Security,
Code Quality, Clean Code, Architecture, Complexity, Performance, Reliability,
Testing, Documentation, Dependencies, DevOps, AI Readiness) and produces a
scored report with per-module narrative summaries, a priority matrix
(impact/effort/priority) for recommendations, and collapsible findings.

## Project Structure

- `index.php` - main UI: repository input, check selection, and inline results
- `dashboard.php` - scan history and summary dashboard
- `api/analyze.php` - runs the selected checks and creates scan records
- `api/report.php` - renders a scan's full report (HTML/JSON/TXT/DOC)
- `api/token_status.php` - reports whether a GitHub token is already on file
- `config/database.php` - PDO database connection helper
- `config/env.php` - loads `.env` into the environment; also writes updated values back to it
- `database/schema.sql` - MySQL schema for repositories, scans, findings, skills, recommendations
- `database/init_db.php` - initializes database schema

## Prerequisites

- [XAMPP](https://www.apachefriends.org/) (PHP 8+, MySQL/MariaDB, Apache) — or any local PHP 8+ / MySQL setup.
- A GitHub (or GitLab) [Personal Access Token](https://github.com/settings/tokens) with repo read access.

## Environment Setup (.env)

The app reads its GitHub token and database settings from a `.env` file at the
project root (loaded automatically by `config/env.php`).

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

`.env` is listed in `.gitignore` and is never committed — only `.env.example`
(with blank placeholders) is tracked.

**You normally don't need to edit `.env` by hand for the token.** The scan form's
Personal Access Token field is optional once a token is on file:

- **First scan**: enter your PAT in the form. On a successful scan, the app
  saves it to `.env` automatically.
- **Every scan after that**: leave the PAT field blank — the saved token from
  `.env` is reused automatically (`api/token_status.php` tells the UI whether
  one is on file, and the field's placeholder reflects that).
- **If the token expires or is revoked**: the next scan attempt fails with a
  clear "token is invalid or expired" message, and the form re-prompts for a
  new one. Submitting a new token replaces (swaps) the old value in `.env`.

## Initialize Database

Run in terminal from project root:

```bash
php database/init_db.php
```

Or import `database/schema.sql` directly through phpMyAdmin. (A few
additional tables/columns — `check_runs`, `module_summaries`, and
recommendation impact/effort/priority columns — are created automatically on
first use via `ensureScanReportColumns()` in `api/analyze.php`, so this step
only needs the base schema.)

## Launch the App

**Option A — XAMPP / Apache**

1. Copy or clone this project into your XAMPP web root (for example, `C:/xampp/htdocs/ai-git-repo-analyzer`).
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open in your browser:
   - `http://localhost/ai-git-repo-analyzer/`
   - If Apache is on a custom port, use that port (example: `http://localhost:8090/ai-git-repo-analyzer/`).

**Option B — PHP's built-in dev server**

No Apache config needed; useful for quick local testing. From the project root:

```bash
php -S localhost:8010
```

Then open `http://localhost:8010/index.php`. MySQL still needs to be running
(e.g. via XAMPP's Control Panel) since the app connects to it via
`config/database.php`.

## Using the App

1. Open the app and paste a GitHub/GitLab repository URL.
2. Enter your PAT (only required the first time — see [Environment Setup](#environment-setup-env) above).
3. Pick which checks to run (or leave all selected) and click **Analyze Repository**.
4. Results render inline, and a full report (with module summaries, priority
   matrix, and collapsible findings/recommendations) is available via
   `api/report.php?scan_id=...` — linked automatically after a scan completes.
5. Past scans are listed on `dashboard.php`.

## Notes

- The GitHub PAT is used only for API access; it's stored in `.env` (local
  file, gitignored), never in the database.
- Findings/recommendations are grouped into collapsible sections per category
  in the report view.
