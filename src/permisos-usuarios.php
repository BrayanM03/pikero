<?php
$controller_permiso->validarAcceso(1, CPERMISO::VER_PERMISOS_USUARIOS->value);
include "vistas/general/header.php";
?>
<body>
    <div class="wrapper">
        <?php include "vistas/general/sidebar.php"; ?>
        <div class="main">
            <?php include "vistas/general/navbar.php"; ?>
            <main class="content">
                <div class="container-fluid p-0">
                    <h1 class="h3 mb-3">Permisos por usuario</h1>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Selecciona un usuario para gestionar sus permisos</h5>
                        </div>
                        <div class="card-body">
                            <table id="usuarios" class="table table-hover nowrap" style="width:100%"></table>
                        </div>
                    </div>
                </div>
            </main>
            <?php include "vistas/general/footer.php"; ?>
        </div>
    </div>
    <?php include "vistas/general/scripts.php"; ?>
    <script src="<?php echo STATIC_URL; ?>js/permisos/permisos-usuarios.js" type="module"></script>
</body>
</html>
