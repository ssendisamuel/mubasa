<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$configPath = __DIR__ . '/config.php';
$config = file_exists($configPath) ? require $configPath : [];
$key = trim($config['anthropic_api_key'] ?? '');

echo json_encode([
    'ok' => true,
    'config_file_exists' => file_exists($configPath),
    'claude_configured' => $key !== '' && !str_contains($key, 'YOUR_'),
    'model' => trim($config['anthropic_model'] ?? '') ?: 'claude-3-5-haiku-latest',
    'php_curl' => function_exists('curl_init'),
    'hr_manual_indexed' => file_exists(dirname(__DIR__) . '/data/hr-manual-chunks.json'),
    'hint' => 'Send a chat message and check the JSON response for "ai": true and source "Claude AI".',
]);
