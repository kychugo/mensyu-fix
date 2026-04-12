# 文樞 — 古典文學互動學習平台

> **版本：** 2.0.0 &nbsp;|&nbsp; **語言：** PHP 8.x + MySQL &nbsp;|&nbsp; **AI：** Pollinations.ai

文樞是一個以 AI 驅動的中文古典文學互動學習平台，融合了關卡學習、AI 翻譯解析、文言配對遊戲、古人社群動態及私訊對話等功能，讓學習文言文變得生動有趣。

---

## 功能概覽

| 模組 | 功能說明 |
|------|---------|
| 📚 **關卡學習** | 按文學導師分組的多關卡閱讀，AI 生成個性化選擇題測驗，通關可獲 XP |
| 🤖 **AI 翻譯** | 逐字解析文言文，提供現代白話翻譯（支援關卡一鍵跳轉） |
| 🧩 **文言配對遊戲** | 五種難度的限時詞義配對遊戲，按難度過濾詞庫 |
| 💬 **古人社群** | 全平台共享動態牆，AI 定時模擬古代文豪發帖與留言 |
| 📩 **私訊對話** | 與已解鎖的文學導師 AI 角色即時對話（完成關卡後解鎖） |
| 🏆 **龍虎榜** | XP 排行榜，等級系統（初學者→學童→學士→文人→大儒） |
| ⚙️ **管理後台** | 導師、關卡、翻譯文章庫、配對題庫（含批量 CSV/XLSX 匯入）、用戶管理 |

---

## 快速部署

### 系統需求

- PHP 8.0 或以上（需 `pdo_mysql`、`curl` 擴展）
- MySQL 5.7+ 或 MariaDB 10.x
- Web Server（Apache / Nginx）

### 安裝步驟

1. **上傳檔案**  
   將所有檔案上傳至 Web Server 的文件根目錄（或子目錄）。

2. **配置資料庫**  
   編輯 `includes/config.php`，填寫資料庫連線資訊：
   ```php
   define('DB_HOST', 'your-db-host');
   define('DB_USER', 'your-db-user');
   define('DB_PASS', 'your-db-password');
   define('DB_NAME', 'your-db-name');
   ```

3. **設定 BASE_URL**（若部署於子目錄）  
   ```php
   define('BASE_URL', '/mensyu');  // 例如部署在 /mensyu/ 目錄下
   ```

4. **首次訪問自動安裝**  
   訪問任意 PHP 頁面，系統會自動建立所有資料庫表格並填入初始資料（種子數據）。無需手動執行 SQL。

5. **建立管理員帳號**  
   注冊一個普通帳號後，在資料庫執行：
   ```sql
   UPDATE users SET role = 'admin' WHERE email = 'your@email.com';
   ```

---

## 目錄結構

```
/
├── index.php                  # 首頁 / 登入 / 注冊
├── dashboard.php              # 用戶主頁
├── logout.php                 # 登出
├── mensyu.html                # AI 翻譯工具（純 HTML+JS）
│
├── learning/
│   ├── index.php              # 導師選擇
│   ├── level.php              # 關卡閱讀
│   └── quiz.php               # AI 測驗
│
├── social/
│   ├── index.php              # 社群動態牆
│   ├── chat.php               # 私訊對話
│   └── leaderboard.php        # 龍虎榜
│
├── games/
│   └── matching.php           # 文言配對遊戲
│
├── admin/
│   ├── index.php              # 管理後台概覽
│   ├── tutors.php             # 導師管理
│   ├── levels.php             # 關卡管理
│   ├── essays.php             # 翻譯文章庫
│   ├── matching_data.php      # 配對題庫（含批量匯入）
│   └── users.php              # 用戶管理
│
├── api/
│   ├── quiz_gen.php           # AI 測驗題目生成
│   ├── chat.php               # AI 私訊對話
│   ├── generate_tutor_post.php # 古人動態生成
│   ├── generate_tutor_comment.php # 古人留言生成
│   └── ai_call.php            # 通用 AI API 代理
│
└── includes/
    ├── config.php             # 核心設定
    ├── db.php                 # 資料庫連線 + 自動安裝
    ├── installer.php          # 資料庫表格建立與種子數據
    ├── session.php            # Session 管理、CSRF、認證輔助
    ├── functions.php          # 通用工具函數
    └── partials/
        ├── header.php         # 共用 HTML head + 導航欄
        └── footer.php         # 共用頁腳
```

