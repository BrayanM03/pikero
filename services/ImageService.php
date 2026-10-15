<?php

class ImageService
{
    public function procesarFoto($archivo)
    {
        if (
            !isset($archivo['tmp_name']) ||
            $archivo['error'] !== UPLOAD_ERR_OK
        ) {
            throw new Exception('No se recibió una imagen válida.');
        }

        // Máximo 10 MB
        if ($archivo['size'] > 10 * 1024 * 1024) {
            throw new Exception('La imagen no puede superar los 10 MB.');
        }

        // Verificar que realmente sea una imagen
        $info = getimagesize($archivo['tmp_name']);

        if ($info === false) {
            throw new Exception('El archivo no es una imagen válida.');
        }

        $imagen = new Imagick($archivo['tmp_name']);

        $imagen->autoOrient();

        // Elimina EXIF y otros metadatos
        $imagen->stripImage();

        // Máximo 600x600 manteniendo proporción
        $imagen->thumbnailImage(
            600,
            600,
            true
        );

        // WebP
        $imagen->setImageFormat('webp');
        $imagen->setImageCompressionQuality(80);

        // Archivo temporal
        $rutaTemporal = tempnam(
            sys_get_temp_dir(),
            'colefy_'
        ) . '.webp';

        $imagen->writeImage($rutaTemporal);

        $imagen->clear();
        $imagen->destroy();

        return $rutaTemporal;
    }
}