<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/dates.php';
require_once __DIR__ . '/../services/ImageService.php';
require_once __DIR__ . '/../services/StorageService.php';


class Ferreteria {
    private Database $db;
    private Date $fecha;
    private $imageService;
    private $storageService;

    public function __construct() {
        $this->db = new Database();
        $this->fecha = new Date();
        $this->imageService = new ImageService();
        $this->storageService = new StorageService();
    }

    public function datatables(int $start, int $length, string $search, string $orderColumn, string $orderDir): array {
        $allowed = ['id', 'nombre', 'rfc', 'correo', 'estatus'];
        $orderColumn = in_array($orderColumn, $allowed, true) ? $orderColumn : 'id';
        $orderDir = strtolower($orderDir) === 'asc' ? 'ASC' : 'DESC';
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = ' WHERE nombre LIKE ? OR razon_social LIKE ? OR rfc LIKE ? OR correo LIKE ?';
            $term = '%' . $search . '%';
            $params = [$term, $term, $term, $term];
        }
        return $this->db->select(
            "SELECT * FROM ferreterias{$where} ORDER BY {$orderColumn} {$orderDir} LIMIT " . max(0, $start) . ', ' . max(1, $length),
            $params
        );
    }

    public function contar(string $search = ''): int {
        if ($search === '') {
            return (int)$this->db->count('ferreterias');
        }
        $term = '%' . $search . '%';
        $rows = $this->db->select(
            'SELECT COUNT(*) AS total FROM ferreterias WHERE nombre LIKE ? OR razon_social LIKE ? OR rfc LIKE ? OR correo LIKE ?',
            [$term, $term, $term, $term]
        );
        return (int)($rows[0]['total'] ?? 0);
    }

    public function obtener(int $id): array {
        $rows = $this->db->select('SELECT * FROM ferreterias WHERE id = ?', [$id]);
        return empty($rows)
            ? ['estatus' => false, 'mensaje' => 'Ferretería no encontrada']
            : ['estatus' => true, 'data' => $rows[0]];
    }

    public function combo(): array {
        return $this->db->select('SELECT id, nombre FROM ferreterias WHERE estatus = 1 ORDER BY nombre ASC');
    }

    public function guardar(array $data, ?array $logo = null, ?int $id = null): array {
        $nombre = trim((string)($data['nombre'] ?? ''));
        if ($nombre === '') {
            return ['estatus' => false, 'mensaje' => 'El nombre comercial es obligatorio'];
        }
        $correo = trim((string)($data['correo'] ?? ''));
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return ['estatus' => false, 'mensaje' => 'El correo no tiene un formato válido'];
        }

        $values = [
            'nombre' => $nombre,
            'razon_social' => trim((string)($data['razon_social'] ?? '')),
            'rfc' => strtoupper(trim((string)($data['rfc'] ?? ''))),
            'telefono' => trim((string)($data['telefono'] ?? '')),
            'correo' => $correo,
            'direccion' => trim((string)($data['direccion'] ?? '')),
            'codigo_postal' => trim((string)($data['codigo_postal'] ?? '')),
            'regimen_fiscal' => trim((string)($data['regimen_fiscal'] ?? '')),
            'uso_cfdi' => trim((string)($data['uso_cfdi'] ?? '')),
            'tipo_reporte' => (int)($data['tipo_reporte'] ?? 1)
        ]; 

        if ($logo !== null && !empty($logo['tmp_name'])) {
            $logoPath = $this->guardarLogo($logo);
            if ($logoPath === null) {
                return ['estatus' => false, 'mensaje' => 'El logo debe ser una imagen PNG, JPG o WEBP de hasta 3 MB'];
            }
            $values['logo'] = $logoPath;
        }

        if ($id === null) {
            $values['estatus'] = 1;
            $values['fecha_registro'] = $this->fecha->obtenerFechaRegistro();
            $newId = $this->db->insert('ferreterias', $values);
            return $newId
                ? ['estatus' => true, 'mensaje' => 'Ferretería registrada', 'nuevo' => $newId]
                : ['estatus' => false, 'mensaje' => 'No se pudo registrar la ferretería'];
        }

        $updated = $this->db->update('ferreterias', $values, 'id = ?', [$id]);
        return $updated >= 0
            ? ['estatus' => true, 'mensaje' => 'Ferretería actualizada']
            : ['estatus' => false, 'mensaje' => 'No se pudo actualizar la ferretería'];
    }

    public function cancelar(int $id): array {
        $updated = $this->db->update('ferreterias', ['estatus' => 0], 'id = ?', [$id]);
        return $updated >= 0
            ? ['estatus' => true, 'mensaje' => 'Ferretería cancelada']
            : ['estatus' => false, 'mensaje' => 'No se pudo cancelar la ferretería'];
    }

    private function guardarLogo(array $file): ?string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int)($file['size'] ?? 0) > 3 * 1024 * 1024) {
            return null;
        }
        $image = @getimagesize($file['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = $image['mime'] ?? '';
        if (!isset($extensions[$mime]) || !is_uploaded_file($file['tmp_name'])) {
            return null;
        }
        

            $archivoOptimizado =
                $this->imageService->procesarFoto($file);

            /*
             * 3. Construimos la ruta
             */
              $timestamp = time();
            $ruta =
                'ferreterias/' .
                $_SESSION['id_ferreteria'] .
                '/logos/' . $timestamp . '.webp';

            /*
             * 4. Subimos a Supabase
             */
            $this->storageService->subirArchivo(
                $archivoOptimizado,
                $ruta,
                'image/webp'
            );

            /*
             * 5. Eliminamos temporal
             */
            unlink($archivoOptimizado);

        return $ruta;
    }
}
