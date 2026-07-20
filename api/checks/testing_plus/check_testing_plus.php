<?php

declare(strict_types=1);

function check_testing_plus(string $owner, string $repo, string $pat, array $tree, array $languages, array $sourceFiles, string $ruleId): array
{
    $rules = [
        'testing_first_principles' => fn() => check_file_summary($tree, $languages),
        'testing_aaa_pattern' => fn() => check_file_summary($tree, $languages),
        'testing_test_data_management' => fn() => check_todos($owner, $repo, $pat, $sourceFiles),
        'testing_flaky_test_risk' => fn() => check_todos($owner, $repo, $pat, $sourceFiles),
        'testing_boundary_case_coverage' => fn() => check_git_history($owner, $repo, $pat),
        'testing_contract_validation' => fn() => check_security_config($owner, $repo, $pat, $tree),
        'testing_security_paths' => fn() => check_insecure_design($owner, $repo, $pat, $sourceFiles),
        'testing_performance_paths' => fn() => check_file_summary($tree, $languages),
        'testing_ci_gate_readiness' => fn() => check_ci_cd_integrity($owner, $repo, $pat, $tree),
        'testing_suite_maintainability' => fn() => check_todos($owner, $repo, $pat, $sourceFiles),
    ];

    $titles = [
        'testing_first_principles' => 'Documentation README Completeness and Clarity',
        'testing_aaa_pattern' => 'Documentation Installation and Usage Guide Quality',
        'testing_test_data_management' => 'Documentation API Reference and Endpoint Notes',
        'testing_flaky_test_risk' => 'Documentation Architecture and Design Decision Notes',
        'testing_boundary_case_coverage' => 'Documentation Changelog and Release Notes Hygiene',
        'testing_contract_validation' => 'Documentation Configuration and Environment Guide',
        'testing_security_paths' => 'Documentation Security and Compliance Notes',
        'testing_performance_paths' => 'Documentation Troubleshooting and FAQ Coverage',
        'testing_ci_gate_readiness' => 'Documentation Contribution and Workflow Guidelines',
        'testing_suite_maintainability' => 'Documentation Freshness and Maintainability',
    ];

    $recommendations = [
        'testing_first_principles' => 'Add a complete README covering project purpose, setup, and core usage scenarios.',
        'testing_aaa_pattern' => 'Provide clear installation and quick-start instructions with working command examples.',
        'testing_test_data_management' => 'Document API endpoints, request/response examples, and important constraints.',
        'testing_flaky_test_risk' => 'Capture architecture decisions and rationale so future changes remain traceable.',
        'testing_boundary_case_coverage' => 'Keep changelog and release notes updated for every meaningful release.',
        'testing_contract_validation' => 'Document required environment variables and configuration defaults.',
        'testing_security_paths' => 'Include security practices, secret-handling guidance, and compliance notes.',
        'testing_performance_paths' => 'Add troubleshooting steps and FAQ entries for common operational issues.',
        'testing_ci_gate_readiness' => 'Document contribution workflow, review expectations, and CI checks.',
        'testing_suite_maintainability' => 'Review documentation regularly to remove stale instructions and dead references.',
    ];

    if (!isset($rules[$ruleId])) {
        return ['findings' => [], 'recommendations' => [], 'skills' => []];
    }

    $base = $rules[$ruleId]();
    return testing_plus_adapt_result(
        $titles[$ruleId],
        $recommendations[$ruleId],
        $base
    );
}

function testing_plus_adapt_result(string $title, string $recommendation, array $base): array
{
    $findings = [];
    foreach (($base['findings'] ?? []) as $finding) {
        $findings[] = [
            'category' => 'Documentation',
            'title' => $title,
            'description' => (string) ($finding['description'] ?? 'Potential documentation quality issue identified.'),
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
