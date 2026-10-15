USE pikero;

CREATE TABLE IF NOT EXISTS clientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  telefono VARCHAR(30) DEFAULT NULL,
  correo VARCHAR(120) DEFAULT NULL,
  rfc VARCHAR(20) DEFAULT NULL,
  direccion VARCHAR(255) DEFAULT NULL,
  es_publico TINYINT(1) NOT NULL DEFAULT 0,
  estatus TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_clientes_ferreteria FOREIGN KEY (id_ferreteria) REFERENCES ferreterias(id)
);

CREATE TABLE IF NOT EXISTS productos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  codigo VARCHAR(60) DEFAULT NULL,
  nombre VARCHAR(150) NOT NULL,
  id_categoria INT DEFAULT NULL,
  id_marca INT DEFAULT NULL,
  id_unidad INT DEFAULT NULL,
  precio_compra DECIMAL(12,2) NOT NULL DEFAULT 0,
  precio_venta DECIMAL(12,2) NOT NULL DEFAULT 0,
  estatus TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_productos_ferreteria FOREIGN KEY (id_ferreteria) REFERENCES ferreterias(id)
);

CREATE TABLE IF NOT EXISTS inventario (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  id_sucursal INT NOT NULL,
  id_producto INT NOT NULL,
  existencia DECIMAL(12,3) NOT NULL DEFAULT 0,
  minimo DECIMAL(12,3) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_inv_suc_prod (id_sucursal, id_producto),
  CONSTRAINT fk_inv_ferreteria FOREIGN KEY (id_ferreteria) REFERENCES ferreterias(id),
  CONSTRAINT fk_inv_sucursal FOREIGN KEY (id_sucursal) REFERENCES sucursales(id),
  CONSTRAINT fk_inv_producto FOREIGN KEY (id_producto) REFERENCES productos(id)
);

CREATE TABLE IF NOT EXISTS movimientos_inventario (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  id_sucursal INT NOT NULL,
  id_producto INT NOT NULL,
  tipo VARCHAR(20) NOT NULL,
  cantidad DECIMAL(12,3) NOT NULL,
  existencia_antes DECIMAL(12,3) NOT NULL DEFAULT 0,
  existencia_despues DECIMAL(12,3) NOT NULL DEFAULT 0,
  referencia VARCHAR(80) DEFAULT NULL,
  id_usuario INT DEFAULT NULL,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ventas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  id_sucursal INT NOT NULL,
  id_usuario INT NOT NULL,
  id_cliente INT DEFAULT NULL,
  folio VARCHAR(40) NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  id_forma_pago INT DEFAULT NULL,
  estatus TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ventas_ferreteria FOREIGN KEY (id_ferreteria) REFERENCES ferreterias(id),
  CONSTRAINT fk_ventas_sucursal FOREIGN KEY (id_sucursal) REFERENCES sucursales(id)
);

CREATE TABLE IF NOT EXISTS detalle_ventas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_venta INT NOT NULL,
  id_producto INT NOT NULL,
  cantidad DECIMAL(12,3) NOT NULL,
  precio DECIMAL(12,2) NOT NULL,
  importe DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_detalle_venta FOREIGN KEY (id_venta) REFERENCES ventas(id)
);

INSERT INTO clientes (id_ferreteria, nombre, es_publico, estatus)
SELECT id, 'Público en general', 1, 1 FROM ferreterias f
WHERE NOT EXISTS (
  SELECT 1 FROM clientes c WHERE c.id_ferreteria = f.id AND c.es_publico = 1
);

ALTER TABLE compras
  ADD COLUMN numero_documento VARCHAR(80) DEFAULT NULL AFTER folio,
  ADD COLUMN fecha_compra DATE DEFAULT NULL AFTER numero_documento,
  ADD COLUMN id_forma_pago INT DEFAULT NULL AFTER total,
  ADD COLUMN observaciones VARCHAR(255) DEFAULT NULL AFTER id_forma_pago;
