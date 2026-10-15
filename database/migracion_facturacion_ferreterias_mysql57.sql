-- Pikero: cambios fiscales, claves SAT, ferreterias y permisos.
-- Compatible con MySQL 5.7. Ejecutar una sola vez sobre la base pikero.
USE pikero;

ALTER TABLE clientes
  ADD COLUMN razon_social VARCHAR(255) DEFAULT NULL AFTER nombre;

ALTER TABLE productos
  ADD COLUMN clave_sat VARCHAR(20) NOT NULL DEFAULT '01010101' AFTER codigo,
  ADD COLUMN codigo_unidad_sat VARCHAR(10) NOT NULL DEFAULT 'H87' AFTER clave_sat,
  ADD COLUMN objeto_impuesto VARCHAR(2) NOT NULL DEFAULT '02' AFTER codigo_unidad_sat;

ALTER TABLE ferreterias
  ADD COLUMN razon_social VARCHAR(255) DEFAULT NULL AFTER nombre,
  ADD COLUMN direccion VARCHAR(255) DEFAULT NULL AFTER telefono,
  ADD COLUMN codigo_postal VARCHAR(10) DEFAULT NULL AFTER direccion,
  ADD COLUMN regimen_fiscal VARCHAR(10) DEFAULT NULL AFTER codigo_postal,
  ADD COLUMN uso_cfdi VARCHAR(10) DEFAULT NULL AFTER regimen_fiscal;

ALTER TABLE ventas
  ADD COLUMN factura_date VARCHAR(19) DEFAULT NULL AFTER factura_issuer_id,
  ADD COLUMN factura_payload LONGTEXT NULL AFTER factura_date,
  ADD COLUMN factura_delivery_payload LONGTEXT NULL AFTER factura_payload;

INSERT INTO roles (slug, nombre, descripcion, estatus)
SELECT 'superadmin', 'Superadministrador', 'Administración global de clientes de Pikero.', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE slug = 'superadmin');

INSERT INTO permisos (permiso, slug, descripcion, tipo, bandera, id_categoria, estatus)
SELECT 'Ver ferreterías', 'ver_ferreterias', 'Acceso al directorio global de ferreterías.', 'SELECT', 18, cp.id, 1
FROM categorias_permisos cp
WHERE cp.nombre = 'Configuración'
  AND NOT EXISTS (SELECT 1 FROM permisos WHERE slug = 'ver_ferreterias')
LIMIT 1;

INSERT INTO permisos (permiso, slug, descripcion, tipo, bandera, id_categoria, estatus)
SELECT 'Crear ferreterías', 'crear_ferreterias', 'Alta de clientes ferretería.', 'CREATE', 19, cp.id, 1
FROM categorias_permisos cp
WHERE cp.nombre = 'Configuración'
  AND NOT EXISTS (SELECT 1 FROM permisos WHERE slug = 'crear_ferreterias')
LIMIT 1;

INSERT INTO permisos (permiso, slug, descripcion, tipo, bandera, id_categoria, estatus)
SELECT 'Editar ferreterías', 'editar_ferreterias', 'Edición de datos de clientes ferretería.', 'UPDATE', 20, cp.id, 1
FROM categorias_permisos cp
WHERE cp.nombre = 'Configuración'
  AND NOT EXISTS (SELECT 1 FROM permisos WHERE slug = 'editar_ferreterias')
LIMIT 1;

INSERT INTO permisos (permiso, slug, descripcion, tipo, bandera, id_categoria, estatus)
SELECT 'Cancelar ferreterías', 'cancelar_ferreterias', 'Cancelación lógica de clientes ferretería.', 'CANCEL', 21, cp.id, 1
FROM categorias_permisos cp
WHERE cp.nombre = 'Configuración'
  AND NOT EXISTS (SELECT 1 FROM permisos WHERE slug = 'cancelar_ferreterias')
LIMIT 1;

INSERT IGNORE INTO permisos_roles (id_rol, id_permiso, activo)
SELECT r.id, p.id, 1
FROM roles r
JOIN permisos p ON p.slug IN ('ver_ferreterias', 'crear_ferreterias', 'editar_ferreterias', 'cancelar_ferreterias')
WHERE r.slug = 'superadmin';

-- Para habilitar a un usuario existente como administrador global, ejecutar manualmente:
-- UPDATE usuarios SET id_rol = (SELECT id FROM roles WHERE slug = 'superadmin') WHERE usuario = 'USUARIO_ADMIN';
-- No se asigna este rol automáticamente: permite consultar y modificar datos de todos los tenants.
