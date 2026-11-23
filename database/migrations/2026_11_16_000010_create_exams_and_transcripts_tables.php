<?php

declare(strict_types=1);

/**
 * Ratings, exams, retakes and transcripts.
 *
 * exams         — an assessment event of a journal (Rating 1, Rating 2, Exam…): period/date, time,
 *                 classroom, examiner, form, status and the statement (ведомость) number.
 * exam_results  — the statement rows: admission, attendance and the value. When the statement is
 *                 closed the values are copied into student_assessments (the journal component),
 *                 which stays the single input for the final grade calculation.
 * retakes       — retake attempts with old and new value (history of result changes).
 * transcripts   — an issued transcript is an immutable snapshot (+ transcript_items), so later
 *                 changes never alter a document already handed out. verification_code is the
 *                 random token in the QR code; the public check page shows no personal data.
 */
return [
    'up' => [
        "CREATE TABLE exams (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            assignment_id INT UNSIGNED NOT NULL,
            assessment_type_id SMALLINT UNSIGNED NOT NULL,
            exam_form ENUM('written','oral','test','computer_test','practical','project','combined') NOT NULL DEFAULT 'written',
            period_start DATE NULL COMMENT 'Rating window start (optional)',
            exam_date DATE NOT NULL,
            start_time TIME NULL,
            classroom_id INT UNSIGNED NULL,
            examiner_teacher_id INT UNSIGNED NOT NULL,
            status ENUM('planned','open','closed') NOT NULL DEFAULT 'planned',
            statement_number VARCHAR(30) NOT NULL,
            closed_at DATETIME NULL,
            closed_by BIGINT UNSIGNED NULL,
            note VARCHAR(500) NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_exams_component (assignment_id, assessment_type_id),
            UNIQUE KEY uq_exams_statement (statement_number),
            KEY idx_exams_date (exam_date, start_time),
            KEY idx_exams_examiner (examiner_teacher_id, exam_date),
            CONSTRAINT fk_exams_assignment FOREIGN KEY (assignment_id) REFERENCES teacher_subject_assignments (id) ON DELETE RESTRICT,
            CONSTRAINT fk_exams_type FOREIGN KEY (assessment_type_id) REFERENCES assessment_types (id) ON DELETE RESTRICT,
            CONSTRAINT fk_exams_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms (id) ON DELETE RESTRICT,
            CONSTRAINT fk_exams_examiner FOREIGN KEY (examiner_teacher_id) REFERENCES teachers (id) ON DELETE RESTRICT,
            CONSTRAINT fk_exams_closed_by FOREIGN KEY (closed_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT fk_exams_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT chk_exams_period CHECK (period_start IS NULL OR period_start <= exam_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE exam_results (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            exam_id INT UNSIGNED NOT NULL,
            student_id INT UNSIGNED NOT NULL,
            admission ENUM('admitted','not_admitted') NOT NULL DEFAULT 'admitted',
            attendance ENUM('present','absent','excused') NULL,
            value DECIMAL(6,2) NULL,
            graded_by BIGINT UNSIGNED NULL,
            graded_at DATETIME NULL,
            note VARCHAR(255) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_exam_results_row (exam_id, student_id),
            KEY idx_exam_results_student (student_id),
            CONSTRAINT fk_er_exam FOREIGN KEY (exam_id) REFERENCES exams (id) ON DELETE CASCADE,
            CONSTRAINT fk_er_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
            CONSTRAINT fk_er_graded_by FOREIGN KEY (graded_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT chk_er_value CHECK (value IS NULL OR attendance = 'present')
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE retakes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            exam_id INT UNSIGNED NOT NULL,
            student_id INT UNSIGNED NOT NULL,
            attempt_no TINYINT UNSIGNED NOT NULL,
            retake_date DATE NOT NULL,
            examiner_teacher_id INT UNSIGNED NOT NULL,
            old_value DECIMAL(6,2) NULL,
            new_value DECIMAL(6,2) NULL,
            status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
            reason VARCHAR(255) NOT NULL,
            order_number VARCHAR(50) NULL,
            completed_at DATETIME NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_retakes_attempt (exam_id, student_id, attempt_no),
            KEY idx_retakes_date (retake_date),
            CONSTRAINT fk_rt_exam FOREIGN KEY (exam_id) REFERENCES exams (id) ON DELETE CASCADE,
            CONSTRAINT fk_rt_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
            CONSTRAINT fk_rt_examiner FOREIGN KEY (examiner_teacher_id) REFERENCES teachers (id) ON DELETE RESTRICT,
            CONSTRAINT fk_rt_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE transcripts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            number VARCHAR(30) NOT NULL,
            verification_code CHAR(32) NOT NULL,
            fingerprint CHAR(64) NOT NULL COMMENT 'SHA-256 of the snapshot, printed on the document',
            snapshot JSON NOT NULL COMMENT 'Header data at issue time (name, programme, group…)',
            gpa DECIMAL(4,2) NULL COMMENT 'Only when an approved GPA scale is configured',
            status ENUM('issued','revoked') NOT NULL DEFAULT 'issued',
            issued_by BIGINT UNSIGNED NULL,
            issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            revoked_at DATETIME NULL,
            revoked_by BIGINT UNSIGNED NULL,
            revoke_reason VARCHAR(255) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_transcripts_number (number),
            UNIQUE KEY uq_transcripts_code (verification_code),
            KEY idx_transcripts_student (student_id, issued_at),
            CONSTRAINT fk_tr_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE RESTRICT,
            CONSTRAINT fk_tr_issued_by FOREIGN KEY (issued_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT fk_tr_revoked_by FOREIGN KEY (revoked_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE transcript_items (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            transcript_id INT UNSIGNED NOT NULL,
            academic_year VARCHAR(9) NOT NULL,
            semester_number TINYINT UNSIGNED NOT NULL,
            subject_code VARCHAR(30) NOT NULL,
            subject_name_ru VARCHAR(255) NOT NULL,
            subject_name_kk VARCHAR(255) NOT NULL,
            hours SMALLINT UNSIGNED NULL,
            hours_source ENUM('curriculum','conducted') NULL,
            credits DECIMAL(5,2) NULL,
            components JSON NOT NULL COMMENT 'Ratings/exam values by type code',
            final DECIMAL(6,2) NULL,
            letter VARCHAR(5) NULL,
            grade_points DECIMAL(4,2) NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_ti_transcript (transcript_id, sort_order),
            CONSTRAINT fk_ti_transcript FOREIGN KEY (transcript_id) REFERENCES transcripts (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS transcript_items',
        'DROP TABLE IF EXISTS transcripts',
        'DROP TABLE IF EXISTS retakes',
        'DROP TABLE IF EXISTS exam_results',
        'DROP TABLE IF EXISTS exams',
    ],
];
