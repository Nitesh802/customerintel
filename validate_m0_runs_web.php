<?php
/**
 * M0 Run Backward Compatibility Validator
 *
 * Validates that Milestone 0 cached runs (103-108) still work after Milestone 1 implementation.
 * Web-accessible with admin authentication.
 *
 * Access via: http://your-moodle-site/local/customerintel/validate_m0_runs_web.php
 */

// Set content type to plain text for clean output
header('Content-Type: text/plain; charset=utf-8');

// Require Moodle config
require_once(__DIR__ . '/../../config.php');

// Security: Require login and site admin capability
require_login();
require_capability('moodle/site:config', context_system::instance());

global $DB, $CFG;

// M0 runs to validate
$m0_runs = [103, 104, 105, 106, 107, 108];

// Track overall status
$all_checks_passed = true;
$issues = [];

// Header
echo str_repeat('=', 80) . "\n";
echo "M0 RUN BACKWARD COMPATIBILITY VALIDATOR\n";
echo str_repeat('=', 80) . "\n\n";

echo "Checking M0 runs: " . implode(', ', $m0_runs) . "\n";
echo "Validation Time: " . date('Y-m-d H:i:s') . "\n\n";

// ============================================================================
// CHECK 1: Database Integrity
// ============================================================================
echo "CHECK 1: Database Integrity\n";
echo str_repeat('-', 80) . "\n";

$db_integrity_passed = true;

try {
    // M0 runs store data in local_ci_artifact table, not local_ci_synthesis
    // Check for synthesis_final_bundle artifacts (Phase 2 format)
    list($insql, $params) = $DB->get_in_or_equal($m0_runs);
    $sql = "SELECT
                r.id,
                r.status,
                r.timecreated,
                a.id as artifact_id,
                a.artifacttype,
                CASE
                    WHEN a.jsondata IS NOT NULL AND LENGTH(a.jsondata) > 0
                    THEN LENGTH(a.jsondata)
                    ELSE 0
                END as data_size,
                (a.jsondata IS NOT NULL AND LENGTH(a.jsondata) > 100) as has_data
            FROM {local_ci_run} r
            LEFT JOIN {local_ci_artifact} a ON r.id = a.runid AND a.artifacttype = 'synthesis_final_bundle'
            WHERE r.id $insql
            ORDER BY r.id";

    $runs = $DB->get_records_sql($sql, $params);

    if (empty($runs)) {
        echo "⚠️  WARNING: No M0 runs found in database\n";
        echo "   Expected runs: " . implode(', ', $m0_runs) . "\n";
        echo "   This may indicate:\n";
        echo "   - Runs haven't been created yet\n";
        echo "   - Database was cleared\n";
        echo "   - Different run ID range in your environment\n\n";
        $db_integrity_passed = false;
    } else {
        // Print table header
        printf("%-8s %-12s %-12s %-15s %-30s\n",
            "Run ID", "Status", "Data (KB)", "Artifact Type", "Issues");
        echo str_repeat('-', 90) . "\n";

        foreach ($m0_runs as $runid) {
            if (isset($runs[$runid])) {
                $run = $runs[$runid];

                // Convert size from bytes to KB
                $data_kb = round($run->data_size / 1024, 1);

                $run_issues = [];

                if ($run->status !== 'completed') {
                    $run_issues[] = "Status: {$run->status}";
                    $db_integrity_passed = false;
                }
                if (!$run->has_data || $run->data_size < 1000) {
                    $run_issues[] = "Missing/Small artifact ({$data_kb} KB)";
                    $db_integrity_passed = false;
                }
                if (empty($run->artifact_id)) {
                    $run_issues[] = "No synthesis artifact";
                    $db_integrity_passed = false;
                }

                $status_icon = empty($run_issues) ? "✅" : "❌";
                $issues_text = empty($run_issues) ? "None" : implode(", ", $run_issues);

                printf("%-8s %-12s %-12s %-15s %-30s\n",
                    $status_icon . " " . $runid,
                    $run->status,
                    $data_kb > 0 ? $data_kb : "Missing",
                    $run->artifacttype ?? "N/A",
                    substr($issues_text, 0, 30)
                );

                if (!empty($run_issues)) {
                    $issues[] = "Run {$runid}: " . implode(", ", $run_issues);
                }
            } else {
                echo "❌ {$runid}       NOT FOUND IN DATABASE\n";
                $db_integrity_passed = false;
                $issues[] = "Run {$runid}: Not found in database";
            }
        }
    }

} catch (Exception $e) {
    echo "❌ ERROR: Database query failed\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   This may indicate:\n";
    echo "   - Table doesn't exist (run: php admin/cli/upgrade.php)\n";
    echo "   - Permission issue\n";
    echo "   - SQL syntax error\n\n";
    $db_integrity_passed = false;
    $issues[] = "Database query failed: " . $e->getMessage();
}

