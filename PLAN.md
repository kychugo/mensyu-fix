# 文樞平台 — 完整重建計劃書

> 版本：v4.0（PHP 全端，以 v3.HTML 為設計基礎）
> 狀態：待確認，確認前不編寫任何程式碼

---

## 一、平台概覽

### 靈感與背景

- 來源：教育學報，2017，第 45 卷第 2 期，頁 161–181
- 超過 50% 中四學生認為文言文「枯燥乏味」
- 454 名學生字詞認讀平均答對率僅 38.33%
- 句式理解僅 26.65%，學生依賴教師講解

### 核心目標

透過遊戲化學習（Gamification），以**挑戰問答 → 文言翻譯 → 與古人聊天**為主線，配合小遊戲，提升學生學習文言文的興趣與成效。

---

## 二、技術架構

| 項目 | 技術 |
|------|------|
| 後端 | PHP 8.x（模組化多檔案結構） |
| 資料庫 | MySQL（`if0_41581260_mensyu`，主機 `sql111.infinityfree.com:3306`） |
| 前端 | HTML5 + Tailwind CSS（CDN）+ Vanilla JS |
| 字型 | Noto Serif TC / Noto Sans TC（Google Fonts） |
| 圖示 | Font Awesome 6 |
| AI API | pollinations.ai（`gen.pollinations.ai`） |
| 語言 | 全平台繁體中文 |
| 響應式 | 手機 / iPad / 桌面三端自適應 |

### AI API 設定

- **密鑰**（伺服器端，保密在 PHP config）：
  - `sk_I9LbeRaewORSMEdm2ontKkJEHgimbE1v`（Secret Key）
  - `pk_ZQ4XnvfBU2tu6riY`（Publishable Key）
- **可用模型**（依序自動切換，遇錯誤換下一個）：
  1. `deepseek`
  2. `glm`
  3. `qwen-large`
  4. `qwen-safety`
- **切換邏輯**：PHP 後端代理請求，遇 HTTP 錯誤或超時自動嘗試下一模型，前端只呼叫本平台 API 端點

### SEO 搜尋引擎優化

為使平台能在 Google 等搜尋引擎被找到，應做以下設定：

1. **每頁加入 `<meta>` 標籤**：`<title>`、`<meta name="description">`、`<meta name="keywords">`
2. **結構化資料（JSON-LD）**：加入 `WebSite`、`EducationalApplication` schema
3. **Open Graph 標籤**：支援社交分享預覽
4. **sitemap.xml**：自動生成，提交至 Google Search Console
5. **robots.txt**：允許爬蟲
6. **語意化 HTML**：使用 `<article>`、`<section>`、`<nav>` 等標籤
7. **頁面載入速度**：壓縮 CSS/JS，使用 CDN

---

## 三、檔案結構

```
/（網站根目錄）
├── index.php                  # 首頁／Landing Page
├── login.php                  # 登入／注冊頁（含管理員入口）
├── logout.php                 # 登出
├── dashboard.php              # 用戶主頁（選擇學習路線）
│
├── /learn/
│   ├── index.php              # 文學導師選擇頁
│   ├── level.php              # 關卡學習頁
│   ├── quiz.php               # 問答挑戰頁
│   └── result.php             # 關卡結果頁
│
├── /translate/
│   ├── index.php              # 翻譯入口（文章列表）
│   └── view.php               # 翻譯閱讀頁（逐字解析）
│
├── /chat/
│   └── index.php              # 與古人聊天頁
│
├── /games/
│   ├── index.php              # 小遊戲選擇頁
│   └── matching.php           # 遊戲：文言配對
│
├── /social/
│   └── index.php              # 古人社群頁
│
├── /admin/
│   ├── index.php              # 管理員面板主頁
│   ├── authors.php            # 管理文學導師
│   ├── levels.php             # 管理關卡與文章
│   ├── essays.php             # 管理翻譯文章庫
│   ├── users.php              # 管理用戶與管理員權限
│   └── settings.php           # 平台設定
│
├── /api/
│   ├── ai.php                 # AI 呼叫代理（含模型自動切換）
│   ├── auth.php               # 登入／注冊 API
│   ├── progress.php           # 進度存取 API
│   ├── essays.php             # 文章資料 API
│   └── social.php             # 社群功能 API
│
├── /includes/
│   ├── config.php             # 資料庫設定（敏感資訊，不外露）
│   ├── db.php                 # 資料庫連線（PDO）
│   ├── auth_check.php         # 登入驗證中介
│   ├── admin_check.php        # 管理員驗證中介
│   └── functions.php          # 通用函數
│
├── /assets/
│   ├── css/
│   │   └── style.css          # 全域樣式（移植自 v3.HTML CSS 變數）
│   └── js/
│       ├── main.js            # 通用 JS
│       ├── ui-select.js       # 自訂 UI 選擇框
│       └── games.js           # 遊戲邏輯
│
├── sitemap.xml                # SEO sitemap
└── robots.txt                 # SEO robots
```

