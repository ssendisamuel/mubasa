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
    echo json_encode(['ok' => false, 'error' => 'Please ask a question about MUBASA, the election, manifesto, candidates, or MUBS policies.']);
    exit;
}

const ASSISTANT_SOURCE = 'AI Model Developed and Trained by Ssendi';

$dataDir = dirname(__DIR__) . '/data';
$indexPath = $dataDir . '/policies-index.json';
$chunksPath = $dataDir . '/hr-manual-chunks.json';
$knowledgePath = $dataDir . '/mubasa-assistant-knowledge.json';

$policies = file_exists($indexPath)
    ? json_decode(file_get_contents($indexPath), true)
    : [];
$chunks = file_exists($chunksPath)
    ? json_decode(file_get_contents($chunksPath), true)
    : [];
$knowledge = file_exists($knowledgePath)
    ? json_decode(file_get_contents($knowledgePath), true)
    : [];

$suggestions = $knowledge['suggestedQuestions'] ?? [
    'What is Ssendi Samuel\'s manifesto for Deputy Chairperson?',
    'When is MUBASA voting in 2026?',
    'Who are the Deputy Chairperson candidates?',
    'What does the HR Manual say about promotions?',
    'What is FASPU\'s role in salary harmonisation?',
];

function assistant_build_core_context(array $knowledge): string
{
    if ($knowledge === []) {
        return '';
    }

    $parts = [];

    $parts[] = 'ABOUT MUBASA: ' . ($knowledge['aboutMubasa'] ?? '');
    $parts[] = 'ABOUT MUBS: ' . ($knowledge['aboutMubs'] ?? '');

    $candidate = $knowledge['campaignCandidate'] ?? [];
    if ($candidate !== []) {
        $parts[] = 'CAMPAIGN CANDIDATE: ' . ($candidate['name'] ?? '') . ' for ' . ($candidate['position'] ?? '')
            . ' (' . ($candidate['term'] ?? '') . '). Slogan: ' . ($candidate['slogan'] ?? '')
            . '. ' . ($candidate['summary'] ?? '');
    }

    foreach ($knowledge['manifestoPillars'] ?? [] as $pillar) {
        $lines = [($pillar['title'] ?? '') . ' — ' . ($pillar['tagline'] ?? '')];
        if (!empty($pillar['intro'])) {
            $lines[] = $pillar['intro'];
        }
        foreach ($pillar['commitments'] ?? [] as $commitment) {
            $lines[] = '- ' . $commitment;
        }
        $parts[] = 'MANIFESTO PILLAR ' . ($pillar['id'] ?? '') . ":\n" . implode("\n", $lines);
    }

    if (!empty($knowledge['manifestoCommitment'])) {
        $parts[] = 'MANIFESTO COMMITMENT: ' . $knowledge['manifestoCommitment'];
    }

    $roadmapLines = ['MUBASA EXECUTIVE ELECTIONS 2025/2026 ROADMAP:'];
    foreach ($knowledge['electionRoadmap'] ?? [] as $step) {
        $roadmapLines[] = ($step['step'] ?? '') . '. ' . ($step['activity'] ?? '') . ' — ' . ($step['dates'] ?? '');
    }
    $parts[] = implode("\n", $roadmapLines);

    $contestedLines = ['CONTESTED POSITIONS (Returning Officer declaration):'];
    foreach ($knowledge['contestedCandidates'] ?? [] as $position => $names) {
        $contestedLines[] = $position . ': ' . implode('; ', (array) $names);
    }
    $parts[] = implode("\n", $contestedLines);

    $unopposedLines = ['UNOPPOSED CANDIDATES:'];
    foreach ($knowledge['unopposedCandidates'] ?? [] as $entry) {
        $unopposedLines[] = ($entry['position'] ?? '') . ': ' . ($entry['name'] ?? '');
    }
    $parts[] = implode("\n", $unopposedLines);

    if (!empty($knowledge['returningOfficer'])) {
        $parts[] = 'RETURNING OFFICER: ' . $knowledge['returningOfficer'];
    }

    return implode("\n\n", $parts);
}

