-- reset_dia.sql
-- Limpia los votos del dia y libera todas las mesas.
-- Ejecutar antes de cada sesion de prueba del dia de la eleccion.
-- Ejecutar en phpMyAdmin directamente. No exponer en el front.
-- NO toca mesas, dias_eleccion, elecciones, usuarios_fiscal
-- ni ninguna tabla de Consulta Padron.

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE votos_dia;
UPDATE mesas SET en_uso = 0;

SET FOREIGN_KEY_CHECKS = 1;
