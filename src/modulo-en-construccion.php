<?php
include "vistas/general/header.php";
$nombre_modulo = $_GET['modulo_nombre'] ?? ($titulo_vista ?? 'Módulo');
?>
<body>
    <div class="wrapper">
        <?php include "vistas/general/sidebar.php"; ?>
        <div class="main">
            <?php include "vistas/general/navbar.php"; ?>
            <main class="content">
                <div class="container-fluid p-0">
                    <h1 class="h3 mb-3"><?php echo htmlspecialchars($nombre_modulo); ?></h1>
                    <div class="card">
                        <div class="card-body">
                            <p class="mb-0">Esta sección ya está enrutada. El siguiente paso es implementar la lógica de ferretería sobre esta misma arquitectura.</p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <?php include "vistas/general/scripts.php"; ?>
</body>
</html>
