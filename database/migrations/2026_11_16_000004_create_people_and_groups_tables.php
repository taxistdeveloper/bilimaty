<?php

declare(strict_types=1);

/**
 * People and groups: teachers, groups, students, student_group_history.
 * The department of a group is derived from its specialty (3NF), not duplicated.
 */
return [
    'up' => [
        "CREATE TABLE teachers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            department_id SMALLINT UNSIGNED NULL,
            employee_number VARCHAR(30) NULL,
            position VARCHAR(150) NULL,
            academic_degree VARCHAR(150) NULL,
            hire_date DATE NULL,
            status ENUM('active','on_leave','dismissed') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_teachers_user (user_id),
            UNIQUE KEY uq_teachers_employee_number (employee_number),
            KEY idx_teachers_department (department_id, status),
            CONSTRAINT fk_teachers_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
            CONSTRAINT fk_teachers_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "ALTER TABLE departments
            ADD COLUMN head_teacher_id INT UNSIGNED NULL AFTER name_kk,
            ADD CONSTRAINT fk_departments_head FOREIGN KEY (head_teacher_id) REFERENCES teachers (id) ON DELETE SET NULL",

        // `groups` is a reserved word in MySQL 8: always quote it with backticks.
        "CREATE TABLE `groups` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(30) NOT NULL,
            name VARCHAR(100) NOT NULL,
            specialty_id INT UNSIGNED NOT NULL,
            qualification_id INT UNSIGNED NULL,
            course TINYINT UNSIGNED NOT NULL,
            admission_year SMALLINT UNSIGNED NOT NULL,
            academic_year_id SMALLINT UNSIGNED NOT NULL,
            curator_teacher_id INT UNSIGNED NULL,
            study_form ENUM('full_time','part_time','evening','distance') NOT NULL DEFAULT 'full_time',
            study_language ENUM('kk','ru') NOT NULL DEFAULT 'kk',
            status ENUM('active','graduated','archived','disbanded') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_groups_code (code),
            KEY idx_groups_specialty (specialty_id, status),
            KEY idx_groups_year (academic_year_id),
            KEY idx_groups_curator (curator_teacher_id),
            CONSTRAINT fk_groups_specialty FOREIGN KEY (specialty_id) REFERENCES specialties (id) ON DELETE RESTRICT,
            CONSTRAINT fk_groups_qualification FOREIGN KEY (qualification_id) REFERENCES qualifications (id) ON DELETE RESTRICT,
            CONSTRAINT fk_groups_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE RESTRICT,
            CONSTRAINT fk_groups_curator FOREIGN KEY (curator_teacher_id) REFERENCES teachers (id) ON DELETE SET NULL,
            CONSTRAINT chk_groups_course CHECK (course BETWEEN 1 AND 4),
            CONSTRAINT chk_groups_admission_year CHECK (admission_year BETWEEN 2000 AND 2100)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE students (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            student_number VARCHAR(30) NOT NULL COMMENT 'Internal student ID',
            iin CHAR(12) NULL COMMENT 'Personal data: IIN',
            birth_date DATE NULL,
            gender ENUM('male','female') NULL,
            current_group_id INT UNSIGNED NULL,
            enrollment_date DATE NULL,
            funding ENUM('state_grant','paid') NULL,
            status ENUM('active','academic_leave','expelled','graduated','transferred') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_students_user (user_id),
            UNIQUE KEY uq_students_number (student_number),
            UNIQUE KEY uq_students_iin (iin),
            KEY idx_students_group (current_group_id, status),
            CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
            CONSTRAINT fk_students_group FOREIGN KEY (current_group_id) REFERENCES `groups` (id) ON DELETE RESTRICT,
            CONSTRAINT chk_students_iin CHECK (iin IS NULL OR iin REGEXP '^[0-9]{12}$')
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE student_group_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            group_id INT UNSIGNED NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NULL,
            reason ENUM('enrollment','transfer','promotion','restoration','academic_leave','graduation','expulsion') NOT NULL,
            order_number VARCHAR(50) NULL COMMENT 'Number of the college order',
            comment VARCHAR(500) NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sgh_student (student_id, start_date),
            KEY idx_sgh_group (group_id),
            CONSTRAINT fk_sgh_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
            CONSTRAINT fk_sgh_group FOREIGN KEY (group_id) REFERENCES `groups` (id) ON DELETE RESTRICT,
            CONSTRAINT fk_sgh_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT chk_sgh_dates CHECK (end_date IS NULL OR end_date >= start_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS student_group_history',
        'DROP TABLE IF EXISTS students',
        'DROP TABLE IF EXISTS `groups`',
        'ALTER TABLE departments DROP FOREIGN KEY fk_departments_head, DROP COLUMN head_teacher_id',
        'DROP TABLE IF EXISTS teachers',
    ],
];
