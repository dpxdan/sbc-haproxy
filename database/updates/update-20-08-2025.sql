SET FOREIGN_KEY_CHECKS=0;

DELETE FROM `cron_settings` WHERE `file_path` LIKE '%voip/%';

INSERT INTO `cron_settings` (`id`, `name`, `command`, `exec_interval`, `creation_date`, `last_modified_date`, `last_execution_date`, `next_execution_date`, `status`, `file_path`) VALUES (NULL, 'Get Sync', 'minutes', 10, '2025-08-20 14:50:48', '2025-08-20 14:54:55', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 1, 'wget --no-check-certificate -O - -q {BASE_URL}ApiSync/sync/');

INSERT INTO `cron_settings` (`id`, `name`, `command`, `exec_interval`, `creation_date`, `last_modified_date`, `last_execution_date`, `next_execution_date`, `status`, `file_path`) VALUES (NULL, 'Get Cities', 'days', 1, '2025-08-20 14:51:49', '2025-08-20 14:52:55', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 1, 'wget --no-check-certificate -O - -q {BASE_URL}ApiSync/sync_locations/');

INSERT INTO `cron_settings` (`id`, `name`, `command`, `exec_interval`, `creation_date`, `last_modified_date`, `last_execution_date`, `next_execution_date`, `status`, `file_path`) VALUES (NULL, 'Get CDRs', 'minutes', 5, '2025-08-20 14:52:19', '2025-08-20 16:21:55', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 1, 'wget --no-check-certificate -O - -q {BASE_URL}ApiSync/sync_cdrs/');

INSERT INTO `cron_settings` (`id`, `name`, `command`, `exec_interval`, `creation_date`, `last_modified_date`, `last_execution_date`, `next_execution_date`, `status`, `file_path`) VALUES (NULL, 'Get Plans', 'hours', 1, '2025-08-20 14:52:44', '2025-08-20 16:21:59', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 1, 'wget --no-check-certificate -O - -q {BASE_URL}ApiSync/sync_voip_plans/');

UPDATE userlevels ul SET ul.module_permissions=TRIM(BOTH ',' FROM CONCAT(ul.module_permissions,',',(SELECT GROUP_CONCAT(m.id) FROM menu_modules m WHERE m.module_url IN ('api_endpoints/partners_endpoints_list/','api_endpoints/partners_list/') AND FIND_IN_SET(m.id,ul.module_permissions)=0))) WHERE ul.userlevelid=-1;

INSERT INTO `endpoints` (`id`, `nome`, `base_url`, `type`, `partner_id`, `accountid`, `reseller_id`, `status`, `creation_date`, `last_modified_date`) VALUES (NULL, 'Cidades', '/cidade', 'list', 0, 2, 0, 0, '2025-07-10 17:04:50', '2025-08-13 21:59:12');

INSERT INTO `endpoints` (`id`, `nome`, `base_url`, `type`, `partner_id`, `accountid`, `reseller_id`, `status`, `creation_date`, `last_modified_date`) VALUES (NULL, 'Clientes Cadastrados', '/cliente', 'list', 0, 2, 0, 0, '2025-03-19 22:49:01', '2025-08-13 21:59:22');

INSERT INTO `endpoints` (`id`, `nome`, `base_url`, `type`, `partner_id`, `accountid`, `reseller_id`, `status`, `creation_date`, `last_modified_date`) VALUES (NULL, 'Contratos de Clientes', '/cliente_contrato', 'list', 0, 2, 0, 0, '2025-03-19 22:49:01', '2025-08-13 21:59:30');

INSERT INTO `endpoints` (`id`, `nome`, `base_url`, `type`, `partner_id`, `accountid`, `reseller_id`, `status`, `creation_date`, `last_modified_date`) VALUES (NULL, 'Ramais Cadastrados', '/view_voip_sippeers_cliente', 'list', 0, 2, 0, 0, '2025-03-19 22:49:01', '2025-08-13 21:59:17');

INSERT INTO `endpoints` (`id`, `nome`, `base_url`, `type`, `partner_id`, `accountid`, `reseller_id`, `status`, `creation_date`, `last_modified_date`) VALUES (NULL, 'Registro de Chamadas', '/cdr', 'list', 0, 2, 0, 0, '2025-03-19 22:49:01', '2025-08-13 21:59:26');

INSERT INTO `endpoints` (`id`, `nome`, `base_url`, `type`, `partner_id`, `accountid`, `reseller_id`, `status`, `creation_date`, `last_modified_date`) VALUES (NULL, 'Usuários', '/usuarios', 'list', 0, 2, 0, 0, '2025-08-13 22:00:49', '2025-08-13 22:00:49');

SET FOREIGN_KEY_CHECKS=1;