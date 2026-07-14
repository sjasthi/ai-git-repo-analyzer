<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

$checkIdRaw = trim((string) ($_GET['check_id'] ?? '1'));
$checkId = preg_match('/^(30|2[0-9]|10|[1-9])$/', $checkIdRaw) === 1 ? $checkIdRaw : '1';
$checkNameFromQuery = trim((string) ($_GET['name'] ?? ''));
$statusRaw = strtolower(trim((string) ($_GET['status'] ?? '')));
$countRaw = isset($_GET['count']) ? (int) $_GET['count'] : null;
$scanId = (int) ($_GET['scan_id'] ?? 0);

$checkContent = [
    '1' => [
        'title' => '#1 Insecure Design and Logic Flaws',
        'tag' => 'OWASP A04 Focus',
        'about' => 'This check looks for risky application design choices and logic mistakes that can be abused. It focuses on secure-by-design patterns for authorization, validation, and critical business rules.',
        'looks_for' => [
            'Missing authorization checks around sensitive actions.',
            'Business-logic bypass opportunities in workflows.',
            'Weak server-side validation on critical fields.',
            'Client-side values trusted for security decisions.',
            'Privilege escalation paths through inconsistent role checks.',
        ],
        'recommendations' => [
            'Enforce server-side authorization for every sensitive action and endpoint.',
            'Validate business workflow states on the backend, not only in client logic.',
            'Add abuse-case tests for privilege escalation and logic bypass scenarios.',
        ],
        'why' => 'Logic flaws can survive clean code reviews; this check helps catch structural weaknesses early.',
    ],
    '2' => [
        'title' => '#2 Vulnerable and Outdated Dependencies',
        'tag' => 'OWASP A06 Focus',
        'about' => 'This check evaluates third-party packages and runtime components for known vulnerabilities and outdated versions.',
        'looks_for' => [
            'Dependency versions with known CVEs.',
            'Unsupported or end-of-life libraries.',
            'Missing lock files or weak version pinning.',
            'High-risk transitive dependencies.',
            'Package sources and update hygiene risks.',
        ],
        'recommendations' => [
            'Upgrade vulnerable dependencies and pin safe minimum versions.',
            'Use lock files and automated dependency update checks in CI.',
            'Review transitive dependencies and remove unused packages.',
        ],
        'why' => 'Supply-chain issues can compromise secure codebases if vulnerable packages are left unpatched.',
    ],
    '3' => [
        'title' => '#3 CI/CD and Software Integrity Risks',
        'tag' => 'OWASP A08 Focus',
        'about' => 'This check reviews automation and build/release settings that could allow tampering or insecure deployments.',
        'looks_for' => [
            'Over-privileged pipeline tokens and secrets exposure.',
            'Untrusted workflow triggers and unsafe scripts.',
            'Missing integrity verification for build artifacts.',
            'Lack of branch protection or release gating.',
            'Weak provenance and auditability of deployments.',
        ],
        'recommendations' => [
            'Restrict CI/CD token permissions to least privilege.',
            'Harden workflow triggers and require reviews for release pipelines.',
            'Add artifact signing or integrity verification before deploy.',
        ],
        'why' => 'Pipeline weaknesses can let attackers inject malicious code into otherwise healthy repositories.',
    ],
    '4' => [
        'title' => '#4 Logging and Monitoring Coverage',
        'tag' => 'OWASP A09 Focus',
        'about' => 'This check estimates whether important security and runtime events are logged and can be monitored.',
        'looks_for' => [
            'Missing logs around auth failures and access denial.',
            'Insufficient audit trails for sensitive actions.',
            'Errors swallowed without alerting paths.',
            'No signs of centralized or structured logging.',
            'Operational blind spots that hinder incident response.',
        ],
        'recommendations' => [
            'Log authentication failures, access denials, and sensitive operations consistently.',
            'Adopt structured logging with severity levels and correlation IDs.',
            'Define alert rules for high-risk runtime and security events.',
        ],
        'why' => 'Without useful telemetry, incidents become harder to detect, investigate, and contain.',
    ],
    '5' => [
        'title' => '#5 Code Quality, Performance and Repo Health',
        'tag' => 'Engineering Quality Focus',
        'about' => 'This check scans repository-level quality signals that affect maintainability, speed, and reliability.',
        'looks_for' => [
            'High-complexity code hotspots.',
            'Code duplication and maintainability debt.',
            'Inefficient patterns likely to impact performance.',
            'Repository hygiene gaps (stale docs, TODO overload).',
            'General structural issues that increase defect risk.',
        ],
        'recommendations' => [
            'Refactor high-complexity and duplicated modules into smaller units.',
            'Address priority TODOs and stale code paths with ownership.',
            'Track code health metrics in CI and block severe regressions.',
        ],
        'why' => 'Poor code health increases delivery risk and can hide security defects over time.',
    ],
    '6' => [
        'title' => '#6 Secret & Credential Scanner',
        'tag' => 'Secrets Hygiene Focus',
        'about' => 'This check detects hardcoded secrets and credential-like patterns in source and configuration files.',
        'looks_for' => [
            'API keys and access tokens committed in code.',
            'Password-like strings in config and scripts.',
            'Private key blocks and sensitive certificate material.',
            'Connection strings with embedded credentials.',
            'Patterns indicating accidental secret leakage.',
        ],
        'recommendations' => [
            'Move secrets to environment variables or a secrets manager.',
            'Rotate exposed credentials and invalidate leaked tokens immediately.',
            'Add pre-commit and CI secret scanning to prevent recurrence.',
        ],
        'why' => 'Exposed credentials are one of the fastest paths to repository and infrastructure compromise.',
    ],
    '7' => [
        'title' => '#7 Dependency CVE Audit (OSV.dev)',
        'tag' => 'CVE Intelligence Focus',
        'about' => 'This check performs a vulnerability audit against public advisories to identify dependency CVEs.',
        'looks_for' => [
            'Packages affected by known CVEs.',
            'Severity and exploitability context where available.',
            'Direct and transitive vulnerable packages.',
            'Version ranges that need immediate upgrades.',
            'Potentially affected ecosystems in lock/manifest files.',
        ],
        'recommendations' => [
            'Patch or replace dependencies affected by high/critical CVEs first.',
            'Create a regular vulnerability triage and remediation cadence.',
            'Document accepted risk and compensating controls for deferred fixes.',
        ],
        'why' => 'Known vulnerabilities provide clear, high-priority remediation targets with measurable risk reduction.',
    ],
    '8' => [
        'title' => '#8 License Compliance Scanner',
        'tag' => 'Legal Compliance Focus',
        'about' => 'This check inspects project and dependency licenses to flag legal or policy conflicts.',
        'looks_for' => [
            'Missing or unknown license declarations.',
            'Copyleft or restricted licenses conflicting with policy.',
            'Mixed license obligations across dependencies.',
            'Potential redistribution/commercial usage constraints.',
            'Repository-level license clarity gaps.',
        ],
        'recommendations' => [
            'Document project and dependency licenses clearly in repository metadata.',
            'Replace dependencies with incompatible licenses where policy requires.',
            'Add automated license policy checks in CI before release.',
        ],
        'why' => 'License issues can block release, distribution, procurement, or commercial usage.',
    ],
    '9' => [
        'title' => '#9 Git History Risk Analysis',
        'tag' => 'Version History Focus',
        'about' => 'This check analyzes commit history for risky patterns that may indicate hidden exposure or governance gaps.',
        'looks_for' => [
            'Secrets that may have existed in historical commits.',
            'Suspicious churn around sensitive files.',
            'Force-push/rewrite indicators when metadata allows.',
            'Large risky changes without clear context.',
            'History patterns that complicate auditing and trust.',
        ],
        'recommendations' => [
            'Purge sensitive artifacts from commit history and rotate impacted secrets.',
            'Enable branch protection and stronger review requirements.',
            'Audit risky commit patterns and document change controls.',
        ],
        'why' => 'Current files can look clean while historical commits still contain sensitive or risky artifacts.',
    ],
    '10' => [
        'title' => '#10 Security Header & Config Auditor',
        'tag' => 'Configuration Hardening Focus',
        'about' => 'This check reviews app and web-server settings for missing hardening controls and weak defaults.',
        'looks_for' => [
            'Missing HTTP security headers (CSP, HSTS, X-Frame-Options).',
            'Weak transport and cookie security settings.',
            'Insecure default config values in deployment files.',
            'Debug or verbose modes enabled in production-like configs.',
            'Mismatch between expected and observed hardening settings.',
        ],
        'recommendations' => [
            'Apply baseline security headers and enforce TLS best practices.',
            'Disable unsafe debug settings in production environments.',
            'Create environment-specific config validation checks in CI/CD.',
        ],
        'why' => 'Config weaknesses are high-impact because they can affect every request and endpoint globally.',
    ],
    '11' => [
        'title' => '#11 Cyclomatic Complexity Average',
        'tag' => 'Complexity Metrics',
        'about' => 'This check evaluates the average branching complexity across detected functions.',
        'looks_for' => [
            'Average decision-path complexity above safe baseline.',
            'Branch-heavy methods that are harder to test.',
            'Growing complexity trend across core modules.',
        ],
        'recommendations' => [
            'Reduce branch chains with guard clauses and early returns.',
            'Split complex methods into smaller focused units.',
            'Track complexity average in CI over time.',
        ],
        'why' => 'High average cyclomatic complexity increases defect risk and testing effort.',
    ],
    '12' => [
        'title' => '#12 Cyclomatic Complexity Maximum',
        'tag' => 'Complexity Metrics',
        'about' => 'This check highlights worst-case cyclomatic hotspots in the repository.',
        'looks_for' => [
            'Outlier functions with very high branch counts.',
            'Single-method hotspots likely to accumulate regressions.',
            'Paths that are difficult to reason about safely.',
        ],
        'recommendations' => [
            'Prioritize refactoring of top cyclomatic hotspots.',
            'Move conditional logic into dedicated helper strategies.',
            'Set hard threshold gates for max cyclomatic score.',
        ],
        'why' => 'Maximum complexity hotspots are common failure points during changes.',
    ],
    '13' => [
        'title' => '#13 Cognitive Complexity Average',
        'tag' => 'Complexity Metrics',
        'about' => 'This check estimates how difficult the average method is to understand.',
        'looks_for' => [
            'Nested and chained logic increasing mental load.',
            'Complexity patterns that slow code reviews.',
            'Average readability decline in core files.',
        ],
        'recommendations' => [
            'Flatten nested logic and simplify conditional flows.',
            'Prefer explicit naming and smaller routines.',
            'Continuously monitor average cognitive complexity in CI.',
        ],
        'why' => 'Higher cognitive complexity reduces maintainability and increases review misses.',
    ],
    '14' => [
        'title' => '#14 Cognitive Complexity Maximum',
        'tag' => 'Complexity Metrics',
        'about' => 'This check identifies the most difficult methods to reason about.',
        'looks_for' => [
            'Deep nesting and heavy branching in single routines.',
            'Control flow that obscures intent and edge cases.',
            'Methods requiring disproportionate review effort.',
        ],
        'recommendations' => [
            'Refactor maximum cognitive hotspots into clear subroutines.',
            'Replace deeply nested branches with composed flows.',
            'Use cognitive thresholds as release quality gates.',
        ],
        'why' => 'Extreme cognitive complexity is strongly associated with fragile code areas.',
    ],
    '15' => [
        'title' => '#15 Function Size Average',
        'tag' => 'Complexity Metrics',
        'about' => 'This check tracks the average function size (LOC) across source files.',
        'looks_for' => [
            'Functions becoming too broad in responsibility.',
            'Rising average function LOC over time.',
            'Long routines that reduce readability and testability.',
        ],
        'recommendations' => [
            'Extract repeated or secondary logic into helper methods.',
            'Align each function to a single responsibility.',
            'Set average LOC targets per module.',
        ],
        'why' => 'Smaller functions are easier to test, review, and maintain.',
    ],
    '16' => [
        'title' => '#16 Function Size Maximum',
        'tag' => 'Complexity Metrics',
        'about' => 'This check detects oversized function outliers that pose maintenance risk.',
        'looks_for' => [
            'Very large methods with mixed concerns.',
            'Functions that require broad context to modify safely.',
            'Outlier LOC hotspots in critical files.',
        ],
        'recommendations' => [
            'Break up oversized functions into cohesive smaller units.',
            'Introduce internal helpers for repeated sequences.',
            'Fail CI when max size exceeds agreed limits.',
        ],
        'why' => 'Large function outliers are a major source of hidden regressions.',
    ],
    '17' => [
        'title' => '#17 Class Size Average',
        'tag' => 'Complexity Metrics',
        'about' => 'This check monitors average class size to control cohesion drift.',
        'looks_for' => [
            'Average class growth beyond maintainable boundaries.',
            'Increasing responsibilities per class.',
            'Signs of bloated abstraction layers.',
        ],
        'recommendations' => [
            'Split classes by domain boundaries and responsibilities.',
            'Prefer composition over centralizing logic into one class.',
            'Review class-size trends in architecture checks.',
        ],
        'why' => 'Lower average class size helps preserve modular architecture and clarity.',
    ],
    '18' => [
        'title' => '#18 Class Size Maximum',
        'tag' => 'Complexity Metrics',
        'about' => 'This check identifies very large class outliers (god classes).',
        'looks_for' => [
            'Classes with excessive LOC and broad responsibilities.',
            'Cross-cutting logic concentrated into one type.',
            'Hotspots hard to test and reason about.',
        ],
        'recommendations' => [
            'Refactor god classes into focused collaborators.',
            'Separate state management from behavior-rich services.',
            'Set maximum class-size thresholds and enforce in CI.',
        ],
        'why' => 'Large class outliers reduce cohesion and slow safe code evolution.',
    ],
    '19' => [
        'title' => '#19 Nesting Depth Average',
        'tag' => 'Complexity Metrics',
        'about' => 'This check tracks average nesting depth in function control flow.',
        'looks_for' => [
            'Deeply nested if/loop constructs across methods.',
            'Average nesting growth that hurts readability.',
            'Control flow structures prone to logical mistakes.',
        ],
        'recommendations' => [
            'Use guard clauses to flatten nested branches.',
            'Extract deeply nested blocks into named methods.',
            'Monitor average nesting depth in quality gates.',
        ],
        'why' => 'Shallow nesting improves comprehension and lowers bug probability.',
    ],
    '20' => [
        'title' => '#20 Nesting Depth Maximum',
        'tag' => 'Complexity Metrics',
        'about' => 'This check highlights the deepest nesting hotspots in the codebase.',
        'looks_for' => [
            'Extreme nesting in individual methods.',
            'Complex blocks with multiple nested branches.',
            'Hard-to-debug hotspots with dense control flow.',
        ],
        'recommendations' => [
            'Refactor deepest nested paths first.',
            'Replace nested branching with explicit strategy decomposition.',
            'Add max nesting depth limits to CI policy.',
        ],
        'why' => 'Maximum nesting hotspots are difficult to maintain and error-prone.',
    ],
    '21' => [
        'title' => '#21 SonarQube Bugs and Reliability Issues',
        'tag' => 'SonarQube Code Quality',
        'about' => 'This check highlights reliability risks and bug-prone implementation patterns that should be fixed first.',
        'looks_for' => [
            'High branching hotspots likely to hide edge-case bugs.',
            'Complex control flow that increases defect probability.',
            'Large methods where reliability regressions are harder to detect.',
        ],
        'recommendations' => [
            'Refactor high-risk methods into smaller, testable units.',
            'Add targeted tests for branch-heavy logic and edge cases.',
            'Set quality gates to block new high-severity reliability issues.',
        ],
        'why' => 'Reliability issues directly affect correctness and production stability.',
    ],
    '22' => [
        'title' => '#22 SonarQube Code Smells and Maintainability Issues',
        'tag' => 'SonarQube Code Quality',
        'about' => 'This check tracks maintainability concerns that make code harder to evolve safely.',
        'looks_for' => [
            'Cognitive complexity hotspots.',
            'Readability issues and inconsistent structure.',
            'Patterns that increase long-term maintenance cost.',
        ],
        'recommendations' => [
            'Reduce cognitive load with clearer control flow and naming.',
            'Break up large routines and repeated decision chains.',
            'Address maintainability findings in each sprint.',
        ],
        'why' => 'Code smells are leading indicators of future defects and delivery slowdown.',
    ],
    '23' => [
        'title' => '#23 SonarQube Duplicated Code Detection',
        'tag' => 'SonarQube Code Quality',
        'about' => 'This check focuses on duplicate logic that increases change risk and inconsistency.',
        'looks_for' => [
            'Repeated blocks across multiple files.',
            'Copy-paste logic with slight variations.',
            'Parallel fixes required for the same bug class.',
        ],
        'recommendations' => [
            'Extract shared utilities or abstractions for repeated logic.',
            'Consolidate near-duplicate routines into a single implementation.',
            'Track duplication trend as part of quality gate checks.',
        ],
        'why' => 'Duplication amplifies maintenance effort and bug propagation risk.',
    ],
    '24' => [
        'title' => '#24 SonarQube Cyclomatic and Cognitive Complexity Limits',
        'tag' => 'SonarQube Code Quality',
        'about' => 'This check enforces complexity boundaries to keep code understandable and testable.',
        'looks_for' => [
            'Methods exceeding cyclomatic complexity limits.',
            'Nested control flows with high cognitive load.',
            'Hotspots requiring disproportionate testing effort.',
        ],
        'recommendations' => [
            'Apply guard clauses and split complex branches.',
            'Refactor deeply nested methods into focused helpers.',
            'Define and enforce complexity thresholds in CI.',
        ],
        'why' => 'Complex code raises defect density and slows safe delivery.',
    ],
    '25' => [
        'title' => '#25 SonarQube Function and Class Size Control',
        'tag' => 'SonarQube Code Quality',
        'about' => 'This check keeps method and class sizes within manageable limits.',
        'looks_for' => [
            'Oversized methods with multiple responsibilities.',
            'God classes with weak cohesion.',
            'Large files that hinder review quality.',
        ],
        'recommendations' => [
            'Extract focused methods and simplify ownership boundaries.',
            'Split large classes by domain responsibility.',
            'Use size thresholds as quality gate criteria.',
        ],
        'why' => 'Smaller units improve readability, testability, and change safety.',
    ],
    '26' => [
        'title' => '#26 SonarQube Naming Convention and Readability Checks',
        'tag' => 'SonarQube Code Quality',
        'about' => 'This check evaluates clarity and consistency of naming and code readability.',
        'looks_for' => [
            'Ambiguous naming and inconsistent conventions.',
            'Readability issues that obscure intent.',
            'Structure that increases onboarding and review time.',
        ],
        'recommendations' => [
            'Adopt repository-wide naming conventions and enforce them.',
            'Prioritize readability in refactoring and code review.',
            'Document style rules and automate linting where possible.',
        ],
        'why' => 'Readable code reduces misunderstandings and review defects.',
    ],
    '27' => [
        'title' => '#27 SonarQube Dead or Commented-Out Code Detection',
        'tag' => 'SonarQube Code Quality',
        'about' => 'This check flags stale and inactive code paths that increase noise and risk.',
        'looks_for' => [
            'Long-lived TODO/FIXME placeholders.',
            'Commented-out blocks retained in source files.',
            'Stale code paths with no active ownership.',
        ],
        'recommendations' => [
            'Delete obsolete code and revive only when needed.',
            'Convert important TODOs into tracked issues.',
            'Enforce cleanup as part of feature completion.',
        ],
        'why' => 'Dead code increases confusion and can hide accidental vulnerabilities.',
    ],
    '28' => [
        'title' => '#28 SonarQube Error Handling and Defensive Coding Patterns',
        'tag' => 'SonarQube Code Quality',
        'about' => 'This check highlights weak error-handling paths and fragile defensive coding.',
        'looks_for' => [
            'Deeply nested flows with limited guard clauses.',
            'Weak fallback or boundary handling in critical paths.',
            'Patterns that make failure behavior unclear.',
        ],
        'recommendations' => [
            'Use explicit guards and fail-fast patterns where appropriate.',
            'Standardize error handling for expected failure modes.',
            'Add tests for invalid input and boundary conditions.',
        ],
        'why' => 'Defensive coding reduces unexpected runtime behavior and incident risk.',
    ],
    '29' => [
        'title' => '#29 SonarQube Technical Debt and Remediation Tracking',
        'tag' => 'SonarQube Code Quality',
        'about' => 'This check tracks debt signals and whether remediation pressure is increasing over time.',
        'looks_for' => [
            'Accumulated maintainability findings.',
            'Growing backlog of unresolved quality issues.',
            'Recurring hotspots without remediation ownership.',
        ],
        'recommendations' => [
            'Define debt budgets per module and release.',
            'Prioritize debt hotspots by risk and change frequency.',
            'Track remediation progress with explicit ownership.',
        ],
        'why' => 'Technical debt compounds delivery risk and future remediation cost.',
    ],
    '30' => [
        'title' => '#30 SonarQube Quality Gate Compliance Summary',
        'tag' => 'SonarQube Code Quality',
        'about' => 'This check summarizes whether selected quality rules meet your repository gate criteria.',
        'looks_for' => [
            'Threshold breaches in core quality dimensions.',
            'High-severity findings that should block merge.',
            'Trends that indicate declining code quality posture.',
        ],
        'recommendations' => [
            'Define pass/fail criteria for severity and metric thresholds.',
            'Block merges on unresolved high-severity quality failures.',
            'Review gate outcomes regularly and tune thresholds responsibly.',
        ],
        'why' => 'Quality gates create a consistent baseline for sustainable engineering velocity.',
    ],
];

