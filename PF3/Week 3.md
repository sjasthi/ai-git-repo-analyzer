# FP3 Scope

## 1. Preliminary Website Design

The website will use a simple dashboard-style layout focused on repository analysis.

- A header with the project name and short description
- A main input card where the user enters the GitHub repository URL and Personal Access Token
- A primary action button to start analysis
- A results section that displays scan status, repository details, findings, and recommendations
- A clean Bootstrap-based interface with responsive layout for desktop and mobile

The design goal is to keep the interface minimal, readable, and task-focused so users can submit a repository and review results quickly.

## 2. Code Structure and Repository Structure

The repository is organized into small, clearly separated parts:

- `index.php` - main user interface
- `api/` - backend endpoints for health checks and repository analysis
- `config/` - shared configuration such as database connection logic
- `database/` - schema and database initialization scripts

This structure keeps presentation, API logic, configuration, and database setup separated so the project is easier to maintain and extend. Future features such as deeper static analysis, findings storage, and recommendation generation can be added inside the same structure without rewriting the whole project.

## 3. Weekly Development Plan

| Week | Date | Activity | Mai | Youssef | Deliverable |
| --- | --- | --- | --- | --- | --- |
| Week 3 | 16-Jun-26 | Project kickoff, requirements review, and environment setup | Development plan, environment setup| Database setup, fixing XAMPP, environment setup | Scope confirmation and kickoff notes |
| Week 4 | 22-Jun-26 | Finalize requirements, scope, and system design | UI wireframes, layout direction, and presentation structure | Project architecture, scope definition, and schema planning | Approved scope, preliminary UI design, and architecture outline |
| Week 5 | 29-Jun-26 | Build frontend and backend project skeleton | Frontend pages, form styling, and user flow setup | PHP backend scaffold, routing, and API structure | Working homepage, form layout, and API scaffolding |
| Week 6 | 6-Jul-26 | Set up database and GitHub API connectivity | Connection testing, configuration review, and error handling | Database setup, GitHub API integration, and repo retrieval | Database schema, initialization script, and connection validation |
| Week 7 | 13-Jul-26 | Implement repository data retrieval and scan creation | Display components for scan results and repository details | File extraction, metadata capture, and scan pipeline logic | Repository metadata capture and initial scan records |
| Week 8 | 20-Jul-26 | Add analysis logic, skills detection, and recommendations | Results presentation, recommendations layout, and UI polish | Analysis rules, skills detection, and risk logic | Findings engine with baseline review results |
| Week 9 | 27-Jul-26 | Testing, refinement, and documentation | Documentation, demo slides, and user guide updates | Testing, bug fixes, final hardening, and presentation preparation | Stable demo version with usage notes and final presentation prep |
| Week 10 | 3-Aug-26 | Presentation | Presentation deck, speaking notes, and demo visuals | Presentation deck, speaking notes, and demo visuals | Final project presentation and submission prep |



