<?php
/**
 * 文樞 — 龍虎榜
 *
 * 顯示：
 *  - 全平台用戶 XP 排行榜（前 20 名）
 *  - 學習進度最高排行
 *  - 社群互動排行（留言最多）
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$db     = getDB();
$userId = getCurrentUserId();

// ── XP 排行榜 (前 20) ────────────────────────────────────────────────────
$xpRanking = $db->query(
    'SELECT id, username, xp, created_at
     FROM users
     ORDER BY xp DESC, created_at ASC
     LIMIT 20'
)->fetchAll();

// ── 學習完成關卡數排行 (前 20) ───────────────────────────────────────────
$levelRanking = $db->query(
    'SELECT u.id, u.username, COUNT(up.id) AS completed_count
     FROM users u
     LEFT JOIN user_progress up ON u.id = up.user_id AND up.completed = 1
     GROUP BY u.id, u.username
     ORDER BY completed_count DESC, u.xp DESC
     LIMIT 20'
)->fetchAll();

// ── 社群活躍排行（留言 + 帖子最多） (前 20) ─────────────────────────────
$socialRanking = $db->query(
    "SELECT u.id, u.username,
            COALESCE(p.post_count, 0) AS post_count,
            COALESCE(c.comment_count, 0) AS comment_count,
            (COALESCE(p.post_count, 0) + COALESCE(c.comment_count, 0)) AS total_activity
     FROM users u
     LEFT JOIN (SELECT user_id, COUNT(*) AS post_count FROM social_posts WHERE author_type='user' GROUP BY user_id) p ON u.id = p.user_id
     LEFT JOIN (SELECT user_id, COUNT(*) AS comment_count FROM social_comments WHERE author_type='user' GROUP BY user_id) c ON u.id = c.user_id
     ORDER BY total_activity DESC, u.xp DESC
     LIMIT 20"
)->fetchAll();

// ── 當前用戶排名 ──────────────────────────────────────────────────────────
$myXpRank = (int)$db->prepare(
    'SELECT COUNT(*) + 1 FROM users WHERE xp > (SELECT xp FROM users WHERE id = ?)'
)->execute([$userId]) ? null : null;
$stMyRank = $db->prepare('SELECT COUNT(*) + 1 AS rank FROM users WHERE xp > (SELECT xp FROM users WHERE id = ?)');
$stMyRank->execute([$userId]);
$myXpRank = (int)$stMyRank->fetchColumn();

$stMyXp = $db->prepare('SELECT xp FROM users WHERE id = ?');
$stMyXp->execute([$userId]);
$myXp = (int)$stMyXp->fetchColumn();

// ── 各獎章門檻 ────────────────────────────────────────────────────────────
$medals = [
    ['icon' => '🥇', 'class' => 'gold',   'color' => '#f1c40f'],
    ['icon' => '🥈', 'class' => 'silver', 'color' => '#95a5a6'],
    ['icon' => '🥉', 'class' => 'bronze', 'color' => '#cd7f32'],
];

$pageTitle  = '龍虎榜';
$activePage = 'social';
require __DIR__ . '/../includes/partials/header.php';
?>

<style>
.leaderboard-tabs {
    display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap;
}
.tab-btn {
    padding: 8px 20px; border-radius: 20px; cursor: pointer;
    font-size: 0.88rem; font-weight: 600; border: 2px solid rgba(127,179,213,0.3);
    background: white; color: var(--dark-color); transition: var(--transition); font-family: inherit;
}
.tab-btn.active, .tab-btn:hover {
    background: var(--gradient-primary); color: white; border-color: transparent;
}
.tab-content { display: none; }
.tab-content.active { display: block; }

.rank-item {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 18px; border-radius: 10px;
    background: white; box-shadow: var(--shadow-light);
    margin-bottom: 10px; transition: var(--transition);
}
.rank-item:hover { box-shadow: var(--shadow-medium); transform: translateX(4px); }
.rank-item.is-me { background: linear-gradient(135deg, rgba(127,179,213,0.1), rgba(162,217,206,0.1)); border: 2px solid rgba(127,179,213,0.3); }
.rank-num {
    width: 36px; height: 36px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 0.9rem; flex-shrink: 0;
    background: rgba(127,179,213,0.12); color: var(--dark-color);
}
.rank-num.top1 { background: linear-gradient(135deg, #f1c40f, #f39c12); color: white; font-size: 1.1rem; }
.rank-num.top2 { background: linear-gradient(135deg, #95a5a6, #7f8c8d); color: white; }
.rank-num.top3 { background: linear-gradient(135deg, #cd7f32, #a0522d); color: white; }

.rank-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: var(--gradient-primary);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; color: white; flex-shrink: 0;
}
.rank-info { flex: 1; min-width: 0; }
.rank-name { font-weight: 600; font-size: 0.9rem; color: var(--text-color); }
.rank-sub  { font-size: 0.78rem; color: var(--dark-color); }
.rank-score { font-weight: 800; font-size: 1rem; color: var(--primary-color); white-space: nowrap; }

.my-rank-card {
    background: linear-gradient(135deg, rgba(127,179,213,0.12), rgba(162,217,206,0.1));
    border: 2px solid rgba(127,179,213,0.3);
    border-radius: var(--border-radius); padding: 20px;
    display: flex; align-items: center; gap: 16px;
    margin-bottom: 24px; flex-wrap: wrap;
}
.trophy-bg {
    font-size: 3rem; line-height: 1; flex-shrink: 0;
}
</style>

<div class="page-container" style="max-width:760px;">

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
        <div>
            <h1 class="page-title"><i class="fas fa-trophy" style="color:var(--warning-color);margin-right:8px;"></i>龍虎榜</h1>
            <p class="page-subtitle">學習、活躍、成就全榜在此，你排第幾？</p>
        </div>
        <a href="<?= BASE_URL ?>/social/index.php" class="btn btn-outline" style="padding:7px 16px;font-size:0.85rem;">
            <i class="fas fa-arrow-left"></i> 返回社群
        </a>
    </div>

    <!-- 我的排名卡 -->
    <div class="my-rank-card">
        <div class="trophy-bg">🏆</div>
        <div>
            <div style="font-weight:700;font-size:1rem;color:var(--text-color);">你的全站 XP 排名</div>
            <div style="font-size:1.6rem;font-weight:800;color:var(--primary-color);">第 <?= $myXpRank ?> 名</div>
            <div style="font-size:0.85rem;color:var(--dark-color);">目前 XP：<strong><?= number_format($myXp) ?></strong></div>
        </div>
        <?php if ($myXpRank === 1): ?>
        <div style="margin-left:auto;font-size:2rem;">👑</div>
        <?php elseif ($myXpRank <= 3): ?>
        <div style="margin-left:auto;font-size:2rem;"><?= $medals[$myXpRank - 1]['icon'] ?></div>
        <?php endif; ?>
    </div>

    <!-- 分頁標籤 -->
    <div class="leaderboard-tabs">
        <button class="tab-btn active" onclick="switchTab('xp', this)">
            <i class="fas fa-star" style="margin-right:5px;"></i>XP 排行
        </button>
        <button class="tab-btn" onclick="switchTab('level', this)">
            <i class="fas fa-layer-group" style="margin-right:5px;"></i>學習達人
        </button>
        <button class="tab-btn" onclick="switchTab('social', this)">
            <i class="fas fa-comments" style="margin-right:5px;"></i>社群活躍
        </button>
    </div>

    <!-- XP 排行榜 -->
    <div id="tab-xp" class="tab-content active">
        <div class="card" style="padding:16px;margin-bottom:12px;">
            <div class="card-title" style="margin-bottom:12px;">
                <i class="fas fa-star" style="color:var(--warning-color);"></i> XP 積分排行
            </div>
            <?php if (empty($xpRanking)): ?>
            <p style="color:var(--dark-color);font-size:0.9rem;">尚無排行資料</p>
            <?php else: ?>
            <?php foreach ($xpRanking as $i => $row):
                $rank = $i + 1;
                $isMe = ($row['id'] == $userId);
                $rankClass = $rank === 1 ? 'top1' : ($rank === 2 ? 'top2' : ($rank === 3 ? 'top3' : ''));
                $rankLabel = $rank <= 3 ? $medals[$rank - 1]['icon'] : $rank;
            ?>
            <div class="rank-item <?= $isMe ? 'is-me' : '' ?>">
                <div class="rank-num <?= $rankClass ?>"><?= $rankLabel ?></div>
                <div class="rank-avatar" style="<?= $isMe ? 'background:var(--gradient-secondary);' : '' ?>">
                    <?= mb_substr(e($row['username']), 0, 1) ?>
                </div>
                <div class="rank-info">
                    <div class="rank-name"><?= e($row['username']) ?><?= $isMe ? ' <span style="font-size:0.75rem;color:var(--primary-color);">（你）</span>' : '' ?></div>
                    <div class="rank-sub">累積 XP</div>
                </div>
                <div class="rank-score"><?= number_format($row['xp']) ?> <span style="font-size:0.75rem;font-weight:400;">XP</span></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 學習達人排行 -->
    <div id="tab-level" class="tab-content">
        <div class="card" style="padding:16px;margin-bottom:12px;">
            <div class="card-title" style="margin-bottom:12px;">
                <i class="fas fa-layer-group" style="color:var(--highlight-color);"></i> 學習達人排行（完成關卡數）
            </div>
            <?php if (empty($levelRanking)): ?>
            <p style="color:var(--dark-color);font-size:0.9rem;">尚無排行資料</p>
            <?php else: ?>
            <?php foreach ($levelRanking as $i => $row):
                $rank = $i + 1;
                $isMe = ($row['id'] == $userId);
                $rankClass = $rank === 1 ? 'top1' : ($rank === 2 ? 'top2' : ($rank === 3 ? 'top3' : ''));
                $rankLabel = $rank <= 3 ? $medals[$rank - 1]['icon'] : $rank;
            ?>
            <div class="rank-item <?= $isMe ? 'is-me' : '' ?>">
                <div class="rank-num <?= $rankClass ?>"><?= $rankLabel ?></div>
                <div class="rank-avatar" style="<?= $isMe ? 'background:var(--gradient-secondary);' : '' ?>">
                    <?= mb_substr(e($row['username']), 0, 1) ?>
                </div>
                <div class="rank-info">
                    <div class="rank-name"><?= e($row['username']) ?><?= $isMe ? ' <span style="font-size:0.75rem;color:var(--primary-color);">（你）</span>' : '' ?></div>
                    <div class="rank-sub">完成關卡</div>
                </div>
                <div class="rank-score"><?= (int)$row['completed_count'] ?> <span style="font-size:0.75rem;font-weight:400;">關</span></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 社群活躍排行 -->
    <div id="tab-social" class="tab-content">
        <div class="card" style="padding:16px;margin-bottom:12px;">
            <div class="card-title" style="margin-bottom:12px;">
                <i class="fas fa-comments" style="color:var(--primary-color);"></i> 社群活躍排行（帖子 + 留言）
            </div>
            <?php if (empty($socialRanking)): ?>
            <p style="color:var(--dark-color);font-size:0.9rem;">尚無排行資料</p>
            <?php else: ?>
            <?php foreach ($socialRanking as $i => $row):
                $rank = $i + 1;
                $isMe = ($row['id'] == $userId);
                $rankClass = $rank === 1 ? 'top1' : ($rank === 2 ? 'top2' : ($rank === 3 ? 'top3' : ''));
                $rankLabel = $rank <= 3 ? $medals[$rank - 1]['icon'] : $rank;
                if ($row['total_activity'] == 0) continue;
            ?>
            <div class="rank-item <?= $isMe ? 'is-me' : '' ?>">
                <div class="rank-num <?= $rankClass ?>"><?= $rankLabel ?></div>
                <div class="rank-avatar" style="<?= $isMe ? 'background:var(--gradient-secondary);' : '' ?>">
                    <?= mb_substr(e($row['username']), 0, 1) ?>
                </div>
                <div class="rank-info">
                    <div class="rank-name"><?= e($row['username']) ?><?= $isMe ? ' <span style="font-size:0.75rem;color:var(--primary-color);">（你）</span>' : '' ?></div>
                    <div class="rank-sub"><?= (int)$row['post_count'] ?> 帖 · <?= (int)$row['comment_count'] ?> 留言</div>
                </div>
                <div class="rank-score"><?= (int)$row['total_activity'] ?> <span style="font-size:0.75rem;font-weight:400;">次</span></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
function switchTab(tab, btn) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    const content = document.getElementById('tab-' + tab);
    if (content) content.classList.add('active');
    if (btn) btn.classList.add('active');
}
</script>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
