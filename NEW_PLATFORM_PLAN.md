# 文樞 全新整合平台 — 完整設計計劃書

> **狀態：待確認** — 請閱讀後確認，確認後才開始編寫程式碼。

---

## 一、專案概覽

**平台名稱：** 文樞（Mensyu）  
**技術棧：** PHP（後端） + MySQL（資料庫） + HTML/CSS/JS（前端）  
**設計基礎：** v3.HTML 的 UI 風格（配色、排版、動畫）全面保留  
**語言：** 全平台繁體中文  
**響應式設計：** 支援手機（iOS/Android）、iPad、桌面電腦  
**靈感來源：** 教育學報 2017，第45卷第2期，頁161–181

---

## 二、目錄結構（PHP 模組化）

```
/mensyu/
│
├── index.php                  ← 登入 / 首頁 Landing Page
├── register.php               ← 用戶註冊
├── dashboard.php              ← 用戶主頁（登入後）
│
├── /learning/
│   ├── index.php              ← 文學導師（關卡）選擇頁
│   ├── level.php              ← 關卡學習頁（作品閱讀、翻譯解析）
│   ├── quiz.php               ← 關卡測驗（AI 生成選擇題）
│   └── result.php             ← 通關結果頁
│
├── /translate/
│   ├── index.php              ← 文言翻譯獨立入口（獨立頁面）
│   └── essay_list.php         ← 預設文章列（UI 選擇器）
│
├── /social/
│   ├── index.php              ← 古人社群（社交動態牆）
│   └── chat.php               ← 與古人私訊對話
│
├── /games/
│   ├── index.php              ← 小遊戲大廳
│   ├── matching.php           ← 遊戲一：文言配對
│   └── fill.php               ← 遊戲二：文言填充（未來擴展）
│
├── /admin/
│   ├── index.php              ← 管理後台主頁（需管理員登入）
│   ├── tutors.php             ← 文學導師管理（增刪改）
│   ├── levels.php             ← 關卡內容管理（增刪改作品）
│   ├── essays.php             ← 翻譯文章庫管理（增刪改分類）
│   ├── users.php              ← 用戶管理（設定管理員）
│   └── matching_data.php      ← 文言配對詞組管理
│
├── /api/
│   ├── ai_call.php            ← 統一 AI API 呼叫（含 key/model 自動切換）
│   ├── translate.php          ← 翻譯 API 端點
│   ├── quiz_gen.php           ← 生成測驗題目
│   ├── social_post.php        ← 社群動態（AI 古人生成）
│   └── chat.php               ← 與古人對話 API
│
├── /includes/
│   ├── config.php             ← 資料庫連線設定（受保護）
│   ├── auth.php               ← 登入驗證函數
│   ├── session.php            ← Session 管理
│   └── functions.php          ← 通用工具函數
│
├── /assets/
│   ├── /css/
│   │   └── main.css           ← 全域樣式（源自 v3.HTML）
│   ├── /js/
│   │   ├── api.js             ← AI 呼叫封裝（前端）
│   │   ├── learning.js        ← 學習模組 JS
│   │   ├── translate.js       ← 翻譯模組 JS
│   │   ├── games.js           ← 遊戲模組 JS
│   │   └── social.js          ← 社群模組 JS
│   └── /images/               ← 圖片資源
│
└── sitemap.xml                ← SEO 搜尋引擎地圖
```

---

## 三、資料庫設計（MySQL：if0_41581260_mensyu）

### 3.1 用戶表 `users`
| 欄位 | 類型 | 說明 |
|---|---|---|
| id | INT PK AUTO | 用戶 ID |
| username | VARCHAR(50) | 用戶名 |
| email | VARCHAR(100) | 電郵（唯一） |
| password_hash | VARCHAR(255) | bcrypt 加密密碼 |
| role | ENUM('user','admin') | 角色（首位注冊者自動成為 admin） |
| avatar | VARCHAR(255) | 頭像 URL |
| xp | INT DEFAULT 0 | 經驗值 |
| created_at | TIMESTAMP | 注冊時間 |

