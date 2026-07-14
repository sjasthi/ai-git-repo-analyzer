<?php

declare(strict_types=1);

/**
 * Check 2: Vulnerable and Outdated Dependencies (OWASP A06)
 * Audits dependency hygiene: lockfiles, version pinning, and risky broad ranges.
 */
function check_dependency_hardening(string $owner, string $repo, string $pat, array $tree): array
{
    $findings = [];
    $recommendations = [];

    $manifests = [
        'package.json' => ['lock' => ['package-lock.json', 'pnpm-lock.yaml', 'yarn.lock']],
        'composer.json' => ['lock' => ['composer.lock']],
        'requirements.txt' => ['lock' => []],
        'Gemfile' => ['lock' => ['Gemfile.lock']],
        'go.mod' => ['lock' => ['go.sum']],
    ];

    $foundAny = false;

    foreach ($manifests as $manifest => $meta) {
        $manifestNode = tree_find_file($tree, $manifest);
        if ($manifestNode === null) {
            continue;
        }
        $foundAny = true;

        $manifestPath = $manifestNode['path'];
        $content = github_get_file_content($owner, $repo, $manifestPath, $pat);
        if ($content === null) {
            continue;
        }

        foreach ($meta['lock'] as $lockName) {
            if (tree_find_file($tree, $lockName) === null) {
                $findings[] = [
                    'category'    => 'Dependency Hygiene',
                    'title'       => "Missing lock file for {$manifest}",
                    'description' => "`{$manifest}` exists but `{$lockName}` was not found. Missing lock files reduce dependency reproducibility and increase supply-chain drift.",
                    'severity'    => 'Medium',
                ];
            }
        }

        $riskyRanges = 0;
        $unbounded = 0;
        foreach (explode("\n", $content) as $line) {
            $trim = trim($line);
            if ($trim === '' || str_starts_with($trim, '#')) {
                continue;
            }
            if (preg_match('/"\s*:\s*"\*"|latest|dev-master|master|main/i', $trim)) {
                $unbounded++;
            }
            if (preg_match('/\^[0-9]|~[0-9]|>=\s*[0-9]/', $trim)) {
                $riskyRanges++;
            }
        }

        if ($unbounded > 0) {
            $findings[] = [
                'category'    => 'Dependency Hygiene',
                'title'       => "Unbounded dependency constraints in {$manifestPath}",
                'description' => "Detected {$unbounded} dependency declarations with broad/unbounded version selectors (for example `*`, `latest`, or floating branches). Pin versions to reduce unexpected upgrades.",
                'severity'    => 'High',
            ];
        } elseif ($riskyRanges > 8) {
            $findings[] = [
                'category'    => 'Dependency Hygiene',
                'title'       => "Many floating version ranges in {$manifestPath}",
                'description' => "Detected {$riskyRanges} permissive version ranges. Consider tighter pinning for production-critical dependencies.",
                'severity'    => 'Low',
            ];
        }
    }

    if (!$foundAny) {
        $findings[] = [
            'category'    => 'Dependency Hygiene',
            'title'       => 'No supported dependency manifest found',
            'description' => 'No package.json, composer.json, requirements.txt, Gemfile, or go.mod was detected. Dependency hygiene and update risk could not be evaluated.',
            'severity'    => 'Low',
        ];
    }

    if (!empty($findings)) {
        $recommendations[] = [
            'recommendation_text' => 'Use lockfiles, pin production dependencies, and enable automated update workflows (Dependabot/Renovate) with review gates.',
            'priority'            => 'High',
        ];
    } else {
        $recommendations[] = [
            'recommendation_text' => 'Dependency hygiene signals look good. Keep lockfiles reviewed and refresh dependencies on a scheduled cadence.',
            'priority'            => 'Low',
        ];
    }

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}
