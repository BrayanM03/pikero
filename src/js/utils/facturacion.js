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

/** Vista previa orientativa; PHP es quien reconstruye y valida el CFDI. */
function calcularConcepto(item, fiscal = {}) {
  const cantidad = Number(item.cantidad);
  const precio = Number(item.precio);
  const descProducto = Number(item.descuento_producto ?? 0);
  const descGeneral = Number(item.descuento_general ?? 0);
  const importeTicket = Number(item.importe);
  const original = String(item.tipo_iva_facturacion ?? item.tipo_iva_venta ?? '').toLowerCase();
  const tipo = String(fiscal.tipo_iva ?? original).toLowerCase();
  const incluye = Number(item.precio_incluye_iva_facturacion ?? item.precio_venta_incluye_iva);
  const aplicarISR = fiscal.retencion_isr_resico === true;
  const objeto = tipo !== original
    ? (tipo === 'no_objeto' ? '01' : '02')
    : String(item.objeto_impuesto_facturacion ?? item.objeto_impuesto ?? '');

  if (![cantidad, precio, descProducto, descGeneral, importeTicket].every(Number.isFinite)
      || cantidad <= 0 || precio < 0 || descProducto < 0 || descGeneral < 0
      || importeTicket < 0 || ![0, 1].includes(incluye)) {
    throw new Error('Datos de precio o descuento incompletos');
  }
  if (!['0', '0.08', '0.16', 'exento', 'no_objeto', 'sin_traslado_iva'].includes(tipo)) {
    throw new Error('Tipo de IVA no soportado');
  }
  if (tipo === 'no_objeto' && objeto !== '01') throw new Error('ObjetoImp incompatible');
  if (tipo !== 'no_objeto' && objeto !== '02') throw new Error('ObjetoImp incompatible');
  if (tipo === 'sin_traslado_iva' && !aplicarISR) throw new Error('Sin traslado IVA requiere retención ISR');
  if (aplicarISR && objeto !== '02') {
    throw new Error('Para retener ISR el concepto debe ser objeto 02; selecciona IVA 0%, exento o tasa válida');
  }

  const tasa = ['exento', 'no_objeto', 'sin_traslado_iva'].includes(tipo) ? 0 : Number(tipo);
  const factor = 1 + tasa;
  const descuentoComercial = descProducto + descGeneral;
  const brutoTicket = Number(item.importe_bruto ?? (importeTicket + descuentoComercial));
  const precioFiscal = tipo !== original
    ? redondear(brutoTicket / cantidad / factor, 6)
    : redondear(incluye ? precio / factor : precio, 6);
  const subtotalFiscal = redondear(precioFiscal * cantidad, 6);
  const descuentoFiscal = redondear(descuentoComercial / factor, 6);
  if (descuentoFiscal > subtotalFiscal) throw new Error('Descuento mayor que el subtotal');
  const baseFiscal = redondear(subtotalFiscal - descuentoFiscal, 6);
  const iva = !['exento', 'no_objeto', 'sin_traslado_iva'].includes(tipo)
    ? redondear(baseFiscal * tasa, 6) : 0;
  const isr = aplicarISR ? redondear(baseFiscal * 0.0125, 6) : 0;
  const totalComercial = redondear(baseFiscal + iva, 2);
  const totalCfdi = redondear(baseFiscal + iva - isr, 2);
  if (aCentavos(totalComercial) !== aCentavos(importeTicket)) {
    throw new Error(`Importe comercial ${moneda(totalComercial)} ≠ ticket ${moneda(importeTicket)}`);
  }
  return { producto: item.producto, cantidad, precioFiscal, subtotalFiscal,
    descuentoFiscal, baseFiscal, iva, isr, totalComercial, totalCfdi,
    objeto, tipo, tasa, aplicarISR, heredado: Number(item.fiscal_heredado) === 1 };
}

