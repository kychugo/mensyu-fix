<?php
/**
 * 文樞 — 首頁 / 登陸頁 (Landing Page)
 *
 * 未登入：顯示平台介紹 + 登入 / 注冊模態視窗
 * 已登入：直接導向各自的主頁
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

// 已登入用戶直接跳轉
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: ' . BASE_URL . '/admin/index.php');
    } else {
        header('Location: ' . BASE_URL . '/dashboard.php');
    }
    exit;
}

// 處理登入表單提交（POST）
$loginError    = '';
$registerError = '';
$registerSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── 驗證 CSRF ──────────────────────────────────────────────────────────
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $loginError = '表單驗證失敗，請重新提交。';
    } else {
        $action = $_POST['action'] ?? '';

        // ── 登入 ──────────────────────────────────────────────────────────
        if ($action === 'login') {
            $email    = trim($_POST['email']    ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $loginError = '請填寫電郵及密碼。';
            } else {
                try {
                    $db  = getDB();
                    $st  = $db->prepare('SELECT id, username, password_hash, role FROM users WHERE email = ? LIMIT 1');
                    $st->execute([$email]);
                    $user = $st->fetch();

                    if (!$user || !password_verify($password, $user['password_hash'])) {
                        // 故意不區分「電郵不存在」與「密碼錯誤」，防止枚舉攻擊
                        $loginError = '電郵或密碼不正確，請重試。';
                    } else {
                        // 登入成功
                        session_regenerate_id(true);
                        $_SESSION['user_id']  = (int)$user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role']     = $user['role'];

                        // 更新最後登入時間
                        $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')
                           ->execute([$user['id']]);

                        // 導向
                        $redirect = $_GET['redirect'] ?? '';
                        if ($user['role'] === 'admin') {
                            header('Location: ' . BASE_URL . '/admin/index.php');
                        } elseif ($redirect
                            && str_starts_with($redirect, '/')
                            && !str_starts_with($redirect, '//')
                            && (BASE_URL === '' || str_starts_with($redirect, BASE_URL . '/'))) {
                            header('Location: ' . $redirect);
                        } else {
                            header('Location: ' . BASE_URL . '/dashboard.php');
                        }
                        exit;
                    }
                } catch (PDOException $e) {
                    $loginError = '系統錯誤，請稍後再試。';
                }
            }
        }

        // ── 注冊 ──────────────────────────────────────────────────────────
        elseif ($action === 'register') {
            $username  = trim($_POST['reg_username']  ?? '');
            $email     = trim($_POST['reg_email']     ?? '');
            $password  = $_POST['reg_password']  ?? '';
            $password2 = $_POST['reg_password2'] ?? '';

            if (empty($username) || empty($email) || empty($password)) {
                $registerError = '請填寫所有必填欄位。';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $registerError = '電郵格式不正確。';
            } elseif (mb_strlen($username) < 2 || mb_strlen($username) > 30) {
                $registerError = '用戶名長度須為 2–30 個字符。';
            } elseif (strlen($password) < 8) {
                $registerError = '密碼長度不得少於 8 個字符。';
            } elseif ($password !== $password2) {
                $registerError = '兩次輸入的密碼不一致。';
            } else {
                try {
                    $db = getDB();

                    // 檢查電郵是否已注冊
                    $st = $db->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
                    $st->execute([$email]);
                    if ((int)$st->fetchColumn() > 0) {
                        $registerError = '此電郵已被注冊，請直接登入或使用其他電郵。';
                    } else {
                        // 首位用戶自動成為管理員
                        $totalUsers = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
                        $role       = ($totalUsers === 0) ? 'admin' : 'user';

                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $st   = $db->prepare(
                            'INSERT INTO users (username, email, password_hash, role, created_at)
                             VALUES (?, ?, ?, ?, NOW())'
                        );
                        $st->execute([$username, $email, $hash, $role]);
                        $newId = (int)$db->lastInsertId();

                        // 自動登入
                        session_regenerate_id(true);
                        $_SESSION['user_id']  = $newId;
                        $_SESSION['username'] = $username;
                        $_SESSION['role']     = $role;

                        if ($role === 'admin') {
                            header('Location: ' . BASE_URL . '/admin/index.php');
                        } else {
                            header('Location: ' . BASE_URL . '/dashboard.php');
                        }
                        exit;
                    }
                } catch (PDOException $e) {
                    $registerError = '注冊失敗，請稍後再試。';
                }
            }
        }
    }
}

// 取得 CSRF token（渲染前產生）
$csrfToken = getCsrfToken();

// 取得平台統計（資料庫連線失敗時顯示 0）
$stats = getPlatformStats();

// 預設顯示哪個 Modal（登入有錯顯示登入，注冊有錯顯示注冊）
$defaultModal = '';
if ($loginError)    $defaultModal = 'login';
if ($registerError) $defaultModal = 'register';
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文樞 — 古典文學互動學習平台 | 文言文學習、翻譯、與古人對話</title>
    <meta name="description" content="文樞是一個以遊戲化方式學習文言文的互動平台。透過關卡挑戰、AI 翻譯解析、與古代文豪對話，讓文言文學習不再枯燥。">
    <meta name="keywords" content="文言文,文言翻譯,古文學習,蘇軾,韓愈,文言配對,香港中文教育,遊戲化學習">
    <meta property="og:title" content="文樞 — 古典文學互動學習平台">
    <meta property="og:description" content="透過遊戲化關卡、AI 翻譯解析、與古代文豪互動，讓文言文學習變得有趣！">
    <meta property="og:type" content="website">
    <link rel="canonical" href="<?= e((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/') ?>">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@300;400;500;600;700&family=Noto+Sans+TC:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">

    <style>
        /* ── CSS 變數（源自 v3.HTML，完全保留）────────────────────────────── */
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

        /* ── Reset & Base ───────────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Noto Sans TC', 'Microsoft JhengHei', sans-serif;
            background: linear-gradient(to bottom right, var(--background-color) 0%, #f0f8ff 100%);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* ── 導航欄 ─────────────────────────────────────────────────────── */
        .navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            backdrop-filter: blur(16px);
            background: rgba(255, 255, 255, 0.9);
            border-bottom: 1px solid rgba(127, 179, 213, 0.2);
            box-shadow: var(--shadow-light);
        }
        .navbar-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        .navbar-brand-title {
            font-family: 'Noto Serif TC', serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary-color);
            letter-spacing: 2px;
        }
        .navbar-brand-sub {
            font-size: 0.8rem;
            color: var(--dark-color);
            display: none; /* 手機隱藏 */
        }
        @media (min-width: 640px) {
            .navbar-brand-sub { display: inline; }
        }
        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .btn-outline {
            padding: 8px 20px;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
        }
        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }
        .btn-primary {
            padding: 8px 20px;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        /* ── Hero Section ───────────────────────────────────────────────── */
        .hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 80px 24px 60px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            /* 裝飾性背景圖案 */
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image:
                radial-gradient(circle at 15% 20%, rgba(127,179,213,0.12) 0%, transparent 50%),
                radial-gradient(circle at 85% 70%, rgba(162,217,206,0.12) 0%, transparent 50%),
                radial-gradient(circle at 50% 90%, rgba(169,204,227,0.10) 0%, transparent 40%);
            pointer-events: none;
            z-index: 0;
        }
        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(127, 179, 213, 0.15);
            border: 1px solid rgba(127, 179, 213, 0.3);
            color: var(--primary-color);
            padding: 6px 16px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 24px;
        }
        .hero-title {
            font-family: 'Noto Serif TC', serif;
            font-size: clamp(2.2rem, 6vw, 4rem);
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 20px;
            color: var(--text-color);
        }
        .hero-title .highlight {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero-subtitle {
            font-size: clamp(1rem, 2.5vw, 1.25rem);
            color: var(--dark-color);
            margin-bottom: 36px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        .hero-cta {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 56px;
        }
        .btn-hero-primary {
            padding: 14px 36px;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-medium);
        }
        .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-heavy);
        }
        .btn-hero-secondary {
            padding: 14px 36px;
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            border-radius: 10px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-hero-secondary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        /* ── 統計數字橫幅 ────────────────────────────────────────────────── */
        .stats-bar {
            display: flex;
            gap: 40px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-family: 'Noto Serif TC', serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            display: block;
        }
        .stat-label {
            font-size: 0.85rem;
            color: var(--dark-color);
        }

        /* ── Section 通用 ────────────────────────────────────────────────── */
        section { padding: 80px 24px; }
        .section-inner { max-width: 1200px; margin: 0 auto; }
        .section-tag {
            display: inline-block;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--primary-color);
            background: rgba(127, 179, 213, 0.12);
            padding: 4px 14px;
            border-radius: 999px;
            margin-bottom: 12px;
        }
        .section-title {
            font-family: 'Noto Serif TC', serif;
            font-size: clamp(1.6rem, 3vw, 2.4rem);
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 12px;
        }
        .section-desc {
            font-size: 1.05rem;
            color: var(--dark-color);
            max-width: 640px;
        }
        .section-header { margin-bottom: 48px; }

        /* ── 研究數據 Section ────────────────────────────────────────────── */
        .research-section { background: white; }
        .research-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 28px;
        }
        .research-card {
            border-radius: var(--border-radius);
            padding: 28px;
            border-left: 5px solid;
            background: linear-gradient(135deg, rgba(127,179,213,0.06), rgba(162,217,206,0.04));
        }
        .research-card:nth-child(1) { border-color: var(--primary-color); }
        .research-card:nth-child(2) { border-color: var(--highlight-color); }
        .research-card:nth-child(3) { border-color: var(--accent-color); }
        .research-number {
            font-family: 'Noto Serif TC', serif;
            font-size: 2.6rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 8px;
        }
        .research-card:nth-child(1) .research-number { color: var(--primary-color); }
        .research-card:nth-child(2) .research-number { color: var(--highlight-color); }
        .research-card:nth-child(3) .research-number { color: var(--accent-color); }
        .research-text {
            font-size: 0.95rem;
            color: var(--dark-color);
            line-height: 1.6;
        }
        .research-source {
            font-size: 0.75rem;
            color: #999;
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid rgba(127,179,213,0.2);
        }

        /* ── 功能特色 Section ────────────────────────────────────────────── */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 28px;
        }
        .feature-card {
            border-radius: var(--border-radius);
            padding: 32px 28px;
            background: white;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            cursor: default;
            position: relative;
            overflow: hidden;
        }
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
        }
        .feature-card:nth-child(1)::before { background: var(--gradient-primary); }
        .feature-card:nth-child(2)::before { background: var(--gradient-secondary); }
        .feature-card:nth-child(3)::before { background: var(--gradient-accent); }
        .feature-card:nth-child(4)::before { background: linear-gradient(135deg, #f9ca24, #f0932b); }
        .feature-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-heavy);
        }
        .feature-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 20px;
        }
        .feature-card:nth-child(1) .feature-icon { background: rgba(127,179,213,0.15); color: var(--primary-color); }
        .feature-card:nth-child(2) .feature-icon { background: rgba(125,206,160,0.15); color: var(--highlight-color); }
        .feature-card:nth-child(3) .feature-icon { background: rgba(169,204,227,0.15); color: var(--accent-color); }
        .feature-card:nth-child(4) .feature-icon { background: rgba(249,202,36,0.15);  color: #f0932b; }
        .feature-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 10px;
        }
        .feature-desc {
            font-size: 0.9rem;
            color: var(--dark-color);
            line-height: 1.65;
        }

        /* ── 學習旅程 Section ────────────────────────────────────────────── */
        .journey-section { background: white; }
        .journey-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 0;
            position: relative;
        }
        .journey-steps::before {
            /* 連接線（桌面） */
            content: '';
            position: absolute;
            top: 40px;
            left: 10%;
            right: 10%;
            height: 2px;
            background: var(--gradient-primary);
            z-index: 0;
        }
        @media (max-width: 768px) {
            .journey-steps::before { display: none; }
            .journey-steps { gap: 24px; }
        }
        .journey-step {
            text-align: center;
            padding: 20px 16px;
            position: relative;
            z-index: 1;
        }
        .step-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--gradient-primary);
            color: white;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            box-shadow: var(--shadow-medium);
            font-family: 'Noto Serif TC', serif;
        }
        .step-title {
            font-weight: 700;
            font-size: 1rem;
            color: var(--text-color);
            margin-bottom: 8px;
        }
        .step-desc {
            font-size: 0.85rem;
            color: var(--dark-color);
            line-height: 1.5;
        }

        /* ── 文豪展示 Section ────────────────────────────────────────────── */
        .tutors-preview {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .tutor-preview-card {
            border-radius: var(--border-radius);
            overflow: hidden;
            width: 200px;
            background: white;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            text-align: center;
        }
        .tutor-preview-card:hover {
            transform: translateY(-8px) perspective(1000px) rotateY(-5deg);
            box-shadow: var(--shadow-heavy);
        }
        .tutor-preview-header {
            padding: 24px 16px;
            color: white;
        }
        .tutor-preview-header:nth-child(1) { background: var(--gradient-primary); }
        .tutor-preview-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.8);
            object-fit: cover;
            margin: 0 auto 10px;
            display: block;
            background: rgba(255,255,255,0.3);
        }
        .tutor-preview-name {
            font-family: 'Noto Serif TC', serif;
            font-size: 1.2rem;
            font-weight: 700;
        }
        .tutor-preview-body {
            padding: 14px 16px;
        }
        .tutor-preview-desc {
            font-size: 0.8rem;
            color: var(--dark-color);
        }
        .tutor-locked {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.75rem;
            color: #aaa;
            margin-top: 8px;
        }

        /* ── CTA Banner ─────────────────────────────────────────────────── */
        .cta-banner {
            background: var(--gradient-primary);
            border-radius: 20px;
            padding: 56px 40px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .cta-banner::before {
            content: '';
            position: absolute;
            top: -50%; right: -20%;
            width: 400px; height: 400px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
        }
        .cta-banner-title {
            font-family: 'Noto Serif TC', serif;
            font-size: clamp(1.6rem, 3vw, 2.4rem);
            font-weight: 700;
            margin-bottom: 12px;
        }
        .cta-banner-desc {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 28px;
        }
        .btn-cta {
            padding: 14px 40px;
            background: white;
            color: var(--primary-color);
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        .btn-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        }

        /* ── Footer ─────────────────────────────────────────────────────── */
        footer {
            background: var(--dark-color);
            color: rgba(255,255,255,0.7);
            padding: 40px 24px;
            text-align: center;
        }
        .footer-brand {
            font-family: 'Noto Serif TC', serif;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 8px;
        }
        .footer-desc { font-size: 0.85rem; margin-bottom: 16px; }
        .footer-links {
            display: flex;
            gap: 24px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .footer-links a {
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.2s;
        }
        .footer-links a:hover { color: white; }
        .footer-copy { font-size: 0.78rem; color: rgba(255,255,255,0.4); }

        /* ── Modal 模態視窗 ────────────────────────────────────────────── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 200;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.active {
            display: flex;
            animation: fadeInOverlay 0.2s ease;
        }
        @keyframes fadeInOverlay {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
        .modal {
            background: white;
            border-radius: 20px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            overflow: hidden;
            animation: slideUpModal 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes slideUpModal {
            from { opacity: 0; transform: translateY(40px) scale(0.96); }
            to   { opacity: 1; transform: translateY(0)   scale(1); }
        }
        .modal-header {
            padding: 28px 28px 0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .modal-title {
            font-family: 'Noto Serif TC', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-color);
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.4rem;
            color: #aaa;
            cursor: pointer;
            padding: 4px;
            line-height: 1;
            transition: color 0.2s;
        }
        .modal-close:hover { color: var(--text-color); }
        .modal-body { padding: 20px 28px 28px; }

        /* 登入 / 注冊 頁籤切換 */
        .auth-tabs {
            display: flex;
            gap: 0;
            border-bottom: 2px solid rgba(127,179,213,0.2);
            margin-bottom: 24px;
        }
        .auth-tab {
            padding: 10px 20px;
            font-size: 0.95rem;
            font-weight: 600;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--dark-color);
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: var(--transition);
        }
        .auth-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        /* 表單元素 */
        .form-group { margin-bottom: 18px; }
        .form-label {
            display: block;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 6px;
        }
        .form-input {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid rgba(127,179,213,0.3);
            border-radius: 8px;
            font-size: 0.95rem;
            color: var(--text-color);
            font-family: inherit;
            transition: border-color 0.2s;
            background: white;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(127,179,213,0.15);
        }
        .form-error {
            color: var(--error-color);
            font-size: 0.83rem;
            margin-top: 5px;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.88rem;
            margin-bottom: 16px;
        }
        .alert-error {
            background: rgba(231,76,60,0.1);
            color: var(--error-color);
            border: 1px solid rgba(231,76,60,0.25);
        }
        .alert-success {
            background: rgba(39,174,96,0.1);
            color: var(--success-color);
            border: 1px solid rgba(39,174,96,0.25);
        }
        .btn-submit {
            width: 100%;
            padding: 13px;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
            margin-top: 4px;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }
        .auth-switch {
            text-align: center;
            margin-top: 16px;
            font-size: 0.88rem;
            color: var(--dark-color);
        }
        .auth-switch a {
            color: var(--primary-color);
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .auth-switch a:hover { text-decoration: underline; }

        /* ── 響應式 ────────────────────────────────────────────────────── */
        @media (max-width: 640px) {
            section { padding: 56px 16px; }
            .hero { padding: 80px 16px 48px; }
            .cta-banner { padding: 40px 20px; }
        }

        /* ── 滾動條 ─────────────────────────────────────────────────────── */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: rgba(127,179,213,0.08); }
        ::-webkit-scrollbar-thumb { background: var(--primary-color); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--secondary-color); }
    </style>
