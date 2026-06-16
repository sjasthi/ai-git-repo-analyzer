<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

try {
    $pdo = db_connection();
    $pdo->query('SELECT 1');

    echo json_encode([
        'status' => 'ok',
        'database' => 'connected',
        'timestamp' => date(DATE_ATOM),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'database' => 'disconnected',
        'message' => $e->getMessage(),
    ]);
}
