<?php
$controller_permiso->validarAcceso(1, CPERMISO::VER_PANEL_PERMISOS->value);
include "vistas/general/header.php";
?>
<body>
    <div class="wrapper">
        <?php include "vistas/general/sidebar.php"; ?>
        <div class="main">
            <?php include "vistas/general/navbar.php"; ?>
            <main class="content">
                <div class="container-fluid p-0">
                    <h1 class="h3 mb-3">Panel de permisos</h1>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <i class="fas fa-list-ul fa-3x text-primary mb-3"></i>
                                    <h5>Lista de permisos</h5>
                                    <p>Ver y crear permisos del sistema.</p>
                                    <a href="<?php echo BASE_URL; ?>permisos" class="btn btn-sm btn-outline-primary">Ver</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <i class="fas fa-user-shield fa-3x text-success mb-3"></i>
                                    <h5>Permisos por usuario</h5>
                                    <p>Overrides individuales sobre el rol.</p>
                                    <a href="<?php echo BASE_URL; ?>permisos_usuarios" class="btn btn-sm btn-outline-success">Gestionar</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <i class="fas fa-id-badge fa-3x text-info mb-3"></i>
                                    <h5>Roles</h5>
                                    <p>Gerente, vendedor e inventarista.</p>
                                    <a href="<?php echo BASE_URL; ?>roles" class="btn btn-sm btn-outline-info">Configurar</a>
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
</body>
</html>