$content = $checkContent[$checkId] ?? $checkContent['1'];
$pageTitle = $content['title'];
$resolvedCheckName = $checkNameFromQuery !== '' ? $checkNameFromQuery : $pageTitle;

$statusLabel = 'Unknown';
$statusClass = 'text-secondary';
if ($statusRaw === 'clean') {
    $statusLabel = 'Clean';
    $statusClass = 'text-success';
} elseif ($statusRaw === 'issues' || $statusRaw === 'issues_found') {
    $statusLabel = 'Issues Found';
    $statusClass = 'text-danger';
}

$findingCountText = 'Not available';
if ($countRaw !== null && $countRaw >= 0) {
    $findingCountText = $countRaw . ' finding' . ($countRaw === 1 ? '' : 's');
}

$findingsByCheckCategory = [
    '1' => ['OWASP'],
    '2' => [],
    '3' => [],
    '4' => [],
    '5' => ['Code Quality', 'Complexity', 'Duplication', 'File Summary'],
    '6' => ['Secret Scanner'],
    '7' => ['Dependencies'],
    '8' => ['License'],
    '9' => ['Git History'],
    '10' => ['Security Config'],
    '11' => ['Complexity'],
    '12' => ['Complexity'],
    '13' => ['Complexity'],
    '14' => ['Complexity'],
    '15' => ['Complexity'],
    '16' => ['Complexity'],
    '17' => ['Complexity'],
    '18' => ['Complexity'],
    '19' => ['Complexity'],
    '20' => ['Complexity'],
    '21' => ['Complexity'],
    '22' => ['Complexity', 'Code Quality'],
    '23' => ['Duplication'],
    '24' => ['Complexity'],
    '25' => ['Complexity'],
    '26' => ['File Summary', 'Code Quality'],
    '27' => ['Code Quality'],
    '28' => ['Complexity'],
    '29' => ['Complexity', 'Code Quality'],
    '30' => ['Complexity', 'Duplication', 'Code Quality'],
];

