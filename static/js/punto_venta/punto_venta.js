import Toast from '../utils/toast.js';
import { abrirFormularioFactura } from '../utils/facturacion.js';

let carrito = [];
let clientes = [];
let formasPago = [];
let searchTimeout = null;
let pagosCache = {}; // montos y referencias capturados por forma de pago

const round2 = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;
const centavos = (n) => Math.round(Number(n) * 100);
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

function obtenerTotalCarrito() {
  // Redondeo por partida, igual que el backend
  return round2(carrito.reduce((acc, it) => acc + round2(it.precio * it.cantidad), 0));
}

// Monto que se cobra ahora: el total en contado, o el abono inicial en crédito
function obtenerMontoACobrar() {
  if ($('#pos-tipo-venta').val() === 'credito') {
    return round2(parseFloat($('#pos-abono-inicial').val()) || 0);
  }
  return obtenerTotalCarrito();
}

function seleccionFormasPago() {
  return $('#pos-forma-pago option:selected').map(function () {
    return { id: Number($(this).val()), nombre: $(this).text().trim() };
  }).get();
}

function cargarCatalogos() {
  $.get(BASE_URL + 'api/punto_venta.php?tipo=catalogos', function (resp) {
    if (!resp.estatus) return;
    clientes = resp.data.clientes || [];
    formasPago = resp.data.formas_pago || [];

    const selectCli = document.getElementById('pos-cliente');
    selectCli.innerHTML = '';
    clientes.forEach((c) => {
      selectCli.innerHTML += `<option value="${c.id}" data-publico="${Number(c.es_publico) === 1 ? '1' : '0'}">${c.nombre} ${c.rfc ? '(' + c.rfc + ')' : ''}</option>`;
    });

    const selectFp = document.getElementById('pos-forma-pago');
    selectFp.innerHTML = '';
    formasPago.forEach((f) => {
      selectFp.innerHTML += `<option value="${f.id}">${f.nombre}</option>`;
    });
    if (formasPago.length) {
      $('#pos-forma-pago').val(String(formasPago[0].id));
    }
    $('#pos-forma-pago').selectpicker('refresh');
    setearFormaPago();
  }, 'json');
}

