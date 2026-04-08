# 文樞 v4 ── 完整合併計劃（修訂版 2.0）

> 版本：Draft 2.0 ｜ 日期：2026-04-08  
> **平台語言：PHP 8.0+（多檔案模組化）+ MySQL + Vanilla JS / Tailwind CSS**

---

## 一、現有版本盤點

| 檔案 | 特色功能 | 狀態 |
|------|---------|------|
| `v1.HTML` | 純文言文翻譯工具（逐字解析） | 已棄用，邏輯移植 |
| `v2.html` | 翻譯 + 初步角色/關卡框架 | 已棄用 |
| `v3.HTML` | **主 UI 基礎**：蘇軾/韓愈 × 5 關、翻譯、測驗、古人社群、浮動原文窗格 | 有 bugs，API 失效 |
| `mensyu.html` | v3 改良版，翻譯分離至獨立頁面 | 部分功能殘缺 |
| `mensyu2.html` | pollinations.ai API + 多模型切換 + 登入守衛 | 無遊戲廳 |
| `mensyu-tran.html` | 獨立翻譯工具 + 打磚塊遊戲（文磚挑戰） | 功能完整 |

**結論**：以 `v3.HTML` 的 UI 為設計基礎，全面遷移至 **PHP + MySQL** 平台，拆分為多個 PHP 檔案。

---

## 二、已知 Bugs（待修復）

| # | 問題 | 所在版本 | 修復方向 |
|---|------|---------|---------|
| 1 | API 使用舊端點 `chatapi.akash.network`，金鑰已失效 | v3、v1 | 改用 pollinations.ai + 新金鑰，AI proxy 由 PHP 後端代理 |
| 2 | 第 5 關（自選篇章）無固定內容，翻譯 AI 無從比對 | v3 | 補充第 4、5 關固定作品 |
| 3 | 關卡解鎖判斷：完成「任意 1 關」即解鎖角色，應改為「第 1 關通過才解鎖」 | v3 | 修正 `getUnlockedCharacters()` 邏輯 |
| 4 | 手機版浮動原文窗格覆蓋翻譯內容，關閉後無法重開 | v3 | 改善 `hidden-mobile` / `no-margin` 邏輯 |
| 5 | 測驗完成後「下一關卡」不自動更新進度條 | v3、mensyu2 | `nextLevel()` 內呼叫 `updateLevelStatus()` |
| 6 | `postGenerationIntervals` 在頁面切換時未清除，導致重複 POST | v3 | `showPage()` 中加入 interval 清理 |
| 7 | 翻譯快取 key 碰撞：不同作者相同文字會取到錯誤快取 | v3 | 快取 key 改為 `userId + authorId + levelId` |
| 8 | 手機版 quiz 按鈕寬度溢出 | v3、mensyu2 | 加 `overflow-x:hidden` 及 flex-wrap |
| 9 | 打磚塊遊戲在 iOS Safari 音效報錯 | mensyu-tran | 加 try/catch + silent fallback |
| 10 | 無 `<meta name="description">` 及 Open Graph 標籤 | 所有版本 | 於每個頁面 `<head>` 補充 SEO 元數據 |
| 11 | 函數名稱不統一（`generatePost` vs `generateAIPost`、`showPage` vs 直接 URL 跳轉等） | 各版本 | 統一命名規範（見第八節） |

---

## 三、平台技術架構

### 3.1 技術棧

| 層 | 技術 |
|----|------|
| 後端 | PHP 8.0+（純函數式 + include 模組化） |
| 資料庫 | MySQL 5.7+（InfinityFree `if0_41581260_mensyu`） |
| 前端 | Tailwind CSS (CDN) + Vanilla JS |
| 字型 | Google Fonts：Noto Serif TC / Noto Sans TC |
| AI | pollinations.ai API（PHP 後端代理，隱藏金鑰） |
| 主機 | InfinityFree（PHP + MySQL） |

### 3.2 資料庫連線（config 安全儲存）

```
Host:     sql111.infinityfree.com
Port:     3306
DB Name:  if0_41581260_mensyu
User:     if0_41581260
Password: hfy23whc
```

> ⚠️ **此憑證僅存放於 `config/db.php`，絕不出現在前端任何 JS 或 HTML 檔案中。**

---

## 四、目錄結構（PHP 多檔案模組化）

