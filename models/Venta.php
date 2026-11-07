<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/dates.php';
require_once __DIR__ . '/../models/Datatable.php';
require_once __DIR__ . '/../utils/Tenant.php';
require_once __DIR__ . '/../services/FacturamaClient.php';
require_once __DIR__ . '/../services/InvoiceMailer.php';

class Venta extends Datatable
{
    private Date $fecha;
    private ?int $id_ferreteria;
    private ?int $id_sucursal;

    public function __construct(?int $id_ferreteria = null, ?int $id_sucursal = null)
    {
        $this->db = new Database();
        $this->fecha = new Date();
        $this->id_ferreteria = $id_ferreteria ?? Tenant::idFerreteria();
        $this->id_sucursal = $id_sucursal ?? (Tenant::idSucursal() ?? 1);
    }

    public function datatables($id_filtro, $start, $length, $search, $orderColumn, $orderDir)
    {
        $sql = "SELECT v.*,
                       CASE WHEN v.tipo_venta = 'credito' THEN
                           GREATEST(v.total - COALESCE((SELECT SUM(a.monto) FROM abonos_venta a WHERE a.id_venta = v.id), 0), 0)
                           ELSE 0 END AS saldo_pendiente,
                       COALESCE(c.nombre, 'Público en general') AS cliente,
                    fp.nombre AS forma_pago,
                       CONCAT(u.nombre, ' ', u.apellido) AS cajero
                FROM ventas v
                LEFT JOIN clientes c ON c.id = v.id_cliente
                LEFT JOIN formas_pago fp ON fp.id = v.id_forma_pago
                LEFT JOIN usuarios u ON u.id = v.id_usuario
                WHERE v.id_ferreteria = :id_ferreteria AND v.id_sucursal = :id_sucursal";

        $params = [
            ':id_ferreteria' => $this->id_ferreteria,
            ':id_sucursal' => $this->id_sucursal
        ];

        if (!empty($search)) {
            $sql .= " AND (v.folio LIKE :s1 OR c.nombre LIKE :s2 OR fp.nombre LIKE :s3)";
            $term = "%{$search}%";
            $params[':s1'] = $term;
            $params[':s2'] = $term;
            $params[':s3'] = $term;
        }

        $allowed = ['v.id', 'v.folio', 'v.fecha_registro', 'cliente', 'v.total', 'forma_pago'];
        $orderColumn = in_array($orderColumn, $allowed) ? $orderColumn : 'v.id';
        $orderDir = strtolower($orderDir) === 'asc' ? 'ASC' : 'DESC';

        $start = (int)$start;
        $length = (int)$length;
        $sql .= " ORDER BY {$orderColumn} {$orderDir} LIMIT {$start}, {$length}";

        return $this->db->select($sql, $params);
    }

    public function contar($id_filtro): int
    {
        return (int)$this->db->count(
            'ventas',
            'id_ferreteria = ? AND id_sucursal = ?',
            [$this->id_ferreteria, $this->id_sucursal]
        );
    }

    public function contarFiltrados($id_filtro, $search): int
    {
        if (empty($search)) {
            return $this->contar($id_filtro);
        }

        $sql = "SELECT COUNT(*) AS total 
                FROM ventas v
                LEFT JOIN clientes c ON c.id = v.id_cliente
                LEFT JOIN formas_pago fp ON fp.id = v.id_forma_pago
                WHERE v.id_ferreteria = :id_ferreteria AND v.id_sucursal = :id_sucursal
                  AND (v.folio LIKE :s1 OR c.nombre LIKE :s2 OR fp.nombre LIKE :s3)";

        $term = "%{$search}%";
        $data = $this->db->select($sql, [
            ':id_ferreteria' => $this->id_ferreteria,
            ':id_sucursal' => $this->id_sucursal,
            ':s1' => $term,
            ':s2' => $term,
            ':s3' => $term
        ]);

        return (int)($data[0]['total'] ?? 0);
    }

    public function detalle_old(int $id_venta): array
    {
        $venta = $this->db->select(
            "SELECT v.*, 
                    COALESCE(c.nombre, 'Público en general') AS cliente,
                    c.rfc AS cliente_rfc,
                    fp.nombre AS forma_pago,
                    CONCAT(u.nombre, ' ', u.apellido) AS cajero,
                    s.nombre AS sucursal_nombre
             FROM ventas v
             LEFT JOIN clientes c ON c.id = v.id_cliente
             LEFT JOIN formas_pago fp ON fp.id = v.id_forma_pago
             LEFT JOIN usuarios u ON u.id = v.id_usuario
             LEFT JOIN sucursales s ON s.id = v.id_sucursal
             WHERE v.id = ? AND v.id_ferreteria = ? AND v.id_sucursal = ?",
            [$id_venta, $this->id_ferreteria, $this->id_sucursal]
        );

        if (empty($venta)) {
            return ['estatus' => false, 'mensaje' => 'Venta no encontrada', 'data' => []];
        }

        $items = $this->db->select(
            "SELECT dv.*, COALESCE(dv.descripcion_producto, p.nombre) AS producto, p.codigo, COALESCE(dv.clave_sat, p.clave_sat) AS clave_sat, COALESCE(dv.codigo_unidad_sat, p.codigo_unidad_sat) AS codigo_unidad_sat, COALESCE(dv.objeto_impuesto, p.objeto_impuesto) AS objeto_impuesto, COALESCE(dv.unidad_producto, u.abreviatura) AS unidad,
                    COALESCE(dv.tipo_iva_venta, p.tipo_iva_venta) AS tipo_iva_facturacion,
                    COALESCE(dv.precio_venta_incluye_iva, p.precio_venta_incluye_iva) AS precio_incluye_iva_facturacion,
                    COALESCE(dv.objeto_impuesto, p.objeto_impuesto) AS objeto_impuesto_facturacion,
                    CASE WHEN dv.tipo_iva_venta IS NULL THEN 1 ELSE 0 END AS fiscal_heredado
             FROM detalle_ventas dv
             INNER JOIN productos p ON p.id = dv.id_producto
             LEFT JOIN unidades u ON u.id = p.id_unidad
             WHERE dv.id_venta = ?",
            [$id_venta]
        );

        $abonos = $this->db->select(
            "SELECT a.id, a.monto, a.observaciones, a.fecha_registro,
                    fp.nombre AS forma_pago, CONCAT(u.nombre, ' ', u.apellido) AS usuario
             FROM abonos_venta a
             LEFT JOIN formas_pago fp ON fp.id = a.id_forma_pago
             LEFT JOIN usuarios u ON u.id = a.id_usuario
             WHERE a.id_venta = ? AND a.id_ferreteria = ? AND a.id_sucursal = ?
             ORDER BY a.fecha_registro ASC, a.id ASC",
            [$id_venta, $this->id_ferreteria, $this->id_sucursal]
        );
        $total_pagado = (float)$venta[0]['total'];
        if (($venta[0]['tipo_venta'] ?? 'contado') === 'credito') {
            $total_pagado = array_sum(array_map(static fn($abono) => (float)$abono['monto'], $abonos));
        }
        $venta[0]['total_pagado'] = round($total_pagado, 2);
        $venta[0]['saldo_pendiente'] = max(0, round((float)$venta[0]['total'] - $total_pagado, 2));
        $formas_pago = $this->db->select(
            "SELECT id, nombre FROM formas_pago WHERE id_ferreteria = ? AND estatus = 1 AND LOWER(nombre) <> 'crédito' ORDER BY nombre ASC",
            [$this->id_ferreteria]
        );

        return [
            'estatus' => true,
            'mensaje' => 'OK',
            'data' => [
                'venta' => $venta[0],
                'items' => $items,
                'abonos' => $abonos,
                'formas_pago' => $formas_pago
            ]
        ];
    }

