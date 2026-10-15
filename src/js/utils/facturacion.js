import { cargarCatalogoSAT, inicializarSelectpicker, opcionesSAT } from './catalogos-sat.js';
import { abrirAccionesFactura } from './factura-actions.js';


function mostrarCargando(titulo, texto = 'Por favor espera...') {
  Swal.fire({
    title: titulo,
    text: texto,
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => Swal.showLoading()
  });
}

export function abrirFormularioFactura(idVenta, onSuccess = null) {
    mostrarCargando('Cargando', 'Obteniendo datos...');


  $.get(BASE_URL + 'api/ventas.php?tipo=emisores', null, null, 'json').done((issuerResponse) => {
    const emisores = issuerResponse.data || [];
    if (!issuerResponse.estatus || emisores.length === 0) {
      Swal.fire({ icon: 'info', title: 'Facturación no disponible', text: issuerResponse.mensaje || 'Esta empresa no tiene emisores asignados. Contacta al administrador.' });
      return;
    }

    $.when(
      $.get(BASE_URL + 'api/clientes.php?tipo=combo', null, null, 'json'),
      $.get(BASE_URL + `api/ventas.php?tipo=detalle&id=${encodeURIComponent(idVenta)}`, null, null, 'json')
    ).done(async (customerResult, detailResult) => {
      const resp = customerResult[0];
      const detail = detailResult[0];
      if (!detail.estatus) {
        Swal.fire({ icon: 'error', title: 'No se pudo cargar el detalle', text: detail.mensaje || 'Inténtalo de nuevo.' });
        return;
      }
      const clientes = resp.data || [];
      let regimenes;
      let usosCfdi;
      let formasPago;
      let metodosPago;
      try {
        [regimenes, usosCfdi, formasPago, metodosPago] = await Promise.all([
          cargarCatalogoSAT('FiscalRegimens'),
          cargarCatalogoSAT('CfdiUses'),
          cargarCatalogoSAT('PaymentForms'),
          cargarCatalogoSAT('PaymentMethods')
        ]);
      } catch (error) {
        Swal.fire({ icon: 'error', title: 'No se pudieron cargar los catálogos SAT', text: error.message });
        return;
      }
      const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
      const opciones = clientes.map((cliente) => `<option value="${Number(cliente.id)}">${escapeHtml(cliente.nombre)}${cliente.rfc ? ` (${escapeHtml(cliente.rfc)})` : ''}</option>`).join('');
      const items = detail.data.items || [];
      const selectedIssuerId = emisores.length === 1 ? Number(emisores[0].issuer_id) : null;
      const issuerControl = emisores.length > 1
        ? `<div class="mb-3"><label for="fac_emisor" class="form-label">Emisor *</label><select id="fac_emisor" class="form-select" required><option value="">Selecciona un emisor</option>${emisores.map((issuer) => `<option value="${Number(issuer.issuer_id)}">${escapeHtml(issuer.name)}${issuer.rfc ? ` (${escapeHtml(issuer.rfc)})` : ''}</option>`).join('')}</select></div>`
        : `<p class="mb-3">Emisor: <strong>${escapeHtml(emisores[0].name)}${emisores[0].rfc ? ` (${escapeHtml(emisores[0].rfc)})` : ''}</strong></p>`;
      const partidas = items.map((item) => `<tr>
        <td>${escapeHtml(item.producto)}<br><small class="text-muted">${escapeHtml(item.codigo || 'S/C')}</small></td>
        <td class="text-end">${Number(item.cantidad).toFixed(3)}</td>
        <td class="text-end">$${Number(item.precio).toFixed(2)}</td>
        <td class="text-end">$${Number(item.importe).toFixed(2)}</td>
      </tr>`).join('');

      Swal.fire({
        title: `Facturar recibo ${escapeHtml(detail.data.venta.folio)}`, width: '900px',
        html: `<div class="text-start">
          ${issuerControl}
          <label class="form-label">Cliente del catálogo</label>
          <select id="fac_cliente" class="selectpicker w-100 mb-3" data-live-search="true"><option value="">Captura datos fiscales manualmente</option>${opciones}</select>
          <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">RFC *</label><input id="fac_rfc" class="form-control" placeholder="XAXX010101000"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Razón social *</label><input id="fac_razon" class="form-control"></div>
          </div>
          <div class="row">
            <div class="col-md-3 mb-3"><label class="form-label">Código postal *</label><select id="fac_cp" class="selectpicker w-100" data-catalogo-sat="PostalCodes" data-live-search="true">${opcionesSAT([])}</select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Régimen fiscal *</label><select id="fac_regimen" class="selectpicker w-100" data-live-search="true">${opcionesSAT(regimenes)}</select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Uso CFDI *</label><select id="fac_uso" class="selectpicker w-100" data-live-search="true">${opcionesSAT(usosCfdi)}</select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Tasa IVA</label><select id="fac_iva" class="form-select"><option value="0">0%</option><option value="0.08">8%</option><option value="0.16" selected>16%</option></select></div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Correo (opcional)</label><input id="fac_correo" type="email" class="form-control"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Método de pago *</label><select id="fac_metodo" class="selectpicker w-100" data-live-search="true">${opcionesSAT(metodosPago, 'PUE')}</select></div>
            <div class="col-md-3 mb-3"><label class="form-label">Forma de pago *</label><select id="fac_forma" class="selectpicker w-100" data-live-search="true">${opcionesSAT(formasPago, '01')}</select></div>
          </div>
          <h6 class="mt-2">Partidas</h6>
          <div class="table-responsive" style="max-height:220px"><table class="table table-sm table-striped"><thead><tr><th>Producto</th><th class="text-end">Cantidad</th><th class="text-end">Precio unitario</th><th class="text-end">Importe</th></tr></thead><tbody>${partidas}</tbody></table></div>
          <div class="ms-auto" style="max-width:320px">
            <div class="d-flex justify-content-between"><span>Subtotal</span><strong id="fac_subtotal">$0.00</strong></div>
            <div class="d-flex justify-content-between"><span>IVA</span><strong id="fac_impuesto">$0.00</strong></div>
            <div class="d-flex justify-content-between border-top mt-1 pt-1"><span>Total</span><strong id="fac_total">$0.00</strong></div>
          </div>
        </div>`,
        showCancelButton: true, confirmButtonText: 'Solicitar factura', cancelButtonText: 'Cancelar',
        didOpen: () => {
          inicializarSelectpicker(Swal.getPopup());
          const recalcular = () => {
            const tasa = Number($('#fac_iva').val());
            const amounts = items.reduce((sum, item) => {
              const quantity = Number(item.cantidad || 0);
              const taxable = String(item.objeto_impuesto || '02') === '02';
              const unitPrice = Number(item.precio || 0);
              const netUnitPrice = taxable && tasa > 0 ? Math.round((unitPrice / (1 + tasa)) * 1e6) / 1e6 : unitPrice;
              const subtotal = Math.round(netUnitPrice * quantity * 100) / 100;
              return {
                subtotal: sum.subtotal + subtotal,
                iva: sum.iva + (taxable ? Math.round(subtotal * tasa * 100) / 100 : 0),
                total: sum.total + Number(item.importe || 0)
              };
            }, { subtotal: 0, iva: 0, total: 0 });
            $('#fac_subtotal').text(`$${amounts.subtotal.toFixed(2)}`);
            $('#fac_impuesto').text(`$${amounts.iva.toFixed(2)}`);
            $('#fac_total').text(`$${amounts.total.toFixed(2)}`);
          };
          $('#fac_iva').on('change', recalcular);
          $('#fac_cliente').on('change', function () {
            const cliente = clientes.find((item) => String(item.id) === String(this.value));
            if (!cliente) return;
            $('#fac_rfc').val(cliente.rfc || '');
            $('#fac_razon').val(cliente.razon_social || cliente.nombre || '');
            $('#fac_cp').selectpicker('val', cliente.codigo_postal || '');
            $('#fac_regimen').selectpicker('val', cliente.regimen_fiscal || '');
            $('#fac_uso').selectpicker('val', cliente.uso_cfdi || '');
            $('#fac_correo').val(cliente.correo || '');
          });
          recalcular();
        },
        preConfirm: () => {
          const issuerId = selectedIssuerId ?? Number($('#fac_emisor').val());
          if (!issuerId) { Swal.showValidationMessage('Selecciona un emisor para continuar.'); return false; }
          return { id: idVenta, issuer_id: issuerId, rfc: $('#fac_rfc').val().trim().toUpperCase(), razon_social: $('#fac_razon').val().trim(), codigo_postal: $('#fac_cp').val().trim(), regimen_fiscal: $('#fac_regimen').val().trim(), uso_cfdi: $('#fac_uso').val().trim(), correo: $('#fac_correo').val().trim(), metodo_pago: $('#fac_metodo').val(), forma_pago: $('#fac_forma').val().trim(), tasa_iva: $('#fac_iva').val() };
        }
      }).then((result) => {
        if (!result.isConfirmed) return;

        mostrarCargando('Emitiendo factura', 'Esto puede tardar unos segundos, no cierres la ventana...');

        $.ajax({ url: BASE_URL + 'api/ventas.php?tipo=facturar', type: 'POST', contentType: 'application/json', data: JSON.stringify(result.value), dataType: 'json' })
          .done((response) => {
            if (!response.estatus) {
              Swal.fire({ icon: 'error', title: 'No se pudo emitir la factura', text: response.mensaje });
              return;
            }
            onSuccess?.(response);
            abrirAccionesFactura(idVenta, result.value.correo);
          })
          .fail(() => Swal.fire({ icon: 'error', title: 'No fue posible emitir la factura', text: 'Inténtalo de nuevo o contacta al administrador.' }));
      });
    }).fail(() => Swal.fire({ icon: 'error', title: 'No se pudieron cargar los datos', text: 'Inténtalo más tarde.' }));
  }).fail(() => Swal.fire({ icon: 'error', title: 'No se pudieron consultar los emisores', text: 'Inténtalo más tarde o contacta al administrador.' }));
}