function assistant_keyword_answer(array $knowledge, array $tokens): ?array
{
    if ($knowledge === [] || $tokens === []) {
        return null;
    }

    $blob = strtolower(assistant_build_core_context($knowledge));

    $electionTerms = ['election', 'vote', 'voting', 'campaign', 'debate', 'nomination', 'handover', 'roadmap'];
    $candidateTerms = ['candidate', 'candidates', 'contest', 'nominated', 'chairperson', 'treasurer', 'deputy'];
    $manifestoTerms = ['manifesto', 'pillar', 'unity', 'welfare', 'growth', 'sustainability', 'ssendi', 'commitment'];
    $mubasaTerms = ['mubasa', 'member', 'members', 'association', 'staff'];

    $score = 0.0;
    foreach ($tokens as $token) {
        if (policy_word_match($blob, $token)) {
            $score += 1.5;
        }
        if (in_array($token, $electionTerms, true)) {
            $score += 3.0;
        }
        if (in_array($token, $candidateTerms, true)) {
            $score += 3.0;
        }
        if (in_array($token, $manifestoTerms, true)) {
            $score += 3.0;
        }
        if (in_array($token, $mubasaTerms, true)) {
            $score += 2.0;
        }
    }

    if ($score < 3) {
        return null;
    }

    $candidate = $knowledge['campaignCandidate'] ?? [];
    $roadmap = $knowledge['electionRoadmap'] ?? [];
    $roadmapText = '';
    foreach ($roadmap as $step) {
        $roadmapText .= ($step['activity'] ?? '') . ' (' . ($step['dates'] ?? '') . ")\n";
    }

    $deputy = $knowledge['contestedCandidates']['Deputy Chairperson'] ?? [];
    $deputyList = is_array($deputy) ? implode(' and ', $deputy) : '';

    return [
        'answer' => "MUBASA is the Makerere University Business School Academic Staff Association.\n\n"
            . ($candidate['name'] ?? 'Ssendi Samuel') . ' is running for ' . ($candidate['position'] ?? 'Deputy Chairperson')
            . ' with the slogan "' . ($candidate['slogan'] ?? 'Results, No Rhetoric') . '". '
            . "His manifesto has four pillars: Unity, Welfare, Growth, and Sustainability.\n\n"
            . "2026 election roadmap:\n" . trim($roadmapText) . "\n\n"
            . 'Deputy Chairperson candidates: ' . $deputyList . '. '
            . 'For full manifesto details and policy answers, configure the AI assistant on the server.',
        'source' => ASSISTANT_SOURCE . ' · MUBASA knowledge base',
        'mode' => 'knowledge',
    ];
}

function policy_tokens(string $text): array
{
    $text = strtolower(preg_replace('/[^a-z0-9\s]/', ' ', $text));
    $parts = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $stop = ['the', 'and', 'for', 'what', 'how', 'does', 'about', 'with', 'from', 'that', 'this', 'are', 'can', 'you', 'mubs', 'tell', 'explain', 'hello', 'hi', 'hey', 'your'];
    return array_values(array_filter($parts, fn($w) => strlen($w) > 2 && !in_array($w, $stop, true)));
}

function policy_word_match(string $haystack, string $term): bool
{
    $term = strtolower(trim($term));
    if ($term === '') {
        return false;
    }
    return (bool) preg_match('/\b' . preg_quote($term, '/') . '\b/i', $haystack);
}

function policy_score(string $haystack, array $keywords, array $tokens): float
{
    $score = 0.0;
    foreach ($tokens as $token) {
        if (policy_word_match($haystack, $token)) {
            $score += 2.0;
        }
    }
    foreach ($keywords as $keyword) {
        if (policy_word_match($haystack, $keyword)) {
            $score += 4.0;
        }
    }
    return $score;
}

function policy_is_greeting(string $query): bool
{
    $q = strtolower(trim($query));
    if (preg_match('/^(hi|hello|hey|good\s+(morning|afternoon|evening)|greetings|howdy|thanks|thank\s+you|ok|okay)[!.?\s]*$/u', $q)) {
        return true;
    }
    return (bool) preg_match('/^how\s+are\s+you[!.?\s]*$/u', $q);
}