    public function detalle(int $id_venta): array
    {
        $venta = $this->db->select(
            "SELECT v.*, 
                COALESCE(c.nombre, 'Público en general') AS cliente,
                c.rfc AS cliente_rfc,
                fp.nombre AS forma_pago,
                CONCAT(u.nombre, ' ', u.apellido) AS cajero,
                s.nombre AS sucursal_nombre,
                s.direccion AS sucursal_direccion,
                s.telefono AS sucursal_telefono,
                f.nombre AS ferreteria_nombre,
                f.razon_social AS ferreteria_razon_social,
                f.rfc AS ferreteria_rfc,
                f.direccion AS ferreteria_direccion,
                f.codigo_postal AS ferreteria_codigo_postal,
                f.logo AS ferreteria_logo,
                f.tipo_reporte
         FROM ventas v
         LEFT JOIN clientes c ON c.id = v.id_cliente
         LEFT JOIN formas_pago fp ON fp.id = v.id_forma_pago
         LEFT JOIN usuarios u ON u.id = v.id_usuario
         LEFT JOIN sucursales s ON s.id = v.id_sucursal
         LEFT JOIN ferreterias f ON f.id = v.id_ferreteria
         WHERE v.id = ? AND v.id_ferreteria = ? AND v.id_sucursal = ?",
            [$id_venta, $this->id_ferreteria, $this->id_sucursal]
        );

        if (empty($venta)) {
            return ['estatus' => false, 'mensaje' => 'Venta no encontrada', 'data' => []];
        }

        $items = $this->db->select(
            "SELECT dv.*, COALESCE(dv.descripcion_producto, p.nombre) AS producto, p.codigo, COALESCE(dv.clave_sat, p.clave_sat) AS clave_sat, COALESCE(dv.codigo_unidad_sat, p.codigo_unidad_sat) AS codigo_unidad_sat, COALESCE(dv.objeto_impuesto, p.objeto_impuesto) AS objeto_impuesto, COALESCE(dv.unidad_producto, u.abreviatura) AS unidad,
                    COALESCE(dv.tipo_iva_venta, p.tipo_iva_venta) AS tipo_iva_facturacion,
                    COALESCE(dv.precio_venta_incluye_iva, p.precio_venta_incluye_iva) AS precio_incluye_iva_facturacion,
                    COALESCE(dv.objeto_impuesto, p.objeto_impuesto) AS objeto_impuesto_facturacion,
                    CASE WHEN dv.tipo_iva_venta IS NULL THEN 1 ELSE 0 END AS fiscal_heredado
         FROM detalle_ventas dv
         INNER JOIN productos p ON p.id = dv.id_producto
         LEFT JOIN unidades u ON u.id = p.id_unidad
         WHERE dv.id_venta = ?",
            [$id_venta]
        );

        $abonos = $this->db->select(
            "SELECT a.id, a.monto, a.observaciones, a.fecha_registro,
                fp.nombre AS forma_pago, CONCAT(u.nombre, ' ', u.apellido) AS usuario
         FROM abonos_venta a
         LEFT JOIN formas_pago fp ON fp.id = a.id_forma_pago
         LEFT JOIN usuarios u ON u.id = a.id_usuario
         WHERE a.id_venta = ? AND a.id_ferreteria = ? AND a.id_sucursal = ?
         ORDER BY a.fecha_registro ASC, a.id ASC",
            [$id_venta, $this->id_ferreteria, $this->id_sucursal]
        );

        $total_pagado = (float)$venta[0]['total'];
        if (($venta[0]['tipo_venta'] ?? 'contado') === 'credito') {
            $total_pagado = array_sum(array_map(static fn($abono) => (float)$abono['monto'], $abonos));
        }
        $venta[0]['total_pagado'] = round($total_pagado, 2);
        $venta[0]['saldo_pendiente'] = max(0, round((float)$venta[0]['total'] - $total_pagado, 2));

        $formas_pago = $this->db->select(
            "SELECT id, nombre FROM formas_pago WHERE id_ferreteria = ? AND estatus = 1 AND LOWER(nombre) <> 'crédito' ORDER BY nombre ASC",
            [$this->id_ferreteria]
        );

        return [
            'estatus' => true,
            'mensaje' => 'OK',
            'data' => [
                'venta' => $venta[0],
                'items' => $items,
                'abonos' => $abonos,
                'formas_pago' => $formas_pago
            ]
        ];
    }

