<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/dates.php';
require_once __DIR__ . '/../services/StorageService.php';
require_once __DIR__ . '/../utils/Tenant.php';

class Gasto {
    private Database $db;
    private Date $date;
    private StorageService $storage;
    private ?int $ferreteria;
    private ?int $sucursalActiva;

    public function __construct(?int $ferreteria = null, ?int $sucursal = null) {
        $this->db = new Database();
        $this->date = new Date();
        $this->storage = new StorageService();
        $this->ferreteria = $ferreteria ?? Tenant::idFerreteria();
        $this->sucursalActiva = $sucursal ?? Tenant::idSucursal();
    }

    public function datatable(array $request): array {
        $search = trim((string)($request['search']['value'] ?? ''));
        $start = max(0, (int)($request['start'] ?? 0));
        $length = min(500, max(1, (int)($request['length'] ?? 10)));
        $where = ['g.id_ferreteria = :ferreteria'];
        $params = [':ferreteria' => $this->ferreteria];

        if (isset($request['estatus']) && $request['estatus'] !== '') {
            $where[] = 'g.estatus = :estatus';
            $params[':estatus'] = (int)$request['estatus'];
        } elseif (empty($request['incluir_cancelados'])) {
            $where[] = 'g.estatus = 1';
        }

        $baseWhere = 'g.id_ferreteria = ?';
        $baseParams = [$this->ferreteria];

        if ($search !== '') {
            $where[] = '(g.folio LIKE :buscar_folio OR g.descripcion LIKE :buscar_descripcion OR cat.nombre LIKE :buscar_categoria OR s.nombre LIKE :buscar_sucursal)';
            foreach (['folio', 'descripcion', 'categoria', 'sucursal'] as $campo) $params[':buscar_' . $campo] = '%' . $search . '%';
        }
        if (!empty($request['fecha_inicio'])) {
            $where[] = 'g.fecha >= :fecha_inicio';
            $params[':fecha_inicio'] = $request['fecha_inicio'];
        }
        if (!empty($request['fecha_fin'])) {
            $where[] = 'g.fecha <= :fecha_fin';
            $params[':fecha_fin'] = $request['fecha_fin'];
        }
        if (!empty($request['id_sucursal'])) {
            $where[] = 'g.id_sucursal = :filtro_sucursal';
            $params[':filtro_sucursal'] = (int)$request['id_sucursal'];
        }
        if (!empty($request['id_categoria'])) {
            $where[] = 'g.id_categoria = :categoria';
            $params[':categoria'] = (int)$request['id_categoria'];
        }

        $from = ' FROM gastos g INNER JOIN categorias_gastos cat ON cat.id = g.id_categoria INNER JOIN sucursales s ON s.id = g.id_sucursal INNER JOIN usuarios u ON u.id = g.id_usuario';
        $totalRows = $this->db->select('SELECT COUNT(*) AS total FROM gastos g WHERE ' . $baseWhere, $baseParams);
        $filteredRows = $this->db->select('SELECT COUNT(*) AS total' . $from . ' WHERE ' . implode(' AND ', $where), $params);
        $allowed = [
            'id' => 'g.id', 'fecha' => 'g.fecha', 'sucursal' => 's.nombre', 'categoria' => 'cat.nombre',
            'monto' => 'g.monto', 'folio' => 'g.folio', 'descripcion' => 'g.descripcion'
        ];
        $column = (string)($request['columns'][$request['order'][0]['column'] ?? 0]['data'] ?? 'fecha');
        $order = $allowed[$column] ?? 'g.fecha';
        $direction = strtolower((string)($request['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $rows = $this->db->select(
            'SELECT g.id, g.id_sucursal, g.id_categoria, g.monto, g.fecha, g.folio, g.descripcion, g.comprobante, g.estatus, cat.nombre AS categoria, s.nombre AS sucursal, CONCAT(u.nombre, \' \', u.apellido) AS usuario' . $from .
            ' WHERE ' . implode(' AND ', $where) . " ORDER BY {$order} {$direction} LIMIT {$start}, {$length}",
            $params
        );
        foreach ($rows as &$row) $row['comprobante_url'] = $this->urlComprobante($row['comprobante']);
        unset($row);

        return [
            'draw' => (int)($request['draw'] ?? 1),
            'recordsTotal' => (int)($totalRows[0]['total'] ?? 0),
            'recordsFiltered' => (int)($filteredRows[0]['total'] ?? 0),
            'data' => $rows
        ];
    }

    public function categorias(): array {
        return $this->db->select(
            'SELECT id, nombre FROM categorias_gastos WHERE id_ferreteria = ? AND estatus = 1 ORDER BY nombre ASC',
            [$this->ferreteria]
        );
    }

    public function crearCategoria(string $nombre): array {
        $nombre = trim($nombre);
        if ($nombre === '' || mb_strlen($nombre) > 100) {
            return ['estatus' => false, 'mensaje' => 'Escribe una categoría de hasta 100 caracteres'];
        }
        $exists = $this->db->select('SELECT id FROM categorias_gastos WHERE id_ferreteria = ? AND nombre = ?', [$this->ferreteria, $nombre]);
        if ($exists) return ['estatus' => false, 'mensaje' => 'Esa categoría ya está registrada'];
        try {
            $id = $this->db->insert('categorias_gastos', [
                'id_ferreteria' => $this->ferreteria,
                'nombre' => $nombre,
                'estatus' => 1,
                'fecha_registro' => $this->date->obtenerFechaRegistro()
            ]);
            return $id ? ['estatus' => true, 'mensaje' => 'Categoría agregada', 'id' => (int)$id, 'nombre' => $nombre] : ['estatus' => false, 'mensaje' => 'No se pudo agregar la categoría'];
        } catch (Throwable $exception) {
            return ['estatus' => false, 'mensaje' => 'Esa categoría ya está registrada'];
        }
    }

    public function registrar(array $data, ?array $file): array {
        $values = $this->validarDatos($data);
        if (!$values['estatus']) return $values;
        $comprobante = null;
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = $this->subirComprobante($file);
            if (!$upload['estatus']) return $upload;
            $comprobante = $upload['ruta'];
        }

        try {
            $id = $this->db->insert('gastos', [
                'id_ferreteria' => $this->ferreteria,
                'id_sucursal' => $values['id_sucursal'],
                'id_categoria' => $values['id_categoria'],
                'id_usuario' => (int)($_SESSION['id'] ?? 0),
                'monto' => $values['monto'],
                'fecha' => $values['fecha'],
                'folio' => $values['folio'],
                'descripcion' => $values['descripcion'],
                'comprobante' => $comprobante,
                'estatus' => 1,
                'fecha_registro' => $this->date->obtenerFechaRegistro()
            ]);
            return $id ? ['estatus' => true, 'mensaje' => 'Gasto registrado correctamente', 'id' => (int)$id] : ['estatus' => false, 'mensaje' => 'No se pudo registrar el gasto'];
        } catch (Throwable $exception) {
            if ($comprobante) $this->eliminarComprobante($comprobante);
            return ['estatus' => false, 'mensaje' => 'No se pudo registrar el gasto'];
        }
    }

    public function obtener(int $id): array {
        $rows = $this->db->select(
            'SELECT g.*, cat.nombre AS categoria, s.nombre AS sucursal FROM gastos g INNER JOIN categorias_gastos cat ON cat.id = g.id_categoria INNER JOIN sucursales s ON s.id = g.id_sucursal WHERE g.id = ? AND g.id_ferreteria = ?',
            [$id, $this->ferreteria]
        );
        if (!$rows) return ['estatus' => false, 'mensaje' => 'Gasto no encontrado'];
        $rows[0]['comprobante_url'] = $this->urlComprobante($rows[0]['comprobante']);
        return ['estatus' => true, 'data' => $rows[0]];
    }

    public function actualizar(int $id, array $data, ?array $file): array {
        $actual = $this->obtener($id);
        if (!$actual['estatus']) return $actual;
        if ((int)$actual['data']['estatus'] !== 1) return ['estatus' => false, 'mensaje' => 'No se puede editar un gasto cancelado'];
        $values = $this->validarDatos($data);
        if (!$values['estatus']) return $values;

        $comprobante = $actual['data']['comprobante'];
        $nuevoComprobante = null;
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = $this->subirComprobante($file);
            if (!$upload['estatus']) return $upload;
            $nuevoComprobante = $upload['ruta'];
            $comprobante = $nuevoComprobante;
        }

        try {
            $this->db->update('gastos', [
                'id_sucursal' => $values['id_sucursal'],
                'id_categoria' => $values['id_categoria'],
                'monto' => $values['monto'],
                'fecha' => $values['fecha'],
                'folio' => $values['folio'],
                'descripcion' => $values['descripcion'],
                'comprobante' => $comprobante,
                'fecha_actualizacion' => $this->date->obtenerFechaRegistro()
            ], 'id = ? AND id_ferreteria = ? AND estatus = 1', [$id, $this->ferreteria]);
            if ($nuevoComprobante && !empty($actual['data']['comprobante'])) $this->eliminarComprobante($actual['data']['comprobante']);
            return ['estatus' => true, 'mensaje' => 'Gasto actualizado correctamente'];
        } catch (Throwable $exception) {
            if ($nuevoComprobante) $this->eliminarComprobante($nuevoComprobante);
            return ['estatus' => false, 'mensaje' => 'No se pudo actualizar el gasto'];
        }
    }

