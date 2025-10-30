/**
 * Analytics Page JavaScript
 * Handles chart rendering and interactive features
 */

jQuery(document).ready(function($) {
    // Check if Chart.js is loaded
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded');
        return;
    }

    // Render Submissions Over Time Chart
    if ($('#submissions-chart').length && typeof submissionsData !== 'undefined') {
        renderSubmissionsChart();
    }

    // Render Products Popularity Chart
    if ($('#products-chart').length && typeof productsData !== 'undefined') {
        renderProductsChart();
    }

    function renderSubmissionsChart() {
        var ctx = document.getElementById('submissions-chart').getContext('2d');

        var labels = submissionsData.map(function(item) {
            return item.date;
        });

        var data = submissionsData.map(function(item) {
            return parseInt(item.count);
        });

        var chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Submissions',
                    data: data,
                    backgroundColor: 'rgba(0, 115, 170, 0.1)',
                    borderColor: 'rgba(0, 115, 170, 1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: 'rgba(0, 115, 170, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleFont: {
                            size: 14
                        },
                        bodyFont: {
                            size: 13
                        },
                        padding: 12,
                        cornerRadius: 6,
                        callbacks: {
                            title: function(context) {
                                return 'Date: ' + context[0].label;
                            },
                            label: function(context) {
                                return 'Submissions: ' + context.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: {
                                size: 12
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 12
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    }

    function renderProductsChart() {
        var ctx = document.getElementById('products-chart').getContext('2d');

        var labels = productsData.map(function(item) {
            var name = item.product_name;
            if (name.length > 30) {
                name = name.substring(0, 27) + '...';
            }
            return name;
        });

        var data = productsData.map(function(item) {
            return parseInt(item.total_quantity);
        });

        // Generate colors for each bar
        var backgroundColors = productsData.map(function(item, index) {
            var colors = [
                'rgba(0, 115, 170, 0.8)',
                'rgba(46, 125, 50, 0.8)',
                'rgba(230, 81, 0, 0.8)',
                'rgba(198, 40, 40, 0.8)',
                'rgba(123, 31, 162, 0.8)',
                'rgba(2, 136, 209, 0.8)',
                'rgba(251, 140, 0, 0.8)',
                'rgba(69, 90, 100, 0.8)',
                'rgba(0, 151, 167, 0.8)',
                'rgba(124, 179, 66, 0.8)'
            ];
            return colors[index % colors.length];
        });

        var chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Total Quantity',
                    data: data,
                    backgroundColor: backgroundColors,
                    borderWidth: 0,
                    borderRadius: 6,
                    barThickness: 40
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleFont: {
                            size: 14
                        },
                        bodyFont: {
                            size: 13
                        },
                        padding: 12,
                        cornerRadius: 6,
                        callbacks: {
                            title: function(context) {
                                var fullName = productsData[context[0].dataIndex].product_name;
                                return fullName;
                            },
                            label: function(context) {
                                return 'Quantity: ' + context.parsed.x;
                            },
                            afterLabel: function(context) {
                                var userCount = productsData[context.dataIndex].user_count;
                                return 'Users: ' + userCount;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: {
                                size: 12
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    y: {
                        ticks: {
                            font: {
                                size: 11
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
});
