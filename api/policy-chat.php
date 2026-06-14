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

    $leadership = $knowledge['mubsLeadership'] ?? [];
    if ($leadership !== []) {
        $parts[] = 'MUBS LEADERSHIP: Principal — ' . ($leadership['principal'] ?? '')
            . '. ' . ($leadership['note'] ?? '');
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
    if (preg_match('/\b(today|principal|dean|election|candidate|manifesto|who|what|when|where|why|tell|about|current|news|mubs|mubasa)\b/u', $q)) {
        return false;
    }
    if (preg_match('/^(hi|hello|hey|good\s+(morning|afternoon|evening)|greetings|howdy|thanks|thank\s+you|ok|okay)[!.?\s]*$/u', $q)) {
        return true;
    }
    if (preg_match('/^how\s+are\s+(you|u)(?:\s+doing)?[!.?\s]*$/u', $q)) {
        return true;
    }
    return false;
}

function policy_current_datetime(): string
{
    $now = new DateTime('now', new DateTimeZone('Africa/Kampala'));
    return $now->format('l, j F Y') . ' at ' . $now->format('g:i A') . ' EAT';
}

function assistant_enhance_search_query(string $query): string
{
    $q = trim($query);
    $lower = strtolower($q);
    if (str_contains($lower, 'mubs') || str_contains($lower, 'mubasa') || str_contains($lower, 'makerere')) {
        return $q;
    }
    if (preg_match('/\b(principal|dean|director|campus|business school)\b/i', $q)) {
        return $q . ' Makerere University Business School MUBS Uganda';
    }
    return $q;
}