### 3.2 文學導師表 `tutors`
| 欄位 | 類型 | 說明 |
|---|---|---|
| id | INT PK AUTO | 導師 ID |
| name | VARCHAR(50) | 導師名稱（如：蘇軾） |
| dynasty | VARCHAR(50) | 朝代 |
| description | TEXT | 簡介 |
| personality | TEXT | 性格設定（供 AI 參考） |
| language_style | TEXT | 語言風格（供 AI 參考） |
| avatar_url | VARCHAR(255) | 頭像圖片 URL |
| gradient_class | VARCHAR(50) | 卡片漸變色 |
| is_active | TINYINT(1) | 是否啟用 |
| sort_order | INT | 顯示排序 |

### 3.3 關卡表 `levels`
| 欄位 | 類型 | 說明 |
|---|---|---|
| id | INT PK AUTO | 關卡 ID |
| tutor_id | INT FK | 所屬導師 |
| level_number | INT | 關卡序號（1-N） |
| difficulty | ENUM('初級','進階','中級','高級','專家') | 難度 |
| essay_title | VARCHAR(100) | 作品標題 |
| essay_author | VARCHAR(50) | 作品作者 |
| essay_content | LONGTEXT | 作品全文 |
| notes | TEXT | 關卡備注/教學提示 |
| is_active | TINYINT(1) | 是否啟用 |

### 3.4 翻譯文章表 `translate_essays`
| 欄位 | 類型 | 說明 |
|---|---|---|
| id | INT PK AUTO | 文章 ID |
| title | VARCHAR(100) | 文章標題 |
| author | VARCHAR(50) | 作者 |
| dynasty | VARCHAR(30) | 朝代 |
| category | VARCHAR(50) | 分類（如：詩歌、散文） |
| genre | VARCHAR(50) | 文體 |
| content | LONGTEXT | 全文 |
| is_active | TINYINT(1) | 是否顯示 |
| sort_order | INT | 排序 |

### 3.5 用戶學習進度表 `user_progress`
| 欄位 | 類型 | 說明 |
|---|---|---|
| id | INT PK AUTO | 記錄 ID |
| user_id | INT FK | 用戶 ID |
| tutor_id | INT FK | 導師 ID |
| level_id | INT FK | 關卡 ID |
| completed | TINYINT(1) | 是否通過 |
| score | INT | 得分（百分制） |
| attempts | INT | 嘗試次數 |
| completed_at | TIMESTAMP | 通關時間 |

### 3.6 翻譯緩存表 `translation_cache`
| 欄位 | 類型 | 說明 |
|---|---|---|
| id | INT PK AUTO | 緩存 ID |
| text_hash | VARCHAR(64) | 原文 MD5 hash |
| essay_title | VARCHAR(100) | 文章標題 |
| translation_result | LONGTEXT | 翻譯結果（JSON） |
| created_at | TIMESTAMP | 緩存時間 |

### 3.7 社群動態表 `social_posts`
| 欄位 | 類型 | 說明 |
|---|---|---|
| id | INT PK AUTO | 動態 ID |
| author_type | ENUM('user','tutor') | 發布者類型 |
| user_id | INT FK NULL | 用戶 ID（用戶發布時） |
| tutor_id | INT FK NULL | 導師 ID（AI 發布時） |
| content | TEXT | 動態內容 |
| likes | INT DEFAULT 0 | 點讚數 |
| created_at | TIMESTAMP | 發布時間 |

### 3.8 社群留言表 `social_comments`
| 欄位 | 類型 | 說明 |
|---|---|---|
| id | INT PK AUTO | 留言 ID |
| post_id | INT FK | 動態 ID |
| author_type | ENUM('user','tutor') | 留言者類型 |
| user_id | INT FK NULL | 用戶 ID |
| tutor_id | INT FK NULL | 導師 ID |
| content | TEXT | 留言內容 |
| created_at | TIMESTAMP | 留言時間 |