$findingsForCheck = [];
$findingsInfo = '';
$scanRecommendationsForCheck = [];
$recommendationsInfo = '';

$recommendationKeywordsByCheck = [
    '1' => ['authorization', 'access', 'logic', 'validation', 'design', 'owasp'],
    '2' => ['dependency', 'dependencies', 'package', 'outdated', 'upgrade'],
    '3' => ['ci', 'cd', 'pipeline', 'workflow', 'integrity', 'build', 'deploy'],
    '4' => ['logging', 'monitoring', 'audit', 'alert', 'telemetry'],
    '5' => ['quality', 'performance', 'complexity', 'duplication', 'maintainability', 'repo health'],
    '6' => ['secret', 'credential', 'token', 'key', 'password'],
    '7' => ['cve', 'osv', 'vulnerability', 'dependency'],
    '8' => ['license', 'compliance', 'legal'],
    '9' => ['git history', 'history', 'commit', 'branch'],
    '10' => ['security header', 'config', 'configuration', 'hardening', 'tls', 'cookie'],
    '11' => ['cyclomatic', 'complexity', 'average', 'branching'],
    '12' => ['cyclomatic', 'complexity', 'maximum', 'hotspot'],
    '13' => ['cognitive', 'complexity', 'average', 'readability'],
    '14' => ['cognitive', 'complexity', 'maximum', 'nesting'],
    '15' => ['function size', 'loc', 'average', 'method'],
    '16' => ['function size', 'loc', 'maximum', 'oversized'],
    '17' => ['class size', 'loc', 'average', 'cohesion'],
    '18' => ['class size', 'loc', 'maximum', 'god class'],
    '19' => ['nesting depth', 'average', 'complexity', 'guard clause'],
    '20' => ['nesting depth', 'maximum', 'complexity', 'nested'],
    '21' => ['reliability', 'bug', 'defect', 'branching', 'cyclomatic'],
    '22' => ['code smell', 'maintainability', 'cognitive', 'readability'],
    '23' => ['duplicate', 'duplication', 'copy', 'repeated'],
    '24' => ['complexity', 'cyclomatic', 'cognitive', 'nesting'],
    '25' => ['function size', 'class size', 'oversized', 'god class'],
    '26' => ['naming', 'readability', 'convention', 'clarity'],
    '27' => ['todo', 'fixme', 'commented-out', 'dead code'],
    '28' => ['error handling', 'defensive', 'guard', 'fallback'],
    '29' => ['technical debt', 'remediation', 'backlog', 'maintainability'],
    '30' => ['quality gate', 'compliance', 'threshold', 'severity'],
];

