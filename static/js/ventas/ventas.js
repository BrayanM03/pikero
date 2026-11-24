import { initReportTable } from "../utils/report-table.js";
import Toast from "../utils/toast.js";
import { abrirFormularioFactura } from "../utils/facturacion.js";
import { abrirAccionesFactura } from "../utils/factura-actions.js";
import { tienePermiso } from "../utils/permisos.js";

let tabla;

function escaparHtml(valor) {
  return String(valor ?? "").replace(
    /[&<>"']/g,
    (caracter) =>
      ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;",
      })[caracter],
  );
}

function abrirModalAbono(idVenta, venta, formasPago) {
  const opciones = (formasPago || [])
    .map(
      (forma) =>
        `<option value="${Number(forma.id)}">${escaparHtml(forma.nombre)}</option>`,
    )
    .join("");
  Swal.fire({
    title: `Abonar a ${escaparHtml(venta.folio)}`,
    html: `<div class="text-start">
      <p class="mb-2">Saldo pendiente: <strong>$${parseFloat(venta.saldo_pendiente).toFixed(2)}</strong></p>
      <label class="form-label" for="venta-abono-monto">Monto del abono</label>
      <input id="venta-abono-monto" type="number" min="0.01" max="${parseFloat(venta.saldo_pendiente).toFixed(2)}" step="0.01" class="form-control mb-3" required>
      <label class="form-label" for="venta-abono-forma">Forma de pago</label>
      <select id="venta-abono-forma" class="form-select">${opciones}</select>
      <label class="form-label mt-3" for="venta-abono-nota">Observaciones</label>
      <input id="venta-abono-nota" class="form-control" maxlength="255" placeholder="Opcional">
    </div>`,
    showCancelButton: true,
    confirmButtonText: "Guardar abono",
    cancelButtonText: "Cancelar",
    preConfirm: () => {
      const monto = parseFloat(
        document.getElementById("venta-abono-monto").value,
      );
      const idFormaPago = document.getElementById("venta-abono-forma").value;
      if (
        !Number.isFinite(monto) ||
        monto <= 0 ||
        monto > parseFloat(venta.saldo_pendiente)
      ) {
        Swal.showValidationMessage(
          "El monto debe ser mayor a cero y no superar el saldo pendiente",
        );
        return false;
      }
      if (!idFormaPago) {
        Swal.showValidationMessage("Selecciona una forma de pago");
        return false;
      }
      return {
        monto,
        id_forma_pago: idFormaPago,
        observaciones: document.getElementById("venta-abono-nota").value,
      };
    },
  }).then((resultado) => {
    if (!resultado.isConfirmed) return;
    $.post(
      `${BASE_URL}api/ventas.php?tipo=abonar`,
      { id: idVenta, ...resultado.value },
      (respuesta) => {
        Toast.fire({
          icon: respuesta.estatus ? "success" : "error",
          title: respuesta.mensaje,
        });
        if (respuesta.estatus) verDetalleVenta(idVenta);
        if (tabla) tabla.ajax.reload(null, false);
      },
      "json",
    );
  });
}

