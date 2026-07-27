<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

$checkIdRaw = trim((string) ($_GET['check_id'] ?? '1'));
$checkId = preg_match('/^(110|10[0-9]|100|9[0-9]|8[0-9]|7[0-9]|6[0-9]|5[0-9]|4[0-9]|3[0-9]|2[0-9]|10|[1-9])$/', $checkIdRaw) === 1 ? $checkIdRaw : '1';
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
    '31' => [
        'title' => '#31 Clean Code SOLID Principles',
        'tag' => 'Clean Code Focus',
        'about' => 'This check looks for modules and abstractions that violate the SOLID design principles.',
        'looks_for' => [
            'Large modules with too many reasons to change.',
            'Interfaces or services that are broader than they need to be.',
            'Classes that bundle unrelated responsibilities.',
            'Designs that are hard to extend without touching many files.',
        ],
        'recommendations' => [
            'Split responsibilities into smaller classes or services with a single purpose.',
            'Keep abstractions narrow and favor composition over broad dependencies.',
            'Refactor hotspots that change for unrelated reasons.',
        ],
        'why' => 'SOLID design keeps the codebase easier to extend, test, and safely refactor.',
    ],
    '32' => [
        'title' => '#32 Clean Code DRY Principle',
        'tag' => 'Clean Code Focus',
        'about' => 'This check highlights repeated logic and copy-pasted blocks that should be shared.',
        'looks_for' => [
            'Repeated blocks of code across files.',
            'Similar logic implemented in multiple places.',
            'Duplicated validation or transformation steps.',
            'Copy-paste patterns that increase bug risk.',
        ],
        'recommendations' => [
            'Extract common behavior into helpers, services, or shared modules.',
            'Add duplication checks to CI so repeated code does not spread.',
            'Prefer reuse over rewriting the same logic in a second place.',
        ],
        'why' => 'Removing duplication reduces maintenance cost and keeps fixes consistent.',
    ],
    '33' => [
        'title' => '#33 Clean Code KISS Principle',
        'tag' => 'Clean Code Focus',
        'about' => 'This check looks for code that is more complex than it needs to be.',
        'looks_for' => [
            'Deeply nested conditionals.',
            'Long functions with many branches.',
            'Control flow that is hard to read at a glance.',
            'Logic that could be simplified with early returns or helpers.',
        ],
        'recommendations' => [
            'Break complex routines into smaller steps with clear names.',
            'Use early returns and guard clauses to flatten logic.',
            'Prefer direct, readable code over clever but dense patterns.',
        ],
        'why' => 'Simple code is easier to understand, test, and change safely.',
    ],
    '34' => [
        'title' => '#34 Clean Code YAGNI Principle',
        'tag' => 'Clean Code Focus',
        'about' => 'This check surfaces unfinished or speculative code that may not be needed yet.',
        'looks_for' => [
            'TODO, FIXME, HACK, or temporary markers.',
            'Placeholder branches and half-finished features.',
            'Code paths that appear to exist for future use only.',
            'Dead scaffolding that adds noise without value.',
        ],
        'recommendations' => [
            'Move unfinished work into tracked issues instead of leaving it in production code.',
            'Remove speculative code until there is a real requirement for it.',
            'Keep the main path focused on what is needed now.',
        ],
        'why' => 'Avoiding speculative code keeps the repository smaller, clearer, and less fragile.',
    ],
    '35' => [
        'title' => '#35 Clean Code Single Responsibility',
        'tag' => 'Clean Code Focus',
        'about' => 'This check looks for files that try to do too many jobs at once.',
        'looks_for' => [
            'Modules that mix orchestration, data access, and presentation.',
            'Files that grow into catch-all utility buckets.',
            'Classes or scripts with many unrelated methods.',
            'Responsibilities that would be easier to test separately.',
        ],
        'recommendations' => [
            'Move each responsibility into a focused class or function.',
            'Keep business logic, transport logic, and rendering separate.',
            'Refactor large files before they become hard to maintain.',
        ],
        'why' => 'A single responsibility per module improves readability and reduces change risk.',
    ],
    '36' => [
        'title' => '#36 Clean Code Separation of Concerns',
        'tag' => 'Clean Code Focus',
        'about' => 'This check looks for code that mixes layers or concerns that should stay separate.',
        'looks_for' => [
            'UI markup mixed with persistence or business logic.',
            'Controller code that also renders complex views.',
            'Database, HTTP, and formatting concerns in one file.',
            'Layer boundaries that are unclear or inconsistent.',
        ],
        'recommendations' => [
            'Split the code into presentation, application, and persistence layers.',
            'Use service objects or helper modules to isolate orchestration from UI code.',
            'Keep layer boundaries explicit and consistent.',
        ],
        'why' => 'Clear concern boundaries make the code easier to understand and evolve.',
    ],
    '37' => [
        'title' => '#37 Clean Code Meaningful Naming',
        'tag' => 'Clean Code Focus',
        'about' => 'This check looks for generic or ambiguous identifiers that make intent hard to understand.',
        'looks_for' => [
            'Variables with names like data, temp, item, or value used broadly.',
            'Functions whose names do not clearly describe what they do.',
            'Classes or methods that hide purpose behind vague labels.',
            'Identifiers that force readers to inspect implementation to understand intent.',
        ],
        'recommendations' => [
            'Rename identifiers to reflect domain intent, ownership, and behavior.',
            'Prefer specific names over placeholder or one-size-fits-all labels.',
            'Use names as documentation for future readers.',
        ],
        'why' => 'Meaningful naming reduces cognitive load and makes code easier to review.',
    ],
    '38' => [
        'title' => '#38 Clean Code Small Functions',
        'tag' => 'Clean Code Focus',
        'about' => 'This check looks for functions that do too much and should be split into smaller helpers.',
        'looks_for' => [
            'Long functions with multiple distinct steps.',
            'Procedures that mix validation, transformation, and persistence.',
            'Methods that are hard to scan or test in one sitting.',
            'Large blocks of code that would be clearer as small helpers.',
        ],
        'recommendations' => [
            'Extract sub-steps into focused helper functions.',
            'Keep functions short enough to explain in one sentence.',
            'Use guard clauses and early returns to simplify flow.',
        ],
        'why' => 'Small functions are easier to read, test, and reuse.',
    ],
    '39' => [
        'title' => '#39 Clean Code Consistent Formatting',
        'tag' => 'Clean Code Focus',
        'about' => 'This check looks for style inconsistencies that make the code harder to scan.',
        'looks_for' => [
            'Long lines and inconsistent spacing.',
            'Trailing whitespace or formatting noise.',
            'Mixed indentation or irregular layout patterns.',
            'Code that would benefit from an automatic formatter.',
        ],
        'recommendations' => [
            'Use a formatter and style guide consistently across the repository.',
            'Automate formatting in CI or pre-commit hooks.',
            'Keep line length and indentation conventions consistent.',
        ],
        'why' => 'Consistent formatting makes intent easier to see quickly.',
    ],
    '40' => [
        'title' => '#40 Clean Code Explicit Error Handling',
        'tag' => 'Clean Code Focus',
        'about' => 'This check looks for silent failures, swallowed exceptions, or suppressed error paths.',
        'looks_for' => [
            'Empty catch blocks or ignored exceptions.',
            'Error suppression that hides failure conditions.',
            'Return paths that fail without logging or propagation.',
            'Defensive checks that do not surface the underlying problem.',
        ],
        'recommendations' => [
            'Handle failures explicitly with logs, retries, or rethrows where appropriate.',
            'Avoid silent suppression of exceptions and warnings.',
            'Make failure modes visible to callers and operators.',
        ],
        'why' => 'Explicit error handling keeps failures visible and debuggable.',
    ],
    '41' => [
        'title' => '#41 Clean Architecture Layered Boundaries',
        'tag' => 'Clean Architecture Focus',
        'about' => 'This check verifies that presentation, application, infrastructure, and feature concerns remain separated.',
        'looks_for' => [
            'Presentation files staying at the edge of the system.',
            'Application services separated from UI code.',
            'Feature folders grouped by responsibility.',
            'Boundary rules that match Clean Architecture layers.',
        ],
        'recommendations' => [
            'Keep each layer focused on its own responsibility.',
            'Avoid pushing framework concerns into the core modules.',
            'Preserve a consistent layered structure as the project grows.',
        ],
        'why' => 'Layered boundaries make the codebase easier to extend and reason about.',
    ],
    '42' => [
        'title' => '#42 Clean Architecture Dependency Rule',
        'tag' => 'Clean Architecture Focus',
        'about' => 'This check looks for dependencies that point outward instead of inward toward policy.',
        'looks_for' => [
            'Inner modules importing UI entry points.',
            'Application code depending on concrete presentation files.',
            'Direction-of-dependency violations across layers.',
        ],
        'recommendations' => [
            'Make dependencies flow toward business rules and abstractions.',
            'Replace direct outer-layer references with adapters or interfaces.',
            'Keep UI code dependent on the core, not the other way around.',
        ],
        'why' => 'The dependency rule protects core logic from outer-layer churn.',
    ],
    '43' => [
        'title' => '#43 Clean Architecture Framework Independence',
        'tag' => 'Clean Architecture Focus',
        'about' => 'This check verifies that core modules remain usable without presentation framework artifacts.',
        'looks_for' => [
            'HTML or CSS embedded in core modules.',
            'Bootstrap or similar framework artifacts in analyzers.',
            'Logic that would break if the UI framework changed.',
        ],
        'recommendations' => [
            'Keep framework-specific code in the outermost layer.',
            'Return structured data from analyzers and render it elsewhere.',
            'Avoid coupling core code to markup or client libraries.',
        ],
        'why' => 'Framework independence makes the project easier to maintain and port.',
    ],
    '44' => [
        'title' => '#44 Clean Architecture Presentation Isolation',
        'tag' => 'Clean Architecture Focus',
        'about' => 'This check looks for presentation code that mixes with persistence or remote orchestration.',
        'looks_for' => [
            'Views that also query databases.',
            'Pages that make HTTP calls directly.',
            'Presentation files coordinating unrelated infrastructure tasks.',
        ],
        'recommendations' => [
            'Keep presentation files focused on rendering only.',
            'Move data retrieval and orchestration behind a service boundary.',
            'Pass prepared data into the UI instead of pulling it from the view.',
        ],
        'why' => 'Presentation isolation keeps the outer layer simple and replaceable.',
    ],
    '45' => [
        'title' => '#45 Clean Architecture Use Case Separation',
        'tag' => 'Clean Architecture Focus',
        'about' => 'This check validates whether orchestration logic is separate from storage and transport details.',
        'looks_for' => [
            'Application services that coordinate behavior without owning SQL.',
            'Use cases mixed with repository implementation details.',
            'Orchestration layers that know too much about persistence.',
        ],
        'recommendations' => [
            'Keep use cases thin and focused on business flow.',
            'Push persistence and transport concerns into adapters.',
            'Define clear boundaries between orchestration and infrastructure.',
        ],
        'why' => 'Use case separation makes the application logic easier to test and evolve.',
    ],
    '46' => [
        'title' => '#46 Clean Architecture Domain Purity',
        'tag' => 'Clean Architecture Focus',
        'about' => 'This check looks for feature modules that stay pure and avoid leaking infrastructure concerns.',
        'looks_for' => [
            'Analyzers that print HTML or headers directly.',
            'Core feature code that talks to storage.',
            'Modules that combine logic with output formatting.',
        ],
        'recommendations' => [
            'Keep core feature modules logic-only.',
            'Return arrays or objects instead of rendering directly.',
            'Move output and infrastructure work to outer adapters.',
        ],
        'why' => 'Pure domain logic is easier to test and less likely to break when infrastructure changes.',
    ],
    '47' => [
        'title' => '#47 Clean Architecture Data Access Abstraction',
        'tag' => 'Clean Architecture Focus',
        'about' => 'This check verifies that data access is routed through shared helpers or adapters.',
        'looks_for' => [
            'Duplicated HTTP or repository calls across modules.',
            'Direct curl usage outside shared helpers.',
            'Repeated access logic instead of one adapter boundary.',
        ],
        'recommendations' => [
            'Centralize external access behind one adapter.',
            'Keep repository and API communication out of feature modules.',
            'Reuse helpers instead of duplicating transport code.',
        ],
        'why' => 'A single data-access abstraction reduces duplication and change risk.',
    ],
    '48' => [
        'title' => '#48 Clean Architecture Interface Adapter Separation',
        'tag' => 'Clean Architecture Focus',
        'about' => 'This check ensures adapters return data and do not take over presentation duties.',
        'looks_for' => [
            'Adapters that render HTML or echo output.',
            'Boundary code that mixes data shaping with UI rendering.',
            'Presentation responsibilities embedded in adapter modules.',
        ],
        'recommendations' => [
            'Keep adapters responsible for translating data only.',
            'Render views in the presentation layer, not in core modules.',
            'Use explicit data contracts between layers.',
        ],
        'why' => 'Adapter separation keeps concerns clean and prevents layered coupling.',
    ],
    '49' => [
        'title' => '#49 Clean Architecture Package Cohesion',
        'tag' => 'Clean Architecture Focus',
        'about' => 'This check evaluates whether related analysis code is grouped into cohesive folders.',
        'looks_for' => [
            'One folder per concern.',
            'Feature modules grouped by responsibility.',
            'Legacy wrapper files fragmenting the layout.',
        ],
        'recommendations' => [
            'Keep check families together under dedicated folders.',
            'Avoid scattering related rules across unrelated locations.',
            'Prefer cohesive packages that are easy to discover.',
        ],
        'why' => 'Cohesive packages make the project easier to navigate and extend.',
    ],
    '50' => [
        'title' => '#50 Clean Architecture No Cyclic Dependencies',
        'tag' => 'Clean Architecture Focus',
        'about' => 'This check looks for dependency loops or upward references that break the inward flow of Clean Architecture.',
        'looks_for' => [
            'Inner layers depending on UI entry points.',
            'Modules that create circular include paths.',
            'Layer coupling that makes changes ripple outward.',
        ],
        'recommendations' => [
            'Refactor imports so dependencies point inward only.',
            'Break cycles by introducing interfaces or adapters.',
            'Keep layer boundaries acyclic as new modules are added.',
        ],
        'why' => 'An acyclic dependency graph is a practical requirement for maintainable architecture.',
    ],
    '51' => [
        'title' => '#51 Test Pyramid Unit Coverage',
        'tag' => 'Testing Focus',
        'about' => 'This check looks for a strong unit-test layer around the repository analyzer core.',
        'looks_for' => [
            'PHPUnit unit test files and unit-oriented folders.',
            'Fast, isolated tests for helpers and analyzers.',
            'Unit coverage signals across the core application logic.',
        ],
        'recommendations' => [
            'Add PHPUnit unit tests for each analyzer helper and parser.',
            'Keep unit tests fast and isolated from external services.',
            'Use unit tests as the main layer of the Test Pyramid.',
        ],
        'why' => 'Unit tests provide the fastest and most reliable feedback in a Test Pyramid.',
    ],
    '52' => [
        'title' => '#52 Test Pyramid Integration Coverage',
        'tag' => 'Testing Focus',
        'about' => 'This check looks for integration tests that validate boundaries like APIs, persistence, and report generation.',
        'looks_for' => [
            'Tests that hit API endpoints or database boundaries.',
            'Integration-oriented test folders or naming patterns.',
            'Coverage of application wiring beyond isolated units.',
        ],
        'recommendations' => [
            'Add integration tests for API, database, and report flows.',
            'Keep boundary tests smaller in number than unit tests.',
            'Focus integration assertions on contracts and wiring.',
        ],
        'why' => 'Integration tests prove that the major layers work together correctly.',
    ],
    '53' => [
        'title' => '#53 Test Pyramid End-to-End Coverage',
        'tag' => 'Testing Focus',
        'about' => 'This check looks for a minimal end-to-end suite that validates the full user path.',
        'looks_for' => [
            'E2E tools like Playwright, Cypress, Selenium, or Codeception.',
            'Repository-level smoke tests for the main analyzer journey.',
            'User-flow tests that cover the application from input to report.',
        ],
        'recommendations' => [
            'Keep end-to-end tests small and focused on critical journeys.',
            'Do not depend on e2e tests for broad functional coverage.',
            'Use e2e tests as the smallest layer of the Test Pyramid.',
        ],
        'why' => 'End-to-end tests catch the highest-level workflow failures.',
    ],
    '54' => [
        'title' => '#54 Test Pyramid Fast Feedback',
        'tag' => 'Testing Focus',
        'about' => 'This check evaluates whether the test suite provides quick local feedback.',
        'looks_for' => [
            'A phpunit.xml configuration or a clearly runnable test suite.',
            'Fast unit-style assertions that can run on every change.',
            'Separation between quick and slow tests.',
        ],
        'recommendations' => [
            'Keep the default suite fast enough for frequent execution.',
            'Split slow boundary tests into a separate group.',
            'Optimize test startup and fixture setup.',
        ],
        'why' => 'Fast feedback keeps the Test Pyramid practical during development.',
    ],
    '55' => [
        'title' => '#55 Test Pyramid Mocking External APIs',
        'tag' => 'Testing Focus',
        'about' => 'This check looks for mocks, stubs, and fakes around GitHub, GitLab, and other external boundaries.',
        'looks_for' => [
            'Mock objects for API clients and HTTP calls.',
            'Stubs or fakes for repository and database boundaries.',
            'Deterministic tests that do not depend on live services.',
        ],
        'recommendations' => [
            'Mock external APIs so tests stay deterministic.',
            'Prefer interface seams around GitHub and database access.',
            'Avoid live network calls in unit tests.',
        ],
        'why' => 'Mocking external APIs keeps the Test Pyramid stable and repeatable.',
    ],
    '56' => [
        'title' => '#56 Test Pyramid Database Test Isolation',
        'tag' => 'Testing Focus',
        'about' => 'This check looks for isolation techniques that keep database tests from leaking state.',
        'looks_for' => [
            'In-memory databases or separate test databases.',
            'Transaction rollback or reset patterns.',
            'State isolation between test cases.',
        ],
        'recommendations' => [
            'Use an isolated database for test execution.',
            'Reset state between tests using transactions or fixtures.',
            'Do not let tests depend on each other.',
        ],
        'why' => 'Database isolation prevents flaky test behavior and hidden coupling.',
    ],
    '57' => [
        'title' => '#57 Test Pyramid API Response Validation',
        'tag' => 'Testing Focus',
        'about' => 'This check looks for assertions on API response structure, status, and error payloads.',
        'looks_for' => [
            'JSON structure or schema assertions.',
            'HTTP status code expectations.',
            'Error payload validation at the API boundary.',
        ],
        'recommendations' => [
            'Validate JSON and HTTP status codes in boundary tests.',
            'Check both success and error payloads.',
            'Keep response assertions close to the API contract.',
        ],
        'why' => 'API response validation protects the contract exposed by the analyzer.',
    ],
    '58' => [
        'title' => '#58 Test Pyramid Error Path Testing',
        'tag' => 'Testing Focus',
        'about' => 'This check looks for tests that exercise invalid input and failure handling paths.',
        'looks_for' => [
            'Invalid repository URLs and missing token cases.',
            'Exception and error handling assertions.',
            'Negative-path coverage for API and parsing failures.',
        ],
        'recommendations' => [
            'Add negative tests for invalid inputs and boundary errors.',
            'Assert failure modes explicitly instead of only happy paths.',
            'Cover exception handling in the core analyzers.',
        ],
        'why' => 'Error-path testing prevents regressions in failure handling.',
    ],
    '59' => [
        'title' => '#59 Test Pyramid Regression Test Coverage',
        'tag' => 'Testing Focus',
        'about' => 'This check looks for tests that protect against previously fixed bugs and edge cases.',
        'looks_for' => [
            'Regression-focused test names or issue references.',
            'Bug-fix coverage for analyzer edge cases.',
            'Tests that preserve behavior after repairs.',
        ],
        'recommendations' => [
            'Add regression tests for every important bug fix.',
            'Keep bug-linked tests in the default suite.',
            'Use regressions to protect the analyzer from reintroducing defects.',
        ],
        'why' => 'Regression tests stop known problems from coming back.',
    ],
    '60' => [
        'title' => '#60 Test Pyramid Test Organization and Maintainability',
        'tag' => 'Testing Focus',
        'about' => 'This check looks for a clear and maintainable test layout across unit, integration, and E2E layers.',
        'looks_for' => [
            'Dedicated unit, integration, and E2E folders.',
            'Readable test naming and helper reuse.',
            'A test structure that is easy to extend.',
        ],
        'recommendations' => [
            'Organize tests by pyramid layer and responsibility.',
            'Keep fixtures and helpers reusable.',
            'Make the test suite easy to navigate and expand.',
        ],
        'why' => 'Maintainable test organization keeps the pyramid healthy as the project grows.',
    ],
];

