<?php

declare(strict_types=1);

/**
 * Check 5: Repo File Type & Size Summary
 * Inventories all files by extension, flags large blobs, and builds the language skills list.
 */
function check_file_summary(array $tree, array $languages): array
{
    $findings        = [];
    $recommendations = [];
    $skills          = [];

    $binaryExts  = ['jpg', 'jpeg', 'png', 'gif', 'ico', 'bmp', 'webp', 'svg',
                     'pdf', 'zip', 'tar', 'gz', 'rar', '7z', 'exe', 'dll', 'so',
                     'dylib', 'woff', 'woff2', 'ttf', 'eot', 'otf',
                     'mp3', 'mp4', 'avi', 'mov', 'mkv', 'psd', 'ai', 'sketch'];

    $extCounts   = [];
    $extSizes    = [];
    $largeFiles  = [];
    $totalSize   = 0;
    $blobCount   = 0;

    foreach ($tree as $node) {
        if ($node['type'] !== 'blob') {
            continue;
        }
        $blobCount++;
        $size       = $node['size'] ?? 0;
        $totalSize += $size;
        $ext        = strtolower(pathinfo($node['path'], PATHINFO_EXTENSION)) ?: 'no-ext';

        $extCounts[$ext] = ($extCounts[$ext] ?? 0) + 1;
        $extSizes[$ext]  = ($extSizes[$ext] ?? 0) + $size;

        if ($size > 1024 * 1024) { // > 1 MB
            $largeFiles[] = ['path' => $node['path'], 'size' => $size];
        }
    }

    arsort($extCounts);
    $top5     = array_slice($extCounts, 0, 5, true);
    $extLabel = implode(', ', array_map(fn($e, $c) => ".{$e} ({$c})", array_keys($top5), $top5));
    $totalKb  = round($totalSize / 1024, 1);

    $findings[] = [
        'category'    => 'File Summary',
        'title'       => "Repository: {$blobCount} files, {$totalKb} KB total",
        'description' => "Top file types by count: {$extLabel}.",
        'severity'    => 'Low',
    ];

    // Flag large files (>1 MB)
    usort($largeFiles, fn($a, $b) => $b['size'] <=> $a['size']);
    foreach (array_slice($largeFiles, 0, 5) as $lf) {
        $sizeMb = round($lf['size'] / (1024 * 1024), 2);
        $findings[] = [
            'category'    => 'File Summary',
            'title'       => "Large binary file: {$lf['path']} ({$sizeMb} MB)",
            'description' => "`{$lf['path']}` is {$sizeMb} MB. Binary assets over 1 MB bloat the clone size. Store them with Git LFS or a CDN.",
            'severity'    => 'Medium',
        ];
    }

    // Warn about many binary files of a single type
    foreach ($extCounts as $ext => $count) {
        if (in_array($ext, $binaryExts, true) && $count > 15) {
            $findings[] = [
                'category'    => 'File Summary',
                'title'       => "Many binary assets: {$count} .{$ext} files",
                'description' => "The repository contains {$count} .{$ext} files. Binary assets bloat repository size and slow down clones. Consider using Git LFS or hosting them externally.",
                'severity'    => 'Low',
            ];
        }
    }

    // Build skills list from GitHub Languages API response
    $langTotal = array_sum(array_values($languages));
    foreach ($languages as $lang => $bytes) {
        $pct     = $langTotal > 0 ? round($bytes / $langTotal * 100) : 0;
        $level   = $pct >= 50 ? 'Primary Language' : ($pct >= 20 ? 'Secondary Language' : 'Minor Language');
        $skills[] = [
            'skill_name'        => "{$lang} ({$pct}%)",
            'proficiency_level' => $level,
            'risk_level'        => 'Low',
        ];
    }

    if (!empty($largeFiles)) {
        $recommendations[] = [
            'recommendation_text' => 'Enable Git LFS (`git lfs track "*.ext"`) for large binary files to keep the repository lean and clone times fast.',
            'priority'            => 'Medium',
        ];
    }

    $recommendations[] = [
        'recommendation_text' => 'Add a .gitattributes file to define line-ending normalisation (text=auto) and mark binary files explicitly, preventing cross-platform diff noise.',
        'priority'            => 'Low',
    ];

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => $skills];
}
