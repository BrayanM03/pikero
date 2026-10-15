-- Script de tablas para módulos de ferretería en Pikero
USE pikero;

CREATE TABLE IF NOT EXISTS clientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  razon_social VARCHAR(255) DEFAULT NULL,
  telefono VARCHAR(30) DEFAULT NULL,
  correo VARCHAR(120) DEFAULT NULL,
  rfc VARCHAR(20) DEFAULT NULL,
  direccion VARCHAR(255) DEFAULT NULL,
  codigo_postal VARCHAR(10) DEFAULT NULL,
  regimen_fiscal VARCHAR(10) DEFAULT NULL,
  uso_cfdi VARCHAR(10) DEFAULT NULL,
  es_publico TINYINT(1) NOT NULL DEFAULT 0,
  estatus TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cli_ferreteria (id_ferreteria)
);

CREATE TABLE IF NOT EXISTS proveedores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  rfc VARCHAR(20) DEFAULT NULL,
  telefono VARCHAR(30) DEFAULT NULL,
  correo VARCHAR(120) DEFAULT NULL,
  direccion VARCHAR(255) DEFAULT NULL,
  contacto VARCHAR(120) DEFAULT NULL,
  estatus TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_prov_ferreteria (id_ferreteria)
);

CREATE TABLE IF NOT EXISTS productos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  codigo VARCHAR(60) DEFAULT NULL,
  clave_sat VARCHAR(20) NOT NULL DEFAULT '01010101',
  codigo_unidad_sat VARCHAR(10) NOT NULL DEFAULT 'H87',
  objeto_impuesto VARCHAR(2) NOT NULL DEFAULT '02',
  nombre VARCHAR(150) NOT NULL,
  id_categoria INT DEFAULT NULL,
  id_marca INT DEFAULT NULL,
  id_unidad INT DEFAULT NULL,
  precio_compra DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  precio_venta DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  estatus TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_prod_ferreteria (id_ferreteria),
  INDEX idx_prod_codigo (codigo)
);

CREATE TABLE IF NOT EXISTS inventario (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  id_sucursal INT NOT NULL,
  id_producto INT NOT NULL,
  existencia DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  minimo DECIMAL(12,3) NOT NULL DEFAULT 5.000,
  UNIQUE KEY uq_inv_suc_prod (id_sucursal, id_producto),
  INDEX idx_inv_ferreteria (id_ferreteria)
);

CREATE TABLE IF NOT EXISTS movimientos_inventario (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  id_sucursal INT NOT NULL,
  id_producto INT NOT NULL,
  tipo VARCHAR(20) NOT NULL,
  cantidad DECIMAL(12,3) NOT NULL,
  existencia_antes DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  existencia_despues DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  referencia VARCHAR(80) DEFAULT NULL,
  id_usuario INT DEFAULT NULL,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_mov_ferreteria (id_ferreteria)
);

CREATE TABLE IF NOT EXISTS ventas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  id_sucursal INT NOT NULL,
  id_usuario INT NOT NULL,
  id_cliente INT DEFAULT NULL,
  folio VARCHAR(40) NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  tipo_venta VARCHAR(20) NOT NULL DEFAULT 'contado',
  id_forma_pago INT DEFAULT NULL,
  estatus_factura VARCHAR(20) NOT NULL DEFAULT 'Pendiente',
  factura_issuer_id INT DEFAULT NULL,
  factura_date VARCHAR(19) DEFAULT NULL,
  factura_payload LONGTEXT NULL,
  factura_delivery_payload LONGTEXT NULL,
  factura_id VARCHAR(100) DEFAULT NULL,
  uuid_factura VARCHAR(36) DEFAULT NULL,
  fecha_factura DATETIME DEFAULT NULL,
  factura_rfc VARCHAR(13) DEFAULT NULL,
  factura_razon_social VARCHAR(255) DEFAULT NULL,
  factura_codigo_postal VARCHAR(10) DEFAULT NULL,
  factura_regimen_fiscal VARCHAR(10) DEFAULT NULL,
  factura_uso_cfdi VARCHAR(10) DEFAULT NULL,
  factura_correo VARCHAR(150) DEFAULT NULL,
  factura_metodo_pago VARCHAR(3) DEFAULT NULL,
  factura_forma_pago VARCHAR(3) DEFAULT NULL,
  estatus TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ventas_ferreteria (id_ferreteria),
  INDEX idx_ventas_sucursal (id_sucursal)
);

CREATE TABLE IF NOT EXISTS detalle_ventas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_venta INT NOT NULL,
  id_producto INT NOT NULL,
  cantidad DECIMAL(12,3) NOT NULL,
  precio DECIMAL(12,2) NOT NULL,
  importe DECIMAL(12,2) NOT NULL,
  INDEX idx_det_venta (id_venta)
);

CREATE TABLE IF NOT EXISTS abonos_venta (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_venta INT NOT NULL,
  id_ferreteria INT NOT NULL,
  id_sucursal INT NOT NULL,
  id_usuario INT NOT NULL,
  id_forma_pago INT DEFAULT NULL,
  monto DECIMAL(12,2) NOT NULL,
  observaciones VARCHAR(255) DEFAULT NULL,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_abono_venta (id_venta),
  INDEX idx_abono_tenant (id_ferreteria, id_sucursal)
);

CREATE TABLE IF NOT EXISTS compras (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  id_sucursal INT NOT NULL,
  id_usuario INT NOT NULL,
  id_proveedor INT DEFAULT NULL,
  folio VARCHAR(40) NOT NULL,
  numero_documento VARCHAR(80) DEFAULT NULL,
  fecha_compra DATE DEFAULT NULL,
  total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  id_forma_pago INT DEFAULT NULL,
  observaciones VARCHAR(255) DEFAULT NULL,
  estatus TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_compras_ferreteria (id_ferreteria)
);

CREATE TABLE IF NOT EXISTS detalle_compras (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_compra INT NOT NULL,
  id_producto INT NOT NULL,
  cantidad DECIMAL(12,3) NOT NULL,
  precio_unitario DECIMAL(12,2) NOT NULL,
  importe DECIMAL(12,2) NOT NULL,
  INDEX idx_det_compra (id_compra)
);

CREATE TABLE IF NOT EXISTS cajas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  id_sucursal INT NOT NULL,
  id_usuario INT NOT NULL,
  monto_apertura DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  monto_cierre DECIMAL(12,2) DEFAULT NULL,
  total_ventas DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_entradas DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_salidas DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  monto_esperado DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  diferencia DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  estado VARCHAR(20) NOT NULL DEFAULT 'abierta',
  fecha_apertura DATETIME DEFAULT CURRENT_TIMESTAMP,
  fecha_cierre DATETIME DEFAULT NULL,
  notas TEXT DEFAULT NULL,
  INDEX idx_caja_ferreteria (id_ferreteria)
);

CREATE TABLE IF NOT EXISTS caja_movimientos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_caja INT NOT NULL,
  id_ferreteria INT NOT NULL,
  id_sucursal INT NOT NULL,
  id_usuario INT NOT NULL,
  tipo VARCHAR(20) NOT NULL,
  monto DECIMAL(12,2) NOT NULL,
  motivo VARCHAR(255) NOT NULL,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cajamov_caja (id_caja)
);

-- Asegurar cliente default "Público en general" para todas las ferreterías
INSERT INTO clientes (id_ferreteria, nombre, es_publico, estatus)
SELECT id, 'Público en general', 1, 1 FROM ferreterias f
WHERE NOT EXISTS (
  SELECT 1 FROM clientes c WHERE c.id_ferreteria = f.id AND c.es_publico = 1
);
