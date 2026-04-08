<?php
/**
 * 文樞 — 翻譯 API 端點
 *
 * POST JSON: { "level_id": 1, "title": "...", "content": "..." }
 * 或         { "title": "...", "content": "..." }  (直接翻譯模式)
 *
 * 回應: { "success": true, "html": "...", "content": "...", "cached": false }
 *
 * 翻譯結果緩存 7 天（TRANSLATION_CACHE_TTL）。
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
$title   = trim($body['title']   ?? '');
$content = trim($body['content'] ?? '');

if (empty($content)) {
    jsonResponse(['success' => false, 'message' => '缺少翻譯內容'], 400);
}

$db = getDB();

// ── 緩存查詢 ────────────────────────────────────────────────────────────────
$cacheKey = md5($title . '_' . $content);
$stCache  = $db->prepare('SELECT translation_result, created_at FROM translation_cache WHERE text_hash = ? LIMIT 1');
$stCache->execute([$cacheKey]);
$cached   = $stCache->fetch();

if ($cached) {
    $age = time() - strtotime($cached['created_at']);
    if ($age < TRANSLATION_CACHE_TTL) {
        jsonResponse([
            'success' => true,
            'content' => $cached['translation_result'],
            'html'    => nl2br(htmlspecialchars($cached['translation_result'], ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'cached'  => true,
        ]);
    }
    // 緩存已過期，刪除後重新生成
    $db->prepare('DELETE FROM translation_cache WHERE text_hash = ?')->execute([$cacheKey]);
}

// ── 呼叫 AI ─────────────────────────────────────────────────────────────────
$prompt = buildTranslationPrompt($title, $content);
$result = callAiForTranslation([
    ['role' => 'system', 'content' => '你是一個文言文翻譯助手。嚴格按照用戶要求的格式輸出，不要輸出任何思考過程或額外解釋。'],
    ['role' => 'user',   'content' => $prompt],
]);

if ($result === null) {
    jsonResponse(['success' => false, 'message' => 'AI 翻譯服務暫時不可用，請稍後再試'], 503);
}

// 儲存緩存
$db->prepare(
    'INSERT INTO translation_cache (text_hash, essay_title, translation_result, created_at)
     VALUES (?, ?, ?, NOW())'
)->execute([$cacheKey, $title, $result]);

jsonResponse([
    'success' => true,
    'content' => $result,
    'html'    => nl2br(htmlspecialchars($result, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
    'cached'  => false,
]);

// ════════════════════════════════════════════════════════════════════════════

function buildTranslationPrompt(string $title, string $content): string
{
    $t = $title ? "《{$title}》" : '以下文言文';
    return "請將{$t}逐字翻譯並解釋(直譯，不要意譯)，格式要求：

原文：
[顯示原文句子]

語譯：
[顯示完整句子翻譯]

逐字解釋：
[對每個文字進行解釋，格式為\"字：解釋\"，常見文言字詞用**粗體**標示，切勿解釋標點符號]

要求：
1. 為每一句(以，。？!：； 作為分隔)進行語譯
2. 如果該行有常見文言字詞，請為該字及其詞解粗體
3. 用中文繁體字顯示所有內容
4. 不要提供思考過程
5. 保持嚴格的格式，使用標題和清晰的分段
6. 不要在任何地方使用多餘的星號(*)
7. 不要使用任何裝飾性符號或分隔線
8. 確保每部分都有明確的標題（原文、語譯、逐字解釋）
9. 逐字解釋只解釋文字字符，不解釋標點符號如，。？!等
10. 必須為每一句(以，。？!：； 作為分隔)進行語譯，嚴禁整段進行語譯

需要翻譯的文言文：
{$content}

請直接輸出解析內容，不要添加任何前言或後記：";
}

function callAiForTranslation(array $messages): ?string
{
    foreach (AI_API_KEYS as $key) {
        foreach (AI_MODELS as $model) {
            $payload = json_encode([
                'model'       => $model,
                'messages'    => $messages,
                'max_tokens'  => 2048,
                'temperature' => 0.3,
            ], JSON_UNESCAPED_UNICODE);

            $ch = curl_init(AI_API_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_TIMEOUT        => 90,
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
                    $content = $data['choices'][0]['message']['content'];
                    $content = preg_replace('/<think>.*?<\/think>/s', '', $content);
                    return trim($content);
                }
            }
        }
    }
    return null;
}
