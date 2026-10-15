import { initCustomDataTable } from '../DataTable/datatables-init.js';
import Toast from '../utils/toast.js';

let tabla;

function modalProveedor(values = {}, id = null) {
  Swal.fire({
    title: id ? 'Editar Proveedor' : 'Agregar Proveedor',
    html: `
      <div class="text-start">
        <div class="mb-3">
          <label class="form-label">Nombre de la Empresa o Proveedor *</label>
          <input id="prov_nombre" class="form-control" value="${values.nombre || ''}" placeholder="Ej: Truper, Urrea, Cementos Moctezuma">
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">RFC</label>
            <input id="prov_rfc" class="form-control" value="${values.rfc || ''}" placeholder="TRU800101XYZ">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Persona de Contacto</label>
            <input id="prov_contacto" class="form-control" value="${values.contacto || ''}" placeholder="Lic. Roberto Gómez">
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Teléfono</label>
            <input id="prov_telefono" class="form-control" value="${values.telefono || ''}" placeholder="8001234567">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Correo Electrónico</label>
            <input id="prov_correo" type="email" class="form-control" value="${values.correo || ''}" placeholder="ventas@proveedor.com">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Dirección / Centro de Distribución</label>
          <textarea id="prov_direccion" class="form-control" rows="2" placeholder="Dirección completa">${values.direccion || ''}</textarea>
        </div>
      </div>`,
    showCancelButton: true,
    confirmButtonText: id ? 'Actualizar' : 'Guardar',
    cancelButtonText: 'Cancelar',
    preConfirm: () => {
      const nombre = document.getElementById('prov_nombre').value.trim();
      if (!nombre) {
        Swal.showValidationMessage('El nombre del proveedor es obligatorio');
        return false;
      }
      return {
        id,
        nombre,
        rfc: document.getElementById('prov_rfc').value.trim(),
        contacto: document.getElementById('prov_contacto').value.trim(),
        telefono: document.getElementById('prov_telefono').value.trim(),
        correo: document.getElementById('prov_correo').value.trim(),
        direccion: document.getElementById('prov_direccion').value.trim()
      };
    }
  }).then((r) => {
    if (!r.isConfirmed) return;
    const tipo = id ? 'actualizar' : 'registrar';
    $.post(BASE_URL + 'api/proveedores.php?tipo=' + tipo, r.value, function (resp) {
      Toast.fire({ icon: resp.estatus ? 'success' : 'error', title: resp.mensaje });
      if (resp.estatus && tabla) {
        tabla.ajax.reload(false);
      }
    }, 'json');
  });
}

window.agregarProveedor = () => modalProveedor();
window.editarProveedor = (id) => {
  $.post(BASE_URL + 'api/proveedores.php?tipo=obtener', { id }, function (resp) {
    if (resp.estatus) {
      modalProveedor(resp.data, id);
    } else {
      Toast.fire({ icon: 'error', title: resp.mensaje });
    }
  }, 'json');
};

window.cancelarProveedor = (id) => {
  Swal.fire({
    icon: 'question',
    title: '¿Desactivar este proveedor?',
    text: 'El proveedor no aparecerá para nuevas órdenes de compra hasta ser reactivado.',
    showCancelButton: true,
    confirmButtonText: 'Sí, desactivar',
    cancelButtonText: 'Cancelar'
  }).then((r) => {
    if (!r.isConfirmed) return;
    $.post(BASE_URL + 'api/proveedores.php?tipo=cancelar', { id }, function (resp) {
      Toast.fire({ icon: resp.estatus ? 'success' : 'error', title: resp.mensaje });
      if (resp.estatus && tabla) {
        tabla.ajax.reload(false);
      }
    }, 'json');
  });
};

$(document).ready(function () {
  const columns = [
    { data: 'id', title: '#' },
    { data: 'nombre', title: 'Proveedor' },
    { data: 'contacto', title: 'Contacto', render: (d) => d || '<span class="text-muted">N/D</span>' },
    { data: 'telefono', title: 'Teléfono', render: (d) => d || '<span class="text-muted">N/D</span>' },
    { data: 'correo', title: 'Correo', render: (d) => d || '<span class="text-muted">N/D</span>' },
    {
      data: null,
      title: 'Estatus',
      render: (d) => d.estatus == 1
        ? '<span class="badge bg-success">Activo</span>'
        : '<span class="badge bg-secondary">Inactivo</span>'
    },
    {
      data: null,
      title: 'Opciones',
      render: (data, type, row) => `
        <button class="btn btn-primary btn-sm" onclick="editarProveedor(${row.id})" title="Editar"><i class="fa-solid fa-pen-to-square"></i></button>
        <button class="btn btn-danger btn-sm" onclick="cancelarProveedor(${row.id})" title="Desactivar"><i class="fa-solid fa-trash"></i></button>`
    }
  ];

  tabla = initCustomDataTable('#tabla-proveedores', BASE_URL + 'api/proveedores.php?tipo=datatable', columns);
});
