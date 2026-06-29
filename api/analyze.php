<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

function ensureScanReportColumns(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec("ALTER TABLE scans ADD COLUMN IF NOT EXISTS selected_checks_json LONGTEXT NULL");
    $pdo->exec("ALTER TABLE scans ADD COLUMN IF NOT EXISTS results_json LONGTEXT NULL");
    $ensured = true;
}

function makeGitHubRequest(string $url, string $pat): array
{
    $ch = curl_init($url);
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

    if ($responseBody === false || $curlError !== '') {
        throw new RuntimeException($curlError !== '' ? $curlError : 'Unable to reach GitHub API.');
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('GitHub API returned an invalid response.');
    }

    return ['code' => $httpCode, 'body' => $decoded];
}

function fetchTextFile(string $owner, string $repo, string $path, string $pat): ?string
{
    $url = "https://api.github.com/repos/{$owner}/{$repo}/contents/" . rawurlencode($path);
    $response = makeGitHubRequest($url, $pat);

    if ($response['code'] >= 400) {
        return null;
    }

    $body = $response['body'];
    if (!is_array($body) || empty($body['content'])) {
        return null;
    }

    $content = $body['content'];
    if (isset($body['encoding']) && $body['encoding'] === 'base64') {
        $decoded = base64_decode($content, true);
        return $decoded !== false ? $decoded : null;
    }

    return (string) $content;
}

function buildResult(string $id, string $title, string $summary, string $details, string $severity, array $evidence = []): array
{
    return [
        'id' => $id,
        'title' => $title,
        'summary' => $summary,
        'details' => $details,
        'severity' => $severity,
        'evidence' => $evidence,
    ];
}

function normalizeSelectedChecks($inputData): array
{
    $raw = $inputData['checks'] ?? $_POST['checks'] ?? [];
    if (!is_array($raw)) {
        return [];
    }

    $checks = [];
    foreach ($raw as $value) {
        $clean = trim((string) $value);
        if ($clean !== '') {
            $checks[] = $clean;
        }
    }

    $checks = array_values(array_unique($checks));
    return $checks;
}

function getCheckLabel(string $checkId): string
{
    $map = [
        'dependency_risk' => '#1 Insecure Design and Logic Flaws (A04)',
        'hardening' => '#2 Vulnerable and Outdated Dependencies (A06)',
        'performance' => '#3 CI/CD and Software Integrity Risks (A08)',
        'maintainability' => '#4 Logging and Monitoring Coverage (A09)',
        'code_intelligence' => '#5 Code Quality, Performance and Repo Health',
    ];

    return $map[$checkId] ?? $checkId;
}

function collectFilesByExtensions(array $treeEntries, array $extensions): array
{
    $files = [];
    foreach ($treeEntries as $entry) {
        if (($entry['type'] ?? '') !== 'blob') {
            continue;
        }
        $path = (string) ($entry['path'] ?? '');
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, $extensions, true)) {
            $files[] = $path;
        }
    }
    return array_values(array_unique($files));
}

function collectPatternHits(string $owner, string $repo, string $pat, array $files, array $rules, int $fileLimit = 25): array
{
    $hits = [];
    foreach (array_slice($files, 0, $fileLimit) as $path) {
        $content = fetchTextFile($owner, $repo, $path, $pat);
        if ($content === null) {
            continue;
        }
        foreach ($rules as $label => $pattern) {
            if (preg_match($pattern, $content)) {
                $hits[] = $path . ' -> ' . $label;
            }
        }
    }
    return array_values(array_unique($hits));
}

