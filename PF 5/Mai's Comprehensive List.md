# Person B: Mai's Comprehensive List (Weeks 6-10)

## Role
Platform plus analysis engine checks:
1. Insecure Design and Logic Flaws (A04)
2. Vulnerable and Outdated Dependencies (A06)
3. CI/CD and Software Integrity Risks (A08)
4. Logging and Monitoring Coverage (A09)
5. Code Quality, Performance and Repo Health

## Week 6 - Foundation Stabilization
Goal: Solid end-to-end flow in current stack (PHP + MySQL + jQuery/Bootstrap)

Backend:
1. Finalize API flow for analyze, status, and report generation.
2. Ensure selected checks run sequentially and consistently.
3. Persist scan metadata and per-check outputs reliably.

Frontend:
1. Improve repo input + analyze flow reliability.
2. Improve status, loading, and error handling.

Output:
1. Stable scan pipeline with stored results and reproducible behavior.

## Week 7 - Settings and Execution Control (FP5 core)
Goal: Settings controls exactly what runs.

Backend:
1. Keep Person B five checks as selectable modules.
2. Execute only selected checks.
3. Store selected check list with each scan.

Frontend:
1. Keep Settings UI for Person B five checks.
2. Keep Run All and Clear behavior.
3. Show selected checks in results.

Output:
1. Settings-driven execution fully working.

## Week 8 - Reporting and Evidence
Goal: Complete report quality for Person B checks.

Backend:
1. Standardize check result shape: title, summary, details, severity, evidence.
2. Persist per-check report content in database.
3. Support summary URL and downloadable report per scan.

Frontend:
1. Show per-check result sections in report.
2. Show severity labels and evidence lists clearly.

Output:
1. Report page and download include Person B five checks.

## Week 9 - Check Quality and Rule Tuning
Goal: Improve detection quality and reduce noisy results.

Per-check hardening:
1. A04 rule refinement for logic and trust-boundary issues.
2. A06 manifest, lockfile, and dependency risk signal tuning.
3. A08 CI workflow and software integrity pattern tuning.
4. A09 logging and monitoring coverage heuristic tuning.
5. Code quality and repo health aggregation tuning.

Validation:
1. Test against several real repositories.
2. Improve false positive and false negative balance.

Output:
1. Person B checks are reliable and demo-ready.

## Week 10 - Final Integration and Demo Polish
Goal: Presentation-ready delivery.

Backend:
1. Score normalization and summary scoring for Person B outputs.
2. Better error messaging and fallback handling.
3. Cleanup and performance pass.

Frontend:
1. Dashboard clarity improvements.
2. Better report readability.
3. Verify scan history summary and download links.

Output:
1. Final polished Person B package ready for assessment and demo.
