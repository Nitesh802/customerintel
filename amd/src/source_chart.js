/**
 * Source Breakdown Chart Module for Customer Intelligence Dashboard
 * 
 * Handles Chart.js initialization for citation source type distribution
 *
 * @module     local_customerintel/source_chart
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/log'], function(Log) {
    'use strict';

    /**
     * Initialize source breakdown chart
     * 
     * @param {string} chartId Canvas element ID
     * @param {object} sourceData Source breakdown data
     */
    function init(chartId, sourceData) {
        
        // Check if Chart.js is available
        if (typeof Chart === 'undefined') {
            Log.error('Chart.js not loaded - source chart cannot be initialized');
            return;
        }

        var canvas = document.getElementById(chartId);
        if (!canvas) {
            Log.error('Canvas element not found: ' + chartId);
            return;
        }

        var ctx = canvas.getContext('2d');
        
        // Prepare chart data
        var chartData = prepareSourceChartData(sourceData);
        
        // Chart configuration
        var config = {
            type: 'doughnut',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Citation Source Distribution',
                        font: {
                            size: 14,
                            weight: 'bold'
                        }
                    },
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var label = context.label || '';
                                var value = context.parsed;
                                var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                var percentage = Math.round((value / total) * 100);
                                
                                return label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                cutout: '60%',
                borderWidth: 2,
                hoverBorderWidth: 3,
                animation: {
                    animateScale: true,
                    animateRotate: true
                }
            }
        };

        try {
            // Create the chart
            new Chart(ctx, config);
            Log.debug('Source chart initialized successfully for: ' + chartId);
        } catch (error) {
            Log.error('Failed to initialize source chart: ' + error.message);
        }
    }

    /**
     * Prepare chart data from source breakdown data
     * 
     * @param {object} sourceData Raw source breakdown data
     * @returns {object} Chart.js data object
     */
    function prepareSourceChartData(sourceData) {
        var labels = [];
        var data = [];
        var colors = [];
        
        // Source type color mapping
        var sourceColors = {
            'news': '#FF6384',
            'analyst': '#36A2EB', 
            'company': '#FFCE56',
            'regulatory': '#4BC0C0',
            'industry': '#9966FF',
            'academic': '#FF9F40'
        };

        // Process source breakdown data
        if (sourceData && typeof sourceData === 'object') {
            Object.keys(sourceData).forEach(function(sourceType) {
                var count = parseInt(sourceData[sourceType]) || 0;
                if (count > 0) {
                    labels.push(formatSourceTypeName(sourceType));
                    data.push(count);
                    colors.push(sourceColors[sourceType] || getRandomColor());
                }
            });
        }

        // If no data, show placeholder
        if (labels.length === 0) {
            labels.push('No Data');
            data.push(1);
            colors.push('#E0E0E0');
        }

        return {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors,
                borderColor: '#FFFFFF',
                borderWidth: 2,
                hoverBackgroundColor: colors.map(function(color) {
                    return adjustColorBrightness(color, -20);
                }),
                hoverBorderColor: '#FFFFFF',
                hoverBorderWidth: 3
            }]
        };
    }

    /**
     * Format source type name for display
     * 
     * @param {string} sourceType Raw source type
     * @returns {string} Formatted display name
     */
    function formatSourceTypeName(sourceType) {
        var nameMap = {
            'news': 'News Sources',
            'analyst': 'Analyst Reports',
            'company': 'Company Sources',
            'regulatory': 'Regulatory Filings',
            'industry': 'Industry Reports',
            'academic': 'Academic Sources'
        };

        return nameMap[sourceType] || sourceType.replace(/_/g, ' ')
            .replace(/\b\w/g, function(l) { return l.toUpperCase(); });
    }

    /**
     * Generate random color for unknown source types
     * 
     * @returns {string} Hex color string
     */
    function getRandomColor() {
        var colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'];
        return colors[Math.floor(Math.random() * colors.length)];
    }

    /**
     * Adjust color brightness
     * 
     * @param {string} color Hex color string
     * @param {number} amount Brightness adjustment amount
     * @returns {string} Adjusted hex color
     */
    function adjustColorBrightness(color, amount) {
        var usePound = false;
        
        if (color[0] === "#") {
            color = color.slice(1);
            usePound = true;
        }
        
        var num = parseInt(color, 16);
        var r = (num >> 16) + amount;
        var b = (num >> 8 & 0x00FF) + amount;
        var g = (num & 0x0000FF) + amount;
        
        r = Math.max(0, Math.min(255, r));
        g = Math.max(0, Math.min(255, g));
        b = Math.max(0, Math.min(255, b));
        
        return (usePound ? "#" : "") + String("000000" + (g | (b << 8) | (r << 16)).toString(16)).slice(-6);
    }

    /**
     * Update chart with new data
     * 
     * @param {string} chartId Canvas element ID
     * @param {object} newData New source data
     */
    function updateChart(chartId, newData) {
        var canvas = document.getElementById(chartId);
        if (!canvas || !canvas.chart) {
            Log.warn('Chart not found for update: ' + chartId);
            return;
        }

        var chart = canvas.chart;
        var chartData = prepareSourceChartData(newData);
        
        chart.data = chartData;
        chart.update('active');
        
        Log.debug('Source chart updated: ' + chartId);
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
            Log.debug('Source chart destroyed: ' + chartId);
        }
    }

    // Public API
    return {
        init: init,
        update: updateChart,
        destroy: destroyChart
    };
});