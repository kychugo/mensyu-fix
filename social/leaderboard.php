<?php
/**
 * 文樞 — 龍虎榜（用戶排行榜）
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$userId = getCurrentUserId();
$db     = getDB();

// ── 等級計算（與 dashboard.php 保持一致）────────────────────────────────
$levelDefs = [
    ['name' => '初學者', 'min' => 0,    'icon' => 'fa-seedling',       'color' => '#7dcea0'],
    ['name' => '學童',   'min' => 100,  'icon' => 'fa-book',           'color' => '#7fb3d5'],
    ['name' => '學士',   'min' => 300,  'icon' => 'fa-graduation-cap', 'color' => '#a9cce3'],
    ['name' => '文人',   'min' => 700,  'icon' => 'fa-feather',        'color' => '#f0932b'],
    ['name' => '大儒',   'min' => 1500, 'icon' => 'fa-crown',          'color' => '#f9ca24'],
];

function getUserLevel(int $xp, array $levelDefs): array {
    $current = $levelDefs[0];
    foreach ($levelDefs as $lv) {
        if ($xp >= $lv['min']) $current = $lv;
    }
    return $current;
}

// ── 取得前 30 名用戶 ──────────────────────────────────────────────────────
$topUsers = $db->query(
    'SELECT id, username, xp,
            (SELECT COUNT(*) FROM user_progress up WHERE up.user_id = users.id AND up.completed = 1) AS completed_levels
     FROM users
     WHERE role = "user"
     ORDER BY xp DESC, created_at ASC
     LIMIT 30'
)->fetchAll();

// ── 取得當前用戶排名 ──────────────────────────────────────────────────────
$myRankRow = $db->prepare(
    'SELECT COUNT(*) + 1 AS rank_pos FROM users WHERE role = "user" AND xp > (SELECT xp FROM users WHERE id = ?)'
);
$myRankRow->execute([$userId]);
$myRank = (int)$myRankRow->fetchColumn();

$myXp = getUserXp($userId);
$stMyLevels = $db->prepare('SELECT COUNT(*) FROM user_progress WHERE user_id = ? AND completed = 1');
$stMyLevels->execute([$userId]);
$myCompletedLevels = (int)$stMyLevels->fetchColumn();

$pageTitle  = '龍虎榜';
$activePage = 'leaderboard';
require __DIR__ . '/../includes/partials/header.php';
?>

<style>
.rank-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-light);
    overflow: hidden;
    transition: var(--transition);
}
.rank-card:hover { box-shadow: var(--shadow-medium); }

.rank-row {
    display: flex; align-items: center; gap: 16px;
    padding: 14px 20px;
    border-bottom: 1px solid rgba(127,179,213,0.1);
    transition: background 0.2s;
}
.rank-row:last-child { border-bottom: none; }
.rank-row:hover { background: rgba(127,179,213,0.04); }
.rank-row.is-me { background: rgba(127,179,213,0.08); border-left: 4px solid var(--primary-color); }

.rank-num {
    width: 36px; text-align: center; flex-shrink: 0;
    font-weight: 800; font-size: 1rem; color: var(--dark-color);
}
.rank-medal { font-size: 1.4rem; }

.rank-avatar {
    width: 42px; height: 42px; border-radius: 50%;
    background: var(--gradient-primary);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; color: white; font-size: 0.95rem; flex-shrink: 0;
}
.rank-info { flex: 1; min-width: 0; }
.rank-username { font-weight: 700; font-size: 0.95rem; }
.rank-level    { font-size: 0.78rem; color: var(--dark-color); }

.rank-xp {
    font-weight: 800; color: var(--primary-color);
    font-size: 1rem; white-space: nowrap;
}
.rank-xp small { font-weight: 400; font-size: 0.72rem; color: #aaa; display: block; text-align: right; }

.top3-card {
    border-radius: var(--border-radius);
    text-align: center; padding: 24px 16px;
    color: white; position: relative; overflow: hidden;
}
.top3-card::before {
    content: ''; position: absolute; inset: 0;
    background: rgba(0,0,0,0.08); pointer-events: none;
}
.top3-avatar {
    width: 64px; height: 64px; border-radius: 50%;
    background: rgba(255,255,255,0.3);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; font-weight: 800; color: white;
    margin: 0 auto 10px; border: 3px solid rgba(255,255,255,0.5);
}
.top3-medal    { font-size: 2rem; display: block; margin-bottom: 4px; }
.top3-username { font-weight: 800; font-size: 1.05rem; }
.top3-xp       { font-size: 0.88rem; opacity: 0.9; margin-top: 4px; }
.top3-level    { font-size: 0.78rem; opacity: 0.75; }

.my-rank-banner {
    background: var(--gradient-primary); color: white;
    border-radius: var(--border-radius); padding: 18px 24px;
    display: flex; align-items: center; gap: 16px;
    margin-bottom: 24px; flex-wrap: wrap;
}
.my-rank-num {
    font-size: 2.5rem; font-weight: 900; opacity: 0.95;
    line-height: 1; flex-shrink: 0;
}
.my-rank-info { flex: 1; }
</style>

<div class="page-container" style="max-width:800px;">

    <h1 class="page-title"><i class="fas fa-trophy" style="color:#f9ca24;margin-right:8px;"></i>龍虎榜</h1>
    <p class="page-subtitle">依學習 XP 排名，見賢思齊，與各路學子切磋共勉</p>

    <!-- 我的排名橫幅 -->
    <div class="my-rank-banner">
        <div class="my-rank-num">#<?= $myRank ?></div>
        <div class="my-rank-info">
            <div style="font-weight:700;font-size:1.05rem;margin-bottom:2px;">
                <?= e($_SESSION['username'] ?? '你') ?> 的排名
            </div>
            <?php $myLevel = getUserLevel($myXp, $levelDefs); ?>
            <div style="opacity:0.9;font-size:0.88rem;">
                <i class="fas <?= e($myLevel['icon']) ?>" style="margin-right:5px;"></i>
                <?= e($myLevel['name']) ?> ·
                <?= $myXp ?> XP ·
                已完成 <?= $myCompletedLevels ?> 關
            </div>
        </div>
        <a href="<?= BASE_URL ?>/learning/index.php" class="btn"
           style="background:rgba(255,255,255,0.2);color:white;border:2px solid rgba(255,255,255,0.4);padding:8px 18px;font-size:0.85rem;">
            <i class="fas fa-play"></i> 繼續學習
        </a>
    </div>

    <?php if (!empty($topUsers)): ?>

    <!-- 前三名特別展示 -->
    <?php if (count($topUsers) >= 1): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:24px;">
        <?php
        $top3Colors = [
            ['#f9ca24','#f0932b'], // 金
            ['#c0c0c0','#a0a0a0'], // 銀
            ['#cd7f32','#a05c20'], // 銅
        ];
        $top3Medals = ['🥇','🥈','🥉'];
        foreach (array_slice($topUsers, 0, min(3, count($topUsers))) as $i => $u):
            $lv = getUserLevel((int)$u['xp'], $levelDefs);
            $isMe = ((int)$u['id'] === $userId);
            $col1 = $top3Colors[$i][0]; $col2 = $top3Colors[$i][1];
        ?>
        <div class="top3-card" style="background:linear-gradient(135deg,<?= $col1 ?>,<?= $col2 ?>);">
            <span class="top3-medal"><?= $top3Medals[$i] ?></span>
            <div class="top3-avatar">
                <?= mb_substr($u['username'], 0, 1) ?>
            </div>
            <div class="top3-username">
                <?= e($u['username']) ?>
                <?php if ($isMe): ?><span style="font-size:0.72rem;opacity:0.85;"> (你)</span><?php endif; ?>
            </div>
            <div class="top3-xp"><?= $u['xp'] ?> XP</div>
            <div class="top3-level">
                <i class="fas <?= e($lv['icon']) ?>" style="margin-right:3px;"></i><?= e($lv['name']) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 完整排名列表 -->
    <div class="rank-card">
        <div style="padding:14px 20px;border-bottom:1px solid rgba(127,179,213,0.1);font-size:0.82rem;font-weight:700;color:var(--dark-color);display:flex;gap:16px;">
            <span style="width:36px;text-align:center;">#</span>
            <span style="width:42px;flex-shrink:0;"></span>
            <span style="flex:1;">學子</span>
            <span style="white-space:nowrap;">XP</span>
        </div>
        <?php foreach ($topUsers as $rank => $u):
            $lv   = getUserLevel((int)$u['xp'], $levelDefs);
            $isMe = ((int)$u['id'] === $userId);
            $rankNum = $rank + 1;
        ?>
        <div class="rank-row <?= $isMe ? 'is-me' : '' ?>">
            <div class="rank-num">
                <?php if ($rankNum === 1): ?>
                    <span class="rank-medal">🥇</span>
                <?php elseif ($rankNum === 2): ?>
                    <span class="rank-medal">🥈</span>
                <?php elseif ($rankNum === 3): ?>
                    <span class="rank-medal">🥉</span>
                <?php else: ?>
                    <?= $rankNum ?>
                <?php endif; ?>
            </div>
            <div class="rank-avatar" style="background:linear-gradient(135deg,<?= $lv['color'] ?>,<?= $lv['color'] ?>aa);">
                <?= mb_substr($u['username'], 0, 1) ?>
            </div>
            <div class="rank-info">
                <div class="rank-username">
                    <?= e($u['username']) ?>
                    <?php if ($isMe): ?><span class="badge badge-primary" style="margin-left:6px;font-size:0.68rem;">你</span><?php endif; ?>
                </div>
                <div class="rank-level">
                    <i class="fas <?= e($lv['icon']) ?>" style="color:<?= e($lv['color']) ?>;margin-right:3px;"></i>
                    <?= e($lv['name']) ?> · <?= $u['completed_levels'] ?> 關已完成
                </div>
            </div>
            <div class="rank-xp">
                <?= number_format($u['xp']) ?>
                <small>XP</small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 若當前用戶不在前 30 名，額外顯示 -->
    <?php if ($myRank > 30): ?>
    <div style="margin-top:12px;padding:12px 20px;background:rgba(127,179,213,0.06);border-radius:var(--border-radius);display:flex;align-items:center;gap:16px;border:1px dashed rgba(127,179,213,0.3);">
        <div class="rank-num" style="font-size:1rem;font-weight:700;color:var(--primary-color);">#<?= $myRank ?></div>
        <div class="rank-avatar" style="background:var(--gradient-primary);"><?= mb_substr($_SESSION['username'] ?? '?', 0, 1) ?></div>
        <div class="rank-info">
            <div class="rank-username"><?= e($_SESSION['username'] ?? '你') ?> <span class="badge badge-primary" style="font-size:0.68rem;">你</span></div>
            <?php $myLv = getUserLevel($myXp, $levelDefs); ?>
            <div class="rank-level"><i class="fas <?= e($myLv['icon']) ?>" style="color:<?= e($myLv['color']) ?>;margin-right:3px;"></i><?= e($myLv['name']) ?></div>
        </div>
        <div class="rank-xp"><?= number_format($myXp) ?><small>XP</small></div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="card" style="text-align:center;padding:48px;">
        <i class="fas fa-trophy" style="font-size:2.5rem;color:rgba(127,179,213,0.3);margin-bottom:16px;display:block;"></i>
        <p style="color:var(--dark-color);">尚無排名資料。完成學習關卡獲得 XP，即可登上龍虎榜！</p>
        <a href="<?= BASE_URL ?>/learning/index.php" class="btn btn-primary" style="margin-top:16px;">
            <i class="fas fa-play"></i> 開始學習
        </a>
    </div>
    <?php endif; ?>

    <!-- 說明 -->
    <div class="alert alert-info" style="margin-top:20px;font-size:0.83rem;">
        <i class="fas fa-info-circle"></i>
        完成學習關卡可獲得 XP。通過測驗將獲得更多 XP 獎勵，努力學習登上榜首！
    </div>

</div>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
