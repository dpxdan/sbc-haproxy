SET FOREIGN_KEY_CHECKS=0;

UPDATE cron_settings set status = '1' WHERE name = 'Manage Services' AND command = 'days' AND exec_interval = '1' AND file_path LIKE '%ManageServices%' AND status = '1';

SET FOREIGN_KEY_CHECKS=1;