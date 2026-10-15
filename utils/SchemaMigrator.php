<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/conexion.php';

class SchemaMigrator {
    private static bool $iniciado = false;

    public static function init(): void {
        if (self::$iniciado) {
            return;
        }
        self::$iniciado = true;

        try {
            $db = new Database();
            $sqlFile = __DIR__ . '/../database/modulos_ferreteria.sql';
            if (file_exists($sqlFile)) {
                $content = file_get_contents($sqlFile);
                $statements = array_filter(array_map('trim', explode(';', $content)));
                foreach ($statements as $stmt) {
                    if (!empty($stmt) && stripos($stmt, 'USE ') !== 0) {
                        try {
                            $db->query($stmt);
                        } catch (Throwable $e) {
                            // Ignorar si la tabla o índice ya existen
                        }
                    }
                }
            }
            self::migrarColumnasFiscalesProducto($db);
            self::migrarVentasCredito($db);
            $db->query("UPDATE formas_pago SET estatus = 0 WHERE LOWER(nombre) = 'crédito'");
        } catch (Throwable $e) {
            // Manejo silencioso en caso de no disponibilidad temporal de DB
        }
    }

    private static function migrarVentasCredito(Database $db): void {
        $columnas = $db->select('SHOW COLUMNS FROM ventas');
        $existentes = array_column($columnas, 'Field');
        if (!in_array('tipo_venta', $existentes, true)) {
            $db->query("ALTER TABLE ventas ADD COLUMN tipo_venta VARCHAR(20) NOT NULL DEFAULT 'contado' AFTER total");
        }
        $db->query(
            "CREATE TABLE IF NOT EXISTS abonos_venta (
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
            )"
        );
    }

    private static function migrarColumnasFiscalesProducto(Database $db): void {
        $columnas = $db->select('SHOW COLUMNS FROM productos');
        $existentes = array_column($columnas, 'Field');
        $mapeo = [
            'product_code' => ['clave_sat', "VARCHAR(20) NOT NULL DEFAULT '01010101'", '01010101'],
            'unit_code' => ['codigo_unidad_sat', "VARCHAR(10) NOT NULL DEFAULT 'H87'", 'H87'],
            'tax_object' => ['objeto_impuesto', "VARCHAR(2) NOT NULL DEFAULT '02'", '02']
        ];

        foreach ($mapeo as $anterior => [$nuevo, $definicion, $valorPorDefecto]) {
            $tieneAnterior = in_array($anterior, $existentes, true);
            $tieneNuevo = in_array($nuevo, $existentes, true);
            if (!$tieneAnterior) {
                continue;
            }
            if ($tieneNuevo) {
                $db->query("UPDATE productos SET {$nuevo} = {$anterior} WHERE {$anterior} <> ?", [$valorPorDefecto]);
                $db->query("ALTER TABLE productos DROP COLUMN {$anterior}");
                continue;
            }
            $db->query("ALTER TABLE productos CHANGE COLUMN {$anterior} {$nuevo} {$definicion}");
        }
    }
}
