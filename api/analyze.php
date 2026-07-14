<?php

declare(strict_types=1);

set_time_limit(120); // Allow up to 2 minutes for all API calls

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/github_helper.php';
require_once __DIR__ . '/checks/security/check_secrets.php';
require_once __DIR__ . '/checks/security/check_owasp.php';
require_once __DIR__ . '/checks/security/check_dependencies.php';
require_once __DIR__ . '/checks/security/check_complexity.php';
require_once __DIR__ . '/checks/security/check_file_summary.php';
require_once __DIR__ . '/checks/security/check_todos.php';
require_once __DIR__ . '/checks/security/check_license.php';
require_once __DIR__ . '/checks/security/check_git_history.php';
require_once __DIR__ . '/checks/security/check_duplication.php';
require_once __DIR__ . '/checks/security/check_security_config.php';
require_once __DIR__ . '/checks/check_insecure_design.php';
require_once __DIR__ . '/checks/check_dependency_hardening.php';
require_once __DIR__ . '/checks/check_ci_cd_integrity.php';
require_once __DIR__ . '/checks/check_logging_monitoring.php';
require_once __DIR__ . '/checks/complexity/check_complexity_metrics.php';
require_once __DIR__ . '/checks/sonarqube/check_sonarqube.php';

header('Content-Type: application/json');

// Kept for api/report.php compatibility
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

// Legacy helper used by report.php — kept for backward compatibility
function makeGitHubRequest(string $url, string $pat): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/vnd.github+json',
            "Authorization: Bearer {$pat}",
            'User-Agent: ai-git-repo-analyzer',
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $responseBody = curl_exec($ch);
    $httpCode     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError    = curl_error($ch);
    curl_close($ch);

    if ($responseBody === false || $curlError !== '') {
        throw new RuntimeException($curlError !== '' ? $curlError : 'Unable to reach GitHub API.');
    }
    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('GitHub API returned an invalid response.');
    }
    return ['code' => $httpCode, 'body' => $decoded];
}

function fetchTextFile(string $owner, string $repo, string $path, string $pat): ?string
{
    $url      = "https://api.github.com/repos/{$owner}/{$repo}/contents/" . rawurlencode($path);
    $response = makeGitHubRequest($url, $pat);
    if ($response['code'] >= 400) {
        return null;
    }
    $body = $response['body'];
    if (!is_array($body) || empty($body['content'])) {
        return null;
    }
    $content = $body['content'];
    if (isset($body['encoding']) && $body['encoding'] === 'base64') {
        $decoded = base64_decode($content, true);
        return $decoded !== false ? $decoded : null;
    }
    return (string) $content;
}

function buildResult(string $id, string $title, string $summary, string $details, string $severity, array $evidence = []): array
{
    return ['id' => $id, 'title' => $title, 'summary' => $summary, 'details' => $details, 'severity' => $severity, 'evidence' => $evidence];
}

function getCheckLabel(string $checkId): string
{
    $map = [
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
        'sonar_bugs_reliability' => '#21 SonarQube Bugs and Reliability Issues',
        'sonar_code_smells' => '#22 SonarQube Code Smells and Maintainability Issues',
        'sonar_duplication_detection' => '#23 SonarQube Duplicated Code Detection',
        'sonar_complexity_limits' => '#24 SonarQube Cyclomatic and Cognitive Complexity Limits',
        'sonar_size_control' => '#25 SonarQube Function and Class Size Control',
        'sonar_naming_readability' => '#26 SonarQube Naming Convention and Readability Checks',
        'sonar_dead_code' => '#27 SonarQube Dead or Commented-Out Code Detection',
        'sonar_error_handling' => '#28 SonarQube Error Handling and Defensive Coding Patterns',
        'sonar_technical_debt' => '#29 SonarQube Technical Debt and Remediation Tracking',
        'sonar_quality_gate_summary' => '#30 SonarQube Quality Gate Compliance Summary',
    ];
    return $map[$checkId] ?? $checkId;
}

