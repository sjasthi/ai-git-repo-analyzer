<?php

declare(strict_types=1);

// Per-request in-memory cache for fetched file content
$_repo_content_cache = [];

function repo_set_context(array $context): void
{
    $provider = strtolower((string) ($context['provider'] ?? 'github'));
    if (!in_array($provider, ['github', 'gitlab'], true)) {
        $provider = 'github';
    }

    $GLOBALS['_repo_context'] = [
        'provider' => $provider,
        'owner' => (string) ($context['owner'] ?? ''),
        'repo' => (string) ($context['repo'] ?? ''),
        'pat' => (string) ($context['pat'] ?? ''),
    ];
}

function repo_get_context(): array
{
    if (!isset($GLOBALS['_repo_context']) || !is_array($GLOBALS['_repo_context'])) {
        $GLOBALS['_repo_context'] = [
            'provider' => 'github',
            'owner' => '',
            'repo' => '',
            'pat' => '',
        ];
    }

    return $GLOBALS['_repo_context'];
}

function repo_http_get_json(string $url, string $pat, array $headers, int $timeout = 20): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
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

function github_get(string $url, string $pat, int $timeout = 20): ?array
{
    $context = repo_get_context();
    $provider = $context['provider'];

    if ($provider === 'gitlab') {
        $headers = [
            'Accept: application/json',
            'PRIVATE-TOKEN: ' . $pat,
            'User-Agent: ai-git-repo-analyzer',
        ];
        return repo_http_get_json($url, $pat, $headers, $timeout);
    }

    $headers = [
        'Accept: application/vnd.github+json',
        'Authorization: Bearer ' . $pat,
        'User-Agent: ai-git-repo-analyzer',
    ];
    return repo_http_get_json($url, $pat, $headers, $timeout);
}

function github_get_tree(string $owner, string $repo, string $pat, string $branch = 'HEAD'): array
{
    $context = repo_get_context();
    $provider = $context['provider'];

    if ($provider === 'gitlab') {
        $projectPath = rawurlencode($owner . '/' . $repo);
        $ref = rawurlencode($branch !== '' ? $branch : 'HEAD');
        $data = github_get(
            'https://gitlab.com/api/v4/projects/' . $projectPath . '/repository/tree?recursive=true&per_page=1000&ref=' . $ref,
            $pat,
            30
        );

        if (!is_array($data)) {
            return [];
        }

        $tree = [];
        foreach ($data as $node) {
            if (!is_array($node) || (string) ($node['type'] ?? '') !== 'blob') {
                continue;
            }
            $path = (string) ($node['path'] ?? '');
            if ($path === '') {
                continue;
            }
            $tree[] = [
                'type' => 'blob',
                'path' => $path,
                'size' => 0,
            ];
        }
        return $tree;
    }

    $data = github_get(
        'https://api.github.com/repos/' . $owner . '/' . $repo . '/git/trees/' . $branch . '?recursive=1',
        $pat,
        30
    );
    return ($data && isset($data['tree']) && is_array($data['tree'])) ? $data['tree'] : [];
}

function github_get_file_content(string $owner, string $repo, string $path, string $pat): ?string
{
    global $_repo_content_cache;

    $context = repo_get_context();
    $provider = $context['provider'];
    $key = $provider . ':' . $owner . '/' . $repo . '/' . $path;

    if (array_key_exists($key, $_repo_content_cache)) {
        return $_repo_content_cache[$key];
    }

    if ($provider === 'gitlab') {
        $projectPath = rawurlencode($owner . '/' . $repo);
        $encodedPath = rawurlencode($path);
        $data = github_get(
            'https://gitlab.com/api/v4/projects/' . $projectPath . '/repository/files/' . $encodedPath,
            $pat,
            20
        );
        $content = ($data && isset($data['content']))
            ? base64_decode(str_replace("\n", '', (string) $data['content']), true)
            : null;

        $_repo_content_cache[$key] = $content === false ? null : $content;
        return $_repo_content_cache[$key];
    }

    $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
    $data = github_get(
        'https://api.github.com/repos/' . $owner . '/' . $repo . '/contents/' . $encodedPath,
        $pat,
        15
    );
    $content = ($data && isset($data['content']))
        ? base64_decode(str_replace("\n", '', (string) $data['content']), true)
        : null;

    $_repo_content_cache[$key] = $content === false ? null : $content;
    return $_repo_content_cache[$key];
}

function github_get_commits(string $owner, string $repo, string $pat, int $perPage = 50): array
{
    $context = repo_get_context();
    $provider = $context['provider'];

    if ($provider === 'gitlab') {
        $projectPath = rawurlencode($owner . '/' . $repo);
        $data = github_get(
            'https://gitlab.com/api/v4/projects/' . $projectPath . '/repository/commits?per_page=' . $perPage,
            $pat,
            20
        );
        return is_array($data) ? $data : [];
    }

    $data = github_get(
        'https://api.github.com/repos/' . $owner . '/' . $repo . '/commits?per_page=' . $perPage,
        $pat,
        20
    );
    return is_array($data) ? $data : [];
}

function github_get_languages(string $owner, string $repo, string $pat): array
{
    $context = repo_get_context();
    $provider = $context['provider'];

    if ($provider === 'gitlab') {
        $projectPath = rawurlencode($owner . '/' . $repo);
        $data = github_get(
            'https://gitlab.com/api/v4/projects/' . $projectPath . '/languages',
            $pat,
            15
        );

        if (!is_array($data)) {
            return [];
        }

        $out = [];
        foreach ($data as $lang => $pct) {
            $name = (string) $lang;
            $percent = (float) $pct;
            $out[$name] = (int) round($percent * 1000);
        }
        return $out;
    }

    $data = github_get('https://api.github.com/repos/' . $owner . '/' . $repo . '/languages', $pat, 10);
    return is_array($data) ? $data : [];
}

/**
 * Filter tree blobs by extension, sorted by size ascending, up to $limit.
 */
function tree_files_by_extensions(array $tree, array $extensions, int $limit = 25): array
{
    $matches = [];
    foreach ($tree as $node) {
        if (($node['type'] ?? '') !== 'blob') {
            continue;
        }
        $ext = strtolower(pathinfo((string) ($node['path'] ?? ''), PATHINFO_EXTENSION));
        if (in_array($ext, $extensions, true)) {
            $matches[] = $node;
        }
    }
    usort($matches, fn($a, $b) => ((int) ($a['size'] ?? 0)) <=> ((int) ($b['size'] ?? 0)));
    return array_slice($matches, 0, $limit);
}

/**
 * Find the first blob in the tree whose basename matches $filename.
 */
function tree_find_file(array $tree, string $filename): ?array
{
    foreach ($tree as $node) {
        if (($node['type'] ?? '') === 'blob' && basename((string) ($node['path'] ?? '')) === $filename) {
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
        if (($node['type'] ?? '') === 'blob' && in_array(basename((string) ($node['path'] ?? '')), $filenames, true)) {
            $found[] = $node;
        }
    }
    return $found;
}
