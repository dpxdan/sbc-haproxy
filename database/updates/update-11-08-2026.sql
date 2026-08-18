START TRANSACTION;

DELETE FROM `roles_and_permission` WHERE `module_name` = 'sngrep_captures' AND `module_url` = 'sngrep_captures_list';

COMMIT;
