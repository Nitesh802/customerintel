<?php
/**
 * Self-Check Validator - Quality assurance for synthesis output
 *
 * Automated validation against synthesis quality rules to ensure output
 * meets standards before publication.
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

/**
 * Self-Check Validator
 * 
 * Validates synthesis output against quality rules:
 * - No execution detail leakage (regex for verbs: email, schedule, sequence, etc.)
 * - No consultant-speak (keyword list)
 * - No speculative claims without adjacent grounded facts
 * - No plain "Source" lists — all citations must be enriched with title/domain
 * - No repetition across opportunity blueprints (Jaccard similarity threshold)
 */
class selfcheck_validator {

    /**
     * Run comprehensive self-check validation
     * 
     * @param array $sections Synthesis sections
     * @param array $enriched_citations Optional enriched citations
     * @return array {pass: bool, violations: array}
     */
    public function run_selfcheck(array $sections, array $enriched_citations = []): array {
        $violations = [];
        
        // Run all validation checks
        $violations = array_merge($violations, $this->check_execution_leak($sections));
        $violations = array_merge($violations, $this->check_consultant_speak($sections));
        $violations = array_merge($violations, $this->check_unsupported_claims($sections));
        $violations = array_merge($violations, $this->check_repetition($sections));
        $violations = array_merge($violations, $this->check_citation_quality($sections, $enriched_citations));
        
        $pass = empty($violations) || !$this->has_error_violations($violations);
        
        return [
            'pass' => $pass,
            'violations' => $violations
        ];
    }

    /**
     * Check for execution detail leakage
     */
    private function check_execution_leak(array $sections): array {
        $violations = [];
        $leak_patterns = [
            'email', 'schedule', 'cadence', 'outreach', 'step 1', 'step 2', 'step 3',
            'script', 'playbook', 'call the', 'DM', 'LinkedIn', 'first,', 'then,', 'finally',
            'weekly plan', 'sequence', 'reach out', 'contact them', 'send a', 'follow up'
        ];
        
        foreach ($sections as $section_name => $content) {
            if (!is_string($content)) continue;
            
            $content_lower = strtolower($content);
            foreach ($leak_patterns as $pattern) {
                if (strpos($content_lower, strtolower($pattern)) !== false) {
                    $violations[] = [
                        'rule' => 'execution_leak',
                        'location' => $section_name,
                        'message' => "Found execution detail: '{$pattern}' in {$section_name}",
                        'severity' => 'error',
                        'suggested_rewrite' => "Remove execution details and focus on strategic insights instead of tactical steps"
                    ];
                }
            }
        }
        
        return $violations;
    }

    /**
     * Check for consultant-speak
     */
    private function check_consultant_speak(array $sections): array {
        $violations = [];
        $consultant_terms = [
            'synergy', 'strategic alignment', 'roadmap', 'workstream', 'enablement',
            'leverage' => 'verb_context', 'optimize' => 'overused', 'low-hanging fruit',
            'best practices', 'paradigm shift', 'game changer', 'move the needle',
            'circle back', 'touch base', 'deep dive', 'drill down'
        ];
        
        foreach ($sections as $section_name => $content) {
            if (!is_string($content)) continue;
            
            $content_lower = strtolower($content);
            foreach ($consultant_terms as $term => $context) {
                $search_term = is_numeric($term) ? $context : $term;
                if (strpos($content_lower, strtolower($search_term)) !== false) {
                    $violations[] = [
                        'rule' => 'consultant_speak',
                        'location' => $section_name,
                        'message' => "Found consultant-speak: '{$search_term}' in {$section_name}",
                        'severity' => 'warn',
                        'suggested_rewrite' => "Replace with specific, concrete language that adds substantive value"
                    ];
                }
            }
        }
        
        return $violations;
    }

    /**
     * Check for unsupported claims
     */
    private function check_unsupported_claims(array $sections): array {
        $violations = [];
        
        foreach ($sections as $section_name => $content) {
            if (!is_string($content)) continue;
            
            $paragraphs = preg_split('/\n\s*\n/', $content);
            
            foreach ($paragraphs as $paragraph) {
                if (empty(trim($paragraph))) continue;
                
                // Check for strong assertion verbs
                $assertion_patterns = [
                    '/will drive/i', '/proves/i', '/dominates/i', '/guarantees/i',
                    '/ensures/i', '/definitely/i', '/certainly will/i'
                ];
                
                $has_strong_claim = false;
                foreach ($assertion_patterns as $pattern) {
                    if (preg_match($pattern, $paragraph)) {
                        $has_strong_claim = true;
                        break;
                    }
                }
                
                if ($has_strong_claim) {
                    // Check for supporting evidence (numbers, dates, or citations)
                    $has_evidence = preg_match('/\[\d+\]/', $paragraph) || // Citations
                                   preg_match('/\d+%/', $paragraph) ||      // Percentages
                                   preg_match('/\$\d+/', $paragraph) ||     // Dollar amounts
                                   preg_match('/\b\d{4}\b/', $paragraph) || // Years
                                   preg_match('/\b\d+\.\d+\b/', $paragraph); // Decimals
                    
                    if (!$has_evidence) {
                        $violations[] = [
                            'rule' => 'unsupported_claims',
                            'location' => $section_name,
                            'message' => "Strong assertion without supporting evidence in {$section_name}",
                            'severity' => 'error',
                            'suggested_rewrite' => "Add specific numbers, dates, or citations to support the claim"
                        ];
                    }
                }
            }
        }
        
        return $violations;
    }

