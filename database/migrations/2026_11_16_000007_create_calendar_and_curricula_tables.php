<?php

declare(strict_types=1);

/**
 * Academic calendar events, curricula (study plans) and the link group -> curriculum.
 *
 * Semester periods are NOT duplicated as events: the calendar reads them from `semesters`.
 * An event applies to the whole college (course and group NULL), to one course, or to one group.
 */
return [
    'up' => [
        "CREATE TABLE academic_calendar_events (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            academic_year_id SMALLINT UNSIGNED NOT NULL,
            type ENUM('theory','exam_session','practice','holiday','public_holiday','final_attestation','control_week','other') NOT NULL,
            title_ru VARCHAR(255) NOT NULL,
            title_kk VARCHAR(255) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            course TINYINT UNSIGNED NULL COMMENT 'NULL = all courses',
            group_id INT UNSIGNED NULL COMMENT 'NULL = all groups',
            note VARCHAR(500) NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_ace_year_dates (academic_year_id, start_date, end_date),
            KEY idx_ace_group (group_id),
            CONSTRAINT fk_ace_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE RESTRICT,
            CONSTRAINT fk_ace_group FOREIGN KEY (group_id) REFERENCES `groups` (id) ON DELETE CASCADE,
            CONSTRAINT fk_ace_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT chk_ace_dates CHECK (end_date >= start_date),
            CONSTRAINT chk_ace_course CHECK (course IS NULL OR course BETWEEN 1 AND 4)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE curricula (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            specialty_id INT UNSIGNED NOT NULL,
            qualification_id INT UNSIGNED NULL,
            title VARCHAR(255) NOT NULL,
            admission_year SMALLINT UNSIGNED NOT NULL COMMENT 'Intake the plan applies to',
            study_form ENUM('full_time','part_time','evening','distance') NOT NULL DEFAULT 'full_time',
            base_education ENUM('basic_secondary','general_secondary','tvet') NOT NULL DEFAULT 'basic_secondary'
                COMMENT 'basic = after 9th grade, general = after 11th grade, tvet = after college',
            semesters_count TINYINT UNSIGNED NOT NULL DEFAULT 8,
            status ENUM('draft','approved','archived') NOT NULL DEFAULT 'draft',
            approved_at DATETIME NULL,
            approved_by BIGINT UNSIGNED NULL,
            note VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_curricula_cohort (specialty_id, qualification_id, admission_year, study_form, base_education),
            KEY idx_curricula_status (status),
            CONSTRAINT fk_curricula_specialty FOREIGN KEY (specialty_id) REFERENCES specialties (id) ON DELETE RESTRICT,
            CONSTRAINT fk_curricula_qualification FOREIGN KEY (qualification_id) REFERENCES qualifications (id) ON DELETE RESTRICT,
            CONSTRAINT fk_curricula_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT chk_curricula_semesters CHECK (semesters_count BETWEEN 1 AND 10),
            CONSTRAINT chk_curricula_year CHECK (admission_year BETWEEN 2000 AND 2100)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE curriculum_subjects (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            curriculum_id INT UNSIGNED NOT NULL,
            subject_id INT UNSIGNED NOT NULL,
            module_code VARCHAR(30) NULL COMMENT 'Cycle / module index, e.g. ООД, БМ 01, ПМ 03',
            semester TINYINT UNSIGNED NOT NULL,
            hours_total SMALLINT UNSIGNED NOT NULL,
            hours_theory SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            hours_practice SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            hours_lab SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            credits DECIMAL(5,2) NULL COMMENT 'Only if the college uses credits; no formula is applied',
            control_form ENUM('exam','credit','diff_credit','coursework','none') NOT NULL DEFAULT 'none',
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uq_cs_subject_semester (curriculum_id, subject_id, semester),
            KEY idx_cs_subject (subject_id),
            CONSTRAINT fk_cs_curriculum FOREIGN KEY (curriculum_id) REFERENCES curricula (id) ON DELETE CASCADE,
            CONSTRAINT fk_cs_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE RESTRICT,
            CONSTRAINT chk_cs_semester CHECK (semester BETWEEN 1 AND 10),
            CONSTRAINT chk_cs_hours CHECK (hours_theory + hours_practice + hours_lab <= hours_total),
            CONSTRAINT chk_cs_credits CHECK (credits IS NULL OR credits >= 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "ALTER TABLE `groups`
            ADD COLUMN curriculum_id INT UNSIGNED NULL AFTER qualification_id,
            ADD CONSTRAINT fk_groups_curriculum FOREIGN KEY (curriculum_id) REFERENCES curricula (id) ON DELETE SET NULL",
    ],
    'down' => [
        'ALTER TABLE `groups` DROP FOREIGN KEY fk_groups_curriculum, DROP COLUMN curriculum_id',
        'DROP TABLE IF EXISTS curriculum_subjects',
        'DROP TABLE IF EXISTS curricula',
        'DROP TABLE IF EXISTS academic_calendar_events',
    ],
];
