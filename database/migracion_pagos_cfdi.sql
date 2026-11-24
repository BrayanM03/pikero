-- Complemento de pago (REP, CFDI tipo P) para facturas PPD.
-- Ejecutar primero sobre respaldo. No modifica tablas existentes.

CREATE TABLE IF NOT EXISTS `cfdi_pagos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_venta` INT(11) NOT NULL,
  `id_ferreteria` INT(11) NOT NULL,
  `id_sucursal` INT(11) NOT NULL,
  `id_cfdi_documento` BIGINT UNSIGNED NOT NULL,      -- factura PPD (cfdi_documentos) que se está pagando
  `id_abono_venta` INT(11) DEFAULT NULL,             -- abono que origina este complemento
  `issuer_id` INT(11) NOT NULL,
  `uuid_factura` CHAR(36) NOT NULL,
  `parcialidad` SMALLINT UNSIGNED NOT NULL,
  `saldo_anterior` DECIMAL(12,2) NOT NULL,
  `monto` DECIMAL(12,2) NOT NULL,
  `saldo_insoluto` DECIMAL(12,2) NOT NULL,
  `forma_pago_sat` CHAR(2) NOT NULL,
  `fecha_pago` VARCHAR(19) NOT NULL,
  `num_operacion` VARCHAR(100) DEFAULT NULL,
  `folio` VARCHAR(80) NOT NULL,                      -- idempotencia: Folio + Date
  `fecha_cfdi` VARCHAR(19) NOT NULL,
  `facturama_id` VARCHAR(100) DEFAULT NULL,
  `uuid` CHAR(36) DEFAULT NULL,
  -- emitiendo | incierto | emitido | rechazado | cancelado
  `estado` VARCHAR(40) NOT NULL,
  `correo` VARCHAR(150) DEFAULT NULL,
  `payload_json` LONGTEXT DEFAULT NULL,
  `fecha_intento` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pago_uuid` (`uuid`),
  UNIQUE KEY `uq_pago_folio_emisor` (`id_ferreteria`,`issuer_id`,`folio`),
  KEY `idx_pago_venta` (`id_ferreteria`,`id_sucursal`,`id_venta`),
  KEY `idx_pago_doc_estado` (`id_cfdi_documento`,`estado`),
  KEY `idx_pago_abono` (`id_abono_venta`),
  CONSTRAINT `fk_pago_venta` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id`),
  CONSTRAINT `fk_pago_documento` FOREIGN KEY (`id_cfdi_documento`) REFERENCES `cfdi_documentos` (`id`),
  CONSTRAINT `chk_pago_payload_json` CHECK (`payload_json` IS NULL OR JSON_VALID(`payload_json`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `cfdi_pagos`
  ADD COLUMN `motivo_cancelacion` CHAR(2) NULL AFTER `estado`,
  ADD COLUMN `sat_status` VARCHAR(40) NULL AFTER `motivo_cancelacion`,
  ADD COLUMN `fecha_cancelacion` DATETIME NULL AFTER `sat_status`;