<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/dates.php';
require_once __DIR__ . '/../utils/Tenant.php';

class Caja {
    private Database $db;
    private Date $fecha;
    private ?int $id_ferreteria;
    private ?int $id_sucursal;

    public function __construct(?int $id_ferreteria = null, ?int $id_sucursal = null) {
        $this->db = new Database();
        $this->fecha = new Date();
        $this->id_ferreteria = $id_ferreteria ?? Tenant::idFerreteria();
        $this->id_sucursal = $id_sucursal ?? (Tenant::idSucursal() ?? 1);
    }

    private function abierta(): array {
        return $this->db->select(
            "SELECT c.*, CONCAT(u.nombre, ' ', u.apellido) AS usuario
             FROM cajas c LEFT JOIN usuarios u ON u.id = c.id_usuario
             WHERE c.id_ferreteria = ? AND c.id_sucursal = ? AND c.estado = 'abierta'
             ORDER BY c.id DESC LIMIT 1",
            [$this->id_ferreteria, $this->id_sucursal]
        );
    }

    public function estado(): array {
        $caja = $this->abierta();
        return ['estatus' => true, 'abierta' => !empty($caja), 'data' => $caja[0] ?? null];
    }

    public function abrir(float $monto, string $notas = ''): array {
        if ($monto < 0) return ['estatus' => false, 'mensaje' => 'El monto de apertura no puede ser negativo'];
        if (!empty($this->abierta())) return ['estatus' => false, 'mensaje' => 'Ya existe una caja abierta en esta sucursal'];

        $id = $this->db->insert('cajas', [
            'id_ferreteria' => $this->id_ferreteria,
            'id_sucursal' => $this->id_sucursal,
            'id_usuario' => $_SESSION['id'] ?? 1,
            'monto_apertura' => $monto,
            'notas' => trim($notas) ?: null,
            'estado' => 'abierta',
            'fecha_apertura' => $this->fecha->obtenerFechaRegistro()
        ]);

        return $id ? ['estatus' => true, 'mensaje' => 'Caja abierta correctamente'] : ['estatus' => false, 'mensaje' => 'No se pudo abrir la caja'];
    }

    public function movimiento(string $tipo, float $monto, string $motivo): array {
        if (!in_array($tipo, ['entrada', 'salida'], true)) return ['estatus' => false, 'mensaje' => 'Tipo de movimiento inválido'];
        if ($monto <= 0 || trim($motivo) === '') return ['estatus' => false, 'mensaje' => 'Captura un monto y un motivo'];
        $caja = $this->abierta();
        if (empty($caja)) return ['estatus' => false, 'mensaje' => 'Primero debes abrir la caja'];

        $idCaja = (int)$caja[0]['id'];
        $campo = $tipo === 'entrada' ? 'total_entradas' : 'total_salidas';
        $this->db->update('cajas', [$campo => (float)$caja[0][$campo] + $monto], 'id = ?', [$idCaja]);
        $this->db->insert('caja_movimientos', [
            'id_caja' => $idCaja,
            'id_ferreteria' => $this->id_ferreteria,
            'id_sucursal' => $this->id_sucursal,
            'id_usuario' => $_SESSION['id'] ?? 1,
            'tipo' => $tipo,
            'monto' => $monto,
            'motivo' => trim($motivo),
            'fecha_registro' => $this->fecha->obtenerFechaRegistro()
        ]);
        return ['estatus' => true, 'mensaje' => 'Movimiento registrado'];
    }

    public function cerrar(float $monto, string $notas = ''): array {
        $caja = $this->abierta();
        if (empty($caja)) return ['estatus' => false, 'mensaje' => 'No hay una caja abierta'];
        if ($monto < 0) return ['estatus' => false, 'mensaje' => 'El monto contado no puede ser negativo'];

        $esperado = (float)$caja[0]['monto_apertura'] + (float)$caja[0]['total_ventas'] + (float)$caja[0]['total_entradas'] - (float)$caja[0]['total_salidas'];
        $this->db->update('cajas', [
            'monto_cierre' => $monto,
            'monto_esperado' => $esperado,
            'diferencia' => $monto - $esperado,
            'notas' => trim($notas) ?: $caja[0]['notas'],
            'estado' => 'cerrada',
            'fecha_cierre' => $this->fecha->obtenerFechaRegistro()
        ], 'id = ?', [$caja[0]['id']]);
        return ['estatus' => true, 'mensaje' => 'Caja cerrada correctamente', 'esperado' => $esperado, 'diferencia' => $monto - $esperado];
    }

    public function movimientos(): array {
        $caja = $this->abierta();
        if (empty($caja)) return ['estatus' => true, 'data' => []];
        $data = $this->db->select(
            "SELECT m.*, CONCAT(u.nombre, ' ', u.apellido) AS usuario
             FROM caja_movimientos m LEFT JOIN usuarios u ON u.id = m.id_usuario
             WHERE m.id_caja = ? ORDER BY m.id DESC",
            [$caja[0]['id']]
        );
        return ['estatus' => true, 'data' => $data];
    }
}