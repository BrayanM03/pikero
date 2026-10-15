<?php
$controller_permiso->validarAcceso(1, CPERMISO::EDITAR_ROLES->value);
require_once __DIR__ . '/../controllers/RolController.php';
$controller_rol = new RolController();
$rol_resp = $controller_rol->obtener_rol(2, $_GET['id_rol']);
$rol = $rol_resp['estatus'] ? $rol_resp['data'][0] : ['nombre' => 'Rol'];
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
                            <h1 class="h3 mb-3">Permisos del rol: <?= htmlspecialchars($rol['nombre']) ?></h1>
                        </div>
                        <div class="col-12 col-md-3 text-end">
                            <a href="<?php echo BASE_URL; ?>roles"><div class="btn btn-info">Atrás</div></a>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><b>Activa o desactiva permisos para este rol.</b></div>
                        <div class="card-body" id="area-permisos"></div>
                    </div>
                </div>
            </main>
            <?php include "vistas/general/footer.php"; ?>
        </div>
    </div>
    <?php include "vistas/general/scripts.php"; ?>
    <script>const ID_ROL = <?php echo (int)$_GET['id_rol']; ?>;</script>
    <script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.8.11/dist/dotlottie-wc.js" type="module"></script>
    <script src="<?php echo STATIC_URL; ?>js/roles/roles-permisos.js" type="module"></script>
</body>
</html>