---

## 四、資料庫設計

### 資料表一覽

#### `users`（用戶）
| 欄位 | 類型 | 說明 |
|------|------|------|
| id | INT PK AUTO | 用戶 ID |
| username | VARCHAR(50) UNIQUE | 用戶名 |
| email | VARCHAR(100) UNIQUE | 電郵 |
| password_hash | VARCHAR(255) | bcrypt 密碼 |
| is_admin | TINYINT(1) | 是否管理員（0/1） |
| created_at | TIMESTAMP | 注冊時間 |
| last_login | TIMESTAMP | 最後登入 |

> **首位注冊用戶自動成為管理員**

---

#### `authors`（文學導師）
| 欄位 | 類型 | 說明 |
|------|------|------|
| id | INT PK AUTO | 導師 ID |
| name | VARCHAR(50) | 導師名稱（如：蘇軾） |
| dynasty | VARCHAR(20) | 朝代 |
| description | TEXT | 簡介 |
| personality | TEXT | 性格（AI 系統提示用） |
| background | TEXT | 背景（AI 系統提示用） |
| style | TEXT | 語言風格（AI 系統提示用） |
| avatar_url | VARCHAR(255) | 頭像圖片網址 |
| is_active | TINYINT(1) | 是否啟用 |
| sort_order | INT | 排序 |

---

#### `levels`（關卡）
| 欄位 | 類型 | 說明 |
|------|------|------|
| id | INT PK AUTO | 關卡 ID |
| author_id | INT FK | 關聯導師 |
| level_number | INT | 關卡號（1–5 等） |
| title | VARCHAR(100) | 關卡標題 |
| difficulty | ENUM | 初級/進階/中級/高級/專家 |
| essay_id | INT FK | 關聯文章 ID |
| pass_score | INT | 通過分數（預設 60） |

---

#### `essays`（文章庫，共用）
| 欄位 | 類型 | 說明 |
|------|------|------|
| id | INT PK AUTO | 文章 ID |
| title | VARCHAR(100) | 文章標題 |
| author | VARCHAR(50) | 作者 |
| dynasty | VARCHAR(20) | 朝代 |
| category | VARCHAR(50) | 類別（詩歌/文言文/記/論說文等） |
| genre | VARCHAR(50) | 體裁 |
| content | LONGTEXT | 原文內容 |
| is_in_translate | TINYINT(1) | 是否顯示於翻譯文章列 |
| is_in_level | TINYINT(1) | 是否可用於關卡 |
| sort_order | INT | 翻譯列表排序 |
| created_at | TIMESTAMP | 建立時間 |

---

#### `essay_categories`（文章分類）
| 欄位 | 類型 | 說明 |
|------|------|------|
| id | INT PK AUTO | ID |
| name | VARCHAR(50) | 分類名稱 |
| sort_order | INT | 排序 |

---

#### `user_progress`（用戶進度）
| 欄位 | 類型 | 說明 |
|------|------|------|
| id | INT PK AUTO | ID |
| user_id | INT FK | 用戶 ID |
| author_id | INT FK | 導師 ID |
| level_id | INT FK | 關卡 ID |
| is_completed | TINYINT(1) | 是否完成 |
| score | INT | 得分 |
| completed_at | TIMESTAMP | 完成時間 |

---

