import { initReportTable } from '../utils/report-table.js';
import Toast from '../utils/toast.js';

let tabla; 

function escaparHtmlInventario(valor) {
  return String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  }[caracter]));
}

function modalAjuste(id_producto, nombreProducto, existenciaActual) {
  Swal.fire({
    title: 'Ajuste de Stock: ' + nombreProducto,
    html: `
      <div class="text-start">
        <div class="alert alert-info py-2">
          Stock actual en esta sucursal: <strong>${existenciaActual}</strong>
        </div>
        <div class="mb-3">
          <label class="form-label">Tipo de Movimiento</label>
          <select id="ajuste_tipo" class="form-select">
            <option value="entrada">Entrada (+) - Compra menor / Devolución / Sobrante</option>
            <option value="salida">Salida (-) - Merma / Daño / Uso interno del negocio</option>
            <option value="ajuste_fijo">Conteo Físico (=) - Establecer cantidad exacta</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Cantidad *</label>
          <input id="ajuste_cantidad" type="number" step="0.01" class="form-control" placeholder="0">
        </div>
        <div class="mb-3">
          <label class="form-label">Motivo o Referencia *</label>
          <input id="ajuste_motivo" class="form-control" placeholder="Ej: Conteo físico mensual, merma en almacén">
        </div>
      </div>`,
    showCancelButton: true,
    confirmButtonText: 'Aplicar Ajuste',
    cancelButtonText: 'Cancelar',
    preConfirm: () => {
      const cantidad = parseFloat(document.getElementById('ajuste_cantidad').value);
      const motivo = document.getElementById('ajuste_motivo').value.trim();
      const tipo = document.getElementById('ajuste_tipo').value;

      if (isNaN(cantidad) || cantidad <= 0) {
        Swal.showValidationMessage('La cantidad debe ser mayor a 0');
        return false;
      }
      if (!motivo) {
        Swal.showValidationMessage('El motivo o referencia es obligatorio');
        return false;
      }

      return {
        id_producto,
        tipo,
        cantidad,
        motivo
      };
    }
  }).then((r) => {
    if (!r.isConfirmed) return;
    $.post(BASE_URL + 'api/inventario.php?tipo=ajustar', r.value, function (resp) {
      Toast.fire({ icon: resp.estatus ? 'success' : 'error', title: resp.mensaje });
      if (resp.estatus && tabla) {
        tabla.ajax.reload(false);
      }
    }, 'json');
  });
}

function modalKardex(id_producto, nombreProducto) {
  $.get(BASE_URL + 'api/inventario.php?tipo=movimientos&id_producto=' + id_producto, function (resp) {
    if (!resp.estatus) {
      Toast.fire({ icon: 'error', title: resp.mensaje });
      return;
    }

    let filas = '';
    if (!resp.data || resp.data.length === 0) {
      filas = '<tr><td colspan="6" class="text-center text-muted">Sin movimientos registrados</td></tr>';
    } else {
      resp.data.forEach((m) => {
        const badgeColor = m.tipo.includes('entrada') || m.tipo.includes('compra') ? 'bg-success' : (m.tipo.includes('salida') || m.tipo.includes('venta') ? 'bg-danger' : 'bg-primary');
        filas += `
          <tr>
            <td><small>${m.fecha_registro}</small></td>
            <td><span class="badge ${badgeColor}">${m.tipo}</span></td>
            <td>${parseFloat(m.cantidad).toFixed(2)}</td>
            <td>${parseFloat(m.existencia_antes).toFixed(2)}</td>
            <td><strong>${parseFloat(m.existencia_despues).toFixed(2)}</strong></td>
            <td><small>${m.referencia || '-'}</small></td>
          </tr>`;
      });
    }

    Swal.fire({
      title: 'Kardex: ' + nombreProducto,
      width: '800px',
      html: `
        <div class="table-responsive text-start" style="max-height: 380px;">
          <table class="table table-sm table-striped">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Cant.</th>
                <th>Antes</th>
                <th>Después</th>
                <th>Referencia</th>
              </tr>
            </thead>
            <tbody>
              ${filas}
            </tbody>
          </table>
        </div>`,
      confirmButtonText: 'Cerrar'
    });
  }, 'json');
}

window.ajustarStock = (id, nombre, stock) => modalAjuste(id, decodeURIComponent(nombre), stock);
window.verKardex = (id, nombre) => modalKardex(id, decodeURIComponent(nombre));

let categoriasList = [];
let marcasList = [];
let unidadesList = [];

async function cargarCatalogosInventario() {
  const [categorias, marcas, unidades] = await Promise.all([
    $.getJSON(BASE_URL + 'api/catalogos.php?tipo=combo&catalogo=categorias'),
    $.getJSON(BASE_URL + 'api/catalogos.php?tipo=combo&catalogo=marcas'),
    $.getJSON(BASE_URL + 'api/catalogos.php?tipo=combo&catalogo=unidades')
  ]);
  if (categorias.estatus) categoriasList = categorias.data;
  if (marcas.estatus) marcasList = marcas.data;
  if (unidades.estatus) unidadesList = unidades.data;
}

