<?php
/**
 * Cache Manager Service - Manages NB result caching and reuse
 *
 * Implements Milestone 0: Interactive Intelligence Cache Manager
 * Allows users to reuse Neural Block data from recent runs instead of re-fetching,
 * reducing processing time from 8-10 minutes to ~10 seconds.
 *
 * SCHEMA: Uses actual database schema with local_ci_* tables
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

/**
 * Cache Manager class
 *
 * Handles cache checking, NB copying, and cache decision processing.
 * Milestone 0 implementation for intelligent cache management.
 */
class cache_manager {

    /**
     * Check if cached NB data is available for given company pair
     *
     * Queries for completed runs with same companies within freshness window.
     * Verifies run has exactly 15 NBs in local_ci_nb_result.
     *
     * @param int $companyid Source company ID
     * @param int|null $targetcompanyid Target company ID (null for single company analysis)
     * @param int $freshness_days Maximum age in days (default: 90)
     * @return array Cache availability info with keys:
     *               - available (bool): Whether valid cache exists
     *               - runid (int|null): Cached run ID if available
     *               - age_days (int|null): Age of cached run in days
     *               - timecreated (int|null): Timestamp of cached run
     *               - nb_count (int): Number of NBs found (should be 15)
     * @throws \dml_exception
     */
    public function check_nb_cache(int $companyid, ?int $targetcompanyid = null, int $freshness_days = 90): array {
        global $DB;

        $result = [
            'available' => false,
            'runid' => null,
            'age_days' => null,
            'timecreated' => null,
            'nb_count' => 0
        ];

        try {
            // Calculate freshness cutoff timestamp
            $cutoff_time = time() - ($freshness_days * 24 * 60 * 60);

            // Find most recent completed run for this company pair within freshness window
            $sql = "SELECT id, timecreated, timecompleted
                    FROM {local_ci_run}
                    WHERE companyid = :companyid
                      AND targetcompanyid " . ($targetcompanyid ? "= :targetcompanyid" : "IS NULL") . "
                      AND status = :status
                      AND timecreated >= :cutoff_time
                    ORDER BY timecreated DESC";

            $params = [
                'companyid' => $companyid,
                'status' => 'completed',
                'cutoff_time' => $cutoff_time
            ];

            if ($targetcompanyid) {
                $params['targetcompanyid'] = $targetcompanyid;
            }

            // Use Moodle-standard limit parameters (offset, limit)
            $cached_runs = $DB->get_records_sql($sql, $params, 0, 1);

            if (empty($cached_runs)) {
                // No completed runs found within freshness window
                $this->log_cache_check($companyid, $targetcompanyid, 'no_cache_found');
                return $result;
            }

            // Get the first (and only) record from the array
            $cached_run = reset($cached_runs);

            // Verify the run has exactly 15 NBs
            $nb_count = $DB->count_records('local_ci_nb_result', ['runid' => $cached_run->id]);

            if ($nb_count !== 15) {
                // Incomplete NB set - not valid for caching
                $this->log_cache_check($companyid, $targetcompanyid, 'incomplete_nb_set', $cached_run->id, $nb_count);
                return $result;
            }

            // Calculate age in days
            $age_seconds = time() - $cached_run->timecreated;
            $age_days = (int)floor($age_seconds / (24 * 60 * 60));

            // Valid cache found
            $result = [
                'available' => true,
                'runid' => $cached_run->id,
                'age_days' => $age_days,
                'timecreated' => $cached_run->timecreated,
                'nb_count' => $nb_count
            ];

            $this->log_cache_check($companyid, $targetcompanyid, 'cache_available', $cached_run->id, $nb_count, $age_days);

            return $result;

        } catch (\Exception $e) {
            // Log error to diagnostics
            $this->log_diagnostics(0, 'cache_check_error', 'error',
                "Cache check failed for companyid {$companyid} / targetcompanyid " .
                ($targetcompanyid ?? 'null') . ": " . $e->getMessage());

            return $result;
        }
    }

