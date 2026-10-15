<?php
require_once __DIR__ . '/../controllers/ProveedorController.php';

$controller = new ProveedorController();
$tipo = $_GET['tipo'] ?? ($_POST['tipo'] ?? '');

if ($tipo === 'datatable') {
    $controller->datatable_proveedores();
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
