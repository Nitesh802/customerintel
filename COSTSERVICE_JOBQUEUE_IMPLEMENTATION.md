# CostService & JobQueue Implementation Summary

## Overview
Completed implementation of CostService and JobQueue components for the Customer Intelligence Dashboard (local_customerintel) as specified in PRD sections 8.7 (Cost Estimator & Telemetry) and 11 (Architecture Overview).

## Components Implemented

### 1. CostService (`classes/services/cost_service.php`)
Enhanced existing service with complete functionality for cost estimation, tracking, and enforcement.

#### Key Features:
- **Cost Estimation**: Pre-run token and cost estimation with provider-specific pricing
- **Threshold Enforcement**: Warning and hard limit thresholds with run blocking
- **Variance Analysis**: Tracks estimated vs actual costs with calibration
- **Reuse Detection**: Identifies reusable snapshots to reduce costs
- **Provider Support**: Multi-provider pricing (OpenAI GPT-4/3.5, Anthropic Claude)
- **Dashboard Analytics**: Cost history, trends, and accuracy metrics

#### Key Methods:
- `estimate_cost()`: Calculate estimated cost with breakdown by NB
- `record_actuals()`: Store actual costs and compute variance
- `check_thresholds()`: Enforce warning and hard limits
- `get_cost_history()`: Retrieve historical cost data
- `get_dashboard_data()`: Prepare cost analytics for dashboard
- `calculate_token_cost()`: Calculate cost for token usage

### 2. JobQueue (`classes/services/job_queue.php`)
Complete implementation using Moodle's adhoc task API for background processing.

#### Key Features:
- **Queue Management**: Queue runs with cost pre-validation
- **Background Execution**: Asynchronous run processing via adhoc tasks
- **Retry Logic**: Exponential backoff with max 3 retries
- **Progress Tracking**: Real-time progress monitoring
- **Failure Handling**: Graceful error recovery with telemetry
- **Cleanup**: Automatic archival of old runs

#### Key Methods:
- `queue_run()`: Queue new intelligence run with cost check
- `execute_run()`: Execute queued run with NB orchestration
- `handle_failure()`: Manage failures with retry scheduling
- `get_run_progress()`: Track execution progress
- `cancel_run()`: Cancel queued/retrying runs
- `get_queue_stats()`: Queue performance metrics

### 3. Execute Run Task (`classes/task/execute_run_task.php`)
Moodle adhoc task for background execution.

#### Features:
- Integrates with Moodle task queue
- Handles run execution with error recovery
- Supports retry attempts with backoff
- Provides detailed execution logging

### 4. Telemetry Service (`classes/services/telemetry_service.php`)
New service for comprehensive telemetry and analytics.

#### Features:
- **Dashboard Data**: Aggregated metrics for visualization
- **Performance Metrics**: NB execution times, queue performance
- **Cost Analytics**: Variance analysis, provider comparison
- **Error Analytics**: Failure patterns and trends
- **Time Series Data**: Historical trends for charts
- **Real-time Status**: Current queue and execution state

#### Key Methods:
- `get_dashboard_data()`: Complete dashboard analytics
- `get_queue_status()`: Real-time queue monitoring
- `get_estimation_accuracy()`: Cost estimation accuracy metrics
- `export_telemetry()`: Export data for external analysis

### 5. CLI Test Tool (`cli/test_job_queue.php`)
Comprehensive CLI tool for testing job queue functionality.

#### Capabilities:
- Queue single/comparison runs
- Execute specific runs
- Monitor run progress
- Check run status
- View queue statistics
- Test failure/retry scenarios
- Clean up old runs

#### Usage Examples:
```bash
# Queue a new run
php test_job_queue.php --mode=queue --company=1

# Execute a run
php test_job_queue.php --mode=execute --runid=5

# Check status
php test_job_queue.php --mode=status --runid=5

# View statistics
php test_job_queue.php --mode=stats
```

### 6. PHPUnit Tests

#### CostService Tests (`tests/cost_service_test.php`)
- Cost estimation for single/comparison runs
- Threshold enforcement (warning/hard limits)
- Actual cost recording
- Variance calculation
- Cost history retrieval
- Dashboard data preparation
- Reuse detection
- Token cost calculation

#### JobQueue Tests (`tests/job_queue_test.php`)
- Run queueing with cost validation
- Status updates and transitions
- Progress tracking
- Retry count management
- Run cancellation
- Queue statistics
- Old run cleanup
- Failure handling with retries
- NB breakdown retrieval

