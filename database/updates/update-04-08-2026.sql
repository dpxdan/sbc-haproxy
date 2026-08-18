SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET @old_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';


DROP PROCEDURE IF EXISTS `_mig_assert_table`;
DROP PROCEDURE IF EXISTS `_mig_add_column`;
DROP PROCEDURE IF EXISTS `_mig_add_index`;
DROP PROCEDURE IF EXISTS `_mig_drop_index`;
DROP PROCEDURE IF EXISTS `_mig_align_gateway_ip`;

DELIMITER ;;

CREATE PROCEDURE `_mig_assert_table`(IN p_table VARCHAR(64))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table
    ) THEN
        SET @msg = CONCAT('Migration DETRAF abortada: tabela obrigatoria ausente -> ', p_table);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @msg;
    END IF;
END ;;

CREATE PROCEDURE `_mig_add_column`(IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_ddl TEXT)
BEGIN
    CALL _mig_assert_table(p_table);

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_ddl);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END ;;

CREATE PROCEDURE `_mig_add_index`(IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_ddl TEXT)
BEGIN
    CALL _mig_assert_table(p_table);

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX ', p_ddl);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END ;;

CREATE PROCEDURE `_mig_drop_index`(IN p_table VARCHAR(64), IN p_index VARCHAR(64))
BEGIN
    CALL _mig_assert_table(p_table);

    IF EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` DROP INDEX `', p_index, '`');
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END ;;

CREATE PROCEDURE `_mig_align_gateway_ip`()
BEGIN
    DECLARE v_atual    VARCHAR(64);
    DECLARE v_ref_cs   VARCHAR(64);
    DECLARE v_ref_coll VARCHAR(64);

    SELECT COLLATION_NAME INTO v_atual
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'detraf_gateway_tronco' AND COLUMN_NAME = 'gateway_ip';

    SELECT CHARACTER_SET_NAME, COLLATION_NAME INTO v_ref_cs, v_ref_coll
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cdrs' AND COLUMN_NAME = 'callerip';

    IF v_ref_coll IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Migration DETRAF abortada: coluna obrigatoria ausente -> cdrs.callerip';
    END IF;

    IF v_atual <> v_ref_coll THEN
        SET @ddl = CONCAT(
            'ALTER TABLE `detraf_gateway_tronco` MODIFY `gateway_ip` varchar(45)',
            ' CHARACTER SET ', v_ref_cs, ' COLLATE ', v_ref_coll,
            ' NOT NULL COMMENT ''IP extraido de gateway_data $.proxy''');
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END ;;

DELIMITER ;

CALL _mig_assert_table('cadup_operadoras');
CALL _mig_assert_table('carrier_routing');
CALL _mig_assert_table('cdrs');
CALL _mig_assert_table('cron_settings');
CALL _mig_assert_table('menu_modules');
CALL _mig_assert_table('userlevels');
CALL _mig_assert_table('roles_and_permission');

CREATE TABLE IF NOT EXISTS `detraf_report_jobs`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `start_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `end_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `carrier_id` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `eot_cred` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'E83',
  `call_direction` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `status` enum('queued','running','done','error') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'queued',
  `total_files` int NOT NULL DEFAULT 0,
  `total_records` int NOT NULL DEFAULT 0,
  `error_message` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL,
  `requested_by` int NULL DEFAULT NULL,
  `reseller_id` int NOT NULL DEFAULT 0,
  `locked_at` datetime NULL DEFAULT NULL,
  `queued_at` datetime NULL DEFAULT NULL,
  `started_at` datetime NULL DEFAULT NULL,
  `finished_at` datetime NULL DEFAULT NULL,
  `creation_date` datetime NULL DEFAULT NULL,
  `last_modified_date` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_status`(`status` ASC) USING BTREE,
  INDEX `reseller_id`(`reseller_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `detraf_report_logs`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `job_id` int NULL DEFAULT NULL,
  `start_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `end_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `carrier_id` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `eot_cred` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'E83',
  `call_direction` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `total_data` int NOT NULL DEFAULT 0,
  `file` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `path` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `generation_time` decimal(10, 3) NULL DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `reseller_id` int NOT NULL DEFAULT 0,
  `creation_date` datetime NULL DEFAULT NULL,
  `last_modified_date` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `reseller_id`(`reseller_id` ASC) USING BTREE,
  INDEX `idx_periodo`(`start_date` ASC, `end_date` ASC) USING BTREE,
  INDEX `idx_job_id`(`job_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `detraf_gateway_tronco`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `gateway_id` int NOT NULL COMMENT 'gateways.id (FluxSBC)',
  `gateway_name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `gateway_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'IP extraido de gateway_data $.proxy',
  `gwid` int NOT NULL COMMENT 'detraf_gws.gwid',
  `nome_gw` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `cifra` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `tronco_desc` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `natureza` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `poippi_origem` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `poippi_destino` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `eot_origem` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `eot_destino` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `codigo_area` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `rn1_destino` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_gw_gwid`(`gateway_id` ASC, `gwid` ASC) USING BTREE,
  INDEX `idx_gateway_ip`(`gateway_ip` ASC) USING BTREE,
  INDEX `idx_cifra`(`cifra` ASC) USING BTREE,
  INDEX `idx_gw_cn`(`gateway_id` ASC, `codigo_area` ASC) USING BTREE,
  INDEX `idx_gw_nat_cn`(`gateway_id` ASC, `natureza` ASC, `codigo_area` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;

CALL _mig_align_gateway_ip();


INSERT INTO `detraf_gateway_tronco` (`id`, `gateway_id`, `gateway_name`, `gateway_ip`, `gwid`, `nome_gw`, `cifra`, `tronco_desc`, `natureza`, `poippi_origem`, `poippi_destino`, `eot_origem`, `eot_destino`, `codigo_area`, `rn1_destino`) VALUES (1, 2, 'sip-i-vivo-sp', '200.201.218.136', 87, 'VIVO_LOCAL_SMP_STFC', '5511630000', 'VIVO_LC_CSL', '', 'CSL.TLF', 'FLUX.CSL', '11', 'E83', '54', '55924'), (2, 2, 'sip-i-vivo-sp', '200.201.218.136', 88, 'VIVO_LD15', '5511630001', 'VIVO_LD15', '', 'SPO.IB', 'FLUX.SPO', '11', 'E89', '11', '55924'), (3, 2, 'sip-i-vivo-sp', '200.201.218.136', 89, 'VIVO_LD_SEM_CSP', '5511610000', 'VIVO_LD_SEM_CSP', '', 'SPO.IB', 'FLUX.SPO', '11', 'E89', '11', '55924'), (4, 2, 'sip-i-vivo-sp', '200.201.218.136', 90, 'VIVO_TRANS_LD_S_CSP', '5511611000', 'VIVO_TRANS_LD_S_CSP', '', 'SPO.IB', 'FLUX.SPO', '11', 'E89', '11', '55924'), (5, 2, 'sip-i-vivo-sp', '200.201.218.136', 91, 'VIVO_VC1', '5511612001', 'VIVO_VC1', '', 'SPO.IB', 'FLUX.SPO', '11', 'E89', '11', '55924'), (6, 2, 'sip-i-vivo-sp', '200.201.218.136', 92, 'VIVO_CONC_LOCAL', '5511710001', 'VIVO_CONC_LOCAL', '', 'SPO.IB', 'FLUX.SPO', '11', 'E89', '11', '55924'), (7, 2, 'sip-i-vivo-sp', '200.201.218.136', 93, 'VIVO_CONC_LD_S_CSP_STFC_REDE_VIVO', '5511720012', 'VIVO_CONC_LD_S_CSP', '', 'SPO.IB', 'FLUX.SPO', '11', 'E89', '11', '55924'), (8, 2, 'sip-i-vivo-sp', '200.201.218.136', 94, 'VIVO_CONC_LD15', '5511720011', 'VIVO_CONC_LD15', '', 'SPO.IB', 'FLUX.SPO', '11', 'E89', '11', '55924'), (9, 2, 'sip-i-vivo-sp', '200.201.218.136', 95, 'VIVO_TRANS_LD_S_CSP_ACOBRAR', '5511630001', 'VIVO_LD15', '', 'SPO.IB', 'FLUX.SPO', '11', 'E89', '11', '55924'), (10, 2, 'sip-i-vivo-sp', '200.201.218.136', 96, 'VIVO_CONC_LD_S_CSP_ACOBRAR', '5511720011', 'VIVO_CONC_LD15', '', 'SPO.IB', 'FLUX.SPO', '11', 'E89', '11', '55924'), (11, 2, 'sip-i-vivo-sp', '200.201.218.136', 97, 'VIVO_CONC_LD15_ESPECIAS', '5511720011', 'VIVO_CONC_LD15', '', 'SPO.IB', 'FLUX.SPO', '11', 'E89', '11', '55924'), (12, 2, 'sip-i-vivo-sp', '200.201.218.136', 98, 'VIVO_CONC_0800', '5511720011', 'VIVO_CONC_LD15', '', 'SPO.IB', 'FLUX.SPO', '11', 'E89', '11', '55924'), (13, 2, 'sip-i-vivo-sp', '200.201.218.136', 99, 'VIVO_LD15_CNG', '5511630001', 'VIVO_LD15', '', 'SPO.IB', 'FLUX.SPO', '11', 'E89', '11', '55924'), (14, 2, 'sip-i-vivo-sp', '200.201.218.136', 167, 'VIVO_LD15_CNG_E164', '5511630001', 'VIVO_LD15', '', 'SPO.IB', 'FLUX.SPO', '11', 'E89', '11', '55924'), (15, 2, 'sip-i-vivo-sp', '200.201.218.136', 237, 'VIVO_SPO_0300', '5511720011', 'VIVO_CONC_LD15', '', 'SPO.IB', 'FLUX.SPO', '11', 'E89', '11', '55924'), (16, 2, 'sip-i-vivo-sp', '200.201.218.136', 244, 'VIVO_CONC_LOCAL_NOVA', '5511710001', 'VIVO_CONC_LOCAL', '', 'SPO.IB', 'FLUX.SPO', '11', 'E89', '11', '55924'), (17, 2, 'sip-i-vivo-sp', '200.201.218.136', 319, 'VIVO_TRANS_LD_S_CSP_2', '5511611000', 'VIVO_TRANS_LD_S_CSP', '', 'SPO.IB', 'FLUX.SPO', '11', 'E89', '11', '55924'), (18, 2, 'sip-i-vivo-sp', '200.201.218.136', 347, 'VIVO_TR_SCSP_E164', '5511611000', 'VIVO_TRANS_LD_S_CSP', '', 'SPO.IB', 'FLUX.SPO', '11', 'E89', '11', '55924'), (19, 2, 'sip-i-vivo-sp', '200.201.218.136', 366, 'VIVO_CONC_LD15_ESPECIAS190', '5511720011', 'VIVO_CONC_LD15', '', 'SPO.IB', 'FLUX.SPO', '11', 'E89', '11', '55924'), (20, 2, 'sip-i-vivo-sp', '200.201.218.136', 415, 'VIVOTR', '5515800003', 'VIVOTR0800', '', 'VIVO.TR', 'FLUX.TR', '951', 'E89', '11', '55924'), (21, 2, 'sip-i-vivo-sp', '200.201.218.136', 416, 'VIVOTR', '5515800003', 'VIVOTR0800', '', 'VIVO.TR', 'FLUX.TR', '951', 'E89', '11', '55924'), (22, 2, 'sip-i-vivo-sp', '200.201.218.136', 417, 'VIVOTR', '5515800003', 'VIVOTR0800', '', 'VIVO.TR', 'FLUX.TR', '951', 'E89', '11', '55924'), (23, 2, 'sip-i-vivo-sp', '200.201.218.136', 418, 'VIVOTR', '5515800003', 'VIVOTR0800', '', 'VIVO.TR', 'FLUX.TR', '951', 'E89', '11', '55924'), (24, 2, 'sip-i-vivo-sp', '200.201.218.136', 419, 'VIVOTR1', '5515800003', 'VIVOTR0800', '', 'VIVO.TR', 'FLUX.TR', '951', 'E89', '11', '55924'), (25, 2, 'sip-i-vivo-sp', '200.201.218.136', 420, 'VIVOTR', '5515800003', 'VIVOTR0800', '', 'VIVO.TR', 'FLUX.TR', '951', 'E89', '11', '55924'), (26, 2, 'sip-i-vivo-sp', '200.201.218.136', 421, 'VIVOTR', '5515800003', 'VIVOTR0800', '', 'VIVO.TR', 'FLUX.TR', '951', 'E89', '11', '55924'), (27, 2, 'sip-i-vivo-sp', '200.201.218.136', 422, 'VIVOTR', '5515800003', 'VIVOTR0800', '', 'VIVO.TR', 'FLUX.TR', '951', 'E89', '11', '55924'), (28, 2, 'sip-i-vivo-sp', '200.201.218.136', 441, 'VIVO_CONC_LD15_3003', '5511720011', 'VIVO_CONC_LD15', '', 'SPO.IB', 'FLUX.SPO', '11', 'E89', '11', '55924'), (29, 2, 'sip-i-vivo-sp', '200.201.218.136', 531, 'VIVO_LOCAL_Especiais', '5511630000', 'VIVO_LC_CSL', '', 'CSL.TLF', 'FLUX.CSL', '11', 'E83', '54', '55924'), (30, 2, 'sip-i-vivo-sp', '200.201.218.136', 532, 'VIVO_STFC_LC11', '5511630000', 'VIVO_LC_CSL', '', 'CSL.TLF', 'FLUX.CSL', '11', 'E83', '54', '55924'), (31, 2, 'sip-i-vivo-sp', '200.201.218.136', 537, 'VIVO_OUT_TR_0800', '5515800003', 'VIVOTR0800', '', 'VIVO.TR', 'FLUX.TR', '951', 'E89', '11', '55924'), (32, 3, 'sip-i-rj', '200.159.177.199', 674, 'ENTRADA_LD15_21', '5521210002', 'VIVO_LD15_RJ_21', '', 'VIVO.RJO', 'FLUX.RJO', '921', 'P41', '21', '55924'), (33, 3, 'sip-i-rj', '200.159.177.199', 675, 'ENTRADA_LC_21_TESTE', '5521210001', 'VIVO_LC_RJ_21', '', 'VIVO.RJO', 'FLUX.RJO', '921', 'P41', '21', '55924'), (34, 3, 'sip-i-rj', '200.159.177.199', 676, 'ENTRADA_LD SCSP_21', '5521210002', 'VIVO_LD15_RJ_21', '', 'VIVO.RJO', 'FLUX.RJO', '921', 'P41', '21', '55924'), (35, 3, 'sip-i-rj', '200.159.177.199', 677, 'ENTRADA_TR LD_21', '5521210003', 'VIVO_TR LD_21_RJO', '', 'VIVO.RJO', 'FLUX_RJO', '921', 'P41', '21', '55924'), (36, 3, 'sip-i-rj', '200.159.177.199', 678, 'ENTRADA_VC1_21', '5521210004', 'VIVO_VC1_21_RJO', '', 'VIVO_RJO', 'FLUX.RJO', '20', 'P41', '21', '55924'), (37, 6, 'sip-i-claro-reg2', '200.159.177.118', 501, 'CLARO_51_LC', '5521510001', 'CLARO_VC1_51', '', 'CLARO.51', 'FLUX.51', '251', 'E83', '51', '55924'), (38, 6, 'sip-i-claro-reg2', '200.159.177.118', 502, 'CLARO_51_LD', '5521510002', 'CNG_FLUX_51', '', 'CLARO.51', 'FLUX.51', '251', 'E83', '51', '55924'), (39, 6, 'sip-i-claro-reg2', '200.159.177.118', 503, 'CLARO_VC1_51', '5521510101', 'CLAROVC1', '', 'CLARO_51', 'FLUX_51', '51', 'E83', '51', '55924'), (40, 6, 'sip-i-claro-reg2', '200.159.177.118', 519, 'RS53CNGFLUX', '5553210002', 'RS53CNGFLUX', '', 'CLARO53', 'FLUX53', '251', 'E83', '53', '55924'), (41, 6, 'sip-i-claro-reg2', '200.159.177.118', 520, 'RS53LC', '5553210001', 'RS53LC', '', 'CLARO53', 'FLUX53', '251', 'E83', '53', '55924'), (42, 6, 'sip-i-claro-reg2', '200.159.177.118', 521, 'RS53VC1', '5553210001', 'RS53LC', '', 'CLARO53', 'FLUX53', '251', 'E83', '53', '55924'), (43, 6, 'sip-i-claro-reg2', '200.159.177.118', 522, 'RS54CNGFLUX', '5554210002', 'RS54CNGFLUX', '', 'CLARO54', 'FLUX54', '251', 'E83', '54', '55924'), (44, 6, 'sip-i-claro-reg2', '200.159.177.118', 523, 'RS54LC', '5554210001', 'RS54LC', '', 'CLARO54', 'FLUX54', '251', 'E83', '54', '55924'), (45, 6, 'sip-i-claro-reg2', '200.159.177.118', 524, 'RS54LD21', '5554210002', 'RS54CNGFLUX', '', 'CLARO54', 'FLUX54', '251', 'E83', '54', '55924'), (46, 6, 'sip-i-claro-reg2', '200.159.177.118', 525, 'RS54VC1', '5554210002', 'RS54CNGFLUX', '', 'CLARO54', 'FLUX54', '251', 'E83', '54', '55924'), (47, 6, 'sip-i-claro-reg2', '200.159.177.118', 526, 'RS55CNGFLUX', '5555210002', 'RS55CNGFLUX', '', 'CLARO55', 'FLUX55', '251', 'E83', '55', '55924'), (48, 6, 'sip-i-claro-reg2', '200.159.177.118', 527, 'RS55LC', '5555210001', 'RS55LC', '', 'CLARO55', 'FLUX55', '251', 'E83', '55', '55924'), (49, 6, 'sip-i-claro-reg2', '200.159.177.118', 602, 'CLARO_LC_ESPECIAIS_54', '5554210001', 'RS54LC', '', 'CLARO54', 'FLUX54', '251', 'E83', '54', '55924'), (50, 7, 'sip-i-poa-b', '200.159.177.156', 127, 'OI_LC_STFC_51_SIP156', '5531451001', 'OI_LC_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (51, 7, 'sip-i-poa-b', '200.159.177.156', 128, 'OI_LD_STFC_51_SIP156', '5531451002', 'OI_LD_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (52, 7, 'sip-i-poa-b', '200.159.177.156', 130, 'OI_LC_STFC_53_SIP156', '5531453001', 'OI_LC_53', '', 'OI_53', 'FLUX_PAE', '51', 'E83', '53', '55924'), (53, 7, 'sip-i-poa-b', '200.159.177.156', 132, 'OI_LD_STFC_53_SIP156', '5531453002', 'OI_LD_53', '', 'OI_53', 'FLUX_PAE', '51', 'E83', '53', '55924'), (54, 7, 'sip-i-poa-b', '200.159.177.156', 134, 'OI_LC_STFC_54_SIP156', '5531454001', 'OI_LC_54', '', 'OI_54', 'FLUX_PAE', '51', 'E83', '54', '55924'), (55, 7, 'sip-i-poa-b', '200.159.177.156', 136, 'OI_LD_STFC_54_SIP156', '5531454002', 'OI_LD_54', '', 'OI_54', 'FLUX_PAE', '51', 'E83', '54', '55924'), (56, 7, 'sip-i-poa-b', '200.159.177.156', 138, 'OI_LC_STFC_55_SIP156', '5531455001', 'OI_LC_55', '', 'OI_55', 'FLUX_PAE', '51', 'E83', '55', '55924'), (57, 7, 'sip-i-poa-b', '200.159.177.156', 328, 'OI_TR_54_SIP156', '5531454002', 'OI_LD_54', '', 'OI_54', 'FLUX_PAE', '51', 'E83', '54', '55924'), (58, 7, 'sip-i-poa-b', '200.159.177.156', 331, 'OI_LD_STFC_SIP156', '5531455001', 'OI_LC_55', '', 'OI_55', 'FLUX_PAE', '51', 'E83', '55', '55924'), (59, 7, 'sip-i-poa-b', '200.159.177.156', 354, 'OI_A_TR_0300_SAIDA', '5531451001', 'OI_LC_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (60, 7, 'sip-i-poa-b', '200.159.177.156', 389, 'OI_LC_CN_41_CTBA_DEFAULT', '5514410001', 'OI_LC_CTBA', '', 'OI_CTBA', 'FLUX.CTBA', '51', 'P76', '41', '55924'), (61, 7, 'sip-i-poa-b', '200.159.177.156', 390, 'OI_LC_CN_41_CTBA_ALTERNATIVA', '5514410001', 'OI_LC_CTBA', '', 'OI_CTBA', 'FLUX.CTBA', '51', 'P76', '41', '55924'), (62, 7, 'sip-i-poa-b', '200.159.177.156', 391, 'OI_LD14_41_CTBA_DEFAULT', '5514410002', 'OI_LD_CTBA', '', 'OI.CTBA', 'FLUX.CTBA', '51', 'P76', '41', '55924'), (63, 7, 'sip-i-poa-b', '200.159.177.156', 392, 'OI_LD14_41_CTBA_ALTERNATIVA', '5514410002', 'OI_LD_CTBA', '', 'OI.CTBA', 'FLUX.CTBA', '51', 'P76', '41', '55924'), (64, 7, 'sip-i-poa-b', '200.159.177.156', 393, 'OI_LC_43_LONDRINA_DEFAULT', '5514430001', 'OI_LD_LDRNA', '', 'OI.LDRNA', 'FLUX.LDRNA', '51', 'P76', '43', '55924'), (65, 7, 'sip-i-poa-b', '200.159.177.156', 394, 'OI_LC_43_LONDRINA_ALTERNATIVA', '5514430001', 'OI_LD_LDRNA', '', 'OI.LDRNA', 'FLUX.LDRNA', '51', 'P76', '43', '55924'), (66, 7, 'sip-i-poa-b', '200.159.177.156', 395, 'OI_LD14_43_LONDRINA_DEFAULT', '5514430002', 'OI_LD_LDRNA', '', 'OI.LDRNA', 'FLUX.LDRNA', '51', 'P76', '43', '55924'), (67, 7, 'sip-i-poa-b', '200.159.177.156', 396, 'OI_LD14_43_LONDRINA_ALTERNATIVA', '5514430002', 'OI_LD_LDRNA', '', 'OI.LDRNA', 'FLUX.LDRNA', '51', 'P76', '43', '55924'), (68, 7, 'sip-i-poa-b', '200.159.177.156', 397, 'OI_LC_44_UMUARAMA_DEFAULT', '5514440001', 'OI_LC_UMRMA', '', 'OI.UMRMA', 'FLUX.UMRMA', '51', 'P76', '44', '55924'), (69, 7, 'sip-i-poa-b', '200.159.177.156', 398, 'OI_LC_44_UMURAMA_ALTERNATIVA', '5514440001', 'OI_LC_UMRMA', '', 'OI.UMRMA', 'FLUX.UMRMA', '51', 'P76', '44', '55924'), (70, 7, 'sip-i-poa-b', '200.159.177.156', 399, 'OI_LD14_44_UMUARAMA_DEFAULT', '5514440002', 'OI_LD_UMRMA', '', 'OI.UMRMA', 'FLUX.UMRMA', '51', 'P76', '44', '55924'), (71, 7, 'sip-i-poa-b', '200.159.177.156', 400, 'OI_LD14_44_UMUARAMA_ALTERNATIVA', '5514440002', 'OI_LD_UMRMA', '', 'OI.UMRMA', 'FLUX.UMRMA', '51', 'P76', '44', '55924'), (72, 7, 'sip-i-poa-b', '200.159.177.156', 401, 'OI_LC_45_CASCAVEL_PRINCIPAL', '5514450001', 'OI_LC_CSVL', '', 'OI.CSVL', 'FLUX.CSVL', '51', 'P76', '45', '55924'), (73, 7, 'sip-i-poa-b', '200.159.177.156', 402, 'OI_LC_45_CASCAVEL_ALTERNATIVA', '5514450001', 'OI_LC_CSVL', '', 'OI.CSVL', 'FLUX.CSVL', '51', 'P76', '45', '55924'), (74, 7, 'sip-i-poa-b', '200.159.177.156', 403, 'OI_LD14_45_CASCAVEL_DEFAULT', '5514450002', 'OI_LD_CSVL', '', 'OI.CSVL', 'FLUX.CSVL', '51', 'P76', '45', '55924'), (75, 7, 'sip-i-poa-b', '200.159.177.156', 404, 'OI_LD14_45_CASCAVEL_ALTERNATIVA', '5514450002', 'OI_LD_CSVL', '', 'OI.CSVL', 'FLUX.CSVL', '51', 'P76', '45', '55924'), (76, 7, 'sip-i-poa-b', '200.159.177.156', 405, 'OI_LC_46_PATOBRANCO_PRINCIPAL', '5514460001', 'OI.LC_PRBRO', '', 'OI.PRBRO', 'FLUX.PRBRO', '51', 'P76', '46', '55924'), (77, 7, 'sip-i-poa-b', '200.159.177.156', 406, 'OI_LC_46_PATOBRANCO_ALTERNATIVA', '5514460001', 'OI.LC_PRBRO', '', 'OI.PRBRO', 'FLUX.PRBRO', '51', 'P76', '46', '55924'), (78, 7, 'sip-i-poa-b', '200.159.177.156', 407, 'OI_LD14_46_PATOBRANCO_PRINCIPAL', '5514460002', 'OI.LD_PTBRO', '', 'OI.PTBRO', 'FLUX.PTBRO', '51', 'P76', '46', '55924'), (79, 7, 'sip-i-poa-b', '200.159.177.156', 408, 'OI_LD14_46_PATOBRANCO_ALTERNATIVA', '5514460002', 'OI.LD_PTBRO', '', 'OI.PTBRO', 'FLUX.PTBRO', '51', 'P76', '46', '55924'), (80, 7, 'sip-i-poa-b', '200.159.177.156', 431, 'OI_LC_STFC_54_GAURAMA', '5531454001', 'OI_LC_54', '', 'OI_54', 'FLUX_PAE', '51', 'E83', '54', '55924'), (81, 7, 'sip-i-poa-b', '200.159.177.156', 433, 'OI_TRANSPORTE_SIP-I_156', '5531454002', 'OI_LD_54', '', 'OI_54', 'FLUX_PAE', '51', 'E83', '54', '55924'), (82, 7, 'sip-i-poa-b', '200.159.177.156', 435, 'OI_TRANSPORTE_SIP-I_FIXO', '5531454002', 'OI_LD_54', '', 'OI_54', 'FLUX_PAE', '51', 'E83', '54', '55924'), (83, 7, 'sip-i-poa-b', '200.159.177.156', 451, 'OI_LC_STFC_51_SIPSOMADATA', '5531451001', 'OI_LC_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (84, 7, 'sip-i-poa-b', '200.159.177.156', 477, 'OI_Especifico_014_TR', '5531454002', 'OI_LD_54', '', 'OI_54', 'FLUX_PAE', '51', 'E83', '54', '55924'), (85, 7, 'sip-i-poa-b', '200.159.177.156', 489, 'OI_LD14_44_UMUARAMA_OUT', '5514440002', 'OI_LD_UMRMA', '', 'OI.UMRMA', 'FLUX.UMRMA', '51', 'P76', '44', '55924'), (86, 7, 'sip-i-poa-b', '200.159.177.156', 511, 'OI_LC_STFC_51_SIP156_3003', '5531451001', 'OI_LC_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (87, 7, 'sip-i-poa-b', '200.159.177.156', 512, 'OI_FLUX_TR_54_SAIDA_0800', '5514518453', 'DEFF_OI_51_TR', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '4926'), (88, 7, 'sip-i-poa-b', '200.159.177.156', 518, 'OI_LC_STFC_51_SIP156_Lajeado', '5531451001', 'OI_LC_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (89, 7, 'sip-i-poa-b', '200.159.177.156', 541, 'OI_51_LD_UFRGS_GOV', '5531451002', 'OI_LD_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (90, 7, 'sip-i-poa-b', '200.159.177.156', 542, 'OI_51_LC_UFRGS_GOV', '5531451001', 'OI_LC_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (91, 7, 'sip-i-poa-b', '200.159.177.156', 543, 'OI_51_LC_UFRGS_GOV_FIXO', '5531451001', 'OI_LC_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (92, 7, 'sip-i-poa-b', '200.159.177.156', 544, 'OI_51_LD_UFRGS_GOV_FIXO', '5531451002', 'OI_LD_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (93, 7, 'sip-i-poa-b', '200.159.177.156', 579, 'OILDN_42_PR', '5514420002', 'OI_PR_LDN_42', '', 'OI.PR', 'FLUX.PR', '41', 'P76', '42', '55924'), (94, 7, 'sip-i-poa-b', '200.159.177.156', 591, 'OI_unitelespecifico', '5531451002', 'OI_LD_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (95, 7, 'sip-i-poa-b', '200.159.177.156', 597, 'OI_LOCAL_42_PR', '5514420005', 'OI_LC_42', '', 'OI.CTBA', 'FLUX.CTBA', '41', 'P76', '42', '55924'), (96, 7, 'sip-i-poa-b', '200.159.177.156', 631, 'Especiais_CSL', '5531454001', 'OI_LC_54', '', 'OI_54', 'FLUX_PAE', '51', 'E83', '54', '55924'), (97, 7, 'sip-i-poa-b', '200.159.177.156', 638, 'LOCAL_ESPECIAIS_CSL54', '5531454001', 'OI_LC_54', '', 'OI_54', 'FLUX_PAE', '51', 'E83', '54', '55924'), (98, 7, 'sip-i-poa-b', '200.159.177.156', 761, 'TR_ENTECH_0800', '5531454002', 'OI_LD_54', '', 'OI_54', 'FLUX_PAE', '51', 'E83', '54', '55924'), (99, 8, 'sip-i-oi-internet', '200.159.177.61', 475, 'OI_48_LD_61', '5521480002', 'CLARO_48_LD', '', 'CLARO_FNS', 'FLUX_FNS', '251', 'G12', '48', '55924'), (100, 8, 'sip-i-oi-internet', '200.159.177.61', 534, 'OI_LC_RO_69_IN', '5514690001', 'OI_LD_RO_69', '', 'OI.RO.69', 'FLUX.RO.69', '51', 'E89', '69', '55924'), (101, 8, 'sip-i-oi-internet', '200.159.177.61', 535, 'OI_LD_RO_69_IN', '5514690002', 'OI_LD_RO_69', '', 'OI.RO.69', 'FLUX.RO.69', '51', 'E89', '69', '55924'), (102, 8, 'sip-i-oi-internet', '200.159.177.61', 566, 'OI_MANAUS_IN_92', '5521920001', 'OI_AMAZONAS_92', '', 'OI.92', 'FLUX.92', '92', 'M80', '92', '55924'), (103, 8, 'sip-i-oi-internet', '200.159.177.61', 584, 'IN_LD_71', '5514710002', 'OI_LD_71', '', 'OI.BHA', 'FLUX.BHA', '71', 'O25', '71', '55924'), (104, 8, 'sip-i-oi-internet', '200.159.177.61', 586, 'LD_73', '5514730002', 'OI_LD_73', '', 'OI.BHA', 'FLUX.BHA', '71', 'O25', '73', '55924'), (105, 8, 'sip-i-oi-internet', '200.159.177.61', 588, 'LD_75', '5514750002', 'OI_LD_75', '', 'OI.BHA', 'FLUX.BHA', '71', 'O25', '75', '55924'), (106, 8, 'sip-i-oi-internet', '200.159.177.61', 590, 'LD_77', '5514770002', 'OI_LD_77', '', 'OI.BHA', 'FLUX.BHA', '71', 'O25', '77', '55924'), (107, 8, 'sip-i-oi-internet', '200.159.177.61', 608, 'OI_LC_21', '5521140001', 'OI_LC_21', '', 'OI.RJO', 'FLUX.RJO', '21', 'P41', '21', '55924'), (108, 8, 'sip-i-oi-internet', '200.159.177.61', 609, 'OI_LD_21', '5521140002', 'OI_LD_21', '', 'OI.RJO', 'FLUX.RJO', '21', 'P41', '21', '55924'), (109, 8, 'sip-i-oi-internet', '200.159.177.61', 610, 'OI_LC_22', '5522140001', 'OI_LC_22', '', 'OI.RJO', 'FLUX.RJO', '21', 'P41', '22', '55924'), (110, 8, 'sip-i-oi-internet', '200.159.177.61', 611, 'OI_LD_22', '5522140002', 'OI_LD_22', '', 'Oi.RJO', 'FLUX.RJO', '21', 'P41', '22', '55924'), (111, 8, 'sip-i-oi-internet', '200.159.177.61', 612, 'OI_LC_24', '5524140001', 'OI_LC_24', '', 'OI.RJO', 'FLUX.RJO', '21', 'P41', '24', '55924'), (112, 8, 'sip-i-oi-internet', '200.159.177.61', 613, 'OI_LD_24', '5524140002', 'OI_LD_24', '', 'OI.RJO', 'FLUX.RJO', '21', 'P41', '24', '55924'), (113, 8, 'sip-i-oi-internet', '200.159.177.61', 681, 'LC47_OI', '5514470001', 'LC47', '', 'OI.FNS', 'FLUX.FNS', '47', 'G12', '47', '55924'), (114, 8, 'sip-i-oi-internet', '200.159.177.61', 682, 'LD47_OI', '5514470002', 'LD47', '', 'OI.FNS', 'FLUX.FNS', '47', 'G12', '47', '55924'), (115, 8, 'sip-i-oi-internet', '200.159.177.61', 683, 'LC49_OI', '5514490001', 'LC49', '', 'OI.FNS', 'FLUX.FNS', '47', 'G12', '49', '55924'), (116, 8, 'sip-i-oi-internet', '200.159.177.61', 686, 'LC27OI', '5521140001', 'OI_LC_21', '', 'OI.RJO', 'FLUX.RJO', '21', 'P41', '21', '55924'), (117, 8, 'sip-i-oi-internet', '200.159.177.61', 732, 'LD74_OI', '5514740002', 'LD74', '', 'OI.BAH', 'FLUX.BAH', '71', 'O25', '74', '55924'), (118, 9, 'sip-i-oi-bhe-a', '200.159.177.176', 293, 'CNG_LD_sCSP_OI_A_BHE_31_IN', '5514310003', 'OI_CNG_0800', '', 'OI_BHE_31', 'FLUX_BH_31', '51', 'E88', '31', '55924'), (119, 9, 'sip-i-oi-bhe-a', '200.159.177.176', 429, 'LC_OI_A_BHE_31_IN', '5514310001', 'OI_LC_BH_31', '', 'OI_BH_31', 'FLUX_BH_31', '51', 'E88', '31', '55924'), (120, 9, 'sip-i-oi-bhe-a', '200.159.177.176', 430, 'LD_OI_A_BHE_31_IN', '5514310002', 'OI_LD_BH_31', '', 'OI_BH_31', 'FLUX_BH_31', '51', 'E88', '31', '55924'), (121, 9, 'sip-i-oi-bhe-a', '200.159.177.176', 446, 'LC_OI_A_BHE_35_IN', '5514350001', 'OI_BHE_CN35', '', 'OI.BHE', 'FLUX_BHE', '51', 'E88', '35', '55924'), (122, 9, 'sip-i-oi-bhe-a', '200.159.177.176', 447, 'LD_OI_A_BHE_35_IN', '5514350002', 'OI_LD_BHE_CN35', '', 'OI.BHE', 'FLUX.BHE', '51', 'E88', '35', '55924'), (123, 9, 'sip-i-oi-bhe-a', '200.159.177.176', 619, 'OUT_LC_OI_A_BHE_31_IN', '5514310001', 'OI_LC_BH_31', '', 'OI_BH_31', 'FLUX_BH_31', '51', 'E88', '31', '55924'), (124, 9, 'sip-i-oi-bhe-a', '200.159.177.176', 663, 'LC32', '5514320001', 'LC32', '', 'OI.BHE', 'FLUX.BHE', '31', 'E88', '32', '55924'), (125, 9, 'sip-i-oi-bhe-a', '200.159.177.176', 664, 'LC33', '5514330001', 'LC33', '', 'OI.BHE', 'FLUX.BHE', '31', 'E88', '33', '55924'), (126, 9, 'sip-i-oi-bhe-a', '200.159.177.176', 665, 'LC34', '5514340001', 'LC34', '', 'OI.BHE', 'FLUX.BHE', '31', 'E88', '34', '55924'), (127, 9, 'sip-i-oi-bhe-a', '200.159.177.176', 666, 'LC37', '5514370001', 'LC37', '', 'OI.BHE', 'FLUX.BHE', '31', 'E88', '37', '55924'), (128, 9, 'sip-i-oi-bhe-a', '200.159.177.176', 667, 'LC38', '5514380001', 'LC38', '', 'OI.BHE', 'FLUX.BHE', '31', 'E88', '38', '55924'), (129, 10, 'sip-i-tim-a-5x', '200.159.177.154', 207, 'TIM_LC_STFC', '5541510001', 'TIM_51', '', 'TIM_POA', 'FLUX_POA', '207', 'E83', '51', '55924'), (130, 10, 'sip-i-tim-a-5x', '200.159.177.154', 215, 'TIM_LC_SMP', '5541510001', 'TIM_51', '', 'TIM_POA', 'FLUX_POA', '207', 'E83', '51', '55924'), (131, 10, 'sip-i-tim-a-5x', '200.159.177.154', 216, 'TIM_LD_SMP', '5541510001', 'TIM_51', '', 'TIM_POA', 'FLUX_POA', '207', 'E83', '51', '55924'), (132, 10, 'sip-i-tim-a-5x', '200.159.177.154', 235, 'TIM_LC_STFC2', '5541510001', 'TIM_51', '', 'TIM_POA', 'FLUX_POA', '207', 'E83', '51', '55924'), (133, 10, 'sip-i-tim-a-5x', '200.159.177.154', 240, 'TIM_LC_54_SMP', '5541510001', 'TIM_51', '', 'TIM_POA', 'FLUX_POA', '207', 'E83', '51', '55924'), (134, 10, 'sip-i-tim-a-5x', '200.159.177.154', 241, 'TIM_LD_54_SMP', '5541510001', 'TIM_51', '', 'TIM_POA', 'FLUX_POA', '207', 'E83', '51', '55924'), (135, 10, 'sip-i-tim-a-5x', '200.159.177.154', 245, 'TIM_PLT_53_LC', '5541530001', 'TIM_53', '', 'TIM_PLT', 'FLUX_PLT', '207', 'E83', '53', '55924'), (136, 10, 'sip-i-tim-a-5x', '200.159.177.154', 246, 'TIM_PLT_53_LD', '5541530002', 'TIM_53_LD', '', 'TIM_PLT', 'FLUX_PLT', '207', 'E83', '53', '55924'), (137, 10, 'sip-i-tim-a-5x', '200.159.177.154', 249, 'TIM_SMA_55_LC', '5541550001', 'TIM_55_LC', '', 'TIM_SMA', 'FLUX_SMA', '207', 'E83', '55', '55924'), (138, 10, 'sip-i-tim-a-5x', '200.159.177.154', 250, 'TIM_SMA_55_LD', '5541550002', 'TIM_55_LD', '', 'TIM_SMA', 'FLUX_SMA', '207', 'E83', '55', '55924'), (139, 10, 'sip-i-tim-a-5x', '200.159.177.154', 255, 'TIM_LC_SC_48_A', '5541480001', 'TIM_48_LC', '', 'TIM_FNS', 'FLUX_FNS', '207', 'E83', '48', '55924'), (140, 10, 'sip-i-tim-a-5x', '200.159.177.154', 256, 'TIM_LD_SC_48_A', '5541480002', 'TIM_48_LD', '', 'TIM_FNS', 'FLUX_FNS', '207', 'E83', '48', '55924'), (141, 10, 'sip-i-tim-a-5x', '200.159.177.154', 379, 'TIM_LC_54_OUT', '5541510001', 'TIM_51', '', 'TIM_POA', 'FLUX_POA', '207', 'E83', '51', '55924'), (142, 10, 'sip-i-tim-a-5x', '200.159.177.154', 514, 'TIM_LC51_OUT_STFC', '5541510001', 'TIM_51', '', 'TIM_POA', 'FLUX_POA', '207', 'E83', '51', '55924'), (143, 11, 'sip-i-poa-a', '200.159.177.195', 125, 'OI_LC_STFC_51', '5531451001', 'OI_LC_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (144, 11, 'sip-i-poa-a', '200.159.177.195', 126, 'OI_LD_STFC_51', '5531451002', 'OI_LD_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (145, 11, 'sip-i-poa-a', '200.159.177.195', 129, 'OI_LC_STFC_53', '5531453001', 'OI_LC_53', '', 'OI_53', 'FLUX_PAE', '51', 'E83', '53', '55924'), (146, 11, 'sip-i-poa-a', '200.159.177.195', 131, 'OI_LD_STFC_53', '5531453002', 'OI_LD_53', '', 'OI_53', 'FLUX_PAE', '51', 'E83', '53', '55924'), (147, 11, 'sip-i-poa-a', '200.159.177.195', 133, 'OI_LC_STFC_54', '5531454001', 'OI_LC_54', '', 'OI_54', 'FLUX_PAE', '51', 'E83', '54', '55924'), (148, 11, 'sip-i-poa-a', '200.159.177.195', 135, 'OI_LD_STFC_54', '5531454002', 'OI_LD_54', '', 'OI_54', 'FLUX_PAE', '51', 'E83', '54', '55924'), (149, 11, 'sip-i-poa-a', '200.159.177.195', 137, 'OI_LC_STFC_55', '5531455001', 'OI_LC_55', '', 'OI_55', 'FLUX_PAE', '51', 'E83', '55', '55924'), (150, 11, 'sip-i-poa-a', '200.159.177.195', 139, 'OI_LD_STFC_55', '5531455002', 'OI_LC_55', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '55', '55924'), (151, 11, 'sip-i-poa-a', '200.159.177.195', 140, 'OI_LD_STFC_55_CO11', '5531455002', 'OI_LC_55', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '55', '55924'), (152, 11, 'sip-i-poa-a', '200.159.177.195', 141, 'OI_LD_STFC_53_ESPECIAIS', '5531453002', 'OI_LD_53', '', 'OI_53', 'FLUX_PAE', '51', 'E83', '53', '55924'), (153, 11, 'sip-i-poa-a', '200.159.177.195', 143, 'OI_LD_STFC_51_ESPECIAIS', '5531451002', 'OI_LD_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (154, 11, 'sip-i-poa-a', '200.159.177.195', 145, 'OI_LD_STFC_54_ESPECIAIS', '5531454002', 'OI_LD_54', '', 'OI_54', 'FLUX_PAE', '51', 'E83', '54', '55924'), (155, 11, 'sip-i-poa-a', '200.159.177.195', 147, 'OI_LD_STFC_55_ESPECIAIS', '5531455002', 'OI_LC_55', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '55', '55924'), (156, 11, 'sip-i-poa-a', '200.159.177.195', 148, 'OI_LD_STFC_55_ESPECIAIS_CO11', '5531455002', 'OI_LC_55', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '55', '55924'), (157, 11, 'sip-i-poa-a', '200.159.177.195', 149, 'OI_LC_STFC_0800', '5531454001', 'OI_LC_54', '', 'OI_54', 'FLUX_PAE', '51', 'E83', '54', '55924'), (158, 11, 'sip-i-poa-a', '200.159.177.195', 150, 'OI_LC_STFC_53_0800', '5531453001', 'OI_LC_53', '', 'OI_53', 'FLUX_PAE', '51', 'E83', '53', '55924'), (159, 11, 'sip-i-poa-a', '200.159.177.195', 158, 'OI_LC_STFC_54_ACOBRAR', '5531454001', 'OI_LC_54', '', 'OI_54', 'FLUX_PAE', '51', 'E83', '54', '55924'), (160, 11, 'sip-i-poa-a', '200.159.177.195', 160, 'OI_LD_STFC_54_ACOBRAR', '5531454002', 'OI_LD_54', '', 'OI_54', 'FLUX_PAE', '51', 'E83', '54', '55924'), (161, 11, 'sip-i-poa-a', '200.159.177.195', 205, 'OI_LD_STFC_51_CLARO', '5531451002', 'OI_LD_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (162, 11, 'sip-i-poa-a', '200.159.177.195', 219, 'OI_TR_CSL_11840_VC2/3-espec', '5531454001', 'OI_LC_54', '', 'OI_54', 'FLUX_PAE', '51', 'E83', '54', '55924'), (163, 11, 'sip-i-poa-a', '200.159.177.195', 229, 'OI_TR_STFC_51_CORP', '5531451001', 'OI_LC_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (164, 11, 'sip-i-poa-a', '200.159.177.195', 299, 'OI_LC_STFC_4xxx', '5541510001', 'TIM_51', '', 'TIM_POA', 'FLUX_POA', '207', 'E83', '51', '55924'), (165, 11, 'sip-i-poa-a', '200.159.177.195', 300, 'OI_TR_3XXX', '5511530002', 'VIVO_TR_STFC_CO', '', 'SPO.CO', 'FLUXSPO', '11', 'E89', '11', '55924'), (166, 11, 'sip-i-poa-a', '200.159.177.195', 303, 'OI_TR_CSL_11840_teste', '7030102031', 'OI_TR_11840', '', 'OI_11840', 'FLUX_CSL', '51', 'E83', '54', '55924'), (167, 11, 'sip-i-poa-a', '200.159.177.195', 314, 'OI_LD_STFC_54teste', '5531454002', 'OI_LD_54', '', 'OI_54', 'FLUX_PAE', '51', 'E83', '54', '55924'), (168, 11, 'sip-i-poa-a', '200.159.177.195', 388, 'OI_LD_STFC_interceptao', '5531451002', 'OI_LD_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (169, 11, 'sip-i-poa-a', '200.159.177.195', 434, 'OI_TR_STFC_51_MOVEL_195', '5531451001', 'OI_LC_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (170, 11, 'sip-i-poa-a', '200.159.177.195', 536, 'OI_TR_195_0800', '5531451001', 'OI_LC_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (171, 11, 'sip-i-poa-a', '200.159.177.195', 594, 'OI_TR_SIP195_TRANSPORTE', '5531451001', 'OI_LC_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (172, 12, 'sip-i-oi-bhe-b', '200.159.177.119', 287, 'LC_OI_A_BHE_31_IN', '5514310001', 'OI_LC_BH_31', '', 'OI_BH_31', 'FLUX_BH_31', '51', 'E88', '31', '55924'), (173, 12, 'sip-i-oi-bhe-b', '200.159.177.119', 288, 'LC_OI_B_BHE_31_IN', '5514310001', 'OI_LC_BH_31', '', 'OI_BH_31', 'FLUX_BH_31', '51', 'E88', '31', '55924'), (174, 12, 'sip-i-oi-bhe-b', '200.159.177.119', 289, 'LD_OI_A_BHE_31_IN', '5514310002', 'OI_LD_BH_31', '', 'OI_BH_31', 'FLUX_BH_31', '51', 'E88', '31', '55924'), (175, 12, 'sip-i-oi-bhe-b', '200.159.177.119', 290, 'LD_OI_B_BHE_31_IN', '5514310002', 'OI_LD_BH_31', '', 'OI_BH_31', 'FLUX_BH_31', '51', 'E88', '31', '55924'), (176, 12, 'sip-i-oi-bhe-b', '200.159.177.119', 291, 'CNG_LD_sCSP_OI_A_BHE_31_IN', '5514310003', 'OI_CNG_0800', '', 'OI_BHE_31', 'FLUX_BH_31', '51', 'E88', '31', '55924'), (177, 12, 'sip-i-oi-bhe-b', '200.159.177.119', 292, 'CNG_LD_sCSP_OI_B_BHE_31_IN', '5514310003', 'OI_CNG_0800', '', 'OI_BHE_31', 'FLUX_BH_31', '51', 'E88', '31', '55924'), (178, 12, 'sip-i-oi-bhe-b', '200.159.177.119', 413, 'LC_OI_A_BHE_31__ESPECIAIS_OUT', '5514310001', 'OI_LC_BH_31', '', 'OI_BH_31', 'FLUX_BH_31', '51', 'E88', '31', '55924'), (179, 12, 'sip-i-oi-bhe-b', '200.159.177.119', 449, 'LC_OI_B_BHE_35_IN', '5514350001', 'OI_BHE_CN35', '', 'OI.BHE', 'FLUX_BHE', '51', 'E88', '35', '55924'), (180, 12, 'sip-i-oi-bhe-b', '200.159.177.119', 450, 'LD_OI_B_BHE_35_IN', '5514350002', 'OI_LD_BHE_CN35', '', 'OI.BHE', 'FLUX.BHE', '51', 'E88', '35', '55924'), (181, 12, 'sip-i-oi-bhe-b', '200.159.177.119', 493, 'OI_0800_TR_BH-119_TESTE', '5514310003', 'OI_CNG_0800', '', 'OI_BHE_31', 'FLUX_BH_31', '51', 'E88', '31', '55924'), (182, 12, 'sip-i-oi-bhe-b', '200.159.177.119', 576, 'OI_STFC_testeee', '5514310003', 'OI_CNG_0800', '', 'OI_BHE_31', 'FLUX_BH_31', '51', 'E88', '31', '55924'), (183, 12, 'sip-i-oi-bhe-b', '200.159.177.119', 669, 'LC32', '5514320001', 'LC32', '', 'OI.BHE', 'FLUX.BHE', '31', 'E88', '32', '55924'), (184, 12, 'sip-i-oi-bhe-b', '200.159.177.119', 670, 'LC33', '5514330001', 'LC33', '', 'OI.BHE', 'FLUX.BHE', '31', 'E88', '33', '55924'), (185, 12, 'sip-i-oi-bhe-b', '200.159.177.119', 671, 'LC34', '5514340001', 'LC34', '', 'OI.BHE', 'FLUX.BHE', '31', 'E88', '34', '55924'), (186, 12, 'sip-i-oi-bhe-b', '200.159.177.119', 672, 'LC37', '5514370001', 'LC37', '', 'OI.BHE', 'FLUX.BHE', '31', 'E88', '37', '55924'), (187, 12, 'sip-i-oi-bhe-b', '200.159.177.119', 673, 'LC38', '5514380001', 'LC38', '', 'OI.BHE', 'FLUX.BHE', '31', 'E88', '38', '55924'), (188, 13, 'sip-i-algar', '200.159.177.168', 637, 'Algar', '9050203012', 'Algar_CN_11', '', 'ALGAR_FLUX', 'FLUX_ALGAR', '143', 'E89', '11', '55924'), (189, 14, 'sip-i-oi-co', '200.159.177.137', 305, 'LC_OI_MS_67_IN_B', '5514670001', 'LC_OI_MS', '', 'OI.MS', 'FLUX.MS', '51', 'Q09', '67', '55924'), (190, 14, 'sip-i-oi-co', '200.159.177.137', 306, 'LD_OI_MS_67_IN_B', '5514670002', 'LD_OI_MS', '', 'OI.MS', 'FLUX.MS', '51', 'Q09', '67', '55924'), (191, 14, 'sip-i-oi-co', '200.159.177.137', 307, 'LC_OI_MS_67_IN', '5514670001', 'LC_OI_MS', '', 'OI.MS', 'FLUX.MS', '51', 'Q09', '67', '55924'), (192, 14, 'sip-i-oi-co', '200.159.177.137', 308, 'LD_OI_MS_67_IN', '5514670002', 'LD_OI_MS', '', 'OI.MS', 'FLUX.MS', '51', 'Q09', '67', '55924'), (193, 14, 'sip-i-oi-co', '200.159.177.137', 309, 'LC_OI_MT_65_IN', '5514650001', 'LC_OI_MT', '', 'OI.MT', 'FLUX.MT', '51', 'Q10', '65', '55924'), (194, 14, 'sip-i-oi-co', '200.159.177.137', 310, 'LD_OI_MT_65_IN', '5514650002', 'LD_OI_MT', '', 'OI.MT', 'FLUX.MT', '51', 'Q10', '65', '55924'), (195, 14, 'sip-i-oi-co', '200.159.177.137', 311, 'LC_OI_MT_66_IN', '5514660001', 'LC_OI_MT_66', '', 'OI.MT', 'FLUX.MT', '51', 'Q10', '66', '55924'), (196, 14, 'sip-i-oi-co', '200.159.177.137', 312, 'LD_OI_MT_66_IN', '5514660002', 'LD_OI_MT_66', '', 'OI.MT', 'FLUX.MT', '51', 'Q10', '66', '55924'), (197, 14, 'sip-i-oi-co', '200.159.177.137', 437, 'LC_OI_GO_62_IN', '5514620001', 'LC_Oi_GO', '', 'OI.GO', 'FLUX.GO', '51', 'P57', '62', '55924'), (198, 14, 'sip-i-oi-co', '200.159.177.137', 438, 'LD_OI_GO_62_IN', '5514620002', 'LD_OI_GO', '', 'OI.GO', 'FLUX.GO', '51', 'P57', '62', '55924'), (199, 14, 'sip-i-oi-co', '200.159.177.137', 719, 'LC64_OI', '5514640001', 'LC64', '', 'OI.GO', 'FLUX.GO', '62', 'P57', '64', '55924'), (200, 14, 'sip-i-oi-co', '200.159.177.137', 720, 'LD64_OI', '5514640002', 'LD64', '', 'OI.GO', 'FLUX.GO', '62', 'P57', '64', '55924'), (201, 15, 'sip-i-geral', '200.159.177.49', 106, 'IP_CORP_AGERA_TR', '8010100220', 'AGERA_FLUX_GERAL', '', 'AGERA', 'FLUX_GERAL', '7', 'E83', '11', '55924'), (202, 15, 'sip-i-geral', '200.159.177.49', 315, 'IP_CORP_AGERA_TR51', '8010100220', 'AGERA_FLUX_GERAL', '', 'AGERA', 'FLUX_GERAL', '7', 'E83', '11', '55924'), (203, 15, 'sip-i-geral', '200.159.177.49', 432, 'TVN_ITX_DIRETA_TR', '8684800001', 'TVN_FLUX', '', 'TVN_DIR', 'FLUX_DIR', '188', 'E83', '51', '55924'), (204, 15, 'sip-i-geral', '200.159.177.49', 644, 'ENTRADA_IDT', '8658956471', 'IDT', '', 'IDT', 'FLUX', '611', 'E89', '11', '55924'), (205, 16, 'sip-i-vivo-poa', '200.159.177.50', 107, 'VIVO_LC_STFC_PAE', '5532051001', 'VIVO_51', '', 'VIVO_51', 'FLUX_PAE', '951', 'E83', '51', '55924'), (206, 16, 'sip-i-vivo-poa', '200.159.177.50', 108, 'VIVO_LD_SFC_15_CNG_PAE', '5532051002', 'VIVO_LD_15_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (207, 16, 'sip-i-vivo-poa', '200.159.177.50', 109, 'VIVO_LD_STFC_SCP_CNG_PAE', '5532051003', 'VIVO_LD_SCP_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (208, 16, 'sip-i-vivo-poa', '200.159.177.50', 110, 'VIVO_TR_STFC_SCP_PAE', '5532051004', 'VIVO_TR_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (209, 16, 'sip-i-vivo-poa', '200.159.177.50', 111, 'VIVO_VC1_SMP_51_PAE', '5532051005', 'VIVO_VC1_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (210, 16, 'sip-i-vivo-poa', '200.159.177.50', 112, 'VIVO_LC_STFC_CONC_PAE', '5532051006', 'VIVO_LC_CONC_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (211, 16, 'sip-i-vivo-poa', '200.159.177.50', 113, 'VIVO_LD_STFC_15_CONC_PAE', '5532051007', 'VIVO_LD_15_CONC_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (212, 16, 'sip-i-vivo-poa', '200.159.177.50', 114, 'VIVO_LD_STFC_SCP_CONC_CNG_PAE', '5532051008', 'VIVO_LD_CONC_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (213, 16, 'sip-i-vivo-poa', '200.159.177.50', 115, 'VIVO_LD_SFC_15_ESP_PAE', '5532051002', 'VIVO_LD_15_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (214, 16, 'sip-i-vivo-poa', '200.159.177.50', 116, 'VIVO_TR_SMP_SCP_PAE', '5532051004', 'VIVO_TR_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (215, 16, 'sip-i-vivo-poa', '200.159.177.50', 117, 'VIVO_CNG_PAE', '5532051002', 'VIVO_LD_15_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (216, 16, 'sip-i-vivo-poa', '200.159.177.50', 118, 'VIVO_0300_PAE', '5532051002', 'VIVO_LD_15_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (217, 16, 'sip-i-vivo-poa', '200.159.177.50', 119, 'VIVO_LD_SFC_15_ACOBRAR_PAE', '5532051002', 'VIVO_LD_15_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (218, 16, 'sip-i-vivo-poa', '200.159.177.50', 121, 'VIVO_LD_SFC_15_ESP_PAE_CONC', '5532051007', 'VIVO_LD_15_CONC_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (219, 16, 'sip-i-vivo-poa', '200.159.177.50', 122, 'VIVO_0800_SFC_PAE_CONC', '5532051007', 'VIVO_LD_15_CONC_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (220, 16, 'sip-i-vivo-poa', '200.159.177.50', 123, 'VIVO_0300_SFC_PAE_CONC', '5532051007', 'VIVO_LD_15_CONC_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (221, 16, 'sip-i-vivo-poa', '200.159.177.50', 124, 'VIVO_LD_SFC_15_ACOBRAR_PAE_CONC', '5532051007', 'VIVO_LD_15_CONC_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (222, 16, 'sip-i-vivo-poa', '200.159.177.50', 227, 'VIVO_TR_SMP_SCP_PAE_CORP', '5532051004', 'VIVO_TR_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (223, 16, 'sip-i-vivo-poa', '200.159.177.50', 230, 'VIVO_TR_STFC_SCP_PAE_CORP', '5532051004', 'VIVO_TR_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (224, 16, 'sip-i-vivo-poa', '200.159.177.50', 280, 'OI_LC_STFC_51_2', '5531451001', 'OI_LC_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (225, 16, 'sip-i-vivo-poa', '200.159.177.50', 297, 'VIVO_TR_STFC_SCP_PAE_051', '5532051004', 'VIVO_TR_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (226, 16, 'sip-i-vivo-poa', '200.159.177.50', 301, 'OI_TR_3XXX2', '5511530002', 'VIVO_TR_STFC_CO', '', 'SPO.CO', 'FLUXSPO', '11', 'E89', '11', '55924'), (227, 16, 'sip-i-vivo-poa', '200.159.177.50', 302, 'VIVO_LDN_4926_NHO_55', '1216110090', 'OI_LDN_4926_NHO', '', 'PAE_OI_02', 'FLUXNHO1', '52', 'B77', '51', '55845'), (228, 16, 'sip-i-vivo-poa', '200.159.177.50', 317, 'VIVO_TR_SMP_SCP_PAE_CORP_SMP_NOVA', '5532051004', 'VIVO_TR_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (229, 16, 'sip-i-vivo-poa', '200.159.177.50', 318, 'VIVO_TR_SMP_SCP_PAE_CORP_FIXO_NOVA', '5532051004', 'VIVO_TR_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (230, 16, 'sip-i-vivo-poa', '200.159.177.50', 362, 'VIVO_VC1_SMP_51_PAE2', '5532051005', 'VIVO_VC1_PAE', '', 'VIVO_PAE', 'FLUX_PAE', '951', 'E83', '51', '55924'), (231, 17, 'sip-i-tim-b', '200.159.177.150', 234, 'TIM_LC_SMP2', '5541510001', 'TIM_51', '', 'TIM_POA', 'FLUX_POA', '207', 'E83', '51', '55924'), (232, 17, 'sip-i-tim-b', '200.159.177.150', 236, 'TIM_LD_SMP2', '5541510001', 'TIM_51', '', 'TIM_POA', 'FLUX_POA', '207', 'E83', '51', '55924'), (233, 17, 'sip-i-tim-b', '200.159.177.150', 242, 'TIM_LC54_STFC2', '5541510001', 'TIM_51', '', 'TIM_POA', 'FLUX_POA', '207', 'E83', '51', '55924'), (234, 17, 'sip-i-tim-b', '200.159.177.150', 247, 'TIM_SMA_53_LC_rotaB', '5541530001', 'TIM_53', '', 'TIM_PLT', 'FLUX_PLT', '207', 'E83', '53', '55924'), (235, 17, 'sip-i-tim-b', '200.159.177.150', 248, 'TIM_SMA_53_LD_rotaB', '5541530002', 'TIM_53_LD', '', 'TIM_PLT', 'FLUX_PLT', '207', 'E83', '53', '55924'), (236, 17, 'sip-i-tim-b', '200.159.177.150', 251, 'TIM_SMA_55_LC_rotaB', '5541550001', 'TIM_55_LC', '', 'TIM_SMA', 'FLUX_SMA', '207', 'E83', '55', '55924'), (237, 17, 'sip-i-tim-b', '200.159.177.150', 252, 'TIM_SMA_55_LD_rotaB', '5541550002', 'TIM_55_LD', '', 'TIM_SMA', 'FLUX_SMA', '207', 'E83', '55', '55924'), (238, 17, 'sip-i-tim-b', '200.159.177.150', 253, 'TIM_LC_SC_48_B', '5541480001', 'TIM_48_LC', '', 'TIM_FNS', 'FLUX_FNS', '207', 'E83', '48', '55924'), (239, 17, 'sip-i-tim-b', '200.159.177.150', 254, 'TIM_LD_SC_48_B', '5541480002', 'TIM_48_LD', '', 'TIM_FNS', 'FLUX_FNS', '207', 'E83', '48', '55924'), (240, 17, 'sip-i-tim-b', '200.159.177.150', 646, 'LC_TIM_B_CN_11_IN', '5541110001', 'TIM_11_LC', '', 'TIM_SPO', 'FLUX_SPO', '207', 'E89', '11', '55924'), (241, 17, 'sip-i-tim-b', '200.159.177.150', 647, 'LD_TIM_B_CN_11_IN', '5541110002', 'TIM_11_LD', '', 'TIM_SPO', 'FLUX_SPO', '207', 'E83', '11', '55924'), (242, 17, 'sip-i-tim-b', '200.159.177.150', 648, 'LC_TIM_B_CN_31_IN', '5541310001', 'TIM_31_LC', '', 'TIM_BHE', 'FLUX_BHE', '207', 'E83', '31', '55924'), (243, 17, 'sip-i-tim-b', '200.159.177.150', 649, 'LD_TIM_B_CN_31_IN', '5541310002', 'TIM_31_LD', '', 'TIM_BHE', 'FLUX_BHE', '207', 'E83', '31', '55924'), (244, 18, 'sip-i-tim-11-31-b', '200.159.177.148', 262, 'TIM_LC_SP_11_B', '5541110001', 'TIM_11_LC', '', 'TIM_SPO', 'FLUX_SPO', '207', 'E89', '11', '55924'), (245, 18, 'sip-i-tim-11-31-b', '200.159.177.148', 263, 'TIM_LD_SP_11_B', '5541110002', 'TIM_11_LD', '', 'TIM_SPO', 'FLUX_SPO', '207', 'E83', '11', '55924'), (246, 18, 'sip-i-tim-11-31-b', '200.159.177.148', 264, 'TIM_LC_BHE_31_B', '5541310001', 'TIM_31_LC', '', 'TIM_BHE', 'FLUX_BHE', '207', 'E83', '31', '55924'), (247, 18, 'sip-i-tim-11-31-b', '200.159.177.148', 265, 'TIM_LD_BHE_31_B', '5541310002', 'TIM_31_LD', '', 'TIM_BHE', 'FLUX_BHE', '207', 'E83', '31', '55924'), (248, 18, 'sip-i-tim-11-31-b', '200.159.177.148', 365, 'TIM_LC_SP_11_B_ESPECIAL', '5541110001', 'TIM_11_LC', '', 'TIM_SPO', 'FLUX_SPO', '207', 'E89', '11', '55924'), (249, 18, 'sip-i-tim-11-31-b', '200.159.177.148', 530, 'TIM_LC_SP_11_teste', '5541110001', 'TIM_11_LC', '', 'TIM_SPO', 'FLUX_SPO', '207', 'E89', '11', '55924'), (250, 19, 'sip-i-tim-11-31-a', '200.159.177.149', 258, 'TIM_LC_SPO_11', '5541110001', 'TIM_11_LC', '', 'TIM_SPO', 'FLUX_SPO', '207', 'E89', '11', '55924'), (251, 19, 'sip-i-tim-11-31-a', '200.159.177.149', 259, 'TIM_LD_SPO_11', '5541110002', 'TIM_11_LD', '', 'TIM_SPO', 'FLUX_SPO', '207', 'E83', '11', '55924'), (252, 19, 'sip-i-tim-11-31-a', '200.159.177.149', 260, 'TIM_LC_BHE_31', '5541310001', 'TIM_31_LC', '', 'TIM_BHE', 'FLUX_BHE', '207', 'E83', '31', '55924'), (253, 19, 'sip-i-tim-11-31-a', '200.159.177.149', 261, 'TIM_LD_BHE_31', '5541310002', 'TIM_31_LD', '', 'TIM_BHE', 'FLUX_BHE', '207', 'E83', '31', '55924'), (254, 19, 'sip-i-tim-11-31-a', '200.159.177.149', 378, 'TIM_LC_SPO_11_OUT', '5541110001', 'TIM_11_LC', '', 'TIM_SPO', 'FLUX_SPO', '207', 'E89', '11', '55924'), (255, 19, 'sip-i-tim-11-31-a', '200.159.177.149', 529, 'TIM_LC_SPO_11teste', '5541110001', 'TIM_11_LC', '', 'TIM_SPO', 'FLUX_SPO', '207', 'E89', '11', '55924'), (256, 19, 'sip-i-tim-11-31-a', '200.159.177.149', 620, 'TIM_LC_BHE_31_OUT', '5541310001', 'TIM_31_LC', '', 'TIM_BHE', 'FLUX_BHE', '207', 'E83', '31', '55924'), (257, 19, 'sip-i-tim-11-31-a', '200.159.177.149', 650, 'LC_RS_51_IN', '5541510001', 'TIM_51', '', 'TIM_POA', 'FLUX_POA', '207', 'E83', '51', '55924'), (258, 19, 'sip-i-tim-11-31-a', '200.159.177.149', 651, 'LC_RS_53_IN', '5541530001', 'TIM_53', '', 'TIM_PLT', 'FLUX_PLT', '207', 'E83', '53', '55924'), (259, 19, 'sip-i-tim-11-31-a', '200.159.177.149', 652, 'LD_RS_53_IN', '5541530002', 'TIM_53_LD', '', 'TIM_PLT', 'FLUX_PLT', '207', 'E83', '53', '55924'), (260, 19, 'sip-i-tim-11-31-a', '200.159.177.149', 653, 'LC_RS_54_IN', '5541510001', 'TIM_51', '', 'TIM_POA', 'FLUX_POA', '207', 'E83', '51', '55924'), (261, 19, 'sip-i-tim-11-31-a', '200.159.177.149', 654, 'LC_RS_55_IN', '5541550001', 'TIM_55_LC', '', 'TIM_SMA', 'FLUX_SMA', '207', 'E83', '55', '55924'), (262, 19, 'sip-i-tim-11-31-a', '200.159.177.149', 655, 'LD_RS_55_IN', '5541550002', 'TIM_55_LD', '', 'TIM_SMA', 'FLUX_SMA', '207', 'E83', '55', '55924'), (263, 19, 'sip-i-tim-11-31-a', '200.159.177.149', 656, 'LC_SC_48__IN', '5541480001', 'TIM_48_LC', '', 'TIM_FNS', 'FLUX_FNS', '207', 'E83', '48', '55924'), (264, 19, 'sip-i-tim-11-31-a', '200.159.177.149', 657, 'LD_SC_48_IN', '5541480002', 'TIM_48_LD', '', 'TIM_FNS', 'FLUX_FNS', '207', 'E83', '48', '55924'), (265, 20, 'sip-i-oi-r1', '200.159.177.90', 687, 'LD27OI', '5514270002', 'LD27', '', 'OI.ES', 'FLUX.ES', '27', 'S86', '27', '55924'), (266, 20, 'sip-i-oi-r1', '200.159.177.90', 688, 'LC28OI', '5514280001', 'LC28', '', 'OI.ES', 'FLUX.ES', '27', 'S86', '28', '55924'), (267, 20, 'sip-i-oi-r1', '200.159.177.90', 689, 'LD28OI', '5514280002', 'LD28', '', 'OI.ES', 'FLUX.ES', '27', 'S86', '28', '55924'), (268, 20, 'sip-i-oi-r1', '200.159.177.90', 690, 'LC27OI', '5514270001', 'LC27', '', 'OI.ES', 'FLUX.ES', '27', 'S86', '27', '55924'), (269, 20, 'sip-i-oi-r1', '200.159.177.90', 692, 'OI_LC61_IN', '5514610001', 'LC61', '', 'OI.DF', 'FLUX.DF', '61', 'Q11', '61', '55924'), (270, 20, 'sip-i-oi-r1', '200.159.177.90', 693, 'OI_LD61_IN', '5514610002', 'LD61', '', 'OI.DF', 'FLUX.DF', '61', 'Q11', '61', '55924'), (271, 20, 'sip-i-oi-r1', '200.159.177.90', 700, 'LC81_OI', '5514810001', 'LC81', '', 'OI.PE', 'FLUX.PE', 'S72', '81', '81', '55924'), (272, 20, 'sip-i-oi-r1', '200.159.177.90', 701, 'LD81_OI', '5514810002', 'LD81', '', 'OI.PE', 'FLUX.PE', 'S72', '81', '81', '55924'), (273, 20, 'sip-i-oi-r1', '200.159.177.90', 702, 'LC86_OI', '5514860001', 'LC86', '', 'OI.PI', 'FLUX.PI', '86', 'S69', '86', '55924'), (274, 20, 'sip-i-oi-r1', '200.159.177.90', 703, 'LD86', '5514860002', 'LD86', '', 'OI.PI', 'FLUX.PI', '86', 'S69', '86', '55924'), (275, 20, 'sip-i-oi-r1', '200.159.177.90', 704, 'LC87_OI', '5514870001', 'LC87', '', 'OI.PE', 'FLUX.PE', '81', 'S72', '87', '55924'), (276, 20, 'sip-i-oi-r1', '200.159.177.90', 705, 'LD87_OI', '5514870002', 'LD87', '', 'OI.PE', 'FLUX.PE', '81', 'S72', '87', '55924'), (277, 20, 'sip-i-oi-r1', '200.159.177.90', 706, 'LC89_OI', '5514890001', 'LC89', '', 'OI.PI', 'FLUX.PI', '86', 'S69', '89', '55924'), (278, 20, 'sip-i-oi-r1', '200.159.177.90', 707, 'LD89_OI', '5514890002', 'LD89', '', 'OI.PI', 'FLUX.PI', '86', 'S69', '89', '55924'), (279, 20, 'sip-i-oi-r1', '200.159.177.90', 721, 'LC98_OI', '5514980001', 'LC98', '', 'OI.MA', 'FLUX.MA', '98', 'S70', '98', '55924'), (280, 20, 'sip-i-oi-r1', '200.159.177.90', 722, 'LD98_OI', '5514980002', 'LD98', '', 'OI.MA', 'FLUX.MA', '98', 'S70', '98', '55924'), (281, 20, 'sip-i-oi-r1', '200.159.177.90', 723, 'LC68_OI', '5514680001', 'LC68', '', 'OI.AC', 'FLUX.AC', '93', 'S71', '68', '55924'), (282, 20, 'sip-i-oi-r1', '200.159.177.90', 724, 'LD68_OI', '5514680002', 'LD68', '', 'OI.AC', 'FLUX.AC', '93', 'S71', '68', '55924'), (283, 20, 'sip-i-oi-r1', '200.159.177.90', 725, 'LD91_OI', '5514910002', 'LD91', '', 'OI.PA', 'FLUX.PA', '91', 'S68', '91', '55924'), (284, 20, 'sip-i-oi-r1', '200.159.177.90', 726, 'LC93_OI', '5514930001', 'LC93', '', 'OI.PA', 'FLUX.PA', '91', 'S68', '93', '55924'), (285, 20, 'sip-i-oi-r1', '200.159.177.90', 728, 'LC94_OI', '5514940001', 'LC94', '', 'OI.PA', 'FLUX.PA', '91', 'S68', '94', '55924'), (286, 20, 'sip-i-oi-r1', '200.159.177.90', 729, 'LD94_OI', '5514940002', 'LD94', '', 'OI.PA', 'FLUX.PA', '91', 'S68', '94', '55924'), (287, 20, 'sip-i-oi-r1', '200.159.177.90', 730, 'LC99_OI', '5514990001', 'LC99', '', 'OI.MA', 'FLUX.MA', '98', 'S70', '99', '55924'), (288, 20, 'sip-i-oi-r1', '200.159.177.90', 731, 'LD99_OI', '5514990002', 'LD99', '', 'OI.MA', 'FLUX.MA', '98', 'S70', '99', '55924'), (289, 20, 'sip-i-oi-r1', '200.159.177.90', 733, 'LD93_OI', '5514930002', 'LD93', '', 'OI.PA', 'FLUX.PA', '91', 'S68', '93', '55924'), (290, 21, 'sip-i-oi-rj', '200.159.177.95', 694, 'IN_LC_21_IN_sip95', '5521140001', 'OI_LC_21', '', 'OI.RJO', 'FLUX.RJO', '21', 'P41', '21', '55924'), (291, 21, 'sip-i-oi-rj', '200.159.177.95', 695, 'IN_LD_21_OI_sip95', '5521140002', 'OI_LD_21', '', 'OI.RJO', 'FLUX.RJO', '21', 'P41', '21', '55924'), (292, 21, 'sip-i-oi-rj', '200.159.177.95', 696, 'IN_LC_22', '5522140001', 'OI_LC_22', '', 'OI.RJO', 'FLUX.RJO', '21', 'P41', '22', '55924'), (293, 21, 'sip-i-oi-rj', '200.159.177.95', 697, 'IN_LD_22', '5522140002', 'OI_LD_22', '', 'Oi.RJO', 'FLUX.RJO', '21', 'P41', '22', '55924'), (294, 21, 'sip-i-oi-rj', '200.159.177.95', 698, 'IN_LC_24', '5524140001', 'OI_LC_24', '', 'OI.RJO', 'FLUX.RJO', '21', 'P41', '24', '55924'), (295, 21, 'sip-i-oi-rj', '200.159.177.95', 699, 'IN_LD_24', '5524140002', 'OI_LD_24', '', 'OI.RJO', 'FLUX.RJO', '21', 'P41', '24', '55924'), (296, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 266, 'LC_CLARO_SP_11', '5521110001', 'CLARO_11_VC1', '', 'CLARO_SPO', 'FLUX_SPO', '251', 'E89', '11', '55924'), (297, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 267, 'LD_CLARO_SP_11', '5521110002', 'CLARO_11_LD', '', 'CLARO_SPO', 'FLUX_SPO', '251', 'E89', '11', '55924'), (298, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 268, 'LD_sCSP_CLARO_SP_11', '5521110003', 'CLARO_11_LD_sCSP', '', 'CLARO_SPO', 'FLUX_SPO', '251', 'E89', '11', '55924'), (299, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 269, 'LC_CLARO_MG_31', '5521310001', 'CLARO_31_LC', '', 'CLARO_BHE', 'FLUX_BHE', '251', 'E88', '31', '55924'), (300, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 270, 'LD_CLARO_MG_31', '5521310002', 'CLARO_31_LD', '', 'CLARO_BHE', 'FLUX_BHE', '251', 'E88', '31', '55924'), (301, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 271, 'LD_sCSP_CLARO_MG_31', '5521310003', 'CLARO_31_LD_sCSP', '', 'CLARO_BHE', 'FLUX_BHE', '251', 'E88', '31', '55924'), (302, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 272, 'LC_CLARO_SC_48', '5521480001', 'CLARO_48_LC', '', 'CLARO_FNS', 'FLUX_FNS', '251', 'G12', '48', '55924'), (303, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 273, 'LD_CLARO_SC_48', '5521480002', 'CLARO_48_LD', '', 'CLARO_FNS', 'FLUX_FNS', '251', 'G12', '48', '55924'), (304, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 274, 'LD_sCSP_CLARO_SC_48', '5521480003', 'CLARO_48_LD_sCSP', '', 'CLARO_FNS', 'FLUX_FNS', '251', 'G12', '48', '55924'), (305, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 276, 'VC1_CLARO_SP_11', '5521110004', 'CLARO_11_VC1', '', 'CLARO_SPO', 'FLUX_SPO', '251', 'E89', '11', '55924'), (306, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 277, 'CNG_CLARO_SP_11', '5521110005', 'CLARO_11_CNG', '', 'CLARO_SPO', 'FLUX_SPO', '251', 'E89', '11', '55924'), (307, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 278, 'VC1_CLARO_MG_31', '5521310004', 'CLARO_31_VC1', '', 'CLARO_BHE', 'FLUX_BHE', '251', 'E88', '31', '55924'), (308, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 279, 'CNG_CLARO_MG_31', '5521310005', 'CLARO_31_CNG', '', 'CLARO_BHE', 'FLUX_BHE', '251', 'E88', '31', '55924'), (309, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 281, 'LC_CLARO_SP_11_OUT', '5521110001', 'CLARO_11_VC1', '', 'CLARO_SPO', 'FLUX_SPO', '251', 'E89', '11', '55924'), (310, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 282, 'LD_CLARO_SP_11_OUT', '5521110002', 'CLARO_11_LD', '', 'CLARO_SPO', 'FLUX_SPO', '251', 'E89', '11', '55924'), (311, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 283, 'LD_sCSP_CLARO_SP_11_OUT', '5521110003', 'CLARO_11_LD_sCSP', '', 'CLARO_SPO', 'FLUX_SPO', '251', 'E89', '11', '55924'), (312, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 284, 'LC_CLARO_MG_31_OUT', '5521310001', 'CLARO_31_LC', '', 'CLARO_BHE', 'FLUX_BHE', '251', 'E88', '31', '55924'), (313, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 285, 'LD_CLARO_MG_sCSP_31_OUT', '5521310003', 'CLARO_31_LD_sCSP', '', 'CLARO_BHE', 'FLUX_BHE', '251', 'E88', '31', '55924'), (314, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 286, 'LD_sCSP_CLARO_MG_31_OUT', '5521310003', 'CLARO_31_LD_sCSP', '', 'CLARO_BHE', 'FLUX_BHE', '251', 'E88', '31', '55924'), (315, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 294, 'VC1_CLARO_SP_11_OUT', '5521110004', 'CLARO_11_VC1', '', 'CLARO_SPO', 'FLUX_SPO', '251', 'E89', '11', '55924'), (316, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 295, 'CNG_CLARO_SP_11_OUT', '5521110005', 'CLARO_11_CNG', '', 'CLARO_SPO', 'FLUX_SPO', '251', 'E89', '11', '55924'), (317, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 465, 'CLARO.51_VC1', '5521510001', 'CLARO_VC1_51', '', 'CLARO.51', 'FLUX.51', '251', 'E83', '51', '55924'), (318, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 466, 'CLARO.51_CNG', '5521510002', 'CNG_FLUX_51', '', 'CLARO.51', 'FLUX.51', '251', 'E83', '51', '55924'), (319, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 467, 'CLARO.53_VC1', '5521530001', 'CLARO.VC1.53', '', 'CLARO.53', 'FLUX.53', '251', 'E83', '53', '55924'), (320, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 468, 'CLARO.53_CNG', '5521530002', 'CNG_FLUX_53', '', 'CLARO.53', 'FLUX.53', '251', 'E83', '53', '55924'), (321, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 469, 'CLARO.54_VC1', '5521540001', 'CLARO.VC1.54', '', 'CLARO.54', 'FLUX.54', '251', 'E83', '54', '55924'), (322, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 470, 'CLARO.54_CNG', '5521540002', 'CNG_FLUX.54', '', 'CLARO.54', 'FLUX.54', '251', 'E83', '54', '55924'), (323, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 471, 'CLARO.55_VC1', '5521550001', 'CLARO.VC1.55', '', 'CLARO.55', 'FLUX.55', '251', 'E83', '55', '55924'), (324, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 472, 'CLARO.55_CNG', '5521550002', 'CNG_FLUX.55', '', 'CLARO.55', 'FLUX.55', '251', 'E83', '55', '55924'), (325, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 490, 'EMBRATEL_35', '5521350005', 'LC_MG_CN35', '', 'CLARO.MG', 'FLUX_MG', '251', 'E88', '35', '55924'), (326, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 528, 'LC_CLARO_SP_11teste', '5521110001', 'CLARO_11_VC1', '', 'CLARO_SPO', 'FLUX_SPO', '251', 'E89', '11', '55924'), (327, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 538, 'LC_CLARO_SP_11_FIXO', '5521110001', 'CLARO_11_VC1', '', 'CLARO_SPO', 'FLUX_SPO', '251', 'E89', '11', '55924'), (328, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 539, 'LC_CLARO_SP_11_FIXO', '5521110001', 'CLARO_11_VC1', '', 'CLARO_SPO', 'FLUX_SPO', '251', 'E89', '11', '55924'), (329, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 545, 'CLARO.51_VC1', '5521510001', 'CLARO_VC1_51', '', 'CLARO.51', 'FLUX.51', '251', 'E83', '51', '55924'), (330, 22, 'sip-i-claro-11-31-a', '200.159.177.147', 546, 'LC_CLARO_SP_11_ESPECIAIS', '5521110001', 'CLARO_11_VC1', '', 'CLARO_SPO', 'FLUX_SPO', '251', 'E89', '11', '55924'), (331, 23, 'sip-i-adylnet', '200.159.177.241', 547, 'Adylnet_ENTRANTE', '6958542584', 'EntranteAdylnet', '', 'Adylnet.RS', 'Fux.RS', '51', 'E83', '54', '55924'), (332, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 367, 'TAMBORE_VIVO_LC_14_IN', '5520140001', 'LC14', '', 'VIVO.SPO', 'FLUX.SPO', '11', 'E89', '14', '55924'), (333, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 368, 'TAMBORE_VIVO_LD_14_CSP_IN', '5520140002', 'LD14', '', 'VIVO_SPO', 'FLUX.SPO', '11', 'E89', '14', '55924'), (334, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 369, 'TAMBORE_VIVO_LD_14_scSP_IN', '5520140002', 'LD14', '', 'VIVO_SPO', 'FLUX.SPO', '11', 'E89', '14', '55924'), (335, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 370, 'TAMBORE_VIVO_TR_14_scSP_IN', '5514140003', 'VIVO_TR_14', '', 'FLUX_SP_14', 'VIVO_SP_14', '11', 'E89', '14', '55924'), (336, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 371, 'TAMBORE_VIVO_VC1_14_IN', '5520140001', 'LC14', '', 'VIVO.SPO', 'FLUX.SPO', '11', 'E89', '14', '55924'), (337, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 372, 'TAMBORE_VIVO_LC_14_CONC_IN', '5520140001', 'LC14', '', 'VIVO.SPO', 'FLUX.SPO', '11', 'E89', '14', '55924'), (338, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 373, 'TAMBORE_VIVO_LD_14_CONC_CSP_IN', '5520140002', 'LD14', '', 'VIVO_SPO', 'FLUX.SPO', '11', 'E89', '14', '55924'), (339, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 374, 'TAMBORE_VIVO_LD_14_cSP_IN', '5520140002', 'LD14', '', 'VIVO_SPO', 'FLUX.SPO', '11', 'E89', '14', '55924'), (340, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 375, 'TAMBORE_VIVO_VC1_14_CONC_IN', '5520140001', 'LC14', '', 'VIVO.SPO', 'FLUX.SPO', '11', 'E89', '14', '55924'), (341, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 376, 'TAMBORE_VIVO_LC_14_OUT', '5520140001', 'LC14', '', 'VIVO.SPO', 'FLUX.SPO', '11', 'E89', '14', '55924'), (342, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 377, 'TAMBORE_VIVO_LC_14_CONC_OUT', '5520140001', 'LC14', '', 'VIVO.SPO', 'FLUX.SPO', '11', 'E89', '14', '55924'), (343, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 453, 'OI_LC_TR_CN_19', '5515190001', 'TR_LC_AL_CN19', '', 'VIVO.SPO', 'FLUX.SPO', '11', 'E89', '19', '55924'), (344, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 454, 'VIVO_LD_15_CN19', '5515190002', 'LD_15_CNG_CN_19', '', 'VIVO.SPO', 'FLUX.SPO', '11', 'E89', '19', '55924'), (345, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 456, 'LD S/CSP_CNG_CN_19', '5515190002', 'LD_15_CNG_CN_19', '', 'VIVO.SPO', 'FLUX.SPO', '11', 'E89', '19', '55924'), (346, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 457, 'TR LD s_CSP_CN_19', '5515190003', 'TR_LD_S_CSP_CN_19', '', 'VIVO.SPO', 'FLUX.SPO', '11', 'E89', '19', '55924'), (347, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 458, 'VC1_AL_CAS_CN_19', '5515190004', 'VC1_CN_19', '', 'VIVO.SPO', 'FLUX.SPO', 'E11', 'E89', '19', '55924'), (348, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 459, 'Concentrado_LC_AL_CAS_CN_19', '5515190005', 'CONC.LC_LD_VC_1_CN_19', '', 'VIVO.SPO', 'FLUX.SPO', '11', 'E89', '19', '55924'), (349, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 460, 'Concentrao_LD_AL_CAS_CN_19', '5514518451', 'DEFF_OI_51_LOCAL', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (350, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 461, 'Concentrao_LD_S_CSP_CN_19', '5515190005', 'CONC.LC_LD_VC_1_CN_19', '', 'VIVO.SPO', 'FLUX.SPO', '11', 'E89', '19', '55924'), (351, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 462, 'Concentrao_VC1_CN_19_AL_CAS_CN_19', '5515190005', 'CONC.LC_LD_VC_1_CN_19', '', 'VIVO.SPO', 'FLUX.SPO', '11', 'E89', '19', '55924'), (352, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 488, 'TAMBORE_VIVO_LD_14_OUT', '5520140002', 'LD14', '', 'VIVO_SPO', 'FLUX.SPO', '11', 'E89', '14', '55924'), (353, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 513, 'TAMBORE_VIVO_TR_0800', '5514140003', 'VIVO_TR_14', '', 'FLUX_SP_14', 'VIVO_SP_14', '11', 'E89', '14', '55924'), (354, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 548, 'CLARO_LC_AL_CTA_41', '4115000001', 'CLARO.PARANA.41', '', 'CLARO.CTA', 'FLUX.CTA', '941', 'P76', '41', '55924'), (355, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 549, 'LD15_CNG_41', '4115000002', 'CLARO.PARANA.41', '', 'CLARO_CTA', 'FLUX.CTA', '941', 'P76', '41', '55924'), (356, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 550, 'LD_S_CSP_CNG_OPER_41', '4115000002', 'CLARO.PARANA.41', '', 'CLARO_CTA', 'FLUX.CTA', '941', 'P76', '41', '55924'), (357, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 551, 'CLARO_TR_LD_S_CSP_41', '4115000002', 'CLARO.PARANA.41', '', 'CLARO_CTA', 'FLUX.CTA', '941', 'P76', '41', '55924'), (358, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 552, 'VC1_CN_41_AL_CTA_41', '4115000003', 'CLARO.PARANA.41', '', 'CLARO_CTA', 'FLUX.CTA', '44', 'P76', '41', '55924'), (359, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 555, 'VIVO_LC_21_RJO', '5521210001', 'VIVO_LC_RJ_21', '', 'VIVO.RJO', 'FLUX.RJO', '921', 'P41', '21', '55924'), (360, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 556, 'VIVO_LD15_21', '5521210002', 'VIVO_LD15_RJ_21', '', 'VIVO.RJO', 'FLUX.RJO', '921', 'P41', '21', '55924'), (361, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 557, 'VIVO_LD SCSP_21', '5521210002', 'VIVO_LD15_RJ_21', '', 'VIVO.RJO', 'FLUX.RJO', '921', 'P41', '21', '55924'), (362, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 558, 'VIVO_TR LD_21', '5521210003', 'VIVO_TR LD_21_RJO', '', 'VIVO.RJO', 'FLUX_RJO', '921', 'P41', '21', '55924'), (363, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 559, 'VIVO_VC1_21', '5521210004', 'VIVO_VC1_21_RJO', '', 'VIVO_RJO', 'FLUX.RJO', '20', 'P41', '21', '55924'), (364, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 560, 'VIVO_LC_71', '5520710001', 'VIVO_LC_71', '', 'VIVO.BHA', 'FLUX.BHA', '971', 'O25', '71', '55924'), (365, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 561, 'VIVO_LD15_71', '5520710001', 'VIVO_LC_71', '', 'VIVO.BHA', 'FLUX.BHA', '971', 'O25', '71', '55924'), (366, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 562, 'VIVO_LD SCSP_71', '5520710002', 'VIVO.LD SCSP_71', '', 'VIVO.BHA', 'FLUX.BHA', '971', 'O25', '71', '55924'), (367, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 563, 'VIVO_TR LD_71', '5520710003', 'VIVOTR LD_71', '', 'VIVO.BHA', 'FLUX.BHA', '971', 'O25', '71', '55924'), (368, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 564, 'VIVO_VC1_71', '5520710004', 'VIVO_VC1_71', '', 'VIVO.BHA', 'FLUX.BHA', '70', 'O25', '71', '55924'), (369, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 569, 'VIVO_LC_31', '5515310001', 'VIVO_LC_31', '', 'VIVO.MG', 'FLUX.MG', '11', 'E88', '31', '55924'), (370, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 570, 'VIVO_LD15_31', '5515310002', 'VIVO_LD_31', '', 'VIVO.MG', 'FLUX.MG', '11', 'E88', '31', '55924'), (371, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 571, 'VIVO_LD SCSP_31', '5515310002', 'VIVO_LD_31', '', 'VIVO.MG', 'FLUX.MG', '11', 'E88', '31', '55924'), (372, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 572, 'VIVO_TR LD_31', '5515310003', 'VIVO_TR LD_31', '', 'VIVO.MG', 'FLUX.MG', '11', 'E88', '31', '55924'), (373, 24, 'sip-i-vivo-tambor-cn', '200.159.177.136', 573, 'VIVO_VC1_31', '5515310004', 'VIVO_VC1_31', '', 'VIVO.MG', 'FLUX.MG', '30', 'E88', '31', '55924'), (374, 26, 'sip-i-deff', '201.20.144.225', 335, 'OI_DEFF_A_LC_51', '5514518451', 'DEFF_OI_51_LOCAL', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (375, 26, 'sip-i-deff', '201.20.144.225', 336, 'OI_DEFF_A_LD_51', '5514518452', 'DEFF_OI_51_LDN', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (376, 26, 'sip-i-deff', '201.20.144.225', 337, 'OI_DEFF_A_TR_51', '5514518453', 'DEFF_OI_51_TR', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '4926'), (377, 26, 'sip-i-deff', '201.20.144.225', 349, 'OI_DEFF_A_TR_0800_SAIDA', '5514518453', 'DEFF_OI_51_TR', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '4926'), (378, 26, 'sip-i-deff', '201.20.144.225', 350, 'OI_DEFF_A_LD_51_SAIDA', '5514518452', 'DEFF_OI_51_LDN', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (379, 26, 'sip-i-deff', '201.20.144.225', 351, 'OI_DEFF_A_LC_51_SAIDA', '5514518451', 'DEFF_OI_51_LOCAL', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (380, 26, 'sip-i-deff', '201.20.144.225', 361, 'OI_DEFF_A_LCFIXO_51_SAIDA', '5514518451', 'DEFF_OI_51_LOCAL', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (381, 26, 'sip-i-deff', '201.20.144.225', 363, 'OI_DEFF_A_TR_51_SAIDA', '5514518453', 'DEFF_OI_51_TR', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '4926'), (382, 26, 'sip-i-deff', '201.20.144.225', 364, 'OI_DEFF_A_LC_51_SMP_SAIDA', '5514518451', 'DEFF_OI_51_LOCAL', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (383, 26, 'sip-i-deff', '201.20.144.225', 428, 'OI_DEFF_A_LD_51_SAIDA048', '5514518452', 'DEFF_OI_51_LDN', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (384, 26, 'sip-i-deff', '201.20.144.225', 440, 'NEXT_OI_51_SIP_I_DEFF_3003', '5514518451', 'DEFF_OI_51_LOCAL', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (385, 26, 'sip-i-deff', '201.20.144.225', 486, 'OI_DEFF_A_TR_0300_LATAM', '5514518453', 'DEFF_OI_51_TR', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '4926'), (386, 26, 'sip-i-deff', '201.20.144.225', 517, 'OI_DEFF_B_LD_51_SAIDA_lajeado', '5514518452', 'DEFF_OI_51_LDN', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (387, 27, 'FUSION246', '200.159.177.246', 616, 'FUSION_SMP_LC_246', '5531451001', 'OI_LC_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (388, 27, 'FUSION246', '200.159.177.246', 617, 'FUSION_STFC_LC_246', '5531451001', 'OI_LC_51', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '51', '55924'), (389, 27, 'FUSION246', '200.159.177.246', 626, 'FUSION_RN3_246', '5531451010', 'TESTE060', '', 'TESTE060', 'TESTE060', '51', 'E83', '51', '55924'), (390, 31, 'Vivo54', '200.159.177.153', 208, 'VIVO_LC_CSL_54', '5531554001', 'VIVO_LC_CSL54', '', 'VIVO.CSL', 'FLUX.CSL', '11', 'E83', '54', '55924'), (391, 31, 'Vivo54', '200.159.177.153', 209, 'VIVO_54_ACOBRAR', '5531554001', 'VIVO_LC_CSL54', '', 'VIVO.CSL', 'FLUX.CSL', '11', 'E83', '54', '55924'), (392, 31, 'Vivo54', '200.159.177.153', 210, 'VIVO_CNG_CSL', '5531554001', 'VIVO_LC_CSL54', '', 'VIVO.CSL', 'FLUX.CSL', '11', 'E83', '54', '55924'), (393, 31, 'Vivo54', '200.159.177.153', 211, 'VIVO_CSL_TR_SMP_CORP', '5531554001', 'VIVO_LC_CSL54', '', 'VIVO.CSL', 'FLUX.CSL', '11', 'E83', '54', '55924'), (394, 31, 'Vivo54', '200.159.177.153', 212, 'VIVO_0300_CSL', '5531554001', 'VIVO_LC_CSL54', '', 'VIVO.CSL', 'FLUX.CSL', '11', 'E83', '54', '55924'), (395, 31, 'Vivo54', '200.159.177.153', 213, 'VIVO_ESPECIAIS_CSL', '5531554001', 'VIVO_LC_CSL54', '', 'VIVO.CSL', 'FLUX.CSL', '11', 'E83', '54', '55924'), (396, 31, 'Vivo54', '200.159.177.153', 214, 'VIVO_TR_LD_54', '5532055002', 'VIVO_LD_SMA', '', 'VIVO_SMA', 'FLUX_55', '951', 'E83', '55', '55924'), (397, 31, 'Vivo54', '200.159.177.153', 217, 'VIVO_54_LD_SCSP', '5531554001', 'VIVO_LC_CSL54', '', 'VIVO.CSL', 'FLUX.CSL', '11', 'E83', '54', '55924'), (398, 31, 'Vivo54', '200.159.177.153', 218, 'VIVO_54_LC_ACOBRAR', '5531554001', 'VIVO_LC_CSL54', '', 'VIVO.CSL', 'FLUX.CSL', '11', 'E83', '54', '55924'), (399, 31, 'Vivo54', '200.159.177.153', 220, 'VIVO_LC_CONCENTRADO_54', '5531554001', 'VIVO_LC_CSL54', '', 'VIVO.CSL', 'FLUX.CSL', '11', 'E83', '54', '55924'), (400, 31, 'Vivo54', '200.159.177.153', 221, 'VIVO_54_LD_15_CONCENTRADO', '5531554001', 'VIVO_LC_CSL54', '', 'VIVO.CSL', 'FLUX.CSL', '11', 'E83', '54', '55924'), (401, 31, 'Vivo54', '200.159.177.153', 222, 'VIVO_54_LD_CONCENTRADO_SCSP', '5531554001', 'VIVO_LC_CSL54', '', 'VIVO.CSL', 'FLUX.CSL', '11', 'E83', '54', '55924'), (402, 31, 'Vivo54', '200.159.177.153', 223, 'VIVO_54_CONCENTRADO_ACOBRAR', '5531554001', 'VIVO_LC_CSL54', '', 'VIVO.CSL', 'FLUX.CSL', '11', 'E83', '54', '55924'), (403, 31, 'Vivo54', '200.159.177.153', 224, 'VIVO_54_LD_CONCENTRADO_ESPECIAIS', '5531554001', 'VIVO_LC_CSL54', '', 'VIVO.CSL', 'FLUX.CSL', '11', 'E83', '54', '55924'), (404, 31, 'Vivo54', '200.159.177.153', 225, 'VIVO_54_LD_CONCENTRADO_0800', '5531554001', 'VIVO_LC_CSL54', '', 'VIVO.CSL', 'FLUX.CSL', '11', 'E83', '54', '55924'), (405, 31, 'Vivo54', '200.159.177.153', 226, 'VIVO_54_LD_CONCENTRADO_0300', '5531554001', 'VIVO_LC_CSL54', '', 'VIVO.CSL', 'FLUX.CSL', '11', 'E83', '54', '55924'), (406, 31, 'Vivo54', '200.159.177.153', 233, 'VIVO_CSL_TR_STFC_CORP', '5531554001', 'VIVO_LC_CSL54', '', 'VIVO.CSL', 'FLUX.CSL', '11', 'E83', '54', '55924'), (407, 31, 'Vivo54', '200.159.177.153', 239, 'VIVO_54_VC1', '5531554001', 'VIVO_LC_CSL54', '', 'VIVO.CSL', 'FLUX.CSL', '11', 'E83', '54', '55924'), (408, 32, 'SIPOI-VIAInternet', '200.159.177.60', 474, 'OI_48_LC_60', '5521480001', 'CLARO_48_LC', '', 'CLARO_FNS', 'FLUX_FNS', '251', 'G12', '48', '55924'), (409, 32, 'SIPOI-VIAInternet', '200.159.177.60', 583, 'IN_LOCAL_71', '5514710001', 'OI_LOCAL_71', '', 'OI.BHA', 'FLUX.BHA', '71', 'O25', '71', '55924'), (410, 32, 'SIPOI-VIAInternet', '200.159.177.60', 585, 'LOCAL_73', '5514730001', 'OI_LOCAL_73', '', 'OI.BHA', 'FLUX.BHA', '71', 'O25', '73', '55924'), (411, 32, 'SIPOI-VIAInternet', '200.159.177.60', 587, 'LOCAL_75', '5514750001', 'OI_LOCAL_75', '', 'OI.BHA', 'FLUX.BHA', '71', 'O25', '75', '55924'), (412, 32, 'SIPOI-VIAInternet', '200.159.177.60', 589, 'LOCAL_77', '5514770001', 'OI_LOCAL_77', '', 'OI.BHA', 'FLUX.BHA', '71', 'O25', '77', '55924'), (413, 33, 'SBC-BHE', '200.201.218.137', 45, 'OI_LC_BHE_6817', '7010103011', 'OI_LC_BHE_6817', '', 'OI_6817', 'FLUX_7809', '31', 'E88', '31', '55924'), (414, 33, 'SBC-BHE', '200.201.218.137', 46, 'OI_LC_BHE_6816', '7010103021', 'OI_LC_BHE_6816', '', 'OI_6816', 'FLUX_7809', '31', 'E88', '31', '55924'), (415, 33, 'SBC-BHE', '200.201.218.137', 50, 'OI_LDN_BHE_6817', '7020103031', 'OI_LDN_BHE_6817', '', 'OI_6817', 'FLUX_7809', '31', 'E88', '31', '55924'), (416, 33, 'SBC-BHE', '200.201.218.137', 51, 'OI_LDN_BHE_6816', '7020103041', 'OI_LDN_BHE_6816', '', 'OI_6816', 'FLUX_7809', '31', 'E88', '31', '55924'), (417, 33, 'SBC-BHE', '200.201.218.137', 55, 'OI_TR_BHE_6817_STFC', '7030103051', 'OI_TR_BHE_6817', '', 'OI_6817', 'FLUX_7809', '31', 'E88', '31', '55924'), (418, 33, 'SBC-BHE', '200.201.218.137', 56, 'OI_TR_BHE_6816', '7030103061', 'OI_TR_BHE_6816', '', 'OI_6816', 'FLUX_7809', '31', 'E88', '31', '55924'), (419, 33, 'SBC-BHE', '200.201.218.137', 74, 'OI_CNG_BHE_6816', '7020103031', 'OI_LDN_BHE_6817', '', 'OI_6817', 'FLUX_7809', '31', 'E88', '31', '55924'), (420, 33, 'SBC-BHE', '200.201.218.137', 85, 'OI_CNG_LDN_BHE_6817', '7020103041', 'OI_LDN_BHE_6816', '', 'OI_6816', 'FLUX_7809', '31', 'E88', '31', '55924'), (421, 33, 'SBC-BHE', '200.201.218.137', 101, 'DATORA-REGII', '7050103011', 'DATORA_635', '', 'DATORA_54', 'FLUX_7809', '635', 'E83', '54', '55924'), (422, 33, 'SBC-BHE', '200.201.218.137', 102, 'DATORA-REGI', '7050103021', 'DATORA_635', '', 'DATORA_31', 'FLUX_CSL', '635', 'E88', '31', '55924'), (423, 33, 'SBC-BHE', '200.201.218.137', 103, 'DATORA-REGIII', '7050103031', 'DATORA_635', '', 'DATORA_11', 'FLUX.SPO', '635', 'E89', '11', '55924'), (424, 33, 'SBC-BHE', '200.201.218.137', 104, 'DATORA-REGII-FNS', '7050103041', 'DATORA_635', '', 'DATORA_48', 'FLUX_7809', '635', 'G12', '48', '55924'), (425, 33, 'SBC-BHE', '200.201.218.137', 151, 'OI_TR_BHE_6816_STFC', '7030103061', 'OI_TR_BHE_6816', '', 'OI_6816', 'FLUX_7809', '31', 'E88', '31', '55924'), (426, 33, 'SBC-BHE', '200.201.218.137', 152, 'OI_TR_BHE_6817', '7030103051', 'OI_TR_BHE_6817', '', 'OI_6817', 'FLUX_7809', '31', 'E88', '31', '55924'), (427, 33, 'SBC-BHE', '200.201.218.137', 155, 'OI_TR_BHE_GERAL', '7030103051', 'OI_TR_BHE_6817', '', 'OI_6817', 'FLUX_7809', '31', 'E88', '31', '55924'), (428, 34, 'SIP-IDefferrariB', '201.20.144.226', 332, 'OI_DEFF_B_LC_51', '5514518451', 'DEFF_OI_51_LOCAL', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (429, 34, 'SIP-IDefferrariB', '201.20.144.226', 333, 'OI_DEFF_B_LD_51', '5514518452', 'DEFF_OI_51_LDN', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (430, 34, 'SIP-IDefferrariB', '201.20.144.226', 334, 'OI_DEFF_B_TR_51', '5514518453', 'DEFF_OI_51_TR', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '4926'), (431, 34, 'SIP-IDefferrariB', '201.20.144.226', 348, 'OI_DEFF_B_TR_51_SAIDA', '5514518453', 'DEFF_OI_51_TR', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '4926'), (432, 34, 'SIP-IDefferrariB', '201.20.144.226', 352, 'OI_DEFF_B_LC_51_SAIDA', '5514518451', 'DEFF_OI_51_LOCAL', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (433, 34, 'SIP-IDefferrariB', '201.20.144.226', 353, 'OI_DEFF_B_LD_51_SAIDA', '5514518452', 'DEFF_OI_51_LDN', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (434, 35, 'SIP-IDefferrariA', '201.20.144.225', 335, 'OI_DEFF_A_LC_51', '5514518451', 'DEFF_OI_51_LOCAL', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (435, 35, 'SIP-IDefferrariA', '201.20.144.225', 336, 'OI_DEFF_A_LD_51', '5514518452', 'DEFF_OI_51_LDN', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (436, 35, 'SIP-IDefferrariA', '201.20.144.225', 337, 'OI_DEFF_A_TR_51', '5514518453', 'DEFF_OI_51_TR', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '4926'), (437, 35, 'SIP-IDefferrariA', '201.20.144.225', 349, 'OI_DEFF_A_TR_0800_SAIDA', '5514518453', 'DEFF_OI_51_TR', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '4926'), (438, 35, 'SIP-IDefferrariA', '201.20.144.225', 350, 'OI_DEFF_A_LD_51_SAIDA', '5514518452', 'DEFF_OI_51_LDN', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (439, 35, 'SIP-IDefferrariA', '201.20.144.225', 351, 'OI_DEFF_A_LC_51_SAIDA', '5514518451', 'DEFF_OI_51_LOCAL', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (440, 35, 'SIP-IDefferrariA', '201.20.144.225', 361, 'OI_DEFF_A_LCFIXO_51_SAIDA', '5514518451', 'DEFF_OI_51_LOCAL', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (441, 35, 'SIP-IDefferrariA', '201.20.144.225', 363, 'OI_DEFF_A_TR_51_SAIDA', '5514518453', 'DEFF_OI_51_TR', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '4926'), (442, 35, 'SIP-IDefferrariA', '201.20.144.225', 364, 'OI_DEFF_A_LC_51_SMP_SAIDA', '5514518451', 'DEFF_OI_51_LOCAL', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (443, 35, 'SIP-IDefferrariA', '201.20.144.225', 428, 'OI_DEFF_A_LD_51_SAIDA048', '5514518452', 'DEFF_OI_51_LDN', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (444, 35, 'SIP-IDefferrariA', '201.20.144.225', 440, 'NEXT_OI_51_SIP_I_DEFF_3003', '5514518451', 'DEFF_OI_51_LOCAL', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (445, 35, 'SIP-IDefferrariA', '201.20.144.225', 486, 'OI_DEFF_A_TR_0300_LATAM', '5514518453', 'DEFF_OI_51_TR', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '4926'), (446, 35, 'SIP-IDefferrariA', '201.20.144.225', 517, 'OI_DEFF_B_LD_51_SAIDA_lajeado', '5514518452', 'DEFF_OI_51_LDN', '', 'OI.51', 'DEFF.51', '51', 'B77', '51', '55845'), (447, 36, 'SBC-TRANSITO-250', '200.159.177.250', 2, 'EBT_LOCAL_4926_NHO', '1222410090', 'EBT_LOCAL_4926_NHO', '', 'PAEUMGBV', 'FLUXNHO1', '251', 'B77', '51', '55845'), (448, 36, 'SBC-TRANSITO-250', '200.159.177.250', 3, 'EBT_LDN_4926_NHO', '1222410091', 'EBT_ENTRANTE_4927_NHO', '', 'PAE_EBT_01', 'FLUXNHO1', '251', 'B77', '51', '55845'), (449, 36, 'SBC-TRANSITO-250', '200.159.177.250', 4, 'EBT_CSP_4926_NHO', '1222410091', 'EBT_ENTRANTE_4927_NHO', '', 'PAE_EBT_01', 'FLUXNHO1', '251', 'B77', '51', '55845'), (450, 36, 'SBC-TRANSITO-250', '200.159.177.250', 6, 'OI_LOCAL_4926_NHO', '1218010090', 'OI_LOCAL_4926_NHO', '', 'PAE_OI_01', 'FLUXNHO1', '51', 'B77', '51', '55845'), (451, 36, 'SBC-TRANSITO-250', '200.159.177.250', 7, 'OI_LDN_4926_NHO', '1216110090', 'OI_LDN_4926_NHO', '', 'PAE_OI_02', 'FLUXNHO1', '52', 'B77', '51', '55845'), (452, 36, 'SBC-TRANSITO-250', '200.159.177.250', 8, 'EBT_0800_4926_NHO', '1222410091', 'EBT_ENTRANTE_4927_NHO', '', 'PAE_EBT_01', 'FLUXNHO1', '251', 'B77', '51', '55845'), (453, 36, 'SBC-TRANSITO-250', '200.159.177.250', 11, 'OI_0800_4926_NHO', '1216110090', 'OI_LDN_4926_NHO', '', 'PAE_OI_02', 'FLUXNHO1', '52', 'B77', '51', '55845'), (454, 36, 'SBC-TRANSITO-250', '200.159.177.250', 12, 'OI_0300_4926_NHO', '1216110090', 'OI_LDN_4926_NHO', '', 'PAE_OI_02', 'FLUXNHO1', '52', 'B77', '51', '55845'), (455, 36, 'SBC-TRANSITO-250', '200.159.177.250', 13, 'EBT_0300_4926_NHO', '1222410091', 'EBT_ENTRANTE_4927_NHO', '', 'PAE_EBT_01', 'FLUXNHO1', '251', 'B77', '51', '55845'), (456, 36, 'SBC-TRANSITO-250', '200.159.177.250', 14, 'EBT_especial_4926_NHO', '1222410091', 'EBT_ENTRANTE_4927_NHO', '', 'PAE_EBT_01', 'FLUXNHO1', '251', 'B77', '51', '55845'), (457, 36, 'SBC-TRANSITO-250', '200.159.177.250', 15, 'OI_ESPECIAL_4926_NHO', '1216110090', 'OI_LDN_4926_NHO', '', 'PAE_OI_02', 'FLUXNHO1', '52', 'B77', '51', '55845'), (458, 36, 'SBC-TRANSITO-250', '200.159.177.250', 16, 'EBT_4003-4_4926_NHO', '1222410091', 'EBT_ENTRANTE_4927_NHO', '', 'PAE_EBT_01', 'FLUXNHO1', '251', 'B77', '51', '55845'), (459, 36, 'SBC-TRANSITO-250', '200.159.177.250', 17, 'OI_4003-4_4926_NHO', '1216110090', 'OI_LDN_4926_NHO', '', 'PAE_OI_02', 'FLUXNHO1', '52', 'B77', '51', '55845'), (460, 36, 'SBC-TRANSITO-250', '200.159.177.250', 20, 'OI_LOCAL_4999_NHO_WS', '1218010091', 'OI_LOCAL_4999_NHO', '', 'PAE_OI_05', 'FLUXNHO1', '51', 'B77', '51', '55845'), (461, 36, 'SBC-TRANSITO-250', '200.159.177.250', 21, 'EBT_LOCAL_4999_NHO_WS', '1222410092', 'EBT_LOCAL_4999_NHO', '', 'PAEUMGBV', 'FLUXNHO1', '251', 'B77', '51', '55845'), (462, 36, 'SBC-TRANSITO-250', '200.159.177.250', 37, 'OI_LC_CSL_11840', '7010102011', 'OI_LC_11840', '', 'OI_11840', 'FLUX_10196', '51', 'E83', '54', '55924'), (463, 36, 'SBC-TRANSITO-250', '200.159.177.250', 38, 'OI_LDN_CSL_11840', '7020102021', 'OI_LDN_11840', '', 'OI_11840', 'FLUX_CSL', '51', 'E83', '54', '55924'), (464, 36, 'SBC-TRANSITO-250', '200.159.177.250', 39, 'OI_TR_CSL_11840', '7030102031', 'OI_TR_11840', '', 'OI_11840', 'FLUX_CSL', '51', 'E83', '54', '55924'), (465, 36, 'SBC-TRANSITO-250', '200.159.177.250', 69, 'OI_TR_CSL_11840_VC2/3', '7030102031', 'OI_TR_11840', '', 'OI_11840', 'FLUX_CSL', '51', 'E83', '54', '55924'), (466, 36, 'SBC-TRANSITO-250', '200.159.177.250', 81, 'OI_LDN_CSL_CNG_11840', '7020102021', 'OI_LDN_11840', '', 'OI_11840', 'FLUX_CSL', '51', 'E83', '54', '55924'), (467, 36, 'SBC-TRANSITO-250', '200.159.177.250', 156, 'OI_TR_CSL_GERAL', '7030102031', 'OI_TR_11840', '', 'OI_11840', 'FLUX_CSL', '51', 'E83', '54', '55924'), (468, 36, 'SBC-TRANSITO-250', '200.159.177.250', 157, 'OI_TR_SMP_CSL_GERAL', '7030102031', 'OI_TR_11840', '', 'OI_11840', 'FLUX_CSL', '51', 'E83', '54', '55924'), (469, 36, 'SBC-TRANSITO-250', '200.159.177.250', 257, 'OI_LC_CSL_11840teste', '7010102011', 'OI_LC_11840', '', 'OI_11840', 'FLUX_10196', '51', 'E83', '54', '55924'), (470, 36, 'SBC-TRANSITO-250', '200.159.177.250', 296, 'OI_LC_CSL_11840_TESTE', '7010102011', 'OI_LC_11840', '', 'OI_11840', 'FLUX_10196', '51', 'E83', '54', '55924'), (471, 36, 'SBC-TRANSITO-250', '200.159.177.250', 298, 'TIm_LD_STFC_51_SURF', '5531455002', 'OI_LC_55', '', 'OI_51', 'FLUX_PAE', '51', 'E83', '55', '55924'), (472, 36, 'SBC-TRANSITO-250', '200.159.177.250', 316, 'OI_TR_CSL_11840_53', '7030102031', 'OI_TR_11840', '', 'OI_11840', 'FLUX_CSL', '51', 'E83', '54', '55924'), (473, 36, 'SBC-TRANSITO-250', '200.159.177.250', 386, 'OI_LOCAL_4926_NHO_PLUS', '1218010090', 'OI_LOCAL_4926_NHO', '', 'PAE_OI_01', 'FLUXNHO1', '51', 'B77', '51', '55845'), (474, 36, 'SBC-TRANSITO-250', '200.159.177.250', 387, 'OI_TR_CSL_GERALelevar', '7030102031', 'OI_TR_11840', '', 'OI_11840', 'FLUX_CSL', '51', 'E83', '54', '55924'), (475, 36, 'SBC-TRANSITO-250', '200.159.177.250', 436, 'OI_TR_CSL_GERAL_FIXO', '7030102031', 'OI_TR_11840', '', 'OI_11840', 'FLUX_CSL', '51', 'E83', '54', '55924'), (476, 37, 'SIP-I-OI-CN11', '201.20.149.70', 734, 'LC11', '5514110001', 'LC11', '', 'OI.SPO', 'FLUX.SPO', '511', 'E89', '11', '55924'), (477, 37, 'SIP-I-OI-CN11', '201.20.149.70', 735, 'LD11', '5514110002', 'LD11', '', 'OI.SPO', 'FLUX.SPO', '511', 'E89', '11', '55924'), (478, 37, 'SIP-I-OI-CN11', '201.20.149.70', 736, 'LC12', '5514120001', 'LC12', '', 'OI.SPO', 'FLUX.SPO', '511', 'E89', '12', '55924'), (479, 37, 'SIP-I-OI-CN11', '201.20.149.70', 737, 'LD12', '5514120002', 'LD12', '', 'OI.SPO', 'FLUX.SPO', '511', 'E89', '12', '55924'), (480, 37, 'SIP-I-OI-CN11', '201.20.149.70', 738, 'LC13', '5514130001', 'LC13', '', 'OI.SPO', 'FLUX.SPO', '511', 'E89', '13', '55924'), (481, 37, 'SIP-I-OI-CN11', '201.20.149.70', 739, 'LD13', '5514130002', 'LD13', '', 'OI.SPO', 'FLUX.SPO', '511', 'E89', '13', '55924'), (482, 37, 'SIP-I-OI-CN11', '201.20.149.70', 740, 'LC14', '5514140001', 'LC14', '', 'OI.SPO', 'FLUX.SPO', '511', 'E89', '14', '55924'), (483, 37, 'SIP-I-OI-CN11', '201.20.149.70', 741, 'LD14', '5514140002', 'LD14', '', 'OI.SPO', 'FLUX.SPO', '511', 'E89', '14', '55924'), (484, 37, 'SIP-I-OI-CN11', '201.20.149.70', 742, 'LC15', '5514150001', 'LC15', '', 'OI.SPO', 'FLUX.SPO', '511', 'E89', '15', '55924'), (485, 37, 'SIP-I-OI-CN11', '201.20.149.70', 743, 'LD15', '5514150002', 'LD15', '', 'OI.SPO', 'FLUX.SPO', '511', 'E89', '15', '55924'), (486, 37, 'SIP-I-OI-CN11', '201.20.149.70', 744, 'LC16', '5514160001', 'LC16', '', 'OI.SPO', 'FLUX.SPO', '511', 'E89', '16', '55924'), (487, 37, 'SIP-I-OI-CN11', '201.20.149.70', 745, 'LD16', '5514160002', 'LD16', '', 'OI.SPO', 'FLUX.SPO', '511', 'E89', '16', '55924'), (488, 37, 'SIP-I-OI-CN11', '201.20.149.70', 746, 'LC17', '5514170001', 'LC17', '', 'OI.SPO', 'FLUX.SPO', '511', 'E89', '17', '55924'), (489, 37, 'SIP-I-OI-CN11', '201.20.149.70', 747, 'LD17', '5514170002', 'LD17', '', 'OI.SPO', 'FLUX.SPO', '511', 'E89', '17', '55924'), (490, 37, 'SIP-I-OI-CN11', '201.20.149.70', 748, 'LC18', '5514180001', 'LC18', '', 'OI.SPO', 'FLUX.SPO', '511', 'E89', '18', '55924'), (491, 37, 'SIP-I-OI-CN11', '201.20.149.70', 749, 'LD18', '5514180002', 'LD18', '', 'OI.SPO', 'FLUX.SPO', '511', 'E89', '18', '55924'), (492, 37, 'SIP-I-OI-CN11', '201.20.149.70', 750, 'LC19', '5514190001', 'LC19', '', 'OI.SPO', 'FLUX.SPO', '511', 'E89', '19', '55924'), (493, 37, 'SIP-I-OI-CN11', '201.20.149.70', 751, 'LD19', '5514190002', 'LD19', '', 'OI.SPO', 'FLUX.SPO', '511', 'E89', '19', '55924')
ON DUPLICATE KEY UPDATE
  `gateway_name`   = VALUES(`gateway_name`),
  `gateway_ip`     = VALUES(`gateway_ip`),
  `nome_gw`        = VALUES(`nome_gw`),
  `cifra`          = VALUES(`cifra`),
  `tronco_desc`    = VALUES(`tronco_desc`),
  `natureza`       = VALUES(`natureza`),
  `poippi_origem`  = VALUES(`poippi_origem`),
  `poippi_destino` = VALUES(`poippi_destino`),
  `eot_origem`     = VALUES(`eot_origem`),
  `eot_destino`    = VALUES(`eot_destino`),
  `codigo_area`    = VALUES(`codigo_area`),
  `rn1_destino`    = VALUES(`rn1_destino`);


CALL _mig_add_column('cadup_operadoras', 'eot_anatel',
    '`eot_anatel` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL AFTER `rn1`');

CALL _mig_add_index('cadup_operadoras', 'idx_carrier_rn1',
    '`idx_carrier_rn1`(`carrier_id` ASC, `rn1` ASC) USING BTREE');

CALL _mig_add_index('cadup_operadoras', 'idx_rn1',
    '`idx_rn1`(`rn1` ASC) USING BTREE');

CALL _mig_add_index('cadup_operadoras', 'idx_cn_prefixo',
    '`idx_cn_prefixo`(`cn` ASC, `prefixo` ASC, `carrier_id` ASC) USING BTREE');

CALL _mig_drop_index('carrier_routing', 'carrier_rn1');

CALL _mig_drop_index('carrier_routing', 'code_rg_accid_key');

CALL _mig_add_column('carrier_routing', 'carrier_eot',
    '`carrier_eot` varchar(5) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '''' AFTER `carrier_rn1`');

CALL _mig_add_column('carrier_routing', 'carrier_poi',
    '`carrier_poi` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '''' COMMENT ''DETRAF: Ponto de Interconexao (ex: PAE_OI_02/)'' AFTER `carrier_eot`');

CALL _mig_add_column('carrier_routing', 'carrier_descritor',
    '`carrier_descritor` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT ''LENL'' COMMENT ''DETRAF: sg_descritor de detraf_descritores'' AFTER `carrier_poi`');

CALL _mig_add_column('carrier_routing', 'carrier_grupo_horario',
    '`carrier_grupo_horario` char(4) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '''' COMMENT ''DETRAF: sg_grupo_horario fixo (D/F/P/NA). Vazio = calcular N/R/S por horario'' AFTER `last_modified_date`');

CALL _mig_add_index('carrier_routing', 'carrier_rn1',
    '`carrier_rn1`(`carrier_id` ASC, `carrier_rn1` ASC) USING BTREE');

CALL _mig_add_index('carrier_routing', 'idx_rn1_name',
    '`idx_rn1_name`(`carrier_rn1` ASC, `carrier_name`(50) ASC) USING BTREE');

SET SESSION sql_mode = @old_sql_mode;

DROP FUNCTION IF EXISTS `fn_clean_number`;
DELIMITER ;;
CREATE FUNCTION `fn_clean_number`(p_raw VARCHAR(120))
 RETURNS varchar(30) CHARSET utf8mb4
  DETERMINISTIC
BEGIN
    DECLARE v_number VARCHAR(120);

    SET v_number = TRIM(REGEXP_REPLACE(p_raw, '\\s*<[^>]*>', ''));
    SET v_number = TRIM(v_number);

    IF v_number NOT REGEXP '^[0-9]' THEN
        RETURN '';
    END IF;

    IF v_number LIKE '00%' THEN
        SET v_number = SUBSTRING(v_number, 3);
    END IF;

    IF v_number LIKE '0600%' THEN
        SET v_number = SUBSTRING(v_number, 7);
    END IF;

    WHILE v_number LIKE '55%' AND LENGTH(v_number) > 11 DO
        SET v_number = SUBSTRING(v_number, 3);
    END WHILE;

    IF v_number REGEXP '^0[1-9][0-9][1-9]' THEN
        SET v_number = SUBSTRING(v_number, 4);
    END IF;

    IF LENGTH(v_number) > 30 THEN
        RETURN '';
    END IF;

    RETURN v_number;
END ;;
DELIMITER ;

DROP FUNCTION IF EXISTS `fn_get_codarea`;
DELIMITER ;;
CREATE FUNCTION `fn_get_codarea`(p_number VARCHAR(30))
 RETURNS varchar(9) CHARSET utf8mb4
  READS SQL DATA
BEGIN
    DECLARE v_result    VARCHAR(9) DEFAULT '';
    DECLARE v_cn        INT;
    DECLARE v_resto     VARCHAR(30);
    DECLARE v_resto_len INT;

    IF p_number IS NULL OR p_number = '' OR LENGTH(p_number) < 10 THEN
        RETURN '';
    END IF;

    SET v_cn        = CAST(LEFT(p_number, 2) AS UNSIGNED);
    SET v_resto     = SUBSTRING(p_number, 3);
    SET v_resto_len = LENGTH(v_resto);

    SELECT TRIM(op.codArea) INTO v_result
    FROM cadup_operadoras op
    WHERE op.cn = v_cn
      AND op.prefixo IN (
            CAST(LEFT(v_resto, v_resto_len)                  AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 1, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 2, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 3, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 4, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 5, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 6, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 7, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 8, 0)) AS UNSIGNED)
      )
      AND op.prefixo > 0
    ORDER BY op.prefixo DESC
    LIMIT 1;

    RETURN IFNULL(v_result, '');
END ;;
DELIMITER ;

DROP FUNCTION IF EXISTS `fn_sip_clean_number`;
DELIMITER ;;
CREATE FUNCTION `fn_sip_clean_number`(p_number VARCHAR(120))
 RETURNS varchar(30) CHARSET utf8mb4
  DETERMINISTIC
BEGIN
    DECLARE v_inner VARCHAR(120);
    DECLARE v_out   VARCHAR(30);
    DECLARE v_start INT;
    DECLARE v_end   INT;

    IF p_number IS NULL OR p_number = '' THEN
        RETURN '';
    END IF;

    SET v_out = fn_clean_number(p_number);
    IF v_out <> '' THEN
        RETURN v_out;
    END IF;

    SET v_start = LOCATE('<', p_number);
    SET v_end   = LOCATE('>', p_number);

    IF v_start > 0 AND v_end > v_start THEN
        SET v_inner = SUBSTRING(p_number, v_start + 1, v_end - v_start - 1);
        SET v_inner = SUBSTRING_INDEX(v_inner, '@', 1);
        SET v_inner = SUBSTRING_INDEX(v_inner, ':', -1);
        RETURN fn_clean_number(v_inner);
    END IF;

    RETURN '';
END ;;
DELIMITER ;

DROP FUNCTION IF EXISTS `fn_get_codcnl`;
DELIMITER ;;
CREATE FUNCTION `fn_get_codcnl`(p_number VARCHAR(30))
 RETURNS varchar(10) CHARSET utf8mb4
  READS SQL DATA
BEGIN
    DECLARE v_result    VARCHAR(10) DEFAULT '';
    DECLARE v_cn        INT;
    DECLARE v_resto     VARCHAR(30);
    DECLARE v_resto_len INT;

    IF p_number IS NULL OR p_number = '' OR LENGTH(p_number) < 10 THEN
        RETURN '';
    END IF;

    SET v_cn        = CAST(LEFT(p_number, 2) AS UNSIGNED);
    SET v_resto     = SUBSTRING(p_number, 3);
    SET v_resto_len = LENGTH(v_resto);

    SELECT op.codCNL INTO v_result
    FROM cadup_operadoras op
    WHERE op.cn = v_cn
      AND op.prefixo IN (
            CAST(LEFT(v_resto, v_resto_len)                  AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 1, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 2, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 3, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 4, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 5, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 6, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 7, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 8, 0)) AS UNSIGNED)
      )
      AND op.prefixo > 0
    ORDER BY op.prefixo DESC
    LIMIT 1;

    RETURN IFNULL(v_result, '');
END ;;
DELIMITER ;

DROP FUNCTION IF EXISTS `fn_get_carrier_id`;
DELIMITER ;;
CREATE FUNCTION `fn_get_carrier_id`(p_number VARCHAR(30))
 RETURNS int
  READS SQL DATA
BEGIN
    DECLARE v_carrier_id INT DEFAULT 0;
    DECLARE v_len        INT;
    DECLARE v_cn         INT;
    DECLARE v_resto      VARCHAR(30);
    DECLARE v_resto_len  INT;

    SET v_len = LENGTH(p_number);

    IF p_number IS NULL OR p_number = '' OR v_len < 10 THEN
        RETURN 0;
    END IF;

    SET v_cn        = CAST(LEFT(p_number, 2) AS UNSIGNED);
    SET v_resto     = SUBSTRING(p_number, 3);
    SET v_resto_len = LENGTH(v_resto);

    SELECT op.carrier_id INTO v_carrier_id
    FROM cadup_operadoras op
    WHERE op.cn = v_cn
      AND op.prefixo IN (
            CAST(LEFT(v_resto, v_resto_len)                  AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 1, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 2, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 3, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 4, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 5, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 6, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 7, 0)) AS UNSIGNED),
            CAST(LEFT(v_resto, GREATEST(v_resto_len - 8, 0)) AS UNSIGNED)
      )
      AND op.prefixo    > 0
      AND op.carrier_id > 0
    ORDER BY op.prefixo DESC
    LIMIT 1;

    RETURN IFNULL(v_carrier_id, 0);
END ;;
DELIMITER ;

DROP PROCEDURE IF EXISTS `update_carrier_id`;
DELIMITER ;;
CREATE PROCEDURE `update_carrier_id`(IN p_data_inicio DATE,
    IN p_data_fim    DATE,
    IN p_debug       TINYINT,
    IN p_cn_padrao   VARCHAR(5))
BEGIN
    IF p_cn_padrao IS NULL OR p_cn_padrao = '' THEN
        SET p_cn_padrao = '51';
    END IF;

    DROP TEMPORARY TABLE IF EXISTS tmp_carrier_lookup;
    CREATE TEMPORARY TABLE tmp_carrier_lookup (
        numero_original VARCHAR(120) NOT NULL,
        numero_limpo    VARCHAR(30)  NOT NULL DEFAULT '',
        numero_lookup   VARCHAR(30)  NOT NULL DEFAULT '',
        cn_aplicado     TINYINT(1)   NOT NULL DEFAULT 0,
        zero_stripped   TINYINT(1)   NOT NULL DEFAULT 0,
        carrier_id      INT          NOT NULL DEFAULT 0,
        PRIMARY KEY (numero_original)
    ) ENGINE=InnoDB;

    INSERT IGNORE INTO tmp_carrier_lookup (numero_original)
    SELECT DISTINCT callerid FROM cdrs
    WHERE callstart >= p_data_inicio AND callstart < DATE_ADD(p_data_fim, INTERVAL 1 DAY)
      AND disposition = 'NORMAL_CLEARING [16]' AND billseconds > 0 AND carrier_id = 0 AND call_direction = 'inbound';

    INSERT IGNORE INTO tmp_carrier_lookup (numero_original)
    SELECT DISTINCT callednum FROM cdrs
    WHERE callstart >= p_data_inicio AND callstart < DATE_ADD(p_data_fim, INTERVAL 1 DAY)
      AND disposition = 'NORMAL_CLEARING [16]' AND billseconds > 0 AND carrier_id = 0 AND call_direction = 'outbound';

    UPDATE tmp_carrier_lookup
    SET numero_limpo = CASE
        WHEN REGEXP_REPLACE(numero_original, '[^0-9]', '') REGEXP '^0[1-9][1-9][0-9]{8,9}$'
            THEN SUBSTRING(REGEXP_REPLACE(numero_original, '[^0-9]', ''), 2)
        ELSE fn_sip_clean_number(numero_original)
    END;

    DELETE FROM tmp_carrier_lookup WHERE numero_limpo = '' OR LENGTH(numero_limpo) < 8;

    UPDATE tmp_carrier_lookup
    SET
        numero_lookup = CASE
            WHEN LENGTH(numero_limpo) IN (8, 9) THEN CONCAT(p_cn_padrao, numero_limpo)
            ELSE numero_limpo
        END,
        cn_aplicado = CASE
            WHEN LENGTH(numero_limpo) IN (8, 9) THEN 1
            ELSE 0
        END;

    UPDATE tmp_carrier_lookup
    SET carrier_id = fn_get_carrier_id(numero_lookup);

    UPDATE tmp_carrier_lookup
    SET
        numero_lookup = CASE
            WHEN LENGTH(SUBSTRING(numero_limpo, 2)) IN (8, 9)
                THEN CONCAT(p_cn_padrao, SUBSTRING(numero_limpo, 2))
            ELSE SUBSTRING(numero_limpo, 2)
        END,
        cn_aplicado   = CASE
            WHEN LENGTH(SUBSTRING(numero_limpo, 2)) IN (8, 9) THEN 1
            ELSE 0
        END,
        zero_stripped = 1
    WHERE carrier_id      = 0
      AND LEFT(numero_limpo, 1) = '0';

    UPDATE tmp_carrier_lookup
    SET carrier_id = fn_get_carrier_id(numero_lookup)
    WHERE carrier_id    = 0
      AND zero_stripped = 1;

    UPDATE cdrs c
    JOIN tmp_carrier_lookup t ON t.numero_original = c.callerid
    SET c.carrier_id = t.carrier_id
    WHERE c.callstart >= p_data_inicio AND c.callstart < DATE_ADD(p_data_fim, INTERVAL 1 DAY)
      AND c.disposition = 'NORMAL_CLEARING [16]' AND billseconds > 0 AND c.carrier_id = 0 AND c.call_direction = 'inbound'
      AND t.carrier_id > 0;

    UPDATE cdrs c
    JOIN tmp_carrier_lookup t ON t.numero_original = c.callednum
    SET c.carrier_id = t.carrier_id
    WHERE c.callstart >= p_data_inicio AND c.callstart < DATE_ADD(p_data_fim, INTERVAL 1 DAY)
      AND c.disposition = 'NORMAL_CLEARING [16]' AND billseconds > 0 AND c.carrier_id = 0 AND c.call_direction = 'outbound'
      AND t.carrier_id > 0;

    SELECT
        COUNT(*)                                      AS numeros_unicos,
        SUM(carrier_id > 0)                           AS resolvidos,
        SUM(carrier_id = 0)                           AS sem_match,
        SUM(LENGTH(numero_limpo) = 8)                 AS numeros_8_digitos,
        SUM(LENGTH(numero_limpo) = 9)                 AS numeros_9_digitos,
        SUM(cn_aplicado  = 1)                         AS com_cn_aplicado,
        SUM(zero_stripped = 1)                        AS com_zero_removido
    FROM tmp_carrier_lookup;

    IF p_debug = 1 THEN
        SELECT
            numero_original,
            numero_limpo,
            numero_lookup,
            LENGTH(numero_limpo) AS digitos,
            cn_aplicado,
            zero_stripped,
            carrier_id
        FROM tmp_carrier_lookup
        WHERE carrier_id = 0
        ORDER BY digitos, numero_limpo;
    END IF;

    DROP TEMPORARY TABLE IF EXISTS tmp_carrier_lookup;
END ;;
DELIMITER ;

DROP PROCEDURE IF EXISTS `get_detraf_report`;
DELIMITER ;;
CREATE PROCEDURE `get_detraf_report`(IN p_data_inicio    DATE,
    IN p_data_fim       DATE,
    IN p_carrier_id     VARCHAR(200),
    IN p_eot_credora    VARCHAR(10),
    IN p_call_direction VARCHAR(20))
BEGIN
    SELECT
        sub.id,
        sub.EOTCREDORA,
        sub.EOTDEVEDORA,
        sub.GRUPOHORARIO,
        sub.ASSINANTEA,
        sub.DATADACHAMADA,
        sub.HORADEATENDIMENTO,
        sub.ASSINANTEB,
        sub.DURACAOREAL,
        sub.POIPPI,
        sub.DESCRITOR,
        sub.DURACAODETRAF,
        sub.CATEGORIA,
        sub.FDS,
        sub.EXITCODE,
        sub.CONTADORSAIDASPARCIAIS,
        sub.IDENTIFICADORORIGEM,
        sub.VRR,
        sub.CNLA,
        sub.CNLB,
        sub.LOCALA,
        sub.LOCALB,
        sub.MESMAAREA,
        sub.EOTA,
        sub.EOTB,
        sub.VALOR,
        sub.RN1,
        sub.troncoss7,
        sub.DURATION,
        sub.DATAHORA,
        sub.encarreirar,
        sub.CALLID
    FROM (
        SELECT
            pre.id,
            CASE pre.call_direction
                WHEN 'inbound'  THEN IFNULL(p_eot_credora, 'E83')
                WHEN 'outbound' THEN cr.carrier_eot
            END AS EOTCREDORA,
            CASE pre.call_direction
                WHEN 'inbound'  THEN cr.carrier_eot
                WHEN 'outbound' THEN IFNULL(p_eot_credora, 'E83')
            END AS EOTDEVEDORA,
            CASE
                WHEN cr.carrier_grupo_horario <> ''      THEN cr.carrier_grupo_horario
                WHEN DAYOFWEEK(pre.callstart) IN (1, 7)  THEN 'R'
                WHEN HOUR(pre.callstart) BETWEEN 6 AND 23 THEN 'N'
                ELSE 'R'
            END AS GRUPOHORARIO,
            CASE pre.call_direction
                WHEN 'inbound'  THEN pre.callerid_clean
                WHEN 'outbound' THEN pre.callednum_clean
            END AS ASSINANTEA,
            DATE_FORMAT(pre.callstart, '%Y%m%d') AS DATADACHAMADA,
            DATE_FORMAT(pre.callstart, '%H%i%S') AS HORADEATENDIMENTO,
            CASE pre.call_direction
                WHEN 'inbound'  THEN pre.callednum_clean
                WHEN 'outbound' THEN pre.callerid_clean
            END AS ASSINANTEB,
            TIME_FORMAT(SEC_TO_TIME(pre.billseconds), '%H%i%s') AS DURACAOREAL,

            IFNULL(dgt.poippi_origem, cr.carrier_poi) AS POIPPI,

            CONCAT(
                CASE
                    WHEN pre.callednum LIKE '0800%'
                      OR pre.callednum LIKE '0300%'
                      OR pre.callednum LIKE '0500%'
                      OR pre.callednum LIKE '0900%' THEN 'G'
                    WHEN pre.mesma_area = 1         THEN 'L'
                    ELSE 'N'
                END,
                CASE pre.call_direction
                    WHEN 'inbound' THEN 'E'
                    ELSE                'S'
                END,
                CASE
                    WHEN pre.callednum LIKE '0800%' THEN '8'
                    WHEN pre.callednum LIKE '0300%' THEN '3'
                    WHEN pre.callednum LIKE '0500%' THEN '5'
                    WHEN pre.callednum LIKE '0900%' THEN '9'
                    WHEN pre.callednum LIKE '90%'   THEN 'A'
                    ELSE                                 'N'
                END,
                CASE IFNULL(ca.tipo, '')
                    WHEN 'M' THEN 'V'
                    WHEN 'F' THEN IF(pre.mesma_area = 1, 'L', 'I')
                    ELSE RIGHT(cr.carrier_descritor, 1)
                END
            ) AS DESCRITOR,
            pre.billseconds AS DURACAODETRAF,
            ''              AS CATEGORIA,
            ''              AS FDS,
            ''              AS EXITCODE,
            ''              AS CONTADORSAIDASPARCIAIS,
            ''              AS IDENTIFICADORORIGEM,
            ROUND(pre.debit / NULLIF(pre.billseconds, 0), 5) AS VRR,
            IFNULL(ca.codCNL,        '') AS CNLA,
            CASE pre.call_direction
                WHEN 'inbound'  THEN fn_get_codcnl(pre.callednum_clean)
                WHEN 'outbound' THEN fn_get_codcnl(pre.callerid_clean)
            END AS CNLB,
            IFNULL(TRIM(ca.codArea), '') AS LOCALA,
            CASE pre.call_direction
                WHEN 'inbound'  THEN fn_get_codarea(pre.callednum_clean)
                WHEN 'outbound' THEN fn_get_codarea(pre.callerid_clean)
            END AS LOCALB,
            pre.mesma_area AS MESMAAREA,
            CASE pre.call_direction
                WHEN 'inbound'  THEN cr.carrier_eot
                WHEN 'outbound' THEN IFNULL(p_eot_credora, '')
            END AS EOTA,
            CASE pre.call_direction
                WHEN 'inbound'  THEN IFNULL(p_eot_credora, '')
                WHEN 'outbound' THEN cr.carrier_eot
            END AS EOTB,
            pre.debit        AS VALOR,
            cr.carrier_rn1   AS RN1,
            IFNULL(dgt.cifra, '') AS troncoss7,
            pre.billseconds  AS DURATION,
            DATE_FORMAT(pre.callstart, '%Y-%m-%d %H:%i:%S') AS DATAHORA,
            'NULL'           AS encarreirar,
            pre.uniqueid     AS CALLID

        FROM (
            SELECT
                c.id,
                c.call_direction,
                c.callstart,
                c.billseconds,
                c.debit,
                c.uniqueid,
                c.callednum,
                c.carrier_id,
                c.call_id_cadup,
                c.callerip,
                c.trunkip,
                fn_sip_clean_number(c.callerid)  AS callerid_clean,
                fn_sip_clean_number(c.callednum) AS callednum_clean,
                CASE
                    WHEN fn_sip_clean_number(c.callerid)  = ''
                      OR fn_sip_clean_number(c.callednum) = ''            THEN 0
                    WHEN LEFT(fn_sip_clean_number(c.callerid),  1) = '0'
                      OR LEFT(fn_sip_clean_number(c.callednum), 1) = '0'  THEN 0
                    WHEN LEFT(fn_sip_clean_number(c.callerid),  2)
                       = LEFT(fn_sip_clean_number(c.callednum), 2)        THEN 1
                    ELSE 0
                END AS mesma_area
            FROM cdrs c
            WHERE
                c.callstart    >= p_data_inicio
                AND c.callstart < DATE_ADD(p_data_fim, INTERVAL 1 DAY)
                AND c.disposition   = 'NORMAL_CLEARING [16]'
                AND c.billseconds   > 0
                AND c.carrier_id    > 0
                AND c.call_direction IN ('inbound', 'outbound')
                AND (
                    p_carrier_id IS NULL
                    OR p_carrier_id = ''
                    OR FIND_IN_SET(c.carrier_id, p_carrier_id) > 0
                )
                AND (
                    p_call_direction IS NULL
                    OR p_call_direction = ''
                    OR c.call_direction = p_call_direction
                )
        ) pre

        JOIN (
            SELECT carrier_id, MIN(id) AS id
            FROM   carrier_routing
            WHERE  STATUS = 0
            GROUP  BY carrier_id
        ) cr_pick ON cr_pick.carrier_id = pre.carrier_id

        JOIN carrier_routing cr ON cr.id = cr_pick.id

        LEFT JOIN cadup_operadoras ca ON ca.idCadup = pre.call_id_cadup

        LEFT JOIN (
            SELECT gateway_ip, MIN(id) AS id
            FROM   detraf_gateway_tronco
            GROUP  BY gateway_ip
        ) dgt_pick
            ON dgt_pick.gateway_ip = CASE pre.call_direction
                                         WHEN 'inbound'  THEN pre.callerip
                                         WHEN 'outbound' THEN pre.trunkip
                                     END

        LEFT JOIN detraf_gateway_tronco dgt ON dgt.id = dgt_pick.id

    ) sub
    ORDER BY sub.id;

END ;;
DELIMITER ;

INSERT INTO `cron_settings` (`name`, `command`, `exec_interval`, `creation_date`, `last_modified_date`, `status`, `file_path`)
SELECT 'Detraf Report Queue', 'minutes', 1, NOW(), NOW(), 1,
       'wget --no-check-certificate -q -O- {BASE_URL}ProcessDetrafQueue/index/'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `cron_settings` WHERE `name` = 'Detraf Report Queue'
);

INSERT INTO `menu_modules` (`menu_label`, `module_name`, `module_url`, `menu_title`, `menu_image`, `menu_subtitle`, `priority`)
SELECT 'Detraf Reports', 'detraf_reports', 'detraf_reports/detraf_reports_list/', 'Reports', 'cdr.png', 'Call Detail Reports', 80.2
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `menu_modules` WHERE `module_url` = 'detraf_reports/detraf_reports_list/'
);

SET @menu_id_1 = (
    SELECT `id` FROM `menu_modules`
    WHERE `module_url` = 'detraf_reports/detraf_reports_list/'
    LIMIT 1
);

UPDATE `userlevels`
SET `module_permissions` = CONCAT_WS(',', NULLIF(`module_permissions`, ''), @menu_id_1)
WHERE `userlevelid` IN (-1, 2)
  AND @menu_id_1 IS NOT NULL
  AND FIND_IN_SET(@menu_id_1, IFNULL(`module_permissions`, '')) = 0;

INSERT INTO `roles_and_permission` (`login_type`, `permission_type`, `menu_name`, `module_name`, `sub_module_name`, `module_url`, `display_name`, `permissions`, `status`, `creation_date`, `priority`)
SELECT 0, 0, 'reports', 'detraf_reports', '0', 'detraf_reports_list', 'Detraf Report History',
       '["main","list","search","create","edit","delete"]', 0, '2019-01-25 09:01:03', 7.21000
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `roles_and_permission`
    WHERE `login_type` = 0
      AND `module_name` = 'detraf_reports'
      AND `module_url`  = 'detraf_reports_list'
);

DROP PROCEDURE IF EXISTS `_mig_align_gateway_ip`;
DROP PROCEDURE IF EXISTS `_mig_add_column`;
DROP PROCEDURE IF EXISTS `_mig_add_index`;
DROP PROCEDURE IF EXISTS `_mig_drop_index`;
DROP PROCEDURE IF EXISTS `_mig_assert_table`;

SET SESSION sql_mode = @old_sql_mode;
SET FOREIGN_KEY_CHECKS = 1;
