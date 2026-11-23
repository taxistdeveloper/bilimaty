<?php

declare(strict_types=1);

/**
 * Requests and certificates (заявления и справки).
 *
 * document_types           — configurable kinds of requests. generator defines what approval produces:
 *                            study_certificate (a numbered certificate with a QR check), transcript
 *                            (an official transcript), personal_data (approved changes are applied
 *                            to the student's profile) or none (handled manually).
 * document_requests        — a student's request: registration number given on submit, status
 *                            draft → submitted → in_review ⇄ needs_info → approved/rejected → completed.
 * document_request_history — every status change and comment (internal notes are hidden from students).
 * generated_documents      — issued certificates: immutable snapshot + verification code for /verify.
 */
return [
    'up' => [
        "CREATE TABLE document_types (
            id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(30) NOT NULL,
            name_ru VARCHAR(150) NOT NULL,
            name_kk VARCHAR(150) NOT NULL,
            description_ru VARCHAR(500) NULL,
            description_kk VARCHAR(500) NULL,
            generator ENUM('study_certificate','transcript','personal_data','none') NOT NULL DEFAULT 'none',
            needs_purpose TINYINT(1) NOT NULL DEFAULT 0,
            allow_attachment TINYINT(1) NOT NULL DEFAULT 0,
            processing_days TINYINT UNSIGNED NULL COMMENT 'Review term in calendar days',
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_dt_code (code),
            CONSTRAINT chk_dt_days CHECK (processing_days IS NULL OR processing_days BETWEEN 1 AND 60)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "INSERT INTO document_types (code, name_ru, name_kk, description_ru, description_kk, generator, needs_purpose, allow_attachment, processing_days, sort_order) VALUES
            ('study_certificate', 'Справка с места учёбы', 'Оқу орнынан анықтама', 'Подтверждает, что вы обучаетесь в колледже.', 'Колледжде оқитыныңызды растайды.', 'study_certificate', 1, 0, 3, 1),
            ('transcript', 'Запрос транскрипта', 'Транскрипт сұрау', 'Официальный транскрипт с утверждёнными результатами.', 'Бекітілген нәтижелері бар ресми транскрипт.', 'transcript', 1, 0, 5, 2),
            ('personal_data', 'Изменение персональных данных', 'Жеке деректерді өзгерту', 'Укажите новые данные и приложите подтверждающий документ.', 'Жаңа деректерді көрсетіп, растайтын құжатты тіркеңіз.', 'personal_data', 0, 1, 5, 3),
            ('other', 'Другое заявление', 'Басқа өтініш', 'Опишите вашу просьбу.', 'Өтінішіңізді сипаттаңыз.', 'none', 0, 1, 10, 9)",

        "CREATE TABLE document_requests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            number VARCHAR(30) NULL COMMENT 'Registration number, given on first submit',
            type_id SMALLINT UNSIGNED NOT NULL,
            student_id INT UNSIGNED NOT NULL,
            status ENUM('draft','submitted','in_review','needs_info','approved','rejected','completed') NOT NULL DEFAULT 'draft',
            purpose VARCHAR(255) NULL COMMENT 'Where the document is to be presented',
            copies TINYINT UNSIGNED NOT NULL DEFAULT 1,
            details TEXT NULL,
            payload JSON NULL COMMENT 'Requested personal data changes: field => new value',
            attachment_path VARCHAR(100) NULL COMMENT 'Random file name inside storage/private/documents',
            attachment_name VARCHAR(255) NULL,
            attachment_mime VARCHAR(100) NULL,
            attachment_size INT UNSIGNED NULL,
            assigned_to BIGINT UNSIGNED NULL,
            transcript_id INT UNSIGNED NULL,
            submitted_at DATETIME NULL,
            due_date DATE NULL,
            decided_at DATETIME NULL,
            decided_by BIGINT UNSIGNED NULL,
            decision_comment VARCHAR(1000) NULL,
            completed_at DATETIME NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_dr_number (number),
            KEY idx_dr_student (student_id, status),
            KEY idx_dr_status (status, type_id, submitted_at),
            KEY idx_dr_assigned (assigned_to, status),
            KEY fk_dr_type (type_id),
            KEY fk_dr_transcript (transcript_id),
            KEY fk_dr_decided_by (decided_by),
            KEY fk_dr_created_by (created_by),
            CONSTRAINT fk_dr_type FOREIGN KEY (type_id) REFERENCES document_types (id) ON DELETE RESTRICT,
            CONSTRAINT fk_dr_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE RESTRICT,
            CONSTRAINT fk_dr_assigned FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT fk_dr_transcript FOREIGN KEY (transcript_id) REFERENCES transcripts (id) ON DELETE SET NULL,
            CONSTRAINT fk_dr_decided_by FOREIGN KEY (decided_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT fk_dr_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT chk_dr_copies CHECK (copies BETWEEN 1 AND 5)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE document_request_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id INT UNSIGNED NOT NULL,
            kind ENUM('status','comment') NOT NULL,
            from_status VARCHAR(20) NULL,
            to_status VARCHAR(20) NULL,
            comment TEXT NULL,
            is_internal TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Staff note, hidden from the student',
            user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_drh_request (request_id, id),
            KEY fk_drh_user (user_id),
            CONSTRAINT fk_drh_request FOREIGN KEY (request_id) REFERENCES document_requests (id) ON DELETE CASCADE,
            CONSTRAINT fk_drh_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE generated_documents (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id INT UNSIGNED NULL,
            student_id INT UNSIGNED NOT NULL,
            kind ENUM('study_certificate') NOT NULL,
            number VARCHAR(30) NOT NULL,
            snapshot JSON NOT NULL,
            fingerprint CHAR(64) NOT NULL COMMENT 'SHA-256 of the snapshot',
            verification_code CHAR(32) NOT NULL,
            status ENUM('valid','revoked') NOT NULL DEFAULT 'valid',
            issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            issued_by BIGINT UNSIGNED NULL,
            revoked_at DATETIME NULL,
            revoked_by BIGINT UNSIGNED NULL,
            revoke_reason VARCHAR(255) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_gd_number (number),
            UNIQUE KEY uq_gd_code (verification_code),
            UNIQUE KEY uq_gd_request (request_id),
            KEY idx_gd_student (student_id, issued_at),
            KEY fk_gd_issued_by (issued_by),
            KEY fk_gd_revoked_by (revoked_by),
            CONSTRAINT fk_gd_request FOREIGN KEY (request_id) REFERENCES document_requests (id) ON DELETE RESTRICT,
            CONSTRAINT fk_gd_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE RESTRICT,
            CONSTRAINT fk_gd_issued_by FOREIGN KEY (issued_by) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT fk_gd_revoked_by FOREIGN KEY (revoked_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS generated_documents',
        'DROP TABLE IF EXISTS document_request_history',
        'DROP TABLE IF EXISTS document_requests',
        'DROP TABLE IF EXISTS document_types',
    ],
];
