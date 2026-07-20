<?php

declare(strict_types=1);

function check_clean_code(string $owner, string $repo, string $pat, array $tree, array $languages, array $sourceFiles, string $principleId): array
{
    return match ($principleId) {
        'clean_code_solid' => clean_code_solid($owner, $repo, $pat, $sourceFiles),
        'clean_code_dry' => clean_code_dry($owner, $repo, $pat, $sourceFiles),
        'clean_code_kiss' => clean_code_kiss($owner, $repo, $pat, $sourceFiles),
        'clean_code_yagni' => clean_code_yagni($owner, $repo, $pat, $sourceFiles),
        'clean_code_single_responsibility' => clean_code_single_responsibility($owner, $repo, $pat, $sourceFiles),
        'clean_code_separation_of_concerns' => clean_code_separation_of_concerns($owner, $repo, $pat, $sourceFiles),
        'clean_code_meaningful_names' => clean_code_meaningful_names($owner, $repo, $pat, $sourceFiles),
        'clean_code_small_functions' => clean_code_small_functions($owner, $repo, $pat, $sourceFiles),
        'clean_code_formatting' => clean_code_formatting($owner, $repo, $pat, $sourceFiles),
        'clean_code_error_handling' => clean_code_error_handling($owner, $repo, $pat, $sourceFiles),
        default => ['findings' => [], 'recommendations' => [], 'skills' => []],
    };
}

function clean_code_principle_title(string $principleId): string
{
    return match ($principleId) {
        'clean_code_solid' => 'SOLID Principles',
        'clean_code_dry' => 'DRY Principle',
        'clean_code_kiss' => 'KISS Principle',
        'clean_code_yagni' => 'YAGNI Principle',
        'clean_code_single_responsibility' => 'Single Responsibility',
        'clean_code_separation_of_concerns' => 'Separation of Concerns',
        'clean_code_meaningful_names' => 'Meaningful Naming',
        'clean_code_small_functions' => 'Small Functions',
        'clean_code_formatting' => 'Consistent Formatting',
        'clean_code_error_handling' => 'Explicit Error Handling',
        default => 'Clean Code',
    };
}

function clean_code_solid(string $owner, string $repo, string $pat, array $sourceFiles): array
{
    $title = clean_code_principle_title('clean_code_solid');
    $findings = [];

    foreach (clean_code_sample_files($sourceFiles, 12) as $fileNode) {
        $analysis = clean_code_analyze_source_file($owner, $repo, $pat, (string) ($fileNode['path'] ?? ''));
        if ($analysis === null) {
            continue;
        }

        if ($analysis['line_count'] >= 400 || $analysis['function_count'] >= 8 || $analysis['class_count'] >= 2) {
            $findings[] = [
                'category' => 'Clean Code',
                'title' => $title,
                'description' => 'This file carries multiple responsibilities or abstractions. Large modules make it harder to preserve single-purpose design, keep interfaces small, and change behavior safely.',
                'severity' => $analysis['line_count'] >= 800 || $analysis['function_count'] >= 15 ? 'Medium' : 'Low',
            ];
        }
    }

    $recommendations = !empty($findings)
        ? [[
            'recommendation_text' => 'Split large modules into smaller classes or services, and keep each abstraction focused on one reason to change.',
            'priority' => 'Medium',
        ]]
        : [[
            'recommendation_text' => 'No obvious SOLID pressure detected in sampled files. Keep interfaces narrow and modules focused as the codebase grows.',
            'priority' => 'Low',
        ]];

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}

function clean_code_dry(string $owner, string $repo, string $pat, array $sourceFiles): array
{
    $title = clean_code_principle_title('clean_code_dry');
    $duplicateCheck = check_duplication($owner, $repo, $pat, $sourceFiles);
    $findings = [];

    foreach (array_slice($duplicateCheck['findings'] ?? [], 0, 5) as $finding) {
        $findings[] = [
            'category' => 'Clean Code',
            'title' => $title,
            'description' => (string) ($finding['description'] ?? 'Repeated logic should be extracted into a shared helper.'),
            'severity' => (string) ($finding['severity'] ?? 'Low'),
        ];
    }

    $recommendations = !empty($findings)
        ? [[
            'recommendation_text' => 'Extract repeated logic into shared helpers or reusable modules, and add copy-paste detection to CI.',
            'priority' => 'Medium',
        ]]
        : [[
            'recommendation_text' => 'No significant duplication found in sampled files. Keep copy-paste checks enabled to preserve DRY discipline.',
            'priority' => 'Low',
        ]];

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}

