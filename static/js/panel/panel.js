let graficaVentas = null;

function mostrarFiltroPeriodo() {
  const periodo = document.getElementById('panel-periodo').value;
  const grupos = {
    day: 'filtro-dia',
    week: 'filtro-dia',
    range: 'filtro-rango',
    month: 'filtro-mes',
    year: 'filtro-anio'
  };
  for (const id of new Set(Object.values(grupos))) {
    const grupo = document.getElementById(id);
    const activo = id === grupos[periodo];
    grupo.classList.toggle('d-none', !activo);
    for (const campo of grupo.querySelectorAll('input')) {
      campo.disabled = !activo;
      campo.required = activo;
    }
  }
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
  const consulta = document.getElementById('grafica-ventas-panel').dataset.consulta;
  fetch(`${BASE_URL}api/panel.php?${consulta}`)
    .then((response) => response.json())
    .then((respuesta) => {
      if (!respuesta.estatus) throw new Error(respuesta.mensaje || 'No se pudo cargar la gráfica');
      renderGrafica(respuesta.data);
    })
    .catch((error) => {
      console.error('Error:', error);
      Swal.fire({ icon: 'error', title: 'No se pudo cargar la gráfica', text: error.message });
    });
}

document.addEventListener('DOMContentLoaded', () => {
  const periodo = document.getElementById('panel-periodo');
  periodo.addEventListener('change', mostrarFiltroPeriodo);
  mostrarFiltroPeriodo();
  cargarGrafica();
});