<?php
require_once __DIR__ . '/../models/Panel.php';

header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['estatus' => false, 'mensaje' => 'Debes iniciar sesión']);
    exit;
}

$panel = new Panel();
$tipo = $_GET['tipo'] ?? '';
if ($tipo === 'resumen') {
    echo json_encode($panel->resumen(), JSON_UNESCAPED_UNICODE);
    exit;
}
if ($tipo === 'grafica') {
    echo json_encode($panel->grafica($_GET), JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['estatus' => false, 'mensaje' => 'Consulta no válida']);