    public function registrarAbono(int $id_venta, array $data): array
    {
        $monto = round((float)($data['monto'] ?? 0), 2);
        $id_forma_pago = (int)($data['id_forma_pago'] ?? 0);
        if ($monto <= 0 || $id_forma_pago <= 0) {
            return ['estatus' => false, 'mensaje' => 'Ingresa un monto y una forma de pago válidos'];
        }

        try {
            $this->db->beginTransaction();
            $ventas = $this->db->select(
                "SELECT id, total, tipo_venta, estatus FROM ventas
                 WHERE id = ? AND id_ferreteria = ? AND id_sucursal = ? FOR UPDATE",
                [$id_venta, $this->id_ferreteria, $this->id_sucursal]
            );
            if (empty($ventas) || (int)$ventas[0]['estatus'] !== 1) {
                $this->db->rollBack();
                return ['estatus' => false, 'mensaje' => 'La venta no existe o está cancelada'];
            }
            if (($ventas[0]['tipo_venta'] ?? 'contado') !== 'credito') {
                $this->db->rollBack();
                return ['estatus' => false, 'mensaje' => 'Esta venta no es una nota a crédito'];
            }

            $pagos = $this->db->select('SELECT COALESCE(SUM(monto), 0) AS pagado FROM abonos_venta WHERE id_venta = ?', [$id_venta]);
            $pagado = (float)($pagos[0]['pagado'] ?? 0);
            $saldo = round((float)$ventas[0]['total'] - $pagado, 2);
            if ($saldo <= 0) {
                $this->db->rollBack();
                return ['estatus' => false, 'mensaje' => 'La nota ya está liquidada'];
            }
            if ($monto > $saldo) {
                $this->db->rollBack();
                return ['estatus' => false, 'mensaje' => 'El abono no puede ser mayor al saldo de ' . number_format($saldo, 2)];
            }

            $forma_pago = $this->db->select(
                "SELECT id FROM formas_pago WHERE id = ? AND id_ferreteria = ? AND estatus = 1 AND LOWER(nombre) <> 'crédito'",
                [$id_forma_pago, $this->id_ferreteria]
            );
            if (empty($forma_pago)) {
                $this->db->rollBack();
                return ['estatus' => false, 'mensaje' => 'La forma de pago seleccionada no está disponible'];
            }

            $cajas = $this->db->select(
                "SELECT id, total_ventas FROM cajas WHERE id_ferreteria = ? AND id_sucursal = ? AND estado = 'abierta' ORDER BY id DESC LIMIT 1 FOR UPDATE",
                [$this->id_ferreteria, $this->id_sucursal]
            );
            if (empty($cajas)) {
                $this->db->rollBack();
                return ['estatus' => false, 'mensaje' => 'Abre la caja antes de registrar un abono'];
            }

            $this->db->insert('abonos_venta', [
                'id_venta' => $id_venta,
                'id_ferreteria' => $this->id_ferreteria,
                'id_sucursal' => $this->id_sucursal,
                'id_usuario' => $_SESSION['id'] ?? 1,
                'id_forma_pago' => $id_forma_pago,
                'monto' => $monto,
                'observaciones' => trim((string)($data['observaciones'] ?? '')) ?: null,
                'fecha_registro' => $this->fecha->obtenerFechaRegistro()
            ]);

            $this->db->update('cajas', ['total_ventas' => (float)$cajas[0]['total_ventas'] + $monto], 'id = ?', [$cajas[0]['id']]);

            $this->db->commit();
            return ['estatus' => true, 'mensaje' => 'Abono registrado correctamente', 'saldo' => round($saldo - $monto, 2)];
        } catch (Throwable $exception) {
            $this->db->rollBack();
            return ['estatus' => false, 'mensaje' => 'No se pudo registrar el abono'];
        }
    }

    function editar(int $id_venta, array $payload): array
    {
        $ventaRows = $this->db->select(
            'SELECT id, fecha_registro, estatus FROM ventas WHERE id = ? AND id_ferreteria = ? AND id_sucursal = ?',
            [$id_venta, $this->id_ferreteria, $this->id_sucursal]
        );
      
        if (empty($ventaRows)) return ['estatus' => false, 'mensaje' => 'Recibo no encontrado'];
        if ((int)$ventaRows[0]['estatus'] === 0) return ['estatus' => false, 'mensaje' => 'No se puede editar un recibo cancelado'];

        $nueva_fecha = trim((string)($payload['nueva_fecha'] ?? ''));
        if ($nueva_fecha === '') return ['estatus' => false, 'mensaje' => 'La nueva fecha es obligatoria'];
       
        if (!$this->fecha->esFechaValida($nueva_fecha)) return ['estatus' => false, 'mensaje' => 'La nueva fecha no tiene un formato válido'];

        try {
            $this->db->update('ventas', ['fecha_registro' => $nueva_fecha], 'id = ?', [$id_venta]);
            return ['estatus' => true, 'mensaje' => 'Fecha de venta actualizada correctamente'];
        } catch (Throwable $exception) {
            return ['estatus' => false, 'mensaje' => 'No se pudo actualizar la fecha de venta'];
        }
    }

    /**
     * facturar() con idempotencia para Facturama (API Multiemisor).
     *
     * En BD solo se persiste la identidad de la operación: Folio (ya es ventas.folio),
     * factura_date y factura_issuer_id. El payload se reconstruye SIEMPRE desde el
     * formulario, así una corrección del usuario (uso_cfdi, régimen, etc.) sí se aplica.
     *
     * SUPUESTOS sobre FacturamaClient (ajusta a tu clase):
     *  - Todas las respuestas traen 'http_code' (0 si fue timeout / error de red).
     *  - buscarEmitidaPorFolio($clientId, $issuerId, $folio) hace
     *      GET /cfdi?type=issedLite&keyword={folio}
     *    filtra por Folio EXACTO y regresa:
     *      ['estatus' => true, 'data' => null]  si no existe
     *      ['estatus' => true, 'data' => ['Id' => ..., 'TaxStamp' => ['Uuid' => ...]]] si existe
     *      ['estatus' => false, ...]            si la consulta misma falló
     *
     * Columnas que ya no se usan: factura_payload, factura_delivery_payload.
     */

