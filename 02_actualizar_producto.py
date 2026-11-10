#!/usr/bin/env python3
"""Aplicar modificaciones acotadas a models/Producto.php y src/js/productos/productos.js
Ejecutar desde la raíz del repo, una vez, después de revisar el diff.
No se conecta a ningún servidor.
"""
from pathlib import Path

def plan_edit(path, operations):
    p=Path(path)
    if not p.exists(): raise RuntimeError('No existe '+path)
    text=p.read_text()
    original=text
    for old,new,desc in operations:
        n=text.count(old)
        if n!=1: raise RuntimeError(f'{path}: ancla {desc} aparece {n} veces, se detuvo sin modificar.')
        text=text.replace(old,new,1)
    backup=p.with_suffix(p.suffix+'.antes_isr')
    if backup.exists(): raise RuntimeError('El respaldo '+str(backup)+' ya existe; evita correr dos veces.')
    return p, backup, original, text

php=[
  (
    "                   p.objeto_impuesto, p.clave_sat, p.codigo_unidad_sat,",
    "                   p.objeto_impuesto, p.sugerir_retencion_isr_resico, p.clave_sat, p.codigo_unidad_sat,",
    'POS flag'
  ),
  (
    "        $tipoIvaVenta = (string) (\n            $data['tipo_iva_venta'] ?? '0.16'\n        );",
    "        $sugerirIsr = filter_var(\n            $data['sugerir_retencion_isr_resico'] ?? false,\n            FILTER_VALIDATE_BOOLEAN\n        ) ? 1 : 0;\n\n        $tipoIvaVenta = (string) (\n            $data['tipo_iva_venta'] ?? '0.16'\n        );",
    'ISR validate'
  ),
  (
    "            'tipo_iva_venta' => $tipoIvaVenta,\n            'precio_venta_incluye_iva' => $precioVentaIncluyeIva,",
    "            'tipo_iva_venta' => $tipoIvaVenta,\n            'precio_venta_incluye_iva' => $precioVentaIncluyeIva,\n            'sugerir_retencion_isr_resico' => $sugerirIsr,",
    'ISR persisted'
  )
]

html_add = '''        <div class="row mt-2">
          <div class="col-md-6 mb-3">
            <label class="form-label">Tratamiento IVA de venta</label>
            <select id="prod_tipo_iva_venta" class="form-select">
              ${[['0.16','IVA 16%'], ['0.08','IVA 8%'], ['0','IVA 0%'],
                ['exento','Exento'], ['no_objeto','No objeto']].map(([v,l]) =>
                `<option value="${v}" ${String(values.tipo_iva_venta ?? '0.16') === v ? 'selected' : ''}>${l}</option>`).join('')}
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Precio de venta</label>
            <select id="prod_precio_venta_incluye_iva" class="form-select">
              <option value="1" ${Number(values.precio_venta_incluye_iva ?? 1) === 1 ? 'selected' : ''}>IVA incluido</option>
              <option value="0" ${Number(values.precio_venta_incluye_iva ?? 1) === 0 ? 'selected' : ''}>IVA aparte</option>
            </select>
          </div>
          <div class="col-12 mb-3">
            <label class="form-check-label">
              <input id="prod_sugerir_ret_isr" class="form-check-input" type="checkbox"
                ${Number(values.sugerir_retencion_isr_resico ?? 0) === 1 ? 'checked' : ''}>
              Sugerir retención ISR RESICO 1.25% al facturar
            </label>
            <small class="d-block text-muted">Solo sugerencia: depende del emisor PF RESICO y receptor persona moral; no afecta el ticket.</small>
          </div>
        </div>

'''
js=[
  (
    "      const payload = {\n        id,",
    "      const tipoIvaProd = document.getElementById('prod_tipo_iva_venta').value;\n      const objetoProd = document.getElementById('prod_objeto_impuesto').value;\n      if ((tipoIvaProd === 'no_objeto' && objetoProd !== '01') ||\n          (tipoIvaProd !== 'no_objeto' && objetoProd !== '02')) {\n        Swal.showValidationMessage('Objeto 01 para no objeto, u objeto 02 para el IVA aplicable.');\n        return false;\n      }\n\n      const payload = {\n        id,",
    'validacion objeto impuesto'
  ),
  (
    '''        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Categoría</label>''',
    html_add + '''        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Categoría</label>''',
    'tax form'
  ),
  (
    "        objeto_impuesto: document.getElementById('prod_objeto_impuesto').value,",
    "        objeto_impuesto: document.getElementById('prod_objeto_impuesto').value,\n        tipo_iva_venta: document.getElementById('prod_tipo_iva_venta').value,\n        precio_venta_incluye_iva: document.getElementById('prod_precio_venta_incluye_iva').value,\n        sugerir_retencion_isr_resico: document.getElementById('prod_sugerir_ret_isr').checked ? 1 : 0,",
    'tax payload'
  )
]
plans=[plan_edit('models/Producto.php',php), plan_edit('src/js/productos/productos.js',js)]
for p,backup,original,content in plans:
    backup.write_text(original)
    p.write_text(content)
    print(f'Actualizado {p}; respaldo {backup}')