</head>
<body>

<!-- ══════════════════════════════════════════════════════
     導航欄
══════════════════════════════════════════════════════ -->
<nav class="navbar" aria-label="主導航">
    <div class="navbar-inner">
        <a href="<?= BASE_URL ?>/" class="navbar-brand">
            <span class="navbar-brand-title">文樞</span>
            <span class="navbar-brand-sub">古典文學互動學習平台</span>
        </a>
        <div class="navbar-actions">
            <button class="btn-outline" onclick="openModal('login')">登入</button>
            <button class="btn-primary" onclick="openModal('register')">立即注冊</button>
        </div>
    </div>
</nav>

<!-- ══════════════════════════════════════════════════════
     Hero Section
══════════════════════════════════════════════════════ -->
<section class="hero" id="hero">
    <div class="hero-content">
        <div class="hero-badge">
            <i class="fas fa-graduation-cap"></i>
            遊戲化文言文學習平台
        </div>

        <h1 class="hero-title">
            與<span class="highlight">古代文豪</span>為友<br>
            以文言文為橋
        </h1>

        <p class="hero-subtitle">
            透過關卡挑戰、AI 翻譯解析、文言配對遊戲，<br>
            解鎖歷史人物，在虛擬社群中與古人互動交流
        </p>

        <div class="hero-cta">
            <button class="btn-hero-primary" onclick="openModal('register')">
                <i class="fas fa-play-circle" style="margin-right:8px;"></i>
                立即開始學習
            </button>
            <button class="btn-hero-secondary" onclick="document.getElementById('features').scrollIntoView({behavior:'smooth'})">
                <i class="fas fa-info-circle" style="margin-right:8px;"></i>
                了解更多
            </button>
        </div>

        <!-- 平台統計 -->
        <div class="stats-bar">
            <div class="stat-item">
                <span class="stat-number" id="stat-users"><?= $stats['users'] > 0 ? $stats['users'] : '∞' ?></span>
                <span class="stat-label">學習用戶</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?= $stats['tutors'] > 0 ? $stats['tutors'] : '多位' ?></span>
                <span class="stat-label">文學導師</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?= $stats['essays'] > 0 ? $stats['essays'] : '豐富' ?></span>
                <span class="stat-label">收錄文章</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">AI</span>
                <span class="stat-label">智能解析</span>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════
     研究數據 Section（問題背景）
