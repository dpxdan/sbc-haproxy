SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `flux`.`localization` 
CHANGE COLUMN `in_caller_id_originate` `in_caller_id_originate` VARCHAR(500) NOT NULL ,
CHANGE COLUMN `out_caller_id_originate` `out_caller_id_originate` VARCHAR(500) NOT NULL ,
CHANGE COLUMN `number_originate` `number_originate` VARCHAR(500) NOT NULL ,
CHANGE COLUMN `in_caller_id_terminate` `in_caller_id_terminate` VARCHAR(500) NOT NULL ,
CHANGE COLUMN `out_caller_id_terminate` `out_caller_id_terminate` VARCHAR(500) NOT NULL ,
CHANGE COLUMN `number_terminate` `number_terminate` VARCHAR(500) NOT NULL ;

SET FOREIGN_KEY_CHECKS = 1;