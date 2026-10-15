 <?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Tenant {
    public static function idFerreteria() {
        return $_SESSION['id_ferreteria'] ?? null;
    }

    public static function idSucursal() {
        return $_SESSION['id_sucursal'] ?? null;
    }

    public static function tipoReporte() {
        return $_SESSION['tipo_reporte'] ?? null;
    }

    public static function exigirFerreteria() {
        $id = self::idFerreteria();
        if (!$id) {
            return null;
        }
        return (int)$id;
    }

    public static function esSuperAdmin(): bool {
        return ($_SESSION['rol_slug'] ?? '') === 'superadmin';
    }
}
