# Week 4 - UI Wireframes and Design

**Owner:** Mai  
**Date:** 22-Jun-26 to 25-Jun-26  
**Status:** In Progress

---

## Dashboard Layout Overview

### 1. Header Section
```
┌─────────────────────────────────────────────┐
│ AI Git Repo Analyzer                        │
│ Intelligent Code Review and Skills Analysis │
└─────────────────────────────────────────────┘
```

**Elements:**
- Logo/title on left
- Navigation links (optional for future)
- Status indicator (online/offline)

---

### 2. Main Input Section

```
┌─────────────────────────────────────────────────┐
│                                                 │
│  Analyze Your GitHub Repository                │
│                                                 │
│  Repository URL:                               │
│  [  https://github.com/user/repo        ]      │
│                                                 │
│  Personal Access Token (PAT):                  │
│  [  ••••••••••••••••••••••••••••••     ]       │
│                                                 │
│  [         ANALYZE REPOSITORY         ]        │
│                                                 │
└─────────────────────────────────────────────────┘
```

**Form Fields:**
- Repository URL (required, validated)
- PAT input (required, masked, not stored)
- Submit button
- Clear button (optional)

**Validation:**
- URL format check on client-side
- PAT non-empty check
- Server-side validation in analyze.php

---

### 3. Progress Indicator (During Analysis)

```
┌─────────────────────────────────────────────────┐
│                                                 │
│  Analyzing Repository...                        │
│                                                 │
│  ████████░░░░░░░░░░░░░░░░░░ 40%               │
│                                                 │
│  ✓ Validating access                           │
│  ✓ Fetching repository metadata                │
│  ⟳ Analyzing code...                           │
│  ○ Generating recommendations                  │
│                                                 │
└─────────────────────────────────────────────────┘
```

---

### 4. Results Section (After Analysis)

#### 4a. Repository Overview Card
```
┌─────────────────────────────────────────────────┐
│ Repository: user/repo-name                      │
│                                                 │
│ Description: Brief repo description here       │
│ Owner: username                                 │
│ Stars: 234  |  Forks: 45  |  Watchers: 12      │
│ Created: 2024-01-15  |  Last Updated: 2026-06  │
└─────────────────────────────────────────────────┘
```

#### 4b. Scan Summary Card
```
┌─────────────────────────────────────────────────┐
│ SCAN RESULTS - Overall Score: 72/100            │
│                                                 │
│ Scan Date: 2026-06-22 14:32:15                  │
│ Status: Complete                                │
│                                                 │
│ Findings: 8  |  Skills: 12  |  Recommendations: 5│
└─────────────────────────────────────────────────┘
```

#### 4c. Findings Summary
```
┌─────────────────────────────────────────────────┐
│ FINDINGS                                        │
│                                                 │
│ Security Issues:                    3 Critical  │
│ ▓▓▓░░░                                          │
│                                                 │
│ Performance Issues:                  1 High     │
│ ▓░░░░░                                          │
│                                                 │
│ Stability Issues:                    2 Medium   │
│ ▓▓░░░░░                                         │
│                                                 │
│ Compliance Issues:                   2 Low      │
│ ▓▓░░░░░░░                                       │
│                                                 │
│ [View Detailed Findings ▶]                      │
└─────────────────────────────────────────────────┘
```

#### 4d. Skills Detected
```
┌─────────────────────────────────────────────────┐
│ DETECTED SKILLS & TECHNOLOGIES                  │
│                                                 │
│ JavaScript      [Advanced]  Risk: Low  ✓        │
│ React.js        [Advanced]  Risk: Low  ✓        │
│ Node.js         [Intermediate] Risk: Medium ⚠   │
│ MongoDB         [Beginner]   Risk: High  ✗      │
│ Docker          [Intermediate] Risk: Low  ✓     │
│ AWS Lambda      [Beginner]   Risk: High  ✗      │
│                                                 │
│ [View All Skills ▶]                            │
└─────────────────────────────────────────────────┘
```

