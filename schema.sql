-- ══════════════════════════════════════════════════════════════════════════
-- 文樞平台資料庫結構
-- 資料庫：if0_41581260_mensyu
-- 字符集：utf8mb4（支援中文及 Emoji）
-- ══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. 用戶表 ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(50)  NOT NULL,
    `email`         VARCHAR(100) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role`          ENUM('user','admin') NOT NULL DEFAULT 'user',
    `avatar`        VARCHAR(255)  DEFAULT NULL,
    `xp`            INT UNSIGNED NOT NULL DEFAULT 0,
    `last_login`    DATETIME      DEFAULT NULL,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. 文學導師表 ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tutors` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`           VARCHAR(50)  NOT NULL,
    `dynasty`        VARCHAR(50)  NOT NULL DEFAULT '',
    `description`    TEXT,
    `background`     TEXT         COMMENT '歷史背景（顯示於導師介紹頁）',
    `personality`    TEXT         COMMENT '性格設定（AI Prompt 用）',
    `language_style` TEXT         COMMENT '語言風格（AI Prompt 用）',
    `avatar_url`     VARCHAR(255) DEFAULT NULL,
    `gradient_class` VARCHAR(50)  NOT NULL DEFAULT 'gradient-primary',
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`     INT          NOT NULL DEFAULT 0,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. 關卡表 ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `levels` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tutor_id`     INT UNSIGNED NOT NULL,
    `level_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `difficulty`   ENUM('初級','進階','中級','高級','專家') NOT NULL DEFAULT '初級',
    `essay_title`  VARCHAR(100) NOT NULL,
    `essay_author` VARCHAR(50)  NOT NULL DEFAULT '',
    `essay_content` LONGTEXT    NOT NULL,
    `notes`        TEXT         COMMENT '教學備注',
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tutor_level` (`tutor_id`, `level_number`),
    CONSTRAINT `fk_levels_tutor`
        FOREIGN KEY (`tutor_id`) REFERENCES `tutors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. 翻譯文章庫 ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `translate_essays` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`      VARCHAR(100) NOT NULL,
    `author`     VARCHAR(50)  NOT NULL DEFAULT '',
    `dynasty`    VARCHAR(30)  NOT NULL DEFAULT '',
    `category`   VARCHAR(50)  NOT NULL DEFAULT '文言文' COMMENT '如：詩歌、散文、史傳、論說文',
    `genre`      VARCHAR(50)  NOT NULL DEFAULT '',
    `content`    LONGTEXT     NOT NULL,
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order` INT          NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category`),
    KEY `idx_active`   (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. 用戶學習進度表 ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user_progress` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED NOT NULL,
    `tutor_id`     INT UNSIGNED NOT NULL,
    `level_id`     INT UNSIGNED NOT NULL,
    `completed`    TINYINT(1)   NOT NULL DEFAULT 0,
    `score`        TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0–100',
    `attempts`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `completed_at` DATETIME     DEFAULT NULL,
    `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_level` (`user_id`, `level_id`),
    KEY `idx_user_tutor` (`user_id`, `tutor_id`),
    CONSTRAINT `fk_up_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_up_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `tutors` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_up_level` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. 翻譯緩存表 ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `translation_cache` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `text_hash`          VARCHAR(64)  NOT NULL COMMENT 'MD5(title + "_" + content)',
    `essay_title`        VARCHAR(100) NOT NULL DEFAULT '',
    `translation_result` LONGTEXT     NOT NULL COMMENT 'AI 翻譯結果（原始文字）',
    `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_text_hash` (`text_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 7. 社群動態表（上限 80 篇，FIFO）──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `social_posts` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `author_type` ENUM('user','tutor') NOT NULL,
    `user_id`     INT UNSIGNED DEFAULT NULL,
    `tutor_id`    INT UNSIGNED DEFAULT NULL,
    `content`     TEXT         NOT NULL,
    `likes`       INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_created` (`created_at`),
    KEY `idx_author_type` (`author_type`),
    CONSTRAINT `fk_sp_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_sp_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `tutors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 8. 社群留言表 ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `social_comments` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 9. 配對遊戲題庫 ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `matching_pairs` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `classical_term` VARCHAR(100) NOT NULL  COMMENT '文言字詞',
    `modern_meaning` VARCHAR(200) NOT NULL  COMMENT '現代語譯',
    `source_essay`   VARCHAR(100) NOT NULL DEFAULT '' COMMENT '出處作品',
    `difficulty`     ENUM('初級','進階','中級','高級','專家') NOT NULL DEFAULT '初級',
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_difficulty` (`difficulty`),
    KEY `idx_active`     (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 10. 系統設定表 ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key`   VARCHAR(100) NOT NULL,
    `setting_value` TEXT         NOT NULL,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 預設系統設定 ───────────────────────────────────────────────────────────
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
    ('post_interval_minutes', '60'),
    ('max_posts',             '80'),
    ('platform_name',         '文樞'),
    ('maintenance_mode',      '0')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- ── 預設文學導師（蘇軾、韓愈）────────────────────────────────────────────
INSERT INTO `tutors`
    (`name`, `dynasty`, `description`, `background`, `personality`, `language_style`, `avatar_url`, `gradient_class`, `is_active`, `sort_order`)
VALUES
(
    '蘇軾', '北宋',
    '北宋文學家、書畫家，唐宋八大家之一，豪放派詞人代表。',
    '蘇軾（1037-1101），字子瞻，號東坡居士，北宋著名文學家、書法家、畫家。官至翰林學士，因政治立場多次被貶，但始終保持樂觀態度。',
    '豁達樂觀，熱愛自然，善於哲理思考，喜歡用比喻和典故。經歷過多次貶謫，但始終保持樂觀態度，善於在逆境中尋找樂趣。',
    '文風優雅，常引用詩詞，語言富有哲理性。只有引用作品才會用文言文，其他情況一概用生活化的香港粵語，但發言要切合角色性格。',
    'https://i.ibb.co/wrhVfCjJ/image.png',
    'gradient-primary', 1, 1
),
(
    '韓愈', '唐代',
    '唐代文學家、思想家，唐宋八大家之首，古文運動的倡導者。',
    '韓愈（768-824），字退之，河南河陽人，唐代傑出的文學家、思想家。官至吏部侍郎，因直言進諫多次被貶。',
    '嚴謹治學，重視道德修養，推崇古文，有教育家風範，言辭直接不諱。',
    '文風簡潔有力，喜歡說理，常有教誨意味，言簡意賅。只有引用作品才會用文言文，其他情況一概用生活化的香港粵語，但發言要切合角色性格。',
    'https://i.ibb.co/LhqsVb40/image.png',
    'gradient-secondary', 1, 2
)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

SET FOREIGN_KEY_CHECKS = 1;
