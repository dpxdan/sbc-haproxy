SET FOREIGN_KEY_CHECKS=0;

DROP VIEW IF EXISTS `view_dids_reseller`;
CREATE ALGORITHM=UNDEFINED 
DEFINER=`fluxuser`@`127.0.0.1` 
SQL SECURITY INVOKER 
VIEW `view_dids_reseller` AS
SELECT
    d.`id` AS `id`,
    d.`number` AS `number`,
    rp.`id` AS `reseller_product_id`,
    rp.`account_id` AS `account_id`,
    rp.`reseller_id` AS `reseller_id`,
    IF(
        (d.`parent_id` <> rp.`account_id`),
        (
            SELECT subrpro.`account_id`
            FROM `reseller_products` AS subrpro
            WHERE subrpro.`id` > rp.`id`
            ORDER BY subrpro.`id`
            LIMIT 1
        ),
        d.`accountid`
    ) AS `buyer_accountid`,
    d.`country_id` AS `country_id`,
    d.`cost` AS `cost`,
    d.`call_type` AS `call_type`,
    d.`city` AS `city`,
    d.`province` AS `province`,
    d.`leg_timeout` AS `leg_timeout`,
    d.`maxchannels` AS `maxchannels`,
    d.`extensions` AS `extensions`,
    rp.`buy_cost` AS `buy_cost`,
    rp.`setup_fee` AS `setup_fee`,
    rp.`price` AS `price`,
    rp.`billing_type` AS `billing_type`,
    rp.`billing_days` AS `billing_days`,
    rp.`product_id` AS `product_id`,
    rp.`modified_date` AS `modified_date`
FROM
    `reseller_products` AS rp
JOIN
    `dids` AS d ON d.`product_id` = rp.`product_id`
WHERE
    rp.`is_optin` = 0
ORDER BY
    rp.`account_id`;

DELIMITER //

DROP TRIGGER IF EXISTS `cdr_records`;
CREATE DEFINER = `fluxuser`@`127.0.0.1`
TRIGGER `cdr_records`
AFTER INSERT ON `cdrs`
FOR EACH ROW
BEGIN
    INSERT INTO `cdrs_staging` (
        `uniqueid`, `accountid`, `type`, `sip_user`, `callerid`, `callednum`,
        `translated_dst`, `ct`, `billseconds`, `trunk_id`, `trunkip`, `callerip`,
        `disposition`, `callstart`, `debit`, `cost`, `provider_id`, `pricelist_id`,
        `package_id`, `pattern`, `notes`, `invoiceid`, `rate_cost`, `reseller_id`,
        `reseller_code`, `reseller_code_destination`, `reseller_cost`, `provider_code`,
        `provider_code_destination`, `provider_cost`, `provider_call_cost`, `call_direction`,
        `calltype`, `billmsec`, `answermsec`, `waitmsec`, `progress_mediamsec`,
        `flow_billmsec`, `is_recording`, `call_request`, `call_id_cadup`, `country_id`,
        `end_stamp`
    )
    VALUES (
        NEW.`uniqueid`, NEW.`accountid`, NEW.`type`, NEW.`sip_user`, NEW.`callerid`, NEW.`callednum`,
        NEW.`translated_dst`, NEW.`ct`, NEW.`billseconds`, NEW.`trunk_id`, NEW.`trunkip`, NEW.`callerip`,
        NEW.`disposition`, NEW.`callstart`, NEW.`debit`, NEW.`cost`, NEW.`provider_id`, NEW.`pricelist_id`,
        NEW.`package_id`, NEW.`pattern`, NEW.`notes`, NEW.`invoiceid`, NEW.`rate_cost`, NEW.`reseller_id`,
        NEW.`reseller_code`, NEW.`reseller_code_destination`, NEW.`reseller_cost`, NEW.`provider_code`,
        NEW.`provider_code_destination`, NEW.`provider_cost`, NEW.`provider_call_cost`, NEW.`call_direction`,
        NEW.`calltype`, NEW.`billmsec`, NEW.`answermsec`, NEW.`waitmsec`, NEW.`progress_mediamsec`,
        NEW.`flow_billmsec`, NEW.`is_recording`, NEW.`call_request`, NEW.`call_id_cadup`,
        NEW.`country_id`, NEW.`end_stamp`
    );
END;
//

DROP TRIGGER IF EXISTS `activity_reports`;
CREATE DEFINER = `fluxuser`@`127.0.0.1`
TRIGGER `activity_reports`
AFTER INSERT ON `cdrs`
FOR EACH ROW
BEGIN
    IF NEW.`calltype` = 'DID' AND NEW.`call_direction` = 'inbound' THEN
        INSERT INTO `activity_reports` (
            `accountid`, `reseller_id`, `last_did_call_time`, `balance`, `credit_limit`
        )
        VALUES (
            NEW.`accountid`,
            NEW.`reseller_id`,
            NEW.`callstart`,
            (SELECT `balance` FROM `accounts` WHERE `id` = NEW.`accountid`),
            (SELECT `credit_limit` FROM `accounts` WHERE `id` = NEW.`accountid`)
        )
        ON DUPLICATE KEY UPDATE
            `last_did_call_time` = NEW.`callstart`,
            `balance` = VALUES(`balance`),
            `credit_limit` = VALUES(`credit_limit`);

    ELSEIF NEW.`calltype` != 'DID' AND NEW.`call_direction` = 'outbound' THEN
        INSERT INTO `activity_reports` (
            `accountid`, `reseller_id`, `last_outbound_call_time`, `balance`, `credit_limit`
        )
        VALUES (
            NEW.`accountid`,
            NEW.`reseller_id`,
            NEW.`callstart`,
            (SELECT `balance` FROM `accounts` WHERE `id` = NEW.`accountid`),
            (SELECT `credit_limit` FROM `accounts` WHERE `id` = NEW.`accountid`)
        )
        ON DUPLICATE KEY UPDATE
            `last_outbound_call_time` = NEW.`callstart`,
            `balance` = VALUES(`balance`),
            `credit_limit` = VALUES(`credit_limit`);
    END IF;
END;
//

DELIMITER ;

SET FOREIGN_KEY_CHECKS=1;
