const escapar = (valor) => String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[caracter]));

export async function cargarCatalogoSAT(catalogo, keyword = '') {
  const respuesta = await $.getJSON(`${BASE_URL}api/ventas.php?tipo=catalogo_sat`, { catalogo, keyword });
  if (!respuesta.estatus) throw new Error(respuesta.mensaje || 'No se pudo consultar el catálogo SAT.');
  return respuesta.data || [];
}

export function opcionesSAT(opciones, seleccionado = '', placeholder = 'Selecciona una opción') {
  const valorSeleccionado = String(seleccionado ?? '');
  let html = `<option value="">${escapar(placeholder)}</option>`;
  if (valorSeleccionado && !opciones.some((opcion) => String(opcion.Value) === valorSeleccionado)) {
    html += `<option value="${escapar(valorSeleccionado)}" selected>${escapar(valorSeleccionado)}</option>`;
  }
  opciones.forEach((opcion) => {
    const value = String(opcion.Value ?? opcion.value ?? '');
    const name = String(opcion.Name ?? opcion.name ?? '');
    if (!value) return;
    html += `<option value="${escapar(value)}" ${value === valorSeleccionado ? 'selected' : ''}>${escapar(value)} · ${escapar(name)}</option>`;
  });
  return html;
}

export function inicializarSelectpicker(contenedor = document) {
  $(contenedor).find('select.selectpicker').each(function () {
    const $select = $(this);
    $select.selectpicker({
      liveSearch: true,
      liveSearchNormalize: true,
      noneSelectedText: 'Selecciona una opción',
      noneResultsText: 'Sin resultados para {0}'
    });

    const catalogo = $select.data('catalogo-sat');
    if (!catalogo) return;

    let temporizador;
    $select.on('shown.bs.select', () => {
      const $busqueda = $select.parent().find('.bs-searchbox input');
      $busqueda.off('input.catalogoSat').on('input.catalogoSat', function () {
        const keyword = this.value.trim();
        clearTimeout(temporizador);
        if (keyword.length < 2) return;
        temporizador = setTimeout(async () => {
          try {
            const opciones = await cargarCatalogoSAT(catalogo, keyword);
            const seleccionado = $select.val();
            $select.empty().append(opcionesSAT(opciones, seleccionado));
            if (seleccionado) $select.val(seleccionado);
            $select.selectpicker('refresh');
            $busqueda.val(keyword);
          } catch (error) {
            console.error(error);
          }
        }, 300);
      });
    });
  });
}