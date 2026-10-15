<?php
$controller_permiso->validarAcceso(1, CPERMISO::VER_INVENTARIO->value);
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
                            <h1 class="h3 mb-3">Movimientos de inventario</h1>
                            <p class="text-muted mb-0">Bitácora de entradas, salidas, ajustes y ventas por sucursal.</p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <table id="tabla-movimientos-inventario" class="table table-hover nowrap" style="width:100%"></table>
                        </div>
                    </div>
                </div>
            </main>
            <?php include "vistas/general/footer.php"; ?>
        </div>
    </div>
    <?php include "vistas/general/scripts.php"; ?>
    <script src="<?php echo STATIC_URL; ?>js/inventario/movimientos-inventario.js" type="module"></script>
</body>
</html>