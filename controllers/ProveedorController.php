<?php
require_once __DIR__ . '/../models/Proveedor.php';
require_once __DIR__ . '/../controllers/DataTableController.php';
require_once __DIR__ . '/../utils/Tenant.php';

class ProveedorController extends DataTableController {
    public function __construct() {
        parent::__construct();
        $this->model = new Proveedor(Tenant::idFerreteria());
    }

    public function datatable_proveedores(): void {
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

    public function actualizar(int $tipo_resp, array $data) {
        $resp = $this->model->actualizar((int)($data['id'] ?? 0), $data);
        if ($tipo_resp === 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function obtener(int $tipo_resp, int $id) {
        $resp = $this->model->obtener($id);
        if ($tipo_resp === 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function cancelar(int $tipo_resp, int $id) {
        $resp = $this->model->cancelar($id);
        if ($tipo_resp === 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function combo(int $tipo_resp) {
        $resp = $this->model->combo();
        if ($tipo_resp === 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }
}