   public function facturar(int $id_venta, array $data): array
    {
        $ventaRows = $this->db->select(
            'SELECT id, folio, total, id_forma_pago, estatus, estatus_factura, factura_date, factura_issuer_id
         FROM ventas WHERE id = ? AND id_ferreteria = ? AND id_sucursal = ?',
            [$id_venta, $this->id_ferreteria, $this->id_sucursal]
        );
        if (empty($ventaRows)) return ['estatus' => false, 'mensaje' => 'Recibo no encontrado'];
        $venta = $ventaRows[0];
        if ((int)$venta['estatus'] === 0) return ['estatus' => false, 'mensaje' => 'No se puede facturar un recibo cancelado'];
        if (($venta['estatus_factura'] ?? 'Pendiente') === 'Facturada') return ['estatus' => false, 'mensaje' => 'Este recibo ya está facturado'];

        $client   = new FacturamaClient();
        $clientId = (string)$this->id_ferreteria;
        $folio    = (string)$venta['folio'];
        $correo   = trim((string)($data['correo'] ?? ''));

        // Hay un intento previo con resultado incierto (timeout / 5xx)
        $intentoPrevio = ($venta['estatus_factura'] ?? '') === 'En proceso'
            && !empty($venta['factura_date'])
            && (int)($venta['factura_issuer_id'] ?? 0) > 0;

        // 1) Antes de reintentar: ¿ya se timbró?
        if ($intentoPrevio) {
            $existente = $client->buscarEmitidaPorFolio($clientId, (int)$venta['factura_issuer_id'], $folio, (string)$venta['factura_date']);
            if (!$existente['estatus']) {
                return ['estatus' => false, 'mensaje' => 'No se pudo verificar si la factura ya fue emitida; intenta de nuevo en unos minutos.'];
            }
            if (!empty($existente['data'])) {
                return $this->finalizarFactura($id_venta, $folio, $existente['data'], $correo);
            }
            // No existe: es seguro reintentar con el mismo Folio + Date
        }

        // 2) Validaciones del formulario (siempre, con los datos actuales)
        $rfc             = strtoupper(trim((string)($data['rfc'] ?? '')));
        $razon_social    = trim((string)($data['razon_social'] ?? ''));
        $codigo_postal   = trim((string)($data['codigo_postal'] ?? ''));
        $regimen_fiscal  = trim((string)($data['regimen_fiscal'] ?? ''));
        $uso_cfdi        = trim((string)($data['uso_cfdi'] ?? ''));
        if (!preg_match('/^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/', $rfc)) {
            return ['estatus' => false, 'mensaje' => 'El RFC no tiene un formato válido'];
        }
        if ($razon_social === '' || !preg_match('/^[0-9]{5}$/', $codigo_postal) || $regimen_fiscal === '' || $uso_cfdi === '') {
            return ['estatus' => false, 'mensaje' => 'Completa los datos fiscales obligatorios'];
        }
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return ['estatus' => false, 'mensaje' => 'El correo no tiene un formato válido'];
        }

        // 3) Emisor: si hay intento previo se conserva el mismo; si no, el del formulario
        $issuersResponse = $client->emisores($clientId);
        if (!$issuersResponse['estatus']) return $issuersResponse;
        $requestedIssuerId = $intentoPrevio
            ? (int)$venta['factura_issuer_id']
            : (isset($data['issuer_id']) && $data['issuer_id'] !== '' ? (int)$data['issuer_id'] : null);
        $issuerResponse = FacturamaClient::seleccionarEmisor($issuersResponse['data'], $requestedIssuerId);
        if (!$issuerResponse['estatus']) return $issuerResponse;
        $issuer   = $issuerResponse['data'];
        $issuerId = (int)$issuer['issuer_id'];

        // 4) Conceptos: snapshots fiscales del ticket, fallback solo para ventas anteriores.
        $items = $this->db->select(
            'SELECT dv.*, p.codigo,
                    COALESCE(dv.descripcion_producto, p.nombre) AS nombre,
                    COALESCE(dv.clave_sat, p.clave_sat) AS clave_sat_cfdi,
                    COALESCE(dv.codigo_unidad_sat, p.codigo_unidad_sat) AS unidad_sat_cfdi,
                    COALESCE(dv.objeto_impuesto, p.objeto_impuesto) AS objeto_cfdi,
                    COALESCE(dv.tipo_iva_venta, p.tipo_iva_venta) AS tipo_iva_cfdi,
                    COALESCE(dv.precio_venta_incluye_iva, p.precio_venta_incluye_iva) AS incluye_iva_cfdi,
                    COALESCE(dv.unidad_producto, u.abreviatura) AS unidad_cfdi
             FROM detalle_ventas dv
             LEFT JOIN productos p ON p.id = dv.id_producto AND p.id_ferreteria = ?
             LEFT JOIN unidades u ON u.id = p.id_unidad
             WHERE dv.id_venta = ? ORDER BY dv.id',
            [$this->id_ferreteria, $id_venta]
        );
        if (!$items) return ['estatus'=>false, 'mensaje'=>'Venta sin conceptos'];

