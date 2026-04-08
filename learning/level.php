<?php
/**
 * 文樞 — 學習關卡頁
 * 顯示文章、AI 翻譯解析入口，及進入測驗按鈕
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$userId  = getCurrentUserId();
$db      = getDB();
$tutorId = (int)($_GET['tutor_id'] ?? 0);
$levelId = (int)($_GET['level_id'] ?? 0);

// 取得導師
$st = $db->prepare('SELECT * FROM tutors WHERE id = ? AND is_active = 1');
$st->execute([$tutorId]);
$tutor = $st->fetch();
if (!$tutor) {
    header('Location: ' . BASE_URL . '/learning/index.php');
    exit;
}

// 若未指定 level_id，找該導師最小未完成關卡（或第一關）
if ($levelId === 0) {
    $progress = getUserProgress($userId);
    $stLv = $db->prepare('SELECT id FROM levels WHERE tutor_id = ? AND is_active = 1 ORDER BY level_number ASC');
    $stLv->execute([$tutorId]);
    $allLevels = $stLv->fetchAll();

    foreach ($allLevels as $lv) {
        $lvProg = $progress[$tutorId][$lv['id']] ?? null;
        if (!$lvProg || !$lvProg['completed']) {
            $levelId = $lv['id'];
            break;
        }
    }
    if ($levelId === 0 && !empty($allLevels)) {
        $levelId = $allLevels[0]['id']; // 全部完成時，重溫第一關
    }
    if ($levelId === 0) {
        header('Location: ' . BASE_URL . '/learning/index.php');
        exit;
    }
}

// 取得關卡
$st = $db->prepare('SELECT * FROM levels WHERE id = ? AND tutor_id = ? AND is_active = 1');
$st->execute([$levelId, $tutorId]);
$level = $st->fetch();
if (!$level) {
    header('Location: ' . BASE_URL . '/learning/index.php');
    exit;
}

// 此關所有關卡列表（導航用）
$stAll = $db->prepare('SELECT id, level_number, difficulty, essay_title FROM levels WHERE tutor_id = ? AND is_active = 1 ORDER BY level_number ASC');
$stAll->execute([$tutorId]);
$allLevels = $stAll->fetchAll();

// 本關進度
$progress = getUserProgress($userId);
$lvProg   = $progress[$tutorId][$levelId] ?? null;
$isCompleted = $lvProg && $lvProg['completed'];

$gradients = ['gradient-primary'=>'var(--gradient-primary)','gradient-secondary'=>'var(--gradient-secondary)','gradient-accent'=>'var(--gradient-accent)'];
$gradStyle = $gradients[$tutor['gradient_class'] ?? 'gradient-primary'] ?? 'var(--gradient-primary)';

$pageTitle  = $level['essay_title'];
$activePage = 'learning';
require __DIR__ . '/../includes/partials/header.php';
?>

<style>
.essay-content {
    font-family: 'Noto Serif TC', serif;
    font-size: 1.05rem;
    line-height: 2.2;
    letter-spacing: 0.05em;
    color: var(--text-color);
    white-space: pre-wrap;
}
.level-nav-item {
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 0.82rem;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
    display: block;
    color: var(--dark-color);
}
.level-nav-item:hover  { background: rgba(127,179,213,0.1); color: var(--primary-color); }
.level-nav-item.active { background: var(--gradient-primary); color: white; }
.level-nav-item.done   { color: var(--success-color); }
</style>

<div class="page-container">
    <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;">

        <!-- 左側：關卡導航 -->
        <div style="width:220px;flex-shrink:0;">
            <div class="card" style="padding:16px;position:sticky;top:76px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                    <div style="width:36px;height:36px;border-radius:50%;background:<?= $gradStyle ?>;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
                        <?php if (!empty($tutor['avatar_url'])): ?>
                        <img src="<?= e($tutor['avatar_url']) ?>" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none'">
                        <?php else: ?>
                        <i class="fas fa-user-tie" style="color:white;font-size:0.9rem;"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:0.9rem;"><?= e($tutor['name']) ?></div>
                        <div style="font-size:0.75rem;color:var(--dark-color);"><?= e($tutor['dynasty']) ?></div>
                    </div>
                </div>
                <div style="font-size:0.78rem;font-weight:600;color:var(--dark-color);margin-bottom:8px;">所有關卡</div>
                <?php foreach ($allLevels as $lv):
                    $isActive    = ($lv['id'] === $levelId);
                    $isDone      = isset($progress[$tutorId][$lv['id']]) && $progress[$tutorId][$lv['id']]['completed'];
                ?>
                <a href="<?= BASE_URL ?>/learning/level.php?tutor_id=<?= $tutorId ?>&level_id=<?= $lv['id'] ?>"
                   class="level-nav-item <?= $isActive ? 'active' : ($isDone ? 'done' : '') ?>">
                    <?php if ($isDone && !$isActive): ?>
                    <i class="fas fa-check-circle" style="margin-right:4px;"></i>
                    <?php else: ?>
                    <i class="fas fa-book-open" style="margin-right:4px;opacity:0.5;"></i>
                    <?php endif; ?>
                    第<?= $lv['level_number'] ?>關 <?= e($lv['essay_title']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 右側：主內容 -->
        <div style="flex:1;min-width:0;">

            <!-- 關卡標題 -->
            <div class="card" style="background:<?= $gradStyle ?>;color:white;margin-bottom:20px;padding:28px;">
                <div style="font-size:0.82rem;opacity:0.8;margin-bottom:6px;">
                    第 <?= $level['level_number'] ?> 關 · <?= e($level['difficulty']) ?>
                </div>
                <h1 style="font-family:'Noto Serif TC',serif;font-size:1.8rem;font-weight:700;margin-bottom:4px;">
                    <?= e($level['essay_title']) ?>
                </h1>
                <div style="font-size:0.88rem;opacity:0.85;">
                    <?= e($level['essay_author']) ?> · <?= e($tutor['dynasty']) ?>
                </div>
                <?php if ($isCompleted): ?>
                <span style="display:inline-flex;align-items:center;gap:4px;margin-top:10px;background:rgba(255,255,255,0.25);padding:4px 14px;border-radius:999px;font-size:0.8rem;">
                    <i class="fas fa-trophy"></i> 已完成（得分 <?= $lvProg['score'] ?>分）
                </span>
                <?php endif; ?>
            </div>

            <!-- 文章內容 -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-title">
                    <i class="fas fa-book-open" style="color:var(--primary-color);"></i>
                    原文
                </div>
                <?php if (!empty($level['notes'])): ?>
                <div class="alert alert-info" style="margin-bottom:16px;font-size:0.85rem;">
                    <i class="fas fa-lightbulb"></i> <?= e($level['notes']) ?>
                </div>
                <?php endif; ?>
                <div class="essay-content"><?= e($level['essay_content']) ?></div>
            </div>

            <!-- AI 翻譯解析區 -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-title">
                    <i class="fas fa-language" style="color:var(--highlight-color);"></i>
                    AI 翻譯解析
                </div>
                <p style="font-size:0.85rem;color:var(--dark-color);margin-bottom:16px;">
                    點擊下方按鈕，AI 將為你提供逐字解析及現代白話翻譯。
                </p>
                <div id="translation-area" style="display:none;" class="alert alert-info" style="white-space:pre-wrap;"></div>
                <button class="btn btn-outline" id="translate-btn"
                        onclick="loadTranslation(<?= $levelId ?>, <?= json_encode($level['essay_title']) ?>, <?= json_encode($level['essay_content']) ?>)">
                    <i class="fas fa-magic"></i> AI 解析此文
                </button>
            </div>

            <!-- 進入測驗 -->
            <div class="card" style="text-align:center;padding:32px;">
                <i class="fas fa-pencil-alt" style="font-size:2rem;color:var(--primary-color);margin-bottom:12px;display:block;"></i>
                <h2 style="font-size:1.1rem;font-weight:700;margin-bottom:8px;">準備好了嗎？</h2>
                <p style="font-size:0.88rem;color:var(--dark-color);margin-bottom:20px;">
                    完成 AI 生成的選擇題測驗，得分 <?= QUIZ_PASS_SCORE ?> 分以上即可通關！
                </p>
                <a href="<?= BASE_URL ?>/learning/quiz.php?tutor_id=<?= $tutorId ?>&level_id=<?= $levelId ?>"
                   class="btn btn-primary" style="font-size:1rem;padding:12px 32px;">
                    <i class="fas fa-arrow-right"></i>
                    <?= $isCompleted ? '重新挑戰測驗' : '開始測驗' ?>
                </a>
            </div>

        </div><!-- end right -->
    </div>
</div>

<script>
async function loadTranslation(levelId, title, content) {
    const btn  = document.getElementById('translate-btn');
    const area = document.getElementById('translation-area');

    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner"></span> AI 解析中，請稍候…';
    area.style.display = 'block';
    area.textContent   = '正在生成逐字解析，可能需要數十秒，請耐心等待…';

    try {
        const res = await fetch('<?= BASE_URL ?>/api/translate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ level_id: levelId, title: title, content: content })
        });
        const data = await res.json();

        if (data.success) {
            area.style.whiteSpace = 'pre-wrap';
            area.innerHTML = data.html || data.content.replace(/</g,'&lt;').replace(/>/g,'&gt;');
            area.className = 'alert alert-info';
        } else {
            area.className  = 'alert alert-error';
            area.textContent = '翻譯失敗：' + (data.message || '請稍後再試');
        }
    } catch (e) {
        area.className  = 'alert alert-error';
        area.textContent = '網絡錯誤，請重試。';
    }

    btn.disabled  = false;
    btn.innerHTML = '<i class="fas fa-magic"></i> 重新解析';
}
</script>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
