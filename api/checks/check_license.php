<?php

declare(strict_types=1);

/**
 * Check 7: License Compliance Scanner
 * Detects license files, identifies license type, and warns about copyleft implications.
 */
function check_license(
    string  $owner,
    string  $repo,
    string  $pat,
    array   $tree,
    ?string $repoLicenseName
): array {
    $findings        = [];
    $recommendations = [];

    $licenseFilenames = ['LICENSE', 'LICENSE.md', 'LICENSE.txt', 'LICENSE.rst',
                         'COPYING', 'COPYING.txt', 'LICENCE', 'LICENCE.md'];

    $licenseNode = null;
    foreach ($licenseFilenames as $name) {
        $node = tree_find_file($tree, $name);
        if ($node) {
            $licenseNode = $node;
            break;
        }
    }

    if ($licenseNode === null) {
        $findings[] = [
            'category'    => 'License',
            'title'       => 'No license file found',
            'description' => 'No LICENSE file was found. Without a license all rights are reserved by default — others cannot legally use, copy, modify, or distribute the code.',
            'severity'    => 'High',
        ];
        $recommendations[] = [
            'recommendation_text' => 'Add a LICENSE file. Choose a license at choosealicense.com — MIT or Apache 2.0 for permissive open source; a copyright notice for proprietary code.',
            'priority'            => 'High',
        ];
        return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
    }

    $licenseContent = github_get_file_content($owner, $repo, $licenseNode['path'], $pat);
    $detected       = detect_license_type($licenseContent ?? '');
    $githubLabel    = $repoLicenseName ? " (GitHub identifies it as: {$repoLicenseName})" : '';

    $findings[] = [
        'category'    => 'License',
        'title'       => "License present: {$detected}",
        'description' => "License file `{$licenseNode['path']}` found. Detected type: {$detected}{$githubLabel}.",
        'severity'    => 'Low',
    ];

    // Warn about copyleft licenses
    $copyleftFlags = ['GPL', 'AGPL', 'LGPL', 'EUPL', 'CDDL'];
    foreach ($copyleftFlags as $cl) {
        if (stripos($detected, $cl) !== false) {
            $findings[] = [
                'category'    => 'License',
                'title'       => "Copyleft license ({$cl}) — review implications",
                'description' => "The {$cl} license requires derivative works to also be released under {$cl}. Verify this is intentional, especially if the code is used as a library in commercial software.",
                'severity'    => 'Medium',
            ];
            $recommendations[] = [
                'recommendation_text' => "Review {$cl} copyleft obligations before incorporating this code into commercial or proprietary products. Consider a dual-licensing model if appropriate.",
                'priority'            => 'Medium',
            ];
            break;
        }
    }

    // Check for missing license headers in source files (quick heuristic)
    $hasReadme = tree_find_file($tree, 'README.md') || tree_find_file($tree, 'README.txt') || tree_find_file($tree, 'README');
    if (!$hasReadme) {
        $findings[] = [
            'category'    => 'License',
            'title'       => 'No README file found',
            'description' => 'The repository has no README. A README should document the project purpose, setup instructions, and usage — and typically references the license.',
            'severity'    => 'Medium',
        ];
        $recommendations[] = [
            'recommendation_text' => 'Add a README.md that describes the project, how to install/use it, and links to the LICENSE.',
            'priority'            => 'Medium',
        ];
    }

    if (empty($recommendations)) {
        $recommendations[] = [
            'recommendation_text' => 'License is present and identified. Ensure all contributors are aware of the license terms and consider adding a CONTRIBUTORS file.',
            'priority'            => 'Low',
        ];
    }

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}

function detect_license_type(string $content): string
{
    $up = strtoupper($content);
    if (str_contains($up, 'MIT LICENSE') || str_contains($up, 'PERMISSION IS HEREBY GRANTED')) {
        return 'MIT';
    }
    if (str_contains($up, 'APACHE LICENSE')) {
        return 'Apache 2.0';
    }
    if (str_contains($up, 'GNU AFFERO GENERAL PUBLIC LICENSE')) {
        return 'AGPL v3';
    }
    if (str_contains($up, 'GNU GENERAL PUBLIC LICENSE') && str_contains($up, 'VERSION 3')) {
        return 'GPL v3';
    }
    if (str_contains($up, 'GNU GENERAL PUBLIC LICENSE') && str_contains($up, 'VERSION 2')) {
        return 'GPL v2';
    }
    if (str_contains($up, 'GNU LESSER GENERAL PUBLIC LICENSE')) {
        return 'LGPL';
    }
    if (str_contains($up, 'MOZILLA PUBLIC LICENSE')) {
        return 'MPL 2.0';
    }
    if (str_contains($up, 'BSD 3-CLAUSE') || str_contains($up, 'NEITHER THE NAME')) {
        return 'BSD 3-Clause';
    }
    if (str_contains($up, 'BSD 2-CLAUSE') || str_contains($up, 'REDISTRIBUTION AND USE')) {
        return 'BSD 2-Clause';
    }
    if (str_contains($up, 'ISC LICENSE') || str_contains($up, 'ISC LICENCE')) {
        return 'ISC';
    }
    if (str_contains($up, 'CREATIVE COMMONS')) {
        return 'Creative Commons';
    }
    if (str_contains($up, 'UNLICENSE') || str_contains($up, 'PUBLIC DOMAIN')) {
        return 'Unlicense / Public Domain';
    }
    return 'Unknown / Custom';
}