function assistant_fetch_web_snippets(string $query, int $limit = 5): string
{
    $searchQuery = assistant_enhance_search_query($query);
    $ch = curl_init('https://html.duckduckgo.com/html/');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['q' => $searchQuery]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (compatible; MUBASA-AI/1.0; +https://mubasa.ssendi.dev)',
        ],
    ]);

    $html = curl_exec($ch);
    curl_close($ch);

    if (!is_string($html) || $html === '') {
        return '';
    }

    $snippets = [];
    if (preg_match_all('/class="result__snippet"[^>]*>(.*?)<\/a>/s', $html, $matches)) {
        foreach (array_slice($matches[1], 0, $limit) as $snippet) {
            $text = html_entity_decode(strip_tags($snippet), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = preg_replace('/\s+/', ' ', trim($text));
            if ($text !== '') {
                $snippets[] = $text;
            }
        }
    }

    if (preg_match_all('/class="result__a"[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/s', $html, $links, PREG_SET_ORDER)) {
        foreach (array_slice($links, 0, $limit) as $i => $link) {
            $title = html_entity_decode(strip_tags($link[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $url = html_entity_decode($link[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($title !== '' && !isset($snippets[$i])) {
                $snippets[] = $title . ' — ' . $url;
            }
        }
    }

    return implode("\n\n", array_slice($snippets, 0, $limit));
}

function assistant_should_supplement_web(string $query, string $policyContext): bool
{
    $q = strtolower($query);
    $triggers = [
        'principal', 'dean', 'director', 'who is', "who's", 'current', 'today',
        'news', 'vice', 'chancellor', 'rector', 'manager', 'website', 'latest',
        'when did', 'contact', 'phone', 'email address', 'located', 'location',
    ];
    foreach ($triggers as $trigger) {
        if (str_contains($q, $trigger)) {
            return true;
        }
    }
    return $policyContext === '';
}

function policy_extract_claude_text(array $data): ?string
{
    $parts = [];
    foreach ($data['content'] ?? [] as $block) {
        if (($block['type'] ?? '') === 'text' && !empty($block['text'])) {
            $parts[] = trim($block['text']);
        }
    }

    if ($parts === []) {
        return null;
    }

    return trim(implode("\n\n", $parts));
}

function policy_greeting_kind(string $query): string
{
    $q = strtolower(trim($query));
    if (preg_match('/^how\s+are\s+(you|u)(?:\s+doing)?[!.?\s]*$/u', $q)) {
        return 'how_are_you';
    }
    if (preg_match('/^how\s+(is|are)\s+(the|your)/u', $q)) {
        return 'question';
    }
    if (preg_match('/^(thanks|thank\s+you|thx)[!.?\s]*$/u', $q)) {
        return 'thanks';
    }
    if (preg_match('/^good\s+(morning|afternoon|evening)[!.?\s]*$/u', $q)) {
        return 'time_of_day';
    }
    return 'hello';
}

function policy_greeting_answer(string $query): array
{
    $kind = policy_greeting_kind($query);

    switch ($kind) {
        case 'how_are_you':
            $answer = "I'm doing well, thank you for asking — ready to help whenever you need me.\n\n"
                . "What's on your mind? Elections, manifesto, candidates, or something at MUBS?";
            break;
        case 'thanks':
            $answer = "You're welcome. Feel free to ask anything else.";
            break;
        case 'time_of_day':
            $answer = "Good to hear from you. I'm the MUBASA AI Assistant — happy to help with the June elections, manifesto, or staff matters at MUBS.";
            break;
        case 'hello':
        default:
            $answer = "Hello — good to meet you.\n\n"
                . "I'm the MUBASA AI Assistant. Ask me about the **June 2026 elections**, **candidates**, **manifesto**, or **your rights at MUBS** — whatever you need.";
            break;
    }

    return [
        'answer' => $answer,
        'source' => ASSISTANT_SOURCE,
        'mode' => 'greeting',
    ];
}

function policy_call_claude_greeting(string $apiKey, string $model, string $question): array
{
    $today = policy_current_datetime();
    $kind = policy_greeting_kind($question);

    $kindGuide = match ($kind) {
        'how_are_you' => 'They asked how you are. Answer that question naturally first (you are an AI assistant doing well and ready to help). Then one short line inviting their question. Do NOT repeat a long welcome paragraph.',
        'thanks' => 'They said thanks. Reply briefly and warmly.',
        'time_of_day' => 'They gave a time-of-day greeting. Mirror it briefly, then one line about being the MUBASA AI Assistant.',
        default => 'They said hello. Greet them back in one or two short sentences. Mention you help MUBASA members. Do NOT list every topic you cover.',
    };

    $system = <<<PROMPT
You are the MUBASA AI Assistant for Makerere University Business School Academic Staff Association members.

CURRENT DATE AND TIME: {$today}

This is casual small talk — NOT a substantive question.

{$kindGuide}

Rules:
- Reply in 1–3 short sentences only (4 max for "how are you").
- Sound human and warm, not like a brochure.
- Do NOT paste the same welcome block every time. Vary your wording.
- Do NOT use bullet lists for greetings.
- Do NOT mention searching the web or your knowledge base.
- Use **bold** sparingly if at all. No emoji unless it feels natural (prefer none).
- You may briefly note it is June 2026 and MUBASA executive elections are underway — only if it fits naturally, one clause max.
PROMPT;

    $payload = json_encode([
        'model' => $model,
        'max_tokens' => 220,
        'system' => $system,
        'messages' => [
            ['role' => 'user', 'content' => 'Member message: ' . $question],
        ],
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
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
    $text = policy_extract_claude_text($data);

    return ['text' => is_string($text) && trim($text) !== '' ? trim($text) : null, 'error' => null];
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
    string $webContext,
    string $question,
    bool $isGreeting = false,
    bool $enableWebSearch = true
): array {
    $today = policy_current_datetime();
    $system = <<<PROMPT
You are the MUBASA AI Assistant on Ssendi Samuel's campaign website. He is running for Deputy Chairperson of MUBASA (Makerere University Business School Academic Staff Association) for 2026–2028.

CURRENT DATE AND TIME: {$today}

You have built-in knowledge about MUBASA, MUBS, members, the June 2026 executive election roadmap, nominated candidates, and Ssendi Samuel's manifesto (Unity, Welfare, Growth, Sustainability).

Rules:
- Be conversational, warm, and genuinely helpful to MUBASA members.
- Answer directly and intelligently. Never tell the user to "check the website" or that you lack information when you can look it up or infer an answer.
- For current facts (MUBS leadership, today's date, news, contacts, people, roles), use web search proactively. Do not mention searching the web or your knowledge base — just answer naturally.
- For "today" questions: use the current date above and, where relevant, connect to the June 2026 MUBASA election timeline.
- Use MUBASA CORE KNOWLEDGE for elections, candidates, and manifesto.
- Use POLICY CONTEXT for HR Manual and policy questions. Do not invent policy provisions.
- When discussing Deputy Chairperson, acknowledge both candidates but advocate clearly for Ssendi Samuel on this campaign site.
- Format with **bold** for emphasis and lines starting with "- " for bullet lists. These render in the chat UI.
- Keep responses focused (2–4 short paragraphs unless listing dates or candidates).
- Avoid excessive emoji. Do not give personal legal advice.
PROMPT;

    $userContent = "MUBASA CORE KNOWLEDGE:\n\n" . $coreContext;

    if ($policyContext !== '') {
        $userContent .= "\n\nPOLICY CONTEXT:\n\n" . $policyContext;
    }

    if ($webContext !== '') {
        $userContent .= "\n\nSUPPLEMENTARY WEB RESULTS:\n\n" . $webContext;
    }

    $userContent .= $isGreeting
        ? "\n\nThe member said: " . $question . "\n\nRespond with a friendly, concise greeting and invite a question."
        : "\n\nMEMBER QUESTION:\n" . $question;

    $payload = [
        'model' => $model,
        'max_tokens' => 1024,
        'system' => $system,
        'messages' => [
            ['role' => 'user', 'content' => $userContent],
        ],
    ];

    if ($enableWebSearch && !$isGreeting) {
        $payload['tools'] = [[
            'type' => 'web_search_20250305',
            'name' => 'web_search',
            'max_uses' => 3,
            'user_location' => [
                'type' => 'approximate',
                'city' => 'Kampala',
                'region' => 'Central Region',
                'country' => 'UG',
                'timezone' => 'Africa/Kampala',
            ],
        ]];
    }

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        return ['text' => null, 'error' => 'API status ' . $status];
    }

    $data = json_decode($response, true);
    $text = policy_extract_claude_text($data);
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
$coreContext .= "\n\nTODAY: " . policy_current_datetime();

if (policy_is_greeting($query)) {
    $apiKey = policy_resolve_api_key($config);
    $model = trim($config['anthropic_model'] ?? 'claude-3-5-haiku-latest');

    if ($apiKey !== '') {
        $claude = policy_call_claude_greeting($apiKey, $model, $query);
        if ($claude['text'] !== null) {
            echo json_encode([
                'ok' => true,
                'answer' => $claude['text'],
                'source' => ASSISTANT_SOURCE,
                'ai' => true,
                'mode' => 'greeting',
                'suggestions' => $suggestions,
            ]);
            exit;
        }
    }

    $greeting = policy_greeting_answer($query);
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
$webContext = assistant_should_supplement_web($query, $policyContext)
    ? assistant_fetch_web_snippets($query)
    : '';

$apiKey = policy_resolve_api_key($config);
$models = array_values(array_unique(array_filter([
    trim($config['anthropic_model'] ?? ''),
    'claude-3-5-haiku-latest',
    'claude-haiku-4-5-20251001',
])));

if ($apiKey !== '') {
    foreach ($models as $model) {
        $claude = policy_call_claude(
            $apiKey,
            $model,
            $coreContext,
            $policyContext,
            $webContext,
            $query,
            false,
            true
        );
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

        if ($webContext === '') {
            $webContext = assistant_fetch_web_snippets($query);
        }
        $claude = policy_call_claude(
            $apiKey,
            $model,
            $coreContext,
            $policyContext,
            $webContext,
            $query,
            false,
            false
        );
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
