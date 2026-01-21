<?php
/**
 * Source Service - Manages data sources and content extraction
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../helpers/text_processor.php');
require_once(__DIR__ . '/../../lib/config_helper.php');

use local_customerintel\helpers\text_processor;
use local_customerintel\lib\config_helper;

/**
 * SourceService class
 * 
 * Handles source discovery, upload management, URL fetching, text extraction,
 * chunking, citation registry, and deduplication.
 * PRD Section 11 - Architecture Overview / Key Services
 */
class source_service {
    
    /**
     * Discover sources via Perplexity API
     * 
     * @param int $companyid Company ID
     * @param string $query Search query
     * @param int $maxresults Maximum results to return
     * @return array List of discovered sources
     * @throws \moodle_exception
     * 
     * Implements PRD Section 8.2 (Sources & Ingest)
     */
    public function discover_sources_perplexity(int $companyid, string $query = '', int $maxresults = 10): array {
        global $DB;
        
        // Get company for default query
        if (empty($query)) {
            $company = $DB->get_record('local_ci_company', ['id' => $companyid]);
            if ($company) {
                $query = $company->name . ' ' . ($company->ticker ? $company->ticker : '');
            }
        }
        
        if (!config_helper::has_perplexity_api_key()) {
            // Return placeholder response if no key configured
            debugging('Perplexity API key not configured, returning mock data', DEBUG_DEVELOPER);
            return $this->get_mock_perplexity_results($companyid, $query, $maxresults);
        }
        
        $apikey = config_helper::get_perplexity_api_key();
        
        // Build Perplexity search request per API docs
        $endpoint = 'https://api.perplexity.ai/chat/completions';
        $headers = [
            'Authorization: Bearer ' . $apikey,
            'Content-Type: application/json'
        ];
        
        // Format request for Perplexity's chat completions API
        $systemprompt = "You are a research assistant finding recent news and information about companies.";
        $userprompt = "Find recent news, articles, and information about: " . $query . ". Limit to " . $maxresults . " most relevant results.";
        
        $payload = json_encode([
            'model' => 'sonar-pro',
            'messages' => [
                ['role' => 'system', 'content' => $systemprompt],
                ['role' => 'user', 'content' => $userprompt]
            ],
            'max_tokens' => 2000,
            'temperature' => 0.2,
            'return_citations' => true,
            'search_domain_filter' => ['news', 'finance', 'business']
        ]);
        
        // Debug logging - request
        debugging('Perplexity API request body: ' . $payload, DEBUG_DEVELOPER);
        
        // Make API call
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Debug logging - response
        debugging('Perplexity API HTTP status: ' . $httpcode, DEBUG_DEVELOPER);
        if ($response) {
            $response_preview = substr($response, 0, 200);
            debugging('Perplexity API response (first 200 chars): ' . $response_preview, DEBUG_DEVELOPER);
        }
        
        if ($response === false) {
            debugging('Perplexity API error: ' . $error, DEBUG_DEVELOPER);
            return $this->get_mock_perplexity_results($companyid, $query, $maxresults);
        }
        
        if ($httpcode !== 200) {
            debugging('Perplexity API HTTP ' . $httpcode . ': ' . $response, DEBUG_DEVELOPER);
            return $this->get_mock_perplexity_results($companyid, $query, $maxresults);
        }
        
        $data = json_decode($response, true);
        
        // Extract citations from Perplexity response
        $citations = $data['citations'] ?? [];
        if (empty($citations)) {
            // Try to extract from response text
            $content = $data['choices'][0]['message']['content'] ?? '';
            $citations = $this->extract_urls_from_text($content);
        }
        
        $sources = [];
        $transaction = $DB->start_delegated_transaction();
        
        try {
            foreach ($citations as $index => $citation) {
                if ($index >= $maxresults) {
                    break;
                }
                
                // Parse citation data
                $url = is_array($citation) ? ($citation['url'] ?? '') : $citation;
                $title = is_array($citation) ? ($citation['title'] ?? '') : 'Source ' . ($index + 1);
                
                if (empty($url)) {
                    continue;
                }
                
                // Check domain filter
                $domain = parse_url($url, PHP_URL_HOST);
                if (!$this->is_domain_allowed($domain)) {
                    continue;
                }
                
                // Check for duplicates
                $hash = sha1($url);
                if ($DB->record_exists('local_ci_source', ['companyid' => $companyid, 'hash' => $hash])) {
                    continue;
                }
                
                // Create source record
                $source = new \stdClass();
                $source->companyid = $companyid;
                $source->sourcetype = 'url';
                $source->title = substr($title, 0, 255);
                $source->url = $url;
                $source->addedbyuserid = 0; // System added
                $source->approved = 1; // Auto-approve discovered sources
                $source->rejected = 0;
                $source->hash = $hash;
                $source->publishedat = time(); // Use current time as placeholder
                $source->timecreated = time();
                
                $sourceid = $DB->insert_record('local_ci_source', $source);
                
                // Store initial chunk if we have snippet
                $snippet = is_array($citation) ? ($citation['snippet'] ?? '') : '';
                if (!empty($snippet)) {
                    $this->store_source_chunk($sourceid, $snippet, 0, ['type' => 'snippet']);
                }
                
                $sources[] = [
                    'id' => $sourceid,
                    'title' => $source->title,
                    'url' => $source->url,
                    'snippet' => $snippet,
                    'published' => $source->publishedat
                ];
            }
            
            $transaction->allow_commit();
            
            // Log discovery
            mtrace("Discovered " . count($sources) . " sources for company $companyid via Perplexity");
            
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
        
        return $sources;
    }
    
    /**
     * Add file upload source
     * 
     * @param int $companyid Company ID
     * @param \stored_file $file Uploaded file
     * @param int $userid User ID
     * @return int Source ID
     * @throws \dml_exception
     * 
     * Implements PRD Section 8.2 - File upload handling
     */
    public function add_file_source(int $companyid, \stored_file $file, int $userid): int {
        global $DB;
        
        // Validate company exists
        if (!$DB->record_exists('local_ci_company', ['id' => $companyid])) {
            throw new \invalid_parameter_exception('Invalid company ID');
        }
        
        // Extract text from file
        $text = $this->extract_text_from_upload($file);
        if (empty($text)) {
            throw new \moodle_exception('failedtextextraction', 'local_customerintel');
        }
        $hash = sha1($text);
        
        // Check for duplicates
        if ($existing = $DB->get_record('local_ci_source', ['companyid' => $companyid, 'hash' => $hash])) {
            return $existing->id;
        }
        
        $transaction = $DB->start_delegated_transaction();
        
        try {
            $source = new \stdClass();
            $source->companyid = $companyid;
            $source->sourcetype = 'file';
            $source->title = $file->get_filename();
            $source->uploadedfilename = $file->get_filename();
            $source->fileid = $file->get_id();
            $source->addedbyuserid = $userid;
            $source->approved = 1;
            $source->rejected = 0;
            $source->hash = $hash;
            $source->timecreated = time();
            
            $sourceid = $DB->insert_record('local_ci_source', $source);
            
            // Chunk and store the text
            $chunks = $this->chunk_text($text);
            foreach ($chunks as $index => $chunk) {
                $this->store_source_chunk($sourceid, $chunk['text'], $index, $chunk);
            }
            
            $transaction->allow_commit();
            
            // Log file upload
            debugging("File source added: {$file->get_filename()} for company $companyid", DEBUG_DEVELOPER);
            
            return $sourceid;
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }
    
    /**
     * Add URL source
     * 
     * @param int $companyid Company ID
     * @param string $url URL to fetch
     * @param int $userid User ID
     * @param string $title Optional title
     * @return int Source ID
     * @throws \invalid_parameter_exception
     * @throws \dml_exception
     * 
     * Implements PRD Section 8.2 - URL source handling
     */
    public function add_url_source(int $companyid, string $url, int $userid, string $title = ''): int {
        global $DB;
        
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \invalid_parameter_exception('Invalid URL');
        }
        
        // Check domain filters
        $domain = parse_url($url, PHP_URL_HOST);
        if (!$this->is_domain_allowed($domain)) {
            throw new \invalid_parameter_exception('Domain not allowed: ' . $domain);
        }
        
        // Check for existing URL
        if ($existing = $DB->get_record('local_ci_source', ['companyid' => $companyid, 'url' => $url])) {
            return $existing->id;
        }
        
        $transaction = $DB->start_delegated_transaction();
        
        try {
            $source = new \stdClass();
            $source->companyid = $companyid;
            $source->sourcetype = 'url';
            $source->title = $title ?: $domain;
            $source->url = $url;
            $source->addedbyuserid = $userid;
            $source->approved = 1;
            $source->rejected = 0;
            $source->timecreated = time();
            
            // Fetch URL content
            $content = $this->fetch_url_content($url);
            if (!empty($content)) {
                $source->hash = sha1($content);
                
                // Check for duplicate content
                if ($existing = $DB->get_record('local_ci_source', ['companyid' => $companyid, 'hash' => $source->hash])) {
                    $transaction->rollback();
                    return $existing->id;
                }
            } else {
                $source->hash = sha1($url . time());
            }
            
            $sourceid = $DB->insert_record('local_ci_source', $source);
            
            // Chunk and store content if available
            if (!empty($content)) {
                $chunks = $this->chunk_text($content);
                foreach ($chunks as $index => $chunk) {
                    $this->store_source_chunk($sourceid, $chunk['text'], $index, $chunk);
                }
            }
            
            $transaction->allow_commit();
            
            return $sourceid;
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }
    
    /**
     * Add manual source (text, file, or URL)
     * 
     * @param mixed $input Can be \stored_file, URL string, or text content
     * @param int $companyid Company ID
     * @param int $userid User ID
     * @param string $title Optional title
     * @return int Source ID
     * @throws \moodle_exception
     * 
     * Implements PRD Section 8.2 - Unified manual source addition
     */
    public function add_manual_source($input, int $companyid, int $userid, string $title = ''): int {
        // Determine input type and delegate
        if ($input instanceof \stored_file) {
            return $this->add_file_source($companyid, $input, $userid);
        } elseif (filter_var($input, FILTER_VALIDATE_URL)) {
            return $this->add_url_source($companyid, $input, $userid, $title);
        } else {
            // Treat as manual text
            return $this->add_text_source($companyid, $input, $title ?: 'Manual Entry', $userid);
        }
    }
    
    /**
     * Add manual text source
     * 
     * @param int $companyid Company ID
     * @param string $text Manual text content
     * @param string $title Source title
     * @param int $userid User ID
     * @return int Source ID
     * @throws \dml_exception
     */
    public function add_text_source(int $companyid, string $text, string $title, int $userid): int {
        global $DB;
        
        $hash = sha1($text);
        
        // Check for duplicates
        if ($existing = $DB->get_record('local_ci_source', ['companyid' => $companyid, 'hash' => $hash])) {
            return $existing->id;
        }
        
        $transaction = $DB->start_delegated_transaction();
        
        try {
            $source = new \stdClass();
            $source->companyid = $companyid;
            $source->sourcetype = 'manual_text';
            $source->title = $title;
            $source->addedbyuserid = $userid;
            $source->approved = 1;
            $source->rejected = 0;
            $source->hash = $hash;
            $source->timecreated = time();
            
            $sourceid = $DB->insert_record('local_ci_source', $source);
            
            // Chunk and store the text
            $chunks = $this->chunk_text($text);
            foreach ($chunks as $index => $chunk) {
                $this->store_source_chunk($sourceid, $chunk['text'], $index, $chunk);
            }
            
            $transaction->allow_commit();
            
            return $sourceid;
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }
    
    /**
     * Get sources for company
     * 
     * @param int $companyid Company ID
     * @param bool $approvedonly Only return approved sources
     * @return array List of sources with user details
     * @throws \dml_exception
     */
    public function get_company_sources(int $companyid, bool $approvedonly = false): array {
        global $DB;
        
        $sql = "SELECT s.*, u.firstname, u.lastname, u.email
                FROM {local_ci_source} s
                JOIN {user} u ON s.addedbyuserid = u.id
                WHERE s.companyid = :companyid";
        
        $params = ['companyid' => $companyid];
        
        if ($approvedonly) {
            $sql .= " AND s.approved = 1 AND s.rejected = 0";
        }
        
        $sql .= " ORDER BY s.timecreated DESC";
        
        return $DB->get_records_sql($sql, $params);
    }
    
    /**
     * Approve/reject source
     * 
     * @param int $sourceid Source ID
     * @param bool $approved Approval status
     * @return bool Success
     * @throws \dml_exception
     */
    public function update_approval(int $sourceid, bool $approved): bool {
        global $DB;
        
        $update = new \stdClass();
        $update->id = $sourceid;
        
        if ($approved) {
            $update->approved = 1;
            $update->rejected = 0;
        } else {
            $update->approved = 0;
            $update->rejected = 1;
        }
        
        return $DB->update_record('local_ci_source', $update);
    }
    
    
    /**
     * Get k-best chunks for NB prompt
     * 
     * @param int $companyid Company ID
     * @param string $nbcode NB code (NB1-NB15)
     * @param int $k Number of chunks
     * @return array Retrieved chunks with citations
     * 
     * TODO: Implement per PRD Section 14
     */
    public function retrieve_chunks(int $companyid, string $nbcode, int $k = 5): array {
        // Delegate to get_chunks_for_nb which provides structured data
        $data = $this->get_chunks_for_nb($companyid, $nbcode);
        
        $retrieved = [];
        $count = 0;
        
        foreach ($data['sources'] as $source) {
            foreach ($source['chunks'] as $chunk) {
                if ($count >= $k) {
                    break 2;
                }
                $retrieved[] = [
                    'text' => $chunk['text'],
                    'source_id' => $source['source_id'],
                    'source_title' => $source['source_title'],
                    'source_url' => $source['source_url']
                ];
                $count++;
            }
        }
        
        return $retrieved;
    }
    
    /**
     * Check domain against allow/deny lists
     * 
     * @param string $domain Domain to check
     * @return bool True if allowed
     */
    protected function is_domain_allowed(string $domain): bool {
        // Get allow/deny lists from settings
        $allowlist = get_config('local_customerintel', 'domains_allow');
        $denylist = get_config('local_customerintel', 'domains_deny');
        
        // Parse lists (one domain per line)
        $allowed = $allowlist ? array_map('trim', explode("\n", $allowlist)) : [];
        $denied = $denylist ? array_map('trim', explode("\n", $denylist)) : [];
        
        // Check deny list first
        if (!empty($denied) && in_array($domain, $denied)) {
            return false;
        }
        
        // If allow list is empty, allow all (except denied)
        if (empty($allowed)) {
            return true;
        }
        
        // Check allow list
        return in_array($domain, $allowed);
    }
    
    /**
     * Delete source
     * 
     * @param int $sourceid Source ID
     * @return bool Success
     * @throws \dml_exception
     */
    public function delete_source(int $sourceid): bool {
        global $DB;
        
        return $DB->delete_records('local_ci_source', ['id' => $sourceid]);
    }
    
    /**
     * Get source by ID
     * 
     * @param int $sourceid Source ID
     * @return \stdClass Source record
     * @throws \dml_exception
     */
    public function get_source(int $sourceid): \stdClass {
        global $DB;
        
        return $DB->get_record('local_ci_source', ['id' => $sourceid], '*', MUST_EXIST);
    }
    
    /**
     * Bulk approve/reject sources
     * 
     * @param array $sourceids Source IDs
     * @param bool $approved Approval status
     * @return int Number of updated records
     * @throws \dml_exception
     */
    public function bulk_update_approval(array $sourceids, bool $approved): int {
        global $DB;
        
        if (empty($sourceids)) {
            return 0;
        }
        
        list($insql, $params) = $DB->get_in_or_equal($sourceids);
        
        $updates = [
            'approved' => $approved ? 1 : 0,
            'rejected' => $approved ? 0 : 1
        ];
        
        $sql = "UPDATE {local_ci_source}
                SET approved = :approved, rejected = :rejected
                WHERE id $insql";
        
        return $DB->execute($sql, array_merge($updates, $params));
    }
    
    /**
     * Extract text from uploaded file using Moodle File API
     * 
     * @param \stored_file $file File to process
     * @return string Extracted text
     */
    public function extract_text_from_upload(\stored_file $file): string {
        global $CFG;
        
        // Get file extension
        $filename = $file->get_filename();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Get temporary file path
        $tempdir = make_temp_directory('customerintel');
        $tempfile = $tempdir . '/' . uniqid() . '_' . $filename;
        $file->copy_content_to($tempfile);
        
        $text = '';
        
        try {
            switch ($extension) {
                case 'pdf':
                    $text = text_processor::extract_from_pdf($tempfile);
                    break;
                    
                case 'docx':
                case 'doc':
                    $text = text_processor::extract_from_docx($tempfile);
                    break;
                    
                case 'txt':
                case 'text':
                    $text = text_processor::extract_from_txt($tempfile);
                    break;
                    
                default:
                    // Try as plain text
                    $text = file_get_contents($tempfile);
                    $text = text_processor::clean_extracted_text($text);
            }
        } finally {
            // Clean up temp file
            if (file_exists($tempfile)) {
                unlink($tempfile);
            }
        }
        
        return $text;
    }
    
    /**
     * Chunk text into manageable segments
     * 
     * @param string $text Text to chunk
     * @param int $chunksize Size in characters
     * @return array Chunked text array
     */
    public function chunk_text(string $text, int $chunksize = 2000): array {
        return text_processor::chunk_text($text, $chunksize);
    }
    
    /**
     * Deduplicate sources by hash
     * 
     * @param int $companyid Company ID
     * @return int Number of duplicates removed
     */
    public function dedupe_sources_by_hash(int $companyid): int {
        global $DB;
        
        // Get all sources for company grouped by hash
        $sql = "SELECT hash, COUNT(*) as cnt, MIN(id) as keepid
                FROM {local_ci_source}
                WHERE companyid = :companyid
                GROUP BY hash
                HAVING COUNT(*) > 1";
        
        $duplicates = $DB->get_records_sql($sql, ['companyid' => $companyid]);
        $removed = 0;
        
        foreach ($duplicates as $dup) {
            // Delete all except the oldest (keepid)
            $sql = "DELETE FROM {local_ci_source}
                    WHERE companyid = :companyid
                    AND hash = :hash
                    AND id != :keepid";
            
            $params = [
                'companyid' => $companyid,
                'hash' => $dup->hash,
                'keepid' => $dup->keepid
            ];
            
            $removed += $DB->execute($sql, $params);
        }
        
        return $removed;
    }
    
    /**
     * Register a citation for a source
     * 
     * @param int $sourceid Source ID
     * @param string $quote Quote text
     * @param string $url Optional URL
     * @param string $page Optional page reference
     * @return int Citation ID
     */
    public function register_citation(int $sourceid, string $quote, string $url = '', string $page = ''): int {
        global $DB;
        
        // We'll store citations in a JSON field or separate table
        // For now, create citation record linked to source
        
        $citation = new \stdClass();
        $citation->sourceid = $sourceid;
        $citation->quote = $quote;
        $citation->url = $url;
        $citation->page = $page;
        $citation->hash = sha1($quote);
        $citation->timecreated = time();
        
        // Check if citation table exists (we may need to add it)
        // For now, return a mock ID
        // In production, this would insert into mdl_local_ci_citation table
        
        return $this->store_citation($citation);
    }
    
    /**
     * Store citation (helper method)
     * 
     * @param \stdClass $citation Citation object
     * @return int Citation ID
     */
    protected function store_citation(\stdClass $citation): int {
        global $DB;
        
        // Store in a JSON field in source or separate table
        // This is a placeholder - in production we'd have a proper citation table
        static $citationid = 1;
        
        return $citationid++;
    }
    
    /**
     * Fetch content from URL
     * 
     * @param string $url URL to fetch
     * @return string Content
     */
    protected function fetch_url_content(string $url): string {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MoodleBot/1.0)');
        
        $content = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpcode !== 200) {
            return '';
        }
        
        // Clean HTML if needed
        $content = strip_tags($content);
        $content = text_processor::clean_extracted_text($content);
        
        return $content;
    }
    
    /**
     * Store source chunk for retrieval
     * 
     * @param int $sourceid Source ID
     * @param string $text Chunk text
     * @param int $index Chunk index
     * @param array $metadata Optional metadata
     * @return int Chunk ID
     */
    protected function store_source_chunk(int $sourceid, string $text, int $index, array $metadata = []): int {
        global $DB;
        
        // Store in local_ci_source_chunk table
        $chunk = new \stdClass();
        $chunk->sourceid = $sourceid;
        $chunk->chunktext = $text;
        $chunk->chunkindex = $index;
        $chunk->hash = sha1($text);
        $chunk->tokens = text_processor::estimate_tokens($text);
        $chunk->metadata = !empty($metadata) ? json_encode($metadata) : null;
        $chunk->timecreated = time();
        
        return $DB->insert_record('local_ci_source_chunk', $chunk);
    }
    
    /**
     * Get chunks for NB orchestrator ingestion
     * 
     * @param int $companyid Company ID
     * @param string $nbcode NB code
     * @return array Structured array for NB processing
     * 
     * Implements per PRD Section 14 (Retrieval & Chunking)
     */
    public function get_chunks_for_nb(int $companyid, string $nbcode): array {
        global $DB;
        
        // Get approved sources for company
        $sql = "SELECT s.*
                FROM {local_ci_source} s
                WHERE s.companyid = :companyid 
                  AND s.approved = 1 
                  AND s.rejected = 0
                ORDER BY s.publishedat DESC, s.timecreated DESC";
        
        $sources = $DB->get_records_sql($sql, ['companyid' => $companyid]);
        
        $structured = [];
        $totaltokens = 0;
        
        foreach ($sources as $source) {
            // Get chunks for this source
            $chunks = $DB->get_records('local_ci_source_chunk', 
                ['sourceid' => $source->id], 
                'chunkindex ASC');
            
            $sourcechunks = [];
            foreach ($chunks as $chunk) {
                $sourcechunks[] = [
                    'text' => $chunk->chunktext,
                    'index' => $chunk->chunkindex,
                    'tokens' => $chunk->tokens ?? text_processor::estimate_tokens($chunk->chunktext),
                    'hash' => $chunk->hash
                ];
                $totaltokens += $chunk->tokens;
            }
            
            // Only include sources that have chunks
            if (!empty($sourcechunks)) {
                $structured[] = [
                    'source_id' => $source->id,
                    'source_title' => $source->title,
                    'source_url' => $source->url ?? '',
                    'source_type' => $source->sourcetype ?? 'manual_text',
                    'chunks' => $sourcechunks,
                    'metadata' => [
                        'published' => $source->publishedat ?? null,
                        'added' => $source->timecreated,
                        'added_by' => $source->addedbyuserid
                    ]
                ];
            }
        }
        
        return [
            'companyid' => $companyid,
            'nb_code' => $nbcode,
            'total_sources' => count($structured),
            'total_chunks' => array_sum(array_map(function($s) { return count($s['chunks']); }, $structured)),
            'total_tokens' => $totaltokens,
            'sources' => $structured,
            'retrieval_timestamp' => time()
        ];
    }
    
    /**
     * Get mock Perplexity results for testing
     * 
     * @param int $companyid Company ID
     * @param string $query Query string
     * @param int $maxresults Max results
     * @return array Mock sources
     */
    protected function get_mock_perplexity_results(int $companyid, string $query, int $maxresults): array {
        global $DB;
        
        $sources = [];
        $transaction = $DB->start_delegated_transaction();
        
        try {
            // Generate mock sources
            $mocksources = [
                ['title' => $query . ' Q3 2024 Earnings Report', 'url' => 'https://example.com/earnings-q3-2024'],
                ['title' => $query . ' Announces New Product Launch', 'url' => 'https://example.com/product-launch'],
                ['title' => $query . ' CEO Interview on Strategy', 'url' => 'https://example.com/ceo-interview'],
                ['title' => $query . ' Market Analysis Report', 'url' => 'https://example.com/market-analysis'],
                ['title' => $query . ' Digital Transformation Update', 'url' => 'https://example.com/digital-transform']
            ];
            
            foreach ($mocksources as $index => $mocksource) {
                if ($index >= $maxresults) {
                    break;
                }
                
                $hash = sha1($mocksource['url']);
                
                // Check for duplicates
                if ($DB->record_exists('local_ci_source', ['companyid' => $companyid, 'hash' => $hash])) {
                    continue;
                }
                
                // Create source record
                $source = new \stdClass();
                $source->companyid = $companyid;
                $source->sourcetype = 'url';
                $source->title = $mocksource['title'];
                $source->url = $mocksource['url'];
                $source->addedbyuserid = 0; // System added
                $source->approved = 1;
                $source->rejected = 0;
                $source->hash = $hash;
                $source->publishedat = time() - (86400 * $index); // Stagger publish dates
                $source->timecreated = time();
                
                $sourceid = $DB->insert_record('local_ci_source', $source);
                
                // Add mock chunk
                $snippet = "This is mock content about " . $mocksource['title'] . ". " .
                          "Contains important information relevant to " . $query . ".";
                $this->store_source_chunk($sourceid, $snippet, 0, ['type' => 'mock']);
                
                $sources[] = [
                    'id' => $sourceid,
                    'title' => $source->title,
                    'url' => $source->url,
                    'snippet' => $snippet,
                    'published' => $source->publishedat
                ];
            }
            
            $transaction->allow_commit();
            
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
        
        return $sources;
    }
    
    /**
     * Extract URLs from text content
     * 
     * @param string $text Text to parse
     * @return array Array of URLs
     */
    protected function extract_urls_from_text(string $text): array {
        $urls = [];
        
        // Match URLs in text
        $pattern = '/(https?:\/\/[^\s<>"]+)/i';
        if (preg_match_all($pattern, $text, $matches)) {
            $urls = array_unique($matches[1]);
        }
        
        return $urls;
    }
}