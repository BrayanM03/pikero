let graficaVentas = null;

function filtrosGrafica() {
  const periodo = document.getElementById('panel-periodo').value;
  const parametros = new URLSearchParams({ tipo: 'grafica', periodo });
  if (periodo === 'day' || periodo === 'week') {
    parametros.set('fecha', document.getElementById('panel-fecha').value);
  } else if (periodo === 'range') {
    parametros.set('inicio', document.getElementById('panel-inicio').value);
    parametros.set('fin', document.getElementById('panel-fin').value);
  } else if (periodo === 'month') {
    parametros.set('mes', document.getElementById('panel-mes').value);
  } else if (periodo === 'year') {
    parametros.set('anio', document.getElementById('panel-anio').value);
  }
  return parametros;
}

function mostrarFiltroPeriodo() {
  const periodo = document.getElementById('panel-periodo').value;
  document.getElementById('filtro-dia').classList.toggle('d-none', !['day', 'week'].includes(periodo));
  document.getElementById('filtro-rango').classList.toggle('d-none', periodo !== 'range');
  document.getElementById('filtro-mes').classList.toggle('d-none', periodo !== 'month');
  document.getElementById('filtro-anio').classList.toggle('d-none', periodo !== 'year');
}

function renderGrafica(datos) {
  const contexto = document.getElementById('grafica-ventas-panel').getContext('2d');
  if (graficaVentas) graficaVentas.destroy();
  graficaVentas = new Chart(contexto, {
    type: 'bar',
    data: {
      labels: datos.labels,
      datasets: [
        {
          label: 'Ventas ($)',
          data: datos.ventas,
          backgroundColor: 'rgba(28, 187, 140, 0.62)',
          borderColor: '#159b72',
          borderWidth: 1,
          yAxisID: 'importe'
        },
        {
          type: 'line',
          label: 'Tickets',
          data: datos.cantidad,
          borderColor: '#3b7ddd',
          backgroundColor: 'rgba(59, 125, 221, 0.12)',
          pointBackgroundColor: '#3b7ddd',
          pointRadius: 3,
          fill: false,
          yAxisID: 'tickets'
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      tooltips: {
        mode: 'index',
        intersect: false,
        callbacks: {
          label: (tooltipItem, data) => {
            const dataset = data.datasets[tooltipItem.datasetIndex];
            const value = parseFloat(tooltipItem.yLabel || 0);
            return dataset.label === 'Ventas ($)' ? `${dataset.label}: $${value.toFixed(2)}` : `${dataset.label}: ${value}`;
          }
        }
      },
      scales: {
        xAxes: [{ gridLines: { display: false } }],
        yAxes: [
          {
            id: 'importe',
            position: 'left',
            ticks: { beginAtZero: true, callback: (value) => `$${value}` },
            scaleLabel: { display: true, labelString: 'Importe vendido' }
          },
          {
            id: 'tickets',
            position: 'right',
            ticks: { beginAtZero: true, precision: 0 },
            gridLines: { drawOnChartArea: false },
            scaleLabel: { display: true, labelString: 'Tickets' }
          }
        ]
      }
    }
  });
}

function cargarGrafica() {
  const boton = document.getElementById('btn-panel-aplicar');
  boton.disabled = true;
  fetch(`${BASE_URL}api/panel.php?${filtrosGrafica().toString()}`)
    .then((response) => response.json())
    .then((respuesta) => {
      if (!respuesta.estatus) throw new Error(respuesta.mensaje || 'No se pudo cargar la gráfica');
      renderGrafica(respuesta.data);
    })
    .catch((error) => {
      console.error('Error:', error);
      Swal.fire({ icon: 'error', title: 'No se pudo cargar la gráfica', text: error.message });
    })
    .finally(() => { boton.disabled = false; });
}

document.addEventListener('DOMContentLoaded', () => {
  const periodo = document.getElementById('panel-periodo');
  periodo.addEventListener('change', mostrarFiltroPeriodo);
  document.getElementById('btn-panel-aplicar').addEventListener('click', cargarGrafica);
  mostrarFiltroPeriodo();
  cargarGrafica();
});