function scanRepoInventory(string $owner, string $repo, string $pat, array $treeEntries): array
{
    $codeFiles = collectFilesByExtensions($treeEntries, ['php', 'js', 'ts', 'py', 'java', 'cs', 'rb']);
    $hits = [];
    foreach (array_slice($codeFiles, 0, 25) as $path) {
        $content = fetchTextFile($owner, $repo, $path, $pat);
        if ($content === null) {
            continue;
        }

        $hasSensitiveRoute = preg_match('/\/(admin|manage|users\/delete|roles|permission)/i', $content) === 1;
        $hasAuthGuard = preg_match('/\b(auth|authorize|middleware|rbac|is_admin|hasRole)\b/i', $content) === 1;
        if ($hasSensitiveRoute && !$hasAuthGuard) {
            $hits[] = $path . ' -> Sensitive route pattern without obvious auth guard';
        }

        if (preg_match('/access-control-allow-origin\s*:\s*\*/i', $content) && preg_match('/credentials\s*[:=]\s*true/i', $content)) {
            $hits[] = $path . ' -> Wildcard CORS with credentials';
        }
    }

    $severity = empty($hits) ? 'Info' : 'High';
    $summary = empty($hits)
        ? 'No obvious broken-access-control patterns were detected in sampled files.'
        : 'Potential broken-access-control patterns were detected.';

    return buildResult(
        'repo_inventory',
        '#1 Access Control and Authentication (A01 + A07)',
        $summary,
        'Scans for sensitive routes without auth checks, weak CORS access controls, and missing authorization hints.',
        $severity,
        array_slice($hits, 0, 20)
    );
}

function scanTechnologyStack(string $owner, string $repo, string $pat, array $treeEntries): array
{
    $codeFiles = collectFilesByExtensions($treeEntries, ['php', 'js', 'ts', 'py', 'java', 'cs', 'rb', 'env', 'ini']);
    $rules = [
        'Weak hash usage (md5)' => '/\bmd5\s*\(/i',
        'Weak hash usage (sha1)' => '/\bsha1\s*\(/i',
        'Insecure random generation' => '/\brand\s*\(|\bmt_rand\s*\(/i',
        'Hardcoded crypto key material' => '/(secret[_-]?key|private[_-]?key|crypto[_-]?key)\s*[:=]\s*["\'][^"\']{8,}["\']/i',
    ];
    $hits = collectPatternHits($owner, $repo, $pat, $codeFiles, $rules);

    $severity = 'Info';
    if (count($hits) >= 3) {
        $severity = 'High';
    } elseif (!empty($hits)) {
        $severity = 'Medium';
    }

    $summary = empty($hits)
        ? 'No obvious cryptographic failures were detected in sampled files.'
        : 'Potential cryptographic weaknesses were detected.';

    return buildResult(
        'technology_stack',
        '#2 Secrets and Cryptographic Exposure (A02)',
        $summary,
        'Scans for weak hashing algorithms, hardcoded key material, and insecure random generation in security-sensitive code.',
        $severity,
        array_slice($hits, 0, 20)
    );
}

function scanSecrets(string $owner, string $repo, string $pat, array $treeEntries): array
{
    $codeFiles = collectFilesByExtensions($treeEntries, ['php', 'js', 'ts', 'py', 'java', 'cs', 'rb']);
    $rules = [
        'Possible SQL injection string concatenation' => '/(SELECT|INSERT|UPDATE|DELETE).*(\$_GET|\$_POST|req\.query|req\.body|request\.)/is',
        'Execution sink (exec/system/shell_exec)' => '/\b(exec|system|shell_exec|passthru)\s*\(/i',
        'Unsafe dynamic query call' => '/\b(mysqli_query|query)\s*\(.*\./is',
        'Potential XSS sink usage' => '/(innerHTML|document\.write|dangerouslySetInnerHTML)/i',
    ];
    $hits = collectPatternHits($owner, $repo, $pat, $codeFiles, $rules);

    $severity = empty($hits) ? 'Info' : 'High';
    $summary = empty($hits)
        ? 'No obvious injection sink patterns were detected in sampled files.'
        : 'Potential injection-related sink patterns were detected.';

    return buildResult(
        'secret_scan',
        '#3 Injection and Unsafe Code Patterns (A03)',
        $summary,
        'Scans for SQL/query concatenation, command execution sinks, and client-side output sinks that can enable injection paths.',
        $severity,
        array_slice($hits, 0, 20)
    );
}