```
mensyu-v4/
├── index.php               ← Landing Page（公開）
├── .htaccess               ← URL 重寫 + 安全設定
├── sitemap.xml             ← SEO 網站地圖
├── robots.txt              ← SEO 爬蟲指引
│
├── config/
│   ├── db.php              ← PDO 連線 + 資料庫憑證（🔒 受保護）
│   ├── ai.php              ← AI API 金鑰 + 模型清單（🔒 受保護）
│   └── app.php             ← 全域常數（APP_NAME、BASE_URL 等）
│
├── includes/
│   ├── session.php         ← Session 啟動 + CSRF token
│   ├── auth.php            ← 登入驗證 helper（isLoggedIn、requireLogin）
│   ├── header.php          ← 公用頭部 HTML（Nav Bar）
│   ├── footer.php          ← 公用底部 HTML
│   └── functions.php       ← 統一全域函數庫
│
├── pages/
│   ├── login.php           ← 登入 / 注冊頁（公開）
│   ├── logout.php          ← 登出處理
│   ├── dashboard.php       ← 主平台入口（需登入）
│   ├── learning.php        ← 學習地圖（需登入）
│   ├── translate.php       ← 翻譯工坊（獨立入口，需登入）
│   ├── social.php          ← 古人社群（需登入）
│   ├── games.php           ← 遊戲廳（需登入）
│   └── profile.php         ← 我的成就 + 個人資料（需登入）
│
├── api/
│   ├── auth_handler.php    ← 登入/注冊 AJAX 端點
│   ├── ai_proxy.php        ← AI API 代理（隱藏 API 金鑰）
│   ├── progress.php        ← 讀寫學習進度 AJAX 端點
│   ├── posts.php           ← 古人社群貼文 CRUD
│   ├── comments.php        ← 留言 CRUD
│   └── achievements.php    ← 成就解鎖 AJAX 端點
│
└── data/
    └── essays.php          ← 16 篇 DSE 範文（PHP 陣列，直接 include）
```

### 3.3 .htaccess 安全設定

- 禁止直接存取 `config/` 目錄
- URL 重寫（`/translate` → `pages/translate.php` 等）
- 強制 HTTPS（上線後）

---

## 五、資料庫設計（MySQL）

### 表格清單

| 表格 | 用途 |
|------|------|
| `users` | 用戶帳號 + 密碼（bcrypt）|
| `user_progress` | 每位用戶各關卡完成狀態 |
| `translation_cache` | AI 翻譯快取（避免重複 API 呼叫）|
| `posts` | 古人社群貼文 |
| `comments` | 貼文留言 |
| `achievements` | 用戶已解鎖成就 |
| `game_scores` | 遊戲廳最高分記錄 |

### 主要表格欄位

**`users`**
```
id (PK), username, email, password_hash, role (student/admin),
created_at, last_login_at
```

**`user_progress`**
```
id (PK), user_id (FK), author_id, level_num,
is_completed, score, completed_at, updated_at
```

**`translation_cache`**
```
id (PK), cache_key (author_id+level_id 或 text_hash),
translation_json (TEXT), created_at
```

**`posts`**
```
id (PK), author_id, content, created_at
```

**`comments`**
```
id (PK), post_id (FK), user_id (FK, nullable for AI),
author_id (nullable for AI), content, created_at
```

**`achievements`**
```
id (PK), user_id (FK), badge_key, unlocked_at
```

**`game_scores`**
```
id (PK), user_id (FK), game_type, score, achieved_at
```

---

## 六、登入系統設計

### 6.1 流程

```
訪問任何受保護頁面
 └─ session 未登入？
      └─ 重定向至 /login
           ├─ 登入成功 → 恢復原目標頁
           └─ 注冊成功 → 跳轉 /dashboard
```

### 6.2 功能清單

- 用戶注冊：username + email + password（bcrypt 雜湊）
- 用戶登入：email + password，記住我（30天 cookie）
- 登出：銷毀 session + cookie
- CSRF 保護：每個表單帶 `csrf_token`
- 密碼重設：（可選，Phase 2）

### 6.3 進度同步策略

