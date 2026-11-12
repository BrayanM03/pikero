import Toast from '../utils/toast.js';

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

export function abrirAccionesFactura(idVenta, correoConfigurado = '') {
  Swal.fire({
    title: 'Factura emitida',
    
html: `
  <p class="mb-3">Selecciona una acción para la factura de esta venta.</p>

  <div class="d-grid gap-2">
    <button type="button" class="btn btn-outline-danger" id="accion-descargar-pdf">
      <i class="fa-solid fa-file-pdf me-2"></i>Descargar PDF
    </button>

    <button type="button" class="btn btn-outline-primary" id="accion-descargar-xml">
      <i class="fa-solid fa-file-code me-2"></i>Descargar XML
    </button>

    <hr class="my-2">

    <div class="text-start">
      <label for="factura-correo-input" class="form-label">
        <i class="fa-solid fa-envelope me-1"></i>Correo del destinatario
      </label>
      <input
        type="email"
        class="form-control"
        id="factura-correo-input"
        placeholder="correo@ejemplo.com"
        value="${escaparHtml(correoConfigurado || '')}"
        required
      >
    </div>

    <div class="text-start">
      <label for="factura-asunto-input" class="form-label">Asunto</label>
      <input
        type="text"
        class="form-control"
        id="factura-asunto-input"
        value="Factura de su compra"
        maxlength="200"
        placeholder="Asunto del correo"
      >
    </div>

    <div class="text-start">
      <label for="factura-comentarios-input" class="form-label">
        Comentarios <span class="text-muted">(opcional)</span>
      </label>
      <textarea
        class="form-control"
        id="factura-comentarios-input"
        rows="3"
        maxlength="1000"
        placeholder="Escribe un mensaje para el destinatario..."
      ></textarea>
      <small class="text-muted">
        Este mensaje acompañará el envío de la factura.
      </small>
    </div>

    <button type="button" class="btn btn-outline-success mt-1" id="accion-enviar-factura">
      <i class="fa-solid fa-paper-plane me-2"></i>Enviar factura por correo
    </button>
     <button type="button" class="btn btn-outline-warning mt-1" id="accion-sustituir-factura">
       <i class="fa-solid fa-arrows-rotate me-2"></i>Corregir / Sustituir CFDI
     </button>
     <button type="button" class="btn btn-outline-danger mt-1" id="accion-cancelar-factura">
      <i class="fa-solid fa-cancel me-2"></i>Cancelar Factura
    </button>
  </div>
`,

    footer: '<div id="factura-acciones-error" class="text-danger small mt-2"></div>',
    showConfirmButton: true,
    confirmButtonText: 'Cerrar',
    didOpen: () => {
      document.getElementById('accion-descargar-pdf').addEventListener('click', async () => {
        try {
          await descargarArchivo(idVenta, 'pdf');
        } catch (error) {
          document.getElementById('factura-acciones-error').textContent = error.message;
        }
      });
      document.getElementById('accion-descargar-xml').addEventListener('click', async () => {
        try {
          await descargarArchivo(idVenta, 'xml');
        } catch (error) {
          document.getElementById('factura-acciones-error').textContent = error.message;
        }
      });
       document.getElementById('accion-cancelar-factura').addEventListener('click', async () => {
        try {
          await cancelarFactura(idVenta);
        } catch (error) {
          document.getElementById('factura-acciones-error').textContent = error.message;
        }
      });
      const botonSustituir = document.getElementById('accion-sustituir-factura');
      if (botonSustituir) botonSustituir.addEventListener('click', () => {
        Swal.close();
        if (typeof window.sustituirFacturaVenta === 'function') window.sustituirFacturaVenta(idVenta);
      });
      const botonCorreo = document.getElementById('accion-enviar-factura');
      if (botonCorreo) botonCorreo.addEventListener('click', () => enviarCorreo(idVenta));
    }
  });
}
