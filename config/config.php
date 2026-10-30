<?php
/**
 * Configuración global del sistema Pikero
 */
require_once __DIR__ . '/../vendor/autoload.php';

$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? null) == 443
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

$protocol = $is_https ? "https://" : "http://";

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if (
    $host == 'localhost:8888' ||
    $host == '127.0.0.1:8888' ||
    $host == 'localhost' ||
    $host == '127.0.0.1'
) {
    define('BASE_URL', $protocol . $host . '/Pikero/');
} else {
    define('BASE_URL', $protocol . $host . '/');
}

define('STATIC_URL', BASE_URL . 'static/');
define('ROOT_PATH', dirname(__DIR__) . '/');

define('ASSET_DATATABLES_CSS', 'https://cdn.datatables.net/1.12.1/css/jquery.dataTables.min.css');
define('ASSET_DATATABLES_RESPONSIVE_CSS', 'https://cdn.datatables.net/responsive/2.3.0/css/responsive.dataTables.min.css');
define('ASSET_ANIMATE_CSS', 'https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css');
define('ASSET_BOOTSTRAP_SELECT_CSS', STATIC_URL . 'css/bootstrap-select.min.css');
define('ASSET_PERMISOS_CSS', STATIC_URL . 'css/permisos.css');
define('ASSET_EDITAR_USUARIO_CSS', STATIC_URL . 'css/editar-usuario.css');
define('ASSET_EDITAR_ROL_CSS', STATIC_URL . 'css/editar-rol.css');
define('ASSET_MENU_USUARIO_CSS', STATIC_URL . 'css/menu-usuario-edit-navegacion-pestañas.css');
define('ASSET_BOOTSTRAP_SELECT_JS', STATIC_URL . 'js/bootstrap-select.min.js');

define('ASSET_FONTAWESOME_JS', 'https://kit.fontawesome.com/5c955c6e98.js');
define('ASSET_SWEETALERT_JS', 'https://cdn.jsdelivr.net/npm/sweetalert2@11');
define('ASSET_DATATABLES_JS', 'https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js');
define('ASSET_DATATABLES_RESPONSIVE_JS', 'https://cdn.datatables.net/responsive/2.3.0/js/dataTables.responsive.min.js');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

define('SUPABASE_URL', $_ENV['SUPABASE_URL'] ?? '');
define('SUPABASE_STORAGE_URL', SUPABASE_URL . '/storage/v1/object/public/pikero_public/' ?? '');
define('SUPABASE_SERVICE_KEY', $_ENV['SUPABASE_SERVICE_KEY'] ?? '');

require_once __DIR__ . '/../utils/SchemaMigrator.php';
SchemaMigrator::init();
