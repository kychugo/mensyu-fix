<?php
/**
 * 古人動態自動生成 API
 *
 * 觸發方式：
 *  1. 偽定時觸發：由 social/index.php 每次載入時靜默呼叫（非同步，不阻塞頁面）
 *  2. 管理後台手動觸發：管理員點擊「立即生成動態」按鈕
 *
 * 請求方式：POST（必須已登入，手動觸發時需管理員角色）
 * 請求參數（JSON）：
 *   { "tutor_id": 1, "mode": "auto" }   ← 偽定時觸發（任何已登入用戶）
 *   { "tutor_id": 1, "mode": "manual" } ← 手動觸發（僅限管理員）
 *
 * 回應：
 *   { "success": true,  "post_id": 123, "content": "..." }
 *   { "success": false, "message": "..." }
 *
 * FIFO 邏輯：
 *   插入新動態前，若 social_posts 總數 >= max_posts（預設80），
 *   刪除最舊一篇（及其所有留言，透過 ON DELETE CASCADE）。
 *
 * 偽定時邏輯：
 *   從 settings 表讀取 last_post_{tutor_id}。
 *   若距上次生成 < post_interval_minutes 分鐘，跳過（返回 skipped: true）。
 *   生成成功後更新 settings 表中的時間戳。
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// 必須已登入
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => '方法不允許'], 405);
}

$body    = json_decode(file_get_contents('php://input'), true);
$tutorId = isset($body['tutor_id']) ? (int)$body['tutor_id'] : 0;
$mode    = $body['mode'] ?? 'auto'; // 'auto' 或 'manual'

// 手動觸發僅限管理員
if ($mode === 'manual' && !isAdmin()) {
    jsonResponse(['success' => false, 'message' => '權限不足'], 403);
}

if ($tutorId <= 0) {
    jsonResponse(['success' => false, 'message' => '缺少有效的 tutor_id'], 400);
}

// ── 讀取導師資料 ──────────────────────────────────────────────────────────
$db = getDB();
$st = $db->prepare('SELECT * FROM tutors WHERE id = ? AND is_active = 1');
$st->execute([$tutorId]);
$tutor = $st->fetch();

if (!$tutor) {
    jsonResponse(['success' => false, 'message' => '找不到指定導師'], 404);
}

// ── 偽定時檢查（auto 模式）────────────────────────────────────────────────
if ($mode === 'auto') {
    $intervalMinutes = (int)getSetting('post_interval_minutes', DEFAULT_POST_INTERVAL);
    $lastPostKey     = 'last_post_' . $tutorId;
    $lastPostTime    = getSetting($lastPostKey, '1970-01-01 00:00:00');

    $secondsSinceLast = time() - strtotime($lastPostTime);
    if ($secondsSinceLast < ($intervalMinutes * 60)) {
        // 尚未到觸發時間，跳過
        jsonResponse([
            'success' => true,
            'skipped' => true,
            'message' => '尚未到生成時間，已跳過',
            'next_in_seconds' => ($intervalMinutes * 60) - $secondsSinceLast,
        ]);
    }
}

// ── 構建 AI Prompt ────────────────────────────────────────────────────────
$promptContent = buildTutorPostPrompt($tutor);

// ── 呼叫 AI（直接在此執行，不經前端代理）────────────────────────────────
$aiContent = callAiDirectly([
    ['role' => 'system', 'content' => '你是一個角色扮演 AI。嚴格按照指示直接輸出角色的社交媒體動態內容，絕對不要輸出任何思考過程、分析說明或前置解釋。'],
    ['role' => 'user', 'content' => $promptContent],
], 512, 0.8);

if ($aiContent === null) {
    jsonResponse(['success' => false, 'message' => 'AI 生成失敗，請稍後再試'], 503);
}

// 清理輸出：移除 <think> 標籤、各種 AI 推理前置文字、特殊符號
$aiContent = trim(preg_replace('/<think>.*?<\/think>/s', '', $aiContent));
// 移除 AI 推理文字（以「首先」「好的」「我需要」「根據」「讓我」等開頭的段落）
$aiContent = stripAiReasoning($aiContent);
$aiContent = preg_replace('/[*#【】\[\]]/u', '', $aiContent);

// ── FIFO：強制執行動態上限 ───────────────────────────────────────────────
enforceSocialPostLimit();

// ── 插入新動態 ────────────────────────────────────────────────────────────
$db->prepare(
    'INSERT INTO social_posts (author_type, tutor_id, content, created_at)
     VALUES (\'tutor\', ?, ?, NOW())'
)->execute([$tutorId, $aiContent]);

$postId = (int)$db->lastInsertId();

// ── 更新上次生成時間（供偽定時邏輯使用）────────────────────────────────────
setSetting('last_post_' . $tutorId, date('Y-m-d H:i:s'));

jsonResponse([
    'success'  => true,
    'skipped'  => false,
    'post_id'  => $postId,
    'content'  => $aiContent,
    'tutor'    => $tutor['name'],
]);

// ════════════════════════════════════════════════════════════════════════════

/**
 * 移除 AI 推理文字：若回覆包含推理段落（以「首先」「好的」「我需要」等開頭），
 * 嘗試從最後一個連續正文段落提取實際內容。
 */
