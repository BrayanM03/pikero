<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/dates.php';
require_once __DIR__ . '/../utils/Tenant.php';

class PuntoVenta
{
    private Database $db;
    private Date $fecha;
    private ?int $id_ferreteria;
    private ?int $id_sucursal;
    private ?int $tipo_reporte;

    public function __construct(?int $id_ferreteria = null, ?int $id_sucursal = null)
    {
        $this->db = new Database();
        $this->fecha = new Date();
        $this->id_ferreteria = $id_ferreteria ?? Tenant::idFerreteria();
        $this->id_sucursal = $id_sucursal ?? (Tenant::idSucursal() ?? 1);
        $this->tipo_reporte = Tenant::tipoReporte();
    }

    public function procesarVenta(array $datosVenta): array
    {
        $items = $datosVenta['items'] ?? [];
        if (empty($items)) {
            return ['estatus' => false, 'mensaje' => 'El carrito de venta está vacío'];
        }

        $id_cliente = !empty($datosVenta['id_cliente']) ? (int)$datosVenta['id_cliente'] : null;
        $id_forma_pago = !empty($datosVenta['id_forma_pago']) ? (int)$datosVenta['id_forma_pago'] : null; // compatibilidad
        $pagosEntrada  = is_array($datosVenta['pagos'] ?? null) ? $datosVenta['pagos'] : [];

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

            $prod = $this->db->select(
                    "SELECT
                        p.id,
                        p.nombre,
                        p.precio_compra,
                        p.precio_venta,
                        p.costo_incluye_iva,
                        p.tasa_iva_costo,
                        p.costo_iva_acreditable,
                        p.tipo_iva_venta,
                        p.precio_venta_incluye_iva,
                        p.objeto_impuesto,
                        p.clave_sat,
                        p.codigo_unidad_sat,
                        u.abreviatura AS unidad,
                        COALESCE(inv.existencia, 0) AS stock
                    FROM productos p
                    LEFT JOIN unidades u ON u.id = p.id_unidad
                    LEFT JOIN inventario inv
                        ON inv.id_producto = p.id
                        AND inv.id_sucursal = ?
                        AND inv.id_ferreteria = p.id_ferreteria
                    WHERE p.id = ?
                    AND p.id_ferreteria = ?
                    AND p.estatus = 1",
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

            /* $precio = (float)$prod[0]['precio_venta'];
            $precio_sin_iva = $precio / (1 + $tasa_iva_default);

            $importe = $precio * $cantidad;
            
            $costo_unitario = (float)$prod[0]['precio_compra'];
            $costo_total = $costo_unitario * $cantidad;
            
            $utilidad_item = ($precio_sin_iva - $costo_unitario) * $cantidad;
            print_r(
                [
                    'utilidad_item:' => $utilidad_item,
                    'precio_sin_iva' => $precio_sin_iva,
                    'costo_unitario' => $costo_unitario,
                    'cantidad' => $cantidad
                    ]
                    );
                    
                    $costo_total = (float)$prod[0]['precio_compra'] * $cantidad; */
                    
            $producto = $prod[0];

            $precio = (float)$producto['precio_venta'];
            $costoUnitario = (float)$producto['precio_compra'];

            $tipoIva = (string)$producto['tipo_iva_venta'];
            $precioIncluyeIva = (int)$producto['precio_venta_incluye_iva'] === 1;

            $tasasPermitidas = [
                '0.16' => 0.16,
                '0.08' => 0.08,
                '0' => 0.0,
                'exento' => 0.0,
                'no_objeto' => 0.0
            ];

            if (!array_key_exists($tipoIva, $tasasPermitidas)) {
                return [
                    'estatus' => false,
                    'mensaje' => "Configuración de IVA inválida para {$producto['nombre']}"
                ];
            }

            $tasaIvaVenta = $tasasPermitidas[$tipoIva];

            // Venta unitaria antes de impuestos e IVA trasladado
            if ($precioIncluyeIva && $tasaIvaVenta > 0) {
                $precioBase = $precio / (1 + $tasaIvaVenta);
                $ivaUnitario = $precio - $precioBase;
            } else {
                $precioBase = $precio;
                $ivaUnitario = $tasaIvaVenta > 0
                    ? $precioBase * $tasaIvaVenta
                    : 0.0;
            }

            $importeBase = round($precioBase * $cantidad, 2);
            $ivaTrasladado = round($ivaUnitario * $cantidad, 2);

            // Total que pagará el cliente
            $importe = $precioIncluyeIva
                ? round($precio * $cantidad, 2)
                : round($importeBase + $ivaTrasladado, 2);
            $subtotal += $importe;

            // Costo neto estimado para calcular utilidad
            $costoIncluyeIva = (int)$producto['costo_incluye_iva'] === 1;
            $ivaCompraAcreditable = (int)$producto['costo_iva_acreditable'] === 1;
            $tasaIvaCosto = (float)$producto['tasa_iva_costo'];

            $costoNetoUnitario = $costoUnitario;

            if (
                $costoIncluyeIva &&
                $ivaCompraAcreditable &&
                $tasaIvaCosto > 0
            ) {
                $costoNetoUnitario = $costoUnitario / (1 + $tasaIvaCosto);
            }

            $costoTotal = round($costoNetoUnitario * $cantidad, 2);
            $utilidadItem = round($importeBase - $costoTotal, 2);

            $itemsValidados[] = [
                    'id_producto' => $id_producto,
                    'nombre' => $producto['nombre'],
                    'cantidad' => $cantidad,
                    'precio' => $precio,
                    'importe' => $importe,
                    'importe_base' => $importeBase,
                    'iva' => $ivaTrasladado,
                    'stock_actual' => $stockActual,
                    'unidad' => $unidadProducto,
                    'costo' => $costoTotal,
                    'utilidad' => $utilidadItem
                ];
        }

        if (empty($itemsValidados)) {
            return ['estatus' => false, 'mensaje' => 'No hay artículos válidos para cobrar'];
        }

        $subtotal = round($subtotal, 2);
        $total = $subtotal;

        // Compatibilidad: si el front todavía manda una sola forma de pago
        if (empty($pagosEntrada) && $id_forma_pago) {
            $pagosEntrada = [[
                'id_forma_pago' => $id_forma_pago,
                'monto' => $tipo_venta === 'contado' ? $total : $abono_inicial,
            ]];
        }

        $resPagos = $this->normalizarPagos($pagosEntrada);
        if (!$resPagos['estatus']) {
            return $resPagos;
        }
        $pagos = $resPagos['pagos'];
        $totalPagos = $resPagos['total'];

        // Con un solo pago en contado, el total del servidor manda
        if ($tipo_venta === 'contado' && count($pagos) === 1) {
            $pagos[0]['monto'] = $total;
            $totalPagos = $total;
        }

        if ($tipo_venta === 'contado') {
            if (empty($pagos)) {
                return ['estatus' => false, 'mensaje' => 'Selecciona al menos una forma de pago'];
            }
            if ($this->aCentavos($totalPagos) !== $this->aCentavos($total)) {
                return ['estatus' => false, 'mensaje' => "La suma de los pagos ({$totalPagos}) no coincide con el total ({$total})"];
            }
            $abono_inicial = $total;
        } else { // crédito
            if ($abono_inicial > $total) {
                return ['estatus' => false, 'mensaje' => 'El abono inicial no puede superar el total de la venta'];
            }
            if ($this->aCentavos($totalPagos) !== $this->aCentavos($abono_inicial)) {
                return ['estatus' => false, 'mensaje' => 'Los pagos del abono inicial no coinciden con el monto del abono'];
            }
        }

        $folio = 'TK-' . strtoupper(substr(uniqid(), -6)) . '-' . date('dmy');

       /*  $subtotal = round($subtotal, 2);
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
        $folio = 'TK-' . strtoupper(substr(uniqid(), -6)) . '-' . date('dmy'); */

        try {
            $this->db->beginTransaction();

            $cajaCobro = [];
            // 1. Obtener el costo total sumando el campo 'costo' de tus items validados
            $costo_total_venta = array_sum(array_column($itemsValidados, 'costo'));
            if ($tipo_venta === 'credito' && $abono_inicial > 0) {
                // 2. Aplicar la fórmula proporcional
                $proporcion = ($total > 0) ? ($abono_inicial / $total) : 0;
                $utilidad_abono = $abono_inicial - ($costo_total_venta * $proporcion);
                $cajaCobro = $this->db->select(
                    "SELECT id, total_ventas FROM cajas WHERE id_ferreteria = ? AND id_sucursal = ? AND estado = 'abierta' ORDER BY id DESC LIMIT 1 FOR UPDATE",
                    [$this->id_ferreteria, $this->id_sucursal]
                );
                if (empty($cajaCobro)) {
                    $this->db->rollBack();
                    return ['estatus' => false, 'mensaje' => 'Abre la caja antes de recibir el abono inicial'];
                }
            } else {
                $utilidad_abono = 0.0;
            }

            $utilidad_total = array_sum(array_column($itemsValidados, 'utilidad'));
            $id_venta = $this->db->insert('ventas', [
                'id_ferreteria' => $this->id_ferreteria,
                'id_sucursal' => $this->id_sucursal,
                'id_usuario' => $id_usuario,
                'id_cliente' => $id_cliente,
                'folio' => $folio,
                'subtotal' => $subtotal,
                'total' => $total,
                'costo' => $costo_total_venta,
                'utilidad' => $utilidad_total,
                'tipo_venta' => $tipo_venta,
                'estatus' => 1,
                'fecha_registro' => $fecha_venta
            ]);

            if (!$id_venta) {
                throw new Exception('No se pudo insertar la cabecera de la venta');
            }

            $fechaPago = $this->fecha->obtenerFechaRegistro();

            if ($tipo_venta === 'credito' && $abono_inicial > 0) {
                $id_abono = $this->db->insert('abonos_venta', [
                    'id_venta' => $id_venta,
                    'id_ferreteria' => $this->id_ferreteria,
                    'id_sucursal' => $this->id_sucursal,
                    'id_usuario' => $id_usuario,
                    'monto' => $abono_inicial,
                    'utilidad' => $utilidad_abono,
                    'observaciones' => 'Abono inicial',
                    'fecha_registro' => $fechaPago
                ]);
                if (!$id_abono) {
                    throw new Exception('No se pudo registrar el abono inicial');
                }
                $this->insertarPagos($pagos, null, (int)$id_abono, $fechaPago);
            } elseif ($tipo_venta === 'contado') {
                $this->insertarPagos($pagos, (int)$id_venta, null, $fechaPago);
            }

            foreach ($itemsValidados as $it) {
                // Insertar detalle
                $this->db->insert('detalle_ventas', [
                    'id_venta' => $id_venta,
                    'id_producto' => $it['id_producto'],
                    'cantidad' => $it['cantidad'],
                    'precio' => $it['precio'],
                    'importe' => $it['importe'],
                    'costo' => $it['costo'],
                    'utilidad' => $it['utilidad'],
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
                'pagos' => $pagos,
                'tipo_reporte' => $this->tipo_reporte
            ];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['estatus' => false, 'mensaje' => 'Error al procesar la venta: ' . $e->getMessage()];
        }
    }

    private function aCentavos(float $monto): int
{
    return (int)round($monto * 100);
}

private function normalizarPagos(array $pagosEntrada): array
{
    $pagos = [];
    $total = 0.0;

    foreach ($pagosEntrada as $p) {
        $idForma = (int)($p['id_forma_pago'] ?? 0);
        $monto = round((float)($p['monto'] ?? 0), 2);

        if ($idForma <= 0 && $monto <= 0) {
            continue; // fila vacía
        }
        if ($idForma <= 0) {
            return ['estatus' => false, 'mensaje' => 'Selecciona la forma de pago en todas las filas'];
        }
        if ($monto <= 0) {
            return ['estatus' => false, 'mensaje' => 'Cada pago debe tener un monto mayor a cero'];
        }

        $referencia = trim((string)($p['referencia'] ?? ''));
        $pagos[] = [
            'id_forma_pago' => $idForma,
            'monto' => $monto,
            'referencia' => $referencia !== '' ? mb_substr($referencia, 0, 100) : null,
        ];
        $total += $monto;
    }

    if (empty($pagos)) {
        return ['estatus' => true, 'pagos' => [], 'total' => 0.0];
    }

    $ids = array_values(array_unique(array_column($pagos, 'id_forma_pago')));
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $validas = $this->db->select(
        "SELECT id FROM formas_pago
         WHERE id_ferreteria = ? AND estatus = 1 AND LOWER(nombre) <> 'crédito' AND id IN ($ph)",
        array_merge([$this->id_ferreteria], $ids)
    );
    if (count($validas) !== count($ids)) {
        return ['estatus' => false, 'mensaje' => 'Alguna forma de pago seleccionada no está disponible'];
    }

    return ['estatus' => true, 'pagos' => $pagos, 'total' => round($total, 2)];
}

private function insertarPagos(array $pagos, ?int $idVenta, ?int $idAbono, string $fecha): void
{
    foreach ($pagos as $p) {
        $this->db->insert('detalle_pagos', [
            'id_ferreteria' => $this->id_ferreteria,
            'id_sucursal' => $this->id_sucursal,
            'id_venta' => $idVenta,
            'id_abono_venta' => $idAbono,
            'id_forma_pago' => $p['id_forma_pago'],
            'monto' => $p['monto'],
            'referencia' => $p['referencia'],
            'fecha_registro' => $fecha,
        ]);
    }
}
}
