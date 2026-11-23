<?php

declare(strict_types=1);

/**
 * Platform database (owner of the installation): colleges, their subscription, invoices,
 * owner accounts, owner settings (requisites) and the owner's audit trail.
 * Each college keeps its own data in its own database (colleges.db_name).
 */
return [
    'up' => [
        "CREATE TABLE platform_admins (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            login VARCHAR(64) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            name VARCHAR(150) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            must_change_password TINYINT(1) NOT NULL DEFAULT 1,
            last_login_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_platform_admins_login (login)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE colleges (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(30) NOT NULL COMMENT 'Address: <site>/<code>',
            name VARCHAR(255) NOT NULL,
            db_name VARCHAR(64) NOT NULL,
            city VARCHAR(100) NULL,
            bin CHAR(12) NULL,
            contact_name VARCHAR(150) NULL,
            contact_phone VARCHAR(20) NULL,
            contact_email VARCHAR(190) NULL,
            admin_login VARCHAR(64) NULL COMMENT 'First administrator inside the college',
            monthly_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            paid_until DATE NULL,
            grace_days TINYINT UNSIGNED NOT NULL DEFAULT 7,
            status ENUM('active','suspended') NOT NULL DEFAULT 'active',
            note VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_colleges_code (code),
            UNIQUE KEY uq_colleges_db (db_name),
            KEY idx_colleges_paid (status, paid_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE college_invoices (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            college_id INT UNSIGNED NOT NULL,
            number VARCHAR(30) NOT NULL,
            issued_on DATE NOT NULL,
            months TINYINT UNSIGNED NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            status ENUM('issued','paid','cancelled') NOT NULL DEFAULT 'issued',
            paid_on DATE NULL,
            method ENUM('bank','kaspi','cash','other') NULL,
            period_from DATE NULL COMMENT 'Set when paid',
            period_to DATE NULL,
            note VARCHAR(500) NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_college_invoices_number (number),
            KEY idx_college_invoices_college (college_id, issued_on),
            KEY idx_college_invoices_paid (status, paid_on),
            KEY fk_college_invoices_created_by (created_by),
            CONSTRAINT fk_college_invoices_college FOREIGN KEY (college_id) REFERENCES colleges (id) ON DELETE RESTRICT,
            CONSTRAINT fk_college_invoices_created_by FOREIGN KEY (created_by) REFERENCES platform_admins (id) ON DELETE SET NULL,
            CONSTRAINT chk_college_invoices CHECK (amount >= 0 AND months BETWEEN 1 AND 36)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE platform_audit (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_id INT UNSIGNED NULL,
            action VARCHAR(100) NOT NULL,
            college_id INT UNSIGNED NULL,
            details JSON NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_platform_audit_college (college_id, created_at),
            KEY fk_platform_audit_admin (admin_id),
            CONSTRAINT fk_platform_audit_admin FOREIGN KEY (admin_id) REFERENCES platform_admins (id) ON DELETE SET NULL,
            CONSTRAINT fk_platform_audit_college FOREIGN KEY (college_id) REFERENCES colleges (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Same structure as in a college database: Settings and LoginThrottle work unchanged.
        "CREATE TABLE settings (
            `key` VARCHAR(100) NOT NULL,
            `value` TEXT NULL,
            `type` ENUM('string','int','bool','json') NOT NULL DEFAULT 'string',
            updated_by BIGINT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE login_attempts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            identifier VARCHAR(190) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            successful TINYINT(1) NOT NULL DEFAULT 0,
            attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_login_attempts_ip (ip_address, successful, attempted_at),
            KEY idx_login_attempts_identifier (identifier, ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS login_attempts',
        'DROP TABLE IF EXISTS settings',
        'DROP TABLE IF EXISTS platform_audit',
        'DROP TABLE IF EXISTS college_invoices',
        'DROP TABLE IF EXISTS colleges',
        'DROP TABLE IF EXISTS platform_admins',
    ],
];
