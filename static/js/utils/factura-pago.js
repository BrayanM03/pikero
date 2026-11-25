import { cargarCatalogoSAT, opcionesSAT } from './catalogos-sat.js';
import Toast from './toast.js';

const esc = (v) =>
  String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const money = (n) => (Number(n) || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
const cents = (n) => Math.round(Number(n) * 100);
const aInputFecha = (s) => String(s || '').replace(' ', 'T').slice(0, 16);
const ahoraInput = () => {
  const d = new Date();
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
  return d.toISOString().slice(0, 16);
};

const cargando = (texto) =>
  Swal.fire({
    title: texto,
    allowEscapeKey: false,
    allowOutsideClick: false,
    showConfirmButton: false,
    didOpen: () => Swal.showLoading(),
  });

/**
 * Abre el formulario para emitir el complemento de pago (REP) de una factura PPD.
 * @param {number|string} idVenta
 * @param {Function|null} onSuccess recibe la respuesta del servidor
 */
export async function abrirFormularioPago(idVenta, onSuccess = null) {
  cargando('Cargando datos del pago...');
  try {
    const [r, formas] = await Promise.all([
      $.getJSON(`${BASE_URL}api/ventas.php?tipo=datos_pago&id=${encodeURIComponent(idVenta)}`),
      cargarCatalogoSAT('PaymentForms'),
    ]);
    if (!r.estatus) throw new Error(r.mensaje || 'No se pudieron cargar los datos del pago');

    const { factura, abonos_pendientes: abonos, en_proceso: enProceso } = r.data;
    const saldo = Number(factura.saldo_insoluto);

    if (enProceso?.length) {
      Swal.fire({
        icon: 'warning',
        title: 'Complemento en proceso',
        text: 'Hay un complemento sin confirmar. Vuelve a emitir el mismo abono para que se verifique antes de reenviar.',
      });
      // Se permite continuar: el backend solo deja reintentar el abono que quedó en proceso.
    }
    if (!(saldo > 0)) throw new Error('Esta factura ya no tiene saldo pendiente');
    if (!abonos.length && !enProceso?.length) {
      await Swal.fire({
        icon: 'info',
        title: 'Sin abonos pendientes',
        text: 'Todos los abonos de esta venta ya tienen complemento de pago. Registra un abono para emitir el siguiente.',
      });
      return;
    }

    const opcionesForma = opcionesSAT(
      formas.filter((x) => String(x.Value) !== '99'),
      '',
      'Forma de pago'
    );

    const listaAbonos = abonos
      .map(
        (a, i) => `
      <label class="d-flex align-items-center justify-content-between gap-2 border rounded p-2 mb-2" style="cursor:pointer">
        <span class="d-flex align-items-center gap-2">
          <input type="radio" name="pg_abono" class="form-check-input mt-0" value="${Number(a.id)}" ${i === 0 ? 'checked' : ''}>
          <span>
            <strong>${esc(String(a.fecha_registro).slice(0, 16))}</strong>
            <span class="text-muted small d-block">${esc(a.forma_pago || 'Sin forma de pago')} · REP a más tardar el ${esc(a.limite_rep || '—')}</span>
          </span>
        </span>
        <strong>${money(a.monto)}</strong>
      </label>`
      )
      .join('');

    const res = await Swal.fire({
      title: `Complemento de pago · ${esc(factura.folio)}`,
      width: 680,
      buttonsStyling: false,
      showCancelButton: true,
      confirmButtonText: 'Emitir complemento',
      cancelButtonText: 'Cancelar',
      customClass: { confirmButton: 'btn btn-primary px-4 me-2', cancelButton: 'btn btn-outline-secondary px-4' },
      html: `
        <div class="text-start">
          <div class="small text-muted mb-3">
            UUID ${esc(factura.uuid)}<br>
            Total CFDI <strong>${money(factura.total)}</strong> · Saldo pendiente <strong>${money(saldo)}</strong> ·
            Parcialidad <strong>${Number(factura.parcialidad_siguiente)}</strong>
          </div>

          <div class="small fw-bold text-uppercase text-muted mb-2">Abono a complementar</div>
          ${listaAbonos || '<p class="text-muted small">El abono en proceso se reintentará automáticamente.</p>'}

          <div class="row g-2 mt-1">
            <div class="col-md-6">
              <label class="form-label small fw-semibold mb-1" for="pg_fecha">Fecha de pago *</label>
              <input id="pg_fecha" type="datetime-local" class="form-control" max="${ahoraInput()}">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold mb-1" for="pg_forma">Forma de pago SAT *</label>
              <select id="pg_forma" class="form-select">${opcionesForma}</select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold mb-1" for="pg_monto">Monto *</label>
              <input id="pg_monto" type="number" step="0.01" min="0.01" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold mb-1">Saldo insoluto</label>
              <input id="pg_insoluto" class="form-control" readonly>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold mb-1" for="pg_operacion">No. de operación</label>
              <input id="pg_operacion" class="form-control" maxlength="100" placeholder="Opcional">
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold mb-1" for="pg_correo">Enviar al correo (opcional)</label>
              <input id="pg_correo" type="email" class="form-control" value="${esc(factura.correo || '')}">
            </div>
          </div>
          <div class="small text-muted mt-2">
            Parcialidad, saldo anterior e insoluto los calcula el servidor al timbrar.
          </div>
        </div>`,
      didOpen: () => {
        const popup = Swal.getPopup();
        const porId = Object.fromEntries(abonos.map((a) => [String(a.id), a]));
        const abonoSel = () => porId[popup.querySelector('input[name="pg_abono"]:checked')?.value];

        const precargar = () => {
          const a = abonoSel();
          if (!a) return;
          popup.querySelector('#pg_fecha').value = aInputFecha(a.fecha_registro);
          popup.querySelector('#pg_forma').value = a.forma_pago_sat_sugerida || '';
          const sugerido = Math.min(Number(a.monto), saldo);
          popup.querySelector('#pg_monto').value = sugerido.toFixed(2);
          actualizar();
        };
        const actualizar = () => {
          const m = Number(popup.querySelector('#pg_monto').value || 0);
          popup.querySelector('#pg_insoluto').value = money(Math.max(0, (cents(saldo) - cents(m)) / 100));
        };

        popup.querySelectorAll('input[name="pg_abono"]').forEach((el) => el.addEventListener('change', precargar));
        popup.querySelector('#pg_monto').addEventListener('input', actualizar);
        precargar();
        actualizar();
      },
      preConfirm: () => {
        const popup = Swal.getPopup();
        const v = (id) => String(popup.querySelector(id)?.value ?? '').trim();
        const abonoId = Number(popup.querySelector('input[name="pg_abono"]:checked')?.value || 0);
        const abono = abonos.find((a) => Number(a.id) === abonoId);
        const monto = Math.round(Number(v('#pg_monto')) * 100) / 100;

        if (!abonoId || !abono) return Swal.showValidationMessage('Selecciona el abono'), false;
        if (!v('#pg_fecha')) return Swal.showValidationMessage('Indica la fecha de pago'), false;
        if (!v('#pg_forma') || v('#pg_forma') === '99') return Swal.showValidationMessage('Selecciona una forma de pago válida'), false;
        if (!(monto > 0)) return Swal.showValidationMessage('Monto inválido'), false;
        if (cents(monto) > cents(saldo)) return Swal.showValidationMessage('El monto excede el saldo pendiente'), false;
        if (cents(monto) > cents(abono.monto)) return Swal.showValidationMessage('El monto no puede ser mayor al abono'), false;
        const correo = v('#pg_correo');
        if (correo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) return Swal.showValidationMessage('Correo inválido'), false;

        return {
          id: idVenta,
          abono_id: abonoId,
          fecha_pago: v('#pg_fecha'),
          forma_pago: v('#pg_forma'),
          monto,
          num_operacion: v('#pg_operacion'),
          correo,
        };
      },
    });
    if (!res.isConfirmed) return;

    cargando('Timbrando complemento de pago...');
    let out;
    try {
      out = await $.ajax({
        url: `${BASE_URL}api/ventas.php?tipo=emitir_pago`,
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(res.value),
        dataType: 'json',
      });
    } catch (e) {
      await Swal.fire({
        icon: 'warning',
        title: 'Resultado incierto',
        text: 'Consulta el estado antes de intentar de nuevo; si repites el mismo abono, el sistema verifica primero que no se haya timbrado.',
      });
      return;
    }
    if (!out.estatus) {
      await Swal.fire({ icon: 'error', title: 'No se emitió el complemento', text: out.mensaje || 'Revisa los datos' });
      return;
    }
    onSuccess?.(out);
    await Swal.fire({
      icon: 'success',
      title: 'Complemento emitido',
      html: `UUID <strong>${esc(out.uuid || '')}</strong><br>Parcialidad ${Number(out.parcialidad)} · Saldo insoluto ${money(out.saldo_insoluto)}${out.correo_enviado === false ? '<br><span class="text-warning small">No se pudo enviar el correo; reenvíalo desde Facturama.</span>' : ''}`,
    });
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'No se pudo emitir el pago', text: e.message || String(e) });
  }
}


