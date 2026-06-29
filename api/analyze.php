<?php

declare(strict_types=1);

set_time_limit(120); // Allow up to 2 minutes for all API calls

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/github_helper.php';
require_once __DIR__ . '/checks/check_secrets.php';
require_once __DIR__ . '/checks/check_owasp.php';
require_once __DIR__ . '/checks/check_dependencies.php';
require_once __DIR__ . '/checks/check_complexity.php';
require_once __DIR__ . '/checks/check_file_summary.php';
require_once __DIR__ . '/checks/check_todos.php';
require_once __DIR__ . '/checks/check_license.php';
require_once __DIR__ . '/checks/check_git_history.php';
require_once __DIR__ . '/checks/check_duplication.php';
require_once __DIR__ . '/checks/check_security_config.php';

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

if (!preg_match('#^https?://github\.com/([^/]+)/([^/]+?)(?:\.git)?/?$#i', $repoUrl, $matches)) {
    http_response_code(422);
    echo json_encode(['error' => 'Provide a valid GitHub repository URL.']);
    exit;
}

$owner = $matches[1];
$repo  = $matches[2];

// ---------------------------------------------------------------------------
// Fetch repository metadata
// ---------------------------------------------------------------------------
$repoPayload = github_get("https://api.github.com/repos/{$owner}/{$repo}", $pat, 30);

if ($repoPayload === null) {
    http_response_code(401);
    echo json_encode(['error' => 'GitHub API rejected the request. Verify the URL and PAT permissions.']);
    exit;
}

$repoName        = $repoPayload['full_name']               ?? "{$owner}/{$repo}";
$repoDescription = trim((string) ($repoPayload['description'] ?? ''));
$repoLanguage    = $repoPayload['language']                ?? 'Unknown';
$repoLicense     = $repoPayload['license']['name']         ?? null;
$repoStars       = (int) ($repoPayload['stargazers_count'] ?? 0);
$repoForks       = (int) ($repoPayload['forks_count']      ?? 0);
$repoWatchers    = (int) ($repoPayload['watchers_count']   ?? 0);
$defaultBranch   = $repoPayload['default_branch']          ?? 'HEAD';

// ---------------------------------------------------------------------------
// Fetch tree and language breakdown (shared across checks)
// ---------------------------------------------------------------------------
$tree      = github_get_tree($owner, $repo, $pat, $defaultBranch);
$languages = github_get_languages($owner, $repo, $pat);

// Pre-select source files (up to 25, smallest first) shared by content checks
$sourceExtensions = ['php', 'js', 'ts', 'tsx', 'jsx', 'mjs', 'py', 'java', 'cs', 'go', 'rb', 'swift', 'c', 'cpp', 'h', 'rs'];
$sourceFiles      = tree_files_by_extensions($tree, $sourceExtensions, 25);

// ---------------------------------------------------------------------------
// Run all 10 static analysis checks
// ---------------------------------------------------------------------------
$allFindings        = [];
$allRecommendations = [];
$allSkills          = [];
$checkResults       = [];

function run_check(string $name, callable $fn): array
{
    $result = $fn();
    return [
        'name'            => $name,
        'finding_count'   => count($result['findings'] ?? []),
        'status'          => empty($result['findings']) ? 'clean' : 'issues_found',
        'findings'        => $result['findings']        ?? [],
        'recommendations' => $result['recommendations'] ?? [],
        'skills'          => $result['skills']          ?? [],
    ];
}

$checks = [
    'Secret Scanner'  => fn() => check_secrets($owner, $repo, $pat, $tree, $sourceFiles),
    'OWASP'           => fn() => check_owasp($owner, $repo, $pat, $sourceFiles),
    'Dependencies'    => fn() => check_dependencies($owner, $repo, $pat, $tree),
    'Complexity'      => fn() => check_complexity($owner, $repo, $pat, $sourceFiles),
    'File Summary'    => fn() => check_file_summary($tree, $languages),
    'Code Quality'    => fn() => check_todos($owner, $repo, $pat, $sourceFiles),
    'License'         => fn() => check_license($owner, $repo, $pat, $tree, $repoLicense),
    'Git History'     => fn() => check_git_history($owner, $repo, $pat),
    'Duplication'     => fn() => check_duplication($owner, $repo, $pat, $sourceFiles),
    'Security Config' => fn() => check_security_config($owner, $repo, $pat, $tree),
];

foreach ($checks as $name => $fn) {
    $r              = run_check($name, $fn);
    $checkResults[] = ['name' => $r['name'], 'finding_count' => $r['finding_count'], 'status' => $r['status']];
    $allFindings          = array_merge($allFindings, $r['findings']);
    $allRecommendations   = array_merge($allRecommendations, $r['recommendations']);
    $allSkills            = array_merge($allSkills, $r['skills']);
}

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
    )->execute([':url' => $repoUrl, ':platform' => 'GitHub']);
    $repositoryId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO scans (repository_id, summary_score, total_findings, total_skills)
         VALUES (:rid, :score, :findings, :skills)'
    )->execute([
        ':rid'      => $repositoryId,
        ':score'    => $overallScore,
        ':findings' => count($allFindings),
        ':skills'   => count($allSkills),
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

    $seenSkills = [];
    $skillStmt  = $pdo->prepare(
        'INSERT INTO skills (scan_id, skill_name, proficiency_level, risk_level)
         VALUES (:scan_id, :name, :level, :risk)'
    );
    foreach ($allSkills as $s) {
        $key = strtolower($s['skill_name']);
        if (isset($seenSkills[$key])) {
            continue;
        }
        $seenSkills[$key] = true;
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
            'download' => 'api/report.php?scan_id=' . $scanId . '&download=1&format=txt',
        ],
        'repository'      => [
            'id'          => $repoPayload['id']   ?? null,
            'name'        => $repoPayload['name'] ?? $repoName,
            'full_name'   => $repoName,
            'description' => $repoDescription,
            'language'    => $repoLanguage,
            'owner'       => $owner,
            'stars'       => $repoStars,
            'forks'       => $repoForks,
            'watchers'    => $repoWatchers,
        ],
        'scan'            => ['summary_score' => $overallScore],
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
