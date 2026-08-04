<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status = 400): void
{
    json_response(['ok' => false, 'error' => $message], $status);
}

function normalize_text(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;

    return $value;
}

function faq_entries(): array
{
    return [
        [
            'id' => 'what-is-tool',
            'question' => 'What is this website?',
            'answer' => implode("\n", [
                'AI Git Repo Analyzer reviews GitHub repositories and generates a structured code health report.',
                'It summarizes security, quality, testing, architecture, dependency, DevOps, and maintainability signals.',
            ]),
        ],
        [
            'id' => 'how-to-use',
            'question' => 'How do I use this website?',
            'answer' => implode("\n", [
                '1. Enter a GitHub repository URL.',
                '2. Enter a Personal Access Token with repository read access if required.',
                '3. Choose checks to run, then start analysis.',
                '4. Review score, findings, recommendations, and report details.',
            ]),
        ],
        [
            'id' => 'checks-available',
            'question' => 'What checks are available?',
            'answer' => implode("\n", [
                'Checks include Security, Code Quality, Clean Code, Architecture, Complexity, Testing, Performance, Reliability, Documentation, Dependencies, DevOps, and AI Readiness.',
                'You can select all checks or run only a subset before analysis.',
            ]),
        ],
        [
            'id' => 'what-is-pat',
            'question' => 'What is a PAT?',
            'answer' => implode("\n", [
                'PAT means Personal Access Token.',
                'It allows the app to read repository data from GitHub for analysis.',
                'Use a token with repository read-only scope whenever possible.',
            ]),
        ],
        [
            'id' => 'create-pat',
            'question' => 'How do I create a PAT?',
            'answer' => implode("\n", [
                'Open GitHub settings, then go to Developer settings and Personal access tokens.',
                'Create a token with repository read permissions and copy it once shown.',
                'Paste it into the analyzer when running your scan.',
            ]),
        ],
        [
            'id' => 'score-formula',
            'question' => 'How is the score calculated?',
            'answer' => implode("\n", [
                'The score decreases based on finding severity.',
                'High severity findings reduce score more than Medium, Low, and Info findings.',
                'Improving high-priority findings first usually gives the biggest score recovery.',
            ]),
        ],
        [
            'id' => 'improve-score',
            'question' => 'How can I improve the score?',
            'answer' => implode("\n", [
                'Fix High findings first, then Medium findings.',
                'Prioritize security, architecture, and reliability issues that affect many files.',
                'Re-run the same checks to confirm finding reductions and score improvement.',
            ]),
        ],
        [
            'id' => 'analysis-duration',
            'question' => 'How long does analysis take?',
            'answer' => implode("\n", [
                'Most scans finish in seconds to a few minutes.',
                'Runtime depends on repository size and the number of selected checks.',
            ]),
        ],
        [
            'id' => 'platform-support',
            'question' => 'Does it support GitLab?',
            'answer' => implode("\n", [
                'The current implementation is GitHub-focused.',
                'Use GitHub repository URLs and a valid GitHub token for the best results.',
            ]),
        ],
        [
            'id' => 'contact-support',
            'question' => 'Who should I contact for help?',
            'answer' => implode("\n", [
                'Use the Contact page form for support questions.',
                'Include repository link, selected checks, and any error message to speed up help.',
            ]),
        ],
        [
            'id' => 'support-sla',
            'question' => 'How long does it take to reply?',
            'answer' => implode("\n", [
                'Support replies are typically sent within 1 to 2 business days.',
                'For urgent issues, mention urgency in your message subject.',
            ]),
        ],
    ];
}

function find_faq_by_id(array $faqs, string $id): ?array
{
    foreach ($faqs as $faq) {
        if ((string) ($faq['id'] ?? '') === $id) {
            return $faq;
        }
    }

    return null;
}

function find_faq_by_question(array $faqs, string $question): ?array
{
    $normalizedQuestion = normalize_text($question);

    foreach ($faqs as $faq) {
        if (normalize_text((string) ($faq['question'] ?? '')) === $normalizedQuestion) {
            return $faq;
        }
    }

    foreach ($faqs as $faq) {
        $candidate = normalize_text((string) ($faq['question'] ?? ''));
        if ($normalizedQuestion !== '' && (str_contains($candidate, $normalizedQuestion) || str_contains($normalizedQuestion, $candidate))) {
            return $faq;
        }
    }

    return null;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$faqs = faq_entries();

if ($method === 'GET') {
    json_response([
        'ok' => true,
        'mode' => 'help_center',
        'title' => 'Help Center',
        'subtitle' => 'Choose a question to see the answer.',
        'faqs' => array_map(static function (array $faq): array {
            return [
                'id' => (string) $faq['id'],
                'question' => (string) $faq['question'],
                'answer' => (string) $faq['answer'],
            ];
        }, $faqs),
    ]);
}

if ($method !== 'POST') {
    json_error('Only GET and POST are supported for Help Center.', 405);
}

$raw = file_get_contents('php://input');
if (!is_string($raw) || trim($raw) === '') {
    json_error('Missing request payload.');
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    json_error('Invalid JSON payload.');
}

$questionId = trim((string) ($payload['question_id'] ?? ''));
$question = trim((string) ($payload['question'] ?? ''));

if ($questionId === '' && $question === '') {
    json_error('question_id or question is required.');
}

if (mb_strlen($question) > 1000) {
    json_error('Question is too long. Please keep it under 1000 characters.');
}

if ($questionId !== '') {
    $match = find_faq_by_id($faqs, $questionId);
} else {
    $match = find_faq_by_question($faqs, $question);
}

if ($match === null) {
    json_response([
        'ok' => true,
        'answer' => 'Sorry, we could not find that exact FAQ entry. Please choose one of the suggested questions.',
        'matched' => false,
        'faqs' => array_map(static function (array $faq): array {
            return [
                'id' => (string) $faq['id'],
                'question' => (string) $faq['question'],
            ];
        }, $faqs),
    ]);
}

json_response([
    'ok' => true,
    'matched' => true,
    'question_id' => (string) $match['id'],
    'question' => (string) $match['question'],
    'answer' => (string) $match['answer'],
]);
