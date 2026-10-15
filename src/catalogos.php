<?php
$controller_permiso->validarAcceso(1, CPERMISO::VER_CATALOGOS->value);
include "vistas/general/header.php";
?>
<body>
    <div class="wrapper">
        <?php include "vistas/general/sidebar.php"; ?>
        <div class="main">
            <?php include "vistas/general/navbar.php"; ?>
            <main class="content">
                <div class="container-fluid p-0">
                    <h1 class="h3 mb-3">Catálogos</h1>
                    <p class="text-muted">Datos maestros de tu ferretería. Cada registro queda ligado a tu empresa.</p>
                    <div class="row">
                        <?php
                        $cards = [
                            ['sucursales', 'building', 'Sucursales', 'Tiendas de la ferretería'],
                            ['categorias', 'grid', 'Categorías', 'Clasificación de productos'],
                            ['marcas', 'award', 'Marcas', 'Marcas que manejas'],
                            ['unidades', 'box', 'Unidades', 'Pieza, caja, metro, kg'],
                            ['formas_pago', 'credit-card', 'Formas de pago', 'Efectivo, tarjeta, etc.'],
                        ];
                        foreach ($cards as $card):
                        ?>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><i data-feather="<?php echo $card[1]; ?>"></i> <?php echo $card[2]; ?></h5>
                                    <p class="card-text"><?php echo $card[3]; ?></p>
                                    <a href="<?php echo BASE_URL . $card[0]; ?>" class="btn btn-outline-primary btn-sm">Abrir</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </main>
            <?php include "vistas/general/footer.php"; ?>
        </div>
    </div>
    <?php include "vistas/general/scripts.php"; ?>
</body>
</html>
