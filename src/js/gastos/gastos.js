import { initReportTable } from '../utils/report-table.js';
import Toast from '../utils/toast.js';

let tabla;
let sucursales = [];
let categorias = [];

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
}[character]));

async function cargarCatalogos() {
  try {
    const [sucursalesResponse, categoriasResponse] = await Promise.all([
      $.getJSON(`${BASE_URL}api/sucursales.php?tipo=combo`),
      $.getJSON(`${BASE_URL}api/gastos.php?tipo=categorias`)
    ]);
    sucursales = sucursalesResponse.data || [];
    categorias = categoriasResponse.data || [];
  } catch (error) {
    console.error(error);
    Toast.fire({ icon: 'error', title: 'No se pudieron cargar sucursales o categorías' });
  }
}

async function gestionarCategorias() {
  const result = await Swal.fire({
    title: 'Agregar categoría de gasto',
    input: 'text',
    inputLabel: 'Nombre de la categoría',
    inputPlaceholder: 'Ej. Servicios, mantenimiento, renta',
    inputAttributes: { maxlength: 100, autocomplete: 'off' },
    showCancelButton: true,
    confirmButtonText: 'Agregar',
    cancelButtonText: 'Cerrar',
    preConfirm: (value) => {
      if (!value || !value.trim()) {
        Swal.showValidationMessage('Escribe el nombre de la categoría');
        return false;
      }
      return value.trim();
    }
  });
  if (!result.isConfirmed) return;
  try {
    const response = await $.post(`${BASE_URL}api/gastos.php?tipo=categoria_crear`, { nombre: result.value });
    Toast.fire({ icon: response.estatus ? 'success' : 'error', title: response.mensaje });
    if (response.estatus) await cargarCatalogos();
  } catch (error) {
    Toast.fire({ icon: 'error', title: 'No se pudo agregar la categoría' });
  }
}

