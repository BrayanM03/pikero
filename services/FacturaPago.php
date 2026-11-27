<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../utils/Tenant.php';
require_once __DIR__ . '/FacturamaClient.php';

/**
 * Complemento de recepción de pagos 2.0 (CFDI tipo P) para facturas PPD.
 *
 * Reglas que aplica el servidor (el cliente JS NUNCA manda parcialidad ni saldos):
 *  - Solo facturas vigentes (Facturada) con método de pago PPD.
 *  - Un complemento por abono (abonos_venta). El abono queda ligado en cfdi_pagos.
 *  - Saldo = total del CFDI (suma de Items[].Total, ya neto de retenciones) - REP emitidos.
 *  - Idempotencia igual que facturar(): Folio + Date fijos y verificación previa
 *    con buscarEmitidaPorFolio() antes de reintentar un intento incierto.
 */
class FacturaPago
{
    private const UUID_RE = '/^[a-fA-F0-9]{8}-(?:[a-fA-F0-9]{4}-){3}[a-fA-F0-9]{12}$/';
    // Estados que "consumen" el abono / bloquean nuevas emisiones.
    private const ESTADOS_ACTIVOS = ['emitido', 'emitiendo', 'incierto'];

    private Database $db;
    private ?int $id_ferreteria;
    private ?int $id_sucursal;

    public function __construct(?int $id_ferreteria = null, ?int $id_sucursal = null)
    {
        $this->db = new Database();
        $this->id_ferreteria = $id_ferreteria ?? Tenant::idFerreteria();
        $this->id_sucursal = $id_sucursal ?? (Tenant::idSucursal() ?? 1);
    }

