CREATE DATABASE IF NOT EXISTS pikero CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pikero;

SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW IF EXISTS vista_usuarios;
DROP TABLE IF EXISTS permisos_usuarios;
DROP TABLE IF EXISTS permisos_roles;
DROP TABLE IF EXISTS permisos;
DROP TABLE IF EXISTS categorias_permisos;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS formas_pago;
DROP TABLE IF EXISTS unidades;
DROP TABLE IF EXISTS marcas;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS sucursales;
DROP TABLE IF EXISTS ferreterias;
DROP TABLE IF EXISTS roles;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE ferreterias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  razon_social VARCHAR(255) DEFAULT NULL,
  rfc VARCHAR(20) DEFAULT NULL,
  telefono VARCHAR(30) DEFAULT NULL,
  correo VARCHAR(120) DEFAULT NULL,
  logo VARCHAR(255) DEFAULT NULL,
  direccion VARCHAR(255) DEFAULT NULL,
  codigo_postal VARCHAR(10) DEFAULT NULL,
  regimen_fiscal VARCHAR(10) DEFAULT NULL,
  uso_cfdi VARCHAR(10) DEFAULT NULL,
  estatus TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE sucursales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  direccion VARCHAR(255) DEFAULT NULL,
  telefono VARCHAR(30) DEFAULT NULL,
  logo VARCHAR(255) DEFAULT NULL,
  estatus TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sucursales_ferreteria FOREIGN KEY (id_ferreteria) REFERENCES ferreterias(id)
);

CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(40) NOT NULL UNIQUE,
  nombre VARCHAR(80) NOT NULL,
  descripcion VARCHAR(255) DEFAULT NULL,
  estatus TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  id_sucursal INT NOT NULL,
  id_rol INT NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  apellido VARCHAR(80) NOT NULL DEFAULT '',
  telefono VARCHAR(30) DEFAULT NULL,
  correo VARCHAR(120) DEFAULT NULL,
  cargo VARCHAR(80) DEFAULT NULL,
  usuario VARCHAR(80) NOT NULL UNIQUE,
  `contraseña` VARCHAR(255) NOT NULL,
  foto_perfil VARCHAR(255) DEFAULT NULL,
  estatus TINYINT(1) NOT NULL DEFAULT 1,
  fecha_ingreso DATETIME DEFAULT NULL,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuarios_ferreteria FOREIGN KEY (id_ferreteria) REFERENCES ferreterias(id),
  CONSTRAINT fk_usuarios_sucursal FOREIGN KEY (id_sucursal) REFERENCES sucursales(id),
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (id_rol) REFERENCES roles(id)
);

CREATE TABLE categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  descripcion VARCHAR(255) DEFAULT NULL,
  estatus TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_categorias_ferreteria FOREIGN KEY (id_ferreteria) REFERENCES ferreterias(id)
);

CREATE TABLE marcas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  descripcion VARCHAR(255) DEFAULT NULL,
  estatus TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_marcas_ferreteria FOREIGN KEY (id_ferreteria) REFERENCES ferreterias(id)
);

CREATE TABLE unidades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  abreviatura VARCHAR(20) DEFAULT NULL,
  descripcion VARCHAR(255) DEFAULT NULL,
  estatus TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_unidades_ferreteria FOREIGN KEY (id_ferreteria) REFERENCES ferreterias(id)
);

CREATE TABLE formas_pago (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  descripcion VARCHAR(255) DEFAULT NULL,
  estatus TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_formas_pago_ferreteria FOREIGN KEY (id_ferreteria) REFERENCES ferreterias(id)
);

CREATE TABLE categorias_permisos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  estatus TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE permisos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  permiso VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  descripcion VARCHAR(255) DEFAULT NULL,
  tipo VARCHAR(40) DEFAULT 'SELECT',
  bandera INT DEFAULT 0,
  id_categoria INT DEFAULT NULL,
  estatus TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_permisos_categoria FOREIGN KEY (id_categoria) REFERENCES categorias_permisos(id)
);

CREATE TABLE permisos_roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_rol INT NOT NULL,
  id_permiso INT NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_rol_permiso (id_rol, id_permiso),
  CONSTRAINT fk_pr_rol FOREIGN KEY (id_rol) REFERENCES roles(id),
  CONSTRAINT fk_pr_permiso FOREIGN KEY (id_permiso) REFERENCES permisos(id)
);

