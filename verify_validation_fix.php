<?php
/**
 * Verify M3.5 Validation Fix Deployment
 * Checks if the allowed_classes array includes M3.5 CSS classes
 *
 * Usage: https://sales.multi.rubi.digital/local/customerintel/verify_validation_fix.php
 */

require_once('../../config.php');
require_login();

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><title>M3.5 Validation Fix Check</title>";
echo "<style>
body { font-family: monospace; padding: 20px; background: #f5f5f5; }
.box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2196F3; }
.success { border-left-color: #4CAF50; background: #d4edda; }
.error { border-left-color: #f44336; background: #ffebee; }
.warning { border-left-color: #ff9800; background: #fff3cd; }
pre { background: #f0f0f0; padding: 10px; border-radius: 4px; white-space: pre-wrap; overflow-x: auto; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
th { background: #f0f0f0; }
</style></head><body>";

echo "<h1>🔍 M3.5 Validation Fix Verification</h1>";
echo "<p><strong>Checked at:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

$composer_file = $CFG->dirroot . '/local/customerintel/classes/services/synthesis_composer.php';

if (!file_exists($composer_file)) {
    echo "<div class='box error'>";
    echo "<h2>❌ File Not Found</h2>";
    echo "<p>synthesis_composer.php does not exist at expected location</p>";
    echo "</div>";
} else {
    $content = file_get_contents($composer_file);

    echo "<div class='box'>";
    echo "<h2>📄 File Info</h2>";
    echo "<pre>";
    echo "File: {$composer_file}\n";
    echo "Size: " . number_format(strlen($content)) . " bytes\n";
    echo "Last Modified: " . date('Y-m-d H:i:s', filemtime($composer_file)) . "\n";
    $age_minutes = round((time() - filemtime($composer_file)) / 60, 1);
    echo "Age: {$age_minutes} minutes ago\n";
    echo "</pre>";
    echo "</div>";

    // Extract the allowed_classes line
    $pattern = '/\$allowed_classes\s*=\s*\[(.*?)\];/s';
    if (preg_match($pattern, $content, $matches)) {
        $classes_line = trim($matches[0]);
        $classes_content = $matches[1];

        // Parse individual classes
        preg_match_all("/['\"]([^'\"]+)['\"]/", $classes_content, $class_matches);
        $found_classes = $class_matches[1];

        echo "<div class='box'>";
        echo "<h2>📋 Current allowed_classes Array</h2>";
        echo "<pre>" . htmlspecialchars($classes_line) . "</pre>";
        echo "</div>";

        // Check for M3.5 classes
        $required_m35_classes = ['subsection-header', 'perf-gap', 'timeline', 'accountability'];
        $original_classes = ['highlight', 'fact-grid', 'fact'];

        $all_present = true;
        $missing = [];

        echo "<div class='box'>";
        echo "<h2>🔍 Class Verification</h2>";
        echo "<table>";
        echo "<tr><th>Class</th><th>Type</th><th>Status</th></tr>";

        // Check original classes
        foreach ($original_classes as $class) {
            $present = in_array($class, $found_classes);
            $status = $present ? '✅ Present' : '❌ Missing';
            echo "<tr><td>{$class}</td><td>Original</td><td>{$status}</td></tr>";
        }

        // Check M3.5 classes
        foreach ($required_m35_classes as $class) {
            $present = in_array($class, $found_classes);
            if (!$present) {
                $all_present = false;
                $missing[] = $class;
            }
            $status = $present ? '✅ Present' : '❌ MISSING';
            $color = $present ? '' : "style='background:#ffebee;'";
            echo "<tr {$color}><td>{$class}</td><td>M3.5 Required</td><td>{$status}</td></tr>";
        }

        echo "</table>";
        echo "</div>";

        // Final verdict
        if ($all_present) {
            echo "<div class='box success'>";
            echo "<h2>✅ VALIDATION FIX DEPLOYED</h2>";
            echo "<p><strong>All M3.5 CSS classes are present in the allowed list!</strong></p>";
            echo "<p>The validation bug has been fixed. The system will now accept M3.5 formatted HTML.</p>";
            echo "</div>";
        } else {
            echo "<div class='box error'>";
            echo "<h2>❌ VALIDATION FIX NOT DEPLOYED</h2>";
            echo "<p><strong>Missing M3.5 classes:</strong> " . implode(', ', $missing) . "</p>";
            echo "<p>The validation will still reject M3.5 formatted HTML until these classes are added.</p>";
            echo "<p><strong>Required fix at line ~1012:</strong></p>";
            echo "<pre>\$allowed_classes = ['highlight', 'fact-grid', 'fact', 'subsection-header', 'perf-gap', 'timeline', 'accountability'];</pre>";
            echo "</div>";
        }

    } else {
        echo "<div class='box error'>";
        echo "<h2>❌ Could Not Find allowed_classes Array</h2>";
        echo "<p>The validation code may have been restructured or removed.</p>";
        echo "</div>";
    }

    // Check for validation method
    if (strpos($content, 'private function validate_section_html') !== false) {
        echo "<div class='box success'>";
        echo "<h2>✅ Validation Method Found</h2>";
        echo "<p>validate_section_html() method exists in the file</p>";
        echo "</div>";
    } else {
        echo "<div class='box error'>";
        echo "<h2>❌ Validation Method Not Found</h2>";
        echo "<p>The validate_section_html() method is missing from the file</p>";
        echo "</div>";
    }
}

echo "<div class='box'>";
echo "<h2>🚀 Next Steps</h2>";
echo "<ol>";
echo "<li>If validation fix is deployed but Run 237 still failed, clear caches using <a href='force_clear_all_caches.php'>force_clear_all_caches.php</a></li>";
echo "<li>If opcache is active, run on server: <code>sudo service php8.1-fpm reload</code></li>";
echo "<li>Create Run 239 with 'Full Refresh'</li>";
echo "<li>Check logs for [M3.5] entries and no 'HTML validation failed' errors</li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