    // ------------------------------------------------------------------
    // Consulta para el formulario
    // ------------------------------------------------------------------
    public function datosPago(int $id_venta): array
    {
        $f = $this->facturaVigente($id_venta);
        if (!$f['estatus']) return $f;

        $tot = $this->totalesFactura($f['payload']);
        if (!$tot['estatus']) return $tot;

        $emitidos = $this->db->select(
            "SELECT uuid, fecha_pago, monto, parcialidad FROM cfdi_pagos
             WHERE id_cfdi_documento=? AND estado IN ('emitido','cancelacion_pendiente') ORDER BY parcialidad ASC, id ASC",
            [$f['doc']['id']]
        );
        $pagado = 0;
        foreach ($emitidos as $e) $pagado += $this->cents($e['monto']);
        $saldo = ($tot['total_cents'] - $pagado) / 100;

        $enProceso = $this->db->select(
            "SELECT id, id_abono_venta, estado FROM cfdi_pagos
             WHERE id_cfdi_documento=? AND estado IN ('emitiendo','incierto')",
            [$f['doc']['id']]
        );

        $abonos = $this->db->select(
            "SELECT a.id, a.monto, a.fecha_registro, fp.nombre AS forma_pago
             FROM abonos_venta a
             LEFT JOIN formas_pago fp ON fp.id = a.id_forma_pago
             WHERE a.id_venta=? AND a.id_ferreteria=? AND a.id_sucursal=?
               AND a.id NOT IN (
                   SELECT id_abono_venta FROM cfdi_pagos
                   WHERE id_venta=? AND id_abono_venta IS NOT NULL
                     AND estado IN ('emitido','cancelacion_pendiente','emitiendo','incierto'))
             ORDER BY a.fecha_registro ASC, a.id ASC",
            [$id_venta, $this->id_ferreteria, $this->id_sucursal, $id_venta]
        );
        foreach ($abonos as &$a) {
            $a['forma_pago_sat_sugerida'] = $this->formaSatSugerida((string)($a['forma_pago'] ?? ''));
            $a['limite_rep'] = $this->limiteRep((string)$a['fecha_registro']);
        }
        unset($a);

        return [
            'estatus' => true,
            'data' => [
                'factura' => [
                    'uuid' => $f['doc']['uuid'],
                    'folio' => (string)($f['payload']['Folio'] ?? $f['venta']['folio']),
                    'fecha' => (string)$f['doc']['fecha_cfdi'],
                    'total' => $tot['total_cents'] / 100,
                    'saldo_insoluto' => round($saldo, 2),
                    'parcialidad_siguiente' => count($emitidos) + 1,
                    'correo' => (string)($f['venta']['factura_correo'] ?? ''),
                ],
                'abonos_pendientes' => $abonos,
                'pagos_emitidos' => $emitidos,
                'en_proceso' => $enProceso,
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Emisión
    // ------------------------------------------------------------------
    public function emitirPago(int $id_venta, array $data): array
    {
        $abonoId = (int)($data['abono_id'] ?? 0);
        $forma = trim((string)($data['forma_pago'] ?? ''));
        $monto = round((float)($data['monto'] ?? 0), 2);
        $fechaIn = trim((string)($data['fecha_pago'] ?? ''));
        $operacion = trim((string)($data['num_operacion'] ?? ''));
        $correo = trim((string)($data['correo'] ?? ''));

        if ($id_venta <= 0 || $abonoId <= 0) return $this->err('Selecciona el abono que se va a complementar');
        if (!preg_match('/^[0-9]{2}$/', $forma) || $forma === '99') return $this->err('La forma de pago del complemento no puede ser 99');
        if ($monto <= 0) return $this->err('Monto inválido');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $fechaIn)) return $this->err('Fecha de pago inválida');
        if (strlen($operacion) > 100) return $this->err('El número de operación excede 100 caracteres');
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) return $this->err('El correo no tiene un formato válido');

        $tz = new DateTimeZone('America/Matamoros');
        $emision = new DateTime('now', $tz);
        $emision->modify('-5 minutes'); // mismo margen que facturar()
        $invoiceDate = $emision->format('Y-m-d\TH:i:s');

        $fechaPago = strlen($fechaIn) === 16 ? $fechaIn . ':00' : $fechaIn;
        try { $dtPago = new DateTime($fechaPago, $tz); } catch (Throwable $e) { return $this->err('Fecha de pago inválida'); }
        if ($dtPago > new DateTime('now', $tz)) return $this->err('La fecha de pago no puede ser futura');
        // La fecha de pago no puede ser posterior a la emisión del REP.
        if ($dtPago > $emision) $fechaPago = $invoiceDate;

        try {
            $this->db->beginTransaction();
            $f = $this->facturaVigente($id_venta, true);
            if (!$f['estatus']) { $this->db->rollBack(); return $f; }
            $doc = $f['doc'];
            $venta = $f['venta'];

            $tot = $this->totalesFactura($f['payload']);
            if (!$tot['estatus']) { $this->db->rollBack(); return $tot; }

            if (substr($fechaPago, 0, 10) < substr((string)$doc['fecha_cfdi'], 0, 10)) {
                $this->db->rollBack();
                return $this->err('La fecha de pago no puede ser anterior a la fecha de la factura');
            }

            $abono = $this->db->select(
                'SELECT id, monto FROM abonos_venta WHERE id=? AND id_venta=? AND id_ferreteria=? AND id_sucursal=?',
                [$abonoId, $id_venta, $this->id_ferreteria, $this->id_sucursal]
            );
            if (!$abono) { $this->db->rollBack(); return $this->err('El abono no corresponde a esta venta'); }

            $activos = $this->db->select(
                "SELECT * FROM cfdi_pagos WHERE id_cfdi_documento=? AND estado IN ('emitido','cancelacion_pendiente','emitiendo','incierto')",
                [$doc['id']]
            );
            $previo = null; $pagadoCents = 0; $numEmitidos = 0;
            foreach ($activos as $p) {
                if (in_array($p['estado'], ['emitido', 'cancelacion_pendiente'], true)) {
                    $pagadoCents += $this->cents($p['monto']);
                    $numEmitidos++;
                    if ((int)$p['id_abono_venta'] === $abonoId) { $this->db->rollBack(); return $this->err('Este abono ya tiene complemento de pago'); }
                } else {
                    if ((int)$p['id_abono_venta'] !== $abonoId) {
                        $this->db->rollBack();
                        return $this->err('Hay otro complemento en proceso para esta factura; resuélvelo antes de emitir uno nuevo.');
                    }
                    $previo = $p; // reintento del mismo abono
                }
            }

            // ---- Reintento de un intento previo (misma Folio + Date + payload) ----
            if ($previo) {
                $this->db->commit();
                return $this->reintentar($previo, $venta, $doc);
            }

            $saldoCents = $tot['total_cents'] - $pagadoCents;
            if ($saldoCents <= 0) { $this->db->rollBack(); return $this->err('Esta factura ya no tiene saldo pendiente'); }
            $montoCents = $this->cents($monto);
            if ($montoCents > $saldoCents) {
                $this->db->rollBack();
                return $this->err('El monto excede el saldo pendiente del CFDI (' . number_format($saldoCents / 100, 2) . '). Si la factura lleva retenciones, el saldo fiscal es menor al cobrado.');
            }
            if ($montoCents > $this->cents($abono[0]['monto'])) {
                $this->db->rollBack();
                return $this->err('El monto no puede ser mayor al abono registrado');
            }

            $parcialidad = $numEmitidos + 1;
            $insolutoCents = $saldoCents - $montoCents;
            $totalRows = $this->db->select('SELECT COUNT(*) AS n FROM cfdi_pagos WHERE id_venta=?', [$id_venta]);
            $folio = 'REP-' . $venta['folio'] . '-' . ((int)($totalRows[0]['n'] ?? 0) + 1);

            $impuestos = $this->impuestosDR($tot['grupos'], $montoCents, $tot['total_cents'], $insolutoCents === 0, (int)$doc['id']);

            $orig = $f['payload'];
            $receiver = [
                'Rfc' => $orig['Receiver']['Rfc'],
                'Name' => $orig['Receiver']['Name'],
                'CfdiUse' => 'CP01',
                'FiscalRegime' => $orig['Receiver']['FiscalRegime'],
                'TaxZipCode' => $orig['Receiver']['TaxZipCode'],
            ];
            if ($correo !== '') $receiver['Email'] = $correo;

            $related = [
                'Uuid' => strtoupper((string)$doc['uuid']),
                'Folio' => (string)($orig['Folio'] ?? $venta['folio']),
                'Currency' => 'MXN',
                'PartialityNumber' => $parcialidad,
                'PreviousBalanceAmount' => $saldoCents / 100,
                'AmountPaid' => $montoCents / 100,
                'ImpSaldoInsoluto' => $insolutoCents / 100,
                'TaxObject' => $tot['objeto'],
            ];
            if ($tot['objeto'] === '02' && $impuestos) $related['Taxes'] = $impuestos;

            $payment = [
                'Date' => $fechaPago,
                'PaymentForm' => $forma,
                'Amount' => $montoCents / 100,
                'Currency' => 'MXN',
                'RelatedDocuments' => [$related],
            ];
            if ($operacion !== '') $payment['OperationNumber'] = $operacion;

            // Facturama arma el concepto del CFDI tipo P: NO enviar Items, Currency,
            // PaymentMethod ni PaymentForm a nivel comprobante. El nodo se llama "Complemento".
            $payload = [
                'NameId' => '14', // PDF titulado "Complemento de pago"
                'CfdiType' => 'P',
                'Folio' => $folio,
                'Date' => $invoiceDate,
                'ExpeditionPlace' => $orig['ExpeditionPlace'],
                'Receiver' => $receiver,
                'Complemento' => ['Payments' => [$payment]],
            ];

            $id = $this->db->insert('cfdi_pagos', [
                'id_venta' => $id_venta,
                'id_ferreteria' => $this->id_ferreteria,
                'id_sucursal' => $this->id_sucursal,
                'id_cfdi_documento' => (int)$doc['id'],
                'id_abono_venta' => $abonoId,
                'issuer_id' => (int)$doc['issuer_id'],
                'uuid_factura' => strtoupper((string)$doc['uuid']),
                'parcialidad' => $parcialidad,
                'saldo_anterior' => $saldoCents / 100,
                'monto' => $montoCents / 100,
                'saldo_insoluto' => $insolutoCents / 100,
                'forma_pago_sat' => $forma,
                'fecha_pago' => $fechaPago,
                'num_operacion' => $operacion !== '' ? $operacion : null,
                'folio' => $folio,
                'fecha_cfdi' => $invoiceDate,
                'estado' => 'emitiendo',
                'correo' => $correo !== '' ? $correo : null,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
            $this->db->commit();
        } catch (Throwable $e) {
            try { $this->db->rollBack(); } catch (Throwable $ignored) {}
            return $this->err('No se pudo guardar la solicitud del complemento antes de timbrar.');
        }

        $row = $this->db->select('SELECT * FROM cfdi_pagos WHERE id=?', [(int)$id]);
        return $this->timbrar($row[0], $payload);
    }

    // ------------------------------------------------------------------
    // Timbrado / reintento
    // ------------------------------------------------------------------
    private function timbrar(array $row, array $payload): array
    {
        $client = new FacturamaClient();
        $clientId = (string)$this->id_ferreteria;
        // facturar.php del microservicio solo lee cfdi_payload (ignora delivery):
        // el correo se envía aparte en finalizar().
        $resp = $client->facturar($clientId, (int)$row['issuer_id'], $payload);
        if (!$resp['estatus']) {
            $http = (int)($resp['http_code'] ?? 0);
            if ($http >= 400 && $http < 500 && !in_array($http, [408, 429], true)) {
                $this->db->update('cfdi_pagos', ['estado' => 'rechazado'], 'id=?', [(int)$row['id']]);
                return $resp; // el SAT/PAC rechazó: no se timbró, el abono queda libre
            }
            $this->db->update('cfdi_pagos', ['estado' => 'incierto'], 'id=?', [(int)$row['id']]);
            $resp['mensaje'] = 'No se pudo confirmar la emisión del complemento. Vuelve a intentar: se verificará antes de reenviar.';
            return $resp;
        }
        return $this->finalizar($row, $resp['data']);
    }

    private function reintentar(array $row, array $venta, array $doc): array
    {
        $edad = time() - strtotime((string)$row['fecha_actualizacion']);
        if ($row['estado'] === 'emitiendo' && $edad < 120) {
            return $this->err('El complemento ya se está procesando. Espera unos segundos.');
        }
        $client = new FacturamaClient();
        $existente = $client->buscarEmitidaPorFolio((string)$this->id_ferreteria, (int)$row['issuer_id'], (string)$row['folio'], (string)$row['fecha_cfdi']);
        if (!$existente['estatus']) {
            return $this->err('No se pudo verificar si el complemento ya fue emitido; intenta de nuevo en unos minutos.');
        }
        if (!empty($existente['data'])) return $this->finalizar($row, $existente['data']);

        $payload = json_decode((string)$row['payload_json'], true);
        if (!is_array($payload) || !isset($payload['Complemento'])) {
            return $this->err('Intento anterior sin payload íntegro; revisar emisión manualmente');
        }
        $this->db->update('cfdi_pagos', ['estado' => 'emitiendo'], 'id=?', [(int)$row['id']]);
        return $this->timbrar($row, $payload);
    }

    private function finalizar(array $row, array $providerData): array
    {
        $taxStamp = $providerData['TaxStamp'] ?? [];
        $facturamaId = (string)($providerData['Id'] ?? '');
        $uuid = is_array($taxStamp) ? (string)($taxStamp['Uuid'] ?? '') : '';
        if (!$facturamaId || !preg_match(self::UUID_RE, $uuid)) {
            return $this->err('Facturama no devolvió ID y UUID verificables; revisar el complemento antes de reintentar');
        }
        try {
            $this->db->update('cfdi_pagos', [
                'estado' => 'emitido',
                'facturama_id' => $facturamaId,
                'uuid' => strtoupper($uuid),
            ], 'id=?', [(int)$row['id']]);
        } catch (Throwable $e) {
            return $this->err('Complemento posiblemente timbrado: fallo al guardarlo; NO reintentar automáticamente');
        }
        $correoEnviado = null;
        if (!empty($row['correo'])) {
            $mail = (new FacturamaClient())->enviarCfdiPorCorreo(
                (string)$this->id_ferreteria, (int)$row['issuer_id'], $facturamaId,
                (string)$row['correo'], null, 'Complemento de pago'
            );
            $correoEnviado = !empty($mail['estatus']);
        }
        return [
            'estatus' => true,
            'mensaje' => 'Complemento de pago emitido',
            'correo_enviado' => $correoEnviado,
            'uuid' => strtoupper($uuid),
            'parcialidad' => (int)$row['parcialidad'],
            'saldo_insoluto' => (float)$row['saldo_insoluto'],
        ];
    }

    // ------------------------------------------------------------------
    // Listado, descarga, envío y cancelación de complementos ya emitidos
    // ------------------------------------------------------------------
    public function listarPagos(int $id_venta): array
    {
        // Resuelve cancelaciones pendientes antes de calcular saldos (normalmente no hay ninguna).
        $pend = $this->db->select(
            "SELECT id FROM cfdi_pagos WHERE id_venta=? AND id_ferreteria=? AND id_sucursal=? AND estado='cancelacion_pendiente' LIMIT 5",
            [$id_venta, $this->id_ferreteria, $this->id_sucursal]
        );
        foreach ($pend as $x) {
            try { $this->actualizarEstatusPago((int)$x['id']); } catch (Throwable $e) { /* no bloquea el listado */ }
        }

        $d = $this->datosPago($id_venta);
        if (!$d['estatus']) return $d;

        $rows = $this->db->select(
            "SELECT id, parcialidad, monto, saldo_insoluto, forma_pago_sat, fecha_pago, folio, uuid,
                    estado, sat_status, correo, fecha_cancelacion
             FROM cfdi_pagos
             WHERE id_venta=? AND id_ferreteria=? AND id_sucursal=? AND estado <> 'rechazado'
             ORDER BY id ASC",
            [$id_venta, $this->id_ferreteria, $this->id_sucursal]
        );
        $maxVigente = 0;
        foreach ($rows as $r) {
            if (in_array($r['estado'], ['emitido', 'cancelacion_pendiente'], true)) {
                $maxVigente = max($maxVigente, (int)$r['parcialidad']);
            }
        }
        foreach ($rows as &$r) {
            // Solo el último complemento vigente puede cancelarse (mantiene consecutivas las parcialidades).
            $r['puede_cancelar'] = $r['estado'] === 'emitido' && (int)$r['parcialidad'] === $maxVigente;
            $r['monto'] = (float)$r['monto'];
            $r['saldo_insoluto'] = (float)$r['saldo_insoluto'];
        }
        unset($r);

        $d['data']['pagos'] = $rows;
        $d['data']['hay_abonos_pendientes'] = count($d['data']['abonos_pendientes']) > 0;
        return $d;
    }

    public function descargarPago(int $id_pago, string $formato): array
    {
        if (!in_array($formato, ['pdf', 'xml'], true)) return $this->err('Formato de descarga no válido');
        $p = $this->pagoPropio($id_pago);
        if (!$p || empty($p['facturama_id']) || empty($p['uuid'])) return $this->err('El complemento no tiene un archivo descargable');

        $r = (new FacturamaClient())->descargarCfdi((string)$this->id_ferreteria, (int)$p['issuer_id'], (string)$p['facturama_id'], $formato);
        if (!empty($r['estatus'])) {
            $r['filename'] = 'complemento_pago_' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$p['folio']) . '.' . $formato;
        }
        return $r;
    }

    public function enviarPago(int $id_pago, string $correo, string $asunto, string $comentarios): array
    {
        $p = $this->pagoPropio($id_pago);
        if (!$p || !in_array($p['estado'], ['emitido', 'cancelacion_pendiente'], true) || empty($p['facturama_id'])) {
            return $this->err('El complemento no está disponible para enviar');
        }
        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) return $this->err('Indica un correo de destino válido');

        $f = $this->db->select('SELECT correo FROM ferreterias WHERE id=?', [$this->id_ferreteria]);
        $emisorCorreo = trim((string)($f[0]['correo'] ?? ''));

        $r = (new FacturamaClient())->enviarCfdiPorCorreo(
            (string)$this->id_ferreteria, (int)$p['issuer_id'], (string)$p['facturama_id'],
            $correo, $emisorCorreo !== '' ? $emisorCorreo : null,
            $asunto !== '' ? $asunto : 'Complemento de pago', $comentarios
        );
        if (!empty($r['estatus'])) $r['correo'] = $correo;
        return $r;
    }