function verDetalleVenta(id_venta) {
  $.get(
    BASE_URL + "api/ventas.php?tipo=detalle&id=" + id_venta,
    function (resp) {
      if (!resp.estatus) {
        Toast.fire({ icon: "error", title: resp.mensaje });
        return;
      }

      const venta = resp.data.venta;
      const items = resp.data.items || [];
      const abonos = resp.data.abonos || [];
      const documentos = resp.data.documentos_cfdi || [];
      const documentosHtml = documentos.length
        ? `<div class="mt-3"><h6>Historial de CFDI</h6>
      <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Versión</th><th>UUID</th><th>Estado</th><th>Relación</th></tr></thead>
      <tbody>${documentos
        .map(
          (
            d,
          ) => `<tr><td>${Number(d.version)}</td><td><code>${escaparHtml(d.uuid || "Pendiente")}</code></td>
        <td>${escaparHtml(d.estado)}</td><td>${d.relacion_tipo ? `${escaparHtml(d.relacion_tipo)} → ${escaparHtml(d.uuid_relacionado)}` : "Original"}</td></tr>`,
        )
        .join("")}</tbody></table></div></div>`
        : "";

      let itemsHtml = "";
      items.forEach((it) => {
        itemsHtml += `
        <tr>
          <td>${escaparHtml(it.producto)} <br><small class="text-muted">SKU: ${escaparHtml(it.codigo || "S/C")}</small></td>
          <td class="text-center">${parseFloat(it.cantidad).toFixed(3)} ${escaparHtml(it.unidad || "pza")}</td>
          <td class="text-end">$${parseFloat(it.precio).toFixed(2)}</td>
          <td class="text-end">$${(Number(it.descuento_producto || 0) + Number(it.descuento_general || 0)).toFixed(2)}</td>
          <td class="text-end fw-bold">$${parseFloat(it.importe).toFixed(2)}</td>
        </tr>`;
      });

      let abonosHtml = "";
      abonos.forEach((abono) => {
        abonosHtml += `<tr>
        <td>${escaparHtml(abono.fecha_registro)}</td>
        <td>${escaparHtml(abono.forma_pago || "N/D")}</td>
        <td>${escaparHtml(abono.usuario || "N/D")}</td>
        <td class="text-end fw-semibold">$${parseFloat(abono.monto).toFixed(2)}</td>
        <td>${escaparHtml(abono.observaciones || "")}</td>
      </tr>`;
      });
      if (!abonosHtml)
        abonosHtml =
          '<tr><td colspan="5" class="text-center text-muted py-3">Aún no hay abonos</td></tr>';

      const esCredito = venta.tipo_venta === "credito";
      const estadoCredito =
        parseFloat(venta.saldo_pendiente) <= 0
          ? '<span class="badge bg-success">Liquidada</span>'
          : '<span class="badge bg-warning text-dark">Pendiente</span>';
      const resumenCredito = esCredito
        ? `
      <div class="row g-2 mb-3">
        <div class="col-md-4"><div class="border rounded p-2 h-100"><small class="text-muted d-block">Total de la nota</small><strong>$${parseFloat(venta.total).toFixed(2)}</strong></div></div>
        <div class="col-md-4"><div class="border rounded p-2 h-100"><small class="text-muted d-block">Pagado</small><strong class="text-success">$${parseFloat(venta.total_pagado).toFixed(2)}</strong></div></div>
        <div class="col-md-4"><div class="border rounded p-2 h-100"><small class="text-muted d-block">Restante</small><strong class="${parseFloat(venta.saldo_pendiente) > 0 ? "text-danger" : "text-success"}">$${parseFloat(venta.saldo_pendiente).toFixed(2)}</strong></div></div>
      </div>
      <div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">Abonos</h6>${estadoCredito}</div>
      <div class="table-responsive mb-3" style="max-height: 190px; overflow:auto">
        <table class="table table-sm table-striped align-middle"><thead><tr><th>Fecha</th><th>Forma de pago</th><th>Usuario</th><th class="text-end">Monto</th><th>Nota</th></tr></thead><tbody>${abonosHtml}</tbody></table>
      </div>`
        : "";

      const estadosVenta = {
        0: { texto: "Cancelada", clase: "text-danger" },
        1: { texto: "Completada", clase: "text-success" },
        2: { texto: "Crédito pendiente", clase: "text-warning" },
        3: { texto: "Abonada parcialmente", clase: "text-info" },
        4: { texto: "Crédito liquidado", clase: "text-success" },
        5: { texto: "Crédito vencido", clase: "text-danger" },
      };

      const estado = estadosVenta[venta.estatus] ?? {
        texto: "Desconocido",
        clase: "text-secondary",
      };

      const ticketHtml = `
      <div class="text-start" id="ticket-modal-content">
        <div class="d-flex justify-content-between mb-2">
          <span>Folio: <strong>${venta.folio}</strong></span>
          <span>Fecha: <strong>${venta.fecha_registro}</strong></span>
        </div>
        <div class="mb-2">
          <span>Cliente: <strong>${venta.cliente}</strong></span>
        </div>
        <div class="mb-2">
          <span>Cajero: <strong>${escaparHtml(venta.cajero || "N/D")}</strong> | Tipo: <strong>${esCredito ? "Crédito" : "Contado"}</strong> | Pago: <strong>${escaparHtml(venta.forma_pago || "N/D")}</strong></span>
        </div>
       <div class="mb-2">
          <span>
              Estado:
              <strong class="${estado.clase}">
                  ${estado.texto}
              </strong>
          </span>
      </div>
        <div class="mb-2">
          <span>Facturación: <strong>${venta.estatus_factura === "Facturada" ? '<span class="text-success">Facturada</span>' : ["Sustituyendo", "Sustitucion pendiente"].includes(venta.estatus_factura) ? '<span class="text-warning">Sustitución pendiente</span>' : venta.estatus_factura === "En proceso" ? '<span class="text-warning">En proceso; reintento disponible</span>' : '<span class="text-secondary">Pendiente</span>'}</strong></span>
          ${venta.estatus_factura === "Facturada" ? '<button type="button" class="btn btn-outline-success btn-sm ms-2" id="ticket-acciones-factura"><i class="fa-solid fa-file-invoice me-1"></i>PDF, XML o correo</button>' : ""}
          ${["Sustituyendo", "Sustitucion pendiente"].includes(venta.estatus_factura) ? `<button class="btn btn-outline-warning btn-sm ms-2" type="button" id="ticket-revisar-sustitucion">Revisar sustitución</button>` : ""}
        </div>
        ${resumenCredito}
        <hr class="my-2">
        <div class="table-responsive" style="max-height: 250px;">
          <table class="table table-sm table-striped">
            <thead>
              <tr>
                <th>Producto</th>
                <th class="text-center">Cant</th>
                <th class="text-end">Precio</th>
                <th class="text-end">Desc.</th>
                <th class="text-end">Importe</th>
              </tr>
            </thead>
            <tbody>
              ${itemsHtml}
            </tbody>
          </table>
        </div>
        <hr class="my-2">
        <div class="d-flex justify-content-between small"><span>Subtotal comercial</span><span>$${Number(venta.subtotal || venta.total).toFixed(2)}</span></div>
        <div class="d-flex justify-content-between small"><span>Descuentos por producto</span><span>-$${Number(venta.descuento_productos || 0).toFixed(2)}</span></div>
        <div class="d-flex justify-content-between small"><span>Descuento general</span><span>-$${Number(venta.descuento_general || 0).toFixed(2)}</span></div>
        <div class="d-flex justify-content-between h4 fw-bold text-dark">
          <span>Total:</span>
          <span>$${parseFloat(venta.total).toFixed(2)}</span>
        </div>
        ${documentosHtml}
      </div>`;

      Swal.fire({
        title: "Ticket: " + venta.folio,
        width: "650px",
        html: ticketHtml,
        showCancelButton: true,
        cancelButtonText: "Cerrar",
        confirmButtonText: '<i class="fa-solid fa-print me-1"></i> Reimprimir',
        showDenyButton:
          esCredito &&
          parseFloat(venta.saldo_pendiente) > 0 &&
          (Number(venta.estatus) === 2 || Number(venta.estatus) === 3 || Number(venta.estatus) === 5),
        denyButtonText:
          '<i class="fa-solid fa-hand-holding-dollar me-1"></i> Registrar abono',
        didOpen: () => {
          const botonFactura = document.getElementById(
            "ticket-acciones-factura",
          );
          if (botonFactura)
            botonFactura.addEventListener("click", () =>
              abrirAccionesFactura(id_venta, venta.factura_correo || "", {
                metodoPago: venta.factura_metodo_pago,
                onPagoEmitido: () => tabla && tabla.ajax.reload(null, false),
              }),
            );
          document
            .getElementById("ticket-revisar-sustitucion")
            ?.addEventListener("click", () =>
              window.revisarSustitucionVenta(id_venta),
            );
        },
      }).then((r) => {
        if (r.isDenied) {
          abrirModalAbono(id_venta, venta, resp.data.formas_pago || []);
        } else if (r.isConfirmed) {
          // Validar si el tipo de reporte es PDF (2) o Ticket Térmico (1)
          if (Number(venta.tipo_reporte) === 2) {
            window.open(
              BASE_URL + "api/ventas.php?tipo=pdf&id=" + id_venta,
              "_blank",
            );
          } else {
            // Comportamiento original para ticket térmico
            const contentHtml = document.getElementById(
              "ticket-modal-content",
            ).innerHTML;
            const printWindow = window.open(
              "",
              "_blank",
              "width=400,height=600",
            );
            printWindow.document.write(`
            <html>
              <head>
                <title>Ticket ${venta.folio}</title>
                <style>
                  body { font-family: monospace; padding: 10px; font-size: 12px; }
                  table { width: 100%; border-collapse: collapse; }
                  td, th { padding: 4px; }
                </style>
              </head>
              <body>${contentHtml}</body>
            </html>
          `);
            printWindow.document.close();
            setTimeout(() => {
              printWindow.focus();
              printWindow.print();
              printWindow.close();
            }, 250);
          }
        }
      });
    },
    "json",
  );
}