function renderCarrito() {
  const tbody = document.getElementById('pos-items-body');
  if (carrito.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="5" class="text-center py-5 text-muted">
          <i class="fa-solid fa-cart-arrow-down fa-3x mb-3 text-secondary opacity-50"></i>
          <p class="mb-0">El carrito está vacío. Escanea o busca productos para comenzar.</p>
        </td>
      </tr>`;
    actualizarTotales();
    return;
  }

  let html = '';
  carrito.forEach((item, idx) => {
    const importe = item.precio * item.cantidad;
    html += `
      <tr>
        <td>
          <div class="fw-bold">${item.nombre}</div>
          <small class="text-muted">SKU: ${item.codigo || 'S/C'} | Stock: ${item.stock || 0} ${item.unidad || 'pza'}</small>
        </td>
        <td class="text-center">
          <div class="input-group input-group-sm">
            <button class="btn btn-outline-secondary" type="button" onclick="modificarCantidad(${idx}, -1)">-</button>
            <input type="number" step="0.5" class="form-control text-center fw-bold px-1" value="${item.cantidad}" onchange="cambiarCantidadDirecta(${idx}, this.value)">
            <button class="btn btn-outline-secondary" type="button" onclick="modificarCantidad(${idx}, 1)">+</button>
          </div>
        </td>
        <td class="text-end">$${item.precio.toFixed(2)}</td>
        <td class="text-end fw-bold text-dark">$${importe.toFixed(2)}</td>
        <td class="text-center">
          <button class="btn btn-outline-danger btn-sm" onclick="eliminarDelCarrito(${idx})" title="Quitar">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </td>
      </tr>`;
  });

  tbody.innerHTML = html;
  actualizarTotales();
}

function actualizarTotales() {
  let subtotal = 0;
  let totalArticulos = 0;

  carrito.forEach((it) => {
    subtotal += it.precio * it.cantidad;
    totalArticulos += it.cantidad;
  });

  document.getElementById('pos-total-articulos').innerText = totalArticulos.toFixed(totalArticulos % 1 === 0 ? 0 : 2);
  document.getElementById('pos-subtotal').innerText = `$${subtotal.toFixed(2)}`;
  document.getElementById('pos-total').innerText = `$${subtotal.toFixed(2)}`;

  const tipoVenta = document.getElementById('pos-tipo-venta').value;
  const abonoInicial = document.getElementById('pos-abono-inicial');
  if (tipoVenta === 'credito' && parseFloat(abonoInicial.value || 0) > subtotal) {
    abonoInicial.value = subtotal.toFixed(2);
  }
  calcularCambio();
  actualizarRestantePagos();
}

function calcularCambio() {
  let total = 0;
  carrito.forEach((it) => { total += it.precio * it.cantidad; });

  const pagoConInput = document.getElementById('pos-pago-con');
  const pagoCon = parseFloat(pagoConInput.value) || 0;
  const cambio = pagoCon - total;

  const cambioSpan = document.getElementById('pos-cambio');
  if (pagoCon > 0) {
    if (cambio >= 0) {
      cambioSpan.className = 'h4 mb-0 fw-bold text-success';
      cambioSpan.innerText = `$${cambio.toFixed(2)}`;
    } else {
      cambioSpan.className = 'h4 mb-0 fw-bold text-danger';
      cambioSpan.innerText = `Faltan $${Math.abs(cambio).toFixed(2)}`;
    }
  } else {
    cambioSpan.className = 'h4 mb-0 fw-bold text-primary';
    cambioSpan.innerText = '$0.00';
  }
}

function agregarProductoAlCarrito(producto, cantidad = 1) {
  const stockDisponible = parseFloat(producto.stock || 0);
  const cantidadSolicitada = parseFloat(cantidad || 1);

  if (stockDisponible <= 0) {
    Toast.fire({
      icon: 'warning',
      title: 'Sin stock disponible',
      text: `${producto.nombre} no tiene existencias en este momento.`
    });
    return;
  }

  if (cantidadSolicitada > stockDisponible) {
    Toast.fire({
      icon: 'warning',
      title: 'Stock insuficiente',
      text: `Solo hay ${stockDisponible} ${producto.unidad || 'pza'} disponibles de ${producto.nombre}.`
    });
    return;
  }

  const index = carrito.findIndex((it) => it.id === producto.id);
  if (index >= 0) {
    const nuevaCantidad = carrito[index].cantidad + cantidadSolicitada;
    if (nuevaCantidad > stockDisponible) {
      Toast.fire({
        icon: 'warning',
        title: 'Stock insuficiente',
        text: `No puedes agregar más de ${stockDisponible} ${producto.unidad || 'pza'} de ${producto.nombre}.`
      });
      return;
    }
    carrito[index].cantidad = nuevaCantidad;
  } else {
    carrito.push({
      id: producto.id,
      codigo: producto.codigo,
      nombre: producto.nombre,
      precio: parseFloat(producto.precio_venta || 0),
      unidad: producto.unidad,
      stock: stockDisponible,
      cantidad: cantidadSolicitada
    });
  }

  // Limpiar buscador y resultados
  const buscador = document.getElementById('pos-buscador');
  buscador.value = '';
  document.getElementById('pos-resultados').style.display = 'none';
  buscador.focus();

  renderCarrito();
}

function guardarPagosCache() {
  $('.input-forma-pago').each(function () {
    const id = $(this).data('id');
    pagosCache[id] = pagosCache[id] || {};
    pagosCache[id].monto = $(this).val();
  });
  $('.input-referencia-pago').each(function () {
    const id = $(this).data('id');
    pagosCache[id] = pagosCache[id] || {};
    pagosCache[id].referencia = $(this).val();
  });
}

function limpiarPagosCapturados() {
  pagosCache = {};
  $('.input-forma-pago, .input-referencia-pago').val('');
  actualizarRestantePagos();
}

// El bloque de efectivo (paga con / cambio) solo aplica en contado con una sola forma de pago
function actualizarVisibilidadEfectivo(cantidadFormas = seleccionFormasPago().length) {
  const esCredito = $('#pos-tipo-venta').val() === 'credito';
  document.getElementById('bloque-efectivo').classList.toggle('d-none', esCredito || cantidadFormas > 1);
}

function actualizarRestantePagos() {
  const el = $('#pagos-restante');
  if (!el.length) return;

  let suma = 0;
  $('.input-forma-pago').each(function () { suma += parseFloat($(this).val()) || 0; });
  const restante = round2(obtenerMontoACobrar() - suma);

  if (restante === 0) {
    el.attr('class', 'mt-2 fw-bold text-success').text('Pagos completos');
  } else if (restante > 0) {
    el.attr('class', 'mt-2 fw-bold text-danger').text(`Faltan $${restante.toFixed(2)}`);
  } else {
    el.attr('class', 'mt-2 fw-bold text-danger').text(`Sobran $${Math.abs(restante).toFixed(2)}`);
  }
}

function setearFormaPago() {
  const seleccion = seleccionFormasPago();
  const colFormasPago = $('#col-formas-pago');
  const area = $('#area-formas-pago');

  guardarPagosCache(); // conservar lo capturado antes de redibujar
  area.empty();
  actualizarVisibilidadEfectivo(seleccion.length);

  // Con una sola forma de pago el monto es todo lo que se cobra: no hay nada que capturar
  if (seleccion.length < 2) {
    colFormasPago.addClass('d-none');
    return;
  }

  colFormasPago.removeClass('d-none');

  let html = '<label class="mb-2"><b>Montos por forma de pago</b></label><div class="row g-2">';
  seleccion.forEach((fp) => {
    const c = pagosCache[fp.id] || {};
    html += `
      <div class="col-12 col-md-6">
        <label class="small fw-bold">${esc(fp.nombre)}</label>
        <div class="input-group input-group-sm mb-1">
          <span class="input-group-text">$</span>
          <input type="number" min="0" step="0.01" class="form-control input-forma-pago" data-id="${fp.id}" value="${esc(c.monto ?? '')}" placeholder="0.00">
          <button type="button" class="btn btn-outline-secondary btn-completar-pago" data-id="${fp.id}" title="Completar con el restante">Resto</button>
        </div>
        <input type="text" maxlength="100" class="form-control form-control-sm input-referencia-pago" data-id="${fp.id}" value="${esc(c.referencia ?? '')}" placeholder="Referencia / autorización (opcional)">
      </div>`;
  });
  html += '</div><div id="pagos-restante" class="mt-2 fw-bold"></div>';

  area.html(html);
  actualizarRestantePagos();
}

// Devuelve { pagos } o { error }. montoACobrar = total (contado) o abono inicial (crédito)
function obtenerPagos(montoACobrar) {
  const seleccion = seleccionFormasPago();
  if (seleccion.length === 0) {
    return { error: 'Selecciona al menos una forma de pago' };
  }

  if (seleccion.length === 1) {
    return {
      pagos: [{ id_forma_pago: seleccion[0].id, nombre: seleccion[0].nombre, monto: montoACobrar, referencia: null }]
    };
  }

  const pagos = [];
  let suma = 0;
  seleccion.forEach((fp) => {
    const monto = round2(parseFloat($(`.input-forma-pago[data-id="${fp.id}"]`).val()) || 0);
    if (monto <= 0) return;
    const referencia = ($(`.input-referencia-pago[data-id="${fp.id}"]`).val() || '').trim();
    pagos.push({ id_forma_pago: fp.id, nombre: fp.nombre, monto, referencia: referencia || null });
    suma += monto;
  });

  if (pagos.length === 0) {
    return { error: 'Captura el monto de cada forma de pago' };
  }
  if (centavos(suma) !== centavos(montoACobrar)) {
    return { error: `La suma de los pagos ($${round2(suma).toFixed(2)}) debe ser igual a $${montoACobrar.toFixed(2)}` };
  }
  return { pagos };
}

function estilizarBordes(id, mensaje, estatus){
  if(!estatus){
    $(`#${id}`).css('border', '1px solid red')                 
    let hermano= $(`#${id}`).next()
    if (!hermano.is("small")) {
        hermano.removeClass('border-success').addClass('border-red')
      }
  }else{
    $(`#${id}`).css('border', '1px solid #59de64') 
    let hermano= $(`#${id}`).next()
    if (!hermano.is("small")) {
      hermano.removeClass('border-red').addClass('border-success')
    }
  }
  $(`#small_${id}`).text(mensaje)
  $(`#${id}`).selectpicker('refresh')
}

