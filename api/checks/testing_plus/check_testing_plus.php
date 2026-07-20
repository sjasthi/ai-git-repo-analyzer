<?php

declare(strict_types=1);

function check_testing_plus(string $owner, string $repo, string $pat, array $tree, array $languages, array $sourceFiles, string $ruleId): array
{
    $rules = [
        'testing_first_principles' => fn() => check_testing($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'testing_fast_feedback'),
        'testing_aaa_pattern' => fn() => check_testing($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'testing_organization_maintainability'),
        'testing_test_data_management' => fn() => check_testing($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'testing_database_isolation'),
        'testing_flaky_test_risk' => fn() => check_testing($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'testing_regression_coverage'),
        'testing_boundary_case_coverage' => fn() => check_testing($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'testing_error_path_testing'),
        'testing_contract_validation' => fn() => check_testing($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'testing_api_response_validation'),
        'testing_security_paths' => fn() => check_insecure_design($owner, $repo, $pat, $sourceFiles),
        'testing_performance_paths' => fn() => check_testing($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'testing_end_to_end_coverage'),
        'testing_ci_gate_readiness' => fn() => check_testing($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'testing_integration_coverage'),
        'testing_suite_maintainability' => fn() => check_testing($owner, $repo, $pat, $tree, $languages, $sourceFiles, 'testing_unit_coverage'),
    ];

    $titles = [
        'testing_first_principles' => 'Testing FIRST Principle Alignment',
        'testing_aaa_pattern' => 'Testing Arrange-Act-Assert Pattern Discipline',
        'testing_test_data_management' => 'Testing Test Data Management and Isolation',
        'testing_flaky_test_risk' => 'Testing Flaky Test Risk Detection',
        'testing_boundary_case_coverage' => 'Testing Boundary and Negative Path Coverage',
        'testing_contract_validation' => 'Testing API Contract and Response Validation',
        'testing_security_paths' => 'Testing Security-Critical Path Coverage',
        'testing_performance_paths' => 'Testing Performance-Critical Path Coverage',
        'testing_ci_gate_readiness' => 'Testing CI Gate and Execution Reliability',
        'testing_suite_maintainability' => 'Testing Suite Maintainability and Structure',
    ];

    $recommendations = [
        'testing_first_principles' => 'Keep tests fast, isolated, repeatable, and self-validating to improve delivery feedback loops.',
        'testing_aaa_pattern' => 'Structure tests with explicit Arrange, Act, and Assert sections to improve readability.',
        'testing_test_data_management' => 'Use isolated fixtures and deterministic test data setup/teardown strategies.',
        'testing_flaky_test_risk' => 'Stabilize flaky tests by removing timing dependencies and shared mutable state.',
        'testing_boundary_case_coverage' => 'Add boundary and invalid-input scenarios for all critical interfaces.',
        'testing_contract_validation' => 'Validate response shape, status, and schema contracts in API-focused tests.',
        'testing_security_paths' => 'Add tests for auth failure, permission boundaries, and security-sensitive workflows.',
        'testing_performance_paths' => 'Add lightweight performance smoke checks for critical hot paths.',
        'testing_ci_gate_readiness' => 'Run the core suite in CI with merge-blocking rules on test failures.',
        'testing_suite_maintainability' => 'Keep suite layout consistent and make test ownership explicit per module.',
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
            'category' => 'Testing',
            'title' => $title,
            'description' => (string) ($finding['description'] ?? 'Potential testing quality risk identified.'),
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
