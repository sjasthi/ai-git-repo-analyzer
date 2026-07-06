<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

$savedRepositoryUrls = [];

try {
    $pdo = db_connection();
    $savedRepositoryUrls = $pdo->query(
        'SELECT repo_url FROM repositories ORDER BY created_at DESC'
    )->fetchAll(PDO::FETCH_COLUMN);
    $savedRepositoryUrls = array_values(array_unique(array_filter(array_map(static function ($value) {
        return trim((string) $value);
    }, $savedRepositoryUrls))));
} catch (Throwable $e) {
    $savedRepositoryUrls = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-Assisted Code and Skills Reviewer</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #7C3AED;
            --primary-light: #A78BFA;
            --bg-body: linear-gradient(180deg, #f5f3ff 0%, #faf8ff 100%);
            --text-main: #111827;
            --text-muted: #6B7280;
            --surface: #FFFFFF;
            --surface-soft: #F9FAFB;
            --surface-emphasis: #F3F4F6;
            --border: #E5E7EB;
            --header-gradient: linear-gradient(135deg, #9B59B6 0%, #7C3AED 100%);
            --hero-image: url("assets/images/git-repo-analyzer-banner.png");
            --hero-overlay-start: rgba(7, 12, 24, 0.42);
            --hero-overlay-end: rgba(10, 20, 38, 0.62);
        }

        body[data-theme="dark"] {
            --bg-body: linear-gradient(180deg, #121826 0%, #0d1321 100%);
            --text-main: #E5E7EB;
            --text-muted: #9CA3AF;
            --surface: #1F2937;
            --surface-soft: #111827;
            --surface-emphasis: #0F172A;
            --border: #374151;
            --header-gradient: linear-gradient(135deg, #5B21B6 0%, #312E81 100%);
            --hero-overlay-start: rgba(4, 8, 18, 0.60);
            --hero-overlay-end: rgba(5, 10, 20, 0.72);
        }

        body {
            background: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .header-section {
            background: var(--header-gradient);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .header-section h1 { font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; }
        .header-section p  { font-size: 1.1rem; opacity: 0.9; margin-bottom: 1rem; }

        .header-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; }

        .theme-toggle-btn {
            border-width: 1px;
        }

        .card {
            border: 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            background: var(--surface);
            color: var(--text-main);
        }

        .hero-section {
            margin-bottom: 2rem;
        }

        .hero-card {
            border-radius: 1rem;
            overflow: hidden;
            padding: 0;
            line-height: 0;
            width: 100%;
            height: 100%;
            background: #0b1220;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .hero-image {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: contain;
        }

        .about-card {
            height: 100%;
            margin-bottom: 0;
        }

        .score-badge {
            display: inline-block;
            font-size: 2.5rem;
            font-weight: 700;
            width: 90px;
            height: 90px;
            line-height: 90px;
            text-align: center;
            border-radius: 50%;
            color: white;
        }

        .score-good    { background: #10B981; }
        .score-medium  { background: #F59E0B; }
        .score-low     { background: #EF4444; }

        .severity-High     { color: #DC2626; font-weight: 600; }
        .severity-Medium   { color: #D97706; font-weight: 600; }
        .severity-Low      { color: #16A34A; font-weight: 600; }
        .severity-Info     { color: #4F46E5; font-weight: 600; }

        .pill {
            display: inline-block;
            padding: 0.2rem 0.65rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
        }

        .pill-purple  { background: rgba(124,58,237,0.12); color: #6D28D9; }
        .pill-green   { background: #DCFCE7; color: #166534; }
        .pill-yellow  { background: #FEF3C7; color: #92400E; }
        .pill-red     { background: #FEE2E2; color: #991B1B; }
        .pill-blue    { background: #DBEAFE; color: #1D4ED8; }

        /* Checks summary grid */
        .checks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 0.75rem;
        }

        .check-tile {
            border-radius: 0.75rem;
            padding: 0.85rem 1rem;
            border: 1.5px solid #E5E7EB;
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            background: #fff;
        }

        .check-tile-link {
            display: block;
            text-decoration: none;
            color: inherit;
        }

        .check-tile-link:hover .check-tile,
        .check-tile-link:focus-visible .check-tile {
            border-color: #7C3AED !important;
            box-shadow: 0 0 0 0.2rem rgba(124, 58, 237, 0.15);
        }

        .check-tile.clean   { border-color: #BBF7D0; background: #F0FDF4; }
        .check-tile.issues  { border-color: #FECACA; background: #FFF5F5; }

        .check-tile .check-name  { font-size: 0.78rem; font-weight: 700; color: #374151; }
        .check-tile .check-count { font-size: 1.1rem; font-weight: 700; }
        .check-tile.clean  .check-count { color: #16A34A; }
        .check-tile.issues .check-count { color: #DC2626; }
        .check-tile .check-label { font-size: 0.7rem; color: #6B7280; }

        /* Findings grouped by category */
        .finding-category-header {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 0.5rem 1rem;
            background: var(--surface-soft);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .text-muted { color: var(--text-muted) !important; }

        .border,
        .list-group-item,
        .table thead th,
        .table td,
        .table th,
        .form-control,
        .check-tile {
            border-color: var(--border) !important;
        }

        .bg-light,
        .table,
        .list-group-item,
        .form-control,
        .check-tile {
            background-color: var(--surface-soft) !important;
            color: var(--text-main);
        }

        .form-check-input {
            background-color: var(--surface) !important;
            border-color: var(--border) !important;
            cursor: pointer;
        }

        .form-check-input:checked {
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
        }

        body[data-theme="dark"] .form-check-input:checked {
            background-color: #7C3AED !important;
            border-color: #7C3AED !important;
        }

        .site-footer {
            margin-top: 2.5rem;
            padding: 1.1rem 0;
            background: var(--header-gradient);
            color: white;
            font-size: 0.92rem;
        }

        .site-footer .footer-line {
            margin: 0;
            text-align: center;
            font-weight: 700;
            font-size: 2rem;
            line-height: 1.2;
        }

        .bg-white,
        .check-tile.clean,
        .check-tile.issues {
            background-color: var(--surface) !important;
            color: var(--text-main);
        }

        .form-control::placeholder { color: var(--text-muted); }

        body[data-theme="dark"] .btn-outline-secondary,
        body[data-theme="dark"] .btn-outline-primary,
        body[data-theme="dark"] .btn-outline-success {
            color: #D1D5DB;
            border-color: #6B7280;
        }

        body[data-theme="dark"] .btn-light {
            background-color: #374151;
            border-color: #4B5563;
            color: #F3F4F6;
        }

        #result-section { display: none; }
    </style>
</head>
<body>

<div class="header-section">
    <div class="container">
        <h1><i class="fas fa-code-branch"></i> AI Git Repo Analyzer</h1>
        <p>Submit a GitHub repository to analyze its code quality, skills, and findings.</p>
        <div class="header-actions">
            <a href="index.php" class="btn btn-light btn-sm">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-history"></i> View Scan History
            </a>
            <a href="contact.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-address-card"></i> Contact
            </a>
            <button type="button" id="theme-toggle" class="btn btn-outline-light btn-sm theme-toggle-btn">
                <i class="fas fa-moon"></i> Dark Mode
            </button>
        </div>
    </div>
</div>

<div class="container hero-section">
    <div class="row g-4 align-items-stretch">
        <div class="col-lg-8">
            <div class="hero-card">
                <img class="hero-image" src="assets/images/git-repo-analyzer-banner.png" alt="">
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card p-4 about-card">
                <h2 class="h5 mb-3"><i class="fas fa-info-circle text-purple"></i> About this website </h2>
                <p class="mb-3">AI Git Repo Analyzer helps you inspect a GitHub repository and understand its code quality, detected skills, and potential improvement areas.</p>
                <div class="row row-cols-1 g-3">
                    <div class="col">
                        <div class="p-3 border rounded bg-white">
                            <a href="#analyze-repository-section" class="btn btn-primary btn-sm mb-2">Analyze repositories</a>
                            <p class="mb-0 small text-muted">Submit a repo URL and GitHub PAT to run an AI-assisted review.</p>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-3 border rounded bg-white">
                            <a href="dashboard.php" class="btn btn-primary btn-sm mb-2">Track history</a>
                            <p class="mb-0 small text-muted">Use the dashboard to monitor scan history and summary metrics.</p>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-3 border rounded bg-white">
                            <strong>Detect skills</strong>
                            <p class="mb-0 small text-muted">Review the skills and proficiency levels inferred from the repository.</p>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-3 border rounded bg-white">
                            <strong>View findings</strong>
                            <p class="mb-0 small text-muted">See issues, risks, and detected technologies found in the repository.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="card p-4" id="analyze-repository-section">
                <h2 class="h5 mb-3"><i class="fas fa-search text-purple"></i> Analyze a Repository</h2>

                <form id="analyze-form">
                    <div class="mb-3">
                        <label for="repo_url" class="form-label">GitHub Repository URL</label>
                        <input
                            type="url"
                            id="repo_url"
                            name="repo_url"
                            class="form-control"
                            list="saved-repository-urls"
                            placeholder="https://github.com/owner/repository"
                            required
                        >
                        <datalist id="saved-repository-urls">
                            <?php foreach ($savedRepositoryUrls as $savedRepositoryUrl): ?>
                                <option value="<?= htmlspecialchars($savedRepositoryUrl, ENT_QUOTES, 'UTF-8') ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" value="1" id="remember_repo_url">
                            <label class="form-check-label" for="remember_repo_url">Remember this repository URL</label>
                        </div>
                        <div class="form-text">If checked, the last repository URL will be auto-filled next time.</div>
                        <?php if (!empty($savedRepositoryUrls)): ?>
                            <div class="mt-3">
                                <div class="small fw-semibold mb-2">Saved GitHub Repository URLs</div>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($savedRepositoryUrls as $savedRepositoryUrl): ?>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary use-saved-repo-url"
                                            data-url="<?= htmlspecialchars($savedRepositoryUrl, ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            <?= htmlspecialchars($savedRepositoryUrl, ENT_QUOTES, 'UTF-8') ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="pat" class="form-label">Personal Access Token (PAT)</label>
                        <input
                            type="password"
                            id="pat"
                            name="pat"
                            class="form-control"
                            placeholder="ghp_..."
                            required
                        >
                        <div class="form-text">Used only for GitHub API access — never stored in the database.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Analysis Settings</label>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <button type="button" id="select-all-checks" class="btn btn-sm btn-outline-primary">Select all</button>
                            <button type="button" id="clear-checks" class="btn btn-sm btn-outline-secondary">Clear</button>
                        </div>
                        <div id="checks-selection-status" class="form-text mb-2"></div>
                        <div class="border rounded p-3 bg-light">
                            <div class="row row-cols-1 row-cols-md-2 g-2">
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="dependency_risk" id="check_dependency_risk" checked>
                                            <label class="form-check-label" for="check_dependency_risk"><strong>#1</strong> Insecure Design and Logic Flaws (A04)</label>
                                        </div>
                                        <a href="check_insecure_design.php" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="checks[]" value="hardening" id="check_hardening" checked><label class="form-check-label" for="check_hardening"><strong>#2</strong> Vulnerable and Outdated Dependencies (A06)</label></div>
                                </div>
                                <div class="col">
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="checks[]" value="performance" id="check_performance" checked><label class="form-check-label" for="check_performance"><strong>#3</strong> CI/CD and Software Integrity Risks (A08)</label></div>
                                </div>
                                <div class="col">
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="checks[]" value="maintainability" id="check_maintainability" checked><label class="form-check-label" for="check_maintainability"><strong>#4</strong> Logging and Monitoring Coverage (A09)</label></div>
                                </div>
                                <div class="col">
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="checks[]" value="code_intelligence" id="check_code_intelligence" checked><label class="form-check-label" for="check_code_intelligence"><strong>#5</strong> Code Quality, Performance and Repo Health</label></div>
                                </div>
                                <div class="col">
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="checks[]" value="secret_scanner" id="check_secret_scanner" checked><label class="form-check-label" for="check_secret_scanner"><strong>#6</strong> Secret &amp; Credential Scanner</label></div>
                                </div>
                                <div class="col">
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="checks[]" value="dependency_cve" id="check_dependency_cve" checked><label class="form-check-label" for="check_dependency_cve"><strong>#7</strong> Dependency CVE Audit (OSV.dev)</label></div>
                                </div>
                                <div class="col">
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="checks[]" value="license_check" id="check_license_check" checked><label class="form-check-label" for="check_license_check"><strong>#8</strong> License Compliance Scanner</label></div>
                                </div>
                                <div class="col">
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="checks[]" value="git_history" id="check_git_history" checked><label class="form-check-label" for="check_git_history"><strong>#9</strong> Git History Risk Analysis</label></div>
                                </div>
                                <div class="col">
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="checks[]" value="security_config" id="check_security_config" checked><label class="form-check-label" for="check_security_config"><strong>#10</strong> Security Header &amp; Config Auditor</label></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary" id="submit-btn">
                            <i class="fas fa-play"></i> Analyze Repository
                        </button>
                        <button type="button" id="health-check" class="btn btn-outline-secondary">
                            <i class="fas fa-heartbeat"></i> Check API Health
                        </button>
                    </div>
                </form>

                <div id="status-msg" class="mt-3 text-muted small"></div>
            </div>

            <div id="result-section">
                <div class="card p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div id="score-badge" class="score-badge"></div>
                        <div>
                            <h2 class="h5 mb-1" id="res-name"></h2>
                            <p class="text-muted mb-1 small" id="res-description"></p>
                            <span class="pill pill-purple" id="res-language"></span>
                        </div>
                    </div>
                    <div class="row text-center g-2 mt-1">
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <strong id="res-stars"></strong><br><small class="text-muted">Stars</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <strong id="res-forks"></strong><br><small class="text-muted">Forks</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <strong id="res-watchers"></strong><br><small class="text-muted">Watchers</small>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="dashboard.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-history"></i> View All Scan Records
                        </a>
                        <span id="report-links" class="ms-2"></span>
                    </div>
                </div>

                <!-- Selected Checks -->
                <div class="card p-4" id="selected-checks-card" style="display:none;">
                    <h3 class="h6 mb-3"><i class="fas fa-list text-info"></i> Selected Checks</h3>
                    <ul class="list-group list-group-flush" id="selected-checks-list"></ul>
                </div>

                <!-- Check Results -->
                <div class="card p-4" id="check-results-card" style="display:none;">
                    <h3 class="h6 mb-3"><i class="fas fa-tasks text-info"></i> Check Results</h3>
                    <ul class="list-group list-group-flush" id="check-results-list"></ul>
                </div>

                <!-- Checks Summary -->
                <div class="card p-4" id="checks-card">
                    <h3 class="h6 mb-3"><i class="fas fa-tasks text-primary"></i> Analysis Checks</h3>
                    <div class="checks-grid" id="checks-grid"></div>
                </div>

                <!-- Findings (grouped by category) -->
                <div class="card p-4" id="findings-card">
                    <h3 class="h6 mb-3"><i class="fas fa-exclamation-triangle text-warning"></i> Findings</h3>
                    <div id="findings-list"></div>
                </div>

                <div class="card p-4" id="recommendations-card">
                    <h3 class="h6 mb-3"><i class="fas fa-lightbulb text-success"></i> Recommendations</h3>
                    <ul class="list-group list-group-flush" id="recommendations-list"></ul>
                </div>

                <div class="card p-4" id="skills-card" style="display:none;">
                    <h3 class="h6 mb-3"><i class="fas fa-star text-info"></i> Skills</h3>
                    <ul class="list-group list-group-flush" id="skills-list"></ul>
                </div>

            </div>

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    const THEME_KEY = 'ai_git_repo_theme';
    const REPO_URL_LAST_KEY = 'repo_url_last';
    const REPO_URL_REMEMBER_ENABLED_KEY = 'repo_url_remember_enabled';

    function preferredTheme() {
        const savedTheme = localStorage.getItem(THEME_KEY);
        if (savedTheme === 'light' || savedTheme === 'dark') {
            return savedTheme;
        }
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light';
    }

    function applyTheme(theme) {
        const nextTheme = theme === 'dark' ? 'dark' : 'light';
        $('body').attr('data-theme', nextTheme);

        const icon = nextTheme === 'dark' ? 'fa-sun' : 'fa-moon';
        const label = nextTheme === 'dark' ? 'Light Mode' : 'Dark Mode';
        $('#theme-toggle').html('<i class="fas ' + icon + '"></i> ' + label);
    }

    function initThemeToggle() {
        applyTheme(preferredTheme());
        $('#theme-toggle').on('click', function () {
            const currentTheme = $('body').attr('data-theme') === 'dark' ? 'dark' : 'light';
            const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
            localStorage.setItem(THEME_KEY, nextTheme);
            applyTheme(nextTheme);
        });
    }

    function normalizeRepoUrl(value) {
        return String(value || '').trim();
    }

    function isValidGitHubRepoUrl(value) {
        return /^https?:\/\/github\.com\/[^\/]+\/[^\/]+\/?$/i.test(value);
    }

    function rememberRepoUrl(value) {
        const repoUrl = normalizeRepoUrl(value);
        const rememberEnabled = $('#remember_repo_url').is(':checked');

        localStorage.setItem(REPO_URL_REMEMBER_ENABLED_KEY, rememberEnabled ? '1' : '0');

        if (!rememberEnabled) {
            localStorage.removeItem(REPO_URL_LAST_KEY);
            return;
        }

        if (!isValidGitHubRepoUrl(repoUrl)) {
            return;
        }

        localStorage.setItem(REPO_URL_LAST_KEY, repoUrl);
    }

    function loadRememberedRepoUrl() {
        const rememberEnabled = localStorage.getItem(REPO_URL_REMEMBER_ENABLED_KEY);
        const shouldRemember = rememberEnabled === null ? true : rememberEnabled === '1';
        $('#remember_repo_url').prop('checked', shouldRemember);

        const lastRepoUrl = normalizeRepoUrl(localStorage.getItem(REPO_URL_LAST_KEY) || '');
        if (shouldRemember && lastRepoUrl !== '') {
            $('#repo_url').val(lastRepoUrl);
        }
    }

    function setStatus(msg, isError) {
        $('#status-msg').html(msg).css('color', isError ? '#DC2626' : '#6B7280');
    }

    function scoreBadgeClass(score) {
        if (score >= 80) return 'score-good';
        if (score >= 60) return 'score-medium';
        return 'score-low';
    }

    function priorityPill(priority) {
        const map = { High: 'pill-red', Medium: 'pill-yellow', Low: 'pill-green' };
        return `<span class="pill ${map[priority] || 'pill-purple'}">${priority}</span>`;
    }

    function safeId(value) {
        return String(value || '').replace(/[^a-zA-Z0-9_-]/g, '_');
    }

    const checkIcons = {
        'Secret Scanner':  'fa-key',
        'OWASP':           'fa-shield-alt',
        'Dependencies':    'fa-box-open',
        'Complexity':      'fa-project-diagram',
        'File Summary':    'fa-folder-open',
        'Code Quality':    'fa-clipboard-list',
        'License':         'fa-file-contract',
        'Git History':     'fa-history',
        'Duplication':     'fa-copy',
        'Security Config': 'fa-cog',
    };

    function esc(str) {
        return $('<span>').text(String(str)).html();
    }

    function checkDetailIdFromName(name) {
        const text = String(name || '').toLowerCase();

        const numMatch = text.match(/#\s*(10|[1-9])/);
        if (numMatch && numMatch[1]) {
            return numMatch[1];
        }

        const checks = [
            ['1', /#?1\s*insecure design and logic flaws|insecure design and logic flaws/],
            ['2', /#?2\s*vulnerable and outdated dependencies|vulnerable and outdated dependencies/],
            ['3', /#?3\s*ci\/cd and software integrity risks|software integrity risks/],
            ['4', /#?4\s*logging and monitoring coverage|logging and monitoring/],
            ['5', /#?5\s*code quality, performance and repo health|repo health/],
            ['6', /#?6\s*secret\s*&\s*credential scanner|secret.*credential scanner/],
            ['7', /#?7\s*dependency cve audit|osv\.dev/],
            ['8', /#?8\s*license compliance scanner|license compliance/],
            ['9', /#?9\s*git history risk analysis|git history risk/],
            ['10', /#?10\s*security header\s*&\s*config auditor|security header.*config auditor/],
        ];

        for (const entry of checks) {
            if (entry[1].test(text)) {
                return entry[0];
            }
        }

        return '';
    }

    function checkDetailsUrl(check, scanId) {
        const name = String((check && check.name) || '');
        const checkId = checkDetailIdFromName(name);
        if (!checkId) {
            return '';
        }

        const params = new URLSearchParams({
            check_id: checkId,
            name: name,
            status: String((check && check.status) || ''),
            count: String(Number((check && check.finding_count) || 0)),
        });

        if (scanId) {
            params.set('scan_id', String(scanId));
        }

        return 'check_insecure_design.php?' + params.toString();
    }

    function renderResults(data) {
        const repo  = data.repository || {};
        const scan  = data.scan       || {};
        const score = scan.summary_score ?? 0;

        // Score badge
        $('#score-badge').text(score)
            .removeClass('score-good score-medium score-low')
            .addClass(scoreBadgeClass(score));

        $('#res-name').text(repo.full_name || repo.name || '');
        $('#res-description').text(repo.description || 'No description provided.');
        $('#res-language').text(repo.language || 'Unknown');
        $('#res-stars').text(repo.stars ?? 0);
        $('#res-forks').text(repo.forks ?? 0);
        $('#res-watchers').text(repo.watchers ?? 0);

        const reportLinks = $('#report-links').empty();
        const reportUrls = data.report_urls || {};
        if (reportUrls.summary) {
            reportLinks.append(
                '<a href="' + $('<span>').text(reportUrls.summary).html() + '" target="_blank" class="btn btn-sm btn-outline-success me-2">' +
                '<i class="fas fa-file-lines"></i> Summary URL</a>'
            );
        }
        if (reportUrls.download) {
            reportLinks.append(
                '<a href="' + $('<span>').text(reportUrls.download).html() + '" target="_blank" class="btn btn-sm btn-success">' +
                '<i class="fas fa-download"></i> Download Report</a>'
            );
        }

        // Checks summary tiles
        const checksGrid = $('#checks-grid').empty();
        const allChecksInOrder = [
            '#1 Insecure Design and Logic Flaws',
            '#2 Vulnerable and Outdated Dependencies',
            '#3 CI/CD and Software Integrity Risks',
            '#4 Logging and Monitoring Coverage',
            '#5 Code Quality, Performance and Repo Health',
            '#6 Secret & Credential Scanner',
            '#7 Dependency CVE Audit (OSV.dev)',
            '#8 License Compliance Scanner',
            '#9 Git History Risk Analysis',
            '#10 Security Header & Config Auditor'
        ];

        const checkMap = {};
        if (Array.isArray(data.checks)) {
            data.checks.forEach(function(c) {
                const id = checkDetailIdFromName(c.name || '');
                if (id) {
                    checkMap[id] = c;
                }
            });
        }

        if (allChecksInOrder.length) {
            allChecksInOrder.forEach(function(defaultName) {
                const checkId = checkDetailIdFromName(defaultName);
                const existing = checkId ? checkMap[checkId] : null;
                const c = existing || {
                    name: defaultName,
                    status: 'not_run',
                    finding_count: 0
                };

                const status = String(c.status || 'unknown').toLowerCase();
                const isClean = status === 'clean';
                const isNotRun = status === 'not_run';
                const tileClass = isNotRun ? '' : (isClean ? 'clean' : 'issues');
                const icon = checkIcons[c.name] || 'fa-check-circle';

                const countText = isNotRun
                    ? '-'
                    : String(Number(c.finding_count || 0));

                const labelText = isNotRun
                    ? 'Not run'
                    : (isClean ? 'No issues' : (c.finding_count === 1 ? '1 issue' : c.finding_count + ' issues'));

                const detailsUrl = checkId
                    ? 'check_insecure_design.php?' + new URLSearchParams({
                        check_id: checkId,
                        name: String(c.name || defaultName),
                        status: String(c.status || 'not_run'),
                        count: String(Number(c.finding_count || 0)),
                        scan_id: String(data.scan_id || '')
                    }).toString()
                    : '';

                const tileHtml =
                    `<div class="check-tile ${tileClass}">
                        <span class="check-name"><i class="fas ${icon} me-1"></i>${esc(c.name || defaultName)}</span>
                        <span class="check-count">${esc(countText)}</span>
                        <span class="check-label">${esc(labelText)}</span>
                    </div>`;

                if (detailsUrl) {
                    checksGrid.append(
                        `<a class="check-tile-link" href="${esc(detailsUrl)}" target="_blank" rel="noopener" title="Open check details">${tileHtml}</a>`
                    );
                } else {
                    checksGrid.append(tileHtml);
                }
            });
            $('#checks-card').show();
        } else {
            $('#checks-card').hide();
        }

        // Selected Checks list
        const checkLabels = {
            'dependency_risk': '#1 Insecure Design and Logic Flaws (A04)',
            'hardening': '#2 Vulnerable and Outdated Dependencies (A06)',
            'performance': '#3 CI/CD and Software Integrity Risks (A08)',
            'maintainability': '#4 Logging and Monitoring Coverage (A09)',
            'code_intelligence': '#5 Code Quality, Performance and Repo Health',
            'secret_scanner': '#6 Secret & Credential Scanner',
            'dependency_cve': '#7 Dependency CVE Audit (OSV.dev)',
            'license_check': '#8 License Compliance Scanner',
            'git_history': '#9 Git History Risk Analysis',
            'security_config': '#10 Security Header & Config Auditor'
        };

        const selectedChecksList = $('#selected-checks-list').empty();
        if (data.selected_checks && data.selected_checks.length) {
            data.selected_checks.forEach(function(checkId) {
                const friendlyName = checkLabels[checkId] || checkId;
                selectedChecksList.append(`<li class="list-group-item">${esc(friendlyName)}</li>`);
            });
            $('#selected-checks-card').show();
        } else {
            $('#selected-checks-card').hide();
        }

        // Check Results list
        const checkResultsList = $('#check-results-list').empty();
        if (data.check_runs && data.check_runs.length) {
            data.check_runs.forEach(function(cr) {
                const statusClass = cr.status === 'clean' ? 'text-success' : 'text-danger';
                const statusLabel = cr.status === 'clean' ? 'Clean' : 'Issues found';
                checkResultsList.append(
                    `<li class="list-group-item">
                        <span class="badge ${statusClass}">${statusLabel}</span> 
                        <strong>${esc(cr.check_name)}</strong> 
                        (${cr.finding_count} finding${cr.finding_count !== 1 ? 's' : ''})
                    </li>`
                );
            });
            $('#check-results-card').show();
        } else {
            $('#check-results-card').hide();
        }

        // Findings grouped by category
        const findingsContainer = $('#findings-list').empty();
        if (data.findings && data.findings.length) {
            // Group by category
            const grouped = {};
            data.findings.forEach(function(f) {
                if (!grouped[f.category]) grouped[f.category] = [];
                grouped[f.category].push(f);
            });

            Object.keys(grouped).forEach(function(category) {
                const count = grouped[category].length;
                findingsContainer.append(
                    `<div class="finding-category-header">${esc(category)} — ${count} finding${count !== 1 ? 's' : ''}</div>`
                );
                const ul = $('<ul class="list-group list-group-flush mb-0"></ul>');
                grouped[category].forEach(function(f) {
                    ul.append(
                        `<li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1 me-2">
                                    <strong>${esc(f.title)}</strong>
                                    <span class="severity-${f.severity} ms-2 small">${f.severity}</span>
                                    <p class="mb-0 small text-muted mt-1">${esc(f.description)}</p>
                                </div>
                            </div>
                        </li>`
                    );
                });
                findingsContainer.append(ul);
            });
            $('#findings-card').show();
        } else {
            findingsContainer.append('<p class="text-muted mb-0">No issues found across all checks.</p>');
            $('#findings-card').show();
        }

        // Skills
        const skillsList = $('#skills-list').empty();
        if (data.skills && data.skills.length) {
            data.skills.forEach(function(s) {
                skillsList.append(
                    `<li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><strong>${esc(s.skill_name)}</strong> — ${esc(s.proficiency_level)}</span>
                        ${priorityPill(s.risk_level)}
                    </li>`
                );
            });
            $('#skills-card').show();
        } else {
            $('#skills-card').hide();
        }

        // Recommendations (High priority first)
        const recList = $('#recommendations-list').empty();
        if (data.recommendations && data.recommendations.length) {
            const order = { High: 0, Medium: 1, Low: 2 };
            const sorted = [...data.recommendations].sort((a, b) =>
                (order[a.priority] ?? 3) - (order[b.priority] ?? 3)
            );
            sorted.forEach(function(r) {
                recList.append(
                    `<li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                        <span class="small">${esc(r.recommendation_text)}</span>
                        ${priorityPill(r.priority)}
                    </li>`
                );
            });
            $('#recommendations-card').show();
        } else {
            $('#recommendations-card').hide();
        }

        $('#result-section').show();
        $('html, body').animate({ scrollTop: $('#result-section').offset().top - 20 }, 400);
    }

    $('#health-check').on('click', function () {
        setStatus('Checking API health…');
        $.get('api/health.php')
            .done(function (data) { setStatus('API is healthy: ' + JSON.stringify(data)); })
            .fail(function (xhr) { setStatus('Health check failed: ' + xhr.responseText, true); });
    });

    function updateChecksSelectionStatus() {
        const total = $('input[name="checks[]"]').length;
        const selected = $('input[name="checks[]"]:checked').length;
        $('#checks-selection-status').text(selected + ' of ' + total + ' checks selected');
    }

    $('#select-all-checks').on('click', function () {
        $('input[name="checks[]"]').prop('checked', true).trigger('change');
        updateChecksSelectionStatus();
    });

    $('#clear-checks').on('click', function () {
        $('input[name="checks[]"]').prop('checked', false).trigger('change');
        updateChecksSelectionStatus();
    });

    $(document).on('change', 'input[name="checks[]"]', function () {
        updateChecksSelectionStatus();
    });

    $('#remember_repo_url').on('change', function () {
        if (!$(this).is(':checked')) {
            localStorage.setItem(REPO_URL_REMEMBER_ENABLED_KEY, '0');
            localStorage.removeItem(REPO_URL_LAST_KEY);
        } else {
            localStorage.setItem(REPO_URL_REMEMBER_ENABLED_KEY, '1');
            rememberRepoUrl($('#repo_url').val());
        }
    });

    $(document).on('click', '.use-saved-repo-url', function () {
        const url = normalizeRepoUrl($(this).data('url') || '');
        if (url !== '') {
            $('#repo_url').val(url).trigger('focus');
        }
    });

    $(document).on('click', '.check-nav-link', function (event) {
        event.preventDefault();
        const target = $(this).attr('href');
        if (!target || !$(target).length) {
            return;
        }
        $('html, body').animate({ scrollTop: $(target).offset().top - 20 }, 350);
    });

    $('#analyze-form').on('submit', function (event) {
        event.preventDefault();

        const currentRepoUrl = $('#repo_url').val();
        rememberRepoUrl(currentRepoUrl);

        const btn = $('#submit-btn');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Analyzing…');
        setStatus('Running the selected static analysis checks…');
        $('#result-section').hide();

        $.post('api/analyze.php', $(this).serialize())
            .done(function (data) {
                setStatus('Scan complete. Record saved to database (scan #' + data.scan_id + ').');
                renderResults(data);
            })
            .fail(function (xhr) {
                const err = xhr.responseJSON || { error: 'Request failed', details: xhr.responseText };
                setStatus('Error: ' + (err.error || 'Unknown error') + (err.details ? ' — ' + err.details : ''), true);
            })
            .always(function () {
                btn.prop('disabled', false).html('<i class="fas fa-play"></i> Analyze Repository');
            });
    });

    initThemeToggle();
    updateChecksSelectionStatus();
    loadRememberedRepoUrl();
</script>
<footer class="site-footer">
    <div class="container">
        <p class="footer-line">@2026 AI Git Repo Analyzer</p>
        <p class="footer-line">803 Summer Street, MN 55106</p>
        <p class="footer-line">ContactUs@aigitrepoanalyzer.com</p>
    </div>
</footer>
</body>
</html>