        $cfdiItems = [];
        $sumaTicketC = 0;
        $sumaFiscalC = 0;
        $sumaSubtotales = 0.0;
        $sumaDescuentos = 0.0;
        $impuestosPorTasa = [];
        foreach ($items as $item) {
            $quantity = (float)$item['cantidad'];
            if ($quantity <= 0) return ['estatus'=>false,'mensaje'=>'Cantidad inválida'];
            $tipoIva = strtolower(trim((string)$item['tipo_iva_cfdi']));
            $objeto = (string)$item['objeto_cfdi'];
            if (!in_array($objeto, ['01','02'], true)) return ['estatus'=>false,'mensaje'=>'ObjetoImp no soportado'];
            if ($tipoIva === 'no_objeto') {
                if ($objeto !== '01') return ['estatus'=>false,'mensaje'=>'Impuesto inconsistente'];
                $factorTipo = 'NoObjeto'; $tasa = 0.0;
            } elseif ($tipoIva === 'exento') {
                if ($objeto !== '02') return ['estatus'=>false,'mensaje'=>'Impuesto inconsistente'];
                $factorTipo = 'Exento'; $tasa = 0.0;
            } elseif (in_array($tipoIva, ['0','0.08','0.16'], true)) {
                if ($objeto !== '02') return ['estatus'=>false,'mensaje'=>'Impuesto inconsistente'];
                $factorTipo = 'Tasa'; $tasa = (float)$tipoIva;
            } else {
                return ['estatus'=>false,'mensaje'=>'Tasa de IVA inválida'];
            }
            $incluyeIva = (int)$item['incluye_iva_cfdi'] === 1;
            $factor = $factorTipo === 'Tasa' ? 1 + $tasa : 1.0;
            $precio = (float)$item['precio'];
            $unitPrice = round($incluyeIva ? $precio / $factor : $precio, 6);
            $subtotal = round($unitPrice * $quantity, 6);

            $descuentoComercial = round((float)$item['descuento_producto'] + (float)$item['descuento_general'], 2);
            $descuentoFiscal = round($descuentoComercial / $factor, 6);
            if ($descuentoFiscal < 0 || $descuentoFiscal > $subtotal) {
                return ['estatus'=>false,'mensaje'=>'Descuento fiscal inválido'];
            }
            $base = round($subtotal - $descuentoFiscal, 6);
            $iva = $factorTipo === 'Tasa' ? round($base * $tasa, 6) : 0.0;
            $totalItem = round($base + $iva, 2);
            $importeTicket = round((float)$item['importe'], 2);
            $sumaTicketC += (int)round($importeTicket * 100);
            $sumaFiscalC += (int)round($totalItem * 100);
            $sumaSubtotales += $subtotal;
            $sumaDescuentos += $descuentoFiscal;
            if ($factorTipo === 'Tasa') {
                $key = number_format($tasa, 6, '.', '');
                $impuestosPorTasa[$key] = ($impuestosPorTasa[$key] ?? 0.0) + $iva;
            }
            if ((int)round($totalItem * 100) !== (int)round($importeTicket * 100)) {
                return ['estatus'=>false,'mensaje'=>'Descuadre fiscal en partida ' . ($item['nombre'] ?? 'sin nombre') . '. Revisar redondeos antes de timbrar.'];
            }

            $claveSat = trim((string)$item['clave_sat_cfdi']);
            $unidadSat = trim((string)$item['unidad_sat_cfdi']);
            if ($claveSat === '' || $unidadSat === '') {
                return ['estatus'=>false,'mensaje'=>'Falta clave o unidad SAT'];
            }
            $concepto = [
                'ProductCode'=>$claveSat,
                'IdentificationNumber'=>(string)($item['codigo'] ?? ''),
                'Description'=>(string)$item['nombre'],
                'Unit'=>(string)($item['unidad_cfdi'] ?: 'Pieza'),
                'UnitCode'=>$unidadSat,
                'Quantity'=>$quantity,
                'UnitPrice'=>$unitPrice,
                'Subtotal'=>$subtotal,
                'TaxObject'=>$objeto,
                'Total'=>$totalItem
            ];
            if ($descuentoFiscal > 0) {
                $concepto['Discount'] = $descuentoFiscal;
            }
            if ($factorTipo === 'Exento') {
                $concepto['Taxes'] = [[
                    'Name'=>'IVA Exento', 'Base'=>$base, 'IsRetention'=>false
                ]];
            } elseif ($factorTipo === 'Tasa') {
                $concepto['Taxes'] = [[
                    'Name'=>'IVA', 'Rate'=>$tasa, 'Base'=>$base,
                    'Total'=>$iva, 'IsRetention'=>false, 'IsQuota'=>false
                ]];
            }
            $cfdiItems[] = $concepto;
        }
        $ivaResumen = 0.0;
        foreach ($impuestosPorTasa as $importeTasa) {
            $ivaResumen += round($importeTasa, 2);
        }
        $totalFiscalResumen = round(
            round($sumaSubtotales, 2) - round($sumaDescuentos, 2) + round($ivaResumen, 2),
            2
        );
        if ($sumaTicketC !== (int)round((float)$venta['total'] * 100)
            || $sumaFiscalC !== $sumaTicketC
            || (int)round($totalFiscalResumen * 100) !== $sumaTicketC) {
            return ['estatus' => false, 'mensaje' =>
                'La suma fiscal de partidas, descuentos e impuestos no coincide con la venta. Revisa redondeos antes de timbrar.'];
        }

        // 5) Date: reutiliza la guardada si hay intento previo; si no, genera una nueva
        if ($intentoPrevio) {
            $invoiceDate = (string)$venta['factura_date'];
        } else {
            $date = new DateTime('now', new DateTimeZone('America/Mexico_City'));
            $date->modify('-5 minutes');
            $invoiceDate = $date->format('Y-m-d\TH:i:s');
        }

        $ferreteriaRows = $this->db->select('SELECT logo, codigo_postal FROM ferreterias WHERE id = ?', [$this->id_ferreteria]);
        $ferreteriaInfo = $ferreteriaRows[0] ?? [];
        $issuerPostalCode = '';
        foreach (['codigo_postal', 'tax_zip_code', 'TaxZipCode', 'postal_code'] as $postalField) {
            if (!empty($issuer[$postalField])) {
                $issuerPostalCode = trim((string)$issuer[$postalField]);
                break;
            }
        }
        $issuerPostalCode = $issuerPostalCode ?: trim((string)($ferreteriaInfo['codigo_postal'] ?? $data['lugar_expedicion'] ?? ''));
        if (!preg_match('/^[0-9]{5}$/', $issuerPostalCode)) {
            return ['estatus' => false, 'mensaje' => 'Configura un código postal fiscal de cinco dígitos para el emisor antes de facturar.'];
        }

     // 1. Recibir los valores sin forzar un valor por defecto arbitrario ('PUE' o '01')
        $paymentForm   = trim((string)($data['forma_pago'] ?? ''));
        $paymentMethod = trim((string)($data['metodo_pago'] ?? ''));
        $exportation   = trim((string)($data['exportacion'] ?? '')) ?: '01';

        // 2. Validar que obligatoriamente se hayan enviado
        if ($paymentMethod === '') {
            return ['estatus' => false, 'mensaje' => 'Debes seleccionar un método de pago válido.'];
        }

        if ($paymentForm === '') {
            return ['estatus' => false, 'mensaje' => 'Debes seleccionar una forma de pago válida.'];
        }

        // 3. Validaciones de consistencia fiscal (Reglas SAT)
        if ($paymentMethod === 'PPD' && $paymentForm !== '99') {
            return [
                'estatus' => false, 
                'mensaje' => 'Las facturas con método PPD deben utilizar obligatoriamente la forma de pago 99 (Por definir).'
            ];
        }

