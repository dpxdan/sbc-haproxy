
UPDATE userlevels ul CROSS JOIN( SELECT GROUP_CONCAT(id) id FROM menu_modules WHERE module_url IN('event_guard/event_guard_list/')) mm SET ul.module_permissions =( CASE WHEN mm.id IS NOT NULL THEN REPLACE (ul.module_permissions, CONCAT(",",mm.id ), '') ELSE ul.module_permissions END ) WHERE ul.userlevelid IN (-1, 1, 0);
UPDATE userlevels ul CROSS JOIN( SELECT GROUP_CONCAT(id) id FROM menu_modules WHERE module_url IN('event_guard/event_guard_whitelist_list/')) mm SET ul.module_permissions =( CASE WHEN mm.id IS NOT NULL THEN REPLACE (ul.module_permissions, CONCAT(",",mm.id ), '') ELSE ul.module_permissions END ) WHERE ul.userlevelid IN (-1, 1, 0);

delete from `menu_modules` where `module_name`='event_guard';

delete from `roles_and_permission` where `module_name`='event_guard';

