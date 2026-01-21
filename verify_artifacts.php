<?php
/**
 * Artifact Verification Script
 * 
 * Verifies the complete artifact repository functionality
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Security
require_login();
$context = context_system::instance();
require_capability('local/customerintel:manage', $context);

// Set up page
$PAGE->set_url(new moodle_url('/local/customerintel/verify_artifacts.php'));
$PAGE->set_context($context);
$PAGE->set_title('Artifact Repository Verification');
$PAGE->set_heading('Artifact Repository Verification');
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();

echo '<div class="container-fluid">';
echo '<h2>🔍 Artifact Repository Verification</h2>';

// Test 1: Check if table exists
echo '<div class="card mb-3">';
echo '<div class="card-header"><h4>1. Database Table Check</h4></div>';
echo '<div class="card-body">';

$dbman = $DB->get_manager();
$table = new xmldb_table('local_ci_artifact');

if ($dbman->table_exists($table)) {
    echo '<div class="alert alert-success">✅ Table <code>local_ci_artifact</code> exists</div>';
    
    // Check columns
    $columns = $DB->get_columns('local_ci_artifact');
    $expected_columns = ['id', 'runid', 'phase', 'artifacttype', 'jsondata', 'timecreated', 'timemodified'];
    $actual_columns = array_keys($columns);
    $missing_columns = array_diff($expected_columns, $actual_columns);
    
    if (empty($missing_columns)) {
        echo '<div class="alert alert-success">✅ All expected columns present: <code>' . implode(', ', $actual_columns) . '</code></div>';
    } else {
        echo '<div class="alert alert-danger">❌ Missing columns: <code>' . implode(', ', $missing_columns) . '</code></div>';
    }
    
    // Check existing records
    $record_count = $DB->count_records('local_ci_artifact');
    echo '<div class="alert alert-info">📊 Current artifact count: <strong>' . $record_count . '</strong> records</div>';
    
} else {
    echo '<div class="alert alert-danger">❌ Table <code>local_ci_artifact</code> does NOT exist</div>';
    echo '<p>The database upgrade may not have run yet. Check:</p>';
    echo '<ul>';
    echo '<li>Visit <a href="/admin/index.php">Admin Notifications</a> to trigger upgrade</li>';
    echo '<li>Check if version 2025203011 upgrade has run</li>';
    echo '</ul>';
}

echo '</div></div>';

// Test 2: Configuration check
echo '<div class="card mb-3">';
echo '<div class="card-header"><h4>2. Configuration Check</h4></div>';
echo '<div class="card-body">';

$trace_mode = get_config('local_customerintel', 'enable_trace_mode');
if ($trace_mode === '1') {
    echo '<div class="alert alert-success">✅ Trace mode is <strong>ENABLED</strong></div>';
} else {
    echo '<div class="alert alert-warning">⚠️ Trace mode is <strong>DISABLED</strong></div>';
    echo '<p>To enable artifact collection:</p>';
    echo '<ol>';
    echo '<li>Go to <a href="/admin/settings.php?section=local_customerintel_settings">Customer Intelligence Settings</a></li>';
    echo '<li>Enable "Transparent Pipeline Tracing"</li>';
    echo '<li>Save changes</li>';
    echo '</ol>';
}

echo '</div></div>';

// Test 3: Class loading test
echo '<div class="card mb-3">';
echo '<div class="card-header"><h4>3. Class Loading Test</h4></div>';
echo '<div class="card-body">';

try {
    require_once($CFG->dirroot . '/local/customerintel/classes/services/artifact_repository.php');
    $artifact_repo = new \local_customerintel\services\artifact_repository();
    echo '<div class="alert alert-success">✅ Artifact repository class loaded successfully</div>';
    
    // Test method existence
    if (method_exists($artifact_repo, 'save_artifact')) {
        echo '<div class="alert alert-success">✅ <code>save_artifact</code> method exists</div>';
    } else {
        echo '<div class="alert alert-danger">❌ <code>save_artifact</code> method missing</div>';
    }
    
    if (method_exists($artifact_repo, 'get_artifacts_for_run')) {
        echo '<div class="alert alert-success">✅ <code>get_artifacts_for_run</code> method exists</div>';
    } else {
        echo '<div class="alert alert-danger">❌ <code>get_artifacts_for_run</code> method missing</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">❌ Failed to load artifact repository: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

echo '</div></div>';

// Test 4: Recent runs check
echo '<div class="card mb-3">';
echo '<div class="card-header"><h4>4. Recent Runs & Artifacts</h4></div>';
echo '<div class="card-body">';

// Get recent completed runs
$recent_runs = $DB->get_records_select(
    'local_ci_run', 
    'status = ?', 
    ['completed'], 
    'timecompleted DESC', 
    '*', 
    0, 
    5
);

if (!empty($recent_runs)) {
    echo '<h5>Recent Completed Runs:</h5>';
    echo '<table class="table table-sm">';
    echo '<thead><tr><th>Run ID</th><th>Company</th><th>Completed</th><th>Artifacts</th><th>Actions</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($recent_runs as $run) {
        $company = $DB->get_record('local_ci_company', ['id' => $run->companyid]);
        $artifact_count = $DB->count_records('local_ci_artifact', ['runid' => $run->id]);
        
        echo '<tr>';
        echo '<td>' . $run->id . '</td>';
        echo '<td>' . ($company ? htmlspecialchars($company->name) : 'Unknown') . '</td>';
        echo '<td>' . userdate($run->timecompleted) . '</td>';
        echo '<td>';
        if ($artifact_count > 0) {
            echo '<span class="badge badge-success">' . $artifact_count . ' artifacts</span>';
        } else {
            echo '<span class="badge badge-secondary">No artifacts</span>';
        }
        echo '</td>';
        echo '<td>';
        echo '<a href="/local/customerintel/view_report.php?runid=' . $run->id . '" class="btn btn-sm btn-outline-primary">View Report</a> ';
        if ($artifact_count > 0) {
            echo '<a href="/local/customerintel/view_trace.php?runid=' . $run->id . '" class="btn btn-sm btn-outline-success">View Trace</a>';
        }
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
} else {
    echo '<div class="alert alert-info">No completed runs found. Run an intelligence report to test artifact collection.</div>';
}

echo '</div></div>';

// Test 5: Test artifact creation (if trace mode enabled)
if ($trace_mode === '1' && $dbman->table_exists($table)) {
    echo '<div class="card mb-3">';
    echo '<div class="card-header"><h4>5. Test Artifact Creation</h4></div>';
    echo '<div class="card-body">';
    
    try {
        require_once($CFG->dirroot . '/local/customerintel/classes/services/artifact_repository.php');
        $artifact_repo = new \local_customerintel\services\artifact_repository();
        
        $test_data = [
            'test' => true,
            'verification_script' => true,
            'timestamp' => time(),
            'message' => 'Test artifact from verification script'
        ];
        
        $test_runid = 999999; // Use a test run ID
        $result = $artifact_repo->save_artifact($test_runid, 'test', 'verification_test', $test_data);
        
        if ($result) {
            echo '<div class="alert alert-success">✅ Test artifact saved successfully</div>';
            
            // Retrieve the artifact
            $artifacts = $artifact_repo->get_artifacts_for_run($test_runid);
            if (!empty($artifacts)) {
                echo '<div class="alert alert-success">✅ Test artifact retrieved successfully</div>';
                echo '<pre><code>' . htmlspecialchars(json_encode($artifacts[0], JSON_PRETTY_PRINT)) . '</code></pre>';
                
                // Clean up test artifact
                $DB->delete_records('local_ci_artifact', ['runid' => $test_runid]);
                echo '<div class="alert alert-info">🧹 Test artifact cleaned up</div>';
            } else {
                echo '<div class="alert alert-warning">⚠️ Could not retrieve test artifact</div>';
            }
        } else {
            echo '<div class="alert alert-danger">❌ Failed to save test artifact</div>';
        }
        
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">❌ Test failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    
    echo '</div></div>';
}

// Summary and next steps
echo '<div class="card">';
echo '<div class="card-header"><h4>📋 Summary & Next Steps</h4></div>';
echo '<div class="card-body">';

if ($dbman->table_exists($table) && $trace_mode === '1') {
    echo '<div class="alert alert-success">';
    echo '<h5>✅ System Ready for Artifact Collection</h5>';
    echo '<p>Your transparent pipeline view system is properly configured. Next steps:</p>';
    echo '<ol>';
    echo '<li><strong>Run a test intelligence report</strong> through the normal process</li>';
    echo '<li><strong>Check for new artifacts</strong> in the table after synthesis completes</li>';
    echo '<li><strong>View the Data Trace tab</strong> in the report to see captured pipeline data</li>';
    echo '</ol>';
    echo '</div>';
} else {
    echo '<div class="alert alert-warning">';
    echo '<h5>⚠️ Setup Required</h5>';
    echo '<p>Complete these steps to enable artifact collection:</p>';
    echo '<ul>';
    if (!$dbman->table_exists($table)) {
        echo '<li>Ensure database upgrade has run (visit <a href="/admin/index.php">Admin Notifications</a>)</li>';
    }
    if ($trace_mode !== '1') {
        echo '<li>Enable trace mode in <a href="/admin/settings.php?section=local_customerintel_settings">Customer Intelligence Settings</a></li>';
    }
    echo '</ul>';
    echo '</div>';
}

echo '</div></div>';

echo '</div>'; // container-fluid

echo $OUTPUT->footer();
?>