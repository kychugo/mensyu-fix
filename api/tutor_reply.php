<?php
/**
 * 文學導師自動回覆 API
 *
 * 觸發方式：由 social/index.php 在用戶發帖或留言後，非同步 fire-and-forget 呼叫。
 *
 * 請求方式：POST（必須已登入）
 * 請求參數（JSON）：
 *   { "post_id": 1 }          ← 用戶發新帖後，導師自動留言
 *   { "post_id": 1, "comment_context": "..." } ← 用戶留言後，導師繼續回覆
 *
 * 邏輯：
 *   - 隨機選一位已啟用導師（若帖子是導師發的，選原導師）
 *   - 讀取帖子內容及已有留言（提供上下文）
 *   - AI 生成一條切合角色的留言
 *   - 插入 social_comments 表
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
$postId  = isset($body['post_id']) ? (int)$body['post_id'] : 0;

if ($postId <= 0) {
    jsonResponse(['success' => false, 'message' => '缺少有效的 post_id'], 400);
}

$db = getDB();

// ── 讀取帖子 ──────────────────────────────────────────────────────────────
$stPost = $db->prepare(
    'SELECT sp.*, u.username, t.name AS tutor_name
     FROM social_posts sp
     LEFT JOIN users u ON sp.user_id = u.id
     LEFT JOIN tutors t ON sp.tutor_id = t.id
     WHERE sp.id = ?'
);
$stPost->execute([$postId]);
$post = $stPost->fetch();

if (!$post) {
    jsonResponse(['success' => false, 'message' => '找不到帖子'], 404);
}

// ── 防重複：若此帖已有導師留言，超過2條則跳過（避免刷屏）─────────────────
$stCount = $db->prepare(
    "SELECT COUNT(*) FROM social_comments WHERE post_id = ? AND author_type = 'tutor'"
);
$stCount->execute([$postId]);
$tutorCommentCount = (int)$stCount->fetchColumn();

// 如果已有2個以上導師留言，跳過
if ($tutorCommentCount >= 2) {
    jsonResponse(['success' => true, 'skipped' => true, 'message' => '已有足夠導師回覆']);
}

// ── 選擇回覆的導師 ────────────────────────────────────────────────────────
// 若帖子是導師發的，優先讓同一導師回覆（但避免自己回覆自己）
$tutors = getActiveTutors();
if (empty($tutors)) {
    jsonResponse(['success' => false, 'message' => '沒有可用導師'], 404);
}

$selectedTutor = null;
if ($post['author_type'] === 'tutor') {
    // 帖子是導師發的 → 選另一位導師
    $others = array_filter($tutors, fn($t) => $t['id'] != $post['tutor_id']);
    if (!empty($others)) {
        $selectedTutor = $others[array_rand($others)];
    } else {
        // 只有一位導師，讓導師自己回覆（角色扮演）
        $selectedTutor = $tutors[array_rand($tutors)];
    }
} else {
    // 帖子是用戶發的 → 隨機選一位導師
    $selectedTutor = $tutors[array_rand($tutors)];
}

// ── 讀取帖子已有留言（最多3條作為上下文）────────────────────────────────
$stCm = $db->prepare(
    'SELECT sc.content, sc.author_type, u.username, t.name AS tutor_name
     FROM social_comments sc
     LEFT JOIN users u ON sc.user_id = u.id
     LEFT JOIN tutors t ON sc.tutor_id = t.id
     WHERE sc.post_id = ?
     ORDER BY sc.created_at DESC LIMIT 3'
);
$stCm->execute([$postId]);
$recentComments = array_reverse($stCm->fetchAll());

// ── 構建 Prompt ───────────────────────────────────────────────────────────
$prompt = buildTutorReplyPrompt($selectedTutor, $post, $recentComments);

// ── 呼叫 AI ───────────────────────────────────────────────────────────────
$aiContent = callAiForReply([
    ['role' => 'system', 'content' => '你是一個古代文豪，直接輸出留言內容，不要任何思考過程或說明。'],
    ['role' => 'user', 'content' => $prompt]
], 256, 0.85);

if ($aiContent === null) {
    jsonResponse(['success' => false, 'message' => 'AI 生成失敗'], 503);
}

// 清理輸出
$aiContent = trim(preg_replace('/<think>.*?<\/think>/s', '', $aiContent));
$aiContent = preg_replace('/[*#【】\[\]]/u', '', $aiContent);
// 移除思考過程前綴
$aiContent = preg_replace('/^(首先[，,]?.*?\n)+/su', '', $aiContent);
$aiContent = trim($aiContent);

// 若內容過長，截取合理長度
if (mb_strlen($aiContent) > 150) {
    $paragraphs = array_filter(array_map('trim', preg_split('/\n{2,}/', $aiContent)));
    if (!empty($paragraphs)) {
        $lastPara = array_pop($paragraphs);
        $aiContent = mb_strlen($lastPara) <= 150 ? $lastPara : mb_substr($lastPara, 0, 120);
    } else {
        $aiContent = mb_substr($aiContent, 0, 120);
    }
}

if (empty(trim($aiContent))) {
    jsonResponse(['success' => false, 'message' => '生成內容為空'], 503);
}

// ── 插入留言 ─────────────────────────────────────────────────────────────
$db->prepare(
    "INSERT INTO social_comments (post_id, author_type, tutor_id, content, created_at)
     VALUES (?, 'tutor', ?, ?, NOW())"
)->execute([$postId, $selectedTutor['id'], $aiContent]);

jsonResponse([
    'success' => true,
    'tutor'   => $selectedTutor['name'],
    'content' => $aiContent,
]);

// ════════════════════════════════════════════════════════════════════════════

function buildTutorReplyPrompt(array $tutor, array $post, array $comments): string
{
    $tutorName   = $tutor['name'];
    $personality = $tutor['personality'] ?? '性格鮮明';
    $style       = $tutor['language_style'] ?? '語言生動';

    $postAuthor  = $post['author_type'] === 'tutor'
        ? ($post['tutor_name'] ?? '古人')
        : ($post['username'] ?? '用戶');
    $postContent = $post['content'];

    $contextStr = '';
    if (!empty($comments)) {
        $contextStr = "\n已有留言：\n";
        foreach ($comments as $cm) {
            $cmAuthor = $cm['author_type'] === 'tutor'
                ? ($cm['tutor_name'] ?? '古人')
                : ($cm['username'] ?? '用戶');
            $contextStr .= "  {$cmAuthor}：{$cm['content']}\n";
        }
    }

    return "以{$tutorName}身份回覆以下帖子（不超過60字，繁體中文生活化香港粵語，只有引用詩文才用文言文，體現{$tutorName}性格：{$personality}，語言風格：{$style}，不加括號解釋，直接輸出留言）：\n\n{$postAuthor}的帖子：{$postContent}{$contextStr}";
}

function callAiForReply(array $messages, int $maxTokens = 256, float $temperature = 0.85): ?string
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
