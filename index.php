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

        .check-group-heading {
            grid-column: 1 / -1;
            font-size: 0.82rem;
            font-weight: 700;
            color: #374151;
            margin-top: 0.35rem;
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

        .checks-folder-header {
            border: 1px solid var(--border);
            border-radius: 0.6rem;
            background: var(--surface-emphasis);
            padding: 0.55rem 0.75rem;
            cursor: pointer;
            user-select: none;
        }

        .checks-folder-header .folder-caret {
            transition: transform 0.18s ease;
        }

        .checks-folder-header.folder-open .folder-caret {
            transform: rotate(90deg);
        }

        .checks-folder-item {
            transition: opacity 0.15s ease;
        }

        .checks-folder-item.folder-hidden {
            display: none;
        }

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

        .details-popup {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 1100;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .details-popup.open {
            display: flex;
        }

        .details-popup-dialog {
            width: min(1000px, 96vw);
            height: min(86vh, 760px);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 0.85rem;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.28);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .details-popup-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.8rem 1rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface-soft);
        }

        .details-popup-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .details-popup-close {
            border: 0;
            background: transparent;
            color: var(--text-main);
            font-size: 1.2rem;
            line-height: 1;
            cursor: pointer;
            padding: 0.2rem 0.35rem;
            border-radius: 0.35rem;
        }

        .details-popup-close:hover,
        .details-popup-close:focus-visible {
            background: rgba(124, 58, 237, 0.14);
            outline: none;
        }

        .details-popup-body {
            flex: 1;
            min-height: 0;
        }

        .details-popup-iframe {
            width: 100%;
            height: 100%;
            border: 0;
            background: #fff;
        }

        .score-display-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .score-help-btn {
            border: 1px solid var(--border);
            background: var(--surface-soft);
            color: var(--text-main);
            width: 1.35rem;
            height: 1.35rem;
            border-radius: 999px;
            padding: 0;
            font-size: 0.78rem;
            font-weight: 700;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .score-help-panel {
            margin-top: 0.45rem;
            padding: 0.55rem 0.7rem;
            border: 1px solid var(--border);
            border-radius: 0.55rem;
            background: var(--surface-soft);
            color: var(--text-muted);
            font-size: 0.78rem;
            line-height: 1.35;
            display: none;
            max-width: 560px;
        }

        .score-help-panel.open {
            display: block;
        }

        #result-section { display: none; }
    </style>
</head>
<body>

<div class="header-section">
    <div class="container">
        <h1><i class="fas fa-code-branch"></i> AI Git Repo Analyzer</h1>
        <p>Submit a GitHub or GitLab repository to analyze its code quality, skills, and findings.</p>
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
                <p class="mb-3">AI Git Repo Analyzer helps you inspect a GitHub or GitLab repository and understand its code quality, detected skills, and potential improvement areas.</p>
                <div class="row row-cols-1 g-3">
                    <div class="col">
                        <div class="p-3 border rounded bg-white">
                            <a href="#analyze-repository-section" class="btn btn-primary btn-sm mb-2">Analyze repositories</a>
                            <p class="mb-0 small text-muted">Submit a repo URL and personal access token to run an AI-assisted review.</p>
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
                        <label for="repo_url" class="form-label">Repository URL (GitHub or GitLab)</label>
                        <input
                            type="url"
                            id="repo_url"
                            name="repo_url"
                            class="form-control"
                            list="saved-repository-urls"
                            placeholder="https://github.com/owner/repository or https://gitlab.com/group/repository"
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
                                <div class="small fw-semibold mb-2">Saved Repository URLs</div>
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
                        <div class="form-text">Used only for API access — never stored in the database.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Analysis Settings</label>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <button type="button" id="select-all-checks" class="btn btn-sm btn-outline-primary">Select all</button>
                            <button type="button" id="clear-checks" class="btn btn-sm btn-outline-secondary">Clear</button>
                        </div>
                        <div id="checks-selection-status" class="form-text mb-2"></div>
                        <input type="hidden" name="checks_present" value="1">
                        <div class="border rounded p-3 bg-light">
                            <div class="row row-cols-1 row-cols-md-2 g-2">
                                <div class="col-12 w-100 mt-2">
                                    <div class="h6 fw-bold mb-1">OWASP Checks</div>
                                    <div class="small text-muted mb-1">Source: <a href="https://owasp.org/www-project-top-ten/" target="_blank" rel="noopener noreferrer">OWASP Top 10 (2021)</a></div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="dependency_risk" id="check_dependency_risk" checked>
                                            <label class="form-check-label" for="check_dependency_risk"><strong>#1</strong> Insecure Design and Logic Flaws (A04)</label>
                                        </div>
                                        <a href="check_insecure_design.php" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="hardening" id="check_hardening" checked>
                                            <label class="form-check-label" for="check_hardening"><strong>#2</strong> Vulnerable and Outdated Dependencies (A06)</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=2" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="performance" id="check_performance" checked>
                                            <label class="form-check-label" for="check_performance"><strong>#3</strong> CI/CD and Software Integrity Risks (A08)</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=3" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="maintainability" id="check_maintainability" checked>
                                            <label class="form-check-label" for="check_maintainability"><strong>#4</strong> Logging and Monitoring Coverage (A09)</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=4" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="code_intelligence" id="check_code_intelligence" checked>
                                            <label class="form-check-label" for="check_code_intelligence"><strong>#5</strong> Code Quality, Performance and Repo Health</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=5" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="secret_scanner" id="check_secret_scanner" checked>
                                            <label class="form-check-label" for="check_secret_scanner"><strong>#6</strong> Secret &amp; Credential Scanner</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=6" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="dependency_cve" id="check_dependency_cve" checked>
                                            <label class="form-check-label" for="check_dependency_cve"><strong>#7</strong> Dependency CVE Audit (OSV.dev)</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=7" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="license_check" id="check_license_check" checked>
                                            <label class="form-check-label" for="check_license_check"><strong>#8</strong> License Compliance Scanner</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=8" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="git_history" id="check_git_history" checked>
                                            <label class="form-check-label" for="check_git_history"><strong>#9</strong> Git History Risk Analysis</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=9" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="security_config" id="check_security_config" checked>
                                            <label class="form-check-label" for="check_security_config"><strong>#10</strong> Security Header &amp; Config Auditor</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=10" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col-12 w-100 mt-2">
                                    <div class="h6 fw-bold mb-1 text-start">Complexity Checks (Weight: 10%)</div>
                                    <div class="small text-muted mb-1">Source: McCabe Cyclomatic + Cognitive Complexity metrics</div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="complexity_cyclomatic_avg" id="check_complexity_cyclomatic_avg" checked>
                                            <label class="form-check-label" for="check_complexity_cyclomatic_avg"><strong>#11</strong> Cyclomatic Complexity Average</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=11" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="complexity_cyclomatic_max" id="check_complexity_cyclomatic_max" checked>
                                            <label class="form-check-label" for="check_complexity_cyclomatic_max"><strong>#12</strong> Cyclomatic Complexity Maximum</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=12" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="complexity_cognitive_avg" id="check_complexity_cognitive_avg" checked>
                                            <label class="form-check-label" for="check_complexity_cognitive_avg"><strong>#13</strong> Cognitive Complexity Average</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=13" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="complexity_cognitive_max" id="check_complexity_cognitive_max" checked>
                                            <label class="form-check-label" for="check_complexity_cognitive_max"><strong>#14</strong> Cognitive Complexity Maximum</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=14" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="complexity_function_size_avg" id="check_complexity_function_size_avg" checked>
                                            <label class="form-check-label" for="check_complexity_function_size_avg"><strong>#15</strong> Function Size Average</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=15" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="complexity_function_size_max" id="check_complexity_function_size_max" checked>
                                            <label class="form-check-label" for="check_complexity_function_size_max"><strong>#16</strong> Function Size Maximum</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=16" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="complexity_class_size_avg" id="check_complexity_class_size_avg" checked>
                                            <label class="form-check-label" for="check_complexity_class_size_avg"><strong>#17</strong> Class Size Average</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=17" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="complexity_class_size_max" id="check_complexity_class_size_max" checked>
                                            <label class="form-check-label" for="check_complexity_class_size_max"><strong>#18</strong> Class Size Maximum</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=18" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="complexity_nesting_depth_avg" id="check_complexity_nesting_depth_avg" checked>
                                            <label class="form-check-label" for="check_complexity_nesting_depth_avg"><strong>#19</strong> Nesting Depth Average</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=19" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="complexity_nesting_depth_max" id="check_complexity_nesting_depth_max" checked>
                                            <label class="form-check-label" for="check_complexity_nesting_depth_max"><strong>#20</strong> Nesting Depth Maximum</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=20" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col-12 w-100 mt-2">
                                    <div class="h6 fw-bold mb-1">SonarQube Rules (Code Quality)</div>
                                    <div class="small text-muted mb-1">Source: <a href="https://docs.sonarsource.com/sonarqube-server/quality-standards-administration/managing-rules/rules" target="_blank" rel="noopener noreferrer">SonarQube Rules Management</a></div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="sonar_bugs_reliability" id="sonar_check_1" checked>
                                            <label class="form-check-label" for="sonar_check_1"><strong>#21</strong> Bugs and Reliability Issues</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=21" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="sonar_code_smells" id="sonar_check_2" checked>
                                            <label class="form-check-label" for="sonar_check_2"><strong>#22</strong> Code Smells and Maintainability Issues</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=22" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="sonar_duplication_detection" id="sonar_check_3" checked>
                                            <label class="form-check-label" for="sonar_check_3"><strong>#23</strong> Duplicated Code Detection</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=23" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="sonar_complexity_limits" id="sonar_check_4" checked>
                                            <label class="form-check-label" for="sonar_check_4"><strong>#24</strong> Cyclomatic and Cognitive Complexity Limits</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=24" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="sonar_size_control" id="sonar_check_5" checked>
                                            <label class="form-check-label" for="sonar_check_5"><strong>#25</strong> Function and Class Size Control</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=25" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="sonar_naming_readability" id="sonar_check_6" checked>
                                            <label class="form-check-label" for="sonar_check_6"><strong>#26</strong> Naming Convention and Readability Checks</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=26" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="sonar_dead_code" id="sonar_check_7" checked>
                                            <label class="form-check-label" for="sonar_check_7"><strong>#27</strong> Dead or Commented-Out Code Detection</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=27" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="sonar_error_handling" id="sonar_check_8" checked>
                                            <label class="form-check-label" for="sonar_check_8"><strong>#28</strong> Error Handling and Defensive Coding Patterns</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=28" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="sonar_technical_debt" id="sonar_check_9" checked>
                                            <label class="form-check-label" for="sonar_check_9"><strong>#29</strong> Technical Debt and Remediation Tracking</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=29" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="sonar_quality_gate_summary" id="sonar_check_10" checked>
                                            <label class="form-check-label" for="sonar_check_10"><strong>#30</strong> Quality Gate Compliance Summary</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=30" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col-12 w-100 mt-2">
                                    <div class="h6 fw-bold mb-1 text-start">Clean Code Checks (Weight: 10%)</div>
                                    <div class="small text-muted mb-1">Source: <a href="https://www.oreilly.com/library/view/clean-code-a/9780136083238/" target="_blank" rel="noopener noreferrer">Clean Code (O'Reilly)</a></div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="clean_code_solid" id="clean_code_1" checked>
                                            <label class="form-check-label" for="clean_code_1"><strong>#31</strong> SOLID Principles</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=31" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="clean_code_dry" id="clean_code_2" checked>
                                            <label class="form-check-label" for="clean_code_2"><strong>#32</strong> DRY Principle</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=32" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="clean_code_kiss" id="clean_code_3" checked>
                                            <label class="form-check-label" for="clean_code_3"><strong>#33</strong> KISS Principle</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=33" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="clean_code_yagni" id="clean_code_4" checked>
                                            <label class="form-check-label" for="clean_code_4"><strong>#34</strong> YAGNI Principle</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=34" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="clean_code_single_responsibility" id="clean_code_5" checked>
                                            <label class="form-check-label" for="clean_code_5"><strong>#35</strong> Single Responsibility</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=35" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="clean_code_separation_of_concerns" id="clean_code_6" checked>
                                            <label class="form-check-label" for="clean_code_6"><strong>#36</strong> Separation of Concerns</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=36" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="clean_code_meaningful_names" id="clean_code_7" checked>
                                            <label class="form-check-label" for="clean_code_7"><strong>#37</strong> Meaningful Naming</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=37" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="clean_code_small_functions" id="clean_code_8" checked>
                                            <label class="form-check-label" for="clean_code_8"><strong>#38</strong> Small Functions</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=38" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="clean_code_formatting" id="clean_code_9" checked>
                                            <label class="form-check-label" for="clean_code_9"><strong>#39</strong> Consistent Formatting</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=39" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="clean_code_error_handling" id="clean_code_10" checked>
                                            <label class="form-check-label" for="clean_code_10"><strong>#40</strong> Explicit Error Handling</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=40" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col-12 w-100 mt-2">
                                    <div class="h6 fw-bold mb-1 text-start">Architecture Checks (Weight: 10%)</div>
                                    <div class="small text-muted mb-1">Source: <a href="https://www.pearson.com/en-us/subject-catalog/p/clean-architecture-a-craftsmans-guide-to-software-structure-and-design/P200000009528/9780134494326" target="_blank" rel="noopener noreferrer">Clean Architecture (Pearson)</a></div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="architecture_layered_boundaries" id="architecture_1" checked>
                                            <label class="form-check-label" for="architecture_1"><strong>#41</strong> Clean Architecture Layered Boundaries</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=41" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="architecture_dependency_rule" id="architecture_2" checked>
                                            <label class="form-check-label" for="architecture_2"><strong>#42</strong> Clean Architecture Dependency Rule</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=42" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="architecture_framework_independence" id="architecture_3" checked>
                                            <label class="form-check-label" for="architecture_3"><strong>#43</strong> Clean Architecture Framework Independence</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=43" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="architecture_presentation_isolation" id="architecture_4" checked>
                                            <label class="form-check-label" for="architecture_4"><strong>#44</strong> Clean Architecture Presentation Isolation</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=44" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="architecture_use_case_separation" id="architecture_5" checked>
                                            <label class="form-check-label" for="architecture_5"><strong>#45</strong> Clean Architecture Use Case Separation</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=45" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="architecture_domain_purity" id="architecture_6" checked>
                                            <label class="form-check-label" for="architecture_6"><strong>#46</strong> Clean Architecture Domain Purity</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=46" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="architecture_data_access_abstraction" id="architecture_7" checked>
                                            <label class="form-check-label" for="architecture_7"><strong>#47</strong> Clean Architecture Data Access Abstraction</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=47" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="architecture_interface_adapter_separation" id="architecture_8" checked>
                                            <label class="form-check-label" for="architecture_8"><strong>#48</strong> Clean Architecture Interface Adapter Separation</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=48" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="architecture_package_cohesion" id="architecture_9" checked>
                                            <label class="form-check-label" for="architecture_9"><strong>#49</strong> Clean Architecture Package Cohesion</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=49" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="architecture_no_cyclic_dependencies" id="architecture_10" checked>
                                            <label class="form-check-label" for="architecture_10"><strong>#50</strong> Clean Architecture No Cyclic Dependencies</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=50" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col-12 w-100 mt-2">
                                    <div class="h6 fw-bold mb-1 text-start">Testing Checks (Weight: 10%)</div>
                                    <div class="small text-muted mb-1">Source: <a href="https://martinfowler.com/bliki/TestPyramid.html" target="_blank" rel="noopener noreferrer">Test Pyramid (Martin Fowler)</a>, <a href="https://circleci.com/blog/testing-pyramid/" target="_blank" rel="noopener noreferrer">Testing Pyramid (CircleCI)</a></div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="testing_unit_coverage" id="testing_1" checked>
                                            <label class="form-check-label" for="testing_1"><strong>#51</strong> Test Pyramid Unit Coverage</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=51" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="testing_integration_coverage" id="testing_2" checked>
                                            <label class="form-check-label" for="testing_2"><strong>#52</strong> Test Pyramid Integration Coverage</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=52" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="testing_end_to_end_coverage" id="testing_3" checked>
                                            <label class="form-check-label" for="testing_3"><strong>#53</strong> Test Pyramid End-to-End Coverage</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=53" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="testing_fast_feedback" id="testing_4" checked>
                                            <label class="form-check-label" for="testing_4"><strong>#54</strong> Test Pyramid Fast Feedback</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=54" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="testing_mocking_external_apis" id="testing_5" checked>
                                            <label class="form-check-label" for="testing_5"><strong>#55</strong> Test Pyramid Mocking External APIs</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=55" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="testing_database_isolation" id="testing_6" checked>
                                            <label class="form-check-label" for="testing_6"><strong>#56</strong> Test Pyramid Database Test Isolation</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=56" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="testing_api_response_validation" id="testing_7" checked>
                                            <label class="form-check-label" for="testing_7"><strong>#57</strong> Test Pyramid API Response Validation</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=57" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="testing_error_path_testing" id="testing_8" checked>
                                            <label class="form-check-label" for="testing_8"><strong>#58</strong> Test Pyramid Error Path Testing</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=58" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="testing_regression_coverage" id="testing_9" checked>
                                            <label class="form-check-label" for="testing_9"><strong>#59</strong> Test Pyramid Regression Test Coverage</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=59" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-check d-flex align-items-center justify-content-between gap-2">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="checks[]" value="testing_organization_maintainability" id="testing_10" checked>
                                            <label class="form-check-label" for="testing_10"><strong>#60</strong> Test Pyramid Test Organization and Maintainability</label>
                                        </div>
                                        <a href="check_insecure_design.php?check_id=60" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a>
                                    </div>
                                </div>
                                <div class="col-12 w-100 mt-2">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                        <div class="h6 fw-bold mb-0 text-start">Performance Checks (Weight: 10%)</div>
                                    </div>
                                    <div class="small text-muted mb-1">Source: Performance analysis checklist from repository standards</div>
                                </div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="performance_nested_loops" id="performance_1" checked><label class="form-check-label" for="performance_1"><strong>#61</strong> Nested Loops and Deep Iterations</label></div><a href="check_insecure_design.php?check_id=61" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="performance_expensive_operations" id="performance_2" checked><label class="form-check-label" for="performance_2"><strong>#62</strong> Expensive Operation Hotspots</label></div><a href="check_insecure_design.php?check_id=62" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="performance_n_plus_one_patterns" id="performance_3" checked><label class="form-check-label" for="performance_3"><strong>#63</strong> N+1 and Repeated Data Access Patterns</label></div><a href="check_insecure_design.php?check_id=63" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="performance_repeated_api_calls" id="performance_4" checked><label class="form-check-label" for="performance_4"><strong>#64</strong> Repeated External API Calls</label></div><a href="check_insecure_design.php?check_id=64" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="performance_blocking_operations" id="performance_5" checked><label class="form-check-label" for="performance_5"><strong>#65</strong> Blocking Operation Risks</label></div><a href="check_insecure_design.php?check_id=65" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="performance_unbounded_queries" id="performance_6" checked><label class="form-check-label" for="performance_6"><strong>#66</strong> Unbounded Query and Scan Risks</label></div><a href="check_insecure_design.php?check_id=66" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="performance_large_payloads" id="performance_7" checked><label class="form-check-label" for="performance_7"><strong>#67</strong> Large Payload and Serialization Costs</label></div><a href="check_insecure_design.php?check_id=67" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="performance_cache_miss_risk" id="performance_8" checked><label class="form-check-label" for="performance_8"><strong>#68</strong> Cache Strategy and Miss Risks</label></div><a href="check_insecure_design.php?check_id=68" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="performance_sync_io_hotspots" id="performance_9" checked><label class="form-check-label" for="performance_9"><strong>#69</strong> Synchronous I/O Hotspots</label></div><a href="check_insecure_design.php?check_id=69" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="performance_build_runtime_cost" id="performance_10" checked><label class="form-check-label" for="performance_10"><strong>#70</strong> Build and Runtime Efficiency Controls</label></div><a href="check_insecure_design.php?check_id=70" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>

                                <div class="col-12 w-100 mt-2">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                        <div class="h6 fw-bold mb-0 text-start">Reliability Checks (Weight: 10%)</div>
                                    </div>
                                    <div class="small text-muted mb-1">Source: Google Site Reliability Engineering (SRE) concepts</div>
                                </div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="reliability_logging_coverage" id="reliability_1" checked><label class="form-check-label" for="reliability_1"><strong>#71</strong> Logging Coverage and Signal Quality</label></div><a href="check_insecure_design.php?check_id=71" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="reliability_retry_strategy" id="reliability_2" checked><label class="form-check-label" for="reliability_2"><strong>#72</strong> Retry Strategy and Backoff Safety</label></div><a href="check_insecure_design.php?check_id=72" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="reliability_timeout_controls" id="reliability_3" checked><label class="form-check-label" for="reliability_3"><strong>#73</strong> Timeout and Circuit Controls</label></div><a href="check_insecure_design.php?check_id=73" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="reliability_exception_handling" id="reliability_4" checked><label class="form-check-label" for="reliability_4"><strong>#74</strong> Exception Handling Discipline</label></div><a href="check_insecure_design.php?check_id=74" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="reliability_null_safety" id="reliability_5" checked><label class="form-check-label" for="reliability_5"><strong>#75</strong> Null Safety and Defensive Guards</label></div><a href="check_insecure_design.php?check_id=75" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="reliability_resource_cleanup" id="reliability_6" checked><label class="form-check-label" for="reliability_6"><strong>#76</strong> Resource Cleanup and Lifecycle Safety</label></div><a href="check_insecure_design.php?check_id=76" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="reliability_input_validation" id="reliability_7" checked><label class="form-check-label" for="reliability_7"><strong>#77</strong> Input Validation and Sanitization</label></div><a href="check_insecure_design.php?check_id=77" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="reliability_idempotency" id="reliability_8" checked><label class="form-check-label" for="reliability_8"><strong>#78</strong> Idempotency and Duplicate Request Safety</label></div><a href="check_insecure_design.php?check_id=78" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="reliability_fallback_paths" id="reliability_9" checked><label class="form-check-label" for="reliability_9"><strong>#79</strong> Fallback and Degradation Paths</label></div><a href="check_insecure_design.php?check_id=79" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="reliability_observability_alerting" id="reliability_10" checked><label class="form-check-label" for="reliability_10"><strong>#80</strong> Observability and Alerting Readiness</label></div><a href="check_insecure_design.php?check_id=80" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>

                                <div class="col-12 w-100 mt-2">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                        <div class="h6 fw-bold mb-0 text-start">Documentation Checks (Weight: 5%)</div>
                                    </div>
                                    <div class="small text-muted mb-1">Source: README best practices, API docs, ADR, and documentation quality standards</div>
                                </div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="testing_first_principles" id="testing_plus_1" checked><label class="form-check-label" for="testing_plus_1"><strong>#81</strong> README Completeness and Clarity</label></div><a href="check_insecure_design.php?check_id=81" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="testing_aaa_pattern" id="testing_plus_2" checked><label class="form-check-label" for="testing_plus_2"><strong>#82</strong> Installation and Usage Guide Quality</label></div><a href="check_insecure_design.php?check_id=82" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="testing_test_data_management" id="testing_plus_3" checked><label class="form-check-label" for="testing_plus_3"><strong>#83</strong> API Reference and Endpoint Notes</label></div><a href="check_insecure_design.php?check_id=83" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="testing_flaky_test_risk" id="testing_plus_4" checked><label class="form-check-label" for="testing_plus_4"><strong>#84</strong> Architecture and Design Decision Notes</label></div><a href="check_insecure_design.php?check_id=84" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="testing_boundary_case_coverage" id="testing_plus_5" checked><label class="form-check-label" for="testing_plus_5"><strong>#85</strong> Changelog and Release Notes Hygiene</label></div><a href="check_insecure_design.php?check_id=85" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="testing_contract_validation" id="testing_plus_6" checked><label class="form-check-label" for="testing_plus_6"><strong>#86</strong> Configuration and Environment Guide</label></div><a href="check_insecure_design.php?check_id=86" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="testing_security_paths" id="testing_plus_7" checked><label class="form-check-label" for="testing_plus_7"><strong>#87</strong> Security and Compliance Notes</label></div><a href="check_insecure_design.php?check_id=87" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="testing_performance_paths" id="testing_plus_8" checked><label class="form-check-label" for="testing_plus_8"><strong>#88</strong> Troubleshooting and FAQ Coverage</label></div><a href="check_insecure_design.php?check_id=88" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="testing_ci_gate_readiness" id="testing_plus_9" checked><label class="form-check-label" for="testing_plus_9"><strong>#89</strong> Contribution and Workflow Guidelines</label></div><a href="check_insecure_design.php?check_id=89" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="testing_suite_maintainability" id="testing_plus_10" checked><label class="form-check-label" for="testing_plus_10"><strong>#90</strong> Documentation Freshness and Maintainability</label></div><a href="check_insecure_design.php?check_id=90" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>

                                <div class="col-12 w-100 mt-2">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                        <div class="h6 fw-bold mb-0 text-start">Dependency SBOM Checks (Weight: 5%)</div>
                                    </div>
                                    <div class="small text-muted mb-1">
                                        Source:
                                        <a href="https://spdx.github.io/spdx-spec/" target="_blank" rel="noopener noreferrer">SPDX Specification</a>,
                                        <a href="https://cyclonedx.org/capabilities/sbom/" target="_blank" rel="noopener noreferrer">CycloneDX SBOM</a>,
                                        and
                                        <a href="https://www.cisa.gov/topics/information-communications-technology-supply-chain-security/sbom" target="_blank" rel="noopener noreferrer">CISA SBOM Resources</a>
                                        (including NTIA Minimum Elements)
                                    </div>
                                </div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="dependency_inventory_accuracy" id="dependency_sbom_1" checked><label class="form-check-label" for="dependency_sbom_1"><strong>#91</strong> Dependency Inventory</label></div><a href="check_insecure_design.php?check_id=91" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="dependency_identity_normalization" id="dependency_sbom_2" checked><label class="form-check-label" for="dependency_sbom_2"><strong>#92</strong> Vulnerability Detection</label></div><a href="check_insecure_design.php?check_id=92" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="dependency_graph_mapping" id="dependency_sbom_3" checked><label class="form-check-label" for="dependency_sbom_3"><strong>#93</strong> License Compliance</label></div><a href="check_insecure_design.php?check_id=93" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="dependency_vulnerability_correlation" id="dependency_sbom_4" checked><label class="form-check-label" for="dependency_sbom_4"><strong>#94</strong> Supply Chain Security</label></div><a href="check_insecure_design.php?check_id=94" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="dependency_license_risk" id="dependency_sbom_5" checked><label class="form-check-label" for="dependency_sbom_5"><strong>#95</strong> Version Tracking</label></div><a href="check_insecure_design.php?check_id=95" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="dependency_provenance_traceability" id="dependency_sbom_6" checked><label class="form-check-label" for="dependency_sbom_6"><strong>#96</strong> Risk Assessment</label></div><a href="check_insecure_design.php?check_id=96" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="dependency_integrity_verification" id="dependency_sbom_7" checked><label class="form-check-label" for="dependency_sbom_7"><strong>#97</strong> Dependency Mapping</label></div><a href="check_insecure_design.php?check_id=97" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="dependency_sbom_format_quality" id="dependency_sbom_8" checked><label class="form-check-label" for="dependency_sbom_8"><strong>#98</strong> Compliance and Auditing</label></div><a href="check_insecure_design.php?check_id=98" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="dependency_sbom_automation" id="dependency_sbom_9" checked><label class="form-check-label" for="dependency_sbom_9"><strong>#99</strong> Continuous SBOM Automation</label></div><a href="check_insecure_design.php?check_id=99" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="dependency_drift_unused" id="dependency_sbom_10" checked><label class="form-check-label" for="dependency_sbom_10"><strong>#100</strong> Software Transparency</label></div><a href="check_insecure_design.php?check_id=100" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>

                                <div class="col-12 w-100 mt-2">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                        <div class="h6 fw-bold mb-0 text-start">DevOps Readiness Checks (Weight: 5%)</div>
                                    </div>
                                    <div class="small text-muted mb-1">Source: <a href="https://docs.github.com/en/actions/security-for-github-actions/security-guides/security-hardening-for-github-actions" target="_blank" rel="noopener noreferrer">GitHub Actions Best Practices</a></div>
                                </div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="devops_ci_cd_pipeline" id="devops_1" checked><label class="form-check-label" for="devops_1"><strong>#101</strong> CI/CD Pipeline Coverage</label></div><a href="check_insecure_design.php?check_id=101" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="devops_docker_readiness" id="devops_2" checked><label class="form-check-label" for="devops_2"><strong>#102</strong> Docker Build Readiness</label></div><a href="check_insecure_design.php?check_id=102" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="devops_secrets_hygiene" id="devops_3" checked><label class="form-check-label" for="devops_3"><strong>#103</strong> Secrets Handling in Pipelines</label></div><a href="check_insecure_design.php?check_id=103" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="devops_env_configuration" id="devops_4" checked><label class="form-check-label" for="devops_4"><strong>#104</strong> Environment Configuration Management</label></div><a href="check_insecure_design.php?check_id=104" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="devops_release_workflow" id="devops_5" checked><label class="form-check-label" for="devops_5"><strong>#105</strong> Release Workflow Automation</label></div><a href="check_insecure_design.php?check_id=105" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="devops_actions_security" id="devops_6" checked><label class="form-check-label" for="devops_6"><strong>#106</strong> GitHub Actions Security Hardening</label></div><a href="check_insecure_design.php?check_id=106" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="devops_branch_pr_signals" id="devops_7" checked><label class="form-check-label" for="devops_7"><strong>#107</strong> Pull Request and Branch Quality Gates</label></div><a href="check_insecure_design.php?check_id=107" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="devops_deployment_automation" id="devops_8" checked><label class="form-check-label" for="devops_8"><strong>#108</strong> Deployment Automation Signals</label></div><a href="check_insecure_design.php?check_id=108" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="devops_observability_ops" id="devops_9" checked><label class="form-check-label" for="devops_9"><strong>#109</strong> Operational Observability Hooks</label></div><a href="check_insecure_design.php?check_id=109" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
                                <div class="col"><div class="form-check d-flex align-items-center justify-content-between gap-2"><div><input class="form-check-input" type="checkbox" name="checks[]" value="devops_incident_recovery_docs" id="devops_10" checked><label class="form-check-label" for="devops_10"><strong>#110</strong> Runbook and Recovery Documentation</label></div><a href="check_insecure_design.php?check_id=110" class="btn btn-sm btn-outline-primary check-details-trigger">Details</a></div></div>
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
                            <div class="mt-2">
                                <div class="score-display-row">
                                    <div id="score-display" class="small fw-semibold">0/100</div>
                                    <button
                                        type="button"
                                        id="score-help-btn"
                                        class="score-help-btn"
                                        aria-label="How score is calculated"
                                        title="How score is calculated"
                                    >?</button>
                                </div>
                                <div id="score-breakdown-quick" class="small text-muted mt-1"></div>
                                <div id="score-help-panel" class="score-help-panel" aria-hidden="true"></div>
                            </div>
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
                    <h3 class="h6 mb-3 d-flex align-items-center justify-content-between gap-2">
                        <span><i class="fas fa-star text-info"></i> Skills</span>
                        <span id="skills-total-count" class="pill pill-blue">0</span>
                    </h3>
                    <ul class="list-group list-group-flush" id="skills-list"></ul>
                </div>

            </div>

        </div>
    </div>
</div>

<div id="check-details-popup" class="details-popup" aria-hidden="true">
    <div class="details-popup-dialog" role="dialog" aria-modal="true" aria-labelledby="check-details-title">
        <div class="details-popup-header">
            <h3 id="check-details-title" class="details-popup-title">Check Details</h3>
            <button type="button" id="check-details-close" class="details-popup-close" aria-label="Close details popup">
                <i class="fas fa-xmark"></i>
            </button>
        </div>
        <div class="details-popup-body">
            <iframe id="check-details-iframe" class="details-popup-iframe" src="about:blank" title="Check Details"></iframe>
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

    function isValidRepositoryUrl(value) {
        return /^https?:\/\/(github\.com|gitlab\.com)\/.+\/.+/i.test(value)
            || /^git@(github\.com|gitlab\.com):.+\/.+$/i.test(value)
            || /^(github\.com|gitlab\.com)\/.+\/.+/i.test(value);
    }

    function rememberRepoUrl(value) {
        const repoUrl = normalizeRepoUrl(value);
        const rememberEnabled = $('#remember_repo_url').is(':checked');

        localStorage.setItem(REPO_URL_REMEMBER_ENABLED_KEY, rememberEnabled ? '1' : '0');

        if (!rememberEnabled) {
            localStorage.removeItem(REPO_URL_LAST_KEY);
            return;
        }

        if (!isValidRepositoryUrl(repoUrl)) {
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

    let latestScanData = null;

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
        'Architecture':    'fa-layer-group',
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

        const numMatch = text.match(/#\s*(\d{1,3})/);
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
            ['21', /#?21\s*sonarqube bugs and reliability issues|bugs and reliability issues/],
            ['22', /#?22\s*sonarqube code smells and maintainability issues|code smells and maintainability issues/],
            ['23', /#?23\s*sonarqube duplicated code detection|duplicated code detection/],
            ['24', /#?24\s*sonarqube cyclomatic and cognitive complexity limits|cyclomatic and cognitive complexity limits/],
            ['25', /#?25\s*sonarqube function and class size control|function and class size control/],
            ['26', /#?26\s*sonarqube naming convention and readability checks|naming convention and readability checks/],
            ['27', /#?27\s*sonarqube dead or commented-out code detection|dead or commented-out code detection/],
            ['28', /#?28\s*sonarqube error handling and defensive coding patterns|error handling and defensive coding patterns/],
            ['29', /#?29\s*sonarqube technical debt and remediation tracking|technical debt and remediation tracking/],
            ['30', /#?30\s*sonarqube quality gate compliance summary|quality gate compliance summary/],
            ['31', /#?31\s*clean code solid principles|solid principles/],
            ['32', /#?32\s*clean code dry principle|dry principle/],
            ['33', /#?33\s*clean code kiss principle|kiss principle/],
            ['34', /#?34\s*clean code yagni principle|yagni principle/],
            ['35', /#?35\s*clean code single responsibility|single responsibility/],
            ['36', /#?36\s*clean code separation of concerns|separation of concerns/],
            ['37', /#?37\s*clean code meaningful naming|meaningful naming/],
            ['38', /#?38\s*clean code small functions|small functions/],
            ['39', /#?39\s*clean code consistent formatting|consistent formatting/],
            ['40', /#?40\s*clean code explicit error handling|explicit error handling/],
            ['41', /#?41\s*clean architecture layered boundaries|layered boundaries/],
            ['42', /#?42\s*clean architecture dependency rule|dependency rule/],
            ['43', /#?43\s*clean architecture framework independence|framework independence/],
            ['44', /#?44\s*clean architecture presentation isolation|presentation isolation/],
            ['45', /#?45\s*clean architecture use case separation|use case separation/],
            ['46', /#?46\s*clean architecture domain purity|domain purity/],
            ['47', /#?47\s*clean architecture data access abstraction|data access abstraction/],
            ['48', /#?48\s*clean architecture interface adapter separation|interface adapter separation/],
            ['49', /#?49\s*clean architecture package cohesion|package cohesion/],
            ['50', /#?50\s*clean architecture no cyclic dependencies|no cyclic dependencies/],
            ['51', /#?51\s*test pyramid unit coverage|unit coverage/],
            ['52', /#?52\s*test pyramid integration coverage|integration coverage/],
            ['53', /#?53\s*test pyramid end-to-end coverage|end-to-end coverage/],
            ['54', /#?54\s*test pyramid fast feedback|fast feedback/],
            ['55', /#?55\s*test pyramid mocking external apis|mocking external apis/],
            ['56', /#?56\s*test pyramid database test isolation|database test isolation/],
            ['57', /#?57\s*test pyramid api response validation|api response validation/],
            ['58', /#?58\s*test pyramid error path testing|error path testing/],
            ['59', /#?59\s*test pyramid regression test coverage|regression test coverage/],
            ['60', /#?60\s*test pyramid test organization and maintainability|test organization and maintainability/],
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

        if (Number(checkId) > 110) {
            return '';
        }

        return 'check_insecure_design.php?' + params.toString();
    }

    function calculateScoreBreakdown(score, findings) {
        const severityWeights = { high: 8, medium: 4, low: 1, info: 1 };
        const counts = { high: 0, medium: 0, low: 0, info: 0 };

        if (Array.isArray(findings)) {
            findings.forEach(function (f) {
                const severity = String((f && f.severity) || '').toLowerCase();
                if (Object.prototype.hasOwnProperty.call(counts, severity)) {
                    counts[severity] += 1;
                }
            });
        }

        const rawDeduction =
            (counts.high * severityWeights.high) +
            (counts.medium * severityWeights.medium) +
            (counts.low * severityWeights.low) +
            (counts.info * severityWeights.info);

        const cappedDeduction = Math.min(60, rawDeduction);
        const calculatedScore = Math.max(10, 100 - cappedDeduction);

        return {
            counts: counts,
            cappedDeduction: cappedDeduction,
            calculatedScore: calculatedScore,
            reportedScore: Number(score),
        };
    }

    function describeScoreCalculation(score, findings) {
        const breakdown = calculateScoreBreakdown(score, findings);
        const counts = breakdown.counts;

        return 'Score uses 100 - min(60, deduction), floor 10. ' +
            'Deduction = 8*High + 4*Medium + 1*Low (+Info). ' +
            'This scan: H:' + counts.high + ', M:' + counts.medium + ', L:' + counts.low + ', I:' + counts.info +
            ' => deduction ' + breakdown.cappedDeduction + ', score ' + breakdown.calculatedScore + '/100.' +
            (breakdown.reportedScore !== breakdown.calculatedScore ? ' Reported score: ' + breakdown.reportedScore + '/100.' : '');
    }

    function openCheckDetailsPopup(url, titleText) {
        const popup = $('#check-details-popup');
        const iframe = $('#check-details-iframe');
        const title = $('#check-details-title');

        if (!popup.length || !iframe.length || !url) {
            return;
        }

        title.text(titleText || 'Check Details');
        iframe.attr('src', url);
        popup.addClass('open').attr('aria-hidden', 'false');
    }

    function closeCheckDetailsPopup() {
        const popup = $('#check-details-popup');
        const iframe = $('#check-details-iframe');
        popup.removeClass('open').attr('aria-hidden', 'true');
        iframe.attr('src', 'about:blank');
    }

    function renderResults(data) {
        latestScanData = data || null;
        window.latestScanData = latestScanData;
        const repo  = data.repository || {};
        const scan  = data.scan       || {};
        const score = scan.summary_score ?? 0;

        // Score badge
        $('#score-badge').text(score)
            .removeClass('score-good score-medium score-low')
            .addClass(scoreBadgeClass(score));
        $('#score-display').text(String(score) + '/100');
        const breakdown = calculateScoreBreakdown(score, data.findings || []);
        $('#score-breakdown-quick').text(
            'H:' + breakdown.counts.high + ' M:' + breakdown.counts.medium + ' L:' + breakdown.counts.low +
            ' I:' + breakdown.counts.info + ' => -' + breakdown.cappedDeduction
        );
        $('#score-help-panel')
            .text(describeScoreCalculation(score, data.findings || []))
            .removeClass('open')
            .attr('aria-hidden', 'true');

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
        const checksToRender = Array.isArray(data.checks) ? data.checks : [];

        if (checksToRender.length) {
            const groupedChecks = {
                'OWASP Checks': [],
                'Complexity Checks': [],
                'SonarQube Rules (Code Quality)': [],
                'Clean Code Checks (Weight: 10%)': [],
                'Architecture Checks (Weight: 10%)': [],
                'Testing Checks (Weight: 10%)': [],
                'Performance Checks (Weight: 10%)': [],
                'Reliability Checks (Weight: 10%)': [],
                'Documentation Checks (Weight: 5%)': [],
                'Dependency SBOM Checks (Weight: 5%)': [],
                'DevOps Readiness Checks (Weight: 5%)': []
            };

            checksToRender.forEach(function(c) {
                const defaultName = String(c.name || '');
                const checkId = checkDetailIdFromName(defaultName);
                if (!checkId) {
                    return;
                }

                const checkNumber = Number(checkId);
                if (checkNumber >= 1 && checkNumber <= 10) {
                    groupedChecks['OWASP Checks'].push(c);
                } else if (checkNumber >= 11 && checkNumber <= 20) {
                    groupedChecks['Complexity Checks'].push(c);
                } else if (checkNumber >= 31 && checkNumber <= 40) {
                    groupedChecks['Clean Code Checks (Weight: 10%)'].push(c);
                } else if (checkNumber >= 41 && checkNumber <= 50) {
                    groupedChecks['Architecture Checks (Weight: 10%)'].push(c);
                } else if (checkNumber >= 51 && checkNumber <= 60) {
                    groupedChecks['Testing Checks (Weight: 10%)'].push(c);
                } else if (checkNumber >= 61 && checkNumber <= 70) {
                    groupedChecks['Performance Checks (Weight: 10%)'].push(c);
                } else if (checkNumber >= 71 && checkNumber <= 80) {
                    groupedChecks['Reliability Checks (Weight: 10%)'].push(c);
                } else if (checkNumber >= 81 && checkNumber <= 90) {
                    groupedChecks['Documentation Checks (Weight: 5%)'].push(c);
                } else if (checkNumber >= 91 && checkNumber <= 100) {
                    groupedChecks['Dependency SBOM Checks (Weight: 5%)'].push(c);
                } else if (checkNumber >= 101 && checkNumber <= 110) {
                    groupedChecks['DevOps Readiness Checks (Weight: 5%)'].push(c);
                } else {
                    groupedChecks['SonarQube Rules (Code Quality)'].push(c);
                }
            });

            Object.keys(groupedChecks).forEach(function(groupName) {
                const groupItems = groupedChecks[groupName];
                if (!groupItems.length) {
                    return;
                }

                checksGrid.append(`<div class="check-group-heading">${esc(groupName)}</div>`);

                groupItems.forEach(function(c) {
                    const defaultName = String(c.name || '');
                    const checkId = checkDetailIdFromName(defaultName);
                    if (!checkId) {
                        return;
                    }

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
                            name: defaultName,
                            status: String(c.status || 'not_run'),
                            count: String(Number(c.finding_count || 0)),
                            scan_id: String(data.scan_id || '')
                        }).toString()
                        : '';
                    const canOpenDetails = Number(checkId) <= 110;

                    const tileHtml =
                        `<div class="check-tile ${tileClass}">
                            <span class="check-name"><i class="fas ${icon} me-1"></i>${esc(c.name || defaultName)}</span>
                            <span class="check-count">${esc(countText)}</span>
                            <span class="check-label">${esc(labelText)}</span>
                        </div>`;

                    if (detailsUrl && canOpenDetails) {
                        checksGrid.append(
                            `<a class="check-tile-link check-details-trigger" href="${esc(detailsUrl)}" data-title="${esc(defaultName)}" title="Open check details">${tileHtml}</a>`
                        );
                    } else {
                        checksGrid.append(tileHtml);
                    }
                });
            });
            $('#checks-card').show();
        } else {
            checksGrid.append('<p class="text-muted mb-0">No selected checks.</p>');
            $('#checks-card').show();
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
            'security_config': '#10 Security Header & Config Auditor',
            'complexity_cyclomatic_avg': '#11 Cyclomatic Complexity Average',
            'complexity_cyclomatic_max': '#12 Cyclomatic Complexity Maximum',
            'complexity_cognitive_avg': '#13 Cognitive Complexity Average',
            'complexity_cognitive_max': '#14 Cognitive Complexity Maximum',
            'complexity_function_size_avg': '#15 Function Size Average',
            'complexity_function_size_max': '#16 Function Size Maximum',
            'complexity_class_size_avg': '#17 Class Size Average',
            'complexity_class_size_max': '#18 Class Size Maximum',
            'complexity_nesting_depth_avg': '#19 Nesting Depth Average',
            'complexity_nesting_depth_max': '#20 Nesting Depth Maximum',
            'sonar_bugs_reliability': '#21 SonarQube Bugs and Reliability Issues',
            'sonar_code_smells': '#22 SonarQube Code Smells and Maintainability Issues',
            'sonar_duplication_detection': '#23 SonarQube Duplicated Code Detection',
            'sonar_complexity_limits': '#24 SonarQube Cyclomatic and Cognitive Complexity Limits',
            'sonar_size_control': '#25 SonarQube Function and Class Size Control',
            'sonar_naming_readability': '#26 SonarQube Naming Convention and Readability Checks',
            'sonar_dead_code': '#27 SonarQube Dead or Commented-Out Code Detection',
            'sonar_error_handling': '#28 SonarQube Error Handling and Defensive Coding Patterns',
            'sonar_technical_debt': '#29 SonarQube Technical Debt and Remediation Tracking',
            'sonar_quality_gate_summary': '#30 SonarQube Quality Gate Compliance Summary',
            'clean_code_solid': '#31 Clean Code SOLID Principles',
            'clean_code_dry': '#32 Clean Code DRY Principle',
            'clean_code_kiss': '#33 Clean Code KISS Principle',
            'clean_code_yagni': '#34 Clean Code YAGNI Principle',
            'clean_code_single_responsibility': '#35 Clean Code Single Responsibility',
            'clean_code_separation_of_concerns': '#36 Clean Code Separation of Concerns',
            'clean_code_meaningful_names': '#37 Clean Code Meaningful Naming',
            'clean_code_small_functions': '#38 Clean Code Small Functions',
            'clean_code_formatting': '#39 Clean Code Consistent Formatting',
            'clean_code_error_handling': '#40 Clean Code Explicit Error Handling',
            'architecture_layered_boundaries': '#41 Clean Architecture Layered Boundaries',
            'architecture_dependency_rule': '#42 Clean Architecture Dependency Rule',
            'architecture_framework_independence': '#43 Clean Architecture Framework Independence',
            'architecture_presentation_isolation': '#44 Clean Architecture Presentation Isolation',
            'architecture_use_case_separation': '#45 Clean Architecture Use Case Separation',
            'architecture_domain_purity': '#46 Clean Architecture Domain Purity',
            'architecture_data_access_abstraction': '#47 Clean Architecture Data Access Abstraction',
            'architecture_interface_adapter_separation': '#48 Clean Architecture Interface Adapter Separation',
            'architecture_package_cohesion': '#49 Clean Architecture Package Cohesion',
            'architecture_no_cyclic_dependencies': '#50 Clean Architecture No Cyclic Dependencies',
            'testing_unit_coverage': '#51 Test Pyramid Unit Coverage',
            'testing_integration_coverage': '#52 Test Pyramid Integration Coverage',
            'testing_end_to_end_coverage': '#53 Test Pyramid End-to-End Coverage',
            'testing_fast_feedback': '#54 Test Pyramid Fast Feedback',
            'testing_mocking_external_apis': '#55 Test Pyramid Mocking External APIs',
            'testing_database_isolation': '#56 Test Pyramid Database Test Isolation',
            'testing_api_response_validation': '#57 Test Pyramid API Response Validation',
            'testing_error_path_testing': '#58 Test Pyramid Error Path Testing',
            'testing_regression_coverage': '#59 Test Pyramid Regression Test Coverage',
            'testing_organization_maintainability': '#60 Test Pyramid Test Organization and Maintainability',
            'performance_nested_loops': '#61 Performance Nested Loops and Deep Iterations',
            'performance_expensive_operations': '#62 Performance Expensive Operation Hotspots',
            'performance_n_plus_one_patterns': '#63 Performance N+1 and Repeated Data Access Patterns',
            'performance_repeated_api_calls': '#64 Performance Repeated External API Call Patterns',
            'performance_blocking_operations': '#65 Performance Blocking Operation Risks',
            'performance_unbounded_queries': '#66 Performance Unbounded Query and Scan Risks',
            'performance_large_payloads': '#67 Performance Large Payload and Serialization Costs',
            'performance_cache_miss_risk': '#68 Performance Cache Strategy and Miss Risks',
            'performance_sync_io_hotspots': '#69 Performance Synchronous I/O Hotspots',
            'performance_build_runtime_cost': '#70 Performance Build and Runtime Efficiency Controls',
            'reliability_logging_coverage': '#71 Reliability Logging Coverage and Signal Quality',
            'reliability_retry_strategy': '#72 Reliability Retry Strategy and Backoff Safety',
            'reliability_timeout_controls': '#73 Reliability Timeout and Circuit Controls',
            'reliability_exception_handling': '#74 Reliability Exception Handling Discipline',
            'reliability_null_safety': '#75 Reliability Null Safety and Defensive Guards',
            'reliability_resource_cleanup': '#76 Reliability Resource Cleanup and Lifecycle Safety',
            'reliability_input_validation': '#77 Reliability Input Validation and Sanitization',
            'reliability_idempotency': '#78 Reliability Idempotency and Duplicate Request Safety',
            'reliability_fallback_paths': '#79 Reliability Fallback and Degradation Paths',
            'reliability_observability_alerting': '#80 Reliability Observability and Alerting Readiness',
            'testing_first_principles': '#81 Documentation README Completeness and Clarity',
            'testing_aaa_pattern': '#82 Documentation Installation and Usage Guide Quality',
            'testing_test_data_management': '#83 Documentation API Reference and Endpoint Notes',
            'testing_flaky_test_risk': '#84 Documentation Architecture and Design Decision Notes',
            'testing_boundary_case_coverage': '#85 Documentation Changelog and Release Notes Hygiene',
            'testing_contract_validation': '#86 Documentation Configuration and Environment Guide',
            'testing_security_paths': '#87 Documentation Security and Compliance Notes',
            'testing_performance_paths': '#88 Documentation Troubleshooting and FAQ Coverage',
            'testing_ci_gate_readiness': '#89 Documentation Contribution and Workflow Guidelines',
            'testing_suite_maintainability': '#90 Documentation Freshness and Maintainability',
            'dependency_inventory_accuracy': '#91 Dependency Inventory',
            'dependency_identity_normalization': '#92 Vulnerability Detection',
            'dependency_graph_mapping': '#93 License Compliance',
            'dependency_vulnerability_correlation': '#94 Supply Chain Security',
            'dependency_license_risk': '#95 Version Tracking',
            'dependency_provenance_traceability': '#96 Risk Assessment',
            'dependency_integrity_verification': '#97 Dependency Mapping',
            'dependency_sbom_format_quality': '#98 Compliance and Auditing',
            'dependency_sbom_automation': '#99 Continuous SBOM Automation',
            'dependency_drift_unused': '#100 Software Transparency',
            'devops_ci_cd_pipeline': '#101 DevOps CI/CD Pipeline Coverage',
            'devops_docker_readiness': '#102 DevOps Docker Build Readiness',
            'devops_secrets_hygiene': '#103 DevOps Secrets Handling in Pipelines',
            'devops_env_configuration': '#104 DevOps Environment Configuration Management',
            'devops_release_workflow': '#105 DevOps Release Workflow Automation',
            'devops_actions_security': '#106 DevOps GitHub Actions Security Hardening',
            'devops_branch_pr_signals': '#107 DevOps Pull Request and Branch Quality Gates',
            'devops_deployment_automation': '#108 DevOps Deployment Automation Signals',
            'devops_observability_ops': '#109 DevOps Operational Observability Hooks',
            'devops_incident_recovery_docs': '#110 DevOps Runbook and Recovery Documentation'
        };

        const selectedChecksList = $('#selected-checks-list').empty();
        if (data.selected_checks && data.selected_checks.length) {
            const groups = {
                'OWASP Checks': [],
                'Complexity Checks': [],
                'SonarQube Rules (Code Quality)': [],
                'Clean Code Checks (Weight: 10%)': [],
                'Architecture Checks (Weight: 10%)': [],
                'Testing Checks (Weight: 10%)': [],
                'Performance Checks (Weight: 10%)': [],
                'Reliability Checks (Weight: 10%)': [],
                'Documentation Checks (Weight: 5%)': [],
                'Dependency SBOM Checks (Weight: 5%)': [],
                'DevOps Readiness Checks (Weight: 5%)': []
            };

            data.selected_checks.forEach(function(checkId) {
                const friendlyName = checkLabels[checkId] || checkId;
                const numberMatch = String(friendlyName).match(/#\s*(\d+)/);
                const checkNumber = numberMatch ? Number(numberMatch[1]) : 0;

                if (checkNumber >= 1 && checkNumber <= 10) {
                    groups['OWASP Checks'].push(friendlyName);
                } else if (checkNumber >= 11 && checkNumber <= 20) {
                    groups['Complexity Checks'].push(friendlyName);
                } else if (checkNumber >= 31 && checkNumber <= 40) {
                    groups['Clean Code Checks (Weight: 10%)'].push(friendlyName);
                } else if (checkNumber >= 41 && checkNumber <= 50) {
                    groups['Architecture Checks (Weight: 10%)'].push(friendlyName);
                } else if (checkNumber >= 51 && checkNumber <= 60) {
                    groups['Testing Checks (Weight: 10%)'].push(friendlyName);
                } else if (checkNumber >= 61 && checkNumber <= 70) {
                    groups['Performance Checks (Weight: 10%)'].push(friendlyName);
                } else if (checkNumber >= 71 && checkNumber <= 80) {
                    groups['Reliability Checks (Weight: 10%)'].push(friendlyName);
                } else if (checkNumber >= 81 && checkNumber <= 90) {
                    groups['Documentation Checks (Weight: 5%)'].push(friendlyName);
                } else if (checkNumber >= 91 && checkNumber <= 100) {
                    groups['Dependency SBOM Checks (Weight: 5%)'].push(friendlyName);
                } else if (checkNumber >= 101 && checkNumber <= 110) {
                    groups['DevOps Readiness Checks (Weight: 5%)'].push(friendlyName);
                } else {
                    groups['SonarQube Rules (Code Quality)'].push(friendlyName);
                }
            });

            Object.keys(groups).forEach(function(groupName) {
                const items = groups[groupName];
                if (!items.length) {
                    return;
                }

                selectedChecksList.append(`<li class="list-group-item fw-bold bg-light">${esc(groupName)}</li>`);
                items.forEach(function(item) {
                    selectedChecksList.append(`<li class="list-group-item">${esc(item)}</li>`);
                });
            });

            $('#selected-checks-card').show();
        } else {
            selectedChecksList.append('<li class="list-group-item text-muted">No selected checks.</li>');
            $('#selected-checks-card').show();
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
            $('#skills-total-count').text(data.skills.length + ' total');
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
            $('#skills-total-count').text('0 total');
            $('#skills-card').hide();
        }

        // Recommendations (High priority first)
        const recList = $('#recommendations-list').empty();
        const hasFindings = Array.isArray(data.findings) && data.findings.length > 0;
        const recommendations = Array.isArray(data.recommendations) ? data.recommendations : [];

        if (hasFindings && recommendations.length) {
            const order = { High: 0, Medium: 1, Low: 2 };

            function recommendationCheckNumber(text) {
                const match = String(text || '').match(/^#\s*(\d+)/);
                return match ? Number(match[1]) : null;
            }

            const sorted = [...recommendations].sort((a, b) => {
                const priorityDiff = (order[a.priority] ?? 3) - (order[b.priority] ?? 3);
                if (priorityDiff !== 0) {
                    return priorityDiff;
                }

                const aNum = recommendationCheckNumber(a.recommendation_text);
                const bNum = recommendationCheckNumber(b.recommendation_text);

                if (aNum !== null && bNum !== null) {
                    return aNum - bNum;
                }
                if (aNum !== null) {
                    return -1;
                }
                if (bNum !== null) {
                    return 1;
                }
                return String(a.recommendation_text || '').localeCompare(String(b.recommendation_text || ''));
            });

            const checkSpecific = [];
            const general = [];
            sorted.forEach(function(r) {
                if (recommendationCheckNumber(r.recommendation_text) !== null) {
                    checkSpecific.push(r);
                } else {
                    general.push(r);
                }
            });

            function renderRecommendationGroup(title, items, showTitle) {
                if (!items.length) {
                    return;
                }

                if (showTitle) {
                    recList.append(`<li class="list-group-item fw-bold bg-light">${esc(title)}</li>`);
                }
                items.forEach(function(r) {
                    recList.append(
                        `<li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                            <span class="small">${esc(r.recommendation_text)}</span>
                            ${priorityPill(r.priority)}
                        </li>`
                    );
                });
            }

            recList.append('<li class="list-group-item fw-bold bg-light">Fix Recommendations</li>');
            const showSubgroupTitles = checkSpecific.length > 0 && general.length > 0;
            renderRecommendationGroup('Check-specific Recommendations', checkSpecific, showSubgroupTitles);
            renderRecommendationGroup('General Recommendations', general, showSubgroupTitles);
            $('#recommendations-card').show();
        } else if (hasFindings) {
            recList.append('<li class="list-group-item text-muted">Findings detected, but no remediation text was generated for this scan.</li>');
            $('#recommendations-card').show();
        } else {
            recList.append('<li class="list-group-item fw-bold bg-light">Preventive Best Practices</li>');
            recList.append('<li class="list-group-item text-success">No issues detected in this scan.</li>');
            recList.append('<li class="list-group-item small">Keep dependency, secret-scanning, and lint checks in CI for every pull request.</li>');
            recList.append('<li class="list-group-item small">Enforce branch protection and require at least one reviewer before merge.</li>');
            recList.append('<li class="list-group-item small">Schedule periodic audits for licenses, headers, and complexity trends.</li>');
            $('#recommendations-card').show();
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

    function initChecksFolderView() {
        const row = $('#analyze-repository-section .row.row-cols-1.row-cols-md-2.g-2').first();
        if (!row.length) {
            return;
        }

        const children = row.children('.col, .col-12');
        let currentGroupId = '';
        let groupIndex = 0;

        children.each(function () {
            const cell = $(this);
            const title = cell.find('.h6.fw-bold').first();
            const isHeader = title.length > 0;

            if (isHeader) {
                groupIndex += 1;
                currentGroupId = 'check-folder-group-' + groupIndex;
                cell.attr('data-folder-group', currentGroupId).addClass('checks-folder-header');

                if (!title.find('.folder-caret').length) {
                    const countSpanId = currentGroupId + '-count';
                    title.prepend('<i class="fas fa-chevron-right folder-caret me-2" aria-hidden="true"></i>');
                    title.append(' <span class="text-muted small" id="' + countSpanId + '"></span>');

                    let groupCount = 0;
                    let next = cell.next();
                    while (next.length) {
                        const nextIsHeader = next.find('.h6.fw-bold').length > 0;
                        if (nextIsHeader) {
                            break;
                        }
                        groupCount += next.find('input[name="checks[]"]').length;
                        next = next.next();
                    }
                    $('#' + countSpanId).text(groupCount > 0 ? '(' + groupCount + ' skills)' : '');
                }

                return;
            }

            if (currentGroupId !== '') {
                cell.attr('data-folder-child', currentGroupId).addClass('checks-folder-item folder-hidden');
            }
        });

        row.on('click', '.checks-folder-header', function (event) {
            if ($(event.target).closest('select, option, a, button, input, label').length) {
                return;
            }

            const header = $(this);
            const groupId = String(header.attr('data-folder-group') || '');
            if (groupId === '') {
                return;
            }

            const items = row.children('[data-folder-child="' + groupId + '"]');
            const isOpen = header.hasClass('folder-open');

            if (isOpen) {
                items.addClass('folder-hidden');
                header.removeClass('folder-open');
            } else {
                items.removeClass('folder-hidden');
                header.addClass('folder-open');
            }
        });
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

    $(document).on('click', '.check-details-trigger', function (event) {
        event.preventDefault();
        const url = $(this).attr('href') || '';
        const titleText = $(this).attr('data-title') || $(this).closest('.form-check').find('label').text().trim() || 'Check Details';
        openCheckDetailsPopup(url, titleText);
    });

    $('#check-details-close').on('click', function () {
        closeCheckDetailsPopup();
    });

    $('#check-details-popup').on('click', function (event) {
        if (event.target === this) {
            closeCheckDetailsPopup();
        }
    });

    $('#score-help-btn').on('click', function () {
        const panel = $('#score-help-panel');
        const isOpen = panel.hasClass('open');
        panel.toggleClass('open', !isOpen).attr('aria-hidden', isOpen ? 'true' : 'false');
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape' && $('#check-details-popup').hasClass('open')) {
            closeCheckDetailsPopup();
        }
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
    initChecksFolderView();
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
<?php require_once __DIR__ . '/includes/site_chat_widget.php'; ?>
</body>
</html>
