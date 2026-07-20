<?php

declare(strict_types=1);

function check_reliability(string $owner, string $repo, string $pat, array $tree, array $languages, array $sourceFiles, string $ruleId): array
{
    $rules = [
        'reliability_logging_coverage' => fn() => check_logging_monitoring($owner, $repo, $pat, $tree, $sourceFiles),
        'reliability_retry_strategy' => fn() => check_ci_cd_integrity($owner, $repo, $pat, $tree),
        'reliability_timeout_controls' => fn() => check_security_config($owner, $repo, $pat, $tree),
        'reliability_exception_handling' => fn() => check_sonarqube_rule($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'sonar_error_handling'),
        'reliability_null_safety' => fn() => check_sonarqube_rule($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'sonar_bugs_reliability'),
        'reliability_resource_cleanup' => fn() => check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_function_size_avg'),
        'reliability_input_validation' => fn() => check_insecure_design($owner, $repo, $pat, $sourceFiles),
        'reliability_idempotency' => fn() => check_sonarqube_rule($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'sonar_code_smells'),
        'reliability_fallback_paths' => fn() => check_sonarqube_rule($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'sonar_technical_debt'),
        'reliability_observability_alerting' => fn() => check_logging_monitoring($owner, $repo, $pat, $tree, $sourceFiles),
    ];

    $titles = [
        'reliability_logging_coverage' => 'Reliability Logging Coverage and Signal Quality',
        'reliability_retry_strategy' => 'Reliability Retry Strategy and Backoff Safety',
        'reliability_timeout_controls' => 'Reliability Timeout and Circuit Controls',
        'reliability_exception_handling' => 'Reliability Exception Handling Discipline',
        'reliability_null_safety' => 'Reliability Null Safety and Defensive Guards',
        'reliability_resource_cleanup' => 'Reliability Resource Cleanup and Lifecycle Safety',
        'reliability_input_validation' => 'Reliability Input Validation and Sanitization',
        'reliability_idempotency' => 'Reliability Idempotency and Duplicate Request Safety',
        'reliability_fallback_paths' => 'Reliability Fallback and Degradation Paths',
        'reliability_observability_alerting' => 'Reliability Observability and Alerting Readiness',
    ];

    $recommendations = [
        'reliability_logging_coverage' => 'Expand structured logs for failures and key state transitions to improve fault diagnosis.',
        'reliability_retry_strategy' => 'Add bounded retries with exponential backoff and jitter for transient operations.',
        'reliability_timeout_controls' => 'Define explicit timeout values and fail-safe boundaries for external calls.',
        'reliability_exception_handling' => 'Handle errors explicitly and avoid silent failures in critical paths.',
        'reliability_null_safety' => 'Add null guards and precondition checks at API boundaries.',
        'reliability_resource_cleanup' => 'Ensure files, connections, and handles are released deterministically.',
        'reliability_input_validation' => 'Validate and normalize inputs at entry points before executing business logic.',
        'reliability_idempotency' => 'Use idempotency keys or deduplication to avoid duplicate side effects.',
        'reliability_fallback_paths' => 'Introduce fallback behavior for expected dependency failures.',
        'reliability_observability_alerting' => 'Define actionable alerts tied to error-rate and latency thresholds.',
    ];

    if (!isset($rules[$ruleId])) {
        return ['findings' => [], 'recommendations' => [], 'skills' => []];
    }

    $base = $rules[$ruleId]();
    return reliability_adapt_result(
        $titles[$ruleId],
        $recommendations[$ruleId],
        $base
    );
}

function reliability_adapt_result(string $title, string $recommendation, array $base): array
{
    $findings = [];
    foreach (($base['findings'] ?? []) as $finding) {
        $findings[] = [
            'category' => 'Reliability',
            'title' => $title,
            'description' => (string) ($finding['description'] ?? 'Potential reliability risk identified.'),
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
