<?php

declare(strict_types=1);

/**
 * DevOps Readiness checks (#101-#110).
 */
function check_devops_readiness(
    string $owner,
    string $repo,
    string $pat,
    array $tree,
    string $ruleId
): array {
    $meta = [
        'devops_ci_cd_pipeline' => ['title' => 'CI/CD Pipeline Coverage', 'priority' => 'High'],
        'devops_docker_readiness' => ['title' => 'Docker Build Readiness', 'priority' => 'Medium'],
        'devops_secrets_hygiene' => ['title' => 'Secrets Handling in Pipelines', 'priority' => 'High'],
        'devops_env_configuration' => ['title' => 'Environment Configuration Management', 'priority' => 'Medium'],
        'devops_release_workflow' => ['title' => 'Release Workflow Automation', 'priority' => 'Medium'],
        'devops_actions_security' => ['title' => 'GitHub Actions Security Hardening', 'priority' => 'High'],
        'devops_branch_pr_signals' => ['title' => 'Pull Request and Branch Quality Gates', 'priority' => 'Medium'],
        'devops_deployment_automation' => ['title' => 'Deployment Automation Signals', 'priority' => 'Medium'],
        'devops_observability_ops' => ['title' => 'Operational Observability Hooks', 'priority' => 'Low'],
        'devops_incident_recovery_docs' => ['title' => 'Runbook and Recovery Documentation', 'priority' => 'Low'],
    ];

    if (!isset($meta[$ruleId])) {
        return ['findings' => [], 'recommendations' => [], 'skills' => []];
    }

    $snapshot = devops_readiness_collect($owner, $repo, $pat, $tree);
    $title = $meta[$ruleId]['title'];
    $findings = [];
    $recommendations = [];

    switch ($ruleId) {
        case 'devops_ci_cd_pipeline':
            if ($snapshot['workflow_count'] === 0) {
                $findings[] = devops_readiness_finding(
                    $title,
                    'No CI/CD workflow files detected',
                    'No .github/workflows YAML file was found. Build, test, and release automation coverage is unclear.',
                    'High'
                );
            }
            break;

        case 'devops_docker_readiness':
            if (!$snapshot['has_dockerfile']) {
                $findings[] = devops_readiness_finding(
                    $title,
                    'Docker build definition is missing',
                    'No Dockerfile was found. Containerized build and deployment consistency may be limited.',
                    'Medium'
                );
            }
            break;

        case 'devops_secrets_hygiene':
            if ($snapshot['has_plaintext_secret_pattern']) {
                $findings[] = devops_readiness_finding(
                    $title,
                    'Potential plaintext secrets in automation files',
                    'Workflow or deployment files include token/password-like patterns that should be managed through secret stores.',
                    'High'
                );
            }
            break;

        case 'devops_env_configuration':
            if (!$snapshot['has_env_template']) {
                $findings[] = devops_readiness_finding(
                    $title,
                    'Environment variable template not detected',
                    'No .env.example/.env.sample style template file was found for runtime configuration guidance.',
                    'Low'
                );
            }
            break;

        case 'devops_release_workflow':
            if (!$snapshot['has_release_workflow']) {
                $findings[] = devops_readiness_finding(
                    $title,
                    'Release workflow automation is limited',
                    'No workflow evidence for release/tag-based automation was detected.',
                    'Medium'
                );
            }
            break;

        case 'devops_actions_security':
            if ($snapshot['unpinned_actions_count'] > 0) {
                $findings[] = devops_readiness_finding(
                    $title,
                    'GitHub Actions are not fully pinned',
                    $snapshot['unpinned_actions_count'] . ' action reference(s) use mutable versions instead of commit SHA pinning.',
                    'Medium'
                );
            }
            break;

        case 'devops_branch_pr_signals':
            if (!$snapshot['has_pr_gate_signal']) {
                $findings[] = devops_readiness_finding(
                    $title,
                    'PR quality gate signals are weak',
                    'No clear pull_request trigger or CODEOWNERS signal was detected for branch quality controls.',
                    'Low'
                );
            }
            break;

        case 'devops_deployment_automation':
            if (!$snapshot['has_deploy_signal']) {
                $findings[] = devops_readiness_finding(
                    $title,
                    'Deployment automation signal not found',
                    'No deployment-oriented workflow keywords (deploy, helm, kubernetes, publish) were detected.',
                    'Medium'
                );
            }
            break;

        case 'devops_observability_ops':
            if (!$snapshot['has_observability_signal']) {
                $findings[] = devops_readiness_finding(
                    $title,
                    'Operational observability hooks are unclear',
                    'Repository signals for monitoring/alerting/log aggregation were not detected in workflow or docs sample.',
                    'Low'
                );
            }
            break;

        case 'devops_incident_recovery_docs':
            if (!$snapshot['has_recovery_docs']) {
                $findings[] = devops_readiness_finding(
                    $title,
                    'Runbook or recovery documentation missing',
                    'No incident/runbook/disaster-recovery style documentation file was detected.',
                    'Low'
                );
            }
            break;
    }

    if (!empty($findings)) {
        $recommendations[] = [
            'recommendation_text' => 'Apply GitHub Actions best practices for this DevOps area and verify in CI on every pull request.',
            'priority' => $meta[$ruleId]['priority'],
        ];
    }

    return ['findings' => $findings, 'recommendations' => $recommendations, 'skills' => []];
}