══════════════════════════════════════════════════════ -->
<section class="research-section" id="research">
    <div class="section-inner">
        <div class="section-header">
            <span class="section-tag">研究背景</span>
            <h2 class="section-title">文言文學習的困境</h2>
            <p class="section-desc">根據學術研究，香港中學生在文言文學習上面臨嚴峻挑戰。</p>
        </div>

        <div class="research-grid">
            <div class="research-card">
                <div class="research-number">&gt;50%</div>
                <p class="research-text">
                    超過一半的中四學生<strong>不主動閱讀文言文</strong>，
                    認為「枯燥乏味」、「與現實無關」，缺乏學習興趣與動機。
                </p>
            </div>
            <div class="research-card">
                <div class="research-number">38.3%</div>
                <p class="research-text">
                    454 名中四學生文言<strong>字詞認讀平均答對率僅 38.33%</strong>，
                    句式理解更低至 26.65%，閱讀能力嚴重不足。
                </p>
            </div>
            <div class="research-card">
                <div class="research-number">文樞</div>
                <p class="research-text">
                    透過<strong>遊戲化學習</strong>、虛擬文豪對話與關卡挑戰，
                    逐字對譯及詳細解釋，讓學生主動投入，突破學習困境。
                </p>
            </div>
        </div>

        <p class="research-source">
            <i class="fas fa-book" style="margin-right:4px;"></i>
            資料來源：教育學報，2017 年，第 45 卷第 2 期，頁 161–181
        </p>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════
     功能特色 Section
