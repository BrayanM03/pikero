<?php
require_once __DIR__ . '/../controllers/VentaController.php';

$controller = new VentaController();
$tipo = $_GET['tipo'] ?? ($_POST['tipo'] ?? '');

if ($tipo === 'datatable') {
    $controller->datatable_ventas();
    exit;
}

if ($tipo === 'detalle') {
    $id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
    $controller->detalle(1, $id);
    exit;
}

if ($tipo === 'abonar') {
    $id = (int)($_POST['id'] ?? 0);
    $controller->abonar(1, $id, $_POST);
    exit;
}

if ($tipo === 'cancelar') {
    $controller->cancelar(1, $_POST);
    exit;
}

if ($tipo === 'facturar') {
    $input = json_decode(file_get_contents('php://input'), true);
    $controller->facturar(1, !empty($input) ? $input : $_POST);
    exit;
}

if ($tipo === 'descargar_factura') {
    require_once __DIR__ . '/../models/Permiso.php';
    require_once __DIR__ . '/../config/permisos-enum.php';
    $permiso = (new Permiso())->verificarPermiso($_SESSION['id_rol'] ?? 0, $_SESSION['id'] ?? 0, CPERMISO::VER_VENTAS->value);
    if (empty($_SESSION['id']) || empty($permiso['estatus'])) {
        http_response_code(empty($_SESSION['id']) ? 401 : 403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['estatus' => false, 'mensaje' => 'No tienes permiso para descargar esta factura']);
        exit;
    }
    $response = $controller->descargarFactura((int)($_GET['id'] ?? 0), (string)($_GET['formato'] ?? ''));
    if (empty($response['estatus'])) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Content-Type: ' . $response['content_type']);
    header('Content-Disposition: attachment; filename="' . basename($response['filename']) . '"');
    header('Content-Length: ' . strlen($response['contenido']));
    header('Cache-Control: private, no-store');
    echo $response['contenido'];
    exit;
}

