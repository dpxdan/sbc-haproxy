SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE `gateways` 
ADD COLUMN `caller_id_type` varchar(30) NOT NULL DEFAULT '' AFTER `dialplan_variable`,
ADD COLUMN `caller_id_number` varchar(255) NOT NULL DEFAULT '' AFTER `caller_id_type`;

SET FOREIGN_KEY_CHECKS=1;
