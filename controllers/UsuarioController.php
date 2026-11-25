<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../utils/FileHelper.php';
require_once __DIR__ . '/../utils/Tenant.php';
require_once __DIR__ . '/../controllers/DataTableController.php';

class UsuarioController extends DataTableController
{
    private $id_sesion;
    protected $current_datatable;
    private $id_ferreteria;

    public function __construct()
    {
        $this->id_sesion = $_SESSION['id'] ?? null;
        $this->id_ferreteria = Tenant::idFerreteria();
        parent::__construct();
        $this->model = new Usuario($this->id_ferreteria);
    }

    public function datatable_usuarios()
    {
        $this->current_datatable = 'vista_usuarios';
        $this->datatable_general();
    }

    protected function getModelData($id_filtro, $start, $length, $search, $orderColumnName, $orderDir, $filtros)
    {
        return $this->model->datatablesUsuarios($id_filtro, $start, $length, $search, $orderColumnName, $orderDir);
    }

    protected function getModelTotal($id_filtro, $filtros)
    {
        return $this->model->contarUsuarios($id_filtro);
    }

    protected function getModelFilteredTotal($id_filtro, $search, $filtros)
    {
        return $this->model->contarUsuariosFiltrados($id_filtro, $search);
    }

    public function obtener_usuario($tipo_resp, $id_usuario = null)
    {
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

    public function actualizar_perfil($tipo_resp, $data)
    {
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

    public function cambiar_ferreteria_activa($tipo_resp, $data)
    {
        $id_ferreteria = $data['id_ferreteria'] ?? null;
        $id_sucursal = $data['sucursal_activa'] ?? null;

        if (empty($_SESSION['id'])) {
            $response = ['estatus' => false, 'mensaje' => 'La sesión expiró'];
        } elseif (!Tenant::esSuperAdmin()) {
            $response = ['estatus' => false, 'mensaje' => 'No tienes permiso para cambiar de ferretería'];
        } else {
            // Llamamos al método que trae la ferretería y sus sucursales
            $ferreteria = $this->model->obtenerFerreteriaConSucursales((int)$id_ferreteria);

            if (!$ferreteria) {
                $response = ['estatus' => false, 'mensaje' => 'La ferretería seleccionada no está disponible'];
            } else {
                // Verificamos si la sucursal enviada pertenece a las sucursales de la ferretería
               
                $contiene_sucursal = in_array((int)$id_sucursal, array_column($ferreteria['sucursales'], 'id'), true);

                if (!$contiene_sucursal) {
                    $response = ['estatus' => false, 'mensaje' => 'La sucursal seleccionada no está disponible en la ferretería'];
                } else {
                    // Buscamos los datos específicos de la sucursales elegida para guardar su nombre y logo en sesión
                    $sucursalElegida = null;
                    foreach ($ferreteria['sucursales'] as $s) {
                        if ((int)$s['id'] === (int)$id_sucursal) {
                            $sucursalElegida = $s;
                            break;
                        }
                    }

                    // Actualizamos en la base de datos el usuario
                    $this->model->actualizarUsuario($_SESSION['id'], [
                        'id_ferreteria' => (int)$ferreteria['id'],
                        'id_sucursal' => (int)$id_sucursal
                    ]);

                    // Asignamos las variables de sesión de forma consistente
                    $_SESSION['id_ferreteria'] = (int)$ferreteria['id'];
                    $_SESSION['ferreteria'] = $ferreteria['nombre'];
                    $_SESSION['logo_ferreteria'] = $ferreteria['logo'] ?? '';
                    $_SESSION['id_sucursal'] = (int)$id_sucursal;
                    $_SESSION['sucursal'] = $sucursalElegida['nombre'] ?? ''; // Guardamos el nombre o ID según tu lógica
                    $_SESSION['logo_sucursal'] = $sucursalElegida['logo'] ?? '';

                    $response = ['estatus' => true, 'mensaje' => 'Ferretería y sucursal activa actualizadas'];
                }
            }
        }

        if ($tipo_resp == 2) {
            return $response;
        }
        echo json_encode($response);
    }

    public function registrar_usuario($tipo_resp, $data)
    {
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

    public function combo($tipo_resp)
    {
        $data = $this->model->combo();
        if ($tipo_resp == 1) {
            echo json_encode($data);
            exit;
        }
        return $data;
    }

    public function cancelar_usuario($tipo_resp, $id_usuario)
    {
        $filas = $this->model->actualizarUsuario($id_usuario, ['estatus' => 0]);
        $resp = ['estatus' => true, 'mensaje' => 'Usuario desactivado'];
        if ($tipo_resp == 2) {
            return $resp;
        }
        echo json_encode($resp);
    }

    private function enviarRespuesta($data, $tipo)
    {
        if ($tipo == 1) {
            echo json_encode($data);
            exit;
        }
        return $data;
    }
}
