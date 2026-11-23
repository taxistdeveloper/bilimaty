<?php

declare(strict_types=1);

/**
 * Practice (учебная / производственная / преддипломная): the academic office assigns a practice to a
 * supervising teacher (group, hours, deadline); the teacher fills in "График программы прохождения
 * практики" — topic, date, start and end time, place, distance learning (ДОТ) — and submits it when the
 * counted hours equal the assigned hours (weekends and public holidays of the academic calendar are not
 * counted). The office approves the schedule or returns it with a reason.
 *
 * practice_assignments — the assignment and its status: assigned → in_progress → submitted → approved,
 *                        submitted → returned → in_progress… "Overdue" is computed (deadline passed,
 *                        not submitted), not stored.
 * practice_entries     — rows of the schedule; hours are computed from the times.
 */
return [
    'up' => [
        "CREATE TABLE practice_assignments (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            teacher_id INT UNSIGNED NOT NULL COMMENT 'Supervisor of the practice',
            group_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL COMMENT 'Наименование практики',
            practice_type ENUM('educational','production','pregraduation') NOT NULL DEFAULT 'production',
            module_name VARCHAR(255) NULL COMMENT 'Наименование модуля обучения',
            hours SMALLINT UNSIGNED NOT NULL COMMENT 'Hours the schedule must contain',
            deadline DATE NOT NULL COMMENT 'Schedule due date',
            status ENUM('assigned','in_progress','submitted','approved','returned') NOT NULL DEFAULT 'assigned',
            teacher_comment VARCHAR(1000) NULL,
            return_reason VARCHAR(1000) NULL,
            submitted_at DATETIME NULL,
            decided_at DATETIME NULL,
            decided_by BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_pa_teacher (teacher_id, status),
            KEY idx_pa_group (group_id),
            KEY idx_pa_deadline (deadline, status),
            KEY idx_pa_decided_by (decided_by),
            KEY idx_pa_created_by (created_by),
            CONSTRAINT fk_pa_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE RESTRICT,
            CONSTRAINT fk_pa_group FOREIGN KEY (group_id) REFERENCES `groups` (id) ON DELETE RESTRICT,
            CONSTRAINT fk_pa_decided_by FOREIGN KEY (decided_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT fk_pa_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT chk_pa_hours CHECK (hours BETWEEN 1 AND 2000)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE practice_entries (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            assignment_id INT UNSIGNED NOT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            topic VARCHAR(500) NULL COMMENT 'Наименование модуля, раздела, тема',
            entry_date DATE NULL,
            time_start TIME NULL,
            time_end TIME NULL,
            place VARCHAR(255) NULL COMMENT 'Room, address, or platform/link for distance learning',
            is_distance TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ДОТ',
            PRIMARY KEY (id),
            KEY idx_pe_assignment (assignment_id, sort_order),
            KEY idx_pe_date (entry_date),
            CONSTRAINT fk_pe_assignment FOREIGN KEY (assignment_id) REFERENCES practice_assignments (id) ON DELETE CASCADE,
            CONSTRAINT chk_pe_times CHECK (time_start IS NULL OR time_end IS NULL OR time_end > time_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS practice_entries',
        'DROP TABLE IF EXISTS practice_assignments',
    ],
];