window.setearFormaPago = setearFormaPago;

window.modificarCantidad = (index, delta) => {
  if (!carrito[index]) return;
  const stockDisponible = parseFloat(carrito[index].stock || 0);
  const nuevaCantidad = carrito[index].cantidad + delta;

  if (nuevaCantidad <= 0) {
    carrito.splice(index, 1);
  } else if (nuevaCantidad > stockDisponible) {
    Toast.fire({
      icon: 'warning',
      title: 'Stock insuficiente',
      text: `Solo puedes vender ${stockDisponible} ${carrito[index].unidad || 'pza'} como máximo.`
    });
    carrito[index].cantidad = stockDisponible;
  } else {
    carrito[index].cantidad = nuevaCantidad;
  }
  renderCarrito();
};

window.cambiarCantidadDirecta = (index, valor) => {
  const v = parseFloat(valor);
  const stockDisponible = parseFloat(carrito[index].stock || 0);

  if (isNaN(v) || v <= 0) {
    carrito.splice(index, 1);
  } else if (v > stockDisponible) {
    Toast.fire({
      icon: 'warning',
      title: 'Stock insuficiente',
      text: `Solo hay ${stockDisponible} ${carrito[index].unidad || 'pza'} disponibles.`
    });
    carrito[index].cantidad = stockDisponible;
  } else {
    carrito[index].cantidad = v;
  }
  renderCarrito();
};

