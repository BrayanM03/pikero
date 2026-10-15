<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/dates.php';
require_once __DIR__ . '/../models/Datatable.php';
require_once __DIR__ . '/../utils/Tenant.php';

class Compra extends Datatable {
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
        $sql = "SELECT c.id, c.folio, c.numero_documento, c.fecha_compra, c.total,
                       p.nombre AS proveedor, fp.nombre AS forma_pago,
                       CONCAT(u.nombre, ' ', u.apellido) AS usuario
                FROM compras c
                LEFT JOIN proveedores p ON p.id = c.id_proveedor
                LEFT JOIN formas_pago fp ON fp.id = c.id_forma_pago
                LEFT JOIN usuarios u ON u.id = c.id_usuario
                WHERE c.id_ferreteria = :id_ferreteria AND c.id_sucursal = :id_sucursal
                  AND c.estatus = 1";
        $params = [
            ':id_ferreteria' => $this->id_ferreteria,
            ':id_sucursal' => $this->id_sucursal
        ];

        if (!empty($search)) {
            $sql .= " AND (c.folio LIKE :s1 OR c.numero_documento LIKE :s2 OR p.nombre LIKE :s3)";
            $term = "%{$search}%";
            $params[':s1'] = $term;
            $params[':s2'] = $term;
            $params[':s3'] = $term;
        }

        $allowed = ['c.id', 'c.folio', 'c.numero_documento', 'c.fecha_compra', 'c.total', 'proveedor'];
        $orderColumn = in_array($orderColumn, $allowed, true) ? $orderColumn : 'c.id';
        $orderDir = strtolower($orderDir) === 'asc' ? 'ASC' : 'DESC';
        $start = (int)$start;
        $length = (int)$length;
        $sql .= " ORDER BY {$orderColumn} {$orderDir} LIMIT {$start}, {$length}";

