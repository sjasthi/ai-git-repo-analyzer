<?php

declare(strict_types=1);

/**
 * Dependency SBOM checks (#91-#100).
 * Each rule consumes shared dependency telemetry generated once per scan.
 */
function check_dependency_sbom(
    string $owner,
    string $repo,
    string $pat,
    array $tree,
    ?string $repoLicenseName,
    string $ruleId
): array {
    $ruleMeta = [
        'dependency_inventory_accuracy' => [
            'title' => 'Dependency Inventory',
            'priority' => 'Medium',
        ],
        'dependency_identity_normalization' => [
            'title' => 'Vulnerability Detection',
            'priority' => 'Medium',
        ],
        'dependency_graph_mapping' => [
            'title' => 'License Compliance',
            'priority' => 'Medium',
        ],
        'dependency_vulnerability_correlation' => [
            'title' => 'Supply Chain Security',
            'priority' => 'High',
        ],
        'dependency_license_risk' => [
            'title' => 'Version Tracking',
            'priority' => 'High',
        ],
        'dependency_provenance_traceability' => [
            'title' => 'Risk Assessment',
            'priority' => 'Medium',
        ],
        'dependency_integrity_verification' => [
            'title' => 'Dependency Mapping',
            'priority' => 'High',
        ],
        'dependency_sbom_format_quality' => [
            'title' => 'Compliance and Auditing',
            'priority' => 'Medium',
        ],
        'dependency_sbom_automation' => [
            'title' => 'Continuous SBOM Automation',
            'priority' => 'Medium',
        ],
        'dependency_drift_unused' => [
            'title' => 'Software Transparency',
            'priority' => 'Medium',
        ],
    ];

    if (!isset($ruleMeta[$ruleId])) {
        return ['findings' => [], 'recommendations' => [], 'skills' => []];
    }

    $data = dependency_sbom_collect($owner, $repo, $pat, $tree, $repoLicenseName);

    $findings = [];
    $recommendations = [];
    $ruleTitle = $ruleMeta[$ruleId]['title'];

    if ($data['manifest_count'] === 0) {
        if ($ruleId === 'dependency_inventory_accuracy') {
            $findings[] = dependency_sbom_finding(
                $ruleTitle,
                'No supported dependency manifests were found',
                'No package.json, composer.json, requirements.txt, Gemfile, or go.mod was detected. This rule could not be evaluated completely.',
                'Low'
            );
            $recommendations[] = [
                'recommendation_text' => 'Add dependency manifests and generate an SBOM for every release. Template: | Dependency | Version | Status |.',
                'priority' => 'Medium',
            ];
        } else {
            $recommendations[] = [
                'recommendation_text' => 'Skipped because no supported dependency manifests were found. Enable #91 Dependency Inventory first, then rerun SBOM checks.',
                'priority' => 'Low',
            ];
        }

        return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
    }

    switch ($ruleId) {
        case 'dependency_inventory_accuracy':
            if ($data['dependency_count'] < 5) {
                $findings[] = dependency_sbom_finding(
                    $ruleTitle,
                    'Dependency inventory appears incomplete',
                    'Only ' . $data['dependency_count'] . ' dependencies were discovered across ' . $data['manifest_count'] . ' manifest(s). Verify that all ecosystems and lockfiles are committed.',
                    'Medium'
                );
            }
            break;

        case 'dependency_identity_normalization':
            if ($data['unversioned_count'] > 0 || $data['floating_count'] > 0) {
                $findings[] = dependency_sbom_finding(
                    $ruleTitle,
                    'Dependency identifiers are not fully normalized',
                    $data['unversioned_count'] . ' dependencies have missing versions and ' . $data['floating_count'] . ' use floating constraints.',
                    'Medium'
                );
            }
            break;

        case 'dependency_graph_mapping':
            if ($data['missing_lockfile_count'] > 0) {
                $findings[] = dependency_sbom_finding(
                    $ruleTitle,
                    'Dependency graph mapping is weakened by missing lockfiles',
                    $data['missing_lockfile_count'] . ' manifest(s) are missing expected lockfiles, reducing transitive dependency traceability.',
                    'Medium'
                );
            }
            break;

        case 'dependency_vulnerability_correlation':
            if ($data['vulnerable_count'] > 0) {
                $findings[] = dependency_sbom_finding(
                    $ruleTitle,
                    'Vulnerable dependencies detected',
                    $data['vulnerable_count'] . ' dependencies matched known OSV advisories.',
                    'High'
                );
            }
            break;

        case 'dependency_license_risk':
            if ($data['license_risk']) {
                $findings[] = dependency_sbom_finding(
                    $ruleTitle,
                    'Dependency license compatibility review required',
                    'Repository license signals indicate compatibility or disclosure obligations should be reviewed before distribution.',
                    'Medium'
                );
            }
            break;

        case 'dependency_provenance_traceability':
            if (!$data['has_repository_metadata']) {
                $findings[] = dependency_sbom_finding(
                    $ruleTitle,
                    'Dependency provenance metadata is limited',
                    'No repository/homepage metadata was found in the sampled manifests, reducing component source traceability.',
                    'Low'
                );
            }
            break;

        case 'dependency_integrity_verification':
            if ($data['missing_lockfile_count'] > 0 || !$data['has_integrity_markers']) {
                $findings[] = dependency_sbom_finding(
                    $ruleTitle,
                    'Dependency integrity controls are incomplete',
                    'Missing lockfiles or checksum/integrity markers reduce confidence in artifact authenticity.',
                    'High'
                );
            }
            break;

        case 'dependency_sbom_format_quality':
            if (!$data['has_sbom']) {
                $findings[] = dependency_sbom_finding(
                    $ruleTitle,
                    'No SBOM file detected',
                    'No CycloneDX/SPDX style SBOM file was found in the repository.',
                    'Medium'
                );
            } elseif (!$data['has_sbom_structure']) {
                $findings[] = dependency_sbom_finding(
                    $ruleTitle,
                    'SBOM file structure appears incomplete',
                    'SBOM files were found but key structure markers (components/packages) were not detected.',
                    'Low'
                );
            }
            break;

        case 'dependency_sbom_automation':
            if (!$data['has_sbom_ci_automation']) {
                $findings[] = dependency_sbom_finding(
                    $ruleTitle,
                    'SBOM generation is not automated in CI',
                    'No CI workflow evidence was found for SBOM generation or validation.',
                    'Medium'
                );
            }
            break;

        case 'dependency_drift_unused':
            if ($data['unused_count'] > 0 || $data['floating_count'] > 0) {
                $findings[] = dependency_sbom_finding(
                    $ruleTitle,
                    'Dependency drift or unused packages detected',
                    $data['unused_count'] . ' dependencies may be unused and ' . $data['floating_count'] . ' use floating constraints. Template: | Dependency | Version | Status |.',
                    'Medium'
                );
            }
            break;
    }

    if (!empty($findings)) {
        $recommendations[] = [
            'recommendation_text' => 'Use SBOM governance for this area and track results with: | Dependency | Version | Status |. Prioritize high-risk items first.',
            'priority' => $ruleMeta[$ruleId]['priority'],
        ];
    }

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}

