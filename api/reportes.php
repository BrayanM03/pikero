<?php
require_once __DIR__ . '/../utils/ReportService.php';
require_once __DIR__ . '/../utils/ExcelReport.php';
require_once __DIR__ . '/../models/Permiso.php';
require_once __DIR__ . '/../config/permisos-enum.php';

$module = (string)($_GET['modulo'] ?? $_POST['modulo'] ?? '');
$tipo = (string)($_GET['tipo'] ?? $_POST['tipo'] ?? '');
$permits = [
    'clientes' => CPERMISO::VER_CLIENTES->value,
    'ventas' => CPERMISO::VER_VENTAS->value,
    'compras' => CPERMISO::VER_COMPRAS->value,
    'gastos' => CPERMISO::VER_GASTOS->value,
    'inventario' => CPERMISO::VER_INVENTARIO->value,
    'movimientos' => CPERMISO::VER_INVENTARIO->value,
    'usuarios' => CPERMISO::VER_USUARIOS->value
];

if (empty($_SESSION['id']) || !isset($permits[$module])) {
    http_response_code(empty($_SESSION['id']) ? 401 : 400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['estatus' => false, 'mensaje' => 'Sesión o módulo de reporte no válido']);
    exit;
}

$permission = (new Permiso())->verificarPermiso($_SESSION['id_rol'] ?? 0, $_SESSION['id'] ?? 0, $permits[$module]);
if (empty($permission['estatus'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['estatus' => false, 'mensaje' => 'No tienes permiso para consultar este reporte']);
    exit;
}

$service = new ReportService();
if ($tipo === 'datatable') {
    $response = $service->datatable($module, $_GET + $_POST);
    if (isset($response['error'])) http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($tipo === 'exportar') {
    $response = $service->export($module, $_POST);
    if (empty($response['estatus'])) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    ExcelReport::download($module, $response);
    exit;
} 

http_response_code(400);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['estatus' => false, 'mensaje' => 'Operación no válida', 'tipo' => $tipo, 'modulo' => $module]);
