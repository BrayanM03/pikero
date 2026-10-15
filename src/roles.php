<?php
$controller_permiso->validarAcceso(1, CPERMISO::VER_ROLES->value);
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
                        <div class="col-12">
                            <h1 class="h3 mb-3">Roles del sistema</h1>
                            <p class="text-muted">Administrador, Gerente, Vendedor e Inventarista.</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Roles activos</h5>
                        </div>
                        <div class="card-body">
                            <table id="roles" class="table table-hover nowrap" style="width:100%"></table>
                        </div>
                    </div>
                </div>
            </main>
            <?php include "vistas/general/footer.php"; ?>
        </div>
    </div>
    <?php include "vistas/general/scripts.php"; ?>
    <script src="<?php echo STATIC_URL; ?>js/roles/traer-lista-roles.js" type="module"></script>
</body>
</html>
