import { initReportTable } from '../utils/report-table.js';
import { tienePermiso } from '../utils/permisos.js';
let tabla;
$(document).ready(function () { 
  console.log(USER_PERMISSIONS);
  console.log(permisos_usuario);
  let cancel_users_p = tienePermiso('cancelar_usuarios')
  let edit_users_p = tienePermiso('editar_usuarios')
  const columns = [
    { data: 'id', title: '#' },
    { data: 'foto_perfil', title: 'Foto', render: (data) => {
        const ruta = data ? BASE_URL + 'static/img/avatars/' + data : STATIC_URL + 'img/avatars/default.png';
        return `<img class="rounded" style="width:40px;" src="${ruta}">`;
    }},
    { data: null, title: 'Nombre', render: (data) => data.nombre + ' ' + data.apellido },
    { data: 'nombre_rol', title: 'Rol' },
    { data: 'usuario', title: 'Usuario' },
    { data: 'sucursal', title: 'Sucursal' },
    { data: null, title: 'Estatus', render: (data) => {
        return data.estatus == 1
          ? '<span class="badge bg-success">Activo</span>'
          : '<span class="badge bg-secondary">Inactivo</span>';
    }},
    { data: null, title: 'Opciones', render: function (data, type, row) {
        return `
          ${edit_users_p ? `<a href="${BASE_URL}usuarios/editar/${row.id}" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-pen-to-square"></i>
          </a>` : ''}
          ${cancel_users_p ? `<div class="btn btn-danger btn-sm" onclick="cancelarUsuario(${row.id})">
            <i class="fa-solid fa-trash"></i>
          </div>` : ''}`;
    }}
  ];

  tabla = initReportTable({
    selector: '#usuarios',
    module: 'usuarios',
    columns,
    order: [[2, 'asc']],
    fields: [
      { name: 'nombre', label: 'Nombre', placeholder: 'Nombre y apellidos' },
      { name: 'usuario', label: 'Usuario', placeholder: 'Nombre de usuario' },
      { name: 'rol', label: 'Rol', type: 'select', endpoint: 'api/roles.php?tipo=combo', liveSearch: true },
      { name: 'sucursal', label: 'Sucursal', type: 'select', endpoint: 'api/sucursales.php?tipo=combo', liveSearch: true },
      { name: 'estatus', label: 'Estatus', type: 'select', options: [{ value: '1', label: 'Activo' }, { value: '0', label: 'Inactivo' }] }
    ]
  });
});

window.cancelarUsuario = function (id) {
  Swal.fire({
    icon: 'question',
    title: '¿Desactivar este usuario?',
    showCancelButton: true,
    cancelButtonText: 'No',
    confirmButtonText: 'Sí'
  }).then(r => {
    if (!r.isConfirmed) return;
    $.post(BASE_URL + 'api/usuarios.php?tipo=cancelar', { id }, function (response) {
      Swal.fire({ icon: response.estatus ? 'success' : 'error', title: response.mensaje });
      tabla.ajax.reload(false);
    }, 'json');
  });
};