$dynamicCheckTitles = [
    '61' => '#61 Performance Nested Loops and Deep Iterations',
    '62' => '#62 Performance Expensive Operation Hotspots',
    '63' => '#63 Performance N+1 and Repeated Data Access Patterns',
    '64' => '#64 Performance Repeated External API Call Patterns',
    '65' => '#65 Performance Blocking Operation Risks',
    '66' => '#66 Performance Unbounded Query and Scan Risks',
    '67' => '#67 Performance Large Payload and Serialization Costs',
    '68' => '#68 Performance Cache Strategy and Miss Risks',
    '69' => '#69 Performance Synchronous I/O Hotspots',
    '70' => '#70 Performance Build and Runtime Efficiency Controls',
    '71' => '#71 Reliability Logging Coverage and Signal Quality',
    '72' => '#72 Reliability Retry Strategy and Backoff Safety',
    '73' => '#73 Reliability Timeout and Circuit Controls',
    '74' => '#74 Reliability Exception Handling Discipline',
    '75' => '#75 Reliability Null Safety and Defensive Guards',
    '76' => '#76 Reliability Resource Cleanup and Lifecycle Safety',
    '77' => '#77 Reliability Input Validation and Sanitization',
    '78' => '#78 Reliability Idempotency and Duplicate Request Safety',
    '79' => '#79 Reliability Fallback and Degradation Paths',
    '80' => '#80 Reliability Observability and Alerting Readiness',
    '81' => '#81 Testing FIRST Principle Alignment',
    '82' => '#82 Testing Arrange-Act-Assert Pattern Discipline',
    '83' => '#83 Testing Test Data Management and Isolation',
    '84' => '#84 Testing Flaky Test Risk Detection',
    '85' => '#85 Testing Boundary and Negative Path Coverage',
    '86' => '#86 Testing API Contract and Response Validation',
    '87' => '#87 Testing Security-Critical Path Coverage',
    '88' => '#88 Testing Performance-Critical Path Coverage',
    '89' => '#89 Testing CI Gate and Execution Reliability',
    '90' => '#90 Testing Suite Maintainability and Structure',
    '91' => '#91 Dependency Inventory',
    '92' => '#92 Vulnerability Detection',
    '93' => '#93 License Compliance',
    '94' => '#94 Supply Chain Security',
    '95' => '#95 Version Tracking',
    '96' => '#96 Risk Assessment',
    '97' => '#97 Dependency Mapping',
    '98' => '#98 Compliance and Auditing',
    '99' => '#99 Continuous SBOM Automation',
    '100' => '#100 Software Transparency',
    '101' => '#101 DevOps CI/CD Pipeline Coverage',
    '102' => '#102 DevOps Docker Build Readiness',
    '103' => '#103 DevOps Secrets Handling in Pipelines',
    '104' => '#104 DevOps Environment Configuration Management',
    '105' => '#105 DevOps Release Workflow Automation',
    '106' => '#106 DevOps GitHub Actions Security Hardening',
    '107' => '#107 DevOps Pull Request and Branch Quality Gates',
    '108' => '#108 DevOps Deployment Automation Signals',
    '109' => '#109 DevOps Operational Observability Hooks',
    '110' => '#110 DevOps Runbook and Recovery Documentation',
];

