<?php
require_once __DIR__ . '/../controllers/PuntoVentaController.php';

$controller = new PuntoVentaController();
$tipo = $_GET['tipo'] ?? ($_POST['tipo'] ?? '');

if ($tipo === 'cobrar') {
    // Si viene como JSON en php://input
    $input = json_decode(file_get_contents('php://input'), true);
    $data = !empty($input) ? $input : $_POST;
    $controller->cobrar(1, $data);
    exit;
}

if ($tipo === 'buscar') {
    $q = trim((string)($_GET['q'] ?? ($_POST['q'] ?? '')));
    $controller->buscarProducto(1, $q);
    exit;
}

if ($tipo === 'catalogos') {
    $controller->catalogosPOS(1);
    exit;
}
