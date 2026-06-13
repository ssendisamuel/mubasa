<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Feedback storage is not configured yet. Copy api/config.example.php to api/config.php on the server.']);
    exit;
}

$config = require $configPath;
$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    $input = $_POST;
}

$pillar = trim($input['pillar'] ?? '');
$message = trim($input['message'] ?? '');
$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$rank = trim($input['rank'] ?? '');
$campus = trim($input['campus'] ?? '');

if ($pillar === '' || $message === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Please select a pillar and share your expectations.']);
    exit;
}

if (strlen($message) > 5000) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Message is too long.']);
    exit;
}

try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_name']);
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $pdo->exec("CREATE TABLE IF NOT EXISTS feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NULL,
        email VARCHAR(255) NULL,
        rank_role VARCHAR(100) NULL,
        campus VARCHAR(100) NULL,
        pillar VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare(
        'INSERT INTO feedback (name, email, rank_role, campus, pillar, message) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $name !== '' ? $name : null,
        $email !== '' ? $email : null,
        $rank !== '' ? $rank : null,
        $campus !== '' ? $campus : null,
        $pillar,
        $message,
    ]);

    if (!empty($config['notify_email'])) {
        $subject = 'MUBASA Campaign Feedback — ' . ucfirst($pillar);
        $body = "New member feedback received\n\n"
            . "Pillar: {$pillar}\n"
            . "Name: " . ($name !== '' ? $name : '(anonymous)') . "\n"
            . "Email: " . ($email !== '' ? $email : '(not provided)') . "\n"
            . "Rank: " . ($rank !== '' ? $rank : '(not provided)') . "\n"
            . "Campus: " . ($campus !== '' ? $campus : '(not provided)') . "\n\n"
            . "Message:\n{$message}\n";
        @mail($config['notify_email'], $subject, $body, 'From: noreply@mubasa.ssendi.dev');
    }

    echo json_encode(['ok' => true, 'message' => 'Thank you. Your feedback has been received.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save feedback. Please try again or email sssendi@mubs.ac.ug directly.']);
}