async function abrirFormulario(gasto = null) {
  if (categorias.length === 0) {
    const createCategory = await Swal.fire({
      icon: 'info', title: 'Agrega una categoría primero',
      text: 'Cada gasto debe pertenecer a una categoría.',
      showCancelButton: true, confirmButtonText: 'Crear categoría', cancelButtonText: 'Cerrar'
    });
    if (createCategory.isConfirmed) await gestionarCategorias();
    if (categorias.length === 0) return;
  }

  const editing = Boolean(gasto);
  const today = new Date();
  const localToday = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
  const receiptMarkup = gasto?.comprobante_url
    ? `<div class="mb-3"><span class="form-label d-block">Comprobante actual</span><a class="btn btn-sm btn-outline-secondary" href="${escapeHtml(gasto.comprobante_url)}" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-paperclip me-1"></i>Ver comprobante</a><small class="form-text d-block">Selecciona un archivo nuevo solo si deseas reemplazarlo.</small></div>`
    : '';
  const result = await Swal.fire({
    title: editing ? 'Actualizar gasto' : 'Registrar gasto',
    width: '760px',
    html: `<form id="form-gasto" class="text-start" enctype="multipart/form-data">
      <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label" for="gasto-sucursal">Sucursal *</label><select id="gasto-sucursal" class="selectpicker w-100" data-live-search="true" required></select></div>
        <div class="col-md-6 mb-3"><label class="form-label" for="gasto-categoria">Categoría *</label><select id="gasto-categoria" class="selectpicker w-100" data-live-search="true" required></select></div>
      </div>
      <div class="row">
        <div class="col-md-4 mb-3"><label class="form-label" for="gasto-monto">Monto *</label><input id="gasto-monto" class="form-control" type="number" min="0.01" step="0.01" required value="${editing ? Number(gasto.monto).toFixed(2) : ''}"></div>
        <div class="col-md-4 mb-3"><label class="form-label" for="gasto-fecha">Fecha *</label><input id="gasto-fecha" class="form-control" type="date" required value="${escapeHtml(editing ? gasto.fecha : localToday)}"></div>
        <div class="col-md-4 mb-3"><label class="form-label" for="gasto-folio">No. folio / factura</label><input id="gasto-folio" class="form-control" maxlength="100" value="${escapeHtml(gasto?.folio || '')}"></div>
      </div>
      <div class="mb-3"><label class="form-label" for="gasto-descripcion">Descripción *</label><textarea id="gasto-descripcion" class="form-control" rows="3" maxlength="500" required>${escapeHtml(gasto?.descripcion || '')}</textarea></div>
      ${receiptMarkup}
      <div class="mb-2"><label class="form-label" for="gasto-comprobante">Comprobante ${editing ? '(opcional)' : '(opcional)'}</label><input id="gasto-comprobante" class="form-control" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp"><small class="form-text">PDF, JPG, PNG o WEBP; máximo 10 MB.</small></div>
    </form>`,
    showCancelButton: true,
    confirmButtonText: editing ? 'Guardar cambios' : 'Registrar gasto',
    cancelButtonText: 'Cancelar',
    didOpen: () => {
      const branch = document.getElementById('gasto-sucursal');
      const category = document.getElementById('gasto-categoria');
      $(branch).selectpicker({ liveSearch: true, noneSelectedText: 'Selecciona una sucursal' });
      $(category).selectpicker({ liveSearch: true, noneSelectedText: 'Selecciona una categoría' });
      cargarOpciones(branch, sucursales, 'id', 'nombre', gasto?.id_sucursal || '');
      cargarOpciones(category, categorias, 'id', 'nombre', gasto?.id_categoria || '');
    },
    preConfirm: () => {
      const file = document.getElementById('gasto-comprobante').files[0];
      if (file && file.size > 10 * 1024 * 1024) {
        Swal.showValidationMessage('El comprobante no puede superar 10 MB');
        return false;
      }
      const payload = {
        id_sucursal: document.getElementById('gasto-sucursal').value,
        id_categoria: document.getElementById('gasto-categoria').value,
        monto: document.getElementById('gasto-monto').value,
        fecha: document.getElementById('gasto-fecha').value,
        folio: document.getElementById('gasto-folio').value.trim(),
        descripcion: document.getElementById('gasto-descripcion').value.trim(),
        comprobante: file || null
      };
      console.log(payload);
      if (!payload.id_sucursal || !payload.id_categoria || !payload.fecha || !payload.descripcion || Number(payload.monto) <= 0) {
        Swal.showValidationMessage('Completa sucursal, categoría, monto, fecha y descripción');
        return false;
      }
      return payload;
    }
  });

  if (!result.isConfirmed) return;
  const formData = new FormData();
  Object.entries(result.value).forEach(([key, value]) => {
    if (value instanceof File) formData.append('comprobante', value);
    else formData.append(key, value ?? '');
  });
  if (editing) formData.append('id', String(gasto.id));
  const type = editing ? 'actualizar' : 'registrar';
  try {
    Swal.fire({ title: editing ? 'Actualizando gasto' : 'Registrando gasto', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
    const response = await fetch(`${BASE_URL}api/gastos.php?tipo=${type}`, { method: 'POST', body: formData });
    const data = await response.json();
    Swal.close();
    Toast.fire({ icon: data.estatus ? 'success' : 'error', title: data.mensaje });
    if (data.estatus && tabla) tabla.ajax.reload(null, false);
  } catch (error) {
    Swal.close();
    Toast.fire({ icon: 'error', title: 'No se pudo guardar el gasto' });
  }
}

async function editarGasto(id) {
  try {
    const response = await $.getJSON(`${BASE_URL}api/gastos.php?tipo=obtener&id=${encodeURIComponent(id)}`);
    if (!response.estatus) return Toast.fire({ icon: 'error', title: response.mensaje });
    await abrirFormulario(response.data);
  } catch (error) {
    Toast.fire({ icon: 'error', title: 'No se pudo cargar el gasto' });
  }
}

function cancelarGasto(id) {
  Swal.fire({
    icon: 'warning', title: '¿Cancelar este gasto?',
    text: 'Se conservará en el historial con estado cancelado. El comprobante no se eliminará.',
    showCancelButton: true, confirmButtonText: 'Cancelar gasto', confirmButtonColor: '#dc3545', cancelButtonText: 'Volver'
  }).then(async (result) => {
    if (!result.isConfirmed) return;
    try {
      const response = await $.post(`${BASE_URL}api/gastos.php?tipo=cancelar`, { id });
      Toast.fire({ icon: response.estatus ? 'success' : 'error', title: response.mensaje });
      if (response.estatus && tabla) tabla.ajax.reload(null, false);
    } catch (error) {
      Toast.fire({ icon: 'error', title: 'No se pudo cancelar el gasto' });
    }
  });
}

window.editarGasto = editarGasto;
window.cancelarGasto = cancelarGasto;

$(document).ready(async () => {
  const columns = [
    { data: 'id', title: '#' },
    { data: 'fecha', title: 'Fecha' },
    { data: 'sucursal', title: 'Sucursal' },
    { data: 'categoria', title: 'Categoría' },
    { data: 'descripcion', title: 'Descripción' },
    { data: 'folio', title: 'Folio / factura', render: (value) => value || '<span class="text-muted">—</span>' },
    { data: 'monto', title: 'Monto', render: (value) => `<strong>$${Number(value || 0).toFixed(2)}</strong>` },
    { data: 'usuario', title: 'Registró' },
    { data: null, title: 'Comprobante', orderable: false, render: (data, type, row) => row.comprobante_url
      ? `<a class="btn btn-outline-secondary btn-sm" href="${escapeHtml(row.comprobante_url)}" target="_blank" rel="noopener noreferrer" title="Ver comprobante"><i class="fa-solid fa-paperclip"></i></a>`
      : '<span class="text-muted">—</span>' },
    { data: null, title: 'Estado', orderable: false, render: (data, type, row) => Number(row.estatus) === 1
      ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Cancelado</span>' },
    { data: null, title: 'Acciones', orderable: false, render: (data, type, row) => {
      if (Number(row.estatus) !== 1) return '<span class="text-muted">—</span>';
      return [
        PERMISOS_GASTOS.editar ? `<button type="button" class="btn btn-primary btn-sm" onclick="editarGasto(${Number(row.id)})" title="Actualizar gasto"><i class="fa-solid fa-pen-to-square"></i></button>` : '',
        PERMISOS_GASTOS.cancelar ? `<button type="button" class="btn btn-danger btn-sm" onclick="cancelarGasto(${Number(row.id)})" title="Cancelar gasto"><i class="fa-solid fa-ban"></i></button>` : ''
      ].filter(Boolean).join(' ') || '<span class="text-muted">—</span>';
    }}
  ];

  await cargarCatalogos();
  tabla = initReportTable({
    selector: '#tabla-gastos',
    module: 'gastos',
    columns,
    order: [[1, 'desc']],
    fields: [
      { name: 'id_sucursal', label: 'Sucursal', type: 'select', options: sucursales.map((sucursal) => ({ value: sucursal.id, label: sucursal.nombre })), liveSearch: true },
      { name: 'id_categoria', label: 'Categoría', type: 'select', options: categorias.map((categoria) => ({ value: categoria.id, label: categoria.nombre })), liveSearch: true },
      { name: 'estatus', label: 'Estado', type: 'select', defaultValue: '1', options: [{ value: '1', label: 'Activos' }, { value: '0', label: 'Cancelados' }, { value: 'all', label: 'Todos' }] },
      { name: 'folio', label: 'Folio / factura', placeholder: 'Número de folio' },
      { name: 'descripcion', label: 'Descripción', placeholder: 'Texto descriptivo' }
    ]
  });

  $('#btn-nuevo-gasto').on('click', () => abrirFormulario());
  $('#btn-categorias-gasto').on('click', gestionarCategorias);
});
