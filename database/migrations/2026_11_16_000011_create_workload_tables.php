<?php

declare(strict_types=1);

/**
 * Teaching workload (учебная нагрузка).
 *
 * workload_types       — configurable kinds of work. lesson_type links a kind to journal lessons
 *                        (its actual hours are counted from the journal); curriculum_field links it
 *                        to the curriculum hours column that caps the distribution.
 * workload_plans       — a teacher's plan for an academic year: rate and the annual hour limit set
 *                        by the administration (no built-in norm), draft → approved.
 * workload_assignments — distribution of planned hours: semester, group, subject, kind, subgroup.
 * workload_actuals     — actual hours entered by hand for kinds not kept in the journal
 *                        (consultations, exams, practice…). Lesson kinds are never entered here.
 */
return [
    'up' => [
        "CREATE TABLE workload_types (
            id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(30) NOT NULL,
            name_ru VARCHAR(150) NOT NULL,
            name_kk VARCHAR(150) NOT NULL,
            lesson_type ENUM('lecture','practice','lab','consultation') NULL COMMENT 'Actual hours come from journal lessons of this type',
            curriculum_field ENUM('hours_theory','hours_practice','hours_lab') NULL COMMENT 'Curriculum column that caps distributed hours',
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_wt_code (code),
            UNIQUE KEY uq_wt_lesson_type (lesson_type),
            UNIQUE KEY uq_wt_curriculum_field (curriculum_field)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "INSERT INTO workload_types (code, name_ru, name_kk, lesson_type, curriculum_field, sort_order) VALUES
            ('theory', 'Теоретические занятия', 'Теориялық сабақтар', 'lecture', 'hours_theory', 1),
            ('practice', 'Практические занятия', 'Практикалық сабақтар', 'practice', 'hours_practice', 2),
            ('lab', 'Лабораторные работы', 'Зертханалық жұмыстар', 'lab', 'hours_lab', 3),
            ('consultation', 'Консультации', 'Консультациялар', 'consultation', NULL, 4),
            ('exam', 'Экзамены', 'Емтихандар', NULL, NULL, 5),
            ('internship', 'Практика', 'Практика', NULL, NULL, 6),
            ('other', 'Другие виды учебной деятельности', 'Оқу қызметінің басқа түрлері', NULL, NULL, 7)",

        "CREATE TABLE workload_plans (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            academic_year_id SMALLINT UNSIGNED NOT NULL,
            teacher_id INT UNSIGNED NOT NULL,
            rate DECIMAL(4,2) NULL COMMENT 'Staff rate (ставка), informational',
            max_hours SMALLINT UNSIGNED NULL COMMENT 'Annual limit set by the administration; NULL = not set',
            status ENUM('draft','approved') NOT NULL DEFAULT 'draft',
            approved_at DATETIME NULL,
            approved_by BIGINT UNSIGNED NULL,
            note VARCHAR(500) NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_wp_teacher_year (academic_year_id, teacher_id),
            KEY idx_wp_teacher (teacher_id),
            CONSTRAINT fk_wp_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE RESTRICT,
            CONSTRAINT fk_wp_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE RESTRICT,
            CONSTRAINT fk_wp_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT fk_wp_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT chk_wp_rate CHECK (rate IS NULL OR (rate > 0 AND rate <= 3)),
            CONSTRAINT chk_wp_max CHECK (max_hours IS NULL OR max_hours BETWEEN 1 AND 3000)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE workload_assignments (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            plan_id INT UNSIGNED NOT NULL,
            semester_id INT UNSIGNED NOT NULL,
            group_id INT UNSIGNED NULL,
            subject_id INT UNSIGNED NULL,
            workload_type_id SMALLINT UNSIGNED NOT NULL,
            subgroup TINYINT UNSIGNED NULL COMMENT 'NULL = whole group',
            hours SMALLINT UNSIGNED NOT NULL,
            note VARCHAR(255) NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_wa_plan (plan_id, semester_id),
            KEY idx_wa_distribution (semester_id, group_id, subject_id, workload_type_id),
            KEY fk_wa_group (group_id),
            KEY fk_wa_subject (subject_id),
            KEY fk_wa_type (workload_type_id),
            KEY fk_wa_created_by (created_by),
            CONSTRAINT fk_wa_plan FOREIGN KEY (plan_id) REFERENCES workload_plans (id) ON DELETE CASCADE,
            CONSTRAINT fk_wa_semester FOREIGN KEY (semester_id) REFERENCES semesters (id) ON DELETE RESTRICT,
            CONSTRAINT fk_wa_group FOREIGN KEY (group_id) REFERENCES `groups` (id) ON DELETE RESTRICT,
            CONSTRAINT fk_wa_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE RESTRICT,
            CONSTRAINT fk_wa_type FOREIGN KEY (workload_type_id) REFERENCES workload_types (id) ON DELETE RESTRICT,
            CONSTRAINT fk_wa_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT chk_wa_hours CHECK (hours BETWEEN 1 AND 2000),
            CONSTRAINT chk_wa_subgroup CHECK (subgroup IS NULL OR subgroup BETWEEN 1 AND 4)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE workload_actuals (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            assignment_id INT UNSIGNED NOT NULL,
            work_date DATE NOT NULL,
            hours DECIMAL(6,2) NOT NULL,
            note VARCHAR(255) NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_wact_assignment (assignment_id, work_date),
            KEY fk_wact_created_by (created_by),
            CONSTRAINT fk_wact_assignment FOREIGN KEY (assignment_id) REFERENCES workload_assignments (id) ON DELETE RESTRICT,
            CONSTRAINT fk_wact_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT chk_wact_hours CHECK (hours > 0 AND hours <= 24)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS workload_actuals',
        'DROP TABLE IF EXISTS workload_assignments',
        'DROP TABLE IF EXISTS workload_plans',
        'DROP TABLE IF EXISTS workload_types',
    ],
];