function policy_greeting_answer(): array
{
    return [
        'answer' => "Hello! I am the MUBASA AI Assistant. I know about MUBASA and MUBS, the 2026 executive election roadmap, nominated candidates, Ssendi Samuel's manifesto, and MUBS policy documents.\n\nAsk about the election dates, candidates, manifesto pillars, promotions, leave, science pay, or staff welfare.",
        'source' => ASSISTANT_SOURCE,
        'mode' => 'greeting',
    ];
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
        $parts[] = 'PRIMARY POLICY: ' . ($bestPolicy['title'] ?? '');
        $parts[] = $bestPolicy['summary'] ?? '';
        $parts[] = 'Manifesto alignment: ' . ($bestPolicy['manifestoAlignment'] ?? '');
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

function policy_keyword_answer(array $policies, array $chunks, array $tokens): array
{
    $bestPolicy = policy_best_policy($policies, $tokens);
    $topChunks = policy_rank_chunks($chunks, $tokens, 1);
    $bestChunk = $topChunks[0] ?? null;
    $bestChunkScore = $bestChunk ? policy_score($bestChunk['text'] ?? '', [], $tokens) : 0.0;

    if (!$bestPolicy && $bestChunkScore < 2) {
        return [
            'answer' => "I could not find a precise match in the policy documents. Try a specific question about promotions, leave, grievances, salary harmonisation, science pay, the Strategic Plan, or FASPU agreements.",
            'source' => ASSISTANT_SOURCE . ' · document search',
            'mode' => 'search',
        ];
    }

    if ($bestPolicy && (!$bestChunk || policy_score(implode(' ', [
        $bestPolicy['title'] ?? '',
        $bestPolicy['summary'] ?? '',
    ]), $bestPolicy['keywords'] ?? [], $tokens) >= $bestChunkScore)) {
        $topics = array_slice($bestPolicy['keyTopics'] ?? [], 0, 4);
        $topicList = $topics ? "\n\nKey areas: " . implode(' · ', $topics) : '';
        return [
            'answer' => ($bestPolicy['summary'] ?? '') . $topicList . "\n\nManifesto alignment: " . ($bestPolicy['manifestoAlignment'] ?? ''),
            'source' => ($bestPolicy['title'] ?? 'Policy document') . ' · ' . ASSISTANT_SOURCE,
            'mode' => 'search',
        ];
    }

    $excerpt = preg_replace('/\s+/', ' ', $bestChunk['text']);
    if (strlen($excerpt) > 520) {
        $excerpt = substr($excerpt, 0, 520) . '…';
    }

    return [
        'answer' => $excerpt . "\n\nThis excerpt is from the MUBS HR Manual (2024). Download the full manual from the Policy Hub for complete details.",
        'source' => 'MUBS HR Manual 2024 · p.' . ($bestChunk['page'] ?? '?') . ' · ' . ASSISTANT_SOURCE,
        'mode' => 'search',
    ];
}

function policy_call_claude(
    string $apiKey,
    string $model,
    string $coreContext,
    string $policyContext,
    string $question,
    bool $isGreeting = false
): array {
    $system = <<<PROMPT
You are the MUBASA AI Assistant on Ssendi Samuel's campaign website. He is running for Deputy Chairperson of MUBASA (Makerere University Business School Academic Staff Association) for 2026–2028.

You have built-in knowledge about MUBASA, MUBS, who members are, the 2026 executive election roadmap, all nominated candidates (contested and unopposed), and Ssendi Samuel's full manifesto (Unity, Welfare, Growth, Sustainability).

Rules:
- Be conversational, warm, and professional.
- For greetings or small talk, reply briefly. Do NOT dump long text for a simple hello. Mention you can help with MUBASA, elections, candidates, manifesto, and policies.
- Use MUBASA CORE KNOWLEDGE as your primary source for association, election, candidate, and manifesto questions.
- For policy questions, use POLICY CONTEXT too. Do not invent provisions not supported by the context.
- When discussing Deputy Chairperson, acknowledge both candidates but advocate clearly for Ssendi Samuel — his manifesto, qualifications, and delivery-focused approach. This is his campaign site.
- Be concise (2–4 short paragraphs max).
- Cite sources when relevant (manifesto pillar, Returning Officer declaration, HR Manual page).
- If context is insufficient, say so honestly.
- Do not give personal legal advice.
PROMPT;

    $userContent = "MUBASA CORE KNOWLEDGE:\n\n" . $coreContext;

    if ($policyContext !== '') {
        $userContent .= "\n\nPOLICY CONTEXT:\n\n" . $policyContext;
    }

    $userContent .= $isGreeting
        ? "\n\nThe member said: " . $question . "\n\nRespond with a friendly greeting and invite them to ask about MUBASA, the election, manifesto, candidates, or policies."
        : "\n\nMEMBER QUESTION:\n" . $question;

    $payload = json_encode([
        'model' => $model,
        'max_tokens' => 700,
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
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        return ['text' => null, 'error' => 'API status ' . $status];
    }

    $data = json_decode($response, true);
    $text = $data['content'][0]['text'] ?? null;
    if (!is_string($text) || trim($text) === '') {
        return ['text' => null, 'error' => 'Empty response'];
    }

    return ['text' => trim($text), 'error' => null];
}

function policy_resolve_api_key(array $config): string
{
    $key = trim($config['anthropic_api_key'] ?? '');
    if ($key === '' || str_contains($key, 'YOUR_')) {
        return '';
    }
    return $key;
}

$coreContext = assistant_build_core_context($knowledge);

if (policy_is_greeting($query)) {
    $apiKey = policy_resolve_api_key($config);
    $model = trim($config['anthropic_model'] ?? 'claude-3-5-haiku-latest');

    if ($apiKey !== '') {
        $claude = policy_call_claude($apiKey, $model, $coreContext, '', $query, true);
        if ($claude['text'] !== null) {
            echo json_encode([
                'ok' => true,
                'answer' => $claude['text'],
                'source' => ASSISTANT_SOURCE,
                'ai' => true,
                'mode' => 'ai',
                'suggestions' => $suggestions,
            ]);
            exit;
        }
    }

    $greeting = policy_greeting_answer();
    echo json_encode([
        'ok' => true,
        'answer' => $greeting['answer'],
        'source' => $greeting['source'],
        'ai' => false,
        'mode' => 'greeting',
        'suggestions' => $suggestions,
    ]);
    exit;
}

$tokens = policy_tokens($query);
$topChunks = policy_rank_chunks($chunks, $tokens, 5);
$bestPolicy = policy_best_policy($policies, $tokens);
$policyContext = policy_build_context($policies, $topChunks, $bestPolicy);

$apiKey = policy_resolve_api_key($config);
$models = array_values(array_unique(array_filter([
    trim($config['anthropic_model'] ?? ''),
    'claude-3-5-haiku-latest',
    'claude-haiku-4-5-20251001',
])));

if ($apiKey !== '') {
    foreach ($models as $model) {
        $claude = policy_call_claude($apiKey, $model, $coreContext, $policyContext, $query, false);
        if ($claude['text'] !== null) {
            echo json_encode([
                'ok' => true,
                'answer' => $claude['text'],
                'source' => ASSISTANT_SOURCE,
                'ai' => true,
                'mode' => 'ai',
                'suggestions' => $suggestions,
            ]);
            exit;
        }
    }
}

$knowledgeFallback = assistant_keyword_answer($knowledge, $tokens);
if ($knowledgeFallback !== null) {
    echo json_encode([
        'ok' => true,
        'answer' => $knowledgeFallback['answer'],
        'source' => $knowledgeFallback['source'],
        'ai' => false,
        'mode' => $knowledgeFallback['mode'],
        'suggestions' => $suggestions,
    ]);
    exit;
}

$fallback = policy_keyword_answer($policies, $chunks, $tokens);
echo json_encode([
    'ok' => true,
    'answer' => $fallback['answer'],
    'source' => $apiKey === ''
        ? $fallback['source'] . ' · AI not configured'
        : $fallback['source'],
    'ai' => false,
    'mode' => $fallback['mode'],
    'suggestions' => $suggestions,
]);