══════════════════════════════════════════════════════ -->
<section id="features">
    <div class="section-inner">
        <div class="section-header">
            <span class="section-tag">平台特色</span>
            <h2 class="section-title">四大核心功能</h2>
            <p class="section-desc">文樞將枯燥的文言文學習，轉化為沉浸式的遊戲體驗。</p>
        </div>

        <div class="features-grid">
            <!-- 關卡學習 -->
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-layer-group"></i></div>
                <h3 class="feature-title">關卡挑戰學習</h3>
                <p class="feature-desc">
                    選擇文學導師，由淺入深完成多個關卡。每關閱讀一篇經典作品，
                    配合 AI 逐字解析，最後挑戰選擇題問答，通關即可解鎖下一關。
                </p>
            </div>

            <!-- 智能翻譯 -->
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-language"></i></div>
                <h3 class="feature-title">AI 智能翻譯解析</h3>
                <p class="feature-desc">
                    提供文言文<strong>逐字對譯</strong>及詳細解釋，幫助學生理解每個字詞的含義。
                    常見文言字詞以粗體標示，突出重點，強化記憶與理解。
                </p>
            </div>

            <!-- 古人社群 -->
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-comments"></i></div>
                <h3 class="feature-title">與古人互動社群</h3>
                <p class="feature-desc">
                    完成關卡後解鎖對應的古代文豪。在社群動態牆上，
                    與蘇軾、韓愈等歷史人物互動留言，以香港粵語與古人交流。
                </p>
            </div>

            <!-- 文言配對遊戲 -->
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-puzzle-piece"></i></div>
                <h3 class="feature-title">文言配對遊戲</h3>
                <p class="feature-desc">
                    挑戰文言字詞與現代語譯的配對遊戲，五級難度設計，
                    限時挑戰，邊玩邊學，在遊戲中鞏固文言字詞記憶。
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════
     學習旅程 Section
