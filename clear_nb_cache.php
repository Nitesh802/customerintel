<?php
/**
 * Clear NB Cache - Remove all cached NBs
 *
 * Use this after the Milestone 1 bug fix to clear incorrectly cached data
 *
 * Access via browser: http://your-moodle-site/local/customerintel/clear_nb_cache.php
 */

// Detect CLI vs Web
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    require_once(__DIR__ . '/../../config.php');
    require_login();
    require_capability('moodle/site:config', context_system::instance());

    echo '<html><head><title>Clear NB Cache</title>';
    echo '<style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .success { color: green; font-weight: bold; background: #d4edda; padding: 15px; margin: 10px 0; border-left: 4px solid #28a745; }
        .warning { color: orange; font-weight: bold; background: #fff3cd; padding: 15px; margin: 10px 0; border-left: 4px solid #ffc107; }
        .error { color: red; font-weight: bold; background: #f8d7da; padding: 15px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .info { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #17a2b8; }
        button { background: #dc3545; color: white; padding: 10px 20px; border: none; cursor: pointer; font-size: 16px; margin: 10px 0; }
        button:hover { background: #c82333; }
        .stats { background: white; padding: 15px; margin: 10px 0; }
    </style></head><body>';
    echo '<h1>Clear NB Cache</h1>';
} else {
    define('CLI_SCRIPT', true);
    require_once(__DIR__ . '/../../config.php');
    require_once($CFG->libdir . '/clilib.php');

    echo "\n=== Clear NB Cache ===\n\n";
}

global $DB;

// Check if confirm parameter is set
$confirm = optional_param('confirm', false, PARAM_BOOL);

// Get current cache stats before clearing
try {
    $cache_stats = $DB->get_record_sql("
        SELECT
            COUNT(*) as total_entries,
            COUNT(DISTINCT company_id) as companies,
            COUNT(DISTINCT nbcode) as nb_types,
            MIN(timecreated) as oldest,
            MAX(timecreated) as newest
        FROM {local_ci_nb_cache}
    ");

    $company_breakdown = $DB->get_records_sql("
        SELECT
            c.name as company_name,
            COUNT(*) as nb_count,
            GROUP_CONCAT(DISTINCT n.nbcode ORDER BY n.nbcode) as nb_codes
        FROM {local_ci_nb_cache} n
        JOIN {local_ci_company} c ON n.company_id = c.id
        GROUP BY c.id, c.name
        ORDER BY nb_count DESC
    ");

    if ($is_cli) {
        echo "Current Cache Status:\n";
        echo "---------------------\n";
        echo "Total cache entries: {$cache_stats->total_entries}\n";
        echo "Companies cached: {$cache_stats->companies}\n";
        echo "NB types cached: {$cache_stats->nb_types}\n";

        if ($cache_stats->oldest) {
            echo "Oldest entry: " . date('Y-m-d H:i:s', $cache_stats->oldest) . "\n";
            echo "Newest entry: " . date('Y-m-d H:i:s', $cache_stats->newest) . "\n";
        }

        echo "\nCache by Company:\n";
        foreach ($company_breakdown as $company) {
            echo "  - {$company->company_name}: {$company->nb_count} NBs\n";
        }
        echo "\n";
    } else {
        echo '<div class="stats">';
        echo '<h2>Current Cache Status</h2>';
        echo "<p><strong>Total cache entries:</strong> {$cache_stats->total_entries}</p>";
        echo "<p><strong>Companies cached:</strong> {$cache_stats->companies}</p>";
        echo "<p><strong>NB types cached:</strong> {$cache_stats->nb_types}</p>";

        if ($cache_stats->oldest) {
            echo "<p><strong>Oldest entry:</strong> " . date('Y-m-d H:i:s', $cache_stats->oldest) . "</p>";
            echo "<p><strong>Newest entry:</strong> " . date('Y-m-d H:i:s', $cache_stats->newest) . "</p>";
        }

        echo '<h3>Cache by Company</h3>';
        echo '<ul>';
        foreach ($company_breakdown as $company) {
            echo "<li><strong>{$company->company_name}:</strong> {$company->nb_count} NBs</li>";
        }
        echo '</ul>';
        echo '</div>';
    }

    // If not confirmed, show warning and confirmation
    if (!$confirm) {
        if ($is_cli) {
            echo "⚠️  WARNING: This will delete ALL cached NBs ({$cache_stats->total_entries} entries)\n";
            echo "\nTo proceed, run with confirm parameter:\n";
            echo "php clear_nb_cache.php --confirm=1\n\n";
            exit(0);
        } else {
            echo '<div class="warning">';
            echo '<h2>⚠️ Confirmation Required</h2>';
            echo "<p>This will delete <strong>ALL {$cache_stats->total_entries} cached NB entries</strong> from the database.</p>";
            echo '<p>This action is recommended after applying the Milestone 1 bug fix to remove incorrectly cached data.</p>';
            echo '<p><strong>Note:</strong> Cached NBs will be automatically regenerated when you run new intelligence analyses.</p>';
            echo '</div>';

            echo '<form method="post">';
            echo '<input type="hidden" name="confirm" value="1">';
            echo '<button type="submit">Confirm - Clear All Cache</button>';
            echo '</form>';

            echo '<div class="info">';
            echo '<h3>Why Clear the Cache?</h3>';
            echo '<p>The Milestone 1 bug fix corrected the NB-to-company mapping logic. Existing cache entries were stored with incorrect company associations:</p>';
            echo '<ul>';
            echo '<li>All NBs were cached under target companies</li>';
            echo '<li>Source companies have no cached NBs</li>';
            echo '<li>After the fix, NB-1 to NB-7 will cache under source companies</li>';
            echo '<li>NB-8 to NB-15 will cache under target companies</li>';
            echo '</ul>';
            echo '<p>Clearing the cache ensures fresh, correctly-mapped data on the next run.</p>';
            echo '</div>';

            echo '</body></html>';
            exit;
        }
    }

    // Confirmed - proceed with clearing
    if ($is_cli) {
        echo "Clearing cache...\n";
    }

    $DB->delete_records('local_ci_nb_cache');

    // Verify cleared
    $remaining = $DB->count_records('local_ci_nb_cache');

    if ($remaining === 0) {
        if ($is_cli) {
            echo "✅ SUCCESS: Cache cleared successfully\n";
            echo "Deleted {$cache_stats->total_entries} cache entries\n";
            echo "\nNext steps:\n";
            echo "1. Run a new intelligence analysis\n";
            echo "2. Verify correct NB distribution via check_cache.php\n";
            echo "3. Expected: Source company (7 NBs), Target company (8 NBs)\n\n";
        } else {
            echo '<div class="success">';
            echo '<h2>✅ Cache Cleared Successfully</h2>';
            echo "<p><strong>Deleted entries:</strong> {$cache_stats->total_entries}</p>";
            echo "<p><strong>Remaining entries:</strong> 0</p>";
            echo '</div>';

            echo '<div class="info">';
            echo '<h3>Next Steps</h3>';
            echo '<ol>';
            echo '<li>Run a new intelligence analysis (any company pairing)</li>';
            echo '<li>Check cache distribution via <a href="check_cache.php">check_cache.php</a></li>';
            echo '<li>Verify correct mapping:';
            echo '<ul>';
            echo '<li>Source company should have 7 NBs (NB-1 to NB-7)</li>';
            echo '<li>Target company should have 8 NBs (NB-8 to NB-15)</li>';
            echo '</ul></li>';
            echo '</ol>';
            echo '</div>';

            echo '<p><a href="check_cache.php">View Cache Status</a> | <a href="check_cache_performance.php">View Cache Performance</a></p>';

            echo '</body></html>';
        }
    } else {
        if ($is_cli) {
            echo "❌ ERROR: Cache clear failed\n";
            echo "Remaining entries: {$remaining}\n\n";
            exit(1);
        } else {
            echo '<div class="error">';
            echo '<h2>❌ Cache Clear Failed</h2>';
            echo "<p>Expected 0 remaining entries, but found: {$remaining}</p>";
            echo '<p>Please check database permissions or contact system administrator.</p>';
            echo '</div>';
            echo '</body></html>';
        }
    }

} catch (Exception $e) {
    if ($is_cli) {
        echo "❌ ERROR: " . $e->getMessage() . "\n\n";
        exit(1);
    } else {
        echo '<div class="error">';
        echo '<h2>Error</h2>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</div>';
        echo '</body></html>';
    }
}
?>
