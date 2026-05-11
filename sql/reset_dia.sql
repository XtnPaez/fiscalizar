-- reset_dia.sql
-- Limpia los votos del dia y libera todas las mesas.
-- Ejecutar antes de cada sesion de prueba del dia de la eleccion.
-- NO toca mesas, usuarios_fiscal ni ninguna tabla de Consulta Padron.

TRUNCATE TABLE votos_dia;
UPDATE mesas SET en_uso = 0;