══════════════════════════════════════════════════════ -->
<section class="journey-section" id="journey">
    <div class="section-inner">
        <div class="section-header" style="text-align:center;">
            <span class="section-tag">學習流程</span>
            <h2 class="section-title">你的文言文學習之旅</h2>
            <p class="section-desc" style="margin:0 auto;">從選擇導師到與古人對話，五步完成一段跨越千年的學習旅程。</p>
        </div>

        <div class="journey-steps">
            <div class="journey-step">
                <div class="step-circle">一</div>
                <div class="step-title">選擇文學導師</div>
                <p class="step-desc">選擇你想了解的古代文豪，如蘇軾、韓愈</p>
            </div>
            <div class="journey-step">
                <div class="step-circle">二</div>
                <div class="step-title">閱讀經典作品</div>
                <p class="step-desc">配合 AI 逐字解析，深入理解文言文精髓</p>
            </div>
            <div class="journey-step">
                <div class="step-circle">三</div>
                <div class="step-title">挑戰關卡問答</div>
                <p class="step-desc">AI 生成選擇題，測試理解，通關即可升級</p>
            </div>
            <div class="journey-step">
                <div class="step-circle">四</div>
                <div class="step-title">解鎖古人社群</div>
                <p class="step-desc">完成關卡後，解鎖對應文豪的社群互動功能</p>
            </div>
            <div class="journey-step">
                <div class="step-circle">五</div>
                <div class="step-title">與古人對話</div>
                <p class="step-desc">在社群動態牆與古人互動，體驗跨越千年的交流</p>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════
     文學導師預覽 Section
