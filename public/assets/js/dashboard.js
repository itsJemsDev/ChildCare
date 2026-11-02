(() => {
  const parse = (value) => { try { return JSON.parse(value || '[]'); } catch { return []; } };
  const health = document.getElementById('healthChart');
  if (health && window.Chart) new Chart(health, { type: 'doughnut', data: { labels: ['Healthy', 'Malnourished', 'Obese'], datasets: [{ data: parse(health.dataset.values), backgroundColor: ['#16a34a', '#f59e0b', '#7c3aed'] }] }, options: { plugins: { legend: { position: 'bottom' } } } });
  const purok = document.getElementById('purokChart');
  if (purok && window.Chart) new Chart(purok, { type: 'bar', data: { labels: parse(purok.dataset.labels), datasets: [{ label: 'Children', data: parse(purok.dataset.values), backgroundColor: '#16a34a' }] }, options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } } });
  const monthly = document.getElementById('monthlyChart');
  if (monthly && window.Chart) new Chart(monthly, { type: 'line', data: { labels: parse(monthly.dataset.labels), datasets: [{ label: 'Dose beneficiaries', data: parse(monthly.dataset.values), borderColor: '#1d4ed8', tension: .25 }] }, options: { scales: { y: { beginAtZero: true } } } });
  const immunization = document.getElementById('immunizationChart');
  if (immunization && window.Chart) new Chart(immunization, { type: 'doughnut', data: { labels: parse(immunization.dataset.labels), datasets: [{ data: parse(immunization.dataset.values), backgroundColor: ['#16a34a', '#f59e0b', '#dc2626'] }] }, options: { plugins: { legend: { position: 'bottom' } } } });
})();
