-- reset_total.sql
-- Borra todo el contenido de las tablas propias de Fiscalizacion.
-- Ejecutar cuando se quiere empezar de cero absoluto.
-- NO toca ninguna tabla de Consulta Padron.

TRUNCATE TABLE votos_dia;
DELETE FROM mesas;
DELETE FROM usuarios_fiscal;
ALTER TABLE mesas AUTO_INCREMENT = 1;
ALTER TABLE usuarios_fiscal AUTO_INCREMENT = 1;