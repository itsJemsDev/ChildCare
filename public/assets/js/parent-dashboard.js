document.addEventListener('DOMContentLoaded', () => {
  const canvas = document.getElementById('parent-growth-chart');
  if (!canvas || typeof Chart === 'undefined') return;
  const parse = (key) => {
    try { return JSON.parse(canvas.dataset[key] || '[]'); } catch { return []; }
  };
  new Chart(canvas, {
    type: 'line',
    data: {labels: parse('labels'), datasets: [
      {label: 'Weight (kg)', data: parse('weights'), yAxisID: 'weight', borderColor: '#7c3aed', backgroundColor: '#7c3aed', tension: 0.25},
      {label: 'Height (cm)', data: parse('heights'), yAxisID: 'height', borderColor: '#15803d', backgroundColor: '#15803d', tension: 0.25}
    ]},
    options: {responsive: true, maintainAspectRatio: false, interaction: {mode: 'index', intersect: false}, scales: {
      weight: {type: 'linear', position: 'left', title: {display: true, text: 'Weight (kg)'}},
      height: {type: 'linear', position: 'right', title: {display: true, text: 'Height (cm)'}, grid: {drawOnChartArea: false}}
    }}
  });
});
