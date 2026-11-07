
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

--Correr desde aqui
---3 nov 2026 Migracion de canclacion
ALTER TABLE ventas ADD COLUMN factura_intent_at DATETIME NULL AFTER factura_date;

ALTER TABLE ventas
  ADD COLUMN factura_cancelacion_motivo CHAR(2) NULL,
  ADD COLUMN factura_cancelacion_uuid_sustituto CHAR(36) NULL,
  ADD COLUMN factura_cancelacion_fecha DATETIME NULL;

  -- Ejecutar sobre respaldo primero. No modifica importes históricos.
ALTER TABLE ventas
  ADD COLUMN descuento_productos DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER subtotal,
  ADD COLUMN descuento_general DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER descuento_productos;

ALTER TABLE detalle_ventas
  MODIFY COLUMN cantidad DECIMAL(12,3) NOT NULL,
  ADD COLUMN importe_bruto DECIMAL(12,2) NULL AFTER importe,
  ADD COLUMN descuento_producto DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER importe_bruto,
  ADD COLUMN descuento_general DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER descuento_producto,
  ADD COLUMN objeto_impuesto CHAR(2) NULL AFTER descuento_general,
  ADD COLUMN tipo_iva_venta VARCHAR(12) NULL AFTER objeto_impuesto,
  ADD COLUMN precio_venta_incluye_iva TINYINT(1) NULL AFTER tipo_iva_venta,
  ADD COLUMN clave_sat VARCHAR(20) NULL AFTER precio_venta_incluye_iva,
  ADD COLUMN codigo_unidad_sat VARCHAR(10) NULL AFTER clave_sat,
  ADD COLUMN descripcion_producto VARCHAR(255) NULL AFTER codigo_unidad_sat,
  ADD COLUMN unidad_producto VARCHAR(60) NULL AFTER descripcion_producto;
-- Las filas antiguas quedan con snapshots NULL; NO rellenarlos desde productos actuales.


-- APLICAR EN DESARROLLO CON RESPALDO PREVIO
-- Diseñada para el esquema Pikero del 05/11/2026 (con o sin migración de descuentos).
-- No repetir; verificar SHOW TABLES antes.
ALTER TABLE `ventas`
  MODIFY COLUMN `estatus_factura` VARCHAR(40) NOT NULL DEFAULT 'Pendiente';

CREATE TABLE `cfdi_documentos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_venta` INT(11) NOT NULL,
  `id_ferreteria` INT(11) NOT NULL,
  `id_sucursal` INT(11) NOT NULL,
  `version` SMALLINT UNSIGNED NOT NULL,
  `tipo_cfdi` CHAR(1) NOT NULL DEFAULT 'I',
  `folio` VARCHAR(80) NOT NULL,
  `fecha_cfdi` VARCHAR(19) DEFAULT NULL,
  `issuer_id` INT(11) NOT NULL,
  `facturama_id` VARCHAR(100) DEFAULT NULL,
  `uuid` CHAR(36) DEFAULT NULL,
  `estado` VARCHAR(40) NOT NULL,
  `relacion_tipo` CHAR(2) DEFAULT NULL,
  `uuid_relacionado` CHAR(36) DEFAULT NULL,
  `documento_sustituido_id` BIGINT UNSIGNED DEFAULT NULL,
  `motivo_cancelacion` CHAR(2) DEFAULT NULL,
  `uuid_sustituto` CHAR(36) DEFAULT NULL,
  `sat_status` VARCHAR(40) DEFAULT NULL,
  `fecha_cancelacion` DATETIME DEFAULT NULL,
  `correo` VARCHAR(150) DEFAULT NULL,
  `payload_json` LONGTEXT DEFAULT NULL,
  `fecha_intento` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cfdi_venta_version` (`id_venta`,`version`),
  UNIQUE KEY `uq_cfdi_uuid` (`uuid`),
  UNIQUE KEY `uq_cfdi_folio_emisor` (`id_ferreteria`,`issuer_id`,`folio`),
  KEY `idx_cfdi_tenant_venta` (`id_ferreteria`,`id_sucursal`,`id_venta`),
  KEY `idx_cfdi_estado` (`estado`),
  CONSTRAINT `fk_cfdi_documentos_venta` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id`),
  CONSTRAINT `fk_cfdi_documentos_anterior` FOREIGN KEY (`documento_sustituido_id`) REFERENCES `cfdi_documentos` (`id`),
  CONSTRAINT `chk_cfdi_payload_json` CHECK (`payload_json` IS NULL OR JSON_VALID(`payload_json`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill: NO elimina ni altera las columnas originales de ventas.
-- Evita fabricar registros de emisiones cuyo UUID o ID de Facturama no se conoce.
INSERT INTO `cfdi_documentos`
  (`id_venta`,`id_ferreteria`,`id_sucursal`,`version`,`tipo_cfdi`,`folio`,
   `fecha_cfdi`,`issuer_id`,`facturama_id`,`uuid`,`estado`,`sat_status`,
   `motivo_cancelacion`,`uuid_sustituto`,`fecha_cancelacion`,`correo`,`payload_json`)
SELECT v.id, v.id_ferreteria, v.id_sucursal, 1, 'I', v.folio,
  v.factura_date, v.factura_issuer_id, v.factura_id, v.uuid_factura,
  CASE WHEN v.estatus_factura='Cancelada' THEN 'cancelado'
       WHEN v.estatus_factura IN ('Cancelacion pendiente','Cancelación pendiente') THEN 'cancelacion_pendiente'
       WHEN v.estatus_factura='Facturada' THEN 'emitido'
       ELSE 'revision' END,
  NULL, v.factura_cancelacion_motivo, v.factura_cancelacion_uuid_sustituto,
  v.factura_cancelacion_fecha, v.factura_correo,
  CASE WHEN JSON_VALID(v.factura_payload) THEN v.factura_payload ELSE NULL END
FROM ventas v
WHERE v.factura_id IS NOT NULL AND v.factura_id<>''
  AND v.uuid_factura IS NOT NULL AND v.uuid_factura<>''
  AND v.factura_issuer_id > 0
  AND NOT EXISTS (SELECT 1 FROM cfdi_documentos d WHERE d.id_venta=v.id);
