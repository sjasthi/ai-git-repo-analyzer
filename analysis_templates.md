# Git Repository Analyzer

**Course:** Software Engineering / DevOps / AI Applications  


---

# Overview

In this project, you will build an **AI-powered Git Repository Analyzer**.

The application accepts:

- Git Repository URL
- Personal Access Token (PAT)

The application clones the repository, performs a comprehensive static analysis, and produces a professional engineering report with scores, findings, recommendations, and visual dashboards.

Think of your project as combining the capabilities of:

- SonarQube
- GitHub Code Scanning
- Code Climate
- OWASP Scanner
- AI Code Reviewer

into one application.

---

# Learning Objectives

By completing this project you will learn:

- Git APIs
- Repository cloning
- Static code analysis
- Software architecture evaluation
- Security scanning
- AI-assisted code review
- Metrics collection
- Report generation
- Dashboard design

---

# Functional Requirements

The application shall allow the user to enter:

- Repository URL
- Personal Access Token (PAT)

Example:

```
Repository:
https://github.com/username/project

PAT:
ghp_xxxxxxxxxxxxxxxxx
```

After clicking **Analyze Repository**, the application should:

1. Clone the repository
2. Traverse every source file
3. Run all analysis modules
4. Compute scores
5. Generate a report
6. Display an overall Repository Health Score

---

# Repository Health Score

The repository should receive an overall score out of **100**.

Example:

| Category | Score |
|-----------|------:|
| Security | 92 |
| Code Quality | 87 |
| Architecture | 81 |
| Testing | 78 |
| Documentation | 95 |
| DevOps | 83 |
| Performance | 80 |
| Dependencies | 91 |
| AI Readiness | 94 |

Overall Repository Health

**88 / 100**

---

# Analysis Categories

---

# 1. Security (OWASP)

Weight: 15%

Detect common security issues.

Examples:

- SQL Injection
- Command Injection
- Cross Site Scripting
- Hardcoded passwords
- Hardcoded API keys
- Weak hashing
- Unsafe deserialization
- Missing authentication
- Missing authorization

Suggested Standards

- OWASP Top 10
- CWE Top 25

Deliverables

- Number of findings
- Severity
- Recommendation
- Security Score

---

# 2. Code Quality

Weight: 15%

Analyze maintainability.

Examples

- Duplicate code
- Dead code
- Long methods
- Large classes
- Poor variable names
- Magic numbers
- Code smells

Metrics

- Cyclomatic Complexity
- Maintainability Index
- Lines of Code

Deliverables

- Code Quality Score

---

# 3. Clean Code

Weight: 10%

Evaluate software engineering principles.

Check for:

- SOLID
- DRY
- KISS
- YAGNI
- Single Responsibility Principle
- High Cohesion
- Low Coupling

Deliverables

- Violations
- Suggestions
- Score

---

# 4. Software Architecture

Weight: 10%

Analyze project organization.

Examples

- Layer violations
- Circular dependencies
- God classes
- Package organization
- Modularity

Deliverables

Architecture Score

---

# 5. Complexity

Weight: 10%

Measure code complexity.

Metrics

- Cyclomatic Complexity
- Cognitive Complexity
- Maximum Nesting
- Function Length
- Class Size

Deliverables

Complexity Score

---

# 6. Performance

Weight: 10%

Static analysis for performance issues.

Examples

- Nested loops
- Inefficient algorithms
- Expensive operations
- Repeated database calls
- Memory concerns

Deliverables

Performance Score

---

# 7. Reliability

Weight: 10%

Evaluate robustness.

Examples

- Exception handling
- Logging
- Retry logic
- Timeout handling
- Resource cleanup
- Null handling

Deliverables

Reliability Score

---

# 8. Testing

Weight: 10%

Analyze testing quality.

Examples

- Unit tests
- Integration tests
- Test coverage
- Assertions
- Test organization

Deliverables

Testing Score

---

# 9. Documentation

