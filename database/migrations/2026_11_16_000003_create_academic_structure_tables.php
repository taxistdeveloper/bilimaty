<?php

declare(strict_types=1);

/**
 * Organizational core: departments, specialties, qualifications, academic_years, semesters.
 */
return [
    'up' => [
        "CREATE TABLE departments (
            id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(20) NOT NULL,
            name_ru VARCHAR(255) NOT NULL,
            name_kk VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_departments_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE specialties (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            department_id SMALLINT UNSIGNED NOT NULL,
            code VARCHAR(20) NOT NULL COMMENT 'Classifier code of the specialty',
            name_ru VARCHAR(255) NOT NULL,
            name_kk VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_specialties_code (code),
            KEY idx_specialties_department (department_id),
            CONSTRAINT fk_specialties_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE qualifications (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            specialty_id INT UNSIGNED NOT NULL,
            code VARCHAR(20) NOT NULL,
            name_ru VARCHAR(255) NOT NULL,
            name_kk VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_qualifications_code (code),
            KEY idx_qualifications_specialty (specialty_id),
            CONSTRAINT fk_qualifications_specialty FOREIGN KEY (specialty_id) REFERENCES specialties (id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // active_flag is 1 only for the active year and NULL otherwise, so the UNIQUE
        // index guarantees at most one active academic year at the database level.
        "CREATE TABLE academic_years (
            id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(9) NOT NULL COMMENT 'e.g. 2025-2026',
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            status ENUM('planned','active','archived') NOT NULL DEFAULT 'planned',
            active_flag TINYINT(1) GENERATED ALWAYS AS (IF(status = 'active', 1, NULL)) STORED,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_academic_years_title (title),
            UNIQUE KEY uq_academic_years_active (active_flag),
            CONSTRAINT chk_academic_years_dates CHECK (end_date > start_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE semesters (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            academic_year_id SMALLINT UNSIGNED NOT NULL,
            number TINYINT UNSIGNED NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_semesters_year_number (academic_year_id, number),
            CONSTRAINT fk_semesters_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE RESTRICT,
            CONSTRAINT chk_semesters_number CHECK (number BETWEEN 1 AND 2),
            CONSTRAINT chk_semesters_dates CHECK (end_date > start_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS semesters',
        'DROP TABLE IF EXISTS academic_years',
        'DROP TABLE IF EXISTS qualifications',
        'DROP TABLE IF EXISTS specialties',
        'DROP TABLE IF EXISTS departments',
    ],
];
