<?php

declare(strict_types=1);

/**
 * SonarQube-style rules (#21-#30) orchestrated over existing repository checks.
 */
function check_sonarqube_rule(
    string $owner,
    string $repo,
    string $pat,
    array $tree,
    array $languages,
    array $sourceFiles,
    string $ruleId
): array {
    $rules = [
        'sonar_bugs_reliability' => fn() => check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_cyclomatic_max'),
        'sonar_code_smells' => fn() => check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_cognitive_avg'),
        'sonar_duplication_detection' => fn() => check_duplication($owner, $repo, $pat, $sourceFiles),
        'sonar_complexity_limits' => fn() => sonar_merge_outputs(
            check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_cyclomatic_max'),
            check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_cognitive_max'),
            check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_nesting_depth_max')
        ),
        'sonar_size_control' => fn() => sonar_merge_outputs(
            check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_function_size_max'),
            check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_class_size_max')
        ),
        'sonar_naming_readability' => fn() => check_file_summary($tree, $languages),
        'sonar_dead_code' => fn() => check_todos($owner, $repo, $pat, $sourceFiles),
        'sonar_error_handling' => fn() => check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_nesting_depth_avg'),
        'sonar_technical_debt' => fn() => sonar_merge_outputs(
            check_todos($owner, $repo, $pat, $sourceFiles),
            check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_function_size_avg')
        ),
        'sonar_quality_gate_summary' => fn() => sonar_merge_outputs(
            check_duplication($owner, $repo, $pat, $sourceFiles),
            check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_cyclomatic_avg'),
            check_complexity_metric($owner, $repo, $pat, $sourceFiles, 'complexity_cognitive_avg')
        ),
    ];

    if (!isset($rules[$ruleId])) {
        return ['findings' => [], 'recommendations' => [], 'skills' => []];
    }

    return $rules[$ruleId]();
}

function sonar_merge_outputs(array ...$outputs): array
{
    $merged = ['findings' => [], 'recommendations' => [], 'skills' => []];

    foreach ($outputs as $out) {
        $merged['findings'] = array_merge($merged['findings'], $out['findings'] ?? []);
        $merged['recommendations'] = array_merge($merged['recommendations'], $out['recommendations'] ?? []);
        $merged['skills'] = array_merge($merged['skills'], $out['skills'] ?? []);
    }

    return $merged;
}