#### `social_posts`（古人社群貼文）
| 欄位 | 類型 | 說明 |
|------|------|------|
| id | INT PK AUTO | 貼文 ID |
| author_id | INT FK NULL | 古人導師 ID（NULL = 用戶） |
| user_id | INT FK NULL | 用戶 ID（NULL = 古人） |
| content | TEXT | 貼文內容 |
| likes | INT | 點讚數 |
| created_at | TIMESTAMP | 發文時間 |

---

#### `social_comments`（貼文留言）
| 欄位 | 類型 | 說明 |
|------|------|------|
| id | INT PK AUTO | 留言 ID |
| post_id | INT FK | 貼文 ID |
| author_id | INT FK NULL | 古人 ID |
| user_id | INT FK NULL | 用戶 ID |
| content | TEXT | 留言內容 |
| created_at | TIMESTAMP | 留言時間 |

---

#### `matching_game_sets`（配對遊戲題組）
| 欄位 | 類型 | 說明 |
|------|------|------|
| id | INT PK AUTO | 題組 ID |
| essay_id | INT FK NULL | 關聯文章（可選） |
| title | VARCHAR(100) | 題組標題 |
| is_active | TINYINT(1) | 是否啟用 |

---

#### `matching_game_pairs`（配對遊戲題對）
| 欄位 | 類型 | 說明 |
|------|------|------|
| id | INT PK AUTO | ID |
| set_id | INT FK | 題組 ID |
| classical_word | VARCHAR(100) | 文言字詞 |
| modern_meaning | VARCHAR(255) | 現代語譯 |

---

## 五、完整遊戲學習流程

### 主線流程（關卡制）

```
[首頁 Landing Page]
        ↓
[登入 / 注冊]
        ↓
[用戶主頁 — 選擇學習路線]
        ↓
[選擇文學導師（蘇軾 / 韓愈 / 管理員新增）]
        ↓
[關卡一：初級]
  ├─ 閱讀文言文原文
  ├─ AI 逐字翻譯解析（可點擊顯示）
  ├─ 問答挑戰（5 題 AI 生成 MCQ）
  └─ 通過 → 解鎖下一關
        ↓
[關卡二：進階]
  ├─ 同上，難度提升
  └─ 通過 → 解鎖與該導師聊天功能
        ↓
[關卡三：中級]
  ├─ 加入文言配對遊戲（遊戲二）
  └─ 通過 → 繼續
        ↓
[關卡四：高級]
        ↓
[關卡五：專家]
  └─ 全通關 → 解鎖古人社群完整功能
```

---

### 關卡內容詳細說明

#### 關卡一（初級）— 文本理解
- 展示文言文原文
- AI 逐字解析（繁體，格式：原文 → 語譯 → 逐字解釋）
- 語譯以**點擊顯示**方式呈現（保留 v3 設計）
- 常見文言字詞以**粗體**標示
- 問答：5 題 MCQ，答對 3 題即通過

#### 關卡二（進階）— 深化理解
- 同關卡一，文章難度提升
- 解鎖「與古人聊天」功能
- 問答：5 題，需答對 4 題

#### 關卡三（中級）— 遊戲挑戰
- 引入**遊戲一：文言問答**（AI 生成短問答，非 MCQ）
- 引入**遊戲二：文言配對**（文言字詞 配對 現代語譯）
- 問答：5 題，需答對 4 題

#### 關卡四（高級）— 綜合應用
- 較長篇文章
- 需完成文言配對遊戲（限時）
- 問答：5 題，全部答對才通過

#### 關卡五（專家）— 大師挑戰
- 最難文章
- 問答 + 配對遊戲組合挑戰
- 全通關解鎖：古人社群完整功能、與古人自由聊天

---

### 翻譯模組（獨立入口）

- **獨立頁面**，可不需完成關卡直接進入
- 入口位於：導航列「翻譯」按鈕 → `/translate/`
- 文章來源：管理員在後台預設的翻譯文章列（與關卡文章可分開管理）
- **UI 選擇框**（非系統原生 select）：以自訂下拉卡片顯示文章清單
- 分類篩選：依類別（詩歌、文言文、記、論說文等）篩選
- 點選文章後進入翻譯閱讀頁，呼叫 AI 生成逐字解析
- 翻譯格式與 v3 相同：原文浮動視窗 + 語譯點擊顯示 + 逐字解釋

---

### 與古人聊天（解鎖後）