$dependencyContent = [
    '91' => [
        'title' => '#91 Dependency Inventory',
        'tag' => 'Dependency Governance Focus',
        'about' => 'Evaluates whether the repository dependency inventory is complete and ready for SBOM reporting.',
        'looks_for' => [
            'Presence of supported dependency manifests.',
            'Coverage of direct dependencies across ecosystems.',
            'Signals that inventory is missing critical components.',
        ],
        'recommendations' => [
            'Track all direct dependencies in committed manifests and lockfiles.',
            'Use SBOM generation in each release pipeline to verify inventory completeness.',
            'Document inventory scope with template: | Dependency | Version | Status |.',
        ],
        'why' => 'Inventory gaps make downstream vulnerability and license analysis unreliable.',
    ],
    '92' => [
        'title' => '#92 Vulnerability Detection',
        'tag' => 'Dependency Governance Focus',
        'about' => 'Assesses whether dependency names and versions are normalized and stable for reliable tracking.',
        'looks_for' => [
            'Missing versions in dependency declarations.',
            'Floating ranges that prevent precise identity mapping.',
            'Inconsistent package notation across ecosystems.',
        ],
        'recommendations' => [
            'Prefer pinned or bounded versions for production-critical packages.',
            'Normalize identifiers and versions in your SBOM export process.',
            'Track package identity in a consistent table format.',
        ],
        'why' => 'Identity normalization improves matching accuracy for security and compliance tooling.',
    ],
    '93' => [
        'title' => '#93 License Compliance',
        'tag' => 'Dependency Governance Focus',
        'about' => 'Checks whether dependency relationships are traceable through lockfiles and graph evidence.',
        'looks_for' => [
            'Expected lockfiles for each manifest type.',
            'Signals that transitive dependency graphs are reproducible.',
            'Graph visibility gaps caused by missing lock data.',
        ],
        'recommendations' => [
            'Commit required lockfiles and keep them updated.',
            'Use tooling that exports transitive graph data into SBOM artifacts.',
            'Review graph drift regularly for unexpected dependency expansion.',
        ],
        'why' => 'Graph traceability is required to understand transitive risk exposure.',
    ],
    '94' => [
        'title' => '#94 Supply Chain Security',
        'tag' => 'Dependency Governance Focus',
        'about' => 'Correlates dependency inventory against known vulnerabilities to prioritize remediation.',
        'looks_for' => [
            'Dependencies with known advisories (OSV/CVE).',
            'Severity concentration in dependency sets.',
            'Packages requiring urgent patching.',
        ],
        'recommendations' => [
            'Patch high-severity vulnerabilities first and verify mitigation.',
            'Automate advisory checks in CI for each pull request.',
            'Maintain remediation status per dependency entry.',
        ],
        'why' => 'Fast vulnerability correlation reduces exploit window for known issues.',
    ],
    '95' => [
        'title' => '#95 Version Tracking',
        'tag' => 'Dependency Governance Focus',
        'about' => 'Reviews dependency and repository licensing signals for compatibility and policy risk.',
        'looks_for' => [
            'Copyleft or restricted license obligations.',
            'Missing or unclear license declarations.',
            'Potential incompatibility with distribution model.',
        ],
        'recommendations' => [
            'Create a license policy and validate dependency licenses against it.',
            'Replace or isolate incompatible dependencies when required.',
            'Include license status in SBOM review outputs.',
        ],
        'why' => 'License issues can block release and create legal exposure.',
    ],
    '96' => [
        'title' => '#96 Risk Assessment',
        'tag' => 'Dependency Governance Focus',
        'about' => 'Assesses whether package source and ownership metadata supports provenance tracking.',
        'looks_for' => [
            'Repository/homepage metadata in manifests.',
            'Traceable package source context.',
            'Evidence needed for supply-chain investigations.',
        ],
        'recommendations' => [
            'Capture source metadata for dependency ecosystems used by the project.',
            'Store provenance context alongside SBOM artifacts.',
            'Review provenance completeness in release readiness checks.',
        ],
        'why' => 'Traceability shortens incident response and improves supply-chain confidence.',
    ],
    '97' => [
        'title' => '#97 Dependency Mapping',
        'tag' => 'Dependency Governance Focus',
        'about' => 'Checks whether dependency integrity signals support authenticity verification.',
        'looks_for' => [
            'Lockfile and checksum/integrity markers.',
            'Reproducibility signals for dependency resolution.',
            'Gaps that allow dependency tampering risks.',
        ],
        'recommendations' => [
            'Enforce lockfile usage and checksum verification where supported.',
            'Reject builds with missing integrity evidence for critical ecosystems.',
            'Audit dependency resolution pathways for tamper resistance.',
        ],
        'why' => 'Integrity controls reduce risk of compromised or substituted artifacts.',
    ],
    '98' => [
        'title' => '#98 Compliance and Auditing',
        'tag' => 'Dependency Governance Focus',
        'about' => 'Validates that SBOM files exist and follow recognizable CycloneDX/SPDX structures.',
        'looks_for' => [
            'Presence of SBOM files in supported formats.',
            'Basic structural fields such as components/packages.',
            'Format quality signals for downstream tool compatibility.',
        ],
        'recommendations' => [
            'Generate CycloneDX or SPDX files as part of release artifacts.',
            'Validate SBOM schema before publication.',
            'Version SBOM artifacts per release.',
        ],
        'why' => 'Format quality determines whether SBOM data can be consumed reliably.',
    ],
    '99' => [
        'title' => '#99 Continuous SBOM Automation',
        'tag' => 'Dependency Governance Focus',
        'about' => 'Examines CI workflows for automated SBOM generation and validation.',
        'looks_for' => [
            'Workflow steps that generate or verify SBOM output.',
            'Pipeline coverage for dependency governance automation.',
            'Gaps requiring manual SBOM effort.',
        ],
        'recommendations' => [
            'Automate SBOM generation in CI on build and release events.',
            'Fail pipeline gates when SBOM generation or validation fails.',
            'Store SBOM artifacts with build metadata for auditability.',
        ],
        'why' => 'Automation keeps dependency governance consistent and repeatable.',
    ],
    '100' => [
        'title' => '#100 Software Transparency',
        'tag' => 'Dependency Governance Focus',
        'about' => 'Detects dependency drift and likely unused packages that increase maintenance and risk.',
        'looks_for' => [
            'Floating or outdated dependency constraints.',
            'Packages with low evidence of usage in source scans.',
            'Dependency growth without corresponding runtime need.',
        ],
        'recommendations' => [
            'Review and remove truly unused dependencies after verification.',
            'Reduce floating constraints to limit drift.',
            'Track lifecycle status using: | Dependency | Version | Status |.',
        ],
        'why' => 'Reducing drift and dead dependencies lowers attack surface and maintenance cost.',
    ],
];

