-- Ejecutar en la base de datos: portally_system
-- Añade datos de contacto y fechas a la ficha del colaborador.

USE portally_system;

ALTER TABLE colaboradores
    ADD COLUMN correo_electronico VARCHAR(150) NULL AFTER celular,
    ADD COLUMN fecha_nacimiento DATE NULL AFTER correo_electronico,
    ADD COLUMN fecha_ingreso DATE NULL AFTER fecha_nacimiento;
