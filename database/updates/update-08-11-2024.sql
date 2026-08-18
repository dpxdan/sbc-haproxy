SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `cadup`  (
  `idCadup` bigint NOT NULL,
  `nomePrestadora` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `CNPJ` varchar(18) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `cn` int NULL DEFAULT NULL,
  `prefixo` int NULL DEFAULT NULL,
  `faixaIni` int NULL DEFAULT NULL,
  `faixaFin` int NULL DEFAULT NULL,
  `codCNL` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `nomeLocalidade` varchar(250) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
  `areaLocal` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `codArea` varchar(9) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `status` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `dataAtivacao` date NULL DEFAULT NULL,
  `eot` varchar(5) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `rn1` int NULL DEFAULT NULL,
  `uf` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `tipo` varchar(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `siglaAreaLocal` varchar(6) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`idCadup`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

CREATE TABLE `cadup_operadoras`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `idCadup` bigint NOT NULL,
  `nomePrestadora` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `CNPJ` varchar(18) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `cn` int NULL DEFAULT NULL,
  `prefixo` int NULL DEFAULT NULL,
  `faixaIni` int NULL DEFAULT NULL,
  `faixaFin` int NULL DEFAULT NULL,
  `codCNL` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `nomeLocalidade` varchar(250) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
  `areaLocal` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `codArea` varchar(9) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `status` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `dataAtivacao` date NULL DEFAULT NULL,
  `eot` varchar(5) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `rn1` int NULL DEFAULT NULL,
  `uf` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `tipo` varchar(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `siglaAreaLocal` varchar(6) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `carrier_id` int NOT NULL DEFAULT 0,
  `carrier_route_id` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`, `idCadup`) USING BTREE,
  UNIQUE INDEX `id`(`idCadup`) USING BTREE,
  INDEX `rn1`(`rn1`) USING BTREE,
  INDEX `prefix`(`prefixo`) USING BTREE,
  INDEX `id_2`(`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

CREATE TABLE `carrier_routing`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `carrier_name` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `carrier_id` int NOT NULL DEFAULT 0,
  `carrier_rn1` int NULL DEFAULT NULL,
  `description` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `reseller_id` int NOT NULL,
  `accountid` int NOT NULL,
  `call_count` int NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0:Active,1:Inactive',
  `creation_date` datetime NOT NULL,
  `last_modified_date` datetime NOT NULL DEFAULT '2022-01-01 00:00:00',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `code_rg_accid_key`(`carrier_rn1`, `carrier_id`, `accountid`) USING BTREE,
  INDEX `carrier_rn1`(`carrier_rn1`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;

DROP TRIGGER `cdr_records`;

DROP TRIGGER `activity_reports`;

ALTER TABLE `cdrs` ADD COLUMN `call_id_cadup` bigint NOT NULL DEFAULT 0 AFTER `call_request`;

ALTER TABLE `cdrs` MODIFY COLUMN `calltype` varchar(120) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'Padrao' AFTER `call_direction`;

ALTER TABLE `cdrs` MODIFY COLUMN `country_id` int NOT NULL DEFAULT 28 AFTER `call_id_cadup`;

ALTER TABLE `cdrs` MODIFY COLUMN `end_stamp` datetime NOT NULL DEFAULT '2021-01-01 00:00:00' AFTER `country_id`;


ALTER TABLE `cdrs_day_by_summary` MODIFY COLUMN `calldate` datetime NOT NULL DEFAULT '2021-01-01 00:00:00' AFTER `unique_date`;

ALTER TABLE `cdrs_staging` ADD COLUMN `call_id_cadup` varchar(120) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL AFTER `call_request`;

ALTER TABLE `cdrs_staging` MODIFY COLUMN `callstart` datetime NOT NULL DEFAULT '2021-01-01 00:00:00' AFTER `disposition`;

ALTER TABLE `cdrs_staging` MODIFY COLUMN `calltype` varchar(120) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'Padrao' AFTER `call_direction`;

ALTER TABLE `cdrs_staging` MODIFY COLUMN `end_stamp` datetime NOT NULL DEFAULT '2021-01-01 00:00:00' AFTER `country_id`;


DELIMITER //

CREATE DEFINER = `fluxuser`@`127.0.0.1` TRIGGER `cdr_records` AFTER INSERT ON `cdrs`
FOR EACH ROW
BEGIN
   INSERT INTO `cdrs_staging` (`uniqueid`, `accountid`, `type`, `sip_user`, `callerid`, `callednum`, `translated_dst`, `ct`, `billseconds`, `trunk_id`, `trunkip`, `callerip`, `disposition`, `callstart`, `debit`, `cost`, `provider_id`, `pricelist_id`, `package_id`, `pattern`, `notes`, `invoiceid`, `rate_cost`, `reseller_id`, `reseller_code`, `reseller_code_destination`, `reseller_cost`, `provider_code`, `provider_code_destination`, `provider_cost`, `provider_call_cost`, `call_direction`, `calltype`, `billmsec`, `answermsec`, `waitmsec`, `progress_mediamsec`, `flow_billmsec`, `is_recording`, `call_request`, `call_id_cadup`,`country_id`,`end_stamp`)
   VALUES (NEW.uniqueid, NEW.accountid, NEW.type, NEW.sip_user, NEW.callerid, NEW.callednum, NEW.translated_dst, NEW.ct, NEW.billseconds, NEW.trunk_id, NEW.trunkip, NEW.callerip, NEW.disposition, NEW.callstart, NEW.debit, NEW.cost, NEW.provider_id, NEW.pricelist_id, NEW.package_id, NEW.pattern, NEW.notes, NEW.invoiceid, NEW.rate_cost, NEW.reseller_id, NEW.reseller_code, NEW.reseller_code_destination, NEW.reseller_cost, NEW.provider_code, NEW.provider_code_destination, NEW.provider_cost, NEW.provider_call_cost, NEW.call_direction, NEW.calltype, NEW.billmsec, NEW.answermsec, NEW.waitmsec, NEW.progress_mediamsec, NEW.flow_billmsec, NEW.is_recording, NEW.call_request, NEW.call_id_cadup,NEW.country_id,NEW.end_stamp);
END //

DELIMITER //

CREATE DEFINER = `fluxuser`@`127.0.0.1` TRIGGER `activity_reports` AFTER INSERT ON `cdrs`
FOR EACH ROW
BEGIN
    IF NEW.calltype = 'DID' AND NEW.call_direction = 'inbound' THEN
        INSERT INTO `activity_reports` (
            accountid, reseller_id, last_did_call_time, balance, credit_limit
        )
        VALUES (
            NEW.accountid,
            NEW.reseller_id,
            NEW.callstart,
            (SELECT balance FROM accounts WHERE id = NEW.accountid),
            (SELECT credit_limit FROM accounts WHERE id = NEW.accountid)
        )
        ON DUPLICATE KEY UPDATE
            `last_did_call_time` = NEW.callstart,
            `balance` = VALUES(balance),
            `credit_limit` = VALUES(credit_limit);

    ELSEIF NEW.calltype != 'DID' AND NEW.call_direction = 'outbound' THEN
        INSERT INTO `activity_reports` (
            accountid, reseller_id, last_outbound_call_time, balance, credit_limit
        )
        VALUES (
            NEW.accountid,
            NEW.reseller_id,
            NEW.callstart,
            (SELECT balance FROM accounts WHERE id = NEW.accountid),
            (SELECT credit_limit FROM accounts WHERE id = NEW.accountid)
        )
        ON DUPLICATE KEY UPDATE
            `last_outbound_call_time` = NEW.callstart,
            `balance` = VALUES(balance),
            `credit_limit` = VALUES(credit_limit);
    END IF;
END //

DELIMITER ;


ALTER TABLE `dids` MODIFY COLUMN `reverse_rate` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 enable 1 for disable' AFTER `hg_type`;

ALTER TABLE `dids` MODIFY COLUMN `rate_group` int NOT NULL DEFAULT 1 AFTER `reverse_rate`;

ALTER TABLE `dids` MODIFY COLUMN `area_code` tinyint NOT NULL DEFAULT 51 AFTER `rate_group`;

ALTER TABLE `pricelists` ADD COLUMN `check_carrier` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 for inactive,1 for active' AFTER `status`;

ALTER TABLE `products` MODIFY COLUMN `apply_on_rategroups` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL AFTER `apply_on_existing_account`;

CREATE TABLE `rn1_operadoras`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `operadora` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `rn1` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `cad_id` int NULL DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 for active 1 for inactive',
  `creation_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `last_modified_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `cad_rn1`(`cad_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

ALTER TABLE `routing` ADD COLUMN `carrier_id` int NOT NULL DEFAULT 0 AFTER `routes_id`;

ALTER TABLE `trunks` ADD COLUMN `carrier_id` int NOT NULL DEFAULT 0 AFTER `provider_id`;

ALTER TABLE `trunks` MODIFY COLUMN `tech` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '' AFTER `name`;

ALTER TABLE `trunks` MODIFY COLUMN `cid_translation` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL AFTER `sip_cid_type`;

ALTER TABLE `trunks` MODIFY COLUMN `creation_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER `cid_translation`;

ALTER TABLE `trunks` MODIFY COLUMN `last_modified_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER `creation_date`;

CREATE ALGORITHM = UNDEFINED DEFINER = `fluxuser`@`127.0.0.1` SQL SECURITY DEFINER VIEW `view_cadup` AS select `cadup`.`idCadup` AS `idCadup`,`cadup`.`idCadup` AS `id`,`cadup`.`nomePrestadora` AS `nomePrestadora`,`cadup`.`tipo` AS `tipo`,`cadup`.`cn` AS `cn`,`cadup`.`prefixo` AS `prefixo`,`cadup`.`faixaIni` AS `faixaIni`,`cadup`.`faixaFin` AS `faixaFin`,concat(`cadup`.`cn`,`cadup`.`prefixo`) AS `cn_prefix`,concat(`cadup`.`cn`,`cadup`.`prefixo`,`cadup`.`faixaIni`) AS `range_inicial`,concat(`cadup`.`cn`,`cadup`.`prefixo`,`cadup`.`faixaFin`) AS `range_final`,concat('^',`cadup`.`cn`,`cadup`.`prefixo`,'.*') AS `pattern`,concat('^',`cadup`.`prefixo`,'.*') AS `route_pattern`,`cadup`.`nomeLocalidade` AS `nomeLocalidade`,`cadup`.`areaLocal` AS `areaLocal`,`cadup`.`codArea` AS `codArea`,`cadup`.`uf` AS `uf`,`cadup`.`dataAtivacao` AS `dataAtivacao`,`cadup`.`rn1` AS `rn1` from `cadup` where (`cadup`.`status` = '1') order by `cadup`.`idCadup`;

CREATE ALGORITHM = UNDEFINED DEFINER = `fluxuser`@`127.0.0.1` SQL SECURITY INVOKER VIEW `view_carrier_id` AS select `cadup_operadoras`.`nomePrestadora` AS `nomePrestadora`,`cadup_operadoras`.`rn1` AS `cad_rn1`,`rn1_operadoras`.`rn1` AS `op_rn1`,`cadup_operadoras`.`id` AS `id`,`rn1_operadoras`.`id` AS `op_id` from (`cadup_operadoras` join `rn1_operadoras`) where (`cadup_operadoras`.`rn1` = `rn1_operadoras`.`rn1`) group by `cadup_operadoras`.`nomePrestadora`,`cadup_operadoras`.`rn1`,`rn1_operadoras`.`rn1` order by `cadup_operadoras`.`nomePrestadora`;

CREATE  OR REPLACE 
    ALGORITHM = UNDEFINED 
    DEFINER = `fluxuser`@`127.0.0.1` 
    SQL SECURITY DEFINER
VIEW `view_carriers` AS
    SELECT 
        `cadup_operadoras`.`id` AS `cadup_id`,
        `cadup_operadoras`.`idCadup` AS `idCadup`,
        `cadup_operadoras`.`nomePrestadora` AS `nomePrestadora`,
        `cadup_operadoras`.`tipo` AS `tipo`,
        `cadup_operadoras`.`cn` AS `cn`,
        `cadup_operadoras`.`prefixo` AS `prefixo`,
        `cadup_operadoras`.`faixaIni` AS `faixaIni`,
        `cadup_operadoras`.`faixaFin` AS `faixaFin`,
        CONCAT(`cadup_operadoras`.`cn`,
                `cadup_operadoras`.`prefixo`) AS `cn_prefix`,
        CONCAT(`cadup_operadoras`.`cn`,
                `cadup_operadoras`.`prefixo`,
                `cadup_operadoras`.`faixaIni`) AS `range_inicial`,
        CONCAT(`cadup_operadoras`.`cn`,
                `cadup_operadoras`.`prefixo`,
                `cadup_operadoras`.`faixaFin`) AS `range_final`,
        CONCAT('^',
                `cadup_operadoras`.`cn`,
                `cadup_operadoras`.`prefixo`,
                '.*') AS `pattern`,
        CONCAT('^', `cadup_operadoras`.`prefixo`, '.*') AS `route_pattern`,
        `cadup_operadoras`.`nomeLocalidade` AS `nomeLocalidade`,
        `cadup_operadoras`.`areaLocal` AS `areaLocal`,
        `cadup_operadoras`.`codArea` AS `codArea`,
        `cadup_operadoras`.`uf` AS `uf`,
        `cadup_operadoras`.`dataAtivacao` AS `dataAtivacao`,
        `cadup_operadoras`.`rn1` AS `rn1`,
        `cadup_operadoras`.`carrier_id` AS `carrier_id`,
        `cadup_operadoras`.`carrier_route_id` AS `carrier_route_id`
    FROM
        `cadup_operadoras`
    WHERE
        (`cadup_operadoras`.`status` = '1')
    ORDER BY `cadup_operadoras`.`idCadup`;

SET FOREIGN_KEY_CHECKS=1;