function collectFilesByExtensions(array $treeEntries, array $extensions): array
{
    $files = [];
    foreach ($treeEntries as $entry) {
        if (($entry['type'] ?? '') !== 'blob') {
            continue;
        }
        $path = (string) ($entry['path'] ?? '');
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, $extensions, true)) {
            $files[] = $path;
        }
    }
    return array_values(array_unique($files));
}

function collectPatternHits(string $owner, string $repo, string $pat, array $files, array $rules, int $fileLimit = 25): array
{
    $hits = [];
    foreach (array_slice($files, 0, $fileLimit) as $path) {
        $content = fetchTextFile($owner, $repo, $path, $pat);
        if ($content === null) {
            continue;
        }
        foreach ($rules as $label => $pattern) {
            if (preg_match($pattern, $content)) {
                $hits[] = $path . ' -> ' . $label;
            }
        }
    }
    return array_values(array_unique($hits));
}

function parseRepositoryRef(string $rawUrl): ?array
{
    $url = trim($rawUrl);
    if ($url === '') {
        return null;
    }

    // Support git remote style: git@github.com:owner/repo.git or git@gitlab.com:group/repo.git
    if (preg_match('/^git@(github\.com|gitlab\.com):([^\/\s]+)\/([^\s]+?)(?:\.git)?$/i', $url, $m) === 1) {
        $host = strtolower($m[1]);
        return [
            'provider' => $host === 'gitlab.com' ? 'gitlab' : 'github',
            'owner' => $m[2],
            'repo' => $m[3],
        ];
    }

    // Support git remote style: https://gitlab.com/group/repo.git
    if (preg_match('/^git@github\.com:([^\/]+)\/([^\s]+?)(?:\.git)?$/i', $url, $m) === 1) {
        return ['provider' => 'github', 'owner' => $m[1], 'repo' => $m[2]];
    }

    // Support host-only URLs entered without scheme.
    if (preg_match('/^(github\.com|gitlab\.com)\//i', $url) === 1) {
        $url = 'https://' . $url;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return null;
    }

    $host = strtolower((string) ($parts['host'] ?? ''));
    if (!in_array($host, ['github.com', 'www.github.com', 'gitlab.com', 'www.gitlab.com'], true)) {
        return null;
    }

    $path = trim((string) ($parts['path'] ?? ''), '/');
    if ($path === '') {
        return null;
    }

    $segments = array_values(array_filter(explode('/', $path), static fn($seg) => $seg !== ''));
    if (count($segments) < 2) {
        return null;
    }

    $provider = str_contains($host, 'gitlab') ? 'gitlab' : 'github';

    if ($provider === 'gitlab') {
        // GitLab paths can be nested groups. Repo is the last segment, owner/group is preceding path.
        $repo = rawurldecode((string) end($segments));
        $ownerSegments = array_slice($segments, 0, -1);
        $owner = rawurldecode(implode('/', $ownerSegments));
    } else {
        $owner = rawurldecode($segments[0]);
        $repo = rawurldecode($segments[1]);
    }
    $repo = preg_replace('/\.git$/i', '', $repo) ?? $repo;

    if ($owner === '' || $repo === '') {
        return null;
    }

    if ($provider === 'github') {
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $owner) || !preg_match('/^[A-Za-z0-9._-]+$/', $repo)) {
            return null;
        }
    } else {
        if (!preg_match('/^[A-Za-z0-9._\/-]+$/', $owner) || !preg_match('/^[A-Za-z0-9._-]+$/', $repo)) {
            return null;
        }
    }

    return ['provider' => $provider, 'owner' => $owner, 'repo' => $repo];
}

function canonicalRepositoryUrl(string $provider, string $owner, string $repo): string
{
    $host = $provider === 'gitlab' ? 'gitlab.com' : 'github.com';
    return 'https://' . $host . '/' . $owner . '/' . $repo;
}

