<?php

declare(strict_types=1);

// Per-request in-memory cache for fetched file content
$_github_content_cache = [];

function github_get(string $url, string $pat, int $timeout = 20): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/vnd.github+json',
            "Authorization: Bearer {$pat}",
            'User-Agent: ai-git-repo-analyzer',
        ],
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $code >= 400) {
        return null;
    }
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}

function github_get_tree(string $owner, string $repo, string $pat, string $branch = 'HEAD'): array
{
    $data = github_get(
        "https://api.github.com/repos/{$owner}/{$repo}/git/trees/{$branch}?recursive=1",
        $pat,
        30
    );
    return ($data && isset($data['tree'])) ? $data['tree'] : [];
}

function github_get_file_content(string $owner, string $repo, string $path, string $pat): ?string
{
    global $_github_content_cache;
    $key = "{$owner}/{$repo}/{$path}";
    if (array_key_exists($key, $_github_content_cache)) {
        return $_github_content_cache[$key];
    }

    $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
    $data = github_get(
        "https://api.github.com/repos/{$owner}/{$repo}/contents/{$encodedPath}",
        $pat,
        15
    );
    $content = ($data && isset($data['content']))
        ? base64_decode(str_replace("\n", '', $data['content']))
        : null;

    $_github_content_cache[$key] = $content;
    return $content;
}

function github_get_commits(string $owner, string $repo, string $pat, int $perPage = 50): array
{
    $data = github_get(
        "https://api.github.com/repos/{$owner}/{$repo}/commits?per_page={$perPage}",
        $pat,
        20
    );
    return is_array($data) ? $data : [];
}

function github_get_languages(string $owner, string $repo, string $pat): array
{
    $data = github_get("https://api.github.com/repos/{$owner}/{$repo}/languages", $pat, 10);
    return is_array($data) ? $data : [];
}

/**
 * Filter tree blobs by extension, sorted by size ascending, up to $limit.
 */
function tree_files_by_extensions(array $tree, array $extensions, int $limit = 25): array
{
    $matches = [];
    foreach ($tree as $node) {
        if ($node['type'] !== 'blob') {
            continue;
        }
        $ext = strtolower(pathinfo($node['path'], PATHINFO_EXTENSION));
        if (in_array($ext, $extensions, true)) {
            $matches[] = $node;
        }
    }
    usort($matches, fn($a, $b) => ($a['size'] ?? 0) <=> ($b['size'] ?? 0));
    return array_slice($matches, 0, $limit);
}

/**
 * Find the first blob in the tree whose basename matches $filename.
 */
function tree_find_file(array $tree, string $filename): ?array
{
    foreach ($tree as $node) {
        if ($node['type'] === 'blob' && basename($node['path']) === $filename) {
            return $node;
        }
    }
    return null;
}

/**
 * Find all blobs whose basename is in $filenames.
 */
function tree_find_files(array $tree, array $filenames): array
{
    $found = [];
    foreach ($tree as $node) {
        if ($node['type'] === 'blob' && in_array(basename($node['path']), $filenames, true)) {
            $found[] = $node;
        }
    }
    return $found;
}
