<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/dates.php';
require_once __DIR__ . '/../models/Datatable.php';
require_once __DIR__ . '/../utils/Tenant.php';
require_once __DIR__ . '/../services/FacturamaClient.php';
require_once __DIR__ . '/../services/InvoiceMailer.php';

class Venta extends Datatable {
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

    public function contar($id_filtro): int {
        return (int)$this->db->count(
            'ventas',
            'id_ferreteria = ? AND id_sucursal = ?',
            [$this->id_ferreteria, $this->id_sucursal]
        );
    }

    public function contarFiltrados($id_filtro, $search): int {
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

    public function detalle_old(int $id_venta): array {
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
             WHERE v.id = ? AND v.id_ferreteria = ?",
            [$id_venta, $this->id_ferreteria]
        );

        if (empty($venta)) {
            return ['estatus' => false, 'mensaje' => 'Venta no encontrada', 'data' => []];
        }

        $items = $this->db->select(
            "SELECT dv.*, p.nombre AS producto, p.codigo, p.clave_sat, p.codigo_unidad_sat, p.objeto_impuesto, u.abreviatura AS unidad
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

    public function detalle(int $id_venta): array {
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
         WHERE v.id = ? AND v.id_ferreteria = ?",
        [$id_venta, $this->id_ferreteria]
    );

    if (empty($venta)) {
        return ['estatus' => false, 'mensaje' => 'Venta no encontrada', 'data' => []];
    }

    $items = $this->db->select(
        "SELECT dv.*, p.nombre AS producto, p.codigo, p.clave_sat, p.codigo_unidad_sat, p.objeto_impuesto, u.abreviatura AS unidad
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
 
    public function registrarAbono(int $id_venta, array $data): array {
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
 
public function facturar(int $id_venta, array $data): array {
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
    $rfc            = strtoupper(trim((string)($data['rfc'] ?? '')));
    $razon_social   = trim((string)($data['razon_social'] ?? ''));
    $codigo_postal  = trim((string)($data['codigo_postal'] ?? ''));
    $regimen_fiscal = trim((string)($data['regimen_fiscal'] ?? ''));
    $uso_cfdi       = trim((string)($data['uso_cfdi'] ?? ''));
    if (!preg_match('/^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/', $rfc)) {
        return ['estatus' => false, 'mensaje' => 'El RFC no tiene un formato válido'];
    }
    if ($razon_social === '' || !preg_match('/^[0-9]{5}$/', $codigo_postal) || $regimen_fiscal === '' || $uso_cfdi === '') {
        return ['estatus' => false, 'mensaje' => 'Completa los datos fiscales obligatorios'];
    }
    if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return ['estatus' => false, 'mensaje' => 'El correo no tiene un formato válido'];
    }
    $rateInput = (string)($data['tasa_iva'] ?? '0.16');
    if (!in_array($rateInput, ['0', '0.08', '0.16'], true)) {
        return ['estatus' => false, 'mensaje' => 'La tasa de IVA seleccionada no es válida'];
    }
    $taxRate = (float)$rateInput;
 
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
 
    // 4) Conceptos
    $items = $this->db->select(
        'SELECT p.nombre, p.codigo, p.clave_sat, p.codigo_unidad_sat, p.objeto_impuesto, dv.cantidad, dv.precio, dv.importe, u.abreviatura AS unidad
         FROM detalle_ventas dv
         INNER JOIN productos p ON p.id = dv.id_producto AND p.id_ferreteria = ?
         LEFT JOIN unidades u ON u.id = p.id_unidad
         WHERE dv.id_venta = ?',
        [$this->id_ferreteria, $id_venta]
    );
    if (empty($items)) return ['estatus' => false, 'mensaje' => 'La venta no tiene artículos válidos para facturar.'];
 
    $cfdiItems = [];
    foreach ($items as $item) {
        $quantity = (float)$item['cantidad'];
        if ($quantity <= 0) continue;
        $taxObject = (string)($item['objeto_impuesto'] ?: '02');
        if (!in_array($taxObject, ['01', '02', '03'], true)) $taxObject = '02';
        $grossUnitPrice = (float)$item['precio'];
        $taxable   = $taxObject === '02';
        $totalItem = round((float)$item['importe'], 2);
        if ($taxable && $taxRate > 0) {
            // 1. Obtener precio unitario sin IVA y subtotal
            $unitPrice = round($grossUnitPrice / (1 + $taxRate), 6);
            $subtotal  = round($unitPrice * $quantity, 2);
            
            // 2. El IVA es la diferencia exacta para forzar el cuadre con $totalItem
            $iva = round($totalItem - $subtotal, 2);
            } else {
                $unitPrice = $grossUnitPrice;
                $subtotal  = $totalItem;
                $iva       = 0.00;
            }
       /*  if ($taxable && $taxRate > 0) {
            $expectedTotal = round($grossUnitPrice * $quantity, 2);
            if (abs($totalItem - $expectedTotal) > 0.01) {
                return ['estatus' => false, 'mensaje' => 'El importe de un artículo no coincide con la cantidad y precio unitario.'];
            }
        }
        $unitPrice = round($taxable && $taxRate > 0 ? $grossUnitPrice / (1 + $taxRate) : $grossUnitPrice, 6);
        $subtotal  = round($unitPrice * $quantity, 2); */
        $cfdiItem = [
            'ProductCode'          => trim((string)$item['clave_sat']) ?: '01010101',
            'IdentificationNumber' => (string)($item['codigo'] ?? ''),
            'Description'          => (string)$item['nombre'],
            'Unit'                 => (string)($item['unidad'] ?: 'Pieza'),
            'UnitCode'             => trim((string)$item['codigo_unidad_sat']) ?: 'H87',
            'Quantity'             => $quantity,
            'UnitPrice'            => $unitPrice,
            'Subtotal'             => $subtotal,
            'TaxObject'            => $taxObject,
            'Total'                => $totalItem
        ];
        if ($taxable) {
            $cfdiItem['Taxes'] = [[
                'Name' => 'IVA', 
                'Rate' => $taxRate,
                'Total' => $iva,
                'Base' => $subtotal, 
                'IsRetention' => false
            ]];
        }
        $cfdiItems[] = $cfdiItem;
    }
    if (empty($cfdiItems)) return ['estatus' => false, 'mensaje' => 'La venta no tiene artículos válidos para facturar.'];
 
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
        if (!empty($issuer[$postalField])) { $issuerPostalCode = trim((string)$issuer[$postalField]); break; }
    }
    $issuerPostalCode = $issuerPostalCode ?: trim((string)($ferreteriaInfo['codigo_postal'] ?? $data['lugar_expedicion'] ?? ''));
    if (!preg_match('/^[0-9]{5}$/', $issuerPostalCode)) {
        return ['estatus' => false, 'mensaje' => 'Configura un código postal fiscal de cinco dígitos para el emisor antes de facturar.'];
    }
 
    $paymentForm   = trim((string)($data['forma_pago'] ?? '')) ?: '01';
    $paymentMethod = trim((string)($data['metodo_pago'] ?? 'PUE')) ?: 'PUE';
    $receiver = [
        'Rfc' => $rfc, 'Name' => $razon_social, 'CfdiUse' => $uso_cfdi,
        'FiscalRegime' => $regimen_fiscal, 'TaxZipCode' => $codigo_postal
    ];
    if ($correo !== '') $receiver['Email'] = $correo;
 
    $cfdiPayload = [
        'CfdiType'      => 'I',
        'Folio'         => $folio,          // clave de idempotencia
        'Date'          => $invoiceDate,    // clave de idempotencia
        'Currency'      => 'MXN',
        'PaymentForm'   => $paymentForm,
        'ExpeditionPlace' => $issuerPostalCode,
        'PaymentMethod' => $paymentMethod,
        'Receiver'      => $receiver,
        'Items'         => $cfdiItems
    ];
    $deliveryOptions = $this->opcionesEntrega($correo, $ferreteriaInfo);
 
    // 6) Marcar "En proceso" y guardar SOLO identidad (+ datos fiscales para mostrar/editar)
    $this->db->beginTransaction();
    try {
        $locked = $this->db->select(
            'SELECT estatus, estatus_factura, factura_date, factura_issuer_id
             FROM ventas WHERE id = ? AND id_ferreteria = ? AND id_sucursal = ? FOR UPDATE',
            [$id_venta, $this->id_ferreteria, $this->id_sucursal]
        );
        if (empty($locked) || (int)$locked[0]['estatus'] === 0 || $locked[0]['estatus_factura'] === 'Facturada') {
            $this->db->rollBack();
            return ['estatus' => false, 'mensaje' => 'La venta ya no está disponible para facturar'];
        }
        // Otra petición ya fijó Date/emisor: respétalos para no romper la idempotencia
        if ($locked[0]['estatus_factura'] === 'En proceso' && !empty($locked[0]['factura_date'])) {
            $invoiceDate = (string)$locked[0]['factura_date'];
            $cfdiPayload['Date'] = $invoiceDate;
        }
        $this->db->update('ventas', [
            'estatus_factura'       => 'En proceso',
            'factura_issuer_id'     => $issuerId,
            'factura_date'          => $invoiceDate,
            'factura_rfc'           => $rfc,
            'factura_razon_social'  => $razon_social,
            'factura_codigo_postal' => $codigo_postal,
            'factura_regimen_fiscal'=> $regimen_fiscal,
            'factura_uso_cfdi'      => $uso_cfdi,
            'factura_correo'        => $correo,
            'factura_metodo_pago'   => $paymentMethod,
            'factura_forma_pago'    => $paymentForm
        ], 'id = ? AND id_ferreteria = ?', [$id_venta, $this->id_ferreteria]);
        $this->db->commit();
    } catch (Throwable $exception) {
        $this->db->rollBack();
        return ['estatus' => false, 'mensaje' => 'No se pudo guardar la solicitud antes de facturar.'];
    }
 
    // 7) Timbrar
    $response = $client->facturar($clientId, $issuerId, $cfdiPayload, $deliveryOptions);
 
    if (!$response['estatus']) {
        $httpCode = (int)($response['http_code'] ?? 0);
        // 4xx (excepto 408/429): rechazo definitivo, no se timbró nada.
        // Se limpia el intento para que el reenvío corregido genere un Date nuevo.
        if ($httpCode >= 400 && $httpCode < 500 && !in_array($httpCode, [408, 429], true)) {
            $this->db->update('ventas', [
                'estatus_factura'   => 'Pendiente',
                'factura_date'      => null,
                'factura_issuer_id' => null
            ], 'id = ? AND id_ferreteria = ? AND estatus_factura = ?', [$id_venta, $this->id_ferreteria, 'En proceso']);
            return $response;
        }
        // Timeout / 5xx / 408 / 429: resultado incierto. Se queda "En proceso";
        // el siguiente intento consultará por Folio antes de reenviar.
        $response['mensaje'] = 'No se pudo confirmar la emisión. Vuelve a intentar: se verificará antes de reenviar.';
        return $response;
    }
 
    return $this->finalizarFactura($id_venta, $folio, $response['data'], $correo);
}
 
/** Marca la venta como Facturada y envía documentos si hay correo. */
private function finalizarFactura(int $id_venta, string $folio, array $providerData, string $correo): array {
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
private function opcionesEntrega(string $correo, array $ferreteriaInfo): array {
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

    public function emisores(): array {
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

    public function descargarFactura(int $id_venta, string $formato): array {
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

    public function enviarFacturaPorCorreo(int $id_venta, string $correo, string $asunto, string $comentarios): array {
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

    public function cancelarVenta(int $id_venta, string $motivo): array {
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
