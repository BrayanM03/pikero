-- Ejecutar sobre la base pikero para habilitar el módulo de gastos.
USE pikero;

CREATE TABLE IF NOT EXISTS categorias_gastos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  estatus TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_categoria_gasto_ferreteria (id_ferreteria, nombre),
  INDEX idx_categoria_gasto_ferreteria (id_ferreteria),
  CONSTRAINT fk_categoria_gasto_ferreteria
    FOREIGN KEY (id_ferreteria) REFERENCES ferreterias(id)
);

CREATE TABLE IF NOT EXISTS gastos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ferreteria INT NOT NULL,
  id_sucursal INT NOT NULL,
  id_categoria INT NOT NULL,
  id_usuario INT NOT NULL,
  monto DECIMAL(12,2) NOT NULL,
  fecha DATE NOT NULL,
  folio VARCHAR(100) DEFAULT NULL,
  descripcion VARCHAR(500) NOT NULL,
  comprobante VARCHAR(500) DEFAULT NULL,
  estatus TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME DEFAULT NULL,
  INDEX idx_gastos_ferreteria_sucursal_fecha (id_ferreteria, id_sucursal, fecha),
  INDEX idx_gastos_categoria (id_categoria),
  CONSTRAINT fk_gastos_ferreteria FOREIGN KEY (id_ferreteria) REFERENCES ferreterias(id),
  CONSTRAINT fk_gastos_sucursal FOREIGN KEY (id_sucursal) REFERENCES sucursales(id),
  CONSTRAINT fk_gastos_categoria FOREIGN KEY (id_categoria) REFERENCES categorias_gastos(id),
  CONSTRAINT fk_gastos_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
);

INSERT INTO permisos (permiso, slug, descripcion, tipo, bandera, id_categoria, estatus)
SELECT 'Ver gastos', 'ver_gastos', 'Consultar gastos de la ferretería', 'SELECT', 22, 5, 1
WHERE NOT EXISTS (SELECT 1 FROM permisos WHERE slug = 'ver_gastos');
INSERT INTO permisos (permiso, slug, descripcion, tipo, bandera, id_categoria, estatus)
SELECT 'Crear gastos', 'crear_gastos', 'Registrar gastos y categorías de gastos', 'CREATE', 23, 5, 1
WHERE NOT EXISTS (SELECT 1 FROM permisos WHERE slug = 'crear_gastos');
INSERT INTO permisos (permiso, slug, descripcion, tipo, bandera, id_categoria, estatus)
SELECT 'Editar gastos', 'editar_gastos', 'Modificar gastos registrados', 'UPDATE', 24, 5, 1
WHERE NOT EXISTS (SELECT 1 FROM permisos WHERE slug = 'editar_gastos');
INSERT INTO permisos (permiso, slug, descripcion, tipo, bandera, id_categoria, estatus)
SELECT 'Cancelar gastos', 'cancelar_gastos', 'Cancelar gastos conservando su historial', 'CANCEL', 25, 5, 1
WHERE NOT EXISTS (SELECT 1 FROM permisos WHERE slug = 'cancelar_gastos');

-- Administrador y gerente pueden gestionar gastos. Otros roles requieren asignación explícita.
INSERT INTO permisos_roles (id_rol, id_permiso, activo)
SELECT r.id, p.id, 1
FROM roles r
CROSS JOIN permisos p
WHERE r.slug IN ('administrador', 'gerente')
  AND p.slug IN ('ver_gastos', 'crear_gastos', 'editar_gastos', 'cancelar_gastos')
ON DUPLICATE KEY UPDATE activo = VALUES(activo);