
        ALTER TABLE ventas ADD COLUMN costo DECIMAL(12,2) AFTER total;

ALTER TABLE `productos`
    ADD COLUMN `costo_incluye_iva` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = el costo capturado incluye IVA'
        AFTER `precio_compra`,

    ADD COLUMN `tasa_iva_costo` DECIMAL(5,4) NOT NULL DEFAULT 0.1600
        COMMENT 'Tasa decimal: 0.1600 = 16%'
        AFTER `costo_incluye_iva`,

    ADD COLUMN `costo_iva_acreditable` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = IVA de compra acreditable confirmado'
        AFTER `tasa_iva_costo`,

    ADD COLUMN `tipo_iva_venta` VARCHAR(12) NOT NULL DEFAULT '0.16'
        COMMENT '0.16, 0.08, 0, exento o no_objeto'
        AFTER `precio_venta`,

    ADD COLUMN `precio_venta_incluye_iva` TINYINT(1) NOT NULL DEFAULT 1
        COMMENT '1 = precio final con IVA incluido'
        AFTER `tipo_iva_venta`,

    ADD COLUMN `sat_descripcion` VARCHAR(1000) DEFAULT NULL
        AFTER `objeto_impuesto`,

    ADD COLUMN `sat_unidad_texto` VARCHAR(50) DEFAULT NULL
        AFTER `sat_descripcion`;


---MULTIPLES FORMA DE PAGO---

CREATE TABLE `detalle_pagos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_ferreteria` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `id_venta` int(11) DEFAULT NULL,
  `id_abono_venta` int(11) DEFAULT NULL,
  `id_forma_pago` int(11) NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dp_venta` (`id_venta`),
  KEY `idx_dp_abono` (`id_abono_venta`),
  KEY `idx_dp_forma` (`id_forma_pago`),
  KEY `idx_dp_reporte` (`id_ferreteria`,`id_sucursal`,`fecha_registro`),
  CONSTRAINT `chk_dp_origen` CHECK (
    (`id_venta` IS NOT NULL AND `id_abono_venta` IS NULL) OR
    (`id_venta` IS NULL AND `id_abono_venta` IS NOT NULL)
  ),
  CONSTRAINT `chk_dp_monto` CHECK (`monto` > 0),
  CONSTRAINT `fk_dp_venta` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id`),
  CONSTRAINT `fk_dp_abono` FOREIGN KEY (`id_abono_venta`) REFERENCES `abonos_venta` (`id`),
  CONSTRAINT `fk_dp_forma` FOREIGN KEY (`id_forma_pago`) REFERENCES `formas_pago` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;

ALTER TABLE formas_pago ADD COLUMN es_efectivo tinyint(1) NOT NULL DEFAULT 0;

INSERT INTO detalle_pagos (id_ferreteria, id_sucursal, id_venta, id_forma_pago, monto, fecha_registro)
SELECT id_ferreteria, id_sucursal, id, id_forma_pago, total, fecha_registro
FROM ventas
WHERE tipo_venta = 'contado' AND id_forma_pago IS NOT NULL;

INSERT INTO detalle_pagos (id_ferreteria, id_sucursal, id_abono_venta, id_forma_pago, monto, fecha_registro)
SELECT id_ferreteria, id_sucursal, id, id_forma_pago, monto, fecha_registro
FROM abonos_venta
WHERE id_forma_pago IS NOT NULL AND monto > 0;

---Gastos
ALTER TABLE gastos ADD COLUMN id_forma_pago INT(11) AFTER monto