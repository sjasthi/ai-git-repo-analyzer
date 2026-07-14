# Weekly Summary (07/13/2026)

## MAI

### Week Overview
Add 10 skills of Code Quality (SonarQube Rules)
Fixing the download files to a doc format




### Completed Work
- Added a full SonarQube Rules (Code Quality) section with 10 checks in the same format and layout as the OWASP section.
- Unified selection behavior for OWASP and Sonar checks (select all, clear all, and selected count).
- Updated analysis handling so both OWASP checks and Sonar checks are submitted and executed together.
- Added check detail mapping for IDs 1 to 20 and extended detail content for Sonar checks in the check details page.
- Improved result rendering so only executed checks are shown, grouped by:
	- OWASP Skills (Security Analysis)
	- SonarQube Rules (Code Quality)
	- Other Checks
- Improved readability of Selected Checks and Analysis Checks sections in report output.
- Added in-page popup modal for check details on result and summary pages.
- Fixed broken/non-responsive analysis flow caused by corrupted JavaScript block and restored normal result rendering.
- Fixed summary report script parsing issue and stabilized popup detail behavior.
- Implemented report download format updates, including DOC export support.
- Updated report download behavior so download view uses normal table output and does not include interactive top controls.

### Summary
- Completed UI, backend, and reporting updates to support 10 SonarQube skills with full behavior parity to OWASP skills.
- Finished report polishing for readability and download output, including DOC format and clean download layout.
