<?php
require_once __DIR__ . '/../controllers/ClienteController.php';
require_once __DIR__ . '/../config/permisos-enum.php';
require_once __DIR__ . '/../models/Permiso.php';

$tipo = $_GET['tipo'] ?? ($_POST['tipo'] ?? '');
$permisos = [
    'datatable' => CPERMISO::VER_CLIENTES->value,
    'obtener' => CPERMISO::VER_CLIENTES->value,
    'combo' => CPERMISO::VER_CLIENTES->value,
    'registrar' => CPERMISO::CREAR_CLIENTES->value,
    'actualizar' => CPERMISO::EDITAR_CLIENTES->value,
    'cancelar' => CPERMISO::CANCELAR_CLIENTES->value
];
$permiso = isset($permisos[$tipo]) ? (new Permiso())->verificarPermiso($_SESSION['id_rol'] ?? 0, $_SESSION['id'] ?? 0, $permisos[$tipo]) : ['estatus' => false];
if (!$permiso['estatus']) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['estatus' => false, 'mensaje' => 'No tienes permiso para esta operación']);
    exit;
}

$controller = new ClienteController();

if ($tipo === 'datatable') {
    $controller->datatable_clientes();
    exit;
}

if ($tipo === 'registrar') {
    $controller->registrar(1, $_POST);
    exit;
}

if ($tipo === 'actualizar') {
    $controller->actualizar(1, $_POST);
    exit;
}

if ($tipo === 'obtener') {
    $controller->obtener(1, (int)($_POST['id'] ?? ($_GET['id'] ?? 0)));
    exit;
}

if ($tipo === 'cancelar') {
    $controller->cancelar(1, (int)($_POST['id'] ?? 0));
    exit;
}

if ($tipo === 'combo') {
    $controller->combo(1);
    exit;
}
