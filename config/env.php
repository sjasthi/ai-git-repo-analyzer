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

// Writes/overwrites a single KEY=value line in the .env file (creating the file
// if needed) and updates the current process's environment to match.
function save_env_value(string $path, string $key, string $value): void
{
    $lines = is_file($path) ? (file($path, FILE_IGNORE_NEW_LINES) ?: []) : [];
    $found = false;

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }
        [$lineKey] = array_pad(explode('=', $trimmed, 2), 2, '');
        if (trim($lineKey) === $key) {
            $lines[$i] = $key . '=' . $value;
            $found = true;
            break;
        }
    }

    if (!$found) {
        $lines[] = $key . '=' . $value;
    }

    file_put_contents($path, implode("\n", $lines) . "\n");

    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
}

load_env_file(__DIR__ . '/../.env');
