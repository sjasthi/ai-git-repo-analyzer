<?php

declare(strict_types=1);

function check_testing(string $owner, string $repo, string $pat, array $tree, array $languages, array $sourceFiles, string $principleId): array
{
    return match ($principleId) {
        'testing_unit_coverage' => testing_unit_coverage($owner, $repo, $pat, $tree),
        'testing_integration_coverage' => testing_integration_coverage($owner, $repo, $pat, $tree),
        'testing_end_to_end_coverage' => testing_end_to_end_coverage($tree),
        'testing_fast_feedback' => testing_fast_feedback($owner, $repo, $pat, $tree),
        'testing_mocking_external_apis' => testing_mocking_external_apis($owner, $repo, $pat, $tree),
        'testing_database_isolation' => testing_database_isolation($owner, $repo, $pat, $tree),
        'testing_api_response_validation' => testing_api_response_validation($owner, $repo, $pat, $tree),
        'testing_error_path_testing' => testing_error_path_testing($owner, $repo, $pat, $tree),
        'testing_regression_coverage' => testing_regression_coverage($owner, $repo, $pat, $tree),
        'testing_organization_maintainability' => testing_organization_maintainability($tree),
        default => ['findings' => [], 'recommendations' => [], 'skills' => []],
    };
}

function testing_principle_title(string $principleId): string
{
    return match ($principleId) {
        'testing_unit_coverage' => 'Test Pyramid Unit Coverage',
        'testing_integration_coverage' => 'Test Pyramid Integration Coverage',
        'testing_end_to_end_coverage' => 'Test Pyramid End-to-End Coverage',
        'testing_fast_feedback' => 'Test Pyramid Fast Feedback',
        'testing_mocking_external_apis' => 'Test Pyramid Mocking External APIs',
        'testing_database_isolation' => 'Test Pyramid Database Test Isolation',
        'testing_api_response_validation' => 'Test Pyramid API Response Validation',
        'testing_error_path_testing' => 'Test Pyramid Error Path Testing',
        'testing_regression_coverage' => 'Test Pyramid Regression Test Coverage',
        'testing_organization_maintainability' => 'Test Pyramid Test Organization and Maintainability',
        default => 'Test Pyramid',
    };
}

function testing_result(string $title, string $summary, string $severity, array $evidence = [], array $recommendations = []): array
{
    $findings = [];
    if (!empty($evidence)) {
        $findings[] = [
            'category' => 'Testing',
            'title' => $title,
            'description' => $summary,
            'severity' => $severity,
            'evidence' => array_values($evidence),
        ];
    }

    if (empty($recommendations)) {
        $recommendations[] = [
            'recommendation_text' => 'Keep the testing strategy aligned to the Test Pyramid and prefer fast, isolated tests.',
            'priority' => 'Low',
        ];
    }

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}

function testing_tree_php_files(array $tree, array $prefixes = []): array
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

function testing_files_with_content(string $owner, string $repo, string $pat, array $paths): array
{
    $files = [];
    foreach ($paths as $path) {
        $content = github_get_file_content($owner, $repo, $path, $pat);
        if ($content !== null) {
            $files[$path] = $content;
        }
    }
    return $files;
}

function testing_test_file_paths(array $tree): array
{
    $paths = [];
    foreach ($tree as $node) {
        if (($node['type'] ?? '') !== 'blob') {
            continue;
        }
        $path = (string) ($node['path'] ?? '');
        if ($path === '') {
            continue;
        }
        if (preg_match('#(^|/)(tests?|specs?)(/|$)#i', $path)
            || preg_match('#(^|/)(unit|integration|e2e|end-to-end)(/|$)#i', $path)
            || preg_match('/Test\.php$/i', basename($path))
            || preg_match('/\.test\.php$/i', $path)) {
            $paths[] = $path;
        }
    }

    return array_values(array_unique($paths));
}

function testing_test_file_contents(string $owner, string $repo, string $pat, array $tree): array
{
    return testing_files_with_content($owner, $repo, $pat, testing_test_file_paths($tree));
}

