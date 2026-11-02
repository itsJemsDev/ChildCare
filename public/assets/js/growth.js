document.addEventListener('alpine:init', () => {
  Alpine.data('growthPreview', () => ({
    previewUrl: '',
    fields: { visit_date: '', weight_kg: '', height_cm: '', measured_position: 'recumbent' },
    result: null, error: '', loading: false, timer: null,
    init() {
      this.previewUrl = this.$el.dataset.previewUrl || '';
      this.$watch('fields', () => { clearTimeout(this.timer); this.timer = setTimeout(() => this.preview(), 350); }, { deep: true });
    },
    async preview() {
      if (!this.fields.visit_date || !this.fields.weight_kg || !this.fields.height_cm) return;
      this.loading = true; this.error = '';
      try {
        const query = new URLSearchParams(this.fields);
        const response = await fetch(`${this.previewUrl}?${query.toString()}`, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Unable to calculate growth status.');
        this.result = data;
      } catch (error) { this.error = error.message; this.result = null; }
      finally { this.loading = false; }
    },
  }));
});
(() => {
  const canvas = document.getElementById('growthChart');
  if (!canvas || !window.Chart) return;
  const data = JSON.parse(canvas.dataset.chart || '{}');
  new Chart(canvas, { type: 'line', data: { labels: data.labels || [], datasets: [{ label: 'Weight (kg)', data: data.weights || [], borderColor: '#16a34a', backgroundColor: '#dcfce7', tension: .2 }] }, options: { responsive: true, scales: { y: { beginAtZero: false, title: { display: true, text: 'Weight (kg)' } } } } });
})();
