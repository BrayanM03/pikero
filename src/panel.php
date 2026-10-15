<?php
require_once __DIR__ . '/../models/Panel.php';
$panel_model = new Panel();
$resumen_panel = $panel_model->resumen()['data'];
include "vistas/general/header.php";
?>
<body>
    <div class="wrapper">
        <?php include "vistas/general/sidebar.php"; ?>
        <div class="main">
            <?php include "vistas/general/navbar.php"; ?>
            <main class="content">
                <div class="container-fluid p-0">
                    <h1 class="h3 mb-3">Panel de Control: <strong><?php echo htmlspecialchars($_SESSION['ferreteria'] ?? 'Ferretería'); ?></strong></h1>
                    <p class="text-muted mb-4">
                        Sucursal: <strong><?php echo htmlspecialchars($_SESSION['sucursal'] ?? ''); ?></strong>
                    </p>

                    <div class="row g-3 mb-4">
                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100 border-start border-success border-4">
                                <div class="card-body">
                                    <h2 class="h6 text-muted">Ventas de hoy</h2>
                                    <div class="h2 mt-1 mb-1">$<?= number_format($resumen_panel['ventas_hoy'], 2) ?></div>
                                    <span class="text-muted"><?= (int)$resumen_panel['cantidad_ventas_hoy'] ?> tickets completados</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100 border-start border-primary border-4">
                                <div class="card-body">
                                    <h2 class="h6 text-muted">Productos activos</h2>
                                    <div class="h2 mt-1 mb-1"><?= number_format($resumen_panel['productos']) ?></div>
                                    <span class="text-muted">Catálogo actual</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100 border-start border-info border-4">
                                <div class="card-body">
                                    <h2 class="h6 text-muted">Clientes registrados</h2>
                                    <div class="h2 mt-1 mb-1"><?= number_format($resumen_panel['clientes']) ?></div>
                                    <span class="text-muted">Sin contar público en general</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100 border-start <?= $resumen_panel['caja_abierta'] ? 'border-success' : 'border-secondary' ?> border-4">
                                <div class="card-body">
                                    <h2 class="h6 text-muted">Estatus de caja</h2>
                                    <div class="h2 mt-1 mb-1"><?= $resumen_panel['caja_abierta'] ? 'Abierta' : 'Cerrada' ?></div>
                                    <span class="text-muted"><?= $resumen_panel['caja_abierta'] ? 'Cobrado en turno: $' . number_format((float)$resumen_panel['caja']['total_ventas'], 2) : 'Sin turno activo en esta sucursal' ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <section class="card">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div>
                                <h2 class="h5 card-title mb-1">Tendencia de ventas</h2>
                                <span class="text-muted small">Importe y número de tickets completados</span>
                            </div>
                            <div class="d-flex flex-wrap align-items-end gap-2">
                                <div>
                                    <label class="form-label small mb-1" for="panel-periodo">Periodo</label>
                                    <select id="panel-periodo" class="form-select form-select-sm">
                                        <option value="day">Día</option>
                                        <option value="range">Rango de días</option>
                                        <option value="week">Semana</option>
                                        <option value="month">Mes</option>
                                        <option value="year">Año</option>
                                    </select>
                                </div>
                                <div id="filtro-dia">
                                    <label class="form-label small mb-1" for="panel-fecha">Fecha</label>
                                    <input id="panel-fecha" type="date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                                </div>
                                <div id="filtro-rango" class="d-none">
                                    <label class="form-label small mb-1" for="panel-inicio">Desde / hasta</label>
                                    <div class="d-flex gap-1">
                                        <input id="panel-inicio" type="date" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>">
                                        <input id="panel-fin" type="date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                                    </div>
                                </div>
                                <div id="filtro-mes" class="d-none">
                                    <label class="form-label small mb-1" for="panel-mes">Mes</label>
                                    <input id="panel-mes" type="month" class="form-control form-control-sm" value="<?= date('Y-m') ?>">
                                </div>
                                <div id="filtro-anio" class="d-none">
                                    <label class="form-label small mb-1" for="panel-anio">Año</label>
                                    <input id="panel-anio" type="number" min="2000" max="<?= date('Y') + 1 ?>" class="form-control form-control-sm" value="<?= date('Y') ?>">
                                </div>
                                <button id="btn-panel-aplicar" type="button" class="btn btn-sm btn-primary" title="Actualizar gráfica"><i data-feather="refresh-cw"></i></button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div style="height: 340px; position: relative;"><canvas id="grafica-ventas-panel" aria-label="Gráfica de ventas" role="img"></canvas></div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>
    <?php include "vistas/general/scripts.php"; ?>
    <script src="<?php echo STATIC_URL; ?>js/panel/panel.js" type="module"></script>
</body>
</html>