        return $this->db->select($sql, $params);
    }

    public function contar($id_filtro): int {
        return (int)$this->db->count(
            'compras',
            'id_ferreteria = ? AND id_sucursal = ? AND estatus = 1',
            [$this->id_ferreteria, $this->id_sucursal]
        );
    }

    public function contarFiltrados($id_filtro, $search): int {
        if (empty($search)) {
            return $this->contar($id_filtro);
        }

        $data = $this->db->select(
            "SELECT COUNT(*) AS total
             FROM compras c
             LEFT JOIN proveedores p ON p.id = c.id_proveedor
             WHERE c.id_ferreteria = :id_ferreteria AND c.id_sucursal = :id_sucursal
               AND c.estatus = 1
               AND (c.folio LIKE :s1 OR c.numero_documento LIKE :s2 OR p.nombre LIKE :s3)",
            [
                ':id_ferreteria' => $this->id_ferreteria,
                ':id_sucursal' => $this->id_sucursal,
                ':s1' => "%{$search}%",
                ':s2' => "%{$search}%",
                ':s3' => "%{$search}%"
            ]
        );

        return (int)($data[0]['total'] ?? 0);
    }

    public function registrar(array $data): array {
        $id_proveedor = (int)($data['id_proveedor'] ?? 0);
        $id_forma_pago = (int)($data['id_forma_pago'] ?? 0);
        $fecha_compra = trim((string)($data['fecha_compra'] ?? date('Y-m-d')));
        $numero_documento = trim((string)($data['numero_documento'] ?? ''));
        $observaciones = trim((string)($data['observaciones'] ?? ''));
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];

        if ($id_proveedor <= 0) {
            return ['estatus' => false, 'mensaje' => 'Selecciona un proveedor'];
        }
        if ($id_forma_pago <= 0) {
            return ['estatus' => false, 'mensaje' => 'Selecciona una forma de pago'];
        }
        if (empty($items)) {
            return ['estatus' => false, 'mensaje' => 'Agrega al menos un producto a la compra'];
        }

        $proveedor = $this->db->select(
            'SELECT id FROM proveedores WHERE id = ? AND id_ferreteria = ? AND estatus = 1',
            [$id_proveedor, $this->id_ferreteria]
        );
        if (empty($proveedor)) {
            return ['estatus' => false, 'mensaje' => 'El proveedor no existe o está inactivo'];
        }

        $itemsValidados = [];
        $total = 0.0;
        foreach ($items as $item) {
            $id_producto = (int)($item['id'] ?? $item['id_producto'] ?? 0);
            $cantidad = (float)($item['cantidad'] ?? 0);
            $precio = (float)($item['precio_compra'] ?? $item['precio'] ?? 0);
            if ($id_producto <= 0 || $cantidad <= 0 || $precio < 0) {
                return ['estatus' => false, 'mensaje' => 'Todos los productos deben tener cantidad y costo válidos'];
            }

            $producto = $this->db->select(
                'SELECT id, nombre FROM productos WHERE id = ? AND id_ferreteria = ? AND estatus = 1',
                [$id_producto, $this->id_ferreteria]
            );
            if (empty($producto)) {
                return ['estatus' => false, 'mensaje' => "El producto {$id_producto} no existe o está inactivo"];
            }

            $importe = round($cantidad * $precio, 2);
            $total += $importe;
            $itemsValidados[] = [
                'id_producto' => $id_producto,
                'nombre' => $producto[0]['nombre'],
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'importe' => $importe
            ];
        }

        $folio = 'COM-' . strtoupper(substr(uniqid(), -8));
        $id_usuario = $_SESSION['id'] ?? 1;

        try {
            $this->db->beginTransaction();

            $id_compra = $this->db->insert('compras', [
                'id_ferreteria' => $this->id_ferreteria,
                'id_sucursal' => $this->id_sucursal,
                'id_usuario' => $id_usuario,
                'id_proveedor' => $id_proveedor,
                'folio' => $folio,
                'numero_documento' => $numero_documento ?: null,
                'fecha_compra' => $fecha_compra,
                'total' => $total,
                'id_forma_pago' => $id_forma_pago,
                'observaciones' => $observaciones ?: null,
                'estatus' => 1,
                'fecha_registro' => $this->fecha->obtenerFechaRegistro()
            ]);

            if (!$id_compra) {
                throw new Exception('No se pudo guardar la compra');
            }

            foreach ($itemsValidados as $item) {
                $this->db->insert('detalle_compras', [
                    'id_compra' => $id_compra,
                    'id_producto' => $item['id_producto'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'importe' => $item['importe']
                ]);

                $inventario = $this->db->select(
                    'SELECT id, existencia FROM inventario WHERE id_producto = ? AND id_sucursal = ? AND id_ferreteria = ?',
                    [$item['id_producto'], $this->id_sucursal, $this->id_ferreteria]
                );
                $antes = !empty($inventario) ? (float)$inventario[0]['existencia'] : 0.0;
                $despues = $antes + $item['cantidad'];

                if (!empty($inventario)) {
                    $this->db->update('inventario', ['existencia' => $despues], 'id = ?', [$inventario[0]['id']]);
                } else {
                    $this->db->insert('inventario', [
                        'id_ferreteria' => $this->id_ferreteria,
                        'id_sucursal' => $this->id_sucursal,
                        'id_producto' => $item['id_producto'],
                        'existencia' => $despues,
                        'minimo' => 5
                    ]);
                }

                $this->db->insert('movimientos_inventario', [
                    'id_ferreteria' => $this->id_ferreteria,
                    'id_sucursal' => $this->id_sucursal,
                    'id_producto' => $item['id_producto'],
                    'tipo' => 'compra',
                    'cantidad' => $item['cantidad'],
                    'existencia_antes' => $antes,
                    'existencia_despues' => $despues,
                    'referencia' => "Compra {$folio}",
                    'id_usuario' => $id_usuario,
                    'fecha_registro' => $this->fecha->obtenerFechaRegistro()
                ]);

                $this->db->update(
                    'productos',
                    ['precio_compra' => $item['precio_unitario']],
                    'id = ? AND id_ferreteria = ?',
                    [$item['id_producto'], $this->id_ferreteria]
                );
            }

            $this->db->commit();
            return ['estatus' => true, 'mensaje' => 'Compra registrada correctamente', 'id_compra' => $id_compra, 'folio' => $folio];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['estatus' => false, 'mensaje' => 'Error al registrar la compra: ' . $e->getMessage()];
        }
    }

    public function detalle(int $id_compra): array {
        $compra = $this->db->select(
            "SELECT c.*, p.nombre AS proveedor, fp.nombre AS forma_pago,
                    CONCAT(u.nombre, ' ', u.apellido) AS usuario
             FROM compras c
             LEFT JOIN proveedores p ON p.id = c.id_proveedor
             LEFT JOIN formas_pago fp ON fp.id = c.id_forma_pago
             LEFT JOIN usuarios u ON u.id = c.id_usuario
             WHERE c.id = ? AND c.id_ferreteria = ? AND c.id_sucursal = ?",
            [$id_compra, $this->id_ferreteria, $this->id_sucursal]
        );
        if (empty($compra)) {
            return ['estatus' => false, 'mensaje' => 'Compra no encontrada', 'data' => []];
        }

        $items = $this->db->select(
            "SELECT dc.*, p.nombre AS producto, p.codigo, u.abreviatura AS unidad
             FROM detalle_compras dc
             INNER JOIN productos p ON p.id = dc.id_producto
             LEFT JOIN unidades u ON u.id = p.id_unidad
             WHERE dc.id_compra = ? ORDER BY dc.id ASC",
            [$id_compra]
        );

        return ['estatus' => true, 'mensaje' => 'OK', 'data' => ['compra' => $compra[0], 'items' => $items]];
    }
}