        if ($paymentMethod === 'PUE' && $paymentForm === '99') {
            return [
                'estatus' => false, 
                'mensaje' => 'El método de pago PUE no puede utilizar la forma de pago 99 (Por definir).'
            ];
        }

        $receiver = [
            'Rfc'=>$rfc,
            'Name'=>$razon_social,
            'CfdiUse'=>$uso_cfdi,
            'FiscalRegime'=>$regimen_fiscal,
            'TaxZipCode'=>$codigo_postal
        ];
        if ($correo !== '') $receiver['Email'] = $correo;

        $cfdiPayload = [
            'CfdiType'        => 'I',
            'Folio'           => $folio,          // clave de idempotencia
            'Date'            => $invoiceDate,    // clave de idempotencia
            'Currency'        => 'MXN',
            'PaymentForm'     => $paymentForm,
            'ExpeditionPlace' => $issuerPostalCode,
            'PaymentMethod'   => $paymentMethod,
            'Receiver'        => $receiver,
            'Items'           => $cfdiItems,
            'Exportation' => $exportation,
        ];
        $deliveryOptions = $this->opcionesEntrega($correo, $ferreteriaInfo);

        // 6) Marcar "En proceso" y guardar identidad
       // 6) Marcar "En proceso" y guardar identidad con control de concurrencia real
        $this->db->beginTransaction();
        try {
            $locked = $this->db->select(
                'SELECT estatus, estatus_factura, factura_date, factura_intent_at, factura_issuer_id
             FROM ventas WHERE id = ? AND id_ferreteria = ? AND id_sucursal = ? FOR UPDATE',
                [$id_venta, $this->id_ferreteria, $this->id_sucursal]
            );
            
            if (empty($locked) || (int)$locked[0]['estatus'] === 0) {
                $this->db->rollBack();
                return ['estatus' => false, 'mensaje' => 'La venta no está disponible para facturar'];
            }

            if ($locked[0]['estatus_factura'] === 'Facturada') {
                $this->db->rollBack();
                return ['estatus' => false, 'mensaje' => 'Este recibo ya está facturado'];
            }

            // CONTROL DE CONCURRENCIA ACTIVA:
            if ($locked[0]['estatus_factura'] === 'En proceso' && !empty($locked[0]['factura_intent_at'])) {
                $tiempoTranscurrido = time() - strtotime($locked[0]['factura_intent_at']);
                
                // Si el intento anterior ocurrió hace menos de 45 segundos, hay otra petición activa procesándolo
                if ($tiempoTranscurrido < 120) {
                    $this->db->rollBack();
                    return ['estatus' => false, 'mensaje' => 'La factura ya se está procesando en este momento. Por favor espera unos segundos.'];
                }
            }

            // Si es un intento previo antiguo (timeout / 5xx real), respetamos su fecha original de idempotencia
            if ($locked[0]['estatus_factura'] === 'En proceso' && !empty($locked[0]['factura_date'])) {
                $invoiceDate = (string)$locked[0]['factura_date'];
                $cfdiPayload['Date'] = $invoiceDate;
            }

            $nowTimestamp = date('Y-m-d H:i:s');

            $this->db->update('ventas', [
                'estatus_factura'        => 'En proceso',
                'factura_issuer_id'      => $issuerId,
                'factura_date'           => $invoiceDate,
                'factura_intent_at'      => $nowTimestamp, // <- Registramos el timestamp exacto del intento
                'factura_rfc'            => $rfc,
                'factura_razon_social'   => $razon_social,
                'factura_codigo_postal'  => $codigo_postal,
                'factura_regimen_fiscal' => $regimen_fiscal,
                'factura_uso_cfdi'       => $uso_cfdi,
                'factura_correo'         => $correo,
                'factura_metodo_pago'    => $paymentMethod,
                'factura_forma_pago'     => $paymentForm
            ], 'id = ? AND id_ferreteria = ?', [$id_venta, $this->id_ferreteria ?? $this->id_ferreteria]); // Mantén tu condición de id_ferreteria
            
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            return ['estatus' => false, 'mensaje' => 'No se pudo guardar la solicitud antes de facturar.'];
        }

        // 7) Timbrar
        $response = $client->facturar($clientId, $issuerId, $cfdiPayload, $deliveryOptions);

        if (!$response['estatus']) {
            $httpCode = (int)($response['http_code'] ?? 0);
            if ($httpCode >= 400 && $httpCode < 500 && !in_array($httpCode, [408, 429], true)) {
                $this->db->update('ventas', [
                    'estatus_factura'   => 'Pendiente',
                    'factura_date'      => null,
                    'factura_issuer_id' => null
                ], 'id = ? AND id_ferreteria = ? AND estatus_factura = ?', [$id_venta, $this->id_ferreteria, 'En proceso']);
                return $response;
            }
            $response['mensaje'] = 'No se pudo confirmar la emisión. Vuelve a intentar: se verificará antes de reenviar.';
            return $response;
        }

