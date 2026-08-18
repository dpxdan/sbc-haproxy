SET NAMES utf8mb4;
SET @old_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';

DROP PROCEDURE IF EXISTS `_mig_add_surrogate_pk`;

DELIMITER ;;

CREATE PROCEDURE `_mig_add_surrogate_pk`(IN p_table VARCHAR(64))
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table
    ) THEN
        IF NOT EXISTS (
            SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table
              AND CONSTRAINT_TYPE = 'PRIMARY KEY'
        ) THEN
            IF EXISTS (
                SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table
                  AND COLUMN_NAME = 'id'
            ) THEN
                SET @msg = CONCAT('Migracao HA abortada: tabela sem PRIMARY KEY mas com coluna id preexistente -> ', p_table);
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @msg;
            END IF;

            SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)');
            PREPARE stmt FROM @ddl;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;
    END IF;
END ;;

DELIMITER ;

CALL _mig_add_surrogate_pk('cdrs');
CALL _mig_add_surrogate_pk('cdrs_staging');
CALL _mig_add_surrogate_pk('reseller_cdrs');
CALL _mig_add_surrogate_pk('q850code');

DROP PROCEDURE IF EXISTS `_mig_add_surrogate_pk`;

SET SESSION sql_mode = @old_sql_mode;
