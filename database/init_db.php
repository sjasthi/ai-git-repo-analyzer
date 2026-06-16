<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

try {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3307';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASSWORD') ?: '';

    $bootstrapDsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    $bootstrapPdo = new PDO(
        $bootstrapDsn,
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $sql = file_get_contents(__DIR__ . '/schema.sql');
    if ($sql === false) {
        throw new RuntimeException('Unable to read schema.sql');
    }

    $bootstrapPdo->exec($sql);

    echo "Database initialized successfully.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Database initialization failed: ' . $e->getMessage() . "\n";
}
