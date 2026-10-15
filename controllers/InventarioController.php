<?php
require_once __DIR__ . '/../models/Inventario.php';
require_once __DIR__ . '/../controllers/DataTableController.php';
require_once __DIR__ . '/../utils/Tenant.php';

class InventarioController extends DataTableController {
    public function __construct() {
        parent::__construct();
        $this->model = new Inventario(Tenant::idFerreteria(), Tenant::idSucursal());
    }

    public function datatable_inventario(): void {
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

    public function ajustar(int $tipo_resp, array $data) {
        $id_producto = (int)($data['id_producto'] ?? 0);
        $tipo = trim($data['tipo'] ?? 'entrada');
        $cantidad = (float)($data['cantidad'] ?? 0);
        $motivo = trim($data['motivo'] ?? '');

        $resp = $this->model->ajustarStock($id_producto, $tipo, $cantidad, $motivo);
        if ($tipo_resp === 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function movimientos(int $tipo_resp, int $id_producto) {
        $resp = $this->model->movimientos($id_producto);
        if ($tipo_resp === 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function historial(int $tipo_resp) {
        $resp = $this->model->historial();
        if ($tipo_resp === 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }
}
