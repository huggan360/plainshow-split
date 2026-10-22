ALTER TABLE `groups` ADD COLUMN `plainshow_group_id` BIGINT UNSIGNED NULL, ADD INDEX `groups_plainshow_group` (`plainshow_group_id`);
ALTER TABLE `members` ADD COLUMN `source` VARCHAR(16) NOT NULL DEFAULT 'manual';