function clean_code_kiss(string $owner, string $repo, string $pat, array $sourceFiles): array
{
    $title = clean_code_principle_title('clean_code_kiss');
    $complexityCheck = check_complexity($owner, $repo, $pat, $sourceFiles);
    $findings = [];

    foreach (array_slice($complexityCheck['findings'] ?? [], 0, 5) as $finding) {
        $findings[] = [
            'category' => 'Clean Code',
            'title' => $title,
            'description' => (string) ($finding['description'] ?? 'Simplify the logic and reduce branching.'),
            'severity' => (string) ($finding['severity'] ?? 'Low'),
        ];
    }

    $recommendations = !empty($findings)
        ? [[
            'recommendation_text' => 'Break complex routines into smaller helpers, flatten nested logic, and prefer early returns over deep branching.',
            'priority' => 'Medium',
        ]]
        : [[
            'recommendation_text' => 'No major KISS issues found in sampled files. Keep routines short and direct as features evolve.',
            'priority' => 'Low',
        ]];

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}

function clean_code_yagni(string $owner, string $repo, string $pat, array $sourceFiles): array
{
    $title = clean_code_principle_title('clean_code_yagni');
    $todoCheck = check_todos($owner, $repo, $pat, $sourceFiles);
    $findings = [];

    foreach (array_slice($todoCheck['findings'] ?? [], 0, 5) as $finding) {
        $findings[] = [
            'category' => 'Clean Code',
            'title' => $title,
            'description' => (string) ($finding['description'] ?? 'Remove speculative or unfinished code paths that are not needed yet.'),
            'severity' => (string) ($finding['severity'] ?? 'Low'),
        ];
    }

    $recommendations = !empty($findings)
        ? [[
            'recommendation_text' => 'Turn TODO/FIXME items into tracked issues and remove speculative code that is not needed for the current scope.',
            'priority' => 'Medium',
        ]]
        : [[
            'recommendation_text' => 'No obvious YAGNI signals found in sampled files. Keep unfinished or optional ideas out of the main path until they are needed.',
            'priority' => 'Low',
        ]];

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}

function clean_code_single_responsibility(string $owner, string $repo, string $pat, array $sourceFiles): array
{
    $title = clean_code_principle_title('clean_code_single_responsibility');
    $findings = [];

    foreach (clean_code_sample_files($sourceFiles, 12) as $fileNode) {
        $analysis = clean_code_analyze_source_file($owner, $repo, $pat, (string) ($fileNode['path'] ?? ''));
        if ($analysis === null) {
            continue;
        }

        if ($analysis['line_count'] >= 300 || $analysis['function_count'] >= 10 || $analysis['class_count'] >= 2) {
            $findings[] = [
                'category' => 'Clean Code',
                'title' => $title,
                'description' => 'This file appears to do too much at once. When one module owns data access, control flow, and rendering or orchestration, it becomes harder to test and change safely.',
                'severity' => $analysis['line_count'] >= 700 ? 'Medium' : 'Low',
            ];
        }
    }

    $recommendations = !empty($findings)
        ? [[
            'recommendation_text' => 'Move data access, business logic, and presentation concerns into separate units with clear boundaries.',
            'priority' => 'Medium',
        ]]
        : [[
            'recommendation_text' => 'No strong single-responsibility violations found in sampled files. Keep each module focused on one job.',
            'priority' => 'Low',
        ]];

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}