| 資料類型 | 儲存位置 | 說明 |
|---------|---------|------|
| 關卡完成狀態 | MySQL `user_progress` | 每次通過測驗後 AJAX POST 至 `api/progress.php` |
| 翻譯快取 | MySQL `translation_cache` | 首次翻譯後存入，下次直接讀取 |
| 遊戲分數 | MySQL `game_scores` | 遊戲結束後提交 |
| 成就 | MySQL `achievements` | 觸發條件達成時 AJAX POST |
| 社群貼文/留言 | MySQL `posts` / `comments` | 即時 AJAX |

---

## 七、各頁面功能規格

### 7.1 Landing Page（`index.php`）

| 區塊 | 內容 |
|------|------|
| **Hero** | Logo + 標語「用遊戲征服文言文」+ 登入/注冊 CTA |
| **痛點數據** | 教育學報 2017：50% 不主動閱讀 / 字詞認讀 38.33% / 句式理解 26.65% |
| **學習旅程** | 6 步驟圖示：閱讀 → 翻譯 → 測驗 → 聊天 → 遊戲 → 成就 |
| **功能展示** | 5 大模組卡片（hover 動畫） |
| **古人介紹** | 蘇軾 / 韓愈角色卡 |
| **數字牆** | 16 篇範文 / 2 位古人 / 10 關卡 / 3 種遊戲 |
| **SEO** | `<title>`、`<meta description>`、Open Graph、結構化資料 JSON-LD |
| **Footer** | 靈感來源、版權 |

---

### 7.2 登入 / 注冊（`pages/login.php`）

- 單一頁面，Tab 切換「登入 / 注冊」
- AJAX 提交至 `api/auth_handler.php`
- 登入成功後 redirect 至 `pages/dashboard.php`
- 第一位注冊用戶自動設為管理員

---

### 7.3 主平台（`pages/dashboard.php`）

導航列（Nav）：
```
[文樞 Logo]  [學習地圖]  [翻譯工坊]  [古人社群]  [遊戲廳]  [我的成就]  [用戶名 ▼]
```

- 歡迎頁：顯示學習進度摘要、最近成就、「繼續學習」快捷按鈕
- 手機版：底部固定 5-icon Tab Bar

---

### 7.4 學習地圖（`pages/learning.php`）

**地圖展示**：
```
[選擇古人：蘇軾 | 韓愈]
  │
  ▼
關1 ✓ ──→ 關2 ✓ ──→ 關3 🔒 ──→ 關4 🔒 ──→ 關5 🔒
```

**每關五步學習法**（與 v3 一致，資料存 MySQL）：

| 步驟 | 名稱 | 說明 |
|------|------|------|
| ① | 閱讀原文 | 顯示文言文，常見字詞粗體標示，點擊顯示解釋 |
| ② | 翻譯理解 | AI 逐字翻譯（先從快取讀取，否則呼叫 AI proxy） |
| ③ | 關鍵詞遊戲 | 文言配對遊戲（見 7.6） |
| ④ | 古人對話 | AI 角色扮演聊天室 |
| ⑤ | 闖關測驗 | AI 生成 5 題選擇題，≥60% 通關，結果存 MySQL |

**解鎖條件**：
- 第 1 關：預設解鎖
- 第 N 關：前一關 `user_progress.is_completed = 1`

---

### 7.5 翻譯工坊（`pages/translate.php`）⭐ 獨立入口

**獨立直接入口**：可從 Landing Page、導航列、以及直接 URL `/translate` 進入（不需先過學習地圖）。

**功能**：
- **範文庫下拉選單**：預設全部 16 篇 DSE 範文，選擇後自動填入文本框
- **自由輸入**：貼上任意文言文段落
- 「翻譯解析」按鈕 → 呼叫 `api/ai_proxy.php`
- 翻譯結果顯示：原文 / 逐字對譯 / 句子意思（三欄）
- 常見文言字詞粗體標示，點擊展開解釋
- 浮動原文窗格（桌面右側；手機摺疊頂部）
- 「點擊顯示」遮蓋模式（學生先自行翻譯）
- **打磚塊遊戲入口**（基於翻譯文本生成磚塊字詞）
- **文言配對遊戲入口**（基於翻譯文本生成配對題）
- 翻譯快取：`translation_cache` 表，key = `md5(text)` + `user_id`

#### DSE 範文預設清單（id 1–16）

