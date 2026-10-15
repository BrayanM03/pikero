<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/dates.php';
require_once __DIR__ . '/../models/Datatable.php';
require_once __DIR__ . '/../utils/Tenant.php';

class Inventario extends Datatable {
    private Date $fecha;
    private ?int $id_ferreteria;
    private ?int $id_sucursal;

    public function __construct(?int $id_ferreteria = null, ?int $id_sucursal = null) {
        $this->db = new Database();
        $this->fecha = new Date();
        $this->id_ferreteria = $id_ferreteria ?? Tenant::idFerreteria();
        $this->id_sucursal = $id_sucursal ?? (Tenant::idSucursal() ?? 1);
    }

    public function datatables($id_filtro, $start, $length, $search, $orderColumn, $orderDir) {
        $sql = "SELECT p.id AS id_producto,
                       p.codigo,
                       p.nombre AS producto,
                       c.nombre AS categoria,
                       m.nombre AS marca,
                       u.abreviatura AS unidad,
                       p.precio_compra,
                       p.precio_venta,
                       COALESCE(inv.existencia, 0) AS existencia,
                       COALESCE(inv.minimo, 5) AS minimo
                FROM productos p
                LEFT JOIN categorias c ON c.id = p.id_categoria
                LEFT JOIN marcas m ON m.id = p.id_marca
                LEFT JOIN unidades u ON u.id = p.id_unidad
                LEFT JOIN inventario inv ON inv.id_producto = p.id AND inv.id_sucursal = :id_sucursal
                WHERE p.id_ferreteria = :id_ferreteria AND p.estatus = 1";

        $params = [
            ':id_ferreteria' => $this->id_ferreteria,
            ':id_sucursal' => $this->id_sucursal
        ];

        if (!empty($search)) {
            $sql .= " AND (p.codigo LIKE :s1 OR p.nombre LIKE :s2 OR c.nombre LIKE :s3 OR m.nombre LIKE :s4)";
            $term = "%{$search}%";
            $params[':s1'] = $term;
            $params[':s2'] = $term;
            $params[':s3'] = $term;
            $params[':s4'] = $term;
        }

        $allowed = ['p.id', 'codigo', 'producto', 'existencia', 'minimo', 'precio_venta'];
        $orderColumn = in_array($orderColumn, $allowed) ? $orderColumn : 'p.nombre';
        $orderDir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';

        $start = (int)$start;
        $length = (int)$length;
        $sql .= " ORDER BY {$orderColumn} {$orderDir} LIMIT {$start}, {$length}";

        return $this->db->select($sql, $params);
    }

    public function contar($id_filtro): int {
        return (int)$this->db->count('productos', 'id_ferreteria = ? AND estatus = 1', [$this->id_ferreteria]);
    }

    public function contarFiltrados($id_filtro, $search): int {
        if (empty($search)) {
            return $this->contar($id_filtro);
        }

        $sql = "SELECT COUNT(*) AS total 
                FROM productos p
                LEFT JOIN categorias c ON c.id = p.id_categoria
                LEFT JOIN marcas m ON m.id = p.id_marca
                WHERE p.id_ferreteria = :id_ferreteria AND p.estatus = 1
                  AND (p.codigo LIKE :s1 OR p.nombre LIKE :s2 OR c.nombre LIKE :s3 OR m.nombre LIKE :s4)";

        $term = "%{$search}%";
        $data = $this->db->select($sql, [
            ':id_ferreteria' => $this->id_ferreteria,
            ':s1' => $term,
            ':s2' => $term,
            ':s3' => $term,
            ':s4' => $term
        ]);

        return (int)($data[0]['total'] ?? 0);
    }