window.eliminarDelCarrito = (index) => {
  carrito.splice(index, 1);
  renderCarrito();
};

function buscarProductos(termino) {
  if (!termino || termino.trim().length === 0) {
    document.getElementById('pos-resultados').style.display = 'none';
    return;
  }

  $.get(BASE_URL + 'api/punto_venta.php?tipo=buscar&q=' + encodeURIComponent(termino), function (resp) {
    const contenedor = document.getElementById('pos-resultados');
    if (!resp.estatus || !resp.data || resp.data.length === 0) {
      contenedor.innerHTML = '<div class="list-group-item text-muted">No se encontraron productos coincidentes</div>';
      contenedor.style.display = 'block';
      return;
    }

    let html = '';
    resp.data.forEach((p) => {
      const sinStock = parseFloat(p.stock || 0) <= 0;
      html += `
        <a href="#" class="list-group-item list-group-item-action py-2 item-resultado-pos ${sinStock ? 'border-danger text-muted' : ''}" data-prod='${JSON.stringify(p)}' ${sinStock ? 'aria-disabled="true"' : ''}>
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <strong>${p.nombre}</strong>
              <div class="small ${sinStock ? 'text-danger' : 'text-muted'}">SKU: ${p.codigo || 'S/C'} | Stock: ${p.stock || 0} ${p.unidad || 'pza'}</div>
            </div>
            <div class="text-end">
              <span class="badge ${sinStock ? 'bg-danger' : 'bg-success'} fs-6">$${parseFloat(p.precio_venta).toFixed(2)}</span>
              ${sinStock ? '<div class="small text-danger mt-1">Sin stock</div>' : ''}
            </div>
          </div>
        </a>`;
    });

    contenedor.innerHTML = html;
    contenedor.style.display = 'block';

    $('.item-resultado-pos').on('click', function (e) {
      e.preventDefault();
      const p = $(this).data('prod');
      if ((parseFloat(p.stock || 0) <= 0)) {
        Toast.fire({ icon: 'warning', title: 'Sin stock', text: `${p.nombre} no tiene existencias disponibles.` });
        return;
      }
      agregarProductoAlCarrito(p);
    });
  }, 'json');
}

