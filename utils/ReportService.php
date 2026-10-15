<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/Tenant.php';
require_once __DIR__ . '/ReportDateRange.php';

class ReportService {
    private Database $db;
    private ?int $ferreteria;
    private ?int $sucursal;

    private array $modules = [
        'clientes' => [
            'select' => 'c.id, c.nombre, c.razon_social, c.rfc, c.telefono, c.correo, c.direccion, c.estatus, c.es_publico, c.fecha_registro',
            'from' => 'clientes c', 'scope' => 'c.id_ferreteria = :ferreteria', 'date' => 'c.fecha_registro',
            'search' => ['c.nombre', 'c.razon_social', 'c.rfc', 'c.telefono', 'c.correo'],
            'filters' => [
                'nombre' => ['c.nombre LIKE', 'like'], 'rfc' => ['c.rfc LIKE', 'like'],
                'telefono' => ['c.telefono LIKE', 'like'], 'correo' => ['c.correo LIKE', 'like'],
                'estatus' => ['c.estatus =', 'equal'], 'tipo_cliente' => ['c.es_publico =', 'equal']
            ],
            'columns' => ['id' => 'ID', 'nombre' => 'Nombre', 'razon_social' => 'Razón social', 'rfc' => 'RFC', 'telefono' => 'Teléfono', 'correo' => 'Correo', 'direccion' => 'Dirección', 'es_publico' => 'Público general', 'estatus' => 'Estatus', 'fecha_registro' => 'Fecha de registro'],
            'sort' => ['id' => 'c.id', 'nombre' => 'c.nombre', 'rfc' => 'c.rfc', 'telefono' => 'c.telefono', 'correo' => 'c.correo', 'fecha_registro' => 'c.fecha_registro'],
            'default' => 'c.fecha_registro'
        ],
        'ventas' => [
            'select' => "v.id, v.folio, v.fecha_registro, COALESCE(c.nombre, 'Público en general') AS cliente, fp.nombre AS forma_pago, v.tipo_venta, v.total, CASE WHEN v.tipo_venta = 'credito' THEN GREATEST(v.total - COALESCE((SELECT SUM(a.monto) FROM abonos_venta a WHERE a.id_venta = v.id), 0), 0) ELSE 0 END AS saldo_pendiente, v.estatus_factura, v.estatus, CONCAT(u.nombre, ' ', u.apellido) AS cajero",
            'from' => 'ventas v LEFT JOIN clientes c ON c.id = v.id_cliente LEFT JOIN formas_pago fp ON fp.id = v.id_forma_pago LEFT JOIN usuarios u ON u.id = v.id_usuario',
            'scope' => 'v.id_ferreteria = :ferreteria AND v.id_sucursal = :sucursal', 'date' => 'v.fecha_registro',
            'search' => ['v.folio', 'c.nombre', 'fp.nombre', 'u.nombre', 'u.apellido'],
            'filters' => [
                'folio' => ['v.folio LIKE', 'like'], 'cliente' => ['c.nombre LIKE', 'like'],
                'forma_pago' => ['v.id_forma_pago =', 'equal'], 'tipo_venta' => ['v.tipo_venta =', 'equal'],
                'estatus_factura' => ['v.estatus_factura =', 'equal'], 'estatus' => ['v.estatus =', 'equal']
            ],
            'columns' => ['id' => 'ID', 'folio' => 'Folio', 'fecha_registro' => 'Fecha', 'cliente' => 'Cliente', 'forma_pago' => 'Forma de pago', 'tipo_venta' => 'Tipo de venta', 'total' => 'Total', 'saldo_pendiente' => 'Saldo pendiente', 'estatus_factura' => 'Factura', 'estatus' => 'Estatus', 'cajero' => 'Atendió'],
            'sort' => ['id' => 'v.id', 'folio' => 'v.folio', 'fecha_registro' => 'v.fecha_registro', 'cliente' => 'c.nombre', 'forma_pago' => 'fp.nombre', 'tipo_venta' => 'v.tipo_venta', 'total' => 'v.total', 'saldo_pendiente' => 'saldo_pendiente', 'estatus_factura' => 'v.estatus_factura', 'estatus' => 'v.estatus', 'cajero' => 'u.nombre'],
            'default' => 'v.fecha_registro'
        ],
        'compras' => [
            'select' => 'c.id, c.folio, c.numero_documento, c.fecha_compra, c.total, p.nombre AS proveedor, fp.nombre AS forma_pago, c.estatus, CONCAT(u.nombre, \' \', u.apellido) AS usuario',
            'from' => 'compras c LEFT JOIN proveedores p ON p.id = c.id_proveedor LEFT JOIN formas_pago fp ON fp.id = c.id_forma_pago LEFT JOIN usuarios u ON u.id = c.id_usuario',
            'scope' => 'c.id_ferreteria = :ferreteria AND c.id_sucursal = :sucursal AND c.estatus = 1', 'date' => 'c.fecha_compra',
            'search' => ['c.folio', 'c.numero_documento', 'p.nombre', 'fp.nombre', 'u.nombre', 'u.apellido'],
            'filters' => [
                'folio' => ['c.folio LIKE', 'like'], 'numero_documento' => ['c.numero_documento LIKE', 'like'],
                'proveedor' => ['c.id_proveedor =', 'equal'], 'forma_pago' => ['c.id_forma_pago =', 'equal']
            ],
            'columns' => ['id' => 'ID', 'folio' => 'Folio', 'numero_documento' => 'Factura / remisión', 'fecha_compra' => 'Fecha de compra', 'proveedor' => 'Proveedor', 'forma_pago' => 'Forma de pago', 'total' => 'Total', 'estatus' => 'Estatus', 'usuario' => 'Registró'],
            'sort' => ['id' => 'c.id', 'folio' => 'c.folio', 'numero_documento' => 'c.numero_documento', 'fecha_compra' => 'c.fecha_compra', 'proveedor' => 'p.nombre', 'forma_pago' => 'fp.nombre', 'total' => 'c.total', 'usuario' => 'u.nombre'],
            'default' => 'c.fecha_compra'
        ],
        'gastos' => [
            'select' => 'g.id, g.fecha, s.nombre AS sucursal, cat.nombre AS categoria, g.descripcion, g.folio, g.monto, CONCAT(u.nombre, \' \', u.apellido) AS usuario, g.estatus',
            'from' => 'gastos g INNER JOIN sucursales s ON s.id = g.id_sucursal INNER JOIN categorias_gastos cat ON cat.id = g.id_categoria INNER JOIN usuarios u ON u.id = g.id_usuario',
            'scope' => 'g.id_ferreteria = :ferreteria', 'date' => 'g.fecha',
            'search' => ['g.folio', 'g.descripcion', 'cat.nombre', 's.nombre', 'u.nombre', 'u.apellido'],
            'filters' => [
                'folio' => ['g.folio LIKE', 'like'],
                'descripcion' => ['g.descripcion LIKE', 'like'],
                'id_sucursal' => ['g.id_sucursal =', 'equal'],
                'id_categoria' => ['g.id_categoria =', 'equal'],
                'estatus' => ['g.estatus =', 'status']
            ],
            'columns' => ['id' => 'ID', 'fecha' => 'Fecha', 'sucursal' => 'Sucursal', 'categoria' => 'Categoría', 'descripcion' => 'Descripción', 'folio' => 'Folio / factura', 'monto' => 'Monto', 'usuario' => 'Registró', 'estatus' => 'Estado'],
            'sort' => ['id' => 'g.id', 'fecha' => 'g.fecha', 'sucursal' => 's.nombre', 'categoria' => 'cat.nombre', 'descripcion' => 'g.descripcion', 'folio' => 'g.folio', 'monto' => 'g.monto', 'usuario' => 'u.nombre', 'estatus' => 'g.estatus'],
            'default' => 'g.fecha'
        ],
        'inventario' => [
            'select' => 'p.id AS id_producto, p.codigo, p.nombre AS producto, c.nombre AS categoria, m.nombre AS marca, un.abreviatura AS unidad, p.precio_compra, p.precio_venta, COALESCE(i.existencia, 0) AS existencia, COALESCE(i.minimo, 5) AS minimo, p.fecha_registro',
            'from' => 'productos p LEFT JOIN categorias c ON c.id = p.id_categoria LEFT JOIN marcas m ON m.id = p.id_marca LEFT JOIN unidades un ON un.id = p.id_unidad LEFT JOIN inventario i ON i.id_producto = p.id AND i.id_ferreteria = p.id_ferreteria AND i.id_sucursal = :join_sucursal',
            'scope' => 'p.id_ferreteria = :ferreteria AND p.estatus = 1', 'date' => 'p.fecha_registro',
            'search' => ['p.codigo', 'p.nombre', 'c.nombre', 'm.nombre'],
            'filters' => [
                'codigo' => ['p.codigo LIKE', 'like'], 'producto' => ['p.nombre LIKE', 'like'],
                'categoria' => ['p.id_categoria =', 'equal'], 'marca' => ['p.id_marca =', 'equal'],
                'stock' => ['stock_status', 'stock']
            ],
            'columns' => ['id_producto' => 'ID producto', 'codigo' => 'Código / SKU', 'producto' => 'Producto', 'categoria' => 'Categoría', 'marca' => 'Marca', 'unidad' => 'Unidad', 'precio_compra' => 'Precio de compra', 'precio_venta' => 'Precio de venta', 'existencia' => 'Existencia', 'minimo' => 'Stock mínimo', 'fecha_registro' => 'Fecha de alta'],
            'sort' => ['id_producto' => 'p.id', 'codigo' => 'p.codigo', 'producto' => 'p.nombre', 'categoria' => 'c.nombre', 'marca' => 'm.nombre', 'unidad' => 'un.abreviatura', 'existencia' => 'existencia', 'minimo' => 'minimo', 'fecha_registro' => 'p.fecha_registro'],
            'default' => 'p.nombre'
        ],
        'movimientos' => [
            'select' => 'm.id, m.fecha_registro, p.nombre AS producto, m.tipo, m.cantidad, m.existencia_antes, m.existencia_despues, m.referencia, CONCAT(COALESCE(u.nombre, \'\'), \' \', COALESCE(u.apellido, \'\')) AS usuario',
            'from' => 'movimientos_inventario m LEFT JOIN productos p ON p.id = m.id_producto AND p.id_ferreteria = m.id_ferreteria LEFT JOIN usuarios u ON u.id = m.id_usuario',
            'scope' => 'm.id_ferreteria = :ferreteria AND m.id_sucursal = :sucursal', 'date' => 'm.fecha_registro',
            'search' => ['p.nombre', 'm.tipo', 'm.referencia', 'u.nombre', 'u.apellido'],
            'filters' => [
                'producto' => ['p.nombre LIKE', 'like'], 'tipo_movimiento' => ['m.tipo =', 'equal'],
                'referencia' => ['m.referencia LIKE', 'like'],
                'usuario' => ['CONCAT(COALESCE(u.nombre, \'\'), \' \', COALESCE(u.apellido, \'\')) LIKE', 'like']
            ],
            'columns' => ['id' => 'ID', 'fecha_registro' => 'Fecha', 'producto' => 'Producto', 'tipo' => 'Tipo', 'cantidad' => 'Cantidad', 'existencia_antes' => 'Existencia antes', 'existencia_despues' => 'Existencia después', 'referencia' => 'Referencia', 'usuario' => 'Usuario'],
            'sort' => ['id' => 'm.id', 'fecha_registro' => 'm.fecha_registro', 'producto' => 'p.nombre', 'tipo' => 'm.tipo', 'cantidad' => 'm.cantidad', 'existencia_antes' => 'm.existencia_antes', 'existencia_despues' => 'm.existencia_despues', 'referencia' => 'm.referencia', 'usuario' => 'u.nombre'],
            'default' => 'm.fecha_registro'
        ],
        'usuarios' => [
            'select' => 'u.id, u.foto_perfil, u.nombre, u.apellido, u.usuario, r.nombre AS nombre_rol, s.nombre AS sucursal, u.telefono, u.correo, u.cargo, u.estatus, u.fecha_registro',
            'from' => 'usuarios u LEFT JOIN roles r ON r.id = u.id_rol LEFT JOIN sucursales s ON s.id = u.id_sucursal',
            'scope' => 'u.id_ferreteria = :ferreteria', 'date' => 'u.fecha_registro',
            'search' => ['u.nombre', 'u.apellido', 'u.usuario', 'r.nombre', 's.nombre', 'u.correo'],
            'filters' => [
                'nombre' => ['CONCAT(u.nombre, \' \', u.apellido) LIKE', 'like'], 'usuario' => ['u.usuario LIKE', 'like'],
                'rol' => ['u.id_rol =', 'equal'], 'sucursal' => ['u.id_sucursal =', 'equal'], 'estatus' => ['u.estatus =', 'equal']
            ],
            'columns' => ['id' => 'ID', 'foto_perfil' => 'Foto', 'nombre' => 'Nombre', 'apellido' => 'Apellidos', 'usuario' => 'Usuario', 'nombre_rol' => 'Rol', 'sucursal' => 'Sucursal', 'telefono' => 'Teléfono', 'correo' => 'Correo', 'cargo' => 'Cargo', 'estatus' => 'Estatus', 'fecha_registro' => 'Fecha de registro'],
            'sort' => ['id' => 'u.id', 'nombre' => 'u.nombre', 'nombre_rol' => 'r.nombre', 'usuario' => 'u.usuario', 'sucursal' => 's.nombre', 'estatus' => 'u.estatus', 'fecha_registro' => 'u.fecha_registro'],
            'default' => 'u.fecha_registro'
        ]
    ];

