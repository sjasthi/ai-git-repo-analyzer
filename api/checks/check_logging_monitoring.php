<?php

declare(strict_types=1);

/**
 * Check 4: Logging and Monitoring Coverage (OWASP A09)
 * Estimates logging/telemetry maturity through code and config signals.
 */
function check_logging_monitoring(string $owner, string $repo, string $pat, array $tree, array $sourceFiles): array
{
    $findings = [];
    $recommendations = [];

    $logSignals = 0;
    $authSignals = 0;
    $errorSuppressions = 0;

    foreach ($sourceFiles as $fileNode) {
        $path = $fileNode['path'];
        $content = github_get_file_content($owner, $repo, $path, $pat);
        if ($content === null) {
            continue;
        }

        if (preg_match('/(error_log|logger|monolog|winston|pino|log4j|serilog|sentry|datadog|newrelic)/i', $content)) {
            $logSignals++;
        }
        if (preg_match('/(login|signin|auth|session|token|permission|access denied|unauthorized)/i', $content)) {
            $authSignals++;
        }
        if (preg_match('/@\s*\w+\(|try\s*\{[\s\S]*?catch\s*\([^\)]*\)\s*\{\s*\}/i', $content)) {
            $errorSuppressions++;
        }
    }

    if ($authSignals > 0 && $logSignals === 0) {
        $findings[] = [
            'category'    => 'Logging',
            'title'       => 'Authentication-sensitive code without clear logging signals',
            'description' => 'Auth/session-related logic was detected but no clear logging framework calls were found in sampled files. This can weaken incident detection and auditability.',
            'severity'    => 'Medium',
        ];
    }

    if ($errorSuppressions > 0) {
        $findings[] = [
            'category'    => 'Logging',
            'title'       => "Potential error suppression patterns: {$errorSuppressions}",
            'description' => 'Patterns that may suppress exceptions or errors were found. Suppressed errors reduce observability and can hide active exploitation attempts.',
            'severity'    => $errorSuppressions > 3 ? 'Medium' : 'Low',
        ];
    }

    $logConfigFiles = ['logging.yml', 'logging.yaml', 'logback.xml', 'php.ini'];
    $configFound = false;
    foreach ($logConfigFiles as $name) {
        $node = tree_find_file($tree, $name);
        if ($node !== null) {
            $configFound = true;
            break;
        }
    }

    if ($logSignals === 0 && !$configFound) {
        $findings[] = [
            'category'    => 'Logging',
            'title'       => 'No obvious logging or monitoring configuration found',
            'description' => 'No logging library usage or known logging config files were detected in sampled content. Consider adding structured logging and alerting for high-risk events.',
            'severity'    => 'Medium',
        ];
    }

    if (!empty($findings)) {
        $recommendations[] = [
            'recommendation_text' => 'Adopt structured security logging for auth failures, access denials, and exception paths; wire alerts to a monitored channel.',
            'priority'            => 'Medium',
        ];
    } else {
        $recommendations[] = [
            'recommendation_text' => 'Logging and monitoring signals are present in sampled files. Validate retention, alerting, and incident runbooks operationally.',
            'priority'            => 'Low',
        ];
    }

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}