### 3.9 配對遊戲題庫 `matching_pairs`
| 欄位 | 類型 | 說明 |
|---|---|---|
| id | INT PK AUTO | 題目 ID |
| classical_term | VARCHAR(100) | 文言字詞 |
| modern_meaning | VARCHAR(200) | 現代語譯 |
| source_essay | VARCHAR(100) | 出處作品 |
| difficulty | ENUM('初級','進階','中級','高級','專家') | 難度 |
| is_active | TINYINT(1) | 是否啟用 |

---

## 四、登入系統

### 4.1 登入 / 注冊規則
- **首位注冊用戶** 自動獲得 `admin` 角色
- 後續注冊用戶預設為 `user` 角色
- 管理員可在後台「用戶管理」中授予或撤銷管理員權限
- 管理員可在 **一般登入頁面**（`index.php`）直接登入，系統自動識別角色並導向後台

### 4.2 登入流程
1. 用戶在 `index.php` 輸入電郵 + 密碼
2. 系統驗證密碼（bcrypt），核對 `users` 表
3. 若 `role = admin` → 導向 `/admin/index.php`
4. 若 `role = user` → 導向 `dashboard.php`
5. Session 存儲用戶 ID、角色、名稱

### 4.3 安全措施
- 密碼 bcrypt 加密
- SQL 預備語句（Prepared Statements）防止 SQL 注入
- CSRF Token 保護表單
- Session 固定攻擊防護（登入時 regenerate session ID）
- 敏感配置（資料庫密碼、API Keys）僅存於 `config.php`，不暴露於前端

---

## 五、主要功能模組詳細設計

### 5.1 首頁 / 登陸頁（Landing Page）`index.php`

**未登入狀態：** 展示平台介紹 + 登入/注冊入口  
**已登入狀態：** 直接進入 `dashboard.php`

**頁面內容：**
- **英雄區塊（Hero Section）：** 平台名稱「文樞」、副標題「以古為師，以文為橋」、主要行動按鈕（開始學習 / 登入）
- **數據展示：** 引用研究數據（50%學生不主動閱讀；454名學生字詞答對率僅38.33%）突顯問題，強調平台必要性
- **三大功能卡片：**
  - 🎮 遊戲化關卡學習
  - 📖 智能文言翻譯
  - 💬 與古人聊天
- **學習流程說明：** 視覺化四步驟圖示（選擇導師 → 完成關卡 → 解鎖古人 → 互動交流）
- **平台特色：** 逐字解析、AI 問答、配對遊戲、社群互動
- **頁腳：** 版權資訊、來源引用

### 5.2 學習模組（關卡系統）`/learning/`

**核心遊戲學習流程：**

```
選擇文學導師 → 選擇關卡 → 閱讀作品原文
    → 使用翻譯解析工具 → 挑戰問答（AI 生成選擇題）
    → 通關（≥60分）→ 解鎖古人社群
    → 未通關 → 重新挑戰
```

**關卡頁功能：**
- 左側：作品原文（逐字可點擊顯示解釋）
- 右側（浮動面板）：翻譯解析結果
- **常見文言字詞**以粗體標示，突出重點
- 點擊「翻譯解析」→ 呼叫 AI API 返回：
  - 逐字解釋（每個字的意思）
  - 逐句白話文翻譯
  - 全文大意
- 「點擊顯示」交互（隱藏翻譯，點擊才顯示）保持 v3.HTML 互動體驗
- 「開始測驗」按鈕進入問答關卡
- 進度條顯示（已完成關卡數 / 總關卡數）

**測驗（Quiz）功能：**
- AI 根據當前作品生成 5 道選擇題
- 題目類型：字詞釋義、句子翻譯、文章理解、背景知識
- 難度對應關卡等級
- 通過分數 ≥ 60%
- 每題顯示答案解釋
- 通關後：
  - 儲存進度至 MySQL
  - 若首次通關該導師任一關卡 → 解鎖「古人社群」互動
  - 顯示解鎖動畫

