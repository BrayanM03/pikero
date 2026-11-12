import {
  cargarCatalogoSAT,
  inicializarSelectpicker,
  opcionesSAT,
} from "./catalogos-sat.js";
import { abrirAccionesFactura } from "./factura-actions.js";

const esc = (v) =>
  String(v ?? "").replace(
    /[&<>"']/g,
    (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        c
      ],
  );
const rnd = (n, d = 6) =>
  Math.round((Number(n) + Number.EPSILON) * 10 ** d) / 10 ** d;
const cents = (n) => Math.round(Number(n) * 100);
const money = (n) =>
  (Number(n) || 0).toLocaleString("es-MX", {
    style: "currency",
    currency: "MXN",
  });
const copy = (x) => JSON.parse(JSON.stringify(x));
const TAXES = [
  {
    id: "iva16",
    label: "IVA trasladado 16%",
    name: "IVA",
    factor: "Tasa",
    rate: 0.16,
    is_retention: false,
  },
  {
    id: "iva8",
    label: "IVA trasladado 8%",
    name: "IVA",
    factor: "Tasa",
    rate: 0.08,
    is_retention: false,
  },
  {
    id: "iva0",
    label: "IVA trasladado 0%",
    name: "IVA",
    factor: "Tasa",
    rate: 0,
    is_retention: false,
  },
  {
    id: "exento",
    label: "IVA exento",
    name: "IVA Exento",
    factor: "Exento",
    rate: 0,
    is_retention: false,
  },
  {
    id: "isr125",
    label: "ISR retenido 1.25% (RESICO)",
    name: "ISR",
    factor: "Tasa",
    rate: 0.0125,
    is_retention: true,
  },
  {
    id: "isr10",
    label: "ISR retenido 10%",
    name: "ISR",
    factor: "Tasa",
    rate: 0.1,
    is_retention: true,
  },
  {
    id: "ivar4",
    label: "IVA retenido 4%",
    name: "IVA RET",
    factor: "Tasa",
    rate: 0.04,
    is_retention: true,
  },
  {
    id: "ivar10",
    label: "IVA retenido 10%",
    name: "IVA RET",
    factor: "Tasa",
    rate: 0.1,
    is_retention: true,
  },
  {
    id: "ivar2t",
    label: "IVA retenido 2/3 (10.6667%)",
    name: "IVA RET",
    factor: "Tasa",
    rate: 0.106667,
    is_retention: true,
  },
  {
    id: "ieps_tasa",
    label: "IEPS trasladado (tasa)",
    name: "IEPS",
    factor: "Tasa",
    rate: 0.08,
    is_retention: false,
  },
  {
    id: "ieps_cuota",
    label: "IEPS trasladado (cuota)",
    name: "IEPS",
    factor: "Cuota",
    rate: 1,
    is_retention: false,
  },
  {
    id: "ieps_ret",
    label: "IEPS retenido (tasa)",
    name: "IEPS",
    factor: "Tasa",
    rate: 0.08,
    is_retention: true,
  },
];
const keyTax = (t) =>
  `${t.name}|${t.is_retention ? "1" : "0"}|${t.factor}|${Number(t.rate).toFixed(6)}`;
const taxTitle = (t) => {
  const n = TAXES.find(
    (x) =>
      x.name === t.name &&
      x.factor === t.factor &&
      x.is_retention === t.is_retention &&
      Math.abs(x.rate - t.rate) < 0.0000001,
  );
  return (
    n?.label ||
    `${t.is_retention ? "Retención" : "Traslado"} ${t.name} ${t.factor === "Cuota" ? money(t.rate) + "/unidad" : (t.rate * 100).toFixed(4) + "%"}`
  );
};
const showLoading = (text) =>
  Swal.fire({
    title: text,
    allowEscapeKey: false,
    allowOutsideClick: false,
    showConfirmButton: false,
    didOpen: () => Swal.showLoading(),
  });

function defaultConcept(it, pre = null) {
  if (pre && typeof pre === "object")
    return {
      sku: String(pre.IdentificationNumber || it.codigo || ""),
      description: String(pre.Description || it.producto || ""),
      product_code: String(pre.ProductCode || it.clave_sat || ""),
      unit_code: String(pre.UnitCode || it.codigo_unidad_sat || ""),
      unit: String(pre.Unit || it.unidad || "Pieza"),
      quantity: Number(pre.Quantity),
      unit_price: Number(pre.UnitPrice),
      discount: Number(pre.Discount || 0),
      tax_object: String(pre.TaxObject || "02"),
      taxes: (pre.Taxes || []).map((t) => ({
        name: String(t.Name),
        factor:
          t.Name === "IVA Exento" ? "Exento" : t.IsQuota ? "Cuota" : "Tasa",
        rate: Number(t.Rate || 0),
        is_retention: !!t.IsRetention,
        base_mode: t.IsQuota ? "cantidad" : "neto",
      })),
      details: "",
      third_party: pre.ThirdPartyAccount
        ? {
            rfc: pre.ThirdPartyAccount.Rfc || "",
            name: pre.ThirdPartyAccount.Name || "",
            regime: pre.ThirdPartyAccount.FiscalRegime || "",
            zip: pre.ThirdPartyAccount.TaxZipCode || "",
          }
        : null,
      predial: Array.isArray(pre.PropertyTaxIDNumber)
        ? String(pre.PropertyTaxIDNumber[0] || "")
        : "",
      pedimentos: Array.isArray(pre.NumerosPedimento)
        ? copy(pre.NumerosPedimento)
        : [],
    };
  const tipo = String(
    it.tipo_iva_facturacion ?? it.tipo_iva_venta ?? "0.16",
  ).toLowerCase();
  const aplicaIVA = ["0", "0.08", "0.16"].includes(tipo);
  const tasa = aplicaIVA ? Number(tipo) : 0;
  const fact = 1 + tasa,
    qty = Number(it.cantidad);
  const descuentoComercial =
    Number(it.descuento_producto || 0) + Number(it.descuento_general || 0);
  const bruto = Number(
    it.importe_bruto ?? Number(it.importe) + descuentoComercial,
  );
  const incluye =
    Number(it.precio_incluye_iva_facturacion ?? it.precio_venta_incluye_iva) ===
    1;
  const punit = rnd(incluye ? Number(it.precio) / fact : Number(it.precio));
  const descFiscal = rnd(descuentoComercial / fact);
  const tax_object =
    tipo === "no_objeto"
      ? "01"
      : String(it.objeto_impuesto_facturacion ?? it.objeto_impuesto ?? "02");
  const taxes =
    tax_object === "01"
      ? []
      : tipo === "exento"
        ? [copy(TAXES.find((x) => x.id === "exento"))]
        : aplicaIVA
          ? [
              {
                name: "IVA",
                factor: "Tasa",
                rate: tasa,
                is_retention: false,
                base_mode: "neto",
              },
            ]
          : [];
  // Mantener precio histórico; el usuario puede ajustar el precio unitario para cuadrar
  // cuando su operación requiera una nueva clasificación fiscal.
  return {
    sku: String(it.codigo || ""),
    description: String(it.producto || ""),
    product_code: String(it.clave_sat || ""),
    unit_code: String(it.codigo_unidad_sat || ""),
    unit: String(it.unidad || "Pieza"),
    quantity: qty,
    unit_price: punit,
    discount: descFiscal,
    tax_object,
    taxes,
    predial: "",
    pedimentos: [],
    details: "",
    third_party: null,
    ticket_gross: Number(it.importe),
    ticket_bruto: bruto,
  };
}

function previewOne(c) {
  const q = Number(c.quantity),
    p = Number(c.unit_price),
    disc = Number(c.discount);
  if (![q, p, disc].every(Number.isFinite) || q <= 0 || p <= 0 || disc < 0)
    throw Error("Cantidad, precio o descuento inválidos");
  const sub = rnd(q * p),
    base = rnd(sub - disc);
  if (base <= 0) throw Error("La base fiscal debe ser positiva");
  if (!["01", "02"].includes(c.tax_object))
    throw Error("ObjetoImp fuera del alcance");
  if (c.tax_object === "01" && c.taxes.length)
    throw Error("ObjetoImp 01 no lleva impuestos");
  const vat = c.taxes.filter(
    (t) => !t.is_retention && ["IVA", "IVA Exento"].includes(t.name),
  );
  if (vat.length > 1)
    throw Error("Solo se permite un traslado de IVA por partida");
  const keys = new Set(),
    trans = [],
    ret = [];
  const ieps = c.taxes
    .filter((t) => t.name === "IEPS" && !t.is_retention)
    .reduce(
      (s, t) => s + rnd((t.factor === "Cuota" ? q : base) * Number(t.rate)),
      0,
    );
  for (const t of c.taxes) {
    if (keys.has(keyTax(t))) throw Error("Impuesto duplicado");
    keys.add(keyTax(t));
    const validName = ["IVA", "IVA Exento", "ISR", "IVA RET", "IEPS"].includes(
      t.name,
    );
    if (!validName || !["Tasa", "Cuota", "Exento"].includes(t.factor))
      throw Error("Impuesto o factor inválido");
    const b =
      t.factor === "Cuota"
        ? q
        : t.base_mode === "neto_mas_ieps"
          ? base + ieps
          : base;
    const amount = t.factor === "Exento" ? 0 : rnd(b * Number(t.rate));
    (t.is_retention ? ret : trans).push({ ...t, base: rnd(b), amount });
  }
  const totalTrans = rnd(trans.reduce((s, t) => s + t.amount, 0));
  const totalRet = rnd(ret.reduce((s, t) => s + t.amount, 0));
  return {
    subtotal: sub,
    discount: disc,
    base,
    traslado: totalTrans,
    retencion: totalRet,
    gross: rnd(base + totalTrans, 2),
    total: rnd(base + totalTrans - totalRet, 2),
    trans,
    ret,
  };
}
function previewAll(items, venta, models) {
  const res = items.map((it) => previewOne(models[String(it.id)]));
  const groupsT = {},
    groupsR = {};
  res.forEach((r) => {
    r.trans.forEach((t) => {
      if (t.factor === "Exento") return;
      const key = `${t.name}|${t.factor}|${Number(t.rate).toFixed(6)}`;
      groupsT[key] = (groupsT[key] || 0) + t.amount;
    });
    r.ret.forEach((t) => {
      groupsR[t.name] = (groupsR[t.name] || 0) + t.amount;
    });
  });
  const subtotal = rnd(
      res.reduce((s, r) => s + r.subtotal, 0),
      2,
    ),
    desc = rnd(
      res.reduce((s, r) => s + r.discount, 0),
      2,
    );
  const tras = rnd(
    Object.values(groupsT).reduce((s, x) => s + rnd(x, 2), 0),
    2,
  );
  const ret = rnd(
    Object.values(groupsR).reduce((s, x) => s + rnd(x, 2), 0),
    2,
  );
  const gross = rnd(
      res.reduce((s, r) => s + r.gross, 0),
      2,
    ),
    total = rnd(subtotal - desc + tras - ret, 2);
  if (cents(gross) !== cents(venta.total))
    throw Error(
      `Importe comercial ${money(gross)} ≠ ticket ${money(venta.total)}. Ajusta los conceptos sin alterar la venta.`,
    );
  if (cents(res.reduce((s, r) => s + r.total, 0)) !== cents(total))
    throw Error("Descuadre de redondeo entre conceptos y CFDI");
  return { rows: res, subtotal, desc, tras, ret, gross, total };
}

const css = `<style>
.pikero-cfdi.swal2-popup{border-radius:18px!important;padding:0!important;overflow:hidden;--cf-border:#e6e9f0;--cf-bg:#f8fafc;color:#17223b}
.pikero-cfdi .swal2-title{font-size:22px!important;margin:22px 28px 0!important;text-align:left}
.pikero-cfdi .swal2-html-container{margin:12px 28px 0!important;text-align:left!important;overflow:auto!important;max-height:64vh!important}
.pikero-cfdi .swal2-actions{margin:22px 28px!important;justify-content:flex-end!important}
.cf-header-note{color:#64748b;font-size:13px;margin-bottom:16px}.cf-tabs{display:flex;gap:8px;border-bottom:1px solid var(--cf-border);margin-bottom:18px}
.cf-tab{background:none;border:0;padding:12px 14px;color:#64748b;font-size:14px;font-weight:600;border-bottom:3px solid transparent}
.cf-tab.active{color:#5146ce;border-color:#7162ed}.cf-panel{border:1px solid var(--cf-border);border-radius:12px;padding:18px;margin-bottom:14px;background:#fff}
.cf-panel h6{font-size:15px;font-weight:700;margin:0 0 13px;color:#26334c}.cf-label{font-size:12px;font-weight:600;color:#58657a;margin-bottom:5px;display:block}
.cf-input,.cf-select{border:1px solid #cfd7e3;border-radius:7px;height:38px;padding:7px 10px;background:#fff;width:100%;min-width:0;font-size:13px;color:#26334c}
.cf-input:focus,.cf-select:focus{border-color:#8275e9;outline:0;box-shadow:0 0 0 3px #eae6ff}
.cf-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:12px}.cf-col-3{grid-column:span 3}.cf-col-4{grid-column:span 4}.cf-col-6{grid-column:span 6}.cf-col-12{grid-column:span 12}
.cf-table{width:100%;border-collapse:collapse;font-size:12px}.cf-table th{color:#66738a;background:#f8fafc;font-size:11px;font-weight:700}.cf-table td,.cf-table th{padding:11px 9px;border-bottom:1px solid var(--cf-border);text-align:left}.cf-table td:last-child{text-align:right}
.cf-act{border:1px solid #d5d4f8;background:#f4f2ff;color:#5146ce;padding:6px 12px;border-radius:7px;font-size:12px;font-weight:600}.cf-act:hover{background:#ebe8ff}
.cf-btn{padding:9px 14px;border-radius:8px;border:1px solid #d5dbe6;background:white;font-size:12px;font-weight:700}.cf-btn-primary{background:#6858de;color:white;border-color:#6858de}
.cf-muted{color:#64748b;font-size:12px}.cf-tax{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 11px;border:1px solid var(--cf-border);border-radius:9px;margin-bottom:6px}
.cf-totals{max-width:390px;margin-left:auto;background:#f8fafc;border:1px solid var(--cf-border);border-radius:12px;padding:14px}.cf-total-row{display:flex;justify-content:space-between;font-size:13px;padding:4px 0}
.cf-total-main{border-top:1px solid #dbe0e9;margin-top:8px;padding-top:12px;font-size:18px;font-weight:800}.cf-danger{color:#b42318;font-size:12px;margin-top:10px}.cf-tip{background:#f5f3ff;border-left:3px solid #7162ed;padding:10px 12px;border-radius:6px;font-size:12px;color:#4c427c;margin:12px 0}
.cf-subtitle{font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#8c97a9;margin:15px 0 8px;font-weight:800}
.pikero-cfdi .dropdown-menu{z-index:2000}@media(max-width:740px){.cf-col-3,.cf-col-4,.cf-col-6{grid-column:span 12}.pikero-cfdi .swal2-html-container{margin:10px 14px!important}.cf-table{font-size:11px}}
</style>`;


const ACC_CAMPOS = {
  fac_acc_opcionales: ["fac_e_details", "fac_e_predial", "fac_e_pedimentos"],
  fac_acc_terceros: ["fac_e_tercero_rfc", "fac_e_tercero_nombre", "fac_e_tercero_regimen", "fac_e_tercero_cp"],
};

function actualizarAcordeones(abrirSiHayDatos = true) {
  Object.entries(ACC_CAMPOS).forEach(([accId, campos]) => {
    const acc = document.getElementById(accId);
    if (!acc) return;
    const hayDatos = campos.some((id) => (document.getElementById(id)?.value || "").trim() !== "");
    acc.querySelector(".cf-acc-badge").hidden = !hayDatos;
    if (abrirSiHayDatos && hayDatos) acc.open = true;
  });
}

export function abrirFormularioFactura(
  idVenta,
  onSuccess = null,
  modo = "normal",
) {
  const sustitucion = modo === "sustitucion";
  showLoading("Cargando información fiscal...");
  $.when(
    $.getJSON(`${BASE_URL}api/ventas.php?tipo=emisores`),
    $.getJSON(`${BASE_URL}api/clientes.php?tipo=combo`),
    $.getJSON(
      `${BASE_URL}api/ventas.php?tipo=detalle&id=${encodeURIComponent(idVenta)}`,
    ),
  )
    .done(async (issuersResult, clientsResult, detailResult) => {
      try {
        const iss = issuersResult[0],
          cr = clientsResult[0],
          detail = detailResult[0];
        if (!iss.estatus || !cr.estatus || !detail.estatus)
          throw new Error("No se pudieron obtener emisor, clientes o detalle");
        const emisores = iss.data || [],
          clientes = cr.data || [],
          venta = detail.data.venta || {},
          items = detail.data.items || [];
        if (!items.length || !emisores.length)
          throw new Error("No hay conceptos o emisores");
        if (
          sustitucion &&
          (!venta.uuid_factura || venta.estatus_factura !== "Facturada")
        )
          throw Error("No hay CFDI vigente para sustituir");
        if (
          !sustitucion &&
          !["Pendiente", "En proceso"].includes(venta.estatus_factura)
        )
          throw Error("La venta ya está facturada");
        let reg, usos, fp, mp;
        try {
          [reg, usos, fp, mp] = await Promise.all(
            [
              "FiscalRegimens",
              "CfdiUses",
              "PaymentForms",
              "PaymentMethods",
            ].map((x) => cargarCatalogoSAT(x)),
          );
        } catch (e) {
          throw Error("No se pudieron cargar catálogos fiscales: " + e.message);
        }
        const originalCfdis = detail.data.conceptos_cfdi_vigente || {};
        const previos = detail.data.impuestos_cfdi_vigente || {};
        const models = {};
        items.forEach((it) => {
          const key = String(it.id),
            pre = sustitucion ? originalCfdis[key] : null;
          models[key] = defaultConcept(it, pre);
          if (sustitucion && !pre && previos[key]) {
            const v = previos[key];
            models[key].taxes = [];
            if (v.tipo_iva === "exento")
              models[key].taxes = [copy(TAXES.find((x) => x.id === "exento"))];
            else if (["0", "0.08", "0.16"].includes(v.tipo_iva))
              models[key].taxes = [
                {
                  name: "IVA",
                  factor: "Tasa",
                  rate: Number(v.tipo_iva),
                  is_retention: false,
                  base_mode: "neto",
                },
              ];
            if (v.retencion_isr_resico)
              models[key].taxes.push(
                copy(TAXES.find((x) => x.id === "isr125")),
              );
          }
        });
        const issuerId = sustitucion
          ? Number(venta.factura_issuer_id)
          : emisores.length === 1
            ? Number(emisores[0].issuer_id)
            : 0;
        const optsClientes = clientes
          .map(
            (c) =>
              `<option value="${Number(c.id)}">${esc(c.nombre)}${c.rfc ? " (" + esc(c.rfc) + ")" : ""}</option>`,
          )
          .join("");
        const issuerControl = issuerId
          ? `<div class="cf-header-note">Emisor: <strong>${esc(emisores.find((e) => Number(e.issuer_id) === issuerId)?.name || "Emisor " + issuerId)}</strong></div>`
          : `<div class="cf-panel"><label class="cf-label">Emisor</label><select id="fac_emisor" class="cf-select"><option value="">Seleccionar emisor</option>${emisores.map((e) => `<option value="${Number(e.issuer_id)}">${esc(e.name)} · ${esc(e.rfc || "")}</option>`).join("")}</select></div>`;
        const esCredito =
          venta.tipo_venta === "credito" ||
          Number(venta.saldo_pendiente || 0) > 0;
        const metodo = sustitucion
          ? venta.factura_metodo_pago || "PUE"
          : esCredito
            ? "PPD"
            : "PUE";
        const forma = sustitucion
          ? venta.factura_forma_pago || "01"
          : esCredito
            ? "99"
            : "01";
        const html = `${css}<div class="cf-header-note">Ticket ${esc(venta.folio)} · Importe comercial ${money(venta.total)} · ${sustitucion ? "Sustitución del CFDI vigente" : "Emisión CFDI 4.0"}</div>
      ${issuerControl}${items.some((x) => Number(x.fiscal_heredado) === 1) ? 
        '<div class="cf-tip">Esta venta tiene conceptos antiguos sin snapshot fiscal; revisa claves e impuestos antes de timbrar.</div>' : ""}<div class="cf-tabs"><button type="button" class="cf-tab active" data-tab="generales">Datos generales</button><button type="button" class="cf-tab" data-tab="conceptos">Conceptos e impuestos <span class="badge bg-light text-dark">${items.length}</span></button></div>
      <section id="fac_section_generales">
        <div class="cf-panel"><h6>Comprobante</h6><div class="cf-grid">
          <div class="cf-col-4"><label class="cf-label">Lugar de expedición</label><input id="fac_lugar" class="cf-input" value="${esc(venta.ferreteria_codigo_postal || "")}" readonly title="Se toma de la configuración fiscal del emisor"></div>
          <div class="cf-col-4"><label class="cf-label">Tipo de CFDI</label><select id="fac_tipo" class="cf-select"><option value="I">I · Ingreso / Factura</option><option value="E" disabled>E · Egreso (módulo futuro)</option><option value="T" disabled>T · Traslado (módulo futuro)</option><option value="P" disabled>P · Pago (REP futuro)</option><option value="N" disabled>N · Nómina</option></select></div>
          <div class="cf-col-4"><label class="cf-label">Moneda</label><select id="fac_moneda" class="cf-select"><option value="MXN">MXN · Peso mexicano</option></select></div>
          <div class="cf-col-4"><label class="cf-label">Exportación</label><select id="fac_exportacion" class="cf-select"><option value="01">01 · No aplica</option><option value="02" disabled>02 · Definitiva (requiere complemento)</option><option value="03">03 · Temporal (revisar operación)</option><option value="04">04 · Definitiva sin enajenación (revisar operación)</option></select></div>
          <div class="cf-col-6"><label class="cf-label">Condiciones de pago (opcional)</label><input id="fac_condiciones" class="cf-input" maxlength="1000" placeholder="Ej. Crédito a 15 días"></div>
          <div class="cf-col-6"><label class="cf-label">Número de orden (opcional)</label><input id="fac_orden" class="cf-input" maxlength="100" placeholder="Referencia del cliente"></div>
          <div class="cf-col-12"><label class="cf-label">Observaciones (solo representación/PDF)</label><input id="fac_observaciones" class="cf-input" maxlength="500" placeholder="Notas que no sustituyen requisitos fiscales"></div>
        </div></div>
        <div class="cf-panel"><h6>Receptor fiscal</h6><div class="cf-grid">
          <div class="cf-col-12"><label class="cf-label">Cliente del catálogo</label><select id="fac_cliente" class="selectpicker w-100" data-live-search="true"><option value="">Captura manual</option>${optsClientes}</select></div>
          <div class="cf-col-6"><label class="cf-label">RFC *</label><input id="fac_rfc" class="cf-input" maxlength="13"></div>
          <div class="cf-col-6"><label class="cf-label">Razón social *</label><input id="fac_razon" class="cf-input"></div>
          <div class="cf-col-4"><label class="cf-label">Código postal fiscal *</label><input id="fac_cp" class="cf-input" maxlength="5" inputmode="numeric"></div>
          <div class="cf-col-4"><label class="cf-label">Régimen fiscal *</label><select id="fac_regimen" class="selectpicker w-100" data-live-search="true">${opcionesSAT(reg)}</select></div>
          <div class="cf-col-4"><label class="cf-label">Uso CFDI *</label><select id="fac_uso" class="selectpicker w-100" data-live-search="true">${opcionesSAT(usos)}</select></div>
          <div class="cf-col-6"><label class="cf-label">Correo (opcional)</label><input id="fac_correo" type="email" class="cf-input"></div>
          <div class="cf-col-3"><label class="cf-label">Método de pago *</label><select id="fac_metodo" class="selectpicker w-100">${opcionesSAT(mp, metodo)}</select></div>
          <div class="cf-col-3"><label class="cf-label">Forma de pago *</label><select id="fac_forma" class="selectpicker w-100">${opcionesSAT(fp, forma)}</select></div>
        </div></div></section>
      <section id="fac_section_conceptos" style="display:none"><div class="cf-panel"><h6>Conceptos precargados desde el ticket</h6><p class="cf-muted">Editar solo afecta al CFDI; no modifica el POS, inventario ni pagos.</p>
        <div class="table-responsive"><table class="cf-table"><thead><tr><th>Descripción</th><th>Cantidad</th><th>Impuestos</th><th>Total CFDI</th><th></th></tr></thead><tbody id="fac_rows"></tbody></table></div></div></section>
      <section id="fac_editor" style="display:none">
        <div class="cf-panel">
          <div class="cf-editor-head">
            <h6 id="fac_editor_title">Editar concepto</h6>
            <button type="button" class="cf-btn" id="fac_editor_close">Volver</button>
          </div>

          <div class="cf-subtitle">Producto o servicio</div>
          <div class="cf-grid">
            <div class="cf-col-3"><label class="cf-label">Clave interna</label><input class="cf-input" id="fac_e_sku"></div>
            <div class="cf-col-9"><label class="cf-label">Producto / servicio *</label><input class="cf-input" id="fac_e_description"></div>
            <div class="cf-col-6"><label class="cf-label">Clave SAT *</label><select id="fac_e_product" class="selectpicker w-100" data-catalogo-sat="ProductsOrServices" data-live-search="true"></select></div>
            <div class="cf-col-6"><label class="cf-label">Unidad SAT *</label><select id="fac_e_unitcode" class="selectpicker w-100" data-catalogo-sat="Units" data-live-search="true"></select></div>
            <div class="cf-col-12"><label class="cf-label">Unidad descriptiva</label><input id="fac_e_unit" class="cf-input"></div>
          </div>

          <div class="cf-subtitle">Cantidades y precio</div>
          <div class="cf-grid">
            <div class="cf-col-3"><label class="cf-label">Cantidad *</label><input id="fac_e_quantity" type="number" min="0.000001" step="0.001" class="cf-input"></div>
            <div class="cf-col-3"><label class="cf-label">Precio unitario s/imp. *</label><input id="fac_e_price" type="number" min="0.000001" step="0.000001" class="cf-input"></div>
            <div class="cf-col-3"><label class="cf-label">Descuento antes de imp.</label><input id="fac_e_discount" type="number" min="0" step="0.000001" class="cf-input"></div>
            <div class="cf-col-3"><label class="cf-label">Objeto de impuesto *</label><select id="fac_e_object" class="cf-select"><option value="01">01 · No objeto</option><option value="02">02 · Sí objeto (desglose)</option></select></div>
          </div>

          <div class="cf-subtitle">Impuestos por concepto</div>
          <div id="fac_e_taxes"></div>
          <div class="cf-tax-add">
            <select id="fac_e_add_tax" class="cf-select">${TAXES.map((t) => `<option value="${t.id}">${esc(t.label)}</option>`).join("")}</select>
            <button type="button" id="fac_e_add_btn" class="cf-btn">+ Agregar impuesto</button>
          </div>
          <div class="cf-tip">Las tasas propuestas son opciones técnicas; su procedencia debe verificarse según la operación. IEPS de cuota requiere la cantidad fiscal correcta.</div>

          <details class="cf-acc" id="fac_acc_opcionales">
              <summary>
                <span class="cf-acc-title">Datos opcionales del concepto</span>
                <span class="cf-acc-badge" hidden>Con datos</span>
              </summary>
              <div class="cf-acc-body">
                <div class="cf-grid">
                  <div class="cf-col-12"><label class="cf-label">Descripción ampliada del concepto (opcional)</label><textarea class="cf-input" id="fac_e_details" rows="3" placeholder="Características y especificaciones de esta operación"></textarea></div>
                  <div class="cf-col-6"><label class="cf-label">Cuenta predial (solo si aplica)</label><input id="fac_e_predial" class="cf-input" maxlength="150"></div>
                  <div class="cf-col-6"><label class="cf-label">Números de pedimento (uno por línea)</label><textarea id="fac_e_pedimentos" class="cf-input" rows="3" placeholder="26 47 3807 8003832"></textarea></div>
                </div>
              </div>
            </details>

            <details class="cf-acc" id="fac_acc_terceros">
              <summary>
                <span class="cf-acc-title">A cuenta de terceros (opcional)</span>
                <span class="cf-acc-badge" hidden>Con datos</span>
              </summary>
              <div class="cf-acc-body">
                <div class="cf-grid">
                  <div class="cf-col-3"><label class="cf-label">RFC</label><input id="fac_e_tercero_rfc" class="cf-input" maxlength="13"></div>
                  <div class="cf-col-4"><label class="cf-label">Nombre / razón social</label><input id="fac_e_tercero_nombre" class="cf-input" maxlength="300"></div>
                  <div class="cf-col-2"><label class="cf-label">Régimen (clave)</label><input id="fac_e_tercero_regimen" class="cf-input" maxlength="3"></div>
                  <div class="cf-col-3"><label class="cf-label">C.P. fiscal</label><input id="fac_e_tercero_cp" class="cf-input" maxlength="5" inputmode="numeric"></div>
                </div>
                <div class="cf-tip">Los complementos sectoriales e impuestos locales se agregarán en módulos propios; no se envían estructuras incompletas al SAT.</div>
              </div>
            </details>
          
          <div id="fac_e_error" class="cf-danger"></div>

          <div class="cf-totals cf-totals-concept">
            <div class="cf-total-row"><span>Subtotal concepto</span><strong id="fac_e_subtotal">—</strong></div>
            <div class="cf-total-row"><span>Traslados / Retenciones</span><strong id="fac_e_taxes_total">—</strong></div>
            <div class="cf-total-row cf-total-main"><span>Total concepto CFDI</span><strong id="fac_e_concept_total">—</strong></div>
          </div>

          <div class="cf-actions">
            <button type="button" class="cf-btn cf-btn-primary" id="fac_e_save">Guardar cambios</button>
            <button type="button" class="cf-btn" id="fac_e_adjust">Ajustar precio para conservar importe de esta partida</button>
          </div>
        </div>
      </section>
      <div class="cf-totals"><div class="cf-total-row"><span>Subtotal</span><strong id="fac_total_sub">—</strong></div><div class="cf-total-row"><span>Descuentos</span><strong id="fac_total_discount">—</strong></div>
      <div class="cf-total-row"><span>Traslados</span><strong id="fac_total_transfer">—</strong></div><div class="cf-total-row"><span>Retenciones</span><strong id="fac_total_ret">—</strong></div>
      <div class="cf-total-row"><span>Importe comercial / ticket</span><strong>${money(venta.total)}</strong></div><div class="cf-total-row cf-total-main"><span>Total CFDI</span><strong id="fac_total_cfdi">—</strong></div><div id="fac_total_error" class="cf-danger"></div></div>
      <p class="cf-muted mt-2">Las retenciones reducen el importe fiscal por cobrar, no cambian la venta ni los movimientos de caja. Verifica su conciliación contable.</p>`;
        let activeEdit = null,
          editDraft = null,
          manuallyTouched = new Set();
        const popup = () => Swal.getPopup();
        const currentIssuer = () =>
          emisores.find(
            (x) =>
              Number(x.issuer_id) ===
              (issuerId || Number($("#fac_emisor").val())),
          ) || {};
        const eligibleISR = () => {
          const i = currentIssuer();
          return (
            String(i.rfc ?? i.Rfc ?? "").length === 13 &&
            String(
              i.fiscal_regime ?? i.FiscalRegime ?? i.regimen_fiscal ?? "",
            ) === "626" &&
            String($("#fac_rfc").val() || "").trim().length === 12
          );
        };
        const applySuggestions = () => {
          if (sustitucion) return;
          items.forEach((it) => {
            const id = String(it.id);
            if (manuallyTouched.has(id)) return;
            const tax = models[id].taxes.filter(
              (t) => !(t.name === "ISR" && Number(t.rate) === 0.0125),
            );
            if (
              eligibleISR() &&
              Number(it.sugerir_retencion_isr_resico) === 1 &&
              models[id].tax_object === "02"
            )
              tax.push({
                ...copy(TAXES.find((x) => x.id === "isr125")),
                base_mode: "neto",
              });
            models[id].taxes = tax;
          });
          refresh();
        };
        const refresh = () => {
          $("#fac_rows").html(
            items
              .map((it) => {
                const c = models[String(it.id)];
                let p;
                try {
                  p = previewOne(c);
                } catch (e) {
                  p = null;
                }
                return `<tr><td><strong>${esc(c.description)}</strong><br><span class="cf-muted">${esc(c.product_code)}</span></td><td>${Number(c.quantity).toFixed(3)}</td><td>${c.taxes.length} impuesto(s)</td><td><strong>${p ? money(p.total) : "Revisar"}</strong></td><td><button class="cf-act" type="button" data-edit="${Number(it.id)}">Editar</button></td></tr>`;
              })
              .join(""),
          );
          try {
            const t = previewAll(items, venta, models);
            $("#fac_total_sub").text(money(t.subtotal));
            $("#fac_total_discount").text("-" + money(t.desc));
            $("#fac_total_transfer").text(money(t.tras));
            $("#fac_total_ret").text("-" + money(t.ret));
            $("#fac_total_cfdi").text(money(t.total));
            $("#fac_total_error").text("");
            popup().fiscalValid = true;
          } catch (e) {
            popup().fiscalValid = false;
            $("#fac_total_cfdi").text("Revisar conceptos");
            $("#fac_total_error").text(e.message);
          }
        };
        const tab = (name) => {
          if (activeEdit !== null) return;
          $(".cf-tab").toggleClass("active", false);
          $(`.cf-tab[data-tab="${name}"]`).addClass("active");
          $("#fac_section_generales").toggle(name === "generales");
          $("#fac_section_conceptos").toggle(name === "conceptos");
        };
        const fillEditor = () => {
          const c = editDraft;
          $("#fac_e_sku").val(c.sku);
          $("#fac_e_description").val(c.description);
          $("#fac_e_product").empty().append(opcionesSAT([], c.product_code));
          $("#fac_e_unitcode").empty().append(opcionesSAT([], c.unit_code));
          $("#fac_e_details").val(c.details || "");
          $("#fac_e_tercero_rfc").val(c.third_party?.rfc || "");
          $("#fac_e_tercero_nombre").val(c.third_party?.name || "");
          $("#fac_e_tercero_regimen").val(c.third_party?.regime || "");
          $("#fac_e_tercero_cp").val(c.third_party?.zip || "");
          $("#fac_e_unit").val(c.unit);
          $("#fac_e_quantity").val(c.quantity);
          $("#fac_e_price").val(c.unit_price);
          $("#fac_e_discount").val(c.discount);
          $("#fac_e_object").val(c.tax_object);
          $("#fac_e_predial").val(c.predial || "");
          $("#fac_e_pedimentos").val((c.pedimentos || []).join("\n"));
            // reinicia acordeones y abre solo los que traen datos
            Object.keys(ACC_CAMPOS).forEach((id) => {
              const acc = document.getElementById(id);
              if (acc) acc.open = false;
            });
            actualizarAcordeones(true);
          $("#fac_e_product, #fac_e_unitcode").each(function () {
            if ($(this).data("selectpicker")) $(this).selectpicker("destroy");
          });
          inicializarSelectpicker($("#fac_editor"));
          $("#fac_e_product, #fac_e_unitcode").selectpicker("refresh");
          renderEditorTaxes();
          refreshEditorPreview();
        };
        const renderEditorTaxes = () => {
          $("#fac_e_taxes").html(
            editDraft.taxes.length
              ? editDraft.taxes
                  .map(
                    (
                      t,
                      j,
                    ) => `<div class="cf-tax"><div><strong style="font-size:13px">${esc(taxTitle(t))}</strong><div class="cf-muted">${esc(t.name)} · ${t.factor} · ${t.is_retention ? "Retención" : "Traslado"}</div></div><div class="d-flex gap-2 align-items-center">
      ${t.name === "IEPS" ? `<label class="cf-muted">${t.factor === "Cuota" ? "Cuota $/unidad" : "Tasa decimal"} <input class="cf-input" style="max-width:115px" type="number" step="0.000001" data-tax-rate="${j}" value="${Number(t.rate)}"></label>` : ""}
      ${t.name === "IVA" && !t.is_retention ? `<label class="cf-muted"><input type="checkbox" data-tax-ieps="${j}" ${t.base_mode === "neto_mas_ieps" ? "checked" : ""}> IVA sobre IEPS</label>` : ""}
      <button type="button" class="cf-btn" data-tax-remove="${j}" aria-label="Eliminar impuesto">×</button></div></div>`,
                  )
                  .join("")
              : '<p class="cf-muted">Sin impuestos. Si la clave es 02, revisa si corresponde el desglose.</p>',
          );
        };
        const refreshEditorPreview = () => {
          actualizarAcordeones(false);
          try {
            const c = readEditor(),
              result = previewOne(c);
            $("#fac_e_subtotal").text(money(result.subtotal));
            $("#fac_e_taxes_total").text(
              "+" + money(result.traslado) + " / −" + money(result.retencion),
            );
            $("#fac_e_concept_total").text(money(result.total));
          } catch (e) {
            $("#fac_e_concept_total").text("Revisar partida");
          }
        };
        const readEditor = () => {
          const d = editDraft;
          d.sku = String($("#fac_e_sku").val() || "").trim();
          d.description = String($("#fac_e_description").val() || "").trim();
          d.product_code = String($("#fac_e_product").val() || "");
          d.unit_code = String($("#fac_e_unitcode").val() || "");
          d.unit = String($("#fac_e_unit").val() || "").trim();
          d.quantity = Number($("#fac_e_quantity").val());
          d.unit_price = Number($("#fac_e_price").val());
          d.discount = Number($("#fac_e_discount").val());
          d.tax_object = String($("#fac_e_object").val());
          d.details = String($("#fac_e_details").val() || "").trim();
          const trfc = String($("#fac_e_tercero_rfc").val() || "").trim();
          d.third_party = trfc
            ? {
                rfc: trfc,
                name: String($("#fac_e_tercero_nombre").val() || "").trim(),
                regime: String($("#fac_e_tercero_regimen").val() || "").trim(),
                zip: String($("#fac_e_tercero_cp").val() || "").trim(),
              }
            : null;
          d.predial = String($("#fac_e_predial").val() || "").trim();
          d.pedimentos = String($("#fac_e_pedimentos").val() || "")
            .split("\n")
            .map((s) => s.trim())
            .filter(Boolean);
          return d;
        };
        const closeEditor = (save) => {
          if (save) {
            try {
              const e = readEditor();
              previewOne(e);
              if (
                !/^\d{8}$/.test(e.product_code) ||
                !e.unit_code ||
                !e.description
              )
                throw Error("Completa clave SAT, unidad y descripción");
              models[activeEdit] = copy(e);
              manuallyTouched.add(activeEdit);
            } catch (ex) {
              actualizarAcordeones(true);
              $("#fac_e_error").text(ex.message);
              return;
            }
          }
          activeEdit = null;
          editDraft = null;
          $("#fac_editor").hide();
          $("#fac_section_conceptos").show();
          $(".cf-tabs").show();
          Swal.getConfirmButton().disabled = false;
          refresh();
        };
        const adjustPrice = () => {
          try {
            const c = readEditor(),
              t = items.find((it) => String(it.id) === activeEdit),
              target = Number(t.importe);
            let lo = 0,
              hi = Math.max(
                1,
                Number(c.unit_price) * 3,
                (target / Number(c.quantity)) * 3,
              );
            for (let k = 0; k < 65; k++) {
              const m = (lo + hi) / 2;
              c.unit_price = rnd(m);
              const a = previewOne(c).gross;
              if (a < target) lo = m;
              else hi = m;
            }
            let found = false;
            for (let delta = -20; delta <= 20; delta++) {
              c.unit_price = rnd(hi + delta * 0.000001);
              if (
                c.unit_price > 0 &&
                cents(previewOne(c).gross) === cents(target)
              ) {
                found = true;
                break;
              }
            }
            if (!found)
              throw Error(
                "No se pudo conservar el importe exacto con la cantidad/descuento actual",
              );
            $("#fac_e_price").val(c.unit_price);
            $("#fac_e_error").text(
              "Precio ajustado; confirma los datos antes de guardar.",
            );
            refreshEditorPreview();
          } catch (e) {
            $("#fac_e_error").text(e.message);
          }
        };
        Swal.fire({
          title: `${sustitucion ? "Sustituir CFDI" : "Facturar ticket"} ${esc(venta.folio)}`,
          width: "1100px",
          customClass: { popup: "pikero-cfdi" },
          html,
          showCancelButton: true,
          confirmButtonText: sustitucion ? "Emitir sustituta" : "Emitir CFDI",
          cancelButtonText: "Cancelar",
          didOpen: () => {
            inicializarSelectpicker($("#fac_section_generales"));
            popup().fiscalValid = false;
            $("#fac_cliente").on("change", function () {
              const c = clientes.find(
                (x) => String(x.id) === String(this.value),
              );
              if (!c) return;
              $("#fac_rfc").val(c.rfc || "");
              $("#fac_razon").val(c.razon_social || c.nombre || "");
              $("#fac_cp").val(c.codigo_postal || "");
              $("#fac_regimen").selectpicker("val", c.regimen_fiscal || "");
              $("#fac_uso").selectpicker("val", c.uso_cfdi || "");
              $("#fac_correo").val(c.correo || "");
              applySuggestions();
            });
            $("#fac_rfc").on("change blur", applySuggestions);
            $("#fac_emisor").on("change", applySuggestions);
            const setExpedition = () => {
              const i = currentIssuer();
              const cp =
                i.codigo_postal ||
                i.tax_zip_code ||
                i.TaxZipCode ||
                i.postal_code ||
                venta.ferreteria_codigo_postal ||
                "";
              $("#fac_lugar").val(cp);
            };
            $("#fac_emisor").on("change", setExpedition);
            setExpedition();
            $("#fac_metodo").on("change", () => {
              if ($("#fac_metodo").val() === "PPD")
                $("#fac_forma").selectpicker("val", "99");
              else if ($("#fac_forma").val() === "99")
                $("#fac_forma").selectpicker("val", "01");
            });
            $(".cf-tab").on("click", function () {
              tab(this.dataset.tab);
            });
            $(popup()).on("click", "[data-edit]", function () {
              activeEdit = String($(this).data("edit"));
              editDraft = copy(models[activeEdit]);
              $("#fac_editor_title").text(
                "Editar concepto · " + editDraft.description,
              );
              $("#fac_section_conceptos").hide();
              $("#fac_section_generales").hide();
              $(".cf-tabs").hide();
              $("#fac_editor").show();
              Swal.getConfirmButton().disabled = true;
              $("#fac_e_error").text("");
              fillEditor();
            });
            $("#fac_e_save").on("click", () => closeEditor(true));
            $("#fac_editor_close").on("click", () => closeEditor(false));
            $("#fac_e_adjust").on("click", adjustPrice);
            $("#fac_e_add_btn").on("click", () => {
              const src = TAXES.find((x) => x.id === $("#fac_e_add_tax").val());
              if (!src) return;
              if (editDraft.taxes.some((x) => keyTax(x) === keyTax(src))) {
                $("#fac_e_error").text("Este impuesto ya está agregado");
                return;
              }
              editDraft.taxes.push({
                ...copy(src),
                base_mode: src.factor === "Cuota" ? "cantidad" : "neto",
              });
              renderEditorTaxes();
              refreshEditorPreview();
              $("#fac_e_error").text("");
            });
            $(popup()).on(
              "input change",
              "#fac_editor input, #fac_editor select, #fac_editor textarea",
              refreshEditorPreview,
            );
            $(popup()).on("click", "[data-tax-remove]", function () {
              editDraft.taxes.splice(Number($(this).data("tax-remove")), 1);
              renderEditorTaxes();
              refreshEditorPreview();
            });
            $(popup()).on("input", "[data-tax-rate]", function () {
              editDraft.taxes[Number($(this).data("tax-rate"))].rate = Number(
                this.value,
              );
              refreshEditorPreview();
            });
            $(popup()).on("change", "[data-tax-ieps]", function () {
              editDraft.taxes[Number($(this).data("tax-ieps"))].base_mode = this
                .checked
                ? "neto_mas_ieps"
                : "neto";
              refreshEditorPreview();
            });
            if (sustitucion) {
              $("#fac_rfc").val(venta.factura_rfc || "");
              $("#fac_razon").val(venta.factura_razon_social || "");
              $("#fac_cp").val(venta.factura_codigo_postal || "");
              $("#fac_regimen").selectpicker(
                "val",
                venta.factura_regimen_fiscal || "",
              );
              $("#fac_uso").selectpicker("val", venta.factura_uso_cfdi || "");
              $("#fac_correo").val(venta.factura_correo || "");
              $("#fac_metodo,#fac_forma")
                .prop("disabled", true)
                .selectpicker("refresh");
            }
            applySuggestions();
            refresh();
          },
          preConfirm: () => {
            if (activeEdit !== null) {
              Swal.showValidationMessage(
                "Guarda o cancela la edición del concepto",
              );
              return false;
            }
            const val = (id) => String($(id).val() ?? "").trim();
            const issuer = issuerId || Number($("#fac_emisor").val());
            const datos = {
              id: idVenta,
              issuer_id: issuer,
              rfc: val("#fac_rfc").toUpperCase(),
              razon_social: val("#fac_razon"),
              codigo_postal: val("#fac_cp"),
              regimen_fiscal: val("#fac_regimen"),
              uso_cfdi: val("#fac_uso"),
              correo: val("#fac_correo"),
              metodo_pago: val("#fac_metodo"),
              forma_pago: val("#fac_forma"),
              tipo_comprobante: val("#fac_tipo"),
              moneda: val("#fac_moneda"),
              exportacion: val("#fac_exportacion"),
              condiciones_pago: val("#fac_condiciones"),
              numero_orden: val("#fac_orden"),
              observaciones: val("#fac_observaciones"),
              conceptos_editados: copy(models),
            };
            if (!issuer) {
              Swal.showValidationMessage("Selecciona un emisor");
              return false;
            }
            if (!/^\d{5}$/.test(datos.codigo_postal)) {
              Swal.showValidationMessage("CP fiscal inválido");
              return false;
            }
            if (
              !datos.rfc ||
              !datos.razon_social ||
              !datos.regimen_fiscal ||
              !datos.uso_cfdi
            ) {
              Swal.showValidationMessage("Completa los datos fiscales");
              return false;
            }
            if (
              (datos.metodo_pago === "PPD" && datos.forma_pago !== "99") ||
              (datos.metodo_pago === "PUE" && datos.forma_pago === "99")
            ) {
              Swal.showValidationMessage(
                "Método y forma de pago incompatibles",
              );
              return false;
            }
            try {
              previewAll(items, venta, models);
            } catch (e) {
              Swal.showValidationMessage(e.message);
              tab("conceptos");
              return false;
            }
            return datos;
          },
        }).then((res) => {
          if (!res.isConfirmed) return;
          showLoading(
            sustitucion ? "Emitiendo sustitución..." : "Timbrando CFDI...",
          );
          $.ajax({
            url: `${BASE_URL}api/ventas.php?tipo=${sustitucion ? "sustituir_factura" : "facturar"}`,
            type: "POST",
            contentType: "application/json",
            data: JSON.stringify(res.value),
            dataType: "json",
          })
            .done((r) => {
              if (!r.estatus) {
                Swal.fire({
                  icon: "error",
                  title: "No se emitió el CFDI",
                  text: r.mensaje || "Revisa los conceptos",
                });
                return;
              }
              onSuccess?.(r);
              if (r.pendiente)
                Swal.fire({
                  icon: "info",
                  title: "Sustitución pendiente",
                  text: r.mensaje,
                });
              else abrirAccionesFactura(idVenta, res.value.correo);
            })
            .fail(() =>
              Swal.fire({
                icon: "warning",
                title: "Resultado incierto",
                text: "Consulta el estado de emisión antes de intentar de nuevo.",
              }),
            );
        });
      } catch (e) {
        Swal.fire({
          icon: "error",
          title: "No se pudo preparar el CFDI",
          text: e.message || String(e),
        });
      }
    })
    .fail(() =>
      Swal.fire({
        icon: "error",
        title: "No se pudieron cargar los datos fiscales",
      }),
    );
}
