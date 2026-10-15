<?php
require_once __DIR__ . '/../models/Sucursal.php';
require_once __DIR__ . '/../controllers/DataTableController.php';
require_once __DIR__ . '/../utils/Tenant.php';

class SucursalController extends DataTableController {
    public function __construct() {
        parent::__construct();
        $this->model = new Sucursal(Tenant::idFerreteria());
    } 

    public function datatable_sucursales() {
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
        $resp = $this->model->registrar($data['nombre'] ?? '', $data['direccion'] ?? '', $data['telefono'] ?? '');
        if ($tipo_resp == 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function actualizar($tipo_resp, $data) {
        $resp = $this->model->actualizar($data['id'], [
            'nombre' => $data['nombre'] ?? '',
            'direccion' => $data['direccion'] ?? '',
            'telefono' => $data['telefono'] ?? ''
        ]);
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

    public function combo($tipo_resp) {
        $resp = $this->model->combo();
        if ($tipo_resp == 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function combo_por_ferreteria($tipo_resp, $id_ferreteria) {
        if (!Tenant::esSuperAdmin()) {
            $resp = ['estatus' => false, 'mensaje' => 'No tienes permiso para consultar sucursales de otra ferretería'];
        } else {
            require_once __DIR__ . '/../models/Sucursal.php';
            $resp = (new Sucursal((int)$id_ferreteria))->combo();
        }
        if ($tipo_resp == 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function cancelar($tipo_resp, $id) {
        $resp = $this->model->actualizar($id, ['estatus' => 0]);
        $resp['mensaje'] = 'Sucursal desactivada';
        if ($tipo_resp == 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }
}