function scanDependencies(string $owner, string $repo, string $pat, array $treeEntries): array
{
    $designFiles = collectFilesByExtensions($treeEntries, ['php', 'js', 'ts', 'py', 'java', 'cs', 'rb']);
    $hits = [];

    foreach (array_slice($designFiles, 0, 20) as $path) {
        $content = fetchTextFile($owner, $repo, $path, $pat);
        if ($content === null) {
            continue;
        }
        if (preg_match('/\$_(GET|POST|REQUEST)\[.*\].*(is_admin|role|permission)/i', $content)) {
            $hits[] = $path . ' -> Client-controlled privilege field used directly';
        }
        if (preg_match('/TODO.*(security|auth|validate)/i', $content)) {
            $hits[] = $path . ' -> Security control marked as TODO';
        }
        if (preg_match('/(login|auth|token)/i', $content) && !preg_match('/(rate.?limit|throttle|captcha|lockout)/i', $content)) {
            $hits[] = $path . ' -> Auth flow without obvious abuse/rate-limit controls';
        }
    }

    $severity = empty($hits) ? 'Info' : 'Medium';
    $summary = empty($hits)
        ? 'No obvious insecure-design anti-patterns were detected in sampled files.'
        : 'Potential insecure-design indicators were detected.';

    return buildResult(
        'dependency_risk',
        '#1 Insecure Design and Logic Flaws (A04)',
        $summary,
        'Scans for trust of client-side privilege data, missing abuse controls in auth flows, and postponed security controls.',
        $severity,
        array_slice(array_values(array_unique($hits)), 0, 20)
    );
}

function scanOwasp(string $owner, string $repo, string $pat, array $treeEntries): array
{
    $configFiles = collectFilesByExtensions($treeEntries, ['env', 'ini', 'yaml', 'yml', 'json', 'php', 'conf', 'xml']);
    $rules = [
        'Debug mode enabled' => '/\b(debug|app_debug)\s*[:=]\s*(true|1)/i',
        'Default password usage' => '/(password\s*[:=]\s*["\']?(password|admin|123456))/i',
        'Wildcard CORS policy' => '/(access-control-allow-origin\s*[:=]\s*\*|cors\s*[:=].*\*)/i',
        'Verbose error display enabled' => '/(display_errors\s*[:=]\s*1|show_errors\s*[:=]\s*true)/i',
    ];
    $hits = collectPatternHits($owner, $repo, $pat, $configFiles, $rules);

    $severity = empty($hits) ? 'Info' : 'High';
    $summary = empty($hits)
        ? 'No obvious security misconfiguration patterns were detected in sampled configuration files.'
        : 'Potential security misconfiguration patterns were detected.';

    return buildResult(
        'owasp',
        '#5 Misconfiguration and Exposure Issues (A05)',
        $summary,
        'Scans configuration and environment files for debug exposure, permissive CORS, default credentials, and verbose error output.',
        $severity,
        array_slice($hits, 0, 20)
    );
}

function scanHardening(string $owner, string $repo, string $pat, array $treeEntries): array
{
    $manifestPaths = [];
    $hasLockFile = false;

    foreach ($treeEntries as $entry) {
        $path = strtolower((string) ($entry['path'] ?? ''));
        if (preg_match('/(package\.json|composer\.json|requirements\.txt|pyproject\.toml|pipfile|go\.mod|cargo\.toml)$/', $path)) {
            $manifestPaths[] = (string) ($entry['path'] ?? '');
        }
        if (preg_match('/(package-lock\.json|composer\.lock|pipfile\.lock|poetry\.lock|cargo\.lock)$/', $path)) {
            $hasLockFile = true;
        }
    }

    $evidence = [];
    if (empty($manifestPaths)) {
        return buildResult(
            'hardening',
            '#2 Vulnerable and Outdated Dependencies (A06)',
            'No dependency manifests were detected in this repository snapshot.',
            'Scans dependency manifest and lockfile signals for component-risk indicators.',
            'Info',
            []
        );
    }

    $evidence = array_map(static function ($path) {
        return $path . ' -> Dependency manifest detected';
    }, $manifestPaths);

    if (!$hasLockFile) {
        $evidence[] = 'No dependency lock file detected (pinning/reproducibility risk)';
    }

    $severity = $hasLockFile ? 'Medium' : 'High';
    $summary = 'Dependency manifests were detected and should be audited for vulnerable or outdated components.';

    return buildResult(
        'hardening',
        '#2 Vulnerable and Outdated Dependencies (A06)',
        $summary,
        'Uses manifest/lockfile presence as a static signal for dependency governance and upgrade hygiene.',
        $severity,
        array_slice($evidence, 0, 20)
    );
}

