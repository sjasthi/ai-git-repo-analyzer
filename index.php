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
    <style>
        body {
            background: linear-gradient(180deg, #f4f8fb 0%, #ffffff 100%);
            min-height: 100vh;
        }

        .card {
            border: 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .status-box {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
            font-size: 0.9rem;
            white-space: pre-wrap;
            min-height: 120px;
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card p-4">
                <h1 class="h3 mb-3">AI-Assisted Code and Skills Reviewer</h1>
                <p class="text-muted mb-4">Starter server is ready. Submit a GitHub repository URL and PAT to create an initial scan record.</p>

                <form id="analyze-form" class="mb-3">
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
                        <div class="form-text">Token is used only for API access and is not stored in the database.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Analyze Repository</button>
                        <button type="button" id="health-check" class="btn btn-outline-secondary">Check API Health</button>
                    </div>
                </form>

                <div class="border rounded p-3 bg-light status-box" id="response-box">Ready.</div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    function showResponse(payload) {
        $('#response-box').text(JSON.stringify(payload, null, 2));
    }

    $('#health-check').on('click', function () {
        $.get('api/health.php')
            .done(function (data) {
                showResponse(data);
            })
            .fail(function (xhr) {
                showResponse({
                    error: 'Health check failed',
                    details: xhr.responseText
                });
            });
    });

    $('#analyze-form').on('submit', function (event) {
        event.preventDefault();

        const formData = $(this).serialize();

        $.post('api/analyze.php', formData)
            .done(function (data) {
                showResponse(data);
            })
            .fail(function (xhr) {
                const payload = xhr.responseJSON || {
                    error: 'Analyze request failed',
                    details: xhr.responseText
                };
                showResponse(payload);
            });
    });
</script>
</body>
</html>
