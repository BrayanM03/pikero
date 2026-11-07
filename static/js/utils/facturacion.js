import { cargarCatalogoSAT, inicializarSelectpicker, opcionesSAT } from './catalogos-sat.js';
import { abrirAccionesFactura } from './factura-actions.js';

const escaparHtml = (valor) => String(valor ?? '').replace(/[&<>"']/g, (c) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[c]));
const redondear = (valor, decimales = 2) =>
  Math.round((Number(valor) + Number.EPSILON) * 10 ** decimales) / 10 ** decimales;
const aCentavos = (valor) => Math.round(Number(valor) * 100);
const moneda = (valor) => Number(valor).toLocaleString('es-MX', {
  style: 'currency', currency: 'MXN'
});

function mostrarCargando(titulo, texto = 'Por favor espera...') {
  Swal.fire({ title: titulo, text: texto, allowOutsideClick: false,
    allowEscapeKey: false, showConfirmButton: false,
    didOpen: () => Swal.showLoading() });
}

/** Vista previa: imita el algoritmo de Venta::facturar(). NO es cálculo fiscal autoritativo. */
function calcularConcepto(item) {
  const cantidad = Number(item.cantidad);
  const precio = Number(item.precio);
  const descProducto = Number(item.descuento_producto ?? 0);
  const descGeneral = Number(item.descuento_general ?? 0);
  const importeTicket = Number(item.importe);
  const objeto = String(item.objeto_impuesto_facturacion ?? item.objeto_impuesto ?? '');
  const tipo = String(item.tipo_iva_facturacion ?? item.tipo_iva_venta ?? '').toLowerCase();
  const incluye = Number(item.precio_incluye_iva_facturacion ?? item.precio_venta_incluye_iva);

  if (![cantidad, precio, descProducto, descGeneral, importeTicket].every(Number.isFinite)
      || cantidad <= 0 || precio < 0 || descProducto < 0 || descGeneral < 0
      || importeTicket < 0 || ![0, 1].includes(incluye)) {
    throw new Error('Datos de precio, cantidad o descuento incompletos');
  }
  if (!['01', '02'].includes(objeto)) throw new Error('Objeto de impuesto no soportado');
  if (tipo === 'no_objeto' && objeto !== '01') throw new Error('No objeto debe tener clave 01');
  if (tipo !== 'no_objeto' && objeto !== '02') throw new Error('Objeto de impuesto incompatible');
  if (!['0', '0.08', '0.16', 'exento', 'no_objeto'].includes(tipo)) {
    throw new Error('Tasa o tipo IVA no soportado');
  }

  const tasa = ['exento', 'no_objeto'].includes(tipo) ? 0 : Number(tipo);
  const factor = 1 + tasa;
  const precioFiscal = redondear(incluye ? precio / factor : precio, 6);
  const subtotalFiscal = redondear(precioFiscal * cantidad, 6);
  // Ambos descuentos son comerciales: IVA incluido incluso si el precio de catálogo no lo incluye.
  const descuentoFiscal = redondear((descProducto + descGeneral) / factor, 6);
  if (descuentoFiscal > subtotalFiscal) throw new Error('Descuento mayor al subtotal');
  const baseFiscal = redondear(subtotalFiscal - descuentoFiscal, 6);
  const impuesto = !['exento', 'no_objeto'].includes(tipo)
    ? redondear(baseFiscal * tasa, 6) : 0;
  const totalFiscal = redondear(baseFiscal + impuesto, 2);
  if (aCentavos(totalFiscal) !== aCentavos(importeTicket)) {
    throw new Error(`Importe fiscal ${moneda(totalFiscal)} ≠ ticket ${moneda(importeTicket)}`);
  }
  return { producto: item.producto, cantidad, precioFiscal, subtotalFiscal,
    descuentoFiscal, baseFiscal, impuesto, totalFiscal, objeto, tipo, tasa,
    heredado: Number(item.fiscal_heredado) === 1 };
}

function calcularFactura(items, venta) {
  if (!items.length) throw new Error('La venta no contiene partidas');
  const partidas = items.map((it) => {
    try { return calcularConcepto(it); }
    catch (e) { throw new Error(`${it.producto || 'Artículo'}: ${e.message}`); }
  });
  const subtotal = redondear(partidas.reduce((s, x) => s + x.subtotalFiscal, 0));
  const descuento = redondear(partidas.reduce((s, x) => s + x.descuentoFiscal, 0));
  // Agrupar traslados por tipo y tasa antes de redondear a 2 decimales.
  const impuestosAgrupados = {};
  partidas.forEach((x) => {
    if (x.objeto === '02' && x.tipo !== 'exento') {
      impuestosAgrupados[x.tipo] = (impuestosAgrupados[x.tipo] || 0) + x.impuesto;
    }
  });
  const iva = redondear(Object.values(impuestosAgrupados)
    .reduce((s, importe) => s + redondear(importe), 0));
  const total = redondear(subtotal - descuento + iva);
  if (aCentavos(total) !== aCentavos(venta.total)) {
    throw new Error(`Total fiscal ${moneda(total)} diferente del ticket ${moneda(venta.total)}`);
  }
  return { partidas, subtotal, descuento, iva, total,
    tieneLegado: partidas.some((x) => x.heredado) };
}

export function abrirFormularioFactura(idVenta, onSuccess = null) {
  mostrarCargando('Cargando', 'Obteniendo datos...');

  $.getJSON(`${BASE_URL}api/ventas.php?tipo=emisores`).done((issuerResponse) => {
    const emisores = issuerResponse.data || [];
    if (!issuerResponse.estatus || emisores.length === 0) {
      Swal.fire({ icon: 'info', title: 'Facturación no disponible',
        text: issuerResponse.mensaje || 'Esta empresa no tiene emisores asignados.' });
      return;
    }

    $.when(
      $.getJSON(`${BASE_URL}api/clientes.php?tipo=combo`),
      $.getJSON(`${BASE_URL}api/ventas.php?tipo=detalle&id=${encodeURIComponent(idVenta)}`)
    ).done(async (customerResult, detailResult) => {
      const resp = customerResult[0];
      const detail = detailResult[0];
      if (!resp.estatus || !detail.estatus) {
        Swal.fire({ icon: 'error', title: 'No se pudieron cargar los datos',
          text: detail.mensaje || resp.mensaje || 'Inténtalo nuevamente.' });
        return;
      }
      const clientes = resp.data || [];
      const items = detail.data?.items || [];
      const venta = detail.data?.venta || {};
      let calculo;
      try { calculo = calcularFactura(items, venta); }
      catch (e) {
        Swal.fire({ icon: 'error', title: 'No se puede previsualizar la factura', text: e.message });
        return;
      }
      let regimenes, usosCfdi, formasPago, metodosPago;
      try {
        [regimenes, usosCfdi, formasPago, metodosPago] = await Promise.all([
          cargarCatalogoSAT('FiscalRegimens'), cargarCatalogoSAT('CfdiUses'),
          cargarCatalogoSAT('PaymentForms'), cargarCatalogoSAT('PaymentMethods')
        ]);
      } catch (e) {
        Swal.fire({ icon: 'error', title: 'No se pudieron cargar los catálogos SAT', text: e.message });
        return;
      }

      const opcionesClientes = clientes.map((c) => `<option value="${Number(c.id)}">${escaparHtml(c.nombre)}${c.rfc ? ` (${escaparHtml(c.rfc)})` : ''}</option>`).join('');
      const idEmisorUnico = emisores.length === 1 ? Number(emisores[0].issuer_id) : null;
      const selectorEmisor = emisores.length > 1
        ? `<div class="mb-3"><label class="form-label" for="fac_emisor">Emisor *</label><select id="fac_emisor" class="form-select"><option value="">Selecciona un emisor</option>${emisores.map((x) => `<option value="${Number(x.issuer_id)}">${escaparHtml(x.name)}${x.rfc ? ` (${escaparHtml(x.rfc)})` : ''}</option>`).join('')}</select></div>`
        : `<p class="mb-3">Emisor: <strong>${escaparHtml(emisores[0].name)}${emisores[0].rfc ? ` (${escaparHtml(emisores[0].rfc)})` : ''}</strong></p>`;

      const lineasHtml = calculo.partidas.map((p, i) => {
        const tratamiento = p.tipo === 'exento' ? 'Exento' : p.tipo === 'no_objeto' ? 'No objeto' : `${p.tasa * 100}%`;
        return `<tr><td>${escaparHtml(items[i].producto)}<br><small class="text-muted">${escaparHtml(items[i].codigo || 'S/C')}</small></td>
          <td class="text-end">${p.cantidad.toFixed(3)}</td>
          <td class="text-end">${moneda(p.precioFiscal)}</td>
          <td class="text-end">${moneda(p.descuentoFiscal)}</td>
          <td class="text-end">${tratamiento}: ${moneda(p.impuesto)}</td>
          <td class="text-end">${moneda(p.totalFiscal)}</td></tr>`;
      }).join('');
      const esCredito = venta.tipo_venta === 'credito' || Number(venta.saldo_pendiente || 0) > 0;
      const metodoSugerido = esCredito ? 'PPD' : 'PUE';
      // venta.forma_pago es un nombre interno (por ejemplo "Efectivo"), NO clave SAT.
      const formaSugerida = esCredito ? '99' : '01';

      Swal.fire({
        title: `Facturar recibo ${escaparHtml(venta.folio)}`, width: '1000px',
        html: `<div class="text-start">
          ${selectorEmisor}
          <label class="form-label">Cliente del catálogo</label>
          <select id="fac_cliente" class="selectpicker w-100 mb-3" data-live-search="true"><option value="">Captura datos fiscales manualmente</option>${opcionesClientes}</select>
          <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">RFC *</label><input id="fac_rfc" class="form-control" placeholder="XAXX010101000"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Razón social *</label><input id="fac_razon" class="form-control"></div>
          </div>
          <div class="row">
            <div class="col-md-3 mb-3">
              <label for="fac_cp" class="form-label">
                Código postal fiscal *
              </label>
              <input
                id="fac_cp"
                type="text"
                class="form-control"
                inputmode="numeric"
                maxlength="5"
                pattern="[0-9]{5}"
                placeholder="Ej. 87454"
                autocomplete="postal-code"
              >
              <small id="fac_cp_estado" class="form-text text-muted">
                Captura el CP de tu domicilio fiscal.
              </small>
            </div>
            <div class="col-md-4 mb-3"><label class="form-label">Régimen fiscal *</label><select id="fac_regimen" class="selectpicker w-100" data-live-search="true">${opcionesSAT(regimenes)}</select></div>
            <div class="col-md-4 mb-3"><label class="form-label">Uso CFDI *</label><select id="fac_uso" class="selectpicker w-100" data-live-search="true">${opcionesSAT(usosCfdi)}</select></div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Correo (opcional)</label><input id="fac_correo" type="email" class="form-control"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Método de pago *</label><select id="fac_metodo" class="selectpicker w-100" data-live-search="true">${opcionesSAT(metodosPago, metodoSugerido)}</select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Forma de pago *</label><select id="fac_forma" class="selectpicker w-100" data-live-search="true">${opcionesSAT(formasPago, formaSugerida)}</select></div>
          </div>
          <h6 class="mt-2">Partidas (importes fiscales)</h6>
          <div class="table-responsive" style="max-height:230px"><table class="table table-sm table-striped"><thead><tr>
            <th>Producto</th><th class="text-end">Cantidad</th><th class="text-end">P. unitario sin IVA</th>
            <th class="text-end">Descuento sin IVA</th><th class="text-end">IVA</th><th class="text-end">Total</th>
          </tr></thead><tbody>${lineasHtml}</tbody></table></div>
          <div class="ms-auto" style="max-width:340px">
            <div class="d-flex justify-content-between"><span>Subtotal sin IVA</span><strong id="fac_subtotal">${moneda(calculo.subtotal)}</strong></div>
            <div class="d-flex justify-content-between"><span>Descuento sin IVA</span><strong id="fac_descuento">-${moneda(calculo.descuento)}</strong></div>
            <div class="d-flex justify-content-between"><span>IVA</span><strong id="fac_impuesto">${moneda(calculo.iva)}</strong></div>
            <div class="d-flex justify-content-between border-top mt-1 pt-1"><span>Total</span><strong id="fac_total">${moneda(calculo.total)}</strong></div>
          </div>
          ${calculo.tieneLegado ? '<p class="small text-warning mt-2">Aviso: este ticket es anterior a la migración. Se consultó la configuración fiscal actual del catálogo; revisa el tratamiento antes de timbrar.</p>' : ''}
        </div>`,
        showCancelButton: true, confirmButtonText: 'Solicitar factura', cancelButtonText: 'Cancelar',
        didOpen: () => {
          inicializarSelectpicker(Swal.getPopup());
          $('#fac_metodo').on('change', function () {
            if ($(this).val() === 'PPD') $('#fac_forma').selectpicker('val', '99');
            else if ($('#fac_forma').val() === '99') $('#fac_forma').selectpicker('val', '01');
          });
          $('#fac_cliente').on('change', function () {
            const c = clientes.find((x) => String(x.id) === String(this.value));
            if (!c) return;
            $('#fac_rfc').val(c.rfc || '');
            $('#fac_razon').val(c.razon_social || c.nombre || '');
            $('#fac_cp').val(c.codigo_postal || '');
            $('#fac_regimen').selectpicker('val', c.regimen_fiscal || '');
            $('#fac_uso').selectpicker('val', c.uso_cfdi || '');
            $('#fac_correo').val(c.correo || '');
          });
        },
        preConfirm: () => {
          const issuerId = idEmisorUnico ?? Number($('#fac_emisor').val());
          const cp = $('#fac_cp').val().trim();

          if (!issuerId) { Swal.showValidationMessage('Selecciona un emisor.'); return false; }
          if (!validarFormatoCP(cp)) {
              Swal.showValidationMessage(
                'El código postal debe contener exactamente 5 números.'
              );
              return false;
            }

          const metodo = $('#fac_metodo').val();
          const forma = $('#fac_forma').val();
          if (metodo === 'PPD' && forma !== '99') {
            Swal.showValidationMessage('Con PPD corresponde la forma de pago 99.'); return false;
          }
          if (metodo === 'PUE' && (!forma || forma === '99')) {
            Swal.showValidationMessage('Selecciona una forma de pago válida para PUE.'); return false;
          }
          const capturar = (id) => String($(id).val() ?? '').trim();
          const datos = {
            id: idVenta, issuer_id: issuerId,
            rfc: capturar('#fac_rfc').toUpperCase(), razon_social: capturar('#fac_razon'),
            codigo_postal: capturar('#fac_cp'), regimen_fiscal: capturar('#fac_regimen'),
            uso_cfdi: capturar('#fac_uso'), correo: capturar('#fac_correo'),
            metodo_pago: metodo, forma_pago: forma
          };
          if (!datos.rfc || !datos.razon_social || !datos.codigo_postal ||
              !datos.regimen_fiscal || !datos.uso_cfdi) {
            Swal.showValidationMessage('Completa los datos fiscales obligatorios.'); return false;
          }
          // No se envían importes, descuentos ni IVA al backend: PHP valida el ticket histórico.
          return datos;
        }
      }).then((result) => {
        if (!result.isConfirmed) return;
        mostrarCargando('Emitiendo factura', 'Esto puede tardar unos segundos...');
        $.ajax({ url: `${BASE_URL}api/ventas.php?tipo=facturar`, type: 'POST',
          contentType: 'application/json', data: JSON.stringify(result.value), dataType: 'json' })
          .done((response) => {
            if (!response.estatus) {
              Swal.fire({ icon: 'error', title: 'No se pudo emitir la factura', text: response.mensaje });
              return;
            }
            onSuccess?.(response);
            abrirAccionesFactura(idVenta, result.value.correo);
          })
          .fail(() => Swal.fire({ icon: 'error', title: 'No fue posible emitir la factura',
            text: 'No sabemos si Facturama la timbró. Consulta el estado antes de reintentar.' }));
      });
    }).fail(() => Swal.fire({ icon: 'error', title: 'No se pudieron cargar los datos', text: 'Inténtalo más tarde.' }));
  }).fail(() => Swal.fire({ icon: 'error', title: 'No se pudieron consultar los emisores', text: 'Inténtalo más tarde.' }));

   function validarFormatoCP(cp) {
              return /^\d{5}$/.test(cp);
            }
}
