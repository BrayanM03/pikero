<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/dates.php';
require_once __DIR__ . '/../models/Datatable.php';
require_once __DIR__ . '/../utils/Tenant.php';

class Producto extends Datatable
{
    private string $tabla = 'productos';
    private Date $fecha;
    private ?int $id_ferreteria;

    public function __construct(?int $id_ferreteria = null)
    {
        $this->db = new Database();
        $this->fecha = new Date();
        $this->id_ferreteria = $id_ferreteria ?? Tenant::idFerreteria();
    }

    public function datatables($id_filtro, $start, $length, $search, $orderColumn, $orderDir)
    {
        $sql = "SELECT p.*, 
                       c.nombre AS categoria, 
                       m.nombre AS marca, 
                       u.nombre AS unidad, 
                       u.abreviatura AS unidad_abrev,
                       COALESCE(inv.existencia, 0) AS stock_actual
                FROM {$this->tabla} p
                LEFT JOIN categorias c ON c.id = p.id_categoria
                LEFT JOIN marcas m ON m.id = p.id_marca
                LEFT JOIN unidades u ON u.id = p.id_unidad
                LEFT JOIN inventario inv ON inv.id_producto = p.id AND inv.id_sucursal = :id_sucursal
                WHERE p.id_ferreteria = :id_ferreteria";

        $params = [
            ':id_ferreteria' => $this->id_ferreteria,
            ':id_sucursal' => Tenant::idSucursal() ?? 1
        ];

        if (!empty($search)) {
            $sql .= " AND (p.codigo LIKE :s1 OR p.nombre LIKE :s2 OR c.nombre LIKE :s3 OR m.nombre LIKE :s4)";
            $term = "%{$search}%";
            $params[':s1'] = $term;
            $params[':s2'] = $term;
            $params[':s3'] = $term;
            $params[':s4'] = $term;
        }

        $allowed = ['id', 'codigo', 'nombre', 'precio_compra', 'precio_venta', 'stock_actual'];
        $orderColumn = in_array($orderColumn, $allowed) ? $orderColumn : 'p.id';
        $orderDir = strtolower($orderDir) === 'asc' ? 'ASC' : 'DESC';

        $start = (int)$start;
        $length = (int)$length;
        $sql .= " ORDER BY {$orderColumn} {$orderDir} LIMIT {$start}, {$length}";

        return $this->db->select($sql, $params);
    }

    public function contar($id_filtro): int
    {
        return (int)$this->db->count(
            $this->tabla,
            "id_ferreteria = :id_ferreteria",
            [':id_ferreteria' => $this->id_ferreteria]
        );
    }

    public function contarFiltrados($id_filtro, $search): int
    {
        if (empty($search)) {
            return $this->contar($id_filtro);
        }

        $sql = "SELECT COUNT(*) AS total 
                FROM {$this->tabla} p
                LEFT JOIN categorias c ON c.id = p.id_categoria
                LEFT JOIN marcas m ON m.id = p.id_marca
                WHERE p.id_ferreteria = :id_ferreteria
                  AND (p.codigo LIKE :s1 OR p.nombre LIKE :s2 OR c.nombre LIKE :s3 OR m.nombre LIKE :s4)";

        $term = "%{$search}%";
        $data = $this->db->select($sql, [
            ':id_ferreteria' => $this->id_ferreteria,
            ':s1' => $term,
            ':s2' => $term,
            ':s3' => $term,
            ':s4' => $term
        ]);

        return (int)($data[0]['total'] ?? 0);
    }


