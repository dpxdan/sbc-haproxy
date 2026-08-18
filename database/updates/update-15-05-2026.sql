SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `account_document_consultations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_id` int DEFAULT NULL,
  `doc_number` varchar(14) NOT NULL,
  `doc_type` enum('CPF','CNPJ') NOT NULL,
  `provider` varchar(32) NOT NULL DEFAULT 'cnpja',
  `http_code` smallint DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `error_message` varchar(255) DEFAULT NULL,
  `response_json` longtext,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_adc_doc_number` (`doc_number`),
  KEY `idx_adc_account_id` (`account_id`),
  CONSTRAINT `fk_adc_account_id` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb3;

INSERT IGNORE INTO `system` (`name`, `display_name`, `value`, `field_type`, `comment`, `group_title`, `sub_group`)
VALUES
	('doc_cache_days', 'Doc Cache Days', '0', 'default_system_input', 'dias de cache MySQL para consultas CPF/CNPJ (0 = desativado)', 'global', 'General'),
	('doc_cpf_provider', 'Doc CPF Provider', 'apicpf', 'default_system_input', 'provedor de consulta CPF (apicpf)', 'global', 'General'),
	('doc_apicpf_key', 'Doc APiCPF Key', '15738b04c027d2501e19dc4b8179de79e9315abdf29c58f5123ac564ea3b1612', 'default_system_input', 'API key do apicpf.com', 'global', 'General'),
	('doc_apicpf_base_url', 'Doc APiCPF Base URL', 'https://apicpf.com', 'default_system_input', 'URL base do apicpf.com', 'global', 'General'),
	('doc_apicpf_path', 'Doc APiCPF Path', '/api/consulta', 'default_system_input', 'path da API apicpf.com', 'global', 'General'),
	('doc_apicpf_timeout', 'Doc APiCPF Timeout', '30', 'default_system_input', 'timeout cURL apicpf.com (segundos)', 'global', 'General'),
	('doc_cnpja_token', 'Doc CNPJá Token', '1cb69254-77f7-40cd-bb35-4b3bb93b764e-1ba5147d-c935-4b7d-9ea5-b5bd4c5616c3', 'default_system_input', 'token da API CNPJá (open.cnpja.com)', 'global', 'General'),
	('doc_cnpja_base_url', 'Doc CNPJá Base URL', 'https://open.cnpja.com', 'default_system_input', 'URL base do CNPJá', 'global', 'General'),
	('doc_cnpja_path', 'Doc CNPJá Path', '/office/{doc}?simples=true&registrations=BR', 'default_system_input', 'path da API CNPJá ({doc} substituído pelo CNPJ)', 'global', 'General');


SET FOREIGN_KEY_CHECKS=1;

