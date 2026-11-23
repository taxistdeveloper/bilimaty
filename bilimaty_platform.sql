-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Хост: localhost:8889
-- Время создания: Ноя 18 2026 г., 16:34
-- Версия сервера: 8.0.44
-- Версия PHP: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `bilimaty_platform`
--

-- --------------------------------------------------------

--
-- Структура таблицы `announcements`
--

CREATE TABLE `announcements` (
  `id` int UNSIGNED NOT NULL,
  `college_id` int UNSIGNED DEFAULT NULL COMMENT 'NULL = every college',
  `audience` enum('all','admins') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admins',
  `level` enum('info','warning') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` varchar(2000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `starts_on` date NOT NULL,
  `ends_on` date DEFAULT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `colleges`
--

CREATE TABLE `colleges` (
  `id` int UNSIGNED NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Address: <site>/<code>',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `db_name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bin` char(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_login` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'First administrator inside the college',
  `plan_id` int UNSIGNED DEFAULT NULL,
  `monthly_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `paid_until` date DEFAULT NULL,
  `grace_days` tinyint UNSIGNED NOT NULL DEFAULT '7',
  `status` enum('active','suspended','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `archived_at` datetime DEFAULT NULL,
  `db_deleted_at` datetime DEFAULT NULL,
  `note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `colleges`
--

INSERT INTO `colleges` (`id`, `code`, `name`, `db_name`, `city`, `bin`, `contact_name`, `contact_phone`, `contact_email`, `admin_login`, `plan_id`, `monthly_price`, `paid_until`, `grace_days`, `status`, `archived_at`, `db_deleted_at`, `note`, `created_at`, `updated_at`) VALUES
(1, 'college', 'Колледж', 'collegeos', NULL, NULL, NULL, NULL, NULL, 'admin', NULL, 0.00, '2027-11-17', 7, 'active', NULL, NULL, NULL, '2026-11-17 12:35:53', '2026-11-17 12:35:53');

-- --------------------------------------------------------

--
-- Структура таблицы `college_invoices`
--

CREATE TABLE `college_invoices` (
  `id` int UNSIGNED NOT NULL,
  `college_id` int UNSIGNED NOT NULL,
  `number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issued_on` date NOT NULL,
  `months` tinyint UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('issued','paid','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'issued',
  `paid_on` date DEFAULT NULL,
  `method` enum('bank','kaspi','cash','other') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `period_from` date DEFAULT NULL COMMENT 'Set when paid',
  `period_to` date DEFAULT NULL,
  `note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Структура таблицы `college_login_tokens`
--

CREATE TABLE `college_login_tokens` (
  `id` int UNSIGNED NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `college_id` int UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL COMMENT 'User in the college database',
  `admin_id` int UNSIGNED NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` bigint UNSIGNED NOT NULL,
  `identifier` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `successful` tinyint(1) NOT NULL DEFAULT '0',
  `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int UNSIGNED NOT NULL,
  `executed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`, `executed_at`) VALUES
(1, '2026_11_18_000001_create_platform_tables', 1, '2026-11-17 12:35:46'),
(2, '2026_11_18_000002_create_plans_announcements_tokens', 2, '2026-11-17 15:26:39');

-- --------------------------------------------------------

--
-- Структура таблицы `plans`
--

CREATE TABLE `plans` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `max_students` int UNSIGNED DEFAULT NULL COMMENT 'NULL = unlimited',
  `monthly_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `platform_admins`
--

CREATE TABLE `platform_admins` (
  `id` int UNSIGNED NOT NULL,
  `login` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `must_change_password` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `platform_admins`
--

INSERT INTO `platform_admins` (`id`, `login`, `password_hash`, `name`, `is_active`, `must_change_password`, `last_login_at`, `created_at`) VALUES
(1, 'owner', '$2y$10$khO3pSuY7e9VwaqAMyDy/eGci4.dwoqriWxPJJ.TL0/e4ZpPXlgM6', 'Владелец', 1, 1, NULL, '2026-11-17 12:35:46');

-- --------------------------------------------------------

--
-- Структура таблицы `platform_audit`
--

CREATE TABLE `platform_audit` (
  `id` bigint UNSIGNED NOT NULL,
  `admin_id` int UNSIGNED DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `college_id` int UNSIGNED DEFAULT NULL,
  `details` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `platform_audit`
--

INSERT INTO `platform_audit` (`id`, `admin_id`, `action`, `college_id`, `details`, `ip_address`, `created_at`) VALUES
(1, NULL, 'college.created', 1, '{\"db\": \"collegeos\", \"code\": \"college\", \"existing\": true}', NULL, '2026-11-17 12:35:53'),
(2, NULL, 'college.updated', 1, '{\"paid_until\": \"own college: one year\"}', NULL, '2026-11-17 12:35:53');

-- --------------------------------------------------------

--
-- Структура таблицы `settings`
--

CREATE TABLE `settings` (
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `type` enum('string','int','bool','json') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_announcements_period` (`starts_on`,`ends_on`),
  ADD KEY `fk_announcements_college` (`college_id`),
  ADD KEY `fk_announcements_created_by` (`created_by`);

--
-- Индексы таблицы `colleges`
--
ALTER TABLE `colleges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_colleges_code` (`code`),
  ADD UNIQUE KEY `uq_colleges_db` (`db_name`),
  ADD KEY `idx_colleges_paid` (`status`,`paid_until`),
  ADD KEY `fk_colleges_plan` (`plan_id`);

--
-- Индексы таблицы `college_invoices`
--
ALTER TABLE `college_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_college_invoices_number` (`number`),
  ADD KEY `idx_college_invoices_college` (`college_id`,`issued_on`),
  ADD KEY `idx_college_invoices_paid` (`status`,`paid_on`),
  ADD KEY `fk_college_invoices_created_by` (`created_by`);

--
-- Индексы таблицы `college_login_tokens`
--
ALTER TABLE `college_login_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_college_login_tokens` (`token_hash`),
  ADD KEY `fk_college_login_tokens_college` (`college_id`),
  ADD KEY `fk_college_login_tokens_admin` (`admin_id`);

--
-- Индексы таблицы `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_attempts_ip` (`ip_address`,`successful`,`attempted_at`),
  ADD KEY `idx_login_attempts_identifier` (`identifier`,`ip_address`);

--
-- Индексы таблицы `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_migrations_migration` (`migration`);

--
-- Индексы таблицы `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_plans_name` (`name`);

--
-- Индексы таблицы `platform_admins`
--
ALTER TABLE `platform_admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_platform_admins_login` (`login`);

--
-- Индексы таблицы `platform_audit`
--
ALTER TABLE `platform_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_platform_audit_college` (`college_id`,`created_at`),
  ADD KEY `fk_platform_audit_admin` (`admin_id`);

--
-- Индексы таблицы `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `colleges`
--
ALTER TABLE `colleges`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `college_invoices`
--
ALTER TABLE `college_invoices`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `college_login_tokens`
--
ALTER TABLE `college_login_tokens`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `plans`
--
ALTER TABLE `plans`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `platform_admins`
--
ALTER TABLE `platform_admins`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `platform_audit`
--
ALTER TABLE `platform_audit`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcements_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_announcements_created_by` FOREIGN KEY (`created_by`) REFERENCES `platform_admins` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `colleges`
--
ALTER TABLE `colleges`
  ADD CONSTRAINT `fk_colleges_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `college_invoices`
--
ALTER TABLE `college_invoices`
  ADD CONSTRAINT `fk_college_invoices_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_college_invoices_created_by` FOREIGN KEY (`created_by`) REFERENCES `platform_admins` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `college_login_tokens`
--
ALTER TABLE `college_login_tokens`
  ADD CONSTRAINT `fk_college_login_tokens_admin` FOREIGN KEY (`admin_id`) REFERENCES `platform_admins` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_college_login_tokens_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `platform_audit`
--
ALTER TABLE `platform_audit`
  ADD CONSTRAINT `fk_platform_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `platform_admins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_platform_audit_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
