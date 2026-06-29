<?php

declare(strict_types=1);

/**
 * Check 6: Dead Code & TODO/FIXME Tracker
 * Counts TODO, FIXME, HACK, BUG, DEPRECATED, and similar annotations across source files.
 */
function check_todos(string $owner, string $repo, string $pat, array $sourceFiles): array
{
    $findings        = [];
    $recommendations = [];

    $tags    = ['TODO', 'FIXME', 'HACK', 'BUG', 'DEPRECATED', 'XXX', 'TEMP', 'KLUDGE', 'NOSONAR'];
    $pattern = '/\b(' . implode('|', $tags) . ')\b[:\s]*(.*)/i';

    $tagCounts = array_fill_keys($tags, 0);
    $examples  = []; // first hit per high-priority tag

    foreach ($sourceFiles as $fileNode) {
        $path    = $fileNode['path'];
        $content = github_get_file_content($owner, $repo, $path, $pat);
        if ($content === null) {
            continue;
        }

        foreach (explode("\n", $content) as $lineIdx => $line) {
            if (!preg_match($pattern, $line, $m)) {
                continue;
            }
            $tag = strtoupper($m[1]);
            $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;

            if (in_array($tag, ['FIXME', 'BUG', 'HACK'], true) && count($examples) < 5) {
                $examples[] = [
                    'tag'  => $tag,
                    'path' => $path,
                    'line' => $lineIdx + 1,
                    'text' => trim(substr($line, 0, 120)),
                ];
            }
        }
    }

    $totalTags   = array_sum($tagCounts);
    $presentTags = array_filter($tagCounts, fn($v) => $v > 0);

    if ($totalTags > 0) {
        $tagSummary = implode(', ', array_map(fn($t, $c) => "{$t}: {$c}", array_keys($presentTags), $presentTags));
        $findings[] = [
            'category'    => 'Code Quality',
            'title'       => "{$totalTags} technical debt annotations found",
            'description' => "Source files contain: {$tagSummary}. These represent known issues or incomplete work that should be tracked as issues.",
            'severity'    => $totalTags > 20 ? 'Medium' : 'Low',
        ];

        // Surface individual high-severity annotations
        foreach ($examples as $ex) {
            $findings[] = [
                'category'    => 'Code Quality',
                'title'       => "{$ex['tag']} in {$ex['path']}:{$ex['line']}",
                'description' => "Flagged code: `{$ex['text']}`",
                'severity'    => $ex['tag'] === 'BUG' ? 'Medium' : 'Low',
            ];
        }

        $recommendations[] = [
            'recommendation_text' => "Convert the {$totalTags} TODO/FIXME/BUG comments into tracked GitHub Issues so they appear in the backlog and are not forgotten.",
            'priority'            => $totalTags > 20 ? 'Medium' : 'Low',
        ];
    } else {
        $recommendations[] = [
            'recommendation_text' => 'No TODO/FIXME annotations found in sampled files. Maintain this discipline and use issue trackers for all known problems.',
            'priority'            => 'Low',
        ];
    }

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}
