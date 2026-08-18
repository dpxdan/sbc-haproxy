SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE planos_voip_externos;
ALTER TABLE planos_voip_externos ADD UNIQUE KEY uq_id_plataforma (id_plataforma);

SET FOREIGN_KEY_CHECKS = 1;