    /**
     * Process user's cache decision and update run accordingly
     *
     * Handles 'reuse' (copy cached NBs) or 'full' (proceed to Stage 1) decisions.
     * Updates run record with cache strategy and reusedfromrunid.
     *
     * @param string $decision User's decision: 'reuse' or 'full'
     * @param int $new_runid ID of the new run being created
     * @param int|null $cached_runid ID of cached run (required if decision is 'reuse')
     * @return string Next step: 'cached' (skip Stage 1) or 'fetch' (proceed to Stage 1)
     * @throws \invalid_parameter_exception
     * @throws \dml_exception
     */
    public function process_cache_decision(string $decision, int $new_runid, ?int $cached_runid = null): string {
        global $DB;

        // Validate decision
        if (!in_array($decision, ['reuse', 'full'])) {
            throw new \invalid_parameter_exception("Invalid cache decision: {$decision}. Must be 'reuse' or 'full'.");
        }

        // Validate cached_runid for reuse decision
        if ($decision === 'reuse' && empty($cached_runid)) {
            throw new \invalid_parameter_exception("cached_runid is required when decision is 'reuse'");
        }

        try {
            // Start transaction
            $transaction = $DB->start_delegated_transaction();

            // Update run record with cache strategy
            $run_update = new \stdClass();
            $run_update->id = $new_runid;
            $run_update->cache_strategy = $decision;

            if ($decision === 'reuse') {
                $run_update->reusedfromrunid = $cached_runid;

                // Copy NBs from cached run
                $copy_success = $this->copy_nbs_from_cache($cached_runid, $new_runid);

                if (!$copy_success) {
                    throw new \moodle_exception('cache_copy_failed', 'local_customerintel', '', null,
                        "Failed to copy NBs from run {$cached_runid} to run {$new_runid}");
                }
            } else {
                // Full refresh - no cached run
                $run_update->reusedfromrunid = null;
            }

            $DB->update_record('local_ci_run', $run_update);

            // Log the decision
            $this->log_cache_decision($new_runid, $decision, $cached_runid);

            // Commit transaction
            $transaction->allow_commit();

            // Return next step
            return ($decision === 'reuse') ? 'cached' : 'fetch';

        } catch (\Exception $e) {
            if (isset($transaction)) {
                $transaction->rollback($e);
            }

            // Log error
            $this->log_diagnostics($new_runid, 'cache_decision_error', 'error',
                "Cache decision processing failed: " . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Copy all NBs from cached run to new run
     *
     * Private method that clones all 15 NBs from the cached run,
     * updates the runid, and inserts them into the new run.
     *
     * @param int $cached_runid Source run ID
     * @param int $new_runid Destination run ID
     * @return bool True if all 15 NBs copied successfully, false otherwise
     * @throws \dml_exception
     */
    private function copy_nbs_from_cache(int $cached_runid, int $new_runid): bool {
        global $DB;

        try {
            // Get all NBs from cached run
            $cached_nbs = $DB->get_records('local_ci_nb_result',
                ['runid' => $cached_runid],
                'nbcode ASC');

            if (count($cached_nbs) !== 15) {
                $this->log_diagnostics($new_runid, 'cache_copy_validation_failed', 'error',
                    "Expected 15 NBs from cached run {$cached_runid}, found " . count($cached_nbs));
                return false;
            }

            $copied_count = 0;

            // Clone each NB and insert into new run
            foreach ($cached_nbs as $nb) {
                $new_nb = new \stdClass();
                $new_nb->runid = $new_runid;
                $new_nb->nbcode = $nb->nbcode;
                $new_nb->jsonpayload = $nb->jsonpayload;
                $new_nb->citations = $nb->citations;
                $new_nb->tokensused = $nb->tokensused;
                $new_nb->durationms = 0; // No API call made, so duration is 0
                $new_nb->status = 'completed'; // Copied NBs are already completed
                $new_nb->timecreated = time();

                $DB->insert_record('local_ci_nb_result', $new_nb);
                $copied_count++;
            }

            // Log successful NB copy
            $this->log_diagnostics($new_runid, 'cache_copy_success', 'info',
                "Successfully copied {$copied_count} NBs from run {$cached_runid}");

            // Log telemetry for copied NBs
            $this->log_telemetry($new_runid, 'nb_copied_count', null, $copied_count);
            $this->log_telemetry($new_runid, 'cached_from_runid', (string)$cached_runid, $cached_runid);

            // === COPY SYNTHESIS ARTIFACTS ===
            $synthesis_copied = 0;

            // 1. Copy local_ci_synthesis record (final rendered report)
            $synthesis_record = $DB->get_record('local_ci_synthesis', ['runid' => $cached_runid]);
            if ($synthesis_record) {
                $new_synthesis = new \stdClass();
                $new_synthesis->runid = $new_runid;
                $new_synthesis->htmlcontent = $synthesis_record->htmlcontent;
                $new_synthesis->jsoncontent = $synthesis_record->jsoncontent;
                $new_synthesis->voice_report = $synthesis_record->voice_report;
                $new_synthesis->selfcheck_report = $synthesis_record->selfcheck_report;
                $new_synthesis->createdat = time();
                $new_synthesis->updatedat = time();

                try {
                    $DB->insert_record('local_ci_synthesis', $new_synthesis);
                    $synthesis_copied++;
                    $this->log_diagnostics($new_runid, 'synthesis_record_copied', 'info',
                        "Copied synthesis record from run {$cached_runid}");
                } catch (\dml_exception $e) {
                    $this->log_diagnostics($new_runid, 'synthesis_copy_error', 'warning',
                        "Failed to copy synthesis record: " . $e->getMessage());
                }
            } else {
                $this->log_diagnostics($new_runid, 'synthesis_not_found', 'warning',
                    "No synthesis record found for cached run {$cached_runid}");
            }

            // 2. Copy local_ci_artifact records (pipeline artifacts)
            $artifacts = $DB->get_records('local_ci_artifact', ['runid' => $cached_runid]);
            $artifact_count = 0;

            if (!empty($artifacts)) {
                foreach ($artifacts as $artifact) {
                    $new_artifact = new \stdClass();
                    $new_artifact->runid = $new_runid;
                    $new_artifact->phase = $artifact->phase;
                    $new_artifact->artifacttype = $artifact->artifacttype;
                    $new_artifact->jsondata = $artifact->jsondata;
                    $new_artifact->timecreated = time();
                    $new_artifact->timemodified = time();

                    try {
                        $DB->insert_record('local_ci_artifact', $new_artifact);
                        $artifact_count++;
                    } catch (\dml_exception $e) {
                        $this->log_diagnostics($new_runid, 'artifact_copy_error', 'warning',
                            "Failed to copy artifact {$artifact->phase}/{$artifact->artifacttype}: " . $e->getMessage());
                    }
                }

                $synthesis_copied += $artifact_count;
                $this->log_diagnostics($new_runid, 'artifacts_copied', 'info',
                    "Copied {$artifact_count} pipeline artifacts from run {$cached_runid}");
            }

            // Log synthesis copy results
            if ($synthesis_copied > 0) {
                $this->log_telemetry($new_runid, 'synthesis_artifacts_copied', null, $synthesis_copied);
                $this->log_diagnostics($new_runid, 'cache_copy_complete', 'info',
                    "Cache copy complete: {$copied_count} NBs + {$synthesis_copied} synthesis artifacts from run {$cached_runid}");
            } else {
                $this->log_diagnostics($new_runid, 'synthesis_copy_warning', 'warning',
                    "No synthesis artifacts found for run {$cached_runid} - synthesis will regenerate on first view");
            }

            return ($copied_count === 15);

        } catch (\Exception $e) {
            $this->log_diagnostics($new_runid, 'cache_copy_exception', 'error',
                "Exception during cache copy: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log cache decision to telemetry
     *
     * Private method that records the user's cache decision and metadata.
     * If reuse, also logs cache age and source run ID.
     *
     * @param int $runid Run ID
     * @param string $decision Cache decision ('reuse' or 'full')
     * @param int|null $cached_runid Source run ID (for reuse)
     * @return void
     * @throws \dml_exception
     */
    private function log_cache_decision(int $runid, string $decision, ?int $cached_runid): void {
        global $DB;

        try {
            // Log the decision itself
            $this->log_telemetry($runid, 'cache_decision', $decision);

            // If reuse, log additional metadata
            if ($decision === 'reuse' && !empty($cached_runid)) {
                // Get cached run to calculate age
                $cached_run = $DB->get_record('local_ci_run',
                    ['id' => $cached_runid],
                    'timecreated',
                    IGNORE_MISSING);

                if ($cached_run) {
                    $age_seconds = time() - $cached_run->timecreated;
                    $age_days = (int)floor($age_seconds / (24 * 60 * 60));

                    $this->log_telemetry($runid, 'cache_age_days', null, $age_days);
                    $this->log_telemetry($runid, 'cached_from_runid', (string)$cached_runid, $cached_runid);
                }
            }

            // Log to diagnostics as well
            $message = "Cache decision: {$decision}";
            if ($decision === 'reuse' && !empty($cached_runid)) {
                $message .= " from run {$cached_runid}";
            }
            $this->log_diagnostics($runid, 'cache_decision_logged', 'info', $message);

        } catch (\Exception $e) {
            // Silent failure - don't break the flow for logging issues
            debugging("Failed to log cache decision: " . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Log cache check result to diagnostics
     *
     * @param int $companyid Company ID
     * @param int|null $targetcompanyid Target company ID
     * @param string $result Check result ('no_cache_found', 'incomplete_nb_set', 'cache_available')
     * @param int|null $runid Cached run ID if found
     * @param int|null $nb_count Number of NBs found
     * @param int|null $age_days Age in days
     * @return void
     */
    private function log_cache_check(int $companyid, ?int $targetcompanyid, string $result,
                                     ?int $runid = null, ?int $nb_count = null, ?int $age_days = null): void {
        $message = "Cache check for companyid={$companyid}, targetcompanyid=" . ($targetcompanyid ?? 'null') . ": {$result}";

        if (!empty($runid)) {
            $message .= " (runid: {$runid}";
            if (!empty($nb_count)) {
                $message .= ", NBs: {$nb_count}";
            }
            if (!empty($age_days)) {
                $message .= ", age: {$age_days} days";
            }
            $message .= ")";
        }

        $severity = ($result === 'cache_available') ? 'info' : 'warning';
        $this->log_diagnostics(0, 'cache_check', $severity, $message);
    }

    /**
     * Helper method to log to telemetry table
     *
     * @param int $runid Run ID
     * @param string $metrickey Metric key
     * @param string|null $metricvalue Text value
     * @param float|null $metricvaluenum Numeric value
     * @return void
     * @throws \dml_exception
     */
    private function log_telemetry(int $runid, string $metrickey, ?string $metricvalue = null,
                                   ?float $metricvaluenum = null): void {
        global $DB;

        $record = new \stdClass();
        $record->runid = $runid;
        $record->metrickey = $metrickey;
        $record->metricvalue = $metricvalue;
        $record->metricvaluenum = $metricvaluenum;
        $record->timecreated = time();

        $DB->insert_record('local_ci_telemetry', $record);
    }

    /**
     * Helper method to log to diagnostics table
     *
     * Made PUBLIC for use by cache_decision.php page
     *
     * @param int $runid Run ID (0 for system-level diagnostics)
     * @param string $metric Metric/operation name
     * @param string $severity Severity level ('error', 'warning', 'info')
     * @param string $message Diagnostic message
     * @return void
     * @throws \dml_exception
     */
    public function log_diagnostics(int $runid, string $metric, string $severity, string $message): void {
        global $DB;

        $record = new \stdClass();
        $record->runid = $runid;
        $record->metric = $metric;
        $record->severity = $severity;
        $record->message = $message;
        $record->timecreated = time();

        $DB->insert_record('local_ci_diagnostics', $record);
    }

    // ========================================================================
    // Milestone 1 Task 4: Programmatic Refresh Control
    // ========================================================================

    /**
     * Get refresh strategy from refresh_config field
     *
     * Reads and parses the refresh_config JSON from the run record.
     * Returns default values if config is missing or invalid.
     *
     * @param int $runid Run ID
     * @return array Refresh strategy with keys:
     *               - force_nb_refresh (bool): Force all NB regeneration
     *               - force_synthesis_refresh (bool): Force synthesis regeneration
     *               - refresh_source (bool): Refresh source company NBs only
     *               - refresh_target (bool): Refresh target company NBs only
     * @throws \dml_exception
     */
    public function get_refresh_strategy(int $runid): array {
        global $DB;

        $default_strategy = [
            'force_nb_refresh' => false,
            'force_synthesis_refresh' => false,
            'refresh_source' => false,
            'refresh_target' => false
        ];

        try {
            $run = $DB->get_record('local_ci_run', ['id' => $runid], 'refresh_config', IGNORE_MISSING);

            if (!$run || empty($run->refresh_config)) {
                // No config found - use default behavior
                $this->log_diagnostics($runid, 'refresh_strategy', 'info',
                    'No refresh_config found - using default behavior (UI-driven cache)');
                return $default_strategy;
            }

            $config = json_decode($run->refresh_config, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($config)) {
                // Invalid JSON - log warning and use default
                $this->log_diagnostics($runid, 'refresh_strategy', 'warning',
                    'Invalid refresh_config JSON: ' . json_last_error_msg() . ' - using default behavior');
                return $default_strategy;
            }

            // Merge with defaults to handle missing fields
            $strategy = array_merge($default_strategy, $config);

            // Log the parsed strategy
            $this->log_diagnostics($runid, 'refresh_strategy', 'info',
                'Refresh strategy parsed: ' . json_encode($strategy));

            return $strategy;

        } catch (\Exception $e) {
            // Log error and return default
            $this->log_diagnostics($runid, 'refresh_strategy_error', 'error',
                'Error reading refresh_config: ' . $e->getMessage());
            return $default_strategy;
        }
    }

    /**
     * Check if NBs should be regenerated based on refresh_config
     *
     * @param int $runid Run ID
     * @param string $nb_type 'source' or 'target'
     * @return bool True if should regenerate
     * @throws \dml_exception
     */
    public function should_regenerate_nbs(int $runid, string $nb_type): bool {
        $strategy = $this->get_refresh_strategy($runid);

        // Force all NB refresh
        if ($strategy['force_nb_refresh']) {
            $this->log_diagnostics($runid, 'nb_refresh_decision', 'info',
                "Force regenerate ALL NBs (force_nb_refresh=true)");
            $this->log_telemetry($runid, 'refresh_decision', 'force_all_nbs');
            return true;
        }

        // Selective refresh based on company type
        if ($nb_type === 'source' && $strategy['refresh_source']) {
            $this->log_diagnostics($runid, 'nb_refresh_decision', 'info',
                "Force regenerate SOURCE NBs (refresh_source=true)");
            $this->log_telemetry($runid, 'refresh_decision', 'source_nbs_only');
            return true;
        }

        if ($nb_type === 'target' && $strategy['refresh_target']) {
            $this->log_diagnostics($runid, 'nb_refresh_decision', 'info',
                "Force regenerate TARGET NBs (refresh_target=true)");
            $this->log_telemetry($runid, 'refresh_decision', 'target_nbs_only');
            return true;
        }

        // Default: use normal cache logic
        $this->log_diagnostics($runid, 'nb_refresh_decision', 'info',
            "Use normal cache behavior for {$nb_type} NBs (no refresh flags set)");
        return false;
    }

    /**
     * Check if synthesis should be regenerated based on refresh_config
     *
     * @param int $runid Run ID
     * @return bool True if should regenerate
     * @throws \dml_exception
     */
    public function should_regenerate_synthesis(int $runid): bool {
        $strategy = $this->get_refresh_strategy($runid);

        // Force synthesis refresh
        if ($strategy['force_synthesis_refresh']) {
            $this->log_diagnostics($runid, 'synthesis_refresh_decision', 'info',
                "Force regenerate synthesis (force_synthesis_refresh=true)");
            $this->log_telemetry($runid, 'refresh_decision', 'synthesis_only');
            return true;
        }

        // If either source or target NBs are refreshed, regenerate synthesis
        if ($strategy['refresh_source'] || $strategy['refresh_target']) {
            $this->log_diagnostics($runid, 'synthesis_refresh_decision', 'info',
                "Regenerate synthesis because NBs were refreshed (refresh_source={$strategy['refresh_source']}, refresh_target={$strategy['refresh_target']})");
            $this->log_telemetry($runid, 'refresh_decision', 'synthesis_after_nb_refresh');
            return true;
        }

        // If all NBs are refreshed, regenerate synthesis
        if ($strategy['force_nb_refresh']) {
            $this->log_diagnostics($runid, 'synthesis_refresh_decision', 'info',
                "Regenerate synthesis because all NBs were refreshed (force_nb_refresh=true)");
            $this->log_telemetry($runid, 'refresh_decision', 'synthesis_after_full_nb_refresh');
            return true;
        }

        // Default: use normal cache logic
        $this->log_diagnostics($runid, 'synthesis_refresh_decision', 'info',
            "Use normal cache behavior for synthesis (no refresh flags set)");
        return false;
    }
}
