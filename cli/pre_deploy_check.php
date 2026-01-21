<?php
/**
 * Pre-deployment Check Script for CustomerIntel
 * 
 * Validates system readiness for production deployment
 * 
 * @package    local_customerintel
 * @copyright  2025 Your Company
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/customerintel/lib.php');

// CLI options
list($options, $unrecognized) = cli_get_params(
    array(
        'help' => false,
        'verbose' => false,
        'skip-mock' => false
    ),
    array('h' => 'help', 'v' => 'verbose')
);

if ($options['help']) {
    echo "CustomerIntel Pre-deployment Check

This script validates that the CustomerIntel plugin is ready for production deployment.

Usage:
    php pre_deploy_check.php [OPTIONS]

Options:
    -h, --help       Show this help message
    -v, --verbose    Verbose output
    --skip-mock      Skip mock run test

Checks performed:
    1. Version validation
    2. Schema consistency
    3. API key configuration
    4. Capabilities setup
    5. Directory permissions
    6. Mock run sanity check
    7. Performance baseline

Example:
    php pre_deploy_check.php
    php pre_deploy_check.php --verbose
";
    exit(0);
}

/**
 * Pre-deployment checker class
 */
class customerintel_predeploy_checker {
    
    private $checks = [];
    private $errors = [];
    private $warnings = [];
    private $verbose;
    private $skip_mock;
    
    const REQUIRED_VERSION = 2025101510;
    const MIN_PHP_VERSION = '7.4.0';
    const MIN_MOODLE_VERSION = 2022041900; // Moodle 4.0
    
    public function __construct($verbose = false, $skip_mock = false) {
        $this->verbose = $verbose;
        $this->skip_mock = $skip_mock;
        
        // Define checks to perform
        $this->checks = [
            'php_version' => 'Check PHP version',
            'moodle_version' => 'Check Moodle version',
            'plugin_version' => 'Check plugin version',
            'database_schema' => 'Validate database schema',
            'api_keys' => 'Check API key configuration',
            'capabilities' => 'Verify capabilities',
            'file_permissions' => 'Check file permissions',
            'dependencies' => 'Check dependencies',
            'mock_run' => 'Perform mock run test',
            'performance' => 'Check performance baseline'
        ];
    }
    
    /**
     * Run all checks
     */
    public function run() {
        $this->log("╔════════════════════════════════════════════════╗");
        $this->log("║     CustomerIntel Pre-deployment Check v1.0     ║");
        $this->log("╚════════════════════════════════════════════════╝");
        $this->log("");
        $this->log("Starting checks at " . date('Y-m-d H:i:s'));
        $this->log("");
        
        $total = count($this->checks);
        $passed = 0;
        $current = 0;
        
        foreach ($this->checks as $key => $description) {
            $current++;
            
            if ($this->skip_mock && $key === 'mock_run') {
                $this->log("[$current/$total] SKIPPED: $description");
                $total--;
                continue;
            }
            
            $this->log("[$current/$total] Checking: $description");
            
            $method = 'check_' . $key;
            if (method_exists($this, $method)) {
                $result = $this->$method();
                
                if ($result === true) {
                    $this->success("  ✓ $description");
                    $passed++;
                } else if ($result === null) {
                    $this->warning("  ⚠ $description - Warning");
                } else {
                    $this->error("  ✗ $description - Failed");
                }
            }
        }
        
        $this->log("");
        $this->output_summary($passed, $total);
        
        return $passed === $total;
    }
    
    /**
     * Check PHP version
     */
    private function check_php_version() {
        $current = PHP_VERSION;
        
        if (version_compare($current, self::MIN_PHP_VERSION, '>=')) {
            $this->verbose_log("    PHP version: $current (OK)");
            return true;
        }
        
        $this->errors[] = "PHP version $current is below minimum " . self::MIN_PHP_VERSION;
        return false;
    }
    
