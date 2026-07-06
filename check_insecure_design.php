<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

$checkIdRaw = trim((string) ($_GET['check_id'] ?? '1'));
$checkId = preg_match('/^(10|[1-9])$/', $checkIdRaw) === 1 ? $checkIdRaw : '1';
$checkNameFromQuery = trim((string) ($_GET['name'] ?? ''));
$statusRaw = strtolower(trim((string) ($_GET['status'] ?? '')));
$countRaw = isset($_GET['count']) ? (int) $_GET['count'] : null;
$scanId = (int) ($_GET['scan_id'] ?? 0);

$checkContent = [
    '1' => [
        'title' => '#1 Insecure Design and Logic Flaws',
        'tag' => 'OWASP A04 Focus',
        'about' => 'This check looks for risky application design choices and logic mistakes that can be abused. It focuses on secure-by-design patterns for authorization, validation, and critical business rules.',
        'looks_for' => [
            'Missing authorization checks around sensitive actions.',
            'Business-logic bypass opportunities in workflows.',
            'Weak server-side validation on critical fields.',
            'Client-side values trusted for security decisions.',
            'Privilege escalation paths through inconsistent role checks.',
        ],
        'recommendations' => [
            'Enforce server-side authorization for every sensitive action and endpoint.',
            'Validate business workflow states on the backend, not only in client logic.',
            'Add abuse-case tests for privilege escalation and logic bypass scenarios.',
        ],
        'why' => 'Logic flaws can survive clean code reviews; this check helps catch structural weaknesses early.',
    ],
    '2' => [
        'title' => '#2 Vulnerable and Outdated Dependencies',
        'tag' => 'OWASP A06 Focus',
        'about' => 'This check evaluates third-party packages and runtime components for known vulnerabilities and outdated versions.',
        'looks_for' => [
            'Dependency versions with known CVEs.',
            'Unsupported or end-of-life libraries.',
            'Missing lock files or weak version pinning.',
            'High-risk transitive dependencies.',
            'Package sources and update hygiene risks.',
        ],
        'recommendations' => [
            'Upgrade vulnerable dependencies and pin safe minimum versions.',
            'Use lock files and automated dependency update checks in CI.',
            'Review transitive dependencies and remove unused packages.',
        ],
        'why' => 'Supply-chain issues can compromise secure codebases if vulnerable packages are left unpatched.',
    ],
    '3' => [
        'title' => '#3 CI/CD and Software Integrity Risks',
        'tag' => 'OWASP A08 Focus',
        'about' => 'This check reviews automation and build/release settings that could allow tampering or insecure deployments.',
        'looks_for' => [
            'Over-privileged pipeline tokens and secrets exposure.',
            'Untrusted workflow triggers and unsafe scripts.',
            'Missing integrity verification for build artifacts.',
            'Lack of branch protection or release gating.',
            'Weak provenance and auditability of deployments.',
        ],
        'recommendations' => [
            'Restrict CI/CD token permissions to least privilege.',
            'Harden workflow triggers and require reviews for release pipelines.',
            'Add artifact signing or integrity verification before deploy.',
        ],
        'why' => 'Pipeline weaknesses can let attackers inject malicious code into otherwise healthy repositories.',
    ],
    '4' => [
        'title' => '#4 Logging and Monitoring Coverage',
        'tag' => 'OWASP A09 Focus',
        'about' => 'This check estimates whether important security and runtime events are logged and can be monitored.',
        'looks_for' => [
            'Missing logs around auth failures and access denial.',
            'Insufficient audit trails for sensitive actions.',
            'Errors swallowed without alerting paths.',
            'No signs of centralized or structured logging.',
            'Operational blind spots that hinder incident response.',
        ],
        'recommendations' => [
            'Log authentication failures, access denials, and sensitive operations consistently.',
            'Adopt structured logging with severity levels and correlation IDs.',
            'Define alert rules for high-risk runtime and security events.',
        ],
        'why' => 'Without useful telemetry, incidents become harder to detect, investigate, and contain.',
    ],
    '5' => [
        'title' => '#5 Code Quality, Performance and Repo Health',
        'tag' => 'Engineering Quality Focus',
        'about' => 'This check scans repository-level quality signals that affect maintainability, speed, and reliability.',
        'looks_for' => [
            'High-complexity code hotspots.',
            'Code duplication and maintainability debt.',
            'Inefficient patterns likely to impact performance.',
            'Repository hygiene gaps (stale docs, TODO overload).',
            'General structural issues that increase defect risk.',
        ],
        'recommendations' => [
            'Refactor high-complexity and duplicated modules into smaller units.',
            'Address priority TODOs and stale code paths with ownership.',
            'Track code health metrics in CI and block severe regressions.',
        ],
        'why' => 'Poor code health increases delivery risk and can hide security defects over time.',
    ],
    '6' => [
        'title' => '#6 Secret & Credential Scanner',
        'tag' => 'Secrets Hygiene Focus',
        'about' => 'This check detects hardcoded secrets and credential-like patterns in source and configuration files.',
        'looks_for' => [
            'API keys and access tokens committed in code.',
            'Password-like strings in config and scripts.',
            'Private key blocks and sensitive certificate material.',
            'Connection strings with embedded credentials.',
            'Patterns indicating accidental secret leakage.',
        ],
        'recommendations' => [
            'Move secrets to environment variables or a secrets manager.',
            'Rotate exposed credentials and invalidate leaked tokens immediately.',
            'Add pre-commit and CI secret scanning to prevent recurrence.',
        ],
        'why' => 'Exposed credentials are one of the fastest paths to repository and infrastructure compromise.',
    ],
    '7' => [
        'title' => '#7 Dependency CVE Audit (OSV.dev)',
        'tag' => 'CVE Intelligence Focus',
        'about' => 'This check performs a vulnerability audit against public advisories to identify dependency CVEs.',
        'looks_for' => [
            'Packages affected by known CVEs.',
            'Severity and exploitability context where available.',
            'Direct and transitive vulnerable packages.',
            'Version ranges that need immediate upgrades.',
            'Potentially affected ecosystems in lock/manifest files.',
        ],
        'recommendations' => [
            'Patch or replace dependencies affected by high/critical CVEs first.',
            'Create a regular vulnerability triage and remediation cadence.',
            'Document accepted risk and compensating controls for deferred fixes.',
        ],
        'why' => 'Known vulnerabilities provide clear, high-priority remediation targets with measurable risk reduction.',
    ],
    '8' => [
        'title' => '#8 License Compliance Scanner',
        'tag' => 'Legal Compliance Focus',
        'about' => 'This check inspects project and dependency licenses to flag legal or policy conflicts.',
        'looks_for' => [
            'Missing or unknown license declarations.',
            'Copyleft or restricted licenses conflicting with policy.',
            'Mixed license obligations across dependencies.',
            'Potential redistribution/commercial usage constraints.',
            'Repository-level license clarity gaps.',
        ],
        'recommendations' => [
            'Document project and dependency licenses clearly in repository metadata.',
            'Replace dependencies with incompatible licenses where policy requires.',
            'Add automated license policy checks in CI before release.',
        ],
        'why' => 'License issues can block release, distribution, procurement, or commercial usage.',
    ],
    '9' => [
        'title' => '#9 Git History Risk Analysis',
        'tag' => 'Version History Focus',
        'about' => 'This check analyzes commit history for risky patterns that may indicate hidden exposure or governance gaps.',
        'looks_for' => [
            'Secrets that may have existed in historical commits.',
            'Suspicious churn around sensitive files.',
            'Force-push/rewrite indicators when metadata allows.',
            'Large risky changes without clear context.',
            'History patterns that complicate auditing and trust.',
        ],
        'recommendations' => [
            'Purge sensitive artifacts from commit history and rotate impacted secrets.',
            'Enable branch protection and stronger review requirements.',
            'Audit risky commit patterns and document change controls.',
        ],
        'why' => 'Current files can look clean while historical commits still contain sensitive or risky artifacts.',
    ],
    '10' => [
        'title' => '#10 Security Header & Config Auditor',
        'tag' => 'Configuration Hardening Focus',
        'about' => 'This check reviews app and web-server settings for missing hardening controls and weak defaults.',
        'looks_for' => [
            'Missing HTTP security headers (CSP, HSTS, X-Frame-Options).',
            'Weak transport and cookie security settings.',
            'Insecure default config values in deployment files.',
            'Debug or verbose modes enabled in production-like configs.',
            'Mismatch between expected and observed hardening settings.',
        ],
        'recommendations' => [
            'Apply baseline security headers and enforce TLS best practices.',
            'Disable unsafe debug settings in production environments.',
            'Create environment-specific config validation checks in CI/CD.',
        ],
        'why' => 'Config weaknesses are high-impact because they can affect every request and endpoint globally.',
    ],
];

