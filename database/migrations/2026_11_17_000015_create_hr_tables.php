<?php

declare(strict_types=1);

/**
 * Human resources (отдел кадров).
 *
 * employees    — personnel file of every staff member (teaching and non-teaching). Names and contacts
 *                stay in users (a staff member without system access has an inactive user with no roles).
 *                For a teacher, position/department/hire date/number/status are copied to the teacher
 *                profile whenever a personnel order is signed, so timetables and journals stay current.
 * hr_orders    — personnel orders (hire, transfer, rate change, dismissal, other): numbered, signed
 *                orders change the personnel file; the list is the employee's history.
 * hr_leaves    — leave, sick leave and business trips: request → approved/rejected/cancelled.
 *                Approved absences are shown to the timetable staff for substitutions.
 * hr_trainings — professional development courses (hours, certificate).
 * Qualification category and its expiry are kept on the employee; reminders come from those dates.
 */
return [
    'up' => [
        "CREATE TABLE employees (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            personnel_number VARCHAR(30) NOT NULL,
            iin CHAR(12) NULL,
            birth_date DATE NULL,
            gender ENUM('male','female') NULL,
            address VARCHAR(255) NULL,
            education VARCHAR(500) NULL COMMENT 'Institution, speciality, year',
            department_id SMALLINT UNSIGNED NULL,
            position VARCHAR(150) NULL,
            staff_category ENUM('teaching','administrative','support') NOT NULL DEFAULT 'teaching',
            employment_type ENUM('main','internal_part_time','external_part_time') NOT NULL DEFAULT 'main',
            rate DECIMAL(4,2) NOT NULL DEFAULT 1.00,
            hire_date DATE NULL,
            dismissal_date DATE NULL,
            prior_experience_months SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Work experience before this college',
            annual_leave_days TINYINT UNSIGNED NULL COMMENT 'NULL = default from settings',
            qualification_category VARCHAR(30) NULL,
            category_assigned_on DATE NULL,
            category_valid_until DATE NULL,
            status ENUM('active','on_leave','dismissed') NOT NULL DEFAULT 'active',
            note VARCHAR(500) NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_employees_user (user_id),
            UNIQUE KEY uq_employees_number (personnel_number),
            UNIQUE KEY uq_employees_iin (iin),
            KEY idx_employees_department (department_id, status),
            KEY idx_employees_category (category_valid_until),
            KEY fk_employees_created_by (created_by),
            CONSTRAINT fk_employees_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
            CONSTRAINT fk_employees_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
            CONSTRAINT fk_employees_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT chk_employees_rate CHECK (rate > 0 AND rate <= 2),
            CONSTRAINT chk_employees_leave CHECK (annual_leave_days IS NULL OR annual_leave_days BETWEEN 1 AND 90)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE hr_orders (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            number VARCHAR(30) NULL COMMENT 'Given on signing',
            employee_id INT UNSIGNED NOT NULL,
            type ENUM('hire','transfer','rate_change','dismissal','other') NOT NULL,
            order_date DATE NOT NULL,
            effective_date DATE NOT NULL,
            details JSON NULL COMMENT 'New position, department, rate, reason…',
            basis VARCHAR(500) NULL,
            status ENUM('draft','signed','cancelled') NOT NULL DEFAULT 'draft',
            signed_at DATETIME NULL,
            signed_by BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_hr_orders_number (number),
            KEY idx_hr_orders_employee (employee_id, effective_date),
            KEY idx_hr_orders_status (status, order_date),
            KEY fk_hr_orders_signed_by (signed_by),
            KEY fk_hr_orders_created_by (created_by),
            CONSTRAINT fk_hr_orders_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE RESTRICT,
            CONSTRAINT fk_hr_orders_signed_by FOREIGN KEY (signed_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT fk_hr_orders_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE hr_leaves (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            employee_id INT UNSIGNED NOT NULL,
            type ENUM('annual','unpaid','study','maternity','sick','business_trip','other') NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            days SMALLINT UNSIGNED NOT NULL COMMENT 'Calendar days',
            status ENUM('requested','approved','rejected','cancelled') NOT NULL DEFAULT 'requested',
            reason VARCHAR(500) NULL,
            document_number VARCHAR(50) NULL COMMENT 'Sick-leave sheet, trip order…',
            destination VARCHAR(255) NULL COMMENT 'Business trip',
            decision_comment VARCHAR(500) NULL,
            requested_by BIGINT UNSIGNED NULL,
            decided_by BIGINT UNSIGNED NULL,
            decided_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_hr_leaves_employee (employee_id, start_date),
            KEY idx_hr_leaves_period (status, start_date, end_date),
            KEY fk_hr_leaves_requested_by (requested_by),
            KEY fk_hr_leaves_decided_by (decided_by),
            CONSTRAINT fk_hr_leaves_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE,
            CONSTRAINT fk_hr_leaves_requested_by FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT fk_hr_leaves_decided_by FOREIGN KEY (decided_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT chk_hr_leaves_period CHECK (end_date >= start_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE hr_trainings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            employee_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            provider VARCHAR(255) NULL,
            hours SMALLINT UNSIGNED NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            certificate_number VARCHAR(100) NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_hr_trainings_employee (employee_id, end_date),
            KEY fk_hr_trainings_created_by (created_by),
            CONSTRAINT fk_hr_trainings_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE,
            CONSTRAINT fk_hr_trainings_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT chk_hr_trainings CHECK (end_date >= start_date AND hours BETWEEN 1 AND 2000)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS hr_trainings',
        'DROP TABLE IF EXISTS hr_leaves',
        'DROP TABLE IF EXISTS hr_orders',
        'DROP TABLE IF EXISTS employees',
    ],
];
