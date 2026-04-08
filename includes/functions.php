<?php
/**
 * 通用工具函數
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * 輸出安全的 HTML 轉義字串
 */
function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * 取得平台設定值（從 settings 表）
 *
 * @param string $key     設定鍵值
 * @param mixed  $default 找不到時的預設值
 */
function getSetting(string $key, $default = null)
{
    try {
        $db  = getDB();
        $sql = 'SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1';
        $st  = $db->prepare($sql);
        $st->execute([$key]);
        $row = $st->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * 更新平台設定值（UPSERT）
 */
function setSetting(string $key, string $value): void
{
    $db  = getDB();
    $sql = 'INSERT INTO settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)';
    $db->prepare($sql)->execute([$key, $value]);
}

/**
 * 輸出 JSON 回應並終止腳本（供 API 端點使用）
 *
 * @param array $data
 * @param int   $httpCode
 */
function jsonResponse(array $data, int $httpCode = 200): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 格式化時間戳為香港時間顯示字串
 */
function formatTimestamp(string $timestamp): string
{
    $dt = new DateTime($timestamp, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('Asia/Hong_Kong'));
    return $dt->format('Y年n月j日 H:i');
}

/**
 * 計算相對時間（幾分鐘前、幾小時前…）
 */
function timeAgo(string $timestamp): string
{
    $now  = time();
    $then = strtotime($timestamp);
    $diff = $now - $then;

    if ($diff < 60)       return '剛剛';
    if ($diff < 3600)     return (int)($diff / 60) . '分鐘前';
    if ($diff < 86400)    return (int)($diff / 3600) . '小時前';
    if ($diff < 2592000)  return (int)($diff / 86400) . '天前';
    return formatTimestamp($timestamp);
}

/**
 * 取得所有已啟用文學導師列表
 */
function getActiveTutors(): array
{
    $db = getDB();
    $st = $db->prepare('SELECT * FROM tutors WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
    $st->execute();
    return $st->fetchAll();
}

/**
 * 取得指定用戶的學習進度（回傳 [tutor_id => [level_id => progress_row]] 結構）
 */
function getUserProgress(int $userId): array
{
    $db = getDB();
    $st = $db->prepare('SELECT * FROM user_progress WHERE user_id = ?');
    $st->execute([$userId]);
    $rows = $st->fetchAll();

    $progress = [];
    foreach ($rows as $row) {
        $progress[$row['tutor_id']][$row['level_id']] = $row;
    }
    return $progress;
}

/**
 * 檢查指定用戶是否已解鎖某位導師（至少完成一個關卡）
 */
function isTutorUnlocked(int $userId, int $tutorId): bool
{
    $db = getDB();
    $st = $db->prepare(
        'SELECT COUNT(*) FROM user_progress
         WHERE user_id = ? AND tutor_id = ? AND completed = 1'
    );
    $st->execute([$userId, $tutorId]);
    return (int)$st->fetchColumn() > 0;
}

/**
 * 取得用戶所有已解鎖導師 ID 列表
 */
function getUnlockedTutorIds(int $userId): array
{
    $db = getDB();
    $st = $db->prepare(
        'SELECT DISTINCT tutor_id FROM user_progress
         WHERE user_id = ? AND completed = 1'
    );
    $st->execute([$userId]);
    return array_column($st->fetchAll(), 'tutor_id');
}

/**
 * 取得用戶 XP
 */
function getUserXp(int $userId): int
{
    $db = getDB();
    $st = $db->prepare('SELECT xp FROM users WHERE id = ?');
    $st->execute([$userId]);
    return (int)$st->fetchColumn();
}

/**
 * 增加用戶 XP
 */
function addUserXp(int $userId, int $amount): void
{
    $db = getDB();
    $db->prepare('UPDATE users SET xp = xp + ? WHERE id = ?')->execute([$amount, $userId]);
}

/**
 * 在新增動態前執行 FIFO 清理（超出 MAX_POSTS 時刪除最舊一篇）
 *
 * 此函數應在每次 INSERT social_posts 之前呼叫。
 */
function enforceSocialPostLimit(): void
{
    $db      = getDB();
    $maxPost = (int)getSetting('max_posts', MAX_POSTS);

    $count = (int)$db->query('SELECT COUNT(*) FROM social_posts')->fetchColumn();

    if ($count >= $maxPost) {
        // 刪除最舊一篇（ON DELETE CASCADE 會自動刪除對應留言）
        $db->exec(
            'DELETE FROM social_posts
             WHERE id = (SELECT id FROM (SELECT id FROM social_posts ORDER BY created_at ASC LIMIT 1) AS t)'
        );
    }
}

/**
 * 取得平台首頁統計數字（供 Landing Page 和 Dashboard 使用）
 */
function getPlatformStats(): array
{
    try {
        $db = getDB();
        return [
            'users'   => (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'tutors'  => (int)$db->query('SELECT COUNT(*) FROM tutors WHERE is_active = 1')->fetchColumn(),
            'essays'  => (int)$db->query('SELECT COUNT(*) FROM translate_essays WHERE is_active = 1')->fetchColumn(),
            'posts'   => (int)$db->query('SELECT COUNT(*) FROM social_posts')->fetchColumn(),
        ];
    } catch (PDOException $e) {
        return ['users' => 0, 'tutors' => 0, 'essays' => 0, 'posts' => 0];
    }
}