| ID | 作者 | 篇名 |
|----|------|------|
| 1 | 蘇軾 | 超然臺記 |
| 2 | 蘇軾 | 方山子傳 |
| 3 | 蘇軾 | 前赤壁賦 |
| 4 | 韓愈 | 雜說四（馬說） |
| 5 | 韓愈 | 送孟東野序 |
| 6 | 韓愈 | 答李翊書 |
| 7 | 歐陽修 | 醉翁亭記 |
| 8 | 王安石 | 遊褒禪山記 |
| 9 | 柳宗元 | 始得西山宴遊記 |
| 10 | 劉禹錫 | 陋室銘 |
| 11 | 周敦頤 | 愛蓮說 |
| 12 | 曾鞏 | 墨池記 |
| 13 | 歐陽修 | 伶官傳序 |
| 14 | 王羲之 | 蘭亭集序 |
| 15 | 陶潛 | 歸去來辭（並序） |
| 16 | 諸葛亮 | 出師表 |

全文預載於 `data/essays.php`（PHP 陣列），翻譯頁 `include` 即可，無需資料庫查詢。

---

### 7.6 遊戲廳（`pages/games.php`）

#### 遊戲一：文磚挑戰（Breakout）
- 來源：`mensyu-tran.html` 打磚塊邏輯
- 磚塊顯示文言字詞，底部顯示「正確釋義」，打中正確磚塊得分
- iOS Safari 音效報錯已修復（try/catch fallback）
- 分數 AJAX POST 至 `api/progress.php` → 存入 `game_scores`

#### 遊戲二：文言配對（Matching）⭐ 新增
- **配對機制**：左欄顯示文言字詞，右欄顯示現代語譯，用戶點擊兩側配對
- **題目來源**：
  - 學習地圖關卡模式：從當前篇章 AI 提取 8–10 對字詞
  - 翻譯工坊模式：從剛翻譯的文本提取字詞
  - 遊戲廳自由模式：從 16 篇範文隨機抽取 8 對
- **計時挑戰**：60 秒內完成，錯誤配對閃紅，正確閃綠 + 消除
- **計分**：每對 10 分，剩餘時間獎勵分（+1 分/秒）
- **完成後**：成績儲存至 `game_scores`，觸發成就檢查

#### 遊戲三：填字闖關（Fill-in-the-Blank）
- 句子挖空，從 4 個選項選出正確文言字詞
- 題目由 AI 從當前篇章生成（5 題）

---

### 7.7 古人社群（`pages/social.php`）

- Instagram 風格動態牆（從 MySQL `posts` 表讀取）
- 古人（蘇軾/韓愈）由 PHP cron / 偽 cron（訪問觸發）定時生成 AI 貼文
- 用戶可留言，留言存入 `comments` 表，AI 自動回覆（AJAX）
- 解鎖條件：對應古人第 1 關 `user_progress.is_completed = 1`

---

### 7.8 我的成就（`pages/profile.php`）

| 成就 | 觸發條件 |
|------|---------|
| 🌱 初探文樞 | 完成第一次翻譯 |
| 📖 博覽群書 | 翻譯 5 篇不同作品 |
| ⚔️ 初出茅廬 | 通過第 1 關 |
| 🏆 文豪之路 | 通過任一角色全部 5 關 |
| 💬 對話古今 | 與古人對話 10 次 |
| 🎮 遊戲達人 | 遊戲廳累積 1000 分 |
| 🃏 配對高手 | 文言配對遊戲一次全對 |

- 顯示個人資料、總學習進度、各關卡狀態、已解鎖成就徽章
- 顯示遊戲廳最高分排行（前 10 名）

---

## 八、統一函數命名規範（PHP + JS）

### 8.1 JavaScript 函數命名（camelCase）

