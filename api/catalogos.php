<?php
require_once __DIR__ . '/../controllers/CatalogoController.php';

$catalogo = !empty($_GET['catalogo']) ? $_GET['catalogo'] : (!empty($_POST['catalogo']) ? $_POST['catalogo'] : 'categorias');
if (is_array($catalogo) || !is_string($catalogo)) {
    $catalogo = 'categorias';
}
$catalogo = trim($catalogo);
$permitidos = ['categorias', 'marcas', 'unidades', 'formas_pago'];
if (!in_array($catalogo, $permitidos, true)) {
    $catalogo = 'categorias';
}

$controller = new CatalogoController($catalogo);
$tipo = $_GET['tipo'] ?? ($_POST['tipo'] ?? '');

if ($tipo == 'datatable') {
    $controller->datatable();
    exit;
}

if ($tipo == 'registrar') {
    $controller->registrar(1, $_POST);
    exit;
}

if ($tipo == 'actualizar') {
    $controller->actualizar(1, $_POST);
    exit;
}

if ($tipo == 'obtener') {
    $controller->obtener(1, $_POST['id'] ?? 0);
    exit;
}

if ($tipo == 'cancelar') {
    $controller->cancelar(1, $_POST['id'] ?? 0);
    exit;
}

if ($tipo == 'combo') {
    $controller->combo(1);
    exit;
}
