import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import 'trix';

window.Alpine = Alpine;

Alpine.store('theme', {
    dark: document.documentElement.classList.contains('dark'),
    toggle() {
        this.dark = !this.dark;
        document.documentElement.classList.toggle('dark', this.dark);
        try {
            localStorage.setItem('theme', this.dark ? 'dark' : 'light');
        } catch (e) {
            // Storage may be unavailable (private mode); the toggle still works for this page.
        }
    },
});

Alpine.data('chart', () => ({
    instance: null,
    init() {
        const config = JSON.parse(this.$el.dataset.chart);
        config.options = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } } },
            ...(config.options ?? {}),
        };
        this.instance = new Chart(this.$el, config);
    },
    destroy() {
        this.instance?.destroy();
    },
}));

// Attachments are not supported: documents go through the class folders instead.
document.addEventListener('trix-file-accept', (event) => event.preventDefault());

Alpine.start();