- 完成關卡二後解鎖對應文學導師的聊天功能
- 聊天介面：類即時通訊 UI
- 古人以其性格、背景、語言風格回應（系統提示從 v3 保留並調整）
- 回應語言：混合文言 + 粵語口語（保持 v3 風格）
- AI 系統提示參考 v3 中的 `personality`、`background`、`style` 欄位
- 回應格式：不顯示 `<think></think>` 思考過程

---

### 遊戲二：文言配對（Matching Game）

- 畫面：左側為打亂的「文言字詞」卡片，右側為打亂的「現代語譯」卡片
- 玩家點選左方一張 + 右方一張，正確配對則消去
- 可設限時模式（如 60 秒）
- 配對題組由管理員在後台預設
- 完成所有配對顯示得分及用時

---

### 古人社群

- 解鎖文學導師後可見其貼文
- 古人定時（可在管理員設定）自動生成 AI 貼文
- 用戶可留言，古人會以 AI 自動回覆
- 用戶可點讚
- 社群頁分頁：全部 / 各導師

---

## 六、登入系統

### 功能

| 功能 | 說明 |
|------|------|
| 注冊 | 輸入用戶名、電郵、密碼 |
| 登入 | 用戶名或電郵 + 密碼 |
| 自動成為管理員 | **第一位注冊的用戶** 自動獲得管理員權限 |
| 記住登入 | Session + 可選「記住我」Cookie |
| 登出 | 清除 Session |
| 進度綁定 | 所有學習進度存入資料庫，跨裝置同步 |

### 管理員登入

- 管理員從**普通登入頁面**登入（無獨立入口，避免暴露）
- 登入後系統偵測 `is_admin = 1`，顯示「管理員面板」入口
- 管理員面板路徑：`/admin/`，非管理員訪問會跳轉

---

## 七、管理員面板

### 7.1 文學導師管理（`/admin/authors.php`）

- 新增 / 編輯 / 刪除 文學導師
- 可設定欄位：
  - 名稱、朝代、簡介、頭像 URL
  - AI 性格描述（`personality`）
  - AI 背景（`background`）
  - AI 語言風格（`style`）
  - 是否啟用
  - 排序

### 7.2 關卡管理（`/admin/levels.php`）

- 為每位導師設定關卡
- 每個關卡可：
  - 設定關卡號、難度
  - 從文章庫選擇對應文章
  - 設定通過分數
  - 新增 / 刪除關卡（不限 5 關，可自由增減）

### 7.3 文章庫管理（`/admin/essays.php`）

- 新增 / 編輯 / 刪除 文章
- 欄位：標題、作者、朝代、類別、體裁、原文內容
- 設定：是否顯示於「翻譯文章列」、是否可用於「關卡」
- 支援分類管理（新增 / 刪除 類別）
- 翻譯列表內可設排序

### 7.4 配對遊戲管理（整合於 essays 或獨立頁面）

- 新增 / 編輯 / 刪除 配對題組
- 每個題組可新增多個「文言字詞 ↔ 現代語譯」配對

### 7.5 用戶管理（`/admin/users.php`）

- 查看所有用戶清單
- 賦予或撤銷管理員權限
- 刪除用戶

### 7.6 平台設定（`/admin/settings.php`）

- 古人自動貼文間距（分鐘）
- AI 模型優先順序調整
- 社群功能開關

---

## 八、UI 設計規範

### 設計語言（繼承 v3.HTML）

| CSS 變數 | 值 |
|---------|-----|
| `--primary-color` | `#7fb3d5` |
| `--secondary-color` | `#a2d9ce` |
| `--accent-color` | `#a9cce3` |
| `--highlight-color` | `#7dcea0` |
| `--soft-color` | `#76d7c4` |
| `--light-accent` | `#aed6f1` |
| `--background-color` | `#f8fafa` |
| `--text-color` | `#2c3e50` |
| `--dark-color` | `#34495e` |
| `--error-color` | `#e74c3c` |

### 響應式斷點

| 裝置 | 斷點 |
|------|------|
| 手機 | ≤ 768px |
| iPad | 769px – 1024px |
| 桌面 | ≥ 1025px |

### UI 選擇框

