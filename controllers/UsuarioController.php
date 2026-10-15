<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../utils/FileHelper.php';
require_once __DIR__ . '/../utils/Tenant.php';
require_once __DIR__ . '/../controllers/DataTableController.php';

class UsuarioController extends DataTableController {
    private $id_sesion;
    protected $current_datatable;
    private $id_ferreteria;

    public function __construct() {
        $this->id_sesion = $_SESSION['id'] ?? null;
        $this->id_ferreteria = Tenant::idFerreteria();
        parent::__construct();
        $this->model = new Usuario($this->id_ferreteria);
    }

    public function datatable_usuarios() {
        $this->current_datatable = 'vista_usuarios';
        $this->datatable_general();
    }

    protected function getModelData($id_filtro, $start, $length, $search, $orderColumnName, $orderDir, $filtros) {
        return $this->model->datatablesUsuarios($id_filtro, $start, $length, $search, $orderColumnName, $orderDir);
    }

    protected function getModelTotal($id_filtro, $filtros) {
        return $this->model->contarUsuarios($id_filtro);
    }

    protected function getModelFilteredTotal($id_filtro, $search, $filtros) {
        return $this->model->contarUsuariosFiltrados($id_filtro, $search);
    }

    public function obtener_usuario($tipo_resp, $id_usuario = null) {
        $id_usuario = $id_usuario ?? ($_SESSION['id'] ?? null);
        $user_data = $this->model->obtenerPorIDUsuario($id_usuario);
        $resp = [
            'estatus' => true,
            'mensaje' => 'Informacion usuario',
            'data' => $user_data
        ];
        if ($tipo_resp == 2) {
            return $resp;
        }
        echo json_encode($resp);
    }

    public function actualizar_perfil($tipo_resp, $data) {
        $id_usuario = $_SESSION['id'] ?? null;
        $response = $id_usuario
            ? $this->model->actualizarPerfil($id_usuario, $data)
            : ['estatus' => false, 'mensaje' => 'La sesión expiró'];

        if ($response['estatus']) {
            $perfil = $response['data'];
            $_SESSION['nombre'] = $perfil['nombre'];
            $_SESSION['apellido'] = $perfil['apellido'];
            $_SESSION['user'] = $perfil['usuario'];
            $_SESSION['correo'] = $perfil['correo'];
            $_SESSION['cargo'] = $perfil['cargo'];
        }

        if ($tipo_resp == 2) {
            return $response;
        }
        echo json_encode($response);
    }

    public function cambiar_ferreteria_activa($tipo_resp, $id_ferreteria) {
        if (empty($_SESSION['id'])) {
            $response = ['estatus' => false, 'mensaje' => 'La sesión expiró'];
        } elseif (!Tenant::esSuperAdmin()) {
            $response = ['estatus' => false, 'mensaje' => 'No tienes permiso para cambiar de ferretería'];
        } else {
            $ferreteria = $this->model->obtenerFerreteriaActiva((int)$id_ferreteria);
            if (!$ferreteria) {
                $response = ['estatus' => false, 'mensaje' => 'La ferretería seleccionada no está disponible'];
            } else {
                $_SESSION['id_ferreteria'] = (int)$ferreteria['id'];
                $_SESSION['ferreteria'] = $ferreteria['nombre'];
                $_SESSION['logo_ferreteria'] = $ferreteria['logo'] ?? '';
                $_SESSION['id_sucursal'] = $ferreteria['id_sucursal'] ? (int)$ferreteria['id_sucursal'] : null;
                $_SESSION['sucursal'] = $ferreteria['sucursal'] ?? '';
                $_SESSION['logo_sucursal'] = $ferreteria['logo_sucursal'] ?? '';
                $response = ['estatus' => true, 'mensaje' => 'Ferretería activa actualizada'];
            }
        }

        if ($tipo_resp == 2) {
            return $response;
        }
        echo json_encode($response);
    }

    public function registrar_usuario($tipo_resp, $data) {
        $id_ferreteria = Tenant::esSuperAdmin()
            ? (int)($data['id_ferreteria'] ?? 0)
            : $this->id_ferreteria;
            
        $response = $this->model->registrarUsuario(
            $data['nombre'] ?? '',
            $data['apellidos'] ?? '',
            $data['id_sucursal'] ?? null,
            $data['telefono'] ?? '',
            $data['usuario'] ?? '',
            $data['password'] ?? '',
            $data['rol'] ?? null,
            $data['cargo'] ?? '',
            $data['correo'] ?? '',
            $id_ferreteria
        );

        if ($response['estatus'] && isset($_FILES['avatar']) && !empty($_FILES['avatar']['tmp_name'])) {
            $id_usuario_nuevo = $response['nuevo'];
            $usuario_data = $response['usuario'];
            $nuevo_foto_perfil = FileHelper::uploadImage($_FILES['avatar'], 'avatars', 'perfil_' . $id_usuario_nuevo, $usuario_data['foto_perfil'] ?? null);
            if ($nuevo_foto_perfil) {
                $this->model->actualizarUsuario($id_usuario_nuevo, ['foto_perfil' => $nuevo_foto_perfil]);
            }
        }

        if ($tipo_resp == 2) {
            return $response;
        }
        echo json_encode($response);
    }

    public function combo($tipo_resp) {
        $data = $this->model->combo();
        if ($tipo_resp == 1) {
            echo json_encode($data);
            exit;
        }
        return $data;
    }

    public function cancelar_usuario($tipo_resp, $id_usuario) {
        $filas = $this->model->actualizarUsuario($id_usuario, ['estatus' => 0]);
        $resp = ['estatus' => true, 'mensaje' => 'Usuario desactivado'];
        if ($tipo_resp == 2) {
            return $resp;
        }
        echo json_encode($resp);
    }

    private function enviarRespuesta($data, $tipo) {
        if ($tipo == 1) {
            echo json_encode($data);
            exit;
        }
        return $data;
    }
}
