<?php

declare(strict_types=1);

/**
 * AI Readiness checks (#111-#120).
 */
function check_ai_readiness(
    string $owner,
    string $repo,
    string $pat,
    array $tree,
    string $ruleId
): array {
    $meta = [
        'ai_readme_richness' => ['title' => 'Rich README for AI Onboarding', 'priority' => 'High'],
        'ai_usage_examples' => ['title' => 'Usage Examples and Snippets', 'priority' => 'Medium'],
        'ai_api_clarity' => ['title' => 'Clear API Reference Documentation', 'priority' => 'Medium'],
        'ai_context_files' => ['title' => 'AI Assistant Context Files', 'priority' => 'Low'],
        'ai_dependency_manifest' => ['title' => 'Explicit Dependency Manifest', 'priority' => 'Medium'],
        'ai_naming_clarity' => ['title' => 'Descriptive Naming for AI Context', 'priority' => 'Medium'],
        'ai_function_granularity' => ['title' => 'Small, Single-Purpose Functions', 'priority' => 'Medium'],
        'ai_modular_structure' => ['title' => 'Modular Design and Boundaries', 'priority' => 'Medium'],
        'ai_consistent_style' => ['title' => 'Consistent Coding Style', 'priority' => 'Low'],
        'ai_docstring_coverage' => ['title' => 'Inline Documentation and Docstrings', 'priority' => 'High'],
    ];

    if (!isset($meta[$ruleId])) {
        return ['findings' => [], 'recommendations' => [], 'skills' => []];
    }

    $snapshot = ai_readiness_collect($owner, $repo, $pat, $tree);
    $title = $meta[$ruleId]['title'];
    $findings = [];
    $recommendations = [];

    switch ($ruleId) {
        case 'ai_readme_richness':
            if (!$snapshot['has_readme'] || $snapshot['readme_word_count'] < 150) {
                $findings[] = ai_readiness_finding(
                    $title,
                    'README is missing or too thin for AI onboarding',
                    'A rich README (purpose, setup, usage) helps both humans and AI assistants build an accurate mental model of the repository.',
                    'High'
                );
            }
            break;

        case 'ai_usage_examples':
            if (!$snapshot['has_usage_examples']) {
                $findings[] = ai_readiness_finding(
                    $title,
                    'No usage examples or code snippets detected',
                    'No fenced code block or "usage"/"example"/"quickstart" section was found in the README.',
                    'Medium'
                );
            }
            break;

        case 'ai_api_clarity':
            if (!$snapshot['has_api_docs']) {
                $findings[] = ai_readiness_finding(
                    $title,
                    'API reference documentation is unclear',
                    'No API/endpoint/reference section, OpenAPI/Swagger file, or docs/api file was detected.',
                    'Medium'
                );
            }
            break;

        case 'ai_context_files':
            if (!$snapshot['has_ai_context_file']) {
                $findings[] = ai_readiness_finding(
                    $title,
                    'No AI assistant context file found',
                    'Files such as CLAUDE.md, AGENTS.md, .cursorrules, or .github/copilot-instructions.md help AI coding assistants follow project conventions.',
                    'Low'
                );
            }
            break;

        case 'ai_dependency_manifest':
            if (!$snapshot['has_dependency_manifest']) {
                $findings[] = ai_readiness_finding(
                    $title,
                    'No recognized dependency manifest found',
                    'No package.json, composer.json, requirements.txt, or similar manifest was detected, making dependency context harder to infer.',
                    'Medium'
                );
            }
            break;

        case 'ai_naming_clarity':
            if ($snapshot['sample_file_count'] > 0 && $snapshot['short_name_ratio'] > 0.4) {
                $findings[] = ai_readiness_finding(
                    $title,
                    'Frequent use of very short, non-descriptive names',
                    'Sampled source files show a high ratio of one/two-character variable names, which reduces clarity for readers and AI tooling.',
                    'Medium'
                );
            }
            break;

        case 'ai_function_granularity':
            if ($snapshot['oversized_file_count'] > 0) {
                $findings[] = ai_readiness_finding(
                    $title,
                    $snapshot['oversized_file_count'] . ' sampled file(s) look oversized with few function boundaries',
                    'Large files with very few function/class declarations suggest sprawling, hard-to-isolate logic.',
                    'Medium'
                );
            }
            break;

        case 'ai_modular_structure':
            if ($snapshot['monolithic_layout']) {
                $findings[] = ai_readiness_finding(
                    $title,
                    'Source files are not organized into distinct modules',
                    'A sizable number of source files sit in a flat, single-directory layout instead of being grouped by feature or responsibility.',
                    'Medium'
                );
            }
            break;

        case 'ai_consistent_style':
            if ($snapshot['mixed_indentation_found']) {
                $findings[] = ai_readiness_finding(
                    $title,
                    'Mixed tabs and spaces detected in sampled files',
                    'Inconsistent indentation style across files makes automated formatting and diffing less reliable.',
                    'Low'
                );
            }
            break;

        case 'ai_docstring_coverage':
            if ($snapshot['sample_function_count'] > 0 && $snapshot['docstring_ratio'] < 0.4) {
                $findings[] = ai_readiness_finding(
                    $title,
                    'Most sampled functions lack a preceding comment or docstring',
                    'Only ' . round($snapshot['docstring_ratio'] * 100) . '% of sampled functions had a preceding comment/docblock explaining intent.',
                    'High'
                );
            }
            break;
    }

    if (!empty($findings)) {
        $recommendations[] = [
            'recommendation_text' => 'Improve this AI readiness area to make the repository easier for humans and AI assistants to understand and safely modify.',
            'priority' => $meta[$ruleId]['priority'],
        ];
    }

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}

