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
            --primary: #7C3AED;
            --primary-light: #A78BFA;
        }

        body {
            background: linear-gradient(180deg, #f5f3ff 0%, #faf8ff 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .header-section {
            background: linear-gradient(135deg, #9B59B6 0%, #7C3AED 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .header-section h1 { font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; }
        .header-section p  { font-size: 1.1rem; opacity: 0.9; margin-bottom: 1rem; }

        .header-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }

        .card {
            border: 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border-radius: 1rem;
            margin-bottom: 1.5rem;
        }

        .score-badge {
            display: inline-block;
            font-size: 2.5rem;
            font-weight: 700;
            width: 90px;
            height: 90px;
            line-height: 90px;
            text-align: center;
            border-radius: 50%;
            color: white;
        }

        .score-good    { background: #10B981; }
        .score-medium  { background: #F59E0B; }
        .score-low     { background: #EF4444; }

        .severity-High     { color: #DC2626; font-weight: 600; }
        .severity-Medium   { color: #D97706; font-weight: 600; }
        .severity-Low      { color: #16A34A; font-weight: 600; }

        .pill {
            display: inline-block;
            padding: 0.2rem 0.65rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
        }

        .pill-purple  { background: rgba(124,58,237,0.12); color: #6D28D9; }
        .pill-green   { background: #DCFCE7; color: #166534; }
        .pill-yellow  { background: #FEF3C7; color: #92400E; }
        .pill-red     { background: #FEE2E2; color: #991B1B; }

        #result-section { display: none; }
    </style>
</head>
<body>

<div class="header-section">
    <div class="container">
        <h1><i class="fas fa-code-branch"></i> AI Git Repo Analyzer</h1>
        <p>Submit a GitHub repository to analyze its code quality, skills, and findings.</p>
        <div class="header-actions">
            <a href="index.php" class="btn btn-light btn-sm">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-history"></i> View Scan History
            </a>
        </div>
    </div>
</div>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <!-- Analysis Form -->
            <div class="card p-4">
                <h2 class="h5 mb-3"><i class="fas fa-search text-purple"></i> Analyze a Repository</h2>

                <form id="analyze-form">
                    <div class="mb-3">
                        <label for="repo_url" class="form-label">GitHub Repository URL</label>
                        <input
                            type="url"
                            id="repo_url"
                            name="repo_url"
                            class="form-control"
                            placeholder="https://github.com/owner/repository"
                            required
                        >
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
                        <div class="form-text">Used only for GitHub API access — never stored in the database.</div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary" id="submit-btn">
                            <i class="fas fa-play"></i> Analyze Repository
                        </button>
                        <button type="button" id="health-check" class="btn btn-outline-secondary">
                            <i class="fas fa-heartbeat"></i> Check API Health
                        </button>
                    </div>
                </form>

                <div id="status-msg" class="mt-3 text-muted small"></div>
            </div>

            <!-- Results Section -->
            <div id="result-section">

                <!-- Repo Overview -->
                <div class="card p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div id="score-badge" class="score-badge"></div>
                        <div>
                            <h2 class="h5 mb-1" id="res-name"></h2>
                            <p class="text-muted mb-1 small" id="res-description"></p>
                            <span class="pill pill-purple" id="res-language"></span>
                        </div>
                    </div>
                    <div class="row text-center g-2 mt-1">
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <strong id="res-stars"></strong><br><small class="text-muted">Stars</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <strong id="res-forks"></strong><br><small class="text-muted">Forks</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <strong id="res-watchers"></strong><br><small class="text-muted">Watchers</small>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="dashboard.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-history"></i> View All Scan Records
                        </a>
                    </div>
                </div>

                <!-- Findings -->
                <div class="card p-4" id="findings-card">
                    <h3 class="h6 mb-3"><i class="fas fa-exclamation-triangle text-warning"></i> Findings</h3>
                    <ul class="list-group list-group-flush" id="findings-list"></ul>
                </div>

                <!-- Skills -->
                <div class="card p-4" id="skills-card">
                    <h3 class="h6 mb-3"><i class="fas fa-tools text-primary"></i> Detected Skills</h3>
                    <ul class="list-group list-group-flush" id="skills-list"></ul>
                </div>

                <!-- Recommendations -->
                <div class="card p-4" id="recommendations-card">
                    <h3 class="h6 mb-3"><i class="fas fa-lightbulb text-success"></i> Recommendations</h3>
                    <ul class="list-group list-group-flush" id="recommendations-list"></ul>
                </div>

            </div><!-- /result-section -->

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    function setStatus(msg, isError) {
        $('#status-msg').html(msg).css('color', isError ? '#DC2626' : '#6B7280');
    }

    function scoreBadgeClass(score) {
        if (score >= 80) return 'score-good';
        if (score >= 60) return 'score-medium';
        return 'score-low';
    }

    function priorityPill(priority) {
        const map = { High: 'pill-red', Medium: 'pill-yellow', Low: 'pill-green' };
        return `<span class="pill ${map[priority] || 'pill-purple'}">${priority}</span>`;
    }

    function renderResults(data) {
        const repo = data.repository || {};
        const scan = data.scan || {};
        const score = scan.summary_score ?? 0;

        // Score badge
        const badge = $('#score-badge');
        badge.text(score).removeClass('score-good score-medium score-low').addClass(scoreBadgeClass(score));

        // Repo info
        $('#res-name').text(repo.full_name || repo.name || '');
        $('#res-description').text(repo.description || 'No description provided.');
        $('#res-language').text(repo.language || 'Unknown');
        $('#res-stars').text(repo.stars ?? 0);
        $('#res-forks').text(repo.forks ?? 0);
        $('#res-watchers').text(repo.watchers ?? 0);

        // Findings
        const findingsList = $('#findings-list').empty();
        if (data.findings && data.findings.length) {
            data.findings.forEach(function(f) {
                findingsList.append(
                    `<li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>${$('<span>').text(f.title).html()}</strong>
                                <span class="severity-${f.severity} ms-2 small">${f.severity}</span>
                                <p class="mb-0 small text-muted mt-1">${$('<span>').text(f.description).html()}</p>
                            </div>
                            <span class="pill pill-purple ms-2">${$('<span>').text(f.category).html()}</span>
                        </div>
                    </li>`
                );
            });
            $('#findings-card').show();
        } else {
            $('#findings-card').hide();
        }

        // Skills
        const skillsList = $('#skills-list').empty();
        if (data.skills && data.skills.length) {
            data.skills.forEach(function(s) {
                skillsList.append(
                    `<li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><strong>${$('<span>').text(s.skill_name).html()}</strong> — ${$('<span>').text(s.proficiency_level).html()}</span>
                        ${priorityPill(s.risk_level)}
                    </li>`
                );
            });
            $('#skills-card').show();
        } else {
            $('#skills-card').hide();
        }

        // Recommendations
        const recList = $('#recommendations-list').empty();
        if (data.recommendations && data.recommendations.length) {
            data.recommendations.forEach(function(r) {
                recList.append(
                    `<li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                        <span class="small">${$('<span>').text(r.recommendation_text).html()}</span>
                        ${priorityPill(r.priority)}
                    </li>`
                );
            });
            $('#recommendations-card').show();
        } else {
            $('#recommendations-card').hide();
        }

        $('#result-section').show();
        $('html, body').animate({ scrollTop: $('#result-section').offset().top - 20 }, 400);
    }

    $('#health-check').on('click', function () {
        setStatus('Checking API health…');
        $.get('api/health.php')
            .done(function (data) { setStatus('API is healthy: ' + JSON.stringify(data)); })
            .fail(function (xhr) { setStatus('Health check failed: ' + xhr.responseText, true); });
    });

    $('#analyze-form').on('submit', function (event) {
        event.preventDefault();

        const btn = $('#submit-btn');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Analyzing…');
        setStatus('Sending request to GitHub API and saving to database…');
        $('#result-section').hide();

        $.post('api/analyze.php', $(this).serialize())
            .done(function (data) {
                setStatus('Scan complete. Record saved to database (scan #' + data.scan_id + ').');
                renderResults(data);
            })
            .fail(function (xhr) {
                const err = xhr.responseJSON || { error: 'Request failed', details: xhr.responseText };
                setStatus('Error: ' + (err.error || 'Unknown error') + (err.details ? ' — ' + err.details : ''), true);
            })
            .always(function () {
                btn.prop('disabled', false).html('<i class="fas fa-play"></i> Analyze Repository');
            });
    });
</script>
</body>
</html>
