import { initReportTable } from '../utils/report-table.js';
import Toast from '../utils/toast.js';

let tabla; 

function modalAjuste(id_producto, nombreProducto, existenciaActual) {
  Swal.fire({
    title: 'Ajuste de Stock: ' + nombreProducto,
    html: `
      <div class="text-start">
        <div class="alert alert-info py-2">
          Stock actual en esta sucursal: <strong>${existenciaActual}</strong>
        </div>
        <div class="mb-3">
          <label class="form-label">Tipo de Movimiento</label>
          <select id="ajuste_tipo" class="form-select">
            <option value="entrada">Entrada (+) - Compra menor / Devolución / Sobrante</option>
            <option value="salida">Salida (-) - Merma / Daño / Uso interno de ferretería</option>
            <option value="ajuste_fijo">Conteo Físico (=) - Establecer cantidad exacta</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Cantidad *</label>
          <input id="ajuste_cantidad" type="number" step="0.01" class="form-control" placeholder="0">
        </div>
        <div class="mb-3">
          <label class="form-label">Motivo o Referencia *</label>
          <input id="ajuste_motivo" class="form-control" placeholder="Ej: Conteo físico mensual, merma en almacén">
        </div>
      </div>`,
    showCancelButton: true,
    confirmButtonText: 'Aplicar Ajuste',
    cancelButtonText: 'Cancelar',
    preConfirm: () => {
      const cantidad = parseFloat(document.getElementById('ajuste_cantidad').value);
      const motivo = document.getElementById('ajuste_motivo').value.trim();
      const tipo = document.getElementById('ajuste_tipo').value;

      if (isNaN(cantidad) || cantidad <= 0) {
        Swal.showValidationMessage('La cantidad debe ser mayor a 0');
        return false;
      }
      if (!motivo) {
        Swal.showValidationMessage('El motivo o referencia es obligatorio');
        return false;
      }

      return {
        id_producto,
        tipo,
        cantidad,
        motivo
      };
    }
  }).then((r) => {
    if (!r.isConfirmed) return;
    $.post(BASE_URL + 'api/inventario.php?tipo=ajustar', r.value, function (resp) {
      Toast.fire({ icon: resp.estatus ? 'success' : 'error', title: resp.mensaje });
      if (resp.estatus && tabla) {
        tabla.ajax.reload(false);
      }
    }, 'json');
  });
}

function modalKardex(id_producto, nombreProducto) {
  $.get(BASE_URL + 'api/inventario.php?tipo=movimientos&id_producto=' + id_producto, function (resp) {
    if (!resp.estatus) {
      Toast.fire({ icon: 'error', title: resp.mensaje });
      return;
    }

    let filas = '';
    if (!resp.data || resp.data.length === 0) {
      filas = '<tr><td colspan="6" class="text-center text-muted">Sin movimientos registrados</td></tr>';
    } else {
      resp.data.forEach((m) => {
        const badgeColor = m.tipo.includes('entrada') || m.tipo.includes('compra') ? 'bg-success' : (m.tipo.includes('salida') || m.tipo.includes('venta') ? 'bg-danger' : 'bg-primary');
        filas += `
          <tr>
            <td><small>${m.fecha_registro}</small></td>
            <td><span class="badge ${badgeColor}">${m.tipo}</span></td>
            <td>${parseFloat(m.cantidad).toFixed(2)}</td>
            <td>${parseFloat(m.existencia_antes).toFixed(2)}</td>
            <td><strong>${parseFloat(m.existencia_despues).toFixed(2)}</strong></td>
            <td><small>${m.referencia || '-'}</small></td>
          </tr>`;
      });
    }

    Swal.fire({
      title: 'Kardex: ' + nombreProducto,
      width: '800px',
      html: `
        <div class="table-responsive text-start" style="max-height: 380px;">
          <table class="table table-sm table-striped">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Cant.</th>
                <th>Antes</th>
                <th>Después</th>
                <th>Referencia</th>
              </tr>
            </thead>
            <tbody>
              ${filas}
            </tbody>
          </table>
        </div>`,
      confirmButtonText: 'Cerrar'
    });
  }, 'json');
}

window.ajustarStock = (id, nombre, stock) => modalAjuste(id, decodeURIComponent(nombre), stock);
window.verKardex = (id, nombre) => modalKardex(id, decodeURIComponent(nombre));

let categoriasList = [];
let marcasList = [];
let unidadesList = [];

function cargarCatalogosInventario() {
  $.get(BASE_URL + 'api/catalogos.php?tipo=combo&catalogo=categorias', (r) => {
    if (r.estatus) categoriasList = r.data;
  }, 'json');

  $.get(BASE_URL + 'api/catalogos.php?tipo=combo&catalogo=marcas', (r) => {
    if (r.estatus) marcasList = r.data;
  }, 'json');

  $.get(BASE_URL + 'api/catalogos.php?tipo=combo&catalogo=unidades', (r) => {
    if (r.estatus) unidadesList = r.data;
  }, 'json');
}

function generarOpcionesInventario(lista, seleccionado) {
  let opts = '<option value="">-- Seleccionar --</option>';
  lista.forEach((item) => {
    const sel = (item.id == seleccionado) ? 'selected' : '';
    opts += `<option value="${item.id}" ${sel}>${item.nombre} ${item.abreviatura ? '(' + item.abreviatura + ')' : ''}</option>`;
  });
  return opts;
}