function devops_readiness_collect(string $owner, string $repo, string $pat, array $tree): array
{
    static $cache = [];
    $cacheKey = $owner . '/' . $repo;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $workflowFiles = [];
    foreach ($tree as $node) {
        if (($node['type'] ?? '') !== 'blob') {
            continue;
        }
        $path = (string) ($node['path'] ?? '');
        if (preg_match('/^\.github\/workflows\/.*\.(yml|yaml)$/i', $path) === 1) {
            $workflowFiles[] = $path;
        }
    }

    $dockerfilePath = tree_find_file($tree, 'Dockerfile');
    $composePath = tree_find_file($tree, 'docker-compose.yml');
    $envExamplePath = tree_find_file($tree, '.env.example') ?? tree_find_file($tree, '.env.sample');
    $codeownersPath = tree_find_file($tree, 'CODEOWNERS');
    $runbookPath = devops_find_path_match($tree, '/(runbook|incident|disaster[-_ ]?recovery)/i');

    $workflowBlob = '';
    $unpinnedActions = 0;
    $hasPlaintextSecretPattern = false;
    foreach ($workflowFiles as $workflowPath) {
        $content = github_get_file_content($owner, $repo, $pat, $workflowPath);
        if (!is_string($content) || $content === '') {
            continue;
        }
        $workflowBlob .= "\n" . $content;

        if (preg_match_all('/^\s*uses\s*:\s*[^\n@]+@([^\n\r]+)/mi', $content, $matches) === 1 && isset($matches[1])) {
            foreach ($matches[1] as $ref) {
                $trimmedRef = trim((string) $ref);
                if (preg_match('/^[0-9a-f]{40}$/i', $trimmedRef) !== 1) {
                    $unpinnedActions++;
                }
            }
        }

        if (preg_match('/(password|token|secret|api[_-]?key)\s*:\s*["\'][^"\']{6,}["\']/i', $content) === 1) {
            $hasPlaintextSecretPattern = true;
        }
    }

    $docsSample = '';
    $readmePath = tree_find_file($tree, 'README.md');
    if (is_string($readmePath) && $readmePath !== '') {
        $readmeContent = github_get_file_content($owner, $repo, $pat, $readmePath);
        if (is_string($readmeContent)) {
            $docsSample = $readmeContent;
        }
    }

    $textCorpus = strtolower($workflowBlob . "\n" . $docsSample);

    $snapshot = [
        'workflow_count' => count($workflowFiles),
        'has_dockerfile' => is_string($dockerfilePath) && $dockerfilePath !== '',
        'has_compose' => is_string($composePath) && $composePath !== '',
        'has_env_template' => is_string($envExamplePath) && $envExamplePath !== '',
        'has_release_workflow' => preg_match('/release|tag|semantic[-_ ]?version/i', $workflowBlob) === 1,
        'unpinned_actions_count' => $unpinnedActions,
        'has_pr_gate_signal' => (preg_match('/pull_request/i', $workflowBlob) === 1) || (is_string($codeownersPath) && $codeownersPath !== ''),
        'has_deploy_signal' => preg_match('/deploy|helm|kubernetes|publish/i', $workflowBlob) === 1,
        'has_observability_signal' => preg_match('/monitor|observability|alert|telemetry|logging/i', $textCorpus) === 1,
        'has_recovery_docs' => is_string($runbookPath) && $runbookPath !== '',
        'has_plaintext_secret_pattern' => $hasPlaintextSecretPattern,
    ];

    $cache[$cacheKey] = $snapshot;
    return $snapshot;
}

function devops_find_path_match(array $tree, string $regex): ?string
{
    foreach ($tree as $node) {
        if (($node['type'] ?? '') !== 'blob') {
            continue;
        }
        $path = (string) ($node['path'] ?? '');
        if (preg_match($regex, $path) === 1) {
            return $path;
        }
    }

    return null;
}

function devops_readiness_finding(string $title, string $summary, string $description, string $severity): array
{
    return [
        'category' => 'DevOps',
        'title' => $title . ': ' . $summary,
        'description' => $description,
        'severity' => $severity,
    ];
}
