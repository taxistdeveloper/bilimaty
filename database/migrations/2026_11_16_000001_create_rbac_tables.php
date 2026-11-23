<?php

declare(strict_types=1);

/**
 * Users and RBAC: users, roles, permissions, role_permissions, user_roles, login_attempts.
 */
return [
    'up' => [
        "CREATE TABLE users (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            login VARCHAR(64) NOT NULL,
            email VARCHAR(190) NULL,
            password_hash VARCHAR(255) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            middle_name VARCHAR(100) NULL,
            phone VARCHAR(20) NULL,
            locale ENUM('kk','ru') NOT NULL DEFAULT 'ru',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_login_at DATETIME NULL,
            last_login_ip VARCHAR(45) NULL,
            password_changed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_users_login (login),
            UNIQUE KEY uq_users_email (email),
            KEY idx_users_name (last_name, first_name),
            KEY idx_users_active (is_active, deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE roles (
            id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(50) NOT NULL,
            name_ru VARCHAR(150) NOT NULL,
            name_kk VARCHAR(150) NOT NULL,
            description VARCHAR(255) NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'System roles cannot be deleted',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_roles_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE permissions (
            id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(100) NOT NULL,
            module VARCHAR(50) NOT NULL,
            name_ru VARCHAR(150) NOT NULL,
            name_kk VARCHAR(150) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_permissions_slug (slug),
            KEY idx_permissions_module (module)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE role_permissions (
            role_id SMALLINT UNSIGNED NOT NULL,
            permission_id SMALLINT UNSIGNED NOT NULL,
            PRIMARY KEY (role_id, permission_id),
            KEY idx_role_permissions_permission (permission_id),
            CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
            CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE user_roles (
            user_id BIGINT UNSIGNED NOT NULL,
            role_id SMALLINT UNSIGNED NOT NULL,
            assigned_by BIGINT UNSIGNED NULL,
            assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, role_id),
            KEY idx_user_roles_role (role_id),
            CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT,
            CONSTRAINT fk_user_roles_assigned_by FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL
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
        'DROP TABLE IF EXISTS user_roles',
        'DROP TABLE IF EXISTS role_permissions',
        'DROP TABLE IF EXISTS permissions',
        'DROP TABLE IF EXISTS roles',
        'DROP TABLE IF EXISTS users',
    ],
];
