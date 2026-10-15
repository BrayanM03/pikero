import { initCustomDataTable } from '../DataTable/datatables-init.js';
import Toast from '../utils/toast.js';

const CATALOGO = window.CATALOGO_TIPO;
const LABELS = window.CATALOGO_LABELS || { singular: 'registro', plural: 'registros' };
let tabla;

function camposForm(values = {}) {
  if (CATALOGO === 'unidades') {
    return `
      <div class="mb-3 text-start">
        <label class="form-label">Nombre</label>
        <input id="cat_nombre" class="form-control" value="${values.nombre || ''}">
      </div>
      <div class="mb-3 text-start">
        <label class="form-label">Abreviatura</label>
        <input id="cat_abreviatura" class="form-control" value="${values.abreviatura || ''}">
      </div>
      <div class="mb-3 text-start">
        <label class="form-label">Descripción</label>
        <input id="cat_descripcion" class="form-control" value="${values.descripcion || ''}">
      </div>`;
  }
  return `
    <div class="mb-3 text-start">
      <label class="form-label">Nombre</label>
      <input id="cat_nombre" class="form-control" value="${values.nombre || ''}">
    </div>
    <div class="mb-3 text-start">
      <label class="form-label">Descripción</label>
      <input id="cat_descripcion" class="form-control" value="${values.descripcion || ''}">
    </div>`;
}

function payloadDesdeForm() {
  const data = {
    catalogo: CATALOGO,
    nombre: document.getElementById('cat_nombre').value,
    descripcion: document.getElementById('cat_descripcion')?.value || ''
  };
  if (CATALOGO === 'unidades') {
    data.abreviatura = document.getElementById('cat_abreviatura').value;
  }
  return data;
}

function modalRegistro(values = {}, id = null) {
  Swal.fire({
    title: id ? 'Editar ' + LABELS.singular : 'Agregar ' + LABELS.singular,
    html: camposForm(values),
    showCancelButton: true,
    confirmButtonText: id ? 'Actualizar' : 'Guardar',
    preConfirm: () => {
      const data = payloadDesdeForm();
      if (!data.nombre) {
        Swal.showValidationMessage('El nombre es obligatorio');
        return false;
      }
      if (id) data.id = id;
      return data;
    }
  }).then((r) => {
    if (!r.isConfirmed) return;
    const tipo = id ? 'actualizar' : 'registrar';
    $.post(BASE_URL + 'api/catalogos.php?tipo=' + tipo + '&catalogo=' + CATALOGO, r.value, function (resp) {
      Toast.fire({ icon: resp.estatus ? 'success' : 'error', title: resp.mensaje });
      tabla.ajax.reload(false);
    }, 'json');
  });
}

window.agregarCatalogo = () => modalRegistro();
window.editarCatalogo = (id) => {
  $.post(BASE_URL + 'api/catalogos.php?tipo=obtener&catalogo=' + CATALOGO, { id, catalogo: CATALOGO }, function (resp) {
    if (!resp.estatus) {
      Toast.fire({ icon: 'error', title: resp.mensaje });
      return;
    }
    modalRegistro(resp.data, id);
  }, 'json');
};
window.cancelarCatalogo = (id) => {
  Swal.fire({
    icon: 'question',
    title: '¿Desactivar este registro?',
    showCancelButton: true,
    confirmButtonText: 'Sí'
  }).then((r) => {
    if (!r.isConfirmed) return;
    $.post(BASE_URL + 'api/catalogos.php?tipo=cancelar&catalogo=' + CATALOGO, { id, catalogo: CATALOGO }, function (resp) {
      Toast.fire({ icon: resp.estatus ? 'success' : 'error', title: resp.mensaje });
      tabla.ajax.reload(false);
    }, 'json');
  });
};

$(document).ready(function () {
  const columns = [
    { data: 'id', title: '#' },
    { data: 'nombre', title: 'Nombre' }
  ];
  if (CATALOGO === 'unidades') {
    columns.push({ data: 'abreviatura', title: 'Abrev.' });
  }
  columns.push({ data: 'descripcion', title: 'Descripción' });
  columns.push({
    data: null, title: 'Estatus', render: (data) => data.estatus == 1
      ? '<span class="badge bg-success">Activo</span>'
      : '<span class="badge bg-secondary">Inactivo</span>'
  });
  columns.push({
    data: null, title: 'Opciones', render: function (data, type, row) {
      return `
        <div class="btn btn-primary btn-sm" onclick="editarCatalogo(${row.id})"><i class="fa-solid fa-pen-to-square"></i></div>
        <div class="btn btn-danger btn-sm" onclick="cancelarCatalogo(${row.id})"><i class="fa-solid fa-trash"></i></div>`;
    }
  });

  const catParam = encodeURIComponent(CATALOGO || 'categorias');
  tabla = initCustomDataTable('#tabla-catalogo', BASE_URL + 'api/catalogos.php?tipo=datatable&catalogo=' + catParam, columns);
});
