<?php
require_once __DIR__ . '/../models/Usuario.php';

class AuthController {
    public function login($username, $password) {
        $usuarioModel = new Usuario();
        $usuario = $usuarioModel->obtenerPorUsuario($username);

        if (!$usuario) {
            return ['estado' => 2];
        }

        if ($usuario['estatus'] != 1) {
            return ["estado" => 4];
        }

        if (!password_verify($password, $usuario['contraseña'])) {
            return ['estado' => 3];
        }

        $_SESSION['sistema'] = 'pikero';
        $_SESSION["id"] = $usuario['id'];
        $_SESSION['nombre'] = $usuario['nombre'];
        $_SESSION['apellido'] = $usuario['apellido'];
        $_SESSION['user'] = $usuario['usuario'];
        $_SESSION['fecha_ingreso'] = $usuario['fecha_ingreso'];
        $_SESSION['id_rol'] = $usuario['id_rol'];
        $_SESSION['rol_nombre'] = $usuario['nombre_rol'];
        $_SESSION['rol_slug'] = $usuario['slug_rol'];
        $_SESSION['sucursal'] = $usuario['sucursal'];
        $_SESSION['estatus'] = $usuario['estatus'];
        $_SESSION['id_sucursal'] = $usuario['id_sucursal'];
        $_SESSION['id_ferreteria'] = $usuario['id_ferreteria'];
        $_SESSION['ferreteria'] = $usuario['ferreteria'];
        $_SESSION['correo'] = $usuario['correo'] ?? '';
        $_SESSION['cargo'] = $usuario['cargo'] ?? '';
        $_SESSION['foto_perfil'] = $usuario['foto_perfil'] ?? '';
        $_SESSION['logo_sucursal'] = $usuario['logo_sucursal'] ?? '';
        $_SESSION['logo_ferreteria'] = $usuario['logo_ferreteria'] ?? '';

        return [
            "estado" => 1,
            "id_rol" => $usuario['id_rol'],
            "rol" => $usuario['nombre_rol'],
            "sucursal" => $usuario['sucursal'],
            "ferreteria" => $usuario['ferreteria']
        ];
    }
}
