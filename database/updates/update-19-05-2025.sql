ALTER TABLE `cdrs` 
ADD COLUMN `block_billseconds` INT NOT NULL DEFAULT '0' AFTER `billseconds`;

ALTER TABLE `reseller_cdrs` 
ADD COLUMN `block_billseconds` INT NOT NULL DEFAULT '0' AFTER `billseconds`;
