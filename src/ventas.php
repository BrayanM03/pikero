<?php
$controller_permiso->validarAcceso(1, CPERMISO::VER_VENTAS->value);
include "vistas/general/header.php";
?>
<style>
    /* Grid de 12 columnas */
.cf-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:12px 16px}
.cf-grid>*{min-width:0}
.cf-col-2{grid-column:span 2}
.cf-col-3{grid-column:span 3}
.cf-col-4{grid-column:span 4}
.cf-col-6{grid-column:span 6}
.cf-col-9{grid-column:span 9}
.cf-col-12{grid-column:span 12}

.cf-input,.cf-select,.cf-grid .bootstrap-select{width:100%!important;box-sizing:border-box}
textarea.cf-input{resize:vertical;height:auto}

/* Encabezado y acciones */
.cf-editor-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:8px}
.cf-subtitle{margin:18px 0 8px;padding-top:12px;border-top:1px solid rgba(0,0,0,.08)}
.cf-editor-head + .cf-subtitle{border-top:0;margin-top:8px;padding-top:0}

.cf-tax-add{display:flex;gap:8px;align-items:center;margin-top:8px}
.cf-tax-add .cf-select{flex:1 1 auto;max-width:420px}

.cf-totals-concept{margin-top:16px}
.cf-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}

/* Tablet */
@media (max-width:991px){
  .cf-col-2,.cf-col-3,.cf-col-4{grid-column:span 6}
  .cf-col-9{grid-column:span 12}
}

/* Móvil */
@media (max-width:575px){
  .cf-grid{gap:10px}
  .cf-col-2,.cf-col-3,.cf-col-4,.cf-col-6,.cf-col-9{grid-column:span 12}
  .cf-tax-add{flex-direction:column;align-items:stretch}
  .cf-tax-add .cf-select{max-width:none}
  .cf-actions{flex-direction:column}
  .cf-actions .cf-btn,.cf-tax-add .cf-btn{width:100%}
  .cf-input,.cf-select{font-size:16px} /* evita zoom automático en iOS */
}

.cf-acc{border:1px solid rgba(0,0,0,.12);border-radius:8px;margin-top:12px;background:#fff}
.cf-acc>summary{
  display:flex;align-items:center;gap:8px;
  padding:10px 14px;cursor:pointer;list-style:none;
  font-weight:600;user-select:none;
}
.cf-acc>summary::-webkit-details-marker{display:none}
.cf-acc>summary::after{
  content:"";margin-left:auto;width:8px;height:8px;
  border-right:2px solid currentColor;border-bottom:2px solid currentColor;
  transform:rotate(45deg);transition:transform .2s;
}
.cf-acc[open]>summary::after{transform:rotate(-135deg)}
.cf-acc>summary:hover{background:rgba(0,0,0,.03)}
.cf-acc>summary:focus-visible{outline:2px solid #0d6efd;outline-offset:-2px;border-radius:8px}
.cf-acc[open]>summary{border-bottom:1px solid rgba(0,0,0,.08)}
.cf-acc-body{padding:14px}
.cf-acc-badge{
  font-size:.72rem;font-weight:600;padding:2px 8px;border-radius:999px;
  background:#e7f1ff;color:#0a58ca;
}
.cf-acc-badge[hidden]{display:none}

@media (max-width:575px){
  .cf-acc>summary{padding:12px}
  .cf-acc-body{padding:12px}
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
