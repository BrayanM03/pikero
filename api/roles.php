<?php
require_once __DIR__ . '/../controllers/RolController.php';

$controller = new RolController();
$tipo = $_GET['tipo'] ?? '';

if ($tipo == 'datatable') {
    $controller->datatable_roles();
}

if ($tipo == 'combo') {
    $controller->combo(1);
}
