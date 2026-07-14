<?php

declare(strict_types=1);

/**
 * Check 1: Insecure Design and Logic Flaws (OWASP A04)
 * Reuses OWASP pattern scanning and adds business-logic abuse heuristics.
 */
function check_insecure_design(string $owner, string $repo, string $pat, array $sourceFiles): array
{
    $base = check_owasp($owner, $repo, $pat, $sourceFiles);

    $findings = $base['findings'] ?? [];
    $recommendations = $base['recommendations'] ?? [];

    foreach ($sourceFiles as $fileNode) {
        $path = $fileNode['path'];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['php', 'js', 'ts', 'py', 'java', 'cs'], true)) {
            continue;
        }

        $content = github_get_file_content($owner, $repo, $path, $pat);
        if ($content === null) {
            continue;
        }

        // Security-sensitive actions without nearby auth/role checks are logic-flaw prone.
        if (preg_match('/(delete|update|transfer|approve|publish|admin)/i', $content)
            && !preg_match('/(authorize|authorise|permission|is_admin|role|gate|policy|can\(|acl)/i', $content)) {
            $findings[] = [
                'category'    => 'OWASP',
                'title'       => "A04 Missing authorization guardrails — {$path}",
                'description' => "Security-sensitive operations appear in `{$path}` without obvious authorization checks. Enforce server-side policy checks on every privileged action.",
                'severity'    => 'Medium',
            ];
        }

        // Trusting client-side flags for security decisions is a common design flaw.
        if (preg_match('/\$_(GET|POST|REQUEST)\[[^\]]*(role|admin|is_admin|permission)[^\]]*\]/i', $content)) {
            $findings[] = [
                'category'    => 'OWASP',
                'title'       => "A04 Client-controlled privilege input — {$path}",
                'description' => "`{$path}` appears to read role/privilege state from user input. Never trust client-supplied privilege flags; derive authorization from trusted server identity.",
                'severity'    => 'High',
            ];
        }
    }

    if (!empty($findings)) {
        $recommendations[] = [
            'recommendation_text' => 'Model threat scenarios for privileged workflows and enforce explicit authorization checks at controller/service boundaries.',
            'priority'            => 'High',
        ];
    }

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}
