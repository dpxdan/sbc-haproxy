SET FOREIGN_KEY_CHECKS=0;

UPDATE `menu_modules`
SET `menu_subtitle` = 'Event Guard'
WHERE `module_url` IN ('event_guard/event_guard_list/', 'event_guard/event_guard_whitelist_list/');

UPDATE `roles_and_permission`
SET `sub_module_name` = 'event_guard'
WHERE `module_name` = 'event_guard';

SET FOREIGN_KEY_CHECKS=1;