| 舊名（各版本混用）| 新統一名稱 | 用途 |
|---------------|-----------|------|
| `showPage` / `loadPage` | `navigateTo(pageId)` | 頁面切換 |
| `generatePost` / `generateAIPost` | `generateAiPost(authorId)` | 生成 AI 社群貼文 |
| `generateComment` / `triggerAIComments` | `generateAiComment(postId, authorId)` | 生成 AI 留言 |
| `translateText` / `callTranslateAPI` | `translateText(text, title)` | 呼叫翻譯 API |
| `displayFormattedResult` / `displayTranslationResult` | `renderTranslation(data)` | 渲染翻譯結果 |
| `setupClickToReveal` / `setupWordExplanations` | `initRevealMode()` | 初始化點擊顯示 |
| `updateLevelStatus` / `updateLearningPage` | `refreshLevelUI(authorId)` | 更新關卡 UI |
| `getUnlockedCharacters` | `getUnlockedAuthors()` | 取得已解鎖古人 |
| `selectAuthor` / `selectLevel` | `selectAuthor(id)` / `selectLevel(n)` | 選擇古人/關卡 |
| `startQuiz` / `loadQuiz` | `startQuiz()` | 開始測驗 |
| `nextQuestion` / `submitQuiz` | `nextQuestion()` / `submitQuiz()` | 測驗流程 |
| `retryQuiz` | `retryQuiz()` | 重試測驗 |
| `nextLevel` | `nextLevel()` | 前往下一關 |
| `showNotification` | `showNotification(msg, type)` | 顯示通知 |
| `initFloatingWindow` | `initFloatingPanel()` | 初始化浮動原文窗格 |

### 8.2 PHP 函數命名（snake_case）

| 函數 | 用途 |
|------|------|
| `get_db()` | 返回 PDO 連線 |
| `require_login()` | 檢查登入，否則重定向 |
| `is_logged_in()` | 返回 bool |
| `get_user_progress($user_id, $author_id)` | 讀取學習進度 |
| `save_user_progress($user_id, $author_id, $level, $score)` | 寫入進度 |
| `get_translation_cache($cache_key)` | 讀取翻譯快取 |
| `save_translation_cache($cache_key, $json)` | 寫入翻譯快取 |
| `get_essays()` | 返回全部 DSE 範文陣列 |
| `get_essay_by_id($id)` | 返回單篇範文 |
| `call_ai_api($messages, $model)` | 呼叫 AI（伺服器端） |
| `unlock_achievement($user_id, $badge_key)` | 解鎖成就 |
| `get_achievements($user_id)` | 讀取成就 |

---

## 九、AI API 整合（PHP 後端代理）

### 9.1 為何使用後端代理

所有 AI 請求須經由 `api/ai_proxy.php` 轉發，**前端不直接接觸 API 金鑰**。

```
前端 JS
  └─ fetch('/api/ai_proxy.php', { messages, task_type })
       └─ PHP ai_proxy.php
            └─ curl → gen.pollinations.ai
                  ├─ 成功 → 返回 JSON
                  └─ 失敗 → 切換下一個 model，最多嘗試 4 次
```

### 9.2 金鑰與模型（`config/ai.php`，受保護）

```php
define('POLLINATIONS_SK', 'sk_I9LbeRaewORSMEdm2ontKkJEHgimbE1v');
define('POLLINATIONS_PK', 'pk_ZQ4XnvfBU2tu6riY');
define('AI_MODELS', ['deepseek', 'glm', 'qwen-large', 'qwen-safety']);
define('AI_API_URL', 'https://gen.pollinations.ai/v1/chat/completions');
```

### 9.3 自動切換邏輯

- 依序嘗試 `AI_MODELS` 陣列
- 任一 model 返回 `200` 即停止
- 全部失敗 → 返回 `{"error": "AI 暫時不可用，請稍後再試"}`

---

## 十、SEO 方案（讓平台可在 Google 搜索到）

### 10.1 技術 SEO 配置

| 項目 | 實現方式 |
|------|---------|
| `<title>` | 每頁獨立標題，如「文樞 - 文言文互動學習平台 \| 翻譯工坊」 |
| `<meta description>` | 每頁 150 字以內描述 |
| Open Graph 標籤 | `og:title`, `og:description`, `og:image`, `og:url` |
| 結構化資料 | JSON-LD `WebSite` + `EducationalOrganization` |
| `sitemap.xml` | 列出所有公開頁面 URL |
| `robots.txt` | 允許 Googlebot，禁止 `config/`、`api/` |
| 語言標籤 | `<html lang="zh-Hant">` |
| 規範 URL | `<link rel="canonical" href="...">` |

### 10.2 內容 SEO（讓 Google 找到）

- Landing Page 包含關鍵詞：「文言文學習」、「DSE 中文」、「文言翻譯」、「文言文遊戲」
- 每篇 DSE 範文在翻譯工坊有獨立 `<h1>` 標題（靜態 HTML，可被爬蟲索引）
- 登入後才能互動的功能，但 Landing Page 和翻譯工坊首頁為公開可索引頁面

