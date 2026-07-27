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
} elseif (preg_match('/score|why.*low|improve.*score|increase.*score/', $q) === 1) {
    $answerLines[] = 'Current quality score summary:';
    if ($score !== null) {
        $answerLines[] = '- Score: ' . $score . '/100';
    }
    $answerLines[] = '- Findings: High ' . $severityCounts['High'] . ', Medium ' . $severityCounts['Medium'] . ', Low ' . $severityCounts['Low'] . ', Info ' . $severityCounts['Info'];
    $answerLines[] = 'To improve score fastest, reduce High findings first, then Medium findings.';
} elseif (preg_match('/devops|ci|cd|pipeline|deploy|workflow/', $q) === 1) {
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
    $answerLines[] = 'Quick scan summary:';
    if ($score !== null) {
        $answerLines[] = '- Score: ' . $score . '/100';
    }
    $answerLines[] = '- Checks executed: ' . count($checks);
    $answerLines[] = '- Findings: ' . count($findings);
    $answerLines[] = 'Try asking: "What should I fix first?", "How do I improve score?", or "Show dependency risks".';
}

echo json_encode([
    'ok' => true,
    'answer' => implode("\n", $answerLines),
], JSON_UNESCAPED_SLASHES);