## Database Integration

### Telemetry Storage
Comprehensive telemetry recording in `mdl_local_ci_telemetry`:
- Cost estimates and actuals
- Token usage by NB
- Execution durations
- Retry attempts
- Error events
- Calibration factors

### Run Tracking
Complete run lifecycle in `mdl_local_ci_run`:
- Status transitions (queued → running → completed/failed)
- Cost estimates and actuals
- Token usage tracking
- Error details
- Timing information

## PRD Compliance

### Section 8.7 (Cost Estimator & Telemetry)
✅ Pre-run estimation with provider-specific pricing
✅ Token and cost tracking at NB level
✅ Variance analysis and calibration
✅ Warning threshold with user confirmation
✅ Hard limit with run blocking
✅ Comprehensive telemetry capture

### Section 11 (Architecture Overview)
✅ CostService with estimation and enforcement
✅ JobQueue with Moodle adhoc task integration
✅ Background execution with retry logic
✅ Progress monitoring and status tracking
✅ Telemetry service for analytics

### Section 16 (Cost Estimator & Controls)
✅ Formula: (#NB × avg tokens × provider price) + overhead
✅ Provider-specific estimates
✅ Admin threshold configuration
✅ Historical calibration for accuracy

### Section 17 (Error Handling)
✅ Exponential backoff (1min, 5min, 15min)
✅ Maximum 3 retry attempts
✅ Graceful failure with telemetry
✅ Partial completion support

## Configuration Settings

### Admin Settings Required:
- `llm_provider`: LLM provider (gpt-4, gpt-3.5-turbo, claude, etc.)
- `cost_warning_threshold`: Warning threshold in USD
- `cost_hard_limit`: Hard limit in USD
- `snapshot_freshness_days`: Days before snapshot expires
- `llm_mock_mode`: Enable mock mode for testing

## Key Improvements

1. **Cost Accuracy**: Historical calibration improves estimation accuracy
2. **Reuse Optimization**: Automatic detection of reusable snapshots
3. **Failure Recovery**: Robust retry mechanism with exponential backoff
4. **Real-time Monitoring**: Progress tracking and queue status
5. **Comprehensive Testing**: Full test coverage with PHPUnit and CLI tools
6. **Dashboard Ready**: Complete analytics for visualization

## Testing Recommendations

1. **Unit Tests**: Run PHPUnit test suite
   ```bash
   vendor/bin/phpunit local/customerintel/tests/cost_service_test.php
   vendor/bin/phpunit local/customerintel/tests/job_queue_test.php
   ```

2. **Integration Tests**: Use CLI tool with mock mode
   ```bash
   php cli/test_job_queue.php --mode=queue --company=1
   php cli/test_job_queue.php --mode=stats
   ```

3. **Failure Scenarios**: Test retry behavior
   ```bash
   php cli/test_job_queue.php --mode=queue --company=1 --simulate-failure
   ```

## Next Steps

1. **Dashboard UI**: Implement visualization for telemetry data
2. **Admin Interface**: Create settings page for thresholds
3. **Monitoring**: Set up alerts for cost overruns
4. **Optimization**: Fine-tune calibration factors
5. **Documentation**: User guide for cost management

## Files Modified/Created

### Modified:
- `/local_customerintel/classes/services/cost_service.php` - Added calculate_token_cost, made get_configured_provider public
- `/local_customerintel/classes/services/job_queue.php` - Fixed provider access

### Created:
- `/local_customerintel/classes/task/execute_run_task.php` - Adhoc task for execution
- `/local_customerintel/classes/services/telemetry_service.php` - Telemetry analytics
- `/local_customerintel/cli/test_job_queue.php` - CLI test tool
- `/local_customerintel/tests/cost_service_test.php` - CostService tests
- `/local_customerintel/tests/job_queue_test.php` - JobQueue tests

## Performance Considerations

1. **Queue Processing**: Uses Moodle's cron for efficient background processing
2. **Cost Caching**: Reuse detection reduces unnecessary API calls
3. **Telemetry Storage**: Indexed for fast retrieval
4. **Cleanup**: Automatic archival prevents table bloat

## Security Considerations

1. **Cost Limits**: Prevents runaway costs from API errors
2. **User Validation**: Ensures proper authorization for runs
3. **Error Handling**: Sensitive data not exposed in logs
4. **Mock Mode**: Safe testing without API calls

This implementation provides a robust, scalable foundation for cost management and job execution in the Customer Intelligence Dashboard.