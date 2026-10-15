-- Split keeps its own tables. Amounts are whole öre (cents) to avoid rounding.
-- People are PlainShow account ids (plainshow.users.id).
CREATE TABLE IF NOT EXISTS `groups` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `currency` CHAR(3) NOT NULL DEFAULT 'SEK',
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `members` (
    `group_id` INT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`group_id`, `user_id`),
    KEY `members_user` (`user_id`),
    CONSTRAINT `members_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(190) NOT NULL,
    `amount` INT UNSIGNED NOT NULL,
    `paid_by` BIGINT UNSIGNED NOT NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `spent_on` DATE NOT NULL,
    `notes` TEXT NULL,
    `split_mode` ENUM('equal', 'amount', 'percent') NOT NULL DEFAULT 'equal',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `expenses_group` (`group_id`, `spent_on`),
    CONSTRAINT `expenses_group_fk` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shares` (
    `expense_id` INT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `amount` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`expense_id`, `user_id`),
    CONSTRAINT `shares_expense` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settlements` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_id` INT UNSIGNED NOT NULL,
    `from_user` BIGINT UNSIGNED NOT NULL,
    `to_user` BIGINT UNSIGNED NOT NULL,
    `amount` INT UNSIGNED NOT NULL,
    `method` ENUM('swish', 'other') NOT NULL DEFAULT 'swish',
    `note` VARCHAR(255) NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `settlements_group` (`group_id`),
    CONSTRAINT `settlements_group_fk` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_id` INT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `kind` VARCHAR(32) NOT NULL,
    `summary` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `activity_group` (`group_id`, `id`),
    CONSTRAINT `activity_group_fk` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `migrations` (
    `name` VARCHAR(190) NOT NULL,
    `ran_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
