<?php
/**
 * Job Queue Service - Manages background job execution
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/cost_service.php');
require_once(__DIR__ . '/nb_orchestrator.php');

/**
 * JobQueue class
 * 
 * Handles queued execution with retry/backoff for long-running operations.
 * PRD Section 11 - Architecture Overview / Key Services
 */
class job_queue {
    
    /** @var int Maximum retry attempts */
    const MAX_RETRIES = 3;
    
    /** @var array Backoff delays in seconds */
    const BACKOFF_DELAYS = [60, 300, 900]; // 1 min, 5 min, 15 min
    
    /**
     * Queue intelligence run
     * 
     * @param int $customerid Customer company ID
     * @param int $targetid Target company ID (optional)
     * @param int $userid User initiating run
     * @param array $options Run options
     * @return int Run ID
     * 
     * Implements per PRD Section 8.3 (NB Orchestration) and Section 17 (Error Handling)
     */
    public function queue_run(int $customerid, int $targetid = null, int $userid, array $options = []): int {
        global $DB;
        
        // Estimate cost before queuing
        $costservice = new cost_service();
        $estimate = $costservice->estimate_cost($customerid, $targetid, $options['force_refresh'] ?? false);
        
        // Check if run can proceed within budget
        if (!$estimate['can_proceed']) {
            throw new \moodle_exception('cost_exceeds_limit', 'local_customerintel', '', 
                ['estimated' => $estimate['total_cost'], 'limit' => $estimate['thresholds']['limit']]);
        }
        
        // Create run record
        $run = new \stdClass();
        $run->companyid = $customerid;
        $run->targetcompanyid = $targetid;
        $run->initiatedbyuserid = $userid;
        $run->userid = $userid;
        $run->mode = $options['mode'] ?? 'full';
        $run->status = 'queued';
        $run->esttokens = $estimate['total_tokens'];
        $run->estcost = $estimate['total_cost'];
        $run->reusedfromrunid = $estimate['reused_snapshot_id'] ?? null;
        $run->timecreated = time();
        $run->timemodified = time();
        
        $runid = $DB->insert_record('local_ci_run', $run);
        
        // Store cost estimate telemetry
        $costservice->record_telemetry($runid, 'estimated_cost', $estimate['total_cost'], [
            'tokens' => $estimate['total_tokens'],
            'provider' => $estimate['provider'],
            'has_warnings' => !empty($estimate['warnings']),
            'reuse_savings' => $estimate['reuse_savings'] ?? 0
        ]);
        
        // Schedule task for execution
        $this->schedule_run_task($runid);
        
        // Log queue event
        $this->log_event('run_queued', $runid, [
            'customer_id' => $customerid,
            'target_id' => $targetid,
            'mode' => $run->mode,
            'estimated_cost' => $estimate['total_cost']
        ]);
        
        return $runid;
    }
    
