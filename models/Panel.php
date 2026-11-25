<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../utils/Tenant.php';

class Panel {
    private Database $db;
    private ?int $id_ferreteria;
    private ?int $id_sucursal;
    private ?int $id_usuario;

    public function __construct(?int $id_ferreteria = null, ?int $id_sucursal = null) {
        $this->db = new Database();
        $this->id_ferreteria = $id_ferreteria ?? Tenant::idFerreteria();
        $this->id_sucursal = $id_sucursal ?? Tenant::idSucursal();
        $this->id_usuario = $_SESSION['id'];
    }

    public function resumen(): array { 
        $inicio = date('Y-m-d 00:00:00');
        $fin = date('Y-m-d 00:00:00', strtotime('+1 day'));
        $params = [$this->id_ferreteria, $this->id_sucursal, $inicio, $fin];
        $ventas = $this->db->select(
            'SELECT COUNT(*) AS cantidad, 
                COALESCE(SUM(costo), 0) AS costo,
                COALESCE(SUM(utilidad), 0) AS utilidad,
                COALESCE(SUM(total), 0) AS total
             FROM ventas WHERE id_ferreteria = ? AND id_sucursal = ? AND estatus <> 0
             AND fecha_registro >= ? AND fecha_registro < ?',
            $params
        );
        
        $gastos = $this->db->select("SELECT SUM(monto) as total FROM gastos WHERE id_ferreteria = ? AND id_sucursal = ? AND estatus = 1
        AND fecha >= ? AND fecha < ?", $params);
      
        $productos = $this->db->select(
            'SELECT COUNT(*) AS total FROM productos WHERE id_ferreteria = ? AND estatus = 1',
            [$this->id_ferreteria]
        );

        $clientes = $this->db->select(
            'SELECT COUNT(*) AS total FROM clientes WHERE id_ferreteria = ? AND estatus = 1 AND es_publico = 0',
            [$this->id_ferreteria]
        );

        $cajas = $this->db->select(
            "SELECT id, estado, monto_apertura, total_ventas, total_entradas, total_salidas, fecha_apertura
             FROM cajas WHERE id_ferreteria = ? AND id_sucursal = ? AND estado = 'abierta'
             ORDER BY id DESC LIMIT 1",
            [$this->id_ferreteria, $this->id_sucursal]
        );


        $params_pagos = array_merge($params, [$this->id_usuario]);
        $pagosHoy = $this->db->select(
                "SELECT fp.id, fp.nombre, SUM(dp.monto) AS total
                FROM detalle_pagos dp
                JOIN formas_pago fp ON fp.id = dp.id_forma_pago
                INNER JOIN cajas c ON c.id = dp.id_caja
                LEFT JOIN abonos_venta a ON a.id = dp.id_abono_venta
                JOIN ventas v ON v.id = COALESCE(dp.id_venta, a.id_venta)
                WHERE dp.id_ferreteria = ? AND dp.id_sucursal = ? AND v.estatus <> 0
                AND dp.fecha_registro >= ? AND dp.fecha_registro < ?
                AND c.estado ='abierta' AND c.id_usuario = ?
                GROUP BY fp.id, fp.nombre
                ORDER BY total DESC",
                $params_pagos
            );

            $cobradoHoy = array_sum(array_column($pagosHoy, 'total'));
          

        return [
            'estatus' => true,
            'data' => [
                'ventas_hoy' => (float)($ventas[0]['total'] ?? 0),
                'gastos_hoy' => (float)($gastos[0]['total'] ?? 0),
                'utilidad' => (float)($ventas[0]['utilidad'] ?? 0),
                'cantidad_ventas_hoy' => (int)($ventas[0]['cantidad'] ?? 0),
                'productos' => (int)($productos[0]['total'] ?? 0),
                'clientes' => (int)($clientes[0]['total'] ?? 0),
                'caja_abierta' => !empty($cajas),
                'cobrado_hoy' => round((float)$cobradoHoy, 2),
                'cobrado_por_forma_pago' => array_map(
                    fn($r) => ['id' => (int)$r['id'], 'nombre' => $r['nombre'], 'total' => (float)$r['total']],
                    $pagosHoy
                ),
                'caja' => $cajas[0] ?? null
            ]
        ];
    }

    public function grafica(array $filtros): array {
        $periodo = $filtros['periodo'] ?? 'day';
        $fecha = $this->fechaValida($filtros['fecha'] ?? date('Y-m-d'));
        $inicio = null;
        $fin = null;
        $modo = 'date';

        if ($periodo === 'day') {
            if (!$fecha) return $this->errorGrafica('La fecha seleccionada no es válida');
            $inicio = $fecha;
            $fin = $inicio->modify('+1 day');
            $modo = 'hour';
        } elseif ($periodo === 'week') {
            if (!$fecha) return $this->errorGrafica('La fecha seleccionada no es válida');
            $inicio = $fecha->modify('monday this week');
            $fin = (clone $inicio)->modify('+7 days');
        } elseif ($periodo === 'range') {
            $inicio = $this->fechaValida($filtros['inicio'] ?? '');
            $ultima = $this->fechaValida($filtros['fin'] ?? '');
            if (!$inicio || !$ultima || $ultima < $inicio) {
                return $this->errorGrafica('Selecciona un rango de fechas válido');
            }
            if ($inicio->diff($ultima)->days > 365) {
                return $this->errorGrafica('El rango máximo permitido es de 366 días');
            }
            $fin = (clone $ultima)->modify('+1 day');
        } elseif ($periodo === 'month') {
            $mes = (string)($filtros['mes'] ?? date('Y-m'));
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
                return $this->errorGrafica('El mes seleccionado no es válido');
            }
            $inicio = DateTimeImmutable::createFromFormat('!Y-m-d', $mes . '-01');
            $fin = (clone $inicio)->modify('+1 month');
        } elseif ($periodo === 'year') {
            $anio = (int)($filtros['anio'] ?? date('Y'));
            if ($anio < 2000 || $anio > (int)date('Y') + 1) {
                return $this->errorGrafica('El año seleccionado no es válido');
            }
            $inicio = DateTimeImmutable::createFromFormat('!Y-m-d', $anio . '-01-01');
            $fin = (clone $inicio)->modify('+1 year');
            $modo = 'month';
        } else {
            return $this->errorGrafica('El periodo seleccionado no es válido');
        }

        $group = match ($modo) {
            'hour' => 'HOUR(fecha_registro)',
            'month' => "DATE_FORMAT(fecha_registro, '%Y-%m')",
            default => 'DATE(fecha_registro)'
        };
        $series = $this->db->select(
            "SELECT {$group} AS bucket, COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total
             FROM ventas WHERE id_ferreteria = ? AND id_sucursal = ? AND estatus <> 0
             AND fecha_registro >= ? AND fecha_registro < ? GROUP BY bucket ORDER BY bucket ASC",
            [$this->id_ferreteria, $this->id_sucursal, $inicio->format('Y-m-d H:i:s'), $fin->format('Y-m-d H:i:s')]
        );
        $resultados = [];
        foreach ($series as $fila) {
            $resultados[(string)$fila['bucket']] = ['total' => (float)$fila['total'], 'cantidad' => (int)$fila['cantidad']];
        }

        /* print_r($resultados); // Agregar esta línea para depuración
        print_r($modo); // Agregar esta línea para depuración
        die(); */ // Detener la ejecución para ver los resultados en el navegador

        $labels = [];
        $totales = [];
        $cantidades = [];
        if ($modo === 'hour') {
            for ($hora = 0; $hora < 24; $hora++) {
                $key = (string)$hora;
                $labels[] = sprintf('%02d:00', $hora);
                $totales[] = $resultados[$key]['total'] ?? 0;
                $cantidades[] = $resultados[$key]['cantidad'] ?? 0;
            }
        } else {
            $paso = $modo === 'month' ? 'P1M' : 'P1D';
                for (
                    $cursor = $inicio;
                    $cursor < $fin;
                    $cursor = $cursor->add(new DateInterval($paso))
                ) {
                    $key = $modo === 'month'
                        ? $cursor->format('Y-m')
                        : $cursor->format('Y-m-d');

                    $labels[] = $modo === 'month'
                        ? $cursor->format('m/Y')
                        : $cursor->format('d/m');

                    $totales[] = $resultados[$key]['total'] ?? 0;
                    $cantidades[] = $resultados[$key]['cantidad'] ?? 0;
                }
        }

        return ['estatus' => true, 'data' => ['labels' => $labels, 'ventas' => $totales, 'cantidad' => $cantidades]];
    }

    private function fechaValida(string $valor): ?DateTimeImmutable {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        return $fecha && $fecha->format('Y-m-d') === $valor ? $fecha : null;
    }

    private function errorGrafica(string $mensaje): array {
        return ['estatus' => false, 'mensaje' => $mensaje, 'data' => []];
    }
}