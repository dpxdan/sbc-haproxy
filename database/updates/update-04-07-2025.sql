SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE `accounts` ADD COLUMN `int_balance` decimal(20, 5) NOT NULL DEFAULT 0.00000 AFTER `tax_number`;

ALTER TABLE `accounts` ADD COLUMN `int_credit_limit` decimal(20, 5) NOT NULL DEFAULT 0.00000 AFTER `int_balance`;

ALTER TABLE `accounts` ADD COLUMN `domain_id` int NOT NULL DEFAULT 0 AFTER `int_credit_limit`;

ALTER TABLE `accounts` ADD COLUMN `id_external` int NOT NULL DEFAULT 0 AFTER `domain_id`;

DROP TABLE IF EXISTS `api_endpoints`;
CREATE TABLE `api_endpoints`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `endpoint_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `endpoint_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `redirect_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `accountid` int NOT NULL DEFAULT 0,
  `reseller_id` int NOT NULL DEFAULT 0,
  `endpoint_auth` enum('basic','password','token','oauth') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'basic',
  `partner_id` int NOT NULL DEFAULT 0,
  `endpoint_user` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `endpoint_password` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `endpoint_token` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `apply_on_endpoints` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `creation_date` datetime NOT NULL DEFAULT '1000-01-01 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `last_modified_date` datetime NOT NULL DEFAULT '1000-01-01 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = DYNAMIC;

DROP TABLE IF EXISTS `api_logs`;
CREATE TABLE `api_logs`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `response` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `http_code` smallint NULL DEFAULT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `http_code`(`http_code` ASC) USING BTREE,
  INDEX `type`(`type` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

DROP TABLE IF EXISTS `api_partners`;
CREATE TABLE `api_partners`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `partner_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `partner_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `accountid` int NOT NULL DEFAULT 0,
  `reseller_id` int NOT NULL DEFAULT 0,
  `partner_auth` enum('basic','password','token','oauth') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT 'basic',
  `partner_user` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `partner_password` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `partner_token` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `run_cron` tinyint(1) NOT NULL DEFAULT 1,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `creation_date` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `last_modified_date` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = DYNAMIC;

DROP TABLE IF EXISTS `cdr`;
CREATE TABLE `cdr` (
  `id` int NOT NULL AUTO_INCREMENT,
  `accountcode` int NOT NULL DEFAULT '0',
  `billsec` smallint NOT NULL DEFAULT '0',
  `calldate` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `clid` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `custo` decimal(20,5) NOT NULL DEFAULT '0.00000',
  `dest_pais` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'BRAZIL',
  `valor_user` decimal(20,5) NOT NULL DEFAULT '0.00000',
  `destino` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `did` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '0',
  `disposition` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `duration` smallint NOT NULL DEFAULT '0',
  `id_ligacao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `id_sip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `id_tarifa` int NOT NULL DEFAULT '0',
  `importado` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `ramal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '0',
  `tarifado` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'S',
  `tp_chamada` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `uniqueid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `dst` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `src` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `enviado_ixc` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'nao',
  `ixc_id` int DEFAULT NULL,
  `account` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `cdr_api_index` (`calldate`,`id_ligacao`,`tp_chamada`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;


DELIMITER //

CREATE TRIGGER `after_insert_cdrs` AFTER INSERT ON `cdrs` FOR EACH ROW BEGIN
    DECLARE v_accountcode INT;
    DECLARE v_first_name VARCHAR(255);
    DECLARE v_last_name VARCHAR(255);
    DECLARE v_company_name VARCHAR(255);
    DECLARE v_cliente_id INT;
    DECLARE v_razao VARCHAR(255);
    DECLARE v_sip_device_id INT;
    DECLARE v_ramal VARCHAR(255);
    DECLARE v_clid VARCHAR(255);
    DECLARE v_did VARCHAR(255);
    DECLARE v_id_sip VARCHAR(255);
    DECLARE v_voip_sippeers_id INT;
    DECLARE v_voip_sippeers_name VARCHAR(255);
    DECLARE v_dest_pais VARCHAR(255);
    DECLARE v_disposition VARCHAR(45);
    DECLARE v_tarifado VARCHAR(5);
    DECLARE v_exists INT DEFAULT 0;
    DECLARE v_account_exists INT DEFAULT 0;

    SELECT COUNT(*)
    INTO v_account_exists
    FROM accounts
    JOIN clientes ON accounts.id_external = clientes.id
    WHERE accounts.id = NEW.accountid;

    SELECT COUNT(*)
    INTO v_exists
    FROM cdr
    WHERE id_ligacao = NEW.id;

    IF v_exists = 0 AND v_account_exists > 0 THEN

        SELECT 
            accounts.id,
            accounts.first_name,
            accounts.last_name,
            accounts.company_name,
            clientes.id,
            clientes.razao
        INTO
            v_accountcode,
            v_first_name,
            v_last_name,
            v_company_name,
            v_cliente_id,
            v_razao
        FROM accounts
        JOIN clientes ON accounts.id_external = clientes.id
        WHERE accounts.id = NEW.accountid
        LIMIT 1;

        SELECT 
            sip_devices.id,
            sip_devices.username,
            JSON_UNQUOTE(JSON_EXTRACT(sip_devices.dir_vars, '$.effective_caller_id_number')),
            JSON_UNQUOTE(JSON_EXTRACT(sip_devices.dir_vars, '$.effective_caller_id_number')),
            sip_devices.id_sip_external
        INTO 
            v_sip_device_id,
            v_ramal,
            v_clid,
            v_did,
            v_id_sip
        FROM sip_devices
        WHERE sip_devices.accountid = NEW.accountid
          AND sip_devices.username = NEW.sip_user
        LIMIT 1;

        SELECT 
            voip_sippeers.id,
            voip_sippeers.name
        INTO 
            v_voip_sippeers_id,
            v_voip_sippeers_name
        FROM voip_sippeers
        WHERE voip_sippeers.id = v_id_sip
          AND voip_sippeers.name = NEW.sip_user
        LIMIT 1;

        SELECT 
            nicename
        INTO 
            v_dest_pais
        FROM countrycode
        WHERE id = IFNULL(NEW.country_id, 28)
        LIMIT 1;

        SET v_disposition = CASE NEW.disposition
            WHEN 'ORIGINATOR_CANCEL [487]' THEN 'NO ANSWER'
            WHEN 'NORMAL_CLEARING [16]' THEN 'ANSWERED'
            WHEN 'USER_BUSY [17]' THEN 'BUSY'
            ELSE 'FAILED'
        END;

        SET v_tarifado = CASE NEW.call_direction
            WHEN 'outbound' THEN 'S'
            ELSE 'N'
        END;

        INSERT INTO cdr (
            accountcode,
            billsec,
            calldate,
            clid,
            custo,
			valor_user,
            destino,
            disposition,
            duration,
            id_ligacao,
            id_sip,
            id_tarifa,
            ramal,
            tp_chamada,
            uniqueid,
            dst,
            src,
            importado,
            tarifado,
            dest_pais,
            enviado_ixc,
            account
        ) VALUES (
            v_accountcode,
            NEW.billseconds,
            NEW.callstart,
            IFNULL(v_clid, NEW.callerid),
            NEW.cost,
			NEW.debit,
            NEW.callednum,
            v_disposition,
            NEW.billseconds,
            NEW.id,
            IFNULL(v_id_sip, ''),
            NEW.pricelist_id,
            IFNULL(v_ramal, NEW.sip_user),
            NEW.calltype,
            NEW.uniqueid,
            NEW.callednum,
            NEW.callerid,
            'N',
            v_tarifado,
            IFNULL(v_dest_pais, 'BRAZIL'),
            'nao',
            IFNULL(v_first_name, '')
        );

  END IF;
END;
//


DROP TABLE IF EXISTS `cidade`;
CREATE TABLE `cidade`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo` int NOT NULL DEFAULT 0,
  `nome` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `uf` int NOT NULL DEFAULT 0,
  `cod_ibge` int NOT NULL DEFAULT 0,
  `regiao` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = DYNAMIC;

