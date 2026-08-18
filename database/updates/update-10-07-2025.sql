SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE `api_endpoints` 
ADD COLUMN `run_cron` tinyint(1) NOT NULL DEFAULT 1 AFTER `apply_on_endpoints`;

DROP TABLE IF EXISTS `api_test_requests`;
CREATE TABLE `api_test_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `method` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `headers` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `api_test_responses`;
CREATE TABLE `api_test_responses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `http_code` int DEFAULT NULL,
  `response_body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `request_id` (`request_id`) USING BTREE,
  CONSTRAINT `api_test_responses_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `api_test_requests` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

SET FOREIGN_KEY_CHECKS=1;