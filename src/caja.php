<?php
$controller_permiso->validarAcceso(1, CPERMISO::VER_CAJA->value);
include "vistas/general/header.php";
?>
<body>
    <div class="wrapper">
        <?php include "vistas/general/sidebar.php"; ?>
        <div class="main">
            <?php include "vistas/general/navbar.php"; ?>
            <main class="content"><div class="container-fluid p-0">
                <div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Caja</h1><p class="text-muted mb-0">Control de apertura, movimientos y cierre de caja.</p></div><span id="caja-estado" class="badge bg-secondary">Consultando...</span></div>
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Apertura</small><h3 id="caja-apertura">$0.00</h3></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Ventas</small><h3 id="caja-ventas">$0.00</h3></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Entradas</small><h3 id="caja-entradas">$0.00</h3></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Salidas</small><h3 id="caja-salidas">$0.00</h3></div></div></div>
                </div>
                <div class="card mb-3"><div class="card-body d-flex gap-2 flex-wrap"><button class="btn btn-success" id="btn-abrir-caja"><i class="fa-solid fa-lock-open me-1"></i>Abrir caja</button><button class="btn btn-outline-primary" id="btn-movimiento-caja"><i class="fa-solid fa-money-bill-transfer me-1"></i>Registrar movimiento</button><button class="btn btn-danger" id="btn-cerrar-caja"><i class="fa-solid fa-lock me-1"></i>Cerrar caja</button></div></div>
                <div class="card"><div class="card-body"><table id="tabla-movimientos-caja" class="table table-hover nowrap" style="width:100%"></table></div></div>
            </div></main>
            <?php include "vistas/general/footer.php"; ?>
        </div>
    </div>
    <?php include "vistas/general/scripts.php"; ?>
    <script src="<?php echo STATIC_URL; ?>js/caja/caja.js" type="module"></script>
</body></html>