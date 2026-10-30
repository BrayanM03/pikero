<?php 
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/dates.php';
require_once __DIR__ . '/../models/Datatable.php';
require_once __DIR__ . '/../utils/Tenant.php';

class Usuario extends Datatable {
    private $tabla_usuarios = 'vista_usuarios';
    private $fecha;
    private $id_ferreteria;

    public function __construct($id_ferreteria = null) {
        $this->db = new Database();
        $this->fecha = new Date();
        $this->id_ferreteria = $id_ferreteria ?? Tenant::idFerreteria();
    }

    private function filtroTenant() {
        return [
            'where' => 'id_ferreteria = :id_ferreteria',
            'params' => [':id_ferreteria' => $this->id_ferreteria]
        ];
    }

    public function datatablesUsuarios($id_filtro, $start, $length, $search, $orderColumn, $orderDir) {
        $filtro = $this->filtroTenant();
        return $this->getDataTable(
            $this->tabla_usuarios,
            $start,
            $length,
            $search,
            $orderColumn,
            $orderDir,
            ['id', 'nombre', 'apellido', 'usuario'],
            $filtro['where'],
            $filtro['params']
        );
    }

    public function contarUsuarios($id_filtro) {
        $filtro = $this->filtroTenant();
        return $this->countAll($this->tabla_usuarios, $filtro['where'], $filtro['params']);
    }
 
    public function contarUsuariosFiltrados($id_filtro, $search) {
        $filtro = $this->filtroTenant();
        return $this->countFiltered($this->tabla_usuarios, $search, ['nombre', 'apellido', 'usuario'], $filtro['where'], $filtro['params']);
    }

    public function registrarUsuario($nombre, $apellidos, $id_sucursal, $telefono, $usuario, $password, $rol, $cargo, $correo = '', $id_ferreteria = null) {
        $id_ferreteria = $id_ferreteria ?? $this->id_ferreteria;
        $ferreteria_ok = $this->db->select(
            'SELECT id FROM ferreterias WHERE id = ? AND estatus = 1',
            [$id_ferreteria]
        );
        if (empty($ferreteria_ok)) {
            return ['estatus' => false, 'mensaje' => 'La ferretería seleccionada no está disponible'];
        }

        $existe = $this->obtenerPorUsuario($usuario);
        if ($existe) {
            return [
                'estatus' => false,
                'mensaje' => "El nombre de usuario '{$usuario}' ya se encuentra registrado."
            ];
        }

        $sucursal_ok = $this->db->select(
            'SELECT id FROM sucursales WHERE id = ? AND id_ferreteria = ? AND estatus = 1',
            [$id_sucursal, $id_ferreteria]
        );
        if (empty($sucursal_ok)) {
            return ['estatus' => false, 'mensaje' => 'La sucursal no pertenece a este negocio'];
        }

        $rol_ok = $this->db->select(
            "SELECT id FROM roles WHERE id = ? AND estatus = 1 AND slug <> 'superadmin'",
            [$rol]
        );
        if (empty($rol_ok)) {
            return ['estatus' => false, 'mensaje' => 'El rol seleccionado no está disponible'];
        }

        $password_encriptada = password_hash($password, PASSWORD_BCRYPT);
        $fecha_registro = $this->fecha->obtenerFechaRegistro();

        $id_nuevo = $this->db->insert('usuarios', [
            'nombre' => $nombre,
            'apellido' => $apellidos,
            'telefono' => $telefono,
            'correo' => $correo,
            'estatus' => 1,
            'cargo' => $cargo,
            'fecha_ingreso' => $fecha_registro,
            'usuario' => $usuario,
            'contraseña' => $password_encriptada,
            'id_sucursal' => $id_sucursal,
            'id_ferreteria' => $id_ferreteria,
            'fecha_registro' => $fecha_registro,
            'id_rol' => $rol,
        ]);

        if (!$id_nuevo) {
            return ['estatus' => false, 'mensaje' => 'Error al insertar en la base de datos'];
        }

        $id_nuevo = intval($id_nuevo);
        $user_data = $this->obtenerPorIDUsuario($id_nuevo);

        return [
            'estatus' => true,
            'mensaje' => 'Registro insertado correctamente',
            'nuevo' => $id_nuevo,
            'usuario' => $user_data
        ];
    }

