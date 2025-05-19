document.addEventListener('DOMContentLoaded', function () {
    // Doughnut Chart - Categoria dos Tickets
    const categoriaCtx = document.getElementById('categoriaTicketsChart');
    if (categoriaCtx && typeof categoriaLabels !== 'undefined' && typeof categoriaCounts !== 'undefined') {
        new Chart(categoriaCtx, {
            type: 'doughnut',
            data: {
                labels: categoriaLabels,
                datasets: [{
                    label: 'Tickets por Categoria',
                    data: categoriaCounts,
                    backgroundColor: [
                        '#529ebe', // E-mail (example color)
                        '#28a745', // XD (example color)
                        '#ffc107', // Impressoras (example color)
                        '#dc3545', // Office (example color)
                        '#6f42c1', // Other categories if any
                        '#fd7e14',
                        '#20c997'
                    ],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += context.parsed;
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }

    // Bar Chart - Prioridade dos Tickets
    const prioridadeCtx = document.getElementById('prioridadeTicketsChart');
    if (prioridadeCtx && typeof prioridadeLabels !== 'undefined' && typeof prioridadeCounts !== 'undefined') {
        new Chart(prioridadeCtx, {
            type: 'bar',
            data: {
                labels: prioridadeLabels,
                datasets: [{
                    label: 'Tickets por Prioridade',
                    data: prioridadeCounts,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)', // Baixo
                        'rgba(54, 162, 235, 0.7)', // Médio
                        'rgba(255, 206, 86, 0.7)'  // Alto
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y', // To make it a horizontal bar chart as in the screenshot
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0 // Ensure only whole numbers are shown on the x-axis
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false // Legend is not shown in the screenshot for this chart
                    }
                }
            }
        });
    }
});
