<?php
/**
 * 共用 HTML head + 導航欄片段
 *
 * 使用方式：
 *   $pageTitle = '社群動態';         // 頁面標題（可選，預設「文樞」）
 *   $activePage = 'social';          // 導航欄高亮項目
 *   require __DIR__ . '/../includes/partials/header.php';
 *
 * $activePage 可用值：'home' | 'learning' | 'social' | 'translate' | 'games' | 'admin'
 */

if (!defined('BASE_URL')) {
    require_once dirname(__DIR__) . '/config.php';
}

$pageTitle  = $pageTitle  ?? APP_NAME;
$activePage = $activePage ?? '';

$fullTitle = ($pageTitle !== APP_NAME)
    ? $pageTitle . ' — ' . APP_NAME
    : APP_NAME . ' — 古典文學互動學習平台';
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($fullTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@300;400;500;600;700&family=Noto+Sans+TC:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <style>
        /* ── CSS 變數（與 Landing Page 完全一致）─────────────────────── */
        :root {
            --primary-color:    #7fb3d5;
            --secondary-color:  #a2d9ce;
            --accent-color:     #a9cce3;
            --highlight-color:  #7dcea0;
            --soft-color:       #76d7c4;
            --light-accent:     #aed6f1;
            --background-color: #f8fafa;
            --text-color:       #2c3e50;
            --light-color:      #ffffff;
            --dark-color:       #34495e;
            --error-color:      #e74c3c;
            --success-color:    #27ae60;
            --warning-color:    #f39c12;

            --shadow-light:  0 2px 12px rgba(127, 179, 213, 0.15);
            --shadow-medium: 0 4px 24px rgba(127, 179, 213, 0.2);
            --shadow-heavy:  0 8px 36px rgba(127, 179, 213, 0.25);

            --gradient-primary:   linear-gradient(135deg, var(--primary-color),   var(--secondary-color));
            --gradient-secondary: linear-gradient(135deg, var(--highlight-color), var(--soft-color));
            --gradient-accent:    linear-gradient(135deg, var(--accent-color),    var(--light-accent));

            --border-radius:       12px;
            --border-radius-small: 6px;
            --transition:          all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'Noto Sans TC', 'Microsoft JhengHei', sans-serif;
            background: linear-gradient(to bottom right, var(--background-color) 0%, #f0f8ff 100%);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* ── 導航欄 ──────────────────────────────────────────────────── */
        .navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            backdrop-filter: blur(16px);
            background: rgba(255, 255, 255, 0.92);
            border-bottom: 1px solid rgba(127, 179, 213, 0.2);
            box-shadow: var(--shadow-light);
        }
        .navbar-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .navbar-brand {
            text-decoration: none;
            font-family: 'Noto Serif TC', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            letter-spacing: 2px;
            white-space: nowrap;
        }
        .navbar-nav {
            display: flex;
            align-items: center;
            gap: 4px;
            list-style: none;
        }
        .navbar-nav a {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: var(--border-radius-small);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            color: var(--dark-color);
            transition: var(--transition);
            white-space: nowrap;
        }
        .navbar-nav a:hover,
        .navbar-nav a.active {
            background: rgba(127, 179, 213, 0.12);
            color: var(--primary-color);
        }
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .navbar-username {
            font-size: 0.85rem;
            color: var(--dark-color);
            white-space: nowrap;
        }
        .btn-sm {
            padding: 5px 14px;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            text-decoration: none;
            display: inline-block;
            white-space: nowrap;
        }
        .btn-sm-outline {
            border: 1.5px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }
        .btn-sm-outline:hover { background: var(--primary-color); color: white; }
        .btn-sm-danger {
            background: rgba(231,76,60,0.1);
            color: var(--error-color);
            border: 1.5px solid rgba(231,76,60,0.3);
        }
        .btn-sm-danger:hover { background: var(--error-color); color: white; }

        /* ── 主內容區 ────────────────────────────────────────────────── */
        .main-content {
            padding-top: 60px;
            min-height: 100vh;
        }
        .page-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 20px 60px;
        }
        .page-title {
            font-family: 'Noto Serif TC', serif;
            font-size: clamp(1.4rem, 3vw, 2rem);
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 6px;
        }
        .page-subtitle {
            font-size: 0.9rem;
            color: var(--dark-color);
            margin-bottom: 28px;
        }

        /* ── 通用卡片 ────────────────────────────────────────────────── */
        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            padding: 24px;
        }
        .card-title {
            font-weight: 700;
            font-size: 1rem;
            color: var(--text-color);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ── 按鈕 ───────────────────────────────────────────────────── */
        .btn {
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
        }
        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow-light);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: var(--shadow-medium); }
        .btn-secondary {
            background: var(--gradient-secondary);
            color: white;
        }
        .btn-secondary:hover { transform: translateY(-2px); }
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }
        .btn-outline:hover { background: var(--primary-color); color: white; }
        .btn-danger {
            background: rgba(231,76,60,0.1);
            color: var(--error-color);
            border: 1.5px solid rgba(231,76,60,0.3);
        }
        .btn-danger:hover { background: var(--error-color); color: white; }
        .btn-success {
            background: rgba(39,174,96,0.1);
            color: var(--success-color);
            border: 1.5px solid rgba(39,174,96,0.3);
        }
        .btn-success:hover { background: var(--success-color); color: white; }

        /* ── 表單元素 ────────────────────────────────────────────────── */
        .form-group { margin-bottom: 16px; }
        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 5px;
        }
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid rgba(127,179,213,0.3);
            border-radius: 7px;
            font-size: 0.9rem;
            color: var(--text-color);
            font-family: inherit;
            transition: border-color 0.2s;
            background: white;
        }
        .form-textarea { resize: vertical; min-height: 100px; }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(127,179,213,0.12);
        }

        /* ── 提示 / 錯誤訊息 ─────────────────────────────────────────── */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.88rem;
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .alert-error   { background: rgba(231,76,60,0.08);  color: var(--error-color);   border: 1px solid rgba(231,76,60,0.2);  }
        .alert-success { background: rgba(39,174,96,0.08);  color: var(--success-color); border: 1px solid rgba(39,174,96,0.2);  }
        .alert-info    { background: rgba(127,179,213,0.1); color: var(--primary-color); border: 1px solid rgba(127,179,213,0.25); }
        .alert-warning { background: rgba(243,156,18,0.08); color: var(--warning-color); border: 1px solid rgba(243,156,18,0.2); }

        /* ── 漸層輔助類別（與 v3 一致）─────────────────────────────── */
        .gradient-primary   { background: var(--gradient-primary); }
        .gradient-secondary { background: var(--gradient-secondary); }
        .gradient-accent    { background: var(--gradient-accent); }

        /* ── Grid ───────────────────────────────────────────────────── */
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
        .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; }
        .grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }

        /* ── 載入動畫 ────────────────────────────────────────────────── */
        .spinner {
            width: 20px; height: 20px;
            border: 2px solid rgba(127,179,213,0.3);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            display: inline-block;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Badge / Tag ────────────────────────────────────────────── */
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-primary   { background: rgba(127,179,213,0.18); color: var(--primary-color); }
        .badge-success   { background: rgba(39,174,96,0.15);   color: var(--success-color); }
        .badge-warning   { background: rgba(243,156,18,0.15);  color: var(--warning-color); }
        .badge-secondary { background: rgba(125,206,160,0.18); color: var(--highlight-color); }

        /* ── 分隔線 ─────────────────────────────────────────────────── */
        .divider { height: 1px; background: rgba(127,179,213,0.15); margin: 20px 0; }

        /* ── 滾動條 ─────────────────────────────────────────────────── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: rgba(127,179,213,0.08); }
        ::-webkit-scrollbar-thumb { background: var(--primary-color); border-radius: 3px; }

        /* ── 響應式：手機隱藏導航文字 ───────────────────────────────── */
        @media (max-width: 640px) {
            .navbar-nav a span { display: none; }
            .navbar-username   { display: none; }
            .page-container    { padding: 24px 12px 48px; }
        }
    </style>
</head>
<body>

<nav class="navbar" aria-label="主導航">
    <div class="navbar-inner">
        <a href="<?= BASE_URL ?>/dashboard.php" class="navbar-brand">文樞</a>

        <ul class="navbar-nav">
            <li>
                <a href="<?= BASE_URL ?>/dashboard.php"
                   class="<?= $activePage === 'home' ? 'active' : '' ?>">
                    <i class="fas fa-home"></i><span>首頁</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/learning/index.php"
                   class="<?= $activePage === 'learning' ? 'active' : '' ?>">
                    <i class="fas fa-layer-group"></i><span>學習</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/social/index.php"
                   class="<?= $activePage === 'social' ? 'active' : '' ?>">
                    <i class="fas fa-comments"></i><span>社群</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/mensyu.html"
                   class="<?= $activePage === 'translate' ? 'active' : '' ?>">
                    <i class="fas fa-language"></i><span>翻譯</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/games/matching.php"
                   class="<?= $activePage === 'games' ? 'active' : '' ?>">
                    <i class="fas fa-puzzle-piece"></i><span>遊戲</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/social/leaderboard.php"
                   class="<?= $activePage === 'leaderboard' ? 'active' : '' ?>">
                    <i class="fas fa-trophy"></i><span>龍虎榜</span>
                </a>
            </li>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <li>
                <a href="<?= BASE_URL ?>/admin/index.php"
                   class="<?= $activePage === 'admin' ? 'active' : '' ?>"
                   style="color: var(--warning-color);">
                    <i class="fas fa-cogs"></i><span>管理</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <div class="navbar-right">
            <span class="navbar-username">
                <i class="fas fa-user-circle" style="margin-right:4px;color:var(--primary-color);"></i>
                <?= htmlspecialchars($_SESSION['username'] ?? '用戶', ENT_QUOTES, 'UTF-8') ?>
            </span>
            <a href="<?= BASE_URL ?>/logout.php" class="btn-sm btn-sm-danger">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</nav>

<?php if (!empty($_SESSION['flash_info']) || !empty($_SESSION['flash_error'])): ?>
<div style="position:fixed;top:60px;left:0;right:0;z-index:99;padding:10px 20px;
            background:<?= !empty($_SESSION['flash_error']) ? 'rgba(231,76,60,0.92)' : 'rgba(39,174,96,0.92)' ?>;
            color:white;text-align:center;font-size:0.88rem;backdrop-filter:blur(4px);"
     id="flash-banner">
    <i class="fas fa-<?= !empty($_SESSION['flash_error']) ? 'exclamation-circle' : 'info-circle' ?>"></i>
    <?= htmlspecialchars($_SESSION['flash_info'] ?? $_SESSION['flash_error'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;color:white;margin-left:12px;cursor:pointer;font-size:1rem;line-height:1;">×</button>
</div>
<script>setTimeout(function(){var b=document.getElementById('flash-banner');if(b)b.remove();},4000);</script>
<?php
unset($_SESSION['flash_info'], $_SESSION['flash_error']);
endif;
?>

<main class="main-content">
