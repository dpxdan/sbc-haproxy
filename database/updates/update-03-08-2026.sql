SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM `roles_and_permission` WHERE `module_name` = 'did' AND `module_url` = 'did_list' AND `login_type` = 0;

DELETE FROM `roles_and_permission` WHERE `module_name` = 'did' AND `module_url` = 'did_list' AND `login_type` = 1;

DELETE FROM `roles_and_permission` WHERE `module_name` = 'did' AND `module_url` = 'did_list' AND `login_type` = 2;

DELETE FROM `roles_and_permission` WHERE `module_name` = 'did' AND `module_url` = 'did_list' AND `login_type` = 3;

INSERT INTO `roles_and_permission` (`id`, `login_type`, `permission_type`, `menu_name`, `module_name`, `sub_module_name`, `module_url`, `display_name`, `permissions`, `status`, `creation_date`, `priority`) VALUES (NULL, '0', '0', 'inbound', 'did', '', 'did_list', 'DIDs', '[\"main\",\"list\",\"create\",\"export\",\"import\",\"delete\",\"edit\",\"forward\",\"search\",\"purchase\",\"batch_update\"]', '0', '2019-01-25 09:01:05', '3.10000');

INSERT INTO `roles_and_permission` (`id`, `login_type`, `permission_type`, `menu_name`, `module_name`, `sub_module_name`, `module_url`, `display_name`, `permissions`, `status`, `creation_date`, `priority`) VALUES (NULL, '1', '0', 'inbound', 'did', '', 'did_list', 'DIDs', '[\"main\",\"list\",\"create\",\"export\",\"import\",\"delete\",\"edit\",\"forward\",\"search\",\"purchase\",\"buy_did\",\"available_did\",\"batch_update\"]', '0', '2019-01-25 09:01:05', '3.10000');

INSERT INTO `roles_and_permission` (`id`, `login_type`, `permission_type`, `menu_name`, `module_name`, `sub_module_name`, `module_url`, `display_name`, `permissions`, `status`, `creation_date`, `priority`) VALUES (NULL, '2', '0', 'inbound', 'did', '', 'did_list', 'DIDs', '[\"main\",\"list\",\"create\",\"export\",\"import\",\"delete\",\"edit\",\"forward\",\"search\",\"purchase\",\"buy_did\",\"available_did\",\"batch_update\"]', '0', '2019-01-25 09:01:05', '3.10000');

INSERT INTO `roles_and_permission` (`id`, `login_type`, `permission_type`, `menu_name`, `module_name`, `sub_module_name`, `module_url`, `display_name`, `permissions`, `status`, `creation_date`, `priority`) VALUES (NULL, '3', '0', 'inbound', 'did', '', 'did_list', 'DIDs', '[\"main\",\"list\",\"create\",\"export\",\"import\",\"delete\",\"edit\",\"forward\",\"search\",\"purchase\",\"buy_did\",\"available_did\",\"batch_update\"]', '0', '2019-01-25 09:01:05', '3.10000');

SET FOREIGN_KEY_CHECKS = 1;