$content = $checkContent[$checkId] ?? $checkContent['1'];
$pageTitle = $content['title'];
$resolvedCheckName = $checkNameFromQuery !== '' ? $checkNameFromQuery : $pageTitle;

$statusLabel = 'Unknown';
$statusClass = 'text-secondary';
if ($statusRaw === 'clean') {
    $statusLabel = 'Clean';
    $statusClass = 'text-success';
} elseif ($statusRaw === 'issues' || $statusRaw === 'issues_found') {
    $statusLabel = 'Issues Found';
    $statusClass = 'text-danger';
}

$findingCountText = 'Not available';
if ($countRaw !== null && $countRaw >= 0) {
    $findingCountText = $countRaw . ' finding' . ($countRaw === 1 ? '' : 's');
}

$findingsByCheckCategory = [
    '1' => ['OWASP'],
    '2' => [],
    '3' => [],
    '4' => [],
    '5' => ['Code Quality', 'Complexity', 'Duplication', 'File Summary'],
    '6' => ['Secret Scanner'],
    '7' => ['Dependencies'],
    '8' => ['License'],
    '9' => ['Git History'],
    '10' => ['Security Config'],
];

$findingsForCheck = [];
$findingsInfo = '';
$scanRecommendationsForCheck = [];
$recommendationsInfo = '';

