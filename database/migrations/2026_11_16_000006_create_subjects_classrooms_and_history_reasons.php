<?php

declare(strict_types=1);

/**
 * Dictionaries: subjects (disciplines) and classrooms.
 * student_group_history gets end_reason: why a membership period ended.
 */
return [
    'up' => [
        "CREATE TABLE subjects (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(30) NOT NULL COMMENT 'Index of the discipline in the curriculum',
            name_ru VARCHAR(255) NOT NULL,
            name_kk VARCHAR(255) NOT NULL,
            department_id SMALLINT UNSIGNED NULL COMMENT 'Responsible department / subject committee',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_subjects_code (code),
            KEY idx_subjects_department (department_id),
            KEY idx_subjects_name (name_ru),
            CONSTRAINT fk_subjects_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE classrooms (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            number VARCHAR(20) NOT NULL,
            building VARCHAR(50) NOT NULL DEFAULT '',
            name VARCHAR(150) NULL,
            type ENUM('lecture','practice','computer','laboratory','workshop','gym','assembly','other') NOT NULL DEFAULT 'lecture',
            capacity SMALLINT UNSIGNED NOT NULL DEFAULT 30,
            department_id SMALLINT UNSIGNED NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_classrooms_building_number (building, number),
            KEY idx_classrooms_type (type, is_active),
            CONSTRAINT fk_classrooms_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
            CONSTRAINT chk_classrooms_capacity CHECK (capacity BETWEEN 1 AND 1000)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "ALTER TABLE student_group_history
            ADD COLUMN end_reason ENUM('transfer','promotion','academic_leave','graduation','expulsion','transferred_out') NULL AFTER end_date,
            ADD KEY idx_sgh_open (student_id, end_date)",
    ],
    'down' => [
        'ALTER TABLE student_group_history DROP KEY idx_sgh_open, DROP COLUMN end_reason',
        'DROP TABLE IF EXISTS classrooms',
        'DROP TABLE IF EXISTS subjects',
    ],
];