### 10.3 Google 收錄步驟（上線後操作）

1. 在 [Google Search Console](https://search.google.com/search-console) 新增網站
2. 使用 HTML 標籤驗證（在 `index.php` 的 `<head>` 加入驗證 meta 標籤）
3. 提交 `sitemap.xml`
4. 使用「URL 檢查」工具請求索引

---

## 十一、UI 設計規範（延續 v3）

### 顏色系統
```css
--primary-color:   #7fb3d5   /* 主色（藍灰） */
--secondary-color: #a2d9ce   /* 次色（青綠） */
--highlight-color: #7dcea0   /* 強調（翠綠） */
--background-color:#f8fafa   /* 背景 */
--text-color:      #2c3e50   /* 正文 */
--error-color:     #e74c3c   /* 錯誤 */
```

### 字型
- 標題：`Noto Serif TC`（傳統、優雅）
- 內文：`Noto Sans TC`（清晰易讀）

### 組件（與 v3 相同）
- 玻璃態卡片（`.glass-effect`）
- 漸層按鈕（`.gradient-primary/secondary/accent`）
- 圓角 12px（`--border-radius`）
- 微動畫：hover 上浮 / 解鎖閃光 / slideDown 展開

---

## 十二、響應式方案

| 斷點 | 佈局 |
|------|------|
| `≥ 1024px` | 側邊/頂部導航 + 主內容 + 浮動原文（翻譯頁） |
| `768px – 1023px` | 頂部導航 + 主內容 |
| `< 768px` | 底部 Tab Bar（5 icon）+ 浮動原文摺疊頂部 |

---

## 十三、執行計劃（分階段）

### Phase 0：環境搭建
- [ ] 建立目錄結構
- [ ] 設定 `config/db.php`（PDO + 自動建表）
- [ ] 設定 `config/ai.php`、`config/app.php`
- [ ] 設定 `.htaccess`（URL 重寫 + 保護 config/）

### Phase 1：登入系統
- [ ] `pages/login.php`（注冊/登入 UI）
- [ ] `api/auth_handler.php`（AJAX 處理）
- [ ] `includes/auth.php`（`is_logged_in()`、`require_login()`）
- [ ] Session 管理 + CSRF 保護

### Phase 2：Landing Page
- [ ] `index.php`（SEO 完整）
- [ ] `sitemap.xml`、`robots.txt`

### Phase 3：翻譯工坊（獨立入口）
- [ ] `pages/translate.php`
- [ ] `data/essays.php`（16 篇全文預載）
- [ ] `api/ai_proxy.php`（AI 代理 + 多模型切換）
- [ ] 翻譯結果渲染（`renderTranslation()`）
- [ ] 浮動原文窗格（修復手機版 Bug #4）
- [ ] 翻譯快取至 MySQL

### Phase 4：學習地圖
- [ ] `pages/learning.php`（地圖 UI + 5 關進度）
- [ ] 五步學習法流程
- [ ] `api/progress.php`（讀寫進度）
- [ ] 修復 Bug #2、#3、#5、#7

### Phase 5：遊戲廳
- [ ] `pages/games.php`
- [ ] 遊戲一：文磚挑戰（移植自 mensyu-tran.html，修復 Bug #9）
- [ ] 遊戲二：文言配對（全新實現）
- [ ] 遊戲三：填字闖關
- [ ] 分數儲存至 `game_scores`

### Phase 6：古人社群
- [ ] `pages/social.php`（Instagram feed UI）
- [ ] `api/posts.php`、`api/comments.php`
- [ ] AI 自動發文（偽 cron）
- [ ] 修復 Bug #6

### Phase 7：成就系統
- [ ] `pages/profile.php`
- [ ] `api/achievements.php`
- [ ] 觸發條件整合至各功能模組

### Phase 8：SEO & 最終測試
- [ ] 補充所有頁面 SEO meta（修復 Bug #10）
- [ ] 統一所有函數名稱（修復 Bug #11）
- [ ] 手機 / 桌面 / API 故障降級全面測試
- [ ] `sitemap.xml` 提交 Google Search Console

---

*請確認此修訂計劃後，我將正式開始 Phase 0 編碼。*