function generarOpcionesInventario(lista, seleccionado) {
  let opts = '<option value="">-- Seleccionar --</option>';
  lista.forEach((item) => {
    const sel = String(item.id) === String(seleccionado) ? 'selected' : '';
    const abreviatura = item.abreviatura ? ` (${escaparHtmlInventario(item.abreviatura)})` : '';
    opts += `<option value="${escaparHtmlInventario(item.id)}" ${sel}>${escaparHtmlInventario(item.nombre)}${abreviatura}</option>`;
  });
  return opts;
}


function modalEditarProductoInventario(producto) {
  // Compara valores que pueden venir como número o string desde MySQL ("0.160000" vs "0.16")
  const igual = (a, b) => {
    const vacio = (v) => v === '' || v === null || v === undefined;
    if (!vacio(a) && !vacio(b) && Number.isFinite(Number(a)) && Number.isFinite(Number(b))) {
      return Number(a) === Number(b);
    }
    return String(a) === String(b);
  };
  const sel = (actual, valor) => (igual(actual, valor) ? 'selected' : '');
 
  // Valores actuales (con los mismos defaults que el modal de nuevo producto)
  const costoIncluyeIva = producto.costo_incluye_iva ?? 0;
  const tasaIvaCosto = producto.tasa_iva_costo ?? 0.16;
  const ivaAcreditable = producto.costo_iva_acreditable ?? 0;
  const tipoIvaVenta = producto.tipo_iva_venta ?? 0.16;
  const ventaIncluyeIva = producto.precio_venta_incluye_iva ?? 1;
  const satProducto = producto.sat_producto_servicio ?? producto.clave_sat ?? '01010101';
  const satUnidad = producto.sat_unidad ?? producto.codigo_unidad_sat ?? 'H87';
  const satObjeto = producto.sat_objeto_impuesto ?? producto.objeto_impuesto ?? '02';
  const stockMinimo = producto.minimo ?? producto.stock_minimo ?? 5;
 
  const html = `
<div class="inv-form text-start">
  <div class="accordion" id="invEditAccordion">
 
    <!-- ========== 1. DATOS GENERALES ========== -->
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button" type="button"
                data-bs-toggle="collapse" data-bs-target="#invEdSecGeneral"
                aria-expanded="true" aria-controls="invEdSecGeneral">
          <span class="sec-icon"><i class="bi bi-box-seam"></i></span>
          Datos generales
          <span class="badge text-bg-danger badge-req ms-auto me-2">Requerido</span>
        </button>
      </h2>
      <div id="invEdSecGeneral" class="accordion-collapse collapse show">
        <div class="accordion-body">
          <div class="row g-3">
            <div class="col-12 col-md-5">
              <label class="form-label" for="inv_editar_codigo">Código / SKU</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                <input id="inv_editar_codigo" class="form-control" placeholder="Ej: T-001"
                       value="${escaparHtmlInventario(producto.codigo || '')}">
              </div>
            </div>
            <div class="col-12 col-md-7">
              <label class="form-label" for="inv_editar_nombre">Nombre del producto *</label>
              <input id="inv_editar_nombre" class="form-control"
                     placeholder="Ej: Taladro inalámbrico 18V"
                     value="${escaparHtmlInventario(producto.nombre || '')}">
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
              <label class="form-label" for="inv_editar_categoria">Categoría</label>
              <select id="inv_editar_categoria" class="form-select">
                ${generarOpcionesInventario(categoriasList, producto.id_categoria)}
              </select>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
              <label class="form-label" for="inv_editar_marca">Marca</label>
              <select id="inv_editar_marca" class="form-select">
                ${generarOpcionesInventario(marcasList, producto.id_marca)}
              </select>
            </div>
            <div class="col-12 col-lg-4">
              <label class="form-label" for="inv_editar_unidad">Unidad de inventario</label>
              <select id="inv_editar_unidad" class="form-select">
                ${generarOpcionesInventario(unidadesList, producto.id_unidad)}
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>
 
    <!-- ========== 2. COMPRA E IMPUESTOS ========== -->
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button"
                data-bs-toggle="collapse" data-bs-target="#invEdSecCompra"
                aria-expanded="false" aria-controls="invEdSecCompra">
          <span class="sec-icon"><i class="bi bi-cart-check"></i></span>
          Compra e impuestos
        </button>
      </h2>
      <div id="invEdSecCompra" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label" for="inv_editar_compra">Costo de compra *</label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input id="inv_editar_compra" type="number" min="0"
                       step="0.000001" class="form-control"
                       value="${escaparHtmlInventario(producto.precio_compra ?? 0)}">
              </div>
              <div class="form-text">Costo unitario capturado.</div>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label" for="inv_editar_costo_incluye_iva">¿El costo incluye IVA?</label>
              <select id="inv_editar_costo_incluye_iva" class="form-select">
                <option value="0" ${sel(costoIncluyeIva, 0)}>No, costo antes de IVA</option>
                <option value="1" ${sel(costoIncluyeIva, 1)}>Sí, costo con IVA incluido</option>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label" for="inv_editar_tasa_iva_costo">Tasa de IVA de compra</label>
              <select id="inv_editar_tasa_iva_costo" class="form-select">
                <option value="0" ${sel(tasaIvaCosto, 0)}>0%</option>
                <option value="0.08" ${sel(tasaIvaCosto, 0.08)}>8%</option>
                <option value="0.16" ${sel(tasaIvaCosto, 0.16)}>16%</option>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label" for="inv_editar_costo_iva_acreditable">IVA de compra acreditable</label>
              <select id="inv_editar_costo_iva_acreditable" class="form-select">
                <option value="0" ${sel(ivaAcreditable, 0)}>No / no confirmado</option>
                <option value="1" ${sel(ivaAcreditable, 1)}>Sí, es acreditable</option>
              </select>
            </div>
            <div class="col-12">
              <div class="preview-box">
                <div class="row-prev">
                  <span><i class="bi bi-calculator me-1"></i>Costo neto estimado para utilidad</span>
                  <strong id="inv_editar_costo_neto_preview">$0.00</strong>
                </div>
                <small class="text-muted">
                  Si el IVA es acreditable, se excluye del costo.
                  Si no, el costo capturado se conserva como costo operativo.
                </small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
 
    <!-- ========== 3. VENTA E IMPUESTOS ========== -->
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button"
                data-bs-toggle="collapse" data-bs-target="#invEdSecVenta"
                aria-expanded="false" aria-controls="invEdSecVenta">
          <span class="sec-icon"><i class="bi bi-cash-coin"></i></span>
          Venta e impuestos
        </button>
      </h2>
      <div id="invEdSecVenta" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label" for="inv_editar_venta">Precio de venta *</label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input id="inv_editar_venta" type="number" min="0"
                       step="0.01" class="form-control"
                       value="${escaparHtmlInventario(producto.precio_venta ?? 0)}">
              </div>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label" for="inv_editar_tipo_iva_venta">Tratamiento fiscal de venta</label>
              <select id="inv_editar_tipo_iva_venta" class="form-select">
                <option value="0.16" ${sel(tipoIvaVenta, 0.16)}>IVA 16%</option>
                <option value="0.08" ${sel(tipoIvaVenta, 0.08)}>IVA 8%</option>
                <option value="0" ${sel(tipoIvaVenta, 0)}>Tasa 0%</option>
                <option value="exento" ${sel(tipoIvaVenta, 'exento')}>Exento de IVA</option>
                <option value="no_objeto" ${sel(tipoIvaVenta, 'no_objeto')}>No objeto de impuesto</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" for="inv_editar_venta_incluye_iva">¿El precio de venta incluye IVA?</label>
              <select id="inv_editar_venta_incluye_iva" class="form-select">
                <option value="1" ${sel(ventaIncluyeIva, 1)}>Sí, precio final con IVA</option>
                <option value="0" ${sel(ventaIncluyeIva, 0)}>No, agregar IVA al precio</option>
              </select>
            </div>
            <div class="col-12">
              <div class="preview-box">
                <div class="row-prev">
                  <span>Precio base (sin IVA)</span>
                  <strong id="inv_editar_precio_neto_preview">$0.00</strong>
                </div>
                <div class="row-prev">
                  <span>IVA estimado por unidad</span>
                  <strong id="inv_editar_iva_venta_preview">$0.00</strong>
                </div>
                <div class="row-prev">
                  <span>Precio final al cliente</span>
                  <strong id="inv_editar_precio_final_preview">$0.00</strong>
                </div>
                <div class="row-prev border-top mt-1 pt-2">
                  <span class="fw-semibold">Utilidad bruta estimada por unidad</span>
                  <strong id="inv_editar_utilidad_preview">$0.00</strong>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
 
    <!-- ========== 4. FACTURACIÓN SAT ========== -->
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button"
                data-bs-toggle="collapse" data-bs-target="#invEdSecSat"
                aria-expanded="false" aria-controls="invEdSecSat">
          <span class="sec-icon"><i class="bi bi-receipt"></i></span>
          Datos para facturación (Facturama / SAT)
        </button>
      </h2>
      <div id="invEdSecSat" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="alert alert-info py-2 small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Utiliza las claves oficiales del catálogo SAT. No son el SKU
            interno ni necesariamente coinciden con la unidad de inventario.
          </div>
          <div class="row g-3">
            <div class="col-12 col-sm-6">
              <label class="form-label" for="inv_editar_sat_producto">Clave de producto/servicio SAT *</label>
              <input id="inv_editar_sat_producto" class="form-control"
                     maxlength="8" placeholder="Ej: clave de 8 dígitos"
                     value="${escaparHtmlInventario(satProducto)}">
            </div>
            <div class="col-12 col-sm-6">
              <label class="form-label" for="inv_editar_sat_unidad">Clave de unidad SAT *</label>
              <input id="inv_editar_sat_unidad" class="form-control"
                     maxlength="3" placeholder="Ej: H87"
                     value="${escaparHtmlInventario(satUnidad)}">
            </div>
            <div class="col-12 col-sm-6">
              <label class="form-label" for="inv_editar_sat_descripcion">Descripción para factura</label>
              <input id="inv_editar_sat_descripcion" class="form-control"
                     maxlength="1000" placeholder="Descripción del concepto"
                     value="${escaparHtmlInventario(producto.sat_descripcion || '')}">
              <div class="form-text">
                Si se deja vacía, se utilizará el nombre del producto.
              </div>
            </div>
            <div class="col-12 col-sm-6">
              <label class="form-label" for="inv_editar_sat_unidad_texto">Unidad SAT (texto)</label>
              <input id="inv_editar_sat_unidad_texto" class="form-control"
                     maxlength="20" placeholder="Ej: Pieza"
                     value="${escaparHtmlInventario(producto.sat_unidad_texto || '')}">
            </div>
            <div class="col-12">
              <label class="form-label" for="inv_editar_sat_objeto_impuesto">Objeto de impuesto SAT</label>
              <select id="inv_editar_sat_objeto_impuesto" class="form-select">
                <option value="02" ${sel(satObjeto, '02')}>02 - Sí objeto de impuesto</option>
                <option value="01" ${sel(satObjeto, '01')}>01 - No objeto de impuesto</option>
                <option value="03" ${sel(satObjeto, '03')}>03 - Sí objeto del impuesto y no obligado al desglose</option>
                <option value="04" ${sel(satObjeto, '04')}>04 - Sí objeto del impuesto y no causa impuesto</option>
              </select>
              <div class="form-text">
                Debe corresponder al tratamiento fiscal real del concepto.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
 
    <!-- ========== 5. EXISTENCIAS ========== -->
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button"
                data-bs-toggle="collapse" data-bs-target="#invEdSecStock"
                aria-expanded="false" aria-controls="invEdSecStock">
          <span class="sec-icon"><i class="bi bi-boxes"></i></span>
          Existencias
        </button>
      </h2>
      <div id="invEdSecStock" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label" for="inv_editar_minimo">Stock mínimo</label>
              <input id="inv_editar_minimo" type="number" min="0"
                     step="0.01" class="form-control"
                     value="${escaparHtmlInventario(stockMinimo)}">
              <div class="form-text">
                Se usa para alertas de bajo inventario. Para cambiar las
                existencias usa el botón <strong>Ajustar</strong> de la tabla.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
 
  </div>
</div>
`;
 
  Swal.fire({
    title: 'Editar producto',
    width: '800px',
    html,
    showCancelButton: true,
    confirmButtonText: 'Guardar cambios',
    cancelButtonText: 'Cancelar',
    focusConfirm: false,
 
    didOpen: () => {
      const $id = (id) => document.getElementById(id);
 
      const dinero = (valor) =>
        new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(valor);
 
      const numero = (id) => {
        const valor = parseFloat($id(id).value);
        return Number.isFinite(valor) ? valor : 0;
      };
 
      function calcular() {
        const costo = Math.max(0, numero('inv_editar_compra'));
        const precio = Math.max(0, numero('inv_editar_venta'));
 
        const incluyeIvaCosto = $id('inv_editar_costo_incluye_iva').value === '1';
        const esAcreditable = $id('inv_editar_costo_iva_acreditable').value === '1';
        const tasaCosto = numero('inv_editar_tasa_iva_costo');
 
        // Solo se excluye el IVA del costo cuando se considera
        // acreditable y el importe capturado ya lo incluye.
        const costoNeto = incluyeIvaCosto && esAcreditable
          ? costo / (1 + tasaCosto)
          : costo;
 
        const tipoIva = $id('inv_editar_tipo_iva_venta').value;
        const tasaVenta = tipoIva === 'exento' || tipoIva === 'no_objeto' ? 0 : Number(tipoIva);
        const incluyeIvaVenta = $id('inv_editar_venta_incluye_iva').value === '1';
 
        const precioNeto = incluyeIvaVenta ? precio / (1 + tasaVenta) : precio;
        const ivaVenta = precioNeto * tasaVenta;
        const precioFinal = precioNeto + ivaVenta;
        const utilidad = precioNeto - costoNeto;
 
        $id('inv_editar_costo_neto_preview').textContent = dinero(costoNeto);
        $id('inv_editar_precio_neto_preview').textContent = dinero(precioNeto);
        $id('inv_editar_iva_venta_preview').textContent = dinero(ivaVenta);
        $id('inv_editar_precio_final_preview').textContent = dinero(precioFinal);
        $id('inv_editar_utilidad_preview').textContent = dinero(utilidad);
        $id('inv_editar_utilidad_preview').className = utilidad < 0 ? 'text-danger' : 'text-success';
      }
 
      [
        'inv_editar_compra',
        'inv_editar_venta',
        'inv_editar_costo_incluye_iva',
        'inv_editar_tasa_iva_costo',
        'inv_editar_costo_iva_acreditable',
        'inv_editar_tipo_iva_venta',
        'inv_editar_venta_incluye_iva'
      ].forEach((id) => {
        $id(id).addEventListener('input', calcular);
        $id(id).addEventListener('change', calcular);
      });
 
      calcular();
    },
 
    preConfirm: () => {
      const $id = (id) => document.getElementById(id);
      const nombre = $id('inv_editar_nombre').value.trim();
      const precioCompra = parseFloat($id('inv_editar_compra').value);
      const precioVenta = parseFloat($id('inv_editar_venta').value);
      const minimo = parseFloat($id('inv_editar_minimo').value);
 
      if (!nombre) {
        Swal.showValidationMessage('El nombre del producto es obligatorio');
        return false;
      }
 
      if (!Number.isFinite(precioVenta) || precioVenta < 0) {
        Swal.showValidationMessage('El precio de venta no es válido');
        return false;
      }
 
      if (!Number.isFinite(precioCompra) || precioCompra < 0) {
        Swal.showValidationMessage('El costo de compra no es válido');
        return false;
      }
 
      if (!Number.isFinite(minimo) || minimo < 0) {
        Swal.showValidationMessage('El stock mínimo debe ser válido');
        return false;
      }
 
      const productoSAT = $id('inv_editar_sat_producto').value.trim();
      const unidadSAT = $id('inv_editar_sat_unidad').value.trim();
 
      if (!/^\d{8}$/.test(productoSAT)) {
        Swal.showValidationMessage('Captura la clave SAT de producto/servicio de 8 dígitos');
        return false;
      }
 
      if (!unidadSAT) {
        Swal.showValidationMessage('Captura la clave de unidad del catálogo SAT');
        return false;
      }
 
      const objetoImpuesto = $id('inv_editar_sat_objeto_impuesto').value;
 
      return {
        id: producto.id,
        codigo: $id('inv_editar_codigo').value.trim(),
        nombre,
        id_categoria: $id('inv_editar_categoria').value || '',
        id_marca: $id('inv_editar_marca').value || '',
        id_unidad: $id('inv_editar_unidad').value || '',
 
        // Costos
        precio_compra: precioCompra,
        costo_incluye_iva: Number($id('inv_editar_costo_incluye_iva').value),
        tasa_iva_costo: Number($id('inv_editar_tasa_iva_costo').value),
        costo_iva_acreditable: Number($id('inv_editar_costo_iva_acreditable').value),
 
        // Precio y tratamiento fiscal de venta
        precio_venta: precioVenta,
        precio_venta_incluye_iva: Number($id('inv_editar_venta_incluye_iva').value),
        tipo_iva_venta: $id('inv_editar_tipo_iva_venta').value,
 
        // Campos SAT / Facturama
        sat_producto_servicio: productoSAT,
        sat_unidad: unidadSAT,
        sat_unidad_texto: $id('inv_editar_sat_unidad_texto').value.trim(),
        sat_descripcion: $id('inv_editar_sat_descripcion').value.trim() || nombre,
        sat_objeto_impuesto: objetoImpuesto,
 
        // Campos SAT con los nombres anteriores (compatibilidad con actualizar)
        clave_sat: productoSAT,
        codigo_unidad_sat: unidadSAT,
        objeto_impuesto: objetoImpuesto,
 
        minimo
      };
    }
  }).then((resultado) => {
    if (!resultado.isConfirmed) return;
 
    $.post(BASE_URL + 'api/productos.php?tipo=actualizar', resultado.value, function (respuesta) {
      Toast.fire({ icon: respuesta.estatus ? 'success' : 'error', title: respuesta.mensaje });
      if (respuesta.estatus && tabla) tabla.ajax.reload(false);
    }, 'json').fail((xhr) => {
      Toast.fire({ icon: 'error', title: 'No se pudo actualizar el producto' });
      console.error(xhr.responseText);
    });
  });
}

