SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE `api_endpoints` ADD COLUMN `external_api_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' AFTER `endpoint_token`;

ALTER TABLE `api_logs` ADD COLUMN `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0:ok,1:error' AFTER `type`;

ALTER TABLE `api_test_requests` ADD COLUMN `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0:ok,1:error' AFTER `body`;

ALTER TABLE `api_test_responses` ADD COLUMN `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0:ok,1:error' AFTER `error`;

ALTER TABLE `clientes` ADD COLUMN `senha` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL AFTER `numero`;

DROP VIEW IF EXISTS `view_api_logs`;

CREATE ALGORITHM = UNDEFINED DEFINER = `fluxuser`@`127.0.0.1` SQL SECURITY DEFINER VIEW `view_api_logs` AS select `api_test_requests`.`id` AS `id`,`api_test_requests`.`url` AS `url`,`api_test_requests`.`method` AS `method`,`api_test_requests`.`headers` AS `headers`,`api_test_requests`.`body` AS `body`,`api_test_requests`.`created_at` AS `created_at`,`api_test_responses`.`request_id` AS `request_id`,`api_test_responses`.`http_code` AS `http_code`,`api_test_responses`.`response_body` AS `response_body`,`api_test_responses`.`error` AS `error`,`api_test_responses`.`created_at` AS `response_created_at`,`api_test_responses`.`status` AS `status` from (`api_test_requests` join `api_test_responses` on((`api_test_requests`.`id` = `api_test_responses`.`request_id`))) order by `api_test_requests`.`created_at` desc;

DROP VIEW IF EXISTS `view_cidade`;

CREATE ALGORITHM = UNDEFINED DEFINER = `fluxuser`@`127.0.0.1` SQL SECURITY INVOKER VIEW `view_cidade` AS select `cidade`.`id` AS `cidade_id`,`cidade`.`nome` AS `cidade`,`uf`.`id` AS `uf_id`,`uf`.`nome` AS `estado` from (`cidade` join `uf` on((`cidade`.`uf` = `uf`.`id`))) order by `cidade`.`nome`;

SET FOREIGN_KEY_CHECKS=1;