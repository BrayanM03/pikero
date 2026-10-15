<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/dates.php';
require_once __DIR__ . '/../models/Datatable.php';
require_once __DIR__ . '/../utils/Tenant.php';

class Sucursal extends Datatable {
    private $tabla = 'sucursales';
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

    public function datatables($id_filtro, $start, $length, $search, $orderColumn, $orderDir) {
        $filtro = $this->filtroTenant();
        return $this->getDataTable($this->tabla, $start, $length, $search, $orderColumn, $orderDir, ['nombre'], $filtro['where'], $filtro['params']);
    }

    public function contar($id_filtro) {
        $filtro = $this->filtroTenant();
        return $this->countAll($this->tabla, $filtro['where'], $filtro['params']);
    }

    public function contarFiltrados($id_filtro, $search) {
        $filtro = $this->filtroTenant();
        return $this->countFiltered($this->tabla, $search, ['nombre'], $filtro['where'], $filtro['params']);
    }

    public function registrar($nombre, $direccion = '', $telefono = '') {
        $id = $this->db->insert($this->tabla, [
            'id_ferreteria' => $this->id_ferreteria,
            'nombre' => $nombre,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'estatus' => 1,
            'fecha_registro' => $this->fecha->obtenerFechaRegistro()
        ]);
        return $id
            ? ['estatus' => true, 'mensaje' => 'Sucursal registrada', 'nuevo' => $id]
            : ['estatus' => false, 'mensaje' => 'No se pudo registrar la sucursal'];
    }

    public function actualizar($id, $data) {
        $filas = $this->db->update($this->tabla, $data, 'id = ? AND id_ferreteria = ?', [$id, $this->id_ferreteria]);
        return ['estatus' => $filas >= 0, 'mensaje' => 'Sucursal actualizada'];
    }

    public function obtener($id) {
        $data = $this->db->select('SELECT * FROM sucursales WHERE id = ? AND id_ferreteria = ?', [$id, $this->id_ferreteria]);
        return empty($data)
            ? ['estatus' => false, 'mensaje' => 'No se encontró', 'data' => []]
            : ['estatus' => true, 'mensaje' => 'OK', 'data' => $data[0]];
    }

    public function combo() {
        $data = $this->db->select(
            'SELECT * FROM sucursales WHERE estatus = 1 AND id_ferreteria = ? ORDER BY nombre ASC',
            [$this->id_ferreteria]
        );
        return ['estatus' => true, 'mensaje' => 'Sucursales encontradas', 'data' => $data];
    }
}
