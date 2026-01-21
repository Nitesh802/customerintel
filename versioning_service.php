<?php
/**
 * Versioning Service - Manages snapshots and diffs
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

/**
 * VersioningService class
 * 
 * Handles creation of immutable snapshots, diff computation, and version history.
 * PRD Section 11 - Architecture Overview / Key Services
 */
class versioning_service {
    
    /**
     * Create snapshot for completed run
     * 
     * @param int $runid Run ID
     * @return int Snapshot ID
     * 
     * Implements PRD Section 8.5 (Versioning & Diffs)
     */
    public function create_snapshot(int $runid): int {
        global $DB;
        
        $starttime = microtime(true);
        
        // Get run details
        $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);
        
        // Build complete snapshot
        $snapshotdata = $this->build_snapshot_json($runid);
        $snapshotjson = json_encode($snapshotdata);
        $snapshotsize = strlen($snapshotjson) / 1024; // Size in KB
        
        // Create snapshot record
        $snapshot = new \stdClass();
        $snapshot->companyid = $run->companyid;
        $snapshot->runid = $runid;
        $snapshot->snapshotjson = $snapshotjson;
        $snapshot->timecreated = time();
        $snapshot->timemodified = time();
        
        // Store snapshot
        $snapshotid = $DB->insert_record('local_ci_snapshot', $snapshot);
        
        // Record telemetry for snapshot creation
        $duration = (microtime(true) - $starttime) * 1000;
        $this->record_snapshot_telemetry($runid, $snapshotid, $duration, $snapshotsize);
        
        // Find previous snapshot for diff computation
        $previoussnapshot = $DB->get_record_sql(
            "SELECT * FROM {local_ci_snapshot} 
             WHERE companyid = ? AND id < ? 
             ORDER BY id DESC LIMIT 1",
            [$run->companyid, $snapshotid]
        );
        
        // Compute and store diff if previous exists
        if ($previoussnapshot) {
            $diff = $this->compute_diff($previoussnapshot->id, $snapshotid);
            
            // Record diff telemetry
            $fieldchangecount = $this->count_field_changes($diff);
            $this->record_diff_telemetry($runid, $previoussnapshot->id, $snapshotid, $fieldchangecount);
        }
        
