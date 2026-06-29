<?php

declare(strict_types=1);

/**
 * Check 3: Dependency Vulnerability Audit
 * Parses dependency manifests and queries the OSV.dev public API for known CVEs.
 */
function check_dependencies(string $owner, string $repo, string $pat, array $tree): array
{
    $manifestMap = [
        'package.json'    => 'npm',
        'composer.json'   => 'Packagist',
        'requirements.txt' => 'PyPI',
        'Gemfile'         => 'RubyGems',
        'go.mod'          => 'Go',
    ];

    $findings        = [];
    $recommendations = [];
    $detectedEcosystems = [];

    foreach ($manifestMap as $filename => $ecosystem) {
        $node = tree_find_file($tree, $filename);
        if (!$node) {
            continue;
        }
        $content = github_get_file_content($owner, $repo, $node['path'], $pat);
        if ($content === null) {
            continue;
        }

        $packages = parse_dependency_file($filename, $content);
        if (empty($packages)) {
            continue;
        }
        $detectedEcosystems[] = $ecosystem;

        // Query OSV for first 25 packages to stay within time budget
        foreach (array_slice($packages, 0, 25) as $pkg) {
            $vulns = query_osv($pkg['name'], $pkg['version'] ?? '', $ecosystem);
            foreach (array_slice($vulns, 0, 3) as $vuln) {
                $findings[] = [
                    'category'    => 'Dependencies',
                    'title'       => "Vulnerable package: {$pkg['name']}",
                    'description' => "Package `{$pkg['name']}` (v" . ($pkg['version'] ?: 'unspecified') . ") — {$vuln['id']}: {$vuln['summary']}",
                    'severity'    => $vuln['severity'],
                ];
            }
        }
    }

    if (empty($detectedEcosystems)) {
        $findings[] = [
            'category'    => 'Dependencies',
            'title'       => 'No recognised dependency manifest found',
            'description' => 'No package.json, composer.json, requirements.txt, Gemfile, or go.mod was found at root level. Dependency auditing could not be performed.',
            'severity'    => 'Low',
        ];
        $recommendations[] = [
            'recommendation_text' => 'Add a dependency manifest (package.json, composer.json, etc.) so automated vulnerability scanners can audit your dependencies.',
            'priority'            => 'Medium',
        ];
        return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
    }

    $vulnCount = count(array_filter($findings, fn($f) => $f['category'] === 'Dependencies' && $f['title'] !== 'No recognised dependency manifest found'));

    if ($vulnCount > 0) {
        $recommendations[] = [
            'recommendation_text' => "Update the {$vulnCount} vulnerable package(s) identified via OSV.dev. Enable GitHub Dependabot alerts for automated notifications on future CVEs.",
            'priority'            => 'High',
        ];
    } else {
        $recommendations[] = [
            'recommendation_text' => 'No known vulnerabilities found in checked packages. Enable Dependabot or run `npm audit` / `composer audit` regularly to stay current.',
            'priority'            => 'Low',
        ];
    }

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}

function parse_dependency_file(string $filename, string $content): array
{
    $packages = [];

    if ($filename === 'package.json') {
        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [];
        }
        $allDeps = array_merge($data['dependencies'] ?? [], $data['devDependencies'] ?? []);
        foreach ($allDeps as $name => $version) {
            $packages[] = ['name' => $name, 'version' => ltrim((string) $version, '^~>=<v ')];
        }

    } elseif ($filename === 'composer.json') {
        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [];
        }
        $allDeps = array_merge($data['require'] ?? [], $data['require-dev'] ?? []);
        foreach ($allDeps as $name => $version) {
            if ($name === 'php') {
                continue;
            }
            $packages[] = ['name' => $name, 'version' => ltrim((string) $version, '^~>=<v* ')];
        }

    } elseif ($filename === 'requirements.txt') {
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === '-') {
                continue;
            }
            if (preg_match('/^([A-Za-z0-9_\-\.]+)\s*(?:[>=<!]+\s*([\d\.]+))?/', $line, $m)) {
                $packages[] = ['name' => $m[1], 'version' => $m[2] ?? ''];
            }
        }

    } elseif ($filename === 'go.mod') {
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if (preg_match('/^require\s+(\S+)\s+(v[\d\.]+)/', $line, $m)
                || preg_match('/^\s+(\S+)\s+(v[\d\.]+)/', $line, $m)) {
                $packages[] = ['name' => $m[1], 'version' => ltrim($m[2], 'v')];
            }
        }
    }

    return $packages;
}

function query_osv(string $packageName, string $version, string $ecosystem): array
{
    $payload = json_encode([
        'version' => $version,
        'package' => ['name' => $packageName, 'ecosystem' => $ecosystem],
    ]);

    $ch = curl_init('https://api.osv.dev/v1/query');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);

    if ($body === false) {
        return [];
    }
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['vulns'])) {
        return [];
    }

    $vulns = [];
    foreach (array_slice($data['vulns'], 0, 3) as $v) {
        $vulns[] = [
            'id'       => $v['id'] ?? 'Unknown',
            'summary'  => substr($v['summary'] ?? 'No details available.', 0, 120),
            'severity' => osv_severity($v),
        ];
    }
    return $vulns;
}

function osv_severity(array $vuln): string
{
    // Try CVSS score embedded in severity array
    foreach ($vuln['severity'] ?? [] as $s) {
        if (preg_match('/\/(\d+\.\d+)$/', $s['score'] ?? '', $m)) {
            $cvss = (float) $m[1];
            return $cvss >= 7.0 ? 'High' : ($cvss >= 4.0 ? 'Medium' : 'Low');
        }
    }
    // Fall back to database_specific severity field
    $sev = strtolower($vuln['database_specific']['severity'] ?? '');
    if (in_array($sev, ['critical', 'high'], true)) {
        return 'High';
    }
    return 'Medium';
}