foreach ($dependencyContent as $id => $meta) {
    $checkContent[$id] = $meta;
}

$devopsContent = [
    '101' => [
        'title' => '#101 DevOps CI/CD Pipeline Coverage',
        'tag' => 'DevOps Focus',
        'about' => 'Validates whether repository workflows provide reliable CI/CD coverage for build, test, and verification.',
        'looks_for' => ['Workflow files under .github/workflows.', 'Build/test automation triggers.', 'Pipeline execution signals in repository automation.'],
        'recommendations' => ['Add CI workflow coverage for every pull request and default branch commit.', 'Gate merges on successful build and test jobs.', 'Track failed workflow trends and remediation ownership.'],
        'why' => 'Consistent CI/CD coverage reduces integration risk and catches regressions early.',
    ],
    '102' => [
        'title' => '#102 DevOps Docker Build Readiness',
        'tag' => 'DevOps Focus',
        'about' => 'Checks whether container build definitions support repeatable deployment packaging.',
        'looks_for' => ['Dockerfile or container build descriptors.', 'Build reproducibility signals.', 'Container workflow readiness for CI usage.'],
        'recommendations' => ['Add and maintain a production-ready Dockerfile.', 'Use deterministic dependency installation and image tagging.', 'Validate image builds in CI before release.'],
        'why' => 'Containerized build consistency improves deployment predictability across environments.',
    ],
    '103' => [
        'title' => '#103 DevOps Secrets Handling in Pipelines',
        'tag' => 'DevOps Focus',
        'about' => 'Assesses whether automation files avoid plaintext credentials and use secure secret injection.',
        'looks_for' => ['Token/password-like patterns in workflow files.', 'Secret manager usage patterns.', 'Pipeline secret hygiene practices.'],
        'recommendations' => ['Move credentials to encrypted CI secrets.', 'Rotate exposed credentials and invalidate leaked tokens.', 'Add secret scanning on pull requests and default branch.'],
        'why' => 'Secrets exposure in CI pipelines can lead to full repository and deployment compromise.',
    ],
    '104' => [
        'title' => '#104 DevOps Environment Configuration Management',
        'tag' => 'DevOps Focus',
        'about' => 'Checks whether environment variable guidance and configuration templates are available for deployments.',
        'looks_for' => ['.env.example or .env.sample templates.', 'Environment-specific configuration patterns.', 'Configuration portability across stages.'],
        'recommendations' => ['Provide environment templates without sensitive values.', 'Document required environment variables and defaults.', 'Validate environment configuration in CI checks.'],
        'why' => 'Configuration clarity reduces deployment failures and environment drift.',
    ],
    '105' => [
        'title' => '#105 DevOps Release Workflow Automation',
        'tag' => 'DevOps Focus',
        'about' => 'Evaluates whether release operations are automated using tag or release-based workflows.',
        'looks_for' => ['Tag/release workflow triggers.', 'Automated release artifact steps.', 'Release process standardization signals.'],
        'recommendations' => ['Automate release workflows for tagged versions.', 'Attach build artifacts and changelog outputs to releases.', 'Add release validation gates before publication.'],
        'why' => 'Release automation lowers manual error risk and improves delivery cadence.',
    ],
    '106' => [
        'title' => '#106 DevOps GitHub Actions Security Hardening',
        'tag' => 'DevOps Focus',
        'about' => 'Reviews action references and permissions for secure workflow execution.',
        'looks_for' => ['Pinned action references.', 'Least-privilege workflow usage.', 'Security hardening of automation components.'],
        'recommendations' => ['Pin GitHub Actions to commit SHA where possible.', 'Set minimal permissions for workflow jobs.', 'Review third-party actions before adoption.'],
        'why' => 'Hardened workflows reduce supply-chain and workflow tampering risks.',
    ],
    '107' => [
        'title' => '#107 DevOps Pull Request and Branch Quality Gates',
        'tag' => 'DevOps Focus',
        'about' => 'Assesses PR validation signals such as pull request workflows and ownership controls.',
        'looks_for' => ['pull_request triggers in workflows.', 'CODEOWNERS or review ownership signals.', 'Branch gate evidence tied to CI.'],
        'recommendations' => ['Require PR checks before merge.', 'Use CODEOWNERS for sensitive paths.', 'Block merges when required checks fail.'],
        'why' => 'PR quality gates prevent risky changes from bypassing review and automation.',
    ],
    '108' => [
        'title' => '#108 DevOps Deployment Automation Signals',
        'tag' => 'DevOps Focus',
        'about' => 'Checks whether deployment-related automation signals are present in workflows.',
        'looks_for' => ['Deploy/publish/helm/kubernetes workflow steps.', 'Automated deployment orchestration signals.', 'Deployment control points in CI/CD.'],
        'recommendations' => ['Automate repeatable deployment steps in workflow pipelines.', 'Define deployment approvals and rollout strategy.', 'Track deployment outcomes and rollback readiness.'],
        'why' => 'Deployment automation reduces release variability and operational mistakes.',
    ],
    '109' => [
        'title' => '#109 DevOps Operational Observability Hooks',
        'tag' => 'DevOps Focus',
        'about' => 'Evaluates whether repository signals indicate monitoring, logging, and alerting readiness.',
        'looks_for' => ['Observability references in workflows/docs.', 'Monitoring and alert integration hints.', 'Operational telemetry readiness.'],
        'recommendations' => ['Define baseline monitoring and alert requirements per service.', 'Document log and metric expectations for production.', 'Integrate observability checks into release readiness reviews.'],
        'why' => 'Observability reduces incident detection time and improves operational confidence.',
    ],
    '110' => [
        'title' => '#110 DevOps Runbook and Recovery Documentation',
        'tag' => 'DevOps Focus',
        'about' => 'Checks whether incident runbooks or recovery guidance exist for operational continuity.',
        'looks_for' => ['Runbook/incident/recovery documentation files.', 'Operational response instructions.', 'Disaster recovery guidance signals.'],
        'recommendations' => ['Create runbooks for major failure scenarios.', 'Document incident escalation and recovery steps.', 'Review and test recovery playbooks regularly.'],
        'why' => 'Runbooks improve incident response speed and reduce recovery uncertainty.',
    ],
];

