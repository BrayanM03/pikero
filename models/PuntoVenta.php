<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/dates.php';
require_once __DIR__ . '/../utils/Tenant.php';

class PuntoVenta {
    private Database $db;
    private Date $fecha;
    private ?int $id_ferreteria;
    private ?int $id_sucursal;
    private ?int $tipo_reporte;

    public function __construct(?int $id_ferreteria = null, ?int $id_sucursal = null) {
        $this->db = new Database();
        $this->fecha = new Date();
        $this->id_ferreteria = $id_ferreteria ?? Tenant::idFerreteria();
        $this->id_sucursal = $id_sucursal ?? (Tenant::idSucursal() ?? 1);
        $this->tipo_reporte = Tenant::tipoReporte();
    }

    public function procesarVenta(array $datosVenta): array {
        $items = $datosVenta['items'] ?? [];
        if (empty($items)) {
            return ['estatus' => false, 'mensaje' => 'El carrito de venta está vacío'];
        }

        $id_cliente = !empty($datosVenta['id_cliente']) ? (int)$datosVenta['id_cliente'] : null;
        $id_forma_pago = !empty($datosVenta['id_forma_pago']) ? (int)$datosVenta['id_forma_pago'] : null;
        $tipo_venta = (string)($datosVenta['tipo_venta'] ?? 'contado');
        $fecha_venta = !empty($datosVenta['fecha_venta']) ? (string)$datosVenta['fecha_venta'] : $this->fecha->obtenerFechaRegistro();
        if (!in_array($tipo_venta, ['contado', 'credito'], true)) {
            return ['estatus' => false, 'mensaje' => 'El tipo de venta seleccionado no es válido'];
        }
        $abono_inicial = round((float)($datosVenta['abono_inicial'] ?? 0), 2);
        $id_usuario = $_SESSION['id'] ?? 1;

        // Si no viene cliente, seleccionar el público en general
        if (!$id_cliente) {
            $cli = $this->db->select(
                "SELECT id FROM clientes WHERE id_ferreteria = ? AND es_publico = 1 LIMIT 1",
                [$this->id_ferreteria]
            );
            $id_cliente = !empty($cli) ? (int)$cli[0]['id'] : null;
        }

        if ($tipo_venta === 'credito') {
            $clienteCredito = $this->db->select(
                'SELECT id FROM clientes WHERE id = ? AND id_ferreteria = ? AND estatus = 1 AND es_publico = 0',
                [$id_cliente, $this->id_ferreteria]
            );
            if (empty($clienteCredito)) {
                return ['estatus' => false, 'mensaje' => 'Las ventas a crédito requieren un cliente registrado'];
            }
            if ($abono_inicial < 0) {
                return ['estatus' => false, 'mensaje' => 'El abono inicial no puede ser negativo'];
            }
        }

        $subtotal = 0.0;
        $itemsValidados = [];

        foreach ($items as $item) {
            $id_producto = (int)($item['id'] ?? 0);
            $cantidad = (float)($item['cantidad'] ?? 0);
            if ($id_producto <= 0 || $cantidad <= 0) {
                continue;
            }

            // Consultar producto actual y stock
            $prod = $this->db->select(
                "SELECT p.id, p.nombre, p.precio_compra, p.precio_venta, u.abreviatura AS unidad, COALESCE(inv.existencia, 0) AS stock
                 FROM productos p
                 LEFT JOIN unidades u ON u.id = p.id_unidad
                 LEFT JOIN inventario inv ON inv.id_producto = p.id AND inv.id_sucursal = ?
                 WHERE p.id = ? AND p.id_ferreteria = ? AND p.estatus = 1",
                [$this->id_sucursal, $id_producto, $this->id_ferreteria]
            );

            if (empty($prod)) {
                return ['estatus' => false, 'mensaje' => "El producto ID {$id_producto} no existe o no está activo"];
            }

            $stockActual = (float)($prod[0]['stock'] ?? 0);
            $unidadProducto = $prod[0]['unidad'] ?? 'pza';
            if ($cantidad > $stockActual) {
                return [
                    'estatus' => false,
                    'mensaje' => "No hay suficiente stock de {$prod[0]['nombre']}. Disponible: {$stockActual} {$unidadProducto}"
                ];
            }

            $precio = (float)$prod[0]['precio_venta'];
            $importe = $precio * $cantidad;
            $subtotal += $importe;

            $costo_total = (float)$prod[0]['precio_compra'] * $cantidad;
            $itemsValidados[] = [
                'id_producto' => $id_producto,
                'nombre' => $prod[0]['nombre'],
                'cantidad' => $cantidad,
                'precio' => $precio,
                'importe' => $importe,
                'stock_actual' => $stockActual,
                'unidad' => $unidadProducto,
                'costo' => $costo_total
            ];
        }

        if (empty($itemsValidados)) {
            return ['estatus' => false, 'mensaje' => 'No hay artículos válidos para cobrar'];
        }

        $subtotal = round($subtotal, 2);
        $total = $subtotal;
        if ($tipo_venta === 'contado' && !$id_forma_pago) {
            return ['estatus' => false, 'mensaje' => 'Selecciona una forma de pago'];
        }
        if ($abono_inicial > $total) {
            return ['estatus' => false, 'mensaje' => 'El abono inicial no puede superar el total de la venta'];
        }
        if ($tipo_venta === 'contado') {
            $abono_inicial = $total;
        }
        if ($tipo_venta === 'credito' && $abono_inicial > 0 && !$id_forma_pago) {
            return ['estatus' => false, 'mensaje' => 'Selecciona la forma de pago del abono inicial'];
        }
        if ($tipo_venta === 'credito' && $abono_inicial === 0.0) {
            $id_forma_pago = null;
        }
        if ($id_forma_pago) {
            $formaPagoValida = $this->db->select(
                "SELECT id FROM formas_pago WHERE id = ? AND id_ferreteria = ? AND estatus = 1 AND LOWER(nombre) <> 'crédito'",
                [$id_forma_pago, $this->id_ferreteria]
            );
            if (empty($formaPagoValida)) {
                return ['estatus' => false, 'mensaje' => 'La forma de pago seleccionada no está disponible'];
            }
        }
        $folio = 'TK-' . strtoupper(substr(uniqid(), -6)) . '-' . date('dmy');

        try {
            $this->db->beginTransaction();

            $cajaCobro = [];
            if ($tipo_venta === 'credito' && $abono_inicial > 0) {
                $cajaCobro = $this->db->select(
                    "SELECT id, total_ventas FROM cajas WHERE id_ferreteria = ? AND id_sucursal = ? AND estado = 'abierta' ORDER BY id DESC LIMIT 1 FOR UPDATE",
                    [$this->id_ferreteria, $this->id_sucursal]
                );
                if (empty($cajaCobro)) {
                    $this->db->rollBack();
                    return ['estatus' => false, 'mensaje' => 'Abre la caja antes de recibir el abono inicial'];
                }
            }

            $id_venta = $this->db->insert('ventas', [
                'id_ferreteria' => $this->id_ferreteria,
                'id_sucursal' => $this->id_sucursal,
                'id_usuario' => $id_usuario,
                'id_cliente' => $id_cliente,
                'folio' => $folio,
                'subtotal' => $subtotal,
                'total' => $total,
                'tipo_venta' => $tipo_venta,
                'id_forma_pago' => $id_forma_pago,
                'estatus' => 1,
                'fecha_registro' => $fecha_venta
            ]);

            if (!$id_venta) {
                throw new Exception('No se pudo insertar la cabecera de la venta');
            }

            if ($tipo_venta === 'credito' && $abono_inicial > 0) {
                $this->db->insert('abonos_venta', [
                    'id_venta' => $id_venta,
                    'id_ferreteria' => $this->id_ferreteria,
                    'id_sucursal' => $this->id_sucursal,
                    'id_usuario' => $id_usuario,
                    'id_forma_pago' => $id_forma_pago,
                    'monto' => $abono_inicial,
                    'observaciones' => 'Abono inicial',
                    'fecha_registro' => $this->fecha->obtenerFechaRegistro()
                ]);
            }

            foreach ($itemsValidados as $it) {
                // Insertar detalle
                $this->db->insert('detalle_ventas', [
                    'id_venta' => $id_venta,
                    'id_producto' => $it['id_producto'],
                    'cantidad' => $it['cantidad'],
                    'precio' => $it['precio'],
                    'importe' => $it['importe'],
                    'costo' => $it['costo']
                ]);

                // Descontar inventario
                $stock_nuevo = $it['stock_actual'] - $it['cantidad'];

                $inv = $this->db->select(
                    "SELECT i.id FROM inventario i WHERE i.id_producto = ? AND i.id_sucursal = ? AND i.id_ferreteria = ?",
                    [$it['id_producto'], $this->id_sucursal, $this->id_ferreteria]
                );

                if (!empty($inv)) {
                    $this->db->update('inventario', [
                        'existencia' => $stock_nuevo
                    ], 'id = ?', [$inv[0]['id']]);
                } else {
                    $this->db->insert('inventario', [
                        'id_ferreteria' => $this->id_ferreteria,
                        'id_sucursal' => $this->id_sucursal,
                        'id_producto' => $it['id_producto'],
                        'existencia' => $stock_nuevo,
                        'minimo' => 5
                    ]);
                }

                // Bitácora de inventario
                $this->db->insert('movimientos_inventario', [
                    'id_ferreteria' => $this->id_ferreteria,
                    'id_sucursal' => $this->id_sucursal,
                    'id_producto' => $it['id_producto'],
                    'tipo' => 'venta',
                    'cantidad' => $it['cantidad'],
                    'existencia_antes' => $it['stock_actual'],
                    'existencia_despues' => $stock_nuevo,
                    'referencia' => "Ticket {$folio}",
                    'id_usuario' => $id_usuario,
                    'fecha_registro' => $this->fecha->obtenerFechaRegistro()
                ]);
            }

            // La caja recibe solo dinero cobrado ahora, no el saldo financiado.
            $cajaAbierta = !empty($cajaCobro) ? $cajaCobro : $this->db->select(
                "SELECT id, total_ventas FROM cajas WHERE id_ferreteria = ? AND id_sucursal = ? AND estado = 'abierta' ORDER BY id DESC LIMIT 1",
                [$this->id_ferreteria, $this->id_sucursal]
            );
            $ingresoCaja = $tipo_venta === 'credito' ? $abono_inicial : $total;
            if (!empty($cajaAbierta) && $ingresoCaja > 0) {
                $cajaId = (int)$cajaAbierta[0]['id'];
                $nuevoTotalVentas = (float)$cajaAbierta[0]['total_ventas'] + $ingresoCaja;
                $this->db->update('cajas', ['total_ventas' => $nuevoTotalVentas], 'id = ?', [$cajaId]);
            }

            $this->db->commit();

            return [
                'estatus' => true,
                'mensaje' => 'Venta registrada con éxito',
                'id_venta' => $id_venta,
                'folio' => $folio,
                'total' => $total,
                'tipo_venta' => $tipo_venta,
                'abono_inicial' => $abono_inicial,
                'saldo' => $total - $abono_inicial,
                'items' => $itemsValidados,
                'tipo_reporte' => $this->tipo_reporte
            ];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['estatus' => false, 'mensaje' => 'Error al procesar la venta: ' . $e->getMessage()];
        }
    }
}
