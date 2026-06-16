<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

$repoUrl = trim($_POST['repo_url'] ?? '');
$pat = trim($_POST['pat'] ?? '');

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
        'INSERT INTO scans (repository_id, summary_score) VALUES (:repository_id, :summary_score)'
    );
    $scanStmt->execute([
        ':repository_id' => $repositoryId,
        ':summary_score' => null,
    ]);

    $scanId = (int) $pdo->lastInsertId();

    $pdo->commit();

    echo json_encode([
        'message' => 'Repository access verified and initial scan record created.',
        'scan_id' => $scanId,
        'repository' => [
            'id' => $repoPayload['id'] ?? null,
            'full_name' => $repoPayload['full_name'] ?? "{$owner}/{$repo}",
            'default_branch' => $repoPayload['default_branch'] ?? 'main',
            'language' => $repoPayload['language'] ?? null,
        ],
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
