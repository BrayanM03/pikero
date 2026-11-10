import { initCustomDataTable } from '../DataTable/datatables-init.js';
import Toast from '../utils/toast.js';
import { inicializarSelectpicker, opcionesSAT } from '../utils/catalogos-sat.js';

let tabla;
let categoriasList = [];
let marcasList = [];
let unidadesList = [];

function cargarCatalogos() {
  $.get(BASE_URL + 'api/catalogos.php?tipo=combo&catalogo=categorias', (r) => { if (r.estatus) categoriasList = r.data; }, 'json');
  $.get(BASE_URL + 'api/catalogos.php?tipo=combo&catalogo=marcas', (r) => { if (r.estatus) marcasList = r.data; }, 'json');
  $.get(BASE_URL + 'api/catalogos.php?tipo=combo&catalogo=unidades', (r) => { if (r.estatus) unidadesList = r.data; }, 'json');
}

function generarOpciones(lista, seleccionado) {
  let opts = '<option value="">-- Seleccionar --</option>';
  lista.forEach((item) => {
    const sel = (item.id == seleccionado) ? 'selected' : '';
    opts += `<option value="${item.id}" ${sel}>${item.nombre} ${item.abreviatura ? '(' + item.abreviatura + ')' : ''}</option>`;
  });
  return opts;
}

