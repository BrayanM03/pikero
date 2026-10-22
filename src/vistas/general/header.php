<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Pikero - Software para ferreterías">
    <meta name="author" content="Pikero">

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link rel="shortcut icon" href="<?php echo STATIC_URL; ?>img/icons/icon-48x48.png" />

    <title><?= $titulo_vista ?> | Pikero</title>

    <link href="<?php echo STATIC_URL; ?>css/app.css" rel="stylesheet">
    <link href="<?php echo STATIC_URL; ?>css/ui.css" rel="stylesheet">
    <link href="<?php echo STATIC_URL; ?>css/bootstrap-select.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/animations/scale.css"/>

    <!-- CSS de Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<!-- Opcional: Tema oscuro o de Bootstrap si prefieres -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">

<!-- JS de Flatpickr y traducción a español -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>

    <?php if (isset($css_especificos)): ?>
        <?php foreach ($css_especificos as $url): ?>
            <link rel="stylesheet" href="<?php echo $url; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
