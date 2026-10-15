<?php
require_once __DIR__ . '/../utils/Tenant.php';
require_once __DIR__ . '/../config/permisos-enum.php';
require_once __DIR__ . '/../models/Ferreteria.php';
require_once __DIR__ . '/../models/Permiso.php';

header('Content-Type: application/json; charset=utf-8');
$tipo = $_GET['tipo'] ?? $_POST['tipo'] ?? '';
$permissionsByAction = [
    'datatable' => CPERMISO::VER_FERRETERIAS->value,
    'obtener' => CPERMISO::VER_FERRETERIAS->value,
    'registrar' => CPERMISO::CREAR_FERRETERIAS->value,
    'actualizar' => CPERMISO::EDITAR_FERRETERIAS->value,
    'cancelar' => CPERMISO::CANCELAR_FERRETERIAS->value
];

if (!Tenant::esSuperAdmin() || !isset($permissionsByAction[$tipo])) {
    http_response_code(403);
    echo json_encode(['estatus' => false, 'mensaje' => 'Acceso denegado']);
    exit;
}
$permission = (new Permiso())->verificarPermiso($_SESSION['id_rol'] ?? 0, $_SESSION['id'] ?? 0, $permissionsByAction[$tipo]);
if (!$permission['estatus']) {
    http_response_code(403);
    echo json_encode(['estatus' => false, 'mensaje' => 'No tienes permiso para esta operación']);
    exit;
}

$model = new Ferreteria();
if ($tipo === 'datatable') {
    $draw = (int)($_GET['draw'] ?? 0);
    $start = max(0, (int)($_GET['start'] ?? 0));
    $length = min(200, max(1, (int)($_GET['length'] ?? 10)));
    $search = trim((string)($_GET['search']['value'] ?? ''));
    $orderIndex = (int)($_GET['order'][0]['column'] ?? 0);
    $orderColumn = (string)($_GET['columns'][$orderIndex]['data'] ?? 'id');
    $orderDir = (string)($_GET['order'][0]['dir'] ?? 'desc');
    $recordsTotal = $model->contar();
    $recordsFiltered = $model->contar($search);
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $model->datatables($start, $length, $search, $orderColumn, $orderDir)
    ]);
    exit;
}

if ($tipo === 'obtener') {
    echo json_encode($model->obtener((int)($_POST['id'] ?? $_GET['id'] ?? 0)));
    exit;
}
if ($tipo === 'registrar' || $tipo === 'actualizar') {
    $id = $tipo === 'actualizar' ? (int)($_POST['id'] ?? 0) : null;
    echo json_encode($model->guardar($_POST, $_FILES['logo'] ?? null, $id));
    exit;
}
if ($tipo === 'cancelar') {
    echo json_encode($model->cancelar((int)($_POST['id'] ?? 0)));
    exit;
}
http_response_code(400);
echo json_encode(['estatus' => false, 'mensaje' => 'Operación no reconocida']);
