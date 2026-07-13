<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $pdo = db_connection();

    $repositoryCount = (int) $pdo->query('SELECT COUNT(*) FROM repositories')->fetchColumn();
    $scanCount = (int) $pdo->query('SELECT COUNT(*) FROM scans')->fetchColumn();
    $findingCount = (int) $pdo->query('SELECT COUNT(*) FROM findings')->fetchColumn();
    $skillCount = (int) $pdo->query('SELECT COUNT(*) FROM skills')->fetchColumn();
    $recommendationCount = (int) $pdo->query('SELECT COUNT(*) FROM recommendations')->fetchColumn();
    $averageScore = $pdo->query('SELECT AVG(summary_score) FROM scans')->fetchColumn();

    $recentScansStmt = $pdo->query(
        'SELECT s.id, s.scan_date, s.summary_score, s.total_findings, s.total_skills, r.repo_url
         FROM scans s
         JOIN repositories r ON s.repository_id = r.id
         ORDER BY s.scan_date DESC
         LIMIT 6'
    );
    $recentScans = $recentScansStmt->fetchAll();
    $latestScore = !empty($recentScans) && $recentScans[0]['summary_score'] !== null
        ? (int) $recentScans[0]['summary_score']
        : null;

    echo json_encode([
        'ok' => true,
        'summary' => [
            'repository_count' => $repositoryCount,
            'scan_count' => $scanCount,
            'finding_count' => $findingCount,
            'skill_count' => $skillCount,
            'recommendation_count' => $recommendationCount,
            'latest_score' => $latestScore,
            'latest_score_text' => $latestScore !== null ? (string) $latestScore : '-',
            'average_score' => $averageScore !== false && $averageScore !== null ? (float) $averageScore : null,
            'average_score_text' => $averageScore !== false && $averageScore !== null
                ? number_format((float) $averageScore, 0)
                : '-',
        ],
        'recent_scans' => array_map(static function (array $scan): array {
            return [
                'id' => (int) $scan['id'],
                'repo_url' => (string) $scan['repo_url'],
                'scan_date' => (string) $scan['scan_date'],
                'summary_score' => $scan['summary_score'] !== null ? (int) $scan['summary_score'] : null,
                'total_findings' => (int) $scan['total_findings'],
                'total_skills' => (int) $scan['total_skills'],
            ];
        }, $recentScans),
        'updated_at' => gmdate('c'),
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to load dashboard analytics.',
    ], JSON_UNESCAPED_SLASHES);
}