<?php

declare(strict_types=1);

/**
 * Check 8: Git History Risk Analysis
 * Scans recent commit messages for risky patterns, bus-factor, and large commit sizes.
 */
function check_git_history(string $owner, string $repo, string $pat): array
{
    $findings        = [];
    $recommendations = [];

    $commits = github_get_commits($owner, $repo, $pat, 50);
    if (empty($commits)) {
        return ['findings' => [], 'recommendations' => [], 'skills' => []];
    }

    // Pattern => human-readable reason
    $riskyPatterns = [
        '/\bhotfix\b/i'            => 'Emergency hotfixes suggest recurring stability issues',
        '/\bemergency\b/i'         => 'Emergency changes bypass normal review processes',
        '/\brevert\b/i'            => 'Reverts indicate instability or mistakes being undone',
        '/skip.{0,10}test/i'       => 'Skipping tests increases regression risk',
        '/no.{0,10}test/i'         => 'Commits labelled "no tests" reduce safety net',
        '/\bwip\b/i'               => 'WIP commits on the default branch indicate incomplete work',
        '/\bhack\b/i'              => 'Hack commits signal deliberate shortcuts creating tech debt',
        '/\bfixme\b/i'             => 'FIXME in commit message indicates a known unfixed problem',
        '/password|secret|token/i' => 'Commit message references credentials — potential accidental exposure',
        '/force\s*push/i'          => 'Force-push referenced in commit history',
    ];

    // High-severity patterns (subset of above)
    $highSeverity = ['password|secret|token', 'skip.{0,10}test'];

    $flagged      = [];  // pattern => first match info
    $authorCounts = [];

    foreach ($commits as $commit) {
        $msg    = $commit['commit']['message'] ?? '';
        $author = $commit['commit']['author']['name'] ?? 'Unknown';
        $date   = $commit['commit']['author']['date'] ?? '';

        $authorCounts[$author] = ($authorCounts[$author] ?? 0) + 1;

        foreach ($riskyPatterns as $regex => $reason) {
            if (!isset($flagged[$regex]) && preg_match($regex, $msg)) {
                $flagged[$regex] = [
                    'reason'  => $reason,
                    'message' => substr($msg, 0, 80),
                    'author'  => $author,
                    'date'    => $date,
                ];
            }
        }
    }

    foreach ($flagged as $regex => $info) {
        $isHigh = false;
        foreach ($highSeverity as $hs) {
            if (str_contains($regex, $hs)) {
                $isHigh = true;
                break;
            }
        }
        $findings[] = [
            'category'    => 'Git History',
            'title'       => "Risky commit pattern — \"{$info['message']}\"",
            'description' => "{$info['reason']}. Author: {$info['author']}. Date: {$info['date']}.",
            'severity'    => $isHigh ? 'High' : 'Medium',
        ];
    }

    // Bus-factor check
    $uniqueAuthors = count($authorCounts);
    if ($uniqueAuthors === 1) {
        $findings[] = [
            'category'    => 'Git History',
            'title'       => 'Single contributor — bus factor is 1',
            'description' => 'All recent commits come from one author. If that person is unavailable, the project stalls. Onboard additional contributors and document the codebase.',
            'severity'    => 'Medium',
        ];
    }

    // Large commit check (stats are not always returned without per-commit calls; skip if missing)
    $largeCount = 0;
    foreach ($commits as $commit) {
        $total = ($commit['stats']['additions'] ?? 0) + ($commit['stats']['deletions'] ?? 0);
        if ($total > 500) {
            $largeCount++;
        }
    }
    if ($largeCount > 5) {
        $findings[] = [
            'category'    => 'Git History',
            'title'       => "{$largeCount} large commits (>500 lines changed)",
            'description' => "Large commits are harder to review and increase the risk of undetected bugs. Break work into smaller focused commits.",
            'severity'    => 'Low',
        ];
    }

    if (!empty($findings)) {
        $recommendations[] = [
            'recommendation_text' => 'Enable branch protection on the default branch: require PR reviews and passing CI before merge. Use conventional commits to standardise commit messages.',
            'priority'            => 'Medium',
        ];
    } else {
        $recommendations[] = [
            'recommendation_text' => 'Git history looks clean. Add branch protection rules and commitlint to keep commit quality high as the project grows.',
            'priority'            => 'Low',
        ];
    }

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}