#### 4e. Recommendations
```
┌─────────────────────────────────────────────────┐
│ RECOMMENDATIONS                                 │
│                                                 │
│ 🔴 CRITICAL (Estimated: 4 hours)               │
│    • Add input validation to API endpoints      │
│    • Implement rate limiting on exposed routes  │
│                                                 │
│ 🟠 HIGH (Estimated: 6 hours)                   │
│    • Update deprecated dependencies            │
│    • Add comprehensive error logging            │
│                                                 │
│ 🟡 MEDIUM (Estimated: 3 hours)                 │
│    • Refactor duplicate utility functions      │
│    • Add JSDoc comments to exported functions  │
│                                                 │
│ 🟢 LOW (Estimated: 2 hours)                    │
│    • Update README with setup instructions     │
│    • Add contributing guidelines                │
│                                                 │
└─────────────────────────────────────────────────┘
```

---

### 5. Mobile Responsive Layout

**Breakpoints:**
- Desktop (≥1024px): 3-column layout where applicable
- Tablet (768px - 1023px): 2-column layout
- Mobile (<768px): Single-column stacked layout

**Mobile Stack Order:**
1. Header
2. Input form
3. Repository overview
4. Scan summary
5. Findings
6. Skills
7. Recommendations

---

## Design Specifications

### Color Scheme
- **Primary:** Blue (#2563EB)
- **Secondary:** Gray (#6B7280)
- **Success:** Green (#10B981)
- **Warning:** Orange (#F59E0B)
- **Critical:** Red (#EF4444)
- **Background:** White (#FFFFFF)
- **Text:** Dark Gray (#1F2937)

### Typography
- **Headings:** Bootstrap defaults (h1-h6)
- **Body:** System fonts (Segoe UI, Roboto, sans-serif)
- **Monospace:** Monaco, Courier New for code samples

### Components
- **Buttons:** Bootstrap btn classes with custom sizing
- **Forms:** Bootstrap form-control with validation feedback
- **Cards:** Bootstrap card component for sections
- **Progress:** Bootstrap progress bar for analysis status
- **Alerts:** Bootstrap alert for error/success messages
- **Badges:** For severity and skill proficiency levels

### Spacing & Layout
- **Padding:** 1rem default section padding
- **Margins:** 2rem between major sections
- **Grid:** Bootstrap 12-column grid
- **Container:** max-width 1200px

---

## User Flow

### Flow 1: New Analysis
```
User Opens App
    ↓
Sees Input Form
    ↓
Enters Repo URL & PAT
    ↓
Clicks "Analyze"
    ↓
Form Validation (Client-side)
    ↓
Submit to Server
    ↓
See Progress Indicator
    ↓
Poll for Results
    ↓
Display Results Sections
    ↓
Option to Analyze Another Repo
```

### Flow 2: View Detailed Findings
```
Click "View Detailed Findings"
    ↓
Expand/Collapse Findings List
    ↓
Filter by Category/Severity
    ↓
View Code Snippets (if available)
    ↓
Return to Summary
```

---

## Accessibility Considerations

- **WCAG 2.1 AA Compliance**
- Form labels associated with inputs
- Color not sole indicator (use icons + text)
- Keyboard navigation for all interactive elements
- Alt text for any icons
- Sufficient color contrast (4.5:1 for normal text)

---

## Bootstrap Implementation Notes

```html
<!-- Head Dependencies -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Key Classes Used -->
.container           - Main wrapper
.card               - Content sections
.form-control       - Form inputs
.btn                - Buttons
.badge              - Severity/skill labels
.progress           - Progress bar
.alert              - Messages
.row, .col-*        - Grid layout
.d-none, .d-block   - Show/hide elements
```

---

## Status Checklist

- [ ] Final wireframe approval
- [ ] Color scheme finalized
- [ ] Bootstrap integration tested
- [ ] Mobile responsiveness verified
- [ ] Accessibility audit complete
- [ ] Design handoff to development

**Target Completion:** 25-Jun-26
