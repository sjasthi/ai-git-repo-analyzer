<?php

declare(strict_types=1);

/**
 * Check 1: Secret & Credential Scanner
 * Scans source files for hardcoded API keys, tokens, passwords, and committed .env files.
 */
function check_secrets(string $owner, string $repo, string $pat, array $tree, array $sourceFiles): array
{
    $patterns = [
        'AWS Access Key'      => '/AKIA[0-9A-Z]{16}/',
        'AWS Secret Key'      => '/aws_secret_access_key\s*[=:]\s*["\']?[A-Za-z0-9\/+]{40}/',
        'GitHub Token'        => '/ghp_[A-Za-z0-9]{36}|github_pat_[A-Za-z0-9_]{82}/',
        'Google API Key'      => '/AIza[0-9A-Za-z\-_]{35}/',
        'Private Key Block'   => '/-----BEGIN (RSA |EC |DSA |OPENSSH )?PRIVATE KEY-----/',
        'Generic Password'    => '/(?:password|passwd|pwd)\s*[=:]\s*["\'][^"\']{6,}["\']/',
        'Generic Secret'      => '/(?:secret|api_key|apikey|token)\s*[=:]\s*["\'][^"\']{8,}["\']/',
        'DB Connection String' => '/(?:mysql|postgres|mongodb|redis):\/\/[^@:\s]+:[^@\s]+@/',
        'Slack Token'         => '/xox[baprs]-[0-9A-Za-z\-]{10,48}/',
        'Stripe Key'          => '/(?:sk|pk)_(?:live|test)_[0-9a-zA-Z]{24,}/',
    ];

    $findings = [];
    $flaggedPaths = [];

    // Check committed .env files first
    foreach ($tree as $node) {
        if ($node['type'] !== 'blob') {
            continue;
        }
        $basename = basename($node['path']);
        if (preg_match('/^\.env(\.|$)/', $basename) && $basename !== '.env.example' && $basename !== '.env.sample') {
            $content = github_get_file_content($owner, $repo, $node['path'], $pat);
            if ($content !== null) {
                $findings[] = [
                    'category'    => 'Secret Scanner',
                    'title'       => "Environment file committed: {$node['path']}",
                    'description' => "The file `{$node['path']}` is tracked in the repository. Environment files often contain production credentials and must be excluded via .gitignore.",
                    'severity'    => 'High',
                ];
            }
        }
    }

    // Scan source files for credential patterns
    foreach ($sourceFiles as $fileNode) {
        $path = $fileNode['path'];
        if (preg_match('/\.(min\.(js|css)|map|lock)$/', $path)) {
            continue;
        }
        $content = github_get_file_content($owner, $repo, $path, $pat);
        if ($content === null) {
            continue;
        }
        foreach ($patterns as $name => $regex) {
            if (preg_match($regex, $content)) {
                $flaggedPaths[] = $path;
                $findings[] = [
                    'category'    => 'Secret Scanner',
                    'title'       => "Potential {$name} in {$path}",
                    'description' => "A pattern matching a {$name} was found in `{$path}`. Verify the value and rotate any real credential immediately. Use environment variables instead of hardcoding.",
                    'severity'    => 'High',
                ];
                break; // one finding per file max
            }
        }
    }

    $recommendations = [];
    if (!empty($findings)) {
        $recommendations[] = [
            'recommendation_text' => 'Rotate all exposed credentials immediately. Add .env and secret files to .gitignore. Use a pre-commit hook (git-secrets, trufflehog) to block future leaks.',
            'priority'            => 'High',
        ];
    } else {
        $recommendations[] = [
            'recommendation_text' => 'No hardcoded secrets detected in sampled files. Add a pre-commit secret-scanning hook (git-secrets, detect-secrets) to keep it that way.',
            'priority'            => 'Low',
        ];
    }

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}
