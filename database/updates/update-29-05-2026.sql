SET NAMES utf8mb4;

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `event_guard_logs` (
  `id`         int unsigned NOT NULL AUTO_INCREMENT,
  `log_uuid`   char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hostname`   varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `log_date`   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `filter`     varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `extension`  varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country`    varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failures`   int unsigned NOT NULL DEFAULT 1,
  `log_status` enum('blocked','unblocked','pending','tracking') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tracking',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_log_uuid`    (`log_uuid`),
         KEY `idx_ip_status`  (`ip_address`, `log_status`),
         KEY `idx_host_status`(`hostname`, `log_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `event_guard_whitelist` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT,
  `cidr`        varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cidr` (`cidr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- system: sem UNIQUE KEY em name, usa WHERE NOT EXISTS por chave de negócio
INSERT INTO `system` (`name`, `display_name`, `value`, `field_type`, `comment`, `is_display`, `group_title`, `sub_group`)
SELECT 'esl_host', 'ESL Host', '127.0.0.1', 'default_system_input', 'FluxSBC Event Socket host', 0, 'calls', 'Event Guard'
WHERE NOT EXISTS (SELECT 1 FROM `system` WHERE `name` = 'esl_host');

INSERT INTO `system` (`name`, `display_name`, `value`, `field_type`, `comment`, `is_display`, `group_title`, `sub_group`)
SELECT 'esl_port', 'ESL Port', '8021', 'default_system_input', 'FluxSBC Event Socket port', 0, 'calls', 'Event Guard'
WHERE NOT EXISTS (SELECT 1 FROM `system` WHERE `name` = 'esl_port');

INSERT INTO `system` (`name`, `display_name`, `value`, `field_type`, `comment`, `is_display`, `group_title`, `sub_group`)
SELECT 'hostname', 'Hostname', '', 'default_system_input', 'FluxSBC Hostname', 0, 'calls', 'Event Guard'
WHERE NOT EXISTS (SELECT 1 FROM `system` WHERE `name` = 'hostname');

INSERT INTO `system` (`name`, `display_name`, `value`, `field_type`, `comment`, `is_display`, `group_title`, `sub_group`)
SELECT 'block_threshold', 'Block Threshold', '5', 'default_system_input', 'Tentativas antes de bloquear', 0, 'calls', 'Event Guard'
WHERE NOT EXISTS (SELECT 1 FROM `system` WHERE `name` = 'block_threshold');

INSERT INTO `system` (`name`, `display_name`, `value`, `field_type`, `comment`, `is_display`, `group_title`, `sub_group`)
SELECT 'whitelist_ttl', 'Whitelist TTL', '30', 'default_system_input', 'TTL do cache da whitelist (s)', 0, 'calls', 'Event Guard'
WHERE NOT EXISTS (SELECT 1 FROM `system` WHERE `name` = 'whitelist_ttl');

INSERT INTO `system` (`name`, `display_name`, `value`, `field_type`, `comment`, `is_display`, `group_title`, `sub_group`)
SELECT 'reg_ttl', 'Reg TTL', '60', 'default_system_input', 'TTL do cache de registros (s)', 0, 'calls', 'Event Guard'
WHERE NOT EXISTS (SELECT 1 FROM `system` WHERE `name` = 'reg_ttl');

INSERT INTO `system` (`name`, `display_name`, `value`, `field_type`, `comment`, `is_display`, `group_title`, `sub_group`)
SELECT 'ring_ready', 'Ring Ready', '1', 'enable_disable_option', 'Set enable to add ring ready support', 0, 'calls', 'General'
WHERE NOT EXISTS (SELECT 1 FROM `system` WHERE `name` = 'ring_ready');

UPDATE `system` set is_display = '1' WHERE (`sub_group` = 'Calling Card' OR `name` = 'mobile_dialer') AND `is_display` = '0';

-- menu_modules: insere apenas se a module_url ainda não existir; usa variável para
-- capturar o ID correto mesmo quando o INSERT é ignorado (re-execução idempotente).
INSERT INTO `menu_modules` (`id`, `menu_label`, `module_name`, `module_url`, `menu_title`, `menu_image`, `menu_subtitle`, `priority`)
SELECT NULL, 'Blocked IPs', 'event_guard', 'event_guard/event_guard_list/', 'Switch', 'Live-Report.png', 'Event Guard', 101.8
WHERE NOT EXISTS (SELECT 1 FROM `menu_modules` WHERE `module_url` = 'event_guard/event_guard_list/');

SET @menu_id_1 = IF(ROW_COUNT() > 0, LAST_INSERT_ID(),
    (SELECT `id` FROM `menu_modules` WHERE `module_url` = 'event_guard/event_guard_list/' LIMIT 1));
UPDATE `userlevels`
SET `module_permissions` = concat(`module_permissions`, ',', @menu_id_1)
WHERE `userlevelid` IN (-1, 2)
  AND FIND_IN_SET(@menu_id_1, `module_permissions`) = 0;

INSERT INTO `menu_modules` (`id`, `menu_label`, `module_name`, `module_url`, `menu_title`, `menu_image`, `menu_subtitle`, `priority`)
SELECT NULL, 'Allowed IPs', 'event_guard', 'event_guard/event_guard_whitelist_list/', 'Switch', 'Live-Report.png', 'Event Guard', 101.9
WHERE NOT EXISTS (SELECT 1 FROM `menu_modules` WHERE `module_url` = 'event_guard/event_guard_whitelist_list/');

SET @menu_id_2 = IF(ROW_COUNT() > 0, LAST_INSERT_ID(),
    (SELECT `id` FROM `menu_modules` WHERE `module_url` = 'event_guard/event_guard_whitelist_list/' LIMIT 1));
UPDATE `userlevels`
SET `module_permissions` = concat(`module_permissions`, ',', @menu_id_2)
WHERE `userlevelid` IN (-1, 2)
  AND FIND_IN_SET(@menu_id_2, `module_permissions`) = 0;

INSERT INTO `roles_and_permission` (`id`, `login_type`, `permission_type`, `menu_name`, `module_name`, `sub_module_name`, `module_url`, `display_name`, `permissions`, `status`, `creation_date`, `priority`)
SELECT NULL, '0', '0', 'switch', 'event_guard', '0', 'event_guard_list', 'Blocked IPs', '["main","list","search","create","edit","delete"]', '0', '2019-01-25 09:01:03', '7.21'
WHERE NOT EXISTS (SELECT 1 FROM `roles_and_permission` WHERE `module_name` = 'event_guard' AND `module_url` = 'event_guard_list');

INSERT INTO `roles_and_permission` (`id`, `login_type`, `permission_type`, `menu_name`, `module_name`, `sub_module_name`, `module_url`, `display_name`, `permissions`, `status`, `creation_date`, `priority`)
SELECT NULL, '0', '0', 'switch', 'event_guard', '0', 'event_guard_whitelist_list', 'Allowed IPs', '["main","list","search","create","edit","delete"]', '0', '2019-01-25 09:01:03', '7.22'
WHERE NOT EXISTS (SELECT 1 FROM `roles_and_permission` WHERE `module_name` = 'event_guard' AND `module_url` = 'event_guard_whitelist_list');


INSERT INTO `menu_modules` (`id`, `menu_label`, `module_name`, `module_url`, `menu_title`, `menu_image`, `menu_subtitle`, `priority`)
SELECT NULL, 'Import Customers', 'accounts', 'flux_import/flux_import_list/', 'Accounts', 'ListAccounts.png', 'Customers', 10.8
WHERE NOT EXISTS (SELECT 1 FROM `menu_modules` WHERE `module_url` = 'flux_import/flux_import_list/');

SET @menu_id_1 = IF(ROW_COUNT() > 0, LAST_INSERT_ID(),
    (SELECT `id` FROM `menu_modules` WHERE `module_url` = 'flux_import/flux_import_list/' LIMIT 1));
UPDATE `userlevels`
SET `module_permissions` = concat(`module_permissions`, ',', @menu_id_1)
WHERE `userlevelid` IN (-1, 2)
  AND FIND_IN_SET(@menu_id_1, `module_permissions`) = 0;

COMMIT;