    /**
     * Execute queued run
     * 
     * @param int $runid Run ID
     * @return bool Success
     * 
     * Implements execution with retry logic
     */
    public function execute_run(int $runid): bool {
        global $DB;
        
        try {
            // Get run details
            $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);
            
            // Check if already running or completed
            if (in_array($run->status, ['running', 'completed', 'cancelled'])) {
                return $run->status === 'completed';
            }
            
            // Update status to running
            $this->update_run_status($runid, 'running');
            
            // Initialize services
            $orchestrator = new nb_orchestrator();
            $costservice = new cost_service();
            
            // Track execution start
            $starttime = microtime(true);
            $starttokens = $this->get_current_token_count();
            
            // Execute NB protocol
            $success = $orchestrator->execute_protocol($runid);
            
            // Track execution end
            $endtime = microtime(true);
            $endtokens = $this->get_current_token_count();
            
            // Calculate actual usage
            $duration = ($endtime - $starttime) * 1000; // ms
            $tokensused = $endtokens - $starttokens;
            
            // Calculate actual cost
            $actualcost = $this->calculate_actual_cost($tokensused);
            
            // Record actuals
            $costservice->record_actuals($runid, $tokensused, $actualcost, 
                $this->get_nb_breakdown($runid));
            
            // Update final status
            if ($success) {
                $this->update_run_status($runid, 'completed');
                
                // Log success
                $this->log_event('run_completed', $runid, [
                    'duration_ms' => $duration,
                    'tokens_used' => $tokensused,
                    'actual_cost' => $actualcost,
                    'variance_pct' => $run->estcost > 0 ? 
                        (($actualcost - $run->estcost) / $run->estcost) * 100 : 0
                ]);
            } else {
                $this->update_run_status($runid, 'failed');
                
                // Log failure
                $this->log_event('run_failed', $runid, [
                    'duration_ms' => $duration,
                    'partial_completion' => true
                ]);
            }
            
            return $success;
            
        } catch (\Exception $e) {
            // Handle failure with retry
            return $this->handle_failure($runid, $e);
        }
    }
    
    /**
     * Handle run failure with retry logic
     * 
     * @param int $runid Run ID
     * @param \Exception $error Error that occurred
     * @return bool Success after retry
     * 
     * Implements per PRD Section 17 (Error Handling)
     */
    protected function handle_failure(int $runid, \Exception $error): bool {
        global $DB;
        
        // Get current retry count
        $retries = $this->get_retry_count($runid);
        
        // Log the error
        $this->log_event('run_error', $runid, [
            'error_message' => $error->getMessage(),
            'error_code' => $error->getCode(),
            'retry_count' => $retries,
            'stack_trace' => $error->getTraceAsString()
        ]);
        
        // Check if should retry
        if ($retries < self::MAX_RETRIES) {
            // Calculate backoff delay
            $delay = self::BACKOFF_DELAYS[$retries] ?? 900;
            
            // Update status to retrying
            $this->update_run_status($runid, 'retrying', $error->getMessage());
            
            // Increment retry count
            $this->increment_retry_count($runid);
            
            // Schedule retry with backoff
            $this->schedule_retry($runid, $delay);
            
            // Log retry scheduled
            $this->log_event('retry_scheduled', $runid, [
                'retry_number' => $retries + 1,
                'delay_seconds' => $delay,
                'next_attempt' => time() + $delay
            ]);
            
            return false;
        }
        
        // Max retries exceeded - mark as failed
        $this->update_run_status($runid, 'failed', $error->getMessage());
        
        // Log final failure
        $this->log_event('max_retries_exceeded', $runid, [
            'total_attempts' => $retries + 1,
            'final_error' => $error->getMessage()
        ]);
        
        return false;
    }
    
    /**
     * Update run status
     * 
     * @param int $runid Run ID
     * @param string $status New status
     * @param string $error Optional error message
     * @return void
     */
    protected function update_run_status(int $runid, string $status, string $error = null): void {
        global $DB;
        
        $update = new \stdClass();
        $update->id = $runid;
        $update->status = $status;
        $update->timemodified = time();
        
        // Set start time
        if ($status === 'running') {
            $run = $DB->get_record('local_ci_run', ['id' => $runid]);
            if (empty($run->timestarted)) {
                $update->timestarted = time();
            }
        }
        
        // Set completion time
        if (in_array($status, ['completed', 'failed', 'cancelled'])) {
            $update->timecompleted = time();
        }
        
        // Store error details
        if ($error) {
            $update->error = json_encode([
                'message' => $error,
                'timestamp' => time(),
                'status' => $status
            ]);
        }
        
        $DB->update_record('local_ci_run', $update);
    }
    
    /**
     * Get run progress
     * 
     * @param int $runid Run ID
     * @return array Progress data
     */
    public function get_run_progress(int $runid): array {
        global $DB;
        
        $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);
        
        // Count completed NBs
        $completednbs = $DB->count_records_select('local_ci_nb_result', 
            "runid = ? AND status = 'completed'", [$runid]);
        
        // Get current NB being processed
        $currentnb = $DB->get_record_select('local_ci_nb_result',
            "runid = ? AND status = 'running'", [$runid], 'nbcode');
        
        $totalnbs = 15;
        $percentage = ($completednbs / $totalnbs) * 100;
        
        // Calculate estimated time remaining
        $eta = null;
        if ($run->timestarted && $completednbs > 0) {
            $elapsed = time() - $run->timestarted;
            $avgtime = $elapsed / $completednbs;
            $remaining = ($totalnbs - $completednbs) * $avgtime;
            $eta = time() + $remaining;
        }
        
        return [
            'run_id' => $runid,
            'status' => $run->status,
            'completed_nbs' => $completednbs,
            'total_nbs' => $totalnbs,
            'current_nb' => $currentnb ? $currentnb->nbcode : null,
            'percentage' => round($percentage, 1),
            'started_at' => $run->timestarted,
            'eta' => $eta,
            'estimated_cost' => $run->estcost,
            'estimated_tokens' => $run->esttokens
        ];
    }
    
    /**
     * Schedule run task
     * 
     * @param int $runid Run ID
     * @return void
     */
    protected function schedule_run_task(int $runid): void {
        // Create Moodle adhoc task
        $task = new \local_customerintel\task\execute_run_task();
        $task->set_custom_data(['runid' => $runid]);
        $task->set_component('local_customerintel');
        
        // Queue for immediate execution
        \core\task\manager::queue_adhoc_task($task);
    }
    
    /**
     * Schedule retry with delay
     * 
     * @param int $runid Run ID
     * @param int $delay Delay in seconds
     * @return void
     */
    protected function schedule_retry(int $runid, int $delay): void {
        // Create adhoc task with delay
        $task = new \local_customerintel\task\execute_run_task();
        $task->set_custom_data([
            'runid' => $runid,
            'retry' => true,
            'attempt' => $this->get_retry_count($runid) + 1
        ]);
        $task->set_component('local_customerintel');
        $task->set_next_run_time(time() + $delay);
        
        // Queue with delay
        \core\task\manager::queue_adhoc_task($task);
    }
    
    /**
     * Get retry count for run
     * 
     * @param int $runid Run ID
     * @return int Number of retries
     */
    protected function get_retry_count(int $runid): int {
        global $DB;
        
        // Query telemetry for retry count
        $count = $DB->get_field_sql(
            "SELECT COALESCE(MAX(CAST(payload->>'$.attempt' AS UNSIGNED)), 0)
             FROM {local_ci_telemetry}
             WHERE runid = ? AND metrickey = 'retry_attempt'",
            [$runid]
        );
        
        return (int)$count;
    }
    
    /**
     * Increment retry count
     * 
     * @param int $runid Run ID
     * @return void
     */
    protected function increment_retry_count(int $runid): void {
        $costservice = new cost_service();
        $count = $this->get_retry_count($runid) + 1;
        
        $costservice->record_telemetry($runid, 'retry_attempt', $count, [
            'attempt' => $count,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Cancel queued run
     * 
     * @param int $runid Run ID
     * @return bool Success
     */
    public function cancel_run(int $runid): bool {
        global $DB;
        
        $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);
        
        // Check if run can be cancelled
        if (!in_array($run->status, ['queued', 'retrying'])) {
            return false;
        }
        
        // Update status to cancelled
        $this->update_run_status($runid, 'cancelled');
        
        // Remove from queue if not started
        // Note: Moodle will handle task cleanup automatically
        
        // Log cancellation
        $this->log_event('run_cancelled', $runid, [
            'cancelled_by' => $GLOBALS['USER']->id,
            'previous_status' => $run->status
        ]);
        
        return true;
    }
    
    /**
     * Clean up old runs
     * 
     * @param int $age Age in days
     * @return int Number of runs cleaned
     */
    public function cleanup_old_runs(int $age = 90): int {
        global $DB;
        
        $cutoff = time() - ($age * 24 * 60 * 60);
        
        // Find old completed/failed runs
        $oldruns = $DB->get_records_select('local_ci_run',
            "status IN ('completed', 'failed', 'cancelled') AND timecompleted < ?",
            [$cutoff], '', 'id');
        
        $cleaned = 0;
        foreach ($oldruns as $run) {
            // Archive telemetry data
            $this->archive_telemetry($run->id);
            
            // Delete NB results
            $DB->delete_records('local_ci_nb_result', ['runid' => $run->id]);
            
            // Delete telemetry
            $DB->delete_records('local_ci_telemetry', ['runid' => $run->id]);
            
            // Mark run as archived
            $DB->set_field('local_ci_run', 'status', 'archived', ['id' => $run->id]);
            
            $cleaned++;
        }
        
        return $cleaned;
    }
    
    /**
     * Archive telemetry data
     * 
     * @param int $runid Run ID
     * @return void
     */
    protected function archive_telemetry(int $runid): void {
        global $DB;
        
        // Get telemetry data
        $telemetry = $DB->get_records('local_ci_telemetry', ['runid' => $runid]);
        
        if (empty($telemetry)) {
            return;
        }
        
        // Create archive record
        $archive = new \stdClass();
        $archive->runid = $runid;
        $archive->metrickey = 'archived_telemetry';
        $archive->metricvaluenum = count($telemetry);
        $archive->payload = json_encode($telemetry);
        $archive->timecreated = time();
        
        $DB->insert_record('local_ci_telemetry', $archive);
    }
    
    /**
     * Get current token count (placeholder)
     * 
     * @return int Token count
     */
    protected function get_current_token_count(): int {
        // This would integrate with the LLM provider's API
        // to get actual token usage
        return 0;
    }
    
    /**
     * Calculate actual cost based on tokens
     * 
     * @param int $tokens Token count
     * @return float Cost
     */
    protected function calculate_actual_cost(int $tokens): float {
        $costservice = new cost_service();
        
        // Simplified calculation - in reality would need input/output breakdown
        return $costservice->calculate_token_cost($tokens, 'output');
    }
    
    /**
     * Get NB breakdown for run
     * 
     * @param int $runid Run ID
     * @return array NB breakdown
     */
    protected function get_nb_breakdown(int $runid): array {
        global $DB;
        
        $nbresults = $DB->get_records('local_ci_nb_result', ['runid' => $runid]);
        
        $breakdown = [];
        foreach ($nbresults as $result) {
            $breakdown[$result->nbcode] = [
                'tokens' => $result->tokensused,
                'duration_ms' => $result->durationms,
                'status' => $result->status
            ];
        }
        
        return $breakdown;
    }
    
    /**
     * Log event
     * 
     * @param string $event Event name
     * @param int $runid Run ID
     * @param array $data Event data
     * @return void
     */
    protected function log_event(string $event, int $runid, array $data = []): void {
        $costservice = new cost_service();
        
        $costservice->record_telemetry($runid, 'event_' . $event, 1, array_merge([
            'event' => $event,
            'timestamp' => time(),
            'user_id' => $GLOBALS['USER']->id ?? 0
        ], $data));
    }
    
    /**
     * Get queue statistics
     * 
     * @return array Queue stats
     */
    public function get_queue_stats(): array {
        global $DB;
        
        $stats = [];
        
        // Count by status
        $statuses = ['queued', 'running', 'retrying', 'completed', 'failed', 'cancelled'];
        foreach ($statuses as $status) {
            $stats[$status] = $DB->count_records('local_ci_run', ['status' => $status]);
        }
        
        // Get average wait time
        $avgwait = $DB->get_field_sql(
            "SELECT AVG(timestarted - timecreated)
             FROM {local_ci_run}
             WHERE timestarted IS NOT NULL AND status != 'queued'"
        );
        
        $stats['avg_wait_time'] = $avgwait ?: 0;
        
        // Get average execution time
        $avgexec = $DB->get_field_sql(
            "SELECT AVG(timecompleted - timestarted)
             FROM {local_ci_run}
             WHERE status = 'completed' AND timecompleted IS NOT NULL"
        );
        
        $stats['avg_execution_time'] = $avgexec ?: 0;
        
        return $stats;
    }
}