// Backward-compatible wrapper.
function check_dependency_analysis(string $owner, string $repo, string $pat, array $tree, ?string $repoLicenseName = null): array
{
    return check_dependency_sbom($owner, $repo, $pat, $tree, $repoLicenseName, 'dependency_vulnerability_correlation');
}

function dependency_sbom_collect(string $owner, string $repo, string $pat, array $tree, ?string $repoLicenseName): array
{
    static $cache = [];
    $cacheKey = $owner . '/' . $repo;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $manifests = [
        'package.json' => ['ecosystem' => 'npm', 'lockfiles' => ['package-lock.json', 'pnpm-lock.yaml', 'yarn.lock']],
        'composer.json' => ['ecosystem' => 'Packagist', 'lockfiles' => ['composer.lock']],
        'requirements.txt' => ['ecosystem' => 'PyPI', 'lockfiles' => []],
        'Gemfile' => ['ecosystem' => 'RubyGems', 'lockfiles' => ['Gemfile.lock']],
        'go.mod' => ['ecosystem' => 'Go', 'lockfiles' => ['go.sum']],
    ];

    $depRows = [];
    $manifestCount = 0;
    $floatingCount = 0;
    $unversionedCount = 0;
    $missingLockfileCount = 0;
    $hasRepositoryMetadata = false;
    $hasIntegrityMarkers = false;

    foreach ($manifests as $manifestName => $meta) {
        $manifestNode = tree_find_file($tree, $manifestName);
        if ($manifestNode === null) {
            continue;
        }

        $content = github_get_file_content($owner, $repo, (string) $manifestNode['path'], $pat);
        if ($content === null) {
            continue;
        }

        $manifestCount++;
        $manifestData = dependency_sbom_parse_manifest($manifestName, $content);
        $deps = $manifestData['dependencies'];
        if ($manifestData['has_repository_metadata']) {
            $hasRepositoryMetadata = true;
        }

        foreach ($deps as $dep) {
            $name = trim((string) ($dep['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $spec = trim((string) ($dep['spec'] ?? ''));
            $version = trim((string) ($dep['version'] ?? ''));
            if ($version === '') {
                $unversionedCount++;
            }
            if (dependency_sbom_is_floating_or_unbounded($spec)) {
                $floatingCount++;
            }

            $key = strtolower((string) $meta['ecosystem']) . '|' . strtolower($name);
            $depRows[$key] = [
                'name' => $name,
                'version' => $version !== '' ? $version : ($spec !== '' ? $spec : 'unspecified'),
                'spec' => $spec,
                'ecosystem' => (string) $meta['ecosystem'],
            ];
        }

        foreach ($meta['lockfiles'] as $lockfile) {
            $lockNode = tree_find_file($tree, (string) $lockfile);
            if ($lockNode === null) {
                $missingLockfileCount++;
                continue;
            }
            $lockContent = github_get_file_content($owner, $repo, (string) $lockNode['path'], $pat);
            if ($lockContent !== null && preg_match('/integrity|checksum|sha256|sha512|resolved/i', $lockContent) === 1) {
                $hasIntegrityMarkers = true;
            }
        }
    }

    $queryPackages = [];
    foreach (array_values($depRows) as $row) {
        $queryPackages[] = [
            'name' => (string) $row['name'],
            'version' => (string) $row['version'],
            'ecosystem' => (string) $row['ecosystem'],
        ];
    }

    $vulnerableCount = 0;
    foreach (array_slice($queryPackages, 0, 20) as $pkg) {
        $vulns = query_osv($pkg['name'], $pkg['version'], $pkg['ecosystem']);
        if (!empty($vulns)) {
            $vulnerableCount++;
        }
    }

    $sourceFiles = tree_files_by_extensions($tree, ['php', 'js', 'ts', 'tsx', 'jsx', 'mjs', 'py', 'java', 'cs', 'go', 'rb'], 30);
    $corpus = dependency_sbom_code_corpus($owner, $repo, $pat, $sourceFiles);

    $unusedCount = 0;
    foreach ($depRows as $row) {
        if (dependency_sbom_is_possibly_unused((string) $row['name'], $corpus)) {
            $unusedCount++;
        }
    }

    $licenseCheck = check_license($owner, $repo, $pat, $tree, $repoLicenseName);
    $licenseRisk = false;
    foreach (($licenseCheck['findings'] ?? []) as $finding) {
        $title = strtolower((string) ($finding['title'] ?? ''));
        if (str_contains($title, 'copyleft') || str_contains($title, 'no license file')) {
            $licenseRisk = true;
            break;
        }
    }

    $sbomNodes = dependency_sbom_find_sbom_nodes($tree);
    $hasSbom = !empty($sbomNodes);
    $hasSbomStructure = false;
    foreach (array_slice($sbomNodes, 0, 3) as $node) {
        $sbomContent = github_get_file_content($owner, $repo, (string) $node['path'], $pat);
        if ($sbomContent !== null && preg_match('/"components"|"packages"|"bomFormat"|"spdxVersion"/i', $sbomContent) === 1) {
            $hasSbomStructure = true;
            break;
        }
    }

    $hasSbomCiAutomation = false;
    foreach ($tree as $entry) {
        if (($entry['type'] ?? '') !== 'blob') {
            continue;
        }
        $path = (string) ($entry['path'] ?? '');
        if (preg_match('/^\.github\/workflows\/.+\.ya?ml$/i', $path) !== 1) {
            continue;
        }
        $workflow = github_get_file_content($owner, $repo, $path, $pat);
        if ($workflow !== null && preg_match('/sbom|cyclonedx|spdx/i', $workflow) === 1) {
            $hasSbomCiAutomation = true;
            break;
        }
    }

    $cache[$cacheKey] = [
        'manifest_count' => $manifestCount,
        'dependency_count' => count($depRows),
        'floating_count' => $floatingCount,
        'unversioned_count' => $unversionedCount,
        'missing_lockfile_count' => $missingLockfileCount,
        'vulnerable_count' => $vulnerableCount,
        'unused_count' => $unusedCount,
        'license_risk' => $licenseRisk,
        'has_repository_metadata' => $hasRepositoryMetadata,
        'has_integrity_markers' => $hasIntegrityMarkers,
        'has_sbom' => $hasSbom,
        'has_sbom_structure' => $hasSbomStructure,
        'has_sbom_ci_automation' => $hasSbomCiAutomation,
    ];

    return $cache[$cacheKey];
}

function dependency_sbom_finding(string $titlePrefix, string $title, string $description, string $severity): array
{
    return [
        'category' => 'Dependency Analysis',
        'title' => $titlePrefix . ': ' . $title,
        'description' => $description,
        'severity' => $severity,
    ];
}

function dependency_sbom_parse_manifest(string $filename, string $content): array
{
    $result = ['dependencies' => [], 'has_repository_metadata' => false];

    if ($filename === 'package.json') {
        $data = json_decode($content, true);
        if (!is_array($data)) {
            return $result;
        }
        $result['has_repository_metadata'] = isset($data['repository']) || isset($data['homepage']);
        $all = array_merge($data['dependencies'] ?? [], $data['devDependencies'] ?? []);
        foreach ($all as $name => $spec) {
            $specString = trim((string) $spec);
            $result['dependencies'][] = [
                'name' => (string) $name,
                'spec' => $specString,
                'version' => ltrim($specString, '^~>=<v* '),
            ];
        }
        return $result;
    }

    if ($filename === 'composer.json') {
        $data = json_decode($content, true);
        if (!is_array($data)) {
            return $result;
        }
        $result['has_repository_metadata'] = isset($data['homepage']) || isset($data['support']);
        $all = array_merge($data['require'] ?? [], $data['require-dev'] ?? []);
        foreach ($all as $name => $spec) {
            if ((string) $name === 'php') {
                continue;
            }
            $specString = trim((string) $spec);
            $result['dependencies'][] = [
                'name' => (string) $name,
                'spec' => $specString,
                'version' => ltrim($specString, '^~>=<v* '),
            ];
        }
        return $result;
    }

    if ($filename === 'requirements.txt') {
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === '-') {
                continue;
            }
            if (preg_match('/^([A-Za-z0-9_\-\.]+)\s*([>=<!~].*)?$/', $line, $m) === 1) {
                $spec = trim((string) ($m[2] ?? ''));
                $result['dependencies'][] = [
                    'name' => (string) $m[1],
                    'spec' => $spec,
                    'version' => ltrim($spec, '=<>!~v '),
                ];
            }
        }
        return $result;
    }

    if ($filename === 'go.mod') {
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if (preg_match('/^require\s+(\S+)\s+(v[\d\.\-\+\w]+)/', $line, $m) === 1
                || preg_match('/^(\S+)\s+(v[\d\.\-\+\w]+)/', $line, $m) === 1) {
                $result['dependencies'][] = [
                    'name' => (string) $m[1],
                    'spec' => (string) $m[2],
                    'version' => ltrim((string) $m[2], 'v'),
                ];
            }
        }
        return $result;
    }

    if ($filename === 'Gemfile') {
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if (preg_match('/^gem\s+["\']([^"\']+)["\']\s*(?:,\s*["\']([^"\']+)["\'])?/i', $line, $m) === 1) {
                $spec = trim((string) ($m[2] ?? ''));
                $result['dependencies'][] = [
                    'name' => (string) $m[1],
                    'spec' => $spec,
                    'version' => ltrim($spec, '^~>=<v* '),
                ];
            }
        }
    }

    return $result;
}