    public function registrar(array $data): array
    {
        $nombre = trim($data['nombre'] ?? '');

        if ($nombre === '') {
            return [
                'estatus' => false,
                'mensaje' => 'El nombre del producto es obligatorio'
            ];
        }

        foreach (['precio_compra', 'precio_venta'] as $campo) {
            if (
                !isset($data[$campo]) ||
                !is_numeric($data[$campo]) ||
                !is_finite((float) $data[$campo]) ||
                (float) $data[$campo] < 0
            ) {
                return [
                    'estatus' => false,
                    'mensaje' => "El campo {$campo} debe ser un número válido mayor o igual a cero"
                ];
            }
        }

        try {
            $fiscal = $this->prepararDatosFiscales($data);
        } catch (\InvalidArgumentException $e) {
            return [
                'estatus' => false,
                'mensaje' => $e->getMessage()
            ];
        }

        $codigo = trim($data['codigo'] ?? '');

        if ($codigo !== '') {
            $existe = $this->db->select(
                "SELECT id
             FROM {$this->tabla}
             WHERE codigo = ?
               AND id_ferreteria = ?
               AND estatus = 1",
                [$codigo, $this->id_ferreteria]
            );

            if (!empty($existe)) {
                return [
                    'estatus' => false,
                    'mensaje' => 'Ya existe un producto con este código'
                ];
            }
        }

        $id = $this->db->insert($this->tabla, array_merge([
            'id_ferreteria' => $this->id_ferreteria,
            'codigo' => $codigo !== '' ? $codigo : null,
            'nombre' => $nombre,

            'id_categoria' => !empty($data['id_categoria'])
                ? (int) $data['id_categoria'] : null,

            'id_marca' => !empty($data['id_marca'])
                ? (int) $data['id_marca'] : null,

            'id_unidad' => !empty($data['id_unidad'])
                ? (int) $data['id_unidad'] : null,

            'precio_compra' => (float) $data['precio_compra'],
            'precio_venta' => (float) $data['precio_venta'],

            'estatus' => 1,
            'fecha_registro' => $this->fecha->obtenerFechaRegistro()
        ], $fiscal));

        if (!$id) {
            return [
                'estatus' => false,
                'mensaje' => 'No se pudo guardar el producto'
            ];
        }

        $idSucursal = Tenant::idSucursal() ?? 1;
        $existencia = (float) ($data['stock_inicial'] ?? 0);
        $minimo = (float) ($data['minimo'] ?? 5);

        if (
            !is_finite($existencia) || $existencia < 0 ||
            !is_finite($minimo) || $minimo < 0
        ) {
            return [
                'estatus' => false,
                'mensaje' => 'El stock inicial y el mínimo deben ser válidos'
            ];
        }

        $this->db->insert('inventario', [
            'id_ferreteria' => $this->id_ferreteria,
            'id_sucursal' => $idSucursal,
            'id_producto' => $id,
            'existencia' => $existencia,
            'minimo' => $minimo
        ]);

        if ($existencia > 0) {
            $this->db->insert('movimientos_inventario', [
                'id_ferreteria' => $this->id_ferreteria,
                'id_sucursal' => $idSucursal,
                'id_producto' => $id,
                'tipo' => 'inventario_inicial',
                'cantidad' => $existencia,
                'existencia_antes' => 0,
                'existencia_despues' => $existencia,
                'referencia' => 'Alta inicial de producto',
                'id_usuario' => $_SESSION['id'] ?? null,
                'fecha_registro' => $this->fecha->obtenerFechaRegistro()
            ]);
        }

        return [
            'estatus' => true,
            'mensaje' => 'Producto registrado correctamente',
            'nuevo' => $id
        ];
    }


