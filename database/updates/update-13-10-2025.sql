SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE `clientes` ADD COLUMN `reseller_id` int NULL DEFAULT NULL AFTER `senha`;

ALTER TABLE `voip_sippeers` ADD COLUMN `cliente_razao` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' AFTER `id_integracao`;

ALTER TABLE `voip_sippeers` ADD COLUMN `id_plano` int NOT NULL DEFAULT 0 AFTER `cliente_razao`;

ALTER TABLE `voip_sippeers` ADD COLUMN `reseller_id` int NULL DEFAULT NULL AFTER `id_plano`;

SET FOREIGN_KEY_CHECKS=1;