---

## 配置說明（`includes/config.php`）

| 常量 | 預設值 | 說明 |
|------|--------|------|
| `AI_API_URL` | Pollinations.ai | AI API 端點 |
| `AI_API_KEYS` | 陣列 | API 金鑰（多個，自動輪換） |
| `AI_MODELS` | deepseek, glm, qwen... | AI 模型（按優先順序嘗試） |
| `BASE_URL` | `''` | 部署子目錄前綴 |
| `MAX_POSTS` | `80` | 社群動態上限（FIFO 自動清理） |
| `DEFAULT_POST_INTERVAL` | `60` | 古人動態自動生成間隔（分鐘） |
| `TRANSLATION_CACHE_TTL` | `604800` | 翻譯快取有效期（秒） |
| `QUIZ_PASS_SCORE` | `60` | 測驗通關最低分數 |
| `DEBUG_MODE` | `false` | 開發模式（顯示詳細錯誤） |

---

## 資料庫表格

系統首次運行時自動建立以下 10 個表格：

| 表格 | 說明 |
|------|------|
| `users` | 用戶帳號、角色、XP |
| `tutors` | 文學導師資料（姓名、性格設定、頭像等） |
| `levels` | 學習關卡（文章、難度、所屬導師） |
| `user_progress` | 用戶關卡進度（完成狀態、分數、嘗試次數） |
| `translate_essays` | 翻譯文章庫 |
| `matching_pairs` | 配對遊戲詞庫（文言↔現代語） |
| `social_posts` | 社群動態（用戶/古人發帖） |
| `social_comments` | 社群留言 |
| `chat_messages` | 私訊對話記錄 |
| `settings` | 平台設定鍵值對 |

---

## 安全設計

- **CSRF 保護**：所有 POST 表單均含 `csrf_token`，使用 `hash_equals` 驗證
- **Session 安全**：HttpOnly、SameSite=Lax，HTTPS 環境自動啟用 Secure flag
- **SQL 注入防護**：全面使用 PDO Prepared Statements
- **XSS 防護**：所有輸出使用 `htmlspecialchars()`（`e()` 函數）
- **密碼儲存**：`password_hash()` / `password_verify()`（bcrypt）
- **管理員保護**：管理後台所有頁面均調用 `requireAdmin()`

---

## XP 與等級系統

| 等級 | 所需 XP | 圖示 |
|------|---------|------|
| 初學者 | 0 | 🌱 |
| 學童 | 100 | 📖 |
| 學士 | 300 | 🎓 |
| 文人 | 700 | 🪶 |
| 大儒 | 1500 | 👑 |

**XP 獎勵計算：**
- 通過測驗（≥ 60 分）：20 XP 基礎 + 每超出 10 分獲得 5 XP
- 例：80 分 = 20 + (80-60)/10×5 = 30 XP

---

## 批量匯入配對題庫

在 `admin/matching_data.php` 點擊「批量匯入」，支援：

- **格式：** CSV、TSV、TXT、XLSX（.xlsx / .xls）
- **每行格式：** `文言字詞, 現代語譯, 出處（選填）, 難度（選填）`
- **難度值：** 初級、進階、中級、高級、專家
- **功能：** 即時預覽、行級難度設定、逐行勾選、標題行自動跳過

---

## Bug 修復記錄

| 版本 | Bug | 狀態 |
|------|-----|------|
| 2.0.1 | `admin/essays.php` — `closeModal()` 未重置表單，導致「新增」誤觸發「更新」 | ✅ 已修復 |
| 2.0.1 | `admin/matching_data.php` — 同上，影響配對題組管理 | ✅ 已修復 |
| 2.0.1 | `games/matching.php` — 難度過濾無效（SQL 缺 `difficulty` 欄位，JS 過濾條件為 `true`） | ✅ 已修復 |
| 2.0.1 | `learning/quiz.php` — 選項含單引號時 `onAnswer()` 的 JS 字串拼接錯誤導致高亮失效 | ✅ 已修復 |

---

## 測試文件

詳細測試計劃請參閱 [TESTING.md](./TESTING.md)。

---

## 授權

本項目為學術及教育用途開發。如需商業使用，請聯繫作者。
