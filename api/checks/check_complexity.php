<?php

declare(strict_types=1);

/**
 * Check 4: Code Complexity & Maintainability
 * Measures file length, function length, and nesting depth across source files.
 */
function check_complexity(string $owner, string $repo, string $pat, array $sourceFiles): array
{
    $findings = [];

    $longFileThreshold     = 500;
    $longFunctionThreshold = 60;
    $deepNestThreshold     = 5;

    $braceLanguages = ['php', 'js', 'ts', 'tsx', 'jsx', 'java', 'cs', 'go', 'swift', 'cpp', 'c'];

    foreach ($sourceFiles as $fileNode) {
        $path    = $fileNode['path'];
        $ext     = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $content = github_get_file_content($owner, $repo, $path, $pat);
        if ($content === null) {
            continue;
        }

        $lines     = explode("\n", $content);
        $lineCount = count($lines);

        if ($lineCount > $longFileThreshold) {
            $findings[] = [
                'category'    => 'Complexity',
                'title'       => "Long file: {$path} ({$lineCount} lines)",
                'description' => "`{$path}` has {$lineCount} lines, exceeding the {$longFileThreshold}-line guideline. Consider splitting into smaller, single-responsibility modules.",
                'severity'    => $lineCount > 1000 ? 'Medium' : 'Low',
            ];
        }

        if (in_array($ext, $braceLanguages, true)) {
            $longFunctions = find_long_functions($lines, $longFunctionThreshold);
            foreach (array_slice($longFunctions, 0, 3) as $fn) {
                $findings[] = [
                    'category'    => 'Complexity',
                    'title'       => "Long function in {$path} near line {$fn['line']} ({$fn['length']} lines)",
                    'description' => "A function starting near line {$fn['line']} in `{$path}` spans {$fn['length']} lines. Functions over {$longFunctionThreshold} lines are harder to test. Break it into smaller helpers.",
                    'severity'    => 'Low',
                ];
            }

            $maxDepth = max_nesting_depth($lines);
            if ($maxDepth >= $deepNestThreshold) {
                $findings[] = [
                    'category'    => 'Complexity',
                    'title'       => "Deep nesting in {$path} (depth {$maxDepth})",
                    'description' => "Code in `{$path}` reaches a brace-nesting depth of {$maxDepth}. Deep nesting is hard to read and test. Extract nested blocks into named functions or use early returns.",
                    'severity'    => 'Low',
                ];
            }
        }
    }

    $recommendations = [];
    if (!empty($findings)) {
        $recommendations[] = [
            'recommendation_text' => 'Refactor long files and functions. Target under 300 lines per file and 40 lines per function. Add a complexity linter (PHP_CodeSniffer, ESLint complexity rule) to CI.',
            'priority'            => 'Medium',
        ];
    } else {
        $recommendations[] = [
            'recommendation_text' => 'File and function sizes look manageable in sampled files. Add cyclomatic complexity checks to CI to prevent future growth.',
            'priority'            => 'Low',
        ];
    }

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}

/**
 * Heuristically find functions longer than $threshold lines using brace counting.
 */
function find_long_functions(array $lines, int $threshold): array
{
    $long   = [];
    $depth  = 0;
    $fnStart = null;

    foreach ($lines as $i => $line) {
        $opens  = substr_count($line, '{');
        $closes = substr_count($line, '}');

        if ($depth === 0 && $opens > 0) {
            if (preg_match('/(?:function|func|def|void |public |private |protected |static )\s*\w+\s*\(/', $line)) {
                $fnStart = $i + 1;
            }
        }

        $depth += $opens - $closes;

        if ($depth <= 0 && $fnStart !== null) {
            $length = ($i + 1) - $fnStart;
            if ($length > $threshold) {
                $long[] = ['line' => $fnStart, 'length' => $length];
            }
            $fnStart = null;
            $depth   = 0;
        }
        if ($depth < 0) {
            $depth = 0;
        }
    }

    return $long;
}

/**
 * Return the maximum brace-nesting depth in a file (rough approximation).
 */
function max_nesting_depth(array $lines): int
{
    $maxDepth = 0;
    $depth    = 0;

    foreach ($lines as $line) {
        // Strip inline strings and line comments before counting braces
        $stripped = preg_replace('/(["\']).*?\1/', '""', $line) ?? $line;
        $stripped = preg_replace('#//.*$|/\*.*?\*/#', '', $stripped);
        $depth   += substr_count($stripped, '{') - substr_count($stripped, '}');
        if ($depth < 0) {
            $depth = 0;
        }
        if ($depth > $maxDepth) {
            $maxDepth = $depth;
        }
    }

    return $maxDepth;
}