function procesarCobro() {
  if (carrito.length === 0) {
    Toast.fire({ icon: 'warning', title: 'Agrega al menos un producto al carrito' });
    return;
  }

  const total = obtenerTotalCarrito();

  const id_cliente = document.getElementById('pos-cliente').value;
  const tipo_venta = document.getElementById('pos-tipo-venta').value;
  const abono_inicial = tipo_venta === 'credito' ? round2(parseFloat(document.getElementById('pos-abono-inicial').value) || 0) : total;
  const clienteNombre = document.getElementById('pos-cliente').selectedOptions[0]?.text || 'Público en general';
  const fecha_venta = document.getElementById('pos-fecha').value;

  if (tipo_venta === 'credito' && (!id_cliente || $('#pos-cliente option:selected').data('publico') === 1)) {
    Toast.fire({ icon: 'warning', title: 'Selecciona un cliente registrado para vender a crédito' });
    return;
  }
  if (abono_inicial < 0 || abono_inicial > total) {
    Toast.fire({ icon: 'warning', title: 'El abono inicial debe estar entre cero y el total de la venta' });
    return;
  }

  // Pagos: una o varias formas de pago (contado o abono inicial de crédito)
  let pagos = [];
  if (abono_inicial > 0) {
    const resPagos = obtenerPagos(abono_inicial);
    if (resPagos.error) {
      Toast.fire({ icon: 'warning', title: resPagos.error });
      return;
    }
    pagos = resPagos.pagos;
  }
  const mixto = pagos.length > 1;

  // El cambio solo aplica con una forma de pago; con pagos mixtos los montos deben ser exactos
  const pagoCon = tipo_venta === 'credito'
    ? abono_inicial
    : (mixto ? total : (parseFloat(document.getElementById('pos-pago-con').value) || total));

  if (tipo_venta === 'contado' && centavos(pagoCon) < centavos(total)) {
    Swal.fire({
      icon: 'warning',
      title: 'Monto insuficiente',
      text: `El monto recibido ($${pagoCon.toFixed(2)}) es menor que el total de la venta ($${total.toFixed(2)})`
    });
    return;
  }

  const cambio = tipo_venta === 'contado' ? round2(pagoCon - total) : 0;

  const lineaPago = (p) => `${esc(p.nombre)}: $${p.monto.toFixed(2)}${p.referencia ? ' (ref. ' + esc(p.referencia) + ')' : ''}`;
  const desgloseConfirm = pagos.length ? pagos.map(lineaPago).join('<br>') : 'Pendiente de abono';
  const desgloseTicket = pagos.length
    ? pagos.map((p) => `<div class="d-flex justify-content-between"><span>${esc(p.nombre)}</span><span>$${p.monto.toFixed(2)}</span></div>`).join('')
    : '<div>Pendiente de abono</div>';

  Swal.fire({
    title: 'Confirmar Cobro',
    html: `
      <div class="text-start">
        <p><strong>Cliente:</strong> ${clienteNombre}</p>
        <p><strong>Tipo de venta:</strong> ${tipo_venta === 'credito' ? 'Crédito' : 'Contado'}</p>
        <p><strong>Forma de Pago:</strong><br>${desgloseConfirm}</p>
        <h3 class="text-center text-success my-3">Total: $${total.toFixed(2)}</h3>
        <p><strong>${tipo_venta === 'credito' ? 'Abono inicial:' : 'Paga con:'}</strong> $${pagoCon.toFixed(2)}</p>
        ${tipo_venta === 'credito' ? `<p><strong>Saldo pendiente:</strong> $${(total - pagoCon).toFixed(2)}</p>` : `<p><strong>Cambio:</strong> $${cambio.toFixed(2)}</p>`}
      </div>`,
    showCancelButton: true,
    confirmButtonText: 'Confirmar Venta',
    cancelButtonText: 'Regresar'
  }).then((r) => {
    if (!r.isConfirmed) return;

    const payload = {
      id_cliente,
      tipo_venta,
      abono_inicial,
      fecha_venta,
      items: carrito,
      pagos: pagos.map(({ id_forma_pago, monto, referencia }) => ({ id_forma_pago, monto, referencia }))
    };

    $.ajax({
      url: BASE_URL + 'api/punto_venta.php?tipo=cobrar',
      type: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(payload),
      dataType: 'json',
      success: function (resp) {
        if (!resp.estatus) {
          Swal.fire({ icon: 'error', title: 'Error al cobrar', text: resp.mensaje });
          return;
        }

        let itemsTicket = '';
        (resp.items || carrito).forEach((it) => {
          itemsTicket += `
            <tr>
              <td>${it.cantidad} x ${it.nombre}</td>
              <td class="text-end">$${(it.precio * it.cantidad).toFixed(2)}</td>
            </tr>`;
        });

        Swal.fire({
          icon: 'success',
          title: '¡Venta Realizada con Éxito!',
          html: `
            <div class="border p-3 rounded text-start" id="ticket-impresion" style="font-family: monospace; font-size: 13px;">
              <div class="text-center fw-bold">FERRETERÍA PIKERO</div>
              <div class="text-center text-muted">Ticket de Venta</div>
              <hr class="my-1">
              <div>Folio: <strong>${resp.folio}</strong></div>
              <div>Cliente: ${clienteNombre}</div>
              <div>Tipo de venta: ${tipo_venta === 'credito' ? 'Crédito' : 'Contado'}</div>
              <div>Forma de pago:</div>
              ${desgloseTicket}
              <hr class="my-1">
              <table class="w-100">
                ${itemsTicket}
              </table>
              <hr class="my-1">
              <div class="d-flex justify-content-between fw-bold fs-6">
                <span>TOTAL:</span>
                <span>$${parseFloat(resp.total).toFixed(2)}</span>
              </div>
              <div class="d-flex justify-content-between text-muted"><span>${tipo_venta === 'credito' ? 'Abono inicial:' : 'Recibido:'}</span><span>$${pagoCon.toFixed(2)}</span></div>
              ${tipo_venta === 'credito' ? `<div class="d-flex justify-content-between fw-bold"><span>Saldo pendiente:</span><span>$${(total - pagoCon).toFixed(2)}</span></div>` : `<div class="d-flex justify-content-between text-muted"><span>Cambio:</span><span>$${cambio.toFixed(2)}</span></div>`}
              <div class="text-center mt-3 text-muted">¡Gracias por su compra!</div>
            </div>`,
          confirmButtonText: '<i class="fa-solid fa-print me-1"></i> Imprimir y Continuar',
          showDenyButton: true,
          denyButtonText: '<i class="fa-solid fa-file-invoice me-1"></i> Facturar recibo',
          showCancelButton: true,
          cancelButtonText: 'Continuar sin imprimir'
        }).then((result) => {
          if (result.isDenied) {
            abrirFormularioFactura(resp.id_venta);
          } else if (result.isConfirmed) {

            if(resp.tipo_reporte === 1) {
            const printContent = document.getElementById('ticket-impresion').innerHTML;
            const printWindow = window.open('', '', 'width=400,height=600');
            printWindow.document.write(`<html><head><title>Ticket ${resp.folio}</title><style>body{font-family:monospace;padding:10px;}</style></head><body>${printContent}</body></html>`);
            printWindow.document.close();
            setTimeout(() => {
            printWindow.focus(); 
            printWindow.print();
            printWindow.close();
          }, 250);
            }else if(resp.tipo_reporte === 2) {
                window.open(BASE_URL + 'api/ventas.php?tipo=pdf&id=' + resp.id_venta, '_blank');
            }
          }

          // Reset carrito
          carrito = [];
          document.getElementById('pos-pago-con').value = '';
          limpiarPagosCapturados();
          renderCarrito();
        });
      },
      error: function () {
        Swal.fire({ icon: 'error', title: 'Error de conexión con el servidor' });
      }
    });
  });
}

