import Toast from '../utils/toast.js';
import { abrirListaPagos  } from "./factura-pago.js";

function escaparHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  }[character]));
}

async function descargarArchivo(idVenta, formato) {
  const response = await fetch(`${BASE_URL}api/ventas.php?tipo=descargar_factura&id=${encodeURIComponent(idVenta)}&formato=${encodeURIComponent(formato)}`);
  const contentType = response.headers.get('content-type') || '';
  if (!response.ok || contentType.includes('application/json')) {
    const error = await response.json();
    throw new Error(error.mensaje || `No se pudo descargar el ${formato.toUpperCase()}`);
  }

  const archivo = await response.blob();
  const url = URL.createObjectURL(archivo);
  const link = document.createElement('a');
  link.href = url;
  link.download = `factura_${idVenta}.${formato}`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

async function cancelarFactura(idVenta) {
  const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

  const { isConfirmed, value } = await Swal.fire({
    icon: 'warning',
    title: 'Cancelar factura',
    html: `
      <p style="margin-bottom:12px">Se cancelará la factura ante el SAT. El ticket seguirá activo.</p>
      <select id="swal-motivo" class="swal2-select" style="width:100%;margin:0 0 12px">
        <option value="">Selecciona el motivo…</option>
        <option value="01" disabled>01 - Usa "Corregir / Sustituir CFDI"</option>
        <option value="02">02 - Emitida con errores, sin relación</option>
        <option value="03">03 - No se llevó a cabo la operación</option>
      </select>
      <input id="swal-uuid" class="swal2-input" style="display:none;width:100%;margin:0"
             placeholder="UUID de la factura sustituta" maxlength="36" autocomplete="off">
    `,
    focusConfirm: false,
    showCancelButton: true,
    confirmButtonText: 'Cancelar factura',
    cancelButtonText: 'Mejor no',
    confirmButtonColor: '#d33',
    showLoaderOnConfirm: true,
    allowOutsideClick: () => !Swal.isLoading(),
    didOpen: () => {
      const popup = Swal.getPopup();
      const motivo = popup.querySelector('#swal-motivo');
      const uuid = popup.querySelector('#swal-uuid');
      motivo.addEventListener('change', () => {
        uuid.style.display = motivo.value === '01' ? 'flex' : 'none';
        if (motivo.value !== '01') uuid.value = '';
      });
    },
    preConfirm: async () => {
      const popup = Swal.getPopup();
      const motivo = popup.querySelector('#swal-motivo').value;
      const uuid = popup.querySelector('#swal-uuid').value.trim().toLowerCase();

      if (!['01', '02', '03'].includes(motivo)) {
        Swal.showValidationMessage('Selecciona el motivo de cancelación');
        return false;
      }
      if (motivo === '01' && !UUID_RE.test(uuid)) {
        Swal.showValidationMessage('Captura un UUID válido de la factura sustituta');
        return false;
      }

      try {
        const response = await fetch(`${BASE_URL}api/ventas.php?tipo=cancelar_factura`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
          body: new URLSearchParams({
            id: String(idVenta),
            motivo,
            uuid_sustituto: motivo === '01' ? uuid : ''
          })
        });
        const result = await response.json();
        if (!response.ok || !result.estatus) {
          throw new Error(result.mensaje || 'No se pudo cancelar la factura');
        }
        return result;
      } catch (error) {
        Swal.showValidationMessage(error.message);
        return false;
      }
    }
  });

  if (!isConfirmed || !value) return;

  const pendiente = value.sat_status === 'pending';
  await Swal.fire({
    icon: pendiente ? 'info' : 'success',
    title: pendiente ? 'Cancelación en proceso' : 'Factura cancelada',
    text: value.mensaje
  });
  // aquí refresca tu tabla/vista
}

async function enviarCorreo(idVenta) {

   let correo = document.getElementById('factura-correo-input').value.trim();
   const asunto = document.getElementById('factura-asunto-input').value.trim();  
   const comentarios = document.getElementById('factura-comentarios-input').value.trim();


if (!correo || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
    // Muestra aquí tu alerta de correo inválido.
    Toast.fire({ icon: 'error', title: 'El correo ingresado no es válido' });
    return;
}
  
const confirmacion = await Swal.fire({
    icon: 'question',
    title: 'Enviar factura por correo',
    html: `Se enviará el PDF y XML al correo configurado:<br><strong>${escaparHtml(correo)}</strong>`,
    showCancelButton: true,
    confirmButtonText: 'Enviar factura',
    cancelButtonText: 'Cancelar'
  });
  if (!confirmacion.isConfirmed) return;

  Swal.fire({
    title: 'Enviando factura',
    text: 'Espera mientras el servicio realiza el envío.',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => Swal.showLoading()
  });

  try {
    const response = await fetch(`${BASE_URL}api/ventas.php?tipo=enviar_factura_correo`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body: new URLSearchParams({ id: String(idVenta), correo: String(correo), asunto: String(asunto), comentarios: String(comentarios) })
    });
    const result = await response.json();
    if (!response.ok || !result.estatus) throw new Error(result.mensaje || 'No se pudo enviar la factura');
    await Swal.fire({ icon: 'success', title: 'Factura enviada', text: result.mensaje || `Se envió al correo ${correo}` });
  } catch (error) {
    await Swal.fire({ icon: 'error', title: 'No se pudo enviar', text: error.message });
  }
}