function fetchRepositoryMetadata(string $provider, string $owner, string $repo, string $pat): ?array
{
    if ($provider === 'gitlab') {
        $projectPath = rawurlencode($owner . '/' . $repo);
        $project = github_get('https://gitlab.com/api/v4/projects/' . $projectPath, $pat, 30);
        if ($project === null) {
            return null;
        }

        $fullName = (string) ($project['path_with_namespace'] ?? ($owner . '/' . $repo));
        $description = trim((string) ($project['description'] ?? ''));
        $defaultBranch = (string) ($project['default_branch'] ?? 'main');
        $stars = (int) ($project['star_count'] ?? 0);
        $forks = (int) ($project['forks_count'] ?? 0);

        $languages = github_get_languages($owner, $repo, $pat);
        $primaryLanguage = 'Unknown';
        if (!empty($languages)) {
            arsort($languages);
            $primaryLanguage = (string) array_key_first($languages);
        }

        return [
            'id' => $project['id'] ?? null,
            'name' => $project['name'] ?? $repo,
            'full_name' => $fullName,
            'description' => $description,
            'language' => $primaryLanguage,
            'owner' => $owner,
            'stars' => $stars,
            'forks' => $forks,
            'watchers' => $stars,
            'license' => null,
            'default_branch' => $defaultBranch,
            'platform' => 'GitLab',
        ];
    }

    $repoPayload = github_get('https://api.github.com/repos/' . $owner . '/' . $repo, $pat, 30);
    if ($repoPayload === null) {
        return null;
    }

    return [
        'id' => $repoPayload['id'] ?? null,
        'name' => $repoPayload['name'] ?? $repo,
        'full_name' => $repoPayload['full_name'] ?? ($owner . '/' . $repo),
        'description' => trim((string) ($repoPayload['description'] ?? '')),
        'language' => $repoPayload['language'] ?? 'Unknown',
        'owner' => $owner,
        'stars' => (int) ($repoPayload['stargazers_count'] ?? 0),
        'forks' => (int) ($repoPayload['forks_count'] ?? 0),
        'watchers' => (int) ($repoPayload['watchers_count'] ?? 0),
        'license' => $repoPayload['license']['name'] ?? null,
        'default_branch' => $repoPayload['default_branch'] ?? 'HEAD',
        'platform' => 'GitHub',
    ];
}

// ---------------------------------------------------------------------------
// Request handling
// ---------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

$inputData   = null;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $inputData = json_decode(file_get_contents('php://input'), true);
}

$repoUrl = trim($inputData['repo_url'] ?? $_POST['repo_url'] ?? '');
$pat     = trim($inputData['pat']      ?? $_POST['pat']      ?? '');

if ($repoUrl === '' || $pat === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Repository URL and PAT are required.']);
    exit;
}

$repoRef = parseRepositoryRef($repoUrl);
if ($repoRef === null) {
    http_response_code(422);
    echo json_encode(['error' => 'Provide a valid GitHub repository URL.']);
    exit;
}

$provider = $repoRef['provider'];
$owner = $repoRef['owner'];
$repo  = $repoRef['repo'];

// Persist canonical root URL so /tree/... and root URLs map to the same repository record.
$repoUrl = canonicalRepositoryUrl($provider, $owner, $repo);

repo_set_context([
    'provider' => $provider,
    'owner' => $owner,
    'repo' => $repo,
    'pat' => $pat,
]);

// ---------------------------------------------------------------------------
// Fetch repository metadata
// ---------------------------------------------------------------------------
$repoMetadata = fetchRepositoryMetadata($provider, $owner, $repo, $pat);

if ($repoMetadata === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Repository API rejected the request. Verify the URL and token permissions.']);
    exit;
}

$repoName        = $repoMetadata['full_name'] ?? ($owner . '/' . $repo);
$repoDescription = (string) ($repoMetadata['description'] ?? '');
$repoLanguage    = $repoMetadata['language'] ?? 'Unknown';
$repoLicense     = $repoMetadata['license'] ?? null;
$repoStars       = (int) ($repoMetadata['stars'] ?? 0);
$repoForks       = (int) ($repoMetadata['forks'] ?? 0);
$repoWatchers    = (int) ($repoMetadata['watchers'] ?? 0);
$defaultBranch   = (string) ($repoMetadata['default_branch'] ?? 'HEAD');
$platformName    = (string) ($repoMetadata['platform'] ?? ($provider === 'gitlab' ? 'GitLab' : 'GitHub'));

