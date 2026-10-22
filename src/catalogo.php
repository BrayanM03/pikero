<?php
$controller_permiso->validarAcceso(1, CPERMISO::VER_CATALOGOS->value);
$tipo = $_GET['catalogo'] ?? 'categorias';
$map = [
    'categorias' => ['Categorías', 'categoría', 'categorías'],
    'marcas' => ['Marcas', 'marca', 'marcas'],
    'unidades' => ['Unidades de medida', 'unidad', 'unidades'],
    'formas_pago' => ['Formas de pago', 'forma de pago', 'formas de pago'],
];
if (!isset($map[$tipo])) {
    $tipo = 'categorias';
}
$meta = $map[$tipo];
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
                            <h1 class="h3 mb-3"><?php echo $meta[0]; ?></h1>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="<?php echo BASE_URL; ?>catalogos" class="btn btn-secondary">Volver</a>
                            <div class="btn btn-success" onclick="agregarCatalogo()">Agregar <?php echo $meta[1]; ?></div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><?php echo ucfirst($meta[2]); ?> de este negocio</h5>
                        </div>
                        <div class="card-body">
                            <table id="tabla-catalogo" class="table table-hover nowrap" style="width:100%"></table>
                        </div>
                    </div>
                </div>
            </main>
            <?php include "vistas/general/footer.php"; ?>
        </div>
    </div>
    <?php include "vistas/general/scripts.php"; ?>
    <script>
        window.CATALOGO_TIPO = <?php echo json_encode($tipo); ?>;
        window.CATALOGO_LABELS = <?php echo json_encode(['singular' => $meta[1], 'plural' => $meta[2]]); ?>;
    </script>
    <script src="<?php echo STATIC_URL; ?>js/catalogos/catalogos.js" type="module"></script>
</body>
</html>
