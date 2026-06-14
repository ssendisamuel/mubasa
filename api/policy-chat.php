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
$config = file_exists($configPath) ? require $configPath : [];

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

$suggestions = [
    'What does the HR Manual say about promotions?',
    'How does the Strategic Plan support staff growth?',
    'What is FASPU\'s role in salary harmonisation?',
    'What are my grievance rights at MUBS?',
    'How does science pay classification work?',
];

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

function policy_rank_chunks(array $chunks, array $tokens, int $limit = 5): array
{
    $ranked = [];
    foreach ($chunks as $chunk) {
        $score = policy_score($chunk['text'] ?? '', [], $tokens);
        if ($score > 0) {
            $ranked[] = ['score' => $score, 'chunk' => $chunk];
        }
    }
    usort($ranked, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice(array_column($ranked, 'chunk'), 0, $limit);
}

function policy_best_policy(array $policies, array $tokens): ?array
{
    $best = null;
    $bestScore = 0.0;
    foreach ($policies as $policy) {
        $blob = implode(' ', [
            $policy['title'] ?? '',
            $policy['summary'] ?? '',
            $policy['manifestoAlignment'] ?? '',
            implode(' ', $policy['keyTopics'] ?? []),
            implode(' ', $policy['keywords'] ?? []),
        ]);
        $score = policy_score($blob, $policy['keywords'] ?? [], $tokens);
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $policy;
        }
    }
    return $bestScore >= 2 ? $best : null;
}

function policy_build_context(array $policies, array $topChunks, ?array $bestPolicy): string
{
    $parts = [];

    if ($bestPolicy) {
        $parts[] = 'POLICY SUMMARY: ' . ($bestPolicy['title'] ?? '');
        $parts[] = $bestPolicy['summary'] ?? '';
        $parts[] = 'Manifesto alignment: ' . ($bestPolicy['manifestoAlignment'] ?? '');
    }

    foreach ($policies as $policy) {
        $parts[] = 'DOCUMENT: ' . ($policy['title'] ?? '') . ' — ' . ($policy['summary'] ?? '');
    }

    foreach ($topChunks as $chunk) {
        $text = preg_replace('/\s+/', ' ', $chunk['text'] ?? '');
        if (strlen($text) > 900) {
            $text = substr($text, 0, 900) . '…';
        }
        $parts[] = 'HR MANUAL EXCERPT (p.' . ($chunk['page'] ?? '?') . '): ' . $text;
    }

    return implode("\n\n", $parts);
}

function policy_keyword_answer(array $policies, array $chunks, array $tokens, array $suggestions): array
{
    $bestPolicy = policy_best_policy($policies, $tokens);
    $topChunks = policy_rank_chunks($chunks, $tokens, 1);
    $bestChunk = $topChunks[0] ?? null;

    $bestPolicyScore = $bestPolicy ? 2 : 0;
    $bestChunkScore = 0;
    if ($bestChunk) {
        $bestChunkScore = policy_score($bestChunk['text'] ?? '', [], $tokens);
    }

    if ($bestPolicyScore < 2 && $bestChunkScore < 2) {
        return [
            'answer' => "I could not find a precise match for that question in the policy knowledge base. Try asking about promotions, leave, grievances, salary harmonisation, science pay, the Strategic Plan Human Capital pillar, or FASPU collective agreements. You can also browse the Policy Hub section on this page.",
            'source' => 'Policy Assistant',
        ];
    }

    if ($bestPolicy && $bestPolicyScore >= $bestChunkScore) {
        $topics = array_slice($bestPolicy['keyTopics'] ?? [], 0, 4);
        $topicList = $topics ? "\n\nKey areas: " . implode(' · ', $topics) : '';
        return [
            'answer' => ($bestPolicy['summary'] ?? '') . $topicList . "\n\nManifesto alignment: " . ($bestPolicy['manifestoAlignment'] ?? ''),
            'source' => $bestPolicy['title'] ?? 'Policy document',
        ];
    }

    $excerpt = preg_replace('/\s+/', ' ', $bestChunk['text']);
    if (strlen($excerpt) > 520) {
        $excerpt = substr($excerpt, 0, 520) . '…';
    }

    return [
        'answer' => $excerpt . "\n\nThis excerpt is from the MUBS HR Manual (2024). For the full provision, download the HR Manual from the Policy Hub.",
        'source' => ($bestChunk['source'] ?? 'MUBS HR Manual 2024') . ' · p.' . ($bestChunk['page'] ?? '?'),
    ];
}

function policy_call_claude(string $apiKey, string $model, string $context, string $question): ?string
{
    $system = <<<PROMPT
You are the MUBASA Policy Assistant on Ssendi Samuel's campaign website for Deputy Chairperson of the Makerere University Business School Academic Staff Association (MUBASA).

Rules:
- Answer ONLY using the policy context provided below. Do not invent policies or legal provisions.
- Be clear, professional, and concise (2–4 short paragraphs max).
- Cite the source document and HR Manual page number when relevant.
- When helpful, briefly explain how the answer connects to Ssendi Samuel's manifesto pillars (Unity, Welfare, Growth, Sustainability).
- If the context does not contain enough information, say so honestly and suggest what the member should consult or ask MUBASA about.
- Do not provide personal legal advice or case-specific judgments.
PROMPT;

    $userContent = "POLICY CONTEXT:\n\n" . $context . "\n\nMEMBER QUESTION:\n" . $question;

    $payload = json_encode([
        'model' => $model,
        'max_tokens' => 900,
        'system' => $system,
        'messages' => [
            ['role' => 'user', 'content' => $userContent],
        ],
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS => $payload,
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        return null;
    }

    $data = json_decode($response, true);
    $text = $data['content'][0]['text'] ?? null;
    return is_string($text) && trim($text) !== '' ? trim($text) : null;
}

$tokens = policy_tokens($query);
$topChunks = policy_rank_chunks($chunks, $tokens, 5);
$bestPolicy = policy_best_policy($policies, $tokens);
$context = policy_build_context($policies, $topChunks, $bestPolicy);

$apiKey = trim($config['anthropic_api_key'] ?? '');
$model = trim($config['anthropic_model'] ?? 'claude-haiku-4-5-20251001');

if ($apiKey !== '') {
    $aiAnswer = policy_call_claude($apiKey, $model, $context, $query);
    if ($aiAnswer !== null) {
        echo json_encode([
            'ok' => true,
            'answer' => $aiAnswer,
            'source' => 'Policy Assistant · Claude + MUBS documents',
            'ai' => true,
            'suggestions' => $suggestions,
        ]);
        exit;
    }
}

$fallback = policy_keyword_answer($policies, $chunks, $tokens, $suggestions);
echo json_encode([
    'ok' => true,
    'answer' => $fallback['answer'],
    'source' => $fallback['source'],
    'ai' => false,
    'suggestions' => $suggestions,
]);
