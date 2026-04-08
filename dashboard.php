<?php
/**
 * 文樞 — 用戶主頁（Dashboard）
 * 登入後的個人學習概覽頁面
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$userId   = getCurrentUserId();
$username = $_SESSION['username'] ?? '用戶';

// 取得學習進度
$progress  = getUserProgress($userId);
$tutors    = getActiveTutors();
$unlockedIds = getUnlockedTutorIds($userId);
$xp        = getUserXp($userId);
$stats     = getPlatformStats();

// 計算本人統計
$completedLevels = 0;
$totalAttempts   = 0;
foreach ($progress as $tidData) {
    foreach ($tidData as $lvData) {
        if ($lvData['completed']) $completedLevels++;
        $totalAttempts += $lvData['attempts'];
    }
}

// 最近社群動態（最新 3 篇）
try {
    $db = getDB();
    $recentPosts = $db->query(
        'SELECT sp.*, 
                COALESCE(u.username, t.name) AS author_name,
                t.name AS tutor_name,
                t.gradient_class
         FROM social_posts sp
         LEFT JOIN users  u ON sp.user_id  = u.id
         LEFT JOIN tutors t ON sp.tutor_id = t.id
         ORDER BY sp.created_at DESC LIMIT 3'
    )->fetchAll();
} catch (PDOException $e) {
    $recentPosts = [];
}

// 計算等級
function calcLevel(int $xp): array {
    $levels = [
        ['name' => '初學者', 'min' => 0,    'icon' => 'fa-seedling',      'color' => '#7dcea0'],
        ['name' => '學童',   'min' => 100,  'icon' => 'fa-book',          'color' => '#7fb3d5'],
        ['name' => '學士',   'min' => 300,  'icon' => 'fa-graduation-cap','color' => '#a9cce3'],
        ['name' => '文人',   'min' => 700,  'icon' => 'fa-feather',       'color' => '#f0932b'],
        ['name' => '大儒',   'min' => 1500, 'icon' => 'fa-crown',         'color' => '#f9ca24'],
    ];
    $current = $levels[0];
    $next    = $levels[1] ?? null;
    foreach ($levels as $i => $lv) {
        if ($xp >= $lv['min']) {
            $current = $lv;
            $next    = $levels[$i + 1] ?? null;
        }
    }
    $progress = 0;
    if ($next) {
        $range    = $next['min'] - $current['min'];
        $progress = min(100, (int)(($xp - $current['min']) / $range * 100));
    } else {
        $progress = 100;
    }
    return compact('current', 'next', 'progress');
}

$levelInfo = calcLevel($xp);

$pageTitle  = '我的主頁';
$activePage = 'home';
require __DIR__ . '/includes/partials/header.php';
?>

<div class="page-container">

    <!-- 歡迎橫幅 -->
    <div class="card" style="background: var(--gradient-primary); color: white; margin-bottom: 24px; position: relative; overflow: hidden;">
        <div style="position:absolute;top:-30px;right:-30px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,0.07);"></div>
        <div style="position:relative;z-index:1;">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                <div>
                    <h1 style="font-family:'Noto Serif TC',serif;font-size:1.6rem;font-weight:700;margin-bottom:4px;">
                        你好，<?= e($username) ?>！
                    </h1>
                    <p style="opacity:0.9;font-size:0.92rem;">
                        <?= date('Y年n月j日') ?>，繼續你的文言文學習之旅
                    </p>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:2rem;font-weight:700;"><?= $xp ?></div>
                    <div style="font-size:0.8rem;opacity:0.9;">學習 XP</div>
                </div>
            </div>

            <!-- 等級進度條 -->
            <div style="margin-top:16px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                    <span style="font-size:0.85rem;opacity:0.9;">
                        <i class="fas <?= e($levelInfo['current']['icon']) ?>" style="margin-right:5px;"></i>
                        <?= e($levelInfo['current']['name']) ?>
                    </span>
                    <?php if ($levelInfo['next']): ?>
                    <span style="font-size:0.8rem;opacity:0.75;">
                        下一等級：<?= e($levelInfo['next']['name']) ?>（需 <?= $levelInfo['next']['min'] ?> XP）
                    </span>
                    <?php else: ?>
                    <span style="font-size:0.8rem;opacity:0.75;">最高等級</span>
                    <?php endif; ?>
                </div>
                <div style="height:6px;background:rgba(255,255,255,0.25);border-radius:3px;overflow:hidden;">
                    <div style="height:100%;width:<?= $levelInfo['progress'] ?>%;background:white;border-radius:3px;transition:width 1s ease;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 統計數字 -->
    <div class="grid-4" style="margin-bottom:24px;">
        <div class="card" style="text-align:center;padding:20px;">
            <div style="font-size:1.8rem;font-weight:700;color:var(--primary-color);"><?= $completedLevels ?></div>
            <div style="font-size:0.82rem;color:var(--dark-color);">完成關卡</div>
        </div>
        <div class="card" style="text-align:center;padding:20px;">
            <div style="font-size:1.8rem;font-weight:700;color:var(--highlight-color);"><?= count($unlockedIds) ?></div>
            <div style="font-size:0.82rem;color:var(--dark-color);">解鎖導師</div>
        </div>
        <div class="card" style="text-align:center;padding:20px;">
            <div style="font-size:1.8rem;font-weight:700;color:var(--accent-color);"><?= $totalAttempts ?></div>
            <div style="font-size:0.82rem;color:var(--dark-color);">挑戰次數</div>
        </div>
        <div class="card" style="text-align:center;padding:20px;">
            <div style="font-size:1.8rem;font-weight:700;" style="color:#f0932b;"><?= $stats['users'] ?></div>
            <div style="font-size:0.82rem;color:var(--dark-color);">平台用戶</div>
        </div>
    </div>

    <div class="grid-2">

        <!-- 我的導師進度 -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-layer-group" style="color:var(--primary-color);"></i>
                我的學習進度
            </div>
            <?php if (empty($tutors)): ?>
            <div class="alert alert-info" style="font-size:0.85rem;">
                <i class="fas fa-info-circle"></i>
                管理員尚未新增任何文學導師，敬請期待！
            </div>
            <?php else: ?>
            <?php foreach ($tutors as $tutor):
                $tid        = $tutor['id'];
                $lvCount    = 0;
                $lvComplete = 0;

                // 查詢此導師關卡總數
                $stLv = $db->prepare('SELECT COUNT(*) FROM levels WHERE tutor_id = ? AND is_active = 1');
                $stLv->execute([$tid]);
                $lvCount = (int)$stLv->fetchColumn();

                if (isset($progress[$tid])) {
                    foreach ($progress[$tid] as $lv) {
                        if ($lv['completed']) $lvComplete++;
                    }
                }
                $pct       = $lvCount > 0 ? (int)($lvComplete / $lvCount * 100) : 0;
                $unlocked  = in_array($tid, $unlockedIds);
                $gradients = ['gradient-primary' => 'var(--gradient-primary)',
                              'gradient-secondary' => 'var(--gradient-secondary)',
                              'gradient-accent' => 'var(--gradient-accent)'];
                $gradStyle = $gradients[$tutor['gradient_class']] ?? 'var(--gradient-primary)';
            ?>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
                <div style="width:42px;height:42px;border-radius:50%;background:<?= e($gradStyle) ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <?php if (!empty($tutor['avatar_url'])): ?>
                        <img src="<?= e($tutor['avatar_url']) ?>" style="width:38px;height:38px;border-radius:50%;object-fit:cover;" onerror="this.style.display='none'">
                    <?php else: ?>
                        <i class="fas fa-user-tie" style="color:white;font-size:1rem;"></i>
                    <?php endif; ?>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-weight:600;font-size:0.9rem;"><?= e($tutor['name']) ?></span>
                        <span style="font-size:0.78rem;color:var(--dark-color);"><?= $lvComplete ?>/<?= $lvCount ?> 關</span>
                    </div>
                    <div style="height:5px;background:rgba(127,179,213,0.15);border-radius:3px;overflow:hidden;">
                        <div style="height:100%;width:<?= $pct ?>%;background:<?= e($gradStyle) ?>;border-radius:3px;transition:width 0.8s ease;"></div>
                    </div>
                    <?php if (!$unlocked && $lvCount > 0): ?>
                    <span style="font-size:0.72rem;color:#aaa;"><i class="fas fa-lock"></i> 完成第一關以解鎖社群</span>
                    <?php elseif ($unlocked): ?>
                    <span style="font-size:0.72rem;color:var(--success-color);"><i class="fas fa-check-circle"></i> 社群已解鎖</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/learning/index.php" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;">
                <i class="fas fa-play"></i> 繼續學習
            </a>
        </div>

        <!-- 最近社群動態 -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-comments" style="color:var(--highlight-color);"></i>
                最新社群動態
            </div>
            <?php if (empty($recentPosts)): ?>
            <div class="alert alert-info" style="font-size:0.85rem;">
                <i class="fas fa-info-circle"></i>
                社群尚無動態。開始學習後，古代文豪將在這裡分享心得！
            </div>
            <?php else: ?>
            <?php foreach ($recentPosts as $post): ?>
            <div style="border-left:3px solid var(--primary-color);padding-left:12px;margin-bottom:14px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;">
                    <?php if ($post['author_type'] === 'tutor'): ?>
                    <span class="badge badge-secondary"><?= e($post['tutor_name'] ?? '') ?></span>
                    <?php else: ?>
                    <span class="badge badge-primary"><?= e($post['author_name'] ?? '用戶') ?></span>
                    <?php endif; ?>
                    <span style="font-size:0.75rem;color:#aaa;"><?= timeAgo($post['created_at']) ?></span>
                </div>
                <p style="font-size:0.88rem;color:var(--text-color);line-height:1.5;">
                    <?= e(mb_substr($post['content'], 0, 80)) ?><?= mb_strlen($post['content']) > 80 ? '…' : '' ?>
                </p>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/social/index.php" class="btn btn-outline" style="width:100%;justify-content:center;margin-top:8px;">
                <i class="fas fa-arrow-right"></i> 查看全部動態
            </a>
        </div>

    </div><!-- end grid -->

    <!-- 快速入口 -->
    <div class="grid-3" style="margin-top:24px;">
        <a href="<?= BASE_URL ?>/translate/index.php" class="card" style="text-decoration:none;text-align:center;cursor:pointer;transition:var(--transition);padding:20px;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
            <i class="fas fa-language" style="font-size:2rem;color:var(--primary-color);margin-bottom:10px;display:block;"></i>
            <div style="font-weight:700;color:var(--text-color);">文言翻譯</div>
            <div style="font-size:0.8rem;color:var(--dark-color);margin-top:4px;">AI 逐字解析古文</div>
        </a>
        <a href="<?= BASE_URL ?>/games/matching.php" class="card" style="text-decoration:none;text-align:center;cursor:pointer;transition:var(--transition);padding:20px;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
            <i class="fas fa-puzzle-piece" style="font-size:2rem;color:var(--highlight-color);margin-bottom:10px;display:block;"></i>
            <div style="font-weight:700;color:var(--text-color);">文言配對</div>
            <div style="font-size:0.8rem;color:var(--dark-color);margin-top:4px;">字詞配對遊戲</div>
        </a>
        <a href="<?= BASE_URL ?>/social/index.php" class="card" style="text-decoration:none;text-align:center;cursor:pointer;transition:var(--transition);padding:20px;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
            <i class="fas fa-users" style="font-size:2rem;color:var(--accent-color);margin-bottom:10px;display:block;"></i>
            <div style="font-weight:700;color:var(--text-color);">古人社群</div>
            <div style="font-size:0.8rem;color:var(--dark-color);margin-top:4px;">與古代文豪互動</div>
        </a>
    </div>

</div><!-- end page-container -->

<?php require __DIR__ . '/includes/partials/footer.php'; ?>
