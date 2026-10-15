<?php
$controller_permiso->validarAcceso(1, CPERMISO::VER_PERMISOS->value);
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
                        <div class="col-12 col-md-9">
                            <h1 class="h3 mb-3">Gestor de permisos</h1>
                        </div>
                        <div class="col-12 col-md-3 text-end">
                            <div class="btn btn-success" onclick="nuevoPermiso()">Nuevo permiso</div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><b>Configura los permisos existentes del sistema.</b></div>
                        <div class="card-body" id="area-permisos"></div>
                    </div>
                </div>
            </main>
            <?php include "vistas/general/footer.php"; ?>
        </div>
    </div>
    <?php include "vistas/general/scripts.php"; ?>
    <script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.8.11/dist/dotlottie-wc.js" type="module"></script>
    <script src="<?php echo STATIC_URL; ?>js/config/permisos.js" type="module"></script>
</body>
</html>