    public function actualizar(int $id, array $data): array
    {
        if ($id <= 0) {
            return [
                'estatus' => false,
                'mensaje' => 'Producto no encontrado'
            ];
        }

        $producto = $this->db->select(
            "SELECT id
         FROM {$this->tabla}
         WHERE id = ? AND id_ferreteria = ?",
            [$id, $this->id_ferreteria]
        );

        if (empty($producto)) {
            return [
                'estatus' => false,
                'mensaje' => 'Producto no encontrado'
            ];
        }

        $nombre = trim($data['nombre'] ?? '');

        if ($nombre === '') {
            return [
                'estatus' => false,
                'mensaje' => 'El nombre es obligatorio'
            ];
        }

        foreach (['precio_compra', 'precio_venta'] as $campo) {
            if (
                !isset($data[$campo]) ||
                !is_numeric($data[$campo]) ||
                !is_finite((float) $data[$campo]) ||
                (float) $data[$campo] < 0
            ) {
                return [
                    'estatus' => false,
                    'mensaje' => "El campo {$campo} debe ser un número válido mayor o igual a cero"
                ];
            }
        }

        try {
            $fiscal = $this->prepararDatosFiscales($data);
        } catch (\InvalidArgumentException $e) {
            return [
                'estatus' => false,
                'mensaje' => $e->getMessage()
            ];
        }

        $codigo = trim($data['codigo'] ?? '');

        if ($codigo !== '') {
            $existe = $this->db->select(
                "SELECT id
             FROM {$this->tabla}
             WHERE codigo = ?
               AND id_ferreteria = ?
               AND id != ?
               AND estatus = 1",
                [$codigo, $this->id_ferreteria, $id]
            ); 

            if (!empty($existe)) {
                return [
                    'estatus' => false,
                    'mensaje' => 'Otro producto ya tiene asignado este código'
                ];
            }
        }

        $filas = $this->db->update($this->tabla, array_merge([
            'codigo' => $codigo !== '' ? $codigo : null,
            'nombre' => $nombre,

            'id_categoria' => !empty($data['id_categoria'])
                ? (int) $data['id_categoria'] : null,

            'id_marca' => !empty($data['id_marca'])
                ? (int) $data['id_marca'] : null,

            'id_unidad' => !empty($data['id_unidad'])
                ? (int) $data['id_unidad'] : null,

            'precio_compra' => (float) $data['precio_compra'],
            'precio_venta' => (float) $data['precio_venta']
        ], $fiscal), 'id = ? AND id_ferreteria = ?', [
            $id,
            $this->id_ferreteria
        ]);

        if ($filas === false) {
            return [
                'estatus' => false,
                'mensaje' => 'No se pudo actualizar el producto'
            ];
        }

        return [
            'estatus' => true,
            'mensaje' => 'Producto actualizado correctamente'
        ];
    }

    public function obtener(int $id): array
    {
        $sql = "SELECT p.*, 
                       c.nombre AS categoria, 
                       m.nombre AS marca, 
                       u.nombre AS unidad, 
                       u.abreviatura AS unidad_abrev,
                       COALESCE(inv.existencia, 0) AS stock_actual,
                       COALESCE(inv.minimo, 5) AS stock_minimo
                FROM {$this->tabla} p
                LEFT JOIN categorias c ON c.id = p.id_categoria
                LEFT JOIN marcas m ON m.id = p.id_marca
                LEFT JOIN unidades u ON u.id = p.id_unidad
                LEFT JOIN inventario inv ON inv.id_producto = p.id AND inv.id_sucursal = :id_sucursal
                WHERE p.id = :id AND p.id_ferreteria = :id_ferreteria";

        $data = $this->db->select($sql, [
            ':id' => $id,
            ':id_ferreteria' => $this->id_ferreteria,
            ':id_sucursal' => Tenant::idSucursal() ?? 1
        ]);

        return empty($data)
            ? ['estatus' => false, 'mensaje' => 'Producto no encontrado', 'data' => []]
            : ['estatus' => true, 'mensaje' => 'OK', 'data' => $data[0]];
    }

    public function cancelar(int $id): array
    {
        $this->db->update($this->tabla, ['estatus' => 0], 'id = ? AND id_ferreteria = ?', [$id, $this->id_ferreteria]);
        return ['estatus' => true, 'mensaje' => 'Producto desactivado'];
    }

