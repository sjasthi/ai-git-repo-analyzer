# AI Git Repo Analyzer

Web application starter for GitHub repository analysis using:

- PHP (server/API)
- MySQL (database)
- HTML/CSS/JavaScript/jQuery/Bootstrap (frontend)

## Project Structure

- `index.php` - starter UI for repository input and analysis trigger
- `api/health.php` - API + database connectivity check
- `api/analyze.php` - validates GitHub access and creates initial scan records
- `config/database.php` - PDO database connection helper
- `database/schema.sql` - MySQL schema for repositories, scans, findings, skills, recommendations
- `database/init_db.php` - initializes database schema

## XAMPP Setup

1. Copy this project into your XAMPP web root (for example, `C:/xampp/htdocs/ai-git-repo-analyzer`).
2. Start Apache and MySQL from XAMPP Control Panel.
3. Configure database environment variables if needed:
   - `DB_HOST` (default `127.0.0.1`)
   - `DB_PORT` (default `3306`)
   - `DB_NAME` (default `repo_analyzer`)
   - `DB_USER` (default `root`)
   - `DB_PASSWORD` (default empty)

## Initialize Database

Run in terminal from project root:

```bash
php database/init_db.php
```

Or import `database/schema.sql` directly through phpMyAdmin.

## Run Application

Open the app in browser:

- `http://localhost/ai-git-repo-analyzer/`
- If Apache is on a custom port, use that port (example: `http://localhost:8090/ai-git-repo-analyzer/`).

## Notes

- Personal Access Token (PAT) is used for GitHub API requests and is not stored in the database.
- Current implementation creates repository and scan records as the first backend milestone.
