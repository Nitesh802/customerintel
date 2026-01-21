<?php
/**
 * Debug M0 Synthesis Data
 *
 * Investigates why validation script shows 0 KB for synthesis content
 */

header('Content-Type: text/plain; charset=utf-8');

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $DB, $CFG;

echo "M0 SYNTHESIS DEBUG TOOL\n";
echo str_repeat('=', 80) . "\n\n";

$m0_runs = [103, 104, 105, 106, 107, 108];

// Test 1: Check if runs exist
echo "TEST 1: Check if runs exist in local_ci_run\n";
echo str_repeat('-', 80) . "\n";

foreach ($m0_runs as $runid) {
    $run = $DB->get_record('local_ci_run', ['id' => $runid]);
    if ($run) {
        echo "✅ Run {$runid}: EXISTS (status: {$run->status})\n";
    } else {
        echo "❌ Run {$runid}: NOT FOUND\n";
    }
}
echo "\n";

// Test 2: Check if synthesis records exist
echo "TEST 2: Check if synthesis records exist\n";
echo str_repeat('-', 80) . "\n";

foreach ($m0_runs as $runid) {
    $synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);
    if ($synthesis) {
        $html_size = isset($synthesis->htmlcontent) ? strlen($synthesis->htmlcontent) : 0;
        $json_size = isset($synthesis->jsoncontent) ? strlen($synthesis->jsoncontent) : 0;

        echo "✅ Synthesis for run {$runid}: EXISTS\n";
        echo "   - Synthesis ID: {$synthesis->id}\n";
        echo "   - HTML size: " . round($html_size / 1024, 1) . " KB\n";
        echo "   - JSON size: " . round($json_size / 1024, 1) . " KB\n";
        echo "   - HTML is NULL: " . (is_null($synthesis->htmlcontent) ? "YES" : "NO") . "\n";
        echo "   - HTML is empty: " . (empty($synthesis->htmlcontent) ? "YES" : "NO") . "\n";
        echo "   - JSON is NULL: " . (is_null($synthesis->jsoncontent) ? "YES" : "NO") . "\n";
        echo "   - JSON is empty: " . (empty($synthesis->jsoncontent) ? "YES" : "NO") . "\n";

        if ($html_size > 0) {
            echo "   - HTML preview: " . substr($synthesis->htmlcontent, 0, 100) . "...\n";
        }
    } else {
        echo "❌ Synthesis for run {$runid}: NOT FOUND\n";
    }
    echo "\n";
}

// Test 3: Try the JOIN query that validation script uses
echo "TEST 3: Test JOIN query (validation script method)\n";
echo str_repeat('-', 80) . "\n";

list($insql, $params) = $DB->get_in_or_equal($m0_runs);
$sql = "SELECT
            r.id,
            r.status,
            s.id as synthesis_id,
            LENGTH(s.htmlcontent) as html_size,
            LENGTH(s.jsoncontent) as json_size
        FROM {local_ci_run} r
        LEFT JOIN {local_ci_synthesis} s ON r.id = s.runid
        WHERE r.id $insql
        ORDER BY r.id";

try {
    $runs = $DB->get_records_sql($sql, $params);

    foreach ($m0_runs as $runid) {
        if (isset($runs[$runid])) {
            $run = $runs[$runid];
            echo "Run {$runid}:\n";
            echo "  - synthesis_id: " . ($run->synthesis_id ?? 'NULL') . "\n";
            echo "  - html_size: " . ($run->html_size ?? 'NULL') . "\n";
            echo "  - json_size: " . ($run->json_size ?? 'NULL') . "\n";
        } else {
            echo "Run {$runid}: NOT IN RESULT SET\n";
        }
    }
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Check table structure
echo "TEST 4: Check actual table structure\n";
echo str_repeat('-', 80) . "\n";

try {
    // Get one synthesis record to inspect structure
    $sample = $DB->get_record_sql("SELECT * FROM {local_ci_synthesis} LIMIT 1");

    if ($sample) {
        echo "Sample synthesis record fields:\n";
        foreach ($sample as $field => $value) {
            $type = gettype($value);
            $size = is_string($value) ? strlen($value) : 'N/A';
            echo "  - {$field}: {$type} (size: {$size})\n";
        }
    } else {
        echo "⚠️  No synthesis records found in table\n";
    }
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Count total synthesis records
echo "TEST 5: Count synthesis records\n";
echo str_repeat('-', 80) . "\n";

try {
    $total = $DB->count_records('local_ci_synthesis');
    echo "Total synthesis records in database: {$total}\n";

    if ($total > 0) {
        $sql = "SELECT runid, LENGTH(htmlcontent) as html_size, LENGTH(jsoncontent) as json_size
                FROM {local_ci_synthesis}
                ORDER BY runid DESC
                LIMIT 10";
        $recent = $DB->get_records_sql($sql);

        echo "\nMost recent synthesis records:\n";
        foreach ($recent as $rec) {
            echo "  - Run {$rec->runid}: HTML=" . round($rec->html_size/1024, 1) . "KB, JSON=" . round($rec->json_size/1024, 1) . "KB\n";
        }
    }
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n";
echo str_repeat('=', 80) . "\n";
echo "DEBUG COMPLETE\n";
?>
