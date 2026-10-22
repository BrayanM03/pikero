<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['id'])) {
    $url_destino = "panel";
    $texto_boton = "Volver al Dashboard";
} else {
    $url_destino = "login";
    $texto_boton = "Ir al Inicio de Sesión";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página no encontrada | Pikero</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container { text-align: center; max-width: 500px; padding: 20px; }
        .error-code { font-size: 10rem; font-weight: 800; color: #1f2a37; line-height: 1; }
        .btn-pikero {
            background-color: #1f2a37;
            color: white;
            padding: 12px 30px;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            display: inline-block;
        }
        .btn-pikero:hover { background-color: #e24b26; color: white; }
    </style>
</head>
<body>
    <div class="error-container">
        <img src="<?php echo STATIC_URL; ?>img/titulo_logo.png" alt="Pikero Logo" class="img-fluid mb-4" width="220" />
        <div class="error-code">404</div>
        <h1 class="h4">La página que buscas no existe.</h1>
        <p class="text-muted">El sistema sigue bajo control.</p>
        <a href="<?php echo BASE_URL . $url_destino; ?>" class="btn-pikero"><?php echo $texto_boton; ?></a>
    </div>
</body>
</html>
