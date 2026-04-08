<?php
/**
 * 文樞平台 — 自動資料庫安裝程序
 *
 * 工作原理：
 *  - 當第一個訪客連接時，PHP 會嘗試用 SHOW TABLES LIKE 'settings' 檢查資料庫是否已建立。
 *  - 若找不到（資料庫是空的），執行全部 CREATE TABLE 及種子資料 INSERT。
 *  - 全程在同一 PDO 連線內執行，無需任何手動操作。
 *  - 安裝完成後，靜態變數 $installed = true，後續請求不再重複檢查。
 *
 * 呼叫方：includes/db.php（在取得連線後立即呼叫）
 */

/**
 * 確保資料庫已安裝。若尚未安裝，執行完整的 schema 創建及種子資料填入。
 *
 * @param PDO $pdo 已建立的資料庫連線
 */
function ensureInstalled(PDO $pdo): void
{
    // 靜態標誌：同一請求週期內只檢查一次
    static $installed = false;
    if ($installed) {
        return;
    }

    // 輕量級檢查：`settings` 表存在即視為已安裝
    $result = $pdo->query("SHOW TABLES LIKE 'settings'")->fetchColumn();
    if ($result !== false) {
        $installed = true;
        return;
    }

    // ── 執行安裝 ──────────────────────────────────────────────────────────
    runInstall($pdo);
    $installed = true;
}

/**
 * 執行完整安裝（CREATE TABLE + 種子資料）
 * 全部語句以順序執行，若已存在則 IF NOT EXISTS 跳過。
 */
