<?php
require_once __DIR__ . '/../models/Venta.php';
require_once __DIR__ . '/../controllers/DataTableController.php';
require_once __DIR__ . '/../utils/Tenant.php';

class VentaController extends DataTableController {
    public function __construct() {
        parent::__construct();
        $this->model = new Venta(Tenant::idFerreteria(), Tenant::idSucursal());
    }

    public function datatable_ventas(): void {
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

    public function detalle(int $tipo_resp, int $id_venta) {
        $resp = $this->model->detalle($id_venta);
        if ($tipo_resp === 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function abonar(int $tipo_resp, int $id_venta, array $data) {
        $resp = $this->model->registrarAbono($id_venta, $data);
        if ($tipo_resp === 1) echo json_encode($resp);
        return $resp;
    }

    public function cancelar(int $tipo_resp, array $data) {
        $id_venta = (int)($data['id'] ?? 0);
        $motivo = trim($data['motivo'] ?? 'Cancelación de venta solicitada por el cliente');
        $resp = $this->model->cancelarVenta($id_venta, $motivo);
        if ($tipo_resp === 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function editar(int $tipo_resp, int $id_venta, array $payload) {
        $resp = $this->model->editar($id_venta, $payload);
        if ($tipo_resp === 1) echo json_encode($resp);
        return $resp;
    }

    public function facturar(int $tipo_resp, array $data) {
        $resp = $this->model->facturar((int)($data['id'] ?? 0), $data);
        if ($tipo_resp === 1) echo json_encode($resp);
        return $resp;
    }

    public function emisores(int $tipo_resp = 1) {
        $resp = $this->model->emisores();
        if ($tipo_resp === 1) echo json_encode($resp);
        return $resp;
    }

    public function descargarFactura(int $id_venta, string $formato): array {
        return $this->model->descargarFactura($id_venta, $formato);
    }

    public function enviarFacturaPorCorreo(int $id_venta, string $correo, string $asunto, string $comentarios): array {
        return $this->model->enviarFacturaPorCorreo($id_venta, $correo, $asunto, $comentarios);
    }

    public function cancelarFactura(int $id_venta, string $motivo, string $uuidSustituto = ''): array
{
    return $this->model->cancelarFactura($id_venta, $motivo, $uuidSustituto);
}
}
