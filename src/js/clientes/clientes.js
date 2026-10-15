import { initReportTable } from '../utils/report-table.js';
import Toast from '../utils/toast.js';
import { cargarCatalogoSAT, inicializarSelectpicker, opcionesSAT } from '../utils/catalogos-sat.js';

let tabla;

async function modalCliente(values = {}, id = null) {
  let regimenes;
  let usosCfdi;
  try {
    [regimenes, usosCfdi] = await Promise.all([
      cargarCatalogoSAT('FiscalRegimens'),
      cargarCatalogoSAT('CfdiUses')
    ]);
  } catch (error) {
    Toast.fire({ icon: 'error', title: error.message });
    return;
  }
  Swal.fire({
    title: id ? 'Editar Cliente' : 'Agregar Cliente',
    width: '1050px',
    html: `
      <div class="text-start">
        <div class="mb-3">
          <label class="form-label">Nombre *</label>
          <input id="cli_nombre" class="form-control" value="${values.nombre || ''}" placeholder="Ej: Ferretera del Norte o Juan Pérez">
        </div>
        <div class="mb-3">
          <label class="form-label">Razón social fiscal</label>
          <input id="cli_razon_social" class="form-control" value="${values.razon_social || ''}" placeholder="Como aparece en la constancia fiscal">
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">RFC</label>
            <input id="cli_rfc" class="form-control" value="${values.rfc || ''}" placeholder="XAXX010101000">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Teléfono</label>
            <input id="cli_telefono" class="form-control" value="${values.telefono || ''}" placeholder="8681234567">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Correo Electrónico</label>
          <input id="cli_correo" type="email" class="form-control" value="${values.correo || ''}" placeholder="cliente@correo.com">
        </div>
        <div class="mb-3">
          <label class="form-label">Dirección</label>
          <textarea id="cli_direccion" class="form-control" rows="2" placeholder="Calle, Número, Colonia, Ciudad">${values.direccion || ''}</textarea>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3"><label class="form-label">Código postal</label><select id="cli_codigo_postal" class="selectpicker w-100" data-catalogo-sat="PostalCodes" data-live-search="true">${opcionesSAT([], values.codigo_postal)}</select></div>
          <div class="col-md-6  mb-3"><label class="form-label">Régimen fiscal</label><select id="cli_regimen_fiscal" class="selectpicker w-100" data-live-search="true">${opcionesSAT(regimenes, values.regimen_fiscal)}</select></div>
          <div class="col-md-6 mb-3"><label class="form-label">Uso de CFDI</label><select id="cli_uso_cfdi" class="selectpicker w-100" data-live-search="true">${opcionesSAT(usosCfdi, values.uso_cfdi)}</select></div>
        </div>
      </div>`,
    showCancelButton: true,
    didOpen: () => inicializarSelectpicker(Swal.getPopup()),
    confirmButtonText: id ? 'Actualizar' : 'Guardar',
    cancelButtonText: 'Cancelar',
    preConfirm: () => {
      const nombre = document.getElementById('cli_nombre').value.trim();
      if (!nombre) {
        Swal.showValidationMessage('El nombre o razón social es obligatorio');
        return false;
      }
      return {
        id,
        nombre,
        razon_social: document.getElementById('cli_razon_social').value.trim(),
        rfc: document.getElementById('cli_rfc').value.trim(),
        telefono: document.getElementById('cli_telefono').value.trim(),
        correo: document.getElementById('cli_correo').value.trim(),
        direccion: document.getElementById('cli_direccion').value.trim()
        ,codigo_postal: document.getElementById('cli_codigo_postal').value.trim()
        ,regimen_fiscal: document.getElementById('cli_regimen_fiscal').value.trim()
        ,uso_cfdi: document.getElementById('cli_uso_cfdi').value.trim()
      };
    }
  }).then((r) => {
    if (!r.isConfirmed) return;
    const tipo = id ? 'actualizar' : 'registrar';
    $.post(BASE_URL + 'api/clientes.php?tipo=' + tipo, r.value, function (resp) {
      Toast.fire({ icon: resp.estatus ? 'success' : 'error', title: resp.mensaje });
      if (resp.estatus && tabla) {
        tabla.ajax.reload(false);
      }
    }, 'json');
  });
}

window.agregarCliente = () => modalCliente();
window.editarCliente = (id) => {
  $.post(BASE_URL + 'api/clientes.php?tipo=obtener', { id }, function (resp) {
    if (resp.estatus) {
      modalCliente(resp.data, id);
    } else {
      Toast.fire({ icon: 'error', title: resp.mensaje });
    }
  }, 'json');
};

window.cancelarCliente = (id) => {
  Swal.fire({
    icon: 'question',
    title: '¿Desactivar este cliente?',
    text: 'El cliente no aparecerá en el punto de venta hasta ser reactivado.',
    showCancelButton: true,
    confirmButtonText: 'Sí, desactivar',
    cancelButtonText: 'Cancelar'
  }).then((r) => {
    if (!r.isConfirmed) return;
    $.post(BASE_URL + 'api/clientes.php?tipo=cancelar', { id }, function (resp) {
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
    { data: 'nombre', title: 'Nombre / Razón Social' },
    { data: 'rfc', title: 'RFC', render: (d) => d || '<span class="text-muted">N/D</span>' },
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
      render: (data, type, row) => [
        USER_PERMISSIONS.can_edit_clientes ? `<button class="btn btn-primary btn-sm" onclick="editarCliente(${row.id})" title="Editar"><i class="fa-solid fa-pen-to-square"></i></button>` : '',
        USER_PERMISSIONS.can_cancel_clientes ? `<button class="btn btn-danger btn-sm" onclick="cancelarCliente(${row.id})" title="Desactivar"><i class="fa-solid fa-trash"></i></button>` : ''
      ].join(' ') || '<span class="text-muted">-</span>'
    }
  ];

  tabla = initReportTable({
    selector: '#tabla-clientes',
    module: 'clientes',
    columns,
    fields: [
      { name: 'nombre', label: 'Nombre', placeholder: 'Nombre del cliente' },
      { name: 'rfc', label: 'RFC', placeholder: 'RFC' },
      { name: 'telefono', label: 'Teléfono', placeholder: 'Teléfono' },
      { name: 'correo', label: 'Correo', placeholder: 'Correo' },
      { name: 'tipo_cliente', label: 'Tipo de cliente', type: 'select', options: [{ value: '0', label: 'Cliente' }, { value: '1', label: 'Público en general' }] },
      { name: 'estatus', label: 'Estatus', type: 'select', options: [{ value: '1', label: 'Activo' }, { value: '0', label: 'Inactivo' }] }
    ]
  });
});
