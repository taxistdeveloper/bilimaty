<?php

declare(strict_types=1);

/**
 * Parents and guardians: a user with the "parent" role linked to one or more students.
 * A parent sees only the students linked here (grades, attendance, exams) — read only.
 */
return [
    'up' => [
        "CREATE TABLE student_guardians (
            student_id INT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            relation ENUM('mother','father','guardian','other') NOT NULL DEFAULT 'guardian',
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (student_id, user_id),
            KEY idx_sg_user (user_id),
            KEY fk_sg_created_by (created_by),
            CONSTRAINT fk_sg_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
            CONSTRAINT fk_sg_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_sg_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS student_guardians',
    ],
];
