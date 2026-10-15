<?php
$controller_permiso->validarAcceso(1, CPERMISO::EDITAR_USUARIOS->value);
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
                            <h1 class="h3 mb-3">Permisos del usuario</h1>
                        </div>
                        <div class="col-12 col-md-3 text-end">
                            <a href="<?php echo BASE_URL; ?>permisos_usuarios">
                                <div class="btn btn-info">Volver</div>
                            </a>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <b>Gestiona los permisos individuales. Si no hay override, hereda del rol.</b>
                        </div>
                        <div class="card-body" id="area-permisos"></div>
                    </div>
                </div>
            </main>
            <?php include "vistas/general/footer.php"; ?>
        </div>
    </div>
    <?php include "vistas/general/scripts.php"; ?>
    <script>const ID_USER = <?php echo (int)$_GET['id_usuario']; ?>;</script>
    <script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.8.11/dist/dotlottie-wc.js" type="module"></script>
    <script src="<?php echo STATIC_URL; ?>js/usuarios/editar-usuario.js" type="module"></script>
</body>
</html>
