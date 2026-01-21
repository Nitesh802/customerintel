<?php
/**
 * Test script for synthesis table setup
 */

require_once(__DIR__ . '/config.php');
require_login();

// Check if synthesis table exists
$dbman = $DB->get_manager();
$table_exists = $dbman->table_exists('local_ci_synthesis');

echo "<h2>Synthesis Table Setup Test</h2>";

if ($table_exists) {
    echo "<p style='color: green;'>✓ local_ci_synthesis table exists</p>";
    
    // Test basic table structure
    $columns = $DB->get_columns('local_ci_synthesis');
    
    $expected_columns = ['id', 'runid', 'htmlcontent', 'jsoncontent', 'voice_report', 'selfcheck_report', 'createdat', 'updatedat'];
    $missing_columns = [];
    
    foreach ($expected_columns as $col) {
        if (!isset($columns[$col])) {
            $missing_columns[] = $col;
        }
    }
    
    if (empty($missing_columns)) {
        echo "<p style='color: green;'>✓ All expected columns present</p>";
    } else {
        echo "<p style='color: red;'>✗ Missing columns: " . implode(', ', $missing_columns) . "</p>";
    }
    
    // Test inserting sample synthesis data
    try {
        $sample_runid = 999999; // Use a non-existent run ID for testing
        
        // Clean up any existing test data
        $DB->delete_records('local_ci_synthesis', ['runid' => $sample_runid]);
        
        $synthesis_record = new stdClass();
        $synthesis_record->runid = $sample_runid;
        $synthesis_record->htmlcontent = '<div><h3>Test Intelligence Playbook</h3><p>This is a test synthesis result.</p></div>';
        $synthesis_record->jsoncontent = json_encode([
            'executive_summary' => 'Test summary',
            'overlooked' => ['Test overlooked point'],
            'opportunities' => [['title' => 'Test opportunity', 'content' => 'Test content']],
            'convergence' => 'Test convergence insight'
        ]);
        $synthesis_record->voice_report = json_encode(['checks' => [], 'score' => 85]);
        $synthesis_record->selfcheck_report = json_encode(['violations' => [], 'pass' => true]);
        $synthesis_record->createdat = time();
        $synthesis_record->updatedat = time();
        
        $id = $DB->insert_record('local_ci_synthesis', $synthesis_record);
        
        if ($id) {
            echo "<p style='color: green;'>✓ Successfully inserted test synthesis record (ID: $id)</p>";
            
            // Test retrieval
            $retrieved = $DB->get_record('local_ci_synthesis', ['id' => $id]);
            if ($retrieved && $retrieved->runid == $sample_runid) {
                echo "<p style='color: green;'>✓ Successfully retrieved test synthesis record</p>";
            } else {
                echo "<p style='color: red;'>✗ Failed to retrieve test synthesis record</p>";
            }
            
            // Clean up
            $DB->delete_records('local_ci_synthesis', ['id' => $id]);
            echo "<p style='color: blue;'>ℹ Test data cleaned up</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to insert test synthesis record</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Database test failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
} else {
    echo "<p style='color: red;'>✗ local_ci_synthesis table does not exist</p>";
    echo "<p>Run the database upgrade to create the table.</p>";
}

// Test view_report.php functionality
echo "<h3>View Report Test</h3>";
echo "<p>To test the view report functionality:</p>";
echo "<ol>";
echo "<li>Create a synthesis record for an existing run</li>";
echo "<li>Visit: <code>/local/customerintel/view_report.php?runid=[run_id]</code></li>";
echo "<li>Verify the Intelligence Playbook is displayed</li>";
echo "<li>Verify the 'Show Raw NB Results' toggle works</li>";
echo "</ol>";

echo "<p><a href='/local/customerintel/dashboard.php'>← Back to Dashboard</a></p>";