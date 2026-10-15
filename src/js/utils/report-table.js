import { initCustomDataTable } from '../DataTable/datatables-init.js';
import Toast from './toast.js';

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  }[character]));
}

function withinSixMonths(startValue, endValue) {
  if (!startValue || !endValue) return false;
  const start = new Date(`${startValue}T00:00:00`);
  const end = new Date(`${endValue}T00:00:00`);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start) return false;
  const limit = new Date(start);
  const day = limit.getDate();
  limit.setDate(1);
  limit.setMonth(limit.getMonth() + 6);
  const lastDay = new Date(limit.getFullYear(), limit.getMonth() + 1, 0).getDate();
  limit.setDate(Math.min(day, lastDay));
  return end <= limit;
}

function fieldMarkup(field) {
  const id = `report-filter-${field.name}`;
  if (field.type === 'select') {
    return `<div class="col-12 col-sm-6 col-lg-3"><label class="form-label small mb-1" for="${id}">${escapeHtml(field.label)}</label><select id="${id}" class="selectpicker w-100" data-live-search="${field.liveSearch ? 'true' : 'false'}" data-report-filter="${escapeHtml(field.name)}"><option value="">Todos</option></select></div>`;
  }
  return `<div class="col-12 col-sm-6 col-lg-3"><label class="form-label small mb-1" for="${id}">${escapeHtml(field.label)}</label><input id="${id}" class="form-control form-control-sm" type="text" data-report-filter="${escapeHtml(field.name)}" placeholder="${escapeHtml(field.placeholder || 'Filtrar...')}"></div>`;
}

function populateSelect(field, select) {
  if (field.options) {
    field.options.forEach((option) => {
      select.append(new Option(option.label, option.value));
    });
    if (field.defaultValue !== undefined) select.value = String(field.defaultValue);
    $(select).selectpicker('refresh');
    return;
  }
  if (!field.endpoint) return;
  fetch(BASE_URL + field.endpoint)
    .then((response) => response.json())
    .then((response) => {
      (response.data || []).forEach((option) => {
        select.append(new Option(field.labelFor ? field.labelFor(option) : option[field.labelKey || 'nombre'], option[field.valueKey || 'id']));
      });
      if (field.defaultValue !== undefined) select.value = String(field.defaultValue);
      $(select).selectpicker('refresh');
    })
    .catch((error) => {
      console.error('No se pudo cargar el filtro:', error);
      Toast.fire({ icon: 'error', title: `No se pudo cargar ${field.label.toLowerCase()}` });
    });
}