        return $snapshotid;
    }
    
    /**
     * Compute diff between snapshots
     * 
     * @param int $fromid From snapshot ID
     * @param int $toid To snapshot ID
     * @return array Diff data
     * 
     * Implements PRD Section 8.5 and follows format in PRD Section 24.2 (Diff JSON Example)
     */
    public function compute_diff(int $fromid, int $toid): array {
        global $DB;
        
        // Load both snapshots
        $fromsnapshot = $DB->get_record('local_ci_snapshot', ['id' => $fromid], '*', MUST_EXIST);
        $tosnapshot = $DB->get_record('local_ci_snapshot', ['id' => $toid], '*', MUST_EXIST);
        
        $fromdata = json_decode($fromsnapshot->snapshotjson, true);
        $todata = json_decode($tosnapshot->snapshotjson, true);
        
        // Initialize diff structure
        $diff = [
            'from_snapshot_id' => $fromid,
            'to_snapshot_id' => $toid,
            'timestamp' => time(),
            'nb_diffs' => []
        ];
        
        // Compare each NB
        $allnbs = array_unique(array_merge(
            array_keys($fromdata['nb_results'] ?? []),
            array_keys($todata['nb_results'] ?? [])
        ));
        
        foreach ($allnbs as $nbcode) {
            $oldnb = $fromdata['nb_results'][$nbcode] ?? null;
            $newnb = $todata['nb_results'][$nbcode] ?? null;
            
            $nbdiff = $this->compare_nb_results($oldnb, $newnb, $nbcode);
            
            if (!empty($nbdiff['changed']) || !empty($nbdiff['added']) || !empty($nbdiff['removed'])) {
                $diff['nb_diffs'][] = $nbdiff;
            }
        }
        
        // Store diff in database
        $this->store_diff($fromid, $toid, $diff);
        
        return $diff;
    }
    
    /**
     * Get version history for company (renamed from get_version_history to match requirements)
     * 
     * @param int $companyid Company ID
     * @return array List of snapshots
     * 
     * Implements PRD Section 8.5
     */
    public function get_history(int $companyid): array {
        global $DB;
        
        // Query snapshots with run metadata
        $sql = "SELECT s.*, r.mode, r.status, r.timecompleted, r.timestarted,
                       u.firstname, u.lastname
                FROM {local_ci_snapshot} s
                JOIN {local_ci_run} r ON s.runid = r.id
                LEFT JOIN {user} u ON r.userid = u.id
                WHERE s.companyid = ?
                ORDER BY s.timecreated DESC";
        
        $snapshots = $DB->get_records_sql($sql, [$companyid]);
        
        $history = [];
        foreach ($snapshots as $snapshot) {
            $history[] = [
                'snapshot_id' => $snapshot->id,
                'run_id' => $snapshot->runid,
                'mode' => $snapshot->mode,
                'status' => $snapshot->status,
                'created' => $snapshot->timecreated,
                'created_formatted' => userdate($snapshot->timecreated, '%Y-%m-%d %H:%M'),
                'run_by' => trim($snapshot->firstname . ' ' . $snapshot->lastname),
                'duration' => $snapshot->timecompleted ? ($snapshot->timecompleted - $snapshot->timestarted) : null
            ];
        }
        
        return $history;
    }
    
    /**
     * Alias for backward compatibility
     */
    public function get_version_history(int $companyid): array {
        return $this->get_history($companyid);
    }
    
    /**
     * Get diff between versions
     * 
     * @param int $snapshotid Snapshot ID
     * @param int $previousid Previous snapshot ID (optional)
     * @return array|null Diff data or null
     * 
     * Implements per PRD Section 8.5
     */
    public function get_diff(int $snapshotid, int $previousid = null): ?array {
        global $DB;
        
        // Get snapshot to find company
        $snapshot = $DB->get_record('local_ci_snapshot', ['id' => $snapshotid], '*', MUST_EXIST);
        
        // If no previousid, find previous snapshot for same company
        if (!$previousid) {
            $previoussnapshot = $DB->get_record_sql(
                "SELECT * FROM {local_ci_snapshot} 
                 WHERE companyid = ? AND id < ? 
                 ORDER BY id DESC LIMIT 1",
                [$snapshot->companyid, $snapshotid]
            );
            
            if (!$previoussnapshot) {
                return null; // No previous snapshot to compare
            }
            
            $previousid = $previoussnapshot->id;
        }
        
        // Try to load existing diff
        $diff = $DB->get_record('local_ci_diff', [
            'fromsnapshotid' => $previousid,
            'tosnapshotid' => $snapshotid
        ]);
        
        if ($diff) {
            return json_decode($diff->diffjson, true);
        }
        
        // Compute diff if not exists
        return $this->compute_diff($previousid, $snapshotid);
    }
    
    /**
     * Build snapshot JSON
     * 
     * @param int $runid Run ID
     * @return array Snapshot data
     * 
     * Includes all data per PRD Section 8.5
     */
    protected function build_snapshot_json(int $runid): array {
        global $DB;
        
        // Get run and company details
        $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);
        $company = $DB->get_record('local_ci_company', ['id' => $run->companyid], '*', MUST_EXIST);
        
        $snapshot = [
            'run_id' => $runid,
            'company_id' => $run->companyid,
            'timestamp' => time(),
            'run_mode' => $run->mode,
            'nb_results' => [],
            'citations' => [],
            'sources' => [],
            'metadata' => [
                'company_name' => $company->name,
                'ticker' => $company->ticker,
                'run_status' => $run->status,
                'tokens_used' => $run->actualtokens,
                'cost' => $run->actualcost
            ]
        ];
        
        // Get all NB results
        $nbresults = $DB->get_records('local_ci_nb_result', ['runid' => $runid]);
        
        foreach ($nbresults as $nbresult) {
            $nbdata = [
                'payload' => json_decode($nbresult->jsonpayload, true),
                'citations' => json_decode($nbresult->citations ?: '[]', true),
                'status' => $nbresult->status,
                'duration_ms' => $nbresult->durationms,
                'tokens_used' => $nbresult->tokensused
            ];
            
            $snapshot['nb_results'][$nbresult->nbcode] = $nbdata;
            
            // Aggregate citations
            if (!empty($nbdata['citations'])) {
                foreach ($nbdata['citations'] as $citation) {
                    $snapshot['citations'][$citation['source_id']] = $citation;
                }
            }
        }
        
        // Get all sources for this company
        $sources = $DB->get_records('local_ci_source', ['companyid' => $run->companyid]);
        foreach ($sources as $source) {
            $snapshot['sources'][$source->id] = [
                'id' => $source->id,
                'type' => $source->type,
                'title' => $source->title,
                'url' => $source->url,
                'uploadedfilename' => $source->uploadedfilename,
                'hash' => $source->hash
            ];
        }
        
        return $snapshot;
    }
    
    /**
     * Compare NB results
     * 
     * @param array|null $old Old NB result
     * @param array|null $new New NB result
     * @param string $nbcode NB code
     * @return array Changes following PRD Section 24.2 format
     */
    protected function compare_nb_results(?array $old, ?array $new, string $nbcode): array {
        // Initialize diff structure per PRD 24.2
        $changes = [
            'nb_code' => $nbcode,
            'changed' => [],
            'added' => [],
            'removed' => [],
            'citations' => [
                'added' => [],
                'removed' => []
            ]
        ];
        
        // Handle completely new or removed NBs
        if (is_null($old) && !is_null($new)) {
            $changes['added'] = $new['payload'] ?? [];
            $changes['citations']['added'] = array_column($new['citations'] ?? [], 'source_id');
            return $changes;
        }
        
        if (!is_null($old) && is_null($new)) {
            $changes['removed'] = $old['payload'] ?? [];
            $changes['citations']['removed'] = array_column($old['citations'] ?? [], 'source_id');
            return $changes;
        }
        
        if (is_null($old) || is_null($new)) {
            return $changes;
        }
        
        // Deep comparison of payloads
        $oldpayload = $old['payload'] ?? [];
        $newpayload = $new['payload'] ?? [];
        
        $this->deep_compare_arrays($oldpayload, $newpayload, $changes);
        
        // Compare citations
        $oldcitations = array_column($old['citations'] ?? [], 'source_id');
        $newcitations = array_column($new['citations'] ?? [], 'source_id');
        
        $changes['citations']['added'] = array_values(array_diff($newcitations, $oldcitations));
        $changes['citations']['removed'] = array_values(array_diff($oldcitations, $newcitations));
        
        // Clean up empty citation arrays
        if (empty($changes['citations']['added']) && empty($changes['citations']['removed'])) {
            unset($changes['citations']);
        }
        
        return $changes;
    }
    
    /**
     * Deep compare two arrays and populate changes
     * 
     * @param array $old Old array
     * @param array $new New array  
     * @param array &$changes Changes array to populate
     * @param string $path Current path in the structure
     */
    protected function deep_compare_arrays(array $old, array $new, array &$changes, string $path = ''): void {
        $allkeys = array_unique(array_merge(array_keys($old), array_keys($new)));
        
        foreach ($allkeys as $key) {
            $currentpath = $path ? $path . '.' . $key : $key;
            
            // Key exists in new but not old - added
            if (!array_key_exists($key, $old) && array_key_exists($key, $new)) {
                $this->set_nested_value($changes['added'], $currentpath, $new[$key]);
                continue;
            }
            
            // Key exists in old but not new - removed
            if (array_key_exists($key, $old) && !array_key_exists($key, $new)) {
                $this->set_nested_value($changes['removed'], $currentpath, $old[$key]);
                continue;
            }
            
            // Both exist - check if changed
            if (array_key_exists($key, $old) && array_key_exists($key, $new)) {
                $oldval = $old[$key];
                $newval = $new[$key];
                
                // Handle arrays recursively
                if (is_array($oldval) && is_array($newval)) {
                    // Check if associative array (object) or indexed array
                    if ($this->is_assoc($oldval) || $this->is_assoc($newval)) {
                        // Recursive comparison for objects
                        $this->deep_compare_arrays($oldval, $newval, $changes, $currentpath);
                    } else {
                        // For indexed arrays, compare as values
                        if ($oldval !== $newval) {
                            $this->set_nested_value($changes['changed'], $currentpath, 
                                ['from' => $oldval, 'to' => $newval]);
                        }
                    }
                } else if ($oldval !== $newval) {
                    // Value changed
                    $this->set_nested_value($changes['changed'], $currentpath, 
                        ['from' => $oldval, 'to' => $newval]);
                }
            }
        }
    }
    
    /**
     * Check if array is associative
     */
    protected function is_assoc(array $arr): bool {
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
    
    /**
     * Set nested value in array using dot notation path
     */
    protected function set_nested_value(array &$arr, string $path, $value): void {
        $keys = explode('.', $path);
        $current = &$arr;
        
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }
    }
    
    /**
     * Store diff in database
     * 
     * @param int $fromid From snapshot ID
     * @param int $toid To snapshot ID
     * @param array $diff Diff data
     * @return int Diff ID
     */
    protected function store_diff(int $fromid, int $toid, array $diff): int {
        global $DB;
        
        // Check if diff already exists
        $existing = $DB->get_record('local_ci_diff', [
            'fromsnapshotid' => $fromid,
            'tosnapshotid' => $toid
        ]);
        
        if ($existing) {
            // Update existing diff
            $existing->diffjson = json_encode($diff);
            $existing->timecreated = time();
            $DB->update_record('local_ci_diff', $existing);
            return $existing->id;
        }
        
        // Create new diff record
        $diffrecord = new \stdClass();
        $diffrecord->fromsnapshotid = $fromid;
        $diffrecord->tosnapshotid = $toid;
        $diffrecord->diffjson = json_encode($diff);
        $diffrecord->timecreated = time();
        
        return $DB->insert_record('local_ci_diff', $diffrecord);
    }
    
    /**
     * Get snapshot by ID
     * 
     * @param int $snapshotid Snapshot ID
     * @return \stdClass Snapshot record with decoded JSON
     */
    public function get_snapshot(int $snapshotid): \stdClass {
        global $DB;
        
        $snapshot = $DB->get_record('local_ci_snapshot', ['id' => $snapshotid], '*', MUST_EXIST);
        
        // Decode JSON data
        $snapshot->data = json_decode($snapshot->snapshotjson, true);
        
        return $snapshot;
    }
    
    /**
     * Get or create diff between snapshots
     * 
     * @param int $fromid From snapshot ID
     * @param int $toid To snapshot ID
     * @return \stdClass|null Diff record or null
     */
    public function get_or_create_diff(int $fromid, int $toid): ?\stdClass {
        global $DB;
        
        // Try to get existing diff
        $diff = $DB->get_record('local_ci_diff', [
            'fromsnapshotid' => $fromid,
            'tosnapshotid' => $toid
        ]);
        
        if ($diff) {
            return $diff;
        }
        
        // Create new diff
        $diffdata = $this->compute_diff($fromid, $toid);
        
        if (!empty($diffdata)) {
            return $DB->get_record('local_ci_diff', [
                'fromsnapshotid' => $fromid,
                'tosnapshotid' => $toid
            ]);
        }
        
        return null;
    }
    
    /**
     * Record snapshot telemetry
     * 
     * @param int $runid Run ID
     * @param int $snapshotid Snapshot ID
     * @param float $duration Duration in milliseconds
     * @param float $size Size in KB
     */
    protected function record_snapshot_telemetry(int $runid, int $snapshotid, float $duration, float $size): void {
        global $DB;
        
        // Record duration
        $telemetry = new \stdClass();
        $telemetry->runid = $runid;
        $telemetry->metrickey = 'snapshot_creation_duration_ms';
        $telemetry->metricvaluenum = $duration;
        $telemetry->payload = json_encode(['snapshot_id' => $snapshotid]);
        $telemetry->timecreated = time();
        $DB->insert_record('local_ci_telemetry', $telemetry);
        
        // Record size
        $telemetry = new \stdClass();
        $telemetry->runid = $runid;
        $telemetry->metrickey = 'snapshot_size_kb';
        $telemetry->metricvaluenum = $size;
        $telemetry->payload = json_encode(['snapshot_id' => $snapshotid]);
        $telemetry->timecreated = time();
        $DB->insert_record('local_ci_telemetry', $telemetry);
    }
    
    /**
     * Record diff telemetry
     * 
     * @param int $runid Run ID
     * @param int $fromid From snapshot ID
     * @param int $toid To snapshot ID
     * @param int $changecount Number of field changes
     */
    protected function record_diff_telemetry(int $runid, int $fromid, int $toid, int $changecount): void {
        global $DB;
        
        $telemetry = new \stdClass();
        $telemetry->runid = $runid;
        $telemetry->metrickey = 'diff_field_changes';
        $telemetry->metricvaluenum = $changecount;
        $telemetry->payload = json_encode([
            'from_snapshot_id' => $fromid,
            'to_snapshot_id' => $toid
        ]);
        $telemetry->timecreated = time();
        $DB->insert_record('local_ci_telemetry', $telemetry);
    }
    
    /**
     * Count total field changes in diff
     * 
     * @param array $diff Diff data
     * @return int Total number of changes
     */
    protected function count_field_changes(array $diff): int {
        $count = 0;
        
        foreach ($diff['nb_diffs'] ?? [] as $nbdiff) {
            $count += count($nbdiff['added'] ?? []);
            $count += count($nbdiff['changed'] ?? []);
            $count += count($nbdiff['removed'] ?? []);
            $count += count($nbdiff['citations']['added'] ?? []);
            $count += count($nbdiff['citations']['removed'] ?? []);
        }
        
        return $count;
    }
    
    /**
     * Format diff for display
     * 
     * @param array $diff Diff data
     * @return string Formatted diff
     */
    public function format_diff_display(array $diff): string {
        $output = "\n=== SNAPSHOT DIFF ===\n";
        $output .= "From Snapshot: {$diff['from_snapshot_id']}\n";
        $output .= "To Snapshot: {$diff['to_snapshot_id']}\n";
        $output .= "Timestamp: " . date('Y-m-d H:i:s', $diff['timestamp']) . "\n";
        $output .= "\n";
        
        foreach ($diff['nb_diffs'] ?? [] as $nbdiff) {
            $output .= "--- {$nbdiff['nb_code']} ---\n";
            
            if (!empty($nbdiff['added'])) {
                $output .= "ADDED:\n";
                $output .= $this->format_diff_section($nbdiff['added'], '+');
            }
            
            if (!empty($nbdiff['changed'])) {
                $output .= "CHANGED:\n";
                foreach ($nbdiff['changed'] as $field => $change) {
                    $output .= "  ~ $field:\n";
                    $output .= "    - FROM: " . json_encode($change['from']) . "\n";
                    $output .= "    - TO: " . json_encode($change['to']) . "\n";
                }
            }
            
            if (!empty($nbdiff['removed'])) {
                $output .= "REMOVED:\n";
                $output .= $this->format_diff_section($nbdiff['removed'], '-');
            }
            
            if (!empty($nbdiff['citations'])) {
                if (!empty($nbdiff['citations']['added'])) {
                    $output .= "CITATIONS ADDED: " . implode(', ', $nbdiff['citations']['added']) . "\n";
                }
                if (!empty($nbdiff['citations']['removed'])) {
                    $output .= "CITATIONS REMOVED: " . implode(', ', $nbdiff['citations']['removed']) . "\n";
                }
            }
            
            $output .= "\n";
        }
        
        return $output;
    }
    
    /**
     * Format diff section
     * 
     * @param array $data Data to format
     * @param string $prefix Prefix character
     * @return string Formatted section
     */
    protected function format_diff_section(array $data, string $prefix): string {
        $output = '';
        foreach ($data as $key => $value) {
            $output .= "  $prefix $key: " . json_encode($value) . "\n";
        }
        return $output;
    }
    
    /**
     * Check if snapshots can be reused
     * 
     * @param int $companyid Company ID
     * @param int $maxage Maximum age in seconds (default 30 days)
     * @return int|null Reusable snapshot ID or null
     * 
     * Implements per PRD Section 15 (Reuse & Freshness)
     */
    public function get_reusable_snapshot(int $companyid, int $maxage = 2592000): ?int {
        global $DB;
        
        // Find latest successful snapshot for company
        $sql = "SELECT s.* 
                FROM {local_ci_snapshot} s
                JOIN {local_ci_run} r ON s.runid = r.id
                WHERE s.companyid = ? 
                  AND r.status = 'completed'
                  AND s.timecreated > ?
                ORDER BY s.timecreated DESC 
                LIMIT 1";
        
        $cutoff = time() - $maxage;
        $snapshot = $DB->get_record_sql($sql, [$companyid, $cutoff]);
        
        return $snapshot ? $snapshot->id : null;
    }
}