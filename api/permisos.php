<?php
require_once __DIR__ . '/../controllers/PermisoController.php';

$controller = new PermisoController();
$tipo = $_GET['tipo'] ?? '';

if ($tipo == 'ver_lista_permisos') {
    $controller->obtener_permisos_existentes(1);
}

if ($tipo == 'registrar_permiso') {
    $controller->registrar_permiso($_POST, 1);
}

if ($tipo == 'ver_lista_permisos_x_rol') {
    $controller->getPermisosPorRol(1, $_POST['id_rol'] ?? 0);
}

if ($tipo == 'ver_lista_permisos_faltantes') {
    $controller->traer_permisos_faltantes(1, $_POST['id_usuario'] ?? 0);
}

if ($tipo == 'permisos_usuario') {
    $controller->obtener_permisos_x_usuario(1, $_POST['id_usuario'] ?? 0);
}

if ($tipo == 'gestion_permiso_individual') {
    $controller->actualizar_permiso_usuario(
        1,
        $_POST['id_usuario'] ?? 0,
        $_POST['id_permiso'] ?? 0,
        $_POST['valor'] ?? 0,
        $_POST['accion'] ?? 'actualizar'
    );
}

if ($tipo == 'gestion_permiso_rol') {
    $controller->actualizar_permiso_rol(1, $_POST['id_rol'] ?? 0, $_POST['id_permiso'] ?? 0, $_POST['valor'] ?? 0);
}