foreach ($devopsContent as $id => $meta) {
    $checkContent[$id] = $meta;
}

$content = $checkContent[$checkId] ?? null;
if ($content === null) {
    $dynamicTitle = $dynamicCheckTitles[$checkId] ?? ($checkNameFromQuery !== '' ? $checkNameFromQuery : ('#' . $checkId . ' Analysis Check'));
    $dynamicTag = 'Quality Focus';
    if ((int) $checkId >= 61 && (int) $checkId <= 70) {
        $dynamicTag = 'Performance Focus';
    } elseif ((int) $checkId >= 71 && (int) $checkId <= 80) {
        $dynamicTag = 'Reliability Focus';
    } elseif ((int) $checkId >= 81 && (int) $checkId <= 90) {
        $dynamicTag = 'Testing Focus';
    } elseif ((int) $checkId >= 91 && (int) $checkId <= 100) {
        $dynamicTag = 'Dependency Governance Focus';
    } elseif ((int) $checkId >= 101 && (int) $checkId <= 110) {
        $dynamicTag = 'DevOps Focus';
    }

    $content = [
        'title' => $dynamicTitle,
        'tag' => $dynamicTag,
        'about' => 'This check evaluates repository signals for this selected quality task and highlights actionable evidence.',
        'looks_for' => [
            'Repository evidence linked to this task.',
            'Patterns that increase implementation or operational risk.',
            'Gaps that can be addressed through targeted remediation.',
        ],
        'recommendations' => [
            'Address high-severity findings first and verify fixes in CI.',
            'Track this task with measurable thresholds and ownership.',
            'Use repeatable checks to prevent regressions.',
        ],
        'why' => 'Consistent task-level checks improve delivery quality and reduce hidden risk over time.',
    ];
}
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
    '31' => ['Clean Code'],
    '32' => ['Clean Code'],
    '33' => ['Clean Code'],
    '34' => ['Clean Code'],
    '35' => ['Clean Code'],
    '36' => ['Clean Code'],
    '37' => ['Clean Code'],
    '38' => ['Clean Code'],
    '39' => ['Clean Code'],
    '40' => ['Clean Code'],
    '41' => ['Architecture'],
    '42' => ['Architecture'],
    '43' => ['Architecture'],
    '44' => ['Architecture'],
    '45' => ['Architecture'],
    '46' => ['Architecture'],
    '47' => ['Architecture'],
    '48' => ['Architecture'],
    '49' => ['Architecture'],
    '50' => ['Architecture'],
    '51' => ['Testing'],
    '52' => ['Testing'],
    '53' => ['Testing'],
    '54' => ['Testing'],
    '55' => ['Testing'],
    '56' => ['Testing'],
    '57' => ['Testing'],
    '58' => ['Testing'],
    '59' => ['Testing'],
    '60' => ['Testing'],
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
    '101' => ['DevOps'],
    '102' => ['DevOps'],
    '103' => ['DevOps'],
    '104' => ['DevOps'],
    '105' => ['DevOps'],
    '106' => ['DevOps'],
    '107' => ['DevOps'],
    '108' => ['DevOps'],
    '109' => ['DevOps'],
    '110' => ['DevOps'],
];

