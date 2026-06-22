<?php

declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-Assisted Code and Skills Reviewer</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #A78BFA;
            --secondary: #8B7AB8;
            --success: #10B981;
            --warning: #F59E0B;
            --critical: #EF4444;
            --bg-light: #F5F3FF;
        }

        body {
            background: linear-gradient(180deg, #f5f3ff 0%, #faf8ff 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        /* Header */
        .header-section {
            background: linear-gradient(135deg, #9B59B6 0%, #7C3AED 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header-section h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .header-section p {
            font-size: 1.1rem;
            margin-bottom: 0;
            opacity: 0.95;
        }

        /* Cards */
        .card {
            border: 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            transition: box-shadow 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        /* Form Styling */
        .form-control, .form-select {
            border: 1px solid #E5E7EB;
            border-radius: 0.5rem;
            padding: 0.75rem;
            font-size: 1rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-label {
            font-weight: 600;
            color: #1F2937;
            margin-bottom: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            padding: 0.75rem 2rem;
            font-weight: 600;
        }

        .btn-primary:hover {
            background-color: #9333EA;
            border-color: #9333EA;
        }

        /* Progress Indicator */
        .progress-section {
            display: none;
        }

        .progress-step {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: #F3F4F6;
            border-radius: 0.5rem;
        }

        .progress-step.active {
            background: #EFF6FF;
            border-left: 4px solid var(--primary);
        }

        .progress-step.complete {
            background: #F0FDF4;
            border-left: 4px solid var(--success);
        }

        .progress-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .progress-step.complete .progress-icon {
            background: var(--success);
            color: white;
        }

        .progress-step.active .progress-icon {
            background: var(--primary);
            color: white;
            animation: spin 1s linear infinite;
        }

        .progress-step.pending .progress-icon {
            background: #D1D5DB;
            color: white;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Results Section */
        .results-section {
            display: none;
        }

        .info-card {
            background: #ffffff;
            border: 1px solid #E5E7EB;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .section-header h2,
        .section-header h3 {
            margin: 0;
            font-weight: 700;
        }

        .section-header h2 {
            font-size: 1.05rem;
        }

        .section-header h3 {
            font-size: 1rem;
            color: #111827;
        }

        .overview-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .overview-item {
            background: #F8FAFC;
            border: 1px solid #E5E7EB;
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: center;
        }

        .overview-item span {
            display: block;
            font-weight: 700;
            font-size: 1.25rem;
            color: #111827;
        }

        .overview-item small {
            color: #6B7280;
        }

        .finding-item {
            background: #F8FAFC;
            border: 1px solid #E5E7EB;
            border-radius: 0.85rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .finding-item-title {
            font-weight: 700;
            margin-bottom: 0.35rem;
            color: #111827;
        }

        .finding-item-body {
            color: #4B5563;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .finding-meta {
            margin-top: 0.75rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }

        .finding-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            background: #E5E7EB;
            color: #111827;
        }

        .skill-item {
            background: #ffffff;
            border: 1px solid #E5E7EB;
            border-radius: 0.85rem;
            padding: 1rem;
            transition: transform 0.2s ease;
        }

        .skill-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
        }

        .skill-name {
            font-weight: 700;
            color: #111827;
        }

        .skill-level {
            color: #6B7280;
            margin-bottom: 0.6rem;
        }

        .recommendation-item {
            border-left: 4px solid #E5E7EB;
            padding: 1rem 1.25rem;
            background: #ffffff;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            transition: transform 0.2s ease;
        }

        .recommendation-item:hover {
            transform: translateY(-2px);
        }

        .recommendation-item.critical {
            border-left-color: var(--critical);
        }

        .recommendation-item.high {
            border-left-color: var(--warning);
        }

        .recommendation-item.medium {
            border-left-color: #3B82F6;
        }

        .recommendation-item.low {
            border-left-color: var(--success);
        }

        .recommendation-title {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .recommendation-priority {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
        }

        .recommendation-priority.critical {
            background: #FEE2E2;
            color: #991B1B;
        }

        .recommendation-priority.high {
            background: #FEF3C7;
            color: #92400E;
        }

        .recommendation-priority.medium {
            background: #DBEAFE;
            color: #1E40AF;
        }

        .recommendation-priority.low {
            background: #DCFCE7;
            color: #166534;
        }

        .analysis-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .summary-card {
            background: #F8FAFC;
            border-radius: 0.85rem;
            padding: 1rem;
            text-align: center;
            border: 1px solid #E5E7EB;
        }

        .summary-card strong {
            display: block;
            font-size: 1.75rem;
            color: #111827;
        }

        .summary-card small {
            color: #6B7280;
        }

        .progress-bar {
            transition: width 0.3s ease;
        }

        .badge-critical, .badge-high, .badge-medium, .badge-low {
            font-size: 0.75rem;
            padding: 0.45rem 0.75rem;
            border-radius: 999px;
            font-weight: 700;
        }

        .details-label {
            color: #6B7280;
            font-size: 0.9rem;
            margin-right: 0.75rem;
        }

        @media (max-width: 992px) {
            .overview-grid, .analysis-summary-grid {
                grid-template-columns: 1fr;
            }
        }

        .badge-high {
            background-color: #FEF3C7;
            color: #92400E;
        }

        .badge-medium {
            background-color: #DBEAFE;
            color: #1E40AF;
        }

        .badge-low {
            background-color: #DCFCE7;
            color: #166534;
        }

        /* Repository Card */
        .repo-card {
            border-left: 4px solid var(--primary);
        }

        .repo-meta {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .repo-meta-item {
            display: flex;
            flex-direction: column;
        }

        .repo-meta-label {
            font-size: 0.875rem;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }

        .repo-meta-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1F2937;
        }

        /* Scan Summary */
        .scan-summary {
            background: linear-gradient(135deg, #F3E8FF 0%, #FAF5FF 100%);
            border-left: 4px solid var(--primary);
        

        .score-display {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1;
        }

        .score-label {
            font-size: 0.875rem;
            color: #6B7280;
            margin-top: 0.5rem;
        }

        /* Findings */
        .findings-category {
            margin-bottom: 1.5rem;
        }

        .findings-category-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .findings-count {
            font-size: 0.875rem;
            font-weight: 700;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
        }

        .progress {
            height: 0.5rem;
            background-color: #E5E7EB;
            border-radius: 999px;
            overflow: hidden;
        }

        .progress-bar {
            transition: width 0.3s ease;
        }

        /* Skills Grid */
        .skills-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }

        .skill-item {
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
        }

        .skill-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1F2937;
        }

        .skill-level {
            font-size: 0.875rem;
            color: #6B7280;
            margin-bottom: 0.5rem;
        }

        /* Recommendations */
        .recommendation-item {
            border-left: 4px solid #E5E7EB;
            padding: 1rem;
            background: #F9FAFB;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .recommendation-item.critical {
            border-left-color: var(--critical);
            background: #FEF2F2;
        }

        .recommendation-item.high {
            border-left-color: var(--warning);
            background: #FFFBEB;
        }

        .recommendation-item.medium {
            border-left-color: #3B82F6;
            background: #EFF6FF;
        }

        .recommendation-item.low {
            border-left-color: var(--success);
            background: #F0FDF4;
        }

        .recommendation-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .recommendation-priority {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            margin-bottom: 0.5rem;
        }

        .recommendation-priority.critical {
            background: #FEE2E2;
            color: #991B1B;
        }

        .recommendation-priority.high {
            background: #FEF3C7;
            color: #92400E;
        }

        .recommendation-priority.medium {
            background: #DBEAFE;
            color: #1E40AF;
        }

        .recommendation-priority.low {
            background: #DCFCE7;
            color: #166534;
        }

        /* Error Messages */
        .alert {
            border-radius: 0.5rem;
            border: 0;
        }

        .alert-danger {
            background-color: #FEE2E2;
            color: #991B1B;
        }

        .alert-success {
            background-color: #F0FDF4;
            color: #166534;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-section h1 {
                font-size: 1.5rem;
            }

            .repo-meta {
                gap: 1rem;
            }

            .skills-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }

            .score-display {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-section">
        <div class="container">
            <h1><i class="fas fa-code"></i> AI Git Repo Analyzer</h1>
            <p>Intelligent Code Review and Skills Analysis</p>
        </div>
    </div>

    <div class="container">
        <!-- Input Form Section -->
        <div class="card p-4" id="input-section">
            <h2 class="h5 mb-4"><i class="fas fa-search"></i> Analyze Your GitHub Repository</h2>

            <form id="analyze-form">
                <div class="mb-3">
                    <label for="repo_url" class="form-label">Repository URL</label>
                    <input
                        type="url"
                        id="repo_url"
                        name="repo_url"
                        class="form-control"
                        placeholder="https://github.com/owner/repository"
                        required
                    >
                    <div class="form-text">Enter the GitHub repository URL you want to analyze.</div>
                </div>

                <div class="mb-3">
                    <label for="pat" class="form-label">Personal Access Token (PAT)</label>
                    <input
                        type="password"
                        id="pat"
                        name="pat"
                        class="form-control"
                        placeholder="ghp_..."
                        required
                    >
                    <div class="form-text">Your token is used only for API access and is <strong>not stored</strong> in the database.</div>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-play"></i> Analyze Repository
                    </button>
                    <button type="button" id="health-check" class="btn btn-outline-secondary">
                        <i class="fas fa-heartbeat"></i> Check API Health
                    </button>
                </div>

                <div id="error-message" class="alert alert-danger mt-3" style="display: none;"></div>
            </form>
        </div>

        <!-- Progress Indicator Section -->
        <div class="card p-4 progress-section" id="progress-section">
            <h2 class="h5 mb-4"><i class="fas fa-spinner"></i> Analyzing Repository...</h2>

            <div class="progress mb-4">
                <div class="progress-bar bg-primary" id="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
            </div>

            <div id="progress-steps">
                <div class="progress-step pending" data-step="validate">
                    <div class="progress-icon">1</div>
                    <div>Validating GitHub Access</div>
                </div>
                <div class="progress-step pending" data-step="fetch-meta">
                    <div class="progress-icon">2</div>
                    <div>Fetching Repository Metadata</div>
                </div>
                <div class="progress-step pending" data-step="fetch-code">
                    <div class="progress-icon">3</div>
                    <div>Fetching Code Files</div>
                </div>
                <div class="progress-step pending" data-step="analyze">
                    <div class="progress-icon">4</div>
                    <div>Analyzing Code</div>
                </div>
                <div class="progress-step pending" data-step="generate">
                    <div class="progress-icon">5</div>
                    <div>Generating Recommendations</div>
                </div>
            </div>
        </div>

        <!-- Results Section -->
        <div class="results-section" id="results-section">
            <!-- Repository Overview -->
            <div class="card p-4 repo-card" id="repo-card">
                <h2 class="h5 mb-3"><i class="fas fa-github"></i> Repository Overview</h2>
                <div id="repo-content">
                    <h3 id="repo-name" class="h6 mb-2"></h3>
                    <p id="repo-description" class="text-muted mb-3"></p>
                            <div class="overview-grid">
                        <div class="overview-item">
                            <small>Owner</small>
                            <span id="repo-owner">-</span>
                        </div>
                        <div class="overview-item">
                            <small>Primary Language</small>
                            <span id="repo-language">-</span>
                        </div>
                        <div class="overview-item">
                            <small>Stars</small>
                            <span id="repo-stars">0</span>
                        </div>
                        <div class="overview-item">
                            <small>Forks</small>
                            <span id="repo-forks">0</span>
                        </div>
                        <div class="overview-item">
                            <small>Watchers</small>
                            <span id="repo-watchers">0</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scan Summary -->
            <div class="card p-4 scan-summary" id="scan-summary">
                <div class="section-header">
                    <h2><i class="fas fa-chart-pie"></i> Scan Results</h2>
                    <small class="text-muted">Scan Date: <span id="scan-date">-</span></small>
                </div>
                <div class="analysis-summary-grid">
                    <div class="summary-card">
                        <small>Overall Score</small>
                        <strong id="overall-score">-</strong>
                    </div>
                    <div class="summary-card">
                        <small>Findings</small>
                        <strong id="findings-count">0</strong>
                    </div>
                    <div class="summary-card">
                        <small>Recommendations</small>
                        <strong id="recommendations-count">0</strong>
                    </div>
                </div>
            </div>

            <!-- Findings Section -->
            <div class="card p-4" id="findings-card">
                <h2 class="h5 mb-4"><i class="fas fa-exclamation-circle"></i> Findings</h2>
                <div id="findings-content">
                            <div class="findings-category">
                        <div class="section-header">
                            <h3><i class="fas fa-shield-alt"></i> Security Issues</h3>
                            <span class="findings-count badge badge-critical" id="security-count">0</span>
                        </div>
                        <div id="security-findings" class="findings-list"></div>
                    </div>

                    <div class="findings-category">
                        <div class="section-header">
                            <h3><i class="fas fa-tachometer-alt"></i> Performance Issues</h3>
                            <span class="findings-count badge badge-high" id="performance-count">0</span>
                        </div>
                        <div id="performance-findings" class="findings-list"></div>
                    </div>

                    <div class="findings-category">
                        <div class="section-header">
                            <h3><i class="fas fa-heartbeat"></i> Stability Issues</h3>
                            <span class="findings-count badge badge-medium" id="stability-count">0</span>
                        </div>
                        <div id="stability-findings" class="findings-list"></div>
                    </div>

                    <div class="findings-category">
                        <div class="section-header">
                            <h3><i class="fas fa-legal"></i> Compliance Issues</h3>
                            <span class="findings-count badge badge-low" id="compliance-count">0</span>
                        </div>
                        <div id="compliance-findings" class="findings-list"></div>
                    </div>
                </div>
            </div>

            <!-- Skills Section -->
            <div class="card p-4" id="skills-card">
                <h2 class="h5 mb-4"><i class="fas fa-star"></i> Detected Skills & Technologies</h2>
                <div id="skills-content" class="skills-grid"></div>
            </div>

            <!-- Recommendations Section -->
            <div class="card p-4" id="recommendations-card">
                <h2 class="h5 mb-4"><i class="fas fa-lightbulb"></i> Recommendations</h2>
                <div id="recommendations-content"></div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex gap-2 mb-5">
                <button type="button" id="analyze-another" class="btn btn-primary">
                    <i class="fas fa-redo"></i> Analyze Another Repository
                </button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        function showSection(section) {
            $('#input-section').hide();
            $('#progress-section').hide();
            $('#results-section').hide();
            $('#' + section).show();
        }

        function updateProgress(step, status) {
            const stepEl = $('[data-step="' + step + '"]');
            stepEl.removeClass('pending complete active');
            stepEl.addClass(status);
        }

        function showError(message) {
            $('#error-message').text(message).show();
            showSection('input-section');
        }

        function displayResults(data) {
            // Repository info
            const repoName = data.repository ? (data.repository.full_name || data.repository.name || 'Unknown') : 'Unknown';
            const repoDescription = data.repository && data.repository.description ? data.repository.description : 'No description provided.';

            $('#repo-name').text(repoName);
            $('#repo-description').text(repoDescription);
            $('#repo-owner').text(data.repository ? data.repository.owner : 'Unknown');
            $('#repo-language').text(data.repository ? data.repository.language || 'N/A' : 'N/A');
            $('#repo-stars').text(data.repository ? data.repository.stars : 0);
            $('#repo-forks').text(data.repository ? data.repository.forks : 0);
            $('#repo-watchers').text(data.repository ? data.repository.watchers : 0);

            // Scan summary
            $('#overall-score').text(data.scan ? (data.scan.summary_score || '-') : '-');
            $('#scan-date').text(new Date().toLocaleString());
            
            // Counts
            const findingsCount = (data.findings || []).length;
            const skillsCount = (data.skills || []).length;
            const recommendationsCount = (data.recommendations || []).length;
            
            $('#findings-count').text(findingsCount);
            $('#skills-count').text(skillsCount);
            $('#recommendations-count').text(recommendationsCount);

            // Findings by category
            const categories = {
                'Security': 'security',
                'Performance': 'performance',
                'Stability': 'stability',
                'Compliance': 'compliance'
            };

            Object.entries(categories).forEach(([cat, key]) => {
                const findings = (data.findings || []).filter(f => f.category === cat);
                const count = findings.length;
                $('#' + key + '-count').text(count);

                let html = '';
                findings.forEach(f => {
                    html += `
                        <div class="finding-item">
                            <div class="finding-item-title">${f.title || 'Finding'}</div>
                            <div class="finding-item-body">${f.description || 'No details available.'}</div>
                            <div class="finding-meta">
                                <span class="finding-tag">Severity: ${f.severity || 'Unknown'}</span>
                                <span class="finding-tag">Category: ${f.category || 'General'}</span>
                            </div>
                        </div>
                    `;
                });
                $('#' + key + '-findings').html(html || '<small class="text-muted">No issues found</small>');
            });

            // Skills
            let skillsHtml = '';
            (data.skills || []).forEach(s => {
                skillsHtml += `
                    <div class="skill-item">
                        <div class="skill-name">${s.skill_name}</div>
                        <div class="skill-level">${s.proficiency_level}</div>
                        <div class="badge badge-${s.risk_level.toLowerCase()}">Risk: ${s.risk_level}</div>
                    </div>
                `;
            });
            $('#skills-content').html(skillsHtml || '<small class="text-muted">No skills detected</small>');

            // Recommendations
            let recsHtml = '';
            (data.recommendations || []).forEach(r => {
                recsHtml += `
                    <div class="recommendation-item ${r.priority.toLowerCase()}">
                        <div class="recommendation-priority ${r.priority.toLowerCase()}">${r.priority}</div>
                        <div class="recommendation-title">${r.recommendation_text}</div>
                    </div>
                `;
            });
            $('#recommendations-content').html(recsHtml || '<small class="text-muted">No recommendations</small>');

            showSection('results-section');
        }

        // Health Check
        $('#health-check').on('click', function () {
            $.get('api/health.php')
                .done(function (data) {
                    if (data.status === 'ok') {
                        alert('✓ API and Database are healthy!');
                    } else {
                        showError('Health check failed: Database not connected');
                    }
                })
                .fail(function () {
                    showError('Health check failed: Could not reach API');
                });
        });

        // Analyze Form
        $('#analyze-form').on('submit', function (e) {
            e.preventDefault();
            $('#error-message').hide();

            const repoUrl = $('#repo_url').val();
            const pat = $('#pat').val();

            if (!repoUrl || !pat) {
                showError('Please fill in all fields');
                return;
            }

            showSection('progress-section');
            updateProgress('validate', 'active');

            $.ajax({
                url: 'api/analyze.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    repo_url: repoUrl,
                    pat: pat
                }),
                success: function (data) {
                    updateProgress('validate', 'complete');
                    updateProgress('fetch-meta', 'complete');
                    updateProgress('fetch-code', 'complete');
                    updateProgress('analyze', 'complete');
                    updateProgress('generate', 'complete');

                    // Simulate display after all steps
                    setTimeout(function () {
                        displayResults(data);
                    }, 500);
                },
                error: function (xhr) {
                    let message = 'Analysis failed';
                    let details = '';

                    try {
                        const error = JSON.parse(xhr.responseText);
                        message = error.message || error.error || message;
                        details = error.details ? ' Details: ' + error.details : '';
                        if (error.github_message) {
                            details += ' GitHub: ' + error.github_message;
                        }
                    } catch (e) {
                        details = ' Status: ' + xhr.status;
                    }

                    showError(message + details);
                }
            });
        });

        // Analyze Another
        $('#analyze-another').on('click', function () {
            $('#repo_url').val('');
            $('#pat').val('');
            showSection('input-section');
        });

        // Show input section initially
        showSection('input-section');
    </script>
</body>
</html>
