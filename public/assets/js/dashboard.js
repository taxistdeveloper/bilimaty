/* Dashboard charts (Chart.js). Data comes from a JSON block rendered by the server. */
(() => {
    'use strict';
    const canvas = document.getElementById('studentsChart');
    const source = document.getElementById('studentsChartData');
    if (!canvas || !source || !window.Chart) return;

    const rows = JSON.parse(source.textContent || '[]');
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map((r) => r.label),
            datasets: [{
                data: rows.map((r) => r.value),
                backgroundColor: 'rgba(37, 99, 235, 0.85)',
                hoverBackgroundColor: '#1d4ed8',
                borderRadius: 6,
                maxBarThickness: 48,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#64748b' } },
                y: { beginAtZero: true, ticks: { precision: 0, color: '#64748b' }, grid: { color: '#e2e8f0' } },
            },
        },
    });
})();
