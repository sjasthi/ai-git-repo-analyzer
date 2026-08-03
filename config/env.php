<?php

declare(strict_types=1);

// Lightweight .env loader (no Composer dependency available in this project).
// Reads KEY=VALUE lines from the project-root .env file and exposes them via getenv().
// Existing environment variables (e.g. set by the OS or a web server) always win.
function load_env_file(string $path): void
{
    static $loaded = false;
    if ($loaded || !is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key   = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }

    $loaded = true;
}

load_env_file(__DIR__ . '/../.env');
