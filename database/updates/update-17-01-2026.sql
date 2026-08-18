SET FOREIGN_KEY_CHECKS=0;

UPDATE `accounts` SET `is_recording` = 1 WHERE `is_recording` = 0;

UPDATE `system` SET `value` = 1 WHERE `name` = 'is_recording' and `value` = 0;

SET FOREIGN_KEY_CHECKS=1;