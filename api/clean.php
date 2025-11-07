<?php

declare(strict_types=1);

require_once __DIR__ . '/TextLinter.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *'); // adjust in production
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Max-Age: 600');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!extension_loaded('mbstring') || !extension_loaded('intl')) {
    http_response_code(500);
    echo json_encode(['error' => 'Required PHP extensions missing (mbstring + intl)']);
    exit;
}

$rawBody = file_get_contents('php://input') ?: '';
if (strlen($rawBody) > TextLinter::MAX_INPUT_SIZE) {
    http_response_code(413);
    echo json_encode(['error' => 'Payload too large (limit 1MB)']);
    exit;
}

try {
    $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

if (!is_array($payload) || !array_key_exists('text', $payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing text field']);
    exit;
}

$text = (string) $payload['text'];
$mode = isset($payload['mode']) ? (string) $payload['mode'] : 'safe';

if ($text === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Input text cannot be empty']);
    exit;
}

$byteLength = strlen($text);
if ($byteLength > TextLinter::MAX_INPUT_SIZE) {
    http_response_code(413);
    echo json_encode(['error' => 'Input exceeds maximum size of 1MB']);
    exit;
}

if (!in_array($mode, ['safe', 'aggressive', 'strict'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid mode. Use safe, aggressive, or strict']);
    exit;
}

try {
    $result = TextLinter::clean($text, $mode);

    $unicodeVersion = class_exists('IntlChar')
        ? IntlChar::UNICODE_VERSION
        : null;

    $response = [
        'text' => $result['text'],
        'stats' => $result['stats'],
        'server' => [
            'version' => TextLinter::VERSION,
            'unicode_version' => $unicodeVersion,
            'extensions' => [
                'intl' => extension_loaded('intl'),
                'mbstring' => extension_loaded('mbstring'),
                'spoofchecker' => class_exists('Spoofchecker'),
            ],
        ],
    ];

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal processing error',
        'details' => $e->getMessage(),
    ]);
}
