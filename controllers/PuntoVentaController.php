<?php
require_once __DIR__ . '/../models/PuntoVenta.php';
require_once __DIR__ . '/../models/Producto.php';
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../models/Catalogo.php';
require_once __DIR__ . '/../utils/Tenant.php';

class PuntoVentaController {
    private PuntoVenta $model;
    private Producto $productoModel;
    private Cliente $clienteModel;
    private Catalogo $catalogoModel;

    public function __construct() {
        $this->model = new PuntoVenta(Tenant::idFerreteria(), Tenant::idSucursal());
        $this->productoModel = new Producto(Tenant::idFerreteria());
        $this->clienteModel = new Cliente(Tenant::idFerreteria());
        $this->catalogoModel = new Catalogo('formas_pago', Tenant::idFerreteria());
    }

    public function cobrar(int $tipo_resp, array $data) {
        $resp = $this->model->procesarVenta($data);
        if ($tipo_resp === 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function buscarProducto(int $tipo_resp, string $q) {
        $resp = $this->productoModel->buscarParaPOS($q);
        if ($tipo_resp === 1) {
            echo json_encode($resp);
        } else {
            return $resp;
        }
    }

    public function catalogosPOS(int $tipo_resp) {
        $clientes = $this->clienteModel->combo();
        $formasPago = $this->catalogoModel->combo();
        $formasPagoDisponibles = array_values(array_filter(
            $formasPago['data'] ?? [],
            static fn($forma) => !preg_match('/^cr[eé]dito$/iu', trim($forma['nombre'] ?? ''))
        ));

        $data = [
            'clientes' => $clientes['data'] ?? [],
            'formas_pago' => $formasPagoDisponibles
        ];

        if ($tipo_resp === 1) {
            echo json_encode(['estatus' => true, 'mensaje' => 'OK', 'data' => $data]);
        } else {
            return $data;
        }
    }
}