    public function obtenerPorUsuario($username) {
        $stmt = $this->db->query(
            "SELECT u.*, r.nombre as nombre_rol, r.slug as slug_rol,
                    s.nombre as sucursal, s.logo as logo_sucursal,
                    f.nombre as ferreteria, f.logo as logo_ferreteria,
                    f.tipo_reporte
             FROM usuarios u
             INNER JOIN roles r ON u.id_rol = r.id
             INNER JOIN sucursales s ON s.id = u.id_sucursal
             INNER JOIN ferreterias f ON f.id = u.id_ferreteria
             WHERE usuario = ?",
            [$username]
        );
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerPorIDUsuario($id_usuario) {
        $params = [$id_usuario];
        $sql = "SELECT u.*, r.nombre as nombre_rol, r.slug as slug_rol,
                    s.nombre as sucursal, s.logo as logo_sucursal,
                    f.nombre as ferreteria, f.logo as logo_ferreteria
             FROM usuarios u
             INNER JOIN roles r ON u.id_rol = r.id
             INNER JOIN sucursales s ON s.id = u.id_sucursal
             INNER JOIN ferreterias f ON f.id = u.id_ferreteria
             WHERE u.id = ?";
        if ($this->id_ferreteria) {
            $sql .= " AND u.id_ferreteria = ?";
            $params[] = $this->id_ferreteria;
        }
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerPerfil($id_usuario) {
        $stmt = $this->db->query(
            "SELECT u.id, u.nombre, u.apellido, u.telefono, u.correo, u.cargo, u.usuario,
                    u.foto_perfil, u.id_ferreteria, u.id_sucursal,
                    r.nombre AS nombre_rol, r.slug AS slug_rol,
                    f.nombre AS ferreteria
             FROM usuarios u
             INNER JOIN roles r ON r.id = u.id_rol
             INNER JOIN ferreterias f ON f.id = u.id_ferreteria
             WHERE u.id = ?",
            [$id_usuario]
        );
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function actualizarPerfil($id_usuario, array $data) {
        $perfil = $this->obtenerPerfil($id_usuario);
        if (!$perfil) {
            return ['estatus' => false, 'mensaje' => 'No se encontró el perfil'];
        }

        $nombre = trim((string)($data['nombre'] ?? $perfil['nombre']));
        $apellido = trim((string)($data['apellido'] ?? $perfil['apellido']));
        $usuario = trim((string)($data['usuario'] ?? $perfil['usuario']));
        $correo = trim((string)($data['correo'] ?? $perfil['correo']));
        if ($nombre === '' || $apellido === '' || $usuario === '') {
            return ['estatus' => false, 'mensaje' => 'Nombre, apellidos y usuario son obligatorios'];
        }
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return ['estatus' => false, 'mensaje' => 'El correo no tiene un formato válido'];
        }

        $usuario_existente = $this->db->select(
            'SELECT id FROM usuarios WHERE usuario = ? AND id <> ?',
            [$usuario, $id_usuario]
        );
        if (!empty($usuario_existente)) {
            return ['estatus' => false, 'mensaje' => 'Ese nombre de usuario ya está en uso'];
        }

        $actualizacion = [
            'nombre' => $nombre,
            'apellido' => $apellido,
            'usuario' => $usuario,
            'correo' => $correo,
            'telefono' => trim((string)($data['telefono'] ?? $perfil['telefono'] ?? '')),
            'cargo' => trim((string)($data['cargo'] ?? $perfil['cargo'] ?? ''))
        ];
        $nueva_password = (string)($data['password_nueva'] ?? '');
        if ($nueva_password !== '') {
            $password_actual = (string)($data['password_actual'] ?? '');
            $password_confirmacion = (string)($data['password_confirmacion'] ?? '');
            $usuario_password = $this->db->select('SELECT `contraseña` FROM usuarios WHERE id = ?', [$id_usuario]);
            if (empty($usuario_password) || !password_verify($password_actual, $usuario_password[0]['contraseña'])) {
                return ['estatus' => false, 'mensaje' => 'La contraseña actual es incorrecta'];
            }
            if (strlen($nueva_password) < 8) {
                return ['estatus' => false, 'mensaje' => 'La nueva contraseña debe tener al menos 8 caracteres'];
            }
            if ($nueva_password !== $password_confirmacion) {
                return ['estatus' => false, 'mensaje' => 'La confirmación de contraseña no coincide'];
            }
            $actualizacion['contraseña'] = password_hash($nueva_password, PASSWORD_BCRYPT);
        }

        $this->db->update('usuarios', $actualizacion, 'id = ?', [$id_usuario]);
        return ['estatus' => true, 'mensaje' => 'Perfil actualizado correctamente', 'data' => $this->obtenerPerfil($id_usuario)];
    }

    public function obtenerFerreteriaActiva($id_ferreteria) {
        $data = $this->db->select(
            "SELECT f.id, f.nombre, f.logo,
                    s.id AS id_sucursal, s.nombre AS sucursal, s.logo AS logo_sucursal
             FROM ferreterias f
             LEFT JOIN sucursales s ON s.id = (
                 SELECT s2.id FROM sucursales s2
                 WHERE s2.id_ferreteria = f.id AND s2.estatus = 1
                 ORDER BY s2.id ASC LIMIT 1
             )
             WHERE f.id = ? AND f.estatus = 1",
            [$id_ferreteria]
        );
        return $data[0] ?? null;
    }

    public function actualizarUsuario($id_usuario, $data_update) {
        if ($this->id_ferreteria) {
            return $this->db->update('usuarios', $data_update, 'id = ?', 
            [$id_usuario]);
        }
        return $this->db->update('usuarios', $data_update, 'id = ?', [$id_usuario]);
    }

    public function combo($sql_where = '') {
        $params[':id_ferreteria'] = $this->id_ferreteria;
        $stmt = $this->db->query(
            "SELECT * FROM vista_usuarios WHERE estatus = 1 AND id_ferreteria = :id_ferreteria" . $sql_where . ' ORDER BY nombre ASC',
            $params
        );
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array('estatus' => true, 'mensaje' => 'Usuarios encontrados', 'data' => $data);
    }
}