function modalNuevoProductoInventario() {
  Swal.fire({
    title: 'Agregar Producto al Inventario',
    width: '700px',
    html: `
      <div class="text-start">
        <div class="row">
          <div class="col-md-5 mb-3">
            <label class="form-label">Código / SKU</label>
            <input id="inv_prod_codigo" class="form-control" placeholder="Ej: T-001">
          </div>
          <div class="col-md-7 mb-3">
            <label class="form-label">Nombre del producto *</label>
            <input id="inv_prod_nombre" class="form-control" placeholder="Ej: Taladro inalámbrico 18V">
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Categoría</label>
            <select id="inv_prod_categoria" class="form-select">
              ${generarOpcionesInventario(categoriasList, '')}
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Marca</label>
            <select id="inv_prod_marca" class="form-select">
              ${generarOpcionesInventario(marcasList, '')}
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Unidad</label>
            <select id="inv_prod_unidad" class="form-select">
              ${generarOpcionesInventario(unidadesList, '')}
            </select>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Precio de compra</label>
            <input id="inv_prod_compra" type="number" step="0.01" class="form-control" value="0" placeholder="0.00">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Precio de venta</label>
            <input id="inv_prod_venta" type="number" step="0.01" class="form-control" value="0" placeholder="0.00">
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Stock inicial</label>
            <input id="inv_prod_stock" type="number" step="0.01" class="form-control" value="0">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Stock mínimo</label>
            <input id="inv_prod_minimo" type="number" step="0.01" class="form-control" value="5">
          </div>
        </div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Guardar producto',
    cancelButtonText: 'Cancelar',
    preConfirm: () => {
      const nombre = document.getElementById('inv_prod_nombre').value.trim();
      const precioVenta = parseFloat(document.getElementById('inv_prod_venta').value);

      if (!nombre) {
        Swal.showValidationMessage('El nombre del producto es obligatorio');
        return false;
      }

      if (isNaN(precioVenta) || precioVenta < 0) {
        Swal.showValidationMessage('El precio de venta debe ser válido');
        return false;
      }

      return {
        codigo: document.getElementById('inv_prod_codigo').value.trim(),
        nombre,
        id_categoria: document.getElementById('inv_prod_categoria').value || null,
        id_marca: document.getElementById('inv_prod_marca').value || null,
        id_unidad: document.getElementById('inv_prod_unidad').value || null,
        precio_compra: parseFloat(document.getElementById('inv_prod_compra').value) || 0,
        precio_venta: precioVenta,
        stock_inicial: parseFloat(document.getElementById('inv_prod_stock').value) || 0,
        minimo: parseFloat(document.getElementById('inv_prod_minimo').value) || 5
      };
    }
  }).then((r) => {
    if (!r.isConfirmed) return;
    $.post(BASE_URL + 'api/productos.php?tipo=registrar', r.value, function (resp) {
      Toast.fire({ icon: resp.estatus ? 'success' : 'error', title: resp.mensaje });
      if (resp.estatus && tabla) {
        tabla.ajax.reload(false);
      }
    }, 'json');
  });
}

window.agregarProductoDesdeInventario = () => modalNuevoProductoInventario();

$(document).ready(function () {
  const columns = [
    { data: 'id_producto', title: '#' },
    { data: 'codigo', title: 'Código / SKU', render: (d) => d ? `<code>${d}</code>` : '<span class="text-muted">S/C</span>' },
    { data: 'producto', title: 'Producto' },
    { data: 'categoria', title: 'Categoría', render: (d) => d || '<span class="text-muted">-</span>' },
    { data: 'marca', title: 'Marca', render: (d) => d || '<span class="text-muted">-</span>' },
    {
      data: 'existencia',
      title: 'Existencia',
      render: (d, type, row) => {
        const stock = parseFloat(d || 0);
        const min = parseFloat(row.minimo || 5);
        const u = row.unidad || 'pza';
        if (stock <= 0) return `<span class="badge bg-danger">Agotado (0 ${u})</span>`;
        if (stock <= min) return `<span class="badge bg-warning text-dark">${stock} ${u} (Bajo)</span>`;
        return `<span class="badge bg-success">${stock} ${u}</span>`;
      }
    },
    { data: 'minimo', title: 'Mínimo', render: (d, type, row) => `${parseFloat(d || 5).toFixed(0)} ${row.unidad || ''}` },
    {
      data: null,
      title: 'Acciones',
      render: (data, type, row) => `
        <button class="btn btn-primary btn-sm" onclick="ajustarStock(${row.id_producto}, '${encodeURIComponent(row.producto)}', ${row.existencia})" title="Ajustar Stock">
          <i class="fa-solid fa-boxes-stacked me-1"></i> Ajustar
        </button>
        <button class="btn btn-secondary btn-sm" onclick="verKardex(${row.id_producto}, '${encodeURIComponent(row.producto)}')" title="Historial de Movimientos">
          <i class="fa-solid fa-clock-rotate-left"></i>
        </button>`
    }
  ];

  tabla = initReportTable({
    selector: '#tabla-inventario',
    module: 'inventario',
    columns,
    fields: [
      { name: 'codigo', label: 'Código / SKU', placeholder: 'Código' },
      { name: 'producto', label: 'Producto', placeholder: 'Nombre del producto' },
      { name: 'categoria', label: 'Categoría', type: 'select', endpoint: 'api/catalogos.php?tipo=combo&catalogo=categorias', liveSearch: true },
      { name: 'marca', label: 'Marca', type: 'select', endpoint: 'api/catalogos.php?tipo=combo&catalogo=marcas', liveSearch: true },
      { name: 'stock', label: 'Disponibilidad', type: 'select', options: [{ value: 'agotado', label: 'Agotado' }, { value: 'bajo', label: 'Stock bajo' }, { value: 'disponible', label: 'Disponible' }] }
    ]
  });
  cargarCatalogosInventario();
});