function runInstall(PDO $pdo): void
{
    $statements = getInstallStatements();

    $pdo->exec('SET NAMES utf8mb4');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    foreach ($statements as $sql) {
        $sql = trim($sql);
        if ($sql === '') {
            continue;
        }
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // 忽略「已存在」等輕微錯誤，記錄其他錯誤至 error_log
            $code = (string)$e->getCode();
            if (!in_array($code, ['42S01', '23000'], true)) {
                error_log('[文樞 Installer] SQL Error: ' . $e->getMessage() . ' | SQL: ' . substr($sql, 0, 100));
            }
        }
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

/**
 * 返回完整安裝 SQL 語句陣列（順序很重要：被依賴的表先建立）
 *
 * @return string[]
 */
function getInstallStatements(): array
{
    return [

        // ── 1. 用戶表 ────────────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS `users` (
            `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
            `username`      VARCHAR(50)      NOT NULL,
            `email`         VARCHAR(100)     NOT NULL,
            `password_hash` VARCHAR(255)     NOT NULL,
            `role`          ENUM('user','admin') NOT NULL DEFAULT 'user',
            `avatar`        VARCHAR(255)     DEFAULT NULL,
            `xp`            INT UNSIGNED     NOT NULL DEFAULT 0,
            `last_login`    DATETIME         DEFAULT NULL,
            `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── 2. 文學導師表 ────────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS `tutors` (
            `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name`           VARCHAR(50)  NOT NULL,
            `dynasty`        VARCHAR(50)  NOT NULL DEFAULT '',
            `description`    TEXT,
            `background`     TEXT,
            `personality`    TEXT,
            `language_style` TEXT,
            `avatar_url`     VARCHAR(255) DEFAULT NULL,
            `gradient_class` VARCHAR(50)  NOT NULL DEFAULT 'gradient-primary',
            `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
            `sort_order`     INT          NOT NULL DEFAULT 0,
            `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── 3. 關卡表 ────────────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS `levels` (
            `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `tutor_id`      INT UNSIGNED NOT NULL,
            `level_number`  INT UNSIGNED NOT NULL DEFAULT 1,
            `difficulty`    ENUM('初級','進階','中級','高級','專家') NOT NULL DEFAULT '初級',
            `essay_title`   VARCHAR(100) NOT NULL,
            `essay_author`  VARCHAR(50)  NOT NULL DEFAULT '',
            `essay_content` LONGTEXT     NOT NULL,
            `notes`         TEXT,
            `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
            `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_tutor_level` (`tutor_id`, `level_number`),
            CONSTRAINT `fk_levels_tutor`
                FOREIGN KEY (`tutor_id`) REFERENCES `tutors` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── 4. 翻譯文章庫 ────────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS `translate_essays` (
            `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `title`      VARCHAR(100) NOT NULL,
            `author`     VARCHAR(50)  NOT NULL DEFAULT '',
            `dynasty`    VARCHAR(30)  NOT NULL DEFAULT '',
            `category`   VARCHAR(50)  NOT NULL DEFAULT '文言文',
            `genre`      VARCHAR(50)  NOT NULL DEFAULT '',
            `content`    LONGTEXT     NOT NULL,
            `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
            `sort_order` INT          NOT NULL DEFAULT 0,
            `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_category` (`category`),
            KEY `idx_active`   (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── 5. 用戶學習進度表 ────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS `user_progress` (
            `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
            `user_id`      INT UNSIGNED     NOT NULL,
            `tutor_id`     INT UNSIGNED     NOT NULL,
            `level_id`     INT UNSIGNED     NOT NULL,
            `completed`    TINYINT(1)       NOT NULL DEFAULT 0,
            `score`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `attempts`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `completed_at` DATETIME         DEFAULT NULL,
            `updated_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_user_level` (`user_id`, `level_id`),
            KEY `idx_user_tutor` (`user_id`, `tutor_id`),
            CONSTRAINT `fk_up_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_up_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `tutors` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_up_level` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── 6. 翻譯緩存表 ────────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS `translation_cache` (
            `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `text_hash`          VARCHAR(64)  NOT NULL,
            `essay_title`        VARCHAR(100) NOT NULL DEFAULT '',
            `translation_result` LONGTEXT     NOT NULL,
            `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_text_hash` (`text_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── 7. 社群動態表（上限 80 篇，FIFO）───────────────────────────
        "CREATE TABLE IF NOT EXISTS `social_posts` (
            `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `author_type` ENUM('user','tutor') NOT NULL,
            `user_id`     INT UNSIGNED DEFAULT NULL,
            `tutor_id`    INT UNSIGNED DEFAULT NULL,
            `content`     TEXT         NOT NULL,
            `likes`       INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_created`     (`created_at`),
            KEY `idx_author_type` (`author_type`),
            CONSTRAINT `fk_sp_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_sp_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `tutors` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── 8. 社群留言表 ────────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS `social_comments` (
            `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `post_id`     INT UNSIGNED NOT NULL,
            `author_type` ENUM('user','tutor') NOT NULL,
            `user_id`     INT UNSIGNED DEFAULT NULL,
            `tutor_id`    INT UNSIGNED DEFAULT NULL,
            `content`     TEXT         NOT NULL,
            `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_post` (`post_id`),
            CONSTRAINT `fk_sc_post`  FOREIGN KEY (`post_id`)  REFERENCES `social_posts` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_sc_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`        (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_sc_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `tutors`       (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── 9. 配對遊戲題庫 ──────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS `matching_pairs` (
            `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `classical_term` VARCHAR(100) NOT NULL,
            `modern_meaning` VARCHAR(200) NOT NULL,
            `source_essay`   VARCHAR(100) NOT NULL DEFAULT '',
            `difficulty`     ENUM('初級','進階','中級','高級','專家') NOT NULL DEFAULT '初級',
            `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
            `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_difficulty` (`difficulty`),
            KEY `idx_active`     (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── 10. 系統設定表 ───────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS `settings` (
            `setting_key`   VARCHAR(100) NOT NULL,
            `setting_value` TEXT         NOT NULL,
            `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── 種子資料：系統設定 ────────────────────────────────────────────
        "INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
            ('post_interval_minutes', '60'),
            ('max_posts',             '80'),
            ('platform_name',         '文樞'),
            ('maintenance_mode',      '0')
         ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)",

        // ── 種子資料：蘇軾 ────────────────────────────────────────────────
        "INSERT INTO `tutors`
            (`name`, `dynasty`, `description`, `background`, `personality`, `language_style`,
             `avatar_url`, `gradient_class`, `is_active`, `sort_order`)
         VALUES (
            '蘇軾', '北宋',
            '北宋文學家、書畫家，唐宋八大家之一，豪放派詞人代表。',
            '蘇軾（1037-1101），字子瞻，號東坡居士，北宋著名文學家、書法家、畫家。官至翰林學士，因政治立場多次被貶，但始終保持樂觀態度。',
            '豁達樂觀，熱愛自然，善於哲理思考，喜歡用比喻和典故。經歷過多次貶謫，但始終保持樂觀態度，善於在逆境中尋找樂趣。',
            '文風優雅，常引用詩詞，語言富有哲理性。只有引用作品才會用文言文，其他情況一概用生活化的香港粵語，但發言要切合角色性格。',
            'https://i.ibb.co/wrhVfCjJ/image.png',
            'gradient-primary', 1, 1
         ) ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)",

        // ── 種子資料：韓愈 ────────────────────────────────────────────────
        "INSERT INTO `tutors`
            (`name`, `dynasty`, `description`, `background`, `personality`, `language_style`,
             `avatar_url`, `gradient_class`, `is_active`, `sort_order`)
         VALUES (
            '韓愈', '唐代',
            '唐代文學家、思想家，唐宋八大家之首，古文運動的倡導者。',
            '韓愈（768-824），字退之，河南河陽人，唐代傑出的文學家、思想家。官至吏部侍郎，因直言進諫多次被貶。',
            '嚴謹治學，重視道德修養，推崇古文，有教育家風範，言辭直接不諱。',
            '文風簡潔有力，喜歡說理，常有教誨意味，言簡意賅。只有引用作品才會用文言文，其他情況一概用生活化的香港粵語，但發言要切合角色性格。',
            'https://i.ibb.co/LhqsVb40/image.png',
            'gradient-secondary', 1, 2
         ) ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)",

        // ── 種子資料：蘇軾關卡 1 ─────────────────────────────────────────
        "INSERT INTO `levels`
            (`tutor_id`, `level_number`, `difficulty`, `essay_title`, `essay_author`, `essay_content`, `notes`)
         SELECT t.id, 1, '初級', '記承天寺夜遊', '蘇軾',
            '元豐六年十月十二日夜，解衣欲睡，月色入戶，欣然起行。念無與為樂者，遂至承天寺尋張懷民。懷民亦未寢，相與步於中庭。庭下如積水空明，水中藻荇交橫，蓋竹柏影也。何夜無月？何處無竹柏？但少閑人如吾兩人者耳。',
            '此文為蘇軾於元豐年間被貶黃州時所作，篇幅短小卻情景交融，是文言散文入門佳作。'
         FROM tutors t WHERE t.name = '蘇軾' LIMIT 1",

        // ── 種子資料：蘇軾關卡 2 ─────────────────────────────────────────
        "INSERT INTO `levels`
            (`tutor_id`, `level_number`, `difficulty`, `essay_title`, `essay_author`, `essay_content`, `notes`)
         SELECT t.id, 2, '進階', '赤壁賦（節選）', '蘇軾',
            '壬戌之秋，七月既望，蘇子與客泛舟遊於赤壁之下。清風徐來，水波不興。舉酒屬客，誦明月之詩，歌窈窕之章。少焉，月出於東山之上，徘徊於斗牛之間。白露橫江，水光接天。縱一葦之所如，凌萬頃之茫然。浩浩乎如馮虛御風，而不知其所止；飄飄乎如遺世獨立，羽化而登仙。',
            '赤壁賦是蘇軾代表作之一，融情、景、理於一體，展現其哲理思考。'
         FROM tutors t WHERE t.name = '蘇軾' LIMIT 1",

        // ── 種子資料：韓愈關卡 1 ─────────────────────────────────────────
        "INSERT INTO `levels`
            (`tutor_id`, `level_number`, `difficulty`, `essay_title`, `essay_author`, `essay_content`, `notes`)
         SELECT t.id, 1, '初級', '師說（節選）', '韓愈',
            '古之學者必有師。師者，所以傳道受業解惑也。人非生而知之者，孰能無惑？惑而不從師，其為惑也，終不解矣。生乎吾前，其聞道也固先乎吾，吾從而師之；生乎吾後，其聞道也亦先乎吾，吾從而師之。吾師道也，夫庸知其年之先後生於吾乎？是故無貴無賤，無長無少，道之所存，師之所存也。',
            '師說是韓愈論教師意義的名篇，語言嚴謹，論述有力，適合初學者理解韓愈的思想風格。'
         FROM tutors t WHERE t.name = '韓愈' LIMIT 1",

        // ── 種子資料：翻譯文章範例 ───────────────────────────────────────
        "INSERT INTO `translate_essays`
            (`title`, `author`, `dynasty`, `category`, `genre`, `content`, `is_active`, `sort_order`)
         VALUES
         (
            '記承天寺夜遊', '蘇軾', '北宋', '散文', '寫景記遊',
            '元豐六年十月十二日夜，解衣欲睡，月色入戶，欣然起行。念無與為樂者，遂至承天寺尋張懷民。懷民亦未寢，相與步於中庭。庭下如積水空明，水中藻荇交橫，蓋竹柏影也。何夜無月？何處無竹柏？但少閑人如吾兩人者耳。',
            1, 1
         ),
         (
            '師說（節選）', '韓愈', '唐代', '散文', '論說文',
            '古之學者必有師。師者，所以傳道受業解惑也。人非生而知之者，孰能無惑？惑而不從師，其為惑也，終不解矣。生乎吾前，其聞道也固先乎吾，吾從而師之；生乎吾後，其聞道也亦先乎吾，吾從而師之。',
            1, 2
         ),
         (
            '岳陽樓記（節選）', '范仲淹', '北宋', '散文', '寫景抒情',
            '予觀夫巴陵勝狀，在洞庭一湖。銜遠山，吞長江，浩浩湯湯，橫無際涯；朝暉夕陰，氣象萬千。此則岳陽樓之大觀也，前人之述備矣。然則北通巫峽，南極瀟湘，遷客騷人，多會於此，覽物之情，得無異乎？',
            1, 3
         )
         ON DUPLICATE KEY UPDATE `title` = VALUES(`title`)",

        // ── 種子資料：配對遊戲題庫（初級，蘇軾作品字詞）───────────────────
        "INSERT INTO `matching_pairs`
            (`classical_term`, `modern_meaning`, `source_essay`, `difficulty`)
         VALUES
            ('欣然',   '高興的樣子',       '記承天寺夜遊', '初級'),
            ('念',     '想到',             '記承天寺夜遊', '初級'),
            ('遂',     '於是，就',         '記承天寺夜遊', '初級'),
            ('相與',   '共同，一起',       '記承天寺夜遊', '初級'),
            ('空明',   '清澈透明',         '記承天寺夜遊', '初級'),
            ('交橫',   '縱橫交錯',         '記承天寺夜遊', '初級'),
            ('蓋',     '原來是',           '記承天寺夜遊', '初級'),
            ('閑人',   '清閒的人',         '記承天寺夜遊', '初級'),
            ('徐來',   '輕輕吹來',         '赤壁賦',       '進階'),
            ('屬客',   '勸客人喝酒',       '赤壁賦',       '進階'),
            ('少焉',   '不一會兒',         '赤壁賦',       '進階'),
            ('縱',     '任憑',             '赤壁賦',       '進階'),
            ('學者',   '求學的人',         '師說',         '初級'),
            ('傳道',   '傳授道理',         '師說',         '初級'),
            ('受業',   '傳授學業',         '師說',         '初級'),
            ('解惑',   '解答疑難問題',     '師說',         '初級'),
            ('從師',   '跟從老師學習',     '師說',         '進階'),
            ('庸知',   '哪裏在乎',         '師說',         '進階')
         ON DUPLICATE KEY UPDATE `classical_term` = VALUES(`classical_term`)",

    ];
}
