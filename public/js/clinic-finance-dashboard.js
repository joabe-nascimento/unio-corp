/**
 * Unio — Dashboard Financeiro (Pulso Clínico)
 * Inicializa gráficos Chart.js no widget financeiro
 */

(function () {
    'use strict';

    if (typeof Chart === 'undefined') {
        console.warn('[Finance Dashboard] Chart.js not loaded');
        return;
    }

    const widget = document.querySelector('[data-widget="finance-dashboard"]');
    if (!widget) return;

    // Configuração global
    Chart.defaults.font.family = "'Quicksand', 'Nunito', system-ui, sans-serif";
    Chart.defaults.font.size = 13;
    Chart.defaults.color = '#5a6b82';

    // Cores do tema
    const colors = {
        primary: '#4b72be',
        success: '#22c55e',
        warning: '#fbbf24',
        danger: '#ef4444',
        amber: '#f59e0b',
        sky: '#0ea5e9',
        slate: '#64748b',
    };

    /**
     * Gráfico de pizza: Mix de Receita
     */
    function initMixChart() {
        const canvas = document.getElementById('financeChartMix');
        if (!canvas) return;

        const labels = JSON.parse(canvas.dataset.chartLabels || '[]');
        const data = JSON.parse(canvas.dataset.chartData || '[]');

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: [
                        colors.sky,
                        colors.primary,
                        colors.slate,
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            padding: 12,
                            font: { size: 12, weight: '600' },
                            usePointStyle: true,
                            pointStyle: 'circle',
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                return label + ': R$ ' + value.toLocaleString('pt-BR', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2,
                                });
                            },
                        },
                    },
                },
            },
        });
    }

    /**
     * Gráfico de linha: Tendência de Receita
     */
    function initTrendChart() {
        const canvas = document.getElementById('financeChartTrend');
        if (!canvas) return;

        const labels = JSON.parse(canvas.dataset.chartLabels || '[]');
        const data = JSON.parse(canvas.dataset.chartData || '[]');

        const gradient = canvas.getContext('2d').createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(75, 114, 190, 0.15)');
        gradient.addColorStop(1, 'rgba(75, 114, 190, 0)');

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Receita (R$)',
                    data: data,
                    borderColor: colors.primary,
                    backgroundColor: gradient,
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: colors.primary,
                    pointBorderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const value = context.parsed.y || 0;
                                return 'Receita: R$ ' + value.toLocaleString('pt-BR', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2,
                                });
                            },
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            },
                        },
                        grid: {
                            color: 'rgba(75, 114, 190, 0.06)',
                            drawBorder: false,
                        },
                    },
                    x: {
                        grid: {
                            display: false,
                        },
                        ticks: {
                            font: { size: 11 },
                        },
                    },
                },
            },
        });
    }

    // Inicializar gráficos quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initMixChart();
            initTrendChart();
        });
    } else {
        initMixChart();
        initTrendChart();
    }
})();
