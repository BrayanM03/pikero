<?php
require_once __DIR__ . '/../controllers/InventarioController.php';

$controller = new InventarioController();
$tipo = $_GET['tipo'] ?? ($_POST['tipo'] ?? '');

if ($tipo === 'datatable') {
    $controller->datatable_inventario();
    exit;
}

if ($tipo === 'ajustar') {
    $controller->ajustar(1, $_POST);
    exit;
}

if ($tipo === 'movimientos') {
    $id_producto = (int)($_GET['id_producto'] ?? ($_POST['id_producto'] ?? 0));
    $controller->movimientos(1, $id_producto);
    exit;
}

if ($tipo === 'historial') {
    $controller->historial(1);
    exit;
}
