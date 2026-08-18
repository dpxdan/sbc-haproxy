SET FOREIGN_KEY_CHECKS = 0;

UPDATE `roles_and_permission` SET `permissions` = '[\"main\",\"list\",\"edit\"]' WHERE `module_url` = 'invoice_conf_list' AND permissions = '[\"main\",\"list\"]';

SET FOREIGN_KEY_CHECKS = 1;