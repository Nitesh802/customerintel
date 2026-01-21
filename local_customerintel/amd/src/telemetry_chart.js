/**
 * Telemetry Chart Module for Customer Intelligence Dashboard
 * 
 * Handles Chart.js initialization for telemetry performance metrics
 *
 * @module     local_customerintel/telemetry_chart
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/log'], function(Log) {
    'use strict';

    /**
     * Initialize telemetry chart
     * 
     * @param {string} chartId Canvas element ID
     * @param {object} telemetryData Chart data object
     */
    function init(chartId, telemetryData) {
        
        // Check if Chart.js is available
        if (typeof Chart === 'undefined') {
            Log.error('Chart.js not loaded - telemetry chart cannot be initialized');
            return;
        }

        var canvas = document.getElementById(chartId);
        if (!canvas) {
            Log.error('Canvas element not found: ' + chartId);
            return;
        }

        var ctx = canvas.getContext('2d');
        
        // Prepare chart data
        var chartData = prepareChartData(telemetryData);
        
        // Chart configuration
        var config = {
            type: 'bar',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Synthesis Phase Performance',
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
                                if (context.dataset.label === 'Duration (seconds)') {
                                    label += Math.round(context.parsed.y * 100) / 100 + 's';
                                } else {
                                    label += Math.round(context.parsed.y * 100) / 100;
                                }
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
                            text: 'Synthesis Phases'
                        }
                    },
                    y: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Duration (seconds)'
                        },
                        beginAtZero: true
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        };

        try {
            // Create the chart
            new Chart(ctx, config);
            Log.debug('Telemetry chart initialized successfully for: ' + chartId);
        } catch (error) {
            Log.error('Failed to initialize telemetry chart: ' + error.message);
        }
    }

    /**
     * Prepare chart data from telemetry data
     * 
     * @param {object} telemetryData Raw telemetry data
     * @returns {object} Chart.js data object
     */
    function prepareChartData(telemetryData) {
        var labels = [];
        var durations = [];
        var colors = [];
        
        // Phase color mapping
        var phaseColors = {
            'nb_orchestration': 'rgba(54, 162, 235, 0.8)',
            'synthesis_drafting': 'rgba(255, 99, 132, 0.8)',
            'coherence_engine': 'rgba(255, 205, 86, 0.8)',
            'pattern_comparator': 'rgba(75, 192, 192, 0.8)',
            'qa_scoring': 'rgba(153, 102, 255, 0.8)',
            'synthesis_overall': 'rgba(255, 159, 64, 0.8)'
        };

        // Extract phase duration data
        if (telemetryData.phase_durations) {
            Object.keys(telemetryData.phase_durations).forEach(function(phase) {
                var phaseData = telemetryData.phase_durations[phase];
                var displayName = formatPhaseName(phase);
                
                labels.push(displayName);
                durations.push(phaseData.duration_seconds || 0);
                colors.push(phaseColors[phase] || 'rgba(128, 128, 128, 0.8)');
            });
        }

        // If no phase data, show a placeholder
        if (labels.length === 0) {
            labels.push('No Data');
            durations.push(0);
            colors.push('rgba(200, 200, 200, 0.8)');
        }

        return {
            labels: labels,
            datasets: [{
                label: 'Duration (seconds)',
                data: durations,
                backgroundColor: colors,
                borderColor: colors.map(function(color) {
                    return color.replace('0.8', '1.0');
                }),
                borderWidth: 2,
                borderRadius: 4,
                borderSkipped: false
            }]
        };
    }

    /**
     * Format phase name for display
     * 
     * @param {string} phaseName Raw phase name
     * @returns {string} Formatted display name
     */
    function formatPhaseName(phaseName) {
        var nameMap = {
            'nb_orchestration': 'NB Orchestration',
            'synthesis_drafting': 'Synthesis Drafting',
            'coherence_engine': 'Coherence Analysis',
            'pattern_comparator': 'Pattern Comparison',
            'qa_scoring': 'QA Scoring',
            'synthesis_overall': 'Overall Synthesis'
        };

        return nameMap[phaseName] || phaseName.replace(/_/g, ' ')
            .replace(/\b\w/g, function(l) { return l.toUpperCase(); });
    }

    /**
     * Update chart with new data
     * 
     * @param {string} chartId Canvas element ID
     * @param {object} newData New telemetry data
     */
    function updateChart(chartId, newData) {
        var canvas = document.getElementById(chartId);
        if (!canvas || !canvas.chart) {
            Log.warn('Chart not found for update: ' + chartId);
            return;
        }

        var chart = canvas.chart;
        var chartData = prepareChartData(newData);
        
        chart.data = chartData;
        chart.update('active');
        
        Log.debug('Telemetry chart updated: ' + chartId);
    }

    /**
     * Destroy chart instance
     * 
     * @param {string} chartId Canvas element ID
     */
    function destroyChart(chartId) {
        var canvas = document.getElementById(chartId);
        if (canvas && canvas.chart) {
            canvas.chart.destroy();
            Log.debug('Telemetry chart destroyed: ' + chartId);
        }
    }

    // Public API
    return {
        init: init,
        update: updateChart,
        destroy: destroyChart
    };
});