        return $this->finalizarFactura($id_venta, $folio, $response['data'], $correo);
    }

    /** Marca la venta como Facturada y envía documentos si hay correo. */
    private function finalizarFactura(int $id_venta, string $folio, array $providerData, string $correo): array
    {
        $taxStamp  = $providerData['TaxStamp'] ?? [];
        $facturaId = $providerData['Id'] ?? null;
        $uuid      = is_array($taxStamp) ? ($taxStamp['Uuid'] ?? null) : null;

        $this->db->update('ventas', [
            'estatus_factura' => 'Facturada',
            'factura_id'      => $facturaId,
            'uuid_factura'    => $uuid,
            'fecha_factura'   => $this->fecha->obtenerFechaRegistro()
        ], 'id = ? AND id_ferreteria = ?', [$id_venta, $this->id_ferreteria]);

        $emailSent = null;
        $message   = 'Factura emitida con éxito';
        if ($correo !== '') {
            $emailSent = !empty($providerData['EmailSent']);
            if (!$emailSent) {
                $emailResult = (new InvoiceMailer())->enviarDocumentos($correo, $folio, $providerData);
                $emailSent = (bool)$emailResult['estatus'];
            }
            $message = $emailSent
                ? 'Factura emitida y documentos enviados al correo registrado.'
                : 'Factura emitida; el proxy no confirmó el envío y no fue posible enviar PDF/XML por SMTP.';
        }
        return ['estatus' => true, 'mensaje' => $message, 'uuid' => $uuid, 'factura_id' => $facturaId, 'correo_enviado' => $emailSent];
    }

    /** Opciones de entrega (correo + logo). No se persisten: se rearman en cada intento. */
    private function opcionesEntrega(string $correo, array $ferreteriaInfo): array
    {
        $options  = $correo !== '' ? ['email' => $correo] : [];
        $logoName = basename((string)($ferreteriaInfo['logo'] ?? ''));
        $logoPath = dirname(__DIR__) . '/static/img/ferreterias/' . $logoName;
        if ($logoName !== '' && is_file($logoPath) && filesize($logoPath) <= 3 * 1024 * 1024) {
            $logoInfo = @getimagesize($logoPath);
            if ($logoInfo !== false && in_array($logoInfo['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) {
                $options['logo'] = [
                    'filename'       => $logoName,
                    'mime_type'      => $logoInfo['mime'],
                    'content_base64' => base64_encode((string)file_get_contents($logoPath))
                ];
            }
        }
        return $options;
    }

    public function emisores(): array
    {
        if ($this->id_ferreteria === null) {
            return ['estatus' => false, 'mensaje' => 'No se pudo identificar la empresa autenticada.'];
        }
        $response = (new FacturamaClient())->emisores((string)$this->id_ferreteria);
        if (!$response['estatus']) return $response;
        if (empty($response['data'])) {
            return ['estatus' => false, 'mensaje' => 'Esta empresa no tiene emisores asignados. Contacta al administrador.', 'data' => []];
        }
        return $response;
    }

    public function descargarFactura(int $id_venta, string $formato): array
    {
        if (!in_array($formato, ['pdf', 'xml'], true)) {
            return ['estatus' => false, 'mensaje' => 'Formato de descarga no válido'];
        }
        $rows = $this->db->select(
            "SELECT factura_id, factura_issuer_id, estatus_factura
             FROM ventas WHERE id = ? AND id_ferreteria = ? AND id_sucursal = ?",
            [$id_venta, $this->id_ferreteria, $this->id_sucursal]
        );
        if (empty($rows) || $rows[0]['estatus_factura'] !== 'Facturada') {
            return ['estatus' => false, 'mensaje' => 'La venta todavía no tiene una factura emitida'];
        }
        if (empty($rows[0]['factura_id']) || (int)($rows[0]['factura_issuer_id'] ?? 0) <= 0) {
            return ['estatus' => false, 'mensaje' => 'La factura no tiene un identificador de descarga válido'];
        }
        return (new FacturamaClient())->descargarCfdi(
            (string)$this->id_ferreteria,
            (int)$rows[0]['factura_issuer_id'],
            (string)$rows[0]['factura_id'],
            $formato
        );
    }

     public function cancelarFactura(int $id_venta, string $motivo, string $uuidSustituto = ''): array
{
    if ($id_venta <= 0) {
        return ['estatus' => false, 'mensaje' => 'Venta inválida'];
    }
    if (!in_array($motivo, ['01', '02', '03'], true)) {
        return ['estatus' => false, 'mensaje' => 'Motivo de cancelación inválido'];
    }
    if ($motivo === '01') {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuidSustituto)) {
            return ['estatus' => false, 'mensaje' => 'El motivo 01 requiere el UUID válido de la factura sustituta'];
        }
    } else {
        $uuidSustituto = ''; // Facturama puede fallar si lo mandas cuando no aplica
    }

    $rows = $this->db->select(
        "SELECT factura_id, factura_issuer_id, estatus_factura
         FROM ventas WHERE id = ? AND id_ferreteria = ? AND id_sucursal = ?",
        [$id_venta, $this->id_ferreteria, $this->id_sucursal]
    );
    if (empty($rows)) {
        return ['estatus' => false, 'mensaje' => 'Venta no encontrada'];
    }
    $venta = $rows[0];

    if ($venta['estatus_factura'] === 'Cancelada') {
        return ['estatus' => false, 'mensaje' => 'La factura ya está cancelada'];
    }
    if ($venta['estatus_factura'] === 'Cancelación pendiente') {
        return ['estatus' => false, 'mensaje' => 'La cancelación ya fue solicitada y espera respuesta del receptor'];
    }
    if ($venta['estatus_factura'] !== 'Facturada') {
        return ['estatus' => false, 'mensaje' => 'La venta todavía no tiene una factura emitida'];
    }
    if (empty($venta['factura_id']) || (int)($venta['factura_issuer_id'] ?? 0) <= 0) {
        return ['estatus' => false, 'mensaje' => 'La factura no tiene un identificador/emisor válido'];
    }

    $resp = (new FacturamaClient())->cancelarCfdi(
        (string)$this->id_ferreteria,
        (int)$venta['factura_issuer_id'],
        (string)$venta['factura_id'],
        $motivo,
        $uuidSustituto
    );
    if (!$resp['estatus']) {
        return $resp;
    }

    if($resp['sat_status'] === 'active'){
        return ['estatus' => false, 'mensaje' => 'No se puede cancelar, tiene documentos relacionados', 'resp' => $resp];  
        }else if($resp['sat_status'] === 'pending'){
        $nuevoEstatus = 'Cancelacion pendiente';
    }else if($resp['sat_status'] == 'canceled'){
        $nuevoEstatus = 'Cancelada';
    }else{
        return ['estatus' => false, 'mensaje' => 'La factura no pudo cancelarse', 'resp' => $resp];
    };

    // Ajusta al método de tu wrapper ($this->db->execute / update / etc.)
    $this->db->query(
        "UPDATE ventas
         SET estatus_factura = ?, factura_cancelacion_motivo = ?,
             factura_cancelacion_uuid_sustituto = ?, factura_cancelacion_fecha = NOW()
         WHERE id = ? AND id_ferreteria = ? AND id_sucursal = ? AND estatus_factura = 'Facturada'",
        [$nuevoEstatus, $motivo, $uuidSustituto ?: null, $id_venta, $this->id_ferreteria, $this->id_sucursal]
    );

    return $resp;
}


    public function enviarFacturaPorCorreo(int $id_venta, string $correo, string $asunto, string $comentarios): array
    {
        $rows = $this->db->select(
            "SELECT v.factura_id, v.factura_issuer_id, v.factura_correo, f.correo AS ferreteria_correo, v.estatus_factura
             FROM ventas v INNER JOIN ferreterias f ON v.id_ferreteria = f.id
             WHERE v.id = ? AND v.id_ferreteria = ? AND v.id_sucursal = ?",
            [$id_venta, $this->id_ferreteria, $this->id_sucursal]
        );
        if (empty($rows) || $rows[0]['estatus_factura'] !== 'Facturada') {
            return ['estatus' => false, 'mensaje' => 'La venta todavía no tiene una factura emitida'];
        }
        $venta = $rows[0];
        $ferreteria_correo = trim((string)($venta['ferreteria_correo'] ?? ''));
        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return ['estatus' => false, 'mensaje' => 'Esta factura no tiene un correo de destino válido configurado'];
        }
        if (empty($venta['factura_id']) || (int)($venta['factura_issuer_id'] ?? 0) <= 0) {
            return ['estatus' => false, 'mensaje' => 'La factura no tiene un identificador de envío válido'];
        }

        $response = (new FacturamaClient())->enviarCfdiPorCorreo(
            (string)$this->id_ferreteria,
            (int)$venta['factura_issuer_id'],
            (string)$venta['factura_id'],
            $correo,
            $ferreteria_correo,
            $asunto,
            $comentarios
        );
        if ($response['estatus']) $response['correo'] = $correo;
        return $response;
    }

    public function cancelarVenta(int $id_venta, string $motivo): array
    {
        $det = $this->detalle($id_venta);
        if (!$det['estatus']) {
            return $det;
        }

        $venta = $det['data']['venta'];

        if ((int)$venta['estatus'] === 0) {
            return ['estatus' => false, 'mensaje' => 'Esta venta ya se encuentra cancelada'];
        }

        $montoCobrado = (float)$venta['total'];
        if (($venta['tipo_venta'] ?? 'contado') === 'credito') {
            $montoCobrado = (float)$venta['total_pagado'];
            if ($montoCobrado > 0) {
                return ['estatus' => false, 'mensaje' => 'No se puede cancelar una nota a crédito con abonos; registra primero la devolución del dinero'];
            }
        }

        // 1) Antes de cancelar: ¿La venta esta facturada?
        $estatusFactura = $venta['estatus_factura'] ?? 'Pendiente';

// Ya facturada: bloquear
if ($estatusFactura === 'Facturada') {
    return ['estatus' => false, 'mensaje' => 'Cancela primero la factura antes de cancelar la venta'];
}

// Intento de timbrado con resultado incierto: verificar en Facturama
if ($estatusFactura === 'En proceso') {
    $issuerId = (int)($venta['factura_issuer_id'] ?? 0);
    if ($issuerId < 1) {
        return ['estatus' => false, 'mensaje' => 'No se puede verificar el estado de la factura; contacta a soporte.'];
    }

    $client = new FacturamaClient();
    $existente = $client->buscarEmitidaPorFolio(
        (string)$this->id_ferreteria,
        $issuerId,
        (string)$venta['folio'],
        '',      // sin date
        true     // quick
    );

    // Si no se pudo verificar, NO cancelar (fail-closed)
    if (!$existente['estatus']) {
        return ['estatus' => false, 'mensaje' => 'No se pudo verificar si la venta tiene factura emitida; intenta de nuevo en unos minutos.'];
    }

    if (!empty($existente['data'])) {
        // Opcional: sincronizar tu BD marcándola como Facturada
        // $this->finalizarFactura($id_venta, $venta['folio'], $existente['data'], '');
        return ['estatus' => false, 'mensaje' => 'La venta ya tiene una factura emitida; cancela primero la factura.'];
    }
}

        $items = $det['data']['items'];
        $id_usuario = $_SESSION['id'] ?? null;

        try {
            $this->db->beginTransaction();

            // Marcar venta como cancelada
            $this->db->update('ventas', ['estatus' => 0], 'id = ?', [$id_venta]);

            // Devolver stock a inventario
            foreach ($items as $it) {
                $id_producto = (int)$it['id_producto'];
                $cantidad = (float)$it['cantidad'];

                $inv = $this->db->select(
                    "SELECT id, existencia FROM inventario WHERE id_producto = ? AND id_sucursal = ? AND id_ferreteria = ?",
                    [$id_producto, $this->id_sucursal, $this->id_ferreteria]
                );

                $stock_antes = !empty($inv) ? (float)$inv[0]['existencia'] : 0.0;
                $stock_despues = $stock_antes + $cantidad;

                if (!empty($inv)) {
                    $this->db->update('inventario', ['existencia' => $stock_despues], 'id = ?', [$inv[0]['id']]);
                }

                $this->db->insert('movimientos_inventario', [
                    'id_ferreteria' => $this->id_ferreteria,
                    'id_sucursal' => $this->id_sucursal,
                    'id_producto' => $id_producto,
                    'tipo' => 'cancelacion_venta',
                    'cantidad' => $cantidad,
                    'existencia_antes' => $stock_antes,
                    'existencia_despues' => $stock_despues,
                    'referencia' => "Cancelación Ticket {$venta['folio']}: {$motivo}",
                    'id_usuario' => $id_usuario,
                    'fecha_registro' => $this->fecha->obtenerFechaRegistro()
                ]);
            }

            // Descontar de la caja activa si existe
            $cajaAbierta = $this->db->select(
                "SELECT id, total_ventas FROM cajas WHERE id_ferreteria = ? AND id_sucursal = ? AND estado = 'abierta' ORDER BY id DESC LIMIT 1",
                [$this->id_ferreteria, $this->id_sucursal]
            );
            if (!empty($cajaAbierta)) {
                $cajaId = (int)$cajaAbierta[0]['id'];
                $totalActual = max(0, (float)$cajaAbierta[0]['total_ventas'] - $montoCobrado);
                $this->db->update('cajas', ['total_ventas' => $totalActual], 'id = ?', [$cajaId]);
            }

            $this->db->commit();

            return ['estatus' => true, 'mensaje' => 'Venta cancelada y stock restaurado con éxito'];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['estatus' => false, 'mensaje' => 'Error al cancelar la venta: ' . $e->getMessage()];
        }
    }
}
