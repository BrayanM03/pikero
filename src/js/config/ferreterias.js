import { initCustomDataTable } from '../DataTable/datatables-init.js';
import Toast from '../utils/toast.js';
import { cargarCatalogoSAT, inicializarSelectpicker, opcionesSAT } from '../utils/catalogos-sat.js';

let tabla;

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[char]));

async function modalFerreteria(values = {}, id = null) {
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
    title: id ? 'Editar ferretería' : 'Agregar ferretería',
    width: '760px',
    html: `<div class="text-start">
      <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">Nombre comercial *</label><input id="fer_nombre" class="form-control" value="${escapeHtml(values.nombre)}"></div>
        <div class="col-md-6 mb-3"><label class="form-label">Razón social</label><input id="fer_razon" class="form-control" value="${escapeHtml(values.razon_social)}"></div>
      </div>
      <div class="row">
        <div class="col-md-4 mb-3"><label class="form-label">RFC</label><input id="fer_rfc" class="form-control" value="${escapeHtml(values.rfc)}"></div>
        <div class="col-md-4 mb-3"><label class="form-label">Teléfono</label><input id="fer_telefono" class="form-control" value="${escapeHtml(values.telefono)}"></div>
        <div class="col-md-4 mb-3"><label class="form-label">Correo</label><input id="fer_correo" type="email" class="form-control" value="${escapeHtml(values.correo)}"></div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">Dirección fiscal</label><input id="fer_direccion" class="form-control" value="${escapeHtml(values.direccion)}"></div>
        <div class="col-md-3 mb-3"><label class="form-label">Código postal fiscal</label><select id="fer_cp" class="selectpicker w-100" data-catalogo-sat="PostalCodes" data-live-search="true">${opcionesSAT([], values.codigo_postal)}</select></div>
        <div class="col-md-3 mb-3"><label class="form-label">Régimen fiscal</label><select id="fer_regimen" class="selectpicker w-100" data-live-search="true">${opcionesSAT(regimenes, values.regimen_fiscal)}</select></div>
      </div>
      <div class="row align-items-end">
        <div class="col-md-6 mb-3"><label class="form-label">Uso CFDI predeterminado</label><select id="fer_uso" class="selectpicker w-100" data-live-search="true">${opcionesSAT(usosCfdi, values.uso_cfdi)}</select></div>
        <div class="col-md-6 mb-3"><label class="form-label">Logo</label><input id="fer_logo" type="file" accept="image/png,image/jpeg,image/webp" class="form-control"><small class="text-muted">PNG, JPG o WEBP; máximo 3 MB.${values.logo ? ` Logo actual: ${escapeHtml(values.logo)}` : ''}</small></div>
      </div>
      <div class="row align-items-end">
        <div class="col-md-6 mb-3"><label class="form-label">Tipo reporte de venta</label>
        <select id="fer_tipo_reporte" class="selectpicker w-100" data-live-search="true">
          <option value="1">Ticket</option>
          <option value="2">Reporte pdf</option>
        </select></div>
      </div>
    </div>`,
    showCancelButton: true,
    didOpen: () => inicializarSelectpicker(Swal.getPopup()),
    confirmButtonText: id ? 'Actualizar' : 'Guardar',
    cancelButtonText: 'Cancelar',
    preConfirm: () => {
      const nombre = $('#fer_nombre').val().trim();
      if (!nombre) { Swal.showValidationMessage('El nombre comercial es obligatorio'); return false; }
      const form = new FormData();
      form.append('id', id || '');
      form.append('nombre', nombre);
      form.append('razon_social', $('#fer_razon').val().trim());
      form.append('rfc', $('#fer_rfc').val().trim());
      form.append('telefono', $('#fer_telefono').val().trim());
      form.append('correo', $('#fer_correo').val().trim());
      form.append('direccion', $('#fer_direccion').val().trim());
      form.append('codigo_postal', $('#fer_cp').val().trim());
      form.append('regimen_fiscal', $('#fer_regimen').val().trim());
      form.append('uso_cfdi', $('#fer_uso').val().trim());
      form.append('tipo_reporte', $('#fer_tipo_reporte').val().trim());
      if ($('#fer_logo')[0].files[0]) form.append('logo', $('#fer_logo')[0].files[0]);
      return form;
    }
  }).then((result) => {
    if (!result.isConfirmed) return;
    const tipo = id ? 'actualizar' : 'registrar';
    $.ajax({
      url: `${BASE_URL}api/ferreterias.php?tipo=${tipo}`,
      type: 'POST',
      data: result.value,
      processData: false,
      contentType: false,
      dataType: 'json'
    }).done((response) => {
      Toast.fire({ icon: response.estatus ? 'success' : 'error', title: response.mensaje });
      if (response.estatus) tabla?.ajax.reload(null, false);
    }).fail((xhr) => Toast.fire({ icon: 'error', title: xhr.responseJSON?.mensaje || 'No se pudo guardar la ferretería' }));
  });
}

