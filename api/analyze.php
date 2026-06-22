<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

$inputData = null;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $inputData = json_decode($raw, true);
}

$repoUrl = trim($inputData['repo_url'] ?? $_POST['repo_url'] ?? '');
$pat = trim($inputData['pat'] ?? $_POST['pat'] ?? '');

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
$repo = $matches[2];
$githubApiUrl = "https://api.github.com/repos/{$owner}/{$repo}";

$ch = curl_init($githubApiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/vnd.github+json',
        "Authorization: Bearer {$pat}",
        'User-Agent: ai-git-repo-analyzer',
    ],
    CURLOPT_TIMEOUT => 30,
]);

$responseBody = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Never persist PAT; remove it from memory as soon as API call is completed.
$pat = '';
unset($pat);

if ($responseBody === false || $curlError !== '') {
    http_response_code(502);
    echo json_encode(['error' => 'Unable to reach GitHub API.', 'details' => $curlError]);
    exit;
}

$repoPayload = json_decode($responseBody, true);
if (!is_array($repoPayload) || $httpCode >= 400) {
    http_response_code(401);
    echo json_encode([
        'error' => 'GitHub API rejected the request. Verify URL and PAT permissions.',
        'http_code' => $httpCode,
        'github_message' => $repoPayload['message'] ?? 'Unknown error',
    ]);
    exit;
}

$repoName = $repoPayload['full_name'] ?? "{$owner}/{$repo}";
$repoDescription = trim((string) ($repoPayload['description'] ?? ''));
$repoLanguage = $repoPayload['language'] ?? 'Unknown';
$repoLicense = $repoPayload['license']['name'] ?? null;
$repoStars = (int) ($repoPayload['stargazers_count'] ?? 0);
$repoForks = (int) ($repoPayload['forks_count'] ?? 0);
$repoWatchers = (int) ($repoPayload['watchers_count'] ?? 0);

$findings = [];
$recommendations = [];
$skills = [];

if ($repoLicense === null) {
    $findings[] = [
        'category' => 'Compliance',
        'title' => 'Missing license information',
        'description' => 'The repository does not specify a license. Adding one clarifies reuse permissions.',
        'severity' => 'High',
    ];
    $recommendations[] = [
        'recommendation_text' => 'Add a license file to the repository to clarify project usage and distribution rights.',
        'priority' => 'High',
    ];
}

if ($repoDescription === '') {
    $findings[] = [
        'category' => 'Stability',
        'title' => 'Missing repository description',
        'description' => 'A short repository description helps users understand the project purpose.',
        'severity' => 'Low',
    ];
    $recommendations[] = [
        'recommendation_text' => 'Provide a clear repository description and README summary.',
        'priority' => 'Medium',
    ];
}

if (strcasecmp($repoLanguage, 'PHP') === 0) {
    $skills[] = [
        'skill_name' => 'PHP',
        'proficiency_level' => 'Advanced',
        'risk_level' => 'Medium',
    ];
    $recommendations[] = [
        'recommendation_text' => 'Follow PHP coding standards (PSR-12) and add error handling for entry points.',
        'priority' => 'Medium',
    ];
} elseif ($repoLanguage !== 'Unknown') {
    $skills[] = [
        'skill_name' => $repoLanguage,
        'proficiency_level' => 'Intermediate',
        'risk_level' => 'Low',
    ];
}

if (empty($findings) && empty($recommendations)) {
    $recommendations[] = [
        'recommendation_text' => 'Review repository documentation and add any missing metadata such as license or description.',
        'priority' => 'Low',
    ];
}

$summaryScore = 100 - min(30, count($findings) * 10);
$overallScore = max(50, $summaryScore);

try {
    $pdo = db_connection();
    $pdo->beginTransaction();

    $repositoryStmt = $pdo->prepare(
        'INSERT INTO repositories (repo_url, platform) VALUES (:repo_url, :platform)
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), platform = VALUES(platform)'
    );
    $repositoryStmt->execute([
        ':repo_url' => $repoUrl,
        ':platform' => 'GitHub',
    ]);

    $repositoryId = (int) $pdo->lastInsertId();

    $scanStmt = $pdo->prepare(
        'INSERT INTO scans (repository_id, summary_score, total_findings, total_skills) VALUES (:repository_id, :summary_score, :total_findings, :total_skills)'
    );
    $scanStmt->execute([
        ':repository_id' => $repositoryId,
        ':summary_score' => $overallScore,
        ':total_findings' => count($findings),
        ':total_skills' => count($skills),
    ]);

    $scanId = (int) $pdo->lastInsertId();

    // Insert findings and recommendations for this initial pass
    $findingStmt = $pdo->prepare(
        'INSERT INTO findings (scan_id, category, title, description, severity) VALUES (:scan_id, :category, :title, :description, :severity)'
    );
    foreach ($findings as $finding) {
        $findingStmt->execute([
            ':scan_id' => $scanId,
            ':category' => $finding['category'],
            ':title' => $finding['title'],
            ':description' => $finding['description'],
            ':severity' => $finding['severity'],
        ]);
    }

    $skillStmt = $pdo->prepare(
        'INSERT INTO skills (scan_id, skill_name, proficiency_level, risk_level) VALUES (:scan_id, :skill_name, :proficiency_level, :risk_level)'
    );
    foreach ($skills as $skill) {
        $skillStmt->execute([
            ':scan_id' => $scanId,
            ':skill_name' => $skill['skill_name'],
            ':proficiency_level' => $skill['proficiency_level'],
            ':risk_level' => $skill['risk_level'],
        ]);
    }

    $recommendationStmt = $pdo->prepare(
        'INSERT INTO recommendations (scan_id, recommendation_text, priority) VALUES (:scan_id, :recommendation_text, :priority)'
    );
    foreach ($recommendations as $recommendation) {
        $recommendationStmt->execute([
            ':scan_id' => $scanId,
            ':recommendation_text' => $recommendation['recommendation_text'],
            ':priority' => $recommendation['priority'],
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'message' => 'Repository access verified and initial scan record created.',
        'scan_id' => $scanId,
        'repository' => [
            'id' => $repoPayload['id'] ?? null,
            'name' => $repoPayload['name'] ?? $repoName,
            'full_name' => $repoName,
            'description' => $repoDescription,
            'language' => $repoLanguage,
            'owner' => $owner,
            'stars' => $repoStars,
            'forks' => $repoForks,
            'watchers' => $repoWatchers,
        ],
        'scan' => [
            'summary_score' => $overallScore,
        ],
        'findings' => $findings,
        'skills' => $skills,
        'recommendations' => $recommendations,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to create repository scan record.',
        'details' => $e->getMessage(),
    ]);
}
