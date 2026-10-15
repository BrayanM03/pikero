<?php
abstract class DataTableController {
    protected $model;

    public function __construct() {
    }

    public function datatable_general($id_filtro = '', $filtros = []) {
        $request = array_merge($_GET, $_POST);

        $start = $request['start'] ?? 0;
        $length = $request['length'] ?? 10;
        $search = $request['search']['value'] ?? '';
        $orderColumnIndex = $request['order'][0]['column'] ?? 0;
        $orderColumnName  = $request['columns'][$orderColumnIndex]['data'] ?? 'id';
        $orderDir         = $request['order'][0]['dir'] ?? 'asc';
        $draw             = $request['draw'] ?? 1;

        $data = $this->getModelData($id_filtro, $start, $length, $search, $orderColumnName, $orderDir, $filtros);
        $total = $this->getModelTotal($id_filtro, $filtros);
        $filtered = $this->getModelFilteredTotal($id_filtro, $search, $filtros);

        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $total,
            "recordsFiltered" => $filtered,
            "data" => $data
        ]);
    }

    abstract protected function getModelData($id_filtro, $start, $length, $search, $orderColumnName, $orderDir, $filtros);
    abstract protected function getModelTotal($id_filtro, $filtros);
    abstract protected function getModelFilteredTotal($id_filtro, $search, $filtros);
}
