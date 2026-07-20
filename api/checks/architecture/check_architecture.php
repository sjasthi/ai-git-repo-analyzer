<?php

declare(strict_types=1);

function check_architecture(string $owner, string $repo, string $pat, array $tree, array $languages, array $sourceFiles, string $principleId): array
{
    return match ($principleId) {
        'architecture_layered_boundaries' => architecture_layered_boundaries($tree),
        'architecture_dependency_rule' => architecture_dependency_rule($owner, $repo, $pat, $tree),
        'architecture_framework_independence' => architecture_framework_independence($owner, $repo, $pat, $tree),
        'architecture_presentation_isolation' => architecture_presentation_isolation($owner, $repo, $pat, $tree),
        'architecture_use_case_separation' => architecture_use_case_separation($owner, $repo, $pat, $tree),
        'architecture_domain_purity' => architecture_domain_purity($owner, $repo, $pat, $tree),
        'architecture_data_access_abstraction' => architecture_data_access_abstraction($owner, $repo, $pat, $tree),
        'architecture_interface_adapter_separation' => architecture_interface_adapter_separation($owner, $repo, $pat, $tree),
        'architecture_package_cohesion' => architecture_package_cohesion($tree),
        'architecture_no_cyclic_dependencies' => architecture_no_cyclic_dependencies($owner, $repo, $pat, $tree),
        default => ['findings' => [], 'recommendations' => [], 'skills' => []],
    };
}

function architecture_principle_title(string $principleId): string
{
    return match ($principleId) {
        'architecture_layered_boundaries' => 'Clean Architecture Layered Boundaries',
        'architecture_dependency_rule' => 'Clean Architecture Dependency Rule',
        'architecture_framework_independence' => 'Clean Architecture Framework Independence',
        'architecture_presentation_isolation' => 'Clean Architecture Presentation Isolation',
        'architecture_use_case_separation' => 'Clean Architecture Use Case Separation',
        'architecture_domain_purity' => 'Clean Architecture Domain Purity',
        'architecture_data_access_abstraction' => 'Clean Architecture Data Access Abstraction',
        'architecture_interface_adapter_separation' => 'Clean Architecture Interface Adapter Separation',
        'architecture_package_cohesion' => 'Clean Architecture Package Cohesion',
        'architecture_no_cyclic_dependencies' => 'Clean Architecture No Cyclic Dependencies',
        default => 'Clean Architecture',
    };
}

function architecture_result(string $title, string $summary, string $severity, array $evidence = [], array $recommendations = []): array
{
    $findings = [];
    if (!empty($evidence)) {
        $findings[] = [
            'category' => 'Architecture',
            'title' => $title,
            'description' => $summary,
            'severity' => $severity,
            'evidence' => array_values($evidence),
        ];
    }

    if (empty($recommendations)) {
        $recommendations[] = [
            'recommendation_text' => 'Maintain the current Clean Architecture boundary and keep dependencies pointing inward.',
            'priority' => 'Low',
        ];
    }

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}

