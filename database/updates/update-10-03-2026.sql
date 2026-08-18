SET FOREIGN_KEY_CHECKS=0;

UPDATE userlevels
SET module_permissions = concat(module_permissions, ',', (SELECT max(id) FROM menu_modules WHERE module_url = 'localization/localization_list/' AND menu_title = 'Switch'))
WHERE
	userlevelid = 2;

SET FOREIGN_KEY_CHECKS=1;