function testing_count_source_tests(array $tree): array
{
    $source = 0;
    $tests = 0;

    foreach ($tree as $node) {
        if (($node['type'] ?? '') !== 'blob') {
            continue;
        }

        $path = (string) ($node['path'] ?? '');
        if ($path === '' || !preg_match('/\.php$/i', $path)) {
            continue;
        }

        if (preg_match('#(^|/)(tests?|specs?)(/|$)#i', $path)
            || preg_match('#(^|/)(unit|integration|e2e|end-to-end)(/|$)#i', $path)
            || preg_match('/Test\.php$/i', basename($path))
            || preg_match('/\.test\.php$/i', $path)) {
            $tests++;
        } else {
            $source++;
        }
    }

    return ['source' => $source, 'tests' => $tests];
}

function testing_coverage_artifacts(array $tree): array
{
    $artifacts = [];
    foreach ($tree as $node) {
        if (($node['type'] ?? '') !== 'blob') {
            continue;
        }

        $path = (string) ($node['path'] ?? '');
        if ($path === '') {
            continue;
        }

        $basename = strtolower(basename($path));
        if (in_array($basename, ['phpunit.xml', 'phpunit.xml.dist', 'clover.xml', 'coverage.xml', 'cobertura.xml'], true)
            || str_contains(strtolower($path), 'coverage/')) {
            $artifacts[] = $path;
        }
    }

    return array_values(array_unique($artifacts));
}

function testing_assertion_signals(string $owner, string $repo, string $pat, array $tree): array
{
    $signals = [];
    $testFiles = testing_test_file_contents($owner, $repo, $pat, $tree);

    foreach ($testFiles as $path => $content) {
        $count = 0;
        if (preg_match_all('/\bassert[A-Z][A-Za-z0-9_]*\s*\(/', $content, $matches)) {
            $count += count($matches[0]);
        }
        if (preg_match_all('/\bexpectException(?:Message|Code)?\s*\(/', $content, $matches)) {
            $count += count($matches[0]);
        }
        if (preg_match_all('/\bexpectNotToPerformAssertions\s*\(/', $content, $matches)) {
            $count += count($matches[0]);
        }

        if ($count > 0) {
            $signals[$path] = $count;
        }
    }

    arsort($signals);
    return $signals;
}

function testing_unit_coverage(string $owner, string $repo, string $pat, array $tree): array
{
    $title = testing_principle_title('testing_unit_coverage');
    $counts = testing_count_source_tests($tree);
    $testFiles = testing_test_file_paths($tree);
    $unitSignals = array_filter($testFiles, static fn($path) => preg_match('#(^|/)(unit)(/|$)#i', $path) === 1);
    $phpunitFiles = array_filter($testFiles, static fn($path) => preg_match('/phpunit/i', $path) === 1);
    $coverageArtifacts = testing_coverage_artifacts($tree);

    if (empty($testFiles)) {
        return testing_result(
            $title,
            'No test files were found, so unit coverage appears to be missing.',
            'High',
            ['No PHPUnit or test directory files were detected in the repository tree.'],
            [[
                'recommendation_text' => 'Add PHPUnit unit tests for the core analysis helpers and check modules.',
                'priority' => 'High',
            ]]
        );
    }

    $evidence = [
        'Detected ' . count($testFiles) . ' test file(s) and ' . $counts['source'] . ' source file(s).',
    ];
    if (!empty($unitSignals)) {
        $evidence[] = 'Unit-oriented folders or files were found: ' . implode(', ', array_slice(array_values($unitSignals), 0, 5));
    }
    if (!empty($phpunitFiles)) {
        $evidence[] = 'PHPUnit naming was found in: ' . implode(', ', array_slice(array_values($phpunitFiles), 0, 3));
    }
    if (!empty($coverageArtifacts)) {
        $evidence[] = 'Coverage artifacts or configuration were found: ' . implode(', ', array_slice($coverageArtifacts, 0, 5));
    }

    $coverageRatio = $counts['source'] > 0 ? $counts['tests'] / max(1, $counts['source']) : 0;
    $severity = (!empty($coverageArtifacts) && $coverageRatio >= 0.25) ? 'Low' : (count($testFiles) >= max(1, (int) ceil($counts['source'] / 4)) ? 'Low' : 'Medium');

    return testing_result(
        $title,
        'The repository has test files, but unit coverage depth is only partially visible from the tree.',
        $severity,
        $evidence,
        [[
            'recommendation_text' => 'Prioritize unit tests for each analyzer function, helper, and parser before broader integration coverage.',
            'priority' => 'High',
        ]]
    );
}

