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
                        '#87CEEB', // E-mail (light sky blue)
'#90EE90', // XD (light green)
'#FFE135', // Impressoras (light yellow)
'#FFB6C1', // Office (light pink)
'#DDA0DD', // Other categories (light plum)
'#FFAB91', // Light orange
'#B2DFDB', // Light teal
'#F0E68C', // Light khaki
'#E6E6FA'  // Light lavender
                    ],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%', // Added cutout percentage for a nicer doughnut
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.parsed || 0;
                                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                
                                return `${label}: ${value} (${percentage}%)`;
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
