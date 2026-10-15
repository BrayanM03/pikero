<?php
require_once __DIR__ . '/../models/Compra.php';
require_once __DIR__ . '/../controllers/DataTableController.php';
require_once __DIR__ . '/../utils/Tenant.php';

class CompraController extends DataTableController {
    public function __construct() {
        parent::__construct();
        $this->model = new Compra(Tenant::idFerreteria(), Tenant::idSucursal());
    }

    public function datatable_compras(): void {
        $this->datatable_general();
    }

    protected function getModelData($id_filtro, $start, $length, $search, $orderColumnName, $orderDir, $filtros) {
        return $this->model->datatables($id_filtro, $start, $length, $search, $orderColumnName, $orderDir);
    }

    protected function getModelTotal($id_filtro, $filtros) {
        return $this->model->contar($id_filtro);
    }

    protected function getModelFilteredTotal($id_filtro, $search, $filtros) {
        return $this->model->contarFiltrados($id_filtro, $search);
    }

    public function registrar(int $tipo_resp, array $data) {
        $resp = $this->model->registrar($data);
        if ($tipo_resp === 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function detalle(int $tipo_resp, int $id) {
        $resp = $this->model->detalle($id);
        if ($tipo_resp === 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }
}