══════════════════════════════════════════════════════ -->
<section id="tutors">
    <div class="section-inner">
        <div class="section-header" style="text-align:center;">
            <span class="section-tag">文學導師</span>
            <h2 class="section-title">認識你的古代文豪</h2>
            <p class="section-desc" style="margin:0 auto;">完成不同導師的關卡，逐步解鎖他們的社群互動。</p>
        </div>

        <div class="tutors-preview">
            <!-- 這裡從資料庫動態讀取，若資料庫不可用則顯示預設 -->
            <?php
            try {
                $tutors = getActiveTutors();
                if (empty($tutors)) {
                    // 顯示預設範例導師
                    $tutors = [
                        ['id' => 1, 'name' => '蘇軾', 'dynasty' => '北宋', 'description' => '豁達樂觀，詩詞書畫皆精', 'avatar_url' => 'https://i.ibb.co/wrhVfCjJ/image.png', 'gradient_class' => 'gradient-primary'],
                        ['id' => 2, 'name' => '韓愈', 'dynasty' => '唐代', 'description' => '古文運動倡導者，唐宋八大家之首', 'avatar_url' => 'https://i.ibb.co/LhqsVb40/image.png', 'gradient_class' => 'gradient-secondary'],
                    ];
                }
                foreach ($tutors as $i => $tutor):
                    $gradients = ['gradient-primary', 'gradient-secondary', 'gradient-accent'];
                    $grad = $tutor['gradient_class'] ?: $gradients[$i % 3];
            ?>
            <div class="tutor-preview-card">
                <div class="tutor-preview-header <?= e($grad) ?>">
                    <?php if (!empty($tutor['avatar_url'])): ?>
                        <img src="<?= e($tutor['avatar_url']) ?>"
                             alt="<?= e($tutor['name']) ?>頭像"
                             class="tutor-preview-avatar"
                             onerror="this.style.display='none'">
                    <?php else: ?>
                        <div class="tutor-preview-avatar" style="background:rgba(255,255,255,0.3);display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-user-tie" style="font-size:2rem;color:white;"></i>
                        </div>
                    <?php endif; ?>
                    <div class="tutor-preview-name"><?= e($tutor['name']) ?></div>
                </div>
                <div class="tutor-preview-body">
                    <p class="tutor-preview-desc"><?= e($tutor['dynasty']) ?> · <?= e(mb_substr($tutor['description'], 0, 20)) ?>…</p>
                    <div class="tutor-locked">
                        <i class="fas fa-lock"></i> 完成關卡後解鎖
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        <?php } catch (PDOException $e) {
            echo '<p style="color:var(--dark-color);text-align:center;">導師資料載入中，請稍後再試。</p>';
        }
        ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════
     CTA Banner
