SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE `api_logs` ADD COLUMN `response_id` int NULL DEFAULT 0 AFTER `response`;

ALTER TABLE `api_logs` ADD COLUMN `request_id` int NULL DEFAULT 0 AFTER `response_id`;

ALTER TABLE `cdr` MODIFY COLUMN `enviado_ixc` enum('nao','sim') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'nao' AFTER `src`;

DROP TRIGGER `clientes_to_accounts`;

ALTER TABLE `clientes` ADD COLUMN `fantasia` varchar(80) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' AFTER `razao`;

ALTER TABLE `clientes` ADD COLUMN `fone` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL AFTER `telefone_celular`;

ALTER TABLE `clientes` MODIFY COLUMN `id` int NOT NULL FIRST;

ALTER TABLE `clientes` MODIFY COLUMN `data_cadastro` date NOT NULL DEFAULT '1000-01-01' AFTER `tipo_pessoa`;

ALTER TABLE `clientes` MODIFY COLUMN `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `voip_sippeers` ADD COLUMN `id_integracao` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '8' AFTER `last_update`;

SET FOREIGN_KEY_CHECKS=1;