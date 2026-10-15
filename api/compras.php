<?php
require_once __DIR__ . '/../controllers/CompraController.php';

$controller = new CompraController();
$tipo = $_GET['tipo'] ?? ($_POST['tipo'] ?? '');

if ($tipo === 'datatable') {
    $controller->datatable_compras();
    exit;
}

if ($tipo === 'registrar') {
    $input = json_decode(file_get_contents('php://input'), true);
    $data = !empty($input) ? $input : $_POST;
    $controller->registrar(1, $data);
    exit;
}

if ($tipo === 'detalle') {
    $controller->detalle(1, (int)($_GET['id'] ?? ($_POST['id'] ?? 0)));
    exit;
}