// ----------------------------------------------------------------------
// Complementos emitidos: ver, descargar, enviar y cancelar
// ----------------------------------------------------------------------
const ESTADOS_PAGO = {
  emitido: ['success', 'Vigente'],
  cancelado: ['secondary', 'Cancelado'],
  cancelacion_pendiente: ['warning', 'Cancelación pendiente'],
  incierto: ['warning', 'Sin confirmar'],
  emitiendo: ['info', 'En proceso'],
};

async function descargarPago(idPago, formato) {
  const response = await fetch(
    `${BASE_URL}api/ventas.php?tipo=descargar_pago&id=${encodeURIComponent(idPago)}&formato=${encodeURIComponent(formato)}`
  );
  const contentType = response.headers.get('content-type') || '';
  if (!response.ok || contentType.includes('application/json')) {
    const error = await response.json();
    throw new Error(error.mensaje || `No se pudo descargar el ${formato.toUpperCase()}`);
  }
  const archivo = await response.blob();
  const url = URL.createObjectURL(archivo);
  const link = document.createElement('a');
  link.href = url;
  link.download = `complemento_pago_${idPago}.${formato}`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

async function postForm(tipo, datos) {
  const response = await fetch(`${BASE_URL}api/ventas.php?tipo=${tipo}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
    body: new URLSearchParams(Object.fromEntries(Object.entries(datos).map(([k, v]) => [k, String(v ?? '')]))),
  });
  const result = await response.json();
  if (!response.ok || !result.estatus) throw new Error(result.mensaje || 'No se pudo completar la acción');
  return result;
}

async function enviarComplemento(pago, correoSugerido) {
  const { isConfirmed, value } = await Swal.fire({
    title: `Enviar complemento · parcialidad ${Number(pago.parcialidad)}`,
    width: 520,
    buttonsStyling: false,
    showCancelButton: true,
    confirmButtonText: 'Enviar',
    cancelButtonText: 'Cancelar',
    showLoaderOnConfirm: true,
    allowOutsideClick: () => !Swal.isLoading(),
    customClass: { confirmButton: 'btn btn-success px-4 me-2', cancelButton: 'btn btn-outline-secondary px-4' },
    html: `
      <div class="text-start">
        <label class="form-label small fw-semibold mb-1" for="pgs_correo">Destinatario *</label>
        <input id="pgs_correo" type="email" class="form-control mb-2" value="${esc(pago.correo || correoSugerido || '')}" placeholder="correo@ejemplo.com">
        <label class="form-label small fw-semibold mb-1" for="pgs_asunto">Asunto</label>
        <input id="pgs_asunto" class="form-control mb-2" maxlength="200" value="Complemento de pago">
        <label class="form-label small fw-semibold mb-1" for="pgs_comentarios">Comentarios (opcional)</label>
        <textarea id="pgs_comentarios" class="form-control" rows="3" maxlength="1000"></textarea>
      </div>`,
    preConfirm: async () => {
      const popup = Swal.getPopup();
      const v = (id) => popup.querySelector(id).value.trim();
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v('#pgs_correo'))) {
        Swal.showValidationMessage('Ingresa un correo válido');
        return false;
      }
      try {
        return await postForm('enviar_pago', {
          id: pago.id,
          correo: v('#pgs_correo'),
          asunto: v('#pgs_asunto'),
          comentarios: v('#pgs_comentarios'),
        });
      } catch (e) {
        Swal.showValidationMessage(e.message);
        return false;
      }
    },
  });
  if (isConfirmed && value) Toast.fire({ icon: 'success', title: value.mensaje || 'Complemento enviado' });
  return isConfirmed;
}

async function cancelarComplemento(pago) {
  const { isConfirmed, value } = await Swal.fire({
    icon: 'warning',
    title: `Cancelar complemento · parcialidad ${Number(pago.parcialidad)}`,
    width: 520,
    buttonsStyling: false,
    showCancelButton: true,
    confirmButtonText: 'Cancelar complemento',
    cancelButtonText: 'Mejor no',
    showLoaderOnConfirm: true,
    allowOutsideClick: () => !Swal.isLoading(),
    customClass: { confirmButton: 'btn btn-danger px-4 me-2', cancelButton: 'btn btn-outline-secondary px-4' },
    html: `
      <div class="text-start">
        <p class="small mb-2">Se cancelará ante el SAT el complemento por <strong>${money(pago.monto)}</strong>.
        El abono quedará libre para emitir otro y el saldo de la factura se recalcula.</p>
        <select id="pgc_motivo" class="form-select">
          <option value="">Selecciona el motivo…</option>
          <option value="02">02 · Emitido con errores, sin relación</option>
          <option value="03">03 · No se llevó a cabo la operación</option>
        </select>
      </div>`,
    preConfirm: async () => {
      const motivo = Swal.getPopup().querySelector('#pgc_motivo').value;
      if (!motivo) {
        Swal.showValidationMessage('Selecciona el motivo de cancelación');
        return false;
      }
      try {
        return await postForm('cancelar_pago', { id: pago.id, motivo });
      } catch (e) {
        Swal.showValidationMessage(e.message);
        return false;
      }
    },
  });
  if (!isConfirmed || !value) return false;
  await Swal.fire({
    icon: value.sat_status === 'pending' ? 'info' : 'success',
    title: value.sat_status === 'pending' ? 'Cancelación en proceso' : 'Complemento cancelado',
    text: value.mensaje,
  });
  return true;
}

/**
 * Lista los complementos de pago de una factura PPD con acciones:
 * descargar PDF/XML, enviar por correo y cancelar (solo el más reciente).
 * @param {number|string} idVenta
 * @param {Function|null} onChange se llama tras emitir/cancelar para refrescar la vista
 */
export async function abrirListaPagos(idVenta, onChange = null) {
  cargando('Cargando complementos...');
  let r;
  try {
    r = await $.getJSON(`${BASE_URL}api/ventas.php?tipo=listar_pagos&id=${encodeURIComponent(idVenta)}`);
    if (!r.estatus) throw new Error(r.mensaje || 'No se pudieron cargar los complementos');
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'No se pudieron cargar los complementos', text: e.message || '' });
    return;
  }

  const { factura, pagos, hay_abonos_pendientes: hayAbonos } = r.data;
  const saldo = Number(factura.saldo_insoluto);
  const pagado = Math.max(0, (cents(factura.total) - cents(saldo)) / 100);
  const puedeEmitir = saldo > 0 && hayAbonos;

  const filas = pagos.length
    ? pagos
        .map((p) => {
          const [color, etiqueta] = ESTADOS_PAGO[p.estado] || ['secondary', p.estado];
          const tieneArchivo = !!p.uuid;
          const vigente = p.estado === 'emitido' || p.estado === 'cancelacion_pendiente';
          const botones = tieneArchivo
            ? `<div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-danger" data-pg-accion="pdf" data-id="${Number(p.id)}" title="Descargar PDF"><i class="fa-solid fa-file-pdf"></i></button>
                <button type="button" class="btn btn-outline-primary" data-pg-accion="xml" data-id="${Number(p.id)}" title="Descargar XML"><i class="fa-solid fa-file-code"></i></button>
                ${p.estado === 'cancelacion_pendiente' ? `<button type="button" class="btn btn-outline-warning" data-pg-accion="estatus" data-id="${Number(p.id)}" title="Actualizar estatus de cancelación"><i class="fa-solid fa-rotate"></i></button>` : ''}
                ${vigente ? `<button type="button" class="btn btn-outline-success" data-pg-accion="correo" data-id="${Number(p.id)}" title="Enviar por correo"><i class="fa-solid fa-paper-plane"></i></button>` : ''}
                ${p.puede_cancelar ? `<button type="button" class="btn btn-outline-danger" data-pg-accion="cancelar" data-id="${Number(p.id)}" title="Cancelar complemento"><i class="fa-solid fa-ban"></i></button>` : ''}
              </div>`
            : '<span class="text-muted small">Vuelve a emitir el mismo abono</span>';
          return `<tr class="${p.estado === 'cancelado' ? 'text-muted' : ''}">
            <td class="fw-semibold">${Number(p.parcialidad)}</td>
            <td>${esc(String(p.fecha_pago).slice(0, 10))}</td>
            <td>${esc(p.forma_pago_sat)}</td>
            <td class="text-end">${money(p.monto)}</td>
            <td><span class="badge bg-${color}${color === 'warning' ? ' text-dark' : ''}">${esc(etiqueta)}</span></td>
            <td><span title="${esc(p.uuid || '')}">${p.uuid ? esc(String(p.uuid).slice(0, 8)) + '…' : '—'}</span></td>
            <td class="text-end">${botones}</td>
          </tr>`;
        })
        .join('')
    : '<tr><td colspan="7" class="text-center text-muted py-3">Aún no hay complementos de pago para esta factura</td></tr>';

  const res = await Swal.fire({
    title: `Complementos de pago · ${esc(factura.folio)}`,
    width: 860,
    buttonsStyling: false,
    showConfirmButton: puedeEmitir,
    confirmButtonText: '<i class="fa-solid fa-plus me-1"></i>Emitir nuevo complemento',
    showCancelButton: true,
    cancelButtonText: 'Cerrar',
    customClass: { confirmButton: 'btn btn-primary px-4 me-2', cancelButton: 'btn btn-outline-secondary px-4' },
    html: `
      <div class="text-start">
        <div class="small text-muted mb-3">UUID ${esc(factura.uuid)}</div>
        <div class="row g-2 mb-3 text-center">
          <div class="col-4"><div class="border rounded p-2"><div class="small text-muted">Total CFDI</div><strong>${money(factura.total)}</strong></div></div>
          <div class="col-4"><div class="border rounded p-2"><div class="small text-muted">Complementado</div><strong>${money(pagado)}</strong></div></div>
          <div class="col-4"><div class="border rounded p-2"><div class="small text-muted">Saldo pendiente</div><strong>${money(saldo)}</strong></div></div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-2">
            <thead class="table-light"><tr>
              <th>#</th><th>Fecha pago</th><th>Forma</th><th class="text-end">Monto</th><th>Estado</th><th>UUID</th><th></th>
            </tr></thead>
            <tbody>${filas}</tbody>
          </table>
        </div>
        <div class="small text-muted">Solo se puede cancelar el complemento más reciente para mantener consecutivas las parcialidades.${!puedeEmitir && saldo > 0 ? ' No hay abonos pendientes de complemento.' : ''}</div>
      </div>`,
    didOpen: () => {
      Swal.getPopup().addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-pg-accion]');
        if (!btn || btn.disabled) return;
        const accion = btn.dataset.pgAccion;
        const pago = pagos.find((p) => Number(p.id) === Number(btn.dataset.id));
        if (!pago) return;

        if (accion === 'pdf' || accion === 'xml') {
          const html = btn.innerHTML;
          btn.disabled = true;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
          try {
            await descargarPago(pago.id, accion);
          } catch (err) {
            Toast.fire({ icon: 'error', title: err.message });
          } finally {
            btn.disabled = false;
            btn.innerHTML = html;
          }
          return;
        }

        if (accion === 'estatus') {
          btn.disabled = true;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
          try {
            const out = await postForm('estatus_pago', { id: pago.id });
            Toast.fire({ icon: out.cambio ? 'success' : 'info', title: out.mensaje });
            if (out.cambio) onChange?.();
          } catch (err) {
            Toast.fire({ icon: 'error', title: err.message });
          }
          Swal.close();
          abrirListaPagos(idVenta, onChange);
          return;
        }

        Swal.close();
        const cambio = accion === 'correo' ? await enviarComplemento(pago, factura.correo) : await cancelarComplemento(pago);
        if (cambio && accion === 'cancelar') onChange?.();
        abrirListaPagos(idVenta, onChange);
      });
    },
  });

  if (res.isConfirmed) {
    await abrirFormularioPago(idVenta, onChange);
    abrirListaPagos(idVenta, onChange);
  }
}