    public function cancelar(int $id): array {
        $rows = $this->db->select('SELECT id, estatus FROM gastos WHERE id = ? AND id_ferreteria = ?', [$id, $this->ferreteria]);
        if (!$rows) return ['estatus' => false, 'mensaje' => 'Gasto no encontrado'];
        if ((int)$rows[0]['estatus'] !== 1) return ['estatus' => false, 'mensaje' => 'El gasto ya está cancelado'];
        $this->db->update('gastos', ['estatus' => 0, 'fecha_actualizacion' => $this->date->obtenerFechaRegistro()], 'id = ? AND id_ferreteria = ?', [$id, $this->ferreteria]);
        return ['estatus' => true, 'mensaje' => 'Gasto cancelado; el comprobante se conservó en el historial'];
    }

    private function validarDatos(array $data): array {
        $sucursal = (int)($data['id_sucursal'] ?? 0);
        $categoria = (int)($data['id_categoria'] ?? 0);
        $monto = round((float)($data['monto'] ?? 0), 2);
        $fecha = trim((string)($data['fecha'] ?? ''));
        $folio = trim((string)($data['folio'] ?? ''));
        $descripcion = trim((string)($data['descripcion'] ?? ''));
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if ($sucursal <= 0 || $categoria <= 0) return ['estatus' => false, 'mensaje' => 'Selecciona sucursal y categoría'];
        if ($monto <= 0 || !is_finite($monto)) return ['estatus' => false, 'mensaje' => 'El monto debe ser mayor a cero'];
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $fecha) return ['estatus' => false, 'mensaje' => 'La fecha del gasto no es válida'];
        if ($descripcion === '' || mb_strlen($descripcion) > 500) return ['estatus' => false, 'mensaje' => 'La descripción es obligatoria y admite hasta 500 caracteres'];
        if (mb_strlen($folio) > 100) return ['estatus' => false, 'mensaje' => 'El folio admite hasta 100 caracteres'];