**文學導師（Tutor）設計：**
- 支援管理員在後台新增任意數量導師
- 每位導師可設定：姓名、朝代、簡介、性格設定、語言風格、頭像、關卡數量
- 關卡數量、作品內容全部由管理員在後台配置

### 5.3 翻譯模組（獨立入口）`/translate/`

**獨立頁面，無需完成關卡即可使用。**

**功能：**
- 左側輸入區：
  - 文字輸入框（可自由輸入任意文言文）
  - **文章列（UI 選擇器）：** 自訂 UI 下拉選擇器（非瀏覽器原生 select）
    - 按**分類**（詩歌、散文、史傳等）分組顯示
    - 每個分類可展開/收合
    - 選擇文章後自動填入輸入框
  - 「翻譯解析」按鈕 + 「重新翻譯」按鈕 + 「清除」按鈕
- 右側結果區：
  - 浮動原文參考面板（保持 v3.HTML 的浮動視窗設計）
  - 分塊顯示：原文段落、逐字解析、句子翻譯
  - 隱藏文字交互（點擊顯示翻譯）
  - 「全部顯示」按鈕
- 翻譯結果**緩存至 MySQL**，相同文章不重複呼叫 AI

**AI 翻譯 System Prompt（保留 v3.HTML 原有 prompt，適當修改）：**
```
請將以下文言文逐字翻譯並解釋(直譯，不要意譯)，格式要求：
[逐字解析]
每個字/詞：字/詞 → 解釋（意思）

[逐句翻譯]
原文句子：...
白話譯文：[顯示完整句子翻譯]

[文章大意]
（簡要說明全文主旨）
```

### 5.4 小遊戲模組 `/games/`

#### 遊戲一：文言配對（Matching Game）
- 介面：兩列卡片（左列：文言字詞，右列：現代語譯）
- 玩法：點擊左列一個詞，再點擊右列對應語譯，配對正確則高亮消除
- 計時器倒數（難度越高時間越短）
- 難度分級：初級（8對）、進階（12對）、中級（16對）、高級（20對）、專家（25對）
- 題庫由管理員在後台維護，也可按文章篩選
- 完成後顯示分數（正確數、用時、星級評分）
- 配對詞組可與關卡文章關聯

#### 遊戲二：文言填充（未來擴展）
- 給出文句，填入缺失字詞
- 此版本先設計入口，功能留後期開發

### 5.5 古人社群 `/social/`

**解鎖條件：** 完成對應導師至少一個關卡

**功能（保留 v3.HTML 原有設計）：**
- 動態牆（Timeline）：顯示已解鎖導師的 AI 生成動態
- 用戶可發布動態
- 已解鎖古人自動回應用戶動態（AI 生成，70字內，用香港粵語，限引用時才用文言）
- 古人之間互相留言（AI 生成）
- 點讚功能
- 定時自動生成古人新動態（間隔可在後台設定）
- 按導師篩選動態（標籤切換）

**AI 古人動態 System Prompt（保留 v3.HTML，適當調整）：**
```
你現在是{古人名字}，請用{古人名字}的身份在現代社交媒體發布一條動態。
性格：{personality}
語言風格：{language_style}
要求：
1. 不超過70字，用繁體中文
2. 只有引用詩文才用文言文，其他用生活化香港粵語
3. 體現古人對現代生活的思考與感受
4. 不加任何括號解釋或「註：」
5. 不含「*」「#」「【】」等符號
```

### 5.6 與古人對話（Chat）`/social/chat.php`

- 選擇已解鎖的古人
- 對話介面（仿即時通訊 UI）
- 用戶輸入訊息 → AI 以古人身份回應
- 對話記錄存於資料庫（`social_comments` 擴展或新增 `chat_history` 表）
- 保持 v3.HTML 的性格設定和語言風格 Prompt

---

## 六、管理後台（Admin Panel）`/admin/`

**登入方式：** 在一般登入頁（`index.php`）以管理員帳號登入，系統自動識別並導向後台。

