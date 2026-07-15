let monthlyChart = null;
let statusChart = null;
let latestReportData = null;
let latestSummary = null;

async function loadCharts() {
    try {
        const response = await fetch('/IECEP-LSC-MEMSYS/public/api/financial-report.php', {
            credentials: 'same-origin'
        });
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Unable to load financial report');
        }

        latestReportData = data.monthly_income_data;
        latestSummary = data.summary;

        renderSummary(data.summary);
        renderMonthlyChart(data.monthly_income_data);
        renderStatusChart(data.total_income_by_status);
        renderReportTable(data.monthly_income_data);
    } catch (error) {
        console.error('Error loading charts:', error);
        const reportTableBody = document.getElementById('reportTableBody');
        if (reportTableBody) {
            reportTableBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Unable to load financial data.</td></tr>';
        }
    }
}

function renderSummary(summary) {
    const formatCurrency = amount => '₱' + Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('totalRevenue').textContent = formatCurrency(summary.total_income);
    document.getElementById('completedRevenue').textContent = formatCurrency(summary.completed_amount);
    document.getElementById('pendingRevenue').textContent = formatCurrency(summary.pending_amount);
    document.getElementById('transactionCount').textContent = summary.transaction_count || 0;
}

function renderMonthlyChart(monthlyData) {
    const labels = monthlyData.map(item => {
        const date = new Date(item.month + '-01');
        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
    });
    const values = monthlyData.map(item => Number(item.income || 0));

    const ctx = document.getElementById('monthlyIncomeChart').getContext('2d');
    if (monthlyChart) {
        monthlyChart.destroy();
    }

    monthlyChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Monthly Income (₱)',
                data: values,
                borderColor: '#0B1D4A',
                backgroundColor: 'rgba(11, 29, 74, 0.1)',
                tension: 0.35,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                title: { display: true, text: 'Monthly Income Trend' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: value => '₱' + Number(value).toLocaleString() }
                }
            }
        }
    });
}

function renderStatusChart(statusData) {
    const labels = Object.keys(statusData).map(status => status.charAt(0).toUpperCase() + status.slice(1));
    const values = Object.values(statusData).map(value => Number(value || 0));

    const ctx = document.getElementById('statusBreakdownChart').getContext('2d');
    if (statusChart) {
        statusChart.destroy();
    }

    statusChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: ['#10B981', '#F59E0B', '#EF4444', '#6C757D'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
                title: { display: true, text: 'Payment Status Distribution' },
                tooltip: {
                    callbacks: {
                        label: context => {
                            const total = context.dataset.data.reduce((acc, value) => acc + value, 0);
                            const value = context.parsed || 0;
                            const percent = total ? ((value / total) * 100).toFixed(1) : '0.0';
                            return `${context.label}: ₱${value.toLocaleString()} (${percent}%)`;
                        }
                    }
                }
            }
        }
    });
}

function renderReportTable(monthlyData) {
    const tbody = document.getElementById('reportTableBody');
    if (!tbody) return;

    let previousIncome = 0;
    const rows = monthlyData.map(month => {
        const income = Number(month.income || 0);
        const count = Number(month.transaction_count || 0);
        const avg = count > 0 ? income / count : 0;
        const growth = previousIncome > 0 ? ((income - previousIncome) / previousIncome) * 100 : 0;
        previousIncome = income;

        return `
            <tr>
                <td>${new Date(month.month + '-01').toLocaleDateString('en-US', { month: 'short', year: 'numeric' })}</td>
                <td>₱${income.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                <td>${count}</td>
                <td>₱${avg.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                <td><span class="badge bg-${growth >= 0 ? 'success' : 'danger'}">${growth >= 0 ? '+' : ''}${growth.toFixed(1)}%</span></td>
            </tr>
        `;
    });

    tbody.innerHTML = rows.join('');
}

function refreshCharts() {
    loadCharts();
}

function exportReport() {
    const printWindow = window.open('', '_blank');
    if (!printWindow || !latestSummary || !latestReportData) {
        console.error('Print window could not be opened or report data is unavailable.');
        return;
    }

    const formatCurrency = amount => '₱' + Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const rowHtml = latestReportData.map(month => {
        const income = Number(month.income || 0);
        const count = Number(month.transaction_count || 0);
        const avg = count > 0 ? income / count : 0;
        return `
            <tr>
                <td>${new Date(month.month + '-01').toLocaleDateString('en-US', { month: 'short', year: 'numeric' })}</td>
                <td>₱${income.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                <td>${count}</td>
                <td>₱${avg.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            </tr>
        `;
    }).join('');

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>IECEP-LSC Financial Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; border-bottom: 2px solid #0B1D4A; padding-bottom: 20px; margin-bottom: 30px; }
                .summary { display: flex; justify-content: space-around; margin: 20px 0; }
                .summary-item { text-align: center; }
                .summary-value { font-size: 24px; font-weight: bold; color: #0B1D4A; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f8f9fa; }
                .footer { margin-top: 40px; text-align: center; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="logo">IECEP-LSC MEMSYS</div>
                <h2>Financial Report</h2>
                <p>Generated on ${new Date().toLocaleDateString()}</p>
            </div>
            <div class="summary">
                <div class="summary-item">
                    <div class="summary-value">${formatCurrency(latestSummary.total_income)}</div>
                    <div>Total Income</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">${formatCurrency(latestSummary.completed_amount)}</div>
                    <div>Completed Payments</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">${latestSummary.transaction_count || 0}</div>
                    <div>Transactions</div>
                </div>
            </div>
            <h3>Monthly Income Trend</h3>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Income</th>
                        <th>Transactions</th>
                        <th>Average per Transaction</th>
                    </tr>
                </thead>
                <tbody>${rowHtml}</tbody>
            </table>
            <div class="footer">
                <p>This report was generated by IECEP-LSC MEMSYS</p>
                <p>Confidential - For Internal Use Only</p>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

document.addEventListener('DOMContentLoaded', loadCharts);