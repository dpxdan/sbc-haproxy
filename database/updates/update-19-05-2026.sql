SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `block_patterns` ADD COLUMN `direction` enum('outbound','inbound','both') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'outbound' AFTER `destination`;

SET FOREIGN_KEY_CHECKS = 1;