<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

/**
 * NB Cache Service - Company-level narrative brief caching
 *
 * Enables reuse of company NBs across multiple runs, reducing API costs
 * and improving performance when analyzing the same company multiple times.
 *
 * @package    local_customerintel
 * @copyright  2025 Fused Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class nb_cache_service {

    /**
     * Get cached NB for a company
     *
     * @param int $companyid Company ID
     * @param string $nbcode NB code (e.g., 'NB1', 'NB2', etc.)
     * @param int $version Cache version (default: latest)
     * @return object|false Cache record or false if not found
     */
    public static function get_cached_nb($companyid, $nbcode, $version = null) {
        global $DB;

        try {
            $params = [
                'company_id' => $companyid,
                'nbcode' => $nbcode
            ];

            if ($version !== null) {
                $params['version'] = $version;
                $cache = $DB->get_record('local_ci_nb_cache', $params);
            } else {
                // Get latest version
                $sql = "SELECT * FROM {local_ci_nb_cache}
                        WHERE company_id = :company_id AND nbcode = :nbcode
                        ORDER BY version DESC, timecreated DESC";
                $records = $DB->get_records_sql($sql, $params, 0, 1);
                $cache = $records ? reset($records) : false;
            }

            if ($cache) {
                // Log cache hit
                self::log_cache_event($companyid, $nbcode, 'hit', $cache->id);
            }

            return $cache;

        } catch (\Exception $e) {
            self::log_error('get_cached_nb', $e->getMessage(), ['company_id' => $companyid, 'nbcode' => $nbcode]);
            return false;
        }
    }

    /**
     * Store NB in cache
     *
     * @param int $companyid Company ID
     * @param string $nbcode NB code
     * @param string $jsonpayload JSON payload of the NB
     * @param string|null $citations Citations data
     * @param int $version Cache version (auto-increments if null)
     * @return int|false Cache record ID or false on failure
     */
    public static function store_nb($companyid, $nbcode, $jsonpayload, $citations = null, $version = null) {
        global $DB;

        try {
            // Auto-increment version if not provided
            if ($version === null) {
                $sql = "SELECT MAX(version) as maxver FROM {local_ci_nb_cache}
                        WHERE company_id = :company_id AND nbcode = :nbcode";
                $result = $DB->get_record_sql($sql, ['company_id' => $companyid, 'nbcode' => $nbcode]);
                $version = $result && $result->maxver ? $result->maxver + 1 : 1;
            }

            $record = new \stdClass();
            $record->company_id = $companyid;
            $record->nbcode = $nbcode;
            $record->jsonpayload = $jsonpayload;
            $record->citations = $citations;
            $record->version = $version;
            $record->timecreated = time();

            $id = $DB->insert_record('local_ci_nb_cache', $record);

            // Log cache store
            self::log_cache_event($companyid, $nbcode, 'store', $id);

            return $id;

        } catch (\Exception $e) {
            self::log_error('store_nb', $e->getMessage(), [
                'company_id' => $companyid,
                'nbcode' => $nbcode,
                'payload_length' => strlen($jsonpayload)
            ]);
            return false;
        }
    }

    /**
     * Invalidate all cached NBs for a company
     *
     * @param int $companyid Company ID
     * @return bool Success
     */
    public static function invalidate_company_cache($companyid) {
        global $DB;

        try {
            $count = $DB->count_records('local_ci_nb_cache', ['company_id' => $companyid]);
            $DB->delete_records('local_ci_nb_cache', ['company_id' => $companyid]);

            self::log_cache_event($companyid, 'ALL', 'invalidate', null, ['count' => $count]);

            return true;
        } catch (\Exception $e) {
            self::log_error('invalidate_company_cache', $e->getMessage(), ['company_id' => $companyid]);
            return false;
        }
    }

    /**
     * Get cache statistics for a company
     *
     * @param int $companyid Company ID (null for all companies)
     * @return object Cache statistics
     */
    public static function get_cache_stats($companyid = null) {
        global $DB;

        try {
            $params = [];
            $where = '';

            if ($companyid !== null) {
                $where = 'WHERE company_id = :company_id';
                $params['company_id'] = $companyid;
            }

            $sql = "SELECT
                        COUNT(*) as total_entries,
                        COUNT(DISTINCT company_id) as unique_companies,
                        COUNT(DISTINCT nbcode) as unique_nbcodes,
                        MIN(timecreated) as oldest_cache,
                        MAX(timecreated) as newest_cache
                    FROM {local_ci_nb_cache}
                    $where";

            return $DB->get_record_sql($sql, $params);

        } catch (\Exception $e) {
            self::log_error('get_cache_stats', $e->getMessage(), ['company_id' => $companyid]);
            return false;
        }
    }

    /**
     * Check if company has cached NBs
     *
     * @param int $companyid Company ID
     * @return bool True if company has cached NBs
     */
    public static function has_cached_nbs($companyid) {
        global $DB;
        return $DB->record_exists('local_ci_nb_cache', ['company_id' => $companyid]);
    }

    /**
     * Get all NB codes cached for a company
     *
     * @param int $companyid Company ID
     * @return array Array of NB codes
     */
    public static function get_cached_nbcodes($companyid) {
        global $DB;

        try {
            $sql = "SELECT DISTINCT nbcode FROM {local_ci_nb_cache}
                    WHERE company_id = :company_id
                    ORDER BY nbcode";
            $records = $DB->get_records_sql($sql, ['company_id' => $companyid]);

            return array_column($records, 'nbcode');
        } catch (\Exception $e) {
            self::log_error('get_cached_nbcodes', $e->getMessage(), ['company_id' => $companyid]);
            return [];
        }
    }

    /**
     * Log cache event to diagnostics
     *
     * @param int $companyid Company ID
     * @param string $nbcode NB code
     * @param string $event Event type (hit, store, invalidate)
     * @param int|null $cacheid Cache record ID
     * @param array $metadata Additional metadata
     */
    private static function log_cache_event($companyid, $nbcode, $event, $cacheid = null, $metadata = []) {
        global $DB;

        try {
            $record = new \stdClass();
            $record->runid = 0; // Cache events are not tied to specific runs
            $record->metric = "nb_cache_{$event}";
            $record->severity = 'info';
            $record->message = "NB Cache {$event}: company={$companyid}, nbcode={$nbcode}" .
                              ($cacheid ? ", cache_id={$cacheid}" : '') .
                              (!empty($metadata) ? ', metadata=' . json_encode($metadata) : '');
            $record->timecreated = time();

            $DB->insert_record('local_ci_diagnostics', $record);
        } catch (\Exception $e) {
            // Silent fail - don't break cache operations due to logging issues
            error_log("NB Cache logging failed: " . $e->getMessage());
        }
    }

    /**
     * Log error to diagnostics
     *
     * @param string $method Method name
     * @param string $message Error message
     * @param array $context Context data
     */
    private static function log_error($method, $message, $context = []) {
        global $DB;

        try {
            $record = new \stdClass();
            $record->runid = 0;
            $record->metric = 'nb_cache_error';
            $record->severity = 'error';
            $record->message = "NB Cache Error in {$method}: {$message}" .
                              (!empty($context) ? ', context=' . json_encode($context) : '');
            $record->timecreated = time();

            $DB->insert_record('local_ci_diagnostics', $record);
        } catch (\Exception $e) {
            error_log("NB Cache error logging failed: " . $e->getMessage());
        }
    }
}
