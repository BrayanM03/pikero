<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/dates.php';
require_once __DIR__ . '/../utils/Tenant.php';

class Panel {
    private Database $db;
    private ?int $id_ferreteria;
    private ?int $id_sucursal;

    public function __construct(?int $id_ferreteria = null, ?int $id_sucursal = null) {
        $this->db = new Database();
        $this->id_ferreteria = $id_ferreteria ?? Tenant::idFerreteria();
        $this->id_sucursal = $id_sucursal ?? Tenant::idSucursal();
    }

    public function resumen(array $filtros = []): array {
        $rango = $this->resolverPeriodo($filtros);
        if (!$rango['estatus']) {
            return $rango;
        }

        $tenant = [$this->id_ferreteria, $this->id_sucursal];
        $params = $tenant;
        $limiteVentas = '';
        $limiteGastos = '';
        if ($rango['inicio'] !== null) {
            $params[] = $rango['inicio']->format('Y-m-d H:i:s');
            $params[] = $rango['fin']->format('Y-m-d H:i:s');
            $limiteVentas = ' AND fecha_registro >= ? AND fecha_registro < ?';
            $limiteGastos = ' AND fecha >= ? AND fecha < ?';
        }

        $ventas = $this->db->select(
            'SELECT COUNT(*) AS cantidad,
                COALESCE(SUM(costo), 0) AS costo,
                COALESCE(SUM(utilidad), 0) AS utilidad,
                COALESCE(SUM(total), 0) AS total
             FROM ventas WHERE id_ferreteria = ? AND id_sucursal = ? AND estatus <> 0' . $limiteVentas,
            $params
        );

        $gastos = $this->db->select(
            'SELECT COALESCE(SUM(monto), 0) AS total
             FROM gastos WHERE id_ferreteria = ? AND id_sucursal = ? AND estatus = 1' . $limiteGastos,
            $params
        );

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

        $pagosTurno = empty($cajas) ? [] : $this->db->select(
            "SELECT fp.id, fp.nombre, fp.es_efectivo, SUM(dp.monto) AS total
             FROM detalle_pagos dp
             JOIN formas_pago fp ON fp.id = dp.id_forma_pago
             LEFT JOIN abonos_venta a ON a.id = dp.id_abono_venta
             JOIN ventas v ON v.id = COALESCE(dp.id_venta, a.id_venta)
             WHERE dp.id_ferreteria = ? AND dp.id_sucursal = ? AND v.estatus <> 0
               AND dp.id_caja = ?
             GROUP BY fp.id, fp.nombre, fp.es_efectivo
             ORDER BY total DESC",
            [...$tenant, (int)$cajas[0]['id']]
        );

        return [
            'estatus' => true,
            'data' => [
                'periodo' => $rango['periodo'],
                'inicio_periodo' => $rango['inicio'],
                'fin_periodo' => $rango['fin'],
                'ventas_hoy' => (float)($ventas[0]['total'] ?? 0),
                'gastos_hoy' => (float)($gastos[0]['total'] ?? 0),
                'utilidad' => (float)($ventas[0]['utilidad'] ?? 0),
                'cantidad_ventas_hoy' => (int)($ventas[0]['cantidad'] ?? 0),
                'productos' => (int)($productos[0]['total'] ?? 0),
                'clientes' => (int)($clientes[0]['total'] ?? 0),
                'caja_abierta' => !empty($cajas),
                'cobrado_hoy' => round((float)array_sum(array_column($pagosTurno, 'total')), 2),
                'cobrado_por_forma_pago' => array_map(
                    fn($r) => ['id' => (int)$r['id'], 'nombre' => $r['nombre'],
                        'es_efectivo' => (bool)$r['es_efectivo'], 'total' => (float)$r['total']],
                    $pagosTurno
                ),
                'caja' => $cajas[0] ?? null
            ]
        ];
    }