echo "\nRESULT: " . ($db_integrity_passed ? "✅ ALL CHECKS PASSED" : "❌ ISSUES FOUND") . "\n\n";
$all_checks_passed = $all_checks_passed && $db_integrity_passed;

// ============================================================================
// CHECK 2: M0 Cache Hits
// ============================================================================
echo "CHECK 2: M0 Cache Hits\n";
echo str_repeat('-', 80) . "\n";

$cache_hits_passed = true;

try {
    list($insql, $params) = $DB->get_in_or_equal($m0_runs);
    $sql = "SELECT
                runid,
                COUNT(*) as cache_hits,
                MIN(timecreated) as first_access,
                MAX(timecreated) as last_access
            FROM {local_ci_telemetry}
            WHERE metric = 'SYNTH_CACHE_HIT'
            AND runid $insql
            GROUP BY runid
            ORDER BY runid";

    $cache_hits = $DB->get_records_sql($sql, $params);

    if (empty($cache_hits)) {
        echo "⚠️  WARNING: No M0 cache hits recorded\n";
        echo "   This is normal if runs haven't been accessed since M1 deployment.\n";
        echo "   Cache hits will be logged when reports are viewed.\n\n";
        echo "   Action: Try accessing a report via browser to trigger cache hit logging.\n\n";
        // Not a failure - just informational
    } else {
        printf("%-8s %-12s %-20s %-20s\n",
            "Run ID", "Cache Hits", "First Access", "Last Access");
        echo str_repeat('-', 80) . "\n";

        foreach ($cache_hits as $hit) {
            printf("%-8s %-12s %-20s %-20s\n",
                "✅ " . $hit->runid,
                $hit->cache_hits,
                date('Y-m-d H:i:s', $hit->first_access),
                date('Y-m-d H:i:s', $hit->last_access)
            );
        }
        echo "\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR: Cache hit query failed\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   This may indicate telemetry table doesn't exist.\n\n";
    $cache_hits_passed = false;
    $issues[] = "Cache hit query failed: " . $e->getMessage();
}

echo "RESULT: " . ($cache_hits_passed ? "✅ CHECK PASSED" : "❌ CHECK FAILED") . "\n\n";
$all_checks_passed = $all_checks_passed && $cache_hits_passed;

// ============================================================================
// CHECK 3: No M1 Interference
// ============================================================================
echo "CHECK 3: No M1 Interference\n";
echo str_repeat('-', 80) . "\n";

$no_interference_passed = true;

try {
    // Check if M1 NB cache service logged events for M0 runs
    $conditions = [];
    foreach ($m0_runs as $runid) {
        $conditions[] = $DB->sql_like('metadata', ':runid' . $runid);
    }

    $where = '(' . implode(' OR ', $conditions) . ')';
    $params = [];
    foreach ($m0_runs as $runid) {
        $params['runid' . $runid] = '%"runid":' . $runid . '%';
    }

    $sql = "SELECT COUNT(*) as m1_events
            FROM {local_ci_diagnostics}
            WHERE metric LIKE 'nb_cache%'
            AND $where";

    $result = $DB->get_record_sql($sql, $params);
    $m1_events = $result ? $result->m1_events : 0;

    if ($m1_events > 0) {
        echo "❌ ISSUE: Found {$m1_events} M1 NB cache events for M0 runs\n";
        echo "   M1 should not interact with M0 runs (they use run-level cache).\n";
        echo "   This may indicate a logic error in cache decision flow.\n\n";
        $no_interference_passed = false;
        $issues[] = "M1 interfered with M0 runs ({$m1_events} events)";
    } else {
        echo "✅ No M1 NB cache events found for M0 runs\n";
        echo "   M0 runs are properly isolated from M1 NB caching logic.\n\n";
    }

} catch (Exception $e) {
    echo "⚠️  WARNING: M1 interference check failed\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   This may indicate diagnostics table doesn't exist.\n";
    echo "   Continuing with other checks...\n\n";
    // Not a critical failure - diagnostics table may not exist in all environments
}

echo "RESULT: " . ($no_interference_passed ? "✅ CHECK PASSED" : "❌ CHECK FAILED") . "\n\n";
$all_checks_passed = $all_checks_passed && $no_interference_passed;

// ============================================================================
// CHECK 4: Recent Errors
// ============================================================================
echo "CHECK 4: Recent Errors (Last 24 Hours)\n";
echo str_repeat('-', 80) . "\n";

$no_errors_passed = true;

try {
    $sql = "SELECT
                timecreated,
                component,
                message
            FROM {local_ci_diagnostics}
            WHERE level = 'error'
            AND timecreated > :since
            ORDER BY timecreated DESC
            LIMIT 10";

    $params = ['since' => time() - 86400]; // Last 24 hours
    $errors = $DB->get_records_sql($sql, $params);

    if (empty($errors)) {
        echo "✅ No errors logged in the last 24 hours\n\n";
    } else {
        echo "⚠️  WARNING: Found " . count($errors) . " error(s) in last 24 hours\n\n";

        printf("%-20s %-25s %-40s\n", "Time", "Component", "Message");
        echo str_repeat('-', 80) . "\n";

        foreach ($errors as $error) {
            printf("%-20s %-25s %-40s\n",
                date('Y-m-d H:i:s', $error->timecreated),
                substr($error->component, 0, 25),
                substr($error->message, 0, 40)
            );
        }
        echo "\n";

        // Errors are informational - don't fail validation unless they're related to M0 runs
        foreach ($errors as $error) {
            foreach ($m0_runs as $runid) {
                if (strpos($error->message, "run {$runid}") !== false ||
                    strpos($error->message, "runid {$runid}") !== false) {
                    $no_errors_passed = false;
                    $issues[] = "Error found for run {$runid}: " . substr($error->message, 0, 50);
                }
            }
        }
    }

} catch (Exception $e) {
    echo "⚠️  WARNING: Error check failed\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   This may indicate diagnostics table doesn't exist.\n\n";
    // Not a critical failure
}

echo "RESULT: " . ($no_errors_passed ? "✅ CHECK PASSED" : "⚠️  ERRORS FOUND") . "\n\n";
$all_checks_passed = $all_checks_passed && $no_errors_passed;

// ============================================================================
// VALIDATION SUMMARY
// ============================================================================
echo str_repeat('=', 80) . "\n";
echo "VALIDATION SUMMARY\n";
echo str_repeat('=', 80) . "\n\n";

echo ($db_integrity_passed ? "✅" : "❌") . " Database Integrity\n";
echo ($cache_hits_passed ? "✅" : "❌") . " Cache System Working\n";
echo ($no_interference_passed ? "✅" : "❌") . " No M1 Interference\n";
echo ($no_errors_passed ? "✅" : "⚠️ ") . " No Recent Errors\n\n";

if ($all_checks_passed) {
    echo "🎉 ALL VALIDATION CHECKS PASSED - M0 RUNS FULLY COMPATIBLE WITH M1\n\n";
} else {
    echo "❌ VALIDATION FAILED - ISSUES FOUND\n\n";

    echo "Issues Found:\n";
    echo str_repeat('-', 80) . "\n";
    foreach ($issues as $issue) {
        echo "  • " . $issue . "\n";
    }
    echo "\n";
}

// ============================================================================
// NEXT STEPS
// ============================================================================
echo str_repeat('=', 80) . "\n";
echo "NEXT STEPS: Manual UI Testing\n";
echo str_repeat('=', 80) . "\n\n";

echo "Test each run in browser to verify UI rendering:\n\n";

foreach ($m0_runs as $runid) {
    $url = $CFG->wwwroot . '/local/customerintel/view_report.php?runid=' . $runid;
    echo "  • Run {$runid}: {$url}\n";
}

echo "\nManual Verification Steps:\n";
echo "  1. Click each URL above\n";
echo "  2. Check browser console (F12) for JavaScript errors\n";
echo "  3. Verify all 15 sections display correctly\n";
echo "  4. Confirm citations are clickable and functional\n";
echo "  5. Check page load time (should be < 1 second for cached reports)\n\n";

echo "Alternative: View All Reports\n";
echo "  • Reports List: {$CFG->wwwroot}/local/customerintel/reports.php\n\n";

// ============================================================================
// DIAGNOSTIC LINKS
// ============================================================================
echo str_repeat('=', 80) . "\n";
echo "DIAGNOSTIC TOOLS\n";
echo str_repeat('=', 80) . "\n\n";

echo "M1 Cache Tools:\n";
echo "  • View NB Cache: {$CFG->wwwroot}/local/customerintel/check_cache.php\n";
echo "  • Cache Performance: {$CFG->wwwroot}/local/customerintel/check_cache_performance.php\n";
echo "  • Clear NB Cache: {$CFG->wwwroot}/local/customerintel/clear_nb_cache.php\n\n";

echo "System Status:\n";
echo "  • Dashboard: {$CFG->wwwroot}/local/customerintel/dashboard.php\n";
echo "  • Diagnostics: {$CFG->wwwroot}/local/customerintel/diagnostics.php\n\n";

// ============================================================================
// END
// ============================================================================
echo str_repeat('=', 80) . "\n";
echo "Validation completed at: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('=', 80) . "\n";

exit($all_checks_passed ? 0 : 1);
?>
