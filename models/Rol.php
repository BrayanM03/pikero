<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/dates.php';
require_once __DIR__ . '/../models/Datatable.php';

class Rol extends Datatable {
    private $tabla = 'roles';

    public function __construct() {
        $this->db = new Database();
    }

    public function datatables($id_filtro, $start, $length, $search, $orderColumn, $orderDir) {
        return $this->getDataTable(
            $this->tabla,
            $start,
            $length,
            $search,
            $orderColumn,
            $orderDir,
            ['nombre'],
            '',//  "slug <> 'superadmin'",
            []
        );
    }

    public function contar($id_filtro) {
        if (is_string($id_filtro) && $id_filtro !== '') {
            return $this->countAll($this->tabla, ltrim($id_filtro, ' AND '), func_num_args() > 1 ? func_get_arg(1) : []);
        }
        return $this->countAll($this->tabla, "slug <> 'superadmin'", []);
    }

    public function contarFiltrados($id_filtro, $search) {
        return $this->countFiltered($this->tabla, $search, ['nombre'], "slug <> 'superadmin'", []);
    }

    public function obtenerRol($id_rol) {
        $data = $this->db->select("SELECT * FROM roles WHERE id = ? AND slug <> 'superadmin'", [$id_rol]);
        if (!empty($data)) {
            return array('estatus' => true, 'mensaje' => 'Se encontró rol', 'data' => $data);
        }
        return array('estatus' => false, 'mensaje' => 'No se encontró', 'data' => []);
    }

    public function obtenerRoles($sql_where = '', $params = []) {
        $stmt = $this->db->query("SELECT * FROM roles WHERE estatus = 1 " . $sql_where . ' ORDER BY id ASC', $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
