<?php

declare(strict_types=1);

/**
 * Complexity checks suite (#11-#20)
 * Heuristic metrics over source files: cyclomatic/cognitive complexity,
 * function size, class size, and nesting depth.
 */
function check_complexity_metric(string $owner, string $repo, string $pat, array $sourceFiles, string $metricId): array
{
    $snapshot = complexity_metrics_snapshot($owner, $repo, $pat, $sourceFiles);

    $metrics = [
        'complexity_cyclomatic_avg' => [
            'label' => '#11 Cyclomatic Complexity Average',
            'metric' => 'Cyclomatic Complexity',
            'value' => $snapshot['cyclomatic_avg'],
            'max' => $snapshot['cyclomatic_max'],
            'threshold' => 'avg <= 10',
            'limit' => 10.0,
            'recommendation' => 'Reduce branching and split large decision-heavy methods to keep average cyclomatic complexity <= 10.',
        ],
        'complexity_cyclomatic_max' => [
            'label' => '#12 Cyclomatic Complexity Maximum',
            'metric' => 'Cyclomatic Complexity',
            'value' => $snapshot['cyclomatic_max'],
            'max' => $snapshot['cyclomatic_max'],
            'threshold' => 'max <= 20',
            'limit' => 20.0,
            'recommendation' => 'Refactor hotspot functions with high branch counts and add guard clauses to bring maximum cyclomatic complexity <= 20.',
        ],
        'complexity_cognitive_avg' => [
            'label' => '#13 Cognitive Complexity Average',
            'metric' => 'Cognitive Complexity',
            'value' => $snapshot['cognitive_avg'],
            'max' => $snapshot['cognitive_max'],
            'threshold' => 'avg <= 15',
            'limit' => 15.0,
            'recommendation' => 'Simplify nested control flow and reduce branching chains to keep average cognitive complexity <= 15.',
        ],
        'complexity_cognitive_max' => [
            'label' => '#14 Cognitive Complexity Maximum',
            'metric' => 'Cognitive Complexity',
            'value' => $snapshot['cognitive_max'],
            'max' => $snapshot['cognitive_max'],
            'threshold' => 'max <= 25',
            'limit' => 25.0,
            'recommendation' => 'Break down highly nested or condition-heavy hotspots to keep maximum cognitive complexity <= 25.',
        ],
        'complexity_function_size_avg' => [
            'label' => '#15 Function Size Average',
            'metric' => 'Function Size (LOC)',
            'value' => $snapshot['function_size_avg'],
            'max' => $snapshot['function_size_max'],
            'threshold' => 'avg <= 40',
            'limit' => 40.0,
            'recommendation' => 'Extract shared logic and helper methods to keep average function size <= 40 LOC.',
        ],
        'complexity_function_size_max' => [
            'label' => '#16 Function Size Maximum',
            'metric' => 'Function Size (LOC)',
            'value' => $snapshot['function_size_max'],
            'max' => $snapshot['function_size_max'],
            'threshold' => 'max <= 100',
            'limit' => 100.0,
            'recommendation' => 'Split oversized methods into focused units and apply single-responsibility boundaries to keep maximum function size <= 100 LOC.',
        ],
        'complexity_class_size_avg' => [
            'label' => '#17 Class Size Average',
            'metric' => 'Class Size (LOC)',
            'value' => $snapshot['class_size_avg'],
            'max' => $snapshot['class_size_max'],
            'threshold' => 'avg <= 300',
            'limit' => 300.0,
            'recommendation' => 'Partition large classes by responsibility and composition to keep average class size <= 300 LOC.',
        ],
        'complexity_class_size_max' => [
            'label' => '#18 Class Size Maximum',
            'metric' => 'Class Size (LOC)',
            'value' => $snapshot['class_size_max'],
            'max' => $snapshot['class_size_max'],
            'threshold' => 'max <= 800',
            'limit' => 800.0,
            'recommendation' => 'Refactor god classes into cohesive modules to keep maximum class size <= 800 LOC.',
        ],
        'complexity_nesting_depth_avg' => [
            'label' => '#19 Nesting Depth Average',
            'metric' => 'Nesting Depth',
            'value' => $snapshot['nesting_avg'],
            'max' => $snapshot['nesting_max'],
            'threshold' => 'avg <= 3',
            'limit' => 3.0,
            'recommendation' => 'Use early returns and strategy extraction to keep average nesting depth <= 3.',
        ],
        'complexity_nesting_depth_max' => [
            'label' => '#20 Nesting Depth Maximum',
            'metric' => 'Nesting Depth',
            'value' => $snapshot['nesting_max'],
            'max' => $snapshot['nesting_max'],
            'threshold' => 'max <= 6',
            'limit' => 6.0,
            'recommendation' => 'Flatten deeply nested hotspots by splitting nested blocks into named routines and guard clauses (max nesting <= 6).',
        ],
    ];

    if (!isset($metrics[$metricId])) {
        return ['findings' => [], 'recommendations' => [], 'skills' => []];
    }

    $cfg = $metrics[$metricId];
    $value = (float) $cfg['value'];
    $max = (float) $cfg['max'];
    $limit = (float) $cfg['limit'];

    $findings = [];
    $recommendations = [];

    $table = 'Metric | Average | Maximum | Threshold: ' .
        $cfg['metric'] . ' | ' . number_format($value, 2) . ' | ' . number_format($max, 2) . ' | ' . $cfg['threshold'];

    if ($value > $limit) {
        $severity = $value > ($limit * 1.5) ? 'High' : 'Medium';
        $findings[] = [
            'category' => 'Complexity',
            'title' => $cfg['label'] . ' threshold exceeded',
            'description' => $table,
            'severity' => $severity,
        ];
        $recommendations[] = [
            'recommendation_text' => $cfg['recommendation'],
            'priority' => $severity === 'High' ? 'High' : 'Medium',
        ];
    } else {
        $recommendations[] = [
            'recommendation_text' => $cfg['label'] . ' is within threshold (' . $cfg['threshold'] . '). Keep tracking this metric in CI.',
            'priority' => 'Low',
        ];
    }

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}

