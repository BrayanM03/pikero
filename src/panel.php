<?php 
require_once __DIR__ . '/../models/Panel.php';
$panel_model = new Panel();
$filtros = array_intersect_key($_GET, array_flip(['periodo', 'fecha', 'inicio', 'fin', 'mes', 'anio']));
$respuesta = $panel_model->resumen($filtros);
$errorPeriodo = $respuesta['mensaje'] ?? null;
if (!$respuesta['estatus']) {
    $filtros = [];
    $respuesta = $panel_model->resumen();
}
$resumen_panel = $respuesta['data'];
$periodo = $resumen_panel['periodo'];
$inicioPeriodo = $resumen_panel['inicio_periodo'];
$ultimoDia = $resumen_panel['fin_periodo']?->modify('-1 day');
$etiquetaPeriodo = match (true) {
    $periodo === 'all' => ' de todo el historial',
    $periodo === 'day' && $inicioPeriodo->format('Y-m-d') === date('Y-m-d') => ' de hoy',
    $periodo === 'month' => ' de ' . $inicioPeriodo->format('m/Y'),
    $periodo === 'year' => ' de ' . $inicioPeriodo->format('Y'),
    $inicioPeriodo == $ultimoDia => ' del ' . $inicioPeriodo->format('d/m/Y'),
    default => ' del ' . $inicioPeriodo->format('d/m/Y') . ' al ' . $ultimoDia->format('d/m/Y'),
};
$valorFiltro = fn(string $clave, string $valor) => htmlspecialchars(
    is_string($filtros[$clave] ?? null) ? $filtros[$clave] : $valor,
    ENT_QUOTES,
    'UTF-8'
);
$consultaGrafica = http_build_query(['tipo' => 'grafica'] + $filtros);

/* ---------- Helpers de presentación ---------- */
$num   = fn($v) => (float)($v ?? 0);
$money = fn($v) => '$' . number_format((float)($v ?? 0), 2);

$caja_abierta = !empty($resumen_panel['caja_abierta']);
$caja         = $resumen_panel['caja'] ?? [];

/* ---------- Tarjetas superiores ---------- */
$ventas_periodo     = $num($resumen_panel['ventas_hoy'] ?? 0);
$gastos_periodo     = $num($resumen_panel['gastos_hoy'] ?? 0);
$utilidad_bruta = $num($resumen_panel['utilidad'] ?? 0);
$margen         = $ventas_periodo > 0 ? ($utilidad_bruta / $ventas_periodo) * 100 : 0;

$cred_total     = $num($resumen_panel['creditos_pendientes'] ?? 0);
$cred_vencido   = $num($resumen_panel['creditos_vencidos'] ?? 0);
$cred_por_venc  = max($cred_total - $cred_vencido, 0);
$cred_clientes  = (int)($resumen_panel['creditos_clientes'] ?? 0);
$abonos_hoy     = $num($resumen_panel['abonos_hoy'] ?? 0);

/* Formas de pago: ['efectivo'=>0,'tarjeta'=>0,'transferencia'=>0,'credito'=>0] */
$pagos_ventas = $resumen_panel['formas_pago'] ?? [];
$pagos_abonos = $resumen_panel['abonos_formas_pago'] ?? [];
$pagos_util   = $resumen_panel['utilidad_formas_pago'] ?? [];

/* ---------- Arqueo de caja ---------- */
$fondo_inicial   = $num($caja['monto_apertura'] ?? 0);
$cobrado_forma_pago = $resumen_panel['cobrado_por_forma_pago'];

$ventas_efectivo = 0.00;
$ventas_varias_fp = 0.00;

foreach ($cobrado_forma_pago as $pago) {
    $monto  = (float)$pago['total'];

    if ($pago['es_efectivo']) {
        $ventas_efectivo += $monto;
    } else {
        $ventas_varias_fp += $monto; // Acumula tarjeta, transferencia, cheque, etc.
    }
}
$entradas_efectivo = $num($caja['total_entradas'] ?? 0);
$salidas_efectivo = $num($caja['total_salidas'] ?? 0);
$efectivo_caja = $fondo_inicial + $ventas_efectivo + $entradas_efectivo - $salidas_efectivo;

