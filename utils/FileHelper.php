<?php
declare(strict_types=1);

class FileHelper {
    public static function uploadImage(array $file, string $folder, string $prefix, ?string $oldFile = null): ?string {
        if (empty($file['tmp_name'])) {
            return null;
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $nuevo_nombre = $prefix . "_" . time() . "." . $extension;

        $uploadDir = ROOT_PATH . "static/img/" . $folder . "/";
        $ruta_destino = $uploadDir . $nuevo_nombre;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (move_uploaded_file($file['tmp_name'], $ruta_destino)) {
            if (!empty($oldFile) && $oldFile !== 'avatar.jpg' && $oldFile !== 'default.png') {
                $ruta_vieja = $uploadDir . $oldFile;
                if (file_exists($ruta_vieja)) {
                    unlink($ruta_vieja);
                }
            }
            return $nuevo_nombre;
        }

        return null;
    }
}
