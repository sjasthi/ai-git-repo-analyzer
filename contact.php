<?php

declare(strict_types=1);

$contactEmail = 'ContactUs@aigitrepoanalyzer.com';
$formStatus = '';
$formStatusType = '';
$formStatusHtml = '';

$nameValue = trim((string) ($_POST['name'] ?? ''));
$emailValue = trim((string) ($_POST['email'] ?? ''));
$subjectValue = trim((string) ($_POST['subject'] ?? ''));
$messageValue = trim((string) ($_POST['message'] ?? ''));

if (isset($_GET['sent']) && $_GET['sent'] === '1') {
    $formStatusType = 'success';
    $formStatusHtml = '<strong>Thank you for contacting us.</strong> We have successfully received your inquiry. Our team will review your questions or concerns and respond within <strong>1-2 business days</strong>. Thank you for your patience.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($nameValue === '' || $emailValue === '' || $subjectValue === '' || $messageValue === '') {
        $formStatus = 'Please complete all fields before sending your message.';
        $formStatusType = 'danger';
    } elseif (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
        $formStatus = 'Please enter a valid email address.';
        $formStatusType = 'danger';
    } else {
        $safeName = str_replace(["\r", "\n"], ' ', $nameValue);
        $safeEmail = str_replace(["\r", "\n"], '', $emailValue);
        $safeSubject = str_replace(["\r", "\n"], ' ', $subjectValue);
        $safeMessage = str_replace(["\r\n", "\r"], "\n", $messageValue);

        $mailSubject = '[AI Git Repo Analyzer Contact] ' . $safeSubject;
        $mailBody = "Name: {$safeName}\n" .
            "Email: {$safeEmail}\n\n" .
            "Message:\n{$safeMessage}\n";

        $headers = 'From: ' . $contactEmail . "\r\n" .
            'Reply-To: ' . $safeEmail . "\r\n" .
            'Content-Type: text/plain; charset=UTF-8';

        @mail($contactEmail, $mailSubject, $mailBody, $headers);

        // Always follow Post/Redirect/Get for a consistent confirmation flow.
        header('Location: contact.php?sent=1');
        exit;
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact | AI Git Repo Analyzer</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --header-gradient: linear-gradient(135deg, #9B59B6 0%, #7C3AED 100%);
            --bg-body: linear-gradient(180deg, #f5f3ff 0%, #faf8ff 100%);
            --surface: #ffffff;
            --surface-soft: #f8fafc;
            --border: #e5e7eb;
            --text-main: #111827;
            --text-muted: #6b7280;
        }

        body[data-theme="dark"] {
            --bg-body: linear-gradient(180deg, #121826 0%, #0d1321 100%);
            --text-main: #E5E7EB;
            --text-muted: #9CA3AF;
            --surface: #1F2937;
            --surface-soft: #111827;
            --border: #374151;
            --header-gradient: linear-gradient(135deg, #5B21B6 0%, #312E81 100%);
        }

        body {
            background: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .header-section {
            background: var(--header-gradient);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header-section h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.4rem;
        }

        .header-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .theme-toggle-btn {
            border-width: 1px;
        }

        .card-soft {
            border: 1px solid var(--border);
            border-radius: 1rem;
            background: var(--surface);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .value {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 0.9rem;
            text-align: center;
        }

        .contact-main {
            min-height: 55vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
        }

        .contact-card {
            width: 100%;
            max-width: 760px;
        }

        .contact-form-wrap {
            margin-top: 1.4rem;
            border-top: 1px solid var(--border);
            padding-top: 1.2rem;
        }

        .form-label {
            font-weight: 600;
        }

        .form-control {
            background-color: var(--surface-soft);
            color: var(--text-main);
            border-color: var(--border);
        }

        .form-control:focus {
            background-color: var(--surface-soft);
            color: var(--text-main);
            border-color: #7C3AED;
            box-shadow: 0 0 0 0.2rem rgba(124, 58, 237, 0.18);
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        body[data-theme="dark"] .btn-outline-light {
            color: #D1D5DB;
            border-color: #9CA3AF;
        }

        body[data-theme="dark"] .btn-light {
            background-color: #374151;
            border-color: #4B5563;
            color: #F3F4F6;
        }

        .site-footer {
            margin-top: 2rem;
            padding: 1.1rem 0;
            background: var(--header-gradient);
            color: white;
        }

        .site-footer .footer-line {
            margin: 0;
            text-align: center;
            font-weight: 700;
            font-size: 2rem;
            line-height: 1.2;
        }
    </style>
</head>
<body>
    <div class="header-section">
        <div class="container">
            <h1><i class="fas fa-address-card"></i> Contact</h1>
            <p class="mb-3">Project contact information for AI Git Repo Analyzer.</p>
            <div class="header-actions">
                <a href="index.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-th-large"></i> History
                </a>
                <a href="contact.php" class="btn btn-light btn-sm">
                    <i class="fas fa-address-card"></i> Contact
                </a>
                <button type="button" id="theme-toggle" class="btn btn-outline-light btn-sm theme-toggle-btn">
                    <i class="fas fa-moon"></i> Dark Mode
                </button>
            </div>
        </div>
    </div>

    <div class="container pb-5 contact-main">
        <div class="card-soft p-4 contact-card">
            <div class="value">AI Git Repo Analyzer</div>
            <div class="value">803 Summer Street, MN 55106</div>
            <div class="value mb-0"><?= h($contactEmail) ?></div>

            <div class="contact-form-wrap">
                <h2 class="h5 mb-3">Send Us a Message If You Have Any Questions or Concerns:</h2>

                <?php if ($formStatusHtml !== ''): ?>
                    <div class="alert alert-<?= h($formStatusType) ?>" role="alert">
                        <?= $formStatusHtml ?>
                    </div>
                <?php elseif ($formStatus !== ''): ?>
                    <div class="alert alert-<?= h($formStatusType) ?>" role="alert">
                        <?= h($formStatus) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="contact.php">
                    <div class="mb-3">
                        <label for="contact-name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="contact-name" name="name" value="<?= h($nameValue) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="contact-email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="contact-email" name="email" value="<?= h($emailValue) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="contact-subject" class="form-label">Subject/Concern</label>
                        <input type="text" class="form-control" id="contact-subject" name="subject" value="<?= h($subjectValue) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="contact-message" class="form-label">Message</label>
                        <textarea class="form-control" id="contact-message" name="message" rows="5" required><?= h($messageValue) ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Email</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const THEME_KEY = 'ai_git_repo_theme';
            const body = document.body;
            const toggle = document.getElementById('theme-toggle');

            function preferredTheme() {
                const savedTheme = localStorage.getItem(THEME_KEY);
                if (savedTheme === 'light' || savedTheme === 'dark') {
                    return savedTheme;
                }
                return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
                    ? 'dark'
                    : 'light';
            }

            function applyTheme(theme) {
                const nextTheme = theme === 'dark' ? 'dark' : 'light';
                body.setAttribute('data-theme', nextTheme);
                if (toggle) {
                    toggle.innerHTML = nextTheme === 'dark'
                        ? '<i class="fas fa-sun"></i> Light Mode'
                        : '<i class="fas fa-moon"></i> Dark Mode';
                }
            }

            applyTheme(preferredTheme());

            if (toggle) {
                toggle.addEventListener('click', function () {
                    const currentTheme = body.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                    const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
                    localStorage.setItem(THEME_KEY, nextTheme);
                    applyTheme(nextTheme);
                });
            }
        })();
    </script>

    <footer class="site-footer">
        <div class="container">
            <p class="footer-line">@2026 AI Git Repo Analyzer</p>
            <p class="footer-line">803 Summer Street, MN 55106</p>
            <p class="footer-line"><?= h($contactEmail) ?></p>
        </div>
    </footer>
</body>
</html>