        $branch = $this->db->select('SELECT id FROM sucursales WHERE id = ? AND id_ferreteria = ? AND estatus = 1', [$sucursal, $this->ferreteria]);
        $categoryRow = $this->db->select('SELECT id FROM categorias_gastos WHERE id = ? AND id_ferreteria = ? AND estatus = 1', [$categoria, $this->ferreteria]);
        if (!$branch) return ['estatus' => false, 'mensaje' => 'La sucursal no pertenece a esta ferretería o está inactiva'];
        if (!$categoryRow) return ['estatus' => false, 'mensaje' => 'La categoría no pertenece a esta ferretería o está inactiva'];

        return ['estatus' => true, 'id_sucursal' => $sucursal, 'id_categoria' => $categoria, 'monto' => $monto, 'fecha' => $fecha, 'folio' => $folio ?: null, 'descripcion' => $descripcion];
    }

    private function subirComprobante(array $file): array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['estatus' => false, 'mensaje' => 'No se recibió un comprobante válido'];
        }
        if ((int)($file['size'] ?? 0) > 10 * 1024 * 1024) return ['estatus' => false, 'mensaje' => 'El comprobante no puede superar 10 MB'];
        $tmp_name = $file['tmp_name'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp_name);

        // Si finfo falla y da el tipo genérico...
        if ($mime === 'application/octet-stream') {
            // 1. Obtener la extensión original del archivo
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // 2. Leer los primeros 4 bytes para verificar la firma de un PDF
            $handle = fopen($tmp_name, 'rb');
            $first_bytes = fread($handle, 4);
            fclose($handle);

            // Si la extensión es pdf Y empieza con %PDF
            if ($extension === 'pdf' && $first_bytes === '%PDF') {
                $mime = 'application/pdf'; // Corregimos el MIME de forma segura
            }
        }

        $extensions = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) return ['estatus' => false, 'mensaje' => 'El comprobante debe ser PDF, JPG, PNG o WEBP'];
        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $path = (int)$this->ferreteria . '/gastos/' . $filename;
        try {
            $this->storage->subirArchivo($file['tmp_name'], $path, $mime);
            return ['estatus' => true, 'ruta' => $path];
        } catch (Throwable $exception) {
            error_log('Gasto: fallo al subir comprobante a Supabase: ' . $exception->getMessage());
            return ['estatus' => false, 'mensaje' => 'No se pudo subir el comprobante'];
        }
    }

    private function eliminarComprobante(string $path): void {
        try {
            $this->storage->eliminarArchivo($path);
        } catch (Throwable $exception) {
            error_log('Gasto: no se pudo eliminar el comprobante reemplazado: ' . $exception->getMessage());
        }
    }

    private function urlComprobante(?string $path): ?string {
        if (!$path) return null;
        return rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/pikero_public/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}