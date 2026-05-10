// Initialize charts for financial reports
async function loadCharts() {
    try {
        const response = await fetch('/IECEP-LSC-MEMSYS/public/api/financial-report.php');
        const data = await response.json();

        // Monthly Income Chart
        const ctx1 = document.getElementById('monthlyIncomeChart').getContext('2d');
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: data.monthly.labels,
                datasets: [{
                    label: 'Monthly Income (₱)',
                    data: data.monthly.data,
                    backgroundColor: '#0B1D4A',
                    borderColor: '#D4AF37',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Status Breakdown Chart
        const ctx2 = document.getElementById('statusBreakdownChart').getContext('2d');
        new Chart(ctx2, {
            type: 'pie',
            data: {
                labels: data.status.labels,
                datasets: [{
                    data: data.status.data,
                    backgroundColor: ['#10B981', '#F59E0B', '#EF4444'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true
            }
        });

        // Update summary stats
        document.getElementById('totalRevenue').textContent = '₱' + data.summary.totalRevenue.toLocaleString();
        document.getElementById('paidCount').textContent = data.summary.paidCount;
        document.getElementById('pendingCount').textContent = data.summary.pendingCount;

    } catch (error) {
        console.error('Error loading charts:', error);
    }
}

// Load charts when page loads
document.addEventListener('DOMContentLoaded', loadCharts);