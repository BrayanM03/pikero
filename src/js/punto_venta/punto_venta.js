import Toast from '../utils/toast.js';
import { abrirFormularioFactura } from '../utils/facturacion.js';

let carrito = [];
let clientes = [];
let formasPago = [];
let searchTimeout = null;

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

  let total = 0;
  carrito.forEach((it) => { total += it.precio * it.cantidad; });

  const id_cliente = document.getElementById('pos-cliente').value;
  const tipo_venta = document.getElementById('pos-tipo-venta').value;
  const abono_inicial = tipo_venta === 'credito' ? (parseFloat(document.getElementById('pos-abono-inicial').value) || 0) : total;
  const id_forma_pago = tipo_venta === 'credito' && abono_inicial === 0 ? '' : document.getElementById('pos-forma-pago').value;
  const clienteNombre = document.getElementById('pos-cliente').selectedOptions[0]?.text || 'Público en general';
  const formaPagoNombre = id_forma_pago ? (document.getElementById('pos-forma-pago').selectedOptions[0]?.text || 'Efectivo') : 'Pendiente de abono';
  const pagoCon = tipo_venta === 'credito' ? abono_inicial : (parseFloat(document.getElementById('pos-pago-con').value) || total);

  if (tipo_venta === 'contado' && pagoCon < total) {
    Swal.fire({
      icon: 'warning',
      title: 'Monto insuficiente',
      text: `El monto recibido ($${pagoCon.toFixed(2)}) es menor que el total de la venta ($${total.toFixed(2)})`
    });
    return;
  }
  if (tipo_venta === 'credito' && (!id_cliente || $('#pos-cliente option:selected').data('publico') === 1)) {
    Toast.fire({ icon: 'warning', title: 'Selecciona un cliente registrado para vender a crédito' });
    return;
  }
  if (abono_inicial < 0 || abono_inicial > total) {
    Toast.fire({ icon: 'warning', title: 'El abono inicial debe estar entre cero y el total de la venta' });
    return;
  }

  const cambio = tipo_venta === 'contado' ? pagoCon - total : 0;

  Swal.fire({
    title: 'Confirmar Cobro',
    html: `
      <div class="text-start">
        <p><strong>Cliente:</strong> ${clienteNombre}</p>
        <p><strong>Tipo de venta:</strong> ${tipo_venta === 'credito' ? 'Crédito' : 'Contado'}</p>
        <p><strong>Forma de Pago:</strong> ${formaPagoNombre}</p>
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
      id_forma_pago,
      tipo_venta,
      abono_inicial,
      items: carrito
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
              <div>Forma de pago: ${formaPagoNombre}</div>
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
            const printContent = document.getElementById('ticket-impresion').innerHTML;
            const printWindow = window.open('', '', 'width=400,height=600');
            printWindow.document.write(`<html><head><title>Ticket ${resp.folio}</title><style>body{font-family:monospace;padding:10px;}</style></head><body>${printContent}</body></html>`);
            printWindow.document.close();
           
            setTimeout(() => {
            printWindow.focus(); 
            printWindow.print();
            printWindow.close();
          }, 250);
          }

          // Reset carrito
          carrito = [];
          document.getElementById('pos-pago-con').value = '';
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
    document.getElementById('bloque-efectivo').classList.toggle('d-none', esCredito);
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
