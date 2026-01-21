<?php
/**
 * Cache Performance Analyzer - View cache hits, misses, and effectiveness
 *
 * Access via browser or CLI
 */

// Detect CLI vs Web
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    require_once(__DIR__ . '/../../config.php');
    require_login();
    require_capability('moodle/site:config', context_system::instance());

    echo '<html><head><title>Cache Performance</title>';
    echo '<style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        table { border-collapse: collapse; background: white; width: 100%; margin: 20px 0; }
        th { background: #333; color: white; padding: 10px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f0f0f0; }
        .metric { background: white; padding: 20px; margin: 10px 0; border-left: 4px solid #4CAF50; display: inline-block; width: 200px; }
        .metric-value { font-size: 36px; font-weight: bold; color: #4CAF50; }
        .hit { background-color: #d4edda; }
        .miss { background-color: #fff3cd; }
        .error { background-color: #f8d7da; }
        h2 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 5px; }
    </style></head><body>';
    echo '<h1>Cache Performance Analysis</h1>';
} else {
    define('CLI_SCRIPT', true);
    require_once(__DIR__ . '/../../config.php');
    require_once($CFG->libdir . '/clilib.php');

    echo "\n=== Cache Performance Analysis ===\n\n";
}

global $DB;

// Overall cache statistics
$cache_stats = $DB->get_record_sql("
    SELECT
        COUNT(CASE WHEN metric = 'nb_cache_hit' THEN 1 END) as hits,
        COUNT(CASE WHEN metric = 'nb_cache_miss' THEN 1 END) as misses,
        COUNT(CASE WHEN metric = 'nb_cache_store' THEN 1 END) as stores,
        COUNT(CASE WHEN metric = 'nb_cache_error' THEN 1 END) as errors
    FROM {local_ci_diagnostics}
    WHERE metric LIKE 'nb_cache%'
");

$total = $cache_stats->hits + $cache_stats->misses;
$hit_rate = $total > 0 ? round(100 * $cache_stats->hits / $total, 1) : 0;

if ($is_cli) {
    echo "Overall Statistics:\n";
    echo "-------------------\n";
    echo "Cache Hits:   {$cache_stats->hits}\n";
    echo "Cache Misses: {$cache_stats->misses}\n";
    echo "Cache Stores: {$cache_stats->stores}\n";
    echo "Errors:       {$cache_stats->errors}\n";
    echo "Hit Rate:     {$hit_rate}%\n\n";
} else {
    echo '<h2>Overall Cache Statistics</h2>';
    echo '<div style="margin: 20px 0;">';
    echo '<div class="metric">';
    echo '<div>Cache Hits</div>';
    echo '<div class="metric-value" style="color: #4CAF50;">' . $cache_stats->hits . '</div>';
    echo '</div>';
    echo '<div class="metric">';
    echo '<div>Cache Misses</div>';
    echo '<div class="metric-value" style="color: #FF9800;">' . $cache_stats->misses . '</div>';
    echo '</div>';
    echo '<div class="metric">';
    echo '<div>Hit Rate</div>';
    echo '<div class="metric-value" style="color: #2196F3;">' . $hit_rate . '%</div>';
    echo '</div>';
    echo '</div>';

    if ($cache_stats->errors > 0) {
        echo '<p style="background: #f8d7da; padding: 10px; border-left: 4px solid #dc3545;">';
        echo "<strong>Errors:</strong> {$cache_stats->errors} cache errors detected. Check diagnostics for details.";
        echo '</p>';
    }
}

// Recent cache events
$recent_events = $DB->get_records_sql("
    SELECT
        id,
        metric,
        message,
        timecreated
    FROM {local_ci_diagnostics}
    WHERE metric LIKE 'nb_cache%'
    ORDER BY timecreated DESC
    LIMIT 50
");

if (!empty($recent_events)) {
    if ($is_cli) {
        echo "Recent Cache Events (Last 50):\n";
        echo "--------------------------------\n";
        printf("%-20s %-20s %-60s\n", "Time", "Event", "Details");
        echo str_repeat("-", 100) . "\n";

        foreach ($recent_events as $event) {
            printf("%-20s %-20s %-60s\n",
                date('Y-m-d H:i:s', $event->timecreated),
                $event->metric,
                substr($event->message, 0, 60)
            );
        }
        echo "\n";
    } else {
        echo '<h2>Recent Cache Events (Last 50)</h2>';
        echo '<table>';
        echo '<tr><th>Time</th><th>Event</th><th>Details</th></tr>';

        foreach ($recent_events as $event) {
            $row_class = '';
            if (strpos($event->metric, 'hit') !== false) $row_class = 'hit';
            if (strpos($event->metric, 'miss') !== false) $row_class = 'miss';
            if (strpos($event->metric, 'error') !== false) $row_class = 'error';

            echo "<tr class='{$row_class}'>";
            echo '<td>' . date('Y-m-d H:i:s', $event->timecreated) . '</td>';
            echo '<td>' . htmlspecialchars($event->metric) . '</td>';
            echo '<td>' . htmlspecialchars($event->message) . '</td>';
            echo '</tr>';
        }

        echo '</table>';
    }
}

// Cache effectiveness by company
$company_cache = $DB->get_records_sql("
    SELECT
        c.id,
        c.name as company_name,
        COUNT(n.id) as cached_nbs,
        SUM(LENGTH(n.jsonpayload)) as total_size
    FROM {local_ci_company} c
    LEFT JOIN {local_ci_nb_cache} n ON c.id = n.company_id
    GROUP BY c.id, c.name
    HAVING cached_nbs > 0
    ORDER BY cached_nbs DESC
");

if (!empty($company_cache)) {
    if ($is_cli) {
        echo "Cache Status by Company:\n";
        echo "------------------------\n";
        printf("%-40s %-12s %-15s\n", "Company", "Cached NBs", "Total Size");
        echo str_repeat("-", 70) . "\n";

        foreach ($company_cache as $company) {
            $size_mb = round($company->total_size / 1024 / 1024, 2);
            printf("%-40s %-12s %-15s\n",
                substr($company->company_name, 0, 40),
                $company->cached_nbs,
                $size_mb . ' MB'
            );
        }
    } else {
        echo '<h2>Cache Status by Company</h2>';
        echo '<table>';
        echo '<tr><th>Company</th><th>Cached NBs</th><th>Total Size</th><th>Avg Size per NB</th></tr>';

        foreach ($company_cache as $company) {
            $size_mb = round($company->total_size / 1024 / 1024, 2);
            $avg_kb = round($company->total_size / $company->cached_nbs / 1024, 1);

            echo '<tr>';
            echo '<td>' . htmlspecialchars($company->company_name) . '</td>';
            echo '<td>' . $company->cached_nbs . '</td>';
            echo '<td>' . $size_mb . ' MB</td>';
            echo '<td>' . $avg_kb . ' KB</td>';
            echo '</tr>';
        }

        echo '</table>';
    }
}

// Time series analysis (cache events over time)
$time_series = $DB->get_records_sql("
    SELECT
        DATE(FROM_UNIXTIME(timecreated)) as event_date,
        COUNT(CASE WHEN metric = 'nb_cache_hit' THEN 1 END) as hits,
        COUNT(CASE WHEN metric = 'nb_cache_miss' THEN 1 END) as misses
    FROM {local_ci_diagnostics}
    WHERE metric IN ('nb_cache_hit', 'nb_cache_miss')
    GROUP BY event_date
    ORDER BY event_date DESC
    LIMIT 30
");

if (!empty($time_series)) {
    if ($is_cli) {
        echo "\nCache Activity Over Time (Last 30 Days):\n";
        echo "-----------------------------------------\n";
        printf("%-12s %-8s %-8s %-10s\n", "Date", "Hits", "Misses", "Hit Rate");
        echo str_repeat("-", 40) . "\n";

        foreach ($time_series as $day) {
            $day_total = $day->hits + $day->misses;
            $day_rate = $day_total > 0 ? round(100 * $day->hits / $day_total, 1) : 0;

            printf("%-12s %-8s %-8s %-10s\n",
                $day->event_date,
                $day->hits,
                $day->misses,
                $day_rate . '%'
            );
        }
    } else {
        echo '<h2>Cache Activity Over Time</h2>';
        echo '<table>';
        echo '<tr><th>Date</th><th>Hits</th><th>Misses</th><th>Total</th><th>Hit Rate</th></tr>';

        foreach ($time_series as $day) {
            $day_total = $day->hits + $day->misses;
            $day_rate = $day_total > 0 ? round(100 * $day->hits / $day_total, 1) : 0;

            echo '<tr>';
            echo '<td>' . $day->event_date . '</td>';
            echo '<td>' . $day->hits . '</td>';
            echo '<td>' . $day->misses . '</td>';
            echo '<td>' . $day_total . '</td>';
            echo '<td>' . $day_rate . '%</td>';
            echo '</tr>';
        }

        echo '</table>';
    }
}

// Cache savings estimate
if ($cache_stats->hits > 0) {
    $estimated_tokens_saved = $cache_stats->hits * 15000; // Rough estimate: 15k tokens per NB
    $estimated_cost_saved = $cache_stats->hits * 0.15; // Rough estimate: $0.15 per NB

    if ($is_cli) {
        echo "\nEstimated Savings:\n";
        echo "------------------\n";
        echo "Tokens saved: ~" . number_format($estimated_tokens_saved) . "\n";
        echo "Cost saved:   ~$" . number_format($estimated_cost_saved, 2) . "\n";
    } else {
        echo '<h2>Estimated Savings</h2>';
        echo '<div style="background: white; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50;">';
        echo '<p><strong>Estimated tokens saved:</strong> ~' . number_format($estimated_tokens_saved) . ' tokens</p>';
        echo '<p><strong>Estimated cost saved:</strong> ~$' . number_format($estimated_cost_saved, 2) . '</p>';
        echo '<p style="font-size: 12px; color: #666;">Based on average NB size and API costs. Actual savings may vary.</p>';
        echo '</div>';
    }
}

if (!$is_cli) {
    echo '<p style="margin-top: 30px; padding: 15px; background: white; border-left: 4px solid #2196F3;">';
    echo '<strong>Tip:</strong> Higher hit rates mean better cache utilization. ';
    echo 'Analyze companies against multiple competitors to maximize cache benefits.';
    echo '</p>';
    echo '</body></html>';
} else {
    echo "\n";
}
?>
