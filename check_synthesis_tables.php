<?php
/**
 * Check synthesis database tables schema
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $CFG;

echo "<h1>Synthesis Tables Schema Check</h1>";

// Check if tables exist
$dbman = $DB->get_manager();

echo "<h2>Table Existence Check</h2>";

$tables_to_check = [
    'local_ci_synthesis',
    'local_ci_synthesis_section'
];

echo "<table border='1' cellpadding='8'>";
echo "<tr><th>Table</th><th>Exists?</th><th>Record Count</th></tr>";

foreach ($tables_to_check as $table) {
    $exists = $dbman->table_exists($table);

    echo "<tr>";
    echo "<td>{$table}</td>";
    echo "<td>" . ($exists ? "✅ Yes" : "❌ No") . "</td>";

    if ($exists) {
        try {
            $count = $DB->count_records($table);
            echo "<td>{$count}</td>";
        } catch (Exception $e) {
            echo "<td>❌ Error: " . htmlspecialchars($e->getMessage()) . "</td>";
        }
    } else {
        echo "<td>N/A</td>";
    }

    echo "</tr>";
}

echo "</table>";

// Show synthesis table structure
echo "<h2>local_ci_synthesis Table Structure</h2>";

if ($dbman->table_exists('local_ci_synthesis')) {
    try {
        $columns = $DB->get_columns('local_ci_synthesis');

        echo "<table border='1' cellpadding='8'>";
        echo "<tr><th>Column</th><th>Type</th></tr>";

        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>{$column->name}</td>";
            echo "<td>{$column->meta_type}</td>";
            echo "</tr>";
        }

        echo "</table>";
    } catch (Exception $e) {
        echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p>❌ Table does not exist!</p>";
}

// Show synthesis_section table structure
echo "<h2>local_ci_synthesis_section Table Structure</h2>";

if ($dbman->table_exists('local_ci_synthesis_section')) {
    try {
        $columns = $DB->get_columns('local_ci_synthesis_section');

        echo "<table border='1' cellpadding='8'>";
        echo "<tr><th>Column</th><th>Type</th></tr>";

        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>{$column->name}</td>";
            echo "<td>{$column->meta_type}</td>";
            echo "</tr>";
        }

        echo "</table>";
    } catch (Exception $e) {
        echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "❌ <strong>Table does not exist!</strong><br>";
    echo "The local_ci_synthesis_section table needs to be created.<br>";
    echo "Run the database upgrade script to create this table.";
    echo "</p>";
}

// Show sample synthesis records
echo "<h2>Sample Synthesis Records</h2>";

try {
    $syntheses = $DB->get_records('local_ci_synthesis', null, 'id DESC', '*', 0, 5);

    if (empty($syntheses)) {
        echo "<p>No synthesis records found.</p>";
    } else {
        echo "<table border='1' cellpadding='8'>";
        echo "<tr><th>ID</th><th>Run ID</th><th>Source ID</th><th>Target ID</th><th>HTML Size</th><th>Created</th></tr>";

        foreach ($syntheses as $synth) {
            echo "<tr>";
            echo "<td>{$synth->id}</td>";
            echo "<td>{$synth->runid}</td>";
            echo "<td>" . ($synth->source_company_id ?? 'NULL') . "</td>";
            echo "<td>" . ($synth->target_company_id ?? 'NULL') . "</td>";
            echo "<td>" . strlen($synth->htmlcontent ?? '') . " bytes</td>";
            echo "<td>" . date('Y-m-d H:i', $synth->createdat ?? 0) . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

?>