function modalProducto(values = {}, id = null) {
  Swal.fire({
    title: id ? 'Editar Producto' : 'Agregar Producto',
    width: '650px',
    html: `
      <div class="text-start">
        <div class="row">
          <div class="col-md-5 mb-3">
            <label class="form-label">Código de barras / SKU</label>
            <input id="prod_codigo" class="form-control" value="${values.codigo || ''}" placeholder="Ej: 750123456789">
          </div>
          <div class="col-md-7 mb-3">
            <label class="form-label">Nombre del Producto *</label>
            <input id="prod_nombre" class="form-control" value="${values.nombre || ''}" placeholder="Ej: Martillo de uña curva 16 oz">
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Clave SAT de producto o servicio</label>
            <select id="prod_clave_sat" class="selectpicker w-100" data-catalogo-sat="ProductsOrServices" data-live-search="true">${opcionesSAT([], values.clave_sat || '01010101')}</select>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Clave SAT de unidad</label>
            <select id="prod_codigo_unidad_sat" class="selectpicker w-100" data-catalogo-sat="Units" data-live-search="true">${opcionesSAT([], values.codigo_unidad_sat || 'H87')}</select>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Objeto de impuesto</label>
            <select id="prod_objeto_impuesto" class="selectpicker w-100" data-live-search="true">
              <option value="01" ${(values.objeto_impuesto || '02') === '01' ? 'selected' : ''}>01 · No objeto de impuesto</option>
              <option value="02" ${(values.objeto_impuesto || '02') === '02' ? 'selected' : ''}>02 · Sí objeto de impuesto</option>
              <option value="03" ${(values.objeto_impuesto || '02') === '03' ? 'selected' : ''}>03 · Sí objeto y no obligado al desglose</option>
              <option value="04" ${(values.objeto_impuesto || '02') === '04' ? 'selected' : ''}>04 · Sí objeto y no causa impuesto</option>
              <option value="05" ${(values.objeto_impuesto || '02') === '05' ? 'selected' : ''}>05 · Sí objeto, IVA crédito PODEBI</option>
              <option value="06" ${(values.objeto_impuesto || '02') === '06' ? 'selected' : ''}>06 · Sí objeto, no traslado de IVA</option>
              <option value="07" ${(values.objeto_impuesto || '02') === '07' ? 'selected' : ''}>07 · Sí objeto, no traslado de IVA ni IEPS</option>
              <option value="08" ${(values.objeto_impuesto || '02') === '08' ? 'selected' : ''}>08 · Sí objeto, no traslado de IVA y sí IEPS</option>
            </select>
          </div>
        </div>

        <div class="row mt-2">
          <div class="col-md-6 mb-3">
            <label class="form-label">Tratamiento IVA de venta</label>
            <select id="prod_tipo_iva_venta" class="form-select">
              ${[['0.16','IVA 16%'], ['0.08','IVA 8%'], ['0','IVA 0%'],
                ['exento','Exento'], ['no_objeto','No objeto']].map(([v,l]) =>
                `<option value="${v}" ${String(values.tipo_iva_venta ?? '0.16') === v ? 'selected' : ''}>${l}</option>`).join('')}
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Precio de venta</label>
            <select id="prod_precio_venta_incluye_iva" class="form-select">
              <option value="1" ${Number(values.precio_venta_incluye_iva ?? 1) === 1 ? 'selected' : ''}>IVA incluido</option>
              <option value="0" ${Number(values.precio_venta_incluye_iva ?? 1) === 0 ? 'selected' : ''}>IVA aparte</option>
            </select>
          </div>
          <div class="col-12 mb-3">
            <label class="form-check-label">
              <input id="prod_sugerir_ret_isr" class="form-check-input" type="checkbox"
                ${Number(values.sugerir_retencion_isr_resico ?? 0) === 1 ? 'checked' : ''}>
              Sugerir retención ISR RESICO 1.25% al facturar
            </label>
            <small class="d-block text-muted">Solo sugerencia: depende del emisor PF RESICO y receptor persona moral; no afecta el ticket.</small>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Categoría</label>
            <select id="prod_categoria" class="form-select">${generarOpciones(categoriasList, values.id_categoria)}</select>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Marca</label>
            <select id="prod_marca" class="form-select">${generarOpciones(marcasList, values.id_marca)}</select>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Unidad de Medida</label>
            <select id="prod_unidad" class="form-select">${generarOpciones(unidadesList, values.id_unidad)}</select>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Precio de Compra (Costo) $ *</label>
            <input id="prod_compra" type="number" step="0.01" class="form-control" value="${values.precio_compra ?? '0.00'}" placeholder="0.00">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Precio de Venta (Público) $ *</label>
            <input id="prod_venta" type="number" step="0.01" class="form-control" value="${values.precio_venta ?? '0.00'}" placeholder="0.00">
          </div>
        </div>

        ${!id ? `
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Stock Inicial</label>
            <input id="prod_stock_ini" type="number" step="0.01" class="form-control" value="0" placeholder="0">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Stock Mínimo (Alerta)</label>
            <input id="prod_minimo" type="number" step="0.01" class="form-control" value="5" placeholder="5">
          </div>
        </div>` : ''}
      </div>`,
    showCancelButton: true,
    didOpen: () => inicializarSelectpicker(Swal.getPopup()),
    confirmButtonText: id ? 'Actualizar' : 'Guardar',
    cancelButtonText: 'Cancelar',
    preConfirm: () => {
      const nombre = document.getElementById('prod_nombre').value.trim();
      const precioVenta = parseFloat(document.getElementById('prod_venta').value);

      if (!nombre) {
        Swal.showValidationMessage('El nombre del producto es obligatorio');
        return false;
      }
      if (isNaN(precioVenta) || precioVenta < 0) {
        Swal.showValidationMessage('El precio de venta debe ser un número válido');
        return false;
      }

      const tipoIvaProd = document.getElementById('prod_tipo_iva_venta').value;
      const objetoProd = document.getElementById('prod_objeto_impuesto').value;
      if ((tipoIvaProd === 'no_objeto' && objetoProd !== '01') ||
          (tipoIvaProd !== 'no_objeto' && objetoProd !== '02')) {
        Swal.showValidationMessage('Objeto 01 para no objeto, u objeto 02 para el IVA aplicable.');
        return false;
      }

      const payload = {
        id,
        codigo: document.getElementById('prod_codigo').value.trim(),
        clave_sat: document.getElementById('prod_clave_sat').value.trim(),
        codigo_unidad_sat: document.getElementById('prod_codigo_unidad_sat').value.trim(),
        objeto_impuesto: document.getElementById('prod_objeto_impuesto').value,
        tipo_iva_venta: document.getElementById('prod_tipo_iva_venta').value,
        precio_venta_incluye_iva: document.getElementById('prod_precio_venta_incluye_iva').value,
        sugerir_retencion_isr_resico: document.getElementById('prod_sugerir_ret_isr').checked ? 1 : 0,
        nombre,
        id_categoria: document.getElementById('prod_categoria').value,
        id_marca: document.getElementById('prod_marca').value,
        id_unidad: document.getElementById('prod_unidad').value,
        precio_compra: parseFloat(document.getElementById('prod_compra').value) || 0,
        precio_venta: precioVenta
      };

      if (!id) {
        payload.stock_inicial = parseFloat(document.getElementById('prod_stock_ini')?.value) || 0;
        payload.minimo = parseFloat(document.getElementById('prod_minimo')?.value) || 5;
      }

      return payload;
    }
  }).then((r) => {
    if (!r.isConfirmed) return;
    const tipo = id ? 'actualizar' : 'registrar';
    $.post(BASE_URL + 'api/productos.php?tipo=' + tipo, r.value, function (resp) {
      Toast.fire({ icon: resp.estatus ? 'success' : 'error', title: resp.mensaje });
      if (resp.estatus && tabla) {
        tabla.ajax.reload(false);
      }
    }, 'json');
  });
}

