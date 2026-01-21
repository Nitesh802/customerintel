<?php
/**
 * Simple Cache Checker - View cached NBs by company
 *
 * Access via browser or CLI
 */

// Detect CLI vs Web
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    require_once(__DIR__ . '/../../config.php');
    require_login();
    require_capability('moodle/site:config', context_system::instance());

    echo '<html><head><title>NB Cache Contents</title>';
    echo '<style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        table { border-collapse: collapse; background: white; width: 100%; margin: 20px 0; }
        th { background: #333; color: white; padding: 10px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f0f0f0; }
        .summary { background: white; padding: 15px; margin: 20px 0; border-left: 4px solid #4CAF50; }
        h2 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 5px; }
    </style></head><body>';
    echo '<h1>NB Cache Contents</h1>';
} else {
    define('CLI_SCRIPT', true);
    require_once(__DIR__ . '/../../config.php');
    require_once($CFG->libdir . '/clilib.php');

    echo "\n=== NB Cache Contents ===\n\n";
}

global $DB;

// Summary statistics
$summary = $DB->get_record_sql("
    SELECT
        COUNT(*) as total,
        COUNT(DISTINCT company_id) as companies,
        COUNT(DISTINCT nbcode) as nb_types,
        MIN(timecreated) as oldest,
        MAX(timecreated) as newest
    FROM {local_ci_nb_cache}
");

if ($is_cli) {
    echo "Summary:\n";
    echo "--------\n";
    echo "Total cached NBs: {$summary->total}\n";
    echo "Unique companies: {$summary->companies}\n";
    echo "Unique NB types: {$summary->nb_types}\n";
    if ($summary->oldest) {
        echo "Oldest cache: " . date('Y-m-d H:i:s', $summary->oldest) . "\n";
        echo "Newest cache: " . date('Y-m-d H:i:s', $summary->newest) . "\n";
    }
    echo "\n";
} else {
    echo '<div class="summary">';
    echo '<h2>Summary</h2>';
    echo "<p><strong>Total cached NBs:</strong> {$summary->total}</p>";
    echo "<p><strong>Unique companies:</strong> {$summary->companies}</p>";
    echo "<p><strong>Unique NB types:</strong> {$summary->nb_types}</p>";
    if ($summary->oldest) {
        echo "<p><strong>Oldest cache:</strong> " . date('Y-m-d H:i:s', $summary->oldest) . "</p>";
        echo "<p><strong>Newest cache:</strong> " . date('Y-m-d H:i:s', $summary->newest) . "</p>";
    }
    echo '</div>';
}

if ($summary->total > 0) {
    // Detailed cache contents
    $caches = $DB->get_records_sql("
        SELECT
            n.id,
            c.name as company_name,
            n.nbcode,
            n.version,
            LENGTH(n.jsonpayload) as payload_size,
            LENGTH(n.citations) as citations_size,
            n.timecreated
        FROM {local_ci_nb_cache} n
        JOIN {local_ci_company} c ON n.company_id = c.id
        ORDER BY c.name, n.nbcode, n.version
    ");

    if ($is_cli) {
        echo "Cached NBs:\n";
        echo "------------\n";
        printf("%-30s %-10s %-7s %-15s %-15s %-20s\n",
            "Company", "NB Code", "Ver", "Payload Size", "Citations", "Cached At");
        echo str_repeat("-", 100) . "\n";

        foreach ($caches as $cache) {
            printf("%-30s %-10s %-7s %-15s %-15s %-20s\n",
                substr($cache->company_name, 0, 30),
                $cache->nbcode,
                $cache->version,
                number_format($cache->payload_size) . ' bytes',
                $cache->citations_size ? number_format($cache->citations_size) . ' bytes' : 'N/A',
                date('Y-m-d H:i:s', $cache->timecreated)
            );
        }
    } else {
        echo '<h2>Cached NBs by Company</h2>';
        echo '<table>';
        echo '<tr><th>Company</th><th>NB Code</th><th>Version</th><th>Payload Size</th><th>Citations Size</th><th>Cached At</th></tr>';

        foreach ($caches as $cache) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($cache->company_name) . '</td>';
            echo '<td>' . htmlspecialchars($cache->nbcode) . '</td>';
            echo '<td>' . $cache->version . '</td>';
            echo '<td>' . number_format($cache->payload_size) . ' bytes</td>';
            echo '<td>' . ($cache->citations_size ? number_format($cache->citations_size) . ' bytes' : 'N/A') . '</td>';
            echo '<td>' . date('Y-m-d H:i:s', $cache->timecreated) . '</td>';
            echo '</tr>';
        }

        echo '</table>';
    }

    // Per-company summary
    $company_stats = $DB->get_records_sql("
        SELECT
            c.id,
            c.name as company_name,
            COUNT(*) as cached_nbs,
            GROUP_CONCAT(DISTINCT n.nbcode ORDER BY n.nbcode SEPARATOR ', ') as nb_codes,
            MAX(n.timecreated) as last_cached
        FROM {local_ci_nb_cache} n
        JOIN {local_ci_company} c ON n.company_id = c.id
        GROUP BY c.id, c.name
        ORDER BY cached_nbs DESC
    ");

    if ($is_cli) {
        echo "\nPer-Company Summary:\n";
        echo "--------------------\n";
        printf("%-30s %-10s %-50s %-20s\n", "Company", "NB Count", "NB Codes", "Last Cached");
        echo str_repeat("-", 115) . "\n";

        foreach ($company_stats as $stat) {
            printf("%-30s %-10s %-50s %-20s\n",
                substr($stat->company_name, 0, 30),
                $stat->cached_nbs,
                substr($stat->nb_codes, 0, 50),
                date('Y-m-d H:i:s', $stat->last_cached)
            );
        }
    } else {
        echo '<h2>Per-Company Summary</h2>';
        echo '<table>';
        echo '<tr><th>Company</th><th>Cached NBs</th><th>NB Codes</th><th>Last Cached</th></tr>';

        foreach ($company_stats as $stat) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($stat->company_name) . '</td>';
            echo '<td>' . $stat->cached_nbs . '</td>';
            echo '<td style="font-size: 11px;">' . htmlspecialchars($stat->nb_codes) . '</td>';
            echo '<td>' . date('Y-m-d H:i:s', $stat->last_cached) . '</td>';
            echo '</tr>';
        }

        echo '</table>';
    }
} else {
    if ($is_cli) {
        echo "No cached NBs yet. Run an intelligence analysis to populate the cache.\n";
    } else {
        echo '<p style="padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107;">';
        echo 'No cached NBs yet. Run an intelligence analysis to populate the cache.';
        echo '</p>';
    }
}

if (!$is_cli) {
    echo '<p style="margin-top: 30px; padding: 15px; background: white; border-left: 4px solid #2196F3;">';
    echo '<strong>Note:</strong> This shows the Milestone 1 per-company NB cache. ';
    echo 'NBs are cached by company and reused across different competitive analyses.';
    echo '</p>';
    echo '</body></html>';
} else {
    echo "\n";
}
?>
