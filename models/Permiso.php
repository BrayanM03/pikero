<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/Usuario.php';

class Permiso {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function obtenerPermisosPorRol($id_rol) {
        $stmt = $this->db->query(
            "SELECT p.permiso
             FROM permisos p
             INNER JOIN permisos_roles pr ON pr.id_permiso = p.id
             WHERE pr.id_rol = ?",
            [$id_rol]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerTodosPermisosPorRol($id_rol) {
        $sql = "SELECT p.*, IFNULL(pr.activo, 0) as activo, cp.nombre categoria
            FROM permisos p
            LEFT JOIN permisos_roles pr ON pr.id_permiso = p.id AND pr.id_rol = ?
            LEFT JOIN categorias_permisos cp ON cp.id = p.id_categoria";
        $stmt = $this->db->query($sql, [$id_rol]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array(
            'estatus' => true,
            'mensaje' => 'Se encontraron permisos',
            'data' => $data
        );
    }

    public function verificarPermiso($id_rol, $id_usuario, $slug_permiso) {
        $sql = "SELECT
            CASE
                WHEN pu.activo IS NOT NULL THEN pu.activo
                WHEN pr.activo IS NOT NULL THEN pr.activo
                ELSE 0
            END as tiene_acceso
        FROM permisos p
        LEFT JOIN permisos_roles pr ON p.id = pr.id_permiso AND pr.id_rol = ?
        LEFT JOIN permisos_usuarios pu ON p.id = pu.id_permiso AND pu.id_usuario = ?
        WHERE p.slug = ? AND p.estatus = 1";

        $stmt = $this->db->query($sql, [$id_rol, $id_usuario, $slug_permiso]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $tiene_permiso = $resultado ? (int)$resultado['tiene_acceso'] : 0;
        $mensaje = $tiene_permiso == 0 ? 'No tiene permiso' : 'Tiene permiso';

        return array('estatus' => $tiene_permiso, 'mensaje' => $mensaje);
    }

    public function obtenerPermisosExistentes() {
        $count = $this->db->count('categorias_permisos', ' estatus = 1', []);
        if ($count > 0) {
            $data_array = $this->db->select(
                "SELECT p.*, cp.nombre categoria FROM permisos p
                 LEFT JOIN categorias_permisos cp ON cp.id = p.id_categoria
                 ORDER BY p.id_categoria, p.bandera ASC",
                []
            );
            return array('estatus' => true, 'mensaje' => 'Se encontraron permisos', 'data' => $data_array);
        }

        return array('estatus' => false, 'mensaje' => 'No se encontraron permisos', 'data' => []);
    }

    public function obtenerPermisosXUsuario($id_usuario) {
        $usuario_model = new Usuario;
        $usuario = $usuario_model->obtenerPorIDUsuario($id_usuario);

        if (!$usuario) {
            return array('estatus' => false, 'mensaje' => 'Usuario no encontrado', 'data' => []);
        }

        $id_rol = $usuario['id_rol'];
        $count = $this->db->count('permisos', ' estatus = ?', [1]);

        if ($count > 0) {
            $sql = "SELECT
                p.id,
                p.permiso,
                p.slug,
                p.id_categoria as id_categoria,
                p.descripcion,
                cp.nombre AS categoria,
                p.tipo as tipo,
                r.nombre AS rol,
                pr.activo AS valor_rol,
                pu.activo AS valor_usuario,
                pu.id AS id_registro_pu
            FROM permisos p
            LEFT JOIN categorias_permisos cp ON cp.id = p.id_categoria
            LEFT JOIN permisos_roles pr ON pr.id_permiso = p.id AND pr.id_rol = ?
            LEFT JOIN roles r ON r.id = pr.id_rol
            LEFT JOIN permisos_usuarios pu ON pu.id_permiso = p.id AND pu.id_usuario = ?
            ORDER BY p.id_categoria, p.bandera ASC";

            $data_array = $this->db->select($sql, [$id_rol, $id_usuario]);
            return array('estatus' => true, 'mensaje' => 'Se encontraron permisos', 'data' => $data_array);
        }

        return array('estatus' => false, 'mensaje' => 'No se encontraron permisos', 'data' => []);
    }

    public function traerPermisosFaltantes($id_usuario) {
        $count = $this->db->count('categorias_permisos', ' estatus = 1', []);
        if ($count > 0) {
            $sql = "SELECT p.*, cp.nombre AS categoria
            FROM permisos p
            LEFT JOIN categorias_permisos cp ON cp.id = p.id_categoria
            LEFT JOIN permisos_usuarios pu ON pu.id_permiso = p.id AND pu.id_usuario = ?
            WHERE pu.id_permiso IS NULL
            ORDER BY p.id_categoria, p.bandera ASC";
            return array('estatus' => true, 'mensaje' => 'Se encontraron permisos', 'data' => $this->db->select($sql, [$id_usuario]));
        }
        return array('estatus' => false, 'mensaje' => 'No se encontraron permisos', 'data' => []);
    }

    public function actualizarPermisoUsuario($id_usuario, $id_permiso, $valor, $accion) {
        $usuario_model = new Usuario;
        $count_permiso = $this->db->count('permisos', 'id = ?', [$id_permiso]);

        if ($count_permiso > 0) {
            $usuario = $usuario_model->obtenerPorIDUsuario($id_usuario);
            if ($usuario != '') {
                $id_rol = $usuario['id_rol'];
                $nombre_rol = $usuario['nombre_rol'];
                $count_usuario_permiso = $this->db->count('permisos_usuarios', 'id_permiso = ? AND id_usuario = ?', [$id_permiso, $id_usuario]);
                $count_permiso_rol = $this->db->count('permisos_roles', 'id_permiso = ? AND id_rol = ?', [$id_permiso, $id_rol]);

                if ($accion == 'actualizar') {
                    if ($count_usuario_permiso > 0) {
                        if ($valor == 0) {
                            $this->db->delete('permisos_usuarios', 'id_usuario = ? AND id_permiso = ?', [$id_usuario, $id_permiso]);
                        } else {
                            $this->db->update('permisos_usuarios', ['activo' => $valor], 'id_usuario = ? AND id_permiso = ?', [$id_usuario, $id_permiso]);
                        }
                    } else {
                        $this->db->insert('permisos_usuarios', ['id_permiso' => $id_permiso, 'id_usuario' => $id_usuario, 'activo' => $valor]);
                    }
                } else if ($accion == 'reset') {
                    $this->db->delete('permisos_usuarios', 'id_usuario = ? AND id_permiso = ?', [$id_usuario, $id_permiso]);
                }

                $data = [];
                $permiso_usuario = $this->db->select('SELECT * FROM permisos_usuarios WHERE id_permiso =? AND id_usuario = ?', [$id_permiso, $id_usuario]);
                if (!empty($permiso_usuario)) {
                    $valor_usuario = $permiso_usuario[0]['activo'];
                } else {
                    $valor_usuario = 'x';
                    $data['switch_off'] = ($accion == 'actualizar');
                }

                if ($count_permiso_rol > 0) {
                    $permiso_rol = $this->db->select('SELECT * FROM permisos_roles WHERE id_permiso =? AND id_rol = ?', [$id_permiso, $id_rol]);
                    $valor_rol = $permiso_rol[0]['activo'];
                } else {
                    $valor_rol = 0;
                }

                if ($valor_usuario == 0 && $valor_rol == 1) {
                    $data['switch'] = 3;
                } else if ($valor_rol == 1) {
                    $data['switch'] = 2;
                } else if ($valor_usuario == 1) {
                    $data['switch'] = 1;
                } else {
                    $data['switch'] = 0;
                }
                $data['id_permiso'] = $id_permiso;
                $data['nombre_rol'] = $nombre_rol;
                return array('estatus' => true, 'mensaje' => 'Se actualizó permiso', 'data' => $data);
            }
            return array('estatus' => false, 'mensaje' => 'No se encontró usuario', 'data' => []);
        }
        return array('estatus' => false, 'mensaje' => 'No se encontraron permisos', 'data' => []);
    }

    public function actualizarPermisoRol($id_rol, $id_permiso, $valor) {
        $count_permiso = $this->db->count('permisos', 'id = ?', [$id_permiso]);
        if ($count_permiso > 0) {
            $no_roles = $this->db->count('roles', 'id = ?', [$id_rol]);
            if ($no_roles > 0) {
                $count_permiso_rol = $this->db->count('permisos_roles', 'id_permiso = ? AND id_rol = ?', [$id_permiso, $id_rol]);
                if ($count_permiso_rol > 0) {
                    if ($valor == 0) {
                        $this->db->delete('permisos_roles', 'id_rol = ? AND id_permiso = ?', [$id_rol, $id_permiso]);
                    } else {
                        $this->db->update('permisos_roles', ['activo' => $valor], 'id_rol = ? AND id_permiso = ?', [$id_rol, $id_permiso]);
                    }
                } else {
                    $this->db->insert('permisos_roles', ['id_permiso' => $id_permiso, 'id_rol' => $id_rol, 'activo' => $valor]);
                }
                return array('estatus' => true, 'mensaje' => 'Permiso actualizado correctamente', 'data' => []);
            }
            return array('estatus' => false, 'mensaje' => 'No se encontró un rol con ese ID', 'data' => []);
        }
        return array('estatus' => false, 'mensaje' => 'No se encontró un permiso con ese ID', 'data' => []);
    }

    public function registrarPermiso($d) {
        $select = $this->db->select('SELECT * FROM categorias_permisos cp INNER JOIN permisos p ON p.id_categoria = cp.id WHERE p.id_categoria = ?', [$d['id_categoria']]);
        $banderas = array_column($select, 'bandera');
        $nueva_bandera = !empty($banderas) ? max($banderas) + 1 : 1;

        $id_nuevo = $this->db->insert('permisos', [
            'slug' => $d['slug'],
            'permiso' => $d['permiso'],
            'descripcion' => $d['descripcion'],
            'tipo' => $d['tipo'],
            'estatus' => 1,
            'id_categoria' => $d['id_categoria'],
            'bandera' => $nueva_bandera
        ]);
        return array('estatus' => true, 'mensaje' => 'Permiso registrado exitosamente ID: ' . $id_nuevo, 'data' => ['id_permiso' => $id_nuevo]);
    }
}