/* ---------- Componente: lista de formas de pago ---------- */
$iconos_pago = [
    'efectivo' => 'dollar-sign', 'tarjeta' => 'credit-card', 'transferencia' => 'repeat',
    'credito' => 'clock', 'crédito' => 'clock', 'cheque' => 'file-text', 'otros' => 'more-horizontal',
];
$render_pagos = function (array $pagos) use ($iconos_pago, $money) {
    $total = array_sum(array_map('floatval', $pagos));
    if (!$pagos) {
        echo '<p class="text-muted small mb-0">Sin movimientos registrados.</p>';
        return;
    }
    foreach ($pagos as $nombre => $monto) {
        $monto = (float)$monto;
        $pct   = $total > 0 ? round(($monto / $total) * 100, 1) : 0;
        $icono = $iconos_pago[strtolower($nombre)] ?? 'circle';
        echo '<div class="pk-pay">'
           . '<span class="pk-pay-icon"><i data-feather="' . $icono . '"></i></span>'
           . '<div class="pk-pay-body">'
           .   '<div class="d-flex justify-content-between"><span class="fw-semibold text-capitalize">' . htmlspecialchars($nombre) . '</span>'
           .   '<span class="fw-semibold">' . $money($monto) . '</span></div>'
           .   '<div class="pk-bar"><span style="width:' . $pct . '%"></span></div>'
           .   '<small class="text-muted">' . $pct . '% del total</small>'
           . '</div></div>';
    }
};

include "vistas/general/header.php";
?>
<style>
/* =========================================================
   Panel de control · estilos con prefijo .pk- (no afectan al resto)
   ========================================================= */