function scanReliability(string $owner, string $repo, string $pat, array $treeEntries): array
{
    $authFiles = collectFilesByExtensions($treeEntries, ['php', 'js', 'ts', 'py', 'java', 'cs']);
    $rules = [
        'Auth cookie missing secure flags' => '/setcookie\s*\(.*(session|token|auth)/i',
        'Long-lived JWT/token configuration' => '/(expiresIn|ttl|expiration).*(30d|60d|90d|365d)/i',
        'Basic auth usage' => '/\bBasic\s+Auth|authorization\s*:\s*basic/i',
        'Hardcoded test credential pattern' => '/(username|user|password)\s*[:=]\s*["\'](admin|test|root)["\']/i',
    ];
    $hits = collectPatternHits($owner, $repo, $pat, $authFiles, $rules);

    $severity = empty($hits) ? 'Info' : 'High';
    $summary = empty($hits)
        ? 'No obvious identification/authentication failure patterns were detected in sampled files.'
        : 'Potential identification and authentication weakness patterns were detected.';

    return buildResult(
        'reliability',
        '#7 A07: Identification and Authentication Failures',
        $summary,
        'Scans auth/session logic for weak cookie handling, long-lived token settings, and weak credential patterns.',
        $severity,
        array_slice($hits, 0, 20)
    );
}

function scanPerformance(string $owner, string $repo, string $pat, array $treeEntries): array
{
    $codeFiles = collectFilesByExtensions($treeEntries, ['php', 'js', 'ts', 'py', 'java', 'cs', 'rb', 'yml', 'yaml']);
    $rules = [
        'Unsafe deserialization' => '/\bunserialize\s*\(/i',
        'Dynamic eval execution' => '/\beval\s*\(/i',
        'Base64 decode with eval pattern' => '/eval\s*\(\s*base64_decode\s*\(/i',
        'Unpinned GitHub action version' => '/uses:\s*[^@\n]+@v\d+/i',
    ];
    $hits = collectPatternHits($owner, $repo, $pat, $codeFiles, $rules);

    $severity = empty($hits) ? 'Info' : 'High';
    $summary = empty($hits)
        ? 'No obvious software/data integrity failure patterns were detected in sampled files.'
        : 'Potential software/data integrity failure patterns were detected.';

    return buildResult(
        'performance',
        '#3 CI/CD and Software Integrity Risks (A08)',
        $summary,
        'Scans for unsafe deserialization/eval usage and unpinned CI action references that can weaken build integrity.',
        $severity,
        array_slice($hits, 0, 20)
    );
}

function scanMaintainability(string $owner, string $repo, string $pat, array $treeEntries): array
{
    $auditFiles = collectFilesByExtensions($treeEntries, ['php', 'js', 'ts', 'py', 'java', 'cs', 'rb']);
    $hits = [];

    foreach (array_slice($auditFiles, 0, 20) as $path) {
        $content = fetchTextFile($owner, $repo, $path, $pat);
        if ($content === null) {
            continue;
        }

        $hasAuthOrPrivilegedFlow = preg_match('/(login|auth|token|permission|admin|access denied)/i', $content) === 1;
        $hasLogging = preg_match('/\b(log|logger|audit|trace|monitor|warn|error)\b/i', $content) === 1;
        if ($hasAuthOrPrivilegedFlow && !$hasLogging) {
            $hits[] = $path . ' -> Auth/privileged flow without obvious security logging';
        }
    }

    $severity = empty($hits) ? 'Info' : 'Medium';
    $summary = empty($hits)
        ? 'No obvious security logging/monitoring coverage gaps were detected in sampled files.'
        : 'Potential security logging/monitoring coverage gaps were detected.';

    return buildResult(
        'maintainability',
        '#4 Logging and Monitoring Coverage (A09)',
        $summary,
        'Scans for authentication/privileged code paths that lack obvious security logging or audit signals.',
        $severity,
        array_slice($hits, 0, 20)
    );
}

