<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function ensureScanReportColumns(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec("ALTER TABLE scans ADD COLUMN IF NOT EXISTS selected_checks_json LONGTEXT NULL");
    $pdo->exec("ALTER TABLE scans ADD COLUMN IF NOT EXISTS results_json LONGTEXT NULL");
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS check_runs (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            scan_id       INT         NOT NULL,
            check_name    VARCHAR(50) NOT NULL,
            status        VARCHAR(20) NOT NULL DEFAULT 'clean',
            finding_count INT         NOT NULL DEFAULT 0,
            FOREIGN KEY (scan_id) REFERENCES scans(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        )"
    );
    $ensured = true;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function absoluteCheckDetailsUrl(array $params = []): string
{
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/api/report.php'));
    $appBasePath = rtrim(dirname(dirname($scriptName)), '/');

    $base = $scheme . '://' . $host . $appBasePath . '/check_insecure_design.php';
    if (empty($params)) {
        return $base;
    }

    return $base . '?' . http_build_query($params);
}

function absoluteReportUrl(array $params = []): string
{
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/api/report.php'));

    $base = $scheme . '://' . $host . $scriptName;
    if (empty($params)) {
        return $base;
    }

    return $base . '?' . http_build_query($params);
}

function checkDetailIdFromName(string $checkName): ?string
{
    $normalized = strtolower(trim($checkName));

    if (preg_match('/#\s*(20|1[0-9]|[1-9])/', $normalized, $numberMatch) === 1) {
        return (string) $numberMatch[1];
    }

    $patterns = [
        '1' => '/#?1\s*insecure design and logic flaws|insecure design and logic flaws/i',
        '2' => '/#?2\s*vulnerable and outdated dependencies|vulnerable and outdated dependencies/i',
        '3' => '/#?3\s*ci\/cd and software integrity risks|software integrity risks/i',
        '4' => '/#?4\s*logging and monitoring coverage|logging and monitoring/i',
        '5' => '/#?5\s*code quality, performance and repo health|repo health/i',
        '6' => '/#?6\s*secret\s*&\s*credential scanner|secret.*credential scanner/i',
        '7' => '/#?7\s*dependency cve audit|osv\.dev/i',
        '8' => '/#?8\s*license compliance scanner|license compliance/i',
        '9' => '/#?9\s*git history risk analysis|git history risk/i',
        '10' => '/#?10\s*security header\s*&\s*config auditor|security header.*config auditor/i',
        '11' => '/#?11\s*cyclomatic complexity average|cyclomatic complexity average/i',
        '12' => '/#?12\s*cyclomatic complexity maximum|cyclomatic complexity maximum/i',
        '13' => '/#?13\s*cognitive complexity average|cognitive complexity average/i',
        '14' => '/#?14\s*cognitive complexity maximum|cognitive complexity maximum/i',
        '15' => '/#?15\s*function size average|function size average/i',
        '16' => '/#?16\s*function size maximum|function size maximum/i',
        '17' => '/#?17\s*class size average|class size average/i',
        '18' => '/#?18\s*class size maximum|class size maximum/i',
        '19' => '/#?19\s*nesting depth average|nesting depth average/i',
        '20' => '/#?20\s*nesting depth maximum|nesting depth maximum/i',
    ];

    foreach ($patterns as $checkId => $pattern) {
        if (preg_match($pattern, $normalized) === 1) {
            return $checkId;
        }
    }

    return null;
}

$scanId = (int) ($_GET['scan_id'] ?? 0);
$download = isset($_GET['download']) && (string) $_GET['download'] === '1';
$format = strtolower(trim((string) ($_GET['format'] ?? 'html')));

if ($scanId <= 0) {
    http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Valid scan_id is required.']);
    exit;
}

try {
    $pdo = db_connection();
    ensureScanReportColumns($pdo);

    $scanStmt = $pdo->prepare(
        'SELECT s.id, s.scan_date, s.summary_score, s.total_findings, s.total_skills, s.selected_checks_json, s.results_json, r.repo_url
         FROM scans s
         JOIN repositories r ON s.repository_id = r.id
         WHERE s.id = :scan_id'
    );
    $scanStmt->execute([':scan_id' => $scanId]);
    $scan = $scanStmt->fetch();

    if (!$scan) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Scan not found.']);
        exit;
    }

    $findingsStmt = $pdo->prepare(
        'SELECT category, title, description, severity
         FROM findings
         WHERE scan_id = :scan_id
         ORDER BY FIELD(severity, "High", "Medium", "Low", "Info"), id ASC'
    );
    $findingsStmt->execute([':scan_id' => $scanId]);
    $findings = $findingsStmt->fetchAll();

    $recommendationsStmt = $pdo->prepare(
        'SELECT recommendation_text, priority
         FROM recommendations
         WHERE scan_id = :scan_id
         ORDER BY FIELD(priority, "High", "Medium", "Low"), id ASC'
    );
    $recommendationsStmt->execute([':scan_id' => $scanId]);
    $recommendations = $recommendationsStmt->fetchAll();

    $skillsStmt = $pdo->prepare(
        'SELECT skill_name, proficiency_level, risk_level
         FROM skills
         WHERE scan_id = :scan_id
         ORDER BY id ASC'
    );
    $skillsStmt->execute([':scan_id' => $scanId]);
    $skills = $skillsStmt->fetchAll();

    $checkRunsStmt = $pdo->prepare(
        'SELECT check_name, status, finding_count
         FROM check_runs
         WHERE scan_id = :scan_id
         ORDER BY id ASC'
    );
    $checkRunsStmt->execute([':scan_id' => $scanId]);
    $checkRuns = $checkRunsStmt->fetchAll();

    $selectedChecks = [];
    if (!empty($scan['selected_checks_json'])) {
        $decodedChecks = json_decode((string) $scan['selected_checks_json'], true);
        if (is_array($decodedChecks)) {
            $selectedChecks = $decodedChecks;
        }
    }

    // Map check IDs to friendly names
    $checkLabels = [
        'dependency_risk'  => '#1 Insecure Design and Logic Flaws (A04)',
        'hardening'        => '#2 Vulnerable and Outdated Dependencies (A06)',
        'performance'      => '#3 CI/CD and Software Integrity Risks (A08)',
        'maintainability'  => '#4 Logging and Monitoring Coverage (A09)',
        'code_intelligence'=> '#5 Code Quality, Performance and Repo Health',
        'secret_scanner'   => '#6 Secret & Credential Scanner',
        'dependency_cve'   => '#7 Dependency CVE Audit (OSV.dev)',
        'license_check'    => '#8 License Compliance Scanner',
        'git_history'      => '#9 Git History Risk Analysis',
        'security_config'  => '#10 Security Header & Config Auditor',
        'complexity_cyclomatic_avg' => '#11 Cyclomatic Complexity Average',
        'complexity_cyclomatic_max' => '#12 Cyclomatic Complexity Maximum',
        'complexity_cognitive_avg' => '#13 Cognitive Complexity Average',
        'complexity_cognitive_max' => '#14 Cognitive Complexity Maximum',
        'complexity_function_size_avg' => '#15 Function Size Average',
        'complexity_function_size_max' => '#16 Function Size Maximum',
        'complexity_class_size_avg' => '#17 Class Size Average',
        'complexity_class_size_max' => '#18 Class Size Maximum',
        'complexity_nesting_depth_avg' => '#19 Nesting Depth Average',
        'complexity_nesting_depth_max' => '#20 Nesting Depth Maximum',
    ];

    $selectedCheckLabels = [];
    foreach ($selectedChecks as $checkId) {
        $selectedCheckLabels[] = $checkLabels[$checkId] ?? $checkId;
    }

    $checkRunsById = [];
    foreach ($checkRuns as $cr) {
        $nameRaw = (string) ($cr['check_name'] ?? '');
        $id = checkDetailIdFromName($nameRaw);
        if ($id !== null) {
            $checkRunsById[$id] = $cr;
        }
    }

    $orderedCheckRuns = [];
    foreach ($checkLabels as $id => $label) {
        $labelRaw = preg_replace('/\s*\([^)]*\)\s*$/', '', $label);
        $labelCheckId = null;
        if (preg_match('/#\s*(20|1[0-9]|[1-9])/', $label, $numberMatch) === 1) {
            $labelCheckId = (string) $numberMatch[1];
        }

        if ($labelCheckId !== null && isset($checkRunsById[$labelCheckId])) {
            $orderedCheckRuns[] = $checkRunsById[$labelCheckId];
            continue;
        }

        $orderedCheckRuns[] = [
            'check_name' => $labelRaw,
            'status' => 'not_run',
            'finding_count' => 0,
        ];
    }

    $results = [];
    if (!empty($scan['results_json'])) {
        $decodedResults = json_decode((string) $scan['results_json'], true);
        if (is_array($decodedResults)) {
            $results = $decodedResults;
        }
    }

    $summaryData = [
        'scan' => [
            'id' => (int) $scan['id'],
            'scan_date' => (string) $scan['scan_date'],
            'summary_score' => $scan['summary_score'] !== null ? (int) $scan['summary_score'] : null,
            'total_findings' => (int) $scan['total_findings'],
            'total_skills' => (int) $scan['total_skills'],
            'repo_url' => (string) $scan['repo_url'],
        ],
        'selected_checks' => $selectedCheckLabels,
        'check_runs' => $checkRuns,
        'results' => $results,
        'findings' => $findings,
        'recommendations' => $recommendations,
        'skills' => $skills,
    ];

    if ($format === 'json' && !$download) {
        header('Content-Type: application/json');
        echo json_encode($summaryData, JSON_PRETTY_PRINT);
        exit;
    }

    if ($download && $format === 'html') {
        header('Content-Disposition: attachment; filename="scan-' . $scanId . '-summary.html"');
    }

    if ($format === 'txt' || ($download && $format !== 'html')) {
        $lines = [];
        $lines[] = 'AI Git Repo Analyzer Report';
        $lines[] = 'Scan ID: ' . $scan['id'];
        $lines[] = 'Repository: ' . $scan['repo_url'];
        $lines[] = 'Scan Date: ' . $scan['scan_date'];
        $lines[] = 'Summary Score: ' . ($scan['summary_score'] ?? 'N/A');
        $lines[] = 'Total Findings: ' . $scan['total_findings'];
        $lines[] = 'Total Skills: ' . $scan['total_skills'];
        $lines[] = '';
        $lines[] = 'Selected Checks';
        if (empty($selectedCheckLabels)) {
            $lines[] = '- No stored check list for this scan';
        } else {
            foreach ($selectedCheckLabels as $check) {
                $lines[] = '- ' . $check;
            }
        }
        $lines[] = '';
        $lines[] = 'Analysis Checks';
        if (empty($checkRuns)) {
            $lines[] = '- No stored per-check results for this scan';
        } else {
            foreach ($checkRuns as $cr) {
                $lines[] = '- [' . ucfirst($cr['status']) . '] ' . $cr['check_name'] . ': ' . $cr['finding_count'] . ' finding' . ($cr['finding_count'] !== 1 ? 's' : '');
            }
        }
        $lines[] = '';
        if (empty($findings)) {
            $lines[] = '- None';
        } else {
            foreach ($findings as $finding) {
                $lines[] = '- [' . $finding['severity'] . '] ' . $finding['title'];
                $lines[] = '  Category: ' . $finding['category'];
                $lines[] = '  Description: ' . $finding['description'];
            }
        }
        $lines[] = '';
        $lines[] = 'Recommendations';
        if (empty($recommendations)) {
            $lines[] = '- None';
        } else {
            foreach ($recommendations as $recommendation) {
                $lines[] = '- [' . $recommendation['priority'] . '] ' . $recommendation['recommendation_text'];
            }
        }
        $lines[] = '';
        $lines[] = 'Skills';
        if (empty($skills)) {
            $lines[] = '- None';
        } else {
            foreach ($skills as $skill) {
                $lines[] = '- ' . $skill['skill_name'] . ' (' . $skill['proficiency_level'] . ', risk: ' . $skill['risk_level'] . ')';
            }
        }

        $text = implode("\n", $lines) . "\n";
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="scan-' . $scanId . '-summary.txt"');
        echo $text;
        exit;
    }

    $summaryUrl = absoluteReportUrl([
        'scan_id' => (string) $scanId,
    ]);
    $downloadUrl = absoluteReportUrl([
        'scan_id' => (string) $scanId,
        'download' => '1',
        'format' => 'html',
    ]);

    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Scan Summary #' . h((string) $scanId) . '</title>';
    echo '<style>body{font-family:Arial,sans-serif;background:#f7f7fb;color:#1f2937;margin:0;padding:24px}.wrap{max-width:980px;margin:0 auto}.card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px;margin-bottom:16px}.btn{display:inline-block;padding:8px 12px;border-radius:8px;text-decoration:none;border:1px solid #d1d5db;color:#111827;margin-right:8px}.btn-primary{background:#2563eb;color:#fff;border-color:#2563eb}.meta{color:#6b7280;font-size:14px}.tag{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;background:#eef2ff;color:#3730a3}.sev-high{background:#fee2e2;color:#991b1b}.sev-medium{background:#fef3c7;color:#92400e}.sev-low{background:#dcfce7;color:#166534}.sev-info{background:#dbeafe;color:#1e40af}ul{margin:8px 0 0 18px}.checks-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:0.75rem}.check-tile{border-radius:0.75rem;padding:0.85rem 1rem;border:1.5px solid #e5e7eb;display:flex;flex-direction:column;gap:0.3rem;background:#fff}.check-tile.clean{border-color:#bbf7d0;background:#f0fdf4}.check-tile.issues{border-color:#fecaca;background:#fff5f5}.check-tile .check-name{font-size:0.78rem;font-weight:700;color:#374151}.check-tile .check-count{font-size:1.1rem;font-weight:700}.check-tile.clean .check-count{color:#16a34a}.check-tile.issues .check-count{color:#dc2626}.check-tile .check-label{font-size:0.7rem;color:#6b7280}</style>';
    echo '<style>body[data-theme="dark"]{background:#0f172a;color:#e5e7eb}body[data-theme="dark"] .card{background:#1f2937;border-color:#374151}body[data-theme="dark"] .meta{color:#9ca3af}body[data-theme="dark"] .btn{color:#e5e7eb;border-color:#6b7280}body[data-theme="dark"] .btn-primary{background:#1d4ed8;border-color:#1d4ed8;color:#fff}body[data-theme="dark"] .tag{background:#1e293b;color:#c7d2fe}body[data-theme="dark"] .check-tile{background:#111827;border-color:#374151}body[data-theme="dark"] .check-tile.clean{background:#0f1f17;border-color:#166534}body[data-theme="dark"] .check-tile.issues{background:#2a1313;border-color:#7f1d1d}body[data-theme="dark"] .check-name{color:#d1d5db}body[data-theme="dark"] .check-label{color:#9ca3af}</style>';
    echo '</head>';
    echo '<body><div class="wrap">';

    echo '<div class="card">';
    echo '<h1 style="margin-top:0">Scan Summary #' . h((string) $scan['id']) . '</h1>';
    echo '<p class="meta">Repository: ' . h((string) $scan['repo_url']) . '</p>';
    echo '<p class="meta">Scan date: ' . h((string) $scan['scan_date']) . '</p>';
    echo '<p class="meta">Score: <strong>' . h((string) ($scan['summary_score'] ?? 'N/A')) . '</strong> | Findings: <strong>' . h((string) $scan['total_findings']) . '</strong> | Skills: <strong>' . h((string) $scan['total_skills']) . '</strong></p>';
    echo '<a class="btn" href="' . h($summaryUrl) . '">Refresh</a>';
    echo '<a class="btn btn-primary" href="' . h($downloadUrl) . '">Download HTML</a>';
    echo '<button type="button" id="theme-toggle" class="btn">Dark Mode</button>';
    echo '</div>';

    echo '<div class="card"><h2 style="margin-top:0">Selected Checks</h2>';
    if (empty($selectedCheckLabels)) {
        echo '<p class="meta">No stored check list for this scan.</p>';
    } else {
        echo '<ul>';
        foreach ($selectedCheckLabels as $check) {
            echo '<li>' . h((string) $check) . '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';

    echo '<div class="card"><h2 style="margin-top:0">Analysis Checks</h2>';
    if (empty($orderedCheckRuns)) {
        echo '<p class="meta">No stored per-check results for this scan.</p>';
    } else {
        echo '<div class="checks-grid">';
        foreach ($orderedCheckRuns as $cr) {
            $checkName = h((string) ($cr['check_name'] ?? 'Unknown'));
            $checkNameRaw = (string) ($cr['check_name'] ?? 'Unknown');
            $status = (string) ($cr['status'] ?? 'unknown');
            $count = (int) ($cr['finding_count'] ?? 0);
            $statusNorm = strtolower($status);
            $tileClass = $statusNorm === 'clean' ? 'clean' : ($statusNorm === 'not_run' ? '' : 'issues');
            $detailsUrl = '';
            $checkId = checkDetailIdFromName($checkNameRaw);
            if ($checkId !== null && (int) $checkId <= 10) {
                $detailsUrl = absoluteCheckDetailsUrl([
                    'check_id' => $checkId,
                    'name' => $checkNameRaw,
                    'status' => $status,
                    'count' => (string) $count,
                    'scan_id' => (string) $scanId,
                ]);
            }
            
            if ($detailsUrl !== '') {
                echo '<a href="' . h($detailsUrl) . '" target="_blank" rel="noopener" style="text-decoration:none;color:inherit">';
            }
            echo '<div class="check-tile ' . $tileClass . '">';
            echo '<span class="check-name">' . $checkName . '</span>';
            echo '<span class="check-count">' . ($statusNorm === 'not_run' ? '-' : (string) $count) . '</span>';
            echo '<span class="check-label">' . ($statusNorm === 'not_run' ? 'Not run' : ($count === 0 ? 'No issues' : ($count === 1 ? '1 issue' : $count . ' issues'))) . '</span>';
            echo '</div>';
            if ($detailsUrl !== '') {
                echo '</a>';
            }
        }
        echo '</div>';
    }
    echo '</div>';

    echo '<div class="card"><h2 style="margin-top:0">Findings</h2>';
    if (empty($findings)) {
        echo '<p class="meta">No findings recorded.</p>';
    } else {
        foreach ($findings as $finding) {
            $sevClass = 'sev-info';
            if ($finding['severity'] === 'High') {
                $sevClass = 'sev-high';
            } elseif ($finding['severity'] === 'Medium') {
                $sevClass = 'sev-medium';
            } elseif ($finding['severity'] === 'Low') {
                $sevClass = 'sev-low';
            }
            echo '<div style="padding:10px 0;border-top:1px solid #f3f4f6">';
            echo '<div><strong>' . h((string) $finding['title']) . '</strong> <span class="tag ' . h($sevClass) . '">' . h((string) $finding['severity']) . '</span></div>';
            echo '<div class="meta">Category: ' . h((string) $finding['category']) . '</div>';
            echo '<div>' . h((string) $finding['description']) . '</div>';
            echo '</div>';
        }
    }
    echo '</div>';

    echo '<div class="card"><h2 style="margin-top:0">Recommendations</h2>';
    if (empty($recommendations)) {
        echo '<p class="meta">No recommendations recorded.</p>';
    } else {
        echo '<ul>';
        foreach ($recommendations as $recommendation) {
            echo '<li><strong>[' . h((string) $recommendation['priority']) . ']</strong> ' . h((string) $recommendation['recommendation_text']) . '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';

    echo '<div class="card"><h2 style="margin-top:0">Skills</h2>';
    if (empty($skills)) {
        echo '<p class="meta">No skills recorded.</p>';
    } else {
        echo '<ul>';
        foreach ($skills as $skill) {
            echo '<li>' . h((string) $skill['skill_name']) . ' (' . h((string) $skill['proficiency_level']) . ', risk: ' . h((string) $skill['risk_level']) . ')</li>';
        }
        echo '</ul>';
    }
    echo '</div>';

    echo '<script>(function(){var key="ai_git_repo_theme";var btn=document.getElementById("theme-toggle");function pref(){var s=localStorage.getItem(key);if(s==="dark"||s==="light"){return s;}return window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";}function apply(t){var next=t==="dark"?"dark":"light";document.body.setAttribute("data-theme",next);if(btn){btn.textContent=next==="dark"?"Light Mode":"Dark Mode";}}apply(pref());if(btn){btn.addEventListener("click",function(){var current=document.body.getAttribute("data-theme")==="dark"?"dark":"light";var next=current==="dark"?"light":"dark";localStorage.setItem(key,next);apply(next);});}})();</script>';
    echo '</div></body></html>';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Unable to generate report.',
        'details' => $e->getMessage(),
    ]);
}
