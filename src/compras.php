<?php
$controller_permiso->validarAcceso(1, CPERMISO::VER_COMPRAS->value);
include "vistas/general/header.php";
?>
<body>
    <div class="wrapper">
        <?php include "vistas/general/sidebar.php"; ?>
        <div class="main">
            <?php include "vistas/general/navbar.php"; ?>
            <main class="content">
                <div class="container-fluid p-0">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h1 class="h3 mb-1">Compras</h1>
                            <p class="text-muted mb-0">Registra mercancía recibida y actualiza existencias.</p>
                        </div>
                        <button class="btn btn-success" id="btn-nueva-compra">
                            <i class="fa-solid fa-plus me-1"></i> Nueva compra
                        </button>
                    </div>

                    <div id="panel-nueva-compra" class="card mb-4 d-none">
                        <div class="card-header"><h5 class="card-title mb-0">Registrar compra</h5></div>
                        <div class="card-body">
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Proveedor *</label>
                                    <select id="compra-proveedor" class="form-select"></select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Factura / Remisión</label>
                                    <input id="compra-documento" class="form-control" maxlength="80">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Fecha *</label>
                                    <input id="compra-fecha" type="date" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Forma de pago *</label>
                                    <select id="compra-forma-pago" class="form-select"></select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label class="form-label">Buscar producto</label>
                                    <input id="compra-buscador" class="form-control" autocomplete="off" placeholder="SKU o nombre">
                                    <div id="compra-resultados" class="list-group position-absolute shadow" style="z-index:1050; max-height:240px; overflow-y:auto; display:none;"></div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Observaciones</label>
                                    <input id="compra-observaciones" class="form-control" maxlength="255">
                                </div>
                            </div>

                            <div class="table-responsive mb-3">
                                <table class="table table-hover align-middle" id="tabla-items-compra">
                                    <thead><tr><th>Producto</th><th>SKU</th><th class="text-center">Cantidad</th><th class="text-end">Costo unitario</th><th class="text-end">Importe</th><th></th></tr></thead>
                                    <tbody><tr><td colspan="6" class="text-center text-muted py-4">Agrega productos a la compra.</td></tr></tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-end align-items-center gap-3">
                                <strong class="h4 mb-0">Total: <span id="compra-total">$0.00</span></strong>
                                <button class="btn btn-outline-secondary" id="btn-cancelar-compra">Cancelar</button>
                                <button class="btn btn-primary" id="btn-guardar-compra"><i class="fa-solid fa-floppy-disk me-1"></i> Guardar compra</button>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body"><table id="tabla-compras" class="table table-hover nowrap" style="width:100%"></table></div>
                    </div>
                </div>
            </main>
            <?php include "vistas/general/footer.php"; ?>
        </div>
    </div>
    <?php include "vistas/general/scripts.php"; ?>
    <script src="<?php echo STATIC_URL; ?>js/compras/compras.js" type="module"></script>
</body>
</html>