function scanTestingAndCi(string $owner, string $repo, string $pat, array $treeEntries): array
{
    $networkFiles = collectFilesByExtensions($treeEntries, ['php', 'js', 'ts', 'py', 'java', 'cs', 'rb']);
    $rules = [
        'Network request built from user input' => '/(curl_init|file_get_contents|axios\.|fetch\().*(\$_GET|\$_POST|req\.query|req\.body|request\.)/is',
        'Potential localhost/internal target usage' => '/(localhost|127\.0\.0\.1|169\.254\.169\.254|metadata\.google|internal)/i',
        'URL parameter directly passed to outbound call' => '/(url|uri|endpoint)\s*=\s*(\$_GET|\$_POST|req\.query|req\.body)/i',
    ];
    $hits = collectPatternHits($owner, $repo, $pat, $networkFiles, $rules);

    $severity = empty($hits) ? 'Info' : 'High';
    $summary = empty($hits)
        ? 'No obvious SSRF-style patterns were detected in sampled files.'
        : 'Potential SSRF-style outbound request patterns were detected.';

    return buildResult(
        'testing_ci',
        '#7 SSRF and Network Security Risks (A10)',
        $summary,
        'Scans outbound HTTP call paths for user-controlled target URLs and internal-network access patterns.',
        $severity,
        array_slice($hits, 0, 20)
    );
}

function scanQualityPerformance(string $owner, string $repo, string $pat, array $treeEntries): array
{
    $codeFiles = collectFilesByExtensions($treeEntries, ['php', 'js', 'ts', 'py', 'java', 'cs', 'rb']);
    $evidence = [];

    foreach (array_slice($codeFiles, 0, 25) as $path) {
        $content = fetchTextFile($owner, $repo, $path, $pat);
        if ($content === null) {
            continue;
        }

        if (preg_match('/\b(for|foreach|while)\s*\([^\)]*\)\s*\{[\s\S]{0,500}\b(for|foreach|while)\s*\(/i', $content)) {
            $evidence[] = $path . ' -> Potential nested loop hotspot';
        }
        if (preg_match('/(SELECT\s+\*|sleep\s*\(|file_get_contents\s*\(|curl_exec\s*\()/i', $content)) {
            $evidence[] = $path . ' -> Potential heavy I/O or blocking operation';
        }
    }

    $evidence = array_values(array_unique($evidence));
    $severity = empty($evidence) ? 'Info' : (count($evidence) > 5 ? 'High' : 'Medium');
    $summary = empty($evidence)
        ? 'No obvious performance anti-patterns were detected in sampled files.'
        : 'Potential performance anti-patterns were detected.';

    return buildResult(
        'quality_performance',
        '#11 Performance Quality and Anti-pattern Analysis',
        $summary,
        'Scans for nested loops, blocking calls, and heavy I/O/query patterns that can affect runtime performance.',
        $severity,
        array_slice($evidence, 0, 20)
    );
}

function scanQualityMaintainability(string $owner, string $repo, string $pat, array $treeEntries): array
{
    $codeFiles = collectFilesByExtensions($treeEntries, ['php', 'js', 'ts', 'py', 'java', 'cs', 'rb']);
    $evidence = [];

    foreach (array_slice($codeFiles, 0, 25) as $path) {
        $content = fetchTextFile($owner, $repo, $path, $pat);
        if ($content === null) {
            continue;
        }

        $lineCount = substr_count($content, "\n") + 1;
        if ($lineCount > 400) {
            $evidence[] = $path . ' -> Large file (' . $lineCount . ' lines)';
        }
        if (preg_match('/\b(TODO|FIXME|HACK)\b/i', $content)) {
            $evidence[] = $path . ' -> Contains TODO/FIXME/HACK markers';
        }
    }

    $evidence = array_values(array_unique($evidence));
    $severity = empty($evidence) ? 'Info' : 'Medium';
    $summary = empty($evidence)
        ? 'No obvious maintainability risks were detected in sampled files.'
        : 'Potential maintainability or complexity risks were detected.';

    return buildResult(
        'quality_maintainability',
        '#12 Maintainability and Complexity Analysis',
        $summary,
        'Scans for oversized files and unresolved TODO/FIXME markers that may indicate growing maintenance debt.',
        $severity,
        array_slice($evidence, 0, 20)
    );
}

