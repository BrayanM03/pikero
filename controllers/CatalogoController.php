<?php
require_once __DIR__ . '/../models/Catalogo.php';
require_once __DIR__ . '/../controllers/DataTableController.php';
require_once __DIR__ . '/../utils/Tenant.php';

class CatalogoController extends DataTableController {
    public function __construct($tipo = 'categorias') {
        parent::__construct();
        $this->model = new Catalogo($tipo, Tenant::idFerreteria());
    }

    public function setTipo($tipo) {
        $this->model->setTipo($tipo);
        return $this;
    }

    public function datatable() {
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

    public function registrar($tipo_resp, $data) {
        $resp = $this->model->registrar($data);
        if ($tipo_resp == 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function actualizar($tipo_resp, $data) {
        $resp = $this->model->actualizar($data['id'], $data);
        if ($tipo_resp == 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function obtener($tipo_resp, $id) {
        $resp = $this->model->obtener($id);
        if ($tipo_resp == 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function cancelar($tipo_resp, $id) {
        $resp = $this->model->cancelar($id);
        if ($tipo_resp == 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function combo($tipo_resp) {
        $resp = $this->model->combo();
        if ($tipo_resp == 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }
}