    /**
     * Check Moodle version
     */
    private function check_moodle_version() {
        global $CFG;
        
        if ($CFG->version >= self::MIN_MOODLE_VERSION) {
            $this->verbose_log("    Moodle version: {$CFG->release} (OK)");
            return true;
        }
        
        $this->errors[] = "Moodle version {$CFG->version} is below minimum " . self::MIN_MOODLE_VERSION;
        return false;
    }
    
    /**
     * Check plugin version
     */
    private function check_plugin_version() {
        global $CFG;
        
        $version_file = $CFG->dirroot . '/local/customerintel/version.php';
        if (!file_exists($version_file)) {
            $this->errors[] = "version.php not found";
            return false;
        }
        
        $plugin = new stdClass();
        require($version_file);
        
        if ($plugin->version >= self::REQUIRED_VERSION) {
            $this->verbose_log("    Plugin version: {$plugin->version} (OK)");
            return true;
        }
        
        $this->errors[] = "Plugin version {$plugin->version} is below required " . self::REQUIRED_VERSION;
        return false;
    }
    
    /**
     * Check database schema
     */
    private function check_database_schema() {
        global $DB, $CFG;
        
        // Check required tables exist
        $tables = [
            'local_ci_company',
            'local_ci_source',
            'local_ci_source_chunk',
            'local_ci_run',
            'local_ci_nb_result',
            'local_ci_snapshot',
            'local_ci_diff',
            'local_ci_comparison',
            'local_ci_telemetry',
            'local_ci_log'
        ];
        
        $missing = [];
        foreach ($tables as $table) {
            if (!$DB->get_manager()->table_exists($table)) {
                $missing[] = $table;
            }
        }
        
        if (empty($missing)) {
            $this->verbose_log("    All " . count($tables) . " tables present");
            return true;
        }
        
        $this->errors[] = "Missing tables: " . implode(', ', $missing);
        return false;
    }
    
    /**
     * Check API keys configuration
     */
    private function check_api_keys() {
        $config = get_config('local_customerintel');
        
        $has_keys = false;
        $providers = [];
        
        if (!empty($config->openaiapikey)) {
            $has_keys = true;
            $providers[] = 'OpenAI';
        }
        
        if (!empty($config->claude_api_key)) {
            $has_keys = true;
            $providers[] = 'Claude';
        }
        
        if (!empty($config->local_model_endpoint)) {
            $has_keys = true;
            $providers[] = 'Local';
        }
        
        if ($has_keys) {
            $this->verbose_log("    API keys configured for: " . implode(', ', $providers));
            return true;
        }
        
        // Check if mock mode is enabled
        if (!empty($config->enable_mock_mode)) {
            $this->warnings[] = "No API keys configured, but mock mode is enabled";
            return null; // Warning, not error
        }
        
        $this->errors[] = "No API keys configured and mock mode not enabled";
        return false;
    }
    
    /**
     * Check capabilities
     */
    private function check_capabilities() {
        global $DB;
        
        $required = [
            'local/customerintel:view',
            'local/customerintel:manage',
            'local/customerintel:export'
        ];
        
        $missing = [];
        foreach ($required as $capability) {
            if (!$DB->record_exists('capabilities', ['name' => $capability])) {
                $missing[] = $capability;
            }
        }
        
        if (empty($missing)) {
            $this->verbose_log("    All " . count($required) . " capabilities defined");
            return true;
        }
        
        $this->errors[] = "Missing capabilities: " . implode(', ', $missing);
        return false;
    }
    
    /**
     * Check file permissions
     */
    private function check_file_permissions() {
        global $CFG;
        
        $dirs_to_check = [
            $CFG->dataroot . '/temp/customerintel',
            $CFG->dataroot . '/customerintel'
        ];
        
        $issues = [];
        foreach ($dirs_to_check as $dir) {
            // Create if doesn't exist
            if (!file_exists($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    $issues[] = "Cannot create: $dir";
                }
            } else if (!is_writable($dir)) {
                $issues[] = "Not writable: $dir";
            }
        }
        
        if (empty($issues)) {
            $this->verbose_log("    All directories writable");
            return true;
        }
        
        foreach ($issues as $issue) {
            $this->warnings[] = $issue;
        }
        return null; // Warning, not error
    }
    
