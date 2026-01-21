<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/synthesis_engine.php');

header('Content-Type: text/plain');

echo "=== Synthesis Composer Diagnostic ===\n\n";

// Check if class exists
if (!class_exists('local_customerintel\services\synthesis_engine')) {
    echo "❌ ERROR: synthesis_engine class not found!\n";
    exit;
}

echo "✅ synthesis_engine class loaded\n\n";

// Get all methods in the class
$reflection = new ReflectionClass('local_customerintel\services\synthesis_engine');
$methods = $reflection->getMethods();

echo "Total methods in class: " . count($methods) . "\n\n";

// Check specifically for our new functions
$target_functions = [
    'compose_synthesis_report',
    'draft_section_for_nb',
    'synthesize_section_from_nb',
    'transform_to_executive_voice',
    'generate_closing_statement',
    'assemble_markdown_report'
];

echo "Checking for Phase 2 synthesis functions:\n";
echo "==========================================\n";

foreach ($target_functions as $func) {
    if ($reflection->hasMethod($func)) {
        echo "✅ FOUND: {$func}()\n";
        $method = $reflection->getMethod($func);
        $start_line = $method->getStartLine();
        $end_line = $method->getEndLine();
        echo "   Location: Lines {$start_line}-{$end_line}\n";
    } else {
        echo "❌ MISSING: {$func}()\n";
    }
}

echo "\n";

// Check for integration point in build_report
if ($reflection->hasMethod('build_report')) {
    echo "✅ build_report() method exists\n";

    $method = $reflection->getMethod('build_report');
    $filename = $method->getFileName();
    $start_line = $method->getStartLine();
    $end_line = $method->getEndLine();

    echo "   File: {$filename}\n";
    echo "   Lines: {$start_line}-{$end_line}\n";

    // Read the method source to check if it calls compose_synthesis_report
    $file_contents = file($filename);
    $method_source = implode('', array_slice($file_contents, $start_line - 1, $end_line - $start_line + 1));

    if (strpos($method_source, 'compose_synthesis_report') !== false) {
        echo "   ✅ INTEGRATION CONFIRMED: build_report() calls compose_synthesis_report()\n";

        // Try to find the line number where it's called
        foreach ($file_contents as $line_num => $line) {
            if ($line_num >= $start_line - 1 && $line_num < $end_line) {
                if (strpos($line, 'compose_synthesis_report') !== false) {
                    $actual_line = $line_num + 1;
                    echo "   Call location: Line {$actual_line}\n";
                    break;
                }
            }
        }
    } else {
        echo "   ❌ INTEGRATION MISSING: build_report() does NOT call compose_synthesis_report()\n";
    }
} else {
    echo "❌ build_report() method not found\n";
}

echo "\n=== File Modification Time ===\n";
$file_path = $reflection->getFileName();
$mod_time = filemtime($file_path);
echo "File: {$file_path}\n";
echo "Last modified: " . date('Y-m-d H:i:s', $mod_time) . "\n";
echo "Current time:  " . date('Y-m-d H:i:s') . "\n";

$age_minutes = round((time() - $mod_time) / 60);
echo "File age: {$age_minutes} minutes ago\n";

if ($age_minutes < 5) {
    echo "✅ File was modified very recently\n";
} elseif ($age_minutes < 60) {
    echo "⚠️ File was modified {$age_minutes} minutes ago\n";
} else {
    $age_hours = round($age_minutes / 60, 1);
    echo "⚠️ File was modified {$age_hours} hours ago\n";
}

echo "\n=== Function Signature Check ===\n";
if ($reflection->hasMethod('compose_synthesis_report')) {
    $method = $reflection->getMethod('compose_synthesis_report');

    echo "Function: compose_synthesis_report()\n";
    echo "  Visibility: " . ($method->isPrivate() ? 'private' : ($method->isProtected() ? 'protected' : 'public')) . "\n";
    echo "  Static: " . ($method->isStatic() ? 'yes' : 'no') . "\n";

    $params = $method->getParameters();
    echo "  Parameters: " . count($params) . "\n";
    foreach ($params as $param) {
        echo "    - \${$param->getName()}" . ($param->isOptional() ? ' (optional)' : ' (required)') . "\n";
    }

    echo "  Returns: " . ($method->hasReturnType() ? $method->getReturnType() : 'mixed/untyped') . "\n";
}

echo "\n=== Helper Functions Check ===\n";
$helper_functions = [
    'build_canonical_nb_dataset',
    'log_trace',
    'start_phase_timer',
    'end_phase_timer',
    'get_or',
    'as_array',
    'is_placeholder_nb',
    'detect_patterns',
    'draft_executive_insight',
    'apply_voice_to_text',
    'calculate_qa_scores'
];

$available_helpers = 0;
foreach ($helper_functions as $helper) {
    if ($reflection->hasMethod($helper)) {
        $available_helpers++;
    }
}

echo "Available helper functions: {$available_helpers}/" . count($helper_functions) . "\n";

if ($available_helpers === count($helper_functions)) {
    echo "✅ All required helper functions are available\n";
} else {
    echo "⚠️ Some helper functions may be missing:\n";
    foreach ($helper_functions as $helper) {
        if (!$reflection->hasMethod($helper)) {
            echo "  - Missing: {$helper}()\n";
        }
    }
}

echo "\n=== CitationManager Class Check ===\n";
if (class_exists('local_customerintel\services\CitationManager')) {
    echo "✅ CitationManager class exists\n";
} else {
    echo "⚠️ CitationManager class not found (may be defined in same file)\n";

    // Check if it's defined in the synthesis_engine.php file
    $file_contents = file_get_contents($file_path);
    if (strpos($file_contents, 'class CitationManager') !== false) {
        echo "✅ CitationManager is defined in synthesis_engine.php\n";
    }
}

echo "\n=== Database Table Check ===\n";
global $DB;

$tables_to_check = [
    'local_ci_artifact',
    'local_ci_telemetry',
    'local_ci_diagnostics',
    'local_ci_nb_result'
];

echo "Checking required database tables:\n";
foreach ($tables_to_check as $table) {
    try {
        $count = $DB->count_records($table);
        echo "✅ {$table} exists ({$count} records)\n";
    } catch (Exception $e) {
        echo "❌ {$table} - ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n=== SUMMARY ===\n";

$all_functions_present = true;
foreach ($target_functions as $func) {
    if (!$reflection->hasMethod($func)) {
        $all_functions_present = false;
        break;
    }
}

$integration_present = false;
if ($reflection->hasMethod('build_report')) {
    $method = $reflection->getMethod('build_report');
    $file_contents = file($method->getFileName());
    $method_source = implode('', array_slice($file_contents, $method->getStartLine() - 1,
                                               $method->getEndLine() - $method->getStartLine() + 1));
    $integration_present = (strpos($method_source, 'compose_synthesis_report') !== false);
}

if ($all_functions_present && $integration_present) {
    echo "✅ SUCCESS: All Phase 2 synthesis functions are implemented and integrated!\n";
    echo "\nThe compose_synthesis_report() function is ready to use.\n";
    echo "Next step: Run a synthesis to test the new composer.\n";
} elseif ($all_functions_present && !$integration_present) {
    echo "⚠️ PARTIAL: Functions implemented but NOT integrated into build_report()\n";
    echo "\nAction needed: Add compose_synthesis_report() call in build_report() method\n";
} else {
    echo "❌ INCOMPLETE: Some functions are missing\n";
    echo "\nAction needed: Complete the implementation\n";
}

echo "\n=== END DIAGNOSTIC ===\n";