$findingsForCheck = [];
$findingsInfo = '';
$scanRecommendationsForCheck = [];
$recommendationsInfo = '';

$findingsKeywordsByCheck = [
    '1' => ['owasp', 'authorization', 'access', 'validation', 'logic flaw', 'insecure'],
    '2' => ['dependenc', 'package', 'vulnerab', 'outdated', 'cve', 'osv', 'manifest', 'lock'],
    '3' => ['ci', 'cd', 'workflow', 'pipeline', 'integrity', 'github actions', 'permissions', 'sha'],
    '4' => ['logging', 'monitoring', 'telemetry', 'audit', 'alert', 'error suppression'],
];

$recommendationKeywordsByCheck = [
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
    '31' => ['solid', 'single responsibility', 'open-closed', 'liskov', 'interface segregation', 'dependency inversion'],
    '32' => ['dry', 'duplicate', 'duplication', 'reuse', 'copy'],
    '33' => ['kiss', 'simple', 'complexity', 'simplify', 'readability'],
    '34' => ['yagni', 'speculative', 'future feature', 'todo', 'placeholder'],
    '35' => ['single responsibility', 'responsibility', 'cohesion', 'separation'],
    '36' => ['separation of concerns', 'layer', 'boundary', 'concern'],
    '37' => ['naming', 'meaningful', 'identifier', 'readability'],
    '38' => ['small function', 'function size', 'method length', 'extract method'],
    '39' => ['formatting', 'style', 'lint', 'indentation', 'line length'],
    '40' => ['error handling', 'exception', 'try catch', 'failure', 'fallback'],
    '41' => ['layered', 'boundary', 'architecture', 'presentation', 'application', 'infrastructure'],
    '42' => ['dependency rule', 'dependency', 'inner', 'outer', 'architecture'],
    '43' => ['framework', 'independence', 'bootstrap', 'html', 'presentation'],
    '44' => ['presentation', 'isolation', 'view', 'database', 'http'],
    '45' => ['use case', 'orchestration', 'application', 'persistence'],
    '46' => ['domain', 'purity', 'core', 'logic', 'adapter'],
    '47' => ['data access', 'abstraction', 'helper', 'adapter', 'curl'],
    '48' => ['interface adapter', 'adapter', 'render', 'presentation'],
    '49' => ['package', 'cohesion', 'folder', 'module'],
    '50' => ['cyclic', 'dependency', 'loop', 'inward'],
    '51' => ['unit', 'coverage', 'phpunit', 'assert', 'fast'],
    '52' => ['integration', 'database', 'api', 'client', 'boundary'],
    '53' => ['e2e', 'end-to-end', 'playwright', 'cypress', 'selenium'],
    '54' => ['fast feedback', 'phpunit', 'quick', 'local', 'suite'],
    '55' => ['mock', 'stub', 'fake', 'external api', 'deterministic'],
    '56' => ['sqlite', 'memory', 'transaction', 'rollback', 'isolation'],
    '57' => ['response', 'json', 'status', 'schema', 'api'],
    '58' => ['error', 'invalid', 'exception', 'negative', 'fail'],
    '59' => ['regression', 'bug', 'fix', 'issue', 'defect'],
    '60' => ['organization', 'maintainability', 'structure', 'folder', 'naming'],
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
    '92' => ['dependency', 'identity', 'normalize', 'version', 'package'],
    '93' => ['dependency', 'graph', 'transitive', 'lockfile', 'mapping'],
    '94' => ['dependency', 'vulnerability', 'cve', 'osv', 'advisory'],
    '95' => ['dependency', 'license', 'compatibility', 'compliance', 'copyleft'],
    '96' => ['dependency', 'provenance', 'source', 'traceability', 'origin'],
    '97' => ['dependency', 'integrity', 'authenticity', 'checksum', 'signature'],
    '98' => ['sbom', 'cyclonedx', 'spdx', 'format', 'components'],
    '99' => ['sbom', 'automation', 'ci', 'pipeline', 'workflow'],
    '100' => ['dependency', 'drift', 'unused', 'outdated', 'cleanup'],
    '101' => ['devops', 'ci', 'cd', 'pipeline', 'workflow'],
    '102' => ['devops', 'docker', 'container', 'image', 'build'],
    '103' => ['devops', 'secret', 'token', 'credential', 'pipeline'],
    '104' => ['devops', 'environment', 'configuration', '.env', 'template'],
    '105' => ['devops', 'release', 'tag', 'workflow', 'automation'],
    '106' => ['devops', 'actions', 'security', 'permissions', 'pinning'],
    '107' => ['devops', 'pull request', 'branch', 'gate', 'codeowners'],
    '108' => ['devops', 'deploy', 'helm', 'kubernetes', 'publish'],
    '109' => ['devops', 'observability', 'monitoring', 'alert', 'logging'],
    '110' => ['devops', 'runbook', 'incident', 'recovery', 'disaster'],
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
            $categoryScopedFindings = $findingsForCheck;

            $expectedTitle = strtolower(trim((string) preg_replace('/^#\s*\d+\s*/', '', $pageTitle)));
            $expectedFromQuery = strtolower(trim((string) preg_replace('/^#\s*\d+\s*/', '', $resolvedCheckName)));
            $expectedTitles = array_values(array_filter([$expectedTitle, $expectedFromQuery], static fn($value) => $value !== ''));

            if (!empty($findingsForCheck) && !empty($expectedTitles)) {
                $filteredFindings = [];
                $keywordMatches = $findingsKeywordsByCheck[$checkId] ?? [];
                foreach ($findingsForCheck as $finding) {
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
                        $filteredFindings[] = $finding;
                    }
                }

                $findingsForCheck = $filteredFindings;
            }

            if (empty($findingsForCheck) && !empty($categoryScopedFindings) && $countRaw !== null && $countRaw > 0) {
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
                $findingsForCheck = array_slice($categoryScopedFindings, 0, max(1, $countRaw));
            }

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
} elseif ($countRaw !== null && $countRaw === 0) {
    $recommendationsInfo = 'No scan-specific recommendations were generated for this check in this scan, showing baseline guidance below.';
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
        $expectedRecTitle = strtolower(trim((string) preg_replace('/^#\s*\d+\s*/', '', $pageTitle)));
        $titleStopWords = ['clean', 'code', 'test', 'tests', 'pyramid', 'and', 'the', 'for', 'with', 'rule', 'checks'];
        $titleTokens = array_values(array_filter(
            preg_split('/[^a-z0-9]+/', $expectedRecTitle) ?: [],
            static fn($token) => $token !== '' && strlen($token) >= 4 && !in_array($token, $titleStopWords, true)
        ));

        $scoredRecommendations = [];
        foreach ($allScanRecommendations as $rec) {
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
            foreach ((array) ($content['recommendations'] ?? []) as $baselineRec) {
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
            if ($countRaw !== null && $countRaw > 0) {
                $maxRecommendations = max(1, min(3, $countRaw));
            }
            $scanRecommendationsForCheck = array_values(array_map(
                static fn($item) => $item['rec'],
                array_slice($scoredRecommendations, 0, $maxRecommendations)
            ));
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
    <?php require_once __DIR__ . '/includes/site_chat_widget.php'; ?>
</body>
</html>
