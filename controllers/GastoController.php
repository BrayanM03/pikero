<?php
require_once __DIR__ . '/../models/Gasto.php';
require_once __DIR__ . '/../utils/Tenant.php';

class GastoController {
    private Gasto $model;

    public function __construct() {
        $this->model = new Gasto(Tenant::idFerreteria(), Tenant::idSucursal());
    }

    public function datatable(): void {
        echo json_encode($this->model->datatable(array_merge($_GET, $_POST)), JSON_UNESCAPED_UNICODE);
    }

    public function categorias(): array {
        return ['estatus' => true, 'data' => $this->model->categorias()];
    }

    public function crearCategoria(string $nombre): array {
        return $this->model->crearCategoria($nombre);
    }

    public function registrar(array $data, ?array $file): array {
        return $this->model->registrar($data, $file);
    }

    public function obtener(int $id): array {
        return $this->model->obtener($id);
    }

    public function actualizar(int $id, array $data, ?array $file): array {
        return $this->model->actualizar($id, $data, $file);
    }

    public function cancelar(int $id): array {
        return $this->model->cancelar($id);
    }
}