function scanQualityDocumentation(string $owner, string $repo, string $pat, array $treeEntries): array
{
    $paths = array_map(static function ($entry) {
        return strtolower((string) ($entry['path'] ?? ''));
    }, $treeEntries);

    $evidence = [];
    $missing = [];

    $mustHave = [
        'readme' => '/(^|\/)readme(\.md|\.txt)?$/i',
        'license' => '/(^|\/)license(\.md|\.txt)?$/i',
    ];
    foreach ($mustHave as $label => $pattern) {
        $found = false;
        foreach ($paths as $path) {
            if (preg_match($pattern, $path)) {
                $evidence[] = $path . ' -> ' . strtoupper($label) . ' detected';
                $found = true;
                break;
            }
        }
        if (!$found) {
            $missing[] = strtoupper($label) . ' missing';
        }
    }

    $optionalPatterns = [
        'Contributing guide' => '/(^|\/)contributing(\.md|\.txt)?$/i',
        'Changelog' => '/(^|\/)changelog(\.md|\.txt)?$/i',
        'Docs folder' => '/(^|\/)docs\//i',
    ];
    foreach ($optionalPatterns as $label => $pattern) {
        foreach ($paths as $path) {
            if (preg_match($pattern, $path)) {
                $evidence[] = $path . ' -> ' . $label . ' detected';
                break;
            }
        }
    }

    foreach ($missing as $item) {
        $evidence[] = $item;
    }

    $severity = empty($missing) ? 'Info' : 'Medium';
    $summary = empty($missing)
        ? 'Core project documentation artifacts were detected.'
        : 'Some core documentation artifacts appear to be missing.';

    return buildResult(
        'quality_documentation',
        '#13 Documentation and Project Health',
        $summary,
        'Evaluates README, LICENSE, and supporting docs to estimate repository onboarding and project health readiness.',
        $severity,
        array_slice(array_values(array_unique($evidence)), 0, 20)
    );
}

function scanQualityRepoStructure(array $treeEntries): array
{
    $totalFiles = 0;
    $totalSize = 0;
    $extensions = [];
    $largeFiles = [];

    foreach ($treeEntries as $entry) {
        if (($entry['type'] ?? '') !== 'blob') {
            continue;
        }
        $totalFiles++;
        $size = (int) ($entry['size'] ?? 0);
        $totalSize += $size;

        $path = (string) ($entry['path'] ?? '');
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== '') {
            $extensions[$ext] = ($extensions[$ext] ?? 0) + 1;
        }

        if ($size >= 1024 * 1024) {
            $largeFiles[] = $path . ' (' . number_format($size) . ' bytes)';
        }
    }

    arsort($extensions);
    $topExt = array_slice($extensions, 0, 5, true);
    $evidence = [];
    foreach ($topExt as $ext => $count) {
        $evidence[] = '.' . $ext . ' -> ' . $count . ' files';
    }
    foreach (array_slice($largeFiles, 0, 10) as $file) {
        $evidence[] = 'Large file: ' . $file;
    }

    $severity = empty($largeFiles) ? 'Info' : 'Medium';
    $summary = 'Repository contains ' . number_format($totalFiles) . ' files with estimated total size ' . number_format($totalSize) . ' bytes.';

    return buildResult(
        'quality_repo_structure',
        '#14 Repository Size and Structure Analysis',
        $summary,
        'Summarizes file volume, dominant file types, and very large files that can affect repository health and maintainability.',
        $severity,
        $evidence
    );
}

