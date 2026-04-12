<?php
/**
 * 文樞 — 文學導師自動回覆 API
 *
 * 觸發方式：
 *  - 用戶在導師的動態下留言後，由 social/index.php 非同步觸發
 *  - 用戶發布新動態後，隨機由一位活躍導師留言互動
 *
 * 請求方式：POST JSON
 *   { "mode": "reply_comment", "post_id": 1, "user_comment": "..." }  ← 回覆用戶留言
 *   { "mode": "comment_post",  "post_id": 1 }                         ← 導師主動留言用戶帖子
 *
 * 回應：
 *   { "success": true,  "comment_id": 123, "content": "..." }
 *   { "success": false, "message": "..." }
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => '方法不允許'], 405);
}

$body       = json_decode(file_get_contents('php://input'), true) ?: [];
$mode       = $body['mode']         ?? '';
$postId     = (int)($body['post_id']      ?? 0);
$userComment = trim($body['user_comment'] ?? '');

if ($postId <= 0) {
    jsonResponse(['success' => false, 'message' => '缺少有效的 post_id'], 400);
}

$db = getDB();

// ── 取得動態資料 ──────────────────────────────────────────────────────────
$stPost = $db->prepare(
    'SELECT sp.*, t.name AS tutor_name, t.personality, t.language_style,
            u.username AS user_name
     FROM social_posts sp
     LEFT JOIN tutors t ON sp.tutor_id = t.id
     LEFT JOIN users  u ON sp.user_id  = u.id
     WHERE sp.id = ?'
);
$stPost->execute([$postId]);
$post = $stPost->fetch();

if (!$post) {
    jsonResponse(['success' => false, 'message' => '找不到指定動態'], 404);
}

// ── 決定回覆導師 ─────────────────────────────────────────────────────────
if ($mode === 'reply_comment' && $post['author_type'] === 'tutor' && !empty($post['tutor_id'])) {
    // 由該動態的導師本人回覆
    $stTutor = $db->prepare('SELECT * FROM tutors WHERE id = ? AND is_active = 1');
    $stTutor->execute([$post['tutor_id']]);
    $tutor = $stTutor->fetch();
} else {
    // 用戶帖子：隨機由一位活躍導師留言
    $tutors = getActiveTutors();
    if (empty($tutors)) {
        jsonResponse(['success' => false, 'message' => '沒有活躍導師'], 404);
    }
    $tutor = $tutors[array_rand($tutors)];
}

if (!$tutor) {
    jsonResponse(['success' => false, 'message' => '找不到導師資料'], 404);
}

// ── 防重複：同一導師不連續回覆同一帖子（60 秒內）────────────────────────
$stRecent = $db->prepare(
    'SELECT COUNT(*) FROM social_comments
     WHERE post_id = ? AND tutor_id = ? AND author_type = "tutor"
       AND created_at >= DATE_SUB(NOW(), INTERVAL 10 SECOND)'
);
$stRecent->execute([$postId, $tutor['id']]);
if ((int)$stRecent->fetchColumn() > 0) {
    jsonResponse(['success' => true, 'skipped' => true, 'message' => '導師剛剛已回覆，請稍後再試']);
}

// ── 構建 Prompt ───────────────────────────────────────────────────────────
$prompt = buildCommentPrompt($tutor, $post, $userComment, $mode);

// ── 呼叫 AI ───────────────────────────────────────────────────────────────
$aiContent = callAiForComment([
    ['role' => 'system', 'content' => '你是一個角色扮演 AI。嚴格按照指示直接輸出角色的留言內容，絕對不要輸出任何思考過程或前置說明。'],
    ['role' => 'user',   'content' => $prompt],
]);

if ($aiContent === null) {
    jsonResponse(['success' => false, 'message' => 'AI 生成失敗'], 503);
}

// 清理輸出
$aiContent = trim(preg_replace('/<think>[\s\S]*?<\/think>/u', '', $aiContent));
$aiContent = trim(preg_replace('/[*#【】\[\]]/u', '', $aiContent));

if (empty($aiContent)) {
    jsonResponse(['success' => false, 'message' => 'AI 回覆為空'], 503);
}

// ── 插入留言 ─────────────────────────────────────────────────────────────
$db->prepare(
    "INSERT INTO social_comments (post_id, author_type, tutor_id, content, created_at)
     VALUES (?, 'tutor', ?, ?, NOW())"
)->execute([$postId, $tutor['id'], $aiContent]);

$commentId = (int)$db->lastInsertId();

jsonResponse([
    'success'    => true,
    'skipped'    => false,
    'comment_id' => $commentId,
    'content'    => $aiContent,
    'tutor'      => $tutor['name'],
]);

// ════════════════════════════════════════════════════════════════════════════

/**
 * 構建留言 Prompt
 */
function buildCommentPrompt(array $tutor, array $post, string $userComment, string $mode): string
{
    $name        = $tutor['name'];
    $personality = $tutor['personality'] ?? '性格鮮明';
    $style       = $tutor['language_style'] ?? '語言生動';
    $postContent = mb_substr($post['content'] ?? '', 0, 120);

    if ($mode === 'reply_comment' && !empty($userComment)) {
        $commenterName = $post['user_name'] ?? '網友';
        return "你是{$name}，剛才在你的社交媒體動態下，有用戶「{$commenterName}」留言：
「{$userComment}」

你的原本動態內容是：
「{$postContent}」

性格特點：{$personality}
語言風格：{$style}

請以{$name}的身份回覆這條留言。嚴格要求：
1. 不超過50字，用繁體中文
2. 只有引用詩文時才用文言文，其他情況一概用生活化的香港粵語
3. 要有互動感，體現{$name}的性格
4. 不含「*」「#」「【】」「[」「]」等特殊符號
5. 不要輸出任何思考過程，只輸出留言正文

直接輸出留言正文：";
    } else {
        // 導師主動評論用戶帖子
        $posterName = $post['user_name'] ?? '網友';
        return "你是{$name}，看到用戶「{$posterName}」在社群發布了以下動態：
「{$postContent}」

性格特點：{$personality}
語言風格：{$style}

請以{$name}的身份留言回應。嚴格要求：
1. 不超過50字，用繁體中文
2. 只有引用詩文時才用文言文，其他情況一概用生活化的香港粵語
3. 要有互動感，對用戶的內容作出點評或鼓勵，體現{$name}的性格
4. 不含「*」「#」「【】」「[」「]」等特殊符號
5. 不要輸出任何思考過程，只輸出留言正文

直接輸出留言正文：";
    }
}

/**
 * 呼叫 AI 生成留言
 */
function callAiForComment(array $messages): ?string
{
    foreach (AI_API_KEYS as $key) {
        foreach (AI_MODELS as $model) {
            $payload = json_encode([
                'model'       => $model,
                'messages'    => $messages,
                'max_tokens'  => 256,
                'temperature' => 0.85,
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
