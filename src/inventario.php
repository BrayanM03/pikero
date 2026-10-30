<?php
$controller_permiso->validarAcceso(1, CPERMISO::VER_INVENTARIO->value);
include "vistas/general/header.php";
?>
<style>
  /* ===== Secciones tipo título con triángulo ===== */
  .inv-form .accordion-item {
    background: transparent !important;
    border: 0 !important;
    border-bottom: 1px solid var(--bs-border-color, #dee2e6) !important;
    border-radius: 0 !important;
    margin-bottom: 0;
  }
  .inv-form .accordion-item:last-child { border-bottom: 0 !important; }

  .inv-form .accordion-button {
    display: flex;
    align-items: center;
    background: transparent !important;
    box-shadow: none !important;
    border: 0;
    color: var(--bs-body-color, #212529) !important;
    font-size: 1.05rem;
    font-weight: 600;
    gap: .5rem;
    padding: .9rem .25rem;
  }
  .inv-form .accordion-button:hover { color: var(--bs-primary, #0d6efd) !important; }

  /* Apagamos la flecha original de Bootstrap */
  .inv-form .accordion-button::after {
    display: none !important;
    content: none !important;
  }

  /* Triángulo propio a la izquierda */
  .inv-form .accordion-button::before {
    content: "";
    flex-shrink: 0;
    display: inline-block;
    width: 0;
    height: 0;
    border-top: .4rem solid transparent;
    border-bottom: .4rem solid transparent;
    border-left: .55rem solid currentColor;   /* cerrado: apunta a la derecha */
    transition: transform .2s ease;
  }
  .inv-form .accordion-button:not(.collapsed)::before {
    transform: rotate(90deg);                 /* abierto: apunta hacia abajo */
  }

  /* Icono simple, sin caja */
  .inv-form .accordion-button .sec-icon {
    display: inline-flex;
    color: var(--bs-primary, #0d6efd);
    font-size: 1.1rem;
    background: none;
    width: auto;
    height: auto;
  }

  .inv-form .accordion-body { padding: .25rem .25rem 1.25rem; }

  /* ===== Resto del formulario ===== */
  .inv-form .form-label { font-size: .85rem; font-weight: 500; margin-bottom: .25rem; }
  .inv-form .form-control,
  .inv-form .form-select,
  .inv-form .input-group-text { font-size: .95rem; }

  .inv-form .preview-box {
    background: var(--bs-tertiary-bg, #f8f9fa);
    border: 1px dashed var(--bs-border-color, #dee2e6);
    border-radius: .65rem;
    padding: .75rem 1rem;
  }
  .inv-form .preview-box .row-prev {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: .2rem 0;
    font-size: .9rem;
  }
  .inv-form .preview-box strong { font-variant-numeric: tabular-nums; }
  .inv-form .badge-req { font-size: .65rem; font-weight: 500; }

  @media (max-width: 575.98px) {
    .inv-form .accordion-body { padding: .25rem 0 1rem; }
  }
</style>
<body>
    <div class="wrapper">
        <?php include "vistas/general/sidebar.php"; ?>
        <div class="main">
            <?php include "vistas/general/navbar.php"; ?>
            <main class="content">
                <div class="container-fluid p-0">
                    <div class="row mb-2">
                        <div class="col-md-8">
                            <h1 class="h3 mb-3">Control de Inventario</h1>
                            <p class="text-muted mb-0">Existencias físicas, stock mínimo y bitácora de movimientos por sucursal.</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <button class="btn btn-success" onclick="agregarProductoDesdeInventario()">
                                <i class="fa-solid fa-plus me-1"></i> Agregar Producto
                            </button>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <table id="tabla-inventario" class="table table-hover nowrap" style="width:100%"></table>
                        </div>
                    </div>
                </div>
            </main>
            <?php include "vistas/general/footer.php"; ?>
        </div>
    </div>
    <?php include "vistas/general/scripts.php"; ?>
    <script src="<?php echo STATIC_URL; ?>js/inventario/inventario.js" type="module"></script>
</body>
</html>
