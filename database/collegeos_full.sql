-- CollegeOS: полный SQL (схема + справочные данные)
-- Сгенерировано из database/migrations и database/seeders, 2026-11-16
-- MySQL 8.0+ / MariaDB 10.4+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `collegeos` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `collegeos`;

-- ── migrations ──
CREATE TABLE IF NOT EXISTS migrations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration VARCHAR(255) NOT NULL,
    batch INT UNSIGNED NOT NULL,
    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_migrations_migration (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2026_11_16_000001_create_rbac_tables ──
CREATE TABLE users (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    name_ru VARCHAR(150) NOT NULL,
    name_kk VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_slug (slug),
    KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id SMALLINT UNSIGNED NOT NULL,
    permission_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    KEY idx_role_permissions_permission (permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id SMALLINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    KEY idx_user_roles_role (role_id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT,
    CONSTRAINT fk_user_roles_assigned_by FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    identifier VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    successful TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_attempts_ip (ip_address, successful, attempted_at),
    KEY idx_login_attempts_identifier (identifier, ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── 2026_11_16_000002_create_system_tables ──
CREATE TABLE settings (
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT NULL,
    `type` ENUM('string','int','bool','json') NOT NULL DEFAULT 'string',
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`),
    CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id BIGINT UNSIGNED NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_user (user_id, created_at),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_action (action, created_at),
    CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NULL,
    link VARCHAR(255) NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user (user_id, read_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── 2026_11_16_000003_create_academic_structure_tables ──
CREATE TABLE departments (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL,
    name_ru VARCHAR(255) NOT NULL,
    name_kk VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_departments_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE specialties (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE qualifications (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE academic_years (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE semesters (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── 2026_11_16_000004_create_people_and_groups_tables ──
CREATE TABLE teachers (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE departments
    ADD COLUMN head_teacher_id INT UNSIGNED NULL AFTER name_kk,
    ADD CONSTRAINT fk_departments_head FOREIGN KEY (head_teacher_id) REFERENCES teachers (id) ON DELETE SET NULL;

CREATE TABLE `groups` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE students (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_group_history (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── 2026_11_16_000005_add_password_flags_to_users ──
ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_changed_at;


-- ── 2026_11_16_000006_create_subjects_classrooms_and_history_reasons ──
CREATE TABLE subjects (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE classrooms (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE student_group_history
    ADD COLUMN end_reason ENUM('transfer','promotion','academic_leave','graduation','expulsion','transferred_out') NULL AFTER end_date,
    ADD KEY idx_sgh_open (student_id, end_date);


-- ── 2026_11_16_000007_create_calendar_and_curricula_tables ──
CREATE TABLE academic_calendar_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    academic_year_id SMALLINT UNSIGNED NOT NULL,
    type ENUM('theory','exam_session','practice','holiday','public_holiday','final_attestation','control_week','other') NOT NULL,
    title_ru VARCHAR(255) NOT NULL,
    title_kk VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    course TINYINT UNSIGNED NULL COMMENT 'NULL = all courses',
    group_id INT UNSIGNED NULL COMMENT 'NULL = all groups',
    note VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ace_year_dates (academic_year_id, start_date, end_date),
    KEY idx_ace_group (group_id),
    CONSTRAINT fk_ace_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE RESTRICT,
    CONSTRAINT fk_ace_group FOREIGN KEY (group_id) REFERENCES `groups` (id) ON DELETE CASCADE,
    CONSTRAINT fk_ace_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_ace_dates CHECK (end_date >= start_date),
    CONSTRAINT chk_ace_course CHECK (course IS NULL OR course BETWEEN 1 AND 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE curricula (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    specialty_id INT UNSIGNED NOT NULL,
    qualification_id INT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    admission_year SMALLINT UNSIGNED NOT NULL COMMENT 'Intake the plan applies to',
    study_form ENUM('full_time','part_time','evening','distance') NOT NULL DEFAULT 'full_time',
    base_education ENUM('basic_secondary','general_secondary','tvet') NOT NULL DEFAULT 'basic_secondary'
        COMMENT 'basic = after 9th grade, general = after 11th grade, tvet = after college',
    semesters_count TINYINT UNSIGNED NOT NULL DEFAULT 8,
    status ENUM('draft','approved','archived') NOT NULL DEFAULT 'draft',
    approved_at DATETIME NULL,
    approved_by BIGINT UNSIGNED NULL,
    note VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_curricula_cohort (specialty_id, qualification_id, admission_year, study_form, base_education),
    KEY idx_curricula_status (status),
    CONSTRAINT fk_curricula_specialty FOREIGN KEY (specialty_id) REFERENCES specialties (id) ON DELETE RESTRICT,
    CONSTRAINT fk_curricula_qualification FOREIGN KEY (qualification_id) REFERENCES qualifications (id) ON DELETE RESTRICT,
    CONSTRAINT fk_curricula_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_curricula_semesters CHECK (semesters_count BETWEEN 1 AND 10),
    CONSTRAINT chk_curricula_year CHECK (admission_year BETWEEN 2000 AND 2100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE curriculum_subjects (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    curriculum_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    module_code VARCHAR(30) NULL COMMENT 'Cycle / module index, e.g. ООД, БМ 01, ПМ 03',
    semester TINYINT UNSIGNED NOT NULL,
    hours_total SMALLINT UNSIGNED NOT NULL,
    hours_theory SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    hours_practice SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    hours_lab SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    credits DECIMAL(5,2) NULL COMMENT 'Only if the college uses credits; no formula is applied',
    control_form ENUM('exam','credit','diff_credit','coursework','none') NOT NULL DEFAULT 'none',
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cs_subject_semester (curriculum_id, subject_id, semester),
    KEY idx_cs_subject (subject_id),
    CONSTRAINT fk_cs_curriculum FOREIGN KEY (curriculum_id) REFERENCES curricula (id) ON DELETE CASCADE,
    CONSTRAINT fk_cs_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE RESTRICT,
    CONSTRAINT chk_cs_semester CHECK (semester BETWEEN 1 AND 10),
    CONSTRAINT chk_cs_hours CHECK (hours_theory + hours_practice + hours_lab <= hours_total),
    CONSTRAINT chk_cs_credits CHECK (credits IS NULL OR credits >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `groups`
    ADD COLUMN curriculum_id INT UNSIGNED NULL AFTER qualification_id,
    ADD CONSTRAINT fk_groups_curriculum FOREIGN KEY (curriculum_id) REFERENCES curricula (id) ON DELETE SET NULL;


-- ── 2026_11_16_000008_create_schedule_tables ──
CREATE TABLE lesson_periods (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    number TINYINT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lesson_periods_number (number),
    CONSTRAINT chk_lesson_periods_time CHECK (end_time > start_time),
    CONSTRAINT chk_lesson_periods_number CHECK (number BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE schedule_entries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    semester_id INT UNSIGNED NOT NULL,
    group_id INT UNSIGNED NOT NULL,
    subgroup TINYINT UNSIGNED NULL COMMENT 'NULL = whole group, 1/2 = subgroup',
    subject_id INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NULL,
    weekday TINYINT UNSIGNED NOT NULL COMMENT '1 = Monday … 6 = Saturday',
    lesson_period_id TINYINT UNSIGNED NOT NULL,
    week_parity ENUM('every','odd','even') NOT NULL DEFAULT 'every',
    lesson_type ENUM('lecture','practice','lab','consultation') NOT NULL DEFAULT 'lecture',
    note VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_se_slot (semester_id, weekday, lesson_period_id),
    KEY idx_se_group (group_id, semester_id),
    KEY idx_se_teacher (teacher_id, semester_id),
    KEY idx_se_classroom (classroom_id, semester_id),
    CONSTRAINT fk_se_semester FOREIGN KEY (semester_id) REFERENCES semesters (id) ON DELETE RESTRICT,
    CONSTRAINT fk_se_group FOREIGN KEY (group_id) REFERENCES `groups` (id) ON DELETE RESTRICT,
    CONSTRAINT fk_se_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE RESTRICT,
    CONSTRAINT fk_se_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_se_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms (id) ON DELETE RESTRICT,
    CONSTRAINT fk_se_period FOREIGN KEY (lesson_period_id) REFERENCES lesson_periods (id) ON DELETE RESTRICT,
    CONSTRAINT fk_se_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_se_weekday CHECK (weekday BETWEEN 1 AND 6),
    CONSTRAINT chk_se_subgroup CHECK (subgroup IS NULL OR subgroup BETWEEN 1 AND 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE schedule_changes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    schedule_entry_id INT UNSIGNED NOT NULL,
    lesson_date DATE NOT NULL COMMENT 'Date of the regular occurrence being changed',
    change_type ENUM('modify','cancel','reschedule') NOT NULL,
    new_teacher_id INT UNSIGNED NULL,
    new_classroom_id INT UNSIGNED NULL,
    new_date DATE NULL,
    new_lesson_period_id TINYINT UNSIGNED NULL,
    reason VARCHAR(255) NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sc_occurrence (schedule_entry_id, lesson_date),
    KEY idx_sc_lesson_date (lesson_date),
    KEY idx_sc_new_date (new_date),
    CONSTRAINT fk_sc_entry FOREIGN KEY (schedule_entry_id) REFERENCES schedule_entries (id) ON DELETE CASCADE,
    CONSTRAINT fk_sc_teacher FOREIGN KEY (new_teacher_id) REFERENCES teachers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_sc_classroom FOREIGN KEY (new_classroom_id) REFERENCES classrooms (id) ON DELETE RESTRICT,
    CONSTRAINT fk_sc_period FOREIGN KEY (new_lesson_period_id) REFERENCES lesson_periods (id) ON DELETE RESTRICT,
    CONSTRAINT fk_sc_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_sc_reschedule CHECK (change_type <> 'reschedule' OR (new_date IS NOT NULL AND new_lesson_period_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── 2026_11_16_000009_create_journal_tables ──
CREATE TABLE assessment_types (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(30) NOT NULL,
    name_ru VARCHAR(150) NOT NULL,
    name_kk VARCHAR(150) NOT NULL,
    short_ru VARCHAR(20) NOT NULL,
    short_kk VARCHAR(20) NOT NULL,
    source ENUM('manual','lessons_average','computed_final') NOT NULL DEFAULT 'manual',
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_assessment_types_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grading_schemes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    parent_id INT UNSIGNED NULL COMMENT 'Previous version',
    grade_min DECIMAL(6,2) NOT NULL,
    grade_max DECIMAL(6,2) NOT NULL,
    final_decimals TINYINT UNSIGNED NOT NULL DEFAULT 0,
    pass_threshold DECIMAL(6,2) NULL,
    status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    note VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_grading_schemes_status (status),
    CONSTRAINT fk_gs_parent FOREIGN KEY (parent_id) REFERENCES grading_schemes (id) ON DELETE SET NULL,
    CONSTRAINT fk_gs_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_gs_scale CHECK (grade_max > grade_min),
    CONSTRAINT chk_gs_decimals CHECK (final_decimals <= 2),
    CONSTRAINT chk_gs_threshold CHECK (pass_threshold IS NULL OR (pass_threshold >= grade_min AND pass_threshold <= grade_max))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grading_scheme_components (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    grading_scheme_id INT UNSIGNED NOT NULL,
    assessment_type_id SMALLINT UNSIGNED NOT NULL,
    weight DECIMAL(5,2) NOT NULL COMMENT 'Percent; weights of an active scheme sum to 100',
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gsc_type (grading_scheme_id, assessment_type_id),
    CONSTRAINT fk_gsc_scheme FOREIGN KEY (grading_scheme_id) REFERENCES grading_schemes (id) ON DELETE CASCADE,
    CONSTRAINT fk_gsc_type FOREIGN KEY (assessment_type_id) REFERENCES assessment_types (id) ON DELETE RESTRICT,
    CONSTRAINT chk_gsc_weight CHECK (weight > 0 AND weight <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE teacher_subject_assignments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    semester_id INT UNSIGNED NOT NULL,
    group_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED NOT NULL COMMENT 'Responsible teacher',
    grading_scheme_id INT UNSIGNED NULL,
    status ENUM('open','submitted','approved') NOT NULL DEFAULT 'open',
    submitted_at DATETIME NULL,
    approved_at DATETIME NULL,
    approved_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tsa_journal (semester_id, group_id, subject_id),
    KEY idx_tsa_teacher (teacher_id, semester_id),
    KEY idx_tsa_group (group_id, semester_id),
    CONSTRAINT fk_tsa_semester FOREIGN KEY (semester_id) REFERENCES semesters (id) ON DELETE RESTRICT,
    CONSTRAINT fk_tsa_group FOREIGN KEY (group_id) REFERENCES `groups` (id) ON DELETE RESTRICT,
    CONSTRAINT fk_tsa_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE RESTRICT,
    CONSTRAINT fk_tsa_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_tsa_scheme FOREIGN KEY (grading_scheme_id) REFERENCES grading_schemes (id) ON DELETE RESTRICT,
    CONSTRAINT fk_tsa_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE journal_lessons (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    assignment_id INT UNSIGNED NOT NULL,
    lesson_date DATE NOT NULL,
    lesson_period_id TINYINT UNSIGNED NOT NULL,
    lesson_type ENUM('lecture','practice','lab','consultation') NOT NULL DEFAULT 'lecture',
    topic VARCHAR(255) NULL,
    homework VARCHAR(500) NULL,
    note VARCHAR(500) NULL,
    hours TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'Academic hours of the lesson',
    conducted_by INT UNSIGNED NULL COMMENT 'Teacher who actually conducted it (substitutions)',
    schedule_entry_id INT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_jl_slot (assignment_id, lesson_date, lesson_period_id),
    KEY idx_jl_conducted (conducted_by, lesson_date),
    CONSTRAINT fk_jl_assignment FOREIGN KEY (assignment_id) REFERENCES teacher_subject_assignments (id) ON DELETE CASCADE,
    CONSTRAINT fk_jl_period FOREIGN KEY (lesson_period_id) REFERENCES lesson_periods (id) ON DELETE RESTRICT,
    CONSTRAINT fk_jl_teacher FOREIGN KEY (conducted_by) REFERENCES teachers (id) ON DELETE SET NULL,
    CONSTRAINT fk_jl_entry FOREIGN KEY (schedule_entry_id) REFERENCES schedule_entries (id) ON DELETE SET NULL,
    CONSTRAINT fk_jl_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_jl_hours CHECK (hours BETWEEN 1 AND 8)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE journal_grades (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    journal_lesson_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    value DECIMAL(6,2) NULL,
    comment VARCHAR(255) NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_jg_cell (journal_lesson_id, student_id),
    KEY idx_jg_student (student_id),
    CONSTRAINT fk_jg_lesson FOREIGN KEY (journal_lesson_id) REFERENCES journal_lessons (id) ON DELETE CASCADE,
    CONSTRAINT fk_jg_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
    CONSTRAINT fk_jg_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    journal_lesson_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    status ENUM('absent','excused') NOT NULL COMMENT 'Only absences are stored; no row = present',
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_attendance_cell (journal_lesson_id, student_id),
    KEY idx_attendance_student (student_id),
    CONSTRAINT fk_att_lesson FOREIGN KEY (journal_lesson_id) REFERENCES journal_lessons (id) ON DELETE CASCADE,
    CONSTRAINT fk_att_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
    CONSTRAINT fk_att_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_assessments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    assignment_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    assessment_type_id SMALLINT UNSIGNED NOT NULL,
    value DECIMAL(6,2) NULL,
    grading_scheme_id INT UNSIGNED NULL COMMENT 'Scheme version used for an official final',
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sa_cell (assignment_id, student_id, assessment_type_id),
    KEY idx_sa_student (student_id),
    CONSTRAINT fk_sa_assignment FOREIGN KEY (assignment_id) REFERENCES teacher_subject_assignments (id) ON DELETE CASCADE,
    CONSTRAINT fk_sa_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
    CONSTRAINT fk_sa_type FOREIGN KEY (assessment_type_id) REFERENCES assessment_types (id) ON DELETE RESTRICT,
    CONSTRAINT fk_sa_scheme FOREIGN KEY (grading_scheme_id) REFERENCES grading_schemes (id) ON DELETE RESTRICT,
    CONSTRAINT fk_sa_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grade_change_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    assignment_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NULL,
    journal_lesson_id INT UNSIGNED NULL,
    assessment_type_id SMALLINT UNSIGNED NULL,
    field ENUM('grade','attendance','assessment','comment','final','status') NOT NULL,
    old_value VARCHAR(255) NULL,
    new_value VARCHAR(255) NULL,
    changed_by BIGINT UNSIGNED NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_gch_journal (assignment_id, changed_at),
    KEY idx_gch_student (student_id, changed_at),
    CONSTRAINT fk_gch_assignment FOREIGN KEY (assignment_id) REFERENCES teacher_subject_assignments (id) ON DELETE CASCADE,
    CONSTRAINT fk_gch_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
    CONSTRAINT fk_gch_lesson FOREIGN KEY (journal_lesson_id) REFERENCES journal_lessons (id) ON DELETE SET NULL,
    CONSTRAINT fk_gch_type FOREIGN KEY (assessment_type_id) REFERENCES assessment_types (id) ON DELETE SET NULL,
    CONSTRAINT fk_gch_changed_by FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO migrations (migration, batch) VALUES
  ('2026_11_16_000001_create_rbac_tables', 1),
  ('2026_11_16_000002_create_system_tables', 1),
  ('2026_11_16_000003_create_academic_structure_tables', 1),
  ('2026_11_16_000004_create_people_and_groups_tables', 1),
  ('2026_11_16_000005_add_password_flags_to_users', 1),
  ('2026_11_16_000006_create_subjects_classrooms_and_history_reasons', 1),
  ('2026_11_16_000007_create_calendar_and_curricula_tables', 1),
  ('2026_11_16_000008_create_schedule_tables', 1),
  ('2026_11_16_000009_create_journal_tables', 1);

-- ════════ Справочные данные (как php cli.php db:seed) ════════

-- permissions
INSERT INTO permissions (slug, module, name_ru, name_kk) VALUES
  ('dashboard.view', 'system', 'Просмотр главной страницы', 'Басты бетті қарау'),
  ('users.view', 'system', 'Просмотр пользователей', 'Пайдаланушыларды қарау'),
  ('users.manage', 'system', 'Управление пользователями', 'Пайдаланушыларды басқару'),
  ('roles.manage', 'system', 'Управление ролями и правами', 'Рөлдер мен құқықтарды басқару'),
  ('settings.manage', 'system', 'Настройки системы', 'Жүйе баптаулары'),
  ('audit.view', 'system', 'Журнал аудита', 'Аудит журналы'),
  ('dictionaries.manage', 'system', 'Управление справочниками', 'Анықтамалықтарды басқару'),
  ('students.view', 'people', 'Просмотр студентов', 'Студенттерді қарау'),
  ('students.manage', 'people', 'Управление студентами', 'Студенттерді басқару'),
  ('teachers.view', 'people', 'Просмотр преподавателей', 'Оқытушыларды қарау'),
  ('teachers.manage', 'people', 'Управление преподавателями', 'Оқытушыларды басқару'),
  ('groups.view', 'academic', 'Просмотр групп', 'Топтарды қарау'),
  ('groups.manage', 'academic', 'Управление группами', 'Топтарды басқару'),
  ('academic_years.manage', 'academic', 'Учебные годы и семестры', 'Оқу жылдары мен семестрлер'),
  ('calendar.view', 'academic', 'Просмотр академического календаря', 'Академиялық күнтізбені қарау'),
  ('calendar.manage', 'academic', 'Управление академическим календарём', 'Академиялық күнтізбені басқару'),
  ('curricula.view', 'academic', 'Просмотр учебных планов', 'Оқу жоспарларын қарау'),
  ('curricula.manage', 'academic', 'Управление учебными планами', 'Оқу жоспарларын басқару'),
  ('schedule.view', 'schedule', 'Просмотр расписания', 'Сабақ кестесін қарау'),
  ('schedule.manage', 'schedule', 'Управление расписанием', 'Сабақ кестесін басқару'),
  ('journal.view', 'journal', 'Просмотр электронного журнала', 'Электрондық журналды қарау'),
  ('journal.manage', 'journal', 'Ведение электронного журнала', 'Электрондық журналды жүргізу'),
  ('journal.approve', 'journal', 'Утверждение журналов', 'Журналдарды бекіту'),
  ('grading.manage', 'journal', 'Настройка схем оценивания', 'Бағалау схемаларын баптау'),
  ('exams.view', 'exams', 'Просмотр экзаменов', 'Емтихандарды қарау'),
  ('exams.manage', 'exams', 'Управление экзаменами', 'Емтихандарды басқару'),
  ('transcripts.view', 'exams', 'Просмотр транскриптов', 'Транскрипттерді қарау'),
  ('transcripts.issue', 'exams', 'Выдача транскриптов', 'Транскрипттерді беру'),
  ('workload.view', 'workload', 'Просмотр учебной нагрузки', 'Оқу жүктемесін қарау'),
  ('workload.manage', 'workload', 'Планирование учебной нагрузки', 'Оқу жүктемесін жоспарлау'),
  ('documents.request', 'documents', 'Подача заявлений', 'Өтініш беру'),
  ('documents.process', 'documents', 'Обработка заявлений', 'Өтініштерді өңдеу'),
  ('surveys.respond', 'surveys', 'Прохождение анкет', 'Сауалнамаға қатысу'),
  ('surveys.manage', 'surveys', 'Конструктор анкет', 'Сауалнама құрастырушы'),
  ('surveys.results', 'surveys', 'Результаты анкетирования', 'Сауалнама нәтижелері'),
  ('reports.view', 'reports', 'Отчёты', 'Есептер');

-- roles
INSERT INTO roles (slug, name_ru, name_kk, is_system) VALUES
  ('super_admin', 'Супер-администратор', 'Супер-әкімші', 1),
  ('admin', 'Администратор колледжа', 'Колледж әкімшісі', 1),
  ('deputy_director', 'Заместитель директора по УР', 'Директордың оқу ісі жөніндегі орынбасары', 1),
  ('department_head', 'Заведующий отделением', 'Бөлім меңгерушісі', 1),
  ('academic_office', 'Учебная часть', 'Оқу бөлімі', 1),
  ('teacher', 'Преподаватель', 'Оқытушы', 1),
  ('curator', 'Куратор', 'Куратор', 1),
  ('student', 'Студент', 'Студент', 1);

-- role_permissions
INSERT INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('dashboard.view', 'users.view', 'users.manage', 'roles.manage', 'settings.manage', 'audit.view', 'dictionaries.manage', 'students.view', 'students.manage', 'teachers.view', 'teachers.manage', 'groups.view', 'groups.manage', 'academic_years.manage', 'calendar.view', 'curricula.view', 'schedule.view', 'journal.view', 'exams.view', 'transcripts.view', 'workload.view', 'documents.process', 'surveys.manage', 'surveys.results', 'reports.view') WHERE r.slug = 'admin';
INSERT INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('dashboard.view', 'audit.view', 'students.view', 'teachers.view', 'groups.view', 'academic_years.manage', 'calendar.view', 'calendar.manage', 'curricula.view', 'curricula.manage', 'schedule.view', 'journal.view', 'journal.approve', 'grading.manage', 'exams.view', 'exams.manage', 'transcripts.view', 'transcripts.issue', 'workload.view', 'workload.manage', 'documents.process', 'surveys.manage', 'surveys.results', 'reports.view') WHERE r.slug = 'deputy_director';
INSERT INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('dashboard.view', 'students.view', 'students.manage', 'teachers.view', 'groups.view', 'groups.manage', 'calendar.view', 'curricula.view', 'schedule.view', 'journal.view', 'journal.approve', 'exams.view', 'transcripts.view', 'workload.view', 'documents.process', 'surveys.results', 'reports.view') WHERE r.slug = 'department_head';
INSERT INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('dashboard.view', 'students.view', 'teachers.view', 'groups.view', 'academic_years.manage', 'calendar.view', 'calendar.manage', 'curricula.view', 'curricula.manage', 'schedule.view', 'schedule.manage', 'journal.view', 'exams.view', 'exams.manage', 'transcripts.view', 'transcripts.issue', 'workload.view', 'workload.manage', 'documents.process', 'reports.view') WHERE r.slug = 'academic_office';
INSERT INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('dashboard.view', 'calendar.view', 'curricula.view', 'schedule.view', 'journal.view', 'journal.manage', 'exams.view', 'workload.view', 'surveys.respond', 'reports.view') WHERE r.slug = 'teacher';
INSERT INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('dashboard.view', 'students.view', 'groups.view', 'calendar.view', 'schedule.view', 'journal.view', 'exams.view', 'surveys.respond') WHERE r.slug = 'curator';
INSERT INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('dashboard.view', 'calendar.view', 'schedule.view', 'journal.view', 'exams.view', 'transcripts.view', 'documents.request', 'surveys.respond') WHERE r.slug = 'student';

-- settings
INSERT IGNORE INTO settings (`key`, `value`, `type`) VALUES
  ('college_name_ru', 'Колледж', 'string'),
  ('college_name_kk', 'Колледж', 'string'),
  ('default_locale', 'ru', 'string'),
  ('journal_lock_after_approval', '1', 'bool');

-- academic_years + semesters
INSERT INTO academic_years (title, start_date, end_date, status) VALUES ('2025-2026', '2025-09-01', '2026-06-30', 'archived');
INSERT INTO semesters (academic_year_id, number, start_date, end_date) VALUES
  (LAST_INSERT_ID(), 1, '2025-09-01', '2025-12-31');
INSERT INTO semesters (academic_year_id, number, start_date, end_date)
  SELECT id, 2, '2026-01-12', '2026-06-30' FROM academic_years WHERE title = '2025-2026';
INSERT INTO academic_years (title, start_date, end_date, status) VALUES ('2026-2027', '2026-09-01', '2027-06-30', 'active');
INSERT INTO semesters (academic_year_id, number, start_date, end_date) VALUES
  (LAST_INSERT_ID(), 1, '2026-09-01', '2026-12-31');
INSERT INTO semesters (academic_year_id, number, start_date, end_date)
  SELECT id, 2, '2027-01-12', '2027-06-30' FROM academic_years WHERE title = '2026-2027';
INSERT INTO academic_years (title, start_date, end_date, status) VALUES ('2027-2028', '2027-09-01', '2028-06-30', 'planned');
INSERT INTO semesters (academic_year_id, number, start_date, end_date) VALUES
  (LAST_INSERT_ID(), 1, '2027-09-01', '2027-12-31');
INSERT INTO semesters (academic_year_id, number, start_date, end_date)
  SELECT id, 2, '2028-01-12', '2028-06-30' FROM academic_years WHERE title = '2027-2028';
INSERT INTO academic_years (title, start_date, end_date, status) VALUES ('2028-2029', '2028-09-01', '2029-06-30', 'planned');
INSERT INTO semesters (academic_year_id, number, start_date, end_date) VALUES
  (LAST_INSERT_ID(), 1, '2028-09-01', '2028-12-31');
INSERT INTO semesters (academic_year_id, number, start_date, end_date)
  SELECT id, 2, '2029-01-12', '2029-06-30' FROM academic_years WHERE title = '2028-2029';

-- lesson_periods
INSERT INTO lesson_periods (number, start_time, end_time) VALUES
  (1, '08:30', '10:00'),
  (2, '10:10', '11:40'),
  (3, '12:10', '13:40'),
  (4, '13:50', '15:20'),
  (5, '15:30', '17:00'),
  (6, '17:10', '18:40');

-- assessment_types
INSERT IGNORE INTO assessment_types (code, name_ru, name_kk, short_ru, short_kk, source, is_system, sort_order) VALUES
  ('current', 'Текущая успеваемость', 'Ағымдағы үлгерім', 'Тек.', 'Ағым.', 'lessons_average', 1, 10),
  ('rating1', 'Рейтинг 1', '1-рейтинг', 'Р1', 'Р1', 'manual', 1, 20),
  ('rating2', 'Рейтинг 2', '2-рейтинг', 'Р2', 'Р2', 'manual', 1, 30),
  ('exam', 'Экзамен', 'Емтихан', 'Экз.', 'Емт.', 'manual', 1, 40),
  ('final', 'Итоговая оценка', 'Қорытынды баға', 'Итог', 'Қор.', 'computed_final', 1, 90);

-- Super admin. Логин: admin   Временный пароль: NKGz5Rjda2krXJ3X   (смена при первом входе)
INSERT INTO users (login, email, password_hash, last_name, first_name, locale, password_changed_at, must_change_password)
  VALUES ('admin', NULL, '$2y$10$MNc6qsgsx0GnsmL/CwOTgeC5KqM6fdMJlQ.vdNjvF05U9MZ5hyXEe', 'Системный', 'Администратор', 'ru', NOW(), 1);
INSERT INTO user_roles (user_id, role_id) SELECT LAST_INSERT_ID(), id FROM roles WHERE slug = 'super_admin';

SET FOREIGN_KEY_CHECKS = 1;
