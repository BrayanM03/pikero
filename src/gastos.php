<?php
$controller_permiso->validarAcceso(1, CPERMISO::VER_GASTOS->value);
$puede_crear_gastos = $controller_permiso->validarAcceso(2, CPERMISO::CREAR_GASTOS->value)['estatus'];
$puede_editar_gastos = $controller_permiso->validarAcceso(2, CPERMISO::EDITAR_GASTOS->value)['estatus'];
$puede_cancelar_gastos = $controller_permiso->validarAcceso(2, CPERMISO::CANCELAR_GASTOS->value)['estatus'];
include 'vistas/general/header.php';
?>
<body>
    <div class="wrapper">
        <?php include 'vistas/general/sidebar.php'; ?>
        <div class="main">
            <?php include 'vistas/general/navbar.php'; ?>
            <main class="content">
                <div class="container-fluid p-0">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                        <div>
                            <h1 class="h3 mb-1">Gastos</h1>
                            <p class="text-muted mb-0">Historial de gastos de <?= htmlspecialchars($_SESSION['ferreteria'] ?? 'la ferretería', ENT_QUOTES, 'UTF-8') ?>.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if ($puede_crear_gastos): ?>
                            <button id="btn-categorias-gasto" type="button" class="btn btn-outline-secondary"><i class="fa-solid fa-tags me-1"></i>Categorías</button>
                            <button id="btn-nuevo-gasto" type="button" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Registrar gasto</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabla-gastos" class="table table-hover align-middle nowrap" style="width:100%"></table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
            <?php include 'vistas/general/footer.php'; ?>
        </div>
    </div>
    <?php include 'vistas/general/scripts.php'; ?>
    <script>
        window.PERMISOS_GASTOS = {
            crear: <?= $puede_crear_gastos ? 'true' : 'false' ?>,
            editar: <?= $puede_editar_gastos ? 'true' : 'false' ?>,
            cancelar: <?= $puede_cancelar_gastos ? 'true' : 'false' ?>
        };
    </script>
    <script src="<?= STATIC_URL ?>js/gastos/gastos.js" type="module"></script>
</body>
</html>