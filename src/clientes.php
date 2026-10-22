<?php
$controller_permiso->validarAcceso(1, CPERMISO::VER_CLIENTES->value);
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
                            <h1 class="h3 mb-3">Directorio de Clientes</h1>
                            <p class="text-muted mb-0">Gestión de clientes y facturación del negocio.</p>
                        </div>
                        <div class="col-md-4 text-end"> 
                            <?php 
                            /** @var array $crear_clientes_p */
                            if ($crear_clientes_p['estatus']): ?>
                            <button class="btn btn-success" onclick="agregarCliente()">
                                <i class="fa-solid fa-plus me-1"></i> Agregar Cliente
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <table id="tabla-clientes" class="table table-hover nowrap" style="width:100%"></table>
                        </div>
                    </div>
                </div>
            </main>
            <?php include "vistas/general/footer.php"; ?>
        </div>
    </div>
    <?php include "vistas/general/scripts.php"; ?>
    <script src="<?php echo STATIC_URL; ?>js/clientes/clientes.js" type="module"></script>
</body>
</html>
