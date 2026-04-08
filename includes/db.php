<?php
/**
 * 資料庫 PDO 連線單例
 *
 * 第一次連線時自動執行資料庫安裝程序（ensureInstalled）。
 * 若資料庫表已存在，安裝程序僅執行一次輕量 SHOW TABLES 查詢即返回，
 * 不影響正常請求效能。
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/installer.php';

/**
 * 取得 PDO 資料庫連線實例（單例模式）
 * 首次呼叫時自動安裝資料庫（若尚未安裝）
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                throw $e;
            }
            // 正式環境不洩露資料庫細節
            http_response_code(503);
            die(json_encode(['success' => false, 'message' => '資料庫連線失敗，請稍後再試']));
        }

        // 自動安裝資料庫（首次訪問時建立所有表及種子資料）
        ensureInstalled($pdo);
    }

    return $pdo;
}
