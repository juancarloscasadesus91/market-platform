import { Chart, registerables } from 'chart.js';
import { CandlestickController, CandlestickElement } from 'chartjs-chart-financial';
import 'chartjs-adapter-date-fns';

Chart.register(...registerables, CandlestickController, CandlestickElement);

export function initCandlestickChart(elementId, data) {
    const ctx = document.getElementById(elementId);
    
    if (!ctx) return;

    new Chart(ctx, {
        type: 'candlestick',
        data: {
            datasets: [{
                label: 'Price',
                data: data,
                borderColor: {
                    up: '#10b981',
                    down: '#ef4444',
                    unchanged: '#64748b',
                },
                backgroundColor: {
                    up: 'rgba(16, 185, 129, 0.3)',
                    down: 'rgba(239, 68, 68, 0.3)',
                    unchanged: 'rgba(100, 116, 139, 0.3)',
                },
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(26, 29, 36, 0.95)',
                    titleColor: '#f1f5f9',
                    bodyColor: '#cbd5e1',
                    borderColor: '#475569',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            const point = context.parsed;
                            return [
                                `Open: $${point.o.toFixed(2)}`,
                                `High: $${point.h.toFixed(2)}`,
                                `Low: $${point.l.toFixed(2)}`,
                                `Close: $${point.c.toFixed(2)}`
                            ];
                        }
                    }
                }
            },
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: 'day',
                        displayFormats: {
                            day: 'MMM dd'
                        }
                    },
                    grid: {
                        color: 'rgba(71, 85, 105, 0.2)',
                    },
                    ticks: {
                        color: '#94a3b8',
                    }
                },
                y: {
                    grid: {
                        color: 'rgba(71, 85, 105, 0.2)',
                    },
                    ticks: {
                        color: '#94a3b8',
                        callback: function(value) {
                            return '$' + value.toFixed(2);
                        }
                    }
                }
            }
        }
    });
}