CREATE TABLE permisos_usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  id_permiso INT NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_usuario_permiso (id_usuario, id_permiso),
  CONSTRAINT fk_pu_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id),
  CONSTRAINT fk_pu_permiso FOREIGN KEY (id_permiso) REFERENCES permisos(id)
);

CREATE OR REPLACE VIEW vista_usuarios AS
SELECT
  u.*,
  r.nombre AS nombre_rol,
  r.slug AS slug_rol,
  s.nombre AS sucursal,
  s.logo AS logo_sucursal,
  f.nombre AS ferreteria,
  f.logo AS logo_ferreteria
FROM usuarios u
INNER JOIN roles r ON r.id = u.id_rol
INNER JOIN sucursales s ON s.id = u.id_sucursal
INNER JOIN ferreterias f ON f.id = u.id_ferreteria;

INSERT INTO ferreterias (id, nombre, correo, estatus) VALUES
  (1, 'Ferretería Demo', 'demo@pikero.local', 1);

INSERT INTO sucursales (id, id_ferreteria, nombre, direccion, estatus) VALUES
  (1, 1, 'Matriz', 'Sucursal principal', 1);

INSERT INTO roles (id, slug, nombre, descripcion, estatus) VALUES
  (1, 'administrador', 'Administrador', 'Dueño de la ferretería. Acceso total de su empresa.', 1),
  (2, 'gerente', 'Gerente', 'Responsable de la tienda / sucursal.', 1),
  (3, 'vendedor', 'Vendedor', 'Punto de venta, clientes y caja.', 1),
  (4, 'inventarista', 'Inventarista', 'Responsable de inventarios, compras y catálogos.', 1),
  (5, 'superadmin', 'Superadministrador', 'Administración global de clientes de Pikero.', 1);

INSERT INTO categorias_permisos (id, nombre) VALUES
  (1, 'Panel'),
  (2, 'Ventas'),
  (3, 'Inventario'),
  (4, 'Catálogos'),
  (5, 'Compras'),
  (6, 'Caja'),
  (7, 'Usuarios'),
  (8, 'Configuración');

