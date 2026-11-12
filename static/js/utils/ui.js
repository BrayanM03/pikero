/**
 * Controla el estado visual de carga de un botón
 * @param {string} id - El ID del botón
 * @param {boolean} loading - true para activar carga, false para restaurar
 * @param {string} textLoading - Texto opcional a mostrar durante la carga
 */
export const toggleLoading = (id, loading, textLoading = "Cargando...") => {
    const btn = document.getElementById(id);
    if (!btn) return;

    // Guardamos el texto original solo la primera vez para poder restaurarlo
    if (loading && !btn.dataset.originalText) {
        btn.dataset.originalText = btn.innerHTML;
    }

    if (loading) {
        btn.disabled = true;
        // Insertamos el spinner de Bootstrap conservando el estilo de AdminKit
        btn.innerHTML = `
            <div class="lds-dual-ring-sm"></div>
            ${textLoading}
        `;
    } else {
        btn.disabled = false;
        // Restauramos el contenido original (iconos feather, texto, etc.)
        btn.innerHTML = btn.dataset.originalText;
        
        // Si usas Feather Icons, es vital refrescar para que vuelvan a aparecer los iconos
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }
};


/**
 * Controla el estado visual de carga dentro de cualquier contenedor (div, h1, span)
 * @param {string} id - El ID del contenedor a reemplazar
 * @param {boolean} loading - true para mostrar el spinner, false para restaurar
 */
export const toggleContainerLoading = (id, loading) => {
    const container = document.getElementById(id);
    if (!container) return;

    if (loading) {
        // Guardamos el HTML actual SIEMPRE antes de poner el spinner.
        // Así, si el fetch falla, podemos regresar al último valor válido.
        container.dataset.originalHtml = container.innerHTML;
        
        // Insertamos un spinner pequeño de Bootstrap
        container.innerHTML = `
            <div class="d-flex justify-content-center align-items-center w-100">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            </div>
        `;
    } else {
        // Si mandamos false (ej. cuando falla el fetch), restauramos el valor original
        if (container.dataset.originalHtml) {
            container.innerHTML = container.dataset.originalHtml;
        }
        
        // Refrescamos iconos por si el HTML original los tenía
        if (typeof feather !== 'undefined') feather.replace();
    }
};