function testing_integration_coverage(string $owner, string $repo, string $pat, array $tree): array
{
    $title = testing_principle_title('testing_integration_coverage');
    $testFiles = testing_test_file_contents($owner, $repo, $pat, $tree);
    $integrationHits = [];

    foreach ($testFiles as $path => $content) {
        if (preg_match('/integration|db|database|api|http|client|repository/i', $path . "\n" . $content)) {
            $integrationHits[] = $path;
        }
    }

    if (empty($integrationHits)) {
        return testing_result(
            $title,
            'No strong integration-test signals were found.',
            'Medium',
            ['No integration or API/DB test files were detected.'],
            [[
                'recommendation_text' => 'Add integration tests for the API pipeline, database persistence, and report generation flow.',
                'priority' => 'High',
            ]]
        );
    }

    return testing_result(
        $title,
        'Integration-style test files are present and touch application boundaries.',
        'Low',
        array_map(static fn($path) => 'Integration signal: ' . $path, array_slice($integrationHits, 0, 5)),
        [[
            'recommendation_text' => 'Keep integration tests focused on boundary behavior and avoid duplicating unit-level assertions.',
            'priority' => 'Low',
        ]]
    );
}

function testing_end_to_end_coverage(array $tree): array
{
    $title = testing_principle_title('testing_end_to_end_coverage');
    $signals = [];
    foreach ($tree as $node) {
        if (($node['type'] ?? '') !== 'blob') {
            continue;
        }
        $path = (string) ($node['path'] ?? '');
        if ($path !== '' && preg_match('/(e2e|end-to-end|cypress|playwright|selenium|codeception|browser)/i', $path)) {
            $signals[] = $path;
        }
    }

    if (empty($signals)) {
        return testing_result(
            $title,
            'No end-to-end testing harness was found.',
            'Medium',
            ['No e2e tooling or test paths such as cypress, playwright, selenium, or codeception were detected.'],
            [[
                'recommendation_text' => 'Add a minimal end-to-end suite for the repository analyzer happy path and report rendering flow.',
                'priority' => 'Medium',
            ]]
        );
    }

    return testing_result(
        $title,
        'End-to-end tooling is present.',
        'Low',
        array_map(static fn($path) => 'E2E signal: ' . $path, array_slice($signals, 0, 5)),
        [[
            'recommendation_text' => 'Keep e2e coverage small and focused on critical user journeys.',
            'priority' => 'Low',
        ]]
    );
}

function testing_fast_feedback(string $owner, string $repo, string $pat, array $tree): array
{
    $title = testing_principle_title('testing_fast_feedback');
    $paths = testing_test_file_paths($tree);
    $content = testing_files_with_content($owner, $repo, $pat, $paths);
    $assertionSignals = testing_assertion_signals($owner, $repo, $pat, $tree);

    foreach ($content as $path => $text) {
        if (preg_match('/phpunit/i', $text) || preg_match('/assert/i', $text)) {
            $assertionSignals[$path] = ($assertionSignals[$path] ?? 0) + 1;
        }
    }

    $hasPhpUnitXml = tree_find_file($tree, 'phpunit.xml') !== null || tree_find_file($tree, 'phpunit.xml.dist') !== null;
    $coverageArtifacts = testing_coverage_artifacts($tree);

    if (empty($assertionSignals) && !$hasPhpUnitXml && empty($coverageArtifacts)) {
        return testing_result(
            $title,
            'The repository does not show signs of a fast, local test feedback loop.',
            'Medium',
            ['No phpunit.xml configuration, coverage artifact, or obvious assertion-heavy test files were found.'],
            [[
                'recommendation_text' => 'Add a fast unit-test layer with phpunit.xml and keep expensive boundary tests separate.',
                'priority' => 'Medium',
            ]]
        );
    }

    return testing_result(
        $title,
        'A local fast-feedback test loop appears to exist.',
        'Low',
        array_merge(
            array_map(static fn($path) => 'Fast feedback signal: ' . $path, array_slice(array_keys($assertionSignals), 0, 5)),
            !empty($coverageArtifacts) ? ['Coverage artifact(s): ' . implode(', ', array_slice($coverageArtifacts, 0, 3))] : []
        ),
        [[
            'recommendation_text' => 'Keep the fastest tests in the default suite and move slower cases behind separate groups.',
            'priority' => 'Low',
        ]]
    );
}

