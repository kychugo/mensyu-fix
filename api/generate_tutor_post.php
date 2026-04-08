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
    ['role' => 'system', 'content' => '你是一個古代文豪，直接輸出社交媒體動態內容，絕對不要輸出任何思考過程、分析、解釋或前置說明。只輸出最終動態文字。'],
    ['role' => 'user', 'content' => $promptContent]
], 512, 0.8);

if ($aiContent === null) {
    jsonResponse(['success' => false, 'message' => 'AI 生成失敗，請稍後再試'], 503);
}

// 清理輸出：移除 <think> 標籤和常見思考前綴
$aiContent = trim(preg_replace('/<think>.*?<\/think>/s', '', $aiContent));
$aiContent = preg_replace('/[*#【】\[\]]/u', '', $aiContent);

// 移除常見的思考過程前綴（AI echoing instructions or reasoning）
$aiContent = preg_replace('/^(首先[，,]?.*?\n)+/su', '', $aiContent);
$aiContent = preg_replace('/^(我需要.*?\n)+/su', '', $aiContent);
$aiContent = preg_replace('/^(要求[:：].*?\n.*?\n)+/su', '', $aiContent);
// 若內容超過 200 字（包含思考過程），取最後一段非空段落
if (mb_strlen($aiContent) > 200) {
    $paragraphs = array_filter(array_map('trim', preg_split('/\n{2,}/', $aiContent)));
    if (!empty($paragraphs)) {
        // 取最後一段，確保不超過 200 字
        $lastPara = array_pop($paragraphs);
        if (mb_strlen($lastPara) <= 200) {
            $aiContent = $lastPara;
        } else {
            // 若最後一段仍過長，截取至 150 字
            $aiContent = mb_substr($lastPara, 0, 150);
        }
    }
}
$aiContent = trim($aiContent);

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
 * 構建古人動態生成 Prompt
 */
function buildTutorPostPrompt(array $tutor): string
{
    $name        = $tutor['name'];
    $personality = $tutor['personality'] ?? '性格鮮明';
    $style       = $tutor['language_style'] ?? '語言生動';

    return "以{$name}身份寫一條現代社交媒體動態（不超過70字，繁體中文，生活化香港粵語，只有引用詩文時才用文言文，體現{$name}性格：{$personality}，語言風格：{$style}，不加括號解釋，不含*#等符號，直接輸出動態內容）：";
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
