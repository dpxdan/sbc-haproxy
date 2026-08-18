SET FOREIGN_KEY_CHECKS=0;

UPDATE userlevels ul CROSS JOIN( SELECT GROUP_CONCAT(id) id FROM menu_modules WHERE module_url IN('api_endpoints/partners_endpoints_list/','api_endpoints/partners_list/')) mm SET ul.module_permissions =( CASE WHEN mm.id IS NOT NULL THEN REPLACE (ul.module_permissions, CONCAT(",",mm.id ), '') ELSE ul.module_permissions END ) WHERE ul.userlevelid = -1;

SET FOREIGN_KEY_CHECKS=1;