    /**
     * Check dependencies
     */
    private function check_dependencies() {
        $dependencies = [];
        $missing = [];
        
        // Check PHP extensions
        $required_extensions = ['json', 'curl', 'mbstring'];
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = "PHP extension: $ext";
            }
        }
        
        // Check optional components
        if (class_exists('TCPDF') || class_exists('Dompdf\Dompdf')) {
            $dependencies[] = 'PDF library';
        } else {
            $this->warnings[] = "PDF library not found (PDF export will be disabled)";
        }
        
        if (empty($missing)) {
            $this->verbose_log("    All required extensions loaded");
            return true;
        }
        
        foreach ($missing as $item) {
            $this->errors[] = "Missing: $item";
        }
        return false;
    }
    
    /**
     * Perform mock run test
     */
    private function check_mock_run() {
        global $DB, $USER;
        
        try {
            // Set admin user
            $admin = get_admin();
            $USER = $admin;
            
            // Create test company
            $company = new stdClass();
            $company->name = 'PREDEPLOY_TEST_' . uniqid();
            $company->ticker = 'TEST';
            $company->type = 'customer';
            $company->website = 'https://predeploytest.com';
            $company->sector = 'Testing';
            $company->metadata = json_encode(['test' => true]);
            $company->timecreated = time();
            $company->timemodified = time();
            
            $company_id = $DB->insert_record('local_ci_company', $company);
            
            // Create test target company
            $target = new stdClass();
            $target->name = 'PREDEPLOY_TARGET_' . uniqid();
            $target->ticker = 'TARG';
            $target->type = 'target';
            $target->website = 'https://predeploytarget.com';
            $target->sector = 'Testing';
            $target->metadata = json_encode(['test' => true]);
            $target->timecreated = time();
            $target->timemodified = time();
            
            $target_id = $DB->insert_record('local_ci_company', $target);
            
            // Create test run
            $run = new stdClass();
            $run->companyid = $company_id;
            $run->targetcompanyid = $target_id;
            $run->initiatedbyuserid = $USER->id;
            $run->userid = $USER->id;
            $run->mode = 'full';
            $run->esttokens = 1000;
            $run->estcost = 0.01;
            $run->actualtokens = 0;
            $run->actualcost = 0.00;
            $run->timestarted = time();
            $run->status = 'running';
            $run->timecreated = time();
            $run->timemodified = time();
            
            $run_id = $DB->insert_record('local_ci_run', $run);
            
            // Test basic record creation and querying (simplified mock test)
            $test_result = $DB->get_record('local_ci_run', ['id' => $run_id]);
            if (!$test_result) {
                throw new Exception("Failed to retrieve test run record");
            }
            
            // Test creating a mock NB result
            $nb_result = new stdClass();
            $nb_result->runid = $run_id;
            $nb_result->nbcode = 'NB1';
            $nb_result->jsonpayload = json_encode(['test' => 'mock_data']);
            $nb_result->citations = json_encode([]);
            $nb_result->durationms = 100;
            $nb_result->tokensused = 50;
            $nb_result->status = 'completed';
            $nb_result->timecreated = time();
            $nb_result->timemodified = time();
            
            $nb_result_id = $DB->insert_record('local_ci_nb_result', $nb_result);
            
            if (!$nb_result_id) {
                throw new Exception("Failed to create test NB result");
            }
            
            // Cleanup test data
            $DB->delete_records('local_ci_nb_result', ['runid' => $run_id]);
            $DB->delete_records('local_ci_run', ['id' => $run_id]);
            $DB->delete_records('local_ci_company', ['id' => $target_id]);
            $DB->delete_records('local_ci_company', ['id' => $company_id]);
            
            $this->verbose_log("    Mock run completed successfully");
            return true;
            
        } catch (Exception $e) {
            $this->errors[] = "Mock run failed: " . $e->getMessage();
            
            // Cleanup on failure
            if (isset($company_id)) {
                $DB->delete_records_select('local_ci_company', 
                    "name LIKE 'PREDEPLOY_TEST_%'");
            }
            
            return false;
        }
    }
    
    /**
     * Check performance baseline
     */
    private function check_performance() {
        global $DB;
        
        // Memory check
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = $this->parse_size($memory_limit);
        $min_memory = 256 * 1024 * 1024; // 256MB
        
        if ($memory_bytes < $min_memory && $memory_limit != '-1') {
            $this->warnings[] = "Memory limit {$memory_limit} below recommended 256MB";
        }
        
        // Execution time check
        $max_execution = ini_get('max_execution_time');
        if ($max_execution > 0 && $max_execution < 300) {
            $this->warnings[] = "Max execution time {$max_execution}s below recommended 300s";
        }
        
        // Database performance check
        $start = microtime(true);
        $count = $DB->count_records('user');
        $elapsed = microtime(true) - $start;
        
        if ($elapsed > 1.0) {
            $this->warnings[] = "Database response slow: {$elapsed}s for simple query";
        }
        
        $this->verbose_log("    Memory: {$memory_limit}, Max execution: {$max_execution}s");
        
        return true; // Performance issues are warnings, not errors
    }
    
    /**
     * Parse size string to bytes
     */
    private function parse_size($size) {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }
        
        return round($size);
    }
    
    /**
     * Output summary
     */
    private function output_summary($passed, $total) {
        $this->log("╔════════════════════════════════════════════════╗");
        $this->log("║                    SUMMARY                      ║");
        $this->log("╚════════════════════════════════════════════════╝");
        $this->log("");
        $this->log("Checks passed: $passed/$total");
        
        if (!empty($this->errors)) {
            $this->log("");
            $this->log("ERRORS (" . count($this->errors) . "):");
            foreach ($this->errors as $error) {
                $this->log("  ✗ $error");
            }
        }
        
        if (!empty($this->warnings)) {
            $this->log("");
            $this->log("WARNINGS (" . count($this->warnings) . "):");
            foreach ($this->warnings as $warning) {
                $this->log("  ⚠ $warning");
            }
        }
        
        $this->log("");
        $this->log("─────────────────────────────────────────────────");
        
        if ($passed === $total) {
            $this->log("");
            $this->success("✅ OK for Production");
            $this->log("");
            $this->log("CustomerIntel is ready for deployment!");
            $this->log("");
        } else {
            $this->log("");
            $this->error("❌ NOT Ready for Production");
            $this->log("");
            $this->log("Please address the errors above before deploying.");
            $this->log("");
        }
        
        // Save report
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'passed' => $passed,
            'total' => $total,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'status' => $passed === $total ? 'READY' : 'NOT_READY'
        ];
        
        $filename = 'predeploy_check_' . date('Ymd_His') . '.json';
        file_put_contents(__DIR__ . '/' . $filename, json_encode($report, JSON_PRETTY_PRINT));
        $this->log("Report saved to: $filename");
    }
    
    /**
     * Log message
     */
    private function log($message) {
        echo "$message\n";
    }
    
    /**
     * Log verbose message
     */
    private function verbose_log($message) {
        if ($this->verbose) {
            echo "$message\n";
        }
    }
    
    /**
     * Log success
     */
    private function success($message) {
        echo "\033[32m$message\033[0m\n"; // Green
    }
    
    /**
     * Log warning
     */
    private function warning($message) {
        echo "\033[33m$message\033[0m\n"; // Yellow
    }
    
    /**
     * Log error
     */
    private function error($message) {
        echo "\033[31m$message\033[0m\n"; // Red
    }
}

// Main execution
$checker = new customerintel_predeploy_checker(
    $options['verbose'],
    $options['skip-mock']
);

$success = $checker->run();
exit($success ? 0 : 1);