# Git Repository Analyzer (API-Driven)
---

# Project Overview

In this project, you will build an **API-driven Git Repository Analyzer** that evaluates the overall health of a software repository **without cloning it locally**.

Instead of downloading the repository, your application will use the **GitHub REST APIs**, **GitHub GraphQL APIs**, and/or **GitLab APIs** to retrieve repository metadata, source files, commit history, pull requests, workflow definitions, documentation, and other artifacts required for analysis.

The goal is to build an **Explainable AI (XAI)** system that not only produces scores, but also explains:

- Why a score was assigned
- What evidence was found
- Which rules were violated
- How the repository can be improved

Your application should behave like an AI Engineering Consultant rather than just a scoring engine.

---

# Learning Objectives

Students will gain experience with:

- GitHub APIs
- GitLab APIs
- REST APIs
- GraphQL
- Repository mining
- Static code analysis
- Software engineering metrics
- Explainable AI (XAI)
- Rule engines
- AI-assisted code reviews
- Report generation
- Dashboard development

---

# Functional Requirements

The application shall accept:

- Repository URL
- Personal Access Token (PAT)

Example

```
Repository

https://github.com/company/project

PAT

ghp_xxxxxxxxxxxxxxxxx
```

---

The application shall:

1. Authenticate using the PAT
2. Read repository metadata using APIs
3. Retrieve source files using APIs
4. Analyze repository contents
5. Compute scores
6. Explain every score
7. Generate recommendations
8. Produce executive reports and dashboards

---

# Important Constraint

**Do NOT clone repositories.**

All repository access must occur through:

- GitHub REST APIs
- GitHub GraphQL APIs
- GitLab REST APIs
- GitLab GraphQL APIs (optional)

Students should demonstrate efficient API usage, pagination, caching, and rate-limit handling.

---

# Explainable AI (XAI) Requirement

A score by itself has little value.

Every score **must include an explanation**.

Example

```
Security Score

84 / 100

Reason

✓ No hardcoded passwords found

✓ Authentication implemented

✓ HTTPS enforced

⚠ 2 secrets detected

⚠ Weak password validation

⚠ Missing Content Security Policy

Score deductions

Hardcoded Secrets
-8

Weak Authentication
-5

Missing Security Headers
-3

Final Score

84
```

Every analyzer should answer:

- What was analyzed?
- What evidence was found?
- Which rules passed?
- Which rules failed?
- Why did the score increase?
- Why did the score decrease?
- How can the repository improve?

---

# Repository Health Score

The final report should contain a weighted scorecard.

| Category | Weight | Score |
|-----------|-------:|------:|
| Security | 15% | 92 |
| Code Quality | 15% | 88 |
| Clean Code | 10% | 90 |
| Architecture | 10% | 84 |
| Complexity | 10% | 80 |
| Performance | 10% | 82 |
| Reliability | 10% | 86 |
| Testing | 10% | 76 |
| Documentation | 5% | 94 |
| Dependencies | 5% | 89 |
| DevOps | 5% | 81 |
| AI Readiness | 5% | 95 |

Overall Repository Health

**87 / 100**

---

# Every Analysis Module Must Produce

Every module shall return a structured result.

Example

```
Module

Security

Score

84

Summary

Repository demonstrates good authentication practices but contains exposed secrets.

Evidence

2 hardcoded API keys

3 authentication modules

No SQL Injection patterns

No unsafe eval() usage

Passed Rules

✓ Authentication

✓ Authorization

✓ Input Validation

Failed Rules

✗ Hardcoded Secrets

✗ CSP Missing

Recommendations

Move secrets to environment variables.

Use GitHub Secrets.

Enable CSP headers.

Confidence

95%
```

---

# Analysis Categories

---

# 1. Security Analysis

Weight: 15%

### Purpose

Identify security vulnerabilities.

### Suggested Frameworks

- OWASP Top 10
- OWASP ASVS
- CWE Top 25
- MITRE ATT&CK (high level)
- CERT Secure Coding Guidelines

### Suggested Template

| Rule | Status | Severity | Evidence | Recommendation |
|------|---------|----------|-----------|----------------|

---

# 2. Code Quality

Weight: 15%

### Suggested Standards

- SonarQube Rules
- ISO/IEC 25010 (Maintainability)
- Clean Code principles

### Suggested Template

| Metric | Value | Threshold | Status |
|---------|------:|----------:|--------|

Example metrics

- Duplicate code
- Dead code
- Naming
- Maintainability Index
- Code smells

---

# 3. Clean Code

Weight: 10%

### Suggested Framework

Robert C. Martin's Clean Code principles

Evaluate

- SOLID
- DRY
- KISS
- YAGNI
- Single Responsibility
- Separation of Concerns

### Suggested Template

| Principle | Rating | Evidence |
|------------|--------|----------|

---

# 4. Architecture

Weight: 10%

### Suggested Frameworks

- Layered Architecture
- Clean Architecture
- Hexagonal Architecture
- Domain Driven Design

