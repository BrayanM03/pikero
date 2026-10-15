<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/dates.php';
require_once __DIR__ . '/../models/Datatable.php';
require_once __DIR__ . '/../utils/Tenant.php';

class Proveedor extends Datatable {
    private string $tabla = 'proveedores';
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
            ['id', 'nombre', 'contacto', 'telefono', 'correo'],
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
            ['nombre', 'contacto', 'telefono', 'correo'],
            $filtro['where'],
            $filtro['params']
        );
    }

    public function registrar(array $data): array {
        $nombre = trim($data['nombre'] ?? '');
        if (empty($nombre)) {
            return ['estatus' => false, 'mensaje' => 'El nombre del proveedor es obligatorio'];
        }

        $id = $this->db->insert($this->tabla, [
            'id_ferreteria' => $this->id_ferreteria,
            'nombre' => $nombre,
            'rfc' => trim($data['rfc'] ?? ''),
            'telefono' => trim($data['telefono'] ?? ''),
            'correo' => trim($data['correo'] ?? ''),
            'direccion' => trim($data['direccion'] ?? ''),
            'contacto' => trim($data['contacto'] ?? ''),
            'estatus' => 1,
            'fecha_registro' => $this->fecha->obtenerFechaRegistro()
        ]);

        return $id
            ? ['estatus' => true, 'mensaje' => 'Proveedor registrado correctamente', 'nuevo' => $id]
            : ['estatus' => false, 'mensaje' => 'No se pudo registrar el proveedor'];
    }

    public function actualizar(int $id, array $data): array {
        $nombre = trim($data['nombre'] ?? '');
        if (empty($nombre)) {
            return ['estatus' => false, 'mensaje' => 'El nombre del proveedor es obligatorio'];
        }

        $filas = $this->db->update($this->tabla, [
            'nombre' => $nombre,
            'rfc' => trim($data['rfc'] ?? ''),
            'telefono' => trim($data['telefono'] ?? ''),
            'correo' => trim($data['correo'] ?? ''),
            'direccion' => trim($data['direccion'] ?? ''),
            'contacto' => trim($data['contacto'] ?? '')
        ], 'id = ? AND id_ferreteria = ?', [$id, $this->id_ferreteria]);

        return ['estatus' => $filas >= 0, 'mensaje' => 'Proveedor actualizado correctamente'];
    }

    public function obtener(int $id): array {
        $data = $this->db->select(
            "SELECT * FROM {$this->tabla} WHERE id = ? AND id_ferreteria = ?",
            [$id, $this->id_ferreteria]
        );
        return empty($data)
            ? ['estatus' => false, 'mensaje' => 'Proveedor no encontrado', 'data' => []]
            : ['estatus' => true, 'mensaje' => 'OK', 'data' => $data[0]];
    }

    public function cancelar(int $id): array {
        $this->db->update($this->tabla, ['estatus' => 0], 'id = ? AND id_ferreteria = ?', [$id, $this->id_ferreteria]);
        return ['estatus' => true, 'mensaje' => 'Proveedor desactivado'];
    }

    public function combo(): array {
        $data = $this->db->select(
            "SELECT id, nombre, contacto, telefono FROM {$this->tabla} WHERE estatus = 1 AND id_ferreteria = ? ORDER BY nombre ASC",
            [$this->id_ferreteria]
        );
        return ['estatus' => true, 'mensaje' => 'OK', 'data' => $data];
    }
}