function cancelarVenta(id_venta, folio) {
  Swal.fire({
    icon: "warning",
    title: "¿Cancelar Venta " + folio + "?",
    text: "Esta acción cancelará el ticket y devolverá automáticamente las cantidades vendidas al inventario. Ademas no canceles el ticket si esta facturado, primero cancela la factura.",
    input: "text",
    inputLabel: "Motivo de la cancelación",
    inputPlaceholder: "Ej: Devolución de mercancía / Error de captura",
    showCancelButton: true,
    confirmButtonText: "Sí, cancelar venta",
    confirmButtonColor: "#dc3545",
    cancelButtonText: "No cancelar",
    preConfirm: (motivo) => {
      if (!motivo || !motivo.trim()) {
        Swal.showValidationMessage("Debes ingresar un motivo");
        return false;
      }
      return motivo.trim();
    },
  }).then((r) => {
    if (!r.isConfirmed) return;
    $.post(
      BASE_URL + "api/ventas.php?tipo=cancelar",
      { id: id_venta, motivo: r.value },
      function (resp) {
        Toast.fire({
          icon: resp.estatus ? "success" : "error",
          title: resp.mensaje,
        });
        if (resp.estatus && tabla) {
          tabla.ajax.reload(false);
        }
      },
      "json",
    );
  });
}