function ai_readiness_collect(string $owner, string $repo, string $pat, array $tree): array
{
    static $cache = [];
    $cacheKey = $owner . '/' . $repo;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $readmeNode = tree_find_file($tree, 'README.md');
    $readmeContent = '';
    if ($readmeNode !== null) {
        $content = github_get_file_content($owner, $repo, (string) $readmeNode['path'], $pat);
        if (is_string($content)) {
            $readmeContent = $content;
        }
    }
    $readmeWordCount = $readmeContent === '' ? 0 : count(preg_split('/\s+/', trim($readmeContent)));

    $hasUsageExamples = (preg_match('/```/', $readmeContent) === 1)
        || (preg_match('/##?\s*(usage|example|quick\s*start|getting started)/i', $readmeContent) === 1);

    $apiFileNode = ai_find_path_match($tree, '/(openapi|swagger)\.(ya?ml|json)$/i')
        ?? ai_find_path_match($tree, '/^docs\/api[^\/]*\.(md|mdx)$/i');
    $hasApiDocs = ($apiFileNode !== null)
        || (preg_match('/##?\s*(api reference|api docs|endpoints)/i', $readmeContent) === 1);

    $contextFileNode = tree_find_files($tree, ['CLAUDE.md', 'AGENTS.md', '.cursorrules'])
        ?: null;
    $hasAiContextFile = (!empty($contextFileNode))
        || (ai_find_path_match($tree, '/\.github\/copilot-instructions\.md$/i') !== null);

    $manifestNames = [
        'package.json', 'composer.json', 'requirements.txt', 'Pipfile', 'pyproject.toml',
        'Gemfile', 'go.mod', 'pom.xml', 'build.gradle', 'Cargo.toml',
    ];
    $hasDependencyManifest = !empty(tree_find_files($tree, $manifestNames));

    $filteredTree = array_values(array_filter($tree, function ($node) {
        $path = strtolower((string) ($node['path'] ?? ''));
        return strpos($path, '/vendor/') === false
            && strpos($path, '/node_modules/') === false
            && strpos($path, '/dist/') === false
            && strpos($path, '/build/') === false;
    }));

    $sampleNodes = tree_files_by_extensions(
        $filteredTree,
        ['php', 'js', 'ts', 'jsx', 'tsx', 'py', 'java', 'go', 'rb'],
        6
    );

    $shortNameMatches = 0;
    $totalDeclarations = 0;
    $oversizedFileCount = 0;
    $mixedIndentationFound = false;
    $functionCount = 0;
    $documentedFunctionCount = 0;
    $sourceDirs = [];

    foreach ($tree as $node) {
        if (($node['type'] ?? '') !== 'blob') {
            continue;
        }
        $path = (string) ($node['path'] ?? '');
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['php', 'js', 'ts', 'jsx', 'tsx', 'py', 'java', 'go', 'rb'], true)) {
            continue;
        }
        if (strpos(strtolower($path), '/vendor/') !== false || strpos(strtolower($path), '/node_modules/') !== false) {
            continue;
        }
        $segments = explode('/', $path);
        $sourceDirs[] = count($segments) > 1 ? $segments[0] : '';
    }
    $sourceFileTotal = count($sourceDirs);
    $distinctSourceDirs = count(array_unique(array_filter($sourceDirs, fn($d) => $d !== '')));
    $monolithicLayout = $sourceFileTotal > 15 && $distinctSourceDirs <= 1;

    foreach ($sampleNodes as $node) {
        $path = (string) ($node['path'] ?? '');
        $content = github_get_file_content($owner, $repo, $path, $pat);
        if (!is_string($content) || $content === '') {
            continue;
        }
        $content = substr($content, 0, 20000);
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $lineCount = count($lines);

        if (preg_match_all('/(?:\$|(?:var|let|const)\s+)([A-Za-z_][A-Za-z0-9_]{0,1})\s*=(?!=)/', $content, $m1) > 0) {
            foreach ($m1[1] as $name) {
                $totalDeclarations++;
                if (!in_array(strtolower($name), ['i', 'j', 'k', 'e', 'x', 'y', '_'], true)) {
                    $shortNameMatches++;
                }
            }
        }

        $funcMatches = preg_match_all('/^\s*(?:public |private |protected |static )*\s*(?:function|def)\s+\w+\s*\(/mi', $content, $mf);
        $functionCount += $funcMatches;

        if ($funcMatches > 0) {
            foreach ($mf[0] as $funcLine) {
                $pos = strpos($content, $funcLine);
                if ($pos === false) {
                    continue;
                }
                $before = substr($content, max(0, $pos - 200), 200);
                if (preg_match('/(\/\*\*|\*\/|\/\/|#\s*\w|\'\'\'|"""|@param|@return)/', $before) === 1) {
                    $documentedFunctionCount++;
                }
            }
        }

        if ($lineCount > 400 && $funcMatches < 3) {
            $oversizedFileCount++;
        }

        $hasTabIndent = preg_match('/^\t/m', $content) === 1;
        $hasSpaceIndent = preg_match('/^ {2,}/m', $content) === 1;
        if ($hasTabIndent && $hasSpaceIndent) {
            $mixedIndentationFound = true;
        }
    }

    $snapshot = [
        'has_readme' => $readmeContent !== '',
        'readme_word_count' => $readmeWordCount,
        'has_usage_examples' => $hasUsageExamples,
        'has_api_docs' => $hasApiDocs,
        'has_ai_context_file' => $hasAiContextFile,
        'has_dependency_manifest' => $hasDependencyManifest,
        'sample_file_count' => count($sampleNodes),
        'short_name_ratio' => $totalDeclarations > 0 ? $shortNameMatches / $totalDeclarations : 0.0,
        'oversized_file_count' => $oversizedFileCount,
        'monolithic_layout' => $monolithicLayout,
        'mixed_indentation_found' => $mixedIndentationFound,
        'sample_function_count' => $functionCount,
        'docstring_ratio' => $functionCount > 0 ? $documentedFunctionCount / $functionCount : 1.0,
    ];

    $cache[$cacheKey] = $snapshot;
    return $snapshot;
}

function ai_find_path_match(array $tree, string $regex): ?array
{
    foreach ($tree as $node) {
        if (($node['type'] ?? '') !== 'blob') {
            continue;
        }
        $path = (string) ($node['path'] ?? '');
        if (preg_match($regex, $path) === 1) {
            return $node;
        }
    }

    return null;
}

function ai_readiness_finding(string $title, string $summary, string $description, string $severity): array
{
    return [
        'category' => 'AI',
        'title' => $title . ': ' . $summary,
        'description' => $description,
        'severity' => $severity,
    ];
}
