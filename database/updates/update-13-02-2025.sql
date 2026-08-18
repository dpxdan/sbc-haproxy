INSERT INTO `roles_and_permission` (`login_type`, `permission_type`, `menu_name`, `module_name`, `module_url`, `display_name`, `permissions`, `status`, `creation_date`, `priority`) VALUES ('0', '0', 'switch', 'localization', 'localization_list', 'Localizations', '[\"main\",\"list\",\"create\",\"edit\",\"delete\",\"search\"]', '0', '2019-01-25 09:01:03', '2.26000');

UPDATE `roles_and_permission` SET `login_type` = '0' WHERE (`id` = '59');
UPDATE `roles_and_permission` SET `login_type` = '0' WHERE (`id` = '58');

UPDATE `roles_and_permission` SET `status` = '1' WHERE (`id` = '13');

DROP TABLE IF EXISTS `refactor`;
CREATE TABLE `refactor`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_id` int NOT NULL,
  `reseller_id` int NOT NULL DEFAULT 0,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `pricelist_id` int NOT NULL,
  `from_date` datetime NOT NULL,
  `to_date` datetime NOT NULL,
  `status` int NOT NULL DEFAULT 0,
  `creation_date` datetime NOT NULL,
  `refactor_date` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 26 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

INSERT INTO `cron_settings` (`id`, `name`, `command`, `exec_interval`, `creation_date`, `last_modified_date`, `last_execution_date`, `next_execution_date`, `status`, `file_path`) VALUES (10, 'Refactor Service', 'minutes', 1, '2025-02-12 16:48:34', '2025-02-12 17:10:37', '2025-02-13 18:48:02', '2025-02-13 18:49:02', 0, 'wget --no-check-certificate -q -O- {BASE_URL}Refactor/index/');

INSERT INTO `menu_modules` (`menu_label`, `module_name`, `module_url`, `menu_title`, `menu_image`, `menu_subtitle`, `priority`) VALUES ('Refactor', 'price', 'pricing/price_refactor/', 'Tariff', 'pricelist.png', '0', 40.2);

UPDATE userlevels SET module_permissions = CONCAT(module_permissions, ',', (SELECT id FROM menu_modules WHERE menu_label = 'Refactor')) WHERE userlevelid = -1 AND FIND_IN_SET((SELECT id FROM menu_modules WHERE menu_label = 'Refactor'), module_permissions) = 0;

UPDATE userlevels SET module_permissions = CONCAT(module_permissions, ',', (SELECT id FROM menu_modules WHERE menu_label = 'Refactor')) WHERE userlevelid = 2 AND FIND_IN_SET((SELECT id FROM menu_modules WHERE menu_label = 'Refactor'), module_permissions) = 0;

INSERT INTO `roles_and_permission` (`login_type`, `permission_type`, `menu_name`, `module_name`, `module_url`, `display_name`, `permissions`, `status`, `creation_date`, `priority`) VALUES ('0', '0', 'tariff', 'pricing', 'price_refactor', 'Refactor', '[\"main\",\"list\",\"create\",\"delete\",\"edit\",\"search\",\"duplicate\"]', '0', '2019-01-25 09:01:05', '4.10000');

UPDATE `permissions` SET `permissions` = '{\"accounts\":{\"customer_list\":{\"main\":\"0\",\"list\":\"0\",\"create\":\"0\",\"mass_create\":\"0\",\"export\":\"0\",\"delete\":\"0\",\"edit\":\"0\",\"callerid\":\"0\",\"payment\":\"0\",\"search\":\"0\",\"batch_update\":\"0\"},\"reseller_list\":{\"main\":\"0\",\"list\":\"0\",\"create\":\"0\",\"export\":\"0\",\"delete\":\"0\",\"edit\":\"0\",\"search\":\"0\",\"payment\":\"0\"},\"admin_list\":{\"main\":\"0\",\"list\":\"0\",\"create_admin\":\"0\",\"delete\":\"0\",\"edit\":\"0\",\"search\":\"0\"}},\"freeswitch\":{\"fssipdevices\":{\"main\":\"0\",\"list\":\"0\",\"create\":\"0\",\"delete\":\"0\",\"edit\":\"0\",\"search\":\"0\"},\"fsgateway\":{\"main\":\"0\",\"list\":\"0\",\"create\":\"0\",\"edit\":\"0\",\"delete\":\"0\",\"search\":\"0\"},\"livecall_report\":{\"main\":\"0\",\"list\":\"0\"}},\"ipmap\":{\"ipmap_detail\":{\"main\":\"0\",\"list\":\"0\",\"create\":\"0\",\"delete\":\"0\",\"edit\":\"0\",\"search\":\"0\"}},\"permissions\":{\"permissions_list\":{\"main\":\"0\",\"list\":\"0\",\"create\":\"0\",\"delete\":\"0\",\"edit\":\"0\",\"search\":\"0\"}},\"invoices\":{\"invoice_list\":{\"main\":\"0\",\"list\":\"0\",\"download\":\"0\",\"edit\":\"0\",\"generate\":\"0\",\"search\":\"0\",\"delete\":\"0\"},\"invoice_conf_list\":{\"main\":\"0\",\"list\":\"0\"}},\"reports\":{\"refillreport\":{\"main\":\"0\",\"list\":\"0\",\"export\":\"0\",\"search\":\"0\"},\"customerReport\":{\"main\":\"0\",\"list\":\"0\",\"export\":\"0\",\"search\":\"0\"},\"resellerReport\":{\"main\":\"0\",\"list\":\"0\",\"export\":\"0\",\"search\":\"0\"},\"providerReport\":{\"main\":\"0\",\"list\":\"0\",\"export\":\"0\",\"search\":\"0\"}},\"rates\":{\"termination_rates_list\":{\"main\":\"0\",\"list\":\"0\",\"create\":\"0\",\"edit\":\"0\",\"delete\":\"0\",\"search\":\"0\"},\"origination_rates_list\":{\"main\":\"0\",\"list\":\"0\",\"create\":\"0\",\"export\":\"0\",\"import\":\"0\",\"delete\":\"0\",\"edit\":\"0\",\"search\":\"0\",\"batch_update\":\"0\"}},\"trunk\":{\"trunk_list\":{\"main\":\"0\",\"list\":\"0\",\"create\":\"0\",\"edit\":\"0\",\"delete\":\"0\",\"search\":\"0\"}},\"fsmonitor\":{\"sip_devices\":{\"main\":\"0\",\"list\":\"0\"}},\"did\":{\"did_list\":{\"main\":\"0\",\"list\":\"0\",\"create\":\"0\",\"export\":\"0\",\"import\":\"0\",\"delete\":\"0\",\"edit\":\"0\",\"forward\":\"0\",\"search\":\"0\",\"purchase\":\"0\"}},\"accessnumber\":{\"accessnumber_list\":{\"main\":\"0\",\"list\":\"0\",\"search\":\"0\"}},\"ringgroup\":{\"ringgroup_list\":{\"main\":\"0\",\"list\":\"0\",\"create\":\"0\",\"edit\":\"0\",\"delete\":\"0\",\"search\":\"0\"}},\"pricing\":{\"price_refactor\":{\"main\":\"0\",\"list\":\"0\",\"create\":\"0\",\"delete\":\"0\",\"edit\":\"0\",\"search\":\"0\",\"duplicate\":\"0\"},\"price_list\":{\"main\":\"0\",\"list\":\"0\",\"create\":\"0\",\"delete\":\"0\",\"edit\":\"0\",\"search\":\"0\",\"duplicate\":\"0\"}},\"products\":{\"products_list\":{\"main\":\"0\",\"list\":\"0\",\"create\":\"0\",\"delete\":\"0\",\"edit\":\"0\",\"search\":\"0\"}},\"orders\":{\"orders_list\":{\"main\":\"0\",\"list\":\"0\",\"new\":\"0\",\"edit\":\"0\",\"search\":\"0\"}},\"low_balance\":{\"low_balance_list\":{\"main\":\"0\",\"list\":\"0\",\"search\":\"0\"}},\"summary\":{\"product\":{\"main\":\"0\",\"list\":\"0\",\"search\":\"0\",\"export\":\"0\"}},\"systems\":{\"template\":{\"main\":\"0\",\"list\":\"0\",\"edit\":\"0\",\"search\":\"0\"}}}' WHERE (`id` = '3');


