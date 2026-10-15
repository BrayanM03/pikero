<?php
require_once __DIR__ . '/../models/Caja.php';
require_once __DIR__ . '/../utils/Tenant.php';

class CajaController {
    private Caja $model;

    public function __construct() {
        $this->model = new Caja(Tenant::idFerreteria(), Tenant::idSucursal());
    }

    public function estado(int $tipo_resp) { return $this->responder($this->model->estado(), $tipo_resp); }
    public function movimientos(int $tipo_resp) { return $this->responder($this->model->movimientos(), $tipo_resp); }
    public function abrir(int $tipo_resp, array $data) { return $this->responder($this->model->abrir((float)($data['monto'] ?? 0), (string)($data['notas'] ?? '')), $tipo_resp); }
    public function movimiento(int $tipo_resp, array $data) { return $this->responder($this->model->movimiento((string)($data['tipo_movimiento'] ?? ''), (float)($data['monto'] ?? 0), (string)($data['motivo'] ?? '')), $tipo_resp); }
    public function cerrar(int $tipo_resp, array $data) { return $this->responder($this->model->cerrar((float)($data['monto'] ?? 0), (string)($data['notas'] ?? '')), $tipo_resp); }

    private function responder(array $resp, int $tipo_resp) {
        if ($tipo_resp === 1) echo json_encode($resp);
        return $resp;
    }
}