INSERT INTO permisos (permiso, slug, descripcion, tipo, bandera, id_categoria) VALUES
  ('Ver panel principal', 'ver_panel_principal', 'Acceso al dashboard', 'SELECT', 1, 1),
  ('Ver punto de venta', 'ver_punto_venta', 'Acceso al POS', 'SELECT', 1, 2),
  ('Crear ventas', 'crear_ventas', 'Registrar ventas', 'CREATE', 2, 2),
  ('Ver ventas', 'ver_ventas', 'Historial de ventas', 'SELECT', 3, 2),
  ('Cancelar ventas', 'cancelar_ventas', 'Cancelar tickets', 'CANCEL', 4, 2),
  ('Ver productos', 'ver_productos', 'Catálogo de productos', 'SELECT', 1, 3),
  ('Crear productos', 'crear_productos', 'Alta de productos', 'CREATE', 2, 3),
  ('Editar productos', 'editar_productos', 'Edición de productos', 'UPDATE', 3, 3),
  ('Cancelar productos', 'cancelar_productos', 'Baja de productos', 'CANCEL', 4, 3),
  ('Ver inventario', 'ver_inventario', 'Existencias', 'SELECT', 5, 3),
  ('Ajustar inventario', 'ajustar_inventario', 'Ajustes de stock', 'UPDATE', 6, 3),
  ('Ver clientes', 'ver_clientes', 'Directorio de clientes', 'SELECT', 1, 4),
  ('Crear clientes', 'crear_clientes', 'Alta de clientes', 'CREATE', 2, 4),
  ('Editar clientes', 'editar_clientes', 'Edición de clientes', 'UPDATE', 3, 4),
  ('Cancelar clientes', 'cancelar_clientes', 'Baja de clientes', 'CANCEL', 4, 4),
  ('Ver proveedores', 'ver_proveedores', 'Directorio de proveedores', 'SELECT', 5, 4),
  ('Crear proveedores', 'crear_proveedores', 'Alta de proveedores', 'CREATE', 6, 4),
  ('Editar proveedores', 'editar_proveedores', 'Edición de proveedores', 'UPDATE', 7, 4),
  ('Cancelar proveedores', 'cancelar_proveedores', 'Baja de proveedores', 'CANCEL', 8, 4),
  ('Ver catálogos', 'ver_catalogos', 'Menú de catálogos', 'SELECT', 9, 4),
  ('Crear catálogos', 'crear_catalogos', 'Alta en catálogos', 'CREATE', 10, 4),
  ('Editar catálogos', 'editar_catalogos', 'Edición de catálogos', 'UPDATE', 11, 4),
  ('Cancelar catálogos', 'cancelar_catalogos', 'Baja de catálogos', 'CANCEL', 12, 4),
  ('Ver compras', 'ver_compras', 'Historial de compras', 'SELECT', 1, 5),
  ('Crear compras', 'crear_compras', 'Registrar compras', 'CREATE', 2, 5),
  ('Cancelar compras', 'cancelar_compras', 'Cancelar compras', 'CANCEL', 3, 5),
  ('Ver caja', 'ver_caja', 'Módulo de caja', 'SELECT', 1, 6),
  ('Abrir caja', 'abrir_caja', 'Apertura de turno', 'CREATE', 2, 6),
  ('Cerrar caja', 'cerrar_caja', 'Cierre de turno', 'UPDATE', 3, 6),
  ('Ver usuarios', 'ver_usuarios', 'Listado de usuarios', 'SELECT', 1, 7),
  ('Crear usuarios', 'crear_usuarios', 'Alta de usuarios', 'CREATE', 2, 7),
  ('Editar usuarios', 'editar_usuarios', 'Edición de usuarios', 'UPDATE', 3, 7),
  ('Cancelar usuarios', 'cancelar_usuarios', 'Baja de usuarios', 'CANCEL', 4, 7),
  ('Ver sucursales', 'ver_sucursales', 'Administrar sucursales', 'SELECT', 1, 8),
  ('Crear sucursales', 'crear_sucursales', 'Alta de sucursales', 'CREATE', 2, 8),
  ('Editar sucursales', 'editar_sucursales', 'Edición de sucursales', 'UPDATE', 3, 8),
  ('Cancelar sucursales', 'cancelar_sucursales', 'Baja de sucursales', 'CANCEL', 4, 8),
  ('Cambiar sucursal', 'cambiar_sucursal', 'Cambiar sucursal activa', 'UPDATE', 5, 8),
  ('Ver permisos', 'ver_permisos', 'Ver permisos', 'SELECT', 6, 8),
  ('Crear permisos', 'crear_permisos', 'Alta de permisos', 'CREATE', 7, 8),
  ('Activar permisos', 'activar_permisos', 'Activar permisos', 'UPDATE', 8, 8),
  ('Desactivar permisos', 'desactivar_permisos', 'Desactivar permisos', 'UPDATE', 9, 8),
  ('Ver panel permisos', 'ver_panel_permisos', 'Panel de permisos', 'SELECT', 10, 8),
  ('Ver permisos usuarios', 'ver_permisos_usuarios', 'Permisos por usuario', 'SELECT', 11, 8),
  ('Ver roles', 'ver_roles', 'Listado de roles', 'SELECT', 12, 8),
  ('Crear roles', 'crear_roles', 'Alta de roles', 'CREATE', 13, 8),
  ('Editar roles', 'editar_roles', 'Edición de roles', 'UPDATE', 14, 8),
  ('Cancelar roles', 'cancelar_roles', 'Baja de roles', 'CANCEL', 15, 8),
  ('Ver perfil', 'ver_perfil', 'Ver perfil', 'SELECT', 16, 8),
  ('Editar perfil', 'editar_perfil', 'Editar perfil', 'UPDATE', 17, 8),
  ('Ver ferreterías', 'ver_ferreterias', 'Acceso al directorio global de ferreterías', 'SELECT', 18, 8),
  ('Crear ferreterías', 'crear_ferreterias', 'Alta de clientes ferretería', 'CREATE', 19, 8),
  ('Editar ferreterías', 'editar_ferreterias', 'Edición de datos de clientes ferretería', 'UPDATE', 20, 8),
  ('Cancelar ferreterías', 'cancelar_ferreterias', 'Baja lógica de clientes ferretería', 'CANCEL', 21, 8);