DROP TABLE IF EXISTS `cliente_contrato`;
CREATE TABLE `cliente_contrato`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `contrato` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `data_ativacao` date NULL DEFAULT NULL,
  `data` date NULL DEFAULT NULL,
  `id_cliente` int NULL DEFAULT NULL,
  `id_vd_contrato` int NULL DEFAULT NULL,
  `status` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `ultima_atualizacao` datetime NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = DYNAMIC;

DROP TABLE IF EXISTS `clientes`;
CREATE TABLE `clientes`  (
  `id` int NOT NULL,
  `razao` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `cnpj_cpf` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `email` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `telefone_celular` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `contato` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `ativo` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `tipo_pessoa` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `data_cadastro` date NULL DEFAULT NULL,
  `ultima_atualizacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `endereco` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `bairro` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `cidade` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `cep` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `id_conta` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `numero` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = DYNAMIC;


CREATE DEFINER = `fluxuser`@`127.0.0.1` TRIGGER `clientes_to_accounts` AFTER INSERT ON `clientes` FOR EACH ROW BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM accounts WHERE id_external = NEW.id
  ) THEN
    INSERT INTO accounts (
    sweep_id,
    number,
	reseller_id,
    first_name,
    email,
    telephone_1,
    creation,
    pricelist_id,
    status,
    language_id,
    currency_id,
    country_id,    
    address_1,
    last_name,
    company_name,
    postal_code,
    timezone_id,
    type,
    notify_flag,
    notify_email,
    validfordays,
    notify_credit_limit,
    id_external
  ) VALUES (
    2,
    REPLACE(REPLACE(REPLACE(NEW.cnpj_cpf, '.', ''), '-', ''), '/', ''),
    0,
	NEW.razao,
    NEW.email,
    NEW.telefone_celular,
    IFNULL(NEW.data_cadastro, NOW()),
    1,
    CASE WHEN UPPER(NEW.ativo) = 'S' THEN 0 ELSE 1 END,
    1,
    16,
    28,
    concat( NEW.endereco, ', ', NEW.numero, ' - ', NEW.bairro ),
    NEW.razao,
    NEW.razao,
    NEW.cep,
    78,
    0,
    1,
    NEW.email,
    3652,
    0.00,
    NEW.id
  );
  END IF;
END;

DROP TABLE IF EXISTS `endpoints`;
CREATE TABLE `endpoints`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `base_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `type` enum('list','insert','update','delete') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'list',
  `partner_id` int NOT NULL DEFAULT 0,
  `accountid` int NOT NULL DEFAULT 0,
  `reseller_id` int NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `creation_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `last_modified_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = DYNAMIC;

DROP TABLE IF EXISTS `planos_voip_externos`;
CREATE TABLE `planos_voip_externos`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `descricao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `id_plataforma` int NOT NULL,
  `atualizado_em` datetime NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

ALTER TABLE `sip_devices` MODIFY COLUMN `id_sip_external` int NULL DEFAULT NULL AFTER `call_waiting`;

DROP TABLE IF EXISTS `uf`;
CREATE TABLE `uf`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `cod_uf` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `nome` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `sigla` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `regiao` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `id_pais` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `cod_ibge` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = DYNAMIC;

DROP TABLE IF EXISTS `voip_sippeers`;
CREATE TABLE `voip_sippeers`  (
  `id` int NOT NULL,
  `cliente_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `username` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `host` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `context` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `type` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `nat` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `qualify` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `disallow` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `allow` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `dtmfmode` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `secret` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `callerid` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `id_contrato` int NOT NULL,
  `ativo` varchar(5) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'N',
  `id_plano_sip` int NOT NULL DEFAULT 0,
  `last_update` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = DYNAMIC;

CREATE DEFINER = `fluxuser`@`127.0.0.1` PROCEDURE `gen_random_uuid`()
BEGIN
  SELECT uuid() as gen_random_uuid;

END;

CREATE DEFINER = `fluxuser`@`127.0.0.1` PROCEDURE `sincronizar_cdrs_para_cdr`()
BEGIN
  INSERT INTO cdr (
    accountcode,
    billsec,
    calldate,
    clid,
    custo,
    dest_pais,
    destino,
    did,
    disposition,
    duration,
    id_ligacao,
    id_sip,
    id_tarifa,
    importado,
    ramal,
    tarifado,
    tp_chamada,
    uniqueid,
    dst,
    src,
    enviado_ixc,
    ixc_id,
	account
  )
  SELECT
    cdrs.accountid,
    cdrs.billseconds,
    cdrs.callstart,
    cdrs.sip_user,
    cdrs.debit,
    'BRAZIL',
    cdrs.callednum,
    cdrs.sip_user,
    CASE cdrs.disposition
      WHEN 'ORIGINATOR_CANCEL [487]' THEN 'NO ANSWER'
      WHEN 'NORMAL_CLEARING [16]' THEN 'ANSWERED'
      WHEN 'USER_BUSY [17]' THEN 'BUSY'
      ELSE 'FAILED'
    END,
    cdrs.billseconds,
    cdrs.id,
    sip_devices.id_sip_external,
    0,
    'N',
    cdrs.sip_user,
    CASE cdrs.call_direction
      WHEN 'outbound' THEN 'S'
      ELSE 'N'
    END,
    cdrs.calltype,
    cdrs.uniqueid,
    cdrs.callednum,
    cdrs.sip_user,
    'nao',
    NULL,
	accounts.first_name
  FROM
    cdrs
  INNER JOIN
    accounts ON cdrs.accountid = accounts.id
  INNER JOIN
    clientes ON accounts.id_external = clientes.id
  LEFT JOIN
    sip_devices ON sip_devices.username = cdrs.sip_user AND sip_devices.accountid = cdrs.accountid
  WHERE
    NOT EXISTS (
      SELECT 1 FROM cdr WHERE cdr.id_ligacao = cdrs.id
    );
END;

CREATE ALGORITHM = UNDEFINED DEFINER = `fluxuser`@`127.0.0.1` SQL SECURITY DEFINER VIEW `view_api_partners` AS select `api_endpoints`.`id` AS `id`,`api_endpoints`.`endpoint_name` AS `name`,`api_endpoints`.`endpoint_url` AS `url`,`api_endpoints`.`redirect_url` AS `redirect_url`,`api_endpoints`.`accountid` AS `accountid`,`api_endpoints`.`endpoint_auth` AS `endpoint_auth`,`api_endpoints`.`partner_id` AS `partner_id`,`api_endpoints`.`endpoint_user` AS `endpoint_user`,`api_endpoints`.`endpoint_password` AS `endpoint_password`,`api_endpoints`.`endpoint_token` AS `endpoint_token`,`api_endpoints`.`status` AS `status`,`api_endpoints`.`last_login_date` AS `last_login_date`,`api_partners`.`partner_name` AS `partner_name`,`api_partners`.`partner_token` AS `partner_token`,`api_partners`.`run_cron` AS `run_cron`,`api_partners`.`status` AS `partner_status` from (`api_endpoints` join `api_partners`) where (`api_endpoints`.`partner_id` = `api_partners`.`id`) group by `api_endpoints`.`id` order by `api_endpoints`.`id`;


INSERT INTO `cron_settings` (`id`, `name`, `command`, `exec_interval`, `creation_date`, `last_modified_date`, `last_execution_date`, `next_execution_date`, `status`, `file_path`) VALUES (NULL, 'Sync API', 'minutes', 10, '2025-05-03 01:54:18', '2025-06-14 02:42:56', '2025-06-16 01:21:01', '2025-06-16 01:31:01', 0, 'wget --no-check-certificate -O - -q {BASE_URL}voip/sync/');

INSERT INTO `cron_settings` (`id`, `name`, `command`, `exec_interval`, `creation_date`, `last_modified_date`, `last_execution_date`, `next_execution_date`, `status`, `file_path`) VALUES (NULL, 'Sync IXC Planos', 'hours', 1, '2025-05-03 01:58:55', '2025-05-12 17:50:37', '2025-05-12 17:37:02', '2025-05-12 18:37:02', 1, 'wget --no-check-certificate -O - -q {BASE_URL}voip/sincronizar-planos/');

INSERT INTO `cron_settings` (`id`, `name`, `command`, `exec_interval`, `creation_date`, `last_modified_date`, `last_execution_date`, `next_execution_date`, `status`, `file_path`) VALUES (NULL, 'Sync API CDR', 'minutes', 10, '2025-05-03 01:59:46', '2025-05-12 17:50:35', '2025-05-12 17:48:01', '2025-05-12 17:53:01', 1, 'wget --no-check-certificate -O - -q {BASE_URL}voip/get_cdrs/');

INSERT INTO `system` (`id`, `name`, `display_name`, `value`, `field_type`, `comment`, `timestamp`, `reseller_id`, `is_display`, `group_title`, `sub_group`, `field_rules`) VALUES (NULL, 'api_user', 'API DB User', 'DB_USER', 'default_system_input', 'Define API DB User', '1000-01-01 00:00:00', 0, 0, 'api', 'API Configuration', '');

INSERT INTO `system` (`id`, `name`, `display_name`, `value`, `field_type`, `comment`, `timestamp`, `reseller_id`, `is_display`, `group_title`, `sub_group`, `field_rules`) VALUES (NULL, 'api_password', 'API DB Password', 'DB_PASSWORD', 'default_system_input', 'Define API DB Password', '1000-01-01 00:00:00', 0, 0, 'api', 'API Configuration', '');

INSERT INTO `system` (`id`, `name`, `display_name`, `value`, `field_type`, `comment`, `timestamp`, `reseller_id`, `is_display`, `group_title`, `sub_group`, `field_rules`) VALUES (NULL, 'api_host', 'API DB Host', 'DB_HOST', 'default_system_input', 'Define API DB Host', '1000-01-01 00:00:00', 0, 0, 'api', 'API Configuration', '');

INSERT INTO `system` (`id`, `name`, `display_name`, `value`, `field_type`, `comment`, `timestamp`, `reseller_id`, `is_display`, `group_title`, `sub_group`, `field_rules`) VALUES (NULL, 'api_db', 'API DB Name', 'flux', 'default_system_input', 'Define API DB Name', '1000-01-01 00:00:00', 0, 0, 'api', 'API Configuration', '');

INSERT INTO `system` (`id`, `name`, `display_name`, `value`, `field_type`, `comment`, `timestamp`, `reseller_id`, `is_display`, `group_title`, `sub_group`, `field_rules`) VALUES (NULL, 'api', 'API PDO', '0', 'enable_disable_option', 'Set enable to add api pdo support', '2019-05-24 19:03:37', 0, 0, 'api', 'API Configuration', '');


INSERT INTO `menu_modules` (`id`, `menu_label`, `module_name`, `module_url`, `menu_title`, `menu_image`, `menu_subtitle`, `priority`) VALUES (NULL, 'API Partners', 'api', 'api_endpoints/partners_list/', 'Configuration', 'Configurations.png', 'API', 90.4);
update userlevels set module_permissions = concat( module_permissions, ',', (  SELECT max( id ) FROM menu_modules ) ) WHERE userlevelid = -1;


INSERT INTO `menu_modules` (`id`, `menu_label`, `module_name`, `module_url`, `menu_title`, `menu_image`, `menu_subtitle`, `priority`) VALUES (NULL, 'Partners Endpoints', 'api', 'api_endpoints/partners_endpoints_list/', 'Configuration', 'Configurations.png', 'API', 90.5);
update userlevels set module_permissions = concat( module_permissions, ',', (  SELECT max( id ) FROM menu_modules ) ) WHERE userlevelid = -1;

INSERT INTO `menu_modules` (`id`, `menu_label`, `module_name`, `module_url`, `menu_title`, `menu_image`, `menu_subtitle`, `priority`) VALUES (NULL, 'API Endpoints', 'api', 'api_endpoints/api_endpoints_list/', 'Configuration', 'Configurations.png', 'API', 90.3);
update userlevels set module_permissions = concat( module_permissions, ',', (  SELECT max( id ) FROM menu_modules ) ) WHERE userlevelid = -1;

SET FOREIGN_KEY_CHECKS=1;