if ($tipo === 'cancelar_factura') {
    require_once __DIR__ . '/../models/Permiso.php';
    require_once __DIR__ . '/../config/permisos-enum.php';
    header('Content-Type: application/json; charset=utf-8');

    $permiso = (new Permiso())->verificarPermiso(
        $_SESSION['id_rol'] ?? 0,
        $_SESSION['id'] ?? 0,
        CPERMISO::CANCELAR_FACTURAS->value   // crea este permiso
    );
    if (empty($_SESSION['id']) || empty($permiso['estatus'])) {
        http_response_code(empty($_SESSION['id']) ? 401 : 403);
        echo json_encode(['estatus' => false, 'mensaje' => 'No tienes permiso para cancelar esta factura']);
        exit;
    }

    $id_venta = (int)($_POST['id'] ?? 0);
    $motivo   = trim((string)($_POST['motivo'] ?? ''));
    $uuid     = strtolower(trim((string)($_POST['uuid_sustituto'] ?? '')));

    echo json_encode($controller->cancelarFactura($id_venta, $motivo, $uuid), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($tipo === 'enviar_factura_correo') {
    require_once __DIR__ . '/../models/Permiso.php';
    require_once __DIR__ . '/../config/permisos-enum.php';
    $permiso = (new Permiso())->verificarPermiso($_SESSION['id_rol'] ?? 0, $_SESSION['id'] ?? 0, CPERMISO::VER_VENTAS->value);
    if (empty($_SESSION['id']) || empty($permiso['estatus'])) {
        http_response_code(empty($_SESSION['id']) ? 401 : 403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['estatus' => false, 'mensaje' => 'No tienes permiso para enviar esta factura']);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    $id_venta = (int)($_POST['id'] ?? 0);
    $correo = trim((string)($_POST['correo'] ?? ''));
    $asunto = trim((string)($_POST['asunto'] ?? 'Factura emitida'));
    $comentarios = trim((string)($_POST['comentarios'] ?? ''));
    echo json_encode($controller->enviarFacturaPorCorreo($id_venta, $correo, $asunto, $comentarios), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($tipo === 'emisores') {
    $controller->emisores();
    exit;
}

if ($tipo === 'catalogo_sat') {
    if (empty($_SESSION['id'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['estatus' => false, 'mensaje' => 'Debes iniciar sesión para consultar los catálogos SAT.']);
        exit;
    }
    $catalogo = (string)($_GET['catalogo'] ?? '');
    $keyword = (string)($_GET['keyword'] ?? '');
    $response = (new FacturamaClient())->catalogoSAT($catalogo, $keyword);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if($tipo === 'pdf'){
    $id_venta = (int)($_GET['id'] ?? 0);
    
    if ($id_venta <= 0) {
        http_response_code(400);
        exit('ID de venta inválido');
    }

    // 1. Obtener toda la información de la venta, items, abonos y datos de la ferretería
    // (Asegúrate de invocar el método detalle() de tu controlador o clase de ventas)
    $resultado = $controller->detalle(2, $id_venta); 

    if (!$resultado['estatus']) {
        http_response_code(404);
        exit($resultado['mensaje']);
    }

    $data = $resultado['data'];
    $venta = $data['venta'];
    $items = $data['items'];
    $abonos = $data['abonos'];

    // 2. Incluir la librería FPDF (ajusta la ruta según tu estructura)
    //require(__DIR__ . '/../vendor/autoload.php');
    require(__DIR__ . '/../config/reporte-venta.php'); // Asegúrate de que este archivo contenga la clase PDF_VentaReporte
    // 3. Instanciar el reporte PDF configurado previamente
    $pdf = new PDF_VentaReporte($data);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // 4. Imprimir las partidas (Productos) en la tabla del PDF
    $pdf->SetFont('Arial', '', 8);
    foreach ($items as $it) {
        $pdf->Cell(85, 6, fpdf_utf8($it['producto'] . ' (SKU: ' . ($it['codigo'] ?? 'S/C') . ')'), 1, 0, 'L');
        $pdf->Cell(25, 6, number_format((float)$it['cantidad'], 2), 1, 0, 'C');
        $pdf->Cell(25, 6, fpdf_utf8($it['unidad'] ?? 'pza'), 1, 0, 'C');
        $pdf->Cell(25, 6, '$' . number_format((float)$it['precio'], 2), 1, 0, 'R');
        $pdf->Cell(30, 6, '$' . number_format((float)$it['importe'], 2), 1, 1, 'R');
    }

    // 5. Imprimir el Total General de la Venta
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(160, 6, fpdf_utf8('TOTAL A PAGAR:'), 0, 0, 'R');
    $pdf->Cell(30, 6, '$' . number_format((float)$venta['total'], 2), 0, 1, 'R');

    // 6. Si la venta es a crédito y tiene abonos registrados, mostrarlos abajo
    if ($venta['tipo_venta'] === 'credito' && !empty($abonos)) {
        $pdf->Ln(6);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell(190, 5, fpdf_utf8(' HISTORIAL DE ABONOS A CRÉDITO'), 1, 1, 'L', true);
        
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(35, 5, fpdf_utf8('Fecha'), 1, 0, 'C', true);
        $pdf->Cell(35, 5, fpdf_utf8('Forma de pago'), 1, 0, 'C', true);
        $pdf->Cell(40, 5, fpdf_utf8('Usuario'), 1, 0, 'C', true);
        $pdf->Cell(30, 5, fpdf_utf8('Monto'), 1, 0, 'R', true);
        $pdf->Cell(50, 5, fpdf_utf8('Observaciones'), 1, 1, 'L', true);

        $pdf->SetFont('Arial', '', 8);
        foreach ($abonos as $abono) {
            $pdf->Cell(35, 5, fpdf_utf8($abono['fecha_registro']), 1, 0, 'C');
            $pdf->Cell(35, 5, fpdf_utf8($abono['forma_pago'] ?? 'N/D'), 1, 0, 'C');
            $pdf->Cell(40, 5, fpdf_utf8($abono['usuario'] ?? 'N/D'), 1, 0, 'L');
            $pdf->Cell(30, 5, '$' . number_format((float)$abono['monto'], 2), 1, 0, 'R');
            $pdf->Cell(50, 5, fpdf_utf8($abono['observaciones'] ?? ''), 1, 1, 'L');
        }
    }

    // 7. Salida directa del PDF al navegador (Modo 'I' para visualizar en pestaña)
    $pdf->Output('I', 'Nota_' . $venta['folio'] . '.pdf');
    exit;
}

if($tipo === 'editar_venta') {
    
    $id_venta = $_POST['id'] ?? 0;
    $payload = $_POST ?? [];
   
    header('Content-Type: application/json; charset=utf-8');
    $controller->editar(1, $id_venta, $payload);
}