function stripAiReasoning(string $text): string
{
    // 移除整個 <think>...</think> 區塊（含多行）
    $text = preg_replace('/<think>[\s\S]*?<\/think>/u', '', $text);

    // 常見推理前置詞模式
    $reasoningPatterns = [
        '/^首先[，,、].{0,200}\n/um',
        '/^好的[，,、].{0,200}\n/um',
        '/^我需要.{0,200}\n/um',
        '/^讓我.{0,200}\n/um',
        '/^根據.{0,200}\n/um',
        '/^思考.{0,200}\n/um',
        '/^分析.{0,200}\n/um',
        '/^用戶要求.{0,200}\n/um',
        '/^要求.{0,200}\n/um',
        '/^注意.{0,200}\n/um',
    ];
    foreach ($reasoningPatterns as $pattern) {
        $text = preg_replace($pattern, '', $text);
    }

    // 如果仍有長串推理（超過 200 字且包含推理關鍵詞），取最後一段非空行作為實際內容
    $lines = array_filter(array_map('trim', explode("\n", $text)));
    if (count($lines) > 1) {
        // 找最後一個看起來像正式動態的段落（不以推理詞開頭，且長度合理）
        $reasoningStarts = ['首先', '好的', '我需要', '讓我', '根據', '思考', '分析', '用戶', '要求', '注意', '結構', '內容', '動態要'];
        $candidates = [];
        foreach ($lines as $line) {
            $isReasoning = false;
            foreach ($reasoningStarts as $s) {
                if (mb_strpos($line, $s) === 0) { $isReasoning = true; break; }
            }
            if (!$isReasoning && mb_strlen($line) >= 10 && mb_strlen($line) <= 150) {
                $candidates[] = $line;
            }
        }
        if (!empty($candidates)) {
            return trim(end($candidates));
        }
    }

    return trim($text);
}

/**
 * 構建古人動態生成 Prompt
 */
function buildTutorPostPrompt(array $tutor): string
{
    $name        = $tutor['name'];
    $personality = $tutor['personality'] ?? '性格鮮明';
    $style       = $tutor['language_style'] ?? '語言生動';

    return "你現在是{$name}，請以{$name}的身份在現代社交媒體發布一條動態。

性格特點：{$personality}
語言風格：{$style}

嚴格要求：
1. 不超過70字，用繁體中文
2. 只有引用詩文時才用文言文，其他情況一概用生活化的香港粵語
3. 體現{$name}的性格，展現對現代生活的思考與感受
4. 絕對不要在內容後加入任何括號解釋、「（註：...）」或「注：」
5. 不含「*」「#」「【】」「[」「]」等特殊符號
6. 內容必須是完整的一段文字，語句流暢自然
7. 不要輸出任何思考過程、分析或解釋，只輸出動態正文

直接輸出動態正文：";
}

/**
 * 直接呼叫 AI API（在後端執行，不涉及前端）
 * 自動輪換 Key 和 Model（邏輯同 ai_call.php）
 *
 * @return string|null AI 生成內容，失敗返回 null
 */
function callAiDirectly(array $messages, int $maxTokens = 512, float $temperature = 0.7): ?string
{
    foreach (AI_API_KEYS as $key) {
        foreach (AI_MODELS as $model) {
            $payload = json_encode([
                'model'       => $model,
                'messages'    => $messages,
                'max_tokens'  => $maxTokens,
                'temperature' => $temperature,
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
