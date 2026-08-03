<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function json_error(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_error('Only POST is supported for chat assistant.', 405);
}

$raw = file_get_contents('php://input');
if (!is_string($raw) || trim($raw) === '') {
    json_error('Missing request payload.');
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    json_error('Invalid JSON payload.');
}

$question = trim((string) ($payload['question'] ?? ''));
if ($question === '') {
    json_error('Question is required.');
}

if (mb_strlen($question) > 1000) {
    json_error('Question is too long. Please keep it under 1000 characters.');
}

$context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
$findings = is_array($context['findings'] ?? null) ? $context['findings'] : [];
$recommendations = is_array($context['recommendations'] ?? null) ? $context['recommendations'] : [];
$checks = is_array($context['checks'] ?? null) ? $context['checks'] : [];
$score = isset($context['score']) ? (int) $context['score'] : null;

$severityOrder = ['High' => 0, 'Medium' => 1, 'Low' => 2, 'Info' => 3];
usort($findings, static function (array $a, array $b) use ($severityOrder): int {
    $sa = (string) ($a['severity'] ?? 'Info');
    $sb = (string) ($b['severity'] ?? 'Info');
    $wa = $severityOrder[$sa] ?? 4;
    $wb = $severityOrder[$sb] ?? 4;
    if ($wa !== $wb) {
        return $wa <=> $wb;
    }
    return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
});

$severityCounts = ['High' => 0, 'Medium' => 0, 'Low' => 0, 'Info' => 0];
foreach ($findings as $finding) {
    $severity = (string) ($finding['severity'] ?? 'Info');
    if (!isset($severityCounts[$severity])) {
        $severityCounts['Info']++;
        continue;
    }
    $severityCounts[$severity]++;
}

$q = strtolower($question);

$answerLines = [];
$answerLines[] = 'Chat Assistant';