function scanCodeIntelligence(string $owner, string $repo, string $pat, array $treeEntries): array
{
    $performance = scanQualityPerformance($owner, $repo, $pat, $treeEntries);
    $maintainability = scanQualityMaintainability($owner, $repo, $pat, $treeEntries);
    $documentation = scanQualityDocumentation($owner, $repo, $pat, $treeEntries);
    $repoStructure = scanQualityRepoStructure($treeEntries);

    $results = [$performance, $maintainability, $documentation, $repoStructure];
    $severityRank = ['Info' => 0, 'Low' => 1, 'Medium' => 2, 'High' => 3];
    $overallSeverity = 'Info';
    $maxRank = 0;

    foreach ($results as $result) {
        $rank = $severityRank[$result['severity']] ?? 0;
        if ($rank > $maxRank) {
            $maxRank = $rank;
            $overallSeverity = $result['severity'];
        }
    }

    $summary = 'Combined quality scan covers performance anti-patterns, maintainability complexity, documentation health, and repository structure.';
    if ($overallSeverity === 'High') {
        $summary = 'Combined quality scan found high-priority issues across performance, maintainability, documentation, or repository structure.';
    } elseif ($overallSeverity === 'Medium') {
        $summary = 'Combined quality scan found medium-priority issues that should be improved for codebase health.';
    }

    $details = 'Includes: performance hotspots, maintainability debt, documentation completeness, and repo size/structure checks.';
    $evidence = [];
    foreach ($results as $result) {
        $evidence[] = $result['title'] . ' [' . $result['severity'] . ']';
        foreach (array_slice($result['evidence'] ?? [], 0, 4) as $item) {
            $evidence[] = ' - ' . $item;
        }
    }

    return buildResult(
        'code_intelligence',
        '#5 Code Quality, Performance and Repo Health',
        $summary,
        $details,
        $overallSeverity,
        array_slice($evidence, 0, 30)
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

$inputData = null;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $inputData = json_decode($raw, true);
}

$repoUrl = trim($inputData['repo_url'] ?? $_POST['repo_url'] ?? '');
$pat = trim($inputData['pat'] ?? $_POST['pat'] ?? '');

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

try {
    $repoResponse = makeGitHubRequest("https://api.github.com/repos/{$owner}/{$repo}", $pat);
    $repoPayload = $repoResponse['body'];
    if ($repoResponse['code'] >= 400 || !is_array($repoPayload)) {
        throw new RuntimeException($repoPayload['message'] ?? 'GitHub API rejected the request.');
    }

    $defaultBranch = (string) ($repoPayload['default_branch'] ?? 'main');
    $treeResponse = makeGitHubRequest("https://api.github.com/repos/{$owner}/{$repo}/git/trees/{$defaultBranch}?recursive=1", $pat);
    $treePayload = $treeResponse['body'];
    $treeEntries = [];
    if (is_array($treePayload) && isset($treePayload['tree']) && is_array($treePayload['tree'])) {
        $treeEntries = $treePayload['tree'];
    }

    $selectedChecks = normalizeSelectedChecks($inputData);
    if (empty($selectedChecks)) {
        $selectedChecks = [
            'dependency_risk',
            'hardening',
            'performance',
            'maintainability',
            'code_intelligence',
        ];
    }

    $results = [];
    $findings = [];
    $recommendations = [];
    $skills = [];

    foreach ($selectedChecks as $checkId) {
        switch ($checkId) {
            case 'dependency_risk':
                $results[] = scanDependencies($owner, $repo, $pat, $treeEntries);
                break;
            case 'hardening':
                $results[] = scanHardening($owner, $repo, $pat, $treeEntries);
                break;
            case 'performance':
                $results[] = scanPerformance($owner, $repo, $pat, $treeEntries);
                break;
            case 'maintainability':
                $results[] = scanMaintainability($owner, $repo, $pat, $treeEntries);
                break;
            case 'code_intelligence':
                $results[] = scanCodeIntelligence($owner, $repo, $pat, $treeEntries);
                break;
        }
    }

    foreach ($results as $result) {
        if ($result['severity'] === 'High') {
            $findings[] = [
                'category' => 'Static Analysis',
                'title' => $result['title'],
                'description' => $result['summary'],
                'severity' => 'High',
            ];
            $recommendations[] = [
                'recommendation_text' => 'Review the findings from ' . $result['title'] . ' and address the highest-risk issues first.',
                'priority' => 'High',
            ];
        } elseif ($result['severity'] === 'Medium') {
            $findings[] = [
                'category' => 'Static Analysis',
                'title' => $result['title'],
                'description' => $result['summary'],
                'severity' => 'Medium',
            ];
            $recommendations[] = [
                'recommendation_text' => 'Consider follow-up improvements for ' . $result['title'] . '.',
                'priority' => 'Medium',
            ];
        }
    }

    $repoLicense = $repoPayload['license']['name'] ?? null;
    $repoDescription = trim((string) ($repoPayload['description'] ?? ''));
    $repoLanguage = $repoPayload['language'] ?? 'Unknown';
    $repoStars = (int) ($repoPayload['stargazers_count'] ?? 0);
    $repoForks = (int) ($repoPayload['forks_count'] ?? 0);
    $repoWatchers = (int) ($repoPayload['watchers_count'] ?? 0);

    if ($repoLicense === null) {
        $findings[] = [
            'category' => 'Compliance',
            'title' => 'Missing license information',
            'description' => 'The repository does not specify a license. Adding one clarifies reuse permissions.',
            'severity' => 'High',
        ];
        $recommendations[] = [
            'recommendation_text' => 'Add a license file to the repository to clarify project usage and distribution rights.',
            'priority' => 'High',
        ];
    }

    if ($repoDescription === '') {
        $findings[] = [
            'category' => 'Stability',
            'title' => 'Missing repository description',
            'description' => 'A short repository description helps users understand the project purpose.',
            'severity' => 'Low',
        ];
        $recommendations[] = [
            'recommendation_text' => 'Provide a clear repository description and README summary.',
            'priority' => 'Medium',
        ];
    }

    if (strcasecmp($repoLanguage, 'PHP') === 0) {
        $skills[] = [
            'skill_name' => 'PHP',
            'proficiency_level' => 'Advanced',
            'risk_level' => 'Medium',
        ];
    } elseif ($repoLanguage !== 'Unknown') {
        $skills[] = [
            'skill_name' => $repoLanguage,
            'proficiency_level' => 'Intermediate',
            'risk_level' => 'Low',
        ];
    }

    $issueCount = max(1, count($findings));
    $overallScore = max(40, 100 - ($issueCount * 6));

    try {
        $pdo = db_connection();
        ensureScanReportColumns($pdo);
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
            'INSERT INTO scans (repository_id, summary_score, total_findings, total_skills, selected_checks_json, results_json) VALUES (:repository_id, :summary_score, :total_findings, :total_skills, :selected_checks_json, :results_json)'
        );
        $scanStmt->execute([
            ':repository_id' => $repositoryId,
            ':summary_score' => $overallScore,
            ':total_findings' => count($findings),
            ':total_skills' => count($skills),
            ':selected_checks_json' => json_encode(array_map(static function ($checkId) {
                return getCheckLabel($checkId);
            }, $selectedChecks), JSON_UNESCAPED_SLASHES),
            ':results_json' => json_encode($results, JSON_UNESCAPED_SLASHES),
        ]);

        $scanId = (int) $pdo->lastInsertId();

        $findingStmt = $pdo->prepare(
            'INSERT INTO findings (scan_id, category, title, description, severity) VALUES (:scan_id, :category, :title, :description, :severity)'
        );
        foreach ($findings as $finding) {
            $findingStmt->execute([
                ':scan_id' => $scanId,
                ':category' => $finding['category'],
                ':title' => $finding['title'],
                ':description' => $finding['description'],
                ':severity' => $finding['severity'],
            ]);
        }

        $skillStmt = $pdo->prepare(
            'INSERT INTO skills (scan_id, skill_name, proficiency_level, risk_level) VALUES (:scan_id, :skill_name, :proficiency_level, :risk_level)'
        );
        foreach ($skills as $skill) {
            $skillStmt->execute([
                ':scan_id' => $scanId,
                ':skill_name' => $skill['skill_name'],
                ':proficiency_level' => $skill['proficiency_level'],
                ':risk_level' => $skill['risk_level'],
            ]);
        }

        $recommendationStmt = $pdo->prepare(
            'INSERT INTO recommendations (scan_id, recommendation_text, priority) VALUES (:scan_id, :recommendation_text, :priority)'
        );
        foreach ($recommendations as $recommendation) {
            $recommendationStmt->execute([
                ':scan_id' => $scanId,
                ':recommendation_text' => $recommendation['recommendation_text'],
                ':priority' => $recommendation['priority'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    echo json_encode([
        'message' => 'Repository analysis completed.',
        'scan_id' => $scanId ?? null,
        'report_urls' => [
            'summary' => 'api/report.php?scan_id=' . ($scanId ?? 0),
            'download' => 'api/report.php?scan_id=' . ($scanId ?? 0) . '&download=1&format=txt',
        ],
        'repository' => [
            'id' => $repoPayload['id'] ?? null,
            'name' => $repoPayload['name'] ?? $repo,
            'full_name' => $repoPayload['full_name'] ?? "{$owner}/{$repo}",
            'description' => $repoDescription,
            'language' => $repoLanguage,
            'owner' => $owner,
            'stars' => $repoStars,
            'forks' => $repoForks,
            'watchers' => $repoWatchers,
        ],
        'scan' => [
            'summary_score' => $overallScore,
        ],
        'selected_checks' => array_map(static function ($checkId) {
            return getCheckLabel($checkId);
        }, $selectedChecks),
        'results' => $results,
        'findings' => $findings,
        'skills' => $skills,
        'recommendations' => $recommendations,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Analysis failed.',
        'details' => $e->getMessage(),
    ]);
}
