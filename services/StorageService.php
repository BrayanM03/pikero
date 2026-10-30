<?php
require_once __DIR__ . '/../config/config.php';

class StorageService
{
    private $supabaseUrl;
    private $supabaseKey;
    private $bucket;

    public function __construct()
    {
        $this->supabaseUrl = SUPABASE_URL;
        $this->supabaseKey = SUPABASE_SERVICE_KEY;
        $this->bucket = 'pikero_public';
    }

    public function subirArchivo($archivo, $ruta, $contentType)
    {
        $url = $this->supabaseUrl . '/storage/v1/object/' . $this->bucket . '/' . $ruta;

        $contenido = file_get_contents($archivo);
        if ($contenido === false) {
            throw new Exception('No se pudo leer el archivo local: ' . $archivo);
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $contenido,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $this->supabaseKey,
                'Content-Type: ' . $contentType,
                'x-upsert: true'
            ]
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            throw new Exception('Error de cURL: ' . $curlError);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception(
                "Supabase respondió $httpCode: $response"
            );
        }

        return $ruta;
    }

    public function eliminarArchivo(string $ruta)
    {
        if (!$ruta) {
            return true;
        }

        $url =
            $this->supabaseUrl .
            '/storage/v1/object/' .
            $this->bucket . '/' . $ruta; 

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_POSTFIELDS => json_encode([$ruta]),
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $this->supabaseKey,
                'Content-Type: application/json'
            ]
        ]);

        curl_exec($ch);

        curl_close($ch);

        return true;
    }

    /**
     * Actualiza un archivo en Supabase eliminando el anterior y subiendo uno nuevo.
     * 
     * @param string $rutaAntigua La ruta en Supabase del archivo que se va a borrar.
     * @param string $archivoNuevo La ruta local/temporal del nuevo archivo a subir.
     * @param string $rutaNueva La ruta de destino en Supabase para el nuevo archivo.
     * @param string $contentType El tipo MIME del nuevo archivo.
     * @return string La ruta del nuevo archivo guardado.
     */
    public function actualizarArchivo($rutaAntigua, $archivoNuevo, $rutaNueva, $contentType)
    {
        // 1. Eliminamos el archivo anterior (solo si nos enviaron una ruta válida)
        if (!empty($rutaAntigua)) {
            try {
                $this->eliminarArchivo($rutaAntigua);
            } catch (Exception $e) {
                // Lo envolvemos en un try-catch por si el archivo viejo fue borrado 
                // manualmente antes desde Supabase, así evitamos que el error detenga
                // el proceso y nos permita subir el nuevo archivo.
                error_log("Supabase Update (Warning): No se pudo eliminar el archivo antiguo -> " . $e->getMessage());
            }
        }

        // 2. Subimos el nuevo archivo y retornamos la nueva ruta
        return $this->subirArchivo($archivoNuevo, $rutaNueva, $contentType);
    }
}
