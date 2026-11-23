<?php

declare(strict_types=1);

/**
 * Surveys (анкетирование).
 *
 * surveys             — title, description, period, target audience (students / teachers / all,
 *                       optionally one department, course or group), anonymous mode, draft → published → closed.
 * survey_questions    — single choice, multiple choice, text or rating scale.
 * survey_options      — answer options of choice questions.
 * survey_responses    — one completed questionnaire. In anonymous surveys user_id and the exact time
 *                       are NOT stored: only coarse segments (role, department, course) for comparisons
 *                       and the date; group is stored for named surveys only.
 * survey_participants — who has answered (to allow one answer per person). It has no surrogate key,
 *                       no time and no reference to the response, so answers cannot be linked to people.
 * survey_answers      — answers to questions.
 */
return [
    'up' => [
        "CREATE TABLE surveys (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title_ru VARCHAR(255) NOT NULL,
            title_kk VARCHAR(255) NULL,
            description_ru TEXT NULL,
            description_kk TEXT NULL,
            audience ENUM('students','teachers','all') NOT NULL DEFAULT 'students',
            department_id SMALLINT UNSIGNED NULL,
            course TINYINT UNSIGNED NULL COMMENT 'Students only',
            group_id INT UNSIGNED NULL COMMENT 'Students only',
            is_anonymous TINYINT(1) NOT NULL DEFAULT 1,
            starts_on DATE NOT NULL,
            ends_on DATE NOT NULL,
            status ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
            published_at DATETIME NULL,
            closed_at DATETIME NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_surveys_status (status, starts_on, ends_on),
            KEY fk_surveys_department (department_id),
            KEY fk_surveys_group (group_id),
            KEY fk_surveys_created_by (created_by),
            CONSTRAINT fk_surveys_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE RESTRICT,
            CONSTRAINT fk_surveys_group FOREIGN KEY (group_id) REFERENCES `groups` (id) ON DELETE RESTRICT,
            CONSTRAINT fk_surveys_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT chk_surveys_period CHECK (ends_on >= starts_on),
            CONSTRAINT chk_surveys_course CHECK (course IS NULL OR course BETWEEN 1 AND 6)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE survey_questions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            survey_id INT UNSIGNED NOT NULL,
            type ENUM('single','multiple','text','scale') NOT NULL,
            text_ru VARCHAR(500) NOT NULL,
            text_kk VARCHAR(500) NULL,
            is_required TINYINT(1) NOT NULL DEFAULT 1,
            scale_min TINYINT UNSIGNED NULL,
            scale_max TINYINT UNSIGNED NULL,
            min_label_ru VARCHAR(100) NULL,
            min_label_kk VARCHAR(100) NULL,
            max_label_ru VARCHAR(100) NULL,
            max_label_kk VARCHAR(100) NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_sq_survey (survey_id, sort_order),
            CONSTRAINT fk_sq_survey FOREIGN KEY (survey_id) REFERENCES surveys (id) ON DELETE CASCADE,
            CONSTRAINT chk_sq_scale CHECK (type <> 'scale' OR (scale_min IS NOT NULL AND scale_max IS NOT NULL AND scale_min < scale_max AND scale_max <= 10))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE survey_options (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id INT UNSIGNED NOT NULL,
            label_ru VARCHAR(255) NOT NULL,
            label_kk VARCHAR(255) NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_so_question (question_id, sort_order),
            CONSTRAINT fk_so_question FOREIGN KEY (question_id) REFERENCES survey_questions (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE survey_responses (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            survey_id INT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL COMMENT 'NULL in anonymous surveys',
            respondent_role ENUM('student','teacher') NOT NULL,
            department_id SMALLINT UNSIGNED NULL,
            course TINYINT UNSIGNED NULL,
            group_id INT UNSIGNED NULL COMMENT 'NULL in anonymous surveys',
            submitted_on DATE NOT NULL,
            submitted_at DATETIME NULL COMMENT 'NULL in anonymous surveys',
            PRIMARY KEY (id),
            KEY idx_sr_survey (survey_id),
            KEY fk_sr_user (user_id),
            KEY fk_sr_department (department_id),
            KEY fk_sr_group (group_id),
            CONSTRAINT fk_sr_survey FOREIGN KEY (survey_id) REFERENCES surveys (id) ON DELETE CASCADE,
            CONSTRAINT fk_sr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT fk_sr_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
            CONSTRAINT fk_sr_group FOREIGN KEY (group_id) REFERENCES `groups` (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE survey_participants (
            survey_id INT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            responded_on DATE NOT NULL,
            PRIMARY KEY (survey_id, user_id),
            KEY fk_sp_user (user_id),
            CONSTRAINT fk_sp_survey FOREIGN KEY (survey_id) REFERENCES surveys (id) ON DELETE CASCADE,
            CONSTRAINT fk_sp_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE survey_answers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            response_id INT UNSIGNED NOT NULL,
            question_id INT UNSIGNED NOT NULL,
            option_id INT UNSIGNED NULL,
            value_int TINYINT UNSIGNED NULL COMMENT 'Rating scale value',
            value_text TEXT NULL,
            PRIMARY KEY (id),
            KEY idx_sa_response (response_id),
            KEY idx_sa_question (question_id, option_id),
            KEY fk_sa_option (option_id),
            CONSTRAINT fk_sa_response FOREIGN KEY (response_id) REFERENCES survey_responses (id) ON DELETE CASCADE,
            CONSTRAINT fk_sa_question FOREIGN KEY (question_id) REFERENCES survey_questions (id) ON DELETE CASCADE,
            CONSTRAINT fk_sa_option FOREIGN KEY (option_id) REFERENCES survey_options (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS survey_answers',
        'DROP TABLE IF EXISTS survey_participants',
        'DROP TABLE IF EXISTS survey_responses',
        'DROP TABLE IF EXISTS survey_options',
        'DROP TABLE IF EXISTS survey_questions',
        'DROP TABLE IF EXISTS surveys',
    ],
];
