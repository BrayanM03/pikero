import { initReportTable } from '../utils/report-table.js';
import Toast from '../utils/toast.js';

let tabla;
let itemsCompra = [];
let timeoutBusqueda;

function cargarCatalogosCompra() {
  $.get(BASE_URL + 'api/proveedores.php?tipo=combo', (resp) => {
    const select = $('#compra-proveedor');
    select.html('<option value="">Selecciona un proveedor</option>');
    (resp.data || []).forEach((item) => select.append(`<option value="${item.id}">${item.nombre}</option>`));
  }, 'json');

  $.get(BASE_URL + 'api/catalogos.php?tipo=combo&catalogo=formas_pago', (resp) => {
    const select = $('#compra-forma-pago');
    select.html('<option value="">Selecciona una forma de pago</option>');
    (resp.data || []).forEach((item) => select.append(`<option value="${item.id}">${item.nombre}</option>`));
  }, 'json');
}

function renderItemsCompra() {
  const tbody = $('#tabla-items-compra tbody');
  if (!itemsCompra.length) {
    tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">Agrega productos a la compra.</td></tr>');
    $('#compra-total').text('$0.00');
    return;
  }

  let total = 0;
  const html = itemsCompra.map((item, index) => {
    const importe = item.cantidad * item.precio_compra;
    total += importe;
    return `<tr>
      <td><strong>${item.nombre}</strong></td>
      <td><code>${item.codigo || 'S/C'}</code></td>
      <td class="text-center"><input class="form-control form-control-sm compra-cantidad" type="number" min="0.001" step="0.001" value="${item.cantidad}" data-index="${index}"></td>
      <td class="text-end"><input class="form-control form-control-sm text-end compra-costo" type="number" min="0" step="0.01" value="${item.precio_compra.toFixed(2)}" data-index="${index}"></td>
      <td class="text-end fw-bold importe-compra" data-index="${index}">$${importe.toFixed(2)}</td>
      <td class="text-center"><button class="btn btn-outline-danger btn-sm quitar-item-compra" data-index="${index}" title="Eliminar"><i class="fa-solid fa-xmark"></i></button></td>
    </tr>`;
  }).join('');

  tbody.html(html);
  $('#compra-total').text(`$${total.toFixed(2)}`);
}

function actualizarTotalesCompra() {
  let total = 0;
  itemsCompra.forEach((item, index) => {
    const importe = item.cantidad * item.precio_compra;
    total += importe;
    $(`.importe-compra[data-index="${index}"]`).text(`$${importe.toFixed(2)}`);
  });
  $('#compra-total').text(`$${total.toFixed(2)}`);
}

function agregarItemCompra(producto) {
  const index = itemsCompra.findIndex((item) => item.id === producto.id);
  if (index >= 0) itemsCompra[index].cantidad += 1;
  else itemsCompra.push({ id: producto.id, codigo: producto.codigo, nombre: producto.nombre, cantidad: 1, precio_compra: parseFloat(producto.precio_compra || 0) });
  $('#compra-buscador').val('').focus();
  $('#compra-resultados').hide().empty();
  renderItemsCompra();
}

function buscarProductosCompra(termino) {
  if (!termino.trim()) {
    $('#compra-resultados').hide().empty();
    return;
  }
  $.get(BASE_URL + 'api/productos.php?tipo=buscar&q=' + encodeURIComponent(termino), (resp) => {
    const resultados = $('#compra-resultados');
    if (!resp.estatus || !(resp.data || []).length) {
      resultados.html('<div class="list-group-item text-muted">No se encontraron productos</div>').show();
      return;
    }
    resultados.html((resp.data || []).map((producto) => `<button type="button" class="list-group-item list-group-item-action resultado-producto-compra" data-producto='${JSON.stringify(producto)}'><strong>${producto.nombre}</strong><br><small>SKU: ${producto.codigo || 'S/C'} | Costo actual: $${parseFloat(producto.precio_compra || 0).toFixed(2)}</small></button>`).join('')).show();
  }, 'json');
}

function limpiarFormularioCompra() {
  itemsCompra = [];
  $('#compra-proveedor, #compra-forma-pago').val('');
  $('#compra-documento, #compra-observaciones').val('');
  $('#compra-fecha').val(new Date().toISOString().slice(0, 10));
  renderItemsCompra();
}

function guardarCompra() {
  if (!$('#compra-proveedor').val()) return Toast.fire({ icon: 'warning', title: 'Selecciona un proveedor' });
  if (!$('#compra-forma-pago').val()) return Toast.fire({ icon: 'warning', title: 'Selecciona una forma de pago' });
  if (!itemsCompra.length) return Toast.fire({ icon: 'warning', title: 'Agrega productos a la compra' });

  const payload = {
    id_proveedor: $('#compra-proveedor').val(),
    numero_documento: $('#compra-documento').val().trim(),
    fecha_compra: $('#compra-fecha').val(),
    id_forma_pago: $('#compra-forma-pago').val(),
    observaciones: $('#compra-observaciones').val().trim(),
    items: itemsCompra
  };

  $('#btn-guardar-compra').prop('disabled', true);
  $.ajax({ url: BASE_URL + 'api/compras.php?tipo=registrar', type: 'POST', contentType: 'application/json', data: JSON.stringify(payload), dataType: 'json' })
    .done((resp) => {
      Toast.fire({ icon: resp.estatus ? 'success' : 'error', title: resp.mensaje });
      if (resp.estatus) {
        limpiarFormularioCompra();
        $('#panel-nueva-compra').addClass('d-none');
        tabla.ajax.reload(null, false);
      }
    })
    .fail(() => Toast.fire({ icon: 'error', title: 'No se pudo comunicar con el servidor' }))
    .always(() => $('#btn-guardar-compra').prop('disabled', false));
}