══════════════════════════════════════════════════════ -->
<section>
    <div class="section-inner">
        <div class="cta-banner">
            <h2 class="cta-banner-title">準備好開始你的文言文之旅了嗎？</h2>
            <p class="cta-banner-desc">免費注冊，立即解鎖遊戲化學習體驗</p>
            <button class="btn-cta" onclick="openModal('register')">
                <i class="fas fa-rocket" style="margin-right:8px;"></i>
                免費開始學習
            </button>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════
     Footer
══════════════════════════════════════════════════════ -->
<footer>
    <div class="footer-brand">文樞</div>
    <p class="footer-desc">古典文學互動學習平台 — 以古為師，以文為橋</p>
    <nav class="footer-links" aria-label="頁腳導航">
        <a href="#features">平台特色</a>
        <a href="#journey">學習流程</a>
        <a href="#tutors">文學導師</a>
        <a href="<?= BASE_URL ?>/translate/">文言翻譯</a>
        <a href="#" onclick="openModal('login'); return false;">登入</a>
        <a href="#" onclick="openModal('register'); return false;">注冊</a>
    </nav>
    <p class="footer-copy">
        &copy; <?= date('Y') ?> 文樞 ·
        靈感來源：教育學報，2017年，第45卷第2期，頁161–181
    </p>
</footer>

<!-- ══════════════════════════════════════════════════════
     登入 / 注冊 Modal
