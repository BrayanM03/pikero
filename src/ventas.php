<?php
$controller_permiso->validarAcceso(1, CPERMISO::VER_VENTAS->value);
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
                            <h1 class="h3 mb-3">Historial de Ventas</h1>
                            <p class="text-muted mb-0">Tickets emitidos, formas de pago, detalle de artículos y cancelaciones.</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="<?php echo BASE_URL; ?>punto_venta" class="btn btn-success">
                                <i class="fa-solid fa-shopping-cart me-1"></i> Ir al Punto de Venta
                            </a>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <table id="tabla-ventas" class="table table-hover nowrap" style="width:100%"></table>
                        </div>
                    </div>
                </div>
            </main>
            <?php include "vistas/general/footer.php"; ?>
        </div>
    </div>
    <?php include "vistas/general/scripts.php"; ?>
    <script src="<?php echo STATIC_URL; ?>js/ventas/ventas.js" type="module"></script>
</body>
</html>
