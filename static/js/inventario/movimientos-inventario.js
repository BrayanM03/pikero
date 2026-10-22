import { initReportTable } from '../utils/report-table.js';

$(document).ready(function () {
  const columns = [
    { data: 'id', title: '#' },
    { data: 'fecha_registro', title: 'Fecha' },
    { data: 'producto', title: 'Producto', render: (value) => value || '<span class="text-muted">N/D</span>' },
    {
      data: 'tipo',
      title: 'Tipo',
      render: (value) => {
        const map = {
          venta: 'bg-danger',
          salida: 'bg-danger',
          compra: 'bg-success',
          entrada: 'bg-success',
          inventario_inicial: 'bg-primary',
          ajuste_fijo: 'bg-warning text-dark',
        };
        const cls = map[value] || 'bg-secondary';
        return `<span class="badge ${cls}">${value || 'N/D'}</span>`;
      }
    },
    { data: 'cantidad', title: 'Cantidad', render: (value) => parseFloat(value || 0).toFixed(2) },
    { data: 'existencia_antes', title: 'Antes', render: (value) => parseFloat(value || 0).toFixed(2) },
    { data: 'existencia_despues', title: 'Después', render: (value) => parseFloat(value || 0).toFixed(2) },
    { data: 'referencia', title: 'Referencia', render: (value) => value || '-' },
    { data: 'usuario', title: 'Usuario', render: (value) => value || '-' }
  ];

  initReportTable({
    selector: '#tabla-movimientos-inventario',
    module: 'movimientos',
    columns,
    order: [[0, 'desc']], 
    fields: [
      { name: 'producto', label: 'Producto', placeholder: 'Nombre del producto' },
      { name: 'tipo_movimiento', label: 'Tipo de movimiento', type: 'select', options: [
        { value: 'venta', label: 'Venta' }, { value: 'compra', label: 'Compra' },
        { value: 'entrada', label: 'Entrada' }, { value: 'salida', label: 'Salida' },
        { value: 'ajuste_fijo', label: 'Ajuste fijo' }, { value: 'inventario_inicial', label: 'Inventario inicial' }
      ] },
      { name: 'referencia', label: 'Referencia', placeholder: 'Referencia o motivo' },
      { name: 'usuario', label: 'Usuario', placeholder: 'Nombre de usuario' }
    ]
  });
});