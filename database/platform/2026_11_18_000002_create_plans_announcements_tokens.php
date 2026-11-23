<?php

declare(strict_types=1);

/**
 * Owner's management of colleges:
 *  plans                 — tariffs with a price and a limit of active students (NULL = no limit);
 *  announcements         — messages of the owner shown inside colleges (all or one, admins or everybody);
 *  college_login_tokens  — one-time links "sign in to the college as its administrator" (60 s, hashed);
 *  colleges              — plan, archive (no access, hidden), the date the database was deleted.
 */
return [
    'up' => [
        "CREATE TABLE plans (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            max_students INT UNSIGNED NULL COMMENT 'NULL = unlimited',
            monthly_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            description VARCHAR(500) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_plans_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "ALTER TABLE colleges
            ADD COLUMN plan_id INT UNSIGNED NULL AFTER admin_login,
            MODIFY COLUMN status ENUM('active','suspended','archived') NOT NULL DEFAULT 'active',
            ADD COLUMN archived_at DATETIME NULL AFTER status,
            ADD COLUMN db_deleted_at DATETIME NULL AFTER archived_at,
            ADD KEY fk_colleges_plan (plan_id),
            ADD CONSTRAINT fk_colleges_plan FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE SET NULL",

        "CREATE TABLE announcements (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            college_id INT UNSIGNED NULL COMMENT 'NULL = every college',
            audience ENUM('all','admins') NOT NULL DEFAULT 'admins',
            level ENUM('info','warning') NOT NULL DEFAULT 'info',
            title VARCHAR(200) NOT NULL,
            body VARCHAR(2000) NULL,
            starts_on DATE NOT NULL,
            ends_on DATE NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_announcements_period (starts_on, ends_on),
            KEY fk_announcements_college (college_id),
            KEY fk_announcements_created_by (created_by),
            CONSTRAINT fk_announcements_college FOREIGN KEY (college_id) REFERENCES colleges (id) ON DELETE CASCADE,
            CONSTRAINT fk_announcements_created_by FOREIGN KEY (created_by) REFERENCES platform_admins (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE college_login_tokens (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            token_hash CHAR(64) NOT NULL,
            college_id INT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL COMMENT 'User in the college database',
            admin_id INT UNSIGNED NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_college_login_tokens (token_hash),
            KEY fk_college_login_tokens_college (college_id),
            KEY fk_college_login_tokens_admin (admin_id),
            CONSTRAINT fk_college_login_tokens_college FOREIGN KEY (college_id) REFERENCES colleges (id) ON DELETE CASCADE,
            CONSTRAINT fk_college_login_tokens_admin FOREIGN KEY (admin_id) REFERENCES platform_admins (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS college_login_tokens',
        'DROP TABLE IF EXISTS announcements',
        'ALTER TABLE colleges DROP FOREIGN KEY fk_colleges_plan, DROP KEY fk_colleges_plan, DROP COLUMN plan_id, DROP COLUMN archived_at, DROP COLUMN db_deleted_at, MODIFY COLUMN status ENUM(\'active\',\'suspended\') NOT NULL DEFAULT \'active\'',
        'DROP TABLE IF EXISTS plans',
    ],
];