    /** Motivos permitidos para un REP: 02 (con errores sin relación) y 03 (no se llevó a cabo la operación). */
    public function cancelarPago(int $id_pago, string $motivo): array
    {
        if (!in_array($motivo, ['02', '03'], true)) return $this->err('Motivo de cancelación inválido');
        $p = $this->pagoPropio($id_pago);
        if (!$p) return $this->err('Complemento no encontrado');
        if ($p['estado'] === 'cancelado') return $this->err('El complemento ya está cancelado');
        if ($p['estado'] === 'cancelacion_pendiente') return $this->err('La cancelación ya fue solicitada y está en proceso');
        if ($p['estado'] !== 'emitido' || empty($p['facturama_id'])) return $this->err('El complemento no está vigente');

        $posterior = $this->db->select(
            "SELECT parcialidad FROM cfdi_pagos
             WHERE id_cfdi_documento=? AND parcialidad>? AND estado IN ('emitido','cancelacion_pendiente','emitiendo','incierto')
             ORDER BY parcialidad DESC LIMIT 1",
            [$p['id_cfdi_documento'], $p['parcialidad']]
        );
        if ($posterior) {
            return $this->err('Cancela primero el complemento de la parcialidad ' . (int)$posterior[0]['parcialidad'] . ' (solo puede cancelarse el más reciente).');
        }

        $resp = (new FacturamaClient())->cancelarCfdi((string)$this->id_ferreteria, (int)$p['issuer_id'], (string)$p['facturama_id'], $motivo, '');
        if (!$resp['estatus']) return $resp;

        $sat = $resp['sat_status'] ?? 'unknown';
        if ($sat === 'canceled') $nuevo = 'cancelado';
        elseif ($sat === 'pending') $nuevo = 'cancelacion_pendiente';
        else return $this->err('El complemento no pudo cancelarse (estatus SAT: ' . $sat . '). Verifica en Facturama.');

        $this->db->update('cfdi_pagos', [
            'estado' => $nuevo,
            'motivo_cancelacion' => $motivo,
            'sat_status' => $sat,
            'fecha_cancelacion' => date('Y-m-d H:i:s'), // fecha de solicitud (o de cancelación efectiva)
        ], 'id=? AND id_ferreteria=? AND estado=?', [(int)$p['id'], $this->id_ferreteria, 'emitido']);

        return ['estatus' => true, 'sat_status' => $sat, 'mensaje' => $resp['mensaje'] ?? 'Solicitud procesada'];
    }

