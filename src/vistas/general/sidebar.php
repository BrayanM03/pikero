<?php
$punto_venta_p = $controller_permiso->validarAcceso(2, CPERMISO::VER_PUNTO_VENTA->value);
$productos_p = $controller_permiso->validarAcceso(2, CPERMISO::VER_PRODUCTOS->value);
$inventario_p = $controller_permiso->validarAcceso(2, CPERMISO::VER_INVENTARIO->value);
$clientes_p = $controller_permiso->validarAcceso(2, CPERMISO::VER_CLIENTES->value);
$crear_clientes_p = $controller_permiso->validarAcceso(2, CPERMISO::CREAR_CLIENTES->value);
$editar_clientes_p = $controller_permiso->validarAcceso(2, CPERMISO::EDITAR_CLIENTES->value);
$cancelar_clientes_p = $controller_permiso->validarAcceso(2, CPERMISO::CANCELAR_CLIENTES->value);
$crear_productos_p = $controller_permiso->validarAcceso(2, CPERMISO::CREAR_PRODUCTOS->value); 
$editar_productos_p = $controller_permiso->validarAcceso(2, CPERMISO::EDITAR_PRODUCTOS->value);
$cancelar_productos_p = $controller_permiso->validarAcceso(2, CPERMISO::CANCELAR_PRODUCTOS->value);
$proveedores_p = $controller_permiso->validarAcceso(2, CPERMISO::VER_PROVEEDORES->value);
$gastos_p = $controller_permiso->validarAcceso(2, CPERMISO::VER_GASTOS->value);
$compras_p = $controller_permiso->validarAcceso(2, CPERMISO::VER_COMPRAS->value);
$ventas_p = $controller_permiso->validarAcceso(2, CPERMISO::VER_VENTAS->value);
$caja_p = $controller_permiso->validarAcceso(2, CPERMISO::VER_CAJA->value);
$usuarios_p = $controller_permiso->validarAcceso(2, CPERMISO::VER_USUARIOS->value);
$catalogos_p = $controller_permiso->validarAcceso(2, CPERMISO::VER_CATALOGOS->value);
$sucursales_p = $controller_permiso->validarAcceso(2, CPERMISO::VER_SUCURSALES->value);
$permisos_panel_p = $controller_permiso->validarAcceso(2, CPERMISO::VER_PANEL_PERMISOS->value);
$ferreterias_p = $controller_permiso->validarAcceso(2, CPERMISO::VER_FERRETERIAS->value);
$modulo_actual = explode('/', trim($_GET['url'] ?? 'panel', '/'))[0];
$modulos_operacion = ['ventas', 'compras', 'gastos', 'inventario', 'movimientos_inventario'];
$modulos_catalogos = ['catalogos', 'catalogo', 'categorias', 'marcas', 'unidades', 'formas_pago', 'sucursales', 'usuarios', 'panel_permisos'];
$es_operacion = in_array($modulo_actual, $modulos_operacion, true);
$es_catalogos = in_array($modulo_actual, $modulos_catalogos, true);
?>
<nav id="sidebar" class="sidebar js-sidebar">
    <div class="sidebar-content js-simplebar">
        <a class="sidebar-brand text-center mb-2" style="border-bottom:1px solid gray" href="<?php echo BASE_URL; ?>panel">
            <img src="<?php echo STATIC_URL; ?>img/titulo_logo.png" alt="Pikero" class="bord" style="width:150px; border-radius:7px; margin-right:1rem; margin-bottom:0px; background:#fff; padding:6px;"><br>
            <span style="font-size:10px; font-weight: 100">Gestión administrativa</span>
        </a>

        <ul class="sidebar-nav">
            <li class="sidebar-item <?php echo $modulo_actual === 'panel' ? 'active' : ''; ?>">
                <a class="sidebar-link <?php echo $modulo_actual === 'panel' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>panel">
                    <i class="align-middle" data-feather="sliders"></i> <span class="align-middle">Panel</span>
                </a>
            </li>

            <?php if ($punto_venta_p['estatus']): ?>
            <li class="sidebar-item <?php echo $modulo_actual === 'punto_venta' ? 'active' : ''; ?>">
                <a class="sidebar-link <?php echo $modulo_actual === 'punto_venta' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>punto_venta">
                    <i class="align-middle" data-feather="shopping-cart"></i> <span class="align-middle">Punto de venta</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($caja_p['estatus']): ?>
            <li class="sidebar-item <?php echo $modulo_actual === 'caja' ? 'active' : ''; ?>">
                <a class="sidebar-link <?php echo $modulo_actual === 'caja' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>caja">
                    <i class="align-middle" data-feather="dollar-sign"></i> <span class="align-middle">Caja</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($clientes_p['estatus']): ?>
            <li class="sidebar-item <?php echo $modulo_actual === 'clientes' ? 'active' : ''; ?>">
                <a class="sidebar-link <?php echo $modulo_actual === 'clientes' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>clientes">
                    <i class="align-middle" data-feather="users"></i> <span class="align-middle">Clientes</span>
                </a>
            </li>
            <?php endif; ?>

            <?php 

            if ($proveedores_p['estatus']): ?>
            <li class="sidebar-item <?php echo $modulo_actual === 'proveedores' ? 'active' : ''; ?>">
                <a class="sidebar-link <?php echo $modulo_actual === 'proveedores' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>proveedores">
                    <i class="align-middle" data-feather="truck"></i> <span class="align-middle">Proveedores</span>
                </a>
            </li>
            <?php endif; ?>

            <div class="accordion" id="accordionOperacion">
                <div class="accordion-item <?php echo $es_operacion ? 'active' : ''; ?>">
                    <li class="sidebar-item accordion-button <?php echo $es_operacion ? '' : 'collapsed'; ?> <?php echo $es_operacion ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#collapseOperacion" aria-expanded="<?php echo $es_operacion ? 'true' : 'false'; ?>" aria-controls="collapseOperacion">
                        <a class="sidebar-link d-flex align-items-center w-100" href="#">
                            <i class="align-middle" data-feather="folder"></i> <span class="align-middle">Operación</span>
                            <i class="ms-auto arrow-icon" data-feather="chevron-right"></i>
                        </a>
                    </li>
                    <div id="collapseOperacion" class="accordion-collapse collapse <?php echo $es_operacion ? 'show' : ''; ?>" style="margin-left:13px;" data-bs-parent="#accordionOperacion">
                        <div class="accordion-body">
                            <?php if ($ventas_p['estatus']): ?>
                            <li class="sidebar-item <?php echo $modulo_actual === 'ventas' ? 'active' : ''; ?>">
                                <a class="sidebar-link <?php echo $modulo_actual === 'ventas' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>ventas">
                                    <i class="align-middle" data-feather="file-text"></i> <span class="align-middle">Ventas</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if ($compras_p['estatus']): ?>
                            <li class="sidebar-item <?php echo $modulo_actual === 'compras' ? 'active' : ''; ?>">
                                <a class="sidebar-link <?php echo $modulo_actual === 'compras' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>compras">
                                    <i class="align-middle" data-feather="truck"></i> <span class="align-middle">Compras</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if ($gastos_p['estatus']): ?>
                            <li class="sidebar-item <?php echo $modulo_actual === 'gastos' ? 'active' : ''; ?>">
                                <a class="sidebar-link <?php echo $modulo_actual === 'gastos' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>gastos">
                                    <i class="align-middle" data-feather="credit-card"></i> <span class="align-middle">Gastos</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if ($inventario_p['estatus']): ?>
                            <li class="sidebar-item <?php echo $modulo_actual === 'inventario' ? 'active' : ''; ?>">
                                <a class="sidebar-link <?php echo $modulo_actual === 'inventario' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>inventario">
                                    <i class="align-middle" data-feather="archive"></i> <span class="align-middle">Inventario</span>
                                </a>
                            </li>
                            <li class="sidebar-item <?php echo $modulo_actual === 'movimientos_inventario' ? 'active' : ''; ?>">
                                <a class="sidebar-link <?php echo $modulo_actual === 'movimientos_inventario' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>movimientos_inventario">
                                    <i class="align-middle" data-feather="list"></i> <span class="align-middle">Movimientos</span>
                                </a>
                            </li>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($catalogos_p['estatus']): ?>
            <div class="accordion" id="accordionCatalogos">
                <div class="accordion-item <?php echo $es_catalogos ? 'active' : ''; ?>">
                    <li class="sidebar-item accordion-button <?php echo $es_catalogos ? '' : 'collapsed'; ?> <?php echo $es_catalogos ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#collapseCatalogos" aria-expanded="<?php echo $es_catalogos ? 'true' : 'false'; ?>" aria-controls="collapseCatalogos">
                        <a class="sidebar-link d-flex align-items-center w-100" href="#">
                            <i class="align-middle" data-feather="clipboard"></i>
                            <span class="align-middle">Catálogos</span>
                            <i class="ms-auto arrow-icon" data-feather="chevron-right"></i>
                        </a>
                    </li>
                    <div id="collapseCatalogos" class="accordion-collapse collapse <?php echo $es_catalogos ? 'show' : ''; ?>" style="margin-left:13px;" data-bs-parent="#accordionCatalogos">
                        <div class="accordion-body">
                            <li class="sidebar-item <?php echo in_array($modulo_actual, ['catalogos', 'catalogo'], true) ? 'active' : ''; ?>">
                                <a class="sidebar-link <?php echo in_array($modulo_actual, ['catalogos', 'catalogo'], true) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>catalogos">
                                    <i class="align-middle" data-feather="layers"></i> <span class="align-middle">Inicio catálogos</span>
                                </a>
                            </li>
                            <?php if ($sucursales_p['estatus']): ?>
                            <li class="sidebar-item <?php echo $modulo_actual === 'sucursales' ? 'active' : ''; ?>">
                                <a class="sidebar-link <?php echo $modulo_actual === 'sucursales' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>sucursales">
                                    <i class="align-middle" data-feather="home"></i> <span class="align-middle">Sucursales</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            <li class="sidebar-item <?php echo $modulo_actual === 'categorias' ? 'active' : ''; ?>">
                                <a class="sidebar-link <?php echo $modulo_actual === 'categorias' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>categorias">
                                    <i class="align-middle" data-feather="grid"></i> <span class="align-middle">Categorías</span>
                                </a>
                            </li>
                            <li class="sidebar-item <?php echo $modulo_actual === 'marcas' ? 'active' : ''; ?>">
                                <a class="sidebar-link <?php echo $modulo_actual === 'marcas' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>marcas">
                                    <i class="align-middle" data-feather="award"></i> <span class="align-middle">Marcas</span>
                                </a>
                            </li>
                            <li class="sidebar-item <?php echo $modulo_actual === 'unidades' ? 'active' : ''; ?>">
                                <a class="sidebar-link <?php echo $modulo_actual === 'unidades' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>unidades">
                                    <i class="align-middle" data-feather="box"></i> <span class="align-middle">Unidades</span>
                                </a>
                            </li>
                            <li class="sidebar-item <?php echo $modulo_actual === 'formas_pago' ? 'active' : ''; ?>">
                                <a class="sidebar-link <?php echo $modulo_actual === 'formas_pago' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>formas_pago">
                                    <i class="align-middle" data-feather="credit-card"></i> <span class="align-middle">Formas de pago</span>
                                </a>
                            </li>
                            <?php if ($usuarios_p['estatus']): ?>
                            <li class="sidebar-item <?php echo $modulo_actual === 'usuarios' ? 'active' : ''; ?>">
                                <a class="sidebar-link <?php echo $modulo_actual === 'usuarios' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>usuarios">
                                    <i class="align-middle" data-feather="user"></i> <span class="align-middle">Usuarios</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if ($permisos_panel_p['estatus']): ?>
                            <li class="sidebar-item <?php echo $modulo_actual === 'panel_permisos' ? 'active' : ''; ?>">
                                <a class="sidebar-link <?php echo $modulo_actual === 'panel_permisos' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>panel_permisos">
                                    <i class="align-middle" data-feather="shield"></i> <span class="align-middle">Permisos</span>
                                </a>
                            </li>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </ul>

         <div class="sidebar-cta">
            <div class="sidebar-cta-content text-center">
                <img src="<?= SUPABASE_URL."/storage/v1/object/public/pikero_public/" . $_SESSION['logo_ferreteria'];?>" onerror="this.src='<?= STATIC_URL; ?>img/logo_pajaro.png';"  style="width: 90px; border-radius: 8px;">
                <strong class="d-inline-block mb-2 mt-3"><?= $_SESSION['ferreteria']?></strong>
            </div>
            <div class="sidebar-cta-content">
                <strong class="d-inline-block mb-2">Sistema en proceso</strong>
                <div class="mb-3 text-sm">
                    Algunas funciones estan en proceso de desarollo.
                </div>
                <!-- <div class="d-grid">
                    <a href="upgrade-to-pro.html" class="btn btn-primary">Upgrade to Pro</a>
                </div> -->
            </div>
        </div>
    </div>
</nav>
