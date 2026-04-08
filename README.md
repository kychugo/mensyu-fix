# 文樞 Mensyu — DSE 文言文互動學習平台

> 專為香港 DSE 學生設計的文言文互動學習平台

---

## ✨ 功能特色

| 模組 | 描述 |
|------|------|
| 📖 **學習關卡** | 蘇軾 / 韓愈 4 大關，「讀→練→驗」三段式學習，AI 實時注釋 |
| 🔤 **AI 翻譯** | 支援 16 篇 DSE 指定範文及自由輸入，逐字翻譯 + AI 測驗 |
| 🎮 **遊戲廳** | 文磚挑戰（Breakout）+ 文言配對（Matching），支援觸控 |
| 🍵 **古人茶館** | 古人（蘇軾/韓愈）AI 自動發文 + 用戶留言，Instagram 風格 feed |
| 📚 **範文庫** | 16 篇 DSE 指定文言文完整收錄 |
| 🏅 **成就系統** | 6 種徽章，完成關卡/配對/茶館互動自動解鎖 |
| ⚙️ **管理面板** | 儀表板 / 用戶管理 / 錯誤日誌 / 使用統計 / 內容審核 / 系統設定 |

---

## 🏗 技術架構

- **後端**：PHP 8.0+ (PDO, bcrypt, session, custom error handler)
- **資料庫**：MySQL 5.7+ / MariaDB 10.3+（**自動建表，無需手動 SQL**）
- **前端**：Tailwind CSS (CDN) + Vanilla JS
- **字型**：Noto Serif TC (Google Fonts)
- **AI**：[Pollinations.ai](https://pollinations.ai) 文字 + 圖片 API（fallback 多模型）
- **主機**：[InfinityFree](https://infinityfree.net) 免費 PHP 主機

---

## 📁 目錄結構

```
mensyu/
├── api/                   # REST API endpoints
│   ├── admin.php          # 管理面板 API
│   ├── ai_text.php        # AI 文字代理 (伺服器端)
│   ├── ai_image.php       # AI 圖片代理
│   ├── auth.php           # 注冊/登入/登出
│   ├── cron_post.php      # 古人自動發文
│   ├── essays.php         # 範文 API
│   ├── posts.php          # 茶館貼文 CRUD
│   ├── progress.php       # 學習進度
│   └── achievements.php   # 成就系統
├── config/
│   ├── db.php             # PDO 連線 + 自動建表觸發
│   ├── ai.php             # AI API 設定
│   ├── install.php        # 資料庫自動安裝器
│   └── local.php.example  # 憑證範本（複製為 local.php）
├── data/
│   └── essays.json        # 16 篇 DSE 範文
├── includes/
│   ├── session.php        # Session 管理 + CSRF
│   ├── header.php         # 公用頭部 + 響應式導航
│   ├── footer.php         # 公用底部
│   ├── geo_guard.php      # 香港 GeoIP + VPN 封鎖
│   └── error_tracker.php  # PHP 錯誤處理 + 使用追蹤
├── pages/                 # 前端頁面
│   ├── home.php
│   ├── learning.php
│   ├── games.php
│   ├── teahouse.php
│   ├── translate.php
│   ├── profile.php
│   ├── login.php
│   ├── register.php
│   └── admin.php          # 管理面板
├── .htaccess              # URL 重寫 + 安全設定
├── index.php              # 主路由分發 + 偽 cron
├── sitemap.xml
├── robots.txt
└── DEPLOY.md              # 部署指南
```

---

## 🚀 快速部署

詳見 [DEPLOY.md](DEPLOY.md)

---

## 👑 管理員說明

- **第一位注冊用戶**自動成為管理員
- 管理員可在管理面板 (`/admin`) 增刪管理員帳戶、封禁用戶、查看錯誤日誌等

