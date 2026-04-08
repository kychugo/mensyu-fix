<?php
/**
 * 文樞 — 管理後台主頁
 *
 * 功能：
 *  - 平台統計概覽
 *  - 手動觸發古人動態生成（逐個導師）
 *  - 設定：動態生成間隔、最大動態數量
 *  - 快速入口：管理導師 / 關卡 / 文章 / 用戶 / 配對題庫
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = getDB();
$msg = '';
$msgType = 'success';

// ── 處理設定儲存 ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'CSRF 驗證失敗，請重試。';
        $msgType = 'error';
    } else {
        $action = $_POST['action'];

        if ($action === 'save_settings') {
            $interval = max(5, (int)($_POST['post_interval'] ?? 60));
            $maxPosts = max(10, min(200, (int)($_POST['max_posts'] ?? 80)));
            setSetting('post_interval_minutes', (string)$interval);
            setSetting('max_posts',             (string)$maxPosts);
            $msg = '設定已儲存。';
        }
    }
}

// ── 取得資料 ────────────────────────────────────────────────────────────────
$stats = getPlatformStats();
$tutors = getActiveTutors();
$interval = (int)getSetting('post_interval_minutes', DEFAULT_POST_INTERVAL);
$maxPosts = (int)getSetting('max_posts', MAX_POSTS);

// 各導師最後生成時間
$tutorLastPost = [];
foreach ($tutors as $t) {
    $last = getSetting('last_post_' . $t['id'], null);
    $tutorLastPost[$t['id']] = $last;
}

$csrfToken  = getCsrfToken();
$pageTitle  = '管理後台';
$activePage = 'admin';
require __DIR__ . '/../includes/partials/header.php';
?>

<div class="page-container">

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
        <div>
            <h1 class="page-title"><i class="fas fa-cogs" style="color:var(--warning-color);margin-right:8px;"></i>管理後台</h1>
            <p class="page-subtitle">平台內容與設定管理</p>
        </div>
        <span class="badge badge-warning" style="font-size:0.8rem;">
            <i class="fas fa-shield-alt"></i> 管理員模式
        </span>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>">
        <i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= e($msg) ?>
    </div>
    <?php endif; ?>

    <!-- 統計數字 -->
    <div class="grid-4" style="margin-bottom:28px;">
        <?php
        $statCards = [
            ['icon'=>'fa-users',      'value'=>$stats['users'],   'label'=>'注冊用戶', 'color'=>'var(--primary-color)'],
            ['icon'=>'fa-user-tie',   'value'=>$stats['tutors'],  'label'=>'文學導師', 'color'=>'var(--highlight-color)'],
            ['icon'=>'fa-scroll',     'value'=>$stats['essays'],  'label'=>'翻譯文章', 'color'=>'var(--accent-color)'],
            ['icon'=>'fa-comment-dots','value'=>$stats['posts'],   'label'=>'社群動態', 'color'=>'#f0932b'],
        ];
        foreach ($statCards as $sc): ?>
        <div class="card" style="text-align:center;padding:20px;">
            <i class="fas <?= e($sc['icon']) ?>" style="font-size:1.6rem;color:<?= $sc['color'] ?>;margin-bottom:8px;display:block;"></i>
            <div style="font-size:1.8rem;font-weight:700;color:<?= $sc['color'] ?>;"><?= $sc['value'] ?></div>
            <div style="font-size:0.82rem;color:var(--dark-color);"><?= e($sc['label']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="grid-2">

        <!-- 古人動態手動觸發 -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-robot" style="color:var(--primary-color);"></i>
                古人動態生成控制
            </div>
            <p style="font-size:0.85rem;color:var(--dark-color);margin-bottom:16px;">
                當前自動生成間隔：<strong><?= $interval ?> 分鐘</strong>（每次載入社群頁面時自動觸發）<br>
                若要立即為某位導師生成新動態，點擊下方按鈕。
            </p>

            <?php if (empty($tutors)): ?>
            <div class="alert alert-info"><i class="fas fa-info-circle"></i> 尚無導師，請先新增導師。</div>
            <?php else: ?>
            <div id="tutor-trigger-list">
                <?php foreach ($tutors as $tutor): 
                    $lastTime = $tutorLastPost[$tutor['id']] ?? null;
                    $lastLabel = $lastTime ? timeAgo($lastTime) : '從未生成';
                ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(127,179,213,0.1);" id="tutor-row-<?= $tutor['id'] ?>">
                    <div>
                        <span style="font-weight:600;"><?= e($tutor['name']) ?></span>
                        <span style="font-size:0.78rem;color:var(--dark-color);margin-left:8px;"><?= e($tutor['dynasty']) ?></span>
                        <br>
                        <span style="font-size:0.75rem;color:#aaa;" id="last-post-<?= $tutor['id'] ?>">
                            上次生成：<?= e($lastLabel) ?>
                        </span>
                    </div>
                    <button class="btn btn-primary" style="padding:7px 16px;font-size:0.82rem;"
                            onclick="triggerPost(<?= $tutor['id'] ?>, '<?= e($tutor['name']) ?>')"
                            id="btn-trigger-<?= $tutor['id'] ?>">
                        <i class="fas fa-bolt"></i> 立即生成
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div id="trigger-result" style="margin-top:12px;display:none;"></div>
        </div>

        <!-- 系統設定 -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-sliders-h" style="color:var(--accent-color);"></i>
                系統設定
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action"     value="save_settings">

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-clock" style="margin-right:4px;"></i>
                        古人動態自動生成間隔（分鐘）
                    </label>
                    <input type="number" class="form-input" name="post_interval"
                           min="5" max="1440" value="<?= $interval ?>"
                           placeholder="60">
                    <div style="font-size:0.78rem;color:#aaa;margin-top:4px;">
                        每次有人瀏覽社群頁面時，若距上次超過此時間，自動觸發生成。
                        最小 5 分鐘，最大 1440 分鐘（24小時）。
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-database" style="margin-right:4px;"></i>
                        社群動態最大儲存數（FIFO）
                    </label>
                    <input type="number" class="form-input" name="max_posts"
                           min="10" max="200" value="<?= $maxPosts ?>"
                           placeholder="80">
                    <div style="font-size:0.78rem;color:#aaa;margin-top:4px;">
                        超出上限時，自動刪除最舊的動態（含所有留言）。
                        範圍 10–200。
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> 儲存設定
                </button>
            </form>
        </div>

    </div><!-- end grid-2 -->

    <!-- 快速管理入口 -->
    <div style="margin-top:28px;">
        <h2 style="font-size:1rem;font-weight:700;color:var(--text-color);margin-bottom:16px;">
            <i class="fas fa-th-large" style="margin-right:6px;color:var(--primary-color);"></i>
            內容管理
        </h2>
        <div class="grid-3">
            <a href="<?= BASE_URL ?>/admin/tutors.php" class="card" style="text-decoration:none;cursor:pointer;transition:var(--transition);" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
                <i class="fas fa-user-tie" style="font-size:1.8rem;color:var(--primary-color);margin-bottom:10px;display:block;"></i>
                <div style="font-weight:700;color:var(--text-color);">文學導師管理</div>
                <div style="font-size:0.8rem;color:var(--dark-color);margin-top:4px;">新增、編輯、刪除文學導師及其設定</div>
            </a>
            <a href="<?= BASE_URL ?>/admin/levels.php" class="card" style="text-decoration:none;cursor:pointer;transition:var(--transition);" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
                <i class="fas fa-layer-group" style="font-size:1.8rem;color:var(--highlight-color);margin-bottom:10px;display:block;"></i>
                <div style="font-weight:700;color:var(--text-color);">關卡內容管理</div>
                <div style="font-size:0.8rem;color:var(--dark-color);margin-top:4px;">管理各導師的學習關卡和文章</div>
            </a>
            <a href="<?= BASE_URL ?>/admin/essays.php" class="card" style="text-decoration:none;cursor:pointer;transition:var(--transition);" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
                <i class="fas fa-scroll" style="font-size:1.8rem;color:var(--accent-color);margin-bottom:10px;display:block;"></i>
                <div style="font-weight:700;color:var(--text-color);">翻譯文章庫</div>
                <div style="font-size:0.8rem;color:var(--dark-color);margin-top:4px;">管理翻譯工具的文章列表</div>
            </a>
            <a href="<?= BASE_URL ?>/admin/users.php" class="card" style="text-decoration:none;cursor:pointer;transition:var(--transition);" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
                <i class="fas fa-users" style="font-size:1.8rem;color:#f0932b;margin-bottom:10px;display:block;"></i>
                <div style="font-weight:700;color:var(--text-color);">用戶管理</div>
                <div style="font-size:0.8rem;color:var(--dark-color);margin-top:4px;">查看用戶列表，設定管理員角色</div>
            </a>
            <a href="<?= BASE_URL ?>/admin/matching_data.php" class="card" style="text-decoration:none;cursor:pointer;transition:var(--transition);" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
                <i class="fas fa-puzzle-piece" style="font-size:1.8rem;color:var(--highlight-color);margin-bottom:10px;display:block;"></i>
                <div style="font-weight:700;color:var(--text-color);">配對遊戲題庫</div>
                <div style="font-size:0.8rem;color:var(--dark-color);margin-top:4px;">管理文言配對遊戲的字詞題組</div>
            </a>
            <a href="<?= BASE_URL ?>/social/index.php" class="card" style="text-decoration:none;cursor:pointer;transition:var(--transition);" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
                <i class="fas fa-comment-dots" style="font-size:1.8rem;color:var(--primary-color);margin-bottom:10px;display:block;"></i>
                <div style="font-weight:700;color:var(--text-color);">查看社群動態</div>
                <div style="font-size:0.8rem;color:var(--dark-color);margin-top:4px;">前往社群動態頁面</div>
            </a>
        </div>
    </div>

</div><!-- end page-container -->

<script>
// ── 手動觸發古人動態生成 ─────────────────────────────────────────────────
async function triggerPost(tutorId, tutorName) {
    const btn    = document.getElementById('btn-trigger-' + tutorId);
    const result = document.getElementById('trigger-result');

    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner"></span> 生成中…';
    result.style.display = 'none';

    try {
        const res = await fetch('<?= BASE_URL ?>/api/generate_tutor_post.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tutor_id: tutorId, mode: 'manual' })
        });
        const data = await res.json();

        result.style.display = 'block';

        if (data.success && !data.skipped) {
            result.className = 'alert alert-success';
            result.innerHTML = '<i class="fas fa-check-circle"></i> <strong>' + tutorName + '</strong> 的新動態已成功生成：「' +
                data.content.substring(0, 50) + (data.content.length > 50 ? '…' : '') + '」';

            // 更新上次生成時間顯示
            const lastEl = document.getElementById('last-post-' + tutorId);
            if (lastEl) lastEl.textContent = '上次生成：剛剛';
        } else if (data.skipped) {
            result.className = 'alert alert-warning';
            result.innerHTML = '<i class="fas fa-info-circle"></i> 已在間隔時間內，可在手動模式下強制生成。';
        } else {
            result.className = 'alert alert-error';
            result.innerHTML = '<i class="fas fa-exclamation-circle"></i> 生成失敗：' + (data.message || '未知錯誤');
        }
    } catch (err) {
        result.style.display = 'block';
        result.className = 'alert alert-error';
        result.innerHTML = '<i class="fas fa-exclamation-circle"></i> 網絡錯誤，請重試。';
    }

    btn.disabled  = false;
    btn.innerHTML = '<i class="fas fa-bolt"></i> 立即生成';
}
</script>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
