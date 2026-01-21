<?php
/**
 * NB Orchestrator - Executes NB-1 through NB-15 protocol
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../clients/llm_client.php');
require_once(__DIR__ . '/../helpers/json_validator.php');
require_once(__DIR__ . '/source_service.php');
require_once(__DIR__ . '/company_service.php');
require_once(__DIR__ . '/versioning_service.php');
require_once(__DIR__ . '/../../lib/config_helper.php');

use local_customerintel\clients\llm_client;
use local_customerintel\helpers\json_validator;
use local_customerintel\lib\config_helper;

/**
 * NBOrchestrator class
 * 
 * Executes the NB-1 through NB-15 research protocol with schema validation,
 * repair loops, and telemetry capture.
 * PRD Section 11 - Architecture Overview / Key Services
 */
class nb_orchestrator {
    
    /** @var array Research Protocol v15 NB definitions */
    const NB_DEFINITIONS = [
        'NB-1' => [
            'objective' => 'Customer Fundamentals',
            'description' => 'Build a foundational understanding of the customer company.',
            'perplexity_query' => 'Key facts, history, business model, and primary markets for [company name].',
            'system_prompt' => 'Summarize the customer\'s core identity—what they do, who they serve, and how they generate value. Include mission, vision, market positioning, and notable recent shifts.'
        ],
        'NB-2' => [
            'objective' => 'Financial Performance & Pressures',
            'description' => 'Identify recent financial trends, growth patterns, and pressures.',
            'perplexity_query' => 'Recent financial performance, growth drivers, and cost pressures for [company name].',
            'system_prompt' => 'Analyze the company\'s financial trajectory, revenue composition, and recent cost or margin trends. Highlight risks or opportunities in their financial posture.'
        ],
        'NB-3' => [
            'objective' => 'Leadership & Decision-Makers',
            'description' => 'Identify key leaders, their backgrounds, and influence.',
            'perplexity_query' => 'Current leadership team, executive changes, and recent public statements by leadership of [company name].',
            'system_prompt' => 'Summarize leadership composition, priorities, and recent commentary. Note any leadership transitions or emerging internal priorities.'
        ],
        'NB-4' => [
            'objective' => 'Strategic Initiatives & Expansion',
            'description' => 'Identify current strategies and expansion priorities.',
            'perplexity_query' => 'Recent strategic initiatives, acquisitions, partnerships, and geographic expansions by [company name].',
            'system_prompt' => 'Summarize strategic initiatives—where the company is investing, growing, or transforming. Include rationale, expected outcomes, and alignment with company goals.'
        ],
        'NB-5' => [
            'objective' => 'Operational Challenges',
            'description' => 'Identify execution or supply-side challenges.',
            'perplexity_query' => 'Operational issues, supply chain challenges, and production constraints faced by [company name].',
            'system_prompt' => 'Identify recurring operational challenges or efficiency constraints. Include internal process issues, capacity limitations, or supply vulnerabilities.'
        ],
        'NB-6' => [
            'objective' => 'Technology & Systems',
            'description' => 'Identify key technologies, systems, and digital capabilities.',
            'perplexity_query' => 'Core technologies, systems, and digital infrastructure in use by [company name].',
            'system_prompt' => 'Describe the company\'s technology stack, digital maturity, and ongoing modernization efforts. Note any dependencies or vulnerabilities.'
        ],
        'NB-7' => [
            'objective' => 'Competitive Dynamics',
            'description' => 'Understand competitors and the company\'s market positioning.',
            'perplexity_query' => 'Major competitors, market share trends, and competitive advantages for [company name].',
            'system_prompt' => 'Analyze how the company differentiates itself and responds to competition. Include competitor strategies and any changing market dynamics.'
        ],
        'NB-8' => [
            'objective' => 'Organizational Structure & Culture',
            'description' => 'Explore organizational characteristics and internal culture.',
            'perplexity_query' => 'Organizational structure, workforce composition, and corporate culture of [company name].',
            'system_prompt' => 'Summarize organizational culture, structure, and engagement trends. Include workforce priorities and cultural values influencing performance.'
        ],
        'NB-9' => [
            'objective' => 'Stakeholder Influence',
            'description' => 'Identify key external influencers shaping company decisions.',
            'perplexity_query' => 'External stakeholders, investors, regulators, or advocacy groups influencing [company name].',
            'system_prompt' => 'Describe external entities influencing company behavior or policy—investors, partnerships, regulators, and advocacy organizations.'
        ],
        'NB-10' => [
            'objective' => 'Sustainability & ESG',
            'description' => 'Analyze sustainability goals, ESG commitments, and reporting.',
            'perplexity_query' => 'Sustainability and ESG initiatives of [company name], including carbon reduction, DEI, and governance.',
            'system_prompt' => 'Summarize ESG strategy, metrics, and public commitments. Highlight key performance areas and potential gaps.'
        ],
        'NB-11' => [
            'objective' => 'Research & Innovation',
            'description' => 'Identify innovation pipelines and R&D focus.',
            'perplexity_query' => 'Recent research, innovation, or new product developments at [company name].',
            'system_prompt' => 'Summarize innovation priorities, product development pipelines, and technology investments. Highlight trends in innovation focus.'
        ],
        'NB-12' => [
            'objective' => 'Market and Customer Segments',
            'description' => 'Understand primary markets and customer relationships.',
            'perplexity_query' => 'Primary customer segments, buying trends, and satisfaction indicators for [company name].',
            'system_prompt' => 'Analyze target markets, key customer segments, and relationship strategies. Identify where growth is occurring or declining.'
        ],
        'NB-13' => [
            'objective' => 'Industry Context',
            'description' => 'Contextualize company activity within broader industry trends.',
            'perplexity_query' => 'Key industry developments, technology shifts, and regulatory changes affecting [company name].',
            'system_prompt' => 'Provide contextual analysis—what macro or regulatory forces shape this company\'s landscape and decisions.'
        ],
        'NB-14' => [
            'objective' => 'Future Outlook',
            'description' => 'Identify forward-looking trends, forecasts, and risks.',
            'perplexity_query' => 'Expert or analyst forecasts and future outlook for [company name].',
            'system_prompt' => 'Summarize expectations for the company\'s direction—growth, challenges, and near-term catalysts.'
        ],
        'NB-15' => [
            'objective' => 'Implications for Engagement',
            'description' => 'Synthesize actionable insights for commercial strategy.',
            'perplexity_query' => 'Strategic opportunities or engagement implications for partners working with [company name].',
            'system_prompt' => 'Based on all previous phases, synthesize key engagement implications for how to approach or serve this customer most effectively.'
        ]
    ];
    
    /**
     * Execute full NB protocol for a run
     * 
     * @param int $runid Run ID
     * @return bool Success
     * @throws \dml_exception
     */
    public function execute_protocol(int $runid): bool {
        global $DB;
        
        $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);
        
        // Update status to running
        $this->instrumented_db_set_field('local_ci_run', 'status', 'running', ['id' => $runid], $runid);
        $this->instrumented_db_set_field('local_ci_run', 'timestarted', time(), ['id' => $runid], $runid);
        
        $success = true;
        
        // Execute each NB sequentially
        foreach (array_keys(self::NB_DEFINITIONS) as $nbcode) {
            try {
                $result = $this->execute_nb($runid, $nbcode);
                
                // Check if this is a failed result from OpenAI API error
                if (isset($result['status']) && $result['status'] === 'failed') {
                    // Save as error instead of normal result
                    \local_customerintel\services\log_service::info($runid, "[execute_protocol] {$nbcode} failed due to API error - recording as failed");
                    
                    try {
                        $this->save_nb_error($runid, $nbcode, $result['error']);
                        \local_customerintel\services\log_service::info($runid, "[execute_protocol] {$nbcode} error recorded successfully");
                    } catch (\Exception $error_save_exception) {
                        \local_customerintel\services\log_service::error($runid, "[execute_protocol] {$nbcode} error save failed: " . $error_save_exception->getMessage());
                    }
                    
                    // Continue with next NB instead of marking as complete failure
                    $success = false;
                    continue;
                }
                
                // ===== INSTRUMENTATION: BEFORE save_nb_result() (execute_protocol) =====
                $result_is_empty = empty($result);
                $result_is_null = is_null($result);
                $payload_size = 0;
                
                if (!$result_is_null && !$result_is_empty) {
                    $result_json = json_encode($result);
                    $payload_size = strlen($result_json);
                } else {
                    $result_json = 'NULL or EMPTY';
                }
                
                \local_customerintel\services\log_service::debug($runid, "[execute_protocol] Attempting to save {$nbcode} result (payload size: {$payload_size} bytes)");
                \local_customerintel\services\log_service::debug($runid, "[execute_protocol] Result is empty: " . ($result_is_empty ? 'YES' : 'NO') . ", Result is null: " . ($result_is_null ? 'YES' : 'NO'));
                
                if ($payload_size > 0) {
                    \local_customerintel\services\log_service::debug($runid, "[execute_protocol] Result structure preview: " . substr($result_json, 0, 300) . '...');
                }
                
                try {
                    $record_id = $this->save_nb_result($runid, $nbcode, $result);
                    
                    // ===== INSTRUMENTATION: AFTER save_nb_result() SUCCESS (execute_protocol) =====
                    \local_customerintel\services\log_service::info($runid, "[execute_protocol] {$nbcode} result save completed successfully (record ID: {$record_id})");
                    
                } catch (\Exception $save_exception) {
                    // ===== INSTRUMENTATION: save_nb_result() EXCEPTION (execute_protocol) =====
                    \local_customerintel\services\log_service::error($runid, "[execute_protocol] {$nbcode} save failed: " . get_class($save_exception) . " (" . $save_exception->getMessage() . ")");
                    \local_customerintel\services\log_service::error($runid, "[execute_protocol] Failed save - nbcode: {$nbcode}, run_id: {$runid}");
                    \local_customerintel\services\log_service::error($runid, "[execute_protocol] Failed save - result structure: " . print_r($result, true));
                    
                    // Re-throw to trigger the outer catch block
                    throw $save_exception;
                }
                
            } catch (\Exception $e) {
                // Log error but continue with other NBs (partial completion)
                
                // ===== INSTRUMENTATION: BEFORE save_nb_error() (execute_protocol) =====
                $error_message = $e->getMessage();
                $error_size = strlen($error_message);
                
                \local_customerintel\services\log_service::debug($runid, "[execute_protocol] Attempting to save {$nbcode} ERROR (error size: {$error_size} bytes)");
                \local_customerintel\services\log_service::debug($runid, "[execute_protocol] Error message preview: " . substr($error_message, 0, 200) . '...');
                
                try {
                    $this->save_nb_error($runid, $nbcode, $error_message);
                    
                    // ===== INSTRUMENTATION: AFTER save_nb_error() SUCCESS (execute_protocol) =====
                    \local_customerintel\services\log_service::info($runid, "[execute_protocol] {$nbcode} error save completed successfully");
                    
                } catch (\Exception $error_save_exception) {
                    // ===== INSTRUMENTATION: save_nb_error() EXCEPTION (execute_protocol) =====
                    \local_customerintel\services\log_service::error($runid, "[execute_protocol] {$nbcode} ERROR save failed: " . get_class($error_save_exception) . " (" . $error_save_exception->getMessage() . ")");
                    \local_customerintel\services\log_service::error($runid, "[execute_protocol] Failed error save - nbcode: {$nbcode}, run_id: {$runid}");
                    \local_customerintel\services\log_service::error($runid, "[execute_protocol] Failed error save - original error: {$error_message}");
                    \local_customerintel\services\log_service::error($runid, "[execute_protocol] Failed error save - exception: " . print_r($error_save_exception, true));
                    
                    // Don't re-throw here - we want to continue with other NBs even if error saving fails
                }
                
                $success = false;
            }
        }
        
        // Update run metrics
        $this->update_run_metrics($runid);
        
        // Update run status
        $status = $success ? 'completed' : 'failed';
        $this->instrumented_db_set_field('local_ci_run', 'status', $status, ['id' => $runid], $runid);
        $this->instrumented_db_set_field('local_ci_run', 'timecompleted', time(), ['id' => $runid], $runid);
        
        // Create snapshot if run completed successfully
        if ($success) {
            try {
                $versioningservice = new versioning_service();
                $snapshotid = $versioningservice->create_snapshot($runid);
                
                // Log snapshot creation
                mtrace("Created snapshot {$snapshotid} for run {$runid}");
            } catch (\Exception $e) {
                // Log error but don't fail the run
                debugging("Failed to create snapshot for run {$runid}: " . $e->getMessage(), DEBUG_DEVELOPER);
            }
            
            // CITATION DOMAIN NORMALIZATION STEP
            // Run after NB orchestration completes but before retrieval rebalancing
            try {
                $this->normalize_citation_domains($runid);
            } catch (\Exception $e) {
                // Log error but don't fail the run - diversity calculations can proceed with URLs
                debugging("Citation domain normalization failed for run {$runid}: " . $e->getMessage(), DEBUG_DEVELOPER);
                \local_customerintel\services\log_service::error($runid, "Citation domain normalization failed: " . $e->getMessage());
            }
        }
        
