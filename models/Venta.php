<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/dates.php';
require_once __DIR__ . '/../models/Datatable.php';
require_once __DIR__ . '/../utils/Tenant.php';
require_once __DIR__ . '/../services/FacturamaClient.php';
require_once __DIR__ . '/../services/InvoiceMailer.php';
require_once __DIR__ . '/../services/CfdiConceptEditor.php';
use App\Services\CfdiConceptEditor; // Ajusta el namespace según corresponda a tu estructura

class Venta extends Datatable
{
    private const UUID_RE = '/^[a-fA-F0-9]{8}-(?:[a-fA-F0-9]{4}-){3}[a-fA-F0-9]{12}$/';
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
                    COALESCE(dv.sugerir_retencion_isr_resico, p.sugerir_retencion_isr_resico, 0) AS sugerir_retencion_isr_resico,
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
                    COALESCE(dv.sugerir_retencion_isr_resico, p.sugerir_retencion_isr_resico, 0) AS sugerir_retencion_isr_resico,
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

        $documentos = $this->db->select(
            'SELECT id,version,tipo_cfdi,folio,fecha_cfdi,issuer_id,uuid,estado,relacion_tipo,uuid_relacionado,documento_sustituido_id,sat_status
             FROM cfdi_documentos WHERE id_venta=? AND id_ferreteria=? AND id_sucursal=? ORDER BY version ASC',
            [$id_venta,$this->id_ferreteria,$this->id_sucursal]
        );
        // La versión timbrada se conserva exactamente por concepto y en su orden original.
        // Esto permite sustituir sin perder ajustes de impuestos o descripciones.
        $conceptosVigentes = [];
        if (!empty($venta[0]['uuid_factura'])) {
            $emitido = $this->db->select(
                'SELECT payload_json FROM cfdi_documentos WHERE id_venta=? AND id_ferreteria=? AND uuid=? LIMIT 1',
                [$id_venta,$this->id_ferreteria,strtoupper((string)$venta[0]['uuid_factura'])]
            );
            $payloadEmitido = json_decode((string)($emitido[0]['payload_json'] ?? ''), true);
            if (is_array($payloadEmitido) && is_array($payloadEmitido['Items'] ?? null)) {
                foreach ($items as $pos => $it) {
                    if (isset($payloadEmitido['Items'][$pos]) && is_array($payloadEmitido['Items'][$pos])) {
                        $conceptosVigentes[(string)$it['id']] = $payloadEmitido['Items'][$pos];
                    }
                }
            }
        }
        // Precargar, por índice de partida, las tasas y retenciones realmente
        // timbradas en la última versión. No consultar el producto actual para ello.
        $configActual = [];
        if (!empty($venta[0]['uuid_factura'])) {
            $actual = $this->db->select(
                'SELECT payload_json FROM cfdi_documentos WHERE id_venta=? AND id_ferreteria=? AND uuid=? LIMIT 1',
                [$id_venta, $this->id_ferreteria, strtoupper((string)$venta[0]['uuid_factura'])]
            );
            $payloadActual = json_decode((string)($actual[0]['payload_json'] ?? ''), true);
            if (is_array($payloadActual) && isset($payloadActual['Items']) && is_array($payloadActual['Items'])) {
                foreach ($items as $idx => $itemBD) {
                    $conceptoActual = $payloadActual['Items'][$idx] ?? null;
                    if (!is_array($conceptoActual)) continue;
                    $tipo = (string)($itemBD['tipo_iva_facturacion'] ?? '');
                    if (($conceptoActual['TaxObject'] ?? '') === '01') $tipo = 'no_objeto';
                    $tieneTrasladoIVA = false;
                    foreach (($conceptoActual['Taxes'] ?? []) as $tax) {
                        if (in_array(($tax['Name'] ?? ''), ['IVA', 'IVA Exento'], true)
                            && empty($tax['IsRetention'])) $tieneTrasladoIVA = true;
                    }
                    if (($conceptoActual['TaxObject'] ?? '') === '02' && !$tieneTrasladoIVA) {
                        $tipo = 'sin_traslado_iva';
                    }
                    foreach (($conceptoActual['Taxes'] ?? []) as $tax) {
                        if (($tax['Name'] ?? '') === 'IVA Exento') $tipo = 'exento';
                        if (($tax['Name'] ?? '') === 'IVA' && empty($tax['IsRetention'])) {
                            $tipo = (string)(float)($tax['Rate'] ?? 0);
                        }
                    }
                    $tieneISR = false;
                    foreach (($conceptoActual['Taxes'] ?? []) as $tax) {
                        if (($tax['Name'] ?? '') === 'ISR' && !empty($tax['IsRetention'])
                            && abs((float)($tax['Rate'] ?? 0) - 0.0125) < 0.0000001) {
                            $tieneISR = true;
                        }
                    }
                    $configActual[(string)$itemBD['id']] = [
                        'tipo_iva' => $tipo, 'retencion_isr_resico' => $tieneISR
                    ];
                }
            }
        }
        return [
            'estatus' => true,
            'mensaje' => 'OK',
            'data' => [
                'venta' => $venta[0],
                'items' => $items,
                'abonos' => $abonos,
                'formas_pago' => $formas_pago,
                'documentos_cfdi' => $documentos,
                'impuestos_cfdi_vigente' => $configActual,
                'conceptos_cfdi_vigente' => $conceptosVigentes
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
        if (!in_array(($venta['estatus_factura'] ?? 'Pendiente'), ['Pendiente','En proceso'], true)) return ['estatus' => false, 'mensaje' => 'La venta ya tiene una factura o una sustitución pendiente; consulta su historial fiscal.'];

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

        // Fecha fija durante todos los reintentos del mismo CFDI.
        if ($intentoPrevio) {
            $invoiceDate = (string)$venta['factura_date'];
        } else {
            $date = new DateTime('now', new DateTimeZone('America/Matamoros'));
            $date->modify('-5 minutes');
            $invoiceDate = $date->format('Y-m-d\TH:i:s');
        }
        $preparado = $this->prepararPayloadFactura(
            $id_venta, $venta, $data, $client, $folio, $invoiceDate,
            $intentoPrevio ? (int)$venta['factura_issuer_id'] : null
        );
        if (!$preparado['estatus']) return $preparado;
        $issuerId = $preparado['issuer_id'];
        $cfdiPayload = $preparado['payload'];
        // Un reintento incierto debe conservar el payload original (conceptos,
        // receptor, tasas, fecha y folio). No cambiar datos al reenviar.
        if ($intentoPrevio) {
            $frozen = $this->db->select('SELECT factura_payload FROM ventas WHERE id=? AND id_ferreteria=?',
                [$id_venta,$this->id_ferreteria]);
            $frozenPayload=json_decode((string)($frozen[0]['factura_payload'] ?? ''),true);
            if (!is_array($frozenPayload) || !isset($frozenPayload['Items'])) {
                return ['estatus'=>false,'mensaje'=>'Intento anterior sin payload íntegro; revisar emisión manualmente'];
            }
            $cfdiPayload=$frozenPayload;
        }

        $deliveryOptions = $preparado['entrega'];
        $rfc = $preparado['rfc'];
        $razon_social = $preparado['razon_social'];
        $codigo_postal = $preparado['codigo_postal'];
        $regimen_fiscal = $preparado['regimen_fiscal'];
        $uso_cfdi = $preparado['uso_cfdi'];
        $paymentMethod = $preparado['metodo_pago'];
        $paymentForm = $preparado['forma_pago'];

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

            if (!in_array($locked[0]['estatus_factura'], ['Pendiente','En proceso'], true)) {
                $this->db->rollBack();
                return ['estatus' => false, 'mensaje' => 'Esta venta ya tiene factura o sustitución en curso'];
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
                'factura_forma_pago'     => $paymentForm,
                'factura_payload'        => json_encode($cfdiPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
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

    /** ÚNICO constructor de conceptos y CFDI: reutilizado en emisión y sustitución. */
    private function prepararPayloadFactura(
        int $id_venta, array $venta, array $data,
        FacturamaClient $client, string $folio, string $invoiceDate,
        ?int $emisorFijo = null
    ): array {
        $clientId = (string)$this->id_ferreteria;
        $correo = trim((string)($data['correo'] ?? ''));
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
        $requestedIssuerId = $emisorFijo ?? (isset($data['issuer_id']) && $data['issuer_id'] !== '' ? (int)$data['issuer_id'] : null);
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

        // La API actual admite conceptos editables SOLO si se envia el conjunto completo.
        // Se ejecuta reconstruccion fiscal en backend; JAMAS confiar en totales JS.
        if (array_key_exists('conceptos_editados', $data)) {
            if (!is_array($data['conceptos_editados'])) {
                return ['estatus'=>false,'mensaje'=>'conceptos_editados debe ser un objeto de partidas'];
            }
            $issuerRfc = strtoupper(trim((string)($issuer['rfc'] ?? $issuer['Rfc'] ?? '')));
            $issuerRegimen = trim((string)($issuer['fiscal_regime'] ?? $issuer['FiscalRegime'] ?? $issuer['regimen_fiscal'] ?? ''));
            if ($issuerRegimen === '' && $issuerRfc !== '') {
                $emisorTenant=$this->db->select('SELECT regimen_fiscal FROM ferreterias WHERE id=? AND UPPER(rfc)=? LIMIT 1',
                    [$this->id_ferreteria,$issuerRfc]);
                $issuerRegimen=trim((string)($emisorTenant[0]['regimen_fiscal'] ?? ''));
            }
            $build =  CfdiConceptEditor::build($items, $data['conceptos_editados'],
                (float)$venta['total'], $issuerRfc, $issuerRegimen, $rfc);
            if (!$build['estatus']) return $build;
            $cfdiItems=$build['items'];
            $isrResumen=0.0;
            foreach ($cfdiItems as $itCfdi) {
                foreach (($itCfdi['Taxes'] ?? []) as $tax) {
                    if (($tax['Name'] ?? '')==='ISR' && !empty($tax['IsRetention'])) {
                        $isrResumen+=(float)($tax['Total'] ?? 0);
                    }
                }
            }
            $isrResumen=round($isrResumen,2);
            $totalFiscalResumen=(float)$build['resumen']['total_cfdi'];
        } else {
        $ajustesFiscal = $data['impuestos_partidas'] ?? [];
        if (!is_array($ajustesFiscal)) {
            return ['estatus'=>false, 'mensaje'=>'La configuración de impuestos debe ser un objeto'];
        }
        $issuerRfc = strtoupper(trim((string)($issuer['rfc'] ?? $issuer['Rfc'] ?? '')));
        $issuerRegimen = trim((string)($issuer['fiscal_regime'] ?? $issuer['FiscalRegime'] ?? $issuer['regimen_fiscal'] ?? ''));
        if ($issuerRegimen === '' && $issuerRfc !== '') {
            // Respaldo seguro solamente cuando el RFC del emisor coincida con el de la empresa.
            $empresaEmisor = $this->db->select(
                'SELECT regimen_fiscal FROM ferreterias WHERE id=? AND UPPER(rfc)=? LIMIT 1',
                [$this->id_ferreteria, $issuerRfc]
            );
            $issuerRegimen = trim((string)($empresaEmisor[0]['regimen_fiscal'] ?? ''));
        }
        $idsValidos = array_fill_keys(array_map('strval', array_column($items, 'id')), true);
        foreach (array_keys($ajustesFiscal) as $idDetalle) {
            if (!isset($idsValidos[(string)$idDetalle]) || !is_array($ajustesFiscal[$idDetalle])) {
                return ['estatus'=>false, 'mensaje'=>'Configuración de impuestos no corresponde a la venta'];
            }
        }

        $cfdiItems = [];
        $sumaTicketC = 0;
        $sumaCfdiC = 0;
        $sumaRetencionIsr = 0.0;
        $sumaFiscalC = 0;
        $sumaSubtotales = 0.0;
        $sumaDescuentos = 0.0;
        $impuestosPorTasa = [];
        foreach ($items as $item) {
            $quantity = (float)$item['cantidad'];
            if ($quantity <= 0) return ['estatus'=>false,'mensaje'=>'Cantidad inválida'];
            $ajuste = $ajustesFiscal[(string)$item['id']] ?? [];
            $tipoOriginal = strtolower(trim((string)$item['tipo_iva_cfdi']));
            $tipoIva = strtolower(trim((string)($ajuste['tipo_iva'] ?? $tipoOriginal)));
            if (!in_array($tipoIva, ['0','0.08','0.16','exento','no_objeto','sin_traslado_iva'], true)) {
                return ['estatus'=>false, 'mensaje'=>'Tratamiento de IVA fuera de catálogo'];
            }
            // Cuando el administrador corrige una tasa antes de facturar,
            // no se cambia detalle_ventas; el CFDI se calcula desde el total cobrado.
            $cambioTipo = $tipoIva !== $tipoOriginal;
            $objeto = $cambioTipo
                ? ($tipoIva === 'no_objeto' ? '01' : '02')
                : (string)$item['objeto_cfdi'];
            $retenIsr = ($ajuste['retencion_isr_resico'] ?? false) === true
                || ($ajuste['retencion_isr_resico'] ?? false) === 1;
            if ($retenIsr) {
                if ($objeto !== '02' || $issuerRegimen !== '626'
                    || strlen($issuerRfc) !== 13 || strlen($rfc) !== 12) {
                    return ['estatus'=>false, 'mensaje'=>'ISR 1.25% requiere emisor persona física RESICO 626, receptor persona moral y objeto de impuesto 02. Verifica el régimen del emisor en el microservicio.'];
                }
            }
            if (!in_array($objeto, ['01','02'], true)) return ['estatus'=>false,'mensaje'=>'ObjetoImp no soportado'];
            if ($tipoIva === 'no_objeto') {
                if ($objeto !== '01') return ['estatus'=>false,'mensaje'=>'Impuesto inconsistente'];
                $factorTipo = 'NoObjeto'; $tasa = 0.0;
            } elseif ($tipoIva === 'exento') {
                if ($objeto !== '02') return ['estatus'=>false,'mensaje'=>'Impuesto inconsistente'];
                $factorTipo = 'Exento'; $tasa = 0.0;
            } elseif ($tipoIva === 'sin_traslado_iva') {
                if ($objeto !== '02' || !$retenIsr) {
                    return ['estatus'=>false,'mensaje'=>'Sin traslado IVA requiere la retención ISR en la partida'];
                }
                $factorTipo = 'SinTraslado'; $tasa = 0.0;
            } elseif (in_array($tipoIva, ['0','0.08','0.16'], true)) {
                if ($objeto !== '02') return ['estatus'=>false,'mensaje'=>'Impuesto inconsistente'];
                $factorTipo = 'Tasa'; $tasa = (float)$tipoIva;
            } else {
                return ['estatus'=>false,'mensaje'=>'Tasa de IVA inválida'];
            }
            $incluyeIva = (int)$item['incluye_iva_cfdi'] === 1;
            $factor = $factorTipo === 'Tasa' ? 1 + $tasa : 1.0;
            $precio = (float)$item['precio'];
            $descuentoComercial = round((float)$item['descuento_producto'] + (float)$item['descuento_general'], 2);
            if ($cambioTipo) {
                $brutoTicket = isset($item['importe_bruto']) && $item['importe_bruto'] !== null
                    ? (float)$item['importe_bruto']
                    : (float)$item['importe'] + $descuentoComercial;
                $unitPrice = round($brutoTicket / $quantity / $factor, 6);
            } else {
                $unitPrice = round($incluyeIva ? $precio / $factor : $precio, 6);
            }
            $subtotal = round($unitPrice * $quantity, 6);

            $descuentoFiscal = round($descuentoComercial / $factor, 6);
            if ($descuentoFiscal < 0 || $descuentoFiscal > $subtotal) {
                return ['estatus'=>false,'mensaje'=>'Descuento fiscal inválido'];
            }
            $base = round($subtotal - $descuentoFiscal, 6);
            $iva = $factorTipo === 'Tasa' ? round($base * $tasa, 6) : 0.0;
            $isr = $retenIsr ? round($base * 0.0125, 6) : 0.0;
            $totalComercialItem = round($base + $iva, 2);
            $totalItem = round($base + $iva - $isr, 2);
            $importeTicket = round((float)$item['importe'], 2);
            $sumaTicketC += (int)round($importeTicket * 100);
            $sumaFiscalC += (int)round($totalComercialItem * 100);
            $sumaCfdiC += (int)round($totalItem * 100);
            $sumaRetencionIsr += $isr;
            $sumaSubtotales += $subtotal;
            $sumaDescuentos += $descuentoFiscal;
            if ($factorTipo === 'Tasa') {
                $key = number_format($tasa, 6, '.', '');
                $impuestosPorTasa[$key] = ($impuestosPorTasa[$key] ?? 0.0) + $iva;
            }
            if ((int)round($totalComercialItem * 100) !== (int)round($importeTicket * 100)) {
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
            if ($retenIsr) {
                $concepto['Taxes'][] = [
                    'Name'=>'ISR', 'Rate'=>0.0125, 'Base'=>$base,
                    'Total'=>$isr, 'IsRetention'=>true, 'IsQuota'=>false
                ];
            }
            $cfdiItems[] = $concepto;
        }
        $ivaResumen = 0.0;
        foreach ($impuestosPorTasa as $importeTasa) {
            $ivaResumen += round($importeTasa, 2);
        }
        $isrResumen = round($sumaRetencionIsr, 2);
        $totalFiscalResumen = round(
            round($sumaSubtotales, 2) - round($sumaDescuentos, 2)
            + round($ivaResumen, 2) - $isrResumen, 2
        );
        if ($sumaTicketC !== (int)round((float)$venta['total'] * 100)
            || $sumaFiscalC !== $sumaTicketC
            || (int)round($totalFiscalResumen * 100) !== $sumaCfdiC) {
            return ['estatus' => false, 'mensaje' =>
                'La suma fiscal de partidas, descuentos e impuestos no coincide con la venta. Revisa redondeos antes de timbrar.'];
        }


        } // fin de compatibilidad con modalidad ISR/IVA anterior

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

        // Los tipos de Egreso, Traslado, Pago y Nómina requieren flujos propios.
        if (($data['tipo_comprobante'] ?? 'I') !== 'I') {
            return ['estatus'=>false,'mensaje'=>'El ticket solo puede originar CFDI tipo I; E/T/P/N requieren módulos independientes'];
        }
        if (($data['moneda'] ?? 'MXN') !== 'MXN') {
            return ['estatus'=>false,'mensaje'=>'La venta de este POS está valuada en MXN; otras monedas requieren tipo de cambio y conciliación'];
        }
        if (!in_array($exportation, ['01','03','04'], true)) {
            return ['estatus'=>false,'mensaje'=>'Exportación 02 requiere complemento de comercio exterior; no habilitada aún'];
        }
        $condiciones=trim((string)($data['condiciones_pago'] ?? ''));
        $orden=trim((string)($data['numero_orden'] ?? ''));
        $observaciones=trim((string)($data['observaciones'] ?? ''));
        if (strlen($condiciones)>2000 || strlen($orden)>200 || strlen($observaciones)>1000) {
            return ['estatus'=>false,'mensaje'=>'Longitud de condiciones, orden u observaciones excedida'];
        }
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
        if ($condiciones !== '') $cfdiPayload['PaymentConditions']=$condiciones;
        if ($orden !== '') $cfdiPayload['OrderNumber']=$orden;
        if ($observaciones !== '') $cfdiPayload['Observations']=$observaciones;
        $deliveryOptions = $this->opcionesEntrega($correo, $ferreteriaInfo);

        return [
            'estatus'=>true, 'payload'=>$cfdiPayload, 'entrega'=>$deliveryOptions,
            'issuer_id'=>$issuerId, 'correo'=>$correo,
            'rfc'=>$rfc, 'razon_social'=>$razon_social,
            'codigo_postal'=>$codigo_postal, 'regimen_fiscal'=>$regimen_fiscal,
            'uso_cfdi'=>$uso_cfdi, 'metodo_pago'=>$paymentMethod,
            'forma_pago'=>$paymentForm,
            'resumen_fiscal'=>[
                'total_venta'=>(float)$venta['total'],
                'retencion_isr'=>$isrResumen,
                'total_cfdi'=>$totalFiscalResumen
            ]
        ];
    }

    /**
     * Emite un sustituto del CFDI vigente, sin cancelar el ticket operativo.
     * IMPORTANTE: solo personal autorizado debe acceder al endpoint que invoque este método.
     */
    public function sustituirFactura(int $id_venta, array $data): array
    {
        $ventaRows=$this->db->select(
            'SELECT * FROM ventas WHERE id=? AND id_ferreteria=? AND id_sucursal=?',
            [$id_venta,$this->id_ferreteria,$this->id_sucursal]
        );
        if (!$ventaRows || (int)$ventaRows[0]['estatus'] !== 1) {
            return ['estatus'=>false,'mensaje'=>'Venta inexistente o cancelada'];
        }
        $venta=$ventaRows[0];
        // En esta primera versión solo se corrige el receptor fiscal:
        // conserva el método y forma de pago originales para no cambiar
        // las condiciones de una operación que ya se cobró.
        if (empty($venta['factura_metodo_pago']) || empty($venta['factura_forma_pago'])) {
            return ['estatus'=>false,'mensaje'=>'Factura anterior sin método/forma de pago; requiere revisión'];
        }
        $data['metodo_pago']=(string)$venta['factura_metodo_pago'];
        $data['forma_pago']=(string)$venta['factura_forma_pago'];
        if ($venta['estatus_factura'] !== 'Facturada' ||
            empty($venta['factura_id']) || empty($venta['uuid_factura']) ||
            (int)$venta['factura_issuer_id']<=0) {
            return ['estatus'=>false,'mensaje'=>'La factura vigente no está disponible para sustitución; revisa su estado'];
        }
        if (!preg_match(self::UUID_RE,(string)$venta['uuid_factura'])) {
            return ['estatus'=>false,'mensaje'=>'UUID original inválido'];
        }
        // Tomar la versión nueva solo como estimación: se revalida bajo bloqueo FOR UPDATE.
        $versionRows=$this->db->select(
            'SELECT COALESCE(MAX(`version`),0) AS ultima FROM cfdi_documentos WHERE id_venta=?',[$id_venta]
        );
        $version=(int)($versionRows[0]['ultima']??0)+1;
        if ($version<2 || $version>999) return ['estatus'=>false,'mensaje'=>'Historial CFDI incompleto o límite de versiones'];
        $folio=substr((string)$venta['folio'],0,65).'-S'.$version;
        $date=new DateTime('now',new DateTimeZone('America/Matamoros'));
        $date->modify('-5 minutes');
        $fecha=$date->format('Y-m-d\TH:i:s');
        $client=new FacturamaClient();
        $preparado=$this->prepararPayloadFactura(
            $id_venta,$venta,$data,$client,$folio,$fecha,(int)$venta['factura_issuer_id']
        );
        if (!$preparado['estatus']) return $preparado;
        $payload=$preparado['payload'];
        $payload['Relations']=[
            'Type'=>'04',
            'Cfdis'=>[['Uuid'=>strtoupper((string)$venta['uuid_factura'])]]
        ];
        $payloadJSON=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $docId=0;
        try {
            $this->db->beginTransaction();
            $locked=$this->db->select(
                'SELECT * FROM ventas WHERE id=? AND id_ferreteria=? AND id_sucursal=? FOR UPDATE',
                [$id_venta,$this->id_ferreteria,$this->id_sucursal]
            );
            if (!$locked || $locked[0]['estatus_factura']!=='Facturada' ||
                $locked[0]['uuid_factura']!==$venta['uuid_factura']) {
                throw new RuntimeException('Otro proceso modificó la factura; actualiza la pantalla');
            }
            $pendiente=$this->db->select(
                "SELECT id FROM cfdi_documentos WHERE id_venta=? AND relacion_tipo='04' AND estado IN ('emitiendo','incierto','pendiente_cancelacion','revision') LIMIT 1",
                [$id_venta]
            );
            if ($pendiente) throw new RuntimeException('Ya hay una sustitución pendiente; revisa antes de timbrar otra');
            $anterior=$this->db->select(
                'SELECT * FROM cfdi_documentos WHERE id_venta=? AND uuid=? AND facturama_id=? FOR UPDATE',
                [$id_venta,strtoupper((string)$venta['uuid_factura']),(string)$venta['factura_id']]
            );
            if (!$anterior || $anterior[0]['estado']!=='emitido') {
                throw new RuntimeException('No existe documento vigente en el historial. Revisa migración/estado SAT');
            }
            $max=$this->db->select('SELECT COALESCE(MAX(`version`),0) AS ultima FROM cfdi_documentos WHERE id_venta=?',[$id_venta]);
            if ((int)$max[0]['ultima']+1!==$version) {
                throw new RuntimeException('Versión de sustitución ocupada; vuelve a abrir el formulario');
            }
            $docId=(int)$this->db->insert('cfdi_documentos',[
                'id_venta'=>$id_venta, 'id_ferreteria'=>$this->id_ferreteria,
                'id_sucursal'=>$this->id_sucursal, 'version'=>$version,
                'tipo_cfdi'=>'I','folio'=>$folio,'fecha_cfdi'=>$fecha,
                'issuer_id'=>(int)$venta['factura_issuer_id'],
                'estado'=>'emitiendo', 'relacion_tipo'=>'04',
                'uuid_relacionado'=>strtoupper((string)$venta['uuid_factura']),
                'documento_sustituido_id'=>(int)$anterior[0]['id'],
                'correo'=>$preparado['correo'], 'payload_json'=>$payloadJSON
            ]);
            if ($docId<=0) throw new RuntimeException('No se reservó el documento');
            $this->db->update('ventas',[
                'estatus_factura'=>'Sustituyendo', 'factura_intent_at'=>date('Y-m-d H:i:s')
            ],'id=? AND id_ferreteria=? AND id_sucursal=?',
              [$id_venta,$this->id_ferreteria,$this->id_sucursal]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['estatus'=>false,'mensaje'=>$e->getMessage()];
        }

        // Fuera de una transacción: Facturama puede tardar o fallar.
        try {
            $respuesta=$client->facturar(
                (string)$this->id_ferreteria,(int)$venta['factura_issuer_id'],
                $payload,$preparado['entrega']
            );
        } catch (Throwable $e) {
            $respuesta=['estatus'=>false,'http_code'=>0,'mensaje'=>'Falla de comunicación con Facturama'];
        }
        if (empty($respuesta['estatus'])) {
            $code=(int)($respuesta['http_code']??0);
            $seguro=($code>=400 && $code<500 && !in_array($code,[408,409,429],true));
            $this->db->update('cfdi_documentos',[
                'estado'=>$seguro?'fallido':'incierto'
            ],'id=? AND id_ferreteria=?',[$docId,$this->id_ferreteria]);
            if ($seguro) {
                $this->db->update('ventas',['estatus_factura'=>'Facturada'],
                    'id=? AND id_ferreteria=? AND estatus_factura=?',
                    [$id_venta,$this->id_ferreteria,'Sustituyendo']);
            }
            return [
                'estatus'=>false,'requiere_revision'=>!$seguro,
                'mensaje'=>$seguro
                    ? ($respuesta['mensaje']??'Facturama rechazó el timbrado; no se generó la sustitución')
                    : 'Resultado de timbrado incierto. NO generes otra factura; utiliza Revisar sustitución.'
            ];
        }
        return $this->completarEmisionSustituta($docId,(array)($respuesta['data']??[]));
    }

    /**
     * Concilia un timeout de timbrado o muestra cancelaciones pendientes.
     * Nunca reenvía automáticamente la petición de emisión.
     */
    public function revisarSustitucion(int $id_venta): array
    {
        $docs=$this->db->select(
            "SELECT * FROM cfdi_documentos WHERE id_venta=? AND id_ferreteria=? AND id_sucursal=? AND relacion_tipo='04' AND estado IN ('emitiendo','incierto','pendiente_cancelacion','revision') ORDER BY version DESC LIMIT 1",
            [$id_venta,$this->id_ferreteria,$this->id_sucursal]
        );
        if (!$docs) return ['estatus'=>false,'mensaje'=>'No existe una sustitución pendiente'];
        $d=$docs[0];
        if ($d['estado']==='pendiente_cancelacion' || $d['estado']==='revision') {
            return ['estatus'=>true,'pendiente'=>true,'documento_id'=>$d['id'],
                'mensaje'=>'La sustituta ya fue emitida, pero falta confirmar la cancelación del CFDI anterior. Verifica estado SAT/Facturama antes de solicitar otra cancelación.'];
        }
        if ($d['estado']==='emitiendo' &&
            time()-strtotime((string)$d['fecha_intento'])<120) {
            return ['estatus'=>false,'mensaje'=>'La emisión todavía podría estar procesándose. No reintentes el timbrado.'];
        }
        $client=new FacturamaClient();
        $resultado=$client->buscarEmitidaPorFolio(
            (string)$this->id_ferreteria,(int)$d['issuer_id'],
            (string)$d['folio'],(string)$d['fecha_cfdi']
        );
        if (empty($resultado['estatus'])) {
            return ['estatus'=>false,'mensaje'=>'No fue posible consultar Facturama. No vuelvas a timbrar todavía.'];
        }
        if (empty($resultado['data'])) {
            return ['estatus'=>false,'requiere_revision'=>true,
                'mensaje'=>'No se encontró el sustituto en la consulta. Antes de reenviar, verifica el portal/UUID y espera consistencia del proveedor.'];
        }
        return $this->completarEmisionSustituta((int)$d['id'],(array)$resultado['data']);
    }

    /** Primero persiste la sustituta; solo después intenta cancelar la anterior. */
    private function completarEmisionSustituta(int $docId, array $respuesta): array
    {
        $idProveedor=(string)($respuesta['Id']??'');
        $uuid=(string)($respuesta['TaxStamp']['Uuid']??'');
        if (!$idProveedor || !preg_match(self::UUID_RE,$uuid)) {
            $this->db->update('cfdi_documentos',['estado'=>'incierto'],
                'id=? AND id_ferreteria=?',[$docId,$this->id_ferreteria]);
            return ['estatus'=>false,'requiere_revision'=>true,
                'mensaje'=>'Facturama respondió sin UUID o ID identificable. No reintentar sin consultar.'];
        }
        try {
            $this->db->beginTransaction();
            $rows=$this->db->select(
                'SELECT * FROM cfdi_documentos WHERE id=? AND id_ferreteria=? AND id_sucursal=? FOR UPDATE',
                [$docId,$this->id_ferreteria,$this->id_sucursal]
            );
            if (!$rows) throw new RuntimeException('Documento sustituto no localizado');
            $d=$rows[0];
            if (!in_array($d['estado'],['emitiendo','incierto'],true)) {
                throw new RuntimeException('El documento ya fue procesado; revisa historial antes de continuar');
            }
            $ventas=$this->db->select(
                'SELECT id,estatus_factura FROM ventas WHERE id=? AND id_ferreteria=? AND id_sucursal=? FOR UPDATE',
                [$d['id_venta'],$this->id_ferreteria,$this->id_sucursal]
            );
            if (!$ventas || $ventas[0]['estatus_factura']!=='Sustituyendo') {
                throw new RuntimeException('Estado de venta incompatible con sustitución');
            }
            $payload=json_decode((string)$d['payload_json'],true,512,JSON_THROW_ON_ERROR);
            $receptor=$payload['Receiver']??[];
            $this->db->update('cfdi_documentos',[
                'estado'=>'pendiente_cancelacion','facturama_id'=>$idProveedor,
                'uuid'=>strtoupper($uuid)
            ],'id=? AND id_ferreteria=?',[$docId,$this->id_ferreteria]);
            $this->db->update('cfdi_documentos',[
                'estado'=>'cancelacion_pendiente','motivo_cancelacion'=>'01',
                'uuid_sustituto'=>strtoupper($uuid)
            ],'id=? AND id_ferreteria=?',[$d['documento_sustituido_id'],$this->id_ferreteria]);
            $this->db->update('ventas',[
                'estatus_factura'=>'Sustitucion pendiente',
                'factura_id'=>$idProveedor, 'uuid_factura'=>strtoupper($uuid),
                'factura_issuer_id'=>(int)$d['issuer_id'],
                'factura_date'=>$d['fecha_cfdi'],
                'fecha_factura'=>$this->fecha->obtenerFechaRegistro(),
                'factura_payload'=>$d['payload_json'],
                'factura_rfc'=>$receptor['Rfc']??null,
                'factura_razon_social'=>$receptor['Name']??null,
                'factura_codigo_postal'=>$receptor['TaxZipCode']??null,
                'factura_regimen_fiscal'=>$receptor['FiscalRegime']??null,
                'factura_uso_cfdi'=>$receptor['CfdiUse']??null,
                'factura_correo'=>$d['correo'],
                'factura_metodo_pago'=>$payload['PaymentMethod']??null,
                'factura_forma_pago'=>$payload['PaymentForm']??null,
                'factura_intent_at'=>null,
                'factura_cancelacion_motivo'=>null,
                'factura_cancelacion_uuid_sustituto'=>null,
                'factura_cancelacion_fecha'=>null
            ],'id=? AND id_ferreteria=? AND id_sucursal=?',
              [$d['id_venta'],$this->id_ferreteria,$this->id_sucursal]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['estatus'=>false,'requiere_revision'=>true,
                'mensaje'=>'La sustituta pudo timbrarse pero no se guardó correctamente. No reintentes: revisa registros.'];
        }
        return $this->solicitarCancelacionAnterior($docId);
    }

    /**
     * Petición independiente de cancelación SAT: motivo 01 + UUID sustituto.
     * Un resultado desconocido NO se interpreta como cancelación confirmada.
     */
    private function solicitarCancelacionAnterior(int $docId): array
    {
        $new=$this->db->select(
            'SELECT * FROM cfdi_documentos WHERE id=? AND id_ferreteria=? AND id_sucursal=?',
            [$docId,$this->id_ferreteria,$this->id_sucursal]
        );
        if (!$new) return ['estatus'=>false,'mensaje'=>'Sustituto no encontrado'];
        $nuevo=$new[0];
        $old=$this->db->select(
            'SELECT * FROM cfdi_documentos WHERE id=? AND id_ferreteria=? AND id_sucursal=?',
            [$nuevo['documento_sustituido_id'],$this->id_ferreteria,$this->id_sucursal]
        );
        if (!$old || empty($nuevo['uuid'])) return ['estatus'=>false,'mensaje'=>'Historial de sustitución incompleto'];
        $anterior=$old[0];
        try {
            $resp=(new FacturamaClient())->cancelarCfdi(
                (string)$this->id_ferreteria,(int)$anterior['issuer_id'],
                (string)$anterior['facturama_id'],'01',(string)$nuevo['uuid']
            );
        } catch (Throwable $e) {
            $resp=['estatus'=>false,'mensaje'=>'No se recibió confirmación de cancelación SAT'];
        }
        $estadoSat=(string)($resp['sat_status']??'unknown');
        if (!empty($resp['estatus']) && $estadoSat==='canceled') {
            try {
                $this->db->beginTransaction();
                $this->db->update('cfdi_documentos',[
                    'estado'=>'cancelado','sat_status'=>'canceled',
                    'fecha_cancelacion'=>date('Y-m-d H:i:s')
                ],'id=? AND id_ferreteria=?',[$anterior['id'],$this->id_ferreteria]);
                $this->db->update('cfdi_documentos',['estado'=>'emitido'],
                    'id=? AND id_ferreteria=?',[$docId,$this->id_ferreteria]);
                $this->db->update('ventas',['estatus_factura'=>'Facturada'],
                    'id=? AND id_ferreteria=? AND id_sucursal=? AND uuid_factura=?',
                    [$nuevo['id_venta'],$this->id_ferreteria,$this->id_sucursal,$nuevo['uuid']]);
                $this->db->commit();
            } catch (Throwable $e) {
                $this->db->rollBack();
                return ['estatus'=>false,'requiere_revision'=>true,'mensaje'=>'SAT confirmó cancelación, pero no se pudo sincronizar la BD.'];
            }
            return ['estatus'=>true,'pendiente'=>false,'mensaje'=>'Factura sustituida y original cancelada correctamente','uuid'=>$nuevo['uuid']];
        }
        // pending/active/timeout/error -> NO cerrar sustitución.
        $this->db->update('cfdi_documentos',[
            'sat_status'=>$estadoSat,
            'estado'=>'pendiente_cancelacion'
        ],'id=? AND id_ferreteria=?',[$docId,$this->id_ferreteria]);
        return ['estatus'=>true,'pendiente'=>true,'uuid'=>$nuevo['uuid'],
            'mensaje'=>'La factura sustituta ya se timbró. La cancelación del CFDI original sigue pendiente o sin confirmar; revisa su estado SAT.'];
    }

    /** Marca la venta como Facturada y envía documentos si hay correo. */
    private function finalizarFactura(int $id_venta, string $folio, array $providerData, string $correo): array
    {
        $taxStamp  = $providerData['TaxStamp'] ?? [];
        $facturaId = (string)($providerData['Id'] ?? '');
        $uuid = is_array($taxStamp) ? (string)($taxStamp['Uuid'] ?? '') : '';
        if (!$facturaId || !preg_match(self::UUID_RE, $uuid)) {
            return ['estatus'=>false, 'mensaje'=>'Facturama no devolvió ID y UUID verificables; revisar emisión antes de reintentar'];
        }
        try {
            $this->db->beginTransaction();
            $vs = $this->db->select(
                'SELECT * FROM ventas WHERE id=? AND id_ferreteria=? AND id_sucursal=? FOR UPDATE',
                [$id_venta,$this->id_ferreteria,$this->id_sucursal]
            );
            if (!$vs) throw new RuntimeException('Venta no localizada');
            $v=$vs[0];
            $exist=$this->db->select('SELECT id FROM cfdi_documentos WHERE id_venta=? AND `version`=1',[$id_venta]);
            if (!$exist) {
                $this->db->insert('cfdi_documentos', [
                    'id_venta'=>$id_venta, 'id_ferreteria'=>$this->id_ferreteria,
                    'id_sucursal'=>$this->id_sucursal, 'version'=>1, 'tipo_cfdi'=>'I',
                    'folio'=>$folio, 'fecha_cfdi'=>$v['factura_date'],
                    'issuer_id'=>(int)$v['factura_issuer_id'],
                    'facturama_id'=>$facturaId, 'uuid'=>strtoupper($uuid),
                    'estado'=>'emitido', 'correo'=>$correo,
                    'payload_json'=>$v['factura_payload'] ?? null
                ]);
            }
            $this->db->update('ventas', [
                'estatus_factura'=>'Facturada', 'factura_id'=>$facturaId,
                'uuid_factura'=>strtoupper($uuid),
                'fecha_factura'=>$this->fecha->obtenerFechaRegistro()
            ], 'id=? AND id_ferreteria=? AND id_sucursal=?',
                [$id_venta,$this->id_ferreteria,$this->id_sucursal]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['estatus'=>false, 'mensaje'=>'Factura posiblemente timbrada: fallo al guardar histórico CFDI; NO reintentar emisión automáticamente'];
        }

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

    /** Descarga una versión concreta; nunca se basa en ventas.factura_id. */
    public function descargarDocumento(int $idDocumento, string $formato): array
    {
        if (!in_array($formato,['pdf','xml'],true)) {
            return ['estatus'=>false,'mensaje'=>'Formato inválido'];
        }
        $docs=$this->db->select(
            'SELECT facturama_id,issuer_id,estado FROM cfdi_documentos
             WHERE id=? AND id_ferreteria=? AND id_sucursal=?',
            [$idDocumento,$this->id_ferreteria,$this->id_sucursal]
        );
        if (!$docs || empty($docs[0]['facturama_id'])) {
            return ['estatus'=>false,'mensaje'=>'Documento no disponible'];
        }
        return (new FacturamaClient())->descargarCfdi(
            (string)$this->id_ferreteria,
            (int)$docs[0]['issuer_id'],
            (string)$docs[0]['facturama_id'],
            $formato
        );
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
        if (empty($rows) || !in_array($rows[0]['estatus_factura'], ['Facturada','Sustitucion pendiente'], true)) {
            return ['estatus' => false, 'mensaje' => 'La venta no tiene una factura descargable'];
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
        return ['estatus'=>false,'mensaje'=>'Para el motivo 01 utiliza Corregir / Sustituir CFDI. Pikero debe emitir el nuevo primero y relacionarlo automáticamente.'];
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
        return ['estatus' => false, 'mensaje' => 'La venta no está en estado Facturada; termina o revisa primero la sustitución en curso'];
    }
    $pendiente = $this->db->select(
      "SELECT id FROM cfdi_documentos WHERE id_venta=? AND relacion_tipo='04' AND estado IN ('emitiendo','incierto','pendiente_cancelacion','revision') LIMIT 1",
      [$id_venta]
    );
    if ($pendiente) return ['estatus'=>false,'mensaje'=>'Existe una sustitución pendiente; usa el flujo de sustitución'];
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

    if (in_array($nuevoEstatus, ['Cancelada','Cancelacion pendiente'], true)) {
        $this->db->update('cfdi_documentos', [
            'estado'=>$nuevoEstatus==='Cancelada' ? 'cancelado' : 'cancelacion_pendiente',
            'motivo_cancelacion'=>$motivo,
            'uuid_sustituto'=>$uuidSustituto ?: null,
            'sat_status'=>$resp['sat_status'] ?? null,
            'fecha_cancelacion'=>$nuevoEstatus==='Cancelada' ? date('Y-m-d H:i:s') : null
        ], 'id_venta=? AND id_ferreteria=? AND id_sucursal=? AND facturama_id=?',
            [$id_venta,$this->id_ferreteria,$this->id_sucursal,$venta['factura_id']]);
    }
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
        if (!in_array($estatusFactura, ['Pendiente','Cancelada','En proceso'], true)) {
            return ['estatus'=>false,'mensaje'=>'Primero debe concluir la operación fiscal del ticket antes de cancelarlo'];
        }

        if (in_array($estatusFactura,['Sustituyendo','Sustitucion pendiente'],true)) {
            return ['estatus'=>false,'mensaje'=>'No puedes cancelar el ticket con una sustitución CFDI en curso'];
        }


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