function testing_mocking_external_apis(string $owner, string $repo, string $pat, array $tree): array
{
    $title = testing_principle_title('testing_mocking_external_apis');
    $tests = testing_test_file_contents($owner, $repo, $pat, $tree);
    $signals = [];

    foreach ($tests as $path => $content) {
        if (preg_match('/mock|stub|fake|prophecy|phpunit\\framework\\mockobject|createMock|expects\(/i', $content)) {
            $signals[] = $path;
        }
    }

    if (empty($signals)) {
        return testing_result(
            $title,
            'No obvious mocking of external APIs was detected in test files.',
            'Medium',
            ['No mocks, stubs, or fakes were detected in the repository tests.'],
            [[
                'recommendation_text' => 'Mock GitHub/GitLab API calls and database access in tests so the suite stays deterministic.',
                'priority' => 'High',
            ]]
        );
    }

    return testing_result(
        $title,
        'Test files include mocking or stubbing patterns.',
        'Low',
        array_map(static fn($path) => 'Mocking signal: ' . $path, array_slice($signals, 0, 5)),
        [[
            'recommendation_text' => 'Continue mocking external APIs to keep tests isolated and repeatable.',
            'priority' => 'Low',
        ]]
    );
}

function testing_database_isolation(string $owner, string $repo, string $pat, array $tree): array
{
    $title = testing_principle_title('testing_database_isolation');
    $tests = testing_test_file_contents($owner, $repo, $pat, $tree);
    $signals = [];

    foreach ($tests as $path => $content) {
        if (preg_match('/sqlite|memory:|transaction|refreshdatabase|databasetransactions|rollback|migrate/i', $content)) {
            $signals[] = $path;
        }
    }

    if (empty($signals)) {
        return testing_result(
            $title,
            'Database test isolation was not clearly visible.',
            'Medium',
            ['No sqlite-in-memory, transaction rollback, or database reset patterns were found in tests.'],
            [[
                'recommendation_text' => 'Use an isolated test database or in-memory database, and reset state between integration tests.',
                'priority' => 'High',
            ]]
        );
    }

    return testing_result(
        $title,
        'Database isolation patterns are present in tests.',
        'Low',
        array_map(static fn($path) => 'Isolation signal: ' . $path, array_slice($signals, 0, 5)),
        [[
            'recommendation_text' => 'Keep database state isolated so tests remain order-independent.',
            'priority' => 'Low',
        ]]
    );
}

function testing_api_response_validation(string $owner, string $repo, string $pat, array $tree): array
{
    $title = testing_principle_title('testing_api_response_validation');
    $tests = testing_test_file_contents($owner, $repo, $pat, $tree);
    $signals = [];

    foreach ($tests as $path => $content) {
        if (preg_match('/response|json|status code|assertjson|assertstatus|assertresponse|schema|content-type/i', $content)) {
            $signals[] = $path;
        }
    }

    if (empty($signals)) {
        return testing_result(
            $title,
            'API response validation tests were not clearly visible.',
            'Medium',
            ['No explicit response-status or JSON-structure assertions were detected.'],
            [[
                'recommendation_text' => 'Add tests that validate JSON schema, HTTP status codes, and error payload structure.',
                'priority' => 'High',
            ]]
        );
    }

    return testing_result(
        $title,
        'Tests appear to validate API response behavior.',
        'Low',
        array_map(static fn($path) => 'API validation signal: ' . $path, array_slice($signals, 0, 5)),
        [[
            'recommendation_text' => 'Keep JSON and status-code assertions near the API boundary.',
            'priority' => 'Low',
        ]]
    );
}

