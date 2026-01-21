<?php
/**
 * Company Service - Manages company entities and metadata
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

/**
 * CompanyService class
 * 
 * Handles CRUD operations for companies, metadata enrichment, and freshness checks.
 * PRD Section 11 - Architecture Overview / Key Services
 */
class company_service {
    
    /**
     * Create a new company
     * 
     * @param string $name Company name
     * @param string $type Type: customer, target, or unknown
     * @param array $metadata Additional metadata
     * @return int Company ID
     * @throws \invalid_parameter_exception
     * @throws \dml_exception
     */
    public function create_company(string $name, string $type = 'unknown', array $metadata = []): int {
        global $DB;
        
        // Validate company type
        $valid_types = ['customer', 'target', 'unknown'];
        if (!in_array($type, $valid_types)) {
            throw new \invalid_parameter_exception("Invalid company type: {$type}");
        }
        
        // Check for duplicates
        if ($existing = $DB->get_record('local_ci_company', ['name' => $name])) {
            return $existing->id;
        }
        
        // Start transaction
        $transaction = $DB->start_delegated_transaction();
        
        try {
            $company = new \stdClass();
            $company->name = $name;
            $company->type = $type;
            $company->ticker = $metadata['ticker'] ?? null;
            $company->website = $metadata['website'] ?? null;
            $company->sector = $metadata['sector'] ?? null;
            $company->metadata = !empty($metadata) ? json_encode($metadata) : null;
            $company->timecreated = time();
            $company->timemodified = time();
            
            $companyid = $DB->insert_record('local_ci_company', $company);
            
            $transaction->allow_commit();
            
            return $companyid;
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }
    
    /**
     * Search companies
     * 
     * @param string $query Search query
     * @param string $type Filter by type (optional)
     * @param int $limit Maximum results
     * @return array List of companies
     * @throws \dml_exception
     */
    public function search_companies(string $query, string $type = null, int $limit = 20): array {
        global $DB;
        
        $params = [];
        $where = [];
        
        // Search by name or ticker
        if (!empty($query)) {
            $where[] = $DB->sql_like('name', ':namequery', false, false);
            $where[] = $DB->sql_like('ticker', ':tickerquery', false, false);
            $params['namequery'] = '%' . $DB->sql_like_escape($query) . '%';
            $params['tickerquery'] = '%' . $DB->sql_like_escape($query) . '%';
        }
        
        // Apply type filter
        if (!empty($type)) {
            $typefilter = 'type = :type';
            $params['type'] = $type;
        } else {
            $typefilter = '1=1';
        }
        
        $sql = "SELECT * FROM {local_ci_company}
                WHERE {$typefilter}";
        
        if (!empty($where)) {
            $sql .= " AND (" . implode(' OR ', $where) . ")";
        }
        
        $sql .= " ORDER BY name ASC";
        
        $companies = $DB->get_records_sql($sql, $params, 0, $limit);
        
        // Decode metadata JSON
        foreach ($companies as &$company) {
            if (!empty($company->metadata)) {
                $company->metadata = json_decode($company->metadata, true);
            }
        }
        
        return $companies;
    }
    
    /**
     * Get company by ID
     * 
     * @param int $companyid Company ID
     * @return \stdClass Company record
     * @throws \dml_exception
     */
    public function get_company(int $companyid): \stdClass {
        global $DB;
        
        $company = $DB->get_record('local_ci_company', ['id' => $companyid], '*', MUST_EXIST);
        
        // Decode JSON metadata
        if (!empty($company->metadata)) {
            $company->metadata = json_decode($company->metadata, true);
        } else {
            $company->metadata = [];
        }
        
        return $company;
    }
    
    /**
     * Update company metadata
     * 
     * @param int $companyid Company ID
     * @param array $metadata New metadata to merge
     * @param bool $replace Replace instead of merge
     * @return bool Success
     * @throws \dml_exception
     */
    public function update_metadata(int $companyid, array $metadata, bool $replace = false): bool {
        global $DB;
        
        $transaction = $DB->start_delegated_transaction();
        
        try {
            $company = $this->get_company($companyid);
            
            // Merge or replace metadata
            if ($replace) {
                $finalmetadata = $metadata;
            } else {
                $existing = is_array($company->metadata) ? $company->metadata : [];
                $finalmetadata = array_merge($existing, $metadata);
            }
            
            $update = new \stdClass();
            $update->id = $companyid;
            $update->metadata = json_encode($finalmetadata);
            $update->timemodified = time();
            
            // Update specific fields if provided
            if (isset($metadata['ticker'])) {
                $update->ticker = $metadata['ticker'];
            }
            if (isset($metadata['website'])) {
                $update->website = $metadata['website'];
            }
            if (isset($metadata['sector'])) {
                $update->sector = $metadata['sector'];
            }
            
            $result = $DB->update_record('local_ci_company', $update);
            
            $transaction->allow_commit();
            
            return $result;
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }
    
    /**
     * Check if company data is fresh
     * 
     * @param int $companyid Company ID
     * @return bool True if data is within freshness window
     * @throws \dml_exception
     */
    public function is_fresh(int $companyid): bool {
        global $DB;
        
        $snapshot = $this->get_latest_snapshot($companyid);
        
        if (!$snapshot) {
            return false;
        }
        
        // Get freshness window from settings (default 30 days)
        $freshness_days = get_config('local_customerintel', 'freshness_window') ?: 30;
        $freshness_seconds = $freshness_days * 86400;
        
        return (time() - $snapshot->timecreated) <= $freshness_seconds;
    }
    
    /**
     * Get latest snapshot for company
     * 
     * @param int $companyid Company ID
     * @return \stdClass|null Snapshot record or null
     * @throws \dml_exception
     */
    public function get_latest_snapshot(int $companyid): ?\stdClass {
        global $DB;
        
        $sql = "SELECT s.*, r.status as run_status, r.mode as run_mode
                FROM {local_ci_snapshot} s
                JOIN {local_ci_run} r ON s.runid = r.id
                WHERE s.companyid = :companyid
                AND r.status = 'succeeded'
                ORDER BY s.timecreated DESC";
        
        $snapshots = $DB->get_records_sql($sql, ['companyid' => $companyid], 0, 1);
        
        if (empty($snapshots)) {
            return null;
        }
        
        $snapshot = reset($snapshots);
        
        // Decode JSON
        if (!empty($snapshot->snapshotjson)) {
            $snapshot->data = json_decode($snapshot->snapshotjson, true);
        }
        
        return $snapshot;
    }
    
    /**
     * Delete company and related data
     * 
     * @param int $companyid Company ID
     * @return bool Success
     * @throws \dml_exception
     */
    public function delete_company(int $companyid): bool {
        global $DB;
        
        $transaction = $DB->start_delegated_transaction();
        
        try {
            // Delete related sources
            $DB->delete_records('local_ci_source', ['companyid' => $companyid]);
            
            // Delete snapshots
            $DB->delete_records('local_ci_snapshot', ['companyid' => $companyid]);
            
            // Delete runs and NB results
            $runs = $DB->get_records('local_ci_run', ['companyid' => $companyid], '', 'id');
            foreach ($runs as $run) {
                $DB->delete_records('local_ci_nb_result', ['runid' => $run->id]);
                $DB->delete_records('local_ci_telemetry', ['runid' => $run->id]);
            }
            $DB->delete_records('local_ci_run', ['companyid' => $companyid]);
            
            // Delete comparisons
            $DB->delete_records('local_ci_comparison', ['customercompanyid' => $companyid]);
            $DB->delete_records('local_ci_comparison', ['targetcompanyid' => $companyid]);
            
            // Finally delete the company
            $DB->delete_records('local_ci_company', ['id' => $companyid]);
            
            $transaction->allow_commit();
            
            return true;
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }
    
    /**
     * Get companies by type
     * 
     * @param string $type Company type
     * @return array List of companies
     * @throws \dml_exception
     */
    public function get_companies_by_type(string $type): array {
        global $DB;
        
        return $DB->get_records('local_ci_company', ['type' => $type], 'name ASC');
    }
}