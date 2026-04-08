<?php
/**
 * 文樞 — 測驗題目生成 API
 *
 * POST JSON: { "level_id": 1, "title": "...", "content": "..." }
 * 回應: { "success": true, "questions": [...] }
 *
 * questions 格式：
 * [
 *   { "question": "...", "options": ["A", "B", "C", "D"], "answer": "A" },
 *   ...
 * ]
 *
 * 生成後快取在 PHP session 內（同關卡、同用戶，避免重複呼叫 AI）
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => '方法不允許'], 405);
}

$body    = json_decode(file_get_contents('php://input'), true) ?: [];
$levelId = (int)($body['level_id'] ?? 0);
$title   = trim($body['title']     ?? '');
$content = trim($body['content']   ?? '');

if (empty($content)) {
    jsonResponse(['success' => false, 'message' => '缺少文章內容'], 400);
}

// ── Session 快取（同一請求週期不重複呼叫 AI）───────────────────────────────
$cacheKey = 'quiz_questions_' . $levelId;
if ($levelId > 0 && !empty($_SESSION[$cacheKey])) {
    jsonResponse(['success' => true, 'questions' => $_SESSION[$cacheKey], 'cached' => true]);
}

// ── 呼叫 AI 生成題目 ────────────────────────────────────────────────────────
$prompt    = buildQuizPrompt($title, $content);
$rawResult = callAiForQuiz([['role' => 'user', 'content' => $prompt]]);

if ($rawResult === null) {
    jsonResponse(['success' => false, 'message' => 'AI 服務暫時不可用，請稍後再試'], 503);
}

$questions = parseQuizJson($rawResult);
if (empty($questions)) {
    jsonResponse(['success' => false, 'message' => '題目解析失敗，請重試'], 500);
}

// 快取至 Session
if ($levelId > 0) {
    $_SESSION[$cacheKey] = $questions;
}

jsonResponse(['success' => true, 'questions' => $questions, 'cached' => false]);

// ════════════════════════════════════════════════════════════════════════════

function buildQuizPrompt(string $title, string $content): string
{
    $t = $title ? "《{$title}》" : '以下文言文';
    return "根據{$t}，生成 5 道文言文理解選擇題，用於測試學生的閱讀理解能力。

文章內容：
{$content}

請嚴格按照以下 JSON 格式輸出，只輸出 JSON，不要任何解釋或前言：

[
  {
    \"question\": \"題目內容\",
    \"options\": [\"A. 選項一\", \"B. 選項二\", \"C. 選項三\", \"D. 選項四\"],
    \"answer\": \"A. 選項一\"
  }
]

要求：
1. 每題必須有 4 個選項（A/B/C/D）
2. answer 必須完全等於 options 中的某一項
3. 題目考核字詞含義、句子理解、文章大意等不同面向
4. 難度適中，適合中學生
5. 使用繁體中文
6. 只輸出 JSON 陣列，不要 markdown code block";
}

/**
 * 解析 AI 返回的 JSON 字串，提取題目陣列
 */
function parseQuizJson(string $raw): array
{
    // 清除 think 標籤
    $raw = preg_replace('/<think>.*?<\/think>/s', '', $raw);
    $raw = trim($raw);

    // 移除可能的 markdown code block
    $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
    $raw = preg_replace('/\s*```$/i', '', $raw);
    $raw = trim($raw);

    // 嘗試直接解析
    $data = json_decode($raw, true);
    if (is_array($data) && !empty($data)) {
        return validateQuestions($data);
    }

    // 嘗試從原始字串中提取 JSON 陣列
    if (preg_match('/\[[\s\S]*\]/u', $raw, $matches)) {
        $data = json_decode($matches[0], true);
        if (is_array($data) && !empty($data)) {
            return validateQuestions($data);
        }
    }

    return [];
}

/**
 * 驗證並清理題目資料
 */
function validateQuestions(array $data): array
{
    $valid = [];
    foreach ($data as $q) {
        if (!is_array($q)) continue;
        if (empty($q['question']) || empty($q['options']) || empty($q['answer'])) continue;
        if (!is_array($q['options']) || count($q['options']) < 2) continue;
        if (!in_array($q['answer'], $q['options'])) {
            // 嘗試模糊比對
            foreach ($q['options'] as $opt) {
                if (strpos($opt, $q['answer']) !== false || strpos($q['answer'], $opt) !== false) {
                    $q['answer'] = $opt;
                    break;
                }
            }
        }
        $valid[] = [
            'question' => (string)$q['question'],
            'options'  => array_values(array_map('strval', $q['options'])),
            'answer'   => (string)$q['answer'],
        ];
    }
    return $valid;
}

function callAiForQuiz(array $messages): ?string
{
    foreach (AI_API_KEYS as $key) {
        foreach (AI_MODELS as $model) {
            $payload = json_encode([
                'model'       => $model,
                'messages'    => $messages,
                'max_tokens'  => 2048,
                'temperature' => 0.4,
            ], JSON_UNESCAPED_UNICODE);

            $ch = curl_init(AI_API_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $key,
                ],
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data['choices'][0]['message']['content'])) {
                    return $data['choices'][0]['message']['content'];
                }
            }
        }
    }
    return null;
}