    /**
     * Sincroniza con Facturama/SAT el estado de un complemento (cancelación pendiente o cancelado por fuera).
     * Facturama solo reporta active/canceled al consultar un CFDI:
     *  - canceled  => estado 'cancelado' (libera abono y recalcula saldo).
     *  - pending/rejected => según lo que informe el microservicio.
     *  - active    => si había solicitud y pasaron más de 96 h (72 h del receptor + margen),
     *                 se asume rechazada y el complemento vuelve a 'emitido'.
     */
    public function actualizarEstatusPago(int $id_pago): array
    {
        $p = $this->pagoPropio($id_pago);
        if (!$p) return $this->err('Complemento no encontrado');
        if (!in_array($p['estado'], ['emitido', 'cancelacion_pendiente'], true) || empty($p['facturama_id'])) {
            return $this->err('El complemento no tiene un estatus que consultar');
        }

        $r = (new FacturamaClient())->consultarEstatusCfdi((string)$this->id_ferreteria, (int)$p['issuer_id'], (string)$p['facturama_id']);
        if (!$r['estatus']) return $r;

        $sat = $r['sat_status'];
        $estadoActual = $p['estado'];
        $nuevo = $estadoActual;
        $mensaje = '';

        if ($sat === 'canceled') {
            $nuevo = 'cancelado';
            $mensaje = 'El complemento está cancelado ante el SAT.';
        } elseif ($sat === 'pending') {
            $nuevo = 'cancelacion_pendiente';
            $mensaje = 'La cancelación sigue pendiente de respuesta del receptor.';
        } elseif ($sat === 'rejected') {
            $nuevo = 'emitido';
            $mensaje = 'El receptor rechazó la cancelación; el complemento sigue vigente.';
        } elseif ($sat === 'active') {
            if ($estadoActual === 'cancelacion_pendiente') {
                $horas = $p['fecha_cancelacion'] ? (time() - strtotime((string)$p['fecha_cancelacion'])) / 3600 : 0;
                if ($horas > 96) {
                    $nuevo = 'emitido';
                    $sat = 'rejected';
                    $mensaje = 'Pasó el plazo de respuesta y el complemento sigue vigente: la cancelación no procedió. Puedes volver a solicitarla.';
                } else {
                    $mensaje = 'Sigue vigente: el receptor tiene hasta 72 horas para responder la cancelación.';
                }
            } else {
                $mensaje = 'El complemento sigue vigente.';
            }
        } else {
            return $this->err('Facturama no devolvió un estatus reconocible; intenta más tarde.');
        }

        $cambio = $nuevo !== $estadoActual;
        if ($cambio) {
            $this->db->update('cfdi_pagos', [
                'estado' => $nuevo,
                'sat_status' => $sat,
                'fecha_cancelacion' => $nuevo === 'cancelado' ? date('Y-m-d H:i:s') : ($nuevo === 'emitido' ? null : ($p['fecha_cancelacion'] ?: date('Y-m-d H:i:s'))),
            ], 'id=? AND id_ferreteria=? AND estado=?', [(int)$p['id'], $this->id_ferreteria, $estadoActual]);
        }
        return ['estatus' => true, 'estado' => $nuevo, 'sat_status' => $sat, 'cambio' => $cambio, 'mensaje' => $mensaje];
    }