const ESTILOS_ID = 'estilos-acciones-factura';

function inyectarEstilosAccionesFactura() {
  if (document.getElementById(ESTILOS_ID)) return;
  const style = document.createElement('style');
  style.id = ESTILOS_ID;
  style.textContent = `
    .swal-factura .swal2-title { font-size: 1.35rem; font-weight: 600; padding-top: 1.25rem; }
    .swal-factura .swal2-html-container { margin: 0; padding: 0 1.5rem 1rem; text-align: left; }
    .swal-factura .swal2-actions { margin-top: .5rem; }
    .swal-factura .swal2-footer { padding: 0 1.5rem 1rem; border: 0; display: block; }

    .fa-seccion { margin-top: 1.25rem; }
    .fa-seccion-titulo {
      font-size: .72rem; font-weight: 700; letter-spacing: .06em;
      text-transform: uppercase; color: #6c757d; margin-bottom: .6rem;
    }
    .fa-descarga {
      display: flex; flex-direction: column; align-items: center; gap: .35rem;
      padding: 1rem .75rem; border: 1px solid #dee2e6; border-radius: .6rem;
      background: #fff; width: 100%; transition: all .15s ease; font-weight: 500;
    }
    .fa-descarga i { font-size: 1.7rem; }
    .fa-descarga small { color: #6c757d; font-weight: 400; }
    .fa-descarga:hover:not(:disabled) {
      transform: translateY(-2px); box-shadow: 0 .4rem .8rem rgba(0,0,0,.08);
    }
    .fa-descarga.pdf:hover:not(:disabled) { border-color: #dc3545; }
    .fa-descarga.xml:hover:not(:disabled) { border-color: #0d6efd; }
    .fa-descarga.pdf i { color: #dc3545; }
    .fa-descarga.xml i { color: #0d6efd; }

    .fa-card {
      border: 1px solid #e9ecef; background: #f8f9fa;
      border-radius: .6rem; padding: 1rem;
    }
    .fa-peligro {
      border: 1px solid #f1aeb5; background: #fff5f5;
      border-radius: .6rem; padding: 1rem;
    }
    .fa-estado {
      display: inline-flex; align-items: center; gap: .4rem;
      background: #d1e7dd; color: #0f5132; border-radius: 2rem;
      padding: .25rem .75rem; font-size: .8rem; font-weight: 600;
    }
    .fa-badge-ppd {
      background: #fff3cd; color: #664d03; border-radius: 2rem;
      padding: .25rem .75rem; font-size: .8rem; font-weight: 600;
    }
    #factura-acciones-error:empty { display: none; }
  `;
  document.head.appendChild(style);
}

function mostrarErrorFactura(mensaje) {
  const cont = document.getElementById('factura-acciones-error');
  if (!cont) return;
  cont.innerHTML = `
    <div class="alert alert-danger d-flex align-items-start gap-2 py-2 mb-0 small" role="alert">
      <i class="fa-solid fa-circle-exclamation mt-1"></i>
      <div>${escaparHtml(mensaje)}</div>
    </div>`;
}

function limpiarErrorFactura() {
  const cont = document.getElementById('factura-acciones-error');
  if (cont) cont.innerHTML = '';
}

// Ejecuta una acción con spinner, bloqueo de botón y manejo de error
async function ejecutarAccionFactura(boton, accion) {
  if (!boton || boton.disabled) return;
  const htmlOriginal = boton.innerHTML;
  boton.disabled = true;
  boton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Procesando...';
  limpiarErrorFactura();
  try {
    await accion();
  } catch (error) {
    mostrarErrorFactura(error?.message || 'Ocurrió un error inesperado.');
  } finally {
    if (document.body.contains(boton)) {
      boton.disabled = false;
      boton.innerHTML = htmlOriginal;
    }
  }
}

/**
 * @param {number|string} idVenta
 * @param {string} correoConfigurado
 * @param {{ metodoPago?: 'PUE'|'PPD', saldoPendiente?: number }} opciones
 */
