<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$token = trim((string) (getenv('GITHUB_TOKEN') ?: ''));

echo json_encode([
    'configured' => $token !== '',
]);
