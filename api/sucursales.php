<?php
require_once __DIR__ . '/../controllers/SucursalController.php';

$controller = new SucursalController();
$tipo = $_GET['tipo'] ?? '';

if ($tipo == 'datatable') {
    $controller->datatable_sucursales();
}

if ($tipo == 'registrar') {
    $controller->registrar(1, $_POST);
}

if ($tipo == 'actualizar') {
    $controller->actualizar(1, $_POST);
}

if ($tipo == 'obtener') {
    $controller->obtener(1, $_POST['id'] ?? 0);
}

if ($tipo == 'combo') {
    $controller->combo(1);
}

if ($tipo == 'combo_ferreteria') {
    $controller->combo_por_ferreteria(1, $_GET['id_ferreteria'] ?? 0);
}

if ($tipo == 'cancelar') {
    $controller->cancelar(1, $_POST['id'] ?? 0);
}