function verDetalleCompra(id) {
  $.get(BASE_URL + 'api/compras.php?tipo=detalle&id=' + id, (resp) => {
    if (!resp.estatus) return Toast.fire({ icon: 'error', title: resp.mensaje });
    const compra = resp.data.compra;
    const rows = (resp.data.items || []).map((item) => `<tr><td>${item.producto}<br><small class="text-muted">SKU: ${item.codigo || 'S/C'}</small></td><td class="text-center">${parseFloat(item.cantidad).toFixed(2)} ${item.unidad || 'pza'}</td><td class="text-end">$${parseFloat(item.precio_unitario).toFixed(2)}</td><td class="text-end fw-bold">$${parseFloat(item.importe).toFixed(2)}</td></tr>`).join('');
    Swal.fire({
      title: 'Compra ' + compra.folio,
      width: '720px',
      html: `<div class="text-start"><p class="mb-1"><strong>Proveedor:</strong> ${compra.proveedor || 'N/D'}</p><p class="mb-1"><strong>Factura/Remisión:</strong> ${compra.numero_documento || 'N/D'}</p><p class="mb-1"><strong>Fecha:</strong> ${compra.fecha_compra} | <strong>Pago:</strong> ${compra.forma_pago || 'N/D'}</p><p><strong>Registró:</strong> ${compra.usuario || 'N/D'}</p><div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Producto</th><th>Cant.</th><th class="text-end">Costo</th><th class="text-end">Importe</th></tr></thead><tbody>${rows}</tbody></table></div><div class="d-flex justify-content-between h4 fw-bold"><span>Total:</span><span>$${parseFloat(compra.total).toFixed(2)}</span></div>${compra.observaciones ? `<p class="mt-2 mb-0"><strong>Observaciones:</strong> ${compra.observaciones}</p>` : ''}</div>`,
      confirmButtonText: 'Cerrar'
    });
  }, 'json');
}

window.verDetalleCompra = verDetalleCompra;

$(document).ready(function () {
  $('#compra-fecha').val(new Date().toISOString().slice(0, 10));
  cargarCatalogosCompra();
  $('#btn-nueva-compra').on('click', () => { limpiarFormularioCompra(); $('#panel-nueva-compra').removeClass('d-none'); $('#compra-proveedor').focus(); });
  $('#btn-cancelar-compra').on('click', () => $('#panel-nueva-compra').addClass('d-none'));
  $('#btn-guardar-compra').on('click', guardarCompra);
  $('#compra-buscador').on('input', function () { clearTimeout(timeoutBusqueda); timeoutBusqueda = setTimeout(() => buscarProductosCompra(this.value), 250); });
  $(document).on('click', '.resultado-producto-compra', function () { agregarItemCompra($(this).data('producto')); });
  $(document).on('input', '.compra-cantidad', function () {
    itemsCompra[$(this).data('index')].cantidad = Math.max(0.001, parseFloat(this.value) || 0.001);
    actualizarTotalesCompra();
  });
  $(document).on('input', '.compra-costo', function () {
    itemsCompra[$(this).data('index')].precio_compra = Math.max(0, parseFloat(this.value) || 0);
    actualizarTotalesCompra();
  });
  $(document).on('click', '.quitar-item-compra', function () { itemsCompra.splice($(this).data('index'), 1); renderItemsCompra(); });

  const columns = [
    { data: 'id', title: '#' },
    { data: 'folio', title: 'Folio', render: (value) => `<code>${value}</code>` },
    { data: 'numero_documento', title: 'Factura / Remisión', render: (value) => value || '-' },
    { data: 'fecha_compra', title: 'Fecha' },
    { data: 'proveedor', title: 'Proveedor' },
    { data: 'forma_pago', title: 'Forma de pago' },
    { data: 'total', title: 'Total', render: (value) => `<strong>$${parseFloat(value || 0).toFixed(2)}</strong>` },
    { data: 'usuario', title: 'Registró', render: (value) => value || 'N/D' },
    { data: null, title: 'Acciones', orderable: false, render: (data, type, row) => `<button class="btn btn-info btn-sm text-white" onclick="verDetalleCompra(${row.id})"><i class="fa-solid fa-eye me-1"></i> Detalle</button>` }
  ];
  tabla = initReportTable({
    selector: '#tabla-compras',
    module: 'compras',
    columns,
    fields: [
      { name: 'folio', label: 'Folio', placeholder: 'Folio' },
      { name: 'numero_documento', label: 'Factura / remisión', placeholder: 'Número de documento' },
      { name: 'proveedor', label: 'Proveedor', type: 'select', endpoint: 'api/proveedores.php?tipo=combo', liveSearch: true },
      { name: 'forma_pago', label: 'Forma de pago', type: 'select', endpoint: 'api/catalogos.php?tipo=combo&catalogo=formas_pago', liveSearch: true }
    ]
  });
});