if ($scanId <= 0) {
    $findingsInfo = 'Scan ID is not available, so finding details cannot be loaded from the database.';
} elseif ($countRaw !== null && $countRaw === 0) {
    $findingsInfo = 'No findings were reported for this check in this scan.';
} else {
    $categories = $findingsByCheckCategory[$checkId] ?? [];
    if (empty($categories)) {
        $findingsInfo = 'Detailed per-check finding mapping is not available for this check yet.';
    } else {
        try {
            $pdo = db_connection();
            $placeholders = implode(',', array_fill(0, count($categories), '?'));

            $sql =
                'SELECT category, title, description, severity
                 FROM findings
                 WHERE scan_id = ?
                 AND category IN (' . $placeholders . ')
                 ORDER BY FIELD(severity, "High", "Medium", "Low", "Info"), id ASC';

            $stmt = $pdo->prepare($sql);
            $params = array_merge([$scanId], $categories);
            $stmt->execute($params);
            $findingsForCheck = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($findingsForCheck)) {
                $findingsInfo = 'No matching finding rows were found for this check in the saved scan details.';
            }
        } catch (Throwable $e) {
            $findingsInfo = 'Unable to load detailed findings for this check right now.';
        }
    }
}

if ($scanId <= 0) {
    $recommendationsInfo = 'Scan ID is not available, so scan recommendations cannot be loaded.';
} else {
    try {
        if (!isset($pdo) || !$pdo instanceof PDO) {
            $pdo = db_connection();
        }

        $recommendationStmt = $pdo->prepare(
            'SELECT recommendation_text, priority
             FROM recommendations
             WHERE scan_id = ?
             ORDER BY FIELD(priority, "High", "Medium", "Low"), id ASC'
        );
        $recommendationStmt->execute([$scanId]);
        $allScanRecommendations = $recommendationStmt->fetchAll(PDO::FETCH_ASSOC);

        $keywords = $recommendationKeywordsByCheck[$checkId] ?? [];
        if (!empty($keywords)) {
            foreach ($allScanRecommendations as $rec) {
                $text = strtolower((string) ($rec['recommendation_text'] ?? ''));
                foreach ($keywords as $keyword) {
                    if ($keyword !== '' && strpos($text, $keyword) !== false) {
                        $scanRecommendationsForCheck[] = $rec;
                        break;
                    }
                }
            }
        }

        if (empty($scanRecommendationsForCheck)) {
            $recommendationsInfo = 'No scan-specific recommendation text matched this check, showing baseline guidance below.';
        }
    } catch (Throwable $e) {
        $recommendationsInfo = 'Unable to load scan recommendations for this check right now.';
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> | Check Details</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >
    <style>
        :root {
            --header-gradient: linear-gradient(135deg, #9B59B6 0%, #7C3AED 100%);
        }

        body {
            background: linear-gradient(180deg, #f5f3ff 0%, #faf8ff 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #111827;
        }

        .header-section {
            background: var(--header-gradient);
            color: #fff;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card-soft {
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
        }

        .pill {
            display: inline-block;
            padding: 0.2rem 0.65rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            background: #ede9fe;
            color: #5b21b6;
        }

        .section-title {
            font-weight: 700;
            margin-bottom: 0.6rem;
        }

        ul.tight li {
            margin-bottom: 0.35rem;
        }

        .sev-high { color: #b91c1c; }
        .sev-medium { color: #b45309; }
        .sev-low { color: #047857; }
        .sev-info { color: #1d4ed8; }

        .site-footer {
            margin-top: 1.5rem;
            padding: 1.1rem 0;
            background: var(--header-gradient);
            color: #ffffff;
            font-size: 0.92rem;
        }

        .site-footer .footer-line {
            margin: 0;
            text-align: center;
            font-weight: 700;
            font-size: 2rem;
            line-height: 1.2;
        }
    </style>
</head>
<body>
    <div class="header-section">
        <div class="container">
            <h1 class="h2 mb-2"><?= h($pageTitle) ?></h1>
            <p class="mb-3">Details about what this analyzer checks, what it looks for, and the latest result details.</p>
            <a href="index.php" class="btn btn-light btn-sm">Back to Home</a>
        </div>
    </div>

    <div class="container pb-5">
        <div class="card-soft p-4 mb-4">
            <h2 class="h5 section-title">Current Result</h2>
            <p class="mb-2">This section shows the latest value passed from the Analysis Checks card.</p>
            <ul class="tight mb-0">
                <li><strong>Check:</strong> <?= h($resolvedCheckName) ?></li>
                <li><strong>Status:</strong> <span class="<?= h($statusClass) ?>"><?= h($statusLabel) ?></span></li>
                <li><strong>Finding count:</strong> <?= h($findingCountText) ?></li>
                <li><strong>Scan ID:</strong> <?= $scanId > 0 ? h((string) $scanId) : 'Not available' ?></li>
            </ul>
        </div>

        <div class="card-soft p-4 mb-4">
            <h2 class="h5 section-title">Findings For This Check</h2>
            <?php if (!empty($findingsForCheck)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($findingsForCheck as $finding): ?>
                        <?php
                        $severity = (string) ($finding['severity'] ?? 'Info');
                        $sevClass = 'sev-info';
                        if ($severity === 'High') {
                            $sevClass = 'sev-high';
                        } elseif ($severity === 'Medium') {
                            $sevClass = 'sev-medium';
                        } elseif ($severity === 'Low') {
                            $sevClass = 'sev-low';
                        }
                        ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <strong><?= h((string) ($finding['title'] ?? 'Untitled finding')) ?></strong>
                                    <div class="small text-muted">Category: <?= h((string) ($finding['category'] ?? 'Unknown')) ?></div>
                                </div>
                                <span class="small fw-semibold <?= h($sevClass) ?>"><?= h($severity) ?></span>
                            </div>
                            <p class="mb-0 mt-2 small"><?= h((string) ($finding['description'] ?? '')) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="mb-0"><?= h($findingsInfo !== '' ? $findingsInfo : 'No findings available for this check.') ?></p>
            <?php endif; ?>
        </div>

        <div class="card-soft p-4 mb-4">
            <span class="pill"><?= h($content['tag']) ?></span>
            <h2 class="h5 mt-3 section-title">What this check is about</h2>
            <p class="mb-0"><?= h($content['about']) ?></p>
        </div>

        <div class="card-soft p-4 mb-4">
            <h2 class="h5 section-title">What the analyzer looks for</h2>
            <ul class="tight mb-0">
                <?php foreach ($content['looks_for'] as $rule): ?>
                    <li><?= h($rule) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card-soft p-4 mb-4">
            <h2 class="h5 section-title">How findings are reported</h2>
            <p class="mb-2">For each finding, the system usually reports:</p>
            <ul class="tight mb-0">
                <li>Title of the issue and risk severity.</li>
                <li>Category and short description.</li>
                <li>Code/location context when available.</li>
                <li>Recommendation to reduce the risk.</li>
            </ul>
        </div>

        <div class="card-soft p-4 mb-4">
            <h2 class="h5 section-title">Recommendations For This Check</h2>

            <?php if (!empty($scanRecommendationsForCheck)): ?>
                <div class="list-group list-group-flush mb-3">
                    <?php foreach ($scanRecommendationsForCheck as $rec): ?>
                        <?php $priority = (string) ($rec['priority'] ?? 'Low'); ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <span><?= h((string) ($rec['recommendation_text'] ?? '')) ?></span>
                                <span class="badge text-bg-secondary"><?= h($priority) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($recommendationsInfo !== ''): ?>
                <p class="mb-2 small text-muted"><?= h($recommendationsInfo) ?></p>
            <?php endif; ?>

            <h3 class="h6 mt-3">Baseline Guidance</h3>
            <ul class="tight mb-0">
                <?php foreach (($content['recommendations'] ?? []) as $recommendation): ?>
                    <li><?= h($recommendation) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card-soft p-4">
            <h2 class="h5 section-title">Why this matters</h2>
            <p class="mb-0"><?= h($content['why']) ?></p>
        </div>
    </div>
    <footer class="site-footer">
        <div class="container">
            <p class="footer-line">@2026 AI Git Repo Analyzer</p>
            <p class="footer-line">803 Summer Street, MN 55106</p>
            <p class="footer-line">ContactUs@aigitrepoanalyzer.com</p>
        </div>
    </footer>
</body>
</html>
