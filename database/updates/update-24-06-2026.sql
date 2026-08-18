SET FOREIGN_KEY_CHECKS=0;

UPDATE userlevels
SET module_permissions = CONCAT(
    TRIM(BOTH ',' FROM
        REGEXP_REPLACE(
            CONCAT(',', module_permissions, ','),
            ',564,',
            ','
        )
    ),
    CASE WHEN FIND_IN_SET(563, module_permissions) = 0 THEN ',563' ELSE '' END
)
WHERE userlevelname = 'Reseller';

SET FOREIGN_KEY_CHECKS=1;