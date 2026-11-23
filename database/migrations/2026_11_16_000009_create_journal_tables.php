<?php

declare(strict_types=1);

/**
 * Electronic journal.
 *
 * assessment_types            — kinds of results: current (average of lesson grades), rating 1/2, exam, final…
 * grading_schemes             — grading rules set by the administration: scale, components, weights,
 *   + _components               pass threshold. An active scheme is immutable; new rules = new version.
 * teacher_subject_assignments — a journal: semester + group + subject + responsible teacher + scheme + status.
 * journal_lessons             — lesson dates with topic, homework and who actually conducted the lesson.
 * journal_grades / attendance — lesson grade and absence (separate status) per student.
 * student_assessments         — rating/exam values; the official final is stored with the scheme version.
 * grade_change_history        — every change: who, when, old and new value.
 */
return [
    'up' => [
        "CREATE TABLE assessment_types (
            id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(30) NOT NULL,
            name_ru VARCHAR(150) NOT NULL,
            name_kk VARCHAR(150) NOT NULL,
            short_ru VARCHAR(20) NOT NULL,
            short_kk VARCHAR(20) NOT NULL,
            source ENUM('manual','lessons_average','computed_final') NOT NULL DEFAULT 'manual',
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_assessment_types_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE grading_schemes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            parent_id INT UNSIGNED NULL COMMENT 'Previous version',
            grade_min DECIMAL(6,2) NOT NULL,
            grade_max DECIMAL(6,2) NOT NULL,
            final_decimals TINYINT UNSIGNED NOT NULL DEFAULT 0,
            pass_threshold DECIMAL(6,2) NULL,
            status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
            approved_by BIGINT UNSIGNED NULL,
            approved_at DATETIME NULL,
            note VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_grading_schemes_status (status),
            CONSTRAINT fk_gs_parent FOREIGN KEY (parent_id) REFERENCES grading_schemes (id) ON DELETE SET NULL,
            CONSTRAINT fk_gs_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT chk_gs_scale CHECK (grade_max > grade_min),
            CONSTRAINT chk_gs_decimals CHECK (final_decimals <= 2),
            CONSTRAINT chk_gs_threshold CHECK (pass_threshold IS NULL OR (pass_threshold >= grade_min AND pass_threshold <= grade_max))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE grading_scheme_components (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            grading_scheme_id INT UNSIGNED NOT NULL,
            assessment_type_id SMALLINT UNSIGNED NOT NULL,
            weight DECIMAL(5,2) NOT NULL COMMENT 'Percent; weights of an active scheme sum to 100',
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uq_gsc_type (grading_scheme_id, assessment_type_id),
            CONSTRAINT fk_gsc_scheme FOREIGN KEY (grading_scheme_id) REFERENCES grading_schemes (id) ON DELETE CASCADE,
            CONSTRAINT fk_gsc_type FOREIGN KEY (assessment_type_id) REFERENCES assessment_types (id) ON DELETE RESTRICT,
            CONSTRAINT chk_gsc_weight CHECK (weight > 0 AND weight <= 100)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE teacher_subject_assignments (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            semester_id INT UNSIGNED NOT NULL,
            group_id INT UNSIGNED NOT NULL,
            subject_id INT UNSIGNED NOT NULL,
            teacher_id INT UNSIGNED NOT NULL COMMENT 'Responsible teacher',
            grading_scheme_id INT UNSIGNED NULL,
            status ENUM('open','submitted','approved') NOT NULL DEFAULT 'open',
            submitted_at DATETIME NULL,
            approved_at DATETIME NULL,
            approved_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_tsa_journal (semester_id, group_id, subject_id),
            KEY idx_tsa_teacher (teacher_id, semester_id),
            KEY idx_tsa_group (group_id, semester_id),
            CONSTRAINT fk_tsa_semester FOREIGN KEY (semester_id) REFERENCES semesters (id) ON DELETE RESTRICT,
            CONSTRAINT fk_tsa_group FOREIGN KEY (group_id) REFERENCES `groups` (id) ON DELETE RESTRICT,
            CONSTRAINT fk_tsa_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE RESTRICT,
            CONSTRAINT fk_tsa_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE RESTRICT,
            CONSTRAINT fk_tsa_scheme FOREIGN KEY (grading_scheme_id) REFERENCES grading_schemes (id) ON DELETE RESTRICT,
            CONSTRAINT fk_tsa_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE journal_lessons (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            assignment_id INT UNSIGNED NOT NULL,
            lesson_date DATE NOT NULL,
            lesson_period_id TINYINT UNSIGNED NOT NULL,
            lesson_type ENUM('lecture','practice','lab','consultation') NOT NULL DEFAULT 'lecture',
            topic VARCHAR(255) NULL,
            homework VARCHAR(500) NULL,
            note VARCHAR(500) NULL,
            hours TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'Academic hours of the lesson',
            conducted_by INT UNSIGNED NULL COMMENT 'Teacher who actually conducted it (substitutions)',
            schedule_entry_id INT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_jl_slot (assignment_id, lesson_date, lesson_period_id),
            KEY idx_jl_conducted (conducted_by, lesson_date),
            CONSTRAINT fk_jl_assignment FOREIGN KEY (assignment_id) REFERENCES teacher_subject_assignments (id) ON DELETE CASCADE,
            CONSTRAINT fk_jl_period FOREIGN KEY (lesson_period_id) REFERENCES lesson_periods (id) ON DELETE RESTRICT,
            CONSTRAINT fk_jl_teacher FOREIGN KEY (conducted_by) REFERENCES teachers (id) ON DELETE SET NULL,
            CONSTRAINT fk_jl_entry FOREIGN KEY (schedule_entry_id) REFERENCES schedule_entries (id) ON DELETE SET NULL,
            CONSTRAINT fk_jl_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT chk_jl_hours CHECK (hours BETWEEN 1 AND 8)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE journal_grades (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            journal_lesson_id INT UNSIGNED NOT NULL,
            student_id INT UNSIGNED NOT NULL,
            value DECIMAL(6,2) NULL,
            comment VARCHAR(255) NULL,
            updated_by BIGINT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_jg_cell (journal_lesson_id, student_id),
            KEY idx_jg_student (student_id),
            CONSTRAINT fk_jg_lesson FOREIGN KEY (journal_lesson_id) REFERENCES journal_lessons (id) ON DELETE CASCADE,
            CONSTRAINT fk_jg_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
            CONSTRAINT fk_jg_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE attendance (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            journal_lesson_id INT UNSIGNED NOT NULL,
            student_id INT UNSIGNED NOT NULL,
            status ENUM('absent','excused') NOT NULL COMMENT 'Only absences are stored; no row = present',
            updated_by BIGINT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_attendance_cell (journal_lesson_id, student_id),
            KEY idx_attendance_student (student_id),
            CONSTRAINT fk_att_lesson FOREIGN KEY (journal_lesson_id) REFERENCES journal_lessons (id) ON DELETE CASCADE,
            CONSTRAINT fk_att_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
            CONSTRAINT fk_att_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE student_assessments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            assignment_id INT UNSIGNED NOT NULL,
            student_id INT UNSIGNED NOT NULL,
            assessment_type_id SMALLINT UNSIGNED NOT NULL,
            value DECIMAL(6,2) NULL,
            grading_scheme_id INT UNSIGNED NULL COMMENT 'Scheme version used for an official final',
            updated_by BIGINT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_sa_cell (assignment_id, student_id, assessment_type_id),
            KEY idx_sa_student (student_id),
            CONSTRAINT fk_sa_assignment FOREIGN KEY (assignment_id) REFERENCES teacher_subject_assignments (id) ON DELETE CASCADE,
            CONSTRAINT fk_sa_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
            CONSTRAINT fk_sa_type FOREIGN KEY (assessment_type_id) REFERENCES assessment_types (id) ON DELETE RESTRICT,
            CONSTRAINT fk_sa_scheme FOREIGN KEY (grading_scheme_id) REFERENCES grading_schemes (id) ON DELETE RESTRICT,
            CONSTRAINT fk_sa_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE grade_change_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            assignment_id INT UNSIGNED NOT NULL,
            student_id INT UNSIGNED NULL,
            journal_lesson_id INT UNSIGNED NULL,
            assessment_type_id SMALLINT UNSIGNED NULL,
            field ENUM('grade','attendance','assessment','comment','final','status') NOT NULL,
            old_value VARCHAR(255) NULL,
            new_value VARCHAR(255) NULL,
            changed_by BIGINT UNSIGNED NULL,
            changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_gch_journal (assignment_id, changed_at),
            KEY idx_gch_student (student_id, changed_at),
            CONSTRAINT fk_gch_assignment FOREIGN KEY (assignment_id) REFERENCES teacher_subject_assignments (id) ON DELETE CASCADE,
            CONSTRAINT fk_gch_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
            CONSTRAINT fk_gch_lesson FOREIGN KEY (journal_lesson_id) REFERENCES journal_lessons (id) ON DELETE SET NULL,
            CONSTRAINT fk_gch_type FOREIGN KEY (assessment_type_id) REFERENCES assessment_types (id) ON DELETE SET NULL,
            CONSTRAINT fk_gch_changed_by FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS grade_change_history',
        'DROP TABLE IF EXISTS student_assessments',
        'DROP TABLE IF EXISTS attendance',
        'DROP TABLE IF EXISTS journal_grades',
        'DROP TABLE IF EXISTS journal_lessons',
        'DROP TABLE IF EXISTS teacher_subject_assignments',
        'DROP TABLE IF EXISTS grading_scheme_components',
        'DROP TABLE IF EXISTS grading_schemes',
        'DROP TABLE IF EXISTS assessment_types',
    ],
];
