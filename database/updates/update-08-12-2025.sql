SET FOREIGN_KEY_CHECKS=0;

INSERT INTO `menu_modules` (`menu_label`, `module_name`, `module_url`, `menu_title`, `menu_image`, `menu_subtitle`, `priority`) VALUES ('CDR Mode', 'fsjsoncdr', 'cdr_mode', 'Switch', 'JsonCdr.png', '0', '70.2');

UPDATE userlevels SET module_permissions = CONCAT(module_permissions, ',', (SELECT id FROM menu_modules WHERE module_name = 'fsjsoncdr')) WHERE userlevelid IN ('-1', '2') AND FIND_IN_SET((SELECT id FROM menu_modules WHERE module_name = 'fsjsoncdr'), module_permissions) = 0;

INSERT INTO `system` (`id`, `name`, `display_name`, `value`, `field_type`, `comment`, `timestamp`, `reseller_id`, `is_display`, `group_title`, `sub_group`, `field_rules`) VALUES (NULL, 'cdr_url', 'CDR URL', 'http://127.0.0.1:8735/cdr.php', 'default_system_input', 'Enter URL for CDRs', '2019-05-24 19:03:37', 0, 0, 'calls', 'CDRs', '');

INSERT INTO `system` (`id`, `name`, `display_name`, `value`, `field_type`, `comment`, `timestamp`, `reseller_id`, `is_display`, `group_title`, `sub_group`, `field_rules`) VALUES (NULL, 'cdr_log_dir', 'CDR Log Dir', '', 'default_system_input', 'Enter dir for CDRs Files.', '2019-05-24 19:03:37', 0, 0, 'calls', 'CDRs', '');

INSERT INTO `system` (`id`, `name`, `display_name`, `value`, `field_type`, `comment`, `timestamp`, `reseller_id`, `is_display`, `group_title`, `sub_group`, `field_rules`) VALUES (NULL, 'cdr_mode', 'CDR Mode', 'url', 'set_cdr_file_mode', 'Enter CDR Mode', '2019-05-24 19:03:37', 0, 0, 'calls', 'CDRs', '');

SET FOREIGN_KEY_CHECKS=1;