$(document).ready(function () {
  cargarCatalogos();

  $('#pos-forma-pago').on('change', setearFormaPago);
  $(document).on('input', '.input-forma-pago', actualizarRestantePagos);
  $(document).on('click', '.btn-completar-pago', function () {
    const id = $(this).data('id');
    let otros = 0;
    $('.input-forma-pago').each(function () {
      if ($(this).data('id') !== id) otros += parseFloat($(this).val()) || 0;
    });
    const resto = Math.max(0, round2(obtenerMontoACobrar() - otros));
    $(`.input-forma-pago[data-id="${id}"]`).val(resto.toFixed(2));
    actualizarRestantePagos();
  });

  const buscador = document.getElementById('pos-buscador');

  buscador.addEventListener('input', function () {
    clearTimeout(searchTimeout);
    const val = this.value;
    searchTimeout = setTimeout(() => buscarProductos(val), 200);
  });

  buscador.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      const val = this.value.trim();
      if (!val) return;

      // Buscar coincidencia exacta por código o primer resultado
      $.get(BASE_URL + 'api/punto_venta.php?tipo=buscar&q=' + encodeURIComponent(val), function (resp) {
        if (resp.estatus && resp.data && resp.data.length > 0) {
          agregarProductoAlCarrito(resp.data[0]);
        } else {
          Toast.fire({ icon: 'warning', title: 'Producto no encontrado' });
        }
      }, 'json');
    }
  });

  document.getElementById('btn-buscar-pos').addEventListener('click', function () {
    buscarProductos(buscador.value);
  });

  document.getElementById('pos-pago-con').addEventListener('input', calcularCambio);
  document.getElementById('pos-tipo-venta').addEventListener('change', function() {
    const esCredito = this.value === 'credito';
    document.getElementById('bloque-abono-inicial').classList.toggle('d-none', !esCredito);
    actualizarVisibilidadEfectivo();
    if (esCredito && $('#pos-cliente option:selected').data('publico') === 1) {
      $('#pos-cliente').val(clientes.find((cliente) => Number(cliente.es_publico) !== 1)?.id || '');
    }
    actualizarTotales();
  });
  document.getElementById('pos-abono-inicial').addEventListener('input', actualizarTotales);
  document.getElementById('btn-cobrar-pos').addEventListener('click', procesarCobro);
  document.getElementById('btn-vaciar-carrito').addEventListener('click', function () {
    if (carrito.length === 0) return;
    Swal.fire({
      icon: 'question',
      title: '¿Vaciar carrito?',
      showCancelButton: true,
      confirmButtonText: 'Sí, vaciar',
      cancelButtonText: 'Cancelar'
    }).then((r) => {
      if (r.isConfirmed) {
        carrito = [];
        renderCarrito();
      }
    });
  });

  // Atajo F2 para cobrar
  document.addEventListener('keydown', function (e) {
    if (e.key === 'F2') {
      e.preventDefault();
      procesarCobro();
    }
  });

  // Ocultar resultados si se hace click fuera
  document.addEventListener('click', function (e) {
    if (!e.target.closest('#pos-buscador') && !e.target.closest('#pos-resultados')) {
      document.getElementById('pos-resultados').style.display = 'none';
    }
  });
});