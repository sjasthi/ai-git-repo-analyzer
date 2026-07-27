<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function ensureScanReportColumns(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec("ALTER TABLE scans ADD COLUMN IF NOT EXISTS selected_checks_json LONGTEXT NULL");
    $pdo->exec("ALTER TABLE scans ADD COLUMN IF NOT EXISTS results_json LONGTEXT NULL");
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS check_runs (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            scan_id       INT         NOT NULL,
            check_name    VARCHAR(50) NOT NULL,
            status        VARCHAR(20) NOT NULL DEFAULT 'clean',
            finding_count INT         NOT NULL DEFAULT 0,
            FOREIGN KEY (scan_id) REFERENCES scans(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        )"
    );
    $ensured = true;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function absoluteCheckDetailsUrl(array $params = []): string
{
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/api/report.php'));
    $appBasePath = rtrim(dirname(dirname($scriptName)), '/');

    $base = $scheme . '://' . $host . $appBasePath . '/check_insecure_design.php';
    if (empty($params)) {
        return $base;
    }

    return $base . '?' . http_build_query($params);
}

function absoluteReportUrl(array $params = []): string
{
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/api/report.php'));

    $base = $scheme . '://' . $host . $scriptName;
    if (empty($params)) {
        return $base;
    }

    return $base . '?' . http_build_query($params);
}

function checkDetailIdFromName(string $checkName): ?string
{
    $normalized = strtolower(trim($checkName));

    if (preg_match('/#\s*(\d{1,3})/', $normalized, $numberMatch) === 1) {
        return (string) $numberMatch[1];
    }

    $patterns = [
        '1' => '/#?1\s*insecure design and logic flaws|insecure design and logic flaws/i',
        '2' => '/#?2\s*vulnerable and outdated dependencies|vulnerable and outdated dependencies/i',
        '3' => '/#?3\s*ci\/cd and software integrity risks|software integrity risks/i',
        '4' => '/#?4\s*logging and monitoring coverage|logging and monitoring/i',
        '5' => '/#?5\s*code quality, performance and repo health|repo health/i',
        '6' => '/#?6\s*secret\s*&\s*credential scanner|secret.*credential scanner/i',
        '7' => '/#?7\s*dependency cve audit|osv\.dev/i',
        '8' => '/#?8\s*license compliance scanner|license compliance/i',
        '9' => '/#?9\s*git history risk analysis|git history risk/i',
        '10' => '/#?10\s*security header\s*&\s*config auditor|security header.*config auditor/i',
        '11' => '/#?11\s*cyclomatic complexity average|cyclomatic complexity average/i',
        '12' => '/#?12\s*cyclomatic complexity maximum|cyclomatic complexity maximum/i',
        '13' => '/#?13\s*cognitive complexity average|cognitive complexity average/i',
        '14' => '/#?14\s*cognitive complexity maximum|cognitive complexity maximum/i',
        '15' => '/#?15\s*function size average|function size average/i',
        '16' => '/#?16\s*function size maximum|function size maximum/i',
        '17' => '/#?17\s*class size average|class size average/i',
        '18' => '/#?18\s*class size maximum|class size maximum/i',
        '19' => '/#?19\s*nesting depth average|nesting depth average/i',
        '20' => '/#?20\s*nesting depth maximum|nesting depth maximum/i',
        '21' => '/#?21\s*sonarqube bugs and reliability issues|bugs and reliability issues/i',
        '22' => '/#?22\s*sonarqube code smells and maintainability issues|code smells and maintainability issues/i',
        '23' => '/#?23\s*sonarqube duplicated code detection|duplicated code detection/i',
        '24' => '/#?24\s*sonarqube cyclomatic and cognitive complexity limits|cyclomatic and cognitive complexity limits/i',
        '25' => '/#?25\s*sonarqube function and class size control|function and class size control/i',
        '26' => '/#?26\s*sonarqube naming convention and readability checks|naming convention and readability checks/i',
        '27' => '/#?27\s*sonarqube dead or commented-out code detection|dead or commented-out code detection/i',
        '28' => '/#?28\s*sonarqube error handling and defensive coding patterns|error handling and defensive coding patterns/i',
        '29' => '/#?29\s*sonarqube technical debt and remediation tracking|technical debt and remediation tracking/i',
        '30' => '/#?30\s*sonarqube quality gate compliance summary|quality gate compliance summary/i',
        '91' => '/#?91\s*dependency inventory accuracy|inventory accuracy/i',
        '92' => '/#?92\s*package identity normalization|identity normalization/i',
        '93' => '/#?93\s*dependency graph mapping|graph mapping/i',
        '94' => '/#?94\s*vulnerability correlation|vulnerability correlation/i',
        '95' => '/#?95\s*license risk analysis|license risk analysis/i',
        '96' => '/#?96\s*provenance and source traceability|source traceability/i',
        '97' => '/#?97\s*integrity and authenticity verification|authenticity verification/i',
        '98' => '/#?98\s*sbom format proficiency|sbom format proficiency/i',
        '99' => '/#?99\s*continuous sbom automation|sbom automation/i',
        '100' => '/#?100\s*drift and unused dependency detection|unused dependency detection|dependency drift/i',
        '101' => '/#?101\s*devops ci\/cd pipeline coverage|ci\/cd pipeline coverage/i',
        '102' => '/#?102\s*devops docker build readiness|docker build readiness/i',
        '103' => '/#?103\s*devops secrets handling in pipelines|secrets handling in pipelines/i',
        '104' => '/#?104\s*devops environment configuration management|environment configuration management/i',
        '105' => '/#?105\s*devops release workflow automation|release workflow automation/i',
        '106' => '/#?106\s*devops github actions security hardening|actions security hardening/i',
        '107' => '/#?107\s*devops pull request and branch quality gates|branch quality gates/i',
        '108' => '/#?108\s*devops deployment automation signals|deployment automation signals/i',
        '109' => '/#?109\s*devops operational observability hooks|operational observability hooks/i',
        '110' => '/#?110\s*devops runbook and recovery documentation|runbook and recovery documentation/i',
        '111' => '/#?111\s*ai readiness rich readme for ai onboarding|rich readme for ai onboarding/i',
        '112' => '/#?112\s*ai readiness usage examples and snippets|usage examples and snippets/i',
        '113' => '/#?113\s*ai readiness clear api reference documentation|clear api reference documentation/i',
        '114' => '/#?114\s*ai readiness assistant context files|ai assistant context files/i',
        '115' => '/#?115\s*ai readiness explicit dependency manifest|explicit dependency manifest/i',
        '116' => '/#?116\s*ai readiness descriptive naming for ai context|descriptive naming for ai context/i',
        '117' => '/#?117\s*ai readiness small, single-purpose functions|single-purpose functions/i',
        '118' => '/#?118\s*ai readiness modular design and boundaries|modular design and boundaries/i',
        '119' => '/#?119\s*ai readiness consistent coding style|consistent coding style/i',
        '120' => '/#?120\s*ai readiness inline documentation and docstrings|inline documentation and docstrings/i',
    ];

    foreach ($patterns as $checkId => $pattern) {
        if (preg_match($pattern, $normalized) === 1) {
            return $checkId;
        }
    }

    return null;
}

function checkDetailContentMap(): array
{
    return [
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
}

function findingsByCheckCategoryMap(): array
{
    return [
        '1' => ['OWASP'],
        '2' => ['Dependencies'],
        '3' => ['CI/CD Integrity'],
        '4' => ['Logging'],
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
        '61' => ['Performance'],
        '62' => ['Performance'],
        '63' => ['Performance'],
        '64' => ['Performance'],
        '65' => ['Performance'],
        '66' => ['Performance'],
        '67' => ['Performance'],
        '68' => ['Performance'],
        '69' => ['Performance'],
        '70' => ['Performance'],
        '71' => ['Reliability'],
        '72' => ['Reliability'],
        '73' => ['Reliability'],
        '74' => ['Reliability'],
        '75' => ['Reliability'],
        '76' => ['Reliability'],
        '77' => ['Reliability'],
        '78' => ['Reliability'],
        '79' => ['Reliability'],
        '80' => ['Reliability'],
        '81' => ['Testing'],
        '82' => ['Testing'],
        '83' => ['Testing'],
        '84' => ['Testing'],
        '85' => ['Testing'],
        '86' => ['Testing'],
        '87' => ['Testing'],
        '88' => ['Testing'],
        '89' => ['Testing'],
        '90' => ['Testing'],
        '91' => ['Dependency Analysis'],
        '92' => ['Dependency Analysis'],
        '93' => ['Dependency Analysis'],
        '94' => ['Dependency Analysis'],
        '95' => ['Dependency Analysis'],
        '96' => ['Dependency Analysis'],
        '97' => ['Dependency Analysis'],
        '98' => ['Dependency Analysis'],
        '99' => ['Dependency Analysis'],
        '100' => ['Dependency Analysis'],
    ];
}

function recommendationKeywordsByCheckMap(): array
{
    return [
        '1' => ['authorization', 'access', 'logic', 'validation', 'design', 'owasp'],
        '2' => ['dependency', 'dependencies', 'package', 'outdated', 'upgrade', 'lockfile', 'manifest'],
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
        '61' => ['performance', 'nested', 'loop', 'iteration', 'complexity'],
        '62' => ['performance', 'expensive', 'operation', 'hotspot', 'cost'],
        '63' => ['performance', 'n+1', 'duplicate', 'query', 'access'],
        '64' => ['performance', 'api', 'repeated', 'network', 'call'],
        '65' => ['performance', 'blocking', 'sync', 'operation', 'latency'],
        '66' => ['performance', 'unbounded', 'scan', 'query', 'pagination'],
        '67' => ['performance', 'payload', 'serialization', 'size', 'transport'],
        '68' => ['performance', 'cache', 'miss', 'reuse', 'memoization'],
        '69' => ['performance', 'sync io', 'io', 'hotspot', 'latency'],
        '70' => ['performance', 'build', 'runtime', 'efficiency', 'pipeline'],
        '71' => ['reliability', 'logging', 'signal', 'trace', 'audit'],
        '72' => ['reliability', 'retry', 'backoff', 'transient', 'resilience'],
        '73' => ['reliability', 'timeout', 'circuit', 'limit', 'latency'],
        '74' => ['reliability', 'exception', 'error handling', 'failure', 'guard'],
        '75' => ['reliability', 'null', 'defensive', 'guard', 'validation'],
        '76' => ['reliability', 'cleanup', 'resource', 'lifecycle', 'release'],
        '77' => ['reliability', 'validation', 'input', 'sanitize', 'boundary'],
        '78' => ['reliability', 'idempotency', 'duplicate', 'request', 'safe'],
        '79' => ['reliability', 'fallback', 'degradation', 'resilience', 'backup'],
        '80' => ['reliability', 'observability', 'alert', 'monitor', 'slo'],
        '81' => ['testing', 'first', 'fast', 'isolated', 'repeatable'],
        '82' => ['testing', 'aaa', 'arrange', 'act', 'assert'],
        '83' => ['testing', 'data', 'fixture', 'isolation', 'deterministic'],
        '84' => ['testing', 'flaky', 'stability', 'timing', 'determinism'],
        '85' => ['testing', 'boundary', 'negative', 'error', 'invalid'],
        '86' => ['testing', 'contract', 'api', 'schema', 'response'],
        '87' => ['testing', 'security', 'auth', 'permission', 'abuse'],
        '88' => ['testing', 'performance', 'latency', 'throughput', 'hot path'],
        '89' => ['testing', 'ci', 'gate', 'pipeline', 'merge'],
        '90' => ['testing', 'suite', 'maintainability', 'structure', 'organization'],
        '91' => ['dependency', 'inventory', 'manifest', 'sbom', 'components'],
        '92' => ['dependency', 'identity', 'version', 'normalize', 'purl'],
        '93' => ['dependency', 'graph', 'transitive', 'lockfile', 'mapping'],
        '94' => ['dependency', 'vulnerability', 'osv', 'cve', 'advisory'],
        '95' => ['dependency', 'license', 'compliance', 'compatibility', 'copyleft'],
        '96' => ['dependency', 'provenance', 'source', 'traceability', 'origin'],
        '97' => ['dependency', 'integrity', 'checksum', 'signature', 'authenticity'],
        '98' => ['sbom', 'cyclonedx', 'spdx', 'format', 'components'],
        '99' => ['sbom', 'ci', 'automation', 'pipeline', 'workflow'],
        '100' => ['dependency', 'drift', 'unused', 'outdated', 'cleanup'],
    ];
}

$scanId = (int) ($_GET['scan_id'] ?? 0);
$download = isset($_GET['download']) && (string) $_GET['download'] === '1';
$format = strtolower(trim((string) ($_GET['format'] ?? 'html')));
$isDocDownload = false;

if ($scanId <= 0) {
    http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Valid scan_id is required.']);
    exit;
}

try {
    $pdo = db_connection();
    ensureScanReportColumns($pdo);

    $scanStmt = $pdo->prepare(
        'SELECT s.id, s.scan_date, s.summary_score, s.total_findings, s.total_skills, s.selected_checks_json, s.results_json, r.repo_url
         FROM scans s
         JOIN repositories r ON s.repository_id = r.id
         WHERE s.id = :scan_id'
    );
    $scanStmt->execute([':scan_id' => $scanId]);
    $scan = $scanStmt->fetch();

    if (!$scan) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Scan not found.']);
        exit;
    }

    $findingsStmt = $pdo->prepare(
        'SELECT category, title, description, severity
         FROM findings
         WHERE scan_id = :scan_id
         ORDER BY FIELD(severity, "High", "Medium", "Low", "Info"), id ASC'
    );
    $findingsStmt->execute([':scan_id' => $scanId]);
    $findings = $findingsStmt->fetchAll();

    $recommendationsStmt = $pdo->prepare(
        'SELECT recommendation_text, priority
         FROM recommendations
         WHERE scan_id = :scan_id
         ORDER BY FIELD(priority, "High", "Medium", "Low"), id ASC'
    );
    $recommendationsStmt->execute([':scan_id' => $scanId]);
    $recommendations = $recommendationsStmt->fetchAll();

    $skillsStmt = $pdo->prepare(
        'SELECT skill_name, proficiency_level, risk_level
         FROM skills
         WHERE scan_id = :scan_id
         ORDER BY id ASC'
    );
    $skillsStmt->execute([':scan_id' => $scanId]);
    $skills = $skillsStmt->fetchAll();

    $checkRunsStmt = $pdo->prepare(
        'SELECT check_name, status, finding_count
         FROM check_runs
         WHERE scan_id = :scan_id
         ORDER BY id ASC'
    );
    $checkRunsStmt->execute([':scan_id' => $scanId]);
    $checkRuns = $checkRunsStmt->fetchAll();

    $selectedChecks = [];
    if (!empty($scan['selected_checks_json'])) {
        $decodedChecks = json_decode((string) $scan['selected_checks_json'], true);
        if (is_array($decodedChecks)) {
            $selectedChecks = $decodedChecks;
        }
    }

    // Map check IDs to friendly names
    $checkLabels = [
        'dependency_risk'  => '#1 Insecure Design and Logic Flaws (A04)',
        'hardening'        => '#2 Vulnerable and Outdated Dependencies (A06)',
        'performance'      => '#3 CI/CD and Software Integrity Risks (A08)',
        'maintainability'  => '#4 Logging and Monitoring Coverage (A09)',
        'code_intelligence'=> '#5 Code Quality, Performance and Repo Health',
        'secret_scanner'   => '#6 Secret & Credential Scanner',
        'dependency_cve'   => '#7 Dependency CVE Audit (OSV.dev)',
        'license_check'    => '#8 License Compliance Scanner',
        'git_history'      => '#9 Git History Risk Analysis',
        'security_config'  => '#10 Security Header & Config Auditor',
        'complexity_cyclomatic_avg' => '#11 Cyclomatic Complexity Average',
        'complexity_cyclomatic_max' => '#12 Cyclomatic Complexity Maximum',
        'complexity_cognitive_avg' => '#13 Cognitive Complexity Average',
        'complexity_cognitive_max' => '#14 Cognitive Complexity Maximum',
        'complexity_function_size_avg' => '#15 Function Size Average',
        'complexity_function_size_max' => '#16 Function Size Maximum',
        'complexity_class_size_avg' => '#17 Class Size Average',
        'complexity_class_size_max' => '#18 Class Size Maximum',
        'complexity_nesting_depth_avg' => '#19 Nesting Depth Average',
        'complexity_nesting_depth_max' => '#20 Nesting Depth Maximum',
        'sonar_bugs_reliability' => '#21 SonarQube Bugs and Reliability Issues',
        'sonar_code_smells' => '#22 SonarQube Code Smells and Maintainability Issues',
        'sonar_duplication_detection' => '#23 SonarQube Duplicated Code Detection',
        'sonar_complexity_limits' => '#24 SonarQube Cyclomatic and Cognitive Complexity Limits',
        'sonar_size_control' => '#25 SonarQube Function and Class Size Control',
        'sonar_naming_readability' => '#26 SonarQube Naming Convention and Readability Checks',
        'sonar_dead_code' => '#27 SonarQube Dead or Commented-Out Code Detection',
        'sonar_error_handling' => '#28 SonarQube Error Handling and Defensive Coding Patterns',
        'sonar_technical_debt' => '#29 SonarQube Technical Debt and Remediation Tracking',
        'sonar_quality_gate_summary' => '#30 SonarQube Quality Gate Compliance Summary',
        'clean_code_solid' => '#31 Clean Code SOLID Principles',
        'clean_code_dry' => '#32 Clean Code DRY Principle',
        'clean_code_kiss' => '#33 Clean Code KISS Principle',
        'clean_code_yagni' => '#34 Clean Code YAGNI Principle',
        'clean_code_single_responsibility' => '#35 Clean Code Single Responsibility',
        'clean_code_separation_of_concerns' => '#36 Clean Code Separation of Concerns',
        'clean_code_meaningful_names' => '#37 Clean Code Meaningful Naming',
        'clean_code_small_functions' => '#38 Clean Code Small Functions',
        'clean_code_formatting' => '#39 Clean Code Consistent Formatting',
        'clean_code_error_handling' => '#40 Clean Code Explicit Error Handling',
        'architecture_layered_boundaries' => '#41 Clean Architecture Layered Boundaries',
        'architecture_dependency_rule' => '#42 Clean Architecture Dependency Rule',
        'architecture_framework_independence' => '#43 Clean Architecture Framework Independence',
        'architecture_presentation_isolation' => '#44 Clean Architecture Presentation Isolation',
        'architecture_use_case_separation' => '#45 Clean Architecture Use Case Separation',
        'architecture_domain_purity' => '#46 Clean Architecture Domain Purity',
        'architecture_data_access_abstraction' => '#47 Clean Architecture Data Access Abstraction',
        'architecture_interface_adapter_separation' => '#48 Clean Architecture Interface Adapter Separation',
        'architecture_package_cohesion' => '#49 Clean Architecture Package Cohesion',
        'architecture_no_cyclic_dependencies' => '#50 Clean Architecture No Cyclic Dependencies',
        'testing_unit_coverage' => '#51 Test Pyramid Unit Coverage',
        'testing_integration_coverage' => '#52 Test Pyramid Integration Coverage',
        'testing_end_to_end_coverage' => '#53 Test Pyramid End-to-End Coverage',
        'testing_fast_feedback' => '#54 Test Pyramid Fast Feedback',
        'testing_mocking_external_apis' => '#55 Test Pyramid Mocking External APIs',
        'testing_database_isolation' => '#56 Test Pyramid Database Test Isolation',
        'testing_api_response_validation' => '#57 Test Pyramid API Response Validation',
        'testing_error_path_testing' => '#58 Test Pyramid Error Path Testing',
        'testing_regression_coverage' => '#59 Test Pyramid Regression Test Coverage',
        'testing_organization_maintainability' => '#60 Test Pyramid Test Organization and Maintainability',
        'performance_nested_loops' => '#61 Performance Nested Loops and Deep Iterations',
        'performance_expensive_operations' => '#62 Performance Expensive Operation Hotspots',
        'performance_n_plus_one_patterns' => '#63 Performance N+1 and Repeated Data Access Patterns',
        'performance_repeated_api_calls' => '#64 Performance Repeated External API Call Patterns',
        'performance_blocking_operations' => '#65 Performance Blocking Operation Risks',
        'performance_unbounded_queries' => '#66 Performance Unbounded Query and Scan Risks',
        'performance_large_payloads' => '#67 Performance Large Payload and Serialization Costs',
        'performance_cache_miss_risk' => '#68 Performance Cache Strategy and Miss Risks',
        'performance_sync_io_hotspots' => '#69 Performance Synchronous I/O Hotspots',
        'performance_build_runtime_cost' => '#70 Performance Build and Runtime Efficiency Controls',
        'reliability_logging_coverage' => '#71 Reliability Logging Coverage and Signal Quality',
        'reliability_retry_strategy' => '#72 Reliability Retry Strategy and Backoff Safety',
        'reliability_timeout_controls' => '#73 Reliability Timeout and Circuit Controls',
        'reliability_exception_handling' => '#74 Reliability Exception Handling Discipline',
        'reliability_null_safety' => '#75 Reliability Null Safety and Defensive Guards',
        'reliability_resource_cleanup' => '#76 Reliability Resource Cleanup and Lifecycle Safety',
        'reliability_input_validation' => '#77 Reliability Input Validation and Sanitization',
        'reliability_idempotency' => '#78 Reliability Idempotency and Duplicate Request Safety',
        'reliability_fallback_paths' => '#79 Reliability Fallback and Degradation Paths',
        'reliability_observability_alerting' => '#80 Reliability Observability and Alerting Readiness',
        'testing_first_principles' => '#81 Testing FIRST Principle Alignment',
        'testing_aaa_pattern' => '#82 Testing Arrange-Act-Assert Pattern Discipline',
        'testing_test_data_management' => '#83 Testing Test Data Management and Isolation',
        'testing_flaky_test_risk' => '#84 Testing Flaky Test Risk Detection',
        'testing_boundary_case_coverage' => '#85 Testing Boundary and Negative Path Coverage',
        'testing_contract_validation' => '#86 Testing API Contract and Response Validation',
        'testing_security_paths' => '#87 Testing Security-Critical Path Coverage',
        'testing_performance_paths' => '#88 Testing Performance-Critical Path Coverage',
        'testing_ci_gate_readiness' => '#89 Testing CI Gate and Execution Reliability',
        'testing_suite_maintainability' => '#90 Testing Suite Maintainability and Structure',
        'dependency_inventory_accuracy' => '#91 Dependency Inventory',
        'dependency_identity_normalization' => '#92 Vulnerability Detection',
        'dependency_graph_mapping' => '#93 License Compliance',
        'dependency_vulnerability_correlation' => '#94 Supply Chain Security',
        'dependency_license_risk' => '#95 Version Tracking',
        'dependency_provenance_traceability' => '#96 Risk Assessment',
        'dependency_integrity_verification' => '#97 Dependency Mapping',
        'dependency_sbom_format_quality' => '#98 Compliance and Auditing',
        'dependency_sbom_automation' => '#99 Continuous SBOM Automation',
        'dependency_drift_unused' => '#100 Software Transparency',
        'devops_ci_cd_pipeline' => '#101 DevOps CI/CD Pipeline Coverage',
        'devops_docker_readiness' => '#102 DevOps Docker Build Readiness',
        'devops_secrets_hygiene' => '#103 DevOps Secrets Handling in Pipelines',
        'devops_env_configuration' => '#104 DevOps Environment Configuration Management',
        'devops_release_workflow' => '#105 DevOps Release Workflow Automation',
        'devops_actions_security' => '#106 DevOps GitHub Actions Security Hardening',
        'devops_branch_pr_signals' => '#107 DevOps Pull Request and Branch Quality Gates',
        'devops_deployment_automation' => '#108 DevOps Deployment Automation Signals',
        'devops_observability_ops' => '#109 DevOps Operational Observability Hooks',
        'devops_incident_recovery_docs' => '#110 DevOps Runbook and Recovery Documentation',
        'ai_readme_richness' => '#111 AI Readiness Rich README for AI Onboarding',
        'ai_usage_examples' => '#112 AI Readiness Usage Examples and Snippets',
        'ai_api_clarity' => '#113 AI Readiness Clear API Reference Documentation',
        'ai_context_files' => '#114 AI Readiness Assistant Context Files',
        'ai_dependency_manifest' => '#115 AI Readiness Explicit Dependency Manifest',
        'ai_naming_clarity' => '#116 AI Readiness Descriptive Naming for AI Context',
        'ai_function_granularity' => '#117 AI Readiness Small, Single-Purpose Functions',
        'ai_modular_structure' => '#118 AI Readiness Modular Design and Boundaries',
        'ai_consistent_style' => '#119 AI Readiness Consistent Coding Style',
        'ai_docstring_coverage' => '#120 AI Readiness Inline Documentation and Docstrings',
    ];

    $selectedCheckLabels = [];
    foreach ($selectedChecks as $checkId) {
        $selectedCheckLabels[] = $checkLabels[$checkId] ?? $checkId;
    }

    $checkRunsById = [];
    foreach ($checkRuns as $cr) {
        $nameRaw = (string) ($cr['check_name'] ?? '');
        $id = checkDetailIdFromName($nameRaw);
        if ($id !== null) {
            $checkRunsById[$id] = $cr;
        }
    }

    $orderedCheckRuns = [];
    foreach ($checkLabels as $id => $label) {
        $labelRaw = preg_replace('/\s*\([^)]*\)\s*$/', '', $label);
        $labelCheckId = null;
        if (preg_match('/#\s*(100|9[0-9]|8[0-9]|7[0-9]|6[0-9]|5[0-9]|4[0-9]|3[0-9]|2[0-9]|1[0-9]|[1-9])/', $label, $numberMatch) === 1) {
            $labelCheckId = (string) $numberMatch[1];
        }

        if ($labelCheckId !== null && isset($checkRunsById[$labelCheckId])) {
            $orderedCheckRuns[] = $checkRunsById[$labelCheckId];
            continue;
        }

        $orderedCheckRuns[] = [
            'check_name' => $labelRaw,
            'status' => 'not_run',
            'finding_count' => 0,
        ];
    }

    $results = [];
    if (!empty($scan['results_json'])) {
        $decodedResults = json_decode((string) $scan['results_json'], true);
        if (is_array($decodedResults)) {
            $results = $decodedResults;
        }
    }

    $summaryData = [
        'scan' => [
            'id' => (int) $scan['id'],
            'scan_date' => (string) $scan['scan_date'],
            'summary_score' => $scan['summary_score'] !== null ? (int) $scan['summary_score'] : null,
            'total_findings' => (int) $scan['total_findings'],
            'total_skills' => (int) $scan['total_skills'],
            'repo_url' => (string) $scan['repo_url'],
        ],
        'selected_checks' => $selectedCheckLabels,
        'check_runs' => $checkRuns,
        'results' => $results,
        'findings' => $findings,
        'recommendations' => $recommendations,
        'skills' => $skills,
    ];

    if ($format === 'json' && !$download) {
        header('Content-Type: application/json');
        echo json_encode($summaryData, JSON_PRETTY_PRINT);
        exit;
    }

    if ($download && ($format === 'html' || $format === 'doc')) {
        // Keep backward compatibility for old format=html links while serving DOC.
        $format = 'doc';
        $isDocDownload = true;
        header('Content-Type: application/msword; charset=UTF-8');
        header('Content-Disposition: attachment; filename="scan-' . $scanId . '-summary.doc"');
    }

    if ($format === 'txt' || ($download && $format === 'txt')) {
        $lines = [];
        $lines[] = 'AI Git Repo Analyzer Report';
        $lines[] = 'Scan ID: ' . $scan['id'];
        $lines[] = 'Repository: ' . $scan['repo_url'];
        $lines[] = 'Scan Date: ' . $scan['scan_date'];
        $lines[] = 'Summary Score: ' . ($scan['summary_score'] ?? 'N/A');
        $lines[] = 'Total Findings: ' . $scan['total_findings'];
        $lines[] = 'Total Skills: ' . $scan['total_skills'];
        $lines[] = '';
        $lines[] = 'Selected Checks';
        if (empty($selectedCheckLabels)) {
            $lines[] = '- No stored check list for this scan';
        } else {
            foreach ($selectedCheckLabels as $check) {
                $lines[] = '- ' . $check;
            }
        }
        $lines[] = '';
        $lines[] = 'Analysis Checks';
        if (empty($checkRuns)) {
            $lines[] = '- No stored per-check results for this scan';
        } else {
            foreach ($checkRuns as $cr) {
                $lines[] = '- [' . ucfirst($cr['status']) . '] ' . $cr['check_name'] . ': ' . $cr['finding_count'] . ' finding' . ($cr['finding_count'] !== 1 ? 's' : '');
            }
        }
        $lines[] = '';
        if (empty($findings)) {
            $lines[] = '- None';
        } else {
            foreach ($findings as $finding) {
                $lines[] = '- [' . $finding['severity'] . '] ' . $finding['title'];
                $lines[] = '  Category: ' . $finding['category'];
                $lines[] = '  Description: ' . $finding['description'];
            }
        }
        $lines[] = '';
        $lines[] = 'Recommendations';
        if (empty($recommendations)) {
            $lines[] = '- None';
        } else {
            foreach ($recommendations as $recommendation) {
                $lines[] = '- [' . $recommendation['priority'] . '] ' . $recommendation['recommendation_text'];
            }
        }
        $lines[] = '';
        $lines[] = 'Skills';
        if (empty($skills)) {
            $lines[] = '- None';
        } else {
            foreach ($skills as $skill) {
                $lines[] = '- ' . $skill['skill_name'] . ' (' . $skill['proficiency_level'] . ', risk: ' . $skill['risk_level'] . ')';
            }
        }

        $text = implode("\n", $lines) . "\n";
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="scan-' . $scanId . '-summary.txt"');
        echo $text;
        exit;
    }

    $summaryUrl = absoluteReportUrl([
        'scan_id' => (string) $scanId,
    ]);
    $downloadUrl = absoluteReportUrl([
        'scan_id' => (string) $scanId,
        'download' => '1',
        'format' => 'doc',
    ]);

    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Scan Summary #' . h((string) $scanId) . '</title>';
    echo '<style>body{font-family:Arial,sans-serif;background:#f7f7fb;color:#1f2937;margin:0;padding:24px}.wrap{max-width:980px;margin:0 auto}.card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px;margin-bottom:16px}.btn{display:inline-block;padding:8px 12px;border-radius:8px;text-decoration:none;border:1px solid #d1d5db;color:#111827;margin-right:8px}.btn-primary{background:#2563eb;color:#fff;border-color:#2563eb}.meta{color:#6b7280;font-size:14px}.tag{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;background:#eef2ff;color:#3730a3}.sev-high{background:#fee2e2;color:#991b1b}.sev-medium{background:#fef3c7;color:#92400e}.sev-low{background:#dcfce7;color:#166534}.sev-info{background:#dbeafe;color:#1e40af}ul{margin:8px 0 0 18px}.checks-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:0.75rem}.check-group-heading{grid-column:1 / -1;font-size:0.82rem;font-weight:700;color:#374151;margin-top:0.35rem}.check-tile{border-radius:0.75rem;padding:0.85rem 1rem;border:1.5px solid #e5e7eb;display:flex;flex-direction:column;gap:0.3rem;background:#fff}.check-tile.clean{border-color:#bbf7d0;background:#f0fdf4}.check-tile.issues{border-color:#fecaca;background:#fff5f5}.check-tile .check-name{font-size:0.78rem;font-weight:700;color:#374151}.check-tile .check-count{font-size:1.1rem;font-weight:700}.check-tile.clean .check-count{color:#16a34a}.check-tile.issues .check-count{color:#dc2626}.check-tile .check-label{font-size:0.7rem;color:#6b7280}.list-item-muted{color:#6b7280}.list-head{font-weight:700;background:#f3f4f6}</style>';
    echo '<style>body[data-theme="dark"]{background:#0f172a;color:#e5e7eb}body[data-theme="dark"] .card{background:#1f2937;border-color:#374151}body[data-theme="dark"] .meta{color:#9ca3af}body[data-theme="dark"] .btn{color:#e5e7eb;border-color:#6b7280}body[data-theme="dark"] .btn-primary{background:#1d4ed8;border-color:#1d4ed8;color:#fff}body[data-theme="dark"] .tag{background:#1e293b;color:#c7d2fe}body[data-theme="dark"] .check-tile{background:#111827;border-color:#374151}body[data-theme="dark"] .check-tile.clean{background:#0f1f17;border-color:#166534}body[data-theme="dark"] .check-tile.issues{background:#2a1313;border-color:#7f1d1d}body[data-theme="dark"] .check-name{color:#d1d5db}body[data-theme="dark"] .check-label{color:#9ca3af}.details-popup{position:fixed;inset:0;display:none;z-index:1200;background:rgba(17,24,39,.55);padding:24px}.details-popup.open{display:block}.details-popup-dialog{width:min(980px,100%);height:min(88vh,820px);margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;display:flex;flex-direction:column;border:1px solid #e5e7eb}.details-popup-header{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #e5e7eb}.details-popup-title{margin:0;font-size:18px;font-weight:700;color:#111827}.details-popup-close{border:1px solid #d1d5db;background:#fff;color:#111827;border-radius:8px;padding:6px 10px;cursor:pointer}.details-popup-body{flex:1}.details-popup-iframe{width:100%;height:100%;border:0}body[data-theme="dark"] .details-popup-dialog{background:#111827;border-color:#374151}body[data-theme="dark"] .details-popup-header{border-bottom-color:#374151}body[data-theme="dark"] .details-popup-title{color:#e5e7eb}body[data-theme="dark"] .details-popup-close{background:#1f2937;border-color:#4b5563;color:#e5e7eb}</style>';
    echo '</head>';
    echo '<body><div class="wrap">';

    echo '<div class="card">';
    echo '<h1 style="margin-top:0">Scan Summary #' . h((string) $scan['id']) . '</h1>';
    echo '<p class="meta">Repository: ' . h((string) $scan['repo_url']) . '</p>';
    echo '<p class="meta">Scan date: ' . h((string) $scan['scan_date']) . '</p>';
    echo '<p class="meta">Score: <strong>' . h((string) ($scan['summary_score'] ?? 'N/A')) . '</strong> | Findings: <strong>' . h((string) $scan['total_findings']) . '</strong> | Skills: <strong>' . h((string) $scan['total_skills']) . '</strong></p>';
    echo '<a class="btn" href="' . h($summaryUrl) . '">Refresh</a>';
    echo '<a class="btn btn-primary" href="' . h($downloadUrl) . '">Download DOC</a>';
    if (!$isDocDownload) {
        echo '<button type="button" id="theme-toggle" class="btn">Dark Mode</button>';
    }
    echo '</div>';

    echo '<div class="card"><h2 style="margin-top:0">Selected Checks</h2>';
    if (empty($selectedCheckLabels)) {
        echo '<p class="meta">No selected checks.</p>';
    } else {
        $selectedGroups = [
            'OWASP Checks' => [],
            'Complexity Checks' => [],
            'SonarQube Rules (Code Quality)' => [],
            'Clean Code Checks (Weight: 10%)' => [],
            'Architecture Checks (Weight: 10%)' => [],
            'Testing Checks (Weight: 10%)' => [],
            'Performance Checks (Weight: 10%)' => [],
            'Reliability Checks (Weight: 10%)' => [],
            'Testing Plus Checks (Weight: 10%)' => [],
            'Dependency SBOM Checks (Weight: 5%)' => [],
            'DevOps Readiness Checks (Weight: 5%)' => [],
            'AI Readiness Checks (Weight: 5%)' => [],
        ];
        foreach ($selectedCheckLabels as $check) {
            $number = 0;
            if (preg_match('/#\s*(\d+)/', (string) $check, $m) === 1) {
                $number = (int) $m[1];
            }
            if ($number >= 1 && $number <= 10) {
                $selectedGroups['OWASP Checks'][] = (string) $check;
            } elseif ($number >= 11 && $number <= 20) {
                $selectedGroups['Complexity Checks'][] = (string) $check;
            } elseif ($number >= 31 && $number <= 40) {
                $selectedGroups['Clean Code Checks (Weight: 10%)'][] = (string) $check;
            } elseif ($number >= 41 && $number <= 50) {
                $selectedGroups['Architecture Checks (Weight: 10%)'][] = (string) $check;
            } elseif ($number >= 51 && $number <= 60) {
                $selectedGroups['Testing Checks (Weight: 10%)'][] = (string) $check;
            } elseif ($number >= 61 && $number <= 70) {
                $selectedGroups['Performance Checks (Weight: 10%)'][] = (string) $check;
            } elseif ($number >= 71 && $number <= 80) {
                $selectedGroups['Reliability Checks (Weight: 10%)'][] = (string) $check;
            } elseif ($number >= 81 && $number <= 90) {
                $selectedGroups['Testing Plus Checks (Weight: 10%)'][] = (string) $check;
            } elseif ($number >= 91 && $number <= 100) {
                $selectedGroups['Dependency SBOM Checks (Weight: 5%)'][] = (string) $check;
            } elseif ($number >= 101 && $number <= 110) {
                $selectedGroups['DevOps Readiness Checks (Weight: 5%)'][] = (string) $check;
            } elseif ($number >= 111 && $number <= 120) {
                $selectedGroups['AI Readiness Checks (Weight: 5%)'][] = (string) $check;
            } else {
                $selectedGroups['SonarQube Rules (Code Quality)'][] = (string) $check;
            }
        }

        echo '<ul style="list-style:none;margin:0;padding:0">';
        foreach ($selectedGroups as $groupName => $items) {
            if (empty($items)) {
                continue;
            }
            echo '<li class="list-head" style="padding:10px 12px;border:1px solid #e5e7eb">' . h($groupName) . '</li>';
            foreach ($items as $item) {
                echo '<li style="padding:10px 12px;border:1px solid #e5e7eb;border-top:none">' . h($item) . '</li>';
            }
        }
        echo '</ul>';
    }
    echo '</div>';

    echo '<div class="card"><h2 style="margin-top:0">Analysis Checks</h2>';
    if (empty($checkRuns)) {
        echo '<p class="meta">No selected checks.</p>';
    } else {
        $analysisGroups = [
            'OWASP Checks' => [],
            'Complexity Checks' => [],
            'SonarQube Rules (Code Quality)' => [],
            'Clean Code Checks (Weight: 10%)' => [],
            'Architecture Checks (Weight: 10%)' => [],
            'Testing Checks (Weight: 10%)' => [],
            'Performance Checks (Weight: 10%)' => [],
            'Reliability Checks (Weight: 10%)' => [],
            'Testing Plus Checks (Weight: 10%)' => [],
            'Dependency SBOM Checks (Weight: 5%)' => [],
            'DevOps Readiness Checks (Weight: 5%)' => [],
            'AI Readiness Checks (Weight: 5%)' => [],
        ];

        foreach ($checkRuns as $cr) {
            $id = checkDetailIdFromName((string) ($cr['check_name'] ?? ''));
            if ($id === null) {
                continue;
            }
            $num = (int) $id;
            if ($num >= 1 && $num <= 10) {
                $analysisGroups['OWASP Checks'][] = $cr;
            } elseif ($num >= 11 && $num <= 20) {
                $analysisGroups['Complexity Checks'][] = $cr;
            } elseif ($num >= 31 && $num <= 40) {
                $analysisGroups['Clean Code Checks (Weight: 10%)'][] = $cr;
            } elseif ($num >= 41 && $num <= 50) {
                $analysisGroups['Architecture Checks (Weight: 10%)'][] = $cr;
            } elseif ($num >= 51 && $num <= 60) {
                $analysisGroups['Testing Checks (Weight: 10%)'][] = $cr;
            } elseif ($num >= 61 && $num <= 70) {
                $analysisGroups['Performance Checks (Weight: 10%)'][] = $cr;
            } elseif ($num >= 71 && $num <= 80) {
                $analysisGroups['Reliability Checks (Weight: 10%)'][] = $cr;
            } elseif ($num >= 81 && $num <= 90) {
                $analysisGroups['Testing Plus Checks (Weight: 10%)'][] = $cr;
            } elseif ($num >= 91 && $num <= 100) {
                $analysisGroups['Dependency SBOM Checks (Weight: 5%)'][] = $cr;
            } elseif ($num >= 101 && $num <= 110) {
                $analysisGroups['DevOps Readiness Checks (Weight: 5%)'][] = $cr;
            } elseif ($num >= 111 && $num <= 120) {
                $analysisGroups['AI Readiness Checks (Weight: 5%)'][] = $cr;
            } else {
                $analysisGroups['SonarQube Rules (Code Quality)'][] = $cr;
            }
        }

        echo '<div class="checks-grid">';
        foreach ($analysisGroups as $groupName => $items) {
            if (empty($items)) {
                continue;
            }
            echo '<div class="check-group-heading">' . h($groupName) . '</div>';
            foreach ($items as $cr) {
            $checkName = h((string) ($cr['check_name'] ?? 'Unknown'));
            $checkNameRaw = (string) ($cr['check_name'] ?? 'Unknown');
            $status = (string) ($cr['status'] ?? 'unknown');
            $count = (int) ($cr['finding_count'] ?? 0);
            $statusNorm = strtolower($status);
            $tileClass = $statusNorm === 'clean' ? 'clean' : ($statusNorm === 'not_run' ? '' : 'issues');
            $detailsUrl = '';
            $checkId = checkDetailIdFromName($checkNameRaw);
            if ($checkId !== null && (int) $checkId <= 120) {
                $detailsUrl = absoluteCheckDetailsUrl([
                    'check_id' => $checkId,
                    'name' => $checkNameRaw,
                    'status' => $status,
                    'count' => (string) $count,
                    'scan_id' => (string) $scanId,
                ]);
            }
            
            if ($detailsUrl !== '' && !$isDocDownload) {
                echo '<a href="' . h($detailsUrl) . '" class="check-details-trigger" data-title="' . h($checkNameRaw) . '" style="text-decoration:none;color:inherit">';
            }
            echo '<div class="check-tile ' . $tileClass . '">';
            echo '<span class="check-name">' . $checkName . '</span>';
            echo '<span class="check-count">' . ($statusNorm === 'not_run' ? '-' : (string) $count) . '</span>';
            echo '<span class="check-label">' . ($statusNorm === 'not_run' ? 'Not run' : ($count === 0 ? 'No issues' : ($count === 1 ? '1 issue' : $count . ' issues'))) . '</span>';
            echo '</div>';
            if ($detailsUrl !== '' && !$isDocDownload) {
                echo '</a>';
            }
        }
        }
        echo '</div>';
    }
    echo '</div>';

    if ($isDocDownload && !empty($checkRuns)) {
        $detailContentMap = checkDetailContentMap();
        $findingsCategoryMap = findingsByCheckCategoryMap();
        $recommendationKeywordMap = recommendationKeywordsByCheckMap();

        foreach ($checkRuns as $cr) {
            $checkNameRaw = (string) ($cr['check_name'] ?? 'Unknown Check');
            $checkId = checkDetailIdFromName($checkNameRaw);
            if ($checkId === null) {
                continue;
            }

            $detail = $detailContentMap[$checkId] ?? [
                'title' => $checkNameRaw,
                'tag' => 'Analyzer Check',
                'about' => 'This check evaluates repository quality and risk signals.',
                'looks_for' => ['Check-specific risk indicators in code and metadata.'],
                'recommendations' => ['Review findings and apply remediation based on risk.'],
                'why' => 'Early detection helps reduce delivery and security risk.',
            ];

            $statusRaw = strtolower((string) ($cr['status'] ?? 'unknown'));
            $statusLabel = 'Unknown';
            if ($statusRaw === 'clean') {
                $statusLabel = 'Clean';
            } elseif ($statusRaw === 'issues' || $statusRaw === 'issues_found') {
                $statusLabel = 'Issues Found';
            }

            $count = (int) ($cr['finding_count'] ?? 0);
            $findingCountText = $count . ' finding' . ($count === 1 ? '' : 's');

            $findingsForCheck = [];
            $findingsInfo = '';
            $categories = $findingsCategoryMap[$checkId] ?? [];
            if ($scanId <= 0) {
                $findingsInfo = 'Scan ID is not available, so finding details cannot be loaded from the database.';
            } elseif ($count === 0) {
                $findingsInfo = 'No findings were reported for this check in this scan.';
            } elseif (empty($categories)) {
                $findingsInfo = 'Detailed per-check finding mapping is not available for this check yet.';
            } else {
                $categoryScopedFindings = [];
                foreach ($findings as $finding) {
                    $category = (string) ($finding['category'] ?? '');
                    if (in_array($category, $categories, true)) {
                        $categoryScopedFindings[] = $finding;
                    }
                }

                $expectedTitle = strtolower(trim((string) preg_replace('/^#\s*\d+\s*/', '', (string) ($detail['title'] ?? ''))));
                $expectedFromCheckName = strtolower(trim((string) preg_replace('/^#\s*\d+\s*/', '', $checkNameRaw)));
                $expectedTitles = array_values(array_filter([$expectedTitle, $expectedFromCheckName], static fn($value) => $value !== ''));
                $keywordMatches = [];

                foreach ($categoryScopedFindings as $finding) {
                    $findingTitleRaw = (string) ($finding['title'] ?? '');
                    $findingTitle = strtolower(trim($findingTitleRaw));
                    $findingTitleNoNumber = strtolower(trim((string) preg_replace('/^#\s*\d+\s*/', '', $findingTitle)));
                    $findingDescription = strtolower(trim((string) ($finding['description'] ?? '')));

                    $isMatch = false;
                    if (preg_match('/#\s*(\d+)/', $findingTitleRaw, $numberMatch) === 1 && $numberMatch[1] === $checkId) {
                        $isMatch = true;
                    }

                    if (!$isMatch) {
                        foreach ($expectedTitles as $expected) {
                            if ($expected === '') {
                                continue;
                            }
                            if ($findingTitleNoNumber === $expected
                                || str_contains($findingTitleNoNumber, $expected)
                                || str_contains($expected, $findingTitleNoNumber)) {
                                $isMatch = true;
                                break;
                            }
                        }
                    }

                    if (!$isMatch && !empty($keywordMatches)) {
                        foreach ($keywordMatches as $keyword) {
                            if ($keyword === '') {
                                continue;
                            }
                            if (str_contains($findingTitle, $keyword) || str_contains($findingDescription, $keyword)) {
                                $isMatch = true;
                                break;
                            }
                        }
                    }

                    if ($isMatch) {
                        $findingsForCheck[] = $finding;
                    }
                }

                if (empty($findingsForCheck) && !empty($categoryScopedFindings) && $count > 0) {
                    $expectedForSimilarity = $expectedTitles[0] ?? '';
                    if ($expectedForSimilarity !== '') {
                        usort($categoryScopedFindings, static function (array $a, array $b) use ($expectedForSimilarity): int {
                            $titleA = strtolower(trim((string) ($a['title'] ?? '')));
                            $titleB = strtolower(trim((string) ($b['title'] ?? '')));

                            similar_text($expectedForSimilarity, $titleA, $scoreA);
                            similar_text($expectedForSimilarity, $titleB, $scoreB);

                            if ($scoreA === $scoreB) {
                                return 0;
                            }
                            return $scoreA < $scoreB ? 1 : -1;
                        });
                    }
                    $findingsForCheck = array_slice($categoryScopedFindings, 0, max(1, $count));
                }

                if (empty($findingsForCheck)) {
                    $findingsInfo = 'No matching finding rows were found for this check in the saved scan details.';
                }
            }

            $scanRecommendationsForCheck = [];
            $recommendationsInfo = '';
            if ($scanId <= 0) {
                $recommendationsInfo = 'Scan ID is not available, so scan recommendations cannot be loaded.';
            } elseif ($count === 0) {
                $recommendationsInfo = 'No scan-specific recommendations were generated for this check in this scan, showing baseline guidance below.';
            } else {
                $keywords = $recommendationKeywordMap[$checkId] ?? [];
                $expectedRecTitle = strtolower(trim((string) preg_replace('/^#\s*\d+\s*/', '', (string) ($detail['title'] ?? $checkNameRaw))));
                $titleStopWords = ['clean', 'code', 'test', 'tests', 'pyramid', 'and', 'the', 'for', 'with', 'rule', 'checks'];
                $titleTokens = array_values(array_filter(
                    preg_split('/[^a-z0-9]+/', $expectedRecTitle) ?: [],
                    static fn($token) => $token !== '' && strlen($token) >= 4 && !in_array($token, $titleStopWords, true)
                ));

                $scoredRecommendations = [];
                foreach ($recommendations as $rec) {
                    $text = strtolower((string) ($rec['recommendation_text'] ?? ''));
                    $score = 0;
                    $mentionsCheckId = false;
                    $hasExactTitle = false;

                    if ($checkId !== '' && preg_match('/#\s*' . preg_quote($checkId, '/') . '\b/', $text) === 1) {
                        $score += 5;
                        $mentionsCheckId = true;
                    }
                    if ($expectedRecTitle !== '' && str_contains($text, $expectedRecTitle)) {
                        $score += 4;
                        $hasExactTitle = true;
                    }

                    $keywordMatches = 0;
                    foreach ($keywords as $keyword) {
                        if ($keyword !== '' && str_contains($text, strtolower($keyword))) {
                            $keywordMatches++;
                        }
                    }
                    $score += $keywordMatches * 2;

                    $tokenMatches = 0;
                    foreach ($titleTokens as $token) {
                        if (str_contains($text, $token)) {
                            $tokenMatches++;
                        }
                    }
                    if ($tokenMatches >= 2) {
                        $score += 2;
                    }

                    $maxBaselineSimilarity = 0.0;
                    foreach ((array) ($detail['recommendations'] ?? []) as $baselineRec) {
                        $baselineText = strtolower(trim((string) $baselineRec));
                        if ($baselineText === '') {
                            continue;
                        }
                        similar_text($baselineText, $text, $similarityPercent);
                        if ($similarityPercent > $maxBaselineSimilarity) {
                            $maxBaselineSimilarity = $similarityPercent;
                        }
                    }
                    if ($maxBaselineSimilarity >= 45.0) {
                        $score += 3;
                    } elseif ($maxBaselineSimilarity >= 35.0) {
                        $score += 2;
                    }

                    $isEligible = $mentionsCheckId
                        || $hasExactTitle
                        || ($keywordMatches >= 2)
                        || ($keywordMatches >= 1 && $tokenMatches >= 1)
                        || ($keywordMatches >= 1 && $maxBaselineSimilarity >= 35.0);

                    if ($isEligible && $score >= 4) {
                        $scoredRecommendations[] = ['score' => $score, 'rec' => $rec];
                    }
                }

                if (!empty($scoredRecommendations)) {
                    $priorityRank = ['High' => 3, 'Medium' => 2, 'Low' => 1];
                    usort($scoredRecommendations, static function (array $a, array $b) use ($priorityRank): int {
                        $scoreA = (int) ($a['score'] ?? 0);
                        $scoreB = (int) ($b['score'] ?? 0);
                        if ($scoreA !== $scoreB) {
                            return $scoreA < $scoreB ? 1 : -1;
                        }

                        $priorityA = (string) (($a['rec']['priority'] ?? 'Low'));
                        $priorityB = (string) (($b['rec']['priority'] ?? 'Low'));
                        $rankA = $priorityRank[$priorityA] ?? 0;
                        $rankB = $priorityRank[$priorityB] ?? 0;
                        if ($rankA === $rankB) {
                            return 0;
                        }
                        return $rankA < $rankB ? 1 : -1;
                    });

                    $maxRecommendations = 3;
                    if ($count > 0) {
                        $maxRecommendations = max(1, min(3, $count));
                    }
                    $scanRecommendationsForCheck = array_values(array_map(
                        static fn($item) => $item['rec'],
                        array_slice($scoredRecommendations, 0, $maxRecommendations)
                    ));
                }
                if (empty($scanRecommendationsForCheck)) {
                    $recommendationsInfo = 'No scan-specific recommendation text matched this check, showing baseline guidance below.';
                }
            }

            echo '<div class="card">';
            echo '<h2 style="margin-top:0">' . h((string) $detail['title']) . '</h2>';
            echo '<p class="meta">Details about what this analyzer checks, what it looks for, and the latest result details.</p>';

            echo '<h3 style="margin-bottom:8px">Current Result</h3>';
            echo '<p class="meta" style="margin-top:0">This section shows the latest value passed from the Analysis Checks card.</p>';
            echo '<ul>';
            echo '<li><strong>Check:</strong> ' . h($checkNameRaw) . '</li>';
            echo '<li><strong>Status:</strong> ' . h($statusLabel) . '</li>';
            echo '<li><strong>Finding count:</strong> ' . h($findingCountText) . '</li>';
            echo '<li><strong>Scan ID:</strong> ' . h((string) $scanId) . '</li>';
            echo '</ul>';

            echo '<h3 style="margin-bottom:8px">Findings For This Check</h3>';
            if (!empty($findingsForCheck)) {
                foreach ($findingsForCheck as $finding) {
                    echo '<div style="padding:8px 0;border-top:1px solid #f3f4f6">';
                    echo '<div><strong>' . h((string) ($finding['title'] ?? 'Untitled finding')) . '</strong> <span class="tag">' . h((string) ($finding['severity'] ?? 'Info')) . '</span></div>';
                    echo '<div class="meta">Category: ' . h((string) ($finding['category'] ?? 'Unknown')) . '</div>';
                    echo '<div>' . h((string) ($finding['description'] ?? '')) . '</div>';
                    echo '</div>';
                }
            } else {
                echo '<p>' . h($findingsInfo !== '' ? $findingsInfo : 'No findings available for this check.') . '</p>';
            }

            echo '<span class="tag">' . h((string) $detail['tag']) . '</span>';
            echo '<h3 style="margin-bottom:8px;margin-top:12px">What this check is about</h3>';
            echo '<p>' . h((string) $detail['about']) . '</p>';

            echo '<h3 style="margin-bottom:8px">What the analyzer looks for</h3>';
            echo '<ul>';
            foreach ((array) ($detail['looks_for'] ?? []) as $rule) {
                echo '<li>' . h((string) $rule) . '</li>';
            }
            echo '</ul>';

            echo '<h3 style="margin-bottom:8px">How findings are reported</h3>';
            echo '<p class="meta" style="margin-top:0">For each finding, the system usually reports:</p>';
            echo '<ul>';
            echo '<li>Title of the issue and risk severity.</li>';
            echo '<li>Category and short description.</li>';
            echo '<li>Code/location context when available.</li>';
            echo '<li>Recommendation to reduce the risk.</li>';
            echo '</ul>';

            echo '<h3 style="margin-bottom:8px">Recommendations For This Check</h3>';
            if (!empty($scanRecommendationsForCheck)) {
                echo '<ul>';
                foreach ($scanRecommendationsForCheck as $rec) {
                    $priority = (string) ($rec['priority'] ?? 'Low');
                    echo '<li><strong>[' . h($priority) . ']</strong> ' . h((string) ($rec['recommendation_text'] ?? '')) . '</li>';
                }
                echo '</ul>';
            }
            if ($recommendationsInfo !== '') {
                echo '<p class="meta">' . h($recommendationsInfo) . '</p>';
            }

            echo '<h4 style="margin-bottom:8px">Baseline Guidance</h4>';
            echo '<ul>';
            foreach ((array) ($detail['recommendations'] ?? []) as $recommendation) {
                echo '<li>' . h((string) $recommendation) . '</li>';
            }
            echo '</ul>';

            echo '<h3 style="margin-bottom:8px">Why this matters</h3>';
            echo '<p style="margin-bottom:0">' . h((string) $detail['why']) . '</p>';
            echo '</div>';
        }
    }

    if (!$isDocDownload) {
        echo '<div id="check-details-popup" class="details-popup" aria-hidden="true">';
        echo '<div class="details-popup-dialog" role="dialog" aria-modal="true" aria-labelledby="check-details-title">';
        echo '<div class="details-popup-header">';
        echo '<h3 id="check-details-title" class="details-popup-title">Check Details</h3>';
        echo '<button type="button" id="check-details-close" class="details-popup-close" aria-label="Close details popup">Close</button>';
        echo '</div>';
        echo '<div class="details-popup-body">';
        echo '<iframe id="check-details-iframe" class="details-popup-iframe" src="about:blank" title="Check Details"></iframe>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    echo '<div class="card"><h2 style="margin-top:0">Findings</h2>';
    if (empty($findings)) {
        echo '<p class="meta">No findings recorded.</p>';
    } else {
        foreach ($findings as $finding) {
            $sevClass = 'sev-info';
            if ($finding['severity'] === 'High') {
                $sevClass = 'sev-high';
            } elseif ($finding['severity'] === 'Medium') {
                $sevClass = 'sev-medium';
            } elseif ($finding['severity'] === 'Low') {
                $sevClass = 'sev-low';
            }
            echo '<div style="padding:10px 0;border-top:1px solid #f3f4f6">';
            echo '<div><strong>' . h((string) $finding['title']) . '</strong> <span class="tag ' . h($sevClass) . '">' . h((string) $finding['severity']) . '</span></div>';
            echo '<div class="meta">Category: ' . h((string) $finding['category']) . '</div>';
            echo '<div>' . h((string) $finding['description']) . '</div>';
            echo '</div>';
        }
    }
    echo '</div>';

    echo '<div class="card"><h2 style="margin-top:0">Recommendations</h2>';
    $hasFindings = !empty($findings);
    if ($hasFindings && !empty($recommendations)) {
        $priorityOrder = ['High' => 0, 'Medium' => 1, 'Low' => 2];
        usort($recommendations, static function (array $a, array $b) use ($priorityOrder): int {
            $pA = $priorityOrder[(string) ($a['priority'] ?? '')] ?? 3;
            $pB = $priorityOrder[(string) ($b['priority'] ?? '')] ?? 3;
            if ($pA !== $pB) {
                return $pA <=> $pB;
            }
            $aText = (string) ($a['recommendation_text'] ?? '');
            $bText = (string) ($b['recommendation_text'] ?? '');
            $aNum = preg_match('/^#\s*(\d+)/', $aText, $mA) === 1 ? (int) $mA[1] : null;
            $bNum = preg_match('/^#\s*(\d+)/', $bText, $mB) === 1 ? (int) $mB[1] : null;
            if ($aNum !== null && $bNum !== null && $aNum !== $bNum) {
                return $aNum <=> $bNum;
            }
            if ($aNum !== null && $bNum === null) {
                return -1;
            }
            if ($aNum === null && $bNum !== null) {
                return 1;
            }
            return strcasecmp($aText, $bText);
        });

        $checkSpecific = [];
        $general = [];
        foreach ($recommendations as $rec) {
            $text = (string) ($rec['recommendation_text'] ?? '');
            if (preg_match('/^#\s*(\d+)/', $text) === 1) {
                $checkSpecific[] = $rec;
            } else {
                $general[] = $rec;
            }
        }

        echo '<ul style="list-style:none;margin:0;padding:0">';
        echo '<li class="list-head" style="padding:10px 12px;border:1px solid #e5e7eb">Fix Recommendations</li>';
        $showSubgroupTitles = !empty($checkSpecific) && !empty($general);
        if (!empty($checkSpecific)) {
            if ($showSubgroupTitles) {
            echo '<li class="list-head" style="padding:10px 12px;border:1px solid #e5e7eb;border-top:none">Check-specific Recommendations</li>';
            }
            foreach ($checkSpecific as $rec) {
                echo '<li style="padding:10px 12px;border:1px solid #e5e7eb;border-top:none"><strong>[' . h((string) $rec['priority']) . ']</strong> ' . h((string) $rec['recommendation_text']) . '</li>';
            }
        }
        if (!empty($general)) {
            if ($showSubgroupTitles) {
            echo '<li class="list-head" style="padding:10px 12px;border:1px solid #e5e7eb;border-top:none">General Recommendations</li>';
            }
            foreach ($general as $rec) {
                echo '<li style="padding:10px 12px;border:1px solid #e5e7eb;border-top:none"><strong>[' . h((string) $rec['priority']) . ']</strong> ' . h((string) $rec['recommendation_text']) . '</li>';
            }
        }
        echo '</ul>';
    } elseif ($hasFindings) {
        echo '<p class="meta">Findings detected, but no remediation text was generated for this scan.</p>';
    } else {
        echo '<ul style="list-style:none;margin:0;padding:0">';
        echo '<li class="list-head" style="padding:10px 12px;border:1px solid #e5e7eb">Preventive Best Practices</li>';
        echo '<li style="padding:10px 12px;border:1px solid #e5e7eb;border-top:none;color:#166534">No issues detected in this scan.</li>';
        echo '<li style="padding:10px 12px;border:1px solid #e5e7eb;border-top:none">Keep dependency, secret-scanning, and lint checks in CI for every pull request.</li>';
        echo '<li style="padding:10px 12px;border:1px solid #e5e7eb;border-top:none">Enforce branch protection and require at least one reviewer before merge.</li>';
        echo '<li style="padding:10px 12px;border:1px solid #e5e7eb;border-top:none">Schedule periodic audits for licenses, headers, and complexity trends.</li>';
        echo '</ul>';
    }
    echo '</div>';

    echo '<div class="card"><h2 style="margin-top:0">Skills</h2>';
    if (empty($skills)) {
        echo '<p class="meta">No skills recorded.</p>';
    } else {
        echo '<ul>';
        foreach ($skills as $skill) {
            echo '<li>' . h((string) $skill['skill_name']) . ' (' . h((string) $skill['proficiency_level']) . ', risk: ' . h((string) $skill['risk_level']) . ')</li>';
        }
        echo '</ul>';
    }
    echo '</div>';

    if (!$isDocDownload) {
        echo '<script>(function(){var key="ai_git_repo_theme";var btn=document.getElementById("theme-toggle");var popup=document.getElementById("check-details-popup");var iframe=document.getElementById("check-details-iframe");var title=document.getElementById("check-details-title");var closeBtn=document.getElementById("check-details-close");function pref(){var s=localStorage.getItem(key);if(s==="dark"||s==="light"){return s;}return window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";}function apply(t){var next=t==="dark"?"dark":"light";document.body.setAttribute("data-theme",next);if(btn){btn.textContent=next==="dark"?"Light Mode":"Dark Mode";}}function openPopup(url,text){if(!popup||!iframe){return;}if(title){title.textContent=text||"Check Details";}iframe.setAttribute("src",url||"about:blank");popup.classList.add("open");popup.setAttribute("aria-hidden","false");}function closePopup(){if(!popup||!iframe){return;}popup.classList.remove("open");popup.setAttribute("aria-hidden","true");iframe.setAttribute("src","about:blank");}apply(pref());if(btn){btn.addEventListener("click",function(){var current=document.body.getAttribute("data-theme")==="dark"?"dark":"light";var next=current==="dark"?"light":"dark";localStorage.setItem(key,next);apply(next);});}if(closeBtn){closeBtn.addEventListener("click",closePopup);}if(popup){popup.addEventListener("click",function(e){if(e.target===popup){closePopup();}});}document.querySelectorAll("a.check-details-trigger").forEach(function(link){link.addEventListener("click",function(e){e.preventDefault();openPopup(link.getAttribute("href"),link.getAttribute("data-title")||"Check Details");});});})();</script>';
    }
    echo '</div></body></html>';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Unable to generate report.',
        'details' => $e->getMessage(),
    ]);
}
