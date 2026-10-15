<?php
require_once __DIR__ . '/../utils/Tenant.php';
if (!Tenant::esSuperAdmin()) {
    header('Location: ' . BASE_URL . 'sin_permiso/ver_ferreterias');
    exit;
}
$controller_permiso->validarAcceso(1, CPERMISO::VER_FERRETERIAS->value);
include 'vistas/general/header.php';
?>
<body>
    <div class="wrapper">
        <?php include 'vistas/general/sidebar.php'; ?>
        <div class="main">
            <?php include 'vistas/general/navbar.php'; ?>
            <main class="content">
                <div class="container-fluid p-0">
                    <div class="row mb-2 align-items-center">
                        <div class="col-md-8">
                            <h1 class="h3 mb-1">Ferreterías</h1>
                            <p class="text-muted mb-0">Administración de clientes de Pikero.</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <?php if ($controller_permiso->validarAcceso(2, CPERMISO::CREAR_FERRETERIAS->value)['estatus']): ?>
                            <button class="btn btn-success" onclick="agregarFerreteria()"><i class="fa-solid fa-plus me-1"></i>Agregar ferretería</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card"><div class="card-body">
                        <table id="tabla-ferreterias" class="table table-hover nowrap" style="width:100%"></table>
                    </div></div>
                </div>
            </main>
            <?php include 'vistas/general/footer.php'; ?>
        </div>
    </div>
    <?php include 'vistas/general/scripts.php'; ?>
    <script src="<?php echo STATIC_URL; ?>js/config/ferreterias.js" type="module"></script>
</body>
</html>