$recommendationKeywordsByCheck = [
    '1' => ['authorization', 'access', 'logic', 'validation', 'design', 'owasp'],
    '2' => ['dependency', 'dependencies', 'package', 'outdated', 'upgrade'],
    '3' => ['ci', 'cd', 'pipeline', 'workflow', 'integrity', 'build', 'deploy'],
    '4' => ['logging', 'monitoring', 'audit', 'alert', 'telemetry'],
    '5' => ['quality', 'performance', 'complexity', 'duplication', 'maintainability', 'repo health'],
    '6' => ['secret', 'credential', 'token', 'key', 'password'],
    '7' => ['cve', 'osv', 'vulnerability', 'dependency'],
    '8' => ['license', 'compliance', 'legal'],
    '9' => ['git history', 'history', 'commit', 'branch'],
    '10' => ['security header', 'config', 'configuration', 'hardening', 'tls', 'cookie'],
];

if ($scanId <= 0) {
    $findingsInfo = 'Scan ID is not available, so finding details cannot be loaded from the database.';
} elseif ($countRaw !== null && $countRaw === 0) {
    $findingsInfo = 'No findings were reported for this check in this scan.';
} else {
    $categories = $findingsByCheckCategory[$checkId] ?? [];
    if (empty($categories)) {
        $findingsInfo = 'Detailed per-check finding mapping is not available for this check yet.';
    } else {
        try {
            $pdo = db_connection();
            $placeholders = implode(',', array_fill(0, count($categories), '?'));

            $sql =
                'SELECT category, title, description, severity
                 FROM findings
                 WHERE scan_id = ?
                 AND category IN (' . $placeholders . ')
                 ORDER BY FIELD(severity, "High", "Medium", "Low", "Info"), id ASC';

            $stmt = $pdo->prepare($sql);
            $params = array_merge([$scanId], $categories);
            $stmt->execute($params);
            $findingsForCheck = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($findingsForCheck)) {
                $findingsInfo = 'No matching finding rows were found for this check in the saved scan details.';
            }
        } catch (Throwable $e) {
            $findingsInfo = 'Unable to load detailed findings for this check right now.';
        }
    }
}