### 6.1 後台主頁 `index.php`
- 統計面板：用戶數、關卡數、翻譯文章數、今日動態數
- 快捷操作入口
- 系統狀態（API 狀態指示燈）

### 6.2 文學導師管理 `tutors.php`
- 列表顯示所有導師（含啟用/停用狀態）
- **新增導師：** 填寫以下欄位：
  - 導師名稱
  - 朝代
  - 簡介
  - 性格設定（AI 用）
  - 語言風格（AI 用）
  - 頭像圖片 URL
  - 漸變色樣式選擇
  - 啟用狀態
  - 顯示排序
- **修改導師：** 編輯以上所有欄位
- **刪除導師：** 同時刪除關聯關卡（或提示先移除關卡）

### 6.3 關卡管理 `levels.php`
- 按導師篩選顯示關卡列表
- **新增關卡：**
  - 選擇所屬導師
  - 設定關卡序號
  - 設定難度等級
  - 輸入文章標題
  - 輸入作者名
  - 輸入作品全文（大型文字輸入框）
  - 添加教學備注
- **修改關卡：** 編輯以上所有欄位（含全文修改）
- **刪除關卡：** 同時清除相關學習進度記錄（需確認）
- 拖曳排序（調整關卡順序）

### 6.4 翻譯文章庫管理 `essays.php`
- 列表顯示所有翻譯文章（含分類、作者、啟用狀態）
- **按分類篩選**
- **新增文章：**
  - 文章標題
  - 作者
  - 朝代
  - **分類**（可自訂新增分類，如：詩歌、散文、史傳、論說文等）
  - 文體
  - 全文內容
  - 排序
  - 啟用狀態
- **修改文章：** 編輯以上所有欄位
- **刪除文章**
- 批量操作（啟用/停用選中文章）

### 6.5 配對遊戲題庫管理 `matching_data.php`
- 列表顯示所有配對詞組（文言字詞 ↔ 現代語譯）
- **新增詞組：**
  - 文言字詞
  - 現代語譯
  - 出處作品
  - 難度等級
  - 啟用狀態
- **批量匯入：** 支援從文章自動提取（管理員選擇文章後，AI 提取字詞配對）
- **修改 / 刪除詞組**

### 6.6 用戶管理 `users.php`
- 用戶列表（用戶名、電郵、角色、注冊時間、XP）
- **授予管理員權限**
- **撤銷管理員權限**（不可撤銷自己的管理員身份）
- **停用用戶帳號**
- 查看用戶學習進度

### 6.7 系統設定（整合進各管理頁）
- 社群動態生成間隔（分鐘）
- API 密鑰輪換設置（可查看但不顯示完整密鑰）

---

## 七、AI API 整合

### 7.1 API 設定（`config.php` 儲存，不暴露前端）

```
API 端點：https://gen.pollinations.ai/v1/chat/completions
API Keys：
  - sk_I9LbeRaewORSMEdm2ontKkJEHgimbE1v（主要）
  - pk_ZQ4XnvfBU2tu6riY（備用）

可用模型（按優先順序自動切換）：
  1. deepseek
  2. glm
  3. qwen-large
  4. qwen-safety
```

### 7.2 自動切換邏輯（`/api/ai_call.php`）
1. 使用目前 Key + Model 發出請求
2. 若回應狀態非 200 或發生例外 → 切換下一個 Model
3. 若所有 Model 均失敗 → 切換下一個 API Key
4. 若所有 Key + Model 均失敗 → 返回錯誤訊息給用戶

### 7.3 各功能 System Prompt 策略

| 功能 | System Role | 保留/修改 |
|---|---|---|
| 翻譯解析 | 文言文翻譯學者 | 保留 v3 prompt，格式保持一致 |
| Quiz 生成 | 中文文言文出題老師 | 保留 v3 prompt，JSON 格式不變 |
| 古人動態生成 | 指定古人角色 | 保留 v3 prompt，加入 MySQL 導師設定 |
| 古人留言 | 指定古人角色 | 保留 v3 prompt |
| 與古人對話 | 指定古人角色（深度對話） | 保留 v3 prompt，更豐富上下文 |
| 配對詞組提取 | 文言字詞專家 | 新增 prompt |