    public function buscarParaPOS(string $termino, ?int $id_sucursal = null): array
    {
        $id_sucursal = $id_sucursal ?? (Tenant::idSucursal() ?? 1);
        $term = "%{$termino}%";

        $sql = "SELECT p.id, p.codigo, p.nombre, p.precio_venta, p.precio_compra,
                       u.abreviatura AS unidad,
                       COALESCE(inv.existencia, 0) AS stock
                FROM {$this->tabla} p
                LEFT JOIN unidades u ON u.id = p.id_unidad
                LEFT JOIN inventario inv ON inv.id_producto = p.id AND inv.id_sucursal = :id_sucursal
                WHERE p.estatus = 1 
                  AND p.id_ferreteria = :id_ferreteria
                  AND (p.codigo LIKE :s1 OR p.nombre LIKE :s2)
                ORDER BY p.nombre ASC
                LIMIT 20";

        $data = $this->db->select($sql, [
            ':id_sucursal' => $id_sucursal,
            ':id_ferreteria' => $this->id_ferreteria,
            ':s1' => $term,
            ':s2' => $term
        ]);

        return ['estatus' => true, 'mensaje' => 'OK', 'data' => $data];
    }


    private function prepararDatosFiscales(array $data): array
    {
        // Aceptar también los nombres enviados por el formulario.
        $claveSat = trim((string) (
            $data['clave_sat']
            ?? $data['sat_producto_servicio']
            ?? ''
        ));

        $unidadSat = trim((string) (
            $data['codigo_unidad_sat']
            ?? $data['sat_unidad']
            ?? ''
        ));

        $objetoImpuesto = trim((string) (
            $data['objeto_impuesto']
            ?? $data['sat_objeto_impuesto']
            ?? '02'
        ));

        $descripcionSat = trim((string) (
            $data['sat_descripcion']
            ?? ''
        ));

        $unidadTexto = trim((string) (
            $data['sat_unidad_texto']
            ?? ''
        ));

        $costoIncluyeIva = filter_var(
            $data['costo_incluye_iva'] ?? 0,
            FILTER_VALIDATE_BOOLEAN
        ) ? 1 : 0;

        $costoIvaAcreditable = filter_var(
            $data['costo_iva_acreditable'] ?? 0,
            FILTER_VALIDATE_BOOLEAN
        ) ? 1 : 0;

        $precioVentaIncluyeIva = filter_var(
            $data['precio_venta_incluye_iva'] ?? 1,
            FILTER_VALIDATE_BOOLEAN
        ) ? 1 : 0;

        $tasaIvaCosto = $data['tasa_iva_costo'] ?? 0.16;

        if (!is_numeric($tasaIvaCosto)) {
            throw new InvalidArgumentException(
                'La tasa de IVA del costo no es válida'
            );
        }

        $tasaIvaCosto = (float) $tasaIvaCosto;

        if (!in_array($tasaIvaCosto, [0.0, 0.08, 0.16], true)) {
            throw new InvalidArgumentException(
                'La tasa de IVA del costo debe ser 0%, 8% o 16%'
            );
        }

        $tipoIvaVenta = (string) (
            $data['tipo_iva_venta'] ?? '0.16'
        );

        if (!in_array(
            $tipoIvaVenta,
            ['0', '0.08', '0.16', 'exento', 'no_objeto'],
            true
        )) {
            throw new InvalidArgumentException(
                'El tratamiento fiscal de venta no es válido'
            );
        }

        if (!in_array($objetoImpuesto, ['01', '02', '03', '04'], true)) {
            throw new InvalidArgumentException(
                'El objeto de impuesto SAT no es válido'
            );
        }

        // La clave 01010101 se conserva como respaldo para productos
        // antiguos, pero no debe sustituir la clasificación SAT real.
        $claveSat = $claveSat ?: '01010101';
        $unidadSat = $unidadSat ?: 'H87';

        return [
            'clave_sat' => $claveSat,
            'codigo_unidad_sat' => $unidadSat,
            'objeto_impuesto' => $objetoImpuesto,
            'sat_descripcion' => $descripcionSat ?: null,
            'sat_unidad_texto' => $unidadTexto ?: null,

            'costo_incluye_iva' => $costoIncluyeIva,
            'tasa_iva_costo' => $tasaIvaCosto,
            'costo_iva_acreditable' => $costoIvaAcreditable,

            'tipo_iva_venta' => $tipoIvaVenta,
            'precio_venta_incluye_iva' => $precioVentaIncluyeIva,
        ];
    }
}
