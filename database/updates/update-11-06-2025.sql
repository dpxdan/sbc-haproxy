ALTER TABLE `products` 
CHANGE COLUMN `release_no_balance` `release_no_balance` TINYINT(1) NOT NULL DEFAULT 1 ;

UPDATE products SET release_no_balance = 1 WHERE product_category = 1;

INSERT INTO `system` (`id`, `name`, `display_name`, `value`, `field_type`, `comment`, `timestamp`, `reseller_id`, `is_display`, `group_title`, `sub_group`, `field_rules`) VALUES (NULL, 'renew_deleted_product', 'Renew Deleted Products', '1', 'renew_deleted_product', 'Allow to Renew Deleted Products', '0000-00-00 00:00:00', 0, 0, 'signup', '', '');