SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `sip_device_routing`
    ADD COLUMN `call_forwarding_destination_type` TINYINT NOT NULL DEFAULT 2,
    ADD COLUMN `on_busy_destination_type`         TINYINT NOT NULL DEFAULT 2,
    ADD COLUMN `no_answer_destination_type`       TINYINT NOT NULL DEFAULT 2,
    ADD COLUMN `not_register_destination_type`    TINYINT NOT NULL DEFAULT 2;

SET FOREIGN_KEY_CHECKS = 1;