<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sin permisos | Pikero</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center" style="height:100vh; background:#f8f9fa;">
<?php $msg = $_GET['msg'] ?? 'No tienes permiso para entrar a este módulo.'; ?>
    <div class="text-center p-4 bg-white rounded shadow-sm" style="max-width:400px;">
        <img src="<?= STATIC_URL?>img/logo_pajaro.png" alt="Logo" style="width:90px;" class="mb-3">
        <h3 class="text-danger fw-bold">Sin permisos</h3>
        <p><?php echo htmlspecialchars($msg); ?></p>
        <a href="<?= BASE_URL?>panel" class="btn btn-dark">Volver al panel</a>
    </div>
</body>
</html>
