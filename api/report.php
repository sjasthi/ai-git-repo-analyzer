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
    $ensured = true;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
    ];

    $selectedCheckLabels = [];
    foreach ($selectedChecks as $checkId) {
        $selectedCheckLabels[] = $checkLabels[$checkId] ?? $checkId;
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

    if ($download || $format === 'txt') {
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
        $lines[] = 'Check Results';
        if (empty($checkRuns)) {
            $lines[] = '- No stored per-check results for this scan';
        } else {
            foreach ($checkRuns as $cr) {
                $lines[] = '- ' . $cr['check_name'] . ': ' . $cr['status'] . ' (' . $cr['finding_count'] . ' findings)';
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

    $summaryUrl = 'report.php?scan_id=' . $scanId;
    $downloadUrl = 'report.php?scan_id=' . $scanId . '&download=1&format=txt';

    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Scan Summary #' . h((string) $scanId) . '</title>';
    echo '<style>body{font-family:Arial,sans-serif;background:#f7f7fb;color:#1f2937;margin:0;padding:24px}.wrap{max-width:980px;margin:0 auto}.card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px;margin-bottom:16px}.btn{display:inline-block;padding:8px 12px;border-radius:8px;text-decoration:none;border:1px solid #d1d5db;color:#111827;margin-right:8px}.btn-primary{background:#2563eb;color:#fff;border-color:#2563eb}.meta{color:#6b7280;font-size:14px}.tag{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;background:#eef2ff;color:#3730a3}.sev-high{background:#fee2e2;color:#991b1b}.sev-medium{background:#fef3c7;color:#92400e}.sev-low{background:#dcfce7;color:#166534}.sev-info{background:#dbeafe;color:#1e40af}ul{margin:8px 0 0 18px}</style>';
    echo '</head>';
    echo '<body><div class="wrap">';

    echo '<div class="card">';
    echo '<h1 style="margin-top:0">Scan Summary #' . h((string) $scan['id']) . '</h1>';
    echo '<p class="meta">Repository: ' . h((string) $scan['repo_url']) . '</p>';
    echo '<p class="meta">Scan date: ' . h((string) $scan['scan_date']) . '</p>';
    echo '<p class="meta">Score: <strong>' . h((string) ($scan['summary_score'] ?? 'N/A')) . '</strong> | Findings: <strong>' . h((string) $scan['total_findings']) . '</strong> | Skills: <strong>' . h((string) $scan['total_skills']) . '</strong></p>';
    echo '<a class="btn" href="' . h($summaryUrl) . '">Refresh</a>';
    echo '<a class="btn btn-primary" href="' . h($downloadUrl) . '">Download TXT</a>';
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

    echo '<div class="card"><h2 style="margin-top:0">Check Results</h2>';
    if (empty($checkRuns)) {
        echo '<p class="meta">No stored per-check results for this scan.</p>';
    } else {
        echo '<ul>';
        foreach ($checkRuns as $cr) {
            $checkName = h((string) ($cr['check_name'] ?? 'Unknown'));
            $status = (string) ($cr['status'] ?? 'unknown');
            $count = (int) ($cr['finding_count'] ?? 0);
            $statusClass = $status === 'clean' ? 'clean' : 'issues_found';
            echo '<li><span class="status status-' . $statusClass . '">' . ucfirst($status) . '</span> ' . $checkName . ' (' . $count . ' finding' . ($count !== 1 ? 's' : '') . ')</li>';
        }
        echo '</ul>';
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

    echo '</div></body></html>';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Unable to generate report.',
        'details' => $e->getMessage(),
    ]);
}