export function initReportTable({ selector, module, columns, order, fields = [] }) {
  const tableNode = document.querySelector(selector);
  const toolbar = document.createElement('section');
  toolbar.className = 'report-filter-toolbar mb-3';
  toolbar.innerHTML = `
    <div class="row g-2 align-items-end">
      <div class="col-12 col-sm-6 col-lg-3">
        <label class="form-label small mb-1" for="report-fecha-inicio">Fecha inicio</label>
        <input id="report-fecha-inicio" class="form-control form-control-sm" type="date" data-report-filter="fecha_inicio">
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <label class="form-label small mb-1" for="report-fecha-fin">Fecha fin</label>
        <input id="report-fecha-fin" class="form-control form-control-sm" type="date" data-report-filter="fecha_fin">
      </div>
      ${fields.map(fieldMarkup).join('')}
      <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2">
        <small id="report-range-message" class="text-muted">Para exportar, selecciona un rango de hasta 6 meses.</small>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-report-action="clear"><i class="fa-solid fa-eraser me-1"></i>Limpiar</button>
          <button type="button" class="btn btn-primary btn-sm" data-report-action="filter"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
          <button type="button" class="btn btn-success btn-sm" data-report-action="export" disabled><i class="fa-solid fa-file-excel me-1"></i>Descargar Excel</button>
        </div>
      </div>
    </div>`;
  tableNode.parentElement.insertBefore(toolbar, tableNode);

  fields.forEach((field) => {
    if (field.type === 'select') {
      const select = toolbar.querySelector(`[data-report-filter="${field.name}"]`);
      $(select).selectpicker({
        liveSearch: Boolean(field.liveSearch),
        noneSelectedText: 'Todos',
        noneResultsText: 'Sin resultados para {0}'
      });
      populateSelect(field, select);
    }
  });

  const start = toolbar.querySelector('[data-report-filter="fecha_inicio"]');
  const end = toolbar.querySelector('[data-report-filter="fecha_fin"]');
  const exportButton = toolbar.querySelector('[data-report-action="export"]');
  const message = toolbar.querySelector('#report-range-message');
  const inputs = [...toolbar.querySelectorAll('[data-report-filter]')];
  const filters = () => Object.fromEntries(inputs.map((input) => [input.dataset.reportFilter, input.value.trim()]));
  let appliedFilters = filters();

  function updateExportState() {
    const hasBoth = Boolean(start.value && end.value);
    const valid = hasBoth && withinSixMonths(start.value, end.value);
    const matchesApplied = Object.keys(appliedFilters).every((key) => appliedFilters[key] === filters()[key]);
    exportButton.disabled = !valid || !matchesApplied;
    if (!hasBoth) {
      message.textContent = start.value || end.value
        ? 'Para filtrar por fecha debes indicar inicio y fin.'
        : 'Para exportar, selecciona fecha inicio y fecha fin (máximo 6 meses).';
      message.className = 'text-muted';
    } else if (valid) {
      message.textContent = matchesApplied ? 'Rango válido para exportar.' : 'Presiona Filtrar para aplicar los cambios antes de exportar.';
      message.className = matchesApplied ? 'text-success' : 'text-muted';
    } else {
      message.textContent = end.value < start.value ? 'La fecha fin debe ser posterior o igual al inicio.' : 'El rango de fechas no puede ser mayor a 6 meses.';
      message.className = 'text-danger';
    }
    return valid;
  }

  inputs.forEach((input) => {
    input.addEventListener('change', updateExportState);
    input.addEventListener('input', updateExportState);
  });

  const table = initCustomDataTable(
    selector,
    {
      url: `${BASE_URL}api/reportes.php?tipo=datatable&modulo=${encodeURIComponent(module)}`,
      type: 'GET',
      dataType: 'json',
      data: (request) => Object.assign(request, filters())
    },
    columns,
    order
  );

  toolbar.querySelector('[data-report-action="filter"]').addEventListener('click', () => {
    const hasOneDate = Boolean(start.value || end.value);
    if (hasOneDate && !withinSixMonths(start.value, end.value)) {
      updateExportState();
      Toast.fire({ icon: 'error', title: start.value && end.value ? message.textContent : 'Indica ambas fechas para filtrar por periodo' });
      return;
    }
    appliedFilters = filters();
    table.ajax.reload();
    updateExportState();
  });

  toolbar.querySelector('[data-report-action="clear"]').addEventListener('click', () => {
    inputs.forEach((input) => {
      input.value = '';
      if (input.matches('select')) $(input).selectpicker('refresh');
    });
    appliedFilters = filters();
    updateExportState();
    table.search('').ajax.reload();
  });

  exportButton.addEventListener('click', async () => {
    if (!updateExportState() || exportButton.disabled) {
      Toast.fire({ icon: 'error', title: message.textContent });
      return;
    }
    exportButton.disabled = true;
    const body = new URLSearchParams({ modulo: module, ...appliedFilters, search: table.search() });
    try {
      const response = await fetch(`${BASE_URL}api/reportes.php?tipo=exportar`, { method: 'POST', body });
      const contentType = response.headers.get('content-type') || '';
      if (!response.ok || contentType.includes('application/json')) {
        const error = await response.json();
        throw new Error(error.mensaje || 'No se pudo generar el reporte');
      }
      const blob = await response.blob();
      const disposition = response.headers.get('content-disposition') || '';
      const filename = disposition.match(/filename="?([^";]+)"?/i)?.[1] || `${module}_reporte.xlsx`;
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (error) {
      Toast.fire({ icon: 'error', title: error.message });
    } finally {
      updateExportState();
    }
  });

  return table;
}
