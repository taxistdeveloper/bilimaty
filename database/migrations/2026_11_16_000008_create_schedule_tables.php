<?php

declare(strict_types=1);

/**
 * Timetable.
 *
 * lesson_periods   — bell schedule (pair number and time).
 * schedule_entries — regular weekly lessons of a semester (weekday + period + week parity).
 * schedule_changes — one-off changes of a single occurrence on a given date:
 *                    substitution of teacher/classroom, cancellation or rescheduling.
 * Conflicts (teacher / group / classroom busy) are checked in ScheduleService under a
 * per-semester lock, because parity and subgroups cannot be expressed as unique keys.
 */
return [
    'up' => [
        "CREATE TABLE lesson_periods (
            id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
            number TINYINT UNSIGNED NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_lesson_periods_number (number),
            CONSTRAINT chk_lesson_periods_time CHECK (end_time > start_time),
            CONSTRAINT chk_lesson_periods_number CHECK (number BETWEEN 1 AND 12)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE schedule_entries (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            semester_id INT UNSIGNED NOT NULL,
            group_id INT UNSIGNED NOT NULL,
            subgroup TINYINT UNSIGNED NULL COMMENT 'NULL = whole group, 1/2 = subgroup',
            subject_id INT UNSIGNED NOT NULL,
            teacher_id INT UNSIGNED NOT NULL,
            classroom_id INT UNSIGNED NULL,
            weekday TINYINT UNSIGNED NOT NULL COMMENT '1 = Monday … 6 = Saturday',
            lesson_period_id TINYINT UNSIGNED NOT NULL,
            week_parity ENUM('every','odd','even') NOT NULL DEFAULT 'every',
            lesson_type ENUM('lecture','practice','lab','consultation') NOT NULL DEFAULT 'lecture',
            note VARCHAR(255) NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_se_slot (semester_id, weekday, lesson_period_id),
            KEY idx_se_group (group_id, semester_id),
            KEY idx_se_teacher (teacher_id, semester_id),
            KEY idx_se_classroom (classroom_id, semester_id),
            CONSTRAINT fk_se_semester FOREIGN KEY (semester_id) REFERENCES semesters (id) ON DELETE RESTRICT,
            CONSTRAINT fk_se_group FOREIGN KEY (group_id) REFERENCES `groups` (id) ON DELETE RESTRICT,
            CONSTRAINT fk_se_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE RESTRICT,
            CONSTRAINT fk_se_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE RESTRICT,
            CONSTRAINT fk_se_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms (id) ON DELETE RESTRICT,
            CONSTRAINT fk_se_period FOREIGN KEY (lesson_period_id) REFERENCES lesson_periods (id) ON DELETE RESTRICT,
            CONSTRAINT fk_se_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT chk_se_weekday CHECK (weekday BETWEEN 1 AND 6),
            CONSTRAINT chk_se_subgroup CHECK (subgroup IS NULL OR subgroup BETWEEN 1 AND 4)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE schedule_changes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            schedule_entry_id INT UNSIGNED NOT NULL,
            lesson_date DATE NOT NULL COMMENT 'Date of the regular occurrence being changed',
            change_type ENUM('modify','cancel','reschedule') NOT NULL,
            new_teacher_id INT UNSIGNED NULL,
            new_classroom_id INT UNSIGNED NULL,
            new_date DATE NULL,
            new_lesson_period_id TINYINT UNSIGNED NULL,
            reason VARCHAR(255) NOT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_sc_occurrence (schedule_entry_id, lesson_date),
            KEY idx_sc_lesson_date (lesson_date),
            KEY idx_sc_new_date (new_date),
            CONSTRAINT fk_sc_entry FOREIGN KEY (schedule_entry_id) REFERENCES schedule_entries (id) ON DELETE CASCADE,
            CONSTRAINT fk_sc_teacher FOREIGN KEY (new_teacher_id) REFERENCES teachers (id) ON DELETE RESTRICT,
            CONSTRAINT fk_sc_classroom FOREIGN KEY (new_classroom_id) REFERENCES classrooms (id) ON DELETE RESTRICT,
            CONSTRAINT fk_sc_period FOREIGN KEY (new_lesson_period_id) REFERENCES lesson_periods (id) ON DELETE RESTRICT,
            CONSTRAINT fk_sc_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT chk_sc_reschedule CHECK (change_type <> 'reschedule' OR (new_date IS NOT NULL AND new_lesson_period_id IS NOT NULL))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS schedule_changes',
        'DROP TABLE IF EXISTS schedule_entries',
        'DROP TABLE IF EXISTS lesson_periods',
    ],
];