function calcularFactura(items, venta, config = {}) {
  if (!items.length) throw new Error('La venta no contiene partidas');
  const partidas = items.map((it) => {
    try { return calcularConcepto(it, config[String(it.id)] || {}); }
    catch (e) { throw new Error(`${it.producto || 'Artículo'}: ${e.message}`); }
  });
  const subtotal = redondear(partidas.reduce((s, x) => s + x.subtotalFiscal, 0));
  const descuento = redondear(partidas.reduce((s, x) => s + x.descuentoFiscal, 0));
  const impuestosAgrupados = {};
  partidas.forEach(x => {
    if (x.objeto === '02' && !['exento', 'sin_traslado_iva'].includes(x.tipo)) {
      impuestosAgrupados[x.tipo] = (impuestosAgrupados[x.tipo] || 0) + x.iva;
    }
  });
  const iva = redondear(Object.values(impuestosAgrupados)
    .reduce((s, importe) => s + redondear(importe), 0));
  const isr = redondear(partidas.reduce((s, x) => s + x.isr, 0));
  const totalTicket = redondear(subtotal - descuento + iva);
  const totalCfdi = redondear(totalTicket - isr);
  if (aCentavos(totalTicket) !== aCentavos(venta.total)) {
    throw new Error(`Total comercial ${moneda(totalTicket)} diferente del ticket ${moneda(venta.total)}`);
  }
  const netoPartidas = partidas.reduce((s, x) => s + aCentavos(x.totalCfdi), 0);
  if (netoPartidas !== aCentavos(totalCfdi)) {
    throw new Error('La suma de las partidas difiere del total fiscal por redondeos');
  }
  return { partidas, subtotal, descuento, iva, isr, totalTicket, totalCfdi,
    tieneLegado: partidas.some(x => x.heredado) };
}