- **禁用**系統原生 `<select>` 元素（外觀因瀏覽器而異）
- 改用**自訂下拉卡片**：
  - 觸發按鈕顯示目前選項
  - 點擊展開選項列表（動畫下滑）
  - 支援鍵盤操作（上下鍵、Enter）
  - 支援觸控

### 特色互動元素（繼承 v3）

- **浮動原文視窗**：翻譯頁右側固定（桌面），頂部滑出（手機）
- **點擊顯示語譯**：語譯以遮蔽方式顯示，點擊才顯示
- **逐字解釋**：點擊字詞展開解釋
- **常見字詞粗體**：重點字詞以漸層背景高亮
- **關卡鎖定動畫**：未解鎖關卡以灰階遮罩顯示
- **通關動畫**：獎盃 + 發光效果

---

## 九、首頁（Landing Page）設計

### 結構

1. **Hero Section**
   - 文樞品牌 Logo + 標語（「與古代文豪為友，在遊戲中習讀文言」）
   - 學習數據展示（如：超過 50% 學生認為文言文枯燥 → 我們的解決方案）
   - 主要 CTA 按鈕：「立即開始學習」→ 登入/注冊頁

2. **平台特色區（Features）**
   - 關卡挑戰、文言翻譯、與古人聊天、配對遊戲
   - 每項以卡片形式呈現（圖示 + 標題 + 簡介）

3. **學習流程示意圖**
   - 視覺化展示：選導師 → 挑戰問答 → 翻譯理解 → 聊天互動

4. **數據/成效區**
   - 引用教育學報數據
   - 平台解決方案說明

5. **文學導師展示**
   - 目前可學習的古人（卡片式，含頭像）

6. **呼籲行動（Footer CTA）**
   - 「立即注冊，免費開始」

### SEO 標籤（Landing Page）

```html
<title>文樞 — 遊戲化文言文學習平台</title>
<meta name="description" content="透過挑戰問答、AI翻譯解析、與古人聊天等遊戲化方式，輕鬆學習文言文。適合中學生使用。">
<meta name="keywords" content="文言文學習, 文言文翻譯, 中文學習, 遊戲化學習, 古文">
```

---

## 十、AI 系統提示（保留 v3，按需調整）

### 翻譯提示（保留 v3 格式要求）

```
請將以下文言文逐字翻譯並解釋(直譯，不要意譯)，格式要求：
原文：[顯示原文句子]
語譯：[顯示完整句子翻譯]
逐字解釋：[對每個文字進行解釋，格式為"字：解釋"，常見文言字詞用**粗體**標示，切勿解釋標點符號]
（其餘原有格式要求保持不變）
```

### 問答題生成提示（保留 v3 格式）

```
請基於以下文言文內容生成5道中文繁體選擇題...
（原有格式保持，新增：難度等級參數，由關卡等級決定）
```

### 古人發文提示（保留 v3 格式）

```
你現在是{導師名}，請生成一個社交媒體貼文...
（原有要求保持，新增：不重複提示，從資料庫歷史貼文中排重）
```

### 古人聊天提示（新增，參考 v3 性格設定）

```
你現在是{導師名}，請以角色身份回應用戶的問題。
角色背景：{background}
性格特點：{personality}
語言風格：{style}
輸出要求：
1. 以{導師名}的身份回應，展現其性格
2. 語言可混合文言文與粵語口語
3. 不要顯示思考過程（<think>標籤內容）
4. 回應長度：50-200字
5. 若話題與所學文章相關，可引用原文
```

---

## 十一、安全性措施

| 項目 | 做法 |
|------|------|
| 密碼儲存 | PHP `password_hash()` + bcrypt |
| SQL 注入防護 | PDO Prepared Statements |
| XSS 防護 | `htmlspecialchars()` 過濾所有輸出 |
| CSRF 防護 | 表單加入 CSRF Token |
| API 密鑰保護 | 密鑰存於 `config.php`（不直接暴露給前端） |
| Session 安全 | `session_regenerate_id()` 登入時重新生成 |
| 管理員路由保護 | `admin_check.php` 中介驗證 |
| 檔案上傳（如適用） | 驗證類型、大小，儲存於非公開目錄 |

---

## 十二、開發工作項目清單

### 第一階段：基礎建設

