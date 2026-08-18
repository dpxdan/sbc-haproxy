SET FOREIGN_KEY_CHECKS = 0;

CREATE OR REPLACE
    ALGORITHM = UNDEFINED
    SQL SECURITY INVOKER
VIEW `packages_view` AS
SELECT
    O.order_id              AS id,
    P.id                    AS product_id,
    P.name                  AS package_name,
    O.free_minutes          AS free_minutes,
    (O.free_minutes * 60)   AS free_secs,
    P.applicable_for        AS applicable_for,
    O.accountid             AS accountid,
    O.is_terminated         AS is_terminated,
    O.price                 AS price,
    P.status                AS status,
    C.id                    AS counter_id,
    C.status                AS counter_status,
    C.package_id            AS counters_package_id,
    C.used_seconds          AS counters_used_seconds,
    ROUND(C.used_seconds / 60, 0) AS counters_used_minutes
FROM products P
INNER JOIN order_items O ON O.product_id = P.id
INNER JOIN counters    C ON C.package_id = O.order_id
WHERE P.product_category = 1
  AND P.status = 0
  AND (
        O.termination_date >= UTC_TIMESTAMP()
     OR O.termination_date = '0000-00-00 00:00:00'
  );

SET FOREIGN_KEY_CHECKS = 1;