INSERT INTO usuarios (nombre, apellido, telefono, correo, cargo, usuario, `contraseña`, estatus, fecha_ingreso, id_rol, id_sucursal, id_ferreteria)
VALUES (
  'Admin',
  'Pikero',
  NULL,
  'admin@pikero.local',
  'Administrador',
  'admin',
  '$2y$12$s2Bky2x/THCIsZRw9UmVleIHMfngHNvjFg9T1NWZOz18/0tr5PkHu',
  1,
  NOW(),
  1,
  1,
  1
);

INSERT INTO categorias (id_ferreteria, nombre, descripcion) VALUES
  (1, 'Herramientas', 'Herramientas de mano y eléctricas'),
  (1, 'Tornillería', 'Tornillos, tuercas y pijas'),
  (1, 'Plomería', 'Conexiones, tubos y válvulas'),
  (1, 'Eléctrico', 'Cables, contactos y lámparas');

INSERT INTO marcas (id_ferreteria, nombre) VALUES
  (1, 'Truper'),
  (1, 'Pretul'),
  (1, 'Fiero'),
  (1, 'Genérico');

INSERT INTO unidades (id_ferreteria, nombre, abreviatura) VALUES
  (1, 'Pieza', 'pz'),
  (1, 'Caja', 'cja'),
  (1, 'Metro', 'm'),
  (1, 'Kilogramo', 'kg');

INSERT INTO formas_pago (id_ferreteria, nombre) VALUES
  (1, 'Efectivo'),
  (1, 'Tarjeta'),
  (1, 'Transferencia'),
  (1, 'Crédito');

-- Administrador: todos los permisos
INSERT INTO permisos_roles (id_rol, id_permiso, activo)
SELECT 1, id, 1 FROM permisos;

-- Gerente: operación de tienda (sin crear permisos del sistema)
INSERT INTO permisos_roles (id_rol, id_permiso, activo)
SELECT 2, id, 1 FROM permisos
WHERE slug IN (
  'ver_panel_principal',
  'ver_punto_venta','crear_ventas','ver_ventas','cancelar_ventas',
  'ver_productos','crear_productos','editar_productos',
  'ver_inventario','ajustar_inventario',
  'ver_clientes','crear_clientes','editar_clientes',
  'ver_proveedores','crear_proveedores','editar_proveedores',
  'ver_catalogos','crear_catalogos','editar_catalogos','cancelar_catalogos',
  'ver_compras','crear_compras',
  'ver_caja','abrir_caja','cerrar_caja',
  'ver_usuarios','crear_usuarios','editar_usuarios',
  'ver_sucursales','crear_sucursales','editar_sucursales','cambiar_sucursal',
  'ver_perfil','editar_perfil'
);

-- Vendedor
INSERT INTO permisos_roles (id_rol, id_permiso, activo)
SELECT 3, id, 1 FROM permisos
WHERE slug IN (
  'ver_panel_principal',
  'ver_punto_venta','crear_ventas','ver_ventas',
  'ver_productos',
  'ver_clientes','crear_clientes','editar_clientes',
  'ver_caja','abrir_caja','cerrar_caja',
  'ver_perfil','editar_perfil'
);

-- Inventarista
INSERT INTO permisos_roles (id_rol, id_permiso, activo)
SELECT 4, id, 1 FROM permisos
WHERE slug IN (
  'ver_panel_principal',
  'ver_productos','crear_productos','editar_productos',
  'ver_inventario','ajustar_inventario',
  'ver_proveedores','crear_proveedores','editar_proveedores',
  'ver_catalogos','crear_catalogos','editar_catalogos','cancelar_catalogos',
  'ver_compras','crear_compras',
  'ver_perfil','editar_perfil'
);

INSERT INTO permisos_roles (id_rol, id_permiso, activo)
SELECT 5, id, 1 FROM permisos
WHERE slug IN ('ver_ferreterias', 'crear_ferreterias', 'editar_ferreterias', 'cancelar_ferreterias');