Weight: 5%

Evaluate documentation.

Examples

- README
- Installation Guide
- API Documentation
- Inline comments
- Examples

Deliverables

Documentation Score

---

# 10. Dependency Analysis

Weight: 5%

Analyze project dependencies.

Examples

- Outdated packages
- Vulnerable packages
- Unused dependencies
- License issues

Deliverables

Dependency Score

---

# 11. DevOps Readiness

Weight: 5%

Evaluate deployment readiness.

Examples

- Dockerfile
- CI/CD pipeline
- GitHub Actions
- Secrets management
- Environment variables

Deliverables

DevOps Score

---

# 12. AI Readiness

Weight: 5%

Evaluate whether the repository is easy for AI coding assistants to understand.

Examples

- Good naming
- Small functions
- Modular code
- Rich documentation
- Clear APIs
- Consistent coding style

Deliverables

AI Readiness Score

---

# Suggested Folder Structure

```
git-repo-analyzer/

    analyzer/

        security.py

        quality.py

        architecture.py

        performance.py

        testing.py

        documentation.py

        dependencies.py

        ai_readiness.py

        devops.py

        scoring.py

    reports/

    templates/

    static/

    app.py

    requirements.txt

```

---

# Suggested Technologies

Backend

- Python
- Flask or FastAPI

Frontend

- HTML
- Bootstrap
- JavaScript

Git

- GitPython

Visualization

- Chart.js
- Plotly

Optional AI

- OpenAI API
- GitHub Models
- Local LLM

---

# Suggested Output

The final report should include:

## Executive Summary

Overall Health Score

Top Strengths

Top Risks

Recommendations

---

## Detailed Findings

For every category:

- Score
- Findings
- Severity
- Recommendation

---

## Dashboard

Include visualizations such as:

- Radar Chart
- Pie Chart
- Bar Graph
- Repository Scorecard

---

# Bonus Features

Students are encouraged to implement additional capabilities such as:

- PDF report generation
- Excel export
- HTML report
- Repository badges
- Trend analysis across multiple repositories
- Git commit history analysis
- Pull request analytics
- Contributor analytics
- Code ownership analysis
- Security heat map
- Technical debt estimation
- AI-generated executive summary
- AI-generated refactoring suggestions
- Chat interface for querying the repository
- Compare two repositories
- Repository leaderboard
- Dark mode dashboard

---

# Evaluation Rubric

| Criteria | Points |
|-----------|-------:|
| User Interface | 10 |
| Repository Cloning | 10 |
| Security Analysis | 15 |
| Code Quality Analysis | 15 |
| Architecture Analysis | 10 |
| Performance Analysis | 10 |
| Testing Analysis | 10 |
| Documentation Analysis | 5 |
| Dashboard & Visualization | 10 |
| AI Features | 5 |
| Code Quality & Documentation | 10 |

Total: **100 Points**

---

# Stretch Goal

Design your application so that **new analysis modules can be plugged in easily** without modifying existing code. Consider using object-oriented principles such as interfaces or abstract base classes to define a common `Analyzer` contract.

Example:

```python
class Analyzer:
    def analyze(self, repository):
        pass

    def score(self):
        pass

    def recommendations(self):
        pass
```

Each category (Security, Performance, Documentation, etc.) can then implement this interface independently.

---

# Deliverables

Students must submit:

- Source code
- README
- Installation instructions
- Sample repository analyzed
- Generated report
- Dashboard screenshots
- Short demo video (5–10 minutes)

---

# Success Criteria

A successful Git Repository Analyzer should:

- Clone public and private repositories using a PAT.
- Analyze source code across multiple quality dimensions.
- Produce a weighted Repository Health Score out of 100.
- Present findings in a clear, professional dashboard.
- Offer actionable recommendations for improving the repository.

The goal is not only to detect problems but also to help developers understand **why** they matter and **how** to improve the overall quality, security, maintainability, and readiness of their software projects.
