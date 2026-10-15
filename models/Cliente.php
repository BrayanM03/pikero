<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/dates.php';
require_once __DIR__ . '/../models/Datatable.php';
require_once __DIR__ . '/../utils/Tenant.php';

class Cliente extends Datatable {
    private string $tabla = 'clientes';
    private Date $fecha;
    private ?int $id_ferreteria;

    public function __construct(?int $id_ferreteria = null) {
        $this->db = new Database();
        $this->fecha = new Date();
        $this->id_ferreteria = $id_ferreteria ?? Tenant::idFerreteria();
    }

    private function filtroTenant(): array {
        return [
            'where' => 'id_ferreteria = :id_ferreteria',
            'params' => [':id_ferreteria' => $this->id_ferreteria]
        ];
    }

    public function datatables($id_filtro, $start, $length, $search, $orderColumn, $orderDir) {
        $filtro = $this->filtroTenant();
        return $this->getDataTable(
            $this->tabla,
            $start,
            $length,
            $search,
            $orderColumn,
            $orderDir,
            ['id', 'nombre', 'rfc', 'telefono', 'correo'],
            $filtro['where'],
            $filtro['params']
        );
    }

    public function contar($id_filtro): int {
        $filtro = $this->filtroTenant();
        return $this->countAll($this->tabla, $filtro['where'], $filtro['params']);
    }

    public function contarFiltrados($id_filtro, $search): int {
        $filtro = $this->filtroTenant();
        return $this->countFiltered(
            $this->tabla,
            $search,
            ['nombre', 'rfc', 'telefono', 'correo'],
            $filtro['where'],
            $filtro['params']
        );
    }

    public function registrar(array $data): array {
        $nombre = trim($data['nombre'] ?? '');
        if (empty($nombre)) {
            return ['estatus' => false, 'mensaje' => 'El nombre del cliente es obligatorio'];
        }

        $id = $this->db->insert($this->tabla, [
            'id_ferreteria' => $this->id_ferreteria,
            'nombre' => $nombre,
            'razon_social' => trim($data['razon_social'] ?? ''),
            'rfc' => trim($data['rfc'] ?? ''),
            'telefono' => trim($data['telefono'] ?? ''),
            'correo' => trim($data['correo'] ?? ''),
            'direccion' => trim($data['direccion'] ?? ''),
            'codigo_postal' => trim($data['codigo_postal'] ?? ''),
            'regimen_fiscal' => trim($data['regimen_fiscal'] ?? ''),
            'uso_cfdi' => trim($data['uso_cfdi'] ?? ''),
            'es_publico' => !empty($data['es_publico']) ? 1 : 0,
            'estatus' => 1,
            'fecha_registro' => $this->fecha->obtenerFechaRegistro()
        ]);

        return $id
            ? ['estatus' => true, 'mensaje' => 'Cliente registrado correctamente', 'nuevo' => $id]
            : ['estatus' => false, 'mensaje' => 'No se pudo registrar el cliente'];
    }

    public function actualizar(int $id, array $data): array {
        $nombre = trim($data['nombre'] ?? '');
        if (empty($nombre)) {
            return ['estatus' => false, 'mensaje' => 'El nombre del cliente es obligatorio'];
        }

        $filas = $this->db->update($this->tabla, [
            'nombre' => $nombre,
            'razon_social' => trim($data['razon_social'] ?? ''),
            'rfc' => trim($data['rfc'] ?? ''),
            'telefono' => trim($data['telefono'] ?? ''),
            'correo' => trim($data['correo'] ?? ''),
            'direccion' => trim($data['direccion'] ?? ''),
            'codigo_postal' => trim($data['codigo_postal'] ?? ''),
            'regimen_fiscal' => trim($data['regimen_fiscal'] ?? ''),
            'uso_cfdi' => trim($data['uso_cfdi'] ?? '')
        ], 'id = ? AND id_ferreteria = ?', [$id, $this->id_ferreteria]);

        return ['estatus' => $filas >= 0, 'mensaje' => 'Cliente actualizado correctamente'];
    }

    public function obtener(int $id): array {
        $data = $this->db->select(
            "SELECT * FROM {$this->tabla} WHERE id = ? AND id_ferreteria = ?",
            [$id, $this->id_ferreteria]
        );
        return empty($data)
            ? ['estatus' => false, 'mensaje' => 'Cliente no encontrado', 'data' => []]
            : ['estatus' => true, 'mensaje' => 'OK', 'data' => $data[0]];
    }

    public function cancelar(int $id): array {
        $this->db->update($this->tabla, ['estatus' => 0], 'id = ? AND id_ferreteria = ?', [$id, $this->id_ferreteria]);
        return ['estatus' => true, 'mensaje' => 'Cliente desactivado'];
    }

    public function combo(): array {
        $data = $this->db->select(
            "SELECT id, nombre, razon_social, rfc, telefono, correo, codigo_postal, regimen_fiscal, uso_cfdi, es_publico FROM {$this->tabla} WHERE estatus = 1 AND id_ferreteria = ? ORDER BY es_publico DESC, nombre ASC",
            [$this->id_ferreteria]
        );
        return ['estatus' => true, 'mensaje' => 'OK', 'data' => $data];
    }
}