- [ ] 建立 PHP 目錄結構
- [ ] 設定 `config.php`（資料庫連線，API 密鑰）
- [ ] 建立 MySQL 資料表（依上述設計）
- [ ] 建立 `db.php`（PDO 連線）
- [ ] 建立 `functions.php`（通用函數）
- [ ] 建立 `auth_check.php`、`admin_check.php`
- [ ] 建立 AI 代理 `api/ai.php`（含模型自動切換）

### 第二階段：身份驗證

- [ ] `login.php`（登入 + 注冊）
- [ ] `logout.php`
- [ ] 首位注冊者自動成為管理員邏輯
- [ ] Session 管理

### 第三階段：首頁

- [ ] `index.php`（Landing Page，完整 SEO）
- [ ] `sitemap.xml`、`robots.txt`

### 第四階段：學習系統

- [ ] `/learn/index.php`（文學導師選擇）
- [ ] `/learn/level.php`（關卡學習 + 翻譯解析）
- [ ] `/learn/quiz.php`（問答挑戰）
- [ ] `/learn/result.php`（結果頁）
- [ ] `api/progress.php`（進度 CRUD）

### 第五階段：翻譯模組

- [ ] `/translate/index.php`（文章列表，UI 選擇框）
- [ ] `/translate/view.php`（翻譯閱讀，浮動原文視窗）

### 第六階段：聊天功能

- [ ] `/chat/index.php`（選擇古人聊天）
- [ ] 聊天 API 呼叫（系統提示整合）

### 第七階段：遊戲

- [ ] `/games/index.php`（遊戲選單）
- [ ] `/games/matching.php`（文言配對遊戲）

### 第八階段：社群

- [ ] `/social/index.php`（古人社群）
- [ ] `api/social.php`（貼文 / 留言 / 點讚）
- [ ] 古人自動發文定時邏輯（後端觸發）

### 第九階段：管理員面板

- [ ] `/admin/index.php`（面板主頁）
- [ ] `/admin/authors.php`（文學導師管理）
- [ ] `/admin/levels.php`（關卡管理）
- [ ] `/admin/essays.php`（文章庫管理，含分類）
- [ ] `/admin/users.php`（用戶 + 管理員管理）
- [ ] `/admin/settings.php`（平台設定）

### 第十階段：整合與優化

- [ ] 全平台響應式設計測試（手機 / iPad / 桌面）
- [ ] SEO 標籤完善
- [ ] 統一所有功能名稱（全繁體中文）
- [ ] 安全性審查
- [ ] Bug 修復

---

## 十三、已知 v3.HTML 問題與修正

| 問題 | 修正方案 |
|------|---------|
| 測試面板暴露在生產環境 | 移除測試面板，管理員功能移入後台 |
| API 密鑰直接寫在前端 JS | 密鑰移入 PHP `config.php`，前端只調用本平台 API |
| 所有資料存 localStorage（刷新即重置） | 改存 MySQL，跨裝置同步 |
| 關卡文章硬編碼在 JS 內 | 改從資料庫讀取，管理員可動態管理 |
| 只有兩位文學導師且無法新增 | 支援管理員動態新增 |
| `<script>` 標籤外的 JS 代碼（第 616–625 行）| 修正放錯位置的 JS 代碼 |
| 系統原生 `<select>` 元素 | 改用自訂 UI 選擇框 |
| 進度圈顯示寫死 5 個 | 動態依關卡數量生成 |

---

## 十四、確認事項

請確認以下各點後，方可開始編寫代碼：

1. **文學導師**：初版除蘇軾、韓愈外，是否有其他要預設的導師？
2. **文章庫**：是否需要將 `essays.json` 的現有文章全部匯入資料庫？
3. **配對遊戲**：是否需要限時模式？限時秒數？
4. **聊天功能**：每位用戶與每位古人的對話紀錄是否需要儲存？
5. **社群功能**：古人自動發文是否用 PHP cron job 還是前端觸發？
6. **部署環境**：是否已在 InfinityFree 建立網站，並確認 PHP 版本？
7. **頭像圖片**：是否繼續使用 v3 的 ibb.co 圖片連結？

---

*計劃書由 GitHub Copilot 整理，待用戶確認後開始實作。*