---

## 八、SEO 優化（讓 Google 可搜尋）

### 8.1 技術 SEO
- 每頁設置唯一 `<title>` 標籤（例如：`文樞 - 文言文互動學習平台 | 蘇軾 韓愈`）
- 設置 `<meta name="description">` 描述每頁內容
- 設置 `<meta name="keywords">` 包含：文言文、文言翻譯、古文學習、蘇軾、韓愈、香港中文
- Open Graph 標籤（方便社交媒體分享）
- 生成 `sitemap.xml` 列出所有公開頁面
- 生成 `robots.txt` 允許搜尋引擎爬取
- 頁面 URL 語意化（如 `/translate/`、`/learning/`）
- 確保頁面在 JavaScript 關閉時仍有基礎內容（PHP 服務端渲染）

### 8.2 內容 SEO
- 每位文學導師和作品頁面有靜態 HTML 內容（不完全依賴 JS 渲染）
- 圖片添加 `alt` 文字
- 標題層次結構清晰（H1 → H2 → H3）
- 內部連結結構清晰

---

## 九、響應式設計規範

| 裝置 | 螢幕寬度 | 佈局策略 |
|---|---|---|
| 手機（直向） | < 640px | 單欄，導航改為漢堡選單 |
| 手機（橫向）/ 小平板 | 640–1023px | 適配雙欄部分 |
| iPad / 中型平板 | 1024–1279px | 雙欄佈局 |
| 桌面 | ≥ 1280px | 完整三欄或雙欄佈局 |

- 保留 v3.HTML 的翻譯浮動視窗行為（桌面固定右側；手機變頂部抽屜）
- 所有按鈕觸控友好（最小 44×44px）
- 字體大小自適應（使用 `clamp()`）

---

## 十、整體學習流程（遊戲化設計）

```
【登入 / 注冊】
      ↓
【首頁 Landing Page — 了解平台特色】
      ↓
【選擇文學導師 — 如：蘇軾 / 韓愈 / 管理員新增的其他導師】
      ↓
【關卡 1（初級）— 閱讀作品原文】
      ↓
【使用翻譯解析工具 — 逐字解析 + 句子翻譯】
      ↓
【挑戰問答（5道 AI 生成選擇題）— 通過率 60%】
      ↓
【通關！解鎖下一關 + 積累 XP】
      ↓
【完成至少 1 關 → 解鎖古人社群動態】
      ↓
【關卡 2-N（進階/中級/高級/專家）— 更難的作品與題目】
      ↓
【解鎖完整古人互動 — 社群動態 + 私訊對話】
      ↓
【小遊戲：文言配對 — 鞏固字詞記憶】
      ↓
【翻譯工具（獨立使用）— 自由翻譯任意文言文】
```

---

## 十一、已知問題修正（Bug Fix）

以下為 v3.HTML 中發現的問題，新版本需修正：

1. **API Keys 硬編碼在前端 JS** → 移至後端 `config.php`，前端通過 PHP API 端點呼叫
2. **進度僅存於 localStorage** → 改為存入 MySQL，確保跨裝置、跨瀏覽器持久化
3. **帖子/社群動態 localStorage 易丟失** → 全部改為 MySQL 存儲
4. **固定只有蘇軾和韓愈** → 改為從資料庫動態讀取，支援無限擴展
5. **翻譯文章只有少量預設** → 改為從資料庫讀取，管理員可自由增刪
6. **無登入系統** → 新增完整登入/注冊功能
7. **測試面板暴露在生產環境** → 移除前端測試面板，功能整合至管理後台
8. **無翻譯緩存（每次重新呼叫 API）** → 新增 MySQL 翻譯緩存，節省 API 用量
9. **function 命名不統一** → 全平台統一命名規範（camelCase）
10. **文章選擇使用瀏覽器原生 select** → 改為自訂 UI 選擇器（UI selection box）