        return $success;
    }
    
    /**
     * Execute single NB with schema validation and repair
     * 
     * @param int $runid Run ID
     * @param string $nbcode NB code (NB1-NB15)
     * @return array Result with payload and citations
     * @throws \moodle_exception
     */
    public function execute_nb(int $runid, string $nbcode): array {
        global $DB;
        
        $starttime = microtime(true);
        
        // Get run and company info
        $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);
        $company = $DB->get_record('local_ci_company', ['id' => $run->companyid], '*', MUST_EXIST);
        
        // Get target company info for dual-entity analysis
        $targetcompany = null;
        if (!empty($run->targetcompanyid)) {
            $targetcompany = $DB->get_record('local_ci_company', ['id' => $run->targetcompanyid]);
            if (!$targetcompany) {
                debugging("Target company ID {$run->targetcompanyid} not found, proceeding with single-entity analysis", DEBUG_DEVELOPER);
            }
        }
        
        // Get context (sources and chunks) - include both entities
        $sourceservice = new source_service();
        $context = $sourceservice->get_chunks_for_nb($run->companyid, $nbcode);
        
        // Add target company context if available
        if ($targetcompany) {
            $target_context = $sourceservice->get_chunks_for_nb($targetcompany->id, $nbcode);
            if (!empty($target_context)) {
                $context = array_merge($context, $target_context);
                debugging("DUAL-ENTITY: Added {$targetcompany->name} context to {$company->name} analysis", DEBUG_DEVELOPER);
            }
        }
        
        // Build prompt with dual-entity support
        $systemprompt = $this->build_system_prompt($nbcode);
        $userprompt = $this->build_user_prompt($nbcode, $company, $context, $targetcompany);
        
        // Get schema
        $schema = $this->load_nb_schema($nbcode);
        
        // Call LLM with strict JSON mode
        $llmclient = new llm_client();
        $maxattempts = 3; // PRD: max 2 retries = 3 total attempts
        $validpayload = null;
        $lastresponse = null;
        
        // Log start of NB execution
        $this->log_to_moodle($runid, $nbcode, 'Starting NB execution');
        
        for ($attempt = 1; $attempt <= $maxattempts; $attempt++) {
            try {
                // Use extract method for NB-specific extraction
                $response = $llmclient->extract($nbcode, $userprompt, $context['chunks'] ?? []);
                $lastresponse = $response;
                
                // Parse JSON
                $payload = json_decode($response['content'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \moodle_exception('invalidjsonresponse', 'local_customerintel');
                }
                
                // Validate against schema
                $validation = $llmclient->validate_json($nbcode, $payload);
                
                if ($validation['valid']) {
                    $validpayload = $payload;
                    $this->log_to_moodle($runid, $nbcode, "Valid JSON on attempt $attempt");
                    break;
                } else {
                    // Log validation errors
                    $this->log_to_moodle($runid, $nbcode, "Validation failed on attempt $attempt: " . implode('; ', $validation['errors']));
                    
                    // Try to repair
                    $repaired = $llmclient->repair_invalid_json($nbcode, $payload);
                    if ($repaired !== null) {
                        $validpayload = $repaired;
                        $this->log_to_moodle($runid, $nbcode, "Successfully repaired JSON on attempt $attempt");
                        break;
                    }
                    
                    // If repair failed, retry with error feedback
                    if ($attempt < $maxattempts) {
                        $userprompt = $this->add_error_feedback($userprompt, $validation['errors']);
                    }
                }
            } catch (\Exception $e) {
                debugging('NB execution error: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $this->log_to_moodle($runid, $nbcode, "Exception on attempt $attempt: " . $e->getMessage());
                if ($attempt === $maxattempts) {
                    // Create placeholder instead of throwing
                    $failure_reason = "NB execution failed with exception: " . $e->getMessage();
                    $this->log_to_moodle($runid, $nbcode, "Creating placeholder result: $failure_reason");
                    $validpayload = $this->create_placeholder_result($nbcode, $failure_reason);
                    break;
                }
            }
        }
        
        if ($validpayload === null) {
            $failure_reason = "NB validation failed after $maxattempts attempts";
            $this->log_to_moodle($runid, $nbcode, "Creating placeholder result: $failure_reason");
            $validpayload = $this->create_placeholder_result($nbcode, $failure_reason);
        }
        
        // Extract citations
        $citations = $validpayload['citations'] ?? [];
        
        // Calculate metrics
        $duration = (microtime(true) - $starttime) * 1000;
        $tokensused = $lastresponse['tokens_used'] ?? 0;
        
        // Log telemetry
        $this->log_telemetry($runid, $nbcode, $tokensused, $duration);
        
        return [
            'nbcode' => $nbcode,
            'payload' => $validpayload,
            'citations' => $citations,
            'duration_ms' => $duration,
            'tokens_used' => $tokensused,
            'status' => 'completed',
            'attempts' => $attempt
        ];
    }
    
    /**
     * Execute single NB with real API calls to Perplexity and OpenAI
     * 
     * @param int $runid Run ID
     * @param string $nbcode NB code (NB-1 to NB-15)
     * @param \stdClass $company Company object
     * @param string $openaiapikey OpenAI API key
     * @param string $perplexityapikey Perplexity API key
     * @param \stdClass|null $targetcompany Target company object for dual-entity analysis
     * @return array Result with payload and citations
     * @throws \moodle_exception
     */
    protected function execute_nb_with_real_apis(int $runid, string $nbcode, \stdClass $company, string $openaiapikey, string $perplexityapikey, ?\stdClass $targetcompany = null): array {
        global $DB;
        
        $start_time = microtime(true);
        
        // Get NB definition
        $nb_def = self::NB_DEFINITIONS[$nbcode];
        if (!$nb_def) {
            throw new \moodle_exception('invalidnbcode', 'local_customerintel', '', $nbcode);
        }
        
        // Step 1: Get company text chunks from database (dual-entity support)
        $source_service = new source_service();
        $chunks = $source_service->get_chunks_for_nb($company->id, $nbcode);
        
        // Add target company chunks if available
        if ($targetcompany) {
            $target_chunks = $source_service->get_chunks_for_nb($targetcompany->id, $nbcode);
            if (!empty($target_chunks)) {
                $chunks = array_merge($chunks, $target_chunks);
                debugging("DUAL-ENTITY API: Added {$targetcompany->name} chunks to analysis", DEBUG_DEVELOPER);
            }
        }
        
        // Step 2: Query Perplexity API with dual-entity query
        if ($targetcompany) {
            // Construct dual-entity query for broader coverage
            $entity_names = $company->name . " and " . $targetcompany->name;
            $perplexity_query = str_replace('[company name]', $entity_names, $nb_def['perplexity_query']);
            debugging("DUAL-ENTITY API: Perplexity query includes both {$company->name} and {$targetcompany->name}", DEBUG_DEVELOPER);
        } else {
            // Single entity query (existing behavior)
            $perplexity_query = str_replace('[company name]', $company->name, $nb_def['perplexity_query']);
        }
        $perplexity_results = $this->query_perplexity_api($runid, $perplexity_query, $perplexityapikey);
        
        // Step 3: Combine context with dual-entity support
        $combined_context = $this->combine_context($chunks, $perplexity_results, $company, $targetcompany);
        
        // Step 4: Query OpenAI with combined context
        $system_prompt = $nb_def['system_prompt'];
        try {
            $openai_response = $this->query_openai_api($system_prompt, $combined_context, $openaiapikey);
            
            // Step 5: Extract and validate JSON
            $payload = json_decode($openai_response['content'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \moodle_exception('invalidjsonresponse', 'local_customerintel', '', $nbcode);
            }
        } catch (\moodle_exception $e) {
            // OpenAI API failed - create a graceful failure record instead of aborting
            $error_message = "OpenAI API Error: " . $e->getMessage();
            \local_customerintel\services\log_service::error($runid, "OpenAI API failed for {$nbcode}: {$error_message}");
            
            // Return a failed result with error details for database recording
            return [
                'nbcode' => $nbcode,
                'status' => 'failed',
                'error' => $error_message,
                'payload' => null,
                'citations' => [],
                'duration_ms' => (microtime(true) - $start_time) * 1000,
                'tokens' => ($perplexity_results['tokens'] ?? 0) // Only count Perplexity tokens
            ];
        }
        
        // Calculate metrics
        $duration_ms = (microtime(true) - $start_time) * 1000;
        $total_tokens = ($openai_response['tokens'] ?? 0) + ($perplexity_results['tokens'] ?? 0);
        
        // Extract citations from both sources
        $citations = array_merge(
            $payload['citations'] ?? [],
            $perplexity_results['citations'] ?? []
        );
        
        // Log telemetry
        $this->log_telemetry($runid, $nbcode, $total_tokens, $duration_ms);
        
        return [
            'nbcode' => $nbcode,
            'payload' => $payload,
            'citations' => $citations,
            'duration_ms' => $duration_ms,
            'tokens_used' => $total_tokens,
            'status' => 'completed',
            'perplexity_results' => $perplexity_results['content'] ?? '',
            'openai_response' => $openai_response['content'] ?? ''
        ];
    }
    
    /**
     * Query Perplexity API for current market data
     * 
     * @param int $runid Run ID for logging
     * @param string $query Query string
     * @param string $apikey Perplexity API key
     * @return array Response with content and citations
     * @throws \moodle_exception
     */
    protected function query_perplexity_api(int $runid, string $query, string $apikey): array {
        // Entry logging to confirm function is called
        \local_customerintel\services\log_service::info($runid, "Entered query_perplexity_api() successfully");
        
        try {
            $endpoint = 'https://api.perplexity.ai/chat/completions';
        
            $headers = [
                "Authorization: Bearer " . trim($apikey),
                "Content-Type: application/json",
                "User-Agent: Rubi/1.0 (+https://rubi.digital)"
            ];
            
            $payload = json_encode([
                'model' => 'sonar-pro',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a research assistant providing current, factual information about companies and markets.'],
                    ['role' => 'user', 'content' => $query]
                ],
                'max_tokens' => 2000,
                'temperature' => 0.2,
                'return_citations' => true
            ]);
            
            // Debug logging - request details (before any curl operations)
            \local_customerintel\services\log_service::info($runid, "Perplexity API endpoint: " . $endpoint);
            \local_customerintel\services\log_service::info($runid, "Perplexity API method: POST");
            \local_customerintel\services\log_service::info($runid, "Perplexity API payload: " . $payload);
            \local_customerintel\services\log_service::info($runid, "Perplexity API headers count: " . count($headers));
            
            // Also keep debugging for developer mode
            debugging('Perplexity API endpoint: ' . $endpoint, DEBUG_DEVELOPER);
            debugging('Perplexity API method: POST', DEBUG_DEVELOPER);
            debugging('Perplexity API payload: ' . $payload, DEBUG_DEVELOPER);
            debugging('Perplexity API headers: ' . print_r($headers, true), DEBUG_DEVELOPER);
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_WHATEVER);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; Rubi/1.0; +https://rubi.digital)");
        curl_setopt($ch, CURLOPT_STDERR, fopen('/Users/jonberardi/Documents/GitHub/CustomerIntel_Rubi/local_customerintel/logs/perplexity_curl_debug.log', 'a'));
        
        // Log API key prefix to verify authentication header
        \local_customerintel\services\log_service::info($runid, "Perplexity Authorization header sent: Bearer " . substr($apikey, 0, 7) . "…");
        
        // Log cURL configuration
        \local_customerintel\services\log_service::info($runid, "Perplexity API cURL options set: IPv4 enforced, timeout 20s");
        
        // Log User-Agent setting
        \local_customerintel\services\log_service::info($runid, "Perplexity API User-Agent set for outbound request.");
        
        // Diagnostic logging for headers
        \local_customerintel\services\log_service::info($runid, "Perplexity full headers array: " . json_encode($headers));
        
        // Enhanced retry logic with exponential backoff
        $max_retries = 3;
        $retry_count = 0;
        $response = false;
        $http_code = 0;
        $error = '';
        $errno = 0;
        
        while ($retry_count <= $max_retries) {
            $start = microtime(true);
            $response = curl_exec($ch);
            $elapsed = round((microtime(true) - $start) * 1000);
            
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            
            $attempt_label = $retry_count === 0 ? "initial" : "retry #$retry_count";
            \local_customerintel\services\log_service::info($runid, "Perplexity $attempt_label - HTTP: $http_code, errno: $errno, error: " . ($error ?: 'None') . ", elapsed: {$elapsed}ms");
            
            // Success conditions
            if ($response !== false && $http_code === 200) {
                if ($retry_count > 0) {
                    \local_customerintel\services\log_service::info($runid, "Perplexity API succeeded after $retry_count retries");
                }
                break;
            }
            
            // Check if we should retry
            $should_retry = false;
            $retry_reason = '';
            
            // Network/connection errors (retryable)
            if ($response === false) {
                if (in_array($errno, [6, 7, 28, 35, 52, 56])) { // DNS, connection, timeout, SSL, empty response, network errors
                    $should_retry = true;
                    $retry_reason = "network error (errno: $errno)";
                }
            }
            // HTTP errors (selectively retryable)
            elseif (in_array($http_code, [429, 500, 502, 503, 504])) { // Rate limit, server errors
                $should_retry = true;
                $retry_reason = "HTTP $http_code error";
            }
            
            // Exit if no more retries or non-retryable error
            if (!$should_retry || $retry_count >= $max_retries) {
                if ($retry_count >= $max_retries) {
                    \local_customerintel\services\log_service::error($runid, "Perplexity API failed after $max_retries retries");
                } else {
                    \local_customerintel\services\log_service::error($runid, "Perplexity API non-retryable error: HTTP $http_code, errno: $errno");
                }
                break;
            }
            
            // Implement exponential backoff: 2^retry_count seconds
            $wait_time = min(pow(2, $retry_count), 8); // Cap at 8 seconds
            \local_customerintel\services\log_service::warning($runid, "Retrying Perplexity after $retry_reason in {$wait_time}s...");
            sleep($wait_time);
            $retry_count++;
        }
        
        curl_close($ch);
        
        // Debug logging - response details
        debugging('Perplexity API HTTP response code: ' . $http_code, DEBUG_DEVELOPER);
        if ($response) {
            $response_preview = substr($response, 0, 200);
            debugging('Perplexity API response (first 200 chars): ' . $response_preview, DEBUG_DEVELOPER);
        }
        if ($error) {
            debugging('Perplexity API cURL error: ' . $error, DEBUG_DEVELOPER);
        }
        
        if ($response === false) {
            // Log detailed HTTP error information before throwing exception
            \local_customerintel\services\log_service::error($runid, "Perplexity API HTTP code: " . $http_code);
            \local_customerintel\services\log_service::error($runid, "Perplexity API raw response: " . substr($response ?: 'FALSE', 0, 500));
            
            throw new \moodle_exception('perplexityapierror', 'local_customerintel', '', $error);
        }
        
        if ($http_code !== 200) {
            // Log detailed HTTP error information before throwing exception
            \local_customerintel\services\log_service::error($runid, "Perplexity API HTTP code: " . $http_code);
            \local_customerintel\services\log_service::error($runid, "Perplexity API raw response: " . substr($response, 0, 500));
            
            throw new \moodle_exception('perplexityapihttp', 'local_customerintel', '', $http_code . ': ' . $response);
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Log detailed HTTP error information before throwing exception
            \local_customerintel\services\log_service::error($runid, "Perplexity API HTTP code: " . $http_code);
            \local_customerintel\services\log_service::error($runid, "Perplexity API raw response: " . substr($response, 0, 500));
            
            throw new \moodle_exception('perplexityapijson', 'local_customerintel', '', json_last_error_msg());
        }
        
        $content = $data['choices'][0]['message']['content'] ?? '';
        $tokens = $data['usage']['total_tokens'] ?? 0;
        $citations = $data['citations'] ?? [];
        
            return [
                'content' => $content,
                'tokens' => $tokens,
                'citations' => $citations
            ];
            
        } catch (\Exception $e) {
            // Catch and log the real exception details
            \local_customerintel\services\log_service::error($runid, "Perplexity exception: " . $e->getMessage());
            \local_customerintel\services\log_service::error($runid, "Perplexity exception trace: " . $e->getTraceAsString());
            
            // Re-throw the original exception
            throw $e;
        }
    }
    
    /**
     * Query OpenAI API for structured analysis with improved error handling and retry logic
     * 
     * @param string $system_prompt System prompt
     * @param string $user_content Combined context
     * @param string $apikey OpenAI API key
     * @return array Response with content and token usage
     * @throws \moodle_exception
     */
    protected function query_openai_api(string $system_prompt, string $user_content, string $apikey): array {
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        $max_retries = 2; // Original attempt + 1 retry
        $last_error = '';
        $last_http_code = 0;
        $last_curl_errno = 0;
        
        $headers = [
            'Authorization: Bearer ' . $apikey,
            'Content-Type: application/json'
        ];
        
        $payload = json_encode([
            'model' => 'gpt-4-turbo',
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_content]
            ],
            'max_tokens' => 4000,
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object']
        ]);
        
        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            // Add randomized delay before retry attempts (but not on first attempt)
            if ($attempt > 1) {
                $delay = rand(1000, 3000); // 1-3 seconds in milliseconds
                usleep($delay * 1000); // Convert to microseconds
            }
            
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_errno = curl_errno($ch);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            // Store error details for final exception if all retries fail
            $last_http_code = $http_code;
            $last_curl_errno = $curl_errno;
            $last_error = $curl_error;
            
            // Check for cURL errors
            if ($response === false) {
                $error_details = "cURL Error #{$curl_errno}: {$curl_error}";
                
                // Retry on timeout or connection errors
                // Use numeric values for compatibility across PHP versions
                $retry_curl_errors = [28, 7, 6]; // TIMEOUT, COULDNT_CONNECT, COULDNT_RESOLVE_HOST
                if (in_array($curl_errno, $retry_curl_errors)) {
                    if ($attempt < $max_retries) {
                        debugging("OpenAI API cURL error (attempt {$attempt}/{$max_retries}): {$error_details}. Retrying...", DEBUG_DEVELOPER);
                        continue;
                    }
                }
                
                throw new \moodle_exception('openaiaapierror', 'local_customerintel', '', $error_details);
            }
            
            // Check HTTP status codes
            if ($http_code !== 200) {
                $error_details = "HTTP {$http_code}: " . substr($response, 0, 500);
                
                // Retry on 5xx server errors or specific 4xx errors
                if (($http_code >= 500 && $http_code < 600) || 
                    in_array($http_code, [408, 429, 502, 503, 504])) {
                    if ($attempt < $max_retries) {
                        debugging("OpenAI API HTTP error (attempt {$attempt}/{$max_retries}): {$error_details}. Retrying...", DEBUG_DEVELOPER);
                        continue;
                    }
                }
                
                throw new \moodle_exception('openaiaapihttp', 'local_customerintel', '', $error_details);
            }
            
            // Parse JSON response
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $json_error = json_last_error_msg();
                
                // Only retry JSON errors on first attempt (might be transient response corruption)
                if ($attempt < $max_retries && $attempt === 1) {
                    debugging("OpenAI API JSON parse error (attempt {$attempt}/{$max_retries}): {$json_error}. Retrying...", DEBUG_DEVELOPER);
                    continue;
                }
                
                throw new \moodle_exception('openaiapijson', 'local_customerintel', '', $json_error);
            }
            
            // Success - extract content and tokens
            $content = $data['choices'][0]['message']['content'] ?? '';
            $tokens = $data['usage']['total_tokens'] ?? 0;
            
            // Log successful retry if this wasn't the first attempt
            if ($attempt > 1) {
                debugging("OpenAI API succeeded on attempt {$attempt} after previous failures", DEBUG_DEVELOPER);
            }
            
            return [
                'content' => $content,
                'tokens' => $tokens
            ];
        }
        
        // This should never be reached due to exceptions in the loop, but added for safety
        throw new \moodle_exception('openaiaapierror', 'local_customerintel', '', 
            "All retry attempts failed. Last error: cURL #{$last_curl_errno}: {$last_error}, HTTP: {$last_http_code}");
    }
    
    /**
     * Combine company chunks and Perplexity results into analysis context
     * 
     * @param array $chunks Company text chunks
     * @param array $perplexity_results Perplexity API results
     * @param \stdClass $company Company object
     * @return string Combined context for OpenAI
     */
    protected function combine_context(array $chunks, array $perplexity_results, \stdClass $company, ?\stdClass $targetcompany = null): string {
        $context = "# Company Analysis Context\n\n";
        
        // Primary company details
        $context .= "## Primary Company Information\n";
        $context .= "- Name: {$company->name}\n";
        if (!empty($company->ticker)) {
            $context .= "- Ticker: {$company->ticker}\n";
        }
        if (!empty($company->website)) {
            $context .= "- Website: {$company->website}\n";
        }
        if (!empty($company->sector)) {
            $context .= "- Sector: {$company->sector}\n";
        }
        
        // Target company details (if dual-entity analysis)
        if ($targetcompany) {
            $context .= "\n## Target Company Information\n";
            $context .= "- Name: {$targetcompany->name}\n";
            if (!empty($targetcompany->ticker)) {
                $context .= "- Ticker: {$targetcompany->ticker}\n";
            }
            if (!empty($targetcompany->website)) {
                $context .= "- Website: {$targetcompany->website}\n";
            }
            if (!empty($targetcompany->sector)) {
                $context .= "- Sector: {$targetcompany->sector}\n";
            }
            $context .= "\n**Analysis Focus**: Examine relationship dynamics, competitive positioning, and strategic relevance between {$company->name} and {$targetcompany->name}.\n";
        }
        
        // Current market intelligence from Perplexity
        $context .= "\n## Current Market Intelligence\n";
        $context .= $perplexity_results['content'] ?? 'No current market data available.';
        
        // Company source documents
        $context .= "\n\n## Company Documentation\n";
        if (isset($chunks['sources']) && is_array($chunks['sources'])) {
            foreach ($chunks['sources'] as $source) {
                $source_id = $source['source_id'] ?? 0;
                $title = $source['source_title'] ?? 'Unknown Source';
                
                if (isset($source['chunks']) && is_array($source['chunks'])) {
                    foreach ($source['chunks'] as $chunk) {
                        $text = $chunk['text'] ?? '';
                        if (!empty($text)) {
                            $context .= "\n### [Source ID: {$source_id}] {$title}\n";
                            $context .= "{$text}\n";
                            $context .= "---\n";
                        }
                    }
                }
            }
        } else {
            $context .= "No company documentation available.\n";
        }
        
        $context .= "\n\n## Analysis Request\n";
        $context .= "Based on the above current market intelligence and company documentation, provide a structured JSON analysis. Include specific citations with source IDs for all claims and findings.";
        
        return $context;
    }
    
    /**
     * Build system prompt for NB
     * 
     * @param string $nbcode NB code
     * @return string System prompt
     */
    protected function build_system_prompt(string $nbcode): string {
        $nb_def = self::NB_DEFINITIONS[$nbcode] ?? null;
        if (!$nb_def) {
            throw new \moodle_exception('invalidnbcode', 'local_customerintel', '', $nbcode);
        }
        
        $objective = $nb_def['objective'];
        $system_prompt = $nb_def['system_prompt'];
        
        $prompt = "You are an expert business analyst executing the {$objective} analysis.\n";
        $prompt .= "{$system_prompt}\n\n";
        $prompt .= "You must respond with valid JSON that includes:\n";
        $prompt .= "- summary: string (comprehensive analysis summary)\n";
        $prompt .= "- key_findings: array of strings (specific insights)\n";
        $prompt .= "- implications: array of strings (strategic implications)\n";
        $prompt .= "- citations: array of objects with source_id, quote, and context\n";
        $prompt .= "\nBe precise, analytical, and comprehensive. Include citations for all facts and findings.";
        
        return $prompt;
    }
    
    /**
     * Build user prompt with context
     * 
     * @param string $nbcode NB code
     * @param \stdClass $company Company object
     * @param array $context Chunks and sources
     * @return string User prompt
     */
    protected function build_user_prompt(string $nbcode, \stdClass $company, array $context, ?\stdClass $targetcompany = null): string {
        $nb_def = self::NB_DEFINITIONS[$nbcode] ?? null;
        if (!$nb_def) {
            throw new \moodle_exception('invalidnbcode', 'local_customerintel', '', $nbcode);
        }
        
        $objective = $nb_def['objective'];
        
        $prompt = "Analyze {$company->name} for: {$objective}\n\n";
        $prompt .= "Company Details:\n";
        $prompt .= "- Name: {$company->name}\n";
        if (!empty($company->ticker)) {
            $prompt .= "- Ticker: {$company->ticker}\n";
        }
        if (!empty($company->website)) {
            $prompt .= "- Website: {$company->website}\n";
        }
        if (!empty($company->sector)) {
            $prompt .= "- Sector: {$company->sector}\n";
        }
        
        $prompt .= "\n=== CONTEXT DOCUMENTS ===\n\n";
        
        // Handle structure from get_chunks_for_nb
        if (isset($context['sources'])) {
            foreach ($context['sources'] as $source) {
                $sourceid = $source['source_id'] ?? 0;
                $title = $source['source_title'] ?? 'Unknown Source';
                
                // Process chunks from this source
                if (isset($source['chunks']) && is_array($source['chunks'])) {
                    foreach ($source['chunks'] as $chunk) {
                        $text = $chunk['text'] ?? '';
                        if (!empty($text)) {
                            $prompt .= "[Source ID: {$sourceid}] {$title}\n";
                            $prompt .= "{$text}\n\n";
                            $prompt .= "---\n\n";
                        }
                    }
                }
            }
        }
        
        $prompt .= "=== END CONTEXT ===\n\n";
        $prompt .= "Based on the above context, provide your analysis for {$objective}.\n";
        $prompt .= "Remember to include source_id references in your citations.";
        
        return $prompt;
    }
    
    /**
     * Load JSON schema for NB
     * 
     * @param string $nbcode NB code
     * @return array JSON schema
     * @throws \moodle_exception
     */
    protected function load_nb_schema(string $nbcode): array {
        global $CFG;
        
        // Build path to schema file
        $schemafile = $CFG->dirroot . '/local/customerintel/schemas/' . strtolower($nbcode) . '.json';
        
        // Check if schema file exists
        if (!file_exists($schemafile)) {
            // Fall back to base schema
            debugging('Schema file not found: ' . $schemafile, DEBUG_DEVELOPER);
            return $this->get_base_schema();
        }
        
        // Load and parse schema
        $schemajson = file_get_contents($schemafile);
        $schema = json_decode($schemajson, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \moodle_exception('invalidschemafile', 'local_customerintel', '', $nbcode);
        }
        
        return $schema;
    }
    
    /**
     * Get base schema for fallback
     * 
     * @return array Base JSON schema
     */
    protected function get_base_schema(): array {
        return [
            'type' => 'object',
            'required' => ['summary', 'key_points', 'citations'],
            'properties' => [
                'summary' => ['type' => 'string'],
                'key_points' => [
                    'type' => 'array',
                    'items' => ['type' => 'string']
                ],
                'citations' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['source_id'],
                        'properties' => [
                            'source_id' => ['type' => 'integer'],
                            'quote' => ['type' => 'string'],
                            'page' => ['type' => 'string'],
                            'url' => ['type' => 'string']
                        ]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Validate JSON against schema
     * 
     * @param string $nbcode NB code
     * @param array $data Data to validate
     * @return array Validation result
     */
    protected function validate_json_against_schema(string $nbcode, array $data): array {
        $schema = $this->load_nb_schema($nbcode);
        
        // Use json_validator helper
        $result = json_validator::validate($data, $schema);
        
        return [
            'valid' => $result['valid'],
            'errors' => $result['errors'] ?? []
        ];
    }
    
    /**
     * Repair invalid JSON
     * 
     * @param array $data Invalid data
     * @param string $nbcode NB code
     * @param array $errors Validation errors
     * @return array|null Repaired data or null
     */
    protected function repair_invalid_json(array $data, string $nbcode, array $errors): ?array {
        $schema = $this->load_nb_schema($nbcode);
        
        // Use json_validator helper for repair
        $repaired = json_validator::repair($data, $schema);
        
        if ($repaired !== null) {
            // Validate repaired data
            $validation = json_validator::validate($repaired, $schema);
            if ($validation['valid']) {
                return $repaired;
            }
        }
        
        return null;
    }
    
    /**
     * Add error feedback to prompt
     * 
     * @param string $prompt Original prompt
     * @param array $errors Validation errors
     * @return string Updated prompt
     */
    protected function add_error_feedback(string $prompt, array $errors): string {
        $prompt .= "\n\n=== VALIDATION ERRORS ===\n";
        $prompt .= "Your previous response had the following validation errors:\n";
        
        foreach ($errors as $error) {
            $prompt .= "- " . $error . "\n";
        }
        
        $prompt .= "\nPlease provide a corrected JSON response that addresses these issues.";
        
        return $prompt;
    }
    
    /**
     * Create placeholder result for failed NB execution
     * 
     * @param string $nbcode NB code
     * @param string $reason Failure reason for logging
     * @return array Placeholder payload
     */
    protected function create_placeholder_result(string $nbcode, string $reason): array {
        // Create minimal valid structure based on schema requirements
        $placeholder = [
            'citations' => [],
            'execution_status' => 'failed',
            'failure_reason' => $reason,
            'placeholder' => true
        ];
        
        // Add NB-specific placeholder structure
        switch ($nbcode) {
            case 'nb10': // Risk Management
                $placeholder['risk_assessment'] = [
                    'top_risks' => [
                        [
                            'risk' => 'Data unavailable due to processing failure',
                            'category' => 'operational',
                            'likelihood' => 'medium',
                            'impact' => 'moderate',
                            'trend' => 'stable'
                        ]
                    ],
                    'risk_appetite' => 'moderate',
                    'overall_exposure' => 'medium'
                ];
                $placeholder['mitigation_strategies'] = [];
                $placeholder['resilience_measures'] = [
                    'business_continuity' => [
                        'plan_status' => 'insufficient',
                        'testing_frequency' => 'Unknown'
                    ],
                    'disaster_recovery' => [
                        'rto' => 'Unknown',
                        'rpo' => 'Unknown',
                        'backup_strategy' => 'Unknown'
                    ],
                    'insurance_coverage' => [
                        'adequacy' => 'insufficient',
                        'key_policies' => []
                    ]
                ];
                $placeholder['crisis_management'] = [
                    'preparedness' => 'unprepared',
                    'response_capability' => 'poor',
                    'communication_plan' => 'none'
                ];
                break;
                
            default:
                // Generic placeholder for other NBs
                $placeholder['status'] = 'Processing failed - data unavailable';
                $placeholder['analysis'] = 'Unable to complete analysis due to technical issues';
                break;
        }
        
        return $placeholder;
    }
    
    /**
     * Log telemetry data
     * 
     * @param int $runid Run ID  
     * @param string $nbcode NB code
     * @param int $tokensused Tokens used
     * @param float $duration Duration in ms
     * @return void
     * @throws \dml_exception
     */
    protected function log_telemetry(int $runid, string $nbcode, int $tokensused, float $duration): void {
        global $DB;
        
        // Record comprehensive telemetry
        $metrics = [
            $nbcode . '_tokens' => $tokensused,
            $nbcode . '_duration_ms' => $duration,
            $nbcode . '_temperature' => 0.2,
            $nbcode . '_provider' => get_config('local_customerintel', 'llm_provider')
        ];
        
        foreach ($metrics as $key => $value) {
            $telemetry = new \stdClass();
            $telemetry->runid = $runid;
            $telemetry->metrickey = $key;
            if (is_numeric($value)) {
                $telemetry->metricvaluenum = $value;
            } else {
                $telemetry->payload = json_encode(['value' => $value]);
            }
            $telemetry->timecreated = time();
            $this->instrumented_db_insert('local_ci_telemetry', $telemetry, $runid);
        }
        
        
        // Calculate and record cost estimate
        $costservice = new \local_customerintel\services\cost_service();
        $cost = $costservice->calculate_token_cost($tokensused);
        
        $telemetry = new \stdClass();
        $telemetry->runid = $runid;
        $telemetry->metrickey = $nbcode . '_cost';
        $telemetry->metricvaluenum = $cost;
        $telemetry->timecreated = time();
        $this->instrumented_db_insert('local_ci_telemetry', $telemetry, $runid);
    }
    
    /**
     * Record NB telemetry
     * 
     * @param int $runid Run ID
     * @param string $nbcode NB code
     * @param array $metrics Metrics to record
     * @return void
     * @throws \dml_exception
     */
    protected function record_telemetry(int $runid, string $nbcode, array $metrics): void {
        global $DB;
        
        foreach ($metrics as $key => $value) {
            $telemetry = new \stdClass();
            $telemetry->runid = $runid;
            $telemetry->metrickey = $nbcode . '_' . $key;
            $telemetry->metricvaluenum = is_numeric($value) ? $value : null;
            $telemetry->payload = is_array($value) ? json_encode($value) : $value;
            $telemetry->timecreated = time();
            
            $this->instrumented_db_insert('local_ci_telemetry', $telemetry, $runid);
        }
    }
    
    /**
     * Save NB result to database with comprehensive diagnostics and validation
     * 
     * @param int $runid Run ID
     * @param string $nbcode NB code
     * @param array $result Result data
     * @return int NB result ID
     * @throws \dml_exception
     */
    protected function save_nb_result(int $runid, string $nbcode, array $result): int {
        global $DB;
        
        // Log the incoming data for diagnostics
        $this->safe_log_debug($runid, "save_nb_result called for {$nbcode} with data: " . json_encode([
            'runid' => $runid,
            'nbcode' => $nbcode,
            'result_keys' => array_keys($result),
            'payload_size' => isset($result['payload']) ? strlen(json_encode($result['payload'])) : 0,
            'citations_size' => isset($result['citations']) ? strlen(json_encode($result['citations'])) : 0
        ]));
        
        // Validate runid exists in local_ci_run
        if (!$this->validate_runid($runid)) {
            $error = "Invalid runid {$runid} - no matching record in local_ci_run table";
            $this->safe_log_error($runid, $error);
            throw new \moodle_exception($error);
        }
        
        // Validate nbcode format
        if (!$this->validate_nbcode($nbcode)) {
            $error = "Invalid nbcode '{$nbcode}' - must be NB-1 through NB-15";
            $this->safe_log_error($runid, $error);
            throw new \moodle_exception($error);
        }
        
        // Size protection - 10MB limit to prevent MySQL packet overflow
        $max_size = 10 * 1024 * 1024; // 10MB
        
        $jsonpayload = json_encode($result['payload'] ?? []);
        $citations = json_encode($result['citations'] ?? []);
        
        // Validate JSON encoding succeeded
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = "JSON encoding failed for {$nbcode}: " . json_last_error_msg();
            $this->safe_log_error($runid, $error);
            throw new \moodle_exception($error);
        }
        
        // Check jsonpayload size
        if (strlen($jsonpayload) > $max_size) {
            $this->safe_log_error($runid, "NB {$nbcode} payload too large (" . strlen($jsonpayload) . " bytes), truncating");
            $truncated_payload = [
                'error' => 'Payload truncated due to size limit',
                'original_size' => strlen($jsonpayload),
                'truncated_data' => substr($jsonpayload, 0, $max_size - 1000) // Leave room for error message
            ];
            $jsonpayload = json_encode($truncated_payload);
        }
        
        // Check citations size
        if (strlen($citations) > $max_size) {
            $this->safe_log_error($runid, "NB {$nbcode} citations too large (" . strlen($citations) . " bytes), truncating");
            $truncated_citations = [
                'error' => 'Citations truncated due to size limit',
                'original_size' => strlen($citations),
                'note' => 'Some citations were removed to fit database constraints'
            ];
            $citations = json_encode($truncated_citations);
        }
        
        // Check if result already exists
        $existing = $DB->get_record('local_ci_nb_result', ['runid' => $runid, 'nbcode' => $nbcode]);
        
        if ($existing) {
            // Update existing record
            $existing->jsonpayload = $jsonpayload;
            $existing->citations = $citations;
            
            // Ensure numeric fields have defaults (set to 0 if null)
            $existing->durationms = isset($result['duration_ms']) && $result['duration_ms'] !== null ? (int)$result['duration_ms'] : 0;
            $existing->tokensused = isset($result['tokens_used']) && $result['tokens_used'] !== null ? (int)$result['tokens_used'] : 0;
            
            $existing->status = $result['status'] ?? 'completed';
            $existing->timemodified = time();
            
            // ===== COMPREHENSIVE PRE-UPDATE DIAGNOSTICS =====
            \local_customerintel\services\log_service::debug($runid, "Preparing to update existing NB-{$nbcode} result (ID: {$existing->id})");
            
            // Validate fields before update
            $field_validation = $this->validate_nb_record_fields($existing, $runid);
            if ($field_validation['valid']) {
                \local_customerintel\services\log_service::debug($runid, "Update field check: All valid ✅");
            } else {
                \local_customerintel\services\log_service::error($runid, "Update field check FAILED: " . implode('; ', $field_validation['errors']));
            }
            
            // Log update record analysis
            \local_customerintel\services\log_service::debug($runid, "Update record analysis: ID={$existing->id}, payload_size=" . strlen($existing->jsonpayload) . " bytes");
            
            try {
                \local_customerintel\services\log_service::debug($runid, "Attempting database update...");
                $this->instrumented_db_update('local_ci_nb_result', $existing, $runid);
                \local_customerintel\services\log_service::debug($runid, "Update succeeded for ID={$existing->id}");
                \local_customerintel\services\log_service::debug($runid, "NB {$nbcode} updated in local_ci_nb_result successfully (ID: {$existing->id})");
                return $existing->id;
                
            } catch (\Exception $e) {
                // ===== COMPREHENSIVE UPDATE ERROR DIAGNOSTICS =====
                \local_customerintel\services\log_service::error($runid, "Database update failed for NB-{$nbcode}");
                \local_customerintel\services\log_service::error($runid, "Update exception type: " . get_class($e));
                \local_customerintel\services\log_service::error($runid, "Update exception message: " . $e->getMessage());
                
                // Get database last error
                $last_error = '';
                try {
                    if (method_exists($DB, 'get_last_error')) {
                        $last_error = $DB->get_last_error();
                    }
                } catch (\Exception $db_err) {
                    $last_error = 'Could not retrieve: ' . $db_err->getMessage();
                }
                \local_customerintel\services\log_service::error($runid, "Update last DB error: " . ($last_error ?: 'None available'));
                
                \local_customerintel\services\log_service::error($runid, "Table: local_ci_nb_result, Operation: update_record, Record ID: {$existing->id}");
                \local_customerintel\services\log_service::error($runid, "Update Run ID: {$runid}, NB Code: {$nbcode}");
                
                throw $e;
            }
            
        } else {
            // Insert new record
            $nbresult = new \stdClass();
            $nbresult->runid = $runid;
            $nbresult->nbcode = $nbcode;
            $nbresult->jsonpayload = $jsonpayload;
            $nbresult->citations = $citations;
            
            // Ensure numeric fields have defaults (set to 0 if null)
            $nbresult->durationms = isset($result['duration_ms']) && $result['duration_ms'] !== null ? (int)$result['duration_ms'] : 0;
            $nbresult->tokensused = isset($result['tokens_used']) && $result['tokens_used'] !== null ? (int)$result['tokens_used'] : 0;
            
            $nbresult->status = $result['status'] ?? 'completed';
            
            // Ensure timestamp fields are set (use current time if not provided)
            $current_time = time();
            $nbresult->timecreated = $current_time;
            $nbresult->timemodified = $current_time;
            
            // Final validation of all required fields
            $validation_errors = $this->validate_nb_record($nbresult);
            if (!empty($validation_errors)) {
                $error = "NB record validation failed for {$nbcode}: " . implode(', ', $validation_errors);
                $this->safe_log_error($runid, $error);
                throw new \moodle_exception($error);
            }
            
            // ===== COMPREHENSIVE PRE-INSERT DIAGNOSTICS =====
            \local_customerintel\services\log_service::debug($runid, "Preparing to insert NB-{$nbcode} result");
            
            // Log record keys and data types
            $record_analysis = [];
            foreach (get_object_vars($nbresult) as $key => $value) {
                $type = gettype($value);
                $size = is_string($value) ? strlen($value) : 'N/A';
                $record_analysis[] = "{$key}: {$type} ({$size} bytes)";
            }
            \local_customerintel\services\log_service::debug($runid, "Record keys: " . implode(', ', array_keys(get_object_vars($nbresult))));
            \local_customerintel\services\log_service::debug($runid, "Record analysis: " . implode('; ', $record_analysis));
            
            // Validate fields against database schema
            $field_validation = $this->validate_nb_record_fields($nbresult, $runid);
            if ($field_validation['valid']) {
                \local_customerintel\services\log_service::debug($runid, "Field check: All valid ✅");
            } else {
                \local_customerintel\services\log_service::error($runid, "Field check FAILED: " . implode('; ', $field_validation['errors']));
            }
            
            // JSON payload validation
            $payload_size = strlen($nbresult->jsonpayload);
            $payload_size_mb = round($payload_size / (1024 * 1024), 2);
            \local_customerintel\services\log_service::debug($runid, "JSON payload size: {$payload_size} bytes ({$payload_size_mb} MB)");
            \local_customerintel\services\log_service::debug($runid, "JSON first 200 chars: " . substr($nbresult->jsonpayload, 0, 200));
            
            // Validate JSON is valid
            $json_test = json_decode($nbresult->jsonpayload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                \local_customerintel\services\log_service::debug($runid, "JSON validation: Valid ✅");
            } else {
                \local_customerintel\services\log_service::error($runid, "JSON validation FAILED: " . json_last_error_msg());
            }
            
            // Check payload size limits
            if ($payload_size > 4 * 1024 * 1024) { // 4MB
                \local_customerintel\services\log_service::error($runid, "Payload size exceeds 4MB limit: {$payload_size_mb} MB");
            }
            
            // Log complete record for debugging
            \local_customerintel\services\log_service::debug($runid, "Complete record structure: " . json_encode($nbresult, JSON_PRETTY_PRINT));
            
            try {
                \local_customerintel\services\log_service::debug($runid, "Attempting database insert...");
                $insert_id = $this->instrumented_db_insert('local_ci_nb_result', $nbresult, $runid);
                \local_customerintel\services\log_service::debug($runid, "Insertion succeeded: ID={$insert_id}");
                \local_customerintel\services\log_service::debug($runid, "NB {$nbcode} completed successfully - saved to local_ci_nb_result (ID: {$insert_id})");
                return $insert_id;
                
            } catch (\Exception $e) {
                // ===== COMPREHENSIVE ERROR DIAGNOSTICS =====
                \local_customerintel\services\log_service::error($runid, "Database insert failed for NB-{$nbcode}");
                \local_customerintel\services\log_service::error($runid, "Exception type: " . get_class($e));
                \local_customerintel\services\log_service::error($runid, "Exception message: " . $e->getMessage());
                
                // Check for specific DML exception types
                if ($e instanceof \dml_write_exception) {
                    \local_customerintel\services\log_service::error($runid, "DML Write Exception detected");
                } else if ($e instanceof \dml_exception) {
                    \local_customerintel\services\log_service::error($runid, "General DML Exception detected");
                }
                
                // Get database last error
                $last_error = '';
                try {
                    if (method_exists($DB, 'get_last_error')) {
                        $last_error = $DB->get_last_error();
                    }
                } catch (\Exception $db_err) {
                    $last_error = 'Could not retrieve: ' . $db_err->getMessage();
                }
                \local_customerintel\services\log_service::error($runid, "Last DB error: " . ($last_error ?: 'None available'));
                
                // Log payload size again for error context
                $payload_size = strlen($nbresult->jsonpayload);
                $payload_size_mb = round($payload_size / (1024 * 1024), 2);
                \local_customerintel\services\log_service::error($runid, "Payload size: {$payload_size_mb} MB");
                
                // Field count and validation
                $field_count = count(get_object_vars($nbresult));
                \local_customerintel\services\log_service::error($runid, "Field count: {$field_count}");
                
                // Re-run field validation in error context
                $field_validation = $this->validate_nb_record_fields($nbresult, $runid);
                if (!$field_validation['valid']) {
                    \local_customerintel\services\log_service::error($runid, "Field mismatches: " . implode('; ', $field_validation['errors']));
                }
                
                // Log raw record content for debugging (truncated)
                $record_json = json_encode($nbresult, JSON_PRETTY_PRINT);
                if (strlen($record_json) > 5000) {
                    $record_json = substr($record_json, 0, 5000) . '... [truncated]';
                }
                \local_customerintel\services\log_service::error($runid, "Raw record content: " . $record_json);
                
                // Log table and operation details
                \local_customerintel\services\log_service::error($runid, "Table: local_ci_nb_result, Operation: insert_record");
                \local_customerintel\services\log_service::error($runid, "Run ID: {$runid}, NB Code: {$nbcode}");
                
                throw $e;
            }
        }
    }
    
    /**
     * Log to Moodle's logging system
     * 
     * @param int $runid Run ID
     * @param string $nbcode NB code
     * @param string $message Log message
     * @return void
     */
    protected function log_to_moodle(int $runid, string $nbcode, string $message): void {
        global $DB, $USER;
        
        // Log to Moodle's standard log
        $context = \context_system::instance();
        $event = \core\event\user_created::create([
            'context' => $context,
            'objectid' => $runid,
            'other' => [
                'nbcode' => $nbcode,
                'message' => $message
            ]
        ]);
        
        // Also add to debugging if enabled
        debugging("NBOrchestrator[$runid/$nbcode]: $message", DEBUG_DEVELOPER);
        
        // Store in telemetry for audit trail
        $telemetry = new \stdClass();
        $telemetry->runid = $runid;
        $telemetry->metrickey = $nbcode . '_log';
        $telemetry->payload = json_encode([
            'timestamp' => time(),
            'message' => $message,
            'userid' => $USER->id
        ]);
        $telemetry->timecreated = time();
        
        try {
            $this->instrumented_db_insert('local_ci_telemetry', $telemetry, $runid);
        } catch (\Exception $e) {
            debugging('Failed to log telemetry: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
    
    /**
     * Save NB error with comprehensive diagnostics and validation
     * 
     * @param int $runid Run ID
     * @param string $nbcode NB code
     * @param string $error Error message
     * @return void
     * @throws \dml_exception
     */
    protected function save_nb_error(int $runid, string $nbcode, string $error): void {
        global $DB;
        
        // Log the incoming error data for diagnostics
        $this->safe_log_debug($runid, "save_nb_error called for {$nbcode} with error: " . substr($error, 0, 200) . '...');
        
        // Validate runid exists in local_ci_run
        if (!$this->validate_runid($runid)) {
            $validation_error = "Invalid runid {$runid} - no matching record in local_ci_run table";
            $this->safe_log_error($runid, $validation_error);
            debugging($validation_error, DEBUG_DEVELOPER);
            return; // Skip saving if runid is invalid
        }
        
        // Validate nbcode format
        if (!$this->validate_nbcode($nbcode)) {
            $validation_error = "Invalid nbcode '{$nbcode}' - must be NB-1 through NB-15";
            $this->safe_log_error($runid, $validation_error);
            debugging($validation_error, DEBUG_DEVELOPER);
            return; // Skip saving if nbcode is invalid
        }
        
        // Size protection for error messages
        $max_size = 10 * 1024 * 1024; // 10MB
        
        $error_payload = ['error' => $error, 'timestamp' => time()];
        $jsonpayload = json_encode($error_payload);
        
        // Validate JSON encoding succeeded
        if (json_last_error() !== JSON_ERROR_NONE) {
            $json_error = "JSON encoding failed for error message: " . json_last_error_msg();
            $this->safe_log_error($runid, $json_error);
            debugging($json_error, DEBUG_DEVELOPER);
            return; // Skip saving if JSON encoding fails
        }
        
        if (strlen($jsonpayload) > $max_size) {
            // Truncate the error message if it's too large
            $truncated_error = substr($error, 0, $max_size - 1000) . '... [Error message truncated]';
            $error_payload = [
                'error' => $truncated_error,
                'original_size' => strlen($error),
                'truncated' => true,
                'timestamp' => time()
            ];
            $jsonpayload = json_encode($error_payload);
            $this->safe_log_debug($runid, "Error message truncated for {$nbcode} (original size: " . strlen($error) . " bytes)");
        }
        
        // Check if exists
        if ($existing = $DB->get_record('local_ci_nb_result', ['runid' => $runid, 'nbcode' => $nbcode])) {
            $existing->jsonpayload = $jsonpayload;
            $existing->status = 'failed';
            $existing->timemodified = time();
            
            // Log record before update
            $this->safe_log_debug($runid, "Updating existing NB error record: " . json_encode([
                'id' => $existing->id,
                'runid' => $existing->runid,
                'nbcode' => $existing->nbcode,
                'status' => $existing->status,
                'error_payload_length' => strlen($existing->jsonpayload)
            ]));
            
            try {
                $this->instrumented_db_update('local_ci_nb_result', $existing, $runid);
                $this->safe_log_debug($runid, "NB {$nbcode} error updated in local_ci_nb_result successfully (ID: {$existing->id})");
                
            } catch (\Exception $e) {
                // Log detailed error update failure information as separate entries
                $this->safe_log_error($runid, "Database update failed for {$nbcode} error: MySQL error: " . $e->getMessage());
                
                // Log the complete record payload being written
                $record_json = json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $record_json = 'JSON encoding failed: ' . json_last_error_msg() . '. Raw data: ' . print_r($existing, true);
                }
                $this->safe_log_error($runid, "Record payload: " . $record_json);
                
                // Log additional database error info if available
                if (method_exists($DB, 'get_last_error')) {
                    $last_error = $DB->get_last_error();
                    if ($last_error) {
                        $this->safe_log_error($runid, "Database last error: " . $last_error);
                    }
                }
                
                // Log table and operation details
                $this->safe_log_error($runid, "Table: local_ci_nb_result, Operation: update_record, Record ID: {$existing->id}");
                $this->safe_log_error($runid, "Run ID: {$runid}, NB Code: {$nbcode}");
                
                throw $e;
            }
            
        } else {
            $nbresult = new \stdClass();
            $nbresult->runid = $runid;
            $nbresult->nbcode = $nbcode;
            $nbresult->jsonpayload = $jsonpayload;
            $nbresult->citations = '[]'; // Empty citations for error records
            
            // Ensure numeric fields have explicit defaults
            $nbresult->durationms = 0;
            $nbresult->tokensused = 0;
            
            $nbresult->status = 'failed';
            
            // Ensure timestamp fields are set
            $current_time = time();
            $nbresult->timecreated = $current_time;
            $nbresult->timemodified = $current_time;
            
            // Final validation of all required fields
            $validation_errors = $this->validate_nb_record($nbresult);
            if (!empty($validation_errors)) {
                $validation_error = "NB error record validation failed for {$nbcode}: " . implode(', ', $validation_errors);
                $this->safe_log_error($runid, $validation_error);
                debugging($validation_error, DEBUG_DEVELOPER);
                return; // Skip saving if validation fails
            }
            
            // ===== COMPREHENSIVE PRE-INSERT DIAGNOSTICS FOR ERROR =====
            \local_customerintel\services\log_service::debug($runid, "Preparing to insert NB-{$nbcode} ERROR result");
            
            // Log record keys and data types
            $record_analysis = [];
            foreach (get_object_vars($nbresult) as $key => $value) {
                $type = gettype($value);
                $size = is_string($value) ? strlen($value) : 'N/A';
                $record_analysis[] = "{$key}: {$type} ({$size} bytes)";
            }
            \local_customerintel\services\log_service::debug($runid, "Error record keys: " . implode(', ', array_keys(get_object_vars($nbresult))));
            \local_customerintel\services\log_service::debug($runid, "Error record analysis: " . implode('; ', $record_analysis));
            
            // Validate fields against database schema
            $field_validation = $this->validate_nb_record_fields($nbresult, $runid);
            if ($field_validation['valid']) {
                \local_customerintel\services\log_service::debug($runid, "Error field check: All valid ✅");
            } else {
                \local_customerintel\services\log_service::error($runid, "Error field check FAILED: " . implode('; ', $field_validation['errors']));
            }
            
            // JSON payload validation
            $payload_size = strlen($nbresult->jsonpayload);
            $payload_size_mb = round($payload_size / (1024 * 1024), 2);
            \local_customerintel\services\log_service::debug($runid, "Error JSON payload size: {$payload_size} bytes ({$payload_size_mb} MB)");
            \local_customerintel\services\log_service::debug($runid, "Error JSON first 200 chars: " . substr($nbresult->jsonpayload, 0, 200));
            
            // Validate JSON is valid
            $json_test = json_decode($nbresult->jsonpayload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                \local_customerintel\services\log_service::debug($runid, "Error JSON validation: Valid ✅");
            } else {
                \local_customerintel\services\log_service::error($runid, "Error JSON validation FAILED: " . json_last_error_msg());
            }
            
            try {
                \local_customerintel\services\log_service::debug($runid, "Attempting error record database insert...");
                $insert_id = $this->instrumented_db_insert('local_ci_nb_result', $nbresult, $runid);
                \local_customerintel\services\log_service::debug($runid, "Error record insertion succeeded: ID={$insert_id}");
                \local_customerintel\services\log_service::debug($runid, "NB {$nbcode} error saved to local_ci_nb_result successfully (ID: {$insert_id})");
                
            } catch (\Exception $e) {
                // ===== COMPREHENSIVE ERROR DIAGNOSTICS FOR ERROR RECORD =====
                \local_customerintel\services\log_service::error($runid, "Database insert failed for NB-{$nbcode} ERROR record");
                \local_customerintel\services\log_service::error($runid, "Exception type: " . get_class($e));
                \local_customerintel\services\log_service::error($runid, "Exception message: " . $e->getMessage());
                
                // Check for specific DML exception types
                if ($e instanceof \dml_write_exception) {
                    \local_customerintel\services\log_service::error($runid, "DML Write Exception detected (error record)");
                } else if ($e instanceof \dml_exception) {
                    \local_customerintel\services\log_service::error($runid, "General DML Exception detected (error record)");
                }
                
                // Get database last error
                $last_error = '';
                try {
                    if (method_exists($DB, 'get_last_error')) {
                        $last_error = $DB->get_last_error();
                    }
                } catch (\Exception $db_err) {
                    $last_error = 'Could not retrieve: ' . $db_err->getMessage();
                }
                \local_customerintel\services\log_service::error($runid, "Last DB error (error record): " . ($last_error ?: 'None available'));
                
                // Log payload size again for error context
                $payload_size = strlen($nbresult->jsonpayload);
                $payload_size_mb = round($payload_size / (1024 * 1024), 2);
                \local_customerintel\services\log_service::error($runid, "Error record payload size: {$payload_size_mb} MB");
                
                // Field count and validation
                $field_count = count(get_object_vars($nbresult));
                \local_customerintel\services\log_service::error($runid, "Error record field count: {$field_count}");
                
                // Re-run field validation in error context
                $field_validation = $this->validate_nb_record_fields($nbresult, $runid);
                if (!$field_validation['valid']) {
                    \local_customerintel\services\log_service::error($runid, "Error record field mismatches: " . implode('; ', $field_validation['errors']));
                }
                
                // Log raw record content for debugging (truncated)
                $record_json = json_encode($nbresult, JSON_PRETTY_PRINT);
                if (strlen($record_json) > 2000) {
                    $record_json = substr($record_json, 0, 2000) . '... [truncated]';
                }
                \local_customerintel\services\log_service::error($runid, "Error record raw content: " . $record_json);
                
                // Log table and operation details
                \local_customerintel\services\log_service::error($runid, "Table: local_ci_nb_result, Operation: insert_record (error)");
                \local_customerintel\services\log_service::error($runid, "Run ID: {$runid}, NB Code: {$nbcode}");
                
                throw $e;
            }
        }
    }
    
    /**
     * Update run metrics
     * 
     * @param int $runid Run ID
     * @return void
     */
    public function update_run_metrics(int $runid): void {
        global $DB;
        
        // Calculate total tokens and cost
        $sql = "SELECT SUM(metricvaluenum) as total 
                FROM {local_ci_telemetry} 
                WHERE runid = :runid 
                AND metrickey LIKE '%_tokens'";
        $totaltokens = $DB->get_field_sql($sql, ['runid' => $runid]);
        
        $sql = "SELECT SUM(metricvaluenum) as total 
                FROM {local_ci_telemetry} 
                WHERE runid = :runid 
                AND metrickey LIKE '%_cost'";
        $totalcost = $DB->get_field_sql($sql, ['runid' => $runid]);
        
        // Update run record
        $this->instrumented_db_set_field('local_ci_run', 'actualtokens', $totaltokens ?: 0, ['id' => $runid], $runid);
        $this->instrumented_db_set_field('local_ci_run', 'actualcost', $totalcost ?: 0, ['id' => $runid], $runid);
    }
    
    /**
     * Get all NB results for a run
     * 
     * @param int $runid Run ID
     * @return array NB results indexed by code
     * @throws \dml_exception
     */
    public function get_run_results(int $runid): array {
        global $DB;
        
        $results = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode');
        
        $formatted = [];
        foreach ($results as $result) {
            $formatted[$result->nbcode] = [
                'payload' => json_decode($result->jsonpayload, true),
                'citations' => json_decode($result->citations, true),
                'duration_ms' => $result->durationms,
                'tokens_used' => $result->tokensused,
                'status' => $result->status
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Instrumented database insert with comprehensive logging
     * 
     * @param string $table Table name
     * @param \stdClass $record Record to insert
     * @param int $runid Run ID for logging context
     * @return int Insert ID
     * @throws \Exception
     */
    protected function instrumented_db_insert(string $table, \stdClass $record, int $runid = 0): int {
        global $DB;
        
        // Pre-operation logging
        $record_keys = array_keys(get_object_vars($record));
        $key_count = count($record_keys);
        $record_json = json_encode($record, JSON_UNESCAPED_SLASHES);
        $record_preview = strlen($record_json) > 200 ? substr($record_json, 0, 200) . '...' : $record_json;
        
        \local_customerintel\services\log_service::debug($runid, "DB Operation Starting: insert_record({$table})");
        \local_customerintel\services\log_service::debug($runid, "Record key count: {$key_count}, Keys: " . implode(', ', $record_keys));
        \local_customerintel\services\log_service::debug($runid, "Record preview: {$record_preview}");
        
        try {
            $insert_id = $DB->insert_record($table, $record);
            
            // Success logging
            \local_customerintel\services\log_service::info($runid, "DB Operation Succeeded: insert_record({$table}, id={$insert_id})");
            
            return $insert_id;
            
        } catch (\Exception $e) {
            // Comprehensive error logging
            \local_customerintel\services\log_service::error($runid, "DB Operation Failed: insert_record({$table})");
            \local_customerintel\services\log_service::error($runid, "Exception type: " . get_class($e));
            \local_customerintel\services\log_service::error($runid, "Exception message: " . $e->getMessage());
            \local_customerintel\services\log_service::error($runid, "Run ID: {$runid}");
            \local_customerintel\services\log_service::error($runid, "Full record: " . print_r($record, true));
            
            // Get database last error
            $last_error = '';
            try {
                if (method_exists($DB, 'get_last_error')) {
                    $last_error = $DB->get_last_error();
                }
            } catch (\Exception $db_err) {
                $last_error = 'Could not retrieve: ' . $db_err->getMessage();
            }
            \local_customerintel\services\log_service::error($runid, "DB last error: " . ($last_error ?: 'None available'));
            
            throw $e;
        }
    }
    
    /**
     * Instrumented database update with comprehensive logging
     * 
     * @param string $table Table name
     * @param \stdClass $record Record to update
     * @param int $runid Run ID for logging context
     * @return bool Success
     * @throws \Exception
     */
    protected function instrumented_db_update(string $table, \stdClass $record, int $runid = 0): bool {
        global $DB;
        
        // Pre-operation logging
        $record_keys = array_keys(get_object_vars($record));
        $key_count = count($record_keys);
        $record_json = json_encode($record, JSON_UNESCAPED_SLASHES);
        $record_preview = strlen($record_json) > 200 ? substr($record_json, 0, 200) . '...' : $record_json;
        $record_id = isset($record->id) ? $record->id : 'unknown';
        
        \local_customerintel\services\log_service::debug($runid, "DB Operation Starting: update_record({$table}, id={$record_id})");
        \local_customerintel\services\log_service::debug($runid, "Record key count: {$key_count}, Keys: " . implode(', ', $record_keys));
        \local_customerintel\services\log_service::debug($runid, "Record preview: {$record_preview}");
        
        try {
            $result = $DB->update_record($table, $record);
            
            // Success logging
            \local_customerintel\services\log_service::info($runid, "DB Operation Succeeded: update_record({$table}, id={$record_id})");
            
            return $result;
            
        } catch (\Exception $e) {
            // Comprehensive error logging
            \local_customerintel\services\log_service::error($runid, "DB Operation Failed: update_record({$table}, id={$record_id})");
            \local_customerintel\services\log_service::error($runid, "Exception type: " . get_class($e));
            \local_customerintel\services\log_service::error($runid, "Exception message: " . $e->getMessage());
            \local_customerintel\services\log_service::error($runid, "Run ID: {$runid}");
            \local_customerintel\services\log_service::error($runid, "Full record: " . print_r($record, true));
            
            // Get database last error
            $last_error = '';
            try {
                if (method_exists($DB, 'get_last_error')) {
                    $last_error = $DB->get_last_error();
                }
            } catch (\Exception $db_err) {
                $last_error = 'Could not retrieve: ' . $db_err->getMessage();
            }
            \local_customerintel\services\log_service::error($runid, "DB last error: " . ($last_error ?: 'None available'));
            
            throw $e;
        }
    }
    
    /**
     * Instrumented database set_field with comprehensive logging
     * 
     * @param string $table Table name
     * @param string $field Field name
     * @param mixed $value Field value
     * @param array $conditions Where conditions
     * @param int $runid Run ID for logging context
     * @return bool Success
     * @throws \Exception
     */
    protected function instrumented_db_set_field(string $table, string $field, $value, array $conditions, int $runid = 0): bool {
        global $DB;
        
        // Pre-operation logging
        $conditions_str = json_encode($conditions);
        $value_str = is_scalar($value) ? (string)$value : json_encode($value);
        
        \local_customerintel\services\log_service::debug($runid, "DB Operation Starting: set_field({$table}.{$field})");
        \local_customerintel\services\log_service::debug($runid, "Setting {$field} = {$value_str} where " . $conditions_str);
        
        try {
            $result = $DB->set_field($table, $field, $value, $conditions);
            
            // Success logging
            \local_customerintel\services\log_service::info($runid, "DB Operation Succeeded: set_field({$table}.{$field})");
            
            return $result;
            
        } catch (\Exception $e) {
            // Comprehensive error logging
            \local_customerintel\services\log_service::error($runid, "DB Operation Failed: set_field({$table}.{$field})");
            \local_customerintel\services\log_service::error($runid, "Exception type: " . get_class($e));
            \local_customerintel\services\log_service::error($runid, "Exception message: " . $e->getMessage());
            \local_customerintel\services\log_service::error($runid, "Run ID: {$runid}");
            \local_customerintel\services\log_service::error($runid, "Field: {$field}, Value: {$value_str}, Conditions: " . $conditions_str);
            
            // Get database last error
            $last_error = '';
            try {
                if (method_exists($DB, 'get_last_error')) {
                    $last_error = $DB->get_last_error();
                }
            } catch (\Exception $db_err) {
                $last_error = 'Could not retrieve: ' . $db_err->getMessage();
            }
            \local_customerintel\services\log_service::error($runid, "DB last error: " . ($last_error ?: 'None available'));
            
            throw $e;
        }
    }
    
    /**
     * Safe error logging that handles missing log_service
     * 
     * @param int $runid Run ID
     * @param string $message Error message
     * @return void
     */
    protected function safe_log_error(int $runid, string $message): void {
        try {
            if (class_exists('\\local_customerintel\\services\\log_service')) {
                \local_customerintel\services\log_service::error($runid, $message);
            } else {
                debugging("NB Orchestrator [{$runid}]: {$message}", DEBUG_DEVELOPER);
            }
        } catch (\Exception $e) {
            debugging("NB Orchestrator [{$runid}]: {$message} (Logging failed: {$e->getMessage()})", DEBUG_DEVELOPER);
        }
    }
    
    /**
     * Safe debug logging that handles missing log_service
     * 
     * @param int $runid Run ID
     * @param string $message Debug message
     * @return void
     */
    protected function safe_log_debug(int $runid, string $message): void {
        try {
            if (class_exists('\\local_customerintel\\services\\log_service')) {
                \local_customerintel\services\log_service::debug($runid, $message);
            } else {
                debugging("NB Orchestrator [{$runid}]: {$message}", DEBUG_DEVELOPER);
            }
        } catch (\Exception $e) {
            debugging("NB Orchestrator [{$runid}]: {$message} (Debug logging failed: {$e->getMessage()})", DEBUG_DEVELOPER);
        }
    }
    
    /**
     * Validate that a runid exists in the local_ci_run table
     * 
     * @param int $runid Run ID to validate
     * @return bool True if valid, false otherwise
     */
    protected function validate_runid(int $runid): bool {
        global $DB;
        
        try {
            return $DB->record_exists('local_ci_run', ['id' => $runid]);
        } catch (\Exception $e) {
            debugging("Failed to validate runid {$runid}: " . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }
    
    /**
     * Validate that an nbcode is in the correct format
     * 
     * @param string $nbcode NB code to validate
     * @return bool True if valid, false otherwise
     */
    protected function validate_nbcode(string $nbcode): bool {
        // Must be NB-1 through NB-15
        $valid_codes = [];
        for ($i = 1; $i <= 15; $i++) {
            $valid_codes[] = "NB-{$i}";
        }
        
        return in_array($nbcode, $valid_codes, true);
    }
    
    /**
     * Validate record fields against actual database table structure
     * 
     * @param \stdClass $record Record to validate
     * @param int $runid Run ID for logging (optional)
     * @return array Array with 'valid' boolean and 'errors' array
     */
    protected function validate_nb_record_fields(\stdClass $record, int $runid = 0): array {
        global $DB;
        
        $errors = [];
        $valid = true;
        
        try {
            // Get actual database columns
            $db_columns = $DB->get_columns('local_ci_nb_result');
            $db_field_names = array_keys($db_columns);
            
            // Get record fields
            $record_fields = array_keys(get_object_vars($record));
            
            // Check for missing fields (in DB but not in record)
            $missing_fields = array_diff($db_field_names, $record_fields);
            if (!empty($missing_fields)) {
                // Filter out 'id' since it's auto-increment
                $missing_fields = array_filter($missing_fields, function($field) {
                    return $field !== 'id';
                });
                if (!empty($missing_fields)) {
                    $errors[] = "Missing database fields: " . implode(', ', $missing_fields);
                    $valid = false;
                }
            }
            
            // Check for extra fields (in record but not in DB)
            $extra_fields = array_diff($record_fields, $db_field_names);
            if (!empty($extra_fields)) {
                $errors[] = "Extra fields not in database: " . implode(', ', $extra_fields);
                $valid = false;
            }
            
            // Log field comparison for debugging
            $this->safe_log_debug(0, "Database fields: " . implode(', ', $db_field_names));
            $this->safe_log_debug(0, "Record fields: " . implode(', ', $record_fields));
            
        } catch (\Exception $e) {
            $errors[] = "Failed to validate fields against database: " . $e->getMessage();
            $valid = false;
        }
        
        return [
            'valid' => $valid,
            'errors' => $errors
        ];
    }
    
    /**
     * Validate all required fields in an NB result record
     * 
     * @param \stdClass $record Record to validate
     * @return array Array of validation errors (empty if valid)
     */
    protected function validate_nb_record(\stdClass $record): array {
        $errors = [];
        
        // Check required fields exist
        $required_fields = ['runid', 'nbcode', 'jsonpayload', 'citations', 'status', 'timecreated', 'timemodified'];
        foreach ($required_fields as $field) {
            if (!property_exists($record, $field)) {
                $errors[] = "Missing required field: {$field}";
            } else if ($record->{$field} === null) {
                $errors[] = "Required field is null: {$field}";
            }
        }
        
        // Validate specific field types and values
        if (property_exists($record, 'runid') && (!is_int($record->runid) || $record->runid <= 0)) {
            $errors[] = "Invalid runid: must be positive integer, got " . gettype($record->runid) . " ({$record->runid})";
        }
        
        if (property_exists($record, 'nbcode') && !$this->validate_nbcode($record->nbcode)) {
            $errors[] = "Invalid nbcode: '{$record->nbcode}' must be NB-1 through NB-15";
        }
        
        if (property_exists($record, 'status') && !in_array($record->status, ['pending', 'running', 'completed', 'failed'], true)) {
            $errors[] = "Invalid status: '{$record->status}' must be one of: pending, running, completed, failed";
        }
        
        if (property_exists($record, 'jsonpayload') && !is_string($record->jsonpayload)) {
            $errors[] = "Invalid jsonpayload: must be string, got " . gettype($record->jsonpayload);
        }
        
        if (property_exists($record, 'citations') && !is_string($record->citations)) {
            $errors[] = "Invalid citations: must be string, got " . gettype($record->citations);
        }
        
        if (property_exists($record, 'durationms') && (!is_int($record->durationms) && !is_null($record->durationms))) {
            $errors[] = "Invalid durationms: must be integer or null, got " . gettype($record->durationms);
        }
        
        if (property_exists($record, 'tokensused') && (!is_int($record->tokensused) && !is_null($record->tokensused))) {
            $errors[] = "Invalid tokensused: must be integer or null, got " . gettype($record->tokensused);
        }
        
        if (property_exists($record, 'timecreated') && (!is_int($record->timecreated) || $record->timecreated <= 0)) {
            $errors[] = "Invalid timecreated: must be positive timestamp, got " . gettype($record->timecreated) . " ({$record->timecreated})";
        }
        
        if (property_exists($record, 'timemodified') && (!is_int($record->timemodified) || $record->timemodified <= 0)) {
            $errors[] = "Invalid timemodified: must be positive timestamp, got " . gettype($record->timemodified) . " ({$record->timemodified})";
        }
        
        return $errors;
    }
    
    /**
     * Execute full Research Protocol v15 with real API calls
     * 
     * @param \stdClass $run Run object
     * @return bool Success
     * @throws \moodle_exception
     */
    public function execute_full_protocol($run): bool {
        global $DB;
        
        try {
            // Log start of execution
            \local_customerintel\services\log_service::info($run->id, "Research Protocol v15 started for run {$run->id}");
            
            // Validate API keys using config helper
            config_helper::log_api_key_status('orchestrator_startup');
            
            if (!config_helper::has_openai_api_key() || !config_helper::has_perplexity_api_key()) {
                $status = config_helper::get_api_key_status();
                \local_customerintel\services\log_service::error($run->id, 
                    "Missing API keys - OpenAI: " . ($status['openai']['configured'] ? 'present' : 'missing') . 
                    ", Perplexity: " . ($status['perplexity']['configured'] ? 'present' : 'missing'));
                throw new \moodle_exception('missingllmapikeys', 'local_customerintel');
            }
            
            // Log successful API key validation
            \local_customerintel\services\log_service::info($run->id, "API keys validated - both OpenAI and Perplexity configured");
            
            // Get API keys for function calls
            $openaiapikey = config_helper::get_openai_api_key();
            $perplexityapikey = config_helper::get_perplexity_api_key();
            
            // Ensure API keys are strings (not null)
            $openaiapikey = (string)$openaiapikey;
            $perplexityapikey = (string)$perplexityapikey;
            
            // Validate company ID
            $companyid = isset($run->companyid) ? (int)$run->companyid : null;
            if (empty($companyid)) {
                \local_customerintel\services\log_service::error($run->id, "Invalid run: missing company ID");
                throw new \moodle_exception('Missing company ID for run execution.');
            }
            
            // Get company data
            $company = $DB->get_record('local_ci_company', ['id' => $companyid], '*', MUST_EXIST);
            \local_customerintel\services\log_service::info($run->id, "Executing protocol for company: {$company->name}");
            
            // Get target company data for dual-entity analysis
            $targetcompany = null;
            if (!empty($run->targetcompanyid)) {
                $targetcompany = $DB->get_record('local_ci_company', ['id' => $run->targetcompanyid]);
                if ($targetcompany) {
                    \local_customerintel\services\log_service::info($run->id, "DUAL-ENTITY: Target company detected: {$targetcompany->name}");
                } else {
                    \local_customerintel\services\log_service::warning($run->id, "Target company ID {$run->targetcompanyid} not found, proceeding with single-entity analysis");
                }
            }
            
            // Update run status
            $this->instrumented_db_set_field('local_ci_run', 'status', 'running', ['id' => $run->id], $run->id);
            $this->instrumented_db_set_field('local_ci_run', 'timestarted', time(), ['id' => $run->id], $run->id);
            
            // Execute all 15 NBs sequentially
            $failed_nbs = 0;
            $completed_nbs = 0;
            $start_time = microtime(true);
            
            foreach (array_keys(self::NB_DEFINITIONS) as $nbcode) {
                try {
                    \local_customerintel\services\log_service::info($run->id, "Starting {$nbcode}: " . self::NB_DEFINITIONS[$nbcode]['objective']);
                    
                    // Debug logging for API key parameters
                    \local_customerintel\services\log_service::info($run->id, 
                        "{$nbcode}: API key lengths - OpenAI: " . strlen($openaiapikey) . ", Perplexity: " . strlen($perplexityapikey));
                    
                    $result = $this->execute_nb_with_real_apis($run->id, $nbcode, $company, $openaiapikey, $perplexityapikey, $targetcompany);
                    
                    // ===== INSTRUMENTATION: BEFORE save_nb_result() =====
                    $result_is_empty = empty($result);
                    $result_is_null = is_null($result);
                    $payload_size = 0;
                    
                    if (!$result_is_null && !$result_is_empty) {
                        $result_json = json_encode($result);
                        $payload_size = strlen($result_json);
                    } else {
                        $result_json = 'NULL or EMPTY';
                    }
                    
                    \local_customerintel\services\log_service::debug($run->id, "Attempting to save {$nbcode} result (payload size: {$payload_size} bytes)");
                    \local_customerintel\services\log_service::debug($run->id, "Result is empty: " . ($result_is_empty ? 'YES' : 'NO') . ", Result is null: " . ($result_is_null ? 'YES' : 'NO'));
                    
                    if ($payload_size > 0) {
                        \local_customerintel\services\log_service::debug($run->id, "Result structure preview: " . substr($result_json, 0, 300) . '...');
                    }
                    
                    try {
                        $record_id = $this->save_nb_result($run->id, $nbcode, $result);
                        
                        // ===== INSTRUMENTATION: AFTER save_nb_result() SUCCESS =====
                        \local_customerintel\services\log_service::info($run->id, "{$nbcode} result save completed successfully (record ID: {$record_id})");
                        
                    } catch (\Exception $save_exception) {
                        // ===== INSTRUMENTATION: save_nb_result() EXCEPTION =====
                        \local_customerintel\services\log_service::error($run->id, "{$nbcode} save failed: " . get_class($save_exception) . " (" . $save_exception->getMessage() . ")");
                        \local_customerintel\services\log_service::error($run->id, "Failed save - nbcode: {$nbcode}, run_id: {$run->id}");
                        \local_customerintel\services\log_service::error($run->id, "Failed save - result structure: " . print_r($result, true));
                        
                        // Re-throw to trigger the outer catch block
                        throw $save_exception;
                    }
                    
                    $completed_nbs++;
                    \local_customerintel\services\log_service::info($run->id, "Completed {$nbcode} successfully");
                    
                } catch (\Exception $e) {
                    $failed_nbs++;
                    \local_customerintel\services\log_service::error($run->id, "Failed {$nbcode}: " . $e->getMessage());
                    
                    // ===== INSTRUMENTATION: BEFORE save_nb_error() =====
                    $error_message = $e->getMessage();
                    $error_size = strlen($error_message);
                    
                    \local_customerintel\services\log_service::debug($run->id, "Attempting to save {$nbcode} ERROR (error size: {$error_size} bytes)");
                    \local_customerintel\services\log_service::debug($run->id, "Error message preview: " . substr($error_message, 0, 200) . '...');
                    
                    try {
                        $this->save_nb_error($run->id, $nbcode, $error_message);
                        
                        // ===== INSTRUMENTATION: AFTER save_nb_error() SUCCESS =====
                        \local_customerintel\services\log_service::info($run->id, "{$nbcode} error save completed successfully");
                        
                    } catch (\Exception $error_save_exception) {
                        // ===== INSTRUMENTATION: save_nb_error() EXCEPTION =====
                        \local_customerintel\services\log_service::error($run->id, "{$nbcode} ERROR save failed: " . get_class($error_save_exception) . " (" . $error_save_exception->getMessage() . ")");
                        \local_customerintel\services\log_service::error($run->id, "Failed error save - nbcode: {$nbcode}, run_id: {$run->id}");
                        \local_customerintel\services\log_service::error($run->id, "Failed error save - original error: {$error_message}");
                        \local_customerintel\services\log_service::error($run->id, "Failed error save - exception: " . print_r($error_save_exception, true));
                        
                        // Don't re-throw here - we want to continue with other NBs even if error saving fails
                    }
                    
                    // Stop if more than 3 NBs fail
                    if ($failed_nbs > 3) {
                        \local_customerintel\services\log_service::error($run->id, "Too many NB failures ({$failed_nbs}), aborting protocol");
                        break;
                    }
                }
            }
            
            // Calculate success rate
            $total_nbs = count(self::NB_DEFINITIONS);
            $success_rate = ($completed_nbs / $total_nbs) * 100;
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            // Update run metrics
            $this->update_run_metrics($run->id);
            
            // Determine final status
            $final_status = ($success_rate >= 80) ? 'completed' : 'failed';
            $this->instrumented_db_set_field('local_ci_run', 'status', $final_status, ['id' => $run->id], $run->id);
            $this->instrumented_db_set_field('local_ci_run', 'timecompleted', time(), ['id' => $run->id], $run->id);
            
            \local_customerintel\services\log_service::info($run->id, "Protocol execution finished: {$completed_nbs}/{$total_nbs} NBs completed (" . round($success_rate, 1) . "% success rate)");
            
            // CITATION DOMAIN NORMALIZATION STEP
            // Run after NB orchestration completes but before report assembly
            if ($final_status === 'completed') {
                try {
                    $this->normalize_citation_domains($run->id);
                } catch (\Exception $e) {
                    // Log error but don't fail the run - diversity calculations can proceed with URLs
                    debugging("Citation domain normalization failed for run {$run->id}: " . $e->getMessage(), DEBUG_DEVELOPER);
                    \local_customerintel\services\log_service::error($run->id, "Citation domain normalization failed: " . $e->getMessage());
                }
            }
            
            // Assemble report if successful
            if ($final_status === 'completed') {
                try {
                    $assembler = new \local_customerintel\services\assembler();
                    $assembler->assemble_report($run->id);
                    \local_customerintel\services\log_service::info($run->id, "Report assembled successfully for run {$run->id}");
                } catch (\Exception $e) {
                    \local_customerintel\services\log_service::error($run->id, "Report assembly failed: " . $e->getMessage());
                }
            }
            
            return ($final_status === 'completed');
            
        } catch (\Exception $e) {
            \local_customerintel\services\log_service::error($run->id, "Protocol execution failed: " . $e->getMessage());
            $this->instrumented_db_set_field('local_ci_run', 'status', 'failed', ['id' => $run->id], $run->id);
            throw new \moodle_exception('protocolexecutionfailed', 'local_customerintel', '', null, $e->getMessage());
        }
    }
    
    /**
     * Citation Domain Normalization Step
     * 
     * Processes NB orchestration results to extract and normalize domain fields
     * from citation URLs. Runs after NB orchestration completes but before
     * retrieval rebalancing to ensure domain-based diversity calculations work.
     * 
     * Objectives:
     * 1. Parse every citation URL and extract its domain (e.g., viivhealthcare.com)
     * 2. Reshape citation entries to contain both full URL and domain name
     * 3. Leave citations already in object form with domain field unchanged
     * 4. Write normalized output to normalized_inputs_v16.json artifact
     * 5. Log summary: total citations, unique domains, top domains by frequency
     * 
     * @param int $runid Run ID to process
     * @throws \Exception If critical normalization errors occur
     */
    protected function normalize_citation_domains(int $runid): void {
        global $DB, $CFG;
        
        $start_time = microtime(true);
        
        // Log start of normalization
        \local_customerintel\services\log_service::info($runid, "Starting citation domain normalization for run {$runid}");
        
        // Collect all NB results for this run
        $nb_results = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode ASC');
        
        if (empty($nb_results)) {
            \local_customerintel\services\log_service::warning($runid, "No NB results found for domain normalization");
            return;
        }
        
        $total_citations = 0;
        $normalized_citations = [];
        $domain_frequency = [];
        $normalization_stats = [
            'citations_processed' => 0,
            'citations_normalized' => 0,
            'citations_already_normalized' => 0,
            'malformed_urls' => 0,
            'missing_urls' => 0
        ];
        
        // Process each NB result
        foreach ($nb_results as $nb_result) {
            // Enhanced citation extraction from both payload and citations columns
            $citations = $this->extract_all_citations_from_nb_result($nb_result);
            
            \local_customerintel\services\log_service::debug($runid, 
                "NB {$nb_result->nbcode}: Found " . count($citations) . " citations for normalization");
            
            foreach ($citations as $citation) {
                $total_citations++;
                $normalization_stats['citations_processed']++;
                
                // Normalize the citation
                $normalized_citation = $this->normalize_single_citation($citation, $normalization_stats);
                
                if ($normalized_citation) {
                    $normalized_citations[] = $normalized_citation;
                    
                    // Track domain frequency
                    if (isset($normalized_citation['domain'])) {
                        $domain = $normalized_citation['domain'];
                        $domain_frequency[$domain] = ($domain_frequency[$domain] ?? 0) + 1;
                    }
                }
            }
        }
        
        // Calculate diversity metrics
        $unique_domains = count($domain_frequency);
        $diversity_score = $this->calculate_preliminary_diversity_score($domain_frequency, $total_citations);
        
        // Sort domains by frequency for top domains list
        arsort($domain_frequency);
        $top_domains = array_slice($domain_frequency, 0, 5, true);
        
        // Create normalized inputs artifact
        $normalized_data = [
            'metadata' => [
                'runid' => $runid,
                'normalization_timestamp' => date('c'),
                'version' => '16.0',
                'processing_time_ms' => round((microtime(true) - $start_time) * 1000, 2)
            ],
            'summary' => [
                'total_citations_processed' => $total_citations,
                'unique_domains_found' => $unique_domains,
                'diversity_score_preliminary' => round($diversity_score, 3),
                'top_domains' => $top_domains,
                'normalization_stats' => $normalization_stats
            ],
            'normalized_citations' => $normalized_citations,
            'domain_frequency_map' => $domain_frequency
        ];
        
        // Save to artifact repository
        $this->save_normalization_artifact($runid, $normalized_data);
        
        // Log comprehensive summary
        $top_domains_text = [];
        foreach ($top_domains as $domain => $count) {
            $percentage = round(($count / $total_citations) * 100, 1);
            $top_domains_text[] = "{$domain} ({$count} citations, {$percentage}%)";
        }
        
        $summary_message = sprintf(
            "Citation normalization completed: %d citations processed, %d unique domains found, diversity score ~%.2f. Top domains: %s",
            $total_citations,
            $unique_domains,
            $diversity_score,
            implode(', ', array_slice($top_domains_text, 0, 3))
        );
        
        \local_customerintel\services\log_service::info($runid, $summary_message);
        
        // Console output for immediate feedback
        mtrace("✅ Citation Domain Normalization Complete:");
        mtrace("   📊 {$total_citations} citations processed");
        mtrace("   🌐 {$unique_domains} unique domains found");
        mtrace("   📈 Diversity Score: " . round($diversity_score, 2));
        mtrace("   🏆 Top domains: " . implode(', ', array_keys(array_slice($top_domains, 0, 3))));
        
        if ($normalization_stats['malformed_urls'] > 0) {
            mtrace("   ⚠️  {$normalization_stats['malformed_urls']} malformed URLs handled gracefully");
        }
    }
    
    /**
     * Extract citations from NB payload data
     * 
     * @param array $payload NB result payload
     * @return array Array of citation objects/strings
     */
    private function extract_citations_from_payload(array $payload): array {
        $citations = [];
        
        // Check for citations in various payload structures
        if (isset($payload['citations']) && is_array($payload['citations'])) {
            $citations = array_merge($citations, $payload['citations']);
        }
        
        // Check for citations in sections
        if (isset($payload['sections']) && is_array($payload['sections'])) {
            foreach ($payload['sections'] as $section) {
                if (isset($section['citations']) && is_array($section['citations'])) {
                    $citations = array_merge($citations, $section['citations']);
                }
            }
        }
        
        // Check for direct citation arrays in payload root
        if (isset($payload['sources']) && is_array($payload['sources'])) {
            $citations = array_merge($citations, $payload['sources']);
        }
        
        return $citations;
    }
    
    /**
     * Extract all citations from NB result - reads both payload and citations columns
     * 
     * @param object $nb_result NB result record with jsonpayload and citations fields
     * @return array Array of unique citations
     */
    private function extract_all_citations_from_nb_result($nb_result): array {
        $all_citations = [];
        $seen_urls = [];
        
        // 1. Extract from jsonpayload (existing logic)
        if (!empty($nb_result->jsonpayload)) {
            $payload = json_decode($nb_result->jsonpayload, true);
            if ($payload) {
                $payload_citations = $this->extract_citations_from_payload($payload);
                foreach ($payload_citations as $citation) {
                    $url = $this->extract_url_from_citation($citation);
                    if (!empty($url) && !in_array($url, $seen_urls)) {
                        $all_citations[] = $citation;
                        $seen_urls[] = $url;
                    }
                }
            }
        }
        
        // 2. Extract from dedicated citations column (NEW FIX)
        if (!empty($nb_result->citations)) {
            $citations_data = json_decode($nb_result->citations, true);
            if (is_array($citations_data)) {
                foreach ($citations_data as $citation) {
                    $url = $this->extract_url_from_citation($citation);
                    if (!empty($url) && !in_array($url, $seen_urls)) {
                        $all_citations[] = $citation;
                        $seen_urls[] = $url;
                    }
                }
            }
        }
        
        return $all_citations;
    }
    
    /**
     * Extract URL from citation regardless of format
     * 
     * @param mixed $citation Citation data (string URL or object)
     * @return string URL or empty string
     */
    private function extract_url_from_citation($citation): string {
        if (is_string($citation)) {
            return $citation;
        } elseif (is_array($citation) && isset($citation['url'])) {
            return $citation['url'];
        }
        return '';
    }
    
    /**
     * Normalize a single citation to include domain field
     * 
     * @param mixed $citation Citation data (string URL or object)
     * @param array &$stats Statistics tracking array (passed by reference)
     * @return array|null Normalized citation or null if unusable
     */
    private function normalize_single_citation($citation, array &$stats): ?array {
        // Handle different citation formats
        if (is_string($citation)) {
            // Citation is just a URL string
            if (empty($citation) || !$this->is_valid_url($citation)) {
                $stats['malformed_urls']++;
                return null;
            }
            
            $domain = $this->extract_domain_from_url($citation);
            if (!$domain) {
                $stats['malformed_urls']++;
                return null;
            }
            
            $stats['citations_normalized']++;
            return [
                'url' => $citation,
                'domain' => $domain,
                'title' => '', // Will be filled by downstream processes
                'normalized_by' => 'nb_orchestrator_v16'
            ];
            
        } else if (is_array($citation) || is_object($citation)) {
            // Citation is an object/array
            $citation_array = is_object($citation) ? (array)$citation : $citation;
            
            // If domain already exists, leave unchanged
            if (isset($citation_array['domain']) && !empty($citation_array['domain'])) {
                $stats['citations_already_normalized']++;
                return $citation_array;
            }
            
            // Extract URL from object
            $url = $citation_array['url'] ?? $citation_array['link'] ?? $citation_array['source'] ?? null;
            
            if (empty($url)) {
                $stats['missing_urls']++;
                return $citation_array; // Return as-is, might have other useful data
            }
            
            if (!$this->is_valid_url($url)) {
                $stats['malformed_urls']++;
                return $citation_array; // Return as-is with original data
            }
            
            $domain = $this->extract_domain_from_url($url);
            if ($domain) {
                $citation_array['domain'] = $domain;
                $citation_array['normalized_by'] = 'nb_orchestrator_v16';
                $stats['citations_normalized']++;
            }
            
            return $citation_array;
        }
        
        // Unknown citation format
        $stats['malformed_urls']++;
        return null;
    }
    
    /**
     * Extract domain from URL with robust parsing
     * 
     * @param string $url URL to parse
     * @return string|null Domain name or null if extraction fails
     */
    private function extract_domain_from_url(string $url): ?string {
        // Clean the URL
        $url = trim($url);
        
        // Add protocol if missing
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url;
        }
        
        // Parse URL
        $parsed = parse_url($url);
        
        if (!isset($parsed['host'])) {
            return null;
        }
        
        $host = strtolower($parsed['host']);
        
        // Remove www. prefix
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }
        
        // Basic validation
        if (empty($host) || strpos($host, '.') === false) {
            return null;
        }
        
        return $host;
    }
    
    /**
     * Basic URL validation
     * 
     * @param string $url URL to validate
     * @return bool True if URL appears valid
     */
    private function is_valid_url(string $url): bool {
        if (empty($url)) {
            return false;
        }
        
        // Add protocol if missing for validation
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url;
        }
        
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Calculate preliminary diversity score using Shannon entropy
     * 
     * @param array $domain_frequency Domain frequency map
     * @param int $total_citations Total number of citations
     * @return float Diversity score between 0.0 and 1.0
     */
    private function calculate_preliminary_diversity_score(array $domain_frequency, int $total_citations): float {
        if ($total_citations === 0 || empty($domain_frequency)) {
            return 0.0;
        }
        
        // Calculate Shannon entropy
        $entropy = 0.0;
        foreach ($domain_frequency as $count) {
            $probability = $count / $total_citations;
            if ($probability > 0) {
                $entropy -= $probability * log($probability, 2);
            }
        }
        
        // Normalize to 0-1 scale (max entropy = log2(unique_domains))
        $max_entropy = log(count($domain_frequency), 2);
        
        return $max_entropy > 0 ? $entropy / $max_entropy : 0.0;
    }
    
    /**
     * Save normalization artifact to repository
     * 
     * @param int $runid Run ID
     * @param array $normalized_data Normalized citation data
     * @throws \Exception If artifact save fails
     */
    private function save_normalization_artifact(int $runid, array $normalized_data): void {
        global $CFG;
        
        try {
            // Save to artifact repository if available
            require_once(__DIR__ . '/artifact_repository.php');
            $artifact_repo = new \local_customerintel\services\artifact_repository();
            
            $artifact_repo->save_artifact(
                $runid,
                'citation_normalization',
                'normalized_inputs_v16',
                $normalized_data
            );
            
            \local_customerintel\services\log_service::info($runid, "Normalization artifact saved to repository");
            
        } catch (\Exception $e) {
            // Fallback: save to file system
            $output_dir = $CFG->dataroot . '/data_trace/';
            if (!is_dir($output_dir)) {
                mkdir($output_dir, 0755, true);
            }
            
            $filename = "normalized_inputs_v16_run{$runid}.json";
            $filepath = $output_dir . $filename;
            
            if (file_put_contents($filepath, json_encode($normalized_data, JSON_PRETTY_PRINT)) === false) {
                throw new \Exception("Failed to save normalization artifact to {$filepath}");
            }
            
            \local_customerintel\services\log_service::info($runid, "Normalization artifact saved to {$filepath}");
        }
    }
}