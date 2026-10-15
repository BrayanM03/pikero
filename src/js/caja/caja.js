import Toast from '../utils/toast.js';

let caja = null;

function moneda(value) { return `$${parseFloat(value || 0).toFixed(2)}`; }

function cargarCaja() {
  $.get(BASE_URL + 'api/caja.php?tipo=estado', (resp) => {
    caja = resp.data;
    $('#caja-estado').text(resp.abierta ? 'Caja abierta' : 'Caja cerrada').attr('class', `badge ${resp.abierta ? 'bg-success' : 'bg-secondary'}`);
    ['apertura', 'ventas', 'entradas', 'salidas'].forEach((key) => $('#caja-' + key).text(moneda(caja ? (key === 'apertura' ? caja.monto_apertura : key === 'ventas' ? caja.total_ventas : key === 'entradas' ? caja.total_entradas : caja.total_salidas) : 0)));
    $('#btn-abrir-caja').prop('disabled', resp.abierta);
    $('#btn-movimiento-caja, #btn-cerrar-caja').prop('disabled', !resp.abierta);
    cargarMovimientos();
  }, 'json');
}

function cargarMovimientos() {
  $.get(BASE_URL + 'api/caja.php?tipo=movimientos', (resp) => {
    const columns = [
      { data: 'fecha_registro', title: 'Fecha' }, { data: 'tipo', title: 'Tipo', render: (v) => `<span class="badge ${v === 'entrada' ? 'bg-success' : 'bg-danger'}">${v}</span>` },
      { data: 'monto', title: 'Monto', render: (v) => moneda(v) }, { data: 'motivo', title: 'Motivo' }, { data: 'usuario', title: 'Usuario' }
    ];
    if ($.fn.DataTable.isDataTable('#tabla-movimientos-caja')) $('#tabla-movimientos-caja').DataTable().clear().rows.add(resp.data || []).draw();
    else $('#tabla-movimientos-caja').DataTable({ data: resp.data || [], columns, responsive: true, language: { emptyTable: 'Sin movimientos', search: 'Buscar:', paginate: { next: 'Siguiente', previous: 'Anterior' } } });
  }, 'json');
}

function enviar(tipo, payload, callback) { $.post(BASE_URL + 'api/caja.php?tipo=' + tipo, payload, (resp) => { Toast.fire({ icon: resp.estatus ? 'success' : 'error', title: resp.mensaje }); if (resp.estatus) { callback?.(); cargarCaja(); } }, 'json'); }

$('#btn-abrir-caja').on('click', () => Swal.fire({ title: 'Abrir caja', input: 'number', inputLabel: 'Monto inicial', inputValue: 0, inputAttributes: { min: 0, step: '0.01' }, showCancelButton: true, confirmButtonText: 'Abrir' }).then((r) => { if (r.isConfirmed) enviar('abrir', { monto: r.value }); }));
$('#btn-movimiento-caja').on('click', () => Swal.fire({ title: 'Movimiento de caja', html: '<select id="mov_tipo" class="form-select mb-2"><option value="entrada">Entrada</option><option value="salida">Salida</option></select><input id="mov_monto" type="number" min="0.01" step="0.01" class="form-control mb-2" placeholder="Monto"><input id="mov_motivo" class="form-control" placeholder="Motivo">', showCancelButton: true, confirmButtonText: 'Registrar', preConfirm: () => ({ tipo_movimiento: $('#mov_tipo').val(), monto: $('#mov_monto').val(), motivo: $('#mov_motivo').val() }) }).then((r) => { if (r.isConfirmed) enviar('movimiento', r.value); }));
$('#btn-cerrar-caja').on('click', () => Swal.fire({ title: 'Cerrar caja', input: 'number', inputLabel: 'Monto contado', inputAttributes: { min: 0, step: '0.01' }, showCancelButton: true, confirmButtonText: 'Cerrar caja' }).then((r) => { if (r.isConfirmed) enviar('cerrar', { monto: r.value }); }));
$(document).ready(cargarCaja);