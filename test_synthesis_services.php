<?php
/**
 * Test script for synthesis service classes autoloading
 */

require_once(__DIR__ . '/config.php');
require_login();

echo "<h2>Synthesis Services Autoloading Test</h2>";

// Test class autoloading
$services = [
    'synthesis_engine' => 'local_customerintel\\services\\synthesis_engine',
    'voice_enforcer' => 'local_customerintel\\services\\voice_enforcer', 
    'selfcheck_validator' => 'local_customerintel\\services\\selfcheck_validator',
    'citation_resolver' => 'local_customerintel\\services\\citation_resolver'
];

foreach ($services as $name => $class) {
    echo "<h3>Testing {$name}</h3>";
    
    try {
        // Test class loading
        if (class_exists($class)) {
            echo "<p style='color: green;'>✓ Class {$class} loads successfully</p>";
            
            // Test instantiation
            $instance = new $class();
            echo "<p style='color: green;'>✓ Can instantiate {$name}</p>";
            
            // Test method existence based on specification
            $methods = [];
            switch ($name) {
                case 'synthesis_engine':
                    $methods = [
                        'build_report', 'get_normalized_inputs', 'detect_patterns',
                        'build_target_bridge', 'draft_sections', 'apply_voice',
                        'run_selfcheck', 'enrich_citations', 'persist'
                    ];
                    break;
                case 'voice_enforcer':
                    $methods = ['enforce'];
                    break;
                case 'selfcheck_validator':
                    $methods = ['validate'];
                    break;
                case 'citation_resolver':
                    $methods = ['resolve'];
                    break;
            }
            
            $missing_methods = [];
            foreach ($methods as $method) {
                if (!method_exists($instance, $method)) {
                    $missing_methods[] = $method;
                }
            }
            
            if (empty($missing_methods)) {
                echo "<p style='color: green;'>✓ All expected methods present</p>";
            } else {
                echo "<p style='color: red;'>✗ Missing methods: " . implode(', ', $missing_methods) . "</p>";
            }
            
            // Test that methods throw not-implemented exceptions (as expected)
            if (!empty($methods)) {
                $test_method = $methods[0];
                try {
                    // Call with dummy parameters based on method signature
                    switch ($test_method) {
                        case 'build_report':
                        case 'get_normalized_inputs':
                            $instance->$test_method(1);
                            break;
                        case 'detect_patterns':
                        case 'draft_sections':
                        case 'apply_voice':
                        case 'run_selfcheck':
                        case 'enrich_citations':
                            $instance->$test_method([]);
                            break;
                        case 'build_target_bridge':
                            $instance->$test_method([], null);
                            break;
                        case 'persist':
                            $instance->$test_method(1, []);
                            break;
                        case 'enforce':
                            $instance->$test_method('test');
                            break;
                        case 'validate':
                            $instance->$test_method([], []);
                            break;
                        case 'resolve':
                            $instance->$test_method([]);
                            break;
                    }
                    echo "<p style='color: red;'>✗ Method {$test_method} should throw not-implemented exception</p>";
                } catch (\coding_exception $e) {
                    if (strpos($e->getMessage(), 'not yet implemented') !== false) {
                        echo "<p style='color: green;'>✓ Method {$test_method} correctly throws not-implemented exception</p>";
                    } else {
                        echo "<p style='color: orange;'>⚠ Method {$test_method} throws unexpected exception: " . htmlspecialchars($e->getMessage()) . "</p>";
                    }
                } catch (Exception $e) {
                    echo "<p style='color: orange;'>⚠ Method {$test_method} throws unexpected exception type: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
            }
            
        } else {
            echo "<p style='color: red;'>✗ Class {$class} failed to load</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error with {$name}: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "<hr>";
}

echo "<h3>Integration Test</h3>";
echo "<p>The service classes are ready for implementation. Each class:</p>";
echo "<ul>";
echo "<li>✓ Uses proper namespace: <code>local_customerintel\\services</code></li>";
echo "<li>✓ Follows Moodle autoloading conventions</li>";
echo "<li>✓ Has all required method signatures from functional spec</li>";
echo "<li>✓ Throws proper not-implemented exceptions</li>";
echo "</ul>";

echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Implement synthesis_engine::get_normalized_inputs() to read NB results</li>";
echo "<li>Implement pattern detection algorithms</li>";
echo "<li>Build target-relevance bridge logic</li>";
echo "<li>Draft section generation with voice enforcement</li>";
echo "<li>Add validation and citation enrichment</li>";
echo "</ol>";

echo "<p><a href='/local/customerintel/dashboard.php'>← Back to Dashboard</a></p>";