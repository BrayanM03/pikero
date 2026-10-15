<?php
require_once __DIR__ . '/../controllers/CajaController.php';

$controller = new CajaController();
$tipo = $_GET['tipo'] ?? ($_POST['tipo'] ?? '');
$input = json_decode(file_get_contents('php://input'), true);
$data = !empty($input) ? $input : $_POST;

if ($tipo === 'estado') $controller->estado(1);
if ($tipo === 'movimientos') $controller->movimientos(1);
if ($tipo === 'abrir') $controller->abrir(1, $data);
if ($tipo === 'movimiento') $controller->movimiento(1, $data);
if ($tipo === 'cerrar') $controller->cerrar(1, $data);