function editarVenta(id_venta) {
  $.get(
    BASE_URL + "api/ventas.php?tipo=detalle&id=" + id_venta,
    function (resp) {
      if (!resp.estatus) {
        Toast.fire({ icon: "error", title: resp.mensaje });
        return;
      }
      const venta = resp.data.venta;
      Swal.fire({
        title: "Editar fecha de venta",
        html: `<div class="text-start">
        <label class="form-label">Fecha y hora de la venta</label>
        <input id="venta-fecha" style="background-color: #f8f9fa;" type="text" class="form-control" value="${venta.fecha_registro.replace(" ", "T")}">
      </div>`,
        showCancelButton: true,
        confirmButtonText: "Guardar cambios",
        cancelButtonText: "Cancelar",
        preConfirm: () => {
          const nuevaFecha = $("#venta-fecha").val();
          if (!nuevaFecha) {
            Swal.showValidationMessage("Debes ingresar una fecha válida");
            return false;
          }
          return nuevaFecha;
        },
        didOpen: () => {
          flatpickr("#venta-fecha", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            locale: "es",
          });
        },
      }).then((result) => {
        if (!result.isConfirmed) return;
        const payload = { id: id_venta, nueva_fecha: result.value };
        $.post(
          BASE_URL + "api/ventas.php?tipo=editar_venta",
          payload,
          function (resp) {
            Toast.fire({
              icon: resp.estatus ? "success" : "error",
              title: resp.mensaje,
            });
            if (resp.estatus && tabla) tabla.ajax.reload(null, false);
          },
          "json",
        );
      });
    },
    "json",
  );
}
window.verDetalleVenta = (id) => verDetalleVenta(id);
window.cancelarVenta = (id, folio) => cancelarVenta(id, folio);
window.editarVenta = (id) => editarVenta(id);
window.facturarVenta = (id) =>
  abrirFormularioFactura(id, () => tabla && tabla.ajax.reload(null, false));