window.agregarProducto = () => modalProducto();
window.editarProducto = (id) => {
  $.post(BASE_URL + 'api/productos.php?tipo=obtener', { id }, function (resp) {
    if (resp.estatus) {
      modalProducto(resp.data, id);
    } else {
      Toast.fire({ icon: 'error', title: resp.mensaje });
    }
  }, 'json');
};

window.cancelarProducto = (id) => {
  Swal.fire({
    icon: 'question',
    title: '¿Desactivar este producto?',
    text: 'El producto no aparecerá disponible para ventas en el punto de venta.',
    showCancelButton: true,
    confirmButtonText: 'Sí, desactivar',
    cancelButtonText: 'Cancelar'
  }).then((r) => {
    if (!r.isConfirmed) return;
    $.post(BASE_URL + 'api/productos.php?tipo=cancelar', { id }, function (resp) {
      Toast.fire({ icon: resp.estatus ? 'success' : 'error', title: resp.mensaje });
      if (resp.estatus && tabla) {
        tabla.ajax.reload(false);
      }
    }, 'json');
  });
};

$(document).ready(function () {
  cargarCatalogos();

  const columns = [
    { data: 'id', title: '#' },
    { data: 'codigo', title: 'Código / SKU', render: (d) => d ? `<code>${d}</code>` : '<span class="text-muted">S/C</span>' },
    { data: 'nombre', title: 'Producto' },
    { data: 'categoria', title: 'Categoría', render: (d) => d || '<span class="text-muted">-</span>' },
    { data: 'marca', title: 'Marca', render: (d) => d || '<span class="text-muted">-</span>' },
    { data: 'precio_compra', title: 'P. Compra', render: (d) => `$${parseFloat(d || 0).toFixed(2)}` },
    { data: 'precio_venta', title: 'P. Venta', render: (d) => `<strong>$${parseFloat(d || 0).toFixed(2)}</strong>` },
    {
      data: 'stock_actual',
      title: 'Existencia',
      render: (d, type, row) => {
        const val = parseFloat(d || 0);
        const abrev = row.unidad_abrev || 'pza';
        if (val <= 0) return `<span class="badge bg-danger">Agotado (0 ${abrev})</span>`;
        if (val <= 5) return `<span class="badge bg-warning text-dark">${val} ${abrev}</span>`;
        return `<span class="badge bg-info text-dark">${val} ${abrev}</span>`;
      }
    },
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
        USER_PERMISSIONS.can_edit_productos ? `<button class="btn btn-primary btn-sm" onclick="editarProducto(${row.id})" title="Editar"><i class="fa-solid fa-pen-to-square"></i></button>` : '',
        USER_PERMISSIONS.can_cancel_productos ? `<button class="btn btn-danger btn-sm" onclick="cancelarProducto(${row.id})" title="Desactivar"><i class="fa-solid fa-trash"></i></button>` : ''
      ].join(' ') || '<span class="text-muted">-</span>'
    }
  ];

  tabla = initCustomDataTable('#tabla-productos', BASE_URL + 'api/productos.php?tipo=datatable', columns);
});
