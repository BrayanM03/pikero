<?php
session_start();
date_default_timezone_set('America/Matamoros');
require_once 'controllers/PermisoController.php';
require_once 'config/config.php';

$controller_permiso = new PermisoController();

/* $url = isset($_GET['url']) ? $_GET['url'] : 'panel';
$datos = explode('/', $url); */
$url = trim($_GET['url'] ?? 'panel', '/');

$datos = explode('/', $url);

$modulo = $datos[0];
$accion = isset($datos[1]) ? $datos[1] : null;
$id     = isset($datos[2]) ? $datos[2] : null;

if ($modulo === 'login') {
    include 'src/login.php';
    exit();
}

$controller_permiso->verificarSesion();

$vista_a_cargar = "";
$necesita_datatables = true;
$necesita_sweetalert = true;
$necesita_fontawesome = true;
$necesita_chartjs = false;
$necesita_animatecss = true;
$necesita_bootstrap_select = false;

$css_especificos = [];
$js_especificos = [];
$titulo_pagina = "Pikero";

switch ($modulo) {
    case 'panel':
        $titulo_vista = 'Panel';
        $necesita_datatables = false;
        $vista_a_cargar = 'src/panel.php';
        break;

    case 'punto_venta':
        $titulo_vista = 'Punto de venta';
        $necesita_datatables = false;
        $vista_a_cargar = 'src/punto_venta.php';
        $_GET['modulo_nombre'] = 'Punto de venta';
        break;

    case 'ventas':
        $titulo_vista = 'Ventas';
        $necesita_bootstrap_select = true;
        $vista_a_cargar = 'src/ventas.php';
        $_GET['modulo_nombre'] = 'Historial de ventas';
        break;

    case 'gastos':
        $titulo_vista = 'Gastos';
        $necesita_bootstrap_select = true;
        $vista_a_cargar = 'src/gastos.php';
        $_GET['modulo_nombre'] = 'Historial de gastos';
        break;    

    case 'productos':
        $titulo_vista = 'Productos';
        $vista_a_cargar = 'src/productos.php';
        $_GET['modulo_nombre'] = 'Productos';
        break;

    case 'inventario':
        $titulo_vista = 'Inventario';
        $necesita_bootstrap_select = true;
        $vista_a_cargar = 'src/inventario.php';
        $_GET['modulo_nombre'] = 'Inventario';
        break;

    case 'movimientos_inventario':
        $titulo_vista = 'Movimientos de inventario';
        $necesita_bootstrap_select = true;
        $vista_a_cargar = 'src/movimientos-inventario.php';
        $_GET['modulo_nombre'] = 'Movimientos de inventario';
        break;

    case 'clientes':
        $titulo_vista = 'Clientes';
        $necesita_bootstrap_select = true;
        $vista_a_cargar = 'src/clientes.php';
        $_GET['modulo_nombre'] = 'Clientes';
        break;

    case 'ferreterias':
        $titulo_vista = 'Ferreterías';
        $vista_a_cargar = 'src/ferreterias.php';
        $_GET['modulo_nombre'] = 'Ferreterías';
        break;

    case 'proveedores':
        $titulo_vista = 'Proveedores';
        $vista_a_cargar = 'src/proveedores.php';
        $_GET['modulo_nombre'] = 'Proveedores';
        break;

    case 'compras':
        $titulo_vista = 'Compras';
        $necesita_bootstrap_select = true;
        $vista_a_cargar = 'src/compras.php';
        $_GET['modulo_nombre'] = 'Compras';
        break;

    case 'caja':
        $titulo_vista = 'Caja';
        $vista_a_cargar = 'src/caja.php';
        $_GET['modulo_nombre'] = 'Caja';
        break;

    case 'usuarios':
        $necesita_bootstrap_select = true;
        if ($accion == 'registrar') {
            $titulo_vista = 'Registrar usuario';
            $necesita_bootstrap_select = true;
            $vista_a_cargar = 'src/registrar-usuario.php';
        } else if ($accion === 'editar' && $id) {
            $titulo_vista = 'Editar usuario';
            $css_especificos[] = ASSET_PERMISOS_CSS;
            $_GET['id_usuario'] = $id;
            $vista_a_cargar = 'src/editar-usuario.php';
        } else {
            $titulo_vista = 'Usuarios';
            $vista_a_cargar = 'src/usuarios.php';
        }
        break;

    case 'sucursales':
        $titulo_vista = 'Sucursales';
        $vista_a_cargar = 'src/sucursales.php';
        break;

    case 'catalogo':
    case 'catalogos':
        $catSolicitado = $accion ?? ($_GET['catalogo'] ?? null);
        if ($catSolicitado && in_array($catSolicitado, ['categorias', 'marcas', 'unidades', 'formas_pago'])) {
            $_GET['catalogo'] = $catSolicitado;
            $titulo_vista = ucfirst(str_replace('_', ' ', $catSolicitado));
            $vista_a_cargar = 'src/catalogo.php';
        } else {
            $titulo_vista = 'Catálogos';
            $necesita_datatables = false;
            $vista_a_cargar = 'src/catalogos.php';
        }
        break;

    case 'categorias':
        $titulo_vista = 'Categorías';
        $_GET['catalogo'] = 'categorias';
        $vista_a_cargar = 'src/catalogo.php';
        break;

    case 'marcas':
        $titulo_vista = 'Marcas';
        $_GET['catalogo'] = 'marcas';
        $vista_a_cargar = 'src/catalogo.php';
        break;

    case 'unidades':
        $titulo_vista = 'Unidades';
        $_GET['catalogo'] = 'unidades';
        $vista_a_cargar = 'src/catalogo.php';
        break;

    case 'formas_pago':
        $titulo_vista = 'Formas de pago';
        $_GET['catalogo'] = 'formas_pago';
        $vista_a_cargar = 'src/catalogo.php';
        break;

    case 'roles':
        if ($accion == 'editar' && $id) {
            $css_especificos[] = ASSET_PERMISOS_CSS;
            $_GET['id_rol'] = $id;
            $titulo_vista = 'Editar rol';
            $vista_a_cargar = 'src/permisos-roles.php';
        } else {
            $titulo_vista = 'Roles';
            $vista_a_cargar = 'src/roles.php';
        }
        break;

    case 'panel_permisos':
        $titulo_vista = 'Panel permisos';
        $necesita_datatables = false;
        $vista_a_cargar = 'src/panel-permisos.php';
        break;

    case 'permisos':
        $titulo_vista = 'Permisos';
        $css_especificos[] = ASSET_PERMISOS_CSS;
        $vista_a_cargar = 'src/permisos.php';
        break;

    case 'permisos_usuarios':
        $titulo_vista = 'Permisos usuarios';
        $css_especificos[] = ASSET_PERMISOS_CSS;
        $vista_a_cargar = 'src/permisos-usuarios.php';
        break;

    case 'perfil':
        $titulo_vista = 'Perfil';
        $necesita_datatables = false;
        $necesita_bootstrap_select = true;
        $js_especificos[] = ['src' => STATIC_URL . 'js/usuarios/perfil.js', 'module' => true];
        $vista_a_cargar = 'src/perfil.php';
        break;

    case 'not_found':
        $titulo_pagina = 'Pagina no encontrada 404';
        $necesita_datatables = false;
        $necesita_animatecss = false;
        $necesita_sweetalert = false;
        $necesita_fontawesome = false;
        $vista_a_cargar = 'src/vistas/error/not_found.php';
        break;

    case 'sin_permiso':
        $titulo_pagina = 'Sin permisos';
        $necesita_datatables = false;
        $necesita_animatecss = false;
        $necesita_sweetalert = false;
        $necesita_fontawesome = false;
        $_GET['msg'] = $accion;
        $vista_a_cargar = 'src/vistas/error/error.php';
        break;

    default:
        $titulo_pagina = 'Pagina no encontrada 404';
        $necesita_datatables = false;
        $necesita_animatecss = false;
        $necesita_sweetalert = false;
        $necesita_fontawesome = false;
        $vista_a_cargar = 'src/vistas/error/not_found.php';
        break;
}

if ($necesita_datatables) {
    $css_especificos[] = ASSET_DATATABLES_CSS;
    $css_especificos[] = ASSET_DATATABLES_RESPONSIVE_CSS;
    $js_especificos[] = ASSET_DATATABLES_JS;
    $js_especificos[] = ASSET_DATATABLES_RESPONSIVE_JS;
}

if ($necesita_sweetalert) {
    $js_especificos[] = ASSET_SWEETALERT_JS;
}

if ($necesita_fontawesome) {
    $js_especificos[] = ASSET_FONTAWESOME_JS;
}

if ($necesita_bootstrap_select) {
    $css_especificos[] = ASSET_BOOTSTRAP_SELECT_CSS;
    $js_especificos[] = ASSET_BOOTSTRAP_SELECT_JS;
}

if ($necesita_animatecss) {
    $css_especificos[] = ASSET_ANIMATE_CSS;
}

if (file_exists($vista_a_cargar)) {
    include $vista_a_cargar;
} else {
    echo "El archivo $vista_a_cargar no existe.";
}