if ($scanId <= 0) {
    $recommendationsInfo = 'Scan ID is not available, so scan recommendations cannot be loaded.';
} else {
    try {
        if (!isset($pdo) || !$pdo instanceof PDO) {
            $pdo = db_connection();
        }

        $recommendationStmt = $pdo->prepare(
            'SELECT recommendation_text, priority
             FROM recommendations
             WHERE scan_id = ?
             ORDER BY FIELD(priority, "High", "Medium", "Low"), id ASC'
        );
        $recommendationStmt->execute([$scanId]);
        $allScanRecommendations = $recommendationStmt->fetchAll(PDO::FETCH_ASSOC);

        $keywords = $recommendationKeywordsByCheck[$checkId] ?? [];
        if (!empty($keywords)) {
            foreach ($allScanRecommendations as $rec) {
                $text = strtolower((string) ($rec['recommendation_text'] ?? ''));
                foreach ($keywords as $keyword) {
                    if ($keyword !== '' && strpos($text, $keyword) !== false) {
                        $scanRecommendationsForCheck[] = $rec;
                        break;
                    }
                }
            }
        }

        if (empty($scanRecommendationsForCheck)) {
            $recommendationsInfo = 'No scan-specific recommendation text matched this check, showing baseline guidance below.';
        }
    } catch (Throwable $e) {
        $recommendationsInfo = 'Unable to load scan recommendations for this check right now.';
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> | Check Details</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >
    <style>
        :root {
            --header-gradient: linear-gradient(135deg, #9B59B6 0%, #7C3AED 100%);
        }

        body {
            background: linear-gradient(180deg, #f5f3ff 0%, #faf8ff 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #111827;
        }

        .header-section {
            background: var(--header-gradient);
            color: #fff;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card-soft {
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
        }

        .pill {
            display: inline-block;
            padding: 0.2rem 0.65rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            background: #ede9fe;
            color: #5b21b6;
        }

        .section-title {
            font-weight: 700;
            margin-bottom: 0.6rem;
        }

        ul.tight li {
            margin-bottom: 0.35rem;
        }

        .sev-high { color: #b91c1c; }
        .sev-medium { color: #b45309; }
        .sev-low { color: #047857; }
        .sev-info { color: #1d4ed8; }

        .site-footer {
            margin-top: 1.5rem;
            padding: 1.1rem 0;
            background: var(--header-gradient);
            color: #ffffff;
            font-size: 0.92rem;
        }

        .site-footer .footer-line {
            margin: 0;
            text-align: center;
            font-weight: 700;
            font-size: 2rem;
            line-height: 1.2;
        }
    </style>
</head>
<body>
    <div class="header-section">
        <div class="container">
            <h1 class="h2 mb-2"><?= h($pageTitle) ?></h1>
            <p class="mb-3">Details about what this analyzer checks, what it looks for, and the latest result details.</p>
            <a href="index.php" class="btn btn-light btn-sm">Back to Home</a>
        </div>
    </div>

    <div class="container pb-5">
        <div class="card-soft p-4 mb-4">
            <h2 class="h5 section-title">Current Result</h2>
            <p class="mb-2">This section shows the latest value passed from the Analysis Checks card.</p>
            <ul class="tight mb-0">
                <li><strong>Check:</strong> <?= h($resolvedCheckName) ?></li>
                <li><strong>Status:</strong> <span class="<?= h($statusClass) ?>"><?= h($statusLabel) ?></span></li>
                <li><strong>Finding count:</strong> <?= h($findingCountText) ?></li>
                <li><strong>Scan ID:</strong> <?= $scanId > 0 ? h((string) $scanId) : 'Not available' ?></li>
            </ul>
        </div>

        <div class="card-soft p-4 mb-4">
            <h2 class="h5 section-title">Findings For This Check</h2>
            <?php if (!empty($findingsForCheck)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($findingsForCheck as $finding): ?>
                        <?php
                        $severity = (string) ($finding['severity'] ?? 'Info');
                        $sevClass = 'sev-info';
                        if ($severity === 'High') {
                            $sevClass = 'sev-high';
                        } elseif ($severity === 'Medium') {
                            $sevClass = 'sev-medium';
                        } elseif ($severity === 'Low') {
                            $sevClass = 'sev-low';
                        }
                        ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <strong><?= h((string) ($finding['title'] ?? 'Untitled finding')) ?></strong>
                                    <div class="small text-muted">Category: <?= h((string) ($finding['category'] ?? 'Unknown')) ?></div>
                                </div>
                                <span class="small fw-semibold <?= h($sevClass) ?>"><?= h($severity) ?></span>
                            </div>
                            <p class="mb-0 mt-2 small"><?= h((string) ($finding['description'] ?? '')) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="mb-0"><?= h($findingsInfo !== '' ? $findingsInfo : 'No findings available for this check.') ?></p>
            <?php endif; ?>
        </div>

        <div class="card-soft p-4 mb-4">
            <span class="pill"><?= h($content['tag']) ?></span>
            <h2 class="h5 mt-3 section-title">What this check is about</h2>
            <p class="mb-0"><?= h($content['about']) ?></p>
        </div>

        <div class="card-soft p-4 mb-4">
            <h2 class="h5 section-title">What the analyzer looks for</h2>
            <ul class="tight mb-0">
                <?php foreach ($content['looks_for'] as $rule): ?>
                    <li><?= h($rule) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card-soft p-4 mb-4">
            <h2 class="h5 section-title">How findings are reported</h2>
            <p class="mb-2">For each finding, the system usually reports:</p>
            <ul class="tight mb-0">
                <li>Title of the issue and risk severity.</li>
                <li>Category and short description.</li>
                <li>Code/location context when available.</li>
                <li>Recommendation to reduce the risk.</li>
            </ul>
        </div>

        <div class="card-soft p-4 mb-4">
            <h2 class="h5 section-title">Recommendations For This Check</h2>

            <?php if (!empty($scanRecommendationsForCheck)): ?>
                <div class="list-group list-group-flush mb-3">
                    <?php foreach ($scanRecommendationsForCheck as $rec): ?>
                        <?php $priority = (string) ($rec['priority'] ?? 'Low'); ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <span><?= h((string) ($rec['recommendation_text'] ?? '')) ?></span>
                                <span class="badge text-bg-secondary"><?= h($priority) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($recommendationsInfo !== ''): ?>
                <p class="mb-2 small text-muted"><?= h($recommendationsInfo) ?></p>
            <?php endif; ?>

            <h3 class="h6 mt-3">Baseline Guidance</h3>
            <ul class="tight mb-0">
                <?php foreach (($content['recommendations'] ?? []) as $recommendation): ?>
                    <li><?= h($recommendation) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card-soft p-4">
            <h2 class="h5 section-title">Why this matters</h2>
            <p class="mb-0"><?= h($content['why']) ?></p>
        </div>
    </div>
    <footer class="site-footer">
        <div class="container">
            <p class="footer-line">@2026 AI Git Repo Analyzer</p>
            <p class="footer-line">803 Summer Street, MN 55106</p>
            <p class="footer-line">ContactUs@aigitrepoanalyzer.com</p>
        </div>
    </footer>
</body>
</html>