export function abrirAccionesFactura(idVenta, correoConfigurado = '', opciones = {}) {
  inyectarEstilosAccionesFactura();
 const metodo = opciones?.metodoPago
    ?? opciones?.data?.venta?.factura_metodo_pago
    ?? '';
  const esPPD = String(metodo).toUpperCase() === 'PPD';

  const bloquePago = esPPD ? `
    <div class="fa-seccion">
      <div class="fa-seccion-titulo">Pagos (método PPD)</div>
      <div class="fa-card">
       <p class="small text-muted mb-2">
  Consulta los complementos emitidos, descárgalos, envíalos o cancélalos, y emite uno nuevo por cada abono.
</p>
<button type="button" class="btn btn-primary w-100" id="accion-emitir-pago">
  <i class="fa-solid fa-money-check-dollar me-2"></i>Complementos de pago
</button>
      </div>
    </div>` : '';

  Swal.fire({
    title: 'Factura emitida',
    width: 620,
    buttonsStyling: false,
    showConfirmButton: true,
    confirmButtonText: 'Cerrar',
    customClass: {
      popup: 'swal-factura',
      confirmButton: 'btn btn-secondary px-4'
    },
    html: `
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="fa-estado"><i class="fa-solid fa-circle-check"></i>Timbrada correctamente</span>
        ${esPPD ? '<span class="fa-badge-ppd">PPD</span>' : ''}
      </div>

      <div class="fa-seccion">
        <div class="fa-seccion-titulo">Descargas</div>
        <div class="row g-2">
          <div class="col-6">
            <button type="button" class="fa-descarga pdf" id="accion-descargar-pdf">
              <i class="fa-solid fa-file-pdf"></i>
              <span>Descargar PDF</span>
              <small>Representación impresa</small>
            </button>
          </div>
          <div class="col-6">
            <button type="button" class="fa-descarga xml" id="accion-descargar-xml">
              <i class="fa-solid fa-file-code"></i>
              <span>Descargar XML</span>
              <small>Comprobante fiscal</small>
            </button>
          </div>
        </div>
      </div>

      <div class="fa-seccion">
        <div class="fa-seccion-titulo">Enviar por correo</div>
        <div class="fa-card">
          <div class="mb-3">
            <label for="factura-correo-input" class="form-label small fw-semibold mb-1">
              Destinatario <span class="text-danger">*</span>
            </label>
            <div class="input-group">
              <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
              <input type="email" class="form-control" id="factura-correo-input"
                     placeholder="correo@ejemplo.com" autocomplete="email"
                     value="${escaparHtml(correoConfigurado || '')}" required>
            </div>
          </div>

          <div class="mb-3">
            <label for="factura-asunto-input" class="form-label small fw-semibold mb-1">Asunto</label>
            <input type="text" class="form-control" id="factura-asunto-input"
                   value="Factura de su compra" maxlength="200" placeholder="Asunto del correo">
          </div>

          <div class="mb-3">
            <label for="factura-comentarios-input" class="form-label small fw-semibold mb-1">
              Comentarios <span class="text-muted fw-normal">(opcional)</span>
            </label>
            <textarea class="form-control" id="factura-comentarios-input" rows="3"
                      maxlength="1000" placeholder="Escribe un mensaje para el destinatario..."></textarea>
          </div>

          <button type="button" class="btn btn-success w-100" id="accion-enviar-factura">
            <i class="fa-solid fa-paper-plane me-2"></i>Enviar factura por correo
          </button>
        </div>
      </div>

      ${bloquePago}

      <div class="fa-seccion">
        <div class="fa-seccion-titulo">Correcciones</div>
        <button type="button" class="btn btn-outline-warning w-100" id="accion-sustituir-factura">
          <i class="fa-solid fa-arrows-rotate me-2"></i>Corregir / Sustituir CFDI
        </button>
      </div>

      <div class="fa-seccion">
        <div class="fa-peligro">
          <div class="fa-seccion-titulo text-danger">Zona de peligro</div>
          <button type="button" class="btn btn-outline-danger w-100" id="accion-cancelar-factura">
            <i class="fa-solid fa-ban me-2"></i>Cancelar factura
          </button>
        </div>
      </div>
    `,
    footer: '<div id="factura-acciones-error" class="w-100"></div>',
    didOpen: () => {
      const $ = (id) => document.getElementById(id);

      $('accion-descargar-pdf').addEventListener('click', (e) =>
        ejecutarAccionFactura(e.currentTarget, () => descargarArchivo(idVenta, 'pdf')));

      $('accion-descargar-xml').addEventListener('click', (e) =>
        ejecutarAccionFactura(e.currentTarget, () => descargarArchivo(idVenta, 'xml')));

      $('accion-enviar-factura').addEventListener('click', (e) => {
        const input = $('factura-correo-input');
        if (!input.value.trim() || !input.checkValidity()) {
          input.classList.add('is-invalid');
          input.focus();
          mostrarErrorFactura('Ingresa un correo electrónico válido.');
          return;
        }
        input.classList.remove('is-invalid');
        ejecutarAccionFactura(e.currentTarget, () => enviarCorreo(idVenta));
      });

      $('factura-correo-input').addEventListener('input', (e) => {
        e.target.classList.remove('is-invalid');
        limpiarErrorFactura();
      });

      const botonPago = $('accion-emitir-pago');
      if (botonPago) {
        botonPago.addEventListener('click', () => {
          Swal.close();
          abrirListaPagos(idVenta, opciones.onPagoEmitido);
        });
      }

      $('accion-sustituir-factura').addEventListener('click', () => {
        Swal.close();
        if (typeof window.sustituirFacturaVenta === 'function') window.sustituirFacturaVenta(idVenta);
      });

      $('accion-cancelar-factura').addEventListener('click', (e) =>
        ejecutarAccionFactura(e.currentTarget, () => cancelarFactura(idVenta)));
    }
  });
}