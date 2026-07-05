import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        type: { type: String, default: 'line' },
        labels: Array,
        values: Array,
        label: { type: String, default: 'Colaboradores ativos' },
    };

    connect() {
        this._attempts = 0;
        this._renderChart();
    }

    _renderChart() {
        const Chart = window.Chart;
        if (!Chart) {
            if (this._attempts < 40) {
                this._attempts += 1;
                this._retryTimer = window.setTimeout(() => this._renderChart(), 50);
            }
            return;
        }

        if (this.chart) return;

        const accent = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#4F7FFF';
        const grid = 'rgba(255,255,255,0.06)';
        const text = getComputedStyle(document.documentElement).getPropertyValue('--text-3').trim() || '#8a96a3';
        const maxValue = Math.max(...this.valuesValue.map((v) => Number(v) || 0));

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
                        beginAtZero: maxValue <= 1,
                        suggestedMax: maxValue <= 1 ? 5 : undefined,
                        grid: { color: grid },
                        ticks: { color: text, font: { size: 11 }, precision: 0 },
                    },
                },
            },
        });
    }

    disconnect() {
        if (this._retryTimer) {
            window.clearTimeout(this._retryTimer);
            this._retryTimer = null;
        }
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
    }
}
