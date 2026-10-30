     <!-- ============ Modal: Ventas por forma de pago ============ -->
    <div class="modal fade pk-modal" id="modal-ventas" tabindex="-1" aria-labelledby="modal-ventas-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content pk-a-green">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-ventas-label"><i data-feather="shopping-bag"></i>Ventas por forma de pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="pk-label">Total vendido hoy</p>
                    <p class="pk-big mb-2"><?= $money($ventas_hoy) ?></p>
                    <?php $render_pagos($pagos_ventas); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ Modal: Utilidad ============ -->
    <div class="modal fade pk-modal" id="modal-utilidad" tabindex="-1" aria-labelledby="modal-utilidad-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content pk-a-violet">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-utilidad-label"><i data-feather="trending-up"></i>Desglose de utilidad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="pk-label">Utilidad neta de hoy</p>
                    <p class="pk-big mb-2"><?= $money($utilidad_hoy) ?></p>

                    <div class="pk-kv"><span>Ventas</span><span class="fw-semibold"><?= $money($ventas_hoy) ?></span></div>
                    <div class="pk-kv"><span>(−) Costo de productos vendidos</span><span class="fw-semibold">-<?= $money($costo_hoy) ?></span></div>
                    <div class="pk-kv"><span>Utilidad bruta</span><span class="fw-semibold"><?= $money($utilidad_bruta) ?></span></div>
                    <div class="pk-kv"><span>(−) Gastos</span><span class="fw-semibold">-<?= $money($gastos_hoy) ?></span></div>
                    <div class="pk-kv is-sum"><span>Utilidad neta</span><span><?= $money($utilidad_hoy) ?></span></div>

                    <?php if ($pagos_util): ?>
                        <h6 class="pk-sub-title"><i data-feather="credit-card"></i>Utilidad por forma de pago</h6>
                        <?php $render_pagos($pagos_util); ?>
                    <?php elseif ($pagos_ventas): ?>
                        <h6 class="pk-sub-title"><i data-feather="credit-card"></i>Ventas por forma de pago</h6>
                        <?php $render_pagos($pagos_ventas); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ Modal: Créditos pendientes ============ -->
    <div class="modal fade pk-modal" id="modal-creditos" tabindex="-1" aria-labelledby="modal-creditos-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content pk-a-amber">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-creditos-label"><i data-feather="file-text"></i>Créditos pendientes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="pk-label">Saldo total por cobrar</p>
                    <p class="pk-big mb-2"><?= $money($cred_total) ?></p>

                    <div class="pk-kv"><span>Clientes con saldo</span><span class="fw-semibold"><?= number_format($cred_clientes) ?></span></div>
                    <div class="pk-kv"><span>Por vencer</span><span class="fw-semibold"><?= $money($cred_por_venc) ?></span></div>
                    <div class="pk-kv"><span>Vencido</span><span class="fw-semibold text-danger"><?= $money($cred_vencido) ?></span></div>
                    <div class="pk-kv is-sum"><span>Abonos cobrados hoy</span><span><?= $money($abonos_hoy) ?></span></div>

                    <?php if ($pagos_abonos): ?>
                        <h6 class="pk-sub-title"><i data-feather="credit-card"></i>Abonos de hoy por forma de pago</h6>
                        <?php $render_pagos($pagos_abonos); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>