    public function grafica(array $filtros): array {
        $rango = $this->resolverPeriodo($filtros);
        if (!$rango['estatus']) {
            return $rango;
        }
        ['inicio' => $inicio, 'fin' => $fin, 'modo' => $modo] = $rango;
        $params = [$this->id_ferreteria, $this->id_sucursal];
        $limite = '';
        if ($inicio !== null) {
            $limite = ' AND fecha_registro >= ? AND fecha_registro < ?';
            $params[] = $inicio->format('Y-m-d H:i:s');
            $params[] = $fin->format('Y-m-d H:i:s');
        }

        $group = match ($modo) {
            'hour' => 'HOUR(fecha_registro)',
            'month' => "DATE_FORMAT(fecha_registro, '%Y-%m')",
            default => 'DATE(fecha_registro)'
        };
        $series = $this->db->select(
            "SELECT {$group} AS bucket, COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total
             FROM ventas WHERE id_ferreteria = ? AND id_sucursal = ? AND estatus <> 0
             {$limite} GROUP BY bucket ORDER BY bucket ASC",
            $params
        );
        $resultados = [];
        foreach ($series as $fila) {
            $resultados[(string)$fila['bucket']] = ['total' => (float)$fila['total'], 'cantidad' => (int)$fila['cantidad']];
        }

        $labels = [];
        $totales = [];
        $cantidades = [];
        if ($inicio === null && $series) {
            $inicio = $this->fechaValida($series[0]['bucket'] . '-01');
            $ultimoMes = $series[array_key_last($series)]['bucket'];
            $fin = $this->fechaValida($ultimoMes . '-01')?->modify('+1 month');
        }
        if ($modo === 'hour') {
            for ($hora = 0; $hora < 24; $hora++) {
                $key = (string)$hora;
                $labels[] = sprintf('%02d:00', $hora);
                $totales[] = $resultados[$key]['total'] ?? 0;
                $cantidades[] = $resultados[$key]['cantidad'] ?? 0;
            }
        } elseif ($inicio !== null && $fin !== null) {
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

    private function resolverPeriodo(array $filtros): array {
        $periodo = $filtros['periodo'] ?? 'day';
        $fecha = $this->fechaValida($filtros['fecha'] ?? date('Y-m-d'));
        $inicio = null;
        $fin = null;
        $modo = 'date';

        if ($periodo === 'day') {
            if (!$fecha) return $this->errorPeriodo('La fecha seleccionada no es válida');
            $inicio = $fecha;
            $fin = $inicio->modify('+1 day');
            $modo = 'hour';
        } elseif ($periodo === 'week') {
            if (!$fecha) return $this->errorPeriodo('La fecha seleccionada no es válida');
            $inicio = $fecha->modify('monday this week');
            $fin = (clone $inicio)->modify('+7 days');
        } elseif ($periodo === 'range') {
            $inicio = $this->fechaValida($filtros['inicio'] ?? '');
            $ultima = $this->fechaValida($filtros['fin'] ?? '');
            if (!$inicio || !$ultima || $ultima < $inicio) {
                return $this->errorPeriodo('Selecciona un rango de fechas válido');
            }
            if ($inicio->diff($ultima)->days > 365) {
                return $this->errorPeriodo('El rango máximo permitido es de 366 días');
            }
            $fin = (clone $ultima)->modify('+1 day');
        } elseif ($periodo === 'month') {
            $mes = $filtros['mes'] ?? date('Y-m');
            if (!is_string($mes) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
                return $this->errorPeriodo('El mes seleccionado no es válido');
            }
            $inicio = DateTimeImmutable::createFromFormat('!Y-m-d', $mes . '-01');
            $fin = (clone $inicio)->modify('+1 month');
        } elseif ($periodo === 'year') {
            $anio = filter_var($filtros['anio'] ?? date('Y'), FILTER_VALIDATE_INT);
            if ($anio < 2000 || $anio > (int)date('Y') + 1) {
                return $this->errorPeriodo('El año seleccionado no es válido');
            }
            $inicio = DateTimeImmutable::createFromFormat('!Y-m-d', $anio . '-01-01');
            $fin = (clone $inicio)->modify('+1 year');
            $modo = 'month';
        } elseif ($periodo === 'all') {
            $modo = 'month';
        } else {
            return $this->errorPeriodo('El periodo seleccionado no es válido');
        }

        return ['estatus' => true, 'periodo' => $periodo, 'inicio' => $inicio, 'fin' => $fin, 'modo' => $modo];
    }

    private function fechaValida(mixed $valor): ?DateTimeImmutable {
        if (!is_string($valor)) {
            return null;
        }
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        return $fecha && $fecha->format('Y-m-d') === $valor ? $fecha : null;
    }

    private function errorPeriodo(string $mensaje): array {
        return ['estatus' => false, 'mensaje' => $mensaje, 'data' => []];
    }
}