window.editarProductoDesdeInventario = async (id) => {
  try {
    const [respuesta] = await Promise.all([
      $.post(BASE_URL + 'api/productos.php?tipo=obtener', { id }, null, 'json'),
      cargarCatalogosInventario()
    ]);
    if (!respuesta.estatus) {
      Toast.fire({ icon: 'error', title: respuesta.mensaje });
      return;
    }
    modalEditarProductoInventario(respuesta.data);
  } catch (error) {
    Toast.fire({ icon: 'error', title: 'No se pudo cargar el producto o sus catálogos' });
  }
};


function modalNuevoProductoInventario() {
  const html = `


<div class="inv-form text-start">
  <div class="accordion" id="invAccordion">

    <!-- ========== 1. DATOS GENERALES ========== -->
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button" type="button"
                data-bs-toggle="collapse" data-bs-target="#invSecGeneral"
                aria-expanded="true" aria-controls="invSecGeneral">
          <span class="sec-icon"><i class="bi bi-box-seam"></i></span>
          Datos generales
          <span class="badge text-bg-danger badge-req ms-auto me-2">Requerido</span>
          </button>
          </h2>
   
      <div id="invSecGeneral" class="accordion-collapse collapse show">
        <div class="accordion-body">
          <div class="row g-3">
            <div class="col-12 col-md-5">
              <label class="form-label" for="inv_prod_codigo">Código / SKU</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                <input id="inv_prod_codigo" class="form-control" placeholder="Ej: T-001">
              </div>
            </div>
            <div class="col-12 col-md-7">
              <label class="form-label" for="inv_prod_nombre">Nombre del producto *</label>
              <input id="inv_prod_nombre" class="form-control"
                     placeholder="Ej: Taladro inalámbrico 18V">
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
              <label class="form-label" for="inv_prod_categoria">Categoría</label>
              <select id="inv_prod_categoria" class="form-select">
                ${generarOpcionesInventario(categoriasList, '')}
              </select>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
              <label class="form-label" for="inv_prod_marca">Marca</label>
              <select id="inv_prod_marca" class="form-select">
                ${generarOpcionesInventario(marcasList, '')}
              </select>
            </div>
            <div class="col-12 col-lg-4">
              <label class="form-label" for="inv_prod_unidad">Unidad de inventario</label>
              <select id="inv_prod_unidad" class="form-select">
                ${generarOpcionesInventario(unidadesList, '')}
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ========== 2. COMPRA E IMPUESTOS ========== -->
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button"
                data-bs-toggle="collapse" data-bs-target="#invSecCompra"
                aria-expanded="false" aria-controls="invSecCompra">
          <span class="sec-icon"><i class="bi bi-cart-check"></i></span>
          Compra e impuestos
        </button>
      </h2>
      <div id="invSecCompra" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label" for="inv_prod_compra">Costo de compra *</label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input id="inv_prod_compra" type="number" min="0"
                       step="0.000001" class="form-control" value="0">
              </div>
              <div class="form-text">Costo unitario capturado.</div>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label" for="inv_costo_incluye_iva">¿El costo incluye IVA?</label>
              <select id="inv_costo_incluye_iva" class="form-select">
                <option value="0">No, costo antes de IVA</option>
                <option value="1">Sí, costo con IVA incluido</option>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label" for="inv_tasa_iva_costo">Tasa de IVA de compra</label>
              <select id="inv_tasa_iva_costo" class="form-select">
                <option value="0">0%</option>
                <option value="0.08">8%</option>
                <option value="0.16" selected>16%</option>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label" for="inv_costo_iva_acreditable">IVA de compra acreditable</label>
              <select id="inv_costo_iva_acreditable" class="form-select">
                <option value="0">No / no confirmado</option>
                <option value="1">Sí, es acreditable</option>
              </select>
            </div>
            <div class="col-12">
              <div class="preview-box">
                <div class="row-prev">
                  <span><i class="bi bi-calculator me-1"></i>Costo neto estimado para utilidad</span>
                  <strong id="inv_costo_neto_preview">$0.00</strong>
                </div>
                <small class="text-muted">
                  Si el IVA es acreditable, se excluye del costo.
                  Si no, el costo capturado se conserva como costo operativo.
                </small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ========== 3. VENTA E IMPUESTOS ========== -->
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button"
                data-bs-toggle="collapse" data-bs-target="#invSecVenta"
                aria-expanded="false" aria-controls="invSecVenta">
          <span class="sec-icon"><i class="bi bi-cash-coin"></i></span>
          Venta e impuestos
        </button>
      </h2>
      <div id="invSecVenta" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label" for="inv_prod_venta">Precio de venta *</label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input id="inv_prod_venta" type="number" min="0"
                       step="0.01" class="form-control" value="0">
              </div>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label" for="inv_tipo_iva_venta">Tratamiento fiscal de venta</label>
              <select id="inv_tipo_iva_venta" class="form-select">
                <option value="0.16" selected>IVA 16%</option>
                <option value="0.08">IVA 8%</option>
                <option value="0">Tasa 0%</option>
                <option value="exento">Exento de IVA</option>
                <option value="no_objeto">No objeto de impuesto</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" for="inv_venta_incluye_iva">¿El precio de venta incluye IVA?</label>
              <select id="inv_venta_incluye_iva" class="form-select">
                <option value="1" selected>Sí, precio final con IVA</option>
                <option value="0">No, agregar IVA al precio</option>
              </select>
            </div>
            <div class="col-12">
              <div class="preview-box">
                <div class="row-prev">
                  <span>Precio base (sin IVA)</span>
                  <strong id="inv_precio_neto_preview">$0.00</strong>
                </div>
                <div class="row-prev">
                  <span>IVA estimado por unidad</span>
                  <strong id="inv_iva_venta_preview">$0.00</strong>
                </div>
                <div class="row-prev">
                  <span>Precio final al cliente</span>
                  <strong id="inv_precio_final_preview">$0.00</strong>
                </div>
                <div class="row-prev border-top mt-1 pt-2">
                  <span class="fw-semibold">Utilidad bruta estimada por unidad</span>
                  <strong id="inv_utilidad_preview">$0.00</strong>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ========== 4. FACTURACIÓN SAT ========== -->
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button"
                data-bs-toggle="collapse" data-bs-target="#invSecSat"
                aria-expanded="false" aria-controls="invSecSat">
          <span class="sec-icon"><i class="bi bi-receipt"></i></span>
          Datos para facturación (Facturama / SAT)
        </button>
      </h2>
      <div id="invSecSat" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="alert alert-info py-2 small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Utiliza las claves oficiales del catálogo SAT. No son el SKU
            interno ni necesariamente coinciden con la unidad de inventario.
          </div>
          <div class="row g-3">
            <div class="col-12 col-sm-6">
              <label class="form-label" for="inv_sat_producto">Clave de producto/servicio SAT *</label>
              <input id="inv_sat_producto" class="form-control"
                     maxlength="8" placeholder="Ej: clave de 8 dígitos">
            </div>
            <div class="col-12 col-sm-6">
              <label class="form-label" for="inv_sat_unidad">Clave de unidad SAT *</label>
              <input id="inv_sat_unidad" class="form-control"
                     maxlength="3" placeholder="Ej: H87">
            </div>
            <div class="col-12 col-sm-6">
              <label class="form-label" for="inv_sat_descripcion">Descripción para factura</label>
              <input id="inv_sat_descripcion" class="form-control"
                     maxlength="1000" placeholder="Descripción del concepto">
              <div class="form-text">
                Si se deja vacía, se utilizará el nombre del producto.
              </div>
            </div>
            <div class="col-12 col-sm-6">
              <label class="form-label" for="inv_sat_unidad_texto">Unidad SAT (texto)</label>
              <input id="inv_sat_unidad_texto" class="form-control"
                     maxlength="20" placeholder="Ej: Pieza">
            </div>
            <div class="col-12">
              <label class="form-label" for="inv_sat_objeto_impuesto">Objeto de impuesto SAT</label>
              <select id="inv_sat_objeto_impuesto" class="form-select">
                <option value="02" selected>02 - Sí objeto de impuesto</option>
                <option value="01">01 - No objeto de impuesto</option>
                <option value="03">03 - Sí objeto del impuesto y no obligado al desglose</option>
                <option value="04">04 - Sí objeto del impuesto y no causa impuesto</option>
              </select>
              <div class="form-text">
                Debe corresponder al tratamiento fiscal real del concepto.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ========== 5. EXISTENCIAS ========== -->
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button"
                data-bs-toggle="collapse" data-bs-target="#invSecStock"
                aria-expanded="false" aria-controls="invSecStock">
          <span class="sec-icon"><i class="bi bi-boxes"></i></span>
          Existencias
        </button>
      </h2>
      <div id="invSecStock" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="row g-3">
            <div class="col-12 col-sm-6">
              <label class="form-label" for="inv_prod_stock">Stock inicial</label>
              <input id="inv_prod_stock" type="number" min="0"
                     step="0.01" class="form-control" value="0">
            </div>
            <div class="col-12 col-sm-6">
              <label class="form-label" for="inv_prod_minimo">Stock mínimo</label>
              <input id="inv_prod_minimo" type="number" min="0"
                     step="0.01" class="form-control" value="5">
              <div class="form-text">Se usa para alertas de bajo inventario.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
`;

  Swal.fire({
    title: 'Agregar Producto al Inventario',
    width: '800px',
    html,
    showCancelButton: true,
    confirmButtonText: 'Guardar producto',
    cancelButtonText: 'Cancelar',
    focusConfirm: false,

    didOpen: () => {
      const $id = id => document.getElementById(id);

      const dinero = valor =>
        new Intl.NumberFormat('es-MX', {
          style: 'currency',
          currency: 'MXN'
        }).format(valor);

      const numero = id => {
        const valor = parseFloat($id(id).value);
        return Number.isFinite(valor) ? valor : 0;
      };

      function calcular() {
        const costo = Math.max(0, numero('inv_prod_compra'));
        const precio = Math.max(0, numero('inv_prod_venta'));

        const incluyeIvaCosto =
          $id('inv_costo_incluye_iva').value === '1';

        const ivaAcreditable =
          $id('inv_costo_iva_acreditable').value === '1';

        const tasaCosto = numero('inv_tasa_iva_costo');

        // Solo se excluye el IVA del costo cuando se considera
        // acreditable y el importe capturado ya lo incluye.
        const costoNeto =
          incluyeIvaCosto && ivaAcreditable
            ? costo / (1 + tasaCosto)
            : costo;

        const tipoIva = $id('inv_tipo_iva_venta').value;
        const tasaVenta =
          tipoIva === 'exento' || tipoIva === 'no_objeto'
            ? 0
            : Number(tipoIva);

        const incluyeIvaVenta =
          $id('inv_venta_incluye_iva').value === '1';

        const precioNeto =
          incluyeIvaVenta
            ? precio / (1 + tasaVenta)
            : precio;

        const ivaVenta = precioNeto * tasaVenta;
        const utilidad = precioNeto - costoNeto;

        $id('inv_costo_neto_preview').textContent =
          dinero(costoNeto);

        $id('inv_precio_neto_preview').textContent =
          dinero(precioNeto);

        $id('inv_iva_venta_preview').textContent =
          dinero(ivaVenta);

        $id('inv_utilidad_preview').textContent =
          dinero(utilidad);

        $id('inv_utilidad_preview').className =
          utilidad < 0 ? 'text-danger' : 'text-success';
      }

      [
        'inv_prod_compra',
        'inv_prod_venta',
        'inv_costo_incluye_iva',
        'inv_tasa_iva_costo',
        'inv_costo_iva_acreditable',
        'inv_tipo_iva_venta',
        'inv_venta_incluye_iva'
      ].forEach(id => {
        $id(id).addEventListener('input', calcular);
        $id(id).addEventListener('change', calcular);
      });

      calcular();
    },

    preConfirm: () => {
      const $id = id => document.getElementById(id);
      const nombre = $id('inv_prod_nombre').value.trim();
      const precioVenta = parseFloat($id('inv_prod_venta').value);
      const precioCompra = parseFloat($id('inv_prod_compra').value);
      const stock = parseFloat($id('inv_prod_stock').value);
      const minimo = parseFloat($id('inv_prod_minimo').value);

      if (!nombre) {
        Swal.showValidationMessage(
          'El nombre del producto es obligatorio'
        );
        return false;
      }

      if (!Number.isFinite(precioVenta) || precioVenta < 0) {
        Swal.showValidationMessage('El precio de venta no es válido');
        return false;
      }

      if (!Number.isFinite(precioCompra) || precioCompra < 0) {
        Swal.showValidationMessage('El costo de compra no es válido');
        return false;
      }

      if (!Number.isFinite(stock) || stock < 0 ||
          !Number.isFinite(minimo) || minimo < 0) {
        Swal.showValidationMessage(
          'El stock y el stock mínimo deben ser válidos'
        );
        return false;
      }

      const productoSAT = $id('inv_sat_producto').value.trim();
      const unidadSAT = $id('inv_sat_unidad').value.trim();

      if (!/^\d{8}$/.test(productoSAT)) {
        Swal.showValidationMessage(
          'Captura la clave SAT de producto/servicio de 8 dígitos'
        );
        return false;
      }

      if (!unidadSAT) {
        Swal.showValidationMessage(
          'Captura la clave de unidad del catálogo SAT'
        );
        return false;
      }

      return {
        codigo: $id('inv_prod_codigo').value.trim(),
        nombre,
        id_categoria: $id('inv_prod_categoria').value || null,
        id_marca: $id('inv_prod_marca').value || null,
        id_unidad: $id('inv_prod_unidad').value || null,

        // Inventario y costos
        precio_compra: precioCompra,
        costo_incluye_iva:
          Number($id('inv_costo_incluye_iva').value),
        tasa_iva_costo:
          Number($id('inv_tasa_iva_costo').value),
        costo_iva_acreditable:
          Number($id('inv_costo_iva_acreditable').value),

        // Precio y tratamiento fiscal de venta
        precio_venta: precioVenta,
        precio_venta_incluye_iva:
          Number($id('inv_venta_incluye_iva').value),
        tipo_iva_venta: $id('inv_tipo_iva_venta').value,

        // Campos SAT / Facturama
        sat_producto_servicio: productoSAT,
        sat_unidad: unidadSAT,
        sat_unidad_texto:
          $id('inv_sat_unidad_texto').value.trim(),
        sat_descripcion:
          $id('inv_sat_descripcion').value.trim() || nombre,
        sat_objeto_impuesto:
          $id('inv_sat_objeto_impuesto').value,

        stock_inicial: stock,
        minimo
      };
    }
  }).then(r => {
    if (!r.isConfirmed) return;

    $.post(
      BASE_URL + 'api/productos.php?tipo=registrar',
      r.value,
      function(resp) {
        Toast.fire({
          icon: resp.estatus ? 'success' : 'error',
          title: resp.mensaje
        });

        if (resp.estatus && tabla) {
          tabla.ajax.reload(false);
        }
      },
      'json'
    ).fail(function(xhr) {
      Toast.fire({
        icon: 'error',
        title: 'Error al registrar el producto'
      });
      console.error(xhr.responseText);
    });
  });
}


