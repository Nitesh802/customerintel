# CustomerIntel Testing Guide

## Overview

This guide provides comprehensive instructions for testing the CustomerIntel plugin, including unit tests, integration tests, and manual testing procedures.

## Table of Contents

1. [Test Environment Setup](#test-environment-setup)
2. [PHPUnit Tests](#phpunit-tests)
3. [CLI Testing Tools](#cli-testing-tools)
4. [Manual Testing](#manual-testing)
5. [Performance Testing](#performance-testing)
6. [Security Testing](#security-testing)

## Test Environment Setup

### Prerequisites

1. **Development Moodle Instance**
   ```bash
   # Clone Moodle
   git clone https://github.com/moodle/moodle.git
   cd moodle
   git checkout MOODLE_40_STABLE
   ```

2. **PHPUnit Configuration**
   ```bash
   # Initialize PHPUnit
   php admin/tool/phpunit/cli/init.php
   ```

3. **Configure Test Database**
   ```php
   // config.php
   $CFG->phpunit_prefix = 'phpu_';
   $CFG->phpunit_dataroot = '/path/to/phpunit_dataroot';
   $CFG->phpunit_dbtype = 'mysqli';
   $CFG->phpunit_dbhost = 'localhost';
   $CFG->phpunit_dbname = 'moodle_test';
   $CFG->phpunit_dbuser = 'root';
   $CFG->phpunit_dbpass = 'password';
   ```

## PHPUnit Tests

### Running All Tests

```bash
# Run all CustomerIntel tests
vendor/bin/phpunit --testsuite local_customerintel_testsuite

# Run with coverage
vendor/bin/phpunit --testsuite local_customerintel_testsuite --coverage-html coverage/
```

### Individual Test Classes

```bash
# Test LLM Client
vendor/bin/phpunit local/customerintel/tests/llm_client_test.php

# Test NB Orchestrator
vendor/bin/phpunit local/customerintel/tests/nb_orchestrator_test.php

# Test Assembler
vendor/bin/phpunit local/customerintel/tests/assembler_test.php

# Test Cost Service
vendor/bin/phpunit local/customerintel/tests/cost_service_test.php

# Test Job Queue
vendor/bin/phpunit local/customerintel/tests/job_queue_test.php

# Test Versioning Service
vendor/bin/phpunit local/customerintel/tests/versioning_service_test.php

# Full Integration Test
vendor/bin/phpunit local/customerintel/tests/integration_fullstack_test.php
```

### Test Examples

#### Example 1: Testing LLM Client

```php
public function test_llm_client_mock_mode() {
    $client = new \local_customerintel\clients\llm_client();
    $client->set_mock_mode(true);
    
    $response = $client->complete('Test prompt', 'gpt-4');
    
    $this->assertNotEmpty($response);
    $this->assertStringContainsString('Mock response', $response);
}
```

#### Example 2: Testing NB Processing

```php
public function test_nb_processing() {
    $orchestrator = new \local_customerintel\services\nb_orchestrator();
    $orchestrator->set_mock_mode(true);
    
    $result = $orchestrator->process_single_nb($run_id, 'nb1_industry_analysis');
    
    $this->assertTrue($result);
    $this->assertDatabaseHas('local_customerintel_nb_result', [
        'run_id' => $run_id,
        'nb_type' => 'nb1_industry_analysis'
    ]);
}
```

## CLI Testing Tools

### 1. Integration Test Harness

The main testing tool for end-to-end validation:

```bash
# Basic run
php local/customerintel/cli/test_integration.php

# Verbose output
php local/customerintel/cli/test_integration.php --verbose

# Stress testing (10 concurrent runs)
php local/customerintel/cli/test_integration.php --mode=stress --concurrent=10

# Security testing
php local/customerintel/cli/test_integration.php --mode=security

# Performance testing
php local/customerintel/cli/test_integration.php --mode=performance

# Full test suite
php local/customerintel/cli/test_integration.php --mode=full --stress --security --performance
```

#### Expected Output

```
=== CustomerIntel QA Integration Test ===
Starting at: 2025-01-13 10:30:45

[Phase 1] Setting up test data...
✓ Created test company
✓ Created test target

[Phase 2] Testing SourceService...
✓ Added 3 sources

[Phase 3] Testing NBOrchestrator (15 NBs)...
✓ Processed 15/15 NBs successfully

[Phase 4] Testing VersioningService...
✓ Created snapshot
✓ Generated diff

[Phase 5] Testing Assembler...
✓ Generated HTML report (145KB)

[Phase 6] Testing CostService...
✓ Cost variance: 8.3% (within 25% tolerance)

=====================================
        QA TEST SUMMARY              
=====================================
Status:       SUCCESS
Runtime:      28.45 seconds
Token Count:  15,250
Total Cost:   $0.0420
Reused:       32.5%
```

### 2. Schema Consistency Checker

Validates database schema alignment:

```bash
# Check schema
php local/customerintel/cli/check_schema_consistency.php

# Verbose mode
php local/customerintel/cli/check_schema_consistency.php --verbose

# Auto-fix issues (use with caution)
php local/customerintel/cli/check_schema_consistency.php --fix
```

#### Expected Output

```
=== CustomerIntel Schema Consistency Check ===
Checking 8 tables...

[1/8] Checking table: local_customerintel_company
[2/8] Checking table: local_customerintel_target
...
[8/8] Checking table: local_customerintel_telemetry

✓ Capability 'local/customerintel:view' exists
✓ Capability 'local/customerintel:manage' exists
✓ Capability 'local/customerintel:export' exists

=====================================
     SCHEMA CHECK SUMMARY           
=====================================
Tables checked:   8
Issues found:     0
Warnings:         0

✓ Schema is consistent with install.xml
```

### 3. Pre-Deployment Check

Final validation before production:

```bash
php local/customerintel/cli/pre_deploy_check.php
```

## Manual Testing

### Test Case 1: Company Creation and Analysis

1. **Login as Admin**
2. **Navigate to CustomerIntel**
   - Go to Navigation > CustomerIntel
3. **Create Company**
   - Click "Add Company"
   - Enter: Name, Domain, Industry, Size
   - Save
4. **Add Target**
   - Click "Add Target"
   - Configure ICP fit, use case, decision stage
   - Save
5. **Add Sources**
   - Add website URL
   - Add LinkedIn profile
   - Add news sources
6. **Run Analysis**
   - Click "Run Analysis"
   - Select mock mode for testing
   - Monitor progress
7. **Verify Results**
   - Check all 15 NBs completed
   - Verify HTML report generated
   - Test PDF export
   - Check cost calculations

### Test Case 2: Report Generation

1. **Generate HTML Report**
   - Navigate to completed run
   - Click "Generate Report"
   - Verify all sections present
2. **Export to PDF**
   - Click "Export as PDF"
   - Verify PDF downloads
3. **Export to JSON**
   - Click "Export as JSON"
   - Verify valid JSON structure

### Test Case 3: Versioning

1. **Create Initial Snapshot**
   - Complete a run
   - Click "Create Snapshot"
   - Name: "Baseline"
2. **Make Changes**
   - Run analysis again
   - Modify some data
3. **Create Second Snapshot**
   - Click "Create Snapshot"
   - Name: "Updated"
4. **Compare Versions**
   - Click "Compare Versions"
   - Select both snapshots
   - Verify diff display

## Performance Testing

### Load Testing

```bash
# Run concurrent analyses
for i in {1..10}; do
    php local/customerintel/cli/test_integration.php &
done
wait
```

### Metrics to Monitor

1. **Response Time**
   - Target: P95 < 15 minutes
   - Measure: End-to-end completion

2. **Token Usage**
   - Target: 25-40% reuse rate
   - Monitor: Telemetry records

3. **Memory Usage**
   - Target: < 256MB peak
   - Tool: `memory_get_peak_usage()`

4. **Database Queries**
   - Target: < 100 per run
   - Tool: `$DB->perf_get_queries()`

## Security Testing

### 1. Access Control

```php
// Test capability checks
$this->expectException('required_capability_exception');
$this->setUser($student);
$page = new \local_customerintel\output\view_page($company_id);
```

### 2. Input Validation

```bash
# Test SQL injection attempts
curl -X POST http://site.com/local/customerintel/api.php \
  -d "company_name='; DROP TABLE users;--"
```

### 3. API Key Security

```php
// Verify encryption
$company = $DB->get_record('local_customerintel_company', ['id' => 1]);
$this->assertNotEquals('plain_api_key', $company->api_keys);
$this->assertTrue(is_encrypted($company->api_keys));
```

## Troubleshooting

### Common Issues

1. **PHPUnit Not Found**
   ```bash
   composer install
   php admin/tool/phpunit/cli/init.php
   ```

2. **Database Connection Failed**
   - Check config.php settings
   - Verify test database exists
   - Check user permissions

3. **Memory Limit Exceeded**
   ```php
   ini_set('memory_limit', '256M');
   ```

4. **API Rate Limits**
   - Use mock mode for testing
   - Implement rate limiting
   - Add retry logic

### Debug Mode

Enable debug output:

```php
// config.php
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;
```

## Test Coverage Report

Generate coverage report:

```bash
vendor/bin/phpunit --testsuite local_customerintel_testsuite \
  --coverage-html coverage/ \
  --coverage-text
```

Expected coverage targets:
- Line Coverage: > 80%
- Function Coverage: > 90%
- Class Coverage: 100%

## Continuous Integration

### GitHub Actions Example

```yaml
name: CustomerIntel Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.0'
        
    - name: Install dependencies
      run: composer install
      
    - name: Run PHPUnit
      run: vendor/bin/phpunit --testsuite local_customerintel_testsuite
      
    - name: Run integration tests
      run: php local/customerintel/cli/test_integration.php
```

## Best Practices

1. **Always Use Mock Mode** for automated testing
2. **Reset Test Data** between test runs
3. **Monitor Resource Usage** during stress tests
4. **Document Test Failures** with screenshots/logs
5. **Version Test Data** for reproducibility
6. **Run Full Suite** before releases

## Support

For testing support:
- Check [FAQ](FAQ.md)
- Report issues on [GitHub](https://github.com/yourorg/customerintel/issues)
- Contact development team

---

Last Updated: 2025-01-13  
Version: 1.0.0