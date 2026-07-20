<?php

declare(strict_types=1);

function check_performance(string $owner, string $repo, string $pat, array $tree, array $languages, array $sourceFiles, string $ruleId): array
{
    $rules = [
        'performance_nested_loops' => fn() => check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_nesting_depth_max'),
        'performance_expensive_operations' => fn() => check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_cyclomatic_max'),
        'performance_n_plus_one_patterns' => fn() => check_duplication($owner, $repo, $pat, $sourceFiles),
        'performance_repeated_api_calls' => fn() => check_file_summary($tree, $languages),
        'performance_blocking_operations' => fn() => check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_function_size_max'),
        'performance_unbounded_queries' => fn() => check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_class_size_max'),
        'performance_large_payloads' => fn() => check_file_summary($tree, $languages),
        'performance_cache_miss_risk' => fn() => check_todos($owner, $repo, $pat, $sourceFiles),
        'performance_sync_io_hotspots' => fn() => check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_nesting_depth_avg'),
        'performance_build_runtime_cost' => fn() => check_ci_cd_integrity($owner, $repo, $pat, $tree),
    ];

    $titles = [
        'performance_nested_loops' => 'Performance Nested Loops and Deep Iterations',
        'performance_expensive_operations' => 'Performance Expensive Operation Hotspots',
        'performance_n_plus_one_patterns' => 'Performance N+1 and Repeated Data Access Patterns',
        'performance_repeated_api_calls' => 'Performance Repeated External API Call Patterns',
        'performance_blocking_operations' => 'Performance Blocking Operation Risks',
        'performance_unbounded_queries' => 'Performance Unbounded Query and Scan Risks',
        'performance_large_payloads' => 'Performance Large Payload and Serialization Costs',
        'performance_cache_miss_risk' => 'Performance Cache Strategy and Miss Risks',
        'performance_sync_io_hotspots' => 'Performance Synchronous I/O Hotspots',
        'performance_build_runtime_cost' => 'Performance Build and Runtime Efficiency Controls',
    ];

    $recommendations = [
        'performance_nested_loops' => 'Flatten nested loops, short-circuit early, and break heavy iterations into focused helper units.',
        'performance_expensive_operations' => 'Refactor expensive decision paths and avoid repeated high-cost logic in hot execution flows.',
        'performance_n_plus_one_patterns' => 'Consolidate repeated data access and use batching to avoid N+1-style query/API patterns.',
        'performance_repeated_api_calls' => 'Deduplicate repeated external calls and introduce memoization/caching for stable lookups.',
        'performance_blocking_operations' => 'Move blocking work off request-critical paths and use async/background processing where possible.',
        'performance_unbounded_queries' => 'Enforce pagination and bounded scans to keep query and traversal cost predictable.',
        'performance_large_payloads' => 'Reduce payload size, compress where appropriate, and trim unused fields in transport contracts.',
        'performance_cache_miss_risk' => 'Introduce cache keys and cache invalidation policy for repeated expensive computations.',
        'performance_sync_io_hotspots' => 'Minimize synchronous I/O in loops and aggregate reads/writes into fewer operations.',
        'performance_build_runtime_cost' => 'Optimize build and runtime steps with deterministic CI checks and performance budgets.',
    ];

    if (!isset($rules[$ruleId])) {
        return ['findings' => [], 'recommendations' => [], 'skills' => []];
    }

    $base = $rules[$ruleId]();
    return performance_adapt_result(
        $titles[$ruleId],
        $recommendations[$ruleId],
        $base
    );
}

function performance_adapt_result(string $title, string $recommendation, array $base): array
{
    $findings = [];
    foreach (($base['findings'] ?? []) as $finding) {
        $findings[] = [
            'category' => 'Performance',
            'title' => $title,
            'description' => (string) ($finding['description'] ?? 'Potential performance risk identified.'),
            'severity' => (string) ($finding['severity'] ?? 'Low'),
        ];
    }

    $recs = [];
    if (!empty($findings)) {
        $recs[] = [
            'recommendation_text' => $recommendation,
            'priority' => 'Medium',
        ];
    }

    return ['findings' => $findings, 'recommendations' => $recs, 'skills' => []];
}