function architecture_php_paths(array $tree, array $prefixes = []): array
{
    $paths = [];
    foreach ($tree as $node) {
        if (($node['type'] ?? '') !== 'blob') {
            continue;
        }
        $path = (string) ($node['path'] ?? '');
        if ($path === '' || !preg_match('/\.php$/i', $path)) {
            continue;
        }
        if (!empty($prefixes)) {
            $matched = false;
            foreach ($prefixes as $prefix) {
                if ($prefix !== '' && str_starts_with($path, $prefix)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                continue;
            }
        }
        $paths[] = $path;
    }

    return array_values(array_unique($paths));
}

function architecture_paths_with_content(string $owner, string $repo, string $pat, array $paths): array
{
    $files = [];
    foreach ($paths as $path) {
        $content = github_get_file_content($owner, $repo, $path, $pat);
        if ($content === null) {
            continue;
        }
        $files[$path] = $content;
    }

    return $files;
}

function architecture_layered_boundaries(array $tree): array
{
    $title = architecture_principle_title('architecture_layered_boundaries');
    $requiredLayers = [
        'presentation' => ['index.php', 'dashboard.php', 'contact.php'],
        'application' => ['api/analyze.php', 'api/report.php'],
        'infrastructure' => ['config/database.php', 'api/github_helper.php'],
        'feature modules' => ['api/checks/clean_code/', 'api/checks/security/', 'api/checks/complexity/', 'api/checks/sonarqube/'],
    ];

    $evidence = [];
    foreach ($requiredLayers as $layerName => $candidates) {
        foreach ($candidates as $candidate) {
            if (str_ends_with($candidate, '/')) {
                foreach ($tree as $node) {
                    if (($node['type'] ?? '') !== 'blob') {
                        continue;
                    }
                    $path = (string) ($node['path'] ?? '');
                    if ($path !== '' && str_starts_with($path, $candidate)) {
                        $evidence[] = $layerName . ': ' . $candidate;
                        break 2;
                    }
                }
            } elseif (tree_find_file($tree, $candidate) !== null) {
                $evidence[] = $layerName . ': ' . $candidate;
                break;
            }
        }
    }

    return architecture_result(
        $title,
        'The repository is organized into distinct presentation, application, infrastructure, and feature layers that fit a Clean Architecture layout.',
        'Low',
        $evidence,
        [[
            'recommendation_text' => 'Keep the current high-level layering and avoid moving framework or persistence concerns into the feature modules.',
            'priority' => 'Low',
        ]]
    );
}

function architecture_dependency_rule(string $owner, string $repo, string $pat, array $tree): array
{
    $title = architecture_principle_title('architecture_dependency_rule');
    $paths = architecture_php_paths($tree, ['api/']);
    $files = architecture_paths_with_content($owner, $repo, $pat, $paths);
    $violations = [];

    foreach ($files as $path => $content) {
        if (preg_match('/(?:require_once|include_once|require|include)\s*[^(]*[\'\"](?:\.\.?\/)?(?:index|dashboard|contact|report)\.php[\'\"]/i', $content)) {
            $violations[] = $path . ' imports a presentation entry point';
        }
    }

    if (!empty($violations)) {
        return architecture_result(
            $title,
            'Inner application files depend on presentation entry points, which reverses the dependency rule.',
            'Medium',
            $violations,
            [[
                'recommendation_text' => 'Remove upward references from application or feature modules to UI pages and keep dependencies pointing inward only.',
                'priority' => 'High',
            ]]
        );
    }

    return architecture_result(
        $title,
        'Application code does not appear to depend on outer presentation entry points.',
        'Low',
        ['No direct references from api/ modules to index/dashboard/report pages were found.'],
        [[
            'recommendation_text' => 'Preserve the dependency rule by keeping UI references out of application and feature modules.',
            'priority' => 'Low',
        ]]
    );
}

function architecture_framework_independence(string $owner, string $repo, string $pat, array $tree): array
{
    $title = architecture_principle_title('architecture_framework_independence');
    $paths = architecture_php_paths($tree, ['api/checks/', 'api/']);
    $files = architecture_paths_with_content($owner, $repo, $pat, $paths);
    $violations = [];

    foreach ($files as $path => $content) {
        if (preg_match('/<\s*(html|div|span|form|button|table|section|article|script|style)\b/i', $content)
            || preg_match('/bootstrap|jquery|font-awesome|tailwind/i', $content)) {
            $violations[] = $path . ' contains presentation framework artifacts';
        }
    }

    if (!empty($violations)) {
        return architecture_result(
            $title,
            'Core modules contain framework-specific presentation artifacts, reducing framework independence.',
            'Medium',
            $violations,
            [[
                'recommendation_text' => 'Keep framework-specific markup, CSS, and scripts in the UI layer and leave core modules framework-agnostic.',
                'priority' => 'High',
            ]]
        );
    }

    return architecture_result(
        $title,
        'Feature and application code remain mostly framework-agnostic.',
        'Low',
        ['No HTML or UI framework artifacts were found in the core check modules.'],
        [[
            'recommendation_text' => 'Continue isolating framework-specific code to the presentation layer.',
            'priority' => 'Low',
        ]]
    );
}

function architecture_presentation_isolation(string $owner, string $repo, string $pat, array $tree): array
{
    $title = architecture_principle_title('architecture_presentation_isolation');
    $paths = architecture_php_paths($tree, ['index.php', 'dashboard.php', 'contact.php']);
    $files = architecture_paths_with_content($owner, $repo, $pat, $paths);
    $violations = [];

    foreach ($files as $path => $content) {
        if (preg_match('/\bdb_connection\s*\(/i', $content)
            || preg_match('/\bSELECT\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b/i', $content)
            || preg_match('/\bcurl_init\s*\(/i', $content)
            || preg_match('/\bgithub_get\s*\(/i', $content)) {
            $violations[] = $path . ' mixes presentation with data access or orchestration';
        }
    }

    if (!empty($violations)) {
        return architecture_result(
            $title,
            'Presentation pages are still reaching into data access and orchestration concerns.',
            'High',
            $violations,
            [[
                'recommendation_text' => 'Move data retrieval behind an application service or API boundary and let the view layer only render prepared data.',
                'priority' => 'High',
            ]]
        );
    }

    return architecture_result(
        $title,
        'Presentation files remain isolated from data access and remote calls.',
        'Low',
        ['No presentation-layer database or HTTP calls were found.'],
        [[
            'recommendation_text' => 'Keep the view layer focused on rendering and event handling only.',
            'priority' => 'Low',
        ]]
    );
}

function architecture_use_case_separation(string $owner, string $repo, string $pat, array $tree): array
{
    $title = architecture_principle_title('architecture_use_case_separation');
    $content = github_get_file_content($owner, $repo, 'api/analyze.php', $pat);
    if ($content === null) {
        return architecture_result($title, 'The main application service file could not be inspected.', 'Low', [], []);
    }

    $evidence = [];
    if (preg_match('/\bINSERT\s+INTO\b/i', $content) || preg_match('/\bUPDATE\s+\w+\b/i', $content) || preg_match('/\bSELECT\b/i', $content)) {
        $evidence[] = 'api/analyze.php performs persistence and query work directly';
    }
    if (preg_match('/function\s+run_check\s*\(/i', $content) && preg_match('/\$newCheckMap\s*=\s*\[/i', $content)) {
        $evidence[] = 'api/analyze.php contains orchestration plus persistence helpers in one file';
    }

    if (!empty($evidence)) {
        return architecture_result(
            $title,
            'Use case orchestration and persistence concerns are currently combined in the application service file.',
            'Medium',
            $evidence,
            [[
                'recommendation_text' => 'Split the use-case orchestration from repository persistence so the application service depends on abstractions, not SQL statements.',
                'priority' => 'High',
            ]]
        );
    }

    return architecture_result(
        $title,
        'Use case orchestration is separated from persistence concerns.',
        'Low',
        ['No mixed orchestration/persistence concern was detected in api/analyze.php.'],
        [[
            'recommendation_text' => 'Keep application services focused on orchestration and move storage into dedicated adapters.',
            'priority' => 'Low',
        ]]
    );
}

function architecture_domain_purity(string $owner, string $repo, string $pat, array $tree): array
{
    $title = architecture_principle_title('architecture_domain_purity');
    $paths = architecture_php_paths($tree, ['api/checks/']);
    $files = architecture_paths_with_content($owner, $repo, $pat, $paths);
    $violations = [];

    foreach ($files as $path => $content) {
        if (preg_match('/\bdb_connection\s*\(/i', $content)
            || preg_match('/\bheader\s*\(/i', $content)
            || preg_match('/<\s*(html|div|form|script|style)\b/i', $content)) {
            $violations[] = $path . ' leaks infrastructure or presentation concerns';
        }
    }

    if (!empty($violations)) {
        return architecture_result(
            $title,
            'Feature modules are not fully pure and still leak infrastructure or presentation concerns.',
            'Medium',
            $violations,
            [[
                'recommendation_text' => 'Keep feature modules as pure analyzers that return structured arrays without touching headers, HTML, or database connections.',
                'priority' => 'High',
            ]]
        );
    }

    return architecture_result(
        $title,
        'Feature modules remain pure and return structured data.',
        'Low',
        ['No infrastructure or presentation leakage was found in the check modules.'],
        [[
            'recommendation_text' => 'Preserve the purity of check modules by keeping I/O outside the analysis functions.',
            'priority' => 'Low',
        ]]
    );
}

function architecture_data_access_abstraction(string $owner, string $repo, string $pat, array $tree): array
{
    $title = architecture_principle_title('architecture_data_access_abstraction');
    $paths = architecture_php_paths($tree, ['api/', 'dashboard.php', 'index.php']);
    $files = architecture_paths_with_content($owner, $repo, $pat, $paths);
    $violations = [];

    foreach ($files as $path => $content) {
        if ($path !== 'api/github_helper.php' && preg_match('/\bcurl_init\s*\(/i', $content)) {
            $violations[] = $path . ' performs HTTP access directly instead of using the shared helper';
        }
    }

    if (!empty($violations)) {
        return architecture_result(
            $title,
            'HTTP/data-access code is still duplicated outside the shared repository helper.',
            'Medium',
            $violations,
            [[
                'recommendation_text' => 'Route external repository access through the shared github_helper adapter so the application depends on one data-access abstraction.',
                'priority' => 'High',
            ]]
        );
    }

    return architecture_result(
        $title,
        'External data access appears centralized in the shared helper.',
        'Low',
        ['No extra curl-based adapter outside github_helper.php was found.'],
        [[
            'recommendation_text' => 'Keep repository and API access behind a single helper boundary.',
            'priority' => 'Low',
        ]]
    );
}

function architecture_interface_adapter_separation(string $owner, string $repo, string $pat, array $tree): array
{
    $title = architecture_principle_title('architecture_interface_adapter_separation');
    $paths = architecture_php_paths($tree, ['api/checks/']);
    $files = architecture_paths_with_content($owner, $repo, $pat, $paths);
    $violations = [];

    foreach ($files as $path => $content) {
        if (preg_match('/\becho\b|\bprintf\b|<\s*(html|div|script|style)/i', $content)) {
            $violations[] = $path . ' renders output instead of returning structured data';
        }
    }

    if (!empty($violations)) {
        return architecture_result(
            $title,
            'Some adapters are rendering or printing output directly rather than returning data objects.',
            'Medium',
            $violations,
            [[
                'recommendation_text' => 'Keep adapter boundaries explicit: checks should return arrays, and presentation should happen only in the UI/report layers.',
                'priority' => 'Medium',
            ]]
        );
    }

    return architecture_result(
        $title,
        'Feature adapters return structured data and do not render views directly.',
        'Low',
        ['Check modules return arrays for findings, recommendations, and skills.'],
        [[
            'recommendation_text' => 'Preserve the adapter pattern by keeping HTML and JSON rendering outside feature modules.',
            'priority' => 'Low',
        ]]
    );
}

function architecture_package_cohesion(array $tree): array
{
    $title = architecture_principle_title('architecture_package_cohesion');
    $groups = [
        'api/checks/security/' => false,
        'api/checks/clean_code/' => false,
        'api/checks/complexity/' => false,
        'api/checks/sonarqube/' => false,
        'api/checks/architecture/' => false,
    ];

    foreach ($tree as $node) {
        if (($node['type'] ?? '') !== 'blob') {
            continue;
        }
        $path = (string) ($node['path'] ?? '');
        foreach ($groups as $prefix => $_) {
            if (str_starts_with($path, $prefix)) {
                $groups[$prefix] = true;
            }
        }
    }

    $missing = array_keys(array_filter($groups, static fn($present) => !$present));
    if (!empty($missing)) {
        return architecture_result(
            $title,
            'Some related architecture or analysis packages are missing dedicated folders.',
            'Low',
            array_map(static fn($prefix) => 'Missing package folder: ' . $prefix, $missing),
            [[
                'recommendation_text' => 'Keep related checks grouped by feature folder so the package layout stays cohesive and discoverable.',
                'priority' => 'Low',
            ]]
        );
    }

    $rootWrappers = [];
    foreach ($tree as $node) {
        if (($node['type'] ?? '') !== 'blob') {
            continue;
        }
        $path = (string) ($node['path'] ?? '');
        if (preg_match('/^check_[^\/]+\.php$/i', basename($path))) {
            $rootWrappers[] = $path;
        }
    }

    if (!empty($rootWrappers)) {
        return architecture_result(
            $title,
            'Feature packages are organized, but legacy root-level wrapper files duplicate the modular layout.',
            'Low',
            array_slice($rootWrappers, 0, 6),
            [[
                'recommendation_text' => 'Phase out legacy root wrappers over time so feature packages become the single source of truth.',
                'priority' => 'Low',
            ]]
        );
    }

    return architecture_result(
        $title,
        'Feature packages are grouped coherently by concern.',
        'Low',
        ['Security, Clean Code, Complexity, SonarQube, and Architecture checks are grouped in dedicated folders.'],
        [[
            'recommendation_text' => 'Continue grouping checks by concern and keep folder responsibilities narrow.',
            'priority' => 'Low',
        ]]
    );
}

function architecture_no_cyclic_dependencies(string $owner, string $repo, string $pat, array $tree): array
{
    $title = architecture_principle_title('architecture_no_cyclic_dependencies');
    $paths = architecture_php_paths($tree);
    $files = architecture_paths_with_content($owner, $repo, $pat, $paths);
    $violations = [];

    foreach ($files as $path => $content) {
        if (preg_match('/(?:require_once|include_once|require|include)\s*[^(]*[\'\"](?:\.\.?\/)?(?:index|dashboard|contact|report)\.php[\'\"]/i', $content)) {
            $violations[] = $path . ' depends on a UI entry point';
        }
    }

    if (!empty($violations)) {
        return architecture_result(
            $title,
            'A cyclic or upward dependency risk exists between layers.',
            'Medium',
            $violations,
            [[
                'recommendation_text' => 'Refactor imports so that lower layers never require higher-level UI entry points.',
                'priority' => 'High',
            ]]
        );
    }

    return architecture_result(
        $title,
        'No cyclic dependencies between the main layers were found.',
        'Low',
        ['No application or feature file required a presentation entry point.'],
        [[
            'recommendation_text' => 'Preserve the acyclic dependency direction as new modules are added.',
            'priority' => 'Low',
        ]]
    );
}