<?php
class Datatable {
    protected $db;

    public function __construct(Database $conexion) {
        $this->db = $conexion;
    }

    public function getDataTable(
        string $tabla,
        int $start,
        int $length,
        ?string $search,
        string $orderColumn,
        string $orderDir,
        array $allowedColumns,
        string $extraWhere = "",
        array $params = []
    ): array {
        $sql = "SELECT * FROM $tabla WHERE 1=1";

        if (!empty($extraWhere)) {
            $sql .= " AND ($extraWhere)";
        }

        if (!empty($search)) {
            $searchConditions = [];
            foreach ($allowedColumns as $i => $col) {
                $paramKey = ":search_$i";
                $searchConditions[] = "$col LIKE $paramKey";
                $params[$paramKey] = "%$search%";
            }
            $sql .= " AND (" . implode(" OR ", $searchConditions) . ")";
        }

        if (!in_array($orderColumn, $allowedColumns)) {
            $orderColumn = $allowedColumns[0];
        }
        $orderDir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';

        $start = (int)$start;
        $length = (int)$length;
        $sql .= " ORDER BY $orderColumn $orderDir LIMIT $start, $length";

        return $this->db->select($sql, $params);
    }

    public function countAll(string $tabla, string $extraWhere = "", array $params = []): int {
        $sql = "1=1";
        if (!empty($extraWhere)) {
            $sql .= " AND ($extraWhere)";
        }
        return (int)$this->db->count($tabla, $sql, $params);
    }

    public function countFiltered(
        string $tabla,
        ?string $search,
        array $allowedColumns,
        string $extraWhere = "",
        array $params = []
    ): int {
        $sql = "1=1";

        if (!empty($extraWhere)) {
            $sql .= " AND ($extraWhere)";
        }

        if (!empty($search)) {
            $searchConditions = [];
            foreach ($allowedColumns as $i => $col) {
                $paramKey = ":search_$i";
                $searchConditions[] = "$col LIKE $paramKey";
                $params[$paramKey] = "%$search%";
            }
            $sql .= " AND (" . implode(" OR ", $searchConditions) . ")";
        }

        return (int)$this->db->count($tabla, $sql, $params);
    }
}