function dependency_sbom_is_floating_or_unbounded(string $spec): bool
{
    $s = strtolower(trim($spec));
    if ($s === '') {
        return true;
    }
    return (bool) preg_match('/(^\*$|latest|dev-|master|main|\^|~|>=|<=|>|<|x$|\.x$)/', $s);
}

function dependency_sbom_code_corpus(string $owner, string $repo, string $pat, array $sourceFiles): string
{
    $parts = [];
    foreach ($sourceFiles as $node) {
        $path = (string) ($node['path'] ?? '');
        if ($path === '') {
            continue;
        }
        $content = github_get_file_content($owner, $repo, $path, $pat);
        if ($content === null || $content === '') {
            continue;
        }
        $parts[] = strtolower(substr($content, 0, 20000));
    }
    return implode("\n", $parts);
}

function dependency_sbom_is_possibly_unused(string $dependencyName, string $codeCorpus): bool
{
    $name = strtolower(trim($dependencyName));
    if ($name === '' || $codeCorpus === '') {
        return false;
    }

    $candidates = [$name];
    if (str_contains($name, '/')) {
        $segments = explode('/', $name);
        $last = end($segments);
        if (is_string($last) && $last !== '') {
            $candidates[] = $last;
        }
    }

    foreach ($candidates as $candidate) {
        $token = trim((string) $candidate);
        if ($token === '' || strlen($token) < 3) {
            continue;
        }
        if (str_contains($codeCorpus, $token)) {
            return false;
        }
    }

    return true;
}

function dependency_sbom_find_sbom_nodes(array $tree): array
{
    $nodes = [];
    foreach ($tree as $entry) {
        if (($entry['type'] ?? '') !== 'blob') {
            continue;
        }
        $path = (string) ($entry['path'] ?? '');
        if (preg_match('/(^|\/)(sbom|bom|cyclonedx|spdx)[^\/]*\.(json|xml|spdx|yaml|yml)$/i', $path) === 1) {
            $nodes[] = $entry;
        }
    }
    return $nodes;
}