// ---------------------------------------------------------------------------
// Fetch tree and language breakdown (shared across checks)
// ---------------------------------------------------------------------------
$tree      = github_get_tree($owner, $repo, $pat, $defaultBranch);
$languages = github_get_languages($owner, $repo, $pat);

// Pre-select source files (up to 25, smallest first) shared by content checks
$sourceExtensions = ['php', 'js', 'ts', 'tsx', 'jsx', 'mjs', 'py', 'java', 'cs', 'go', 'rb', 'swift', 'c', 'cpp', 'h', 'rs'];
$sourceFiles      = tree_files_by_extensions($tree, $sourceExtensions, 25);

// ---------------------------------------------------------------------------
// Determine which checks were selected
// ---------------------------------------------------------------------------
$checksWereProvided = isset($inputData['checks']) || isset($_POST['checks'])
    || isset($inputData['checks_present']) || isset($_POST['checks_present']);

$rawChecks = $inputData['checks'] ?? $_POST['checks'] ?? [];
if (!is_array($rawChecks)) {
    $rawChecks = [];
}

if (!$checksWereProvided) {
    // Backward compatibility: if a client omits checks entirely, run all by default.
    $rawChecks = [
        'dependency_risk', 'hardening', 'performance', 'maintainability', 'code_intelligence',
        'secret_scanner', 'dependency_cve', 'license_check', 'git_history', 'security_config',
        'complexity_cyclomatic_avg', 'complexity_cyclomatic_max', 'complexity_cognitive_avg', 'complexity_cognitive_max',
        'complexity_function_size_avg', 'complexity_function_size_max', 'complexity_class_size_avg', 'complexity_class_size_max',
        'complexity_nesting_depth_avg', 'complexity_nesting_depth_max',
        'sonar_bugs_reliability', 'sonar_code_smells', 'sonar_duplication_detection', 'sonar_complexity_limits',
        'sonar_size_control', 'sonar_naming_readability', 'sonar_dead_code', 'sonar_error_handling',
        'sonar_technical_debt', 'sonar_quality_gate_summary',
    ];
}
$selectedChecks = array_values(array_unique(array_filter(array_map('trim', $rawChecks))));

// ---------------------------------------------------------------------------
// Initialize results arrays
// ---------------------------------------------------------------------------
$results         = [];  // legacy compatibility
$allFindings     = [];
$allRecommendations = [];
$allSkills       = [];
$checkResults    = [];

// ---------------------------------------------------------------------------
// Run all checks (based on checkbox selection)
// ---------------------------------------------------------------------------
function run_check(string $name, callable $fn): array
{
    $result = $fn();
    $findings = $result['findings'] ?? [];
    $hasFindings = !empty($findings);
    $recommendations = $hasFindings ? ($result['recommendations'] ?? []) : [];
    if ($hasFindings && empty($recommendations)) {
        $recommendations = [[
            'recommendation_text' => $name . ': Review reported findings and apply targeted remediation controls.',
            'priority' => 'Medium',
        ]];
    }

    return [
        'name'            => $name,
        'finding_count'   => count($findings),
        'status'          => $hasFindings ? 'issues_found' : 'clean',
        'findings'        => $findings,
        'recommendations' => $recommendations,
        'skills'          => $result['skills']          ?? [],
    ];
}

function merge_check_outputs(array ...$outputs): array
{
    $merged = ['findings' => [], 'recommendations' => [], 'skills' => []];
    foreach ($outputs as $out) {
        $merged['findings'] = array_merge($merged['findings'], $out['findings'] ?? []);
        $merged['recommendations'] = array_merge($merged['recommendations'], $out['recommendations'] ?? []);
        $merged['skills'] = array_merge($merged['skills'], $out['skills'] ?? []);
    }
    return $merged;
}

