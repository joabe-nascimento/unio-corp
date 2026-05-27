import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        type: { type: String, default: 'line' },
        labels: Array,
        values: Array,
        label: { type: String, default: 'Colaboradores ativos' },
    };

    connect() {
        const Chart = window.Chart;
        if (!Chart) return;

        const accent = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#4F7FFF';
        const grid = 'rgba(255,255,255,0.06)';
        const text = getComputedStyle(document.documentElement).getPropertyValue('--text-3').trim() || '#8a96a3';

        this.chart = new Chart(this.element, {
            type: this.typeValue,
            data: {
                labels: this.labelsValue,
                datasets: [{
                    label: this.labelValue,
                    data: this.valuesValue,
                    borderColor: accent,
                    backgroundColor: `${accent}22`,
                    tension: 0.35,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: accent,
                    borderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(12, 16, 22, 0.92)',
                        padding: 10,
                        cornerRadius: 8,
                    },
                },
                scales: {
                    x: {
                        grid: { color: grid },
                        ticks: { color: text, font: { size: 11 } },
                    },
                    y: {
                        beginAtZero: false,
                        grid: { color: grid },
                        ticks: { color: text, font: { size: 11 }, precision: 0 },
                    },
                },
            },
        });
    }

    disconnect() {
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
    }
}
