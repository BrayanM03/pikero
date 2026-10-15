<?php
$controller_permiso->validarAcceso(1, CPERMISO::VER_SUCURSALES->value);
$crear_sucursal = $controller_permiso->verificarPermiso(2, CPERMISO::CREAR_SUCURSALES->value);
include "vistas/general/header.php";
?>
<body>
    <div class="wrapper">
        <?php include "vistas/general/sidebar.php"; ?>
        <div class="main">
            <?php include "vistas/general/navbar.php"; ?>
            <main class="content">
                <div class="container-fluid p-0">
                    <div class="row mb-2">
                        <div class="col-md-8">
                            <h1 class="h3 mb-3">Sucursales</h1>
                            <p class="text-muted mb-0">Una ferretería puede tener una o varias sucursales. Los usuarios se ligan a una.</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <?php if ($crear_sucursal['estatus']): ?>
                            <div class="btn btn-success" onclick="agregarSucursal()">Agregar sucursal</div>
                            <?php endif ?>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <table id="sucursales" class="table table-hover nowrap" style="width:100%"></table>
                        </div>
                    </div>
                </div>
            </main>
            <?php include "vistas/general/footer.php"; ?>
        </div>
    </div>
    <?php include "vistas/general/scripts.php"; ?>
    <script src="<?php echo STATIC_URL; ?>js/sucursales/sucursales.js" type="module"></script>
</body>
</html>
