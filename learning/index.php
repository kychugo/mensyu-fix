<?php
/**
 * 文樞 — 學習模組：導師選擇頁
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$userId      = getCurrentUserId();
$db          = getDB();
$tutors      = getActiveTutors();
$progress    = getUserProgress($userId);
$unlockedIds = getUnlockedTutorIds($userId);

// 各導師完成情況
$tutorStats = [];
foreach ($tutors as $t) {
    $tid    = $t['id'];
    $stLv   = $db->prepare('SELECT COUNT(*) FROM levels WHERE tutor_id = ? AND is_active = 1');
    $stLv->execute([$tid]);
    $total  = (int)$stLv->fetchColumn();

    $done   = 0;
    if (isset($progress[$tid])) {
        foreach ($progress[$tid] as $lv) {
            if ($lv['completed']) $done++;
        }
    }
    $tutorStats[$tid] = ['total' => $total, 'done' => $done];
}

$pageTitle  = '選擇導師';
$activePage = 'learning';
require __DIR__ . '/../includes/partials/header.php';
?>

<div class="page-container">
    <h1 class="page-title"><i class="fas fa-layer-group" style="color:var(--primary-color);margin-right:8px;"></i>選擇文學導師</h1>
    <p class="page-subtitle">每位導師有多個關卡，完成第一關即可解鎖他的社群互動</p>

    <?php if (empty($tutors)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        管理員尚未新增任何文學導師，敬請期待！
    </div>
    <?php else: ?>
    <div class="grid-2">
        <?php foreach ($tutors as $tutor):
            $tid       = $tutor['id'];
            $total     = $tutorStats[$tid]['total'];
            $done      = $tutorStats[$tid]['done'];
            $pct       = $total > 0 ? (int)($done / $total * 100) : 0;
            $unlocked  = in_array($tid, $unlockedIds);
            $gradients = ['gradient-primary'=>'var(--gradient-primary)','gradient-secondary'=>'var(--gradient-secondary)','gradient-accent'=>'var(--gradient-accent)'];
            $gradStyle = $gradients[$tutor['gradient_class'] ?? 'gradient-primary'] ?? 'var(--gradient-primary)';
        ?>
        <div class="card" style="overflow:hidden;transition:var(--transition);" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='var(--shadow-heavy)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
            <!-- 導師頭部 -->
            <div style="background:<?= $gradStyle ?>;padding:24px;color:white;display:flex;align-items:center;gap:16px;margin:-24px -24px 20px;">
                <div style="width:64px;height:64px;border-radius:50%;border:3px solid rgba(255,255,255,0.7);overflow:hidden;flex-shrink:0;background:rgba(255,255,255,0.2);">
                    <?php if (!empty($tutor['avatar_url'])): ?>
                    <img src="<?= e($tutor['avatar_url']) ?>" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none'">
                    <?php else: ?>
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;"><i class="fas fa-user-tie" style="font-size:1.6rem;"></i></div>
                    <?php endif; ?>
                </div>
                <div>
                    <div style="font-family:'Noto Serif TC',serif;font-size:1.4rem;font-weight:700;"><?= e($tutor['name']) ?></div>
                    <div style="font-size:0.85rem;opacity:0.85;"><?= e($tutor['dynasty']) ?></div>
                </div>
                <?php if ($unlocked): ?>
                <span style="margin-left:auto;background:rgba(255,255,255,0.25);padding:4px 12px;border-radius:999px;font-size:0.78rem;">
                    <i class="fas fa-check-circle"></i> 已解鎖社群
                </span>
                <?php endif; ?>
            </div>

            <p style="font-size:0.88rem;color:var(--dark-color);margin-bottom:16px;"><?= e($tutor['description'] ?? '') ?></p>

            <!-- 進度 -->
            <div style="margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;font-size:0.82rem;color:var(--dark-color);margin-bottom:5px;">
                    <span>學習進度</span>
                    <span><?= $done ?> / <?= $total ?> 關完成</span>
                </div>
                <div style="height:6px;background:rgba(127,179,213,0.15);border-radius:3px;overflow:hidden;">
                    <div style="height:100%;width:<?= $pct ?>%;background:<?= $gradStyle ?>;border-radius:3px;"></div>
                </div>
            </div>

            <a href="<?= BASE_URL ?>/learning/level.php?tutor_id=<?= $tid ?>"
               class="btn btn-primary" style="width:100%;justify-content:center;">
                <?php if ($done === 0): ?>
                <i class="fas fa-play"></i> 開始學習
                <?php elseif ($done < $total): ?>
                <i class="fas fa-arrow-right"></i> 繼續學習
                <?php else: ?>
                <i class="fas fa-trophy"></i> 全部完成（重溫）
                <?php endif; ?>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
