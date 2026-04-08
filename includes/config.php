<?php
/**
 * 文樞平台核心設定檔
 * 此檔案含有敏感資料，請勿公開或提交至版本控制
 */

// ── 資料庫連線設定 ──────────────────────────────────────────────────────────
define('DB_HOST', 'sql111.infinityfree.com');
define('DB_PORT', '3306');
define('DB_USER', 'if0_41581260');
define('DB_PASS', 'hfy23whc');
define('DB_NAME', 'if0_41581260_mensyu');
define('DB_CHARSET', 'utf8mb4');

// ── AI API 設定 ─────────────────────────────────────────────────────────────
define('AI_API_URL', 'https://gen.pollinations.ai/v1/chat/completions');

// API Keys（按優先順序排列，失敗時自動切換至下一個）
define('AI_API_KEYS', [
    'sk_I9LbeRaewORSMEdm2ontKkJEHgimbE1v',
    'pk_ZQ4XnvfBU2tu6riY',
]);

// 可用模型（同一個 key 內按優先順序嘗試）
define('AI_MODELS', [
    'deepseek',
    'glm',
    'qwen-large',
    'qwen-safety',
]);

// ── 平台設定 ─────────────────────────────────────────────────────────────────
define('APP_NAME', '文樞');
define('APP_SUBTITLE', '古典文學互動學習平台');
define('APP_VERSION', '2.0.0');
define('BASE_URL', '');          // 若部署於子目錄，請填入，例如 '/mensyu'

// 社群動態上限（FIFO：超出時自動刪除最舊一篇）
define('MAX_POSTS', 80);

// 預設古人動態自動生成間隔（分鐘）
define('DEFAULT_POST_INTERVAL', 60);

// 翻譯緩存有效期（秒，預設 7 天）
define('TRANSLATION_CACHE_TTL', 604800);

// 測驗通過分數下限（百分制）
define('QUIZ_PASS_SCORE', 60);

// ── 環境模式 ─────────────────────────────────────────────────────────────────
// 正式環境設為 false，開發時設為 true（顯示詳細錯誤）
define('DEBUG_MODE', false);

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