---

## 十二、檔案命名與函數命名規範

### PHP 函數命名（snake_case）
```
get_user_progress()
save_user_progress()
generate_ai_response()
get_translate_essays()
create_social_post()
```

### JavaScript 函數命名（camelCase，全平台統一）
```
showPage()
loadEssayList()
startTranslation()
startQuiz()
submitQuizAnswer()
finishQuiz()
completeLevel()
nextLevel()
createSocialPost()
loadSocialFeed()
startMatchingGame()
checkMatchingPair()
callAiApi()
```

---

## 十三、開發優先順序

| 優先級 | 模組 | 說明 |
|---|---|---|
| P1（必需） | 資料庫 + 基礎架構 | config.php、tables、auth |
| P1（必需） | 登入 / 注冊系統 | index.php、register.php |
| P1（必需） | Landing Page | 完整首頁設計 |
| P1（必需） | 管理後台基礎 | 導師、關卡、文章管理 |
| P1（必需） | 學習模組 | 關卡、翻譯解析、測驗 |
| P1（必需） | 翻譯獨立入口 | 含文章列 UI 選擇器 |
| P2（重要） | 古人社群 | 動態牆、AI 生成 |
| P2（重要） | 文言配對遊戲 | Matching Game |
| P2（重要） | 用戶管理後台 | 角色管理 |
| P3（增強） | 與古人聊天 | 私訊功能 |
| P3（增強） | SEO 優化 | sitemap、meta tags |
| P3（增強） | XP 系統 | 用戶成就與積分 |

---

## 十四、資料庫連線設定（受保護）

```php
// /includes/config.php
// 此檔案不公開，存於伺服器端
define('DB_HOST', 'sql111.infinityfree.com');
define('DB_PORT', '3306');
define('DB_USER', 'if0_41581260');
define('DB_PASS', 'hfy23whc');
define('DB_NAME', 'if0_41581260_mensyu');

define('API_URL', 'https://gen.pollinations.ai/v1/chat/completions');
define('API_KEYS', [
    'sk_I9LbeRaewORSMEdm2ontKkJEHgimbE1v',
    'pk_ZQ4XnvfBU2tu6riY'
]);
define('API_MODELS', ['deepseek', 'glm', 'qwen-large', 'qwen-safety']);
```

---

## 十五、UI 設計規範（源自 v3.HTML）

### 配色
```
--primary-color: #7fb3d5
--secondary-color: #a2d9ce
--accent-color: #a9cce3
--highlight-color: #7dcea0
--soft-color: #76d7c4
--background-color: #f8fafa
--text-color: #2c3e50
```

### 字體
- 標題：Noto Serif TC
- 內文：Noto Sans TC

### 元件風格
- 圓角：12px（卡片）、8px（按鈕）
- 陰影：三級陰影系統（light/medium/heavy）
- 玻璃效果：`backdrop-filter: blur(16px)`（導航欄）
- 動畫：`cubic-bezier(0.4, 0, 0.2, 1)`（0.3s 標準過渡）
- 漸變：三套主要漸變（primary/secondary/accent）

---

## 十六、注意事項

1. **不在前端暴露 API Keys** — 所有 AI 呼叫通過後端 PHP 中轉
2. **所有資料庫操作使用 PDO Prepared Statements** — 防止 SQL 注入
3. **所有表單使用 CSRF Token** — 防止跨站請求偽造
4. **密碼使用 `password_hash()` (bcrypt)** — 禁止明文存儲
5. **翻譯緩存設置過期時間** — 建議 7 天
6. **管理後台所有操作需確認管理員身份**（每頁頂部驗證）
7. **圖片上傳功能**（如有需要）需限制檔案類型和大小

---

> **等待確認後開始編寫程式碼。**  
> 如有任何修改、補充或調整，請在確認前告知。