if (preg_match('/top|priority|first|urgent|critical|fix first/', $q) === 1) {
    $answerLines[] = 'Here are the highest-priority items from this scan:';
    $top = array_slice($findings, 0, 3);
    if (empty($top)) {
        $answerLines[] = '- No findings were detected in the current scan.';
    } else {
        foreach ($top as $item) {
            $title = trim((string) ($item['title'] ?? 'Untitled finding'));
            $severity = (string) ($item['severity'] ?? 'Info');
            $answerLines[] = '- [' . $severity . '] ' . $title;
        }
    }

    $highRec = array_values(array_filter($recommendations, static function (array $r): bool {
        return strtolower((string) ($r['priority'] ?? '')) === 'high';
    }));
    if (!empty($highRec)) {
        $answerLines[] = 'Recommended next action:';
        $answerLines[] = '- ' . trim((string) ($highRec[0]['recommendation_text'] ?? 'Start with the highest-risk finding and verify the fix in CI.'));
    }
} elseif (preg_match('/what does owasp checks measure|what is owasp checks|owasp checks/', $q) === 1) {
    $answerLines[] = 'OWASP Checks overview:';
    $answerLines[] = 'These checks map repository risks to common OWASP Top 10 web security categories.';
    $answerLines[] = 'They look for issues such as injection risk, insecure design, weak access control, security misconfiguration, and sensitive data exposure patterns.';
    $answerLines[] = 'Improve this area by addressing High findings first and enforcing secure coding patterns in code review and CI.';
} elseif (preg_match('/complexity checks|what does complexity checks mean|mccabe|cognitive complexity/', $q) === 1) {
    $answerLines[] = 'Complexity Checks overview:';
    $answerLines[] = 'These checks evaluate code complexity using signals like cyclomatic complexity and cognitive complexity.';
    $answerLines[] = 'Higher complexity makes code harder to test, maintain, and review safely.';
    $answerLines[] = 'Improve this area by splitting long functions, reducing nested branches, and simplifying conditional logic.';
} elseif (preg_match('/sonarqube rules|what is sonarqube rules|code quality rules/', $q) === 1) {
    $answerLines[] = 'SonarQube Rules overview:';
    $answerLines[] = 'These checks apply code-quality style rules similar to SonarQube rule categories.';
    $answerLines[] = 'They focus on maintainability issues, reliability bugs, and unsafe coding practices.';
    $answerLines[] = 'Improve this area by resolving repeated rule violations and adding static analysis checks in CI.';
} elseif (preg_match('/clean code checks|what is included in clean code checks|clean code/', $q) === 1) {
    $answerLines[] = 'Clean Code Checks overview:';
    $answerLines[] = 'These checks assess naming clarity, readability, duplication, function length, and maintainable structure.';
    $answerLines[] = 'They help ensure code is easy to understand and safer to change over time.';
    $answerLines[] = 'Improve this area by renaming unclear symbols, removing duplication, and extracting focused functions.';
} elseif (preg_match('/architecture checks|architecture evaluate|clean architecture/', $q) === 1) {
    $answerLines[] = 'Architecture Checks overview:';
    $answerLines[] = 'These checks evaluate layering, dependency direction, separation of concerns, and coupling patterns.';
    $answerLines[] = 'They identify design risks that can reduce scalability and long-term maintainability.';
    $answerLines[] = 'Improve this area by enforcing dependency boundaries and moving infrastructure logic out of domain code.';
} elseif (preg_match('/testing checks|testing pass or fail|test pyramid/', $q) === 1) {
    $answerLines[] = 'Testing Checks overview:';
    $answerLines[] = 'These checks look for evidence of test coverage, test structure, and test reliability signals in the repository.';
    $answerLines[] = 'They help identify gaps that can allow regressions to reach production.';
    $answerLines[] = 'Improve this area by adding targeted unit/integration tests and ensuring tests run in CI.';
} elseif (preg_match('/performance checks|performance look for|performance analysis/', $q) === 1) {
    $answerLines[] = 'Performance Checks overview:';
    $answerLines[] = 'These checks look for patterns that may cause slow execution, inefficient resource usage, or scalability bottlenecks.';
    $answerLines[] = 'They flag areas where optimization and measurement are likely needed.';
    $answerLines[] = 'Improve this area by profiling hotspots, reducing expensive loops, and adding performance budgets in CI.';
} elseif (preg_match('/reliability checks|reliability engineering|sre/', $q) === 1) {
    $answerLines[] = 'Reliability Checks overview:';
    $answerLines[] = 'These checks assess stability signals such as error handling, resilience patterns, and operational readiness.';
    $answerLines[] = 'They help teams reduce outages and improve recoverability.';
    $answerLines[] = 'Improve this area by hardening error handling, retries/timeouts, and observability coverage.';
} elseif (preg_match('/documentation checks|readme best practices|api docs|adr/', $q) === 1) {
    $answerLines[] = 'Documentation Checks overview:';
    $answerLines[] = 'These checks review documentation quality, including README clarity, API docs, and architecture decision records.';
    $answerLines[] = 'Good documentation improves onboarding and reduces implementation mistakes.';
    $answerLines[] = 'Improve this area by documenting setup, architecture decisions, and key operational procedures.';
} elseif (preg_match('/dependency sbom checks|what are dependency sbom checks|sbom checks/', $q) === 1) {
    $answerLines[] = 'Dependency SBOM Checks overview:';
    $answerLines[] = 'These checks analyze dependency risk, software bill of materials (SBOM) quality, and license/compliance signals.';
    $answerLines[] = 'They help identify vulnerable, outdated, or non-compliant packages earlier.';
    $answerLines[] = 'Improve this area by updating vulnerable dependencies, maintaining lockfiles, and generating standards-based SBOM files.';
} elseif (preg_match('/devops readiness checks|what is devops readiness checks|devops readiness/', $q) === 1) {
    $answerLines[] = 'DevOps Readiness Checks overview:';
    $answerLines[] = 'These checks evaluate CI/CD pipeline hygiene, automation quality, workflow hardening, and deployment safety controls.';
    $answerLines[] = 'They highlight release-process risks that can affect delivery reliability and security.';
    $answerLines[] = 'Improve this area by enforcing workflow permissions, action pinning, and deployment gates.';
} elseif (preg_match('/which check affects score the most|what affects score the most|highest weight|most important check/', $q) === 1) {
    $answerLines[] = 'Score impact priority:';
    $answerLines[] = 'Severity impacts score first: High findings reduce score most, then Medium, then Low and Info.';
    $answerLines[] = 'Within categories, checks weighted at 10% have higher impact than checks weighted at 5%.';
    $answerLines[] = 'For fastest improvement, fix High findings from 10% categories first.';
} elseif (preg_match('/why.*10%|why.*5%|weighted at 10|weighted at 5|check weight|weighting/', $q) === 1) {
    $answerLines[] = 'Why some checks are 10% and others 5%:';
    $answerLines[] = 'Higher-weight checks represent areas with broader impact on code quality, security posture, and maintainability.';
    $answerLines[] = 'Lower-weight checks still matter, but typically have narrower or secondary impact on overall repository health.';
    $answerLines[] = 'The weighting helps prioritize remediation work in a practical order.';
} elseif (preg_match('/how do i improve this specific check|improve this check|fix this check/', $q) === 1) {
    $answerLines[] = 'How to improve a specific check:';
    $answerLines[] = '1. Open the check details and list its findings by severity.';
    $answerLines[] = '2. Fix High findings first, then Medium findings.';
    $answerLines[] = '3. Re-run the scan and confirm the finding count and score change.';
    $answerLines[] = '4. Add a CI guard (lint/test/policy) to prevent recurrence.';
} elseif (preg_match('/dependency|sbom|license|vulnerab|package/', $q) === 1) {
    $depFindings = array_values(array_filter($findings, static function (array $f): bool {
        $category = strtolower((string) ($f['category'] ?? ''));
        $title = strtolower((string) ($f['title'] ?? ''));
        return strpos($category, 'depend') !== false || strpos($title, 'sbom') !== false || strpos($title, 'dependency') !== false;
    }));

    $answerLines[] = 'Dependency/SBOM snapshot:';
    if (empty($depFindings)) {
        $answerLines[] = '- No dependency-specific findings in this scan.';
    } else {
        foreach (array_slice($depFindings, 0, 3) as $item) {
            $answerLines[] = '- [' . ((string) ($item['severity'] ?? 'Info')) . '] ' . trim((string) ($item['title'] ?? 'Dependency finding'));
        }
    }
    $answerLines[] = 'Focus order: vulnerable packages -> license risks -> outdated/unused packages.';
} elseif (preg_match('/clean architecture|architecture score|architecture.*low|layered|dependency rule|circular dependency/', $q) === 1) {
    $archFindings = array_values(array_filter($findings, static function (array $f): bool {
        $category = strtolower((string) ($f['category'] ?? ''));
        $title = strtolower((string) ($f['title'] ?? ''));
        $description = strtolower((string) ($f['description'] ?? ''));

        return strpos($category, 'architecture') !== false
            || strpos($title, 'architecture') !== false
            || strpos($description, 'architecture') !== false
            || strpos($title, 'dependency rule') !== false
            || strpos($description, 'circular dependency') !== false;
    }));

    if ($score !== null) {
        $answerLines[] = 'Your score is ' . $score . '/100 because:';
    } else {
        $answerLines[] = 'Architecture score explanation from current findings:';
    }

    if (empty($archFindings)) {
        $answerLines[] = '1. No architecture-specific findings were detected in the current scan context.';
        $answerLines[] = 'Recommendation:';
        $answerLines[] = 'Run a full scan with Architecture checks enabled, then ask again for a detailed root-cause breakdown.';
    } else {
        $maxReasons = min(3, count($archFindings));
        for ($i = 0; $i < $maxReasons; $i++) {
            $title = trim((string) ($archFindings[$i]['title'] ?? 'Architecture finding'));
            $title = preg_replace('/^#\s*\d+\s*/', '', $title);
            $title = $title !== '' ? $title : 'Architecture finding';
            $answerLines[] = ($i + 1) . '. ' . $title;
        }

        $answerLines[] = 'Recommendation:';
        $titleBlob = strtolower(implode(' | ', array_map(static function (array $f): string {
            return (string) ($f['title'] ?? '');
        }, $archFindings)));

        $actions = [];
        if (strpos($titleBlob, 'data access abstraction') !== false) {
            $actions[] = 'Move database and external API logic to the data/infrastructure layer and expose interfaces to use-case/domain layers.';
        }
        if (strpos($titleBlob, 'dependency rule') !== false) {
            $actions[] = 'Enforce inward dependencies only: outer layers may depend on inner layers, never the reverse.';
        }
        if (strpos($titleBlob, 'domain purity') !== false) {
            $actions[] = 'Keep domain models and business rules framework-agnostic and free from persistence/HTTP concerns.';
        }
        if (strpos($titleBlob, 'no cyclic dependencies') !== false || strpos($titleBlob, 'cyclic') !== false) {
            $actions[] = 'Break cyclic package dependencies by extracting shared interfaces or moving shared contracts inward.';
        }

        if (empty($actions)) {
            $actions[] = 'Move data-access logic into data/infrastructure adapters, enforce dependency inversion, and remove cyclic dependencies between packages.';
        }

        $answerLines[] = implode(' ', array_slice($actions, 0, 2));
    }
} elseif (preg_match('/how\s+do\s+you\s+calculate\s+the\s+score|how\s+is\s+the\s+score\s+calculated|calculate.*score|score\s+formula|scoring\s+formula/', $q) === 1) {
    $high = $severityCounts['High'];
    $medium = $severityCounts['Medium'];
    $low = $severityCounts['Low'];
    $info = $severityCounts['Info'];

    $rawDeduction = ($high * 8) + ($medium * 4) + ($low * 1) + ($info * 1);
    $cappedDeduction = min(60, $rawDeduction);
    $computedScore = max(10, 100 - $cappedDeduction);

    $answerLines[] = 'Score calculation formula:';
    $answerLines[] = '- Deduction = (8 x High) + (4 x Medium) + (1 x Low) + (1 x Info)';
    $answerLines[] = '- Final score = max(10, 100 - min(60, deduction))';
    $answerLines[] = 'Current scan breakdown:';
    $answerLines[] = '- High: ' . $high . ', Medium: ' . $medium . ', Low: ' . $low . ', Info: ' . $info;
    $answerLines[] = '- Raw deduction: ' . $rawDeduction . ', Capped deduction: ' . $cappedDeduction;
    $answerLines[] = '- Computed score: ' . $computedScore . '/100';
    if ($score !== null) {
        $answerLines[] = '- Reported score: ' . $score . '/100';
    }
} elseif (preg_match('/score|why.*low|improve.*score|increase.*score|what affects.*score|what improves.*score|what makes.*score|how do i raise.*score|what does.*score mean|how is.*score determined|what is a good score|why is.*score.*low|why is.*score.*high/', $q) === 1) {
    $answerLines[] = 'How this score works:';
    if ($score !== null) {
        $answerLines[] = '- Current score: ' . $score . '/100';
    }
    $answerLines[] = '- The score is based on the findings returned by the checks you ran.';
    $answerLines[] = '- High severity findings affect the score the most, followed by Medium, then Low and Info.';
    $answerLines[] = '- Current breakdown: High ' . $severityCounts['High'] . ', Medium ' . $severityCounts['Medium'] . ', Low ' . $severityCounts['Low'] . ', Info ' . $severityCounts['Info'];
    $answerLines[] = '- A lower score usually means there are more serious issues or more unresolved findings.';
    $answerLines[] = '- To improve the score, fix High issues first, then Medium issues, and address security, reliability, and architecture warnings early.';
} elseif (preg_match('/\bdevops\b|\bci\b|\bcd\b|\bci\/cd\b|\bpipeline\b|\bdeploy(?:ment)?\b|\bworkflow\b/', $q) === 1) {
    $devopsFindings = array_values(array_filter($findings, static function (array $f): bool {
        $category = strtolower((string) ($f['category'] ?? ''));
        $title = strtolower((string) ($f['title'] ?? ''));
        return strpos($category, 'devops') !== false || strpos($title, 'devops') !== false || strpos($title, 'workflow') !== false;
    }));

    $answerLines[] = 'DevOps readiness snapshot:';
    if (empty($devopsFindings)) {
        $answerLines[] = '- No DevOps findings detected in this scan.';
    } else {
        foreach (array_slice($devopsFindings, 0, 3) as $item) {
            $answerLines[] = '- [' . ((string) ($item['severity'] ?? 'Info')) . '] ' . trim((string) ($item['title'] ?? 'DevOps finding'));
        }
    }
    $answerLines[] = 'Start with CI workflow coverage and secrets handling, then harden action pinning and deployment gates.';
} elseif (preg_match('/what is this website|what does this website do|what is this app|what is this tool|who is this for|what can this website do|what does this site do|what is ai git repo analyzer/i', $q) === 1) {
    $answerLines[] = 'About this website:';
    $answerLines[] = 'AI Git Repo Analyzer is a web app for reviewing GitHub and GitLab repositories.';
    $answerLines[] = 'It scans repository content and reports code quality, security, architecture, dependency, testing, DevOps, and maintainability signals.';
    $answerLines[] = 'You can paste a repository URL, provide a PAT, run selected checks, and review the score, findings, recommendations, and skills.';
    $answerLines[] = 'It is designed for developers, reviewers, and teams who want a quick health overview before deeper manual review.';
} elseif (preg_match('/what checks|which checks|available checks|check types|what does it analyze|what kinds of checks/i', $q) === 1) {
    $answerLines[] = 'This website can run checks in these areas:';
    $answerLines[] = '- Security and secrets';
    $answerLines[] = '- Dependencies and licensing';
    $answerLines[] = '- Architecture and design quality';
    $answerLines[] = '- Testing and reliability';
    $answerLines[] = '- DevOps and CI/CD readiness';
    $answerLines[] = '- Clean code and maintainability';
    $answerLines[] = 'You can enable or disable individual checks before running an analysis.';
} elseif (preg_match('/what is a pat|personal access token|pat|why do i need a token|token/i', $q) === 1) {
    $answerLines[] = 'About the PAT:';
    $answerLines[] = 'A PAT is a Personal Access Token that gives the website permission to read repository data from GitHub or GitLab.';
    $answerLines[] = 'The token is used to inspect repository contents and generate the scan results.';
    $answerLines[] = 'Use a token with read access only and keep it private. The app does not need to store it permanently for this workflow.';
} elseif (preg_match('/where do i get a pat|how do i create a pat|create a pat|pat setup|generate a pat/i', $q) === 1) {
    $answerLines[] = 'Creating a PAT:';
    $answerLines[] = 'Open your GitHub or GitLab account settings and look for Developer settings or Access Tokens.';
    $answerLines[] = 'Create a token with read access for repositories and keep it private.';
    $answerLines[] = 'Paste it into the website when you run an analysis.';
} elseif (preg_match('/does it support gitlab|support gitlab|github and gitlab|can i analyze gitlab|gitlab support/i', $q) === 1) {
    $answerLines[] = 'Platform support:';
    $answerLines[] = 'Yes. This website supports both GitHub and GitLab repository URLs.';
    $answerLines[] = 'You can analyze repositories from either platform as long as the URL is valid and the token has access.';
} elseif (preg_match('/is it free|free to use|pricing|cost|is this website free/i', $q) === 1) {
    $answerLines[] = 'Pricing:';
    $answerLines[] = 'This website is intended for local or project use, and access depends on your environment and repository permissions.';
    $answerLines[] = 'If you are running it on your own setup, the main requirements are a working web server, PHP, and a valid token.';
} elseif (preg_match('/how long does (the )?(analysis|scan|repository|repo|check|review) take|how long does it take (for|to) (the )?(analysis|scan|repository|repo|check|review)|analysis time/i', $q) === 1) {
    $answerLines[] = 'Analysis time:';
    $answerLines[] = 'Most scans complete within seconds to a few minutes depending on repository size and the number of checks selected.';
    $answerLines[] = 'Larger repositories with more checks will take longer.';
} elseif (preg_match('/setup|how do i set it up|do i need to install anything|requirements/i', $q) === 1) {
    $answerLines[] = 'Setup:';
    $answerLines[] = 'You need a web server with PHP, access to the project files, and a database if you want full scan history.';
    $answerLines[] = 'For repository analysis, you also need a valid PAT with repository read access.';
} elseif (preg_match('/who should i contact|who can i contact|contact us|contact support|support team|need help|have a question|questions or concerns|who to contact/i', $q) === 1) {
    $answerLines[] = 'Contact support:';
    $answerLines[] = 'If you have questions or concerns, please use the contact page or the contact form on this website.';
    $answerLines[] = 'Your message will be reviewed by the team and they will follow up with you.';
} elseif (preg_match('/how long.*reply|how long.*response|how quickly.*reply|response time|reply back|reply within|respond within|how fast.*reply/i', $q) === 1) {
    $answerLines[] = 'Support response time:';
    $answerLines[] = 'For support or contact messages, replies are usually sent within 1 to 2 business days.';
    $answerLines[] = 'This refers to the time it takes our team to respond to your question, not the time for repository analysis.';
    $answerLines[] = 'If your message is urgent, please mention that so it can be prioritized.';
} elseif (preg_match('/how\s+do\s+i\s+use\s+this\s+website|how\s+to\s+use\s+this\s+website|how\s+to\s+use\s+this\s+site|how\s+do\s+i\s+use\s+this\s+site|how\s+to\s+use\s+this\s+tool|how\s+to\s+use\s+this\s+app/', $q) === 1) {
    $answerLines[] = 'How to use this website:';
    $answerLines[] = '1. Enter a GitHub or GitLab repository URL.';
    $answerLines[] = '2. Enter a Personal Access Token (PAT) with repository read permissions.';
    $answerLines[] = '3. Select the checks you want to run (or keep all selected).';
    $answerLines[] = '4. Click Analyze Repository and wait for scan completion.';
    $answerLines[] = '5. Review score, findings, recommendations, and skills in the results section.';
    $answerLines[] = '6. Open Details on any check to see deeper explanations and evidence.';
    $answerLines[] = '7. Ask Chat Assistant follow-up questions like: What should I fix first?';
} else {
    $answerLines[] = 'Sorry, we are unable to find an answer to your question at the moment.';
    $answerLines[] = 'Please use the contact form to send us your message.';
    $answerLines[] = 'Our team will review your request and assist you further.';
}

echo json_encode([
    'ok' => true,
    'answer' => implode("\n", $answerLines),
], JSON_UNESCAPED_SLASHES);
