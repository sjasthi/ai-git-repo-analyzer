<?php

declare(strict_types=1);

/**
 * Check 2: OWASP Top 10 Pattern Matcher
 * Scans source files for common OWASP vulnerability patterns using language-aware regex.
 */
function check_owasp(string $owner, string $repo, string $pat, array $sourceFiles): array
{
    $findings = [];

    foreach ($sourceFiles as $fileNode) {
        $path    = $fileNode['path'];
        $ext     = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $content = github_get_file_content($owner, $repo, $path, $pat);
        if ($content === null) {
            continue;
        }

        // A01 – Broken Access Control: dynamic file inclusion from user input (PHP)
        if (in_array($ext, ['php'], true)) {
            if (preg_match('/(?:include|require|include_once|require_once|file_get_contents|readfile|fopen)\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/i', $content)) {
                $findings[] = [
                    'category'    => 'OWASP',
                    'title'       => "A01 Path Traversal / File Inclusion — {$path}",
                    'description' => "User input flows directly into a file-inclusion function in `{$path}`. An attacker can read arbitrary server files (LFI) or execute remote code (RFI). Whitelist allowed paths.",
                    'severity'    => 'High',
                ];
            }
        }

        // A03 – Injection: SQL query built by string concatenation with user input
        if (in_array($ext, ['php', 'py', 'java', 'cs', 'rb'], true)) {
            if (preg_match('/(?:query|execute|exec|mysqli_query|pg_query|mysql_query)\s*\([^)]*\$_(GET|POST|REQUEST|COOKIE)/is', $content)
                || preg_match('/["\']SELECT\b.+?["\'\s]\s*\.\s*\$_(GET|POST|REQUEST|COOKIE)/i', $content)
                || preg_match('/f["\']SELECT\b.+?\{[^}]*(request|params|input)/i', $content)) {
                $findings[] = [
                    'category'    => 'OWASP',
                    'title'       => "A03 SQL Injection risk — {$path}",
                    'description' => "Unsanitized user input appears to be concatenated into an SQL query in `{$path}`. Replace with parameterised queries / prepared statements.",
                    'severity'    => 'High',
                ];
            }
        }

        // A03 – Injection: XSS in PHP (echoing raw input)
        if ($ext === 'php') {
            if (preg_match('/echo\s+\$_(GET|POST|REQUEST|COOKIE)/i', $content)
                || preg_match('/print\s+\$_(GET|POST|REQUEST|COOKIE)/i', $content)) {
                $findings[] = [
                    'category'    => 'OWASP',
                    'title'       => "A03 Cross-Site Scripting (XSS) — {$path}",
                    'description' => "Unescaped user input is echoed directly in `{$path}`. Wrap all user-supplied output with htmlspecialchars(\$var, ENT_QUOTES, 'UTF-8').",
                    'severity'    => 'High',
                ];
            }
        }

        // A03 – Injection: XSS sinks in JavaScript/TypeScript
        if (in_array($ext, ['js', 'ts', 'tsx', 'jsx'], true)) {
            if (preg_match('/\.innerHTML\s*=(?!=)|document\.write\s*\(|eval\s*\(/', $content)) {
                $findings[] = [
                    'category'    => 'OWASP',
                    'title'       => "A03 DOM XSS sink — {$path}",
                    'description' => "Dangerous DOM API (innerHTML, document.write, eval) detected in `{$path}`. Prefer textContent or DOM creation methods, and sanitize untrusted data with DOMPurify.",
                    'severity'    => 'Medium',
                ];
            }
        }

        // A02 – Cryptographic Failures: weak hashing near password logic
        if (preg_match('/\b(?:md5|sha1)\s*\(/i', $content)
            && preg_match('/password|passwd|pwd|secret/i', $content)) {
            $findings[] = [
                'category'    => 'OWASP',
                'title'       => "A02 Weak password hashing — {$path}",
                'description' => "MD5 or SHA1 is used near password-related code in `{$path}`. Use password_hash() (PHP), bcrypt, or Argon2 instead.",
                'severity'    => 'High',
            ];
        }

        // A05 – Security Misconfiguration: debug flags on in source
        if (preg_match('/error_reporting\s*\(\s*E_ALL\s*\)/i', $content)
            || preg_match('/display_errors\s*=\s*(?:On|1|true)/i', $content)
            || preg_match('/DEBUG\s*=\s*True/i', $content)) {
            $findings[] = [
                'category'    => 'OWASP',
                'title'       => "A05 Debug mode / error display enabled — {$path}",
                'description' => "Debug or verbose error output is enabled in `{$path}`. This can expose stack traces, file paths, and internal logic in production. Disable in production config.",
                'severity'    => 'Medium',
            ];
        }

        // A08 – Insecure Deserialization
        if (preg_match('/\bunserialize\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/i', $content)) {
            $findings[] = [
                'category'    => 'OWASP',
                'title'       => "A08 Insecure Deserialization — {$path}",
                'description' => "User-controlled data is passed to unserialize() in `{$path}`. This can lead to remote code execution. Use JSON (json_decode) instead.",
                'severity'    => 'High',
            ];
        }

        // A07 – Identification failures: session_start without HTTPS-only cookie flags
        if ($ext === 'php' && preg_match('/session_start\s*\(\s*\)/i', $content)
            && !preg_match('/session\.cookie_secure|cookie_secure.*true/i', $content)) {
            $findings[] = [
                'category'    => 'OWASP',
                'title'       => "A07 Session cookie not secured — {$path}",
                'description' => "session_start() in `{$path}` does not appear to set the Secure or HttpOnly cookie flags. Add session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Lax']) before session_start().",
                'severity'    => 'Medium',
            ];
        }
    }

    $recommendations = [];
    if (!empty($findings)) {
        $recommendations[] = [
            'recommendation_text' => 'Fix OWASP issues: use prepared statements for all DB queries, escape output with htmlspecialchars(), replace MD5/SHA1 with password_hash(), and disable debug output in production.',
            'priority'            => 'High',
        ];
    } else {
        $recommendations[] = [
            'recommendation_text' => 'No critical OWASP patterns found in sampled files. Run Semgrep or a dedicated SAST tool against the full repository for deeper coverage.',
            'priority'            => 'Low',
        ];
    }

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}
