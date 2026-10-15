<?php
$controller_permiso->validarAcceso(1, CPERMISO::VER_USUARIOS->value);
$crear_usuario = $controller_permiso->verificarPermiso(2, CPERMISO::CREAR_USUARIOS->value);
include "vistas/general/header.php";
?>
<body>
    <div class="wrapper">
        <?php include "vistas/general/sidebar.php"; ?>
        <div class="main">
            <?php include "vistas/general/navbar.php"; ?>
            <main class="content">
                <div class="container-fluid p-0 animate__animated animate__fadeIn animate__faster">
                    <div class="row mb-2">
                        <div class="col-12 col-md-6">
                            <h1 class="h3 mb-3">Área de usuarios</h1>
                        </div>
                        <div class="col-12 col-md-6 text-end">
                              <?php
                              if ($crear_usuario['estatus']): ?>
                            <a href="<?= BASE_URL?>usuarios/registrar"><div class="btn btn-success">Agregar nuevo</div></a>
                            <?php endif ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Usuarios de <?php echo htmlspecialchars($_SESSION['ferreteria'] ?? ''); ?></h5>
                                </div>
                                <div class="card-body">
                                    <table id="usuarios" class="table table-hover nowrap" style="width:100%"></table>
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
    <script src="<?php echo STATIC_URL; ?>js/usuarios/traer-lista-usuarios.js" type="module"></script>
</body>
</html>
