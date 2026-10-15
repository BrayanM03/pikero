import { initCustomDataTable } from '../DataTable/datatables-init.js';
$(document).ready(function () {
  const columns = [
    { data: 'id', title: '#' },
    { data: null, title: 'Nombre', render: (data) => data.nombre + ' ' + data.apellido },
    { data: 'nombre_rol', title: 'Rol' },
    { data: 'usuario', title: 'Usuario' },
    { data: 'sucursal', title: 'Sucursal' },
    { data: null, title: 'Opciones', render: function (data, type, row) {
        return `
          <a href="${BASE_URL}usuarios/editar/${row.id}" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-user-shield"></i>
          </a>`;
    }}
  ];
  initCustomDataTable('#usuarios', BASE_URL + 'api/usuarios.php?tipo=datatable', columns, [[1, 'asc']]);
});
