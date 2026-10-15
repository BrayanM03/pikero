<?php
require_once __DIR__ . '/../controllers/GastoController.php';
require_once __DIR__ . '/../models/Permiso.php';
require_once __DIR__ . '/../config/permisos-enum.php';

header('Content-Type: application/json; charset=utf-8');
$tipo = (string)($_GET['tipo'] ?? $_POST['tipo'] ?? '');
$permisos = [
    'datatable' => CPERMISO::VER_GASTOS,
    'categorias' => CPERMISO::VER_GASTOS,
    'categoria_crear' => CPERMISO::CREAR_GASTOS,
    'registrar' => CPERMISO::CREAR_GASTOS,
    'obtener' => CPERMISO::VER_GASTOS,
    'actualizar' => CPERMISO::EDITAR_GASTOS,
    'cancelar' => CPERMISO::CANCELAR_GASTOS
];

if (empty($_SESSION['id']) || !isset($permisos[$tipo])) {
    http_response_code(empty($_SESSION['id']) ? 401 : 400);
    echo json_encode(['estatus' => false, 'mensaje' => 'Sesión u operación no válida']);
    exit;
}
$permission = (new Permiso())->verificarPermiso($_SESSION['id_rol'] ?? 0, $_SESSION['id'], $permisos[$tipo]->value);
if (empty($permission['estatus'])) {
    http_response_code(403);
    echo json_encode(['estatus' => false, 'mensaje' => 'No tienes permiso para esta operación']);
    exit;
}

$controller = new GastoController();
if ($tipo === 'datatable') {
    $controller->datatable();
    exit;
}
if ($tipo === 'categorias') {
    echo json_encode($controller->categorias(), JSON_UNESCAPED_UNICODE);
    exit;
}
if ($tipo === 'categoria_crear') {
    echo json_encode($controller->crearCategoria((string)($_POST['nombre'] ?? '')), JSON_UNESCAPED_UNICODE);
    exit;
}
if ($tipo === 'registrar') {
    echo json_encode($controller->registrar($_POST, $_FILES['comprobante'] ?? null), JSON_UNESCAPED_UNICODE);
    exit;
}
if ($tipo === 'obtener') {
    echo json_encode($controller->obtener((int)($_GET['id'] ?? 0)), JSON_UNESCAPED_UNICODE);
    exit;
}
if ($tipo === 'actualizar') {
    echo json_encode($controller->actualizar((int)($_POST['id'] ?? 0), $_POST, $_FILES['comprobante'] ?? null), JSON_UNESCAPED_UNICODE);
    exit;
}
if ($tipo === 'cancelar') {
    echo json_encode($controller->cancelar((int)($_POST['id'] ?? 0)), JSON_UNESCAPED_UNICODE);
    exit;
}