function testing_error_path_testing(string $owner, string $repo, string $pat, array $tree): array
{
    $title = testing_principle_title('testing_error_path_testing');
    $tests = testing_test_file_contents($owner, $repo, $pat, $tree);
    $signals = [];

    foreach ($tests as $path => $content) {
        if (preg_match('/invalid|exception|error|fail|missing|forbidden|unauthorized|404|500/i', $content)) {
            $signals[] = $path;
        }
    }

    if (empty($signals)) {
        return testing_result(
            $title,
            'Error-path testing coverage was not clearly visible.',
            'Medium',
            ['No tests clearly targeting invalid input or exception paths were found.'],
            [[
                'recommendation_text' => 'Add negative tests for invalid repository URLs, API failures, missing tokens, and malformed data.',
                'priority' => 'High',
            ]]
        );
    }

    return testing_result(
        $title,
        'Some tests appear to cover failure paths.',
        'Low',
        array_map(static fn($path) => 'Error-path signal: ' . $path, array_slice($signals, 0, 5)),
        [[
            'recommendation_text' => 'Keep negative-path assertions in the suite so regressions fail loudly.',
            'priority' => 'Low',
        ]]
    );
}

function testing_regression_coverage(string $owner, string $repo, string $pat, array $tree): array
{
    $title = testing_principle_title('testing_regression_coverage');
    $tests = testing_test_file_contents($owner, $repo, $pat, $tree);
    $signals = [];

    foreach ($tests as $path => $content) {
        if (preg_match('/regression|bug|issue|fix|ticket|gh-|#\d+/i', $content)) {
            $signals[] = $path;
        }
    }

    if (empty($signals)) {
        return testing_result(
            $title,
            'Regression-oriented tests were not obvious.',
            'Medium',
            ['No regression, bug-fix, or issue-linked test names were found.'],
            [[
                'recommendation_text' => 'Add regression tests for previously fixed bugs and analysis edge cases.',
                'priority' => 'Medium',
            ]]
        );
    }

    return testing_result(
        $title,
        'Regression-style tests are present.',
        'Low',
        array_map(static fn($path) => 'Regression signal: ' . $path, array_slice($signals, 0, 5)),
        [[
            'recommendation_text' => 'Retain bug-fix regression tests in the main suite.',
            'priority' => 'Low',
        ]]
    );
}

function testing_organization_maintainability(array $tree): array
{
    $title = testing_principle_title('testing_organization_maintainability');
    $paths = [];
    foreach ($tree as $node) {
        if (($node['type'] ?? '') !== 'blob') {
            continue;
        }
        $path = (string) ($node['path'] ?? '');
        if ($path !== '' && preg_match('/(tests?|specs?)(\/|$)|Test\.php$|\.test\.php$/i', $path)) {
            $paths[] = $path;
        }
    }

    if (empty($paths)) {
        return testing_result(
            $title,
            'Test organization and maintainability are weak because no test directory structure was found.',
            'High',
            ['The repository tree does not show a dedicated test folder or test file convention.'],
            [[
                'recommendation_text' => 'Create a clear test structure such as tests/Unit, tests/Integration, and tests/E2E.',
                'priority' => 'High',
            ]]
        );
    }

    $evidence = ['Test files/folders found: ' . implode(', ', array_slice($paths, 0, 6))];
    if (tree_find_file($tree, 'phpunit.xml') !== null || tree_find_file($tree, 'phpunit.xml.dist') !== null) {
        $evidence[] = 'PHPUnit configuration file exists.';
    }

    return testing_result(
        $title,
        'The repository has a visible testing structure, though maintainability depends on how the files are grouped.',
        'Low',
        $evidence,
        [[
            'recommendation_text' => 'Keep test naming, folder structure, and fixture helpers consistent across suites.',
            'priority' => 'Low',
        ]]
    );
}