:root{
    --pk-ink:#0f172a; --pk-muted:#64748b; --pk-line:#e2e8f0; --pk-bg:#f8fafc;
    --pk-radius:18px;
    --pk-shadow:0 1px 2px rgba(15,23,42,.05),0 8px 24px -8px rgba(15,23,42,.14);
    --pk-shadow-hover:0 2px 4px rgba(15,23,42,.06),0 18px 36px -12px rgba(15,23,42,.24);
    --pk-g-green:linear-gradient(135deg,#10b981,#059669);
    --pk-g-coral:linear-gradient(135deg,#fb923c,#f43f5e);
    --pk-g-violet:linear-gradient(135deg,#8b5cf6,#6366f1);
    --pk-g-amber:linear-gradient(135deg,#fbbf24,#f59e0b);
    --pk-g-blue:linear-gradient(135deg,#38bdf8,#2563eb);
    --pk-g-teal:linear-gradient(135deg,#2dd4bf,#0891b2);
    --pk-g-slate:linear-gradient(135deg,#475569,#1e293b);
}

/* Encabezado */
.pk-hero{
    border-radius:var(--pk-radius); padding:1.5rem 1.75rem; color:#fff; position:relative; overflow:hidden;
    background:linear-gradient(120deg,#0f172a 0%,#1e3a8a 55%,#2563eb 100%);
    box-shadow:var(--pk-shadow);
}
.pk-hero::after{
    content:""; position:absolute; right:-60px; top:-80px; width:260px; height:260px; border-radius:50%;
    background:radial-gradient(circle,rgba(255,255,255,.22),transparent 70%);
}
.pk-hero h1{ color:#fff; margin:0; font-weight:700; }
.pk-hero .pk-sub{ color:rgba(255,255,255,.78); margin:.25rem 0 0; }
.pk-chip{
    display:inline-flex; align-items:center; gap:.4rem; padding:.35rem .8rem; border-radius:999px;
    background:rgba(255,255,255,.16); backdrop-filter:blur(4px); color:#fff; font-size:.82rem; font-weight:600;
}
.pk-chip .pk-dot{ width:8px; height:8px; border-radius:50%; background:#4ade80; box-shadow:0 0 0 3px rgba(74,222,128,.3); }
.pk-chip.is-closed .pk-dot{ background:#cbd5e1; box-shadow:0 0 0 3px rgba(203,213,225,.3); }
.pk-chip svg{ width:15px; height:15px; }

/* Tarjetas KPI */
.pk-card{
    position:relative; display:block; width:100%; height:100%; text-align:left; border:1px solid var(--pk-line);
    border-radius:var(--pk-radius); background:#fff; padding:1.15rem 1.25rem; box-shadow:var(--pk-shadow);
    transition:transform .2s ease, box-shadow .2s ease; color:var(--pk-ink); overflow:hidden;
}
.pk-card::before{ /* barra de acento superior */
    content:""; position:absolute; left:0; right:0; top:0; height:4px; background:var(--pk-accent,var(--pk-g-blue));
}
button.pk-card{ cursor:pointer; font:inherit; }
.pk-clickable:hover{ transform:translateY(-3px); box-shadow:var(--pk-shadow-hover); }
.pk-clickable:focus-visible{ outline:3px solid rgba(37,99,235,.45); outline-offset:2px; }
.pk-icon{
    width:46px; height:46px; border-radius:14px; display:grid; place-items:center; color:#fff;
    background:var(--pk-accent,var(--pk-g-blue)); box-shadow:0 8px 16px -6px rgba(15,23,42,.35); flex:none;
}
.pk-icon svg{ width:22px; height:22px; }
.pk-label{ font-size:.82rem; color:var(--pk-muted); font-weight:600; margin:0; }
.pk-value{ font-size:1.7rem; font-weight:700; letter-spacing:-.02em; line-height:1.15; margin:.15rem 0 0; }
.pk-foot{ font-size:.8rem; color:var(--pk-muted); margin-top:.5rem; display:flex; align-items:center; justify-content:space-between; gap:.5rem; }
.pk-more{ display:inline-flex; align-items:center; gap:.2rem; color:#2563eb; font-weight:600; font-size:.78rem; white-space:nowrap; }
.pk-more svg{ width:14px; height:14px; }
.pk-badge{
    display:inline-flex; align-items:center; gap:.25rem; padding:.15rem .55rem; border-radius:999px;
    font-size:.75rem; font-weight:700; background:#ecfdf5; color:#047857;
}
.pk-badge.is-neg{ background:#fef2f2; color:#b91c1c; }
.pk-badge.is-warn{ background:#fffbeb; color:#b45309; }

.pk-a-green{ --pk-accent:var(--pk-g-green); }
.pk-a-coral{ --pk-accent:var(--pk-g-coral); }
.pk-a-violet{ --pk-accent:var(--pk-g-violet); }
.pk-a-amber{ --pk-accent:var(--pk-g-amber); }
.pk-a-blue{ --pk-accent:var(--pk-g-blue); }
.pk-a-teal{ --pk-accent:var(--pk-g-teal); }
.pk-a-slate{ --pk-accent:var(--pk-g-slate); }

/* Tarjetas compactas (segunda fila) */
.pk-card.pk-compact{ display:flex; align-items:center; gap:1rem; padding:1rem 1.15rem; }
.pk-card.pk-compact .pk-value{ font-size:1.45rem; }

/* Secciones */
.pk-section{
    border:1px solid var(--pk-line); border-radius:var(--pk-radius); background:#fff; box-shadow:var(--pk-shadow); height:100%;
    overflow:hidden;
}
.pk-section-head{
    padding:1.1rem 1.25rem; border-bottom:1px solid var(--pk-line);
    display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:.75rem;
    background:linear-gradient(180deg,#fff,var(--pk-bg));
}
.pk-section-title{ display:flex; align-items:center; gap:.75rem; }
.pk-section-title h2{ font-size:1.05rem; font-weight:700; margin:0; }
.pk-section-title small{ color:var(--pk-muted); }
.pk-section-title .pk-icon{ width:40px; height:40px; border-radius:12px; }
.pk-section-body{ padding:1.25rem; }

/* Arqueo de caja */
.pk-arqueo{ display:flex; flex-direction:column; gap:.5rem; }
.pk-row{
    display:flex; align-items:center; gap:.85rem; padding:.7rem .9rem; border-radius:14px; background:var(--pk-bg);
    border:1px solid var(--pk-line);
}
.pk-sign{
    width:34px; height:34px; border-radius:10px; display:grid; place-items:center; font-weight:800; font-size:1rem; flex:none;
}
.pk-sign.plus{ background:#dcfce7; color:#15803d; }
.pk-sign.minus{ background:#fee2e2; color:#b91c1c; }
.pk-sign.info{ background:#e0f2fe; color:#0369a1; }
.pk-row-main{ flex:1; min-width:0; }
.pk-row-main strong{ display:block; font-size:.93rem; }
.pk-row-main small{ color:var(--pk-muted); }
.pk-row-amount{ font-weight:700; font-size:1.02rem; white-space:nowrap; }
.pk-row.is-total{
    background:linear-gradient(135deg,#0f172a,#1e40af); border:0; color:#fff; padding:1rem 1.1rem;
    box-shadow:0 14px 26px -12px rgba(30,64,175,.6);
}
.pk-row.is-total .pk-sign{ background:rgba(255,255,255,.18); color:#fff; }
.pk-row.is-total small{ color:rgba(255,255,255,.75); }
.pk-row.is-total .pk-row-amount{ font-size:1.35rem; }
.pk-row.is-info{ background:#f0f9ff; border-style:dashed; border-color:#bae6fd; }
.pk-divider{ height:0; border-top:2px dashed var(--pk-line); margin:.15rem 0; }

/* Formas de pago */
.pk-pay{ display:flex; gap:.85rem; padding:.75rem 0; border-bottom:1px dashed var(--pk-line); }
.pk-pay:last-child{ border-bottom:0; }
.pk-pay-icon{ width:38px; height:38px; border-radius:12px; display:grid; place-items:center; background:#eff6ff; color:#2563eb; flex:none; }
.pk-pay-icon svg{ width:18px; height:18px; }
.pk-pay-body{ flex:1; min-width:0; }
.pk-bar{ height:7px; border-radius:99px; background:#e2e8f0; margin:.35rem 0 .2rem; overflow:hidden; }
.pk-bar span{ display:block; height:100%; border-radius:99px; background:linear-gradient(90deg,#38bdf8,#2563eb); }

/* Modales */
.pk-modal .modal-content{ border:0; border-radius:22px; overflow:hidden; box-shadow:0 30px 60px -20px rgba(15,23,42,.5); }
.pk-modal .modal-header{ border:0; color:#fff; padding:1.25rem 1.5rem; background:var(--pk-accent,var(--pk-g-blue)); }
.pk-modal .modal-title{ color:#fff; font-weight:700; display:flex; align-items:center; gap:.6rem; }
.pk-modal .modal-title svg{ width:20px; height:20px; }
.pk-modal .btn-close{ filter:invert(1) grayscale(1) brightness(2); opacity:.9; }
.pk-modal .modal-body{ padding:1.25rem 1.5rem 1.5rem; }
.pk-big{ font-size:2rem; font-weight:800; letter-spacing:-.02em; margin:0; }
.pk-kv{ display:flex; justify-content:space-between; align-items:center; padding:.55rem 0; border-bottom:1px dashed var(--pk-line); }
.pk-kv:last-child{ border-bottom:0; }
.pk-kv.is-sum{ font-weight:800; border-top:2px solid var(--pk-ink); border-bottom:0; margin-top:.25rem; padding-top:.7rem; }
.pk-sub-title{ font-size:.9rem; font-weight:700; margin:1.1rem 0 .25rem; display:flex; align-items:center; gap:.4rem; }
.pk-sub-title svg{ width:16px; height:16px; color:#2563eb; }

/* Gráfica: filtros */
.pk-filters .form-select,.pk-filters .form-control{ border-radius:10px; }
.pk-filters .btn{ border-radius:10px; height:31px; display:inline-grid; place-items:center; }

/* Responsivo */
@media (max-width:575.98px){
    .pk-hero{ padding:1.15rem; }
    .pk-value{ font-size:1.45rem; }
    .pk-row{ padding:.6rem .7rem; gap:.65rem; }
    .pk-row.is-total .pk-row-amount{ font-size:1.15rem; }
    .pk-section-head,.pk-section-body{ padding:1rem; }
    .pk-filters{ width:100%; }
    .pk-filters > div{ flex:1 1 130px; }
}
@media (prefers-reduced-motion:reduce){ .pk-card{ transition:none; } .pk-clickable:hover{ transform:none; } }
</style>
<body>
    <div class="wrapper">
        <?php include "vistas/general/sidebar.php"; ?>
        <div class="main">
            <?php include "vistas/general/navbar.php"; ?>
            <main class="content">
                <div class="container-fluid p-0">

                    <!-- Encabezado -->
                    <div class="pk-hero mb-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 position-relative" style="z-index:1">
                            <div>
                                <h1 class="h3">Panel de control</h1>
                                <p class="pk-sub">
                                    <strong><?= htmlspecialchars($_SESSION['ferreteria'] ?? 'Ferretería') ?></strong>
                                    &nbsp;•&nbsp; Sucursal <?= htmlspecialchars($_SESSION['sucursal'] ?? '') ?>
                                </p>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="pk-chip <?= $caja_abierta ? '' : 'is-closed' ?>">
                                    <span class="pk-dot"></span>Caja <?= $caja_abierta ? 'abierta' : 'cerrada' ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <form method="get" id="panel-filtros" class="mb-3 d-flex flex-wrap align-items-end gap-2">
                        <div>
                            <label class="form-label small mb-1" for="panel-periodo">Periodo</label>
                            <select id="panel-periodo" name="periodo" class="form-select form-select-sm">
                                <option value="day" <?= $periodo === 'day' ? 'selected' : '' ?>>Día</option>
                                <option value="range" <?= $periodo === 'range' ? 'selected' : '' ?>>Rango de días</option>
                                <option value="week" <?= $periodo === 'week' ? 'selected' : '' ?>>Semana</option>
                                <option value="month" <?= $periodo === 'month' ? 'selected' : '' ?>>Mes</option>
                                <option value="year" <?= $periodo === 'year' ? 'selected' : '' ?>>Año</option>
                                <option value="all" <?= $periodo === 'all' ? 'selected' : '' ?>>Todo el historial</option>
                            </select>
                        </div>
                        <div id="filtro-dia">
                            <label class="form-label small mb-1" for="panel-fecha">Fecha</label>
                            <input id="panel-fecha" name="fecha" type="date" class="form-control form-control-sm" value="<?= $valorFiltro('fecha', date('Y-m-d')) ?>">
                        </div>
                        <div id="filtro-rango" class="d-none">
                            <label class="form-label small mb-1" for="panel-inicio">Desde / hasta</label>
                            <div class="d-flex gap-1">
                                <input id="panel-inicio" name="inicio" type="date" class="form-control form-control-sm" value="<?= $valorFiltro('inicio', date('Y-m-01')) ?>">
                                <input id="panel-fin" name="fin" type="date" class="form-control form-control-sm" value="<?= $valorFiltro('fin', date('Y-m-d')) ?>">
                            </div>
                        </div>
                        <div id="filtro-mes" class="d-none">
                            <label class="form-label small mb-1" for="panel-mes">Mes</label>
                            <input id="panel-mes" name="mes" type="month" class="form-control form-control-sm" value="<?= $valorFiltro('mes', date('Y-m')) ?>">
                        </div>
                        <div id="filtro-anio" class="d-none">
                            <label class="form-label small mb-1" for="panel-anio">Año</label>
                            <input id="panel-anio" name="anio" type="number" min="2000" max="<?= date('Y') + 1 ?>" class="form-control form-control-sm" value="<?= $valorFiltro('anio', date('Y')) ?>">
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">Aplicar</button>
                    </form>
                    <?php if ($errorPeriodo): ?>
                        <div class="alert alert-warning mt-2" role="alert"><?= htmlspecialchars($errorPeriodo) ?>. Se muestran los datos de hoy.</div>
                    <?php endif; ?>

                    <!-- Tarjetas principales (clic = desglose) -->
                    <div class="row g-3 mb-3">
                        <div class="col-xl-3 col-md-6">
                            <button type="button" class="pk-card pk-clickable pk-a-green" data-bs-toggle="modal" data-bs-target="#modal-ventas">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <p class="pk-label">Ventas<?= $etiquetaPeriodo ?></p>
                                        <p class="pk-value"><?= $money($ventas_periodo) ?></p>
                                    </div>
                                    <span class="pk-icon"><i data-feather="shopping-bag"></i></span>
                                </div>
                                <div class="pk-foot">
                                    <span><?= (int)($resumen_panel['cantidad_ventas_hoy'] ?? 0) ?> tickets registrados</span>
                                    <span class="pk-more">Ver pagos <i data-feather="chevron-right"></i></span>
                                </div>
                            </button>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="pk-card pk-a-coral">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <p class="pk-label">Gastos<?= $etiquetaPeriodo ?></p>
                                        <p class="pk-value"><?= $money($gastos_periodo) ?></p>
                                    </div>
                                    <span class="pk-icon"><i data-feather="trending-down"></i></span>
                                </div>
                                <div class="pk-foot"><span>Total de egresos registrados</span></div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <button type="button" class="pk-card pk-clickable pk-a-violet" data-bs-toggle="modal" data-bs-target="#modal-utilidad">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <p class="pk-label">Utilidad bruta estimada<?= $etiquetaPeriodo ?></p>
                                        <p class="pk-value"><?= $money($utilidad_bruta) ?></p>
                                    </div>
                                    <span class="pk-icon"><i data-feather="trending-up"></i></span>
                                </div>
                                <div class="pk-foot">
                                    <span class="pk-badge <?= $utilidad_bruta < 0 ? 'is-neg' : '' ?>"><?= number_format($margen, 1) ?>% margen</span>
                                    <span class="pk-more">Ver desglose <i data-feather="chevron-right"></i></span>
                                </div>
                            </button>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <button type="button" class="pk-card pk-clickable pk-a-amber" data-bs-toggle="modal" data-bs-target="#modal-creditos">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <p class="pk-label">Saldo pendiente actual</p>
                                        <p class="pk-value"><?= $money($cred_total) ?></p>
                                    </div>
                                    <span class="pk-icon"><i data-feather="file-text"></i></span>
                                </div>
                                <div class="pk-foot">
                                    <span class="pk-badge <?= $cred_vencido > 0 ? 'is-warn' : '' ?>"><?= $cred_vencido > 0 ? $money($cred_vencido) . ' vencido' : 'Sin vencidos' ?></span>
                                    <span class="pk-more">Ver desglose <i data-feather="chevron-right"></i></span>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Tarjetas secundarias -->
                    <div class="row g-3 mb-4">
                        <div class="col-xl-4 col-md-6">
                            <div class="pk-card pk-compact pk-a-blue">
                                <span class="pk-icon"><i data-feather="package"></i></span>
                                <div>
                                    <p class="pk-label">Productos activos</p>
                                    <p class="pk-value"><?= number_format((int)($resumen_panel['productos'] ?? 0)) ?></p>
                                    <small class="text-muted">Catálogo actual</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-md-6">
                            <div class="pk-card pk-compact pk-a-teal">
                                <span class="pk-icon"><i data-feather="users"></i></span>
                                <div>
                                    <p class="pk-label">Clientes registrados</p>
                                    <p class="pk-value"><?= number_format((int)($resumen_panel['clientes'] ?? 0)) ?></p>
                                    <small class="text-muted">Sin contar público en general</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-md-12">
                            <div class="pk-card pk-compact <?= $caja_abierta ? 'pk-a-green' : 'pk-a-slate' ?>">
                                <span class="pk-icon"><i data-feather="<?= $caja_abierta ? 'unlock' : 'lock' ?>"></i></span>
                                <div>
                                    <p class="pk-label">Estatus de caja</p>
                                    <p class="pk-value"><?= $caja_abierta ? 'Abierta' : 'Cerrada' ?></p>
                                    <small class="text-muted"><?= $caja_abierta ? 'Cobrado en turno: ' . $money($caja['total_ventas'] ?? 0) : 'Sin turno activo en esta sucursal' ?></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Arqueo de caja + gráfica -->
                    <div class="row g-3">
                        <div class="col-xl-5">
                            <section class="pk-section">
                                <div class="pk-section-head">
                                    <div class="pk-section-title">
                                        <span class="pk-icon pk-a-green"><i data-feather="inbox"></i></span>
                                        <div>
                                            <h2>Arqueo de caja</h2>
                                            <small>Resultado esperado del turno</small>
                                        </div>
                                    </div>
                                    <span class="pk-badge <?= $caja_abierta ? '' : 'is-warn' ?>"><?= $caja_abierta ? 'Turno activo' : 'Sin turno' ?></span>
                                </div>
                                <div class="pk-section-body">
                                    <div class="pk-arqueo">
                                        <div class="pk-row">
                                            <span class="pk-sign plus">+</span>
                                            <div class="pk-row-main"><strong>Fondo inicial</strong><small>Valor fijo de apertura</small></div>
                                            <span class="pk-row-amount"><?= $money($fondo_inicial) ?></span>
                                        </div>
                                        <div class="pk-row">
                                            <span class="pk-sign plus">+</span>
                                            <div class="pk-row-main"><strong>Cobrado en efectivo</strong><small>Ventas y abonos del turno</small></div>
                                            <span class="pk-row-amount"><?= $money($ventas_efectivo) ?></span>
                                        </div>
                                        <div class="pk-row">
                                            <span class="pk-sign plus">+</span>
                                            <div class="pk-row-main"><strong>Entradas de caja</strong><small>Movimientos del turno</small></div>
                                            <span class="pk-row-amount"><?= $money($entradas_efectivo) ?></span>
                                        </div>
                                        <div class="pk-row">
                                            <span class="pk-sign minus">−</span>
                                            <div class="pk-row-main"><strong>Salidas de caja</strong><small>Movimientos del turno</small></div>
                                            <span class="pk-row-amount">-<?= $money($salidas_efectivo) ?></span>
                                        </div>

                                        <div class="pk-divider"></div>

                                        <div class="pk-row is-total">
                                            <span class="pk-sign">=</span>
                                            <div class="pk-row-main"><strong>Efectivo en caja</strong><small>Resultado final esperado</small></div>
                                            <span class="pk-row-amount"><?= $money($efectivo_caja) ?></span>
                                        </div>

                                        <div class="pk-row is-info mt-1">
                                            <span class="pk-sign info"><i data-feather="credit-card" style="width:16px;height:16px"></i></span>
                                            <div class="pk-row-main"><span><strong>Informativo: Otras formas pago</strong><span><small>Registro acumulado</small></div>
                                            <span class="pk-row-amount"><?= $money($ventas_varias_fp) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div class="col-xl-7">
                            <section class="pk-section">
                                <div class="pk-section-head">
                                    <div class="pk-section-title">
                                        <span class="pk-icon pk-a-blue"><i data-feather="bar-chart-2"></i></span>
                                        <div>
                                            <h2>Tendencia de ventas</h2>
                                            <small>Importe y número de tickets registrados</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="pk-section-body">
                                    <div style="height: 340px; position: relative;"><canvas id="grafica-ventas-panel" data-consulta="<?= htmlspecialchars($consultaGrafica, ENT_QUOTES, 'UTF-8') ?>" aria-label="Gráfica de ventas" role="img"></canvas></div>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php include "vistas/general/scripts.php"; ?>
    <script src="<?php echo STATIC_URL; ?>js/panel/panel.js" type="module"></script>
</body>
</html>