window.agregarFerreteria = () => modalFerreteria();
window.editarFerreteria = (id) => $.post(`${BASE_URL}api/ferreterias.php?tipo=obtener`, { id }, (response) => {
  if (response.estatus) modalFerreteria(response.data, id);
  else Toast.fire({ icon: 'error', title: response.mensaje });
}, 'json');
window.cancelarFerreteria = (id) => Swal.fire({
  icon: 'warning', title: '¿Cancelar esta ferretería?', text: 'Quedará inactiva, sin borrar su información.',
  showCancelButton: true, confirmButtonText: 'Cancelar ferretería', cancelButtonText: 'Volver'
}).then((result) => {
  if (!result.isConfirmed) return;
  $.post(`${BASE_URL}api/ferreterias.php?tipo=cancelar`, { id }, (response) => {
    Toast.fire({ icon: response.estatus ? 'success' : 'error', title: response.mensaje });
    if (response.estatus) tabla?.ajax.reload(null, false);
  }, 'json');
});

$(document).ready(() => {
  const columns = [
    { data: 'id', title: '#' },
    { data: 'nombre', title: 'Ferretería', render: (value) => escapeHtml(value) },
    { data: 'razon_social', title: 'Razón social', render: (value) => escapeHtml(value || 'N/D') },
    { data: 'rfc', title: 'RFC', render: (value) => escapeHtml(value || 'N/D') },
    { data: 'correo', title: 'Correo', render: (value) => escapeHtml(value || 'N/D') },
    { data: 'logo', title: 'Logo', orderable: false, searchable: false, render: (value) => value ? `<img src="${SUPABASE_URL}/storage/v1/object/public/pikero_public/${value}" alt="Logo" style="max-width:70px;max-height:38px">` : 'N/D' },
    { data: 'estatus', title: 'Estatus', render: (value) => Number(value) === 1 ? '<span class="badge bg-success">Activa</span>' : '<span class="badge bg-secondary">Cancelada</span>' },
    { data: null, title: 'Acciones', orderable: false, render: (row) => [
      USER_PERMISSIONS.can_edit_ferreterias ? `<button class="btn btn-primary btn-sm" onclick="editarFerreteria(${Number(row.id)})" title="Editar"><i class="fa-solid fa-pen-to-square"></i></button>` : '',
      USER_PERMISSIONS.can_cancel_ferreterias && Number(row.estatus) === 1 ? `<button class="btn btn-danger btn-sm" onclick="cancelarFerreteria(${Number(row.id)})" title="Cancelar"><i class="fa-solid fa-ban"></i></button>` : ''
    ].join(' ') || '<span class="text-muted">-</span>' }
  ];
  tabla = initCustomDataTable('#tabla-ferreterias', `${BASE_URL}api/ferreterias.php?tipo=datatable`, columns);
});