function clean_code_separation_of_concerns(string $owner, string $repo, string $pat, array $sourceFiles): array
{
    $title = clean_code_principle_title('clean_code_separation_of_concerns');
    $findings = [];

    foreach (clean_code_sample_files($sourceFiles, 12) as $fileNode) {
        $path = (string) ($fileNode['path'] ?? '');
        $analysis = clean_code_analyze_source_file($owner, $repo, $pat, $path);
        if ($analysis === null) {
            continue;
        }

        $content = $analysis['content'];
        $hasPresentation = preg_match('/<\s*(html|div|form|table|section|main|article|span)\b/i', $content) === 1;
        $hasBackendLogic = preg_match('/\b(PDO|mysqli|curl_|json_encode|json_decode|SELECT|INSERT|UPDATE|DELETE|require_once|include_once)\b/i', $content) === 1;
        $hasMultipleBlocks = $analysis['function_count'] >= 8 || $analysis['class_count'] >= 2;

        if (($hasPresentation && $hasBackendLogic) || ($hasPresentation && $hasMultipleBlocks)) {
            $findings[] = [
                'category' => 'Clean Code',
                'title' => $title,
                'description' => 'This file mixes presentation and application logic. Keeping UI, data access, and orchestration separate makes the code easier to reason about and safer to evolve.',
                'severity' => $analysis['line_count'] >= 600 ? 'Medium' : 'Low',
            ];
        }
    }

    $recommendations = !empty($findings)
        ? [[
            'recommendation_text' => 'Move rendering, persistence, and business rules into separate layers or services so each concern has a clear owner.',
            'priority' => 'Medium',
        ]]
        : [[
            'recommendation_text' => 'No major separation-of-concerns issues found in sampled files. Keep layers distinct as the repository evolves.',
            'priority' => 'Low',
        ]];

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}

function clean_code_meaningful_names(string $owner, string $repo, string $pat, array $sourceFiles): array
{
    $title = clean_code_principle_title('clean_code_meaningful_names');
    $findings = [];
    $genericNames = ['temp', 'data', 'value', 'item', 'items', 'result', 'obj', 'thing', 'things', 'foo', 'bar', 'baz', 'x', 'y'];

    foreach (clean_code_sample_files($sourceFiles, 12) as $fileNode) {
        $analysis = clean_code_analyze_source_file($owner, $repo, $pat, (string) ($fileNode['path'] ?? ''));
        if ($analysis === null) {
            continue;
        }

        $content = strtolower($analysis['content']);
        $hits = 0;
        foreach ($genericNames as $name) {
            $hits += preg_match_all('/\b' . preg_quote($name, '/') . '\b/', $content) ?: 0;
        }

        if ($hits >= 8 && $analysis['line_count'] >= 80) {
            $findings[] = [
                'category' => 'Clean Code',
                'title' => $title,
                'description' => 'This file uses many generic identifiers. Descriptive names make intent obvious and reduce the need for comments or extra context.',
                'severity' => $hits >= 20 ? 'Medium' : 'Low',
            ];
        }
    }

    $recommendations = !empty($findings)
        ? [[
            'recommendation_text' => 'Replace generic identifiers with domain-specific names that explain purpose, scope, and ownership.',
            'priority' => 'Medium',
        ]]
        : [[
            'recommendation_text' => 'Meaningful naming looks acceptable in sampled files. Keep identifiers specific and expressive as the code grows.',
            'priority' => 'Low',
        ]];

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}

function clean_code_small_functions(string $owner, string $repo, string $pat, array $sourceFiles): array
{
    $title = clean_code_principle_title('clean_code_small_functions');
    $complexityCheck = check_complexity($owner, $repo, $pat, $sourceFiles);
    $findings = [];

    foreach (array_slice($complexityCheck['findings'] ?? [], 0, 5) as $finding) {
        $title = (string) ($finding['title'] ?? 'Complex routine');
        $titleLower = strtolower($title);
        if (str_contains($titleLower, 'long function') || str_contains($titleLower, 'deep nesting')) {
            $findings[] = [
                'category' => 'Clean Code',
                'title' => $title,
                'description' => (string) ($finding['description'] ?? 'Break the routine into smaller, single-purpose helpers.'),
                'severity' => (string) ($finding['severity'] ?? 'Low'),
            ];
        }
    }

    $recommendations = !empty($findings)
        ? [[
            'recommendation_text' => 'Keep functions short, add guard clauses, and split multi-step logic into focused helpers.',
            'priority' => 'Medium',
        ]]
        : [[
            'recommendation_text' => 'Function sizes look manageable in sampled files. Preserve that by keeping new logic narrowly scoped.',
            'priority' => 'Low',
        ]];

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}