══════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-overlay" role="dialog" aria-modal="true" aria-label="用戶登入與注冊">
    <div class="modal" id="modal-box">
        <div class="modal-header">
            <div>
                <div class="modal-title" id="modal-title">歡迎回來</div>
            </div>
            <button class="modal-close" onclick="closeModal()" aria-label="關閉">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <!-- 頁籤 -->
            <div class="auth-tabs">
                <button class="auth-tab" id="tab-login"    onclick="switchTab('login')">登入</button>
                <button class="auth-tab" id="tab-register" onclick="switchTab('register')">注冊</button>
            </div>

            <!-- 登入表單 -->
            <div id="form-login">
                <?php if ($loginError): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>
                    <?= e($loginError) ?>
                </div>
                <?php endif; ?>
                <form method="POST" action="<?= BASE_URL ?>/index.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action"     value="login">

                    <div class="form-group">
                        <label class="form-label" for="login-email">電郵地址</label>
                        <input class="form-input"
                               type="email"
                               id="login-email"
                               name="email"
                               value="<?= e($_POST['email'] ?? '') ?>"
                               placeholder="your@email.com"
                               autocomplete="email"
                               required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="login-password">
                            密碼
                            <span style="float:right;font-weight:400;color:var(--primary-color);font-size:0.8rem;cursor:pointer;"
                                  onclick="togglePassword('login-password', this)">顯示</span>
                        </label>
                        <input class="form-input"
                               type="password"
                               id="login-password"
                               name="password"
                               placeholder="••••••••"
                               autocomplete="current-password"
                               required>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-sign-in-alt" style="margin-right:8px;"></i>登入
                    </button>
                </form>
                <div class="auth-switch">
                    還沒有帳號？<a onclick="switchTab('register')">立即注冊</a>
                </div>
            </div>

            <!-- 注冊表單 -->
            <div id="form-register" style="display:none;">
                <?php if ($registerError): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>
                    <?= e($registerError) ?>
                </div>
                <?php endif; ?>
                <?php if ($registerSuccess): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle" style="margin-right:6px;"></i>
                    <?= e($registerSuccess) ?>
                </div>
                <?php endif; ?>
                <form method="POST" action="<?= BASE_URL ?>/index.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action"     value="register">

                    <div class="form-group">
                        <label class="form-label" for="reg-username">用戶名 <span style="color:var(--error-color)">*</span></label>
                        <input class="form-input"
                               type="text"
                               id="reg-username"
                               name="reg_username"
                               value="<?= e($_POST['reg_username'] ?? '') ?>"
                               placeholder="你的名稱（2–30字）"
                               autocomplete="username"
                               required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reg-email">電郵地址 <span style="color:var(--error-color)">*</span></label>
                        <input class="form-input"
                               type="email"
                               id="reg-email"
                               name="reg_email"
                               value="<?= e($_POST['reg_email'] ?? '') ?>"
                               placeholder="your@email.com"
                               autocomplete="email"
                               required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reg-password">
                            密碼 <span style="color:var(--error-color)">*</span>
                            <span style="float:right;font-weight:400;color:var(--primary-color);font-size:0.8rem;cursor:pointer;"
                                  onclick="togglePassword('reg-password', this)">顯示</span>
                        </label>
                        <input class="form-input"
                               type="password"
                               id="reg-password"
                               name="reg_password"
                               placeholder="至少 8 個字符"
                               autocomplete="new-password"
                               required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reg-password2">確認密碼 <span style="color:var(--error-color)">*</span></label>
                        <input class="form-input"
                               type="password"
                               id="reg-password2"
                               name="reg_password2"
                               placeholder="再次輸入密碼"
                               autocomplete="new-password"
                               required>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-user-plus" style="margin-right:8px;"></i>注冊帳號
                    </button>
                </form>
                <div class="auth-switch">
                    已有帳號？<a onclick="switchTab('login')">直接登入</a>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     JavaScript
══════════════════════════════════════════════════════ -->
<script>
    // ── Modal 控制 ─────────────────────────────────────────────────────────
    function openModal(tab) {
        document.getElementById('modal-overlay').classList.add('active');
        switchTab(tab || 'login');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById('modal-overlay').classList.remove('active');
        document.body.style.overflow = '';
    }

    // 點擊遮罩關閉
    document.getElementById('modal-overlay').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    // ESC 鍵關閉
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    // ── 頁籤切換 ──────────────────────────────────────────────────────────
    function switchTab(tab) {
        const isLogin = (tab === 'login');

        document.getElementById('form-login').style.display    = isLogin ? '' : 'none';
        document.getElementById('form-register').style.display = isLogin ? 'none' : '';

        document.getElementById('tab-login').classList.toggle('active', isLogin);
        document.getElementById('tab-register').classList.toggle('active', !isLogin);

        document.getElementById('modal-title').textContent = isLogin ? '歡迎回來' : '建立帳號';
    }

    // ── 密碼顯示切換 ──────────────────────────────────────────────────────
    function togglePassword(inputId, btn) {
        const input = document.getElementById(inputId);
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        btn.textContent = isHidden ? '隱藏' : '顯示';
    }

    // ── 頁面載入後，若有表單錯誤則自動打開對應 Modal ──────────────────────
    <?php if ($defaultModal): ?>
    window.addEventListener('DOMContentLoaded', function() {
        openModal('<?= $defaultModal ?>');
    });
    <?php endif; ?>

    // ── 統計數字動畫（Counter Animation）─────────────────────────────────
    function animateCounter(el, target, duration) {
        if (isNaN(target)) return; // 跳過非數字（如「∞」）
        const start    = 0;
        const step     = Math.ceil(duration / target);
        let   current  = start;
        const timer    = setInterval(function() {
            current += Math.max(1, Math.ceil(target / 50));
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            el.textContent = current;
        }, step);
    }

    // IntersectionObserver 觸發統計動畫
    const statEls = document.querySelectorAll('.stat-number');
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                const el  = entry.target;
                const val = parseInt(el.textContent);
                if (!isNaN(val) && val > 0) animateCounter(el, val, 1000);
                observer.unobserve(el);
            }
        });
    }, { threshold: 0.5 });
    statEls.forEach(el => observer.observe(el));
</script>

</body>
</html>
