# FP2 – Requirements Elaboration

# Project Title

AI-Assisted Code and Skills Reviewer

---

# 1. Project Description

The AI-Assisted Code and Skills Reviewer is a web-based application that analyzes software repositories hosted on GitHub and GitLab. The system uses repository APIs to retrieve source code, project structure, and metadata for automated analysis.

Users only need to provide:

- Repository URL
- Personal Access Token (PAT)

The application will review source code for quality, security, performance, compliance, and stability issues. It will also identify technologies and skills used within the repository and assess associated licensing and execution risks. After analysis, the system generates recommendations to help developers improve their projects.

---

# 2. Technology Stack

## Front End

- HTML
- CSS
- JavaScript
- jQuery
- Bootstrap

## Server Side

- PHP

## Database

- MySQL

## External APIs

- GitHub API
- GitLab API

---

# 3. Functional Requirements

## FR1 – Repository Input

The system shall allow users to enter:

- GitHub repository URL or GitLab repository URL
- Personal Access Token (PAT)

The system shall validate that both fields are provided before starting analysis.

---

## FR2 – Repository Access via APIs

The system shall connect to either GitHub or GitLab using the provided Personal Access Token.

The system shall retrieve:

- Repository metadata
- Repository file structure
- Source code files
- Branch information
- Programming language statistics

---

## FR3 – Code Review Analysis

The system shall analyze repository source code using automated review rules.

### Security Analysis

The system shall identify:

- Hardcoded passwords
- API keys
- Sensitive credentials
- Potential vulnerabilities

### Performance Analysis

The system shall identify:

- Inefficient algorithms
- Duplicate code
- Resource-intensive operations

### Stability Analysis

The system shall identify:

- Missing exception handling
- Potential runtime failures
- Reliability concerns

### Compliance Analysis

The system shall evaluate:

- Coding standards
- Best practices
- Code organization

---

## FR4 – Skills Detection and Risk Assessment

The system shall identify technologies and skills used in the repository.

### Programming Languages

- PHP
- JavaScript
- Python
- Java
- C#

### Frameworks

- Bootstrap
- React
- Angular
- Laravel

### Databases

- MySQL
- PostgreSQL
- MongoDB

### DevOps Tools

- Docker
- GitHub Actions
- GitLab CI/CD

The system shall assess:

### Licensing Risk

Examples:

- Open-source license compatibility
- Restricted license usage

### Execution Risk

Examples:

- Unsupported dependencies
- Build failures
- Deployment issues

---

## FR5 – Recommendation Engine

The system shall generate recommendations based on findings.

Each recommendation shall include:

- Description
- Suggested fix
- Priority level

Priority Levels:

- High
- Medium
- Low

Example Recommendations:

- Move secrets to environment variables
- Improve error handling
- Optimize database queries
- Update vulnerable dependencies

---

## FR6 – Static Repository Analysis (Stretch Goal)

The system may provide additional repository insights.

### Technology Stack Detection

Identify technologies used throughout the project.

### Repository Metrics

Report:

- Total files
- Source code files
- Configuration files

### CI/CD Analysis

Detect:

- GitHub Actions workflows
- GitLab CI/CD pipelines

Provide:

- Pipeline count
- Build configuration summary

---

# 4. Non-Functional Requirements

## Security

- Personal Access Tokens shall not be permanently stored.
- API communications shall use HTTPS.

## Performance

- Repository analysis should complete within a reasonable time.

## Usability

- User interface shall be simple and easy to use.

## Scalability

- System architecture shall support future features and integrations.

## Compatibility

- Support GitHub repositories.
- Support GitLab repositories.

---

# 5. System Architecture

```text
User Browser
      |
      v
Frontend
(HTML/CSS/JS/jQuery/Bootstrap)
      |
      v
PHP Backend Server
      |
      +----------------------+
      |                      |
      v                      v
 GitHub API            GitLab API
      |                      |
      +----------+-----------+
                 |
                 v
      Repository Analyzer
(Code Review + Skills Detection
     + Risk Assessment)
                 |
                 v
          MySQL Database
                 |
                 v
        Results Dashboard
```

---

# 6. Database Design

## Table: repositories

| Field | Description |
|---------|-------------|
| id | Primary Key |
| repo_url | Repository URL |
| platform | GitHub or GitLab |
| created_at | Analysis Date |

### SQL Example

```sql
CREATE TABLE repositories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repo_url VARCHAR(500),
    platform VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Table: scans

```sql
CREATE TABLE scans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repository_id INT,
    scan_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    summary_score INT,
    FOREIGN KEY (repository_id) REFERENCES repositories(id)
);
```

## Table: findings

```sql
CREATE TABLE findings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scan_id INT,
    category VARCHAR(50),
    description TEXT,
    severity VARCHAR(20),
    FOREIGN KEY (scan_id) REFERENCES scans(id)
);
```

## Table: skills

```sql
CREATE TABLE skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scan_id INT,
    skill_name VARCHAR(100),
    risk_level VARCHAR(20),
    FOREIGN KEY (scan_id) REFERENCES scans(id)
);
```

## Table: recommendations

```sql
CREATE TABLE recommendations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scan_id INT,
    recommendation_text TEXT,
    priority VARCHAR(20),
    FOREIGN KEY (scan_id) REFERENCES scans(id)
);
```

---

# 7. API Usage

## GitHub API

Used For:

- Repository metadata
- File retrieval
- Language detection

Endpoints:

```text
/repos/{owner}/{repo}
/repos/{owner}/{repo}/contents
/repos/{owner}/{repo}/languages
```

## GitLab API

Used For:

- Project metadata
- Repository tree
- Pipeline information

Endpoints:

```text
/projects/:id
/projects/:id/repository/tree
/projects/:id/pipelines
```

---

# 8. User Interface Design

## Page 1 – Repository Input

Components:

- Repository URL textbox
- PAT textbox
- Analyze button

## Page 2 – Repository Summary

Displays:

- Repository name
- Platform
- File count
- Language breakdown

## Page 3 – Analysis Report

Displays:

- Security findings
- Performance findings
- Stability findings
- Compliance findings

## Page 4 – Skills and Risk Dashboard

Displays:

- Detected skills
- Licensing risk
- Execution risk

## Page 5 – Recommendations

Displays:

- Suggested improvements
- Priority levels
- Fix recommendations

---

# 9. Sample Output

Repository: Demo Project

## Security Issues

- Hardcoded password found

## Performance Issues

- Inefficient database query detected

## Skills Detected

- PHP
- JavaScript
- MySQL

## Licensing Risk

Low

## Execution Risk

Medium

## Recommendations

- Move secrets to environment variables
- Optimize SQL queries
- Improve exception handling
- Update outdated dependencies

---

# 10. FP3 – Eight Week Development Plan

| Week | Activity |
|------|----------|
| Week 1 | Database Design and UI Setup |
| Week 2 | PHP Backend Structure |
| Week 3 | GitHub API Integration |
| Week 4 | GitLab API Integration |
| Week 5 | Repository Data Extraction |
| Week 6 | Code Analysis and Skills Detection |
| Week 7 | Recommendation Engine Development |
| Week 8 | Testing, Documentation, and Final Demo |

---

# Conclusion

The AI-Assisted Code and Skills Reviewer will provide automated repository analysis for GitHub and GitLab projects. By combining API integration, code review analysis, skill detection, and risk assessment, the system will help developers improve software quality and better understand project technologies.