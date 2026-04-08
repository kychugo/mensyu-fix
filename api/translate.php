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
$result = callAiForTranslation([['role' => 'user', 'content' => $prompt]]);

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
    return "請為{$t}提供詳細的文言文翻譯解析，格式如下：

【原文】
{$content}

請按以下格式輸出（使用繁體中文）：

一、逐句對譯
對每個句子先列出原文，然後提供白話文翻譯。

二、重要字詞解釋
列出文中重要的文言字詞，格式：字詞 → 解釋（現代含義）

三、全文大意
用3-5句白話文概括全文主旨。

請直接輸出解析內容，不要添加任何前言或後記。";
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