function complexity_metrics_snapshot(string $owner, string $repo, string $pat, array $sourceFiles): array
{
    static $cache = [];

    $cacheKey = strtolower($owner . '/' . $repo) . ':' . count($sourceFiles);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $functions = [];
    $classes = [];

    foreach ($sourceFiles as $fileNode) {
        $path = (string) ($fileNode['path'] ?? '');
        if ($path === '') {
            continue;
        }

        $content = github_get_file_content($owner, $repo, $path, $pat);
        if ($content === null) {
            continue;
        }

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $depth = 0;

        $inFunction = false;
        $functionBaseDepth = 0;
        $functionSize = 0;
        $functionCyclomatic = 1;
        $functionCognitive = 0;
        $functionNestingMax = 0;

        $inClass = false;
        $classBaseDepth = 0;
        $classSize = 0;

        foreach ($lines as $line) {
            $stripped = preg_replace('/(["\']).*?\1/', '""', $line) ?? $line;
            $stripped = preg_replace('#//.*$|/\*.*?\*/#', '', $stripped) ?? $stripped;

            $opens = substr_count($stripped, '{');
            $closes = substr_count($stripped, '}');

            if (!$inClass && preg_match('/\b(class|interface|struct)\s+[A-Za-z_][A-Za-z0-9_]*/', $stripped) === 1 && $opens > 0) {
                $inClass = true;
                $classBaseDepth = $depth;
                $classSize = 0;
            }

            if (!$inFunction && preg_match('/\b(function|def|func)\b|\b(public|private|protected|static)\b[^;]*\(/i', $stripped) === 1 && $opens > 0) {
                $inFunction = true;
                $functionBaseDepth = $depth;
                $functionSize = 0;
                $functionCyclomatic = 1;
                $functionCognitive = 0;
                $functionNestingMax = 0;
            }

            if ($inClass) {
                $classSize++;
            }

            if ($inFunction) {
                $functionSize++;

                $nesting = max(0, $depth - $functionBaseDepth);
                if ($nesting > $functionNestingMax) {
                    $functionNestingMax = $nesting;
                }

                $branchMatches = preg_match_all('/\b(if|else\s+if|for|foreach|while|case|catch)\b|\&\&|\|\||\?/', $stripped, $m);
                if ($branchMatches === false) {
                    $branchMatches = 0;
                }
                $functionCyclomatic += $branchMatches;

                $cognitiveMatches = preg_match_all('/\b(if|else\s+if|for|foreach|while|switch|catch)\b/', $stripped, $m2);
                if ($cognitiveMatches === false) {
                    $cognitiveMatches = 0;
                }
                $functionCognitive += $cognitiveMatches * (1 + $nesting);
            }

            $depth += $opens - $closes;
            if ($depth < 0) {
                $depth = 0;
            }

            if ($inFunction && $depth <= $functionBaseDepth) {
                $functions[] = [
                    'size' => max(1, $functionSize),
                    'cyclomatic' => max(1, $functionCyclomatic),
                    'cognitive' => max(0, $functionCognitive),
                    'nesting_max' => max(0, $functionNestingMax),
                ];
                $inFunction = false;
            }

            if ($inClass && $depth <= $classBaseDepth) {
                $classes[] = max(1, $classSize);
                $inClass = false;
            }
        }

        if ($inFunction) {
            $functions[] = [
                'size' => max(1, $functionSize),
                'cyclomatic' => max(1, $functionCyclomatic),
                'cognitive' => max(0, $functionCognitive),
                'nesting_max' => max(0, $functionNestingMax),
            ];
        }

        if ($inClass) {
            $classes[] = max(1, $classSize);
        }
    }

    $cyclomatic = array_column($functions, 'cyclomatic');
    $cognitive = array_column($functions, 'cognitive');
    $functionSizes = array_column($functions, 'size');
    $nesting = array_column($functions, 'nesting_max');

    $snapshot = [
        'cyclomatic_avg' => complexity_avg($cyclomatic),
        'cyclomatic_max' => complexity_max($cyclomatic),
        'cognitive_avg' => complexity_avg($cognitive),
        'cognitive_max' => complexity_max($cognitive),
        'function_size_avg' => complexity_avg($functionSizes),
        'function_size_max' => complexity_max($functionSizes),
        'class_size_avg' => complexity_avg($classes),
        'class_size_max' => complexity_max($classes),
        'nesting_avg' => complexity_avg($nesting),
        'nesting_max' => complexity_max($nesting),
    ];

    $cache[$cacheKey] = $snapshot;
    return $snapshot;
}

function complexity_avg(array $values): float
{
    if (empty($values)) {
        return 0.0;
    }
    return array_sum($values) / count($values);
}

function complexity_max(array $values): float
{
    if (empty($values)) {
        return 0.0;
    }
    return (float) max($values);
}