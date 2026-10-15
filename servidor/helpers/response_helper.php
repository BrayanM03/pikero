<?php
function responder($status, $message, $tipo = 'danger', $data = null, $exit = true) {
    header('Content-Type: application/json');
    echo json_encode([
        'estatus' => $status,
        'mensaje' => $message,
        'tipo' => $tipo,
        'data' => $data
    ]);
    if ($exit == true) {
        exit;
    }
}
