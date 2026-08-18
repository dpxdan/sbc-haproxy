INSERT INTO `languages` ( `code`, `name`, `locale`) VALUES ('pt', 'Portuguese', 'pt_BR');
ALTER TABLE `translations` ADD `pt_BR` TEXT NOT NULL;
INSERT INTO `system` (`name`, `display_name`, `value`, `field_type`, `reseller_id`, `is_display`, `group_title`, `sub_group`) VALUES ('default_language', 'Default Language', 'Portuguese', 'default_system_input', '0', '1', 'global', 'General');