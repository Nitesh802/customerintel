/**
 * Analytics Charts Module for Customer Intelligence Dashboard (Slice 10)
 * 
 * Handles Chart.js initialization for analytics visualization
 *
 * @module     local_customerintel/analytics_charts
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/log'], function(Log) {
    'use strict';

    /**
     * Initialize QA trends line chart
     * 
     * @param {string} chartId Canvas element ID
     * @param {object} trendData Chart data object
     */
    function init_trends_chart(chartId, trendData) {
        
        // Check if Chart.js is available
        if (typeof Chart === 'undefined') {
            Log.error('Chart.js not loaded - trends chart cannot be initialized');
            return;
        }

        var canvas = document.getElementById(chartId);
        if (!canvas) {
            Log.error('Canvas element not found: ' + chartId);
            return;
        }

        var ctx = canvas.getContext('2d');
        
        // Chart configuration
        var config = {
            type: 'line',
            data: trendData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'QA Score Trends Over Time',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                var label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += Number(context.parsed.y).toFixed(3);
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Score'
                        },
                        beginAtZero: true,
                        max: 1.0
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                },
                elements: {
                    line: {
                        tension: 0.4
                    }
                }
            }
        };

        try {
            // Create the chart
            new Chart(ctx, config);
            Log.debug('Trends chart initialized successfully for: ' + chartId);
        } catch (error) {
            Log.error('Failed to initialize trends chart: ' + error.message);
        }
    }

    /**
     * Initialize phase duration stacked bar chart
     * 
     * @param {string} chartId Canvas element ID
     * @param {object} phaseData Chart data object
     */
    function init_phase_chart(chartId, phaseData) {
        
        if (typeof Chart === 'undefined') {
            Log.error('Chart.js not loaded - phase chart cannot be initialized');
            return;
        }

        var canvas = document.getElementById(chartId);
        if (!canvas) {
            Log.error('Canvas element not found: ' + chartId);
            return;
        }

        var ctx = canvas.getContext('2d');
        
        var config = {
            type: 'bar',
            data: phaseData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Average Phase Durations',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += Number(context.parsed.y).toFixed(2) + 's';
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        title: {
                            display: true,
                            text: 'Synthesis Phases'
                        }
                    },
                    y: {
                        stacked: true,
                        title: {
                            display: true,
                            text: 'Duration (seconds)'
                        },
                        beginAtZero: true
                    }
                }
            }
        };

        try {
            new Chart(ctx, config);
            Log.debug('Phase chart initialized successfully for: ' + chartId);
        } catch (error) {
            Log.error('Failed to initialize phase chart: ' + error.message);
        }
    }

    /**
     * Initialize QA distribution doughnut chart
     * 
     * @param {string} chartId Canvas element ID
     * @param {object} distributionData Chart data object
     */
    function init_distribution_chart(chartId, distributionData) {
        
        if (typeof Chart === 'undefined') {
            Log.error('Chart.js not loaded - distribution chart cannot be initialized');
            return;
        }

        var canvas = document.getElementById(chartId);
        if (!canvas) {
            Log.error('Canvas element not found: ' + chartId);
            return;
        }

        var ctx = canvas.getContext('2d');
        
        var config = {
            type: 'doughnut',
            data: distributionData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'QA Score Distribution',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    legend: {
                        display: true,
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                var percentage = ((context.parsed * 100) / total).toFixed(1);
                                label += context.parsed + ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        };

        try {
            new Chart(ctx, config);
            Log.debug('Distribution chart initialized successfully for: ' + chartId);
        } catch (error) {
            Log.error('Failed to initialize distribution chart: ' + error.message);
        }
    }

    /**
     * Initialize coherence vs pattern alignment scatter chart
     * 
     * @param {string} chartId Canvas element ID
     * @param {object} correlationData Chart data object
     */
    function init_correlation_chart(chartId, correlationData) {
        
        if (typeof Chart === 'undefined') {
            Log.error('Chart.js not loaded - correlation chart cannot be initialized');
            return;
        }

        var canvas = document.getElementById(chartId);
        if (!canvas) {
            Log.error('Canvas element not found: ' + chartId);
            return;
        }

        var ctx = canvas.getContext('2d');
        
        var config = {
            type: 'scatter',
            data: correlationData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Coherence vs Pattern Alignment Correlation',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var point = context.parsed;
                                var label = 'Run ' + (context.raw.runid || 'Unknown');
                                label += ' - Coherence: ' + Number(point.x).toFixed(3);
                                label += ', Pattern: ' + Number(point.y).toFixed(3);
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'linear',
                        position: 'bottom',
                        title: {
                            display: true,
                            text: 'Coherence Score'
                        },
                        min: 0,
                        max: 1
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Pattern Alignment Score'
                        },
                        min: 0,
                        max: 1
                    }
                }
            }
        };

        try {
            new Chart(ctx, config);
            Log.debug('Correlation chart initialized successfully for: ' + chartId);
        } catch (error) {
            Log.error('Failed to initialize correlation chart: ' + error.message);
        }
    }

    /**
     * Initialize citation diversity vs confidence bubble chart
     * 
     * @param {string} chartId Canvas element ID
     * @param {object} citationData Chart data object
     */
    function init_citation_chart(chartId, citationData) {
        
        if (typeof Chart === 'undefined') {
            Log.error('Chart.js not loaded - citation chart cannot be initialized');
            return;
        }

        var canvas = document.getElementById(chartId);
        if (!canvas) {
            Log.error('Canvas element not found: ' + chartId);
            return;
        }

        var ctx = canvas.getContext('2d');
        
        var config = {
            type: 'bubble',
            data: citationData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Citation Diversity vs Confidence (Bubble size = Citation count)',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var point = context.parsed;
                                var raw = context.raw;
                                var label = raw.company || ('Run ' + (raw.runid || 'Unknown'));
                                label += ' - Confidence: ' + Number(point.x).toFixed(3);
                                label += ', Diversity: ' + Number(point.y).toFixed(3);
                                label += ', Citations: ' + Math.round(raw.r * 2); // Approximate citation count
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'linear',
                        position: 'bottom',
                        title: {
                            display: true,
                            text: 'Confidence Average'
                        },
                        min: 0,
                        max: 1
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Diversity Score'
                        },
                        min: 0,
                        max: 1
                    }
                }
            }
        };

        try {
            new Chart(ctx, config);
            Log.debug('Citation chart initialized successfully for: ' + chartId);
        } catch (error) {
            Log.error('Failed to initialize citation chart: ' + error.message);
        }
    }

    /**
     * Update chart with new data
     * 
     * @param {string} chartId Canvas element ID
     * @param {object} newData New chart data
     */
    function update_chart(chartId, newData) {
        var canvas = document.getElementById(chartId);
        if (!canvas || !canvas.chart) {
            Log.warn('Chart not found for update: ' + chartId);
            return;
        }

        var chart = canvas.chart;
        chart.data = newData;
        chart.update('active');
        
        Log.debug('Chart updated: ' + chartId);
    }

    /**
     * Destroy chart instance
     * 
     * @param {string} chartId Canvas element ID
     */
    function destroy_chart(chartId) {
        var canvas = document.getElementById(chartId);
        if (canvas && canvas.chart) {
            canvas.chart.destroy();
            Log.debug('Chart destroyed: ' + chartId);
        }
    }

    /**
     * Show loading state for chart
     * 
     * @param {string} chartId Canvas element ID
     */
    function show_loading(chartId) {
        var canvas = document.getElementById(chartId);
        if (canvas) {
            var loadingDiv = document.createElement('div');
            loadingDiv.className = 'chart-loading text-center py-4';
            loadingDiv.innerHTML = '<div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div>';
            
            canvas.parentNode.insertBefore(loadingDiv, canvas);
            canvas.style.display = 'none';
        }
    }

    /**
     * Hide loading state for chart
     * 
     * @param {string} chartId Canvas element ID
     */
    function hide_loading(chartId) {
        var canvas = document.getElementById(chartId);
        if (canvas) {
            var loadingDiv = canvas.parentNode.querySelector('.chart-loading');
            if (loadingDiv) {
                loadingDiv.remove();
            }
            canvas.style.display = 'block';
        }
    }

    // Public API
    return {
        init_trends_chart: init_trends_chart,
        init_phase_chart: init_phase_chart,
        init_distribution_chart: init_distribution_chart,
        init_correlation_chart: init_correlation_chart,
        init_citation_chart: init_citation_chart,
        update_chart: update_chart,
        destroy_chart: destroy_chart,
        show_loading: show_loading,
        hide_loading: hide_loading
    };
});