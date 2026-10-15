<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/permisos-enum.php';
require_once __DIR__ . '/../models/Permiso.php';

class PermisoController {
    private $model;

    public function __construct() {
        $this->model = new Permiso();
    }

    public function verificarSesion() {
        if (empty($_SESSION['id'])) {
            header("Location: " . BASE_URL . "login");
            exit();
        }
    }

    public function getPermisosPorRol($tipo_resp, $rol_id) {
        $resp = $this->model->obtenerTodosPermisosPorRol($rol_id);
        if ($tipo_resp == 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function validarAcceso($tipo_resp, $slug_permiso) {
        $id_usuario = $_SESSION['id'] ?? null;
        $id_rol = $_SESSION['id_rol'] ?? null;

        if (!$id_usuario || !$id_rol) {
            header("Location: " . BASE_URL . "login");
            exit();
        }

        $response_permiso = $this->model->verificarPermiso($id_rol, $id_usuario, $slug_permiso);
        $tipo_resp = intval($tipo_resp);

        if (!$response_permiso['estatus'] && $tipo_resp != 2) {
            header("Location: " . BASE_URL . "sin_permiso/" . $slug_permiso);
            exit();
        }

        if ($tipo_resp == 2) {
            return $response_permiso;
        }
    }

    public function verificarPermiso($tipo_resp, $slug_permiso) {
        $id_usuario = $_SESSION['id'] ?? null;
        $id_rol = $_SESSION['id_rol'] ?? null;
        $response_permiso = $this->model->verificarPermiso($id_rol, $id_usuario, $slug_permiso);
        if ($tipo_resp == 2) {
            return $response_permiso;
        }
        echo json_encode($response_permiso);
    }

    public function obtener_permisos_existentes($tipo_resp) {
        $resp = $this->model->obtenerPermisosExistentes();
        if ($tipo_resp == 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function obtener_permisos_x_usuario($tipo_resp, $id_usuario) {
        $resp = $this->model->obtenerPermisosXUsuario($id_usuario);
        if ($tipo_resp == 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function traer_permisos_faltantes($tipo_resp, $id_usuario) {
        $resp = $this->model->traerPermisosFaltantes($id_usuario);
        if ($tipo_resp == 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function actualizar_permiso_usuario($tipo_resp, $id_usuario, $id_permiso, $valor, $accion) {
        $resp = $this->model->actualizarPermisoUsuario($id_usuario, $id_permiso, $valor, $accion);
        if ($tipo_resp == 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function actualizar_permiso_rol($tipo_resp, $id_rol, $id_permiso, $valor) {
        $resp = $this->model->actualizarPermisoRol($id_rol, $id_permiso, $valor);
        if ($tipo_resp == 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function registrar_permiso($data, $tipo_resp) {
        $resp = $this->model->registrarPermiso($data);
        if ($tipo_resp == 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }
}
