<?php

declare(strict_types=1);

/**
 * Check 9: Code Duplication Detector
 * Detects repeated code blocks using a rolling hash over meaningful (non-blank, non-comment) lines.
 */
function check_duplication(string $owner, string $repo, string $pat, array $sourceFiles): array
{
    $findings        = [];
    $recommendations = [];

    $blockSize = 6;    // consecutive meaningful lines per block
    $minHits   = 2;    // blocks must appear at least this many times to be reported

    $blockIndex = []; // hash => ['path' => ..., 'line' => ...]
    $dupPairs   = []; // "path1::path2" => count

    foreach ($sourceFiles as $fileNode) {
        $path    = $fileNode['path'];
        $content = github_get_file_content($owner, $repo, $path, $pat);
        if ($content === null) {
            continue;
        }

        // Keep only meaningful lines (non-empty, non-comment, non-brace-only)
        $meaningful = [];
        foreach (explode("\n", $content) as $i => $line) {
            $trimmed = trim($line);
            if ($trimmed === ''
                || preg_match('#^(?://|/\*|\*|#\s|<!--|--\s|;$)#', $trimmed)
                || in_array($trimmed, ['{', '}', '};', '},'], true)) {
                continue;
            }
            $meaningful[] = ['original_line' => $i + 1, 'text' => $trimmed];
        }

        $count = count($meaningful);
        for ($i = 0; $i <= $count - $blockSize; $i++) {
            $block = implode("\n", array_column(array_slice($meaningful, $i, $blockSize), 'text'));
            $hash  = md5($block);
            $line  = $meaningful[$i]['original_line'];

            if (isset($blockIndex[$hash])) {
                $orig   = $blockIndex[$hash];
                $pairKey = $orig['path'] . '::' . $path;
                if (!isset($dupPairs[$pairKey])) {
                    $dupPairs[$pairKey] = [
                        'path1' => $orig['path'],
                        'line1' => $orig['line'],
                        'path2' => $path,
                        'line2' => $line,
                        'count' => 0,
                    ];
                }
                $dupPairs[$pairKey]['count']++;
            } else {
                $blockIndex[$hash] = ['path' => $path, 'line' => $line];
            }
        }
    }

    // Sort by duplication count descending; report top 5
    usort($dupPairs, fn($a, $b) => $b['count'] <=> $a['count']);
    $reported = 0;
    foreach ($dupPairs as $dup) {
        if ($dup['count'] < $minHits || $reported >= 5) {
            continue;
        }
        $findings[] = [
            'category'    => 'Duplication',
            'title'       => "Duplicated code: {$dup['path1']} ↔ {$dup['path2']}",
            'description' => "{$dup['count']} repeated {$blockSize}-line blocks between `{$dup['path1']}` (line {$dup['line1']}) and `{$dup['path2']}` (line {$dup['line2']}). Extract shared logic into a reusable function or module.",
            'severity'    => 'Low',
        ];
        $reported++;
    }

    if (!empty($findings)) {
        $recommendations[] = [
            'recommendation_text' => 'Extract duplicated blocks into shared utility functions. Add jscpd or phpcpd to CI to prevent future duplication from going unnoticed.',
            'priority'            => 'Medium',
        ];
    } else {
        $recommendations[] = [
            'recommendation_text' => 'No significant code duplication found in sampled files. Add a copy-paste detector (jscpd) to CI to prevent future regressions.',
            'priority'            => 'Low',
        ];
    }

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}