function clean_code_formatting(string $owner, string $repo, string $pat, array $sourceFiles): array
{
    $title = clean_code_principle_title('clean_code_formatting');
    $findings = [];

    foreach (clean_code_sample_files($sourceFiles, 12) as $fileNode) {
        $analysis = clean_code_analyze_source_file($owner, $repo, $pat, (string) ($fileNode['path'] ?? ''));
        if ($analysis === null) {
            continue;
        }

        $content = $analysis['content'];
        $longLines = 0;
        $trailingWhitespace = preg_match_all('/[ \t]+$/m', $content) ?: 0;
        foreach (explode("\n", $content) as $line) {
            if (strlen($line) > 120) {
                $longLines++;
            }
        }

        if ($longLines >= 6 || $trailingWhitespace >= 10) {
            $findings[] = [
                'category' => 'Clean Code',
                'title' => $title,
                'description' => 'Long lines and whitespace noise make code harder to scan and review. Consistent formatting supports readability.',
                'severity' => $longLines >= 20 ? 'Medium' : 'Low',
            ];
        }
    }

    $recommendations = !empty($findings)
        ? [[
            'recommendation_text' => 'Use a formatter and line-length conventions to keep code visually consistent and easy to scan.',
            'priority' => 'Medium',
        ]]
        : [[
            'recommendation_text' => 'Formatting looks consistent in sampled files. Keep formatting automated to prevent drift.',
            'priority' => 'Low',
        ]];

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}

function clean_code_error_handling(string $owner, string $repo, string $pat, array $sourceFiles): array
{
    $title = clean_code_principle_title('clean_code_error_handling');
    $findings = [];

    foreach (clean_code_sample_files($sourceFiles, 12) as $fileNode) {
        $analysis = clean_code_analyze_source_file($owner, $repo, $pat, (string) ($fileNode['path'] ?? ''));
        if ($analysis === null) {
            continue;
        }

        $content = $analysis['content'];
        $hasEmptyCatch = preg_match('/catch\s*\([^\)]*\)\s*\{\s*\}/i', $content) === 1;
        $hasSuppressedErrors = preg_match('/@\s*[A-Za-z_\\\\][A-Za-z0-9_\\\\]*/', $content) === 1;
        $hasCatchWithoutAction = preg_match('/catch\s*\([^\)]*\)\s*\{[^{}]{0,80}\}/is', $content) === 1 && preg_match('/(throw|log|error_log|console\.error|report)/i', $content) !== 1;

        if ($hasEmptyCatch || $hasSuppressedErrors || $hasCatchWithoutAction) {
            $findings[] = [
                'category' => 'Clean Code',
                'title' => $title,
                'description' => 'This file appears to suppress or ignore failures. Clean code handles errors explicitly so failures are visible and actionable.',
                'severity' => 'Low',
            ];
        }
    }

    $recommendations = !empty($findings)
        ? [[
            'recommendation_text' => 'Avoid silent failure paths, log or rethrow exceptions, and make error handling visible in the control flow.',
            'priority' => 'Medium',
        ]]
        : [[
            'recommendation_text' => 'Error handling looks explicit in sampled files. Keep failures visible and actionable.',
            'priority' => 'Low',
        ]];

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}

function clean_code_sample_files(array $sourceFiles, int $limit): array
{
    $sample = [];
    foreach ($sourceFiles as $fileNode) {
        if (!is_array($fileNode) || empty($fileNode['path'])) {
            continue;
        }
        $sample[] = $fileNode;
        if (count($sample) >= $limit) {
            break;
        }
    }

    return $sample;
}

function clean_code_analyze_source_file(string $owner, string $repo, string $pat, string $path): ?array
{
    if ($path === '') {
        return null;
    }

    $content = github_get_file_content($owner, $repo, $path, $pat);
    if ($content === null) {
        return null;
    }

    $lineCount = substr_count($content, "\n") + 1;
    $functionCount = preg_match_all('/\bfunction\s+[A-Za-z_][A-Za-z0-9_]*\s*\(/i', $content) ?: 0;
    $classCount = preg_match_all('/\bclass\s+[A-Za-z_][A-Za-z0-9_]*\b/i', $content) ?: 0;

    return [
        'path' => $path,
        'content' => $content,
        'line_count' => $lineCount,
        'function_count' => $functionCount,
        'class_count' => $classCount,
    ];
}