export function abrirFormularioFactura(idVenta, onSuccess = null, modo = 'normal') {
  const sustitucion = modo === 'sustitucion';
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
      if (sustitucion && (venta.estatus_factura !== 'Facturada' || !venta.uuid_factura)) {
        Swal.fire({icon:'warning',title:'Sustitución no disponible',text:'El ticket no tiene una factura vigente o existe un proceso pendiente.'});
        return;
      }
      if (!sustitucion && !['Pendiente','En proceso'].includes(venta.estatus_factura)) {
        Swal.fire({icon:'warning',title:'Factura ya existente',text:'Usa Sustituir CFDI o consulta el historial fiscal.'});
        return;
      }
      const configuracionPrevia = sustitucion ? (detail.data?.impuestos_cfdi_vigente || {}) : {};
      let calculo;
      try { calculo = calcularFactura(items, venta, configuracionPrevia); }
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
      const idEmisorUnico = sustitucion ? Number(venta.factura_issuer_id) :
        (emisores.length === 1 ? Number(emisores[0].issuer_id) : null);
      const selectorEmisor = sustitucion
        ? `<p class="mb-3">Emisor original (obligatorio): <strong>${escaparHtml(emisores.find(x=>Number(x.issuer_id)===idEmisorUnico)?.name || 'Emisor ' + idEmisorUnico)}</strong></p>`
        : emisores.length > 1
        ? `<div class="mb-3"><label class="form-label" for="fac_emisor">Emisor *</label><select id="fac_emisor" class="form-select"><option value="">Selecciona un emisor</option>${emisores.map((x) => `<option value="${Number(x.issuer_id)}">${escaparHtml(x.name)}${x.rfc ? ` (${escaparHtml(x.rfc)})` : ''}</option>`).join('')}</select></div>`
        : `<p class="mb-3">Emisor: <strong>${escaparHtml(emisores[0].name)}${emisores[0].rfc ? ` (${escaparHtml(emisores[0].rfc)})` : ''}</strong></p>`;

      const tasas = [
        ['no_objeto', 'No objeto'], ['exento', 'Exento'],
        ['sin_traslado_iva', 'Sin traslado IVA (solo ISR)'],
        ['0', 'IVA 0%'], ['0.08', 'IVA 8%'], ['0.16', 'IVA 16%']
      ];
      const lineasHtml = calculo.partidas.map((p, i) => {
        const it = items[i];
        const previo = configuracionPrevia[String(it.id)] || {};
        const sugerencia = Number(it.sugerir_retencion_isr_resico) === 1;
        const checked = previo.retencion_isr_resico === true ? 'checked' : '';
        const tasa = String(previo.tipo_iva ?? it.tipo_iva_facturacion ?? it.tipo_iva_venta);
        const opcionesIva = tasas.map(([val, label]) => `<option value="${val}" ${val === tasa ? 'selected' : ''}>${label}</option>`).join('');
        return `<tr><td>${escaparHtml(it.producto)}<br><small class="text-muted">${escaparHtml(it.codigo || 'S/C')}</small></td>
          <td class="text-end">${p.cantidad.toFixed(3)}</td>
          <td class="text-end"><span id="fac_punit_${Number(it.id)}">${moneda(p.precioFiscal)}</span></td>
          <td class="text-end"><span id="fac_desc_${Number(it.id)}">${moneda(p.descuentoFiscal)}</span></td>
          <td><select class="form-select form-select-sm fac_iva_partida" data-id="${Number(it.id)}">${opcionesIva}</select>
            <small id="fac_iva_importe_${Number(it.id)}">IVA: ${moneda(p.iva)}</small></td>
          <td class="text-center">
            <label class="small mb-0"><input type="checkbox" class="fac_isr_partida" data-id="${Number(it.id)}" ${checked}>
              ISR 1.25%${sugerencia ? ' (sugerido)' : ''}</label>
            <br><small id="fac_isr_importe_${Number(it.id)}">${moneda(p.isr)}</small>
          </td>
          <td class="text-end"><strong id="fac_total_linea_${Number(it.id)}">${moneda(p.totalCfdi)}</strong></td></tr>`;
      }).join('');
      const esCredito = venta.tipo_venta === 'credito' || Number(venta.saldo_pendiente || 0) > 0;
      const metodoSugerido = sustitucion ? (venta.factura_metodo_pago || 'PUE') : (esCredito ? 'PPD' : 'PUE');
      // venta.forma_pago es un nombre interno (por ejemplo "Efectivo"), NO clave SAT.
      const formaSugerida = sustitucion ? (venta.factura_forma_pago || '01') : (esCredito ? '99' : '01');

      Swal.fire({
        title: `${sustitucion ? 'Sustituir CFDI del ticket' : 'Facturar recibo'} ${escaparHtml(venta.folio)}`, width: '1000px',
        html: `<div class="text-start">
          ${selectorEmisor}
          ${sustitucion ? `<p class="alert alert-warning small">Se emitirá un CFDI nuevo con relación 04 al UUID <code>${escaparHtml(venta.uuid_factura)}</code>. Luego Pikero solicitará cancelar el anterior con motivo 01. El ticket y su total no cambian.</p>` : ''}
          <label class="form-label">Cliente del catálogo</label>
          <select id="fac_cliente" class="selectpicker w-100 mb-3" data-live-search="true"><option value="">Captura datos fiscales manualmente</option>${opcionesClientes}</select>
          <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">RFC *</label><input id="fac_rfc" class="form-control" placeholder="XAXX010101000"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Razón social *</label><input id="fac_razon" class="form-control"></div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3"><label class="form-label">Código postal fiscal *</label><input id="fac_cp" type="text" class="form-control" inputmode="numeric" maxlength="5" pattern="[0-9]{5}" placeholder="87454"></div>
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
            <th class="text-end">Descuento sin IVA</th><th>IVA aplicado</th><th>Retención ISR</th><th class="text-end">Total CFDI</th>
          </tr></thead><tbody>${lineasHtml}</tbody></table></div>
          <div class="ms-auto" style="max-width:340px">
            <div class="d-flex justify-content-between"><span>Subtotal sin IVA</span><strong id="fac_subtotal">${moneda(calculo.subtotal)}</strong></div>
            <div class="d-flex justify-content-between"><span>Descuento sin IVA</span><strong id="fac_descuento">-${moneda(calculo.descuento)}</strong></div>
            <div class="d-flex justify-content-between"><span>IVA trasladado</span><strong id="fac_impuesto">${moneda(calculo.iva)}</strong></div>
            <div class="d-flex justify-content-between"><span>ISR retenido</span><strong id="fac_isr_total">-${moneda(calculo.isr)}</strong></div>
            <div class="d-flex justify-content-between"><span>Total del ticket</span><strong id="fac_total_ticket">${moneda(calculo.totalTicket)}</strong></div>
            <div class="d-flex justify-content-between border-top mt-1 pt-1"><span>Total del CFDI</span><strong id="fac_total">${moneda(calculo.totalCfdi)}</strong></div>
            <p id="fac_aviso_retenido" class="small text-warning mt-2" style="display:none">El total fiscal será menor que el ticket por la retención. Pikero NO ajusta caja, abonos ni pagos automáticamente. Concilia lo efectivamente pagado.</p>
            <div id="fac_error_impuestos" class="small text-danger mt-2" style="display:none"></div>
          </div>
          ${calculo.tieneLegado ? '<p class="small text-warning mt-2">Aviso: este ticket es anterior a la migración. Se consultó la configuración fiscal actual del catálogo; revisa el tratamiento antes de timbrar.</p>' : ''}
        </div>`,
        showCancelButton: true, confirmButtonText: sustitucion ? 'Emitir sustituta' : 'Solicitar factura', cancelButtonText: 'Cancelar',
        didOpen: () => {
          inicializarSelectpicker(Swal.getPopup());
          const leerConfigImpuestos = () => {
            const config = {};
            items.forEach(it => {
              const id = String(it.id);
              config[id] = {
                tipo_iva: String($(`.fac_iva_partida[data-id="${id}"]`).val()),
                retencion_isr_resico: $(`.fac_isr_partida[data-id="${id}"]`).is(':checked')
              };
            });
            return config;
          };
          // Se expone solo dentro del popup, no se envían montos desde el navegador.
          Swal.getPopup().leerConfigImpuestos = leerConfigImpuestos;
          const actualizarVista = () => {
            try {
              const r = calcularFactura(items, venta, leerConfigImpuestos());
              r.partidas.forEach((p, i) => {
                const id = String(items[i].id);
                $(`#fac_punit_${id}`).text(moneda(p.precioFiscal));
                $(`#fac_desc_${id}`).text(moneda(p.descuentoFiscal));
                $(`#fac_iva_importe_${id}`).text(`IVA: ${moneda(p.iva)}`);
                $(`#fac_isr_importe_${id}`).text(moneda(p.isr));
                $(`#fac_total_linea_${id}`).text(moneda(p.totalCfdi));
              });
              $('#fac_subtotal').text(moneda(r.subtotal));
              $('#fac_descuento').text('-' + moneda(r.descuento));
              $('#fac_impuesto').text(moneda(r.iva));
              $('#fac_isr_total').text('-' + moneda(r.isr));
              $('#fac_total_ticket').text(moneda(r.totalTicket));
              $('#fac_total').text(moneda(r.totalCfdi));
              $('#fac_aviso_retenido').toggle(r.isr > 0);
              $('#fac_error_impuestos').text('').hide();
            } catch (e) {
              $('#fac_error_impuestos').text(e.message).show();
              $('#fac_total').text('Revisar impuestos');
            }
          };
          // Sugerencia automatica solo con emisor PF RESICO 626 y receptor PM.
          // PHP hace la validacion definitiva; la configuracion del producto no basta.
          const isrAjustadoManualmente = new Set();
          $(Swal.getPopup()).on('change', '.fac_isr_partida', function () {
            isrAjustadoManualmente.add(String($(this).data('id')));
          });
          const aplicarSugerencias = () => {
            if (sustitucion) return;
            const emisorId = idEmisorUnico ?? Number($('#fac_emisor').val());
            const emisor = emisores.find(x => Number(x.issuer_id) === emisorId) || {};
            const rfcEmisor = String(emisor.rfc ?? emisor.Rfc ?? '');
            const regimenEmisor = String(emisor.fiscal_regime ?? emisor.FiscalRegime ?? emisor.regimen_fiscal ?? '');
            const receptorEsPM = String($('#fac_rfc').val() || '').trim().toUpperCase().length === 12;
            const procede = rfcEmisor.length === 13 && regimenEmisor === '626' && receptorEsPM;
            items.forEach(it => {
              const id = String(it.id);
              if (isrAjustadoManualmente.has(id)) return;
              const sugerir = Number(it.sugerir_retencion_isr_resico) === 1;
              $(`.fac_isr_partida[data-id="${id}"]`).prop('checked', procede && sugerir);
            });
            actualizarVista();
          };
          $(Swal.getPopup()).on('change', '.fac_iva_partida, .fac_isr_partida', actualizarVista);
          $('#fac_rfc').on('blur change', aplicarSugerencias);
          $('#fac_emisor').on('change', aplicarSugerencias);
          actualizarVista();
          if (sustitucion) {
            $('#fac_rfc').val(venta.factura_rfc || '');
            $('#fac_razon').val(venta.factura_razon_social || '');
            $('#fac_cp').val(venta.factura_codigo_postal || '');
            $('#fac_regimen').selectpicker('val', venta.factura_regimen_fiscal || '');
            $('#fac_uso').selectpicker('val', venta.factura_uso_cfdi || '');
            $('#fac_correo').val(venta.factura_correo || '');
            $('#fac_metodo').selectpicker('val', venta.factura_metodo_pago || 'PUE');
            $('#fac_forma').selectpicker('val', venta.factura_forma_pago || '01');
            $('#fac_metodo, #fac_forma').prop('disabled', true).selectpicker('refresh');
          }
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
            aplicarSugerencias();
          });
        },
        preConfirm: () => {
          const issuerId = idEmisorUnico ?? Number($('#fac_emisor').val());
          if (!issuerId) { Swal.showValidationMessage('Selecciona un emisor.'); return false; }
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
          if (!/^[0-9]{5}$/.test(datos.codigo_postal)) { Swal.showValidationMessage('El CP debe tener 5 dígitos'); return false; }
          if (!datos.rfc || !datos.razon_social || !datos.codigo_postal ||
              !datos.regimen_fiscal || !datos.uso_cfdi) {
            Swal.showValidationMessage('Completa los datos fiscales obligatorios.'); return false;
          }
          datos.impuestos_partidas = Swal.getPopup().leerConfigImpuestos();
          let resultadoFiscal;
          try { resultadoFiscal = calcularFactura(items, venta, datos.impuestos_partidas); }
          catch (e) { Swal.showValidationMessage(e.message); return false; }
          if (resultadoFiscal.isr > 0 && datos.rfc.length !== 12) {
            Swal.showValidationMessage('La retención ISR RESICO del 1.25% requiere receptor persona moral (RFC de 12 caracteres).');
            return false;
          }
          // No se envían montos ni descuentos desde el navegador: PHP reconstruye la operación.
          return datos;
        }
      }).then((result) => {
        if (!result.isConfirmed) return;
        mostrarCargando(sustitucion ? 'Emitiendo CFDI sustituto' : 'Emitiendo factura', 'Esto puede tardar unos segundos...');
        $.ajax({ url: `${BASE_URL}api/ventas.php?tipo=${sustitucion ? 'sustituir_factura' : 'facturar'}`,  type: 'POST',
          contentType: 'application/json', data: JSON.stringify(result.value), dataType: 'json' })
          .done((response) => {
            if (!response.estatus) {
              Swal.fire({ icon: 'error', title: 'No se pudo emitir la factura', text: response.mensaje });
              return;
            }
            onSuccess?.(response);
            if (response.pendiente) {
              Swal.fire({icon:'info',title:'Sustituta emitida',text:response.mensaje || 'La cancelación de la original todavía está pendiente.'});
            } else {
              abrirAccionesFactura(idVenta, result.value.correo);
            }
          })
          .fail(() => Swal.fire({ icon: 'error', title: 'No fue posible emitir la factura',
            text: 'No sabemos si Facturama la timbró. Consulta el estado antes de reintentar.' }));
      });
    }).fail(() => Swal.fire({ icon: 'error', title: 'No se pudieron cargar los datos', text: 'Inténtalo más tarde.' }));
  }).fail(() => Swal.fire({ icon: 'error', title: 'No se pudieron consultar los emisores', text: 'Inténtalo más tarde.' }));
}
