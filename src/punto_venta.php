<?php
$controller_permiso->validarAcceso(1, CPERMISO::VER_PUNTO_VENTA->value);
include "vistas/general/header.php";
?>
<body>
    <div class="wrapper">
        <?php include "vistas/general/sidebar.php"; ?>
        <div class="main">
            <?php include "vistas/general/navbar.php"; ?>
            <main class="content p-3">
                <div class="container-fluid p-0">
                    <div class="row">
                        <!-- Columna izquierda: Búsqueda y Carrito -->
                        <div class="col-lg-8 mb-3">
                            <div class="card mb-3 shadow-sm">
                                <div class="card-body p-3">
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text bg-primary text-white"><i class="fa-solid fa-barcode"></i></span>
                                        <input type="text" id="pos-buscador" class="form-control" placeholder="Escanear código de barras o teclear nombre del producto..." autofocus autocomplete="off">
                                        <button class="btn btn-primary" type="button" id="btn-buscar-pos">
                                            <i class="fa-solid fa-magnifying-glass me-1"></i> Buscar
                                        </button>
                                    </div>
                                    <div id="pos-resultados" class="list-group position-absolute w-100 shadow mt-1" style="z-index: 1050; max-height: 280px; overflow-y: auto; display: none;"></div>
                                </div>
                            </div>

                            <div class="card shadow-sm">
                                <div class="card-header d-flex justify-content-between align-items-center py-2 bg-light">
                                    <h5 class="card-title mb-0"><i class="fa-solid fa-cart-shopping me-2"></i>Artículos a cobrar</h5>
                                    <button class="btn btn-outline-danger btn-sm" id="btn-vaciar-carrito">
                                        <i class="fa-solid fa-trash me-1"></i> Vaciar
                                    </button>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive" style="min-height: 320px; max-height: 480px; overflow-y: auto;">
                                        <table class="table table-hover align-middle mb-0" id="tabla-carrito">
                                            <thead class="table-light sticky-top">
                                                <tr>
                                                    <th>Producto</th>
                                                    <th style="width: 130px;" class="text-center">Cantidad</th>
                                                    <th style="width: 120px;" class="text-end">Precio</th>
                                                    <th style="width: 130px;" class="text-end">Importe</th>
                                                    <th style="width: 50px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="pos-items-body">
                                                <tr>
                                                    <td colspan="5" class="text-center py-5 text-muted">
                                                        <i class="fa-solid fa-cart-arrow-down fa-3x mb-3 text-secondary opacity-50"></i>
                                                        <p class="mb-0">El carrito está vacío. Escanea o busca productos para comenzar.</p>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Columna derecha: Resumen de Venta y Cobro -->
                        <div class="col-lg-4">
                            <div class="card shadow-sm border-primary">
                                <div class="card-header bg-primary text-white py-2">
                                    <h5 class="card-title mb-0 text-white"><i class="fa-solid fa-cash-register me-2"></i>Total de Venta</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Cliente</label>
                                        <select id="pos-cliente" class="form-select"></select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Tipo de venta</label>
                                        <select id="pos-tipo-venta" class="form-select">
                                            <option value="contado">Contado</option>
                                            <option value="credito">Crédito</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Forma de Pago</label><br>
                                        <select id="pos-forma-pago" multiple class="form-control selectpicker" onchange="setearFormaPago()"></select>
                                    <div class="col-12 col-md-12 d-none" id="col-formas-pago">
                                            <div class="row mt-3">
                                                <div class="col-12">
                                                    <div class="area-formas-pago" id="area-formas-pago">
                                                        <label><b>Montos de la forma de pago</b></label><br>
                                                        <div class="row mt-3" id="lista-formas-pago">
                                                            <div class="col-12 text-start">
                                                                Sin formas de pago seleccionadas
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <small id="small_area-formas-pago" style="color:tomato;"></small>

                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Fecha</label>
                                        <input type="text" id="pos-fecha" class="form-control" placeholder="Selecciona fecha y hora" style="background-color: #fff; cursor: pointer;" readonly>
                                    </div>

                                    <div class="mb-3 d-none" id="bloque-abono-inicial">
                                        <label class="form-label fw-bold" for="pos-abono-inicial">Abono inicial</label>
                                        <input type="number" min="0" step="0.01" id="pos-abono-inicial" class="form-control" value="0">
                                        <small class="text-muted">Puede dejarse en cero y cobrarse después desde el historial.</small>
                                    </div>

                                    <div class="p-3 bg-light rounded-3 mb-3 border">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Artículos:</span>
                                            <span id="pos-total-articulos" class="fw-bold">0</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Subtotal:</span>
                                            <span id="pos-subtotal" class="fw-bold">$0.00</span>
                                        </div>
                                        <hr class="my-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="h4 mb-0 fw-bold text-dark">TOTAL:</span>
                                            <span id="pos-total" class="h2 mb-0 fw-bold text-success">$0.00</span>
                                        </div>
                                    </div>

                                    <div class="mb-3" id="bloque-efectivo">
                                        <label class="form-label fw-bold">Paga con $</label>
                                        <input type="number" step="0.50" id="pos-pago-con" class="form-control form-control-lg text-end fw-bold" placeholder="0.00">
                                        <div class="d-flex justify-content-between align-items-center mt-2 p-2 bg-light rounded">
                                            <span class="fw-bold">Cambio:</span>
                                            <span id="pos-cambio" class="h4 mb-0 fw-bold text-primary">$0.00</span>
                                        </div>
                                    </div>

                                    <button class="btn btn-success btn-lg w-100 py-3 shadow" id="btn-cobrar-pos">
                                        <i class="fa-solid fa-check-double me-2"></i> COBRAR VENTA (F2)
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
            <?php include "vistas/general/footer.php"; ?>
        </div>
    </div>
    <?php include "vistas/general/scripts.php"; ?>
    <script src="<?php echo STATIC_URL; ?>js/punto_venta/punto_venta.js" type="module"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar Flatpickr en el campo de fecha
            flatpickr("#pos-fecha", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                locale: "es"
            });
        });
    </script>
</body>
</html>
