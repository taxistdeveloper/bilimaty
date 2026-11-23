/* Surveys: question builder (show fields of the chosen type) and result charts (Chart.js). */
(() => {
    'use strict';

    // Builder: options for choice questions, range and labels for scales.
    document.querySelectorAll('[data-question-form]').forEach((form) => {
        const select = form.querySelector('[data-question-type]');
        if (!select) return;
        const sync = () => {
            form.querySelectorAll('[data-show-for]').forEach((block) => {
                const visible = block.dataset.showFor.split(' ').includes(select.value);
                block.hidden = !visible;
                block.querySelectorAll('input, textarea, select').forEach((el) => { el.disabled = !visible; });
            });
        };
        select.addEventListener('change', sync);
        sync();
    });

    // Results charts: data comes from a JSON block rendered by the server.
    const source = document.getElementById('surveyCharts');
    if (!source || !window.Chart) return;
    const charts = JSON.parse(source.textContent || '{}');
    const palette = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed', '#0891b2', '#db2777', '#65a30d'];
    const grid = { color: '#e2e8f0' };
    const ticks = { color: '#64748b' };

    Object.entries(charts).forEach(([id, chart]) => {
        const canvas = document.getElementById(id);
        if (!canvas) return;
        if (chart.grouped) {
            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: chart.labels,
                    datasets: chart.series.map((s, i) => ({ label: s.label, data: s.values, backgroundColor: palette[i % palette.length], borderRadius: 4, maxBarThickness: 40 })),
                },
                options: { responsive: true, maintainAspectRatio: false, scales: { x: { ticks, grid: { display: false } }, y: { beginAtZero: true, ticks, grid } } },
            });
            return;
        }
        new Chart(canvas, {
            type: 'bar',
            data: { labels: chart.labels, datasets: [{ data: chart.values, backgroundColor: 'rgba(37, 99, 235, 0.85)', borderRadius: 4, maxBarThickness: 36 }] },
            options: {
                indexAxis: chart.horizontal ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: chart.horizontal ? { beginAtZero: true, ticks: { ...ticks, precision: 0 }, grid } : { ticks, grid: { display: false } },
                    y: chart.horizontal ? { ticks, grid: { display: false } } : { beginAtZero: true, ticks: { ...ticks, precision: 0 }, grid },
                },
            },
        });
    });
})();