$newCheckMap = [
    'dependency_risk'  => ['#1 Insecure Design and Logic Flaws',      fn() => check_insecure_design($owner, $repo, $pat, $sourceFiles)],
    'hardening'        => ['#2 Vulnerable and Outdated Dependencies', fn() => check_dependency_hardening($owner, $repo, $pat, $tree)],
    'performance'      => ['#3 CI/CD and Software Integrity Risks',   fn() => check_ci_cd_integrity($owner, $repo, $pat, $tree)],
    'maintainability'  => ['#4 Logging and Monitoring Coverage',      fn() => check_logging_monitoring($owner, $repo, $pat, $tree, $sourceFiles)],
    'code_intelligence'=> ['#5 Code Quality, Performance and Repo Health', fn() => merge_check_outputs(
        check_file_summary($tree, $languages),
        check_complexity($owner, $repo, $pat, $sourceFiles),
        check_duplication($owner, $repo, $pat, $sourceFiles),
        check_todos($owner, $repo, $pat, $sourceFiles)
    )],
    'secret_scanner'  => ['#6 Secret & Credential Scanner',   fn() => check_secrets($owner, $repo, $pat, $tree, $sourceFiles)],
    'dependency_cve'  => ['#7 Dependency CVE Audit (OSV.dev)',   fn() => check_dependencies($owner, $repo, $pat, $tree)],
    'license_check'   => ['#8 License Compliance Scanner',          fn() => check_license($owner, $repo, $pat, $tree, $repoLicense)],
    'git_history'     => ['#9 Git History Risk Analysis',      fn() => check_git_history($owner, $repo, $pat)],
    'security_config' => ['#10 Security Header & Config Auditor',  fn() => check_security_config($owner, $repo, $pat, $tree)],
    'complexity_cyclomatic_avg' => ['#11 Cyclomatic Complexity Average', fn() => check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_cyclomatic_avg')],
    'complexity_cyclomatic_max' => ['#12 Cyclomatic Complexity Maximum', fn() => check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_cyclomatic_max')],
    'complexity_cognitive_avg' => ['#13 Cognitive Complexity Average', fn() => check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_cognitive_avg')],
    'complexity_cognitive_max' => ['#14 Cognitive Complexity Maximum', fn() => check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_cognitive_max')],
    'complexity_function_size_avg' => ['#15 Function Size Average', fn() => check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_function_size_avg')],
    'complexity_function_size_max' => ['#16 Function Size Maximum', fn() => check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_function_size_max')],
    'complexity_class_size_avg' => ['#17 Class Size Average', fn() => check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_class_size_avg')],
    'complexity_class_size_max' => ['#18 Class Size Maximum', fn() => check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_class_size_max')],
    'complexity_nesting_depth_avg' => ['#19 Nesting Depth Average', fn() => check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_nesting_depth_avg')],
    'complexity_nesting_depth_max' => ['#20 Nesting Depth Maximum', fn() => check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_nesting_depth_max')],
    'sonar_bugs_reliability' => ['#21 SonarQube Bugs and Reliability Issues', fn() => check_sonarqube_rule($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'sonar_bugs_reliability')],
    'sonar_code_smells' => ['#22 SonarQube Code Smells and Maintainability Issues', fn() => check_sonarqube_rule($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'sonar_code_smells')],
    'sonar_duplication_detection' => ['#23 SonarQube Duplicated Code Detection', fn() => check_sonarqube_rule($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'sonar_duplication_detection')],
    'sonar_complexity_limits' => ['#24 SonarQube Cyclomatic and Cognitive Complexity Limits', fn() => check_sonarqube_rule($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'sonar_complexity_limits')],
    'sonar_size_control' => ['#25 SonarQube Function and Class Size Control', fn() => check_sonarqube_rule($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'sonar_size_control')],
    'sonar_naming_readability' => ['#26 SonarQube Naming Convention and Readability Checks', fn() => check_sonarqube_rule($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'sonar_naming_readability')],
    'sonar_dead_code' => ['#27 SonarQube Dead or Commented-Out Code Detection', fn() => check_sonarqube_rule($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'sonar_dead_code')],
    'sonar_error_handling' => ['#28 SonarQube Error Handling and Defensive Coding Patterns', fn() => check_sonarqube_rule($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'sonar_error_handling')],
    'sonar_technical_debt' => ['#29 SonarQube Technical Debt and Remediation Tracking', fn() => check_sonarqube_rule($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'sonar_technical_debt')],
    'sonar_quality_gate_summary' => ['#30 SonarQube Quality Gate Compliance Summary', fn() => check_sonarqube_rule($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'sonar_quality_gate_summary')],
];

foreach ($selectedChecks as $checkId) {
    if (!isset($newCheckMap[$checkId])) {
        continue;
    }
    [$name, $fn] = $newCheckMap[$checkId];
    $r              = run_check($name, $fn);
    $checkResults[] = ['name' => $r['name'], 'finding_count' => $r['finding_count'], 'status' => $r['status']];
    $allFindings          = array_merge($allFindings, $r['findings']);
    $allRecommendations   = array_merge($allRecommendations, $r['recommendations']);
    $allSkills            = array_merge($allSkills, $r['skills']);
}

$dedupFindings = [];
foreach ($allFindings as $finding) {
    $key = strtolower(trim((string) ($finding['category'] ?? ''))) . '|' .
        strtolower(trim((string) ($finding['title'] ?? ''))) . '|' .
        strtolower(trim((string) ($finding['description'] ?? ''))) . '|' .
        strtolower(trim((string) ($finding['severity'] ?? '')));
    $dedupFindings[$key] = $finding;
}
$allFindings = array_values($dedupFindings);

$dedupRecommendations = [];
foreach ($allRecommendations as $recommendation) {
    $key = strtolower(trim((string) ($recommendation['recommendation_text'] ?? ''))) . '|' .
        strtolower(trim((string) ($recommendation['priority'] ?? '')));
    $dedupRecommendations[$key] = $recommendation;
}
$allRecommendations = array_values($dedupRecommendations);

if (empty($allSkills) && !empty($languages)) {
    $langTotal = array_sum(array_values($languages));
    foreach ($languages as $lang => $bytes) {
        $pct = $langTotal > 0 ? round(($bytes / $langTotal) * 100) : 0;
        $allSkills[] = [
            'skill_name' => (string) $lang,
            'proficiency_level' => $pct >= 50 ? 'Primary Language' : ($pct >= 20 ? 'Secondary Language' : 'Minor Language'),
            'risk_level' => 'Low',
        ];
    }
}

$skillsByKey = [];
foreach ($allSkills as $s) {
    $name = trim((string) ($s['skill_name'] ?? ''));
    if ($name === '') {
        continue;
    }
    $key = strtolower($name);
    if (isset($skillsByKey[$key])) {
        continue;
    }
    $skillsByKey[$key] = [
        'skill_name' => $name,
        'proficiency_level' => (string) ($s['proficiency_level'] ?? 'Intermediate'),
        'risk_level' => (string) ($s['risk_level'] ?? 'Low'),
    ];
}
$allSkills = array_values($skillsByKey);

// PAT no longer needed — clear from memory
$pat = '';
unset($pat);

if ($repoDescription === '') {
    $allFindings[] = [
        'category'    => 'File Summary',
        'title'       => 'Missing repository description',
        'description' => 'A short description helps users understand the project purpose.',
        'severity'    => 'Low',
    ];
}

// ---------------------------------------------------------------------------
// Severity-weighted score (max deduction 60 points, floor 10)
// ---------------------------------------------------------------------------
$severityWeights = ['High' => 8, 'Medium' => 4, 'Low' => 1];
$deduction = 0;
foreach ($allFindings as $f) {
    $deduction += $severityWeights[$f['severity']] ?? 1;
}
$overallScore = max(10, 100 - min(60, $deduction));

// ---------------------------------------------------------------------------
// Persist to database
// ---------------------------------------------------------------------------
try {
    $pdo = db_connection();
    ensureScanReportColumns($pdo);
    $pdo->beginTransaction();

    $pdo->prepare(
        'INSERT INTO repositories (repo_url, platform) VALUES (:url, :platform)
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), platform = VALUES(platform)'
    )->execute([':url' => $repoUrl, ':platform' => $platformName]);
    $repositoryId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO scans (repository_id, summary_score, total_findings, total_skills, selected_checks_json)
         VALUES (:rid, :score, :findings, :skills, :checks_json)'
    )->execute([
        ':rid'         => $repositoryId,
        ':score'       => $overallScore,
        ':findings'    => count($allFindings),
        ':skills'      => count($allSkills),
        ':checks_json' => json_encode($selectedChecks),
    ]);
    $scanId = (int) $pdo->lastInsertId();

    $findingStmt = $pdo->prepare(
        'INSERT INTO findings (scan_id, category, title, description, severity)
         VALUES (:scan_id, :category, :title, :description, :severity)'
    );
    foreach ($allFindings as $f) {
        $findingStmt->execute([
            ':scan_id'     => $scanId,
            ':category'    => $f['category'],
            ':title'       => $f['title'],
            ':description' => $f['description'],
            ':severity'    => $f['severity'],
        ]);
    }

    $skillStmt  = $pdo->prepare(
        'INSERT INTO skills (scan_id, skill_name, proficiency_level, risk_level)
         VALUES (:scan_id, :name, :level, :risk)'
    );
    foreach ($allSkills as $s) {
        $skillStmt->execute([
            ':scan_id' => $scanId,
            ':name'    => $s['skill_name'],
            ':level'   => $s['proficiency_level'],
            ':risk'    => $s['risk_level'],
        ]);
    }

    $seenRecs = [];
    $recStmt  = $pdo->prepare(
        'INSERT INTO recommendations (scan_id, recommendation_text, priority)
         VALUES (:scan_id, :text, :priority)'
    );
    foreach ($allRecommendations as $r) {
        $key = md5($r['recommendation_text']);
        if (isset($seenRecs[$key])) {
            continue;
        }
        $seenRecs[$key] = true;
        $recStmt->execute([
            ':scan_id'  => $scanId,
            ':text'     => $r['recommendation_text'],
            ':priority' => $r['priority'],
        ]);
    }

    $checkStmt = $pdo->prepare(
        'INSERT INTO check_runs (scan_id, check_name, status, finding_count)
         VALUES (:scan_id, :name, :status, :count)'
    );
    foreach ($checkResults as $cr) {
        $checkStmt->execute([
            ':scan_id' => $scanId,
            ':name'    => $cr['name'],
            ':status'  => $cr['status'],
            ':count'   => $cr['finding_count'],
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'message'         => 'Repository analysis complete.',
        'scan_id'         => $scanId,
        'report_urls'     => [
            'summary'  => 'api/report.php?scan_id=' . $scanId,
            'download' => 'api/report.php?scan_id=' . $scanId . '&download=1&format=doc',
        ],
        'repository'      => [
            'id'          => $repoMetadata['id']   ?? null,
            'name'        => $repoMetadata['name'] ?? $repoName,
            'full_name'   => $repoName,
            'description' => $repoDescription,
            'language'    => $repoLanguage,
            'owner'       => $owner,
            'stars'       => $repoStars,
            'forks'       => $repoForks,
            'watchers'    => $repoWatchers,
            'platform'    => $platformName,
        ],
        'scan'            => ['summary_score' => $overallScore],
        'selected_checks' => $selectedChecks,
        'results'         => $results,
        'checks'          => $checkResults,
        'findings'        => $allFindings,
        'skills'          => array_values($allSkills),
        'recommendations' => array_values($allRecommendations),
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'error'   => 'Failed to save scan results.',
        'details' => $e->getMessage(),
    ]);
}