    public function __construct(?int $ferreteria = null, ?int $sucursal = null) {
        $this->db = new Database();
        $this->ferreteria = $ferreteria ?? Tenant::idFerreteria();
        $this->sucursal = $sucursal ?? Tenant::idSucursal();
    }

    public function datatable(string $module, array $request): array {
        $config = $this->config($module);
        $range = ReportDateRange::validate($request);
        if (!$range['estatus']) {
            return ['draw' => (int)($request['draw'] ?? 1), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => $range['mensaje']];
        }

        $baseWhere = $this->scope($module);
        $baseParams = $this->scopeParams($module);
        $total = $this->count($config, $baseWhere, $baseParams);
        [$where, $params] = $this->buildWhere($module, $request, (string)($request['search']['value'] ?? ''));
        $filtered = $this->count($config, $where, $params);
        $sortKey = (string)($request['columns'][$request['order'][0]['column'] ?? 0]['data'] ?? 'id');
        $sort = $config['sort'][$sortKey] ?? $config['default'];
        $direction = strtolower((string)($request['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $offset = max(0, (int)($request['start'] ?? 0));
        $limit = min(500, max(1, (int)($request['length'] ?? 10)));
        $sql = "SELECT {$config['select']} FROM {$config['from']} WHERE {$where} ORDER BY {$sort} {$direction} LIMIT {$offset}, {$limit}";
        $rows = $this->db->select($sql, $params);
        return ['draw' => (int)($request['draw'] ?? 1), 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $rows];
    }

    public function export(string $module, array $request): array {
        $config = $this->config($module);
        $range = ReportDateRange::validate($request, true);
        if (!$range['estatus']) return $range;
        [$where, $params] = $this->buildWhere($module, $request, (string)($request['search'] ?? ''));
        $sql = "SELECT {$config['select']} FROM {$config['from']} WHERE {$where} ORDER BY {$config['default']} DESC";
        return ['estatus' => true, 'columns' => $config['columns'], 'rows' => $this->db->select($sql, $params)];
    }

    private function buildWhere(string $module, array $request, string $search): array {
        $config = $this->config($module);
        $parts = [$this->scope($module)];
        $params = $this->scopeParams($module);
        $range = ReportDateRange::validate($request);
        if (!$range['estatus']) return ['1 = 0', []];
        if ($range['inicio'] !== null) {
            $parts[] = "{$config['date']} >= :fecha_inicio AND {$config['date']} < :fecha_fin_exclusivo";
            $params[':fecha_inicio'] = $range['inicio'];
            $params[':fecha_fin_exclusivo'] = $range['fin_exclusivo'];
        }

        foreach ($config['filters'] as $key => [$sql, $kind]) {
            $value = trim((string)($request[$key] ?? ''));
            if ($kind === 'status') {
                if ($value === '' || $value === 'all') continue;
            }
            if ($value === '') continue;
            if ($kind === 'stock') {
                if ($value === 'agotado') $parts[] = 'COALESCE(i.existencia, 0) <= 0';
                elseif ($value === 'bajo') $parts[] = 'COALESCE(i.existencia, 0) > 0 AND COALESCE(i.existencia, 0) <= COALESCE(i.minimo, 5)';
                elseif ($value === 'disponible') $parts[] = 'COALESCE(i.existencia, 0) > COALESCE(i.minimo, 5)';
                continue;
            }
            $placeholder = ':filter_' . $key;
            $parts[] = $sql . ' ' . $placeholder;
            $params[$placeholder] = $kind === 'like' ? '%' . $value . '%' : $value;
        }

        $search = trim($search);
        if ($search !== '') {
            $parts[] = '(' . implode(' OR ', array_map(static fn($index, $column) => $column . ' LIKE :search_' . $index, array_keys($config['search']), $config['search'])) . ')';
            foreach ($config['search'] as $index => $_column) $params[':search_' . $index] = '%' . $search . '%';
        }
        return [implode(' AND ', $parts), $params];
    }

    private function scope(string $module): string {
        if (in_array($module, ['ventas', 'compras', 'inventario', 'movimientos'], true) && !$this->sucursal) return '1 = 0';
        return $this->config($module)['scope'];
    }

    private function scopeParams(string $module): array {
        if (in_array($module, ['ventas', 'compras', 'inventario', 'movimientos'], true) && !$this->sucursal) {
            return $module === 'inventario' ? [':join_sucursal' => 0] : [];
        }
        $params = [':ferreteria' => $this->ferreteria];
        if (in_array($module, ['ventas', 'compras', 'movimientos'], true)) $params[':sucursal'] = $this->sucursal;
        if ($module === 'inventario') $params[':join_sucursal'] = $this->sucursal;
        return $params;
    }

    private function count(array $config, string $where, array $params): int {
        $rows = $this->db->select("SELECT COUNT(*) AS total FROM {$config['from']} WHERE {$where}", $params);
        return (int)($rows[0]['total'] ?? 0);
    }

    private function config(string $module): array {
        if (!isset($this->modules[$module])) throw new InvalidArgumentException('Módulo de reporte no permitido');
        return $this->modules[$module];
    }
}
