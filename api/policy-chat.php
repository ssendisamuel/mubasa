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

$input = json_decode(file_get_contents('php://input'), true);
$query = trim($input['message'] ?? '');

if ($query === '' || strlen($query) > 500) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Please ask a question about MUBS policies or the manifesto.']);
    exit;
}

$dataDir = dirname(__DIR__) . '/data';
$indexPath = $dataDir . '/policies-index.json';
$chunksPath = $dataDir . '/hr-manual-chunks.json';

$policies = file_exists($indexPath)
    ? json_decode(file_get_contents($indexPath), true)
    : [];
$chunks = file_exists($chunksPath)
    ? json_decode(file_get_contents($chunksPath), true)
    : [];

function policy_tokens(string $text): array
{
    $text = strtolower(preg_replace('/[^a-z0-9\s]/', ' ', $text));
    $parts = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $stop = ['the', 'and', 'for', 'what', 'how', 'does', 'about', 'with', 'from', 'that', 'this', 'are', 'can', 'you', 'mubs', 'tell', 'explain'];
    return array_values(array_filter($parts, fn($w) => strlen($w) > 2 && !in_array($w, $stop, true)));
}

function policy_score(string $haystack, array $keywords, array $tokens): float
{
    $haystack = strtolower($haystack);
    $score = 0.0;
    foreach ($tokens as $token) {
        if (str_contains($haystack, $token)) {
            $score += 2.0;
        }
    }
    foreach ($keywords as $keyword) {
        $keyword = strtolower($keyword);
        if ($keyword !== '' && str_contains($haystack, $keyword)) {
            $score += 4.0;
        }
    }
    return $score;
}

$tokens = policy_tokens($query);
$bestPolicy = null;
$bestPolicyScore = 0.0;

foreach ($policies as $policy) {
    $blob = implode(' ', [
        $policy['title'] ?? '',
        $policy['summary'] ?? '',
        $policy['manifestoAlignment'] ?? '',
        implode(' ', $policy['keyTopics'] ?? []),
        implode(' ', $policy['keywords'] ?? []),
    ]);
    $score = policy_score($blob, $policy['keywords'] ?? [], $tokens);
    if ($score > $bestPolicyScore) {
        $bestPolicyScore = $score;
        $bestPolicy = $policy;
    }
}

$bestChunk = null;
$bestChunkScore = 0.0;

foreach ($chunks as $chunk) {
    $score = policy_score($chunk['text'] ?? '', [], $tokens);
    if ($score > $bestChunkScore) {
        $bestChunkScore = $score;
        $bestChunk = $chunk;
    }
}

$suggestions = [
    'What does the HR Manual say about promotions?',
    'How does the Strategic Plan support staff growth?',
    'What is FASPU\'s role in salary harmonisation?',
    'What are my grievance rights at MUBS?',
    'How does science pay classification work?',
];

if ($bestPolicyScore < 2 && $bestChunkScore < 2) {
    echo json_encode([
        'ok' => true,
        'answer' => "I could not find a precise match for that question in the policy knowledge base. Try asking about promotions, leave, grievances, salary harmonisation, science pay, the Strategic Plan Human Capital pillar, or FASPU collective agreements. You can also browse the Policy Hub section on this page.",
        'source' => 'Policy Assistant',
        'suggestions' => $suggestions,
    ]);
    exit;
}

if ($bestPolicyScore >= $bestChunkScore && $bestPolicy) {
    $topics = array_slice($bestPolicy['keyTopics'] ?? [], 0, 4);
    $topicList = $topics ? "\n\nKey areas: " . implode(' · ', $topics) : '';
    $alignment = $bestPolicy['manifestoAlignment'] ?? '';
    $download = !empty($bestPolicy['download'])
        ? "\n\nDownload: " . $bestPolicy['download']
        : (!empty($bestPolicy['externalLink']) ? "\n\nLearn more: " . $bestPolicy['externalLink'] : '');

    echo json_encode([
        'ok' => true,
        'answer' => ($bestPolicy['summary'] ?? '') . $topicList . "\n\nManifesto alignment: " . $alignment . $download,
        'source' => $bestPolicy['title'] ?? 'Policy document',
        'policyId' => $bestPolicy['id'] ?? null,
        'suggestions' => $suggestions,
    ]);
    exit;
}

$excerpt = preg_replace('/\s+/', ' ', $bestChunk['text']);
if (strlen($excerpt) > 520) {
    $excerpt = substr($excerpt, 0, 520) . '…';
}

echo json_encode([
    'ok' => true,
    'answer' => $excerpt . "\n\nThis excerpt is from the MUBS HR Manual (2024). For the full provision, download the HR Manual from the Policy Hub. Ask me about manifesto alignment for how MUBASA will advocate on this issue.",
    'source' => ($bestChunk['source'] ?? 'MUBS HR Manual 2024') . ' · p.' . ($bestChunk['page'] ?? '?'),
    'page' => $bestChunk['page'] ?? null,
    'suggestions' => $suggestions,
]);