    public function ajustarStock(int $id_producto, string $tipo, float $cantidad, string $motivo, ?int $id_usuario = null): array {
        if ($cantidad <= 0) {
            return ['estatus' => false, 'mensaje' => 'La cantidad debe ser mayor a cero'];
        }

        // Obtener o inicializar registro en inventario
        $inv = $this->db->select(
            "SELECT * FROM inventario WHERE id_producto = ? AND id_sucursal = ? AND id_ferreteria = ?",
            [$id_producto, $this->id_sucursal, $this->id_ferreteria]
        );

        $existencia_antes = !empty($inv) ? (float)$inv[0]['existencia'] : 0.0;
        $minimo = !empty($inv) ? (float)$inv[0]['minimo'] : 5.0;

        if ($tipo === 'entrada') {
            $existencia_despues = $existencia_antes + $cantidad;
        } elseif ($tipo === 'salida') {
            if ($cantidad > $existencia_antes) {
                return ['estatus' => false, 'mensaje' => "Stock insuficiente (disponible: {$existencia_antes})"];
            }
            $existencia_despues = $existencia_antes - $cantidad;
        } elseif ($tipo === 'ajuste_fijo') {
            $existencia_despues = $cantidad;
        } else {
            return ['estatus' => false, 'mensaje' => 'Tipo de ajuste no reconocido'];
        }

        if (empty($inv)) {
            $this->db->insert('inventario', [
                'id_ferreteria' => $this->id_ferreteria,
                'id_sucursal' => $this->id_sucursal,
                'id_producto' => $id_producto,
                'existencia' => $existencia_despues,
                'minimo' => $minimo
            ]);
        } else {
            $this->db->update('inventario', [
                'existencia' => $existencia_despues
            ], 'id = ?', [$inv[0]['id']]);
        }

        // Registrar en bitácora de movimientos
        $this->db->insert('movimientos_inventario', [
            'id_ferreteria' => $this->id_ferreteria,
            'id_sucursal' => $this->id_sucursal,
            'id_producto' => $id_producto,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'existencia_antes' => $existencia_antes,
            'existencia_despues' => $existencia_despues,
            'referencia' => $motivo ?: 'Ajuste manual de inventario',
            'id_usuario' => $id_usuario ?? ($_SESSION['id'] ?? null),
            'fecha_registro' => $this->fecha->obtenerFechaRegistro()
        ]);

        return [
            'estatus' => true,
            'mensaje' => 'Inventario actualizado correctamente',
            'existencia_nueva' => $existencia_despues
        ];
    }

    public function movimientos(int $id_producto): array {
        $sql = "SELECT m.*, u.nombre AS usuario_nombre, u.apellido AS usuario_apellido
                FROM movimientos_inventario m
                LEFT JOIN usuarios u ON u.id = m.id_usuario
                WHERE m.id_producto = ? AND m.id_sucursal = ? AND m.id_ferreteria = ?
                ORDER BY m.id DESC LIMIT 50";

        $data = $this->db->select($sql, [$id_producto, $this->id_sucursal, $this->id_ferreteria]);
        return ['estatus' => true, 'mensaje' => 'OK', 'data' => $data];
    }

    public function historial(): array {
        $sql = "SELECT
                    m.id,
                    DATE_FORMAT(m.fecha_registro, '%d/%m/%Y %H:%i') AS fecha_registro,
                    m.tipo,
                    m.cantidad,
                    m.existencia_antes,
                    m.existencia_despues,
                    m.referencia,
                    p.nombre AS producto,
                    CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, '')) AS usuario,
                    s.nombre AS sucursal
                FROM movimientos_inventario m
                LEFT JOIN productos p ON p.id = m.id_producto
                LEFT JOIN usuarios u ON u.id = m.id_usuario
                LEFT JOIN sucursales s ON s.id = m.id_sucursal
                WHERE m.id_ferreteria = ? AND m.id_sucursal = ?
                ORDER BY m.id DESC
                LIMIT 200";

        $data = $this->db->select($sql, [$this->id_ferreteria, $this->id_sucursal]);
        return ['estatus' => true, 'mensaje' => 'OK', 'data' => $data, 'recordsTotal' => count($data), 'recordsFiltered' => count($data)];
    }
}
