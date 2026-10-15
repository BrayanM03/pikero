import { initCustomDataTable } from '../DataTable/datatables-init.js';
$(document).ready(function () {
  const columns = [
    { data: 'id', title: '#' },
    { data: 'nombre', title: 'Nombre' },
    { data: 'descripcion', title: 'Descripción' },
    { data: null, title: 'Estatus', render: (data) => data.estatus == 1
        ? '<span class="badge bg-success">Activo</span>'
        : '<span class="badge bg-secondary">Inactivo</span>' },
    { data: null, title: 'Opciones', render: function (data, type, row) {
        return `
          <a href="${BASE_URL}roles/editar/${row.id}" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-pen-to-square"></i>
          </a>`;
    }}
  ];
  initCustomDataTable('#roles', BASE_URL + 'api/roles.php?tipo=datatable', columns);
});