    private function pagoPropio(int $id_pago): ?array
    {
        $r = $this->db->select(
            'SELECT * FROM cfdi_pagos WHERE id=? AND id_ferreteria=? AND id_sucursal=?',
            [$id_pago, $this->id_ferreteria, $this->id_sucursal]
        );
        return $r[0] ?? null;
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------
    private function facturaVigente(int $id_venta, bool $lock = false): array
    {
        $rows = $this->db->select(
            'SELECT id, folio, estatus, estatus_factura, uuid_factura, factura_issuer_id,
                    factura_metodo_pago, factura_correo
             FROM ventas WHERE id=? AND id_ferreteria=? AND id_sucursal=?' . ($lock ? ' FOR UPDATE' : ''),
            [$id_venta, $this->id_ferreteria, $this->id_sucursal]
        );
        if (!$rows) return $this->err('Venta no encontrada');
        $v = $rows[0];
        if (($v['estatus_factura'] ?? '') !== 'Facturada' || empty($v['uuid_factura'])) {
            return $this->err('La venta no tiene una factura vigente');
        }
        if (strtoupper((string)$v['factura_metodo_pago']) !== 'PPD') {
            return $this->err('El complemento de pago solo aplica a facturas con método PPD');
        }
        $docs = $this->db->select(
            'SELECT id, uuid, fecha_cfdi, issuer_id, payload_json FROM cfdi_documentos
             WHERE id_venta=? AND id_ferreteria=? AND uuid=? LIMIT 1',
            [$id_venta, $this->id_ferreteria, strtoupper((string)$v['uuid_factura'])]
        );
        if (!$docs) return $this->err('No se encontró el CFDI vigente en el histórico');
        $payload = json_decode((string)$docs[0]['payload_json'], true);
        if (!is_array($payload) || !is_array($payload['Items'] ?? null) || !isset($payload['Receiver'], $payload['ExpeditionPlace'])) {
            return $this->err('El CFDI vigente no tiene un payload íntegro');
        }
        return ['estatus' => true, 'venta' => $v, 'doc' => $docs[0], 'payload' => $payload];
    }

    /** Total del CFDI (neto de retenciones), grupos de impuestos y ObjetoImpDR. */
    private function totalesFactura(array $payload): array
    {
        $total = 0; $grupos = []; $hay02 = false;
        foreach ($payload['Items'] as $it) {
            $total += $this->cents($it['Total'] ?? 0);
            if (($it['TaxObject'] ?? '') === '02') $hay02 = true;
            foreach (($it['Taxes'] ?? []) as $t) {
                if (!empty($t['IsQuota']) || !isset($t['Rate'])) {
                    return $this->err('La factura incluye IEPS de cuota o IVA exento; el complemento de pago para esos casos aún no está habilitado.');
                }
                $k = $this->keyImpuesto($t);
                if (!isset($grupos[$k])) {
                    $grupos[$k] = ['Name' => $this->nombreImpuestoDR($t['Name']), 'Rate' => (float)$t['Rate'], 'IsRetention' => !empty($t['IsRetention']), 'base' => 0, 'total' => 0];
                }
                $grupos[$k]['base'] += $this->cents($t['Base'] ?? 0);
                $grupos[$k]['total'] += $this->cents($t['Total'] ?? 0);
            }
        }
        if ($total <= 0) return $this->err('El total del CFDI vigente no es válido');
        return ['estatus' => true, 'total_cents' => $total, 'grupos' => $grupos, 'objeto' => $hay02 ? '02' : '01'];
    }

    /** ImpuestosDR proporcionales; el último pago absorbe el redondeo. */
    private function impuestosDR(array $grupos, int $montoCents, int $totalCents, bool $ultimo, int $docId): array
    {
        $prev = [];
        if ($ultimo) {
            $rows = $this->db->select("SELECT payload_json FROM cfdi_pagos WHERE id_cfdi_documento=? AND estado IN ('emitido','cancelacion_pendiente')", [$docId]);
            foreach ($rows as $r) {
                $p = json_decode((string)$r['payload_json'], true);
                foreach ((($p['Complemento'] ?? $p['Complement'] ?? [])['Payments'][0]['RelatedDocuments'][0]['Taxes'] ?? []) as $t) {
                    $k = $this->keyImpuesto($t);
                    $prev[$k]['base'] = ($prev[$k]['base'] ?? 0) + $this->cents($t['Base']);
                    $prev[$k]['total'] = ($prev[$k]['total'] ?? 0) + $this->cents($t['Total']);
                }
            }
        }
        $out = [];
        foreach ($grupos as $k => $g) {
            if ($ultimo) {
                $base = $g['base'] - ($prev[$k]['base'] ?? 0);
                $imp = $g['total'] - ($prev[$k]['total'] ?? 0);
            } else {
                $base = (int)round($g['base'] * $montoCents / $totalCents);
                $imp = (int)round($base * $g['Rate']);
            }
            if ($base <= 0) continue;
            $out[] = [
                'Name' => $g['Name'],
                'Rate' => $g['Rate'],
                'Total' => $imp / 100,
                'Base' => $base / 100,
                'IsRetention' => $g['IsRetention'],
            ];
        }
        return $out;
    }

    private function keyImpuesto(array $t): string
    {
        return $this->nombreImpuestoDR($t['Name']) . '|' . (!empty($t['IsRetention']) ? '1' : '0') . '|' . number_format((float)$t['Rate'], 6, '.', '');
    }

    /** En ImpuestosDR la retención de IVA se expresa como IVA con IsRetention=true. */
    private function nombreImpuestoDR(string $name): string
    {
        return $name === 'IVA RET' ? 'IVA' : $name;
    }

    private function formaSatSugerida(string $nombre): string
    {
        $n = mb_strtolower(trim($nombre));
        if (strpos($n, 'efectivo') !== false) return '01';
        if (strpos($n, 'transfer') !== false) return '03';
        if (strpos($n, 'tarjeta') !== false) return '04';
        if (strpos($n, 'cheque') !== false) return '02';
        return '';
    }

    /** Día 10 del mes siguiente al pago. */
    private function limiteRep(string $fecha): string
    {
        try {
            $d = new DateTime($fecha);
            $d->modify('first day of next month');
            $d->setDate((int)$d->format('Y'), (int)$d->format('m'), 10);
            return $d->format('Y-m-d');
        } catch (Throwable $e) {
            return '';
        }
    }

    private function cents($n): int { return (int)round(((float)$n) * 100); }
    private function err(string $m): array { return ['estatus' => false, 'mensaje' => $m]; }
}