    /**
     * Check for repetition using Jaccard similarity
     */
    private function check_repetition(array $sections): array {
        $violations = [];
        $threshold = 0.6;
        
        // Extract opportunity descriptions if available
        $opportunities = [];
        if (isset($sections['opportunities'])) {
            // Split opportunities by common delimiters
            $opp_text = $sections['opportunities'];
            $opp_parts = preg_split('/\n\s*[-*]\s*|\n\s*\d+\.\s*/', $opp_text);
            
            foreach ($opp_parts as $part) {
                $part = trim($part);
                if (strlen($part) > 50) { // Only check substantial content
                    $opportunities[] = $part;
                }
            }
        }
        
        // Compare opportunities for similarity
        for ($i = 0; $i < count($opportunities); $i++) {
            for ($j = $i + 1; $j < count($opportunities); $j++) {
                $similarity = $this->jaccard_similarity($opportunities[$i], $opportunities[$j]);
                if ($similarity > $threshold) {
                    $violations[] = [
                        'rule' => 'repetition',
                        'location' => 'opportunities',
                        'message' => "High similarity ({$similarity}) between opportunity {$i} and {$j}",
                        'severity' => 'warn',
                        'suggested_rewrite' => "Differentiate opportunities or merge similar ones"
                    ];
                }
            }
        }
        
        return $violations;
    }

    /**
     * Check citation quality
     */
    private function check_citation_quality(array $sections, array $enriched_citations): array {
        $violations = [];
        
        foreach ($sections as $section_name => $content) {
            if (!is_string($content)) continue;
            
            // Check for "Source" placeholders
            if (preg_match('/\bSource\s*\d*\b/', $content)) {
                $violations[] = [
                    'rule' => 'citation_quality',
                    'location' => $section_name,
                    'message' => "Found generic 'Source' placeholder in {$section_name}",
                    'severity' => 'error',
                    'suggested_rewrite' => "Replace with enriched citations including title and domain"
                ];
            }
            
            // Check for empty citation labels
            if (preg_match('/\[\s*\]/', $content)) {
                $violations[] = [
                    'rule' => 'citation_quality',
                    'location' => $section_name,
                    'message' => "Found empty citation brackets in {$section_name}",
                    'severity' => 'error',
                    'suggested_rewrite' => "Add proper citation numbers or remove empty brackets"
                ];
            }
            
            // Check for bare URLs
            if (preg_match('/https?:\/\/[^\s\]]+/', $content)) {
                $violations[] = [
                    'rule' => 'citation_quality',
                    'location' => $section_name,
                    'message' => "Found bare URL in {$section_name}",
                    'severity' => 'warn',
                    'suggested_rewrite' => "Replace bare URLs with proper citation format"
                ];
            }
        }
        
        return $violations;
    }

    /**
     * Calculate Jaccard similarity between two texts
     */
    private function jaccard_similarity(string $text1, string $text2): float {
        $words1 = array_filter(explode(' ', strtolower(preg_replace('/[^\w\s]/', '', $text1))));
        $words2 = array_filter(explode(' ', strtolower(preg_replace('/[^\w\s]/', '', $text2))));
        
        $set1 = array_flip($words1);
        $set2 = array_flip($words2);
        
        $intersection = array_intersect_key($set1, $set2);
        $union = array_merge($set1, $set2);
        
        if (empty($union)) return 0.0;
        
        return count($intersection) / count($union);
    }

    /**
     * Check if violations contain any errors
     */
    private function has_error_violations(array $violations): bool {
        foreach ($violations as $violation) {
            if ($violation['severity'] === 'error') {
                return true;
            }
        }
        return false;
    }

    /**
     * Legacy validate method for backward compatibility
     * 
     * @param array $sections Synthesis sections (executive_summary, overlooked, opportunities, convergence)
     * @param array $enriched_citations Enriched citation objects with title/domain
     * @return array Validation result with pass:bool, violations:[], severities:{}
     *               violations: array of {rule, location, message, severity}
     *               severities: {critical: count, warning: count, info: count}
     */
    public function validate(array $sections, array $enriched_citations): array {
        return $this->run_selfcheck($sections, $enriched_citations);
    }
}