window.sustituirFacturaVenta = (id) => {
  abrirFormularioFactura(
    id,
    () => tabla && tabla.ajax.reload(null, false),
    "sustitucion",
  );
};
window.revisarSustitucionVenta = (id) => {
  $.ajax({
    url: `${BASE_URL}api/ventas.php?tipo=revisar_sustitucion`,
    type: "POST",
    dataType: "json",
    data: { id },
  })
    .done((r) => {
      Swal.fire({
        icon: r.pendiente ? "info" : r.estatus ? "success" : "warning",
        title: "Estado de sustitución",
        text: r.mensaje || "Sin información",
      });
      if (tabla) tabla.ajax.reload(null, false);
    })
    .fail(() =>
      Swal.fire({
        icon: "error",
        title: "No se pudo consultar la sustitución",
      }),
    );
};
window.accionesFacturaVenta = (id) => {
  $.get(
    `${BASE_URL}api/ventas.php?tipo=detalle&id=${encodeURIComponent(id)}`,
    (response) => {
      if (!response.estatus) {
        Toast.fire({
          icon: "error",
          title:
            response.mensaje || "No se pudieron cargar los datos de la factura",
        });
        return;
      }

      abrirAccionesFactura(id, response.data.venta.factura_correo || "", {
        metodoPago: response.data.venta.factura_metodo_pago,
        onPagoEmitido: () => tabla && tabla.ajax.reload(null, false),
      });
    },
    "json",
  ).fail(() =>
    Toast.fire({
      icon: "error",
      title: "No se pudieron cargar los datos de la factura",
    }),
  );
};