### Suggested Template

| Check | Status | Evidence |
|--------|--------|----------|

Examples

- Circular dependencies
- Module cohesion
- Package organization
- Separation of layers

---

# 5. Complexity

Weight: 10%

### Suggested Metrics

- Cyclomatic Complexity
- Cognitive Complexity
- Function Size
- Class Size
- Nesting Depth

### Suggested Template

| Metric | Average | Maximum | Threshold |

---

# 6. Performance

Weight: 10%

### Suggested Checklist

- Nested loops
- Expensive operations
- N+1 patterns
- Repeated API calls
- Blocking operations

### Suggested Template

| Finding | Impact | Recommendation |

---

# 7. Reliability

Weight: 10%

### Suggested Framework

Google Site Reliability Engineering (SRE) concepts

Evaluate

- Logging
- Retry
- Timeout
- Exception handling
- Null handling
- Resource cleanup

### Suggested Template

| Rule | Passed | Evidence |

---

# 8. Testing

Weight: 10%

### Suggested Frameworks

- FIRST Principles
- Test Pyramid
- AAA Pattern

Evaluate

- Unit tests
- Integration tests
- Test organization
- Coverage
- Assertions

### Suggested Template

| Metric | Score |

---

# 9. Documentation

Weight: 5%

### Suggested Standards

- README Best Practices
- API Documentation
- Architecture Decision Records (ADR)

Evaluate

- Installation
- Usage
- Examples
- Architecture
- Contribution Guide

### Suggested Template

| Section | Present | Quality |

---

# 10. Dependency Analysis

Weight: 5%

### Suggested Frameworks

- Software Bill of Materials (SBOM)
- Supply Chain Security
- Semantic Versioning

Evaluate

- Vulnerabilities
- Outdated packages
- License compatibility
- Unused packages

### Suggested Template

| Dependency | Version | Status |

---

# 11. DevOps Readiness

Weight: 5%

### Suggested Standards

- DevOps Maturity Model
- Twelve-Factor App
- GitHub Actions Best Practices

Evaluate

- CI/CD
- Docker
- Secrets
- Environment variables
- Release workflows

### Suggested Template

| Capability | Status |

---

# 12. AI Readiness

Weight: 5%

### Suggested Framework

Design your own AI Readiness Rubric.

Possible criteria

- Good naming
- Small functions
- Modular design
- Documentation
- Consistent style
- Rich README
- Examples
- Clear APIs

### Suggested Template

| Criterion | Score | Evidence |

---

# Explainability Dashboard

Every dashboard should answer:

## What is my score?

## Why did I receive this score?

## What evidence supports it?

## Which files contributed?

## What should I fix first?

## What will improve my score the most?

---

# Priority Matrix

Every recommendation should include:

| Recommendation | Impact | Effort | Priority |
|---------------|--------|--------|----------|

Example

| Remove hardcoded secrets | High | Low | ⭐⭐⭐⭐⭐ |

---

# Architecture Recommendation

Design your solution using a plug-in architecture.

```
Analyzer

    analyze()

    evaluate()

    explain()

    recommendations()

    score()
```

Every analyzer should implement the same interface so that future analysis modules can be added without changing the rest of the application.

---

# Stretch Goals

Students may implement:

- AI-generated executive summaries
- Natural language explanations
- Repository chat assistant
- Repository comparison
- Historical trend analysis
- Team analytics
- Contributor analytics
- Pull Request analytics
- Release analytics
- Interactive score simulator ("What if I fix these issues?")
- Export to PDF, HTML, Excel, or JSON
- Custom rule packs
- Organization-wide dashboards

---

# Deliverables

Students must submit:

- Source code
- API documentation
- README
- Installation guide
- Architecture diagram
- Sample analysis of at least one GitHub repository
- Explainability report
- Dashboard screenshots
- 5–10 minute demonstration video

---

# Evaluation Rubric

| Criteria | Points |
|-----------|-------:|
| API Integration (GitHub/GitLab) | 10 |
| Repository Analysis Engine | 20 |
| Scoring Methodology | 10 |
| Explainability (XAI) | 20 |
| Dashboard & Visualizations | 10 |
| Report Generation | 10 |
| Software Architecture & Extensibility | 10 |
| AI Features | 5 |
| Code Quality & Documentation | 5 |

**Total: 100 Points**

---

# Success Criteria

A successful Git Repository Analyzer should:

- Analyze repositories entirely through GitHub/GitLab APIs without cloning.
- Evaluate multiple dimensions of repository quality using recognized industry standards and best-practice frameworks.
- Produce a transparent, weighted Repository Health Score.
- Explain every score with supporting evidence, rule evaluations, and actionable recommendations.
- Prioritize improvements based on impact and effort.
- Present results in an intuitive dashboard suitable for developers, technical leads, and engineering managers.

**Remember:** The objective is not merely to *score* a repository, but to provide an **explainable engineering assessment** that helps users understand the current state of their software and guides them toward measurable improvements.
