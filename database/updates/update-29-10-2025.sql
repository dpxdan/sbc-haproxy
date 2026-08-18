SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE `api_endpoints` ADD COLUMN `sync_cdrs_for` tinyint(1) NOT NULL DEFAULT '1' AFTER `last_modified_date`;

ALTER TABLE `cidade` ADD COLUMN `cod_siafi` varchar(255) NOT NULL DEFAULT '' AFTER `regiao`;
ALTER TABLE `cidade` ADD COLUMN `cod_cidade_nfse_forquilhinha_sc` varchar(255) NOT NULL DEFAULT '' AFTER `cod_siafi`;
ALTER TABLE `cidade` ADD COLUMN `origem` varchar(255) NOT NULL DEFAULT '' AFTER `cod_cidade_nfse_forquilhinha_sc`;

SET FOREIGN_KEY_CHECKS=1;
