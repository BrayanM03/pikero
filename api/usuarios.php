<?php
require_once __DIR__ . '/../controllers/UsuarioController.php';

$controller = new UsuarioController();
$tipo = $_GET['tipo'] ?? '';

if ($tipo == 'obtener_usuario') {
    $controller->obtener_usuario(1, $_SESSION['id']);
}

if ($tipo == 'datatable') {
    $controller->datatable_usuarios();
}

if ($tipo == 'registrar') {
    $controller->registrar_usuario(1, $_POST);
}

if ($tipo == 'combo') {
    $controller->combo(1);
}

if ($tipo == 'cancelar') {
    $controller->cancelar_usuario(1, $_POST['id'] ?? 0);
}

if ($tipo == 'actualizar_perfil') {
    $controller->actualizar_perfil(1, $_POST);
}

if ($tipo == 'cambiar_ferreteria_activa') {
    $controller->cambiar_ferreteria_activa(1, $_POST['id_ferreteria'] ?? 0);
}
