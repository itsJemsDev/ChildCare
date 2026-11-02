document.addEventListener('DOMContentLoaded', () => {
  document.querySelector('[data-report-refresh]')?.addEventListener('click', () => window.location.reload());
  document.querySelectorAll('canvas[data-report]').forEach(async (canvas) => {
    const type = canvas.dataset.report;
    const query = new URLSearchParams(location.search);
    try {
      const endpoint = new URL(canvas.dataset.endpoint, location.href);
      endpoint.search = query.toString();
      const response = await fetch(endpoint, {headers: {'Accept': 'application/json'}});
      if (!response.ok) throw new Error('Report unavailable');
      const result = await response.json();
      const isLine = ['beneficiaries', 'checkups'].includes(type);
      new Chart(canvas, {
        type: isLine ? 'line' : (type === 'purok' || type === 'growth' || type === 'overdue' ? 'bar' : 'doughnut'),
        data: {labels: result.labels, datasets: result.datasets.map((set) => ({...set, backgroundColor: ['#15803d','#2563eb','#7c3aed','#f59e0b','#dc2626','#0d9488'], borderColor: '#15803d', tension: 0.25}))},
        options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom'}}}
      });
      const total = document.querySelector('[data-total-for="' + type + '"]');
      if (total) total.textContent = 'Total: ' + result.total + ' records';
    } catch (error) {
      const total = document.querySelector('[data-total-for="' + type + '"]');
      if (total) total.textContent = 'Unable to load this report.';
    }
  });

  document.querySelectorAll('form[data-report-export]').forEach((form) => {
    form.addEventListener('submit', () => {
      const canvas = document.querySelector('canvas[data-report="' + CSS.escape(form.dataset.reportExport) + '"]');
      const chart = canvas && Chart.getChart(canvas);
      const field = form.querySelector('[name="chart_image"]');
      if (field && chart) field.value = chart.toBase64Image('image/png');
    });
  });
});
