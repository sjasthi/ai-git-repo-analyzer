<?php

declare(strict_types=1);

/**
 * Check 3: CI/CD and Software Integrity Risks (OWASP A08)
 * Reviews workflow and pipeline config for high-risk patterns.
 */
function check_ci_cd_integrity(string $owner, string $repo, string $pat, array $tree): array
{
    $findings = [];
    $recommendations = [];

    $workflowNodes = [];
    foreach ($tree as $node) {
        if (($node['type'] ?? '') !== 'blob') {
            continue;
        }
        $path = (string) ($node['path'] ?? '');
        if (str_starts_with($path, '.github/workflows/') && preg_match('/\.(yml|yaml)$/i', $path)) {
            $workflowNodes[] = $node;
        }
    }

    if (empty($workflowNodes)) {
        $findings[] = [
            'category'    => 'CI/CD Integrity',
            'title'       => 'No GitHub Actions workflow detected',
            'description' => 'No files were found under .github/workflows/. Missing CI pipelines reduce test and security assurance before merges/releases.',
            'severity'    => 'Medium',
        ];
        $recommendations[] = [
            'recommendation_text' => 'Add CI workflows for tests, dependency scanning, and static analysis before merge.',
            'priority'            => 'Medium',
        ];
        return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
    }

    foreach ($workflowNodes as $wf) {
        $path = $wf['path'];
        $content = github_get_file_content($owner, $repo, $path, $pat);
        if ($content === null) {
            continue;
        }

        if (preg_match('/pull_request_target/i', $content)) {
            $findings[] = [
                'category'    => 'CI/CD Integrity',
                'title'       => "Risky trigger pull_request_target in {$path}",
                'description' => "`{$path}` uses pull_request_target. Misuse can expose privileged secrets to untrusted fork changes. Restrict privileged jobs and validate event context.",
                'severity'    => 'High',
            ];
        }

        if (preg_match('/permissions\s*:\s*(write-all|\{\s*\})/i', $content)
            || preg_match('/contents\s*:\s*write/i', $content)) {
            $findings[] = [
                'category'    => 'CI/CD Integrity',
                'title'       => "Broad workflow permissions in {$path}",
                'description' => "Workflow token permissions in `{$path}` appear broader than least privilege. Scope permissions minimally per job.",
                'severity'    => 'Medium',
            ];
        }

        if (preg_match('/uses\s*:\s*[^@\n]+@v?[0-9]+/i', $content) && !preg_match('/uses\s*:\s*[^@\n]+@[a-f0-9]{40}/i', $content)) {
            $findings[] = [
                'category'    => 'CI/CD Integrity',
                'title'       => "Action references not pinned to commit SHA in {$path}",
                'description' => "`{$path}` references Actions by mutable tags (for example v3) instead of immutable commit SHAs. Pinning reduces supply-chain tampering risk.",
                'severity'    => 'Medium',
            ];
        }

        if (!preg_match('/(codeql|trivy|semgrep|gitleaks|dependency-review|npm audit|composer audit)/i', $content)) {
            $findings[] = [
                'category'    => 'CI/CD Integrity',
                'title'       => "No security scanning step detected in {$path}",
                'description' => "No obvious SAST/secrets/dependency scan step was found in `{$path}`. Add at least one security gate to CI.",
                'severity'    => 'Low',
            ];
        }
    }

    if (!empty($findings)) {
        $recommendations[] = [
            'recommendation_text' => 'Harden pipelines with least-privilege permissions, SHA-pinned actions, and mandatory security scan jobs.',
            'priority'            => 'High',
        ];
    } else {
        $recommendations[] = [
            'recommendation_text' => 'CI/CD integrity checks look healthy. Keep workflow security controls documented and reviewed quarterly.',
            'priority'            => 'Low',
        ];
    }

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}
