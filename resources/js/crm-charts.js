import Chart from 'chart.js/auto'; // Tambahkan ini di paling atas

function renderCrmCharts() {
    const paymentStatusEl = document.getElementById('paymentStatusChart');
    const pipelineEl = document.getElementById('pipelineChart');
    const monthlyTrendEl = document.getElementById('monthlyTrendChart');

    if (!paymentStatusEl || !pipelineEl || !monthlyTrendEl || !window.crmChartData) return;

    // Hancurkan chart lama (Penting untuk Turbo)
    if (window.paymentStatusChart instanceof Chart) window.paymentStatusChart.destroy();
    if (window.pipelineChart instanceof Chart) window.pipelineChart.destroy();
    if (window.monthlyTrendChart instanceof Chart) window.monthlyTrendChart.destroy();

    const data = window.crmChartData; 

    // --- PAYMENT STATUS DONUT ---
    window.paymentStatusChart = new Chart(paymentStatusEl, {
        type: 'doughnut',
        data: {
            labels: ['Belum Bayar', 'Cicilan', 'Lunas'],
            datasets: [{
                data: [data.payment.unpaid, data.payment.partial, data.payment.paid],
                backgroundColor: ['#ef4444', '#f59e0b', '#10b981'],
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '70%',
            plugins: { legend: { display: false } }
        }
    });

    // --- PIPELINE BAR ---
    window.pipelineChart = new Chart(pipelineEl, {
        type: 'bar',
        data: {
            labels: ['New', 'Contacted', 'Qualified', 'Proposal', 'Menang', 'Kalah'],
            datasets: [{
                label: 'Jumlah Customer',
                data: [data.pipeline.new, data.pipeline.contacted, data.pipeline.qualified, data.pipeline.proposal, data.pipeline.won, data.pipeline.lost],
                backgroundColor: ['#3b82f6', '#06b6d4', '#8b5cf6', '#6366f1', '#10b981', '#ef4444'],
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, grid: { color: '#f1f5f9' } }, y: { grid: { display: false } } }
        }
    });

    // --- MONTHLY TREND LINE ---
    window.monthlyTrendChart = new Chart(monthlyTrendEl, {
        type: 'line',
        data: {
            labels: data.trend.months,
            datasets: [
                { label: 'Terbayar', data: data.trend.paid, borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.1)', fill: true, tension: 0.3, pointRadius: 4 },
                { label: 'Diskon', data: data.trend.discount, borderColor: '#8b5cf6', backgroundColor: 'rgba(139, 92, 246, 0.1)', fill: true, tension: 0.3, pointRadius: 4 },
                { label: 'Total Koleksi', data: data.trend.total, borderColor: '#14b8a6', backgroundColor: 'rgba(20, 184, 166, 0.1)', fill: true, tension: 0.3, pointRadius: 4, borderDash: [5, 5] }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top', labels: { usePointStyle: true, padding: 20 } } },
            scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } }
        }
    });
}

document.addEventListener('turbo:load', renderCrmCharts);