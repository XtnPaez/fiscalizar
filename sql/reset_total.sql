-- reset_total.sql
-- Borra todo el contenido de las tablas propias de Fiscalizacion.
-- Ejecutar cuando se quiere empezar de cero absoluto.
-- Ejecutar en phpMyAdmin directamente. No exponer en el front.
-- NO toca ninguna tabla de Consulta Padron.
-- ATENCION: borra usuarios_fiscal incluyendo el superadmin.
-- Despues de ejecutar, recrear el superadmin con generar_usuario_fiscal.php.

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Votos del dia (sin hijos, va primero)
TRUNCATE TABLE votos_dia;

-- 2. Mesas (depende de dias_eleccion, va antes que dias)
DELETE FROM mesas;
ALTER TABLE mesas AUTO_INCREMENT = 1;

-- 3. Dias (depende de elecciones, va antes que elecciones se toque)
DELETE FROM dias_eleccion;
ALTER TABLE dias_eleccion AUTO_INCREMENT = 1;

-- 4. Usuarios fiscales (independiente)
DELETE FROM usuarios_fiscal;
ALTER TABLE usuarios_fiscal AUTO_INCREMENT = 1;

-- 5. Elecciones: vuelven todas a estado programada
-- No se borran — son el historial real del sistema.
-- Solo se resetea el estado para poder activarlas con datos reales.
UPDATE elecciones SET estado = 'programada';

SET FOREIGN_KEY_CHECKS = 1;