$(document).ready(function () {
  const estadosVenta = {
    0: { texto: "Cancelada", clase: "badge bg-danger" },
    1: { texto: "Completada", clase: "badge bg-success" },
    2: { texto: "Crédito pendiente", clase: "badge bg-warning" },
    3: { texto: "Abonada parcialmente", clase: "badge bg-info" },
    4: { texto: "Crédito liquidado", clase: "badge bg-success" },
    5: { texto: "Crédito vencido", clase: "badge bg-danger" },
  };

  const columns = [
    { data: "id", title: "#" },
    { data: "folio", title: "Folio", render: (d) => `<code>${d}</code>` },
    { data: "fecha_registro", title: "Fecha" },
    { data: "cliente", title: "Cliente" },
    {
      data: "forma_pago",
      title: "Forma de Pago",
      render: (d) => d || "Efectivo",
    },
    {
      data: "tipo_venta",
      title: "Tipo",
      render: (d) =>
        d === "credito"
          ? '<span class="badge bg-warning text-dark">Crédito</span>'
          : '<span class="badge bg-light text-dark">Contado</span>',
    },
    {
      data: "saldo_pendiente",
      title: "Saldo",
      render: (d, type, row) =>
        row.tipo_venta === "credito"
          ? `<strong class="${parseFloat(d) > 0 ? "text-danger" : "text-success"}">$${parseFloat(d || 0).toFixed(2)}</strong>`
          : "—",
    },
    { data: "cajero", title: "Atendió", render: (d) => d || "Admin" },
    {
      data: "total",
      title: "Total",
      render: (d) => `<strong>$${parseFloat(d || 0).toFixed(2)}</strong>`,
    },
    {
      data: "estatus_factura",
      title: "Factura",
      render: (d, type, row) => {
        switch (d) {
          case "Sustituyendo":
          case "Sustitucion pendiente":
            return `<button class="btn btn-outline-warning btn-sm" onclick="revisarSustitucionVenta(${Number(row.id)})">Revisar sustitución</button>`;
          case "Facturada":
            const factura_metodo_pago = row.factura_metodo_pago || "";
            return `<button class="btn btn-success btn-sm" onclick="accionesFacturaVenta(${Number(row.id)})" title="Descargar o enviar factura">
            <i class="fa-solid fa-file-invoice me-1"></i>Factura ${factura_metodo_pago}</button>`;

          case "En proceso":
            return '<span class="badge bg-warning text-dark">En proceso</span>';

          case "Cancelada":
            return '<span class="badge bg-danger">Cancelada</span>';

          case "Cancelacion pendiente":
            return '<span class="badge bg-info text-dark">Cancelación pendiente</span>';

          default:
            return '<span class="badge bg-secondary">Pendiente</span>';
        }
      },
    },
    {
      data: null,
      title: "Estatus",
      render: (d) => {
        const estado = estadosVenta[d.estatus] ?? {
          texto: "Desconocido",
          clase: "text-secondary",
        };
        return `<strong class="${estado.clase}">${estado.texto}</strong>`;
      },
    },
    {
      data: null,
      title: "Acciones",
      render: (data, type, row) => `
        <button class="btn btn-info btn-sm text-white" onclick="verDetalleVenta(${row.id})" title="Ver Ticket / Detalle">
          <i class="fa-solid fa-receipt me-1"></i> Detalle
        </button>
        ${(row.estatus == 1 || row.estatus == 2 || row.estatus == 3 || row.estatus == 4 || row.estatus == 5) && ["Pendiente", "En proceso"].includes(row.estatus_factura) ? 
          `<button class="btn btn-warning btn-sm" onclick="facturarVenta(${row.id})" title="Facturar recibo"><i class="fa-solid fa-file-invoice"></i></button>` : ""}
        ${
          tienePermiso("cancelar_ventas") && (row.estatus == 1 || row.estatus == 2 || row.estatus == 3 || row.estatus == 4 || row.estatus == 5)
            ? `
        <button class="btn btn-danger btn-sm" onclick="cancelarVenta(${row.id}, '${row.folio}')" title="Cancelar Venta">
          <i class="fa-solid fa-ban"></i>
        </button>`
            : ""
        }
        ${
          tienePermiso("cambiar_fecha_ventas") && (row.estatus == 1 || row.estatus == 2 || row.estatus == 3 || row.estatus == 4 || row.estatus == 5)
            ? `
        <button class="btn btn-sm" style="background-color: coral; color: white;" onclick="editarVenta(${row.id})" title="Cambiar fecha de venta">
          <i class="fa-solid fa-edit"></i>
        </button>`
            : ""
        }
        
        `,
    },
  ];

  tabla = initReportTable({
    selector: "#tabla-ventas",
    module: "ventas",
    columns,
    fields: [
      { name: "folio", label: "Folio", placeholder: "Folio" },
      { name: "cliente", label: "Cliente", placeholder: "Nombre del cliente" },
      {
        name: "forma_pago",
        label: "Forma de pago",
        type: "select",
        endpoint: "api/catalogos.php?tipo=combo&catalogo=formas_pago",
        liveSearch: true,
      },
      {
        name: "tipo_venta",
        label: "Tipo de venta",
        type: "select",
        options: [
          { value: "contado", label: "Contado" },
          { value: "credito", label: "Crédito" },
        ],
      },
      {
        name: "estatus_factura",
        label: "Factura",
        type: "select",
        options: [
          { value: "Pendiente", label: "Pendiente" },
          { value: "En proceso", label: "En proceso" },
          { value: "Facturada", label: "Facturada" },
        ],
      },
      {
        name: "estatus",
        label: "Estatus",
        type: "select",
        options: [
          { value: "1", label: "Completada" },
          { value: "0", label: "Cancelada" },
        ],
      },
    ],
  });
});
