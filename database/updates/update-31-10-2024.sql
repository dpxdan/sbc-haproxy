CREATE TABLE IF NOT EXISTS `git_version` (
  `id` int NOT NULL AUTO_INCREMENT,
  `commit_hash` varchar(40) NOT NULL,
  `description` text,
  `title` text,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_current` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`)
);

DROP VIEW IF EXISTS `view_git_version`;

CREATE 
    ALGORITHM = UNDEFINED 
    DEFINER = `fluxuser`@`127.0.0.1` 
    SQL SECURITY INVOKER
VIEW `view_git_version` AS
    SELECT 
        `git_version`.`id` AS `id`,
        `git_version`.`commit_hash` AS `commit_hash`,
        `git_version`.`title` AS `title`,
        `git_version`.`description` AS `description`,
        `git_version`.`updated_at` AS `updated_at`,
        (CASE
            WHEN (`git_version`.`is_current` = 0) THEN CONCAT('Ativo/Atual')
            ELSE CONCAT('Inativo')
        END) AS `is_current`
    FROM
        `git_version`;

INSERT INTO `menu_modules` (`menu_label`, `module_name`, `module_url`, `menu_title`, `menu_image`, `menu_subtitle`, `priority`) VALUES ('Updates', 'update', 'systems/update/', 'Configuration', 'TemplateManagement.png', '0', 90.3);

UPDATE userlevels SET module_permissions = CONCAT(module_permissions, ',', (SELECT id FROM menu_modules WHERE module_name = 'update')) WHERE userlevelid = -1 AND FIND_IN_SET((SELECT id FROM menu_modules WHERE module_name = 'update'), module_permissions) = 0;