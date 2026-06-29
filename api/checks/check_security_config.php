<?php

declare(strict_types=1);

/**
 * Check 10: Security Header & Config Auditor
 * Inspects web server configs, Docker files, and .gitignore for security misconfigurations.
 */
function check_security_config(string $owner, string $repo, string $pat, array $tree): array
{
    $findings        = [];
    $recommendations = [];

    $securityHeaders = [
        'X-Frame-Options'           => 'Clickjacking protection missing',
        'X-Content-Type-Options'    => 'MIME-sniffing protection (nosniff) missing',
        'Content-Security-Policy'   => 'Content Security Policy (CSP) header missing',
        'Strict-Transport-Security' => 'HTTP Strict Transport Security (HSTS) missing',
        'Referrer-Policy'           => 'Referrer-Policy header missing',
        'Permissions-Policy'        => 'Permissions-Policy (Feature-Policy) header missing',
    ];

    // --- Web server config checks ---
    $webConfigFiles = ['.htaccess', 'nginx.conf', 'nginx.conf.example', 'nginx.conf.sample', 'web.config'];
    $foundHeaders   = [];
    $webConfigFound = false;

    foreach ($webConfigFiles as $cfName) {
        $node = tree_find_file($tree, $cfName);
        if (!$node) {
            continue;
        }
        $webConfigFound = true;
        $content = github_get_file_content($owner, $repo, $node['path'], $pat);
        if ($content === null) {
            continue;
        }

        foreach ($securityHeaders as $header => $_) {
            if (stripos($content, $header) !== false) {
                $foundHeaders[$header] = true;
            }
        }

        // Directory listing
        if (preg_match('/Options\s+[+]?Indexes/i', $content) && !preg_match('/Options\s+-Indexes/i', $content)) {
            $findings[] = [
                'category'    => 'Security Config',
                'title'       => "Directory listing enabled in {$node['path']}",
                'description' => "`{$node['path']}` enables Apache directory listing (Options +Indexes). This exposes folder contents. Use `Options -Indexes`.",
                'severity'    => 'Medium',
            ];
        }

        // No HTTPS redirect
        if (!preg_match('/https|ssl|rewritecond.+http/i', $content)) {
            $findings[] = [
                'category'    => 'Security Config',
                'title'       => "No HTTPS redirect detected in {$node['path']}",
                'description' => "`{$node['path']}` does not appear to redirect HTTP to HTTPS. Add a redirect rule to enforce encrypted connections.",
                'severity'    => 'Medium',
            ];
        }
    }

    // Report missing security headers only when a web config file was found
    if ($webConfigFound) {
        foreach ($securityHeaders as $header => $description) {
            if (!isset($foundHeaders[$header])) {
                $findings[] = [
                    'category'    => 'Security Config',
                    'title'       => "Missing security header: {$header}",
                    'description' => "{$description}. Add it to your web server configuration to harden the HTTP response.",
                    'severity'    => 'Medium',
                ];
            }
        }
    }

    // --- Docker checks ---
    $dockerFiles = ['Dockerfile', 'docker-compose.yml', 'docker-compose.yaml', 'Dockerfile.prod'];
    foreach ($dockerFiles as $dfName) {
        $node = tree_find_file($tree, $dfName);
        if (!$node) {
            continue;
        }
        $content = github_get_file_content($owner, $repo, $node['path'], $pat);
        if ($content === null) {
            continue;
        }

        // Container running as root
        if (!preg_match('/^\s*USER\s+(?!root)(\w+)/im', $content)) {
            $findings[] = [
                'category'    => 'Security Config',
                'title'       => "Docker container may run as root — {$node['path']}",
                'description' => "No non-root USER instruction found in `{$node['path']}`. Running containers as root violates least-privilege. Add `RUN useradd -m appuser && USER appuser`.",
                'severity'    => 'Medium',
            ];
        }

        // Hardcoded secrets in ENV
        if (preg_match('/^\s*ENV\s+\w*(?:PASSWORD|SECRET|KEY|TOKEN)\w*\s*[=\s]\S+/im', $content)) {
            $findings[] = [
                'category'    => 'Security Config',
                'title'       => "Hardcoded secret in Dockerfile ENV — {$node['path']}",
                'description' => "A credential-like ENV variable is set directly in `{$node['path']}`. Use Docker secrets, runtime environment injection, or a secrets manager instead.",
                'severity'    => 'High',
            ];
        }
    }

    // --- .gitignore checks ---
    $gitignoreNode = tree_find_file($tree, '.gitignore');
    if ($gitignoreNode === null) {
        $findings[] = [
            'category'    => 'Security Config',
            'title'       => 'No .gitignore file found',
            'description' => 'Without a .gitignore, OS files, IDE directories, build artifacts, and secrets like .env can be accidentally committed.',
            'severity'    => 'Medium',
        ];
    } else {
        $gitignoreContent = github_get_file_content($owner, $repo, $gitignoreNode['path'], $pat);
        if ($gitignoreContent !== null && !preg_match('/^\.env/im', $gitignoreContent)) {
            $findings[] = [
                'category'    => 'Security Config',
                'title'       => '.env not excluded in .gitignore',
                'description' => 'The .gitignore file does not appear to exclude .env files. Add `.env` and `.env.*` to prevent accidental credential commits.',
                'severity'    => 'High',
            ];
        }
    }

    // --- CI/CD config check ---
    $ciFiles = ['.travis.yml', 'Jenkinsfile', '.circleci/config.yml'];
    $hasCI   = false;
    foreach ($ciFiles as $ciName) {
        if (tree_find_file($tree, basename($ciName))) {
            $hasCI = true;
            break;
        }
    }
    // Also check for GitHub Actions workflows directory
    foreach ($tree as $node) {
        if ($node['type'] === 'blob' && str_starts_with($node['path'], '.github/workflows/')) {
            $hasCI = true;
            break;
        }
    }
    if (!$hasCI) {
        $findings[] = [
            'category'    => 'Security Config',
            'title'       => 'No CI/CD pipeline configuration found',
            'description' => 'No CI configuration (GitHub Actions, Travis CI, CircleCI, Jenkins) was detected. Automated testing and security scanning in CI catches issues before they reach production.',
            'severity'    => 'Medium',
        ];
    }

    if (!empty($findings)) {
        $recommendations[] = [
            'recommendation_text' => 'Add missing HTTP security headers via web server config. Run containers as non-root. Validate live headers at securityheaders.com and Mozilla Observatory.',
            'priority'            => 'High',
        ];
    } else {
        $recommendations[] = [
            'recommendation_text' => 'Security configuration looks good. Validate live HTTP headers at securityheaders.com and Mozilla Observatory for production confirmation.',
            'priority'            => 'Low',
        ];
    }

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}
