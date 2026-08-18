INSERT INTO `sweeplist` (`id`, `sweep`) VALUES (1, 'Weekly');

DROP VIEW IF EXISTS view_invoices; 

CREATE 
    ALGORITHM = UNDEFINED 
    DEFINER = `fluxuser`@`127.0.0.1` 
    SQL SECURITY DEFINER
VIEW `view_invoices` AS
    SELECT 
        `invoices`.`id` AS `id`,
        CONCAT(`invoices`.`prefix`, `invoices`.`number`) AS `number`,
        `invoices`.`accountid` AS `accountid`,
        `invoices`.`reseller_id` AS `reseller_id`,
        `invoices`.`from_date` AS `from_date`,
        `invoices`.`to_date` AS `to_date`,
        `invoices`.`due_date` AS `due_date`,
        `invoices`.`status` AS `status`,
        IF(((SELECT 
                    `accounts`.`posttoexternal`
                FROM
                    `accounts`
                WHERE
                    (`accounts`.`id` = `invoices`.`accountid`)) = 0),
            0,
            IF(((SUM(`invoice_details`.`debit`) - SUM(`invoice_details`.`credit`)) = 0),
                0,
                1)) AS `is_paid`,
        `invoices`.`generate_date` AS `generate_date`,
        `invoices`.`type` AS `type`,
        `invoices`.`payment_id` AS `payment_id`,
        `invoices`.`generate_type` AS `generate_type`,
        `invoices`.`confirm` AS `confirm`,
        `invoices`.`notes` AS `notes`,
        `invoices`.`is_deleted` AS `is_deleted`,
        SUM(`invoice_details`.`debit`) AS `debit`,
        SUM((`invoice_details`.`debit` * `invoice_details`.`exchange_rate`)) AS `debit_exchange_rate`,
        SUM(`invoice_details`.`credit`) AS `credit`,
        SUM((`invoice_details`.`credit` * `invoice_details`.`exchange_rate`)) AS `credit_exchange_rate`
    FROM
        (`invoices`
        JOIN `invoice_details` ON ((`invoices`.`id` = `invoice_details`.`invoiceid`)))
    WHERE
        (`invoice_details`.`charge_type` <> 'REFILL')
    GROUP BY `invoice_details`.`invoiceid`;

    DELETE FROM `system` WHERE (`id` = '220');