window.agregarProductoDesdeInventario = async () => {
  try {
    await cargarCatalogosInventario();
    modalNuevoProductoInventario();
  } catch (error) {
    Toast.fire({ icon: 'error', title: 'No se pudieron cargar los catálogos' });
  }
};

$(document).ready(function () {
  const columns = [
    { data: 'id_producto', title: '#' },
    { data: 'codigo', title: 'Código / SKU', render: (d) => d ? `<code>${d}</code>` : '<span class="text-muted">S/C</span>' },
    { data: 'producto', title: 'Producto' },
    { data: 'precio_compra', title: 'Costo' },
    { data: 'precio_venta', title: 'Precio venta' },
    { data: 'categoria', title: 'Categoría', render: (d) => d || '<span class="text-muted">-</span>' },
    { data: 'marca', title: 'Marca', render: (d) => d || '<span class="text-muted">-</span>' },
    {
      data: 'existencia',
      title: 'Existencia',
      render: (d, type, row) => {
        const stock = parseFloat(d || 0);
        const min = parseFloat(row.minimo || 5);
        const u = row.unidad || 'pza';
        if (stock <= 0) return `<span class="badge bg-danger">Agotado (0 ${u})</span>`;
        if (stock <= min) return `<span class="badge bg-warning text-dark">${stock} ${u} (Bajo)</span>`;
        return `<span class="badge bg-success">${stock} ${u}</span>`;
      }
    },
    { data: 'minimo', title: 'Mínimo', render: (d, type, row) => `${parseFloat(d || 5).toFixed(0)} ${row.unidad || ''}` },
    {
      data: null,
      title: 'Acciones',
      render: (data, type, row) => `
        <button class="btn btn-outline-primary btn-sm" onclick="editarProductoDesdeInventario(${row.id_producto})" title="Editar producto">
          <i class="fa-solid fa-pen-to-square"></i>
        </button>
        <button class="btn btn-primary btn-sm" onclick="ajustarStock(${row.id_producto}, '${encodeURIComponent(row.producto)}', ${row.existencia})" title="Ajustar Stock">
          <i class="fa-solid fa-boxes-stacked me-1"></i> Ajustar
        </button>
        <button class="btn btn-secondary btn-sm" onclick="verKardex(${row.id_producto}, '${encodeURIComponent(row.producto)}')" title="Historial de Movimientos">
          <i class="fa-solid fa-clock-rotate-left"></i>
        </button>`
    }
  ];

  tabla = initReportTable({
    selector: '#tabla-inventario',
    module: 'inventario',
    columns,
    fields: [
      { name: 'codigo', label: 'Código / SKU', placeholder: 'Código' },
      { name: 'producto', label: 'Producto', placeholder: 'Nombre del producto' },
      { name: 'categoria', label: 'Categoría', type: 'select', endpoint: 'api/catalogos.php?tipo=combo&catalogo=categorias', liveSearch: true },
      { name: 'marca', label: 'Marca', type: 'select', endpoint: 'api/catalogos.php?tipo=combo&catalogo=marcas', liveSearch: true },
      { name: 'stock', label: 'Disponibilidad', type: 'select', options: [{ value: 'agotado', label: 'Agotado' }, { value: 'bajo', label: 'Stock bajo' }, { value: 'disponible', label: 'Disponible' }] }
    ]
  });
  cargarCatalogosInventario().catch((error) => console.error('No se pudieron cargar catálogos de inventario:', error));
});
