<?php
/**
 * 文樞 — 社群動態頁
 *
 * 功能：
 *  - 顯示全平台共享的社群動態牆（所有用戶看到相同內容）
 *  - 偽定時觸發：頁面載入時，若距上次超過間隔，自動生成古人動態
 *  - 已登入用戶可發布動態及留言
 *  - 動態按時間倒序排列
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$userId   = getCurrentUserId();
$db       = getDB();

// ── 偽定時觸發：靜默呼叫各已啟用導師的自動生成 ────────────────────────────
// 使用非同步 curl（fire-and-forget），不阻塞頁面載入
$tutors      = getActiveTutors();
$interval    = (int)getSetting('post_interval_minutes', DEFAULT_POST_INTERVAL);
$baseUrl     = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . BASE_URL;
$sessionId   = session_id();

// 計算哪些導師需要觸發（在釋放 session 鎖前）
$tutorsToTrigger = [];
foreach ($tutors as $tutor) {
    $lastPostKey  = 'last_post_' . $tutor['id'];
    $lastPostTime = getSetting($lastPostKey, '1970-01-01 00:00:00');
    $elapsed      = time() - strtotime($lastPostTime);
    if ($elapsed >= ($interval * 60)) {
        $tutorsToTrigger[] = $tutor;
    }
}

// 釋放 session 鎖後再發出 curl，避免子請求因 session 鎖等待而死鎖
if (!empty($tutorsToTrigger)) {
    session_write_close();
    foreach ($tutorsToTrigger as $tutor) {
        $apiUrl  = $baseUrl . '/api/generate_tutor_post.php';
        $payload = json_encode(['tutor_id' => $tutor['id'], 'mode' => 'auto'], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 1,       // 只等 1 秒，之後不管它（fire-and-forget）
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Cookie: PHPSESSID=' . $sessionId,
            ],
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}

// ── 處理用戶發布動態（POST）────────────────────────────────────────────────
$postError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $postError = 'CSRF 驗證失敗。';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'new_post') {
            $content = trim($_POST['content'] ?? '');
            if (empty($content)) {
                $postError = '動態內容不可為空。';
            } elseif (mb_strlen($content) > 500) {
                $postError = '動態內容不可超過 500 字。';
            } else {
                enforceSocialPostLimit();
                $db->prepare(
                    "INSERT INTO social_posts (author_type, user_id, content, created_at)
                     VALUES ('user', ?, ?, NOW())"
                )->execute([$userId, $content]);
                $newPostId = (int)$db->lastInsertId();

                // 偽定時觸發：讓一位活躍導師對用戶新帖留言（50% 機率，火焰即忘）
                if (mt_rand(1, 2) === 1) {
                    $activeTutors = getActiveTutors();
                    if (!empty($activeTutors)) {
                        // 確保 session 已關閉（避免子請求死鎖）
                        if (session_status() === PHP_SESSION_ACTIVE) {
                            session_write_close();
                        }
                        $commentApiUrl = $baseUrl . '/api/generate_tutor_comment.php';
                        $payload = json_encode([
                            'mode'    => 'comment_post',
                            'post_id' => $newPostId,
                        ], JSON_UNESCAPED_UNICODE);
                        $ch = curl_init($commentApiUrl);
                        curl_setopt_array($ch, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_POST           => true,
                            CURLOPT_POSTFIELDS     => $payload,
                            CURLOPT_TIMEOUT        => 1,
                            CURLOPT_HTTPHEADER     => [
                                'Content-Type: application/json',
                                'Cookie: PHPSESSID=' . $sessionId,
                            ],
                        ]);
                        curl_exec($ch);
                        curl_close($ch);
                    }
                }
                header('Location: ' . BASE_URL . '/social/index.php');
                exit;
            }
        }

        if ($action === 'new_comment') {
            $postId  = (int)($_POST['post_id'] ?? 0);
            $content = trim($_POST['comment_content'] ?? '');
            if ($postId > 0 && !empty($content) && mb_strlen($content) <= 200) {
                $db->prepare(
                    "INSERT INTO social_comments (post_id, author_type, user_id, content, created_at)
                     VALUES (?, 'user', ?, ?, NOW())"
                )->execute([$postId, $userId, $content]);

                // 若是在導師帖子下留言，觸發導師自動回覆（火焰即忘）
                // 若是在用戶帖子下留言，也有50%機率觸發導師留言
                $stCheckTutor = $db->prepare('SELECT author_type FROM social_posts WHERE id = ?');
                $stCheckTutor->execute([$postId]);
                $postRow = $stCheckTutor->fetch();
                $shouldTrigger = false;
                if ($postRow) {
                    if ($postRow['author_type'] === 'tutor') {
                        // Always reply on tutor posts
                        $shouldTrigger = true;
                    } elseif ($postRow['author_type'] === 'user' && mt_rand(1, 2) === 1) {
                        // 50% chance to reply on user posts
                        $shouldTrigger = true;
                    }
                }
                if ($shouldTrigger) {
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_write_close();
                    }
                    $commentApiUrl = $baseUrl . '/api/generate_tutor_comment.php';
                    $payload = json_encode([
                        'mode'         => 'reply_comment',
                        'post_id'      => $postId,
                        'user_comment' => $content,
                    ], JSON_UNESCAPED_UNICODE);
                    $ch = curl_init($commentApiUrl);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => $payload,
                        CURLOPT_TIMEOUT        => 1,
                        CURLOPT_HTTPHEADER     => [
                            'Content-Type: application/json',
                            'Cookie: PHPSESSID=' . $sessionId,
                        ],
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                }
            }
            header('Location: ' . BASE_URL . '/social/index.php#post-' . $postId);
            exit;
        }

        if ($action === 'like_post' && isset($_POST['post_id'])) {
            $postId = (int)$_POST['post_id'];
            $db->prepare('UPDATE social_posts SET likes = likes + 1 WHERE id = ?')->execute([$postId]);
            header('Location: ' . BASE_URL . '/social/index.php');
            exit;
        }
    }
}

// ── 取得所有動態（含作者資訊和留言數）──────────────────────────────────────
$posts = $db->query(
    'SELECT sp.*,
            u.username,
            t.name AS tutor_name,
            t.gradient_class,
            t.avatar_url AS tutor_avatar,
            (SELECT COUNT(*) FROM social_comments sc WHERE sc.post_id = sp.id) AS comment_count
     FROM social_posts sp
     LEFT JOIN users  u ON sp.user_id  = u.id
     LEFT JOIN tutors t ON sp.tutor_id = t.id
     ORDER BY sp.created_at DESC'
)->fetchAll();

// 取得已解鎖導師（用於頭像顯示）
$unlockedIds = getUnlockedTutorIds($userId);

$csrfToken  = getCsrfToken();
$pageTitle  = '古人社群';
$activePage = 'social';
require __DIR__ . '/../includes/partials/header.php';
?>

<style>
.post-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-light);
    margin-bottom: 20px;
    overflow: hidden;
    transition: var(--transition);
}
.post-card:hover { box-shadow: var(--shadow-medium); }
.post-header {
    padding: 16px 20px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}
.post-avatar {
    width: 44px; height: 44px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem; font-weight: 700; color: white;
    flex-shrink: 0; overflow: hidden;
}
.post-avatar img { width: 100%; height: 100%; object-fit: cover; }
.post-author { font-weight: 700; font-size: 0.92rem; }
.post-time   { font-size: 0.75rem; color: #aaa; }
.post-body   { padding: 12px 20px; font-size: 0.92rem; line-height: 1.65; }
.post-footer {
    padding: 8px 20px 12px;
    display: flex; align-items: center; gap: 16px;
    border-top: 1px solid rgba(127,179,213,0.1);
}
.post-action-btn {
    background: none; border: none; cursor: pointer;
    font-size: 0.82rem; color: var(--dark-color);
    display: flex; align-items: center; gap: 4px;
    padding: 4px 8px; border-radius: 6px;
    transition: var(--transition); font-family: inherit;
}
.post-action-btn:hover { background: rgba(127,179,213,0.1); color: var(--primary-color); }
.comment-section { padding: 0 20px 16px; }
.comment-item {
    display: flex; gap: 10px; margin-bottom: 10px;
    padding: 8px; background: rgba(127,179,213,0.05);
    border-radius: 8px;
}
.comment-avatar {
    width: 30px; height: 30px; border-radius: 50%;
    background: var(--gradient-primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; color: white; flex-shrink: 0;
}
.comment-content { flex: 1; }
.comment-author { font-weight: 600; font-size: 0.8rem; color: var(--text-color); }
.comment-text   { font-size: 0.85rem; color: var(--dark-color); }
.comment-time   { font-size: 0.72rem; color: #aaa; }
.comment-input-row { display: flex; gap: 8px; margin-top: 8px; }
.comment-input {
    flex: 1; padding: 8px 12px;
    border: 1.5px solid rgba(127,179,213,0.3); border-radius: 8px;
    font-size: 0.85rem; font-family: inherit;
    transition: border-color 0.2s;
}
.comment-input:focus { outline: none; border-color: var(--primary-color); }
.tutor-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 0.72rem; font-weight: 600;
    background: rgba(127,179,213,0.12); color: var(--primary-color);
    padding: 2px 8px; border-radius: 999px; margin-left: 6px;
}
</style>

<div class="page-container" style="max-width:760px;">

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
        <div>
            <h1 class="page-title"><i class="fas fa-comments" style="color:var(--highlight-color);margin-right:8px;"></i>古人社群</h1>
            <p class="page-subtitle">所有用戶共享同一動態牆，古代文豪亦會定時發帖</p>
        </div>
        <div style="font-size:0.8rem;color:var(--dark-color);">
            <i class="fas fa-database" style="margin-right:4px;"></i>
            動態上限：<?= (int)getSetting('max_posts', MAX_POSTS) ?> 篇（FIFO 自動清理）
        </div>
    </div>

    <?php if ($postError): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= e($postError) ?></div>
    <?php endif; ?>

    <!-- 發布動態 -->
    <div class="card" style="margin-bottom:24px;">
        <div class="card-title" style="margin-bottom:12px;">
            <i class="fas fa-pen" style="color:var(--primary-color);"></i>
            發布動態
        </div>
        <form method="POST" action="<?= BASE_URL ?>/social/index.php">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action"     value="new_post">
            <textarea class="form-textarea" name="content" placeholder="分享你的文言文學習心得，或對某段古文的感想…" maxlength="500" rows="3" style="resize:none;"></textarea>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
                <span style="font-size:0.78rem;color:#aaa;">最多 500 字</span>
                <button type="submit" class="btn btn-primary" style="padding:8px 20px;">
                    <i class="fas fa-paper-plane"></i> 發布
                </button>
            </div>
        </form>
    </div>

    <!-- 動態列表 -->
    <?php if (empty($posts)): ?>
    <div class="card" style="text-align:center;padding:48px;">
        <i class="fas fa-comment-slash" style="font-size:2.5rem;color:rgba(127,179,213,0.4);margin-bottom:16px;display:block;"></i>
        <p style="color:var(--dark-color);">社群尚無動態。<br>開始學習關卡，古代文豪將自動在此分享心得！</p>
    </div>
    <?php else: ?>
    <?php foreach ($posts as $post):
        $istutor = ($post['author_type'] === 'tutor');
        $authorName = $istutor ? ($post['tutor_name'] ?? '古人') : ($post['username'] ?? '用戶');
        $gradients = ['gradient-primary'=>'var(--gradient-primary)','gradient-secondary'=>'var(--gradient-secondary)','gradient-accent'=>'var(--gradient-accent)'];
        $gradStyle = $gradients[$post['gradient_class'] ?? 'gradient-primary'] ?? 'var(--gradient-primary)';

        // 取得留言
        $comments = $db->prepare(
            'SELECT sc.*,
                    u.username,
                    t.name AS tutor_name,
                    t.gradient_class AS t_grad
             FROM social_comments sc
             LEFT JOIN users  u ON sc.user_id  = u.id
             LEFT JOIN tutors t ON sc.tutor_id = t.id
             WHERE sc.post_id = ?
             ORDER BY sc.created_at ASC'
        );
        $comments->execute([$post['id']]);
        $commentRows = $comments->fetchAll();
    ?>
    <div class="post-card" id="post-<?= $post['id'] ?>">
        <div class="post-header">
            <div class="post-avatar" style="background:<?= $gradStyle ?>;">
                <?php if ($istutor && !empty($post['tutor_avatar'])): ?>
                    <img src="<?= e($post['tutor_avatar']) ?>" alt="<?= e($authorName) ?>" onerror="this.style.display='none'">
                <?php else: ?>
                    <?= mb_substr($authorName, 0, 1) ?>
                <?php endif; ?>
            </div>
            <div>
                <div class="post-author">
                    <?= e($authorName) ?>
                    <?php if ($istutor): ?>
                    <span class="tutor-badge"><i class="fas fa-star"></i> 文學導師</span>
                    <?php endif; ?>
                </div>
                <div class="post-time"><?= timeAgo($post['created_at']) ?></div>
            </div>
        </div>
        <div class="post-body"><?= nl2br(e($post['content'])) ?></div>
        <div class="post-footer">
            <form method="POST" action="<?= BASE_URL ?>/social/index.php" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action"     value="like_post">
                <input type="hidden" name="post_id"    value="<?= $post['id'] ?>">
                <button type="submit" class="post-action-btn">
                    <i class="fas fa-heart" style="color:var(--error-color);"></i> <?= $post['likes'] ?>
                </button>
            </form>
            <button class="post-action-btn" onclick="toggleComments(<?= $post['id'] ?>)">
                <i class="fas fa-comment"></i> <?= $post['comment_count'] ?> 留言
            </button>
        </div>

        <!-- 留言區 -->
        <div class="comment-section" id="comments-<?= $post['id'] ?>" style="display:none;">
            <?php foreach ($commentRows as $cm):
                $cmIstutor = ($cm['author_type'] === 'tutor');
                $cmAuthor  = $cmIstutor ? ($cm['tutor_name'] ?? '古人') : ($cm['username'] ?? '用戶');
                $cmGrads   = ['gradient-primary'=>'var(--gradient-primary)','gradient-secondary'=>'var(--gradient-secondary)'];
                $cmGrad    = $cmGrads[$cm['t_grad'] ?? ''] ?? 'var(--gradient-primary)';
            ?>
            <div class="comment-item">
                <div class="comment-avatar" style="background:<?= $cmIstutor ? $cmGrad : 'var(--gradient-primary)' ?>;">
                    <?= mb_substr($cmAuthor, 0, 1) ?>
                </div>
                <div class="comment-content">
                    <div class="comment-author">
                        <?= e($cmAuthor) ?>
                        <?php if ($cmIstutor): ?><span class="tutor-badge" style="font-size:0.68rem;"><i class="fas fa-star"></i></span><?php endif; ?>
                        <span class="comment-time" style="margin-left:6px;"><?= timeAgo($cm['created_at']) ?></span>
                    </div>
                    <div class="comment-text"><?= nl2br(e($cm['content'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- 新增留言 -->
            <form method="POST" action="<?= BASE_URL ?>/social/index.php">
                <input type="hidden" name="csrf_token"      value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action"          value="new_comment">
                <input type="hidden" name="post_id"         value="<?= $post['id'] ?>">
                <div class="comment-input-row">
                    <input type="text" class="comment-input" name="comment_content"
                           placeholder="留言…" maxlength="200" required>
                    <button type="submit" class="btn btn-primary" style="padding:8px 14px;font-size:0.82rem;">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

</div><!-- end page-container -->

<script>
function toggleComments(postId) {
    const el = document.getElementById('comments-' + postId);
    if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

// 自動展開帶有錨點的留言區
window.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash;
    if (hash && hash.startsWith('#post-')) {
        const postId = hash.replace('#post-', '');
        const cm = document.getElementById('comments-' + postId);
        if (cm) cm.style.display = 'block';
        const el = document.getElementById('post-' + postId);
        if (el) el.scrollIntoView({ behavior: 'smooth' });
    }
});
</script>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
