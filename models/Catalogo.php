<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/dates.php';
require_once __DIR__ . '/../models/Datatable.php';
require_once __DIR__ . '/../utils/Tenant.php';

class Catalogo extends Datatable {
    private $tablas = [
        'categorias' => ['tabla' => 'categorias', 'columnas' => ['id', 'nombre', 'descripcion'], 'campos' => ['nombre', 'descripcion']],
        'marcas' => ['tabla' => 'marcas', 'columnas' => ['id', 'nombre', 'descripcion'], 'campos' => ['nombre', 'descripcion']],
        'unidades' => ['tabla' => 'unidades', 'columnas' => ['id', 'nombre', 'abreviatura', 'descripcion'], 'campos' => ['nombre', 'abreviatura', 'descripcion']],
        'formas_pago' => ['tabla' => 'formas_pago', 'columnas' => ['id', 'nombre', 'descripcion'], 'campos' => ['nombre', 'descripcion']],
    ];
    private $fecha;
    private $id_ferreteria;
    private $tipo;

    public function __construct($tipo = 'categorias', $id_ferreteria = null) {
        $this->db = new Database();
        $this->fecha = new Date();
        $this->id_ferreteria = $id_ferreteria ?? Tenant::idFerreteria();
        $this->setTipo($tipo);
    }

    public function setTipo($tipo) {
        if (!isset($this->tablas[$tipo])) {
            $tipo = 'categorias';
        }
        $this->tipo = $tipo;
        return $this;
    }

    public function config() {
        return $this->tablas[$this->tipo];
    }

    private function filtroTenant() {
        return [
            'where' => 'id_ferreteria = :id_ferreteria',
            'params' => [':id_ferreteria' => $this->id_ferreteria]
        ];
    }

    public function datatables($id_filtro, $start, $length, $search, $orderColumn, $orderDir) {
        $cfg = $this->config();
        $filtro = $this->filtroTenant();
        return $this->getDataTable($cfg['tabla'], $start, $length, $search, $orderColumn, $orderDir, $cfg['columnas'], $filtro['where'], $filtro['params']);
    }

    public function contar($id_filtro) {
        $filtro = $this->filtroTenant();
        return $this->countAll($this->config()['tabla'], $filtro['where'], $filtro['params']);
    }

    public function contarFiltrados($id_filtro, $search) {
        $filtro = $this->filtroTenant();
        return $this->countFiltered($this->config()['tabla'], $search, $this->config()['columnas'], $filtro['where'], $filtro['params']);
    }

    public function registrar($data) {
        $cfg = $this->config();
        $payload = ['id_ferreteria' => $this->id_ferreteria, 'estatus' => 1, 'fecha_registro' => $this->fecha->obtenerFechaRegistro()];
        foreach ($cfg['campos'] as $campo) {
            $payload[$campo] = $data[$campo] ?? null;
        }
        if (empty($payload['nombre'])) {
            return ['estatus' => false, 'mensaje' => 'El nombre es obligatorio'];
        }
        $id = $this->db->insert($cfg['tabla'], $payload);
        return $id
            ? ['estatus' => true, 'mensaje' => 'Registro agregado', 'nuevo' => $id]
            : ['estatus' => false, 'mensaje' => 'No se pudo guardar'];
    }

    public function actualizar($id, $data) {
        $cfg = $this->config();
        $payload = [];
        foreach ($cfg['campos'] as $campo) {
            if (array_key_exists($campo, $data)) {
                $payload[$campo] = $data[$campo];
            }
        }
        if (empty($payload)) {
            return ['estatus' => false, 'mensaje' => 'Sin datos para actualizar'];
        }
        $this->db->update($cfg['tabla'], $payload, 'id = ? AND id_ferreteria = ?', [$id, $this->id_ferreteria]);
        return ['estatus' => true, 'mensaje' => 'Registro actualizado'];
    }

    public function obtener($id) {
        $cfg = $this->config();
        $data = $this->db->select('SELECT * FROM ' . $cfg['tabla'] . ' WHERE id = ? AND id_ferreteria = ?', [$id, $this->id_ferreteria]);
        return empty($data)
            ? ['estatus' => false, 'mensaje' => 'No se encontró', 'data' => []]
            : ['estatus' => true, 'mensaje' => 'OK', 'data' => $data[0]];
    }

    public function cancelar($id) {
        $cfg = $this->config();
        $this->db->update($cfg['tabla'], ['estatus' => 0], 'id = ? AND id_ferreteria = ?', [$id, $this->id_ferreteria]);
        return ['estatus' => true, 'mensaje' => 'Registro desactivado'];
    }

    public function combo() {
        $cfg = $this->config();
        $data = $this->db->select(
            'SELECT * FROM ' . $cfg['tabla'] . ' WHERE estatus = 1 AND id_ferreteria = ? ORDER BY nombre ASC',
            [$this->id_ferreteria]
        );
        return ['estatus' => true, 'mensaje' => 'OK', 'data' => $data];
    }
}
