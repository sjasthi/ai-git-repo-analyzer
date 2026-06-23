<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

try {
    $pdo = db_connection();

    $repositoryCount = (int) $pdo->query('SELECT COUNT(*) FROM repositories')->fetchColumn();
    $scanCount = (int) $pdo->query('SELECT COUNT(*) FROM scans')->fetchColumn();
    $findingCount = (int) $pdo->query('SELECT COUNT(*) FROM findings')->fetchColumn();
    $skillCount = (int) $pdo->query('SELECT COUNT(*) FROM skills')->fetchColumn();
    $recommendationCount = (int) $pdo->query('SELECT COUNT(*) FROM recommendations')->fetchColumn();
    $averageScore = $pdo->query('SELECT AVG(summary_score) FROM scans')->fetchColumn();
    $averageScoreText = $averageScore !== false ? number_format((float) $averageScore, 0) : '-';

    $recentScansStmt = $pdo->query(
        'SELECT s.id, s.scan_date, s.summary_score, s.total_findings, s.total_skills, r.repo_url
         FROM scans s
         JOIN repositories r ON s.repository_id = r.id
         ORDER BY s.scan_date DESC
         LIMIT 6'
    );
    $recentScans = $recentScansStmt->fetchAll();
} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
    $repositoryCount = $scanCount = $findingCount = $skillCount = $recommendationCount = 0;
    $averageScoreText = '-';
    $recentScans = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | AI Git Repo Analyzer</title>
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
            margin-bottom: 1rem;
            opacity: 0.95;
        }

        .header-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .card {
            border: 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            transition: box-shadow 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        .stat-card {
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            background: #ffffff;
            border: 1px solid #E5E7EB;
        }

        .stat-card strong {
            font-size: 2rem;
            display: block;
            margin-bottom: 0.5rem;
            color: #111827;
        }

        .stat-card small {
            color: #6B7280;
        }

        .overview-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
        }

        .summary-card {
            background: #F8FAFC;
            border-radius: 0.85rem;
            padding: 1.25rem;
            border: 1px solid #E5E7EB;
            text-align: center;
        }

        .summary-card strong {
            display: block;
            font-size: 1.75rem;
            color: #111827;
        }

        .summary-card small {
            color: #6B7280;
        }

        .table thead th {
            border-bottom: 2px solid #E5E7EB;
        }

        .table tbody tr:hover {
            background: rgba(167, 139, 250, 0.08);
        }

        .label-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .label-primary { background: rgba(167, 139, 250, 0.18); color: #6D28D9; }
        .label-success { background: #DCFCE7; color: #166534; }
        .label-warning { background: #FEF3C7; color: #92400E; }
        .label-danger { background: #FEE2E2; color: #991B1B; }

        @media (max-width: 991px) {
            .overview-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header-section">
        <div class="container">
            <h1><i class="fas fa-tachometer-alt"></i> AI Git Repo Analyzer Dashboard</h1>
            <p>Summary of repositories, scans, findings, skills, and recommendations.</p>
            <div class="header-actions">
                <a href="index.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="dashboard.php" class="btn btn-light btn-sm text-purple">
                    <i class="fas fa-th-large"></i> Recent History
                </a>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card p-4">
                    <div class="section-header">
                        <h2>Project Summary</h2>
                    </div>
                    <div class="overview-grid">
                        <div class="summary-card">
                            <small>Total Repositories</small>
                            <strong><?= $repositoryCount ?></strong>
                        </div>
                        <div class="summary-card">
                            <small>Total Scans</small>
                            <strong><?= $scanCount ?></strong>
                        </div>
                        <div class="summary-card">
                            <small>Average Score</small>
                            <strong><?= $averageScoreText ?></strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card p-4 stat-card">
                    <strong>Results Totals</strong>
                    <small>Findings</small>
                    <div class="mb-3"><?= $findingCount ?></div>
                    <small>Skills</small>
                    <div class="mb-3"><?= $skillCount ?></div>
                    <small>Recommendations</small>
                    <div><?= $recommendationCount ?></div>
                </div>
            </div>
        </div>

        <div class="card p-4 mb-4">
            <div class="section-header">
                <h2>Recent Scan History</h2>
                <span class="label-pill label-primary">Latest 6 entries</span>
            </div>
            <?php if (!empty($recentScans)): ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Repository</th>
                                <th>Scan Date</th>
                                <th>Score</th>
                                <th>Findings</th>
                                <th>Skills</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentScans as $scan): ?>
                                <tr>
                                    <td><?= htmlspecialchars($scan['repo_url'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($scan['scan_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) $scan['summary_score'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) $scan['total_findings'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) $scan['total_skills'], ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No scan history is available yet. Run an analysis from the home page to populate the dashboard.</p>
            <?php endif; ?>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card p-4">
                    <div class="section-header">
                        <h2>Quick Actions</h2>
                    </div>
                    <div class="d-flex flex-column gap-3">
                        <a href="index.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-search"></i> Analyze Another Repository
                        </a>
                        <a href="api/health.php" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-heartbeat"></i> Check API Health
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card p-4">
                    <div class="section-header">
                        <h2>Notes</h2>
                    </div>
                    <p class="text-muted">This dashboard displays counts from your latest scans and repository records. Use the homepage to submit a new analysis and refresh the dashboard data.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
