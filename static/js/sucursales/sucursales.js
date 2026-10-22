import { initCustomDataTable } from '../DataTable/datatables-init.js';
import { tienePermiso } from '../utils/permisos.js';
import Toast from '../utils/toast.js';

let tabla;

function modalSucursal(values = {}, id = null) {
  Swal.fire({
    title: id ? 'Editar sucursal' : 'Agregar sucursal',
    html: `
      <div class="text-start">
        <div class="mb-3">
          <label class="form-label">Nombre</label>
          <input id="suc_nombre" class="form-control" value="${values.nombre || ''}">
        </div>
        <div class="mb-3">
          <label class="form-label">Dirección</label>
          <input id="suc_direccion" class="form-control" value="${values.direccion || ''}">
        </div>
        <div class="mb-3">
          <label class="form-label">Teléfono</label>
          <input id="suc_telefono" class="form-control" value="${values.telefono || ''}">
        </div>
      </div>`,
    showCancelButton: true,
    confirmButtonText: id ? 'Actualizar' : 'Guardar',
    preConfirm: () => {
      const nombre = document.getElementById('suc_nombre').value;
      if (!nombre) {
        Swal.showValidationMessage('El nombre es obligatorio');
        return false;
      }
      return {
        id,
        nombre,
        direccion: document.getElementById('suc_direccion').value,
        telefono: document.getElementById('suc_telefono').value
      };
    }
  }).then((r) => {
    if (!r.isConfirmed) return;
    const tipo = id ? 'actualizar' : 'registrar';
    $.post(BASE_URL + 'api/sucursales.php?tipo=' + tipo, r.value, function (resp) {
      Toast.fire({ icon: resp.estatus ? 'success' : 'error', title: resp.mensaje });
      tabla.ajax.reload(false);
    }, 'json');
  });
}

window.agregarSucursal = () => modalSucursal();
window.editarSucursal = (id) => {
  $.post(BASE_URL + 'api/sucursales.php?tipo=obtener', { id }, function (resp) {
    if (resp.estatus) modalSucursal(resp.data, id);
  }, 'json');
};
window.cancelarSucursal = (id) => {
  Swal.fire({ icon: 'question', title: '¿Desactivar esta sucursal?', showCancelButton: true, confirmButtonText: 'Sí' })
    .then((r) => {
      if (!r.isConfirmed) return;
      $.post(BASE_URL + 'api/sucursales.php?tipo=cancelar', { id }, function (resp) {
        Toast.fire({ icon: resp.estatus ? 'success' : 'error', title: resp.mensaje });
        tabla.ajax.reload(false);
      }, 'json');
    });
};

$(document).ready(function () {
  const columns = [
    { data: 'id', title: '#' },
    { data: 'nombre', title: 'Nombre' },
    { data: 'direccion', title: 'Dirección' },
    { data: 'telefono', title: 'Teléfono' },
    { data: null, title: 'Estatus', render: (d) => d.estatus == 1
        ? '<span class="badge bg-success">Activa</span>'
        : '<span class="badge bg-secondary">Inactiva</span>' },
    { data: null, title: 'Opciones', render: (data, type, row) => `
        ${tienePermiso('editar_sucursales') ? `<div class="btn btn-primary btn-sm" onclick="editarSucursal(${row.id})"><i class="fa-solid fa-pen-to-square"></i></div>` : ''}
        ${tienePermiso('cancelar_sucursales') ? `<div class="btn btn-danger btn-sm" onclick="cancelarSucursal(${row.id})"><i class="fa-solid fa-trash"></i></div>` : ''}` }
  ];
  tabla = initCustomDataTable('#sucursales', BASE_URL + 'api/sucursales.php?tipo=datatable', columns);
});
