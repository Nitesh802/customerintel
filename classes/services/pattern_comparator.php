<?php
/**
 * Pattern Comparator for Gold Standard Alignment (Slice 6)
 * 
 * Compares generated sections against exemplar patterns for quality assurance
 *
 * @package    local_customerintel
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

/**
 * Pattern Comparator - Gold Standard Pattern Integration
 * 
 * Loads exemplars and evaluates section quality against gold standards
 */
class pattern_comparator {
    
    /**
     * @var array Loaded exemplar patterns
     */
    private $exemplars;
    
    /**
     * @var array QA targets for scoring
     */
    private $qa_targets;
    
    /**
     * @var string Path to patterns schema file
     */
    private $schema_path;
    
    /**
     * Constructor
     * 
     * @param string|null $schema_path Optional custom path to schema file
     */
    public function __construct($schema_path = null) {
        global $CFG;
        
        $this->schema_path = $schema_path ?: 
            $CFG->dirroot . '/local/customerintel/schemas/gold_standard_patterns.json';
        
        $this->load_exemplars();
    }
    
    /**
     * Load exemplar patterns from JSON schema
     */
    private function load_exemplars(): void {
        if (!file_exists($this->schema_path)) {
            debugging("Gold standard patterns file not found: {$this->schema_path}", DEBUG_DEVELOPER);
            $this->exemplars = [];
            $this->qa_targets = [
                'structure' => 0.85,
                'tone' => 0.90,
                'quantification' => 0.80,
                'voice' => 0.85,
                'logical_flow' => 0.90
            ];
            return;
        }
        
        $json_content = file_get_contents($this->schema_path);
        $patterns = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            debugging("Failed to parse gold standard patterns: " . json_last_error_msg(), DEBUG_DEVELOPER);
            $this->exemplars = [];
            $this->qa_targets = [];
            return;
        }
        
        $this->exemplars = $patterns['sections'] ?? [];
        $this->qa_targets = $patterns['qa_targets'] ?? [];
    }
    
    /**
     * Compare sections against gold standard patterns
     * 
     * @param array $sections Generated sections to evaluate
     * @param array $options Configuration options
     * @return array Comparison results with alignment score
     */
    public function compare(array $sections, array $options = []): array {
        // Feature flag check
        $enabled = $options['enable_pattern_comparator'] ?? true;
        if (!$enabled) {
            return [
                'pattern_alignment_score' => 1.0,
                'diagnostics' => ['status' => 'disabled'],
                'details' => []
            ];
        }
        
        // Initialize scoring
        $section_scores = [];
        $diagnostics = [];
        
        // Evaluate each section
        foreach ($sections as $section_name => $content) {
            if (!isset($this->exemplars[$section_name])) {
                // No exemplar for this section, skip
                continue;
            }
            
            $exemplar = $this->exemplars[$section_name]['exemplar'] ?? [];
            $quality_markers = $this->exemplars[$section_name]['quality_markers'] ?? [];
            $anti_patterns = $this->exemplars[$section_name]['anti_patterns'] ?? [];
            
            // Evaluate section
            $section_score = $this->evaluate_section(
                $content,
                $exemplar,
                $quality_markers,
                $anti_patterns
            );
            
            $section_scores[$section_name] = $section_score;
            $diagnostics[$section_name] = $this->generate_diagnostics(
                $section_name,
                $content,
                $section_score
            );
        }
        
        // Calculate overall alignment score
        $pattern_alignment_score = $this->calculate_overall_score($section_scores);
        
        return [
            'pattern_alignment_score' => $pattern_alignment_score,
            'diagnostics' => $diagnostics,
            'details' => [
                'section_scores' => $section_scores,
                'evaluated_sections' => count($section_scores),
                'qa_targets' => $this->qa_targets
            ]
        ];
    }
    
    /**
     * Evaluate a single section against its exemplar
     */
    private function evaluate_section($content, array $exemplar, array $quality_markers, array $anti_patterns): array {
        $scores = [
            'structure' => $this->evaluate_structure($content, $exemplar['structure'] ?? []),
            'tone' => $this->evaluate_tone($content, $exemplar['tone'] ?? ''),
            'quantification' => $this->evaluate_quantification($content, $exemplar['quantification'] ?? []),
            'voice' => $this->evaluate_voice($content, $exemplar['voice'] ?? []),
            'logical_flow' => $this->evaluate_logical_flow($content, $exemplar['logical_flow'] ?? [])
        ];
        
        // Check quality markers
        $marker_score = $this->check_quality_markers($content, $quality_markers);
        
        // Check for anti-patterns (negative scoring)
        $anti_pattern_penalty = $this->check_anti_patterns($content, $anti_patterns);
        
        // Combine scores
        $scores['quality_markers'] = $marker_score;
        $scores['anti_pattern_penalty'] = $anti_pattern_penalty;
        
        return $scores;
    }
    
    /**
     * Evaluate structure alignment
     */
    private function evaluate_structure($content, array $structure): float {
        if (empty($structure)) {
            return 0.8; // Default score if no structure defined
        }
        
        $text = $this->get_text_content($content);
        $score = 0.7; // Base score
        
        // Check word count if specified
        if (isset($structure['word_count'])) {
            $word_count = str_word_count($text);
            $min = $structure['word_count']['min'] ?? 0;
            $max = $structure['word_count']['max'] ?? PHP_INT_MAX;
            
            if ($word_count >= $min && $word_count <= $max) {
                $score += 0.15;
            }
        }
        
        // Check format (array vs string)
        if (isset($structure['format'])) {
            if ($structure['format'] === 'array' && is_array($content)) {
                $score += 0.15;
            } elseif ($structure['format'] === 'array_of_objects' && $this->is_array_of_objects($content)) {
                $score += 0.15;
            }
        }
        
        return min(1.0, $score);
    }
    
    /**
     * Evaluate tone alignment
     */
    private function evaluate_tone($content, string $expected_tone): float {
        if (empty($expected_tone)) {
            return 0.85;
        }
        
        $text = $this->get_text_content($content);
        $score = 0.7;
        
        // Simple tone markers check
        $tone_keywords = [
            'executive' => ['strategic', 'imperative', 'critical', 'essential'],
            'analytical' => ['analysis', 'data', 'metrics', 'indicates'],
            'urgent' => ['immediate', 'critical', 'pressing', 'now'],
            'actionable' => ['implement', 'execute', 'deploy', 'activate']
        ];
        
        foreach (explode(',', $expected_tone) as $tone) {
            $tone = trim(strtolower($tone));
            if (isset($tone_keywords[$tone])) {
                foreach ($tone_keywords[$tone] as $keyword) {
                    if (stripos($text, $keyword) !== false) {
                        $score += 0.1;
                        break;
                    }
                }
            }
        }
        
        return min(1.0, $score);
    }
    
    /**
     * Evaluate quantification level
     */
    private function evaluate_quantification($content, array $quantification): float {
        $text = $this->get_text_content($content);
        $metrics_required = $quantification['metrics_required'] ?? 0;
        
        if ($metrics_required === 0) {
            return 0.9;
        }
        
        // Count metrics in text
        $metrics_found = 0;
        
        // Count percentages
        preg_match_all('/\b\d+(?:\.\d+)?%/', $text, $matches);
        $metrics_found += count($matches[0]);
        
        // Count dollar amounts
        preg_match_all('/\$[\d,]+(?:\.\d+)?[MBK]?/', $text, $matches);
        $metrics_found += count($matches[0]);
        
        // Count other numbers with context
        preg_match_all('/\b\d+(?:\.\d+)?(?:\s+(?:days|months|years|quarters|basis points|bps))\b/i', $text, $matches);
        $metrics_found += count($matches[0]);
        
        // Calculate score based on metrics found vs required
        $ratio = min(1.0, $metrics_found / $metrics_required);
        return 0.5 + ($ratio * 0.5); // Scale to 0.5-1.0 range
    }
    
    /**
     * Evaluate voice consistency
     */
    private function evaluate_voice($content, array $voice): float {
        if (empty($voice)) {
            return 0.85;
        }
        
        $text = $this->get_text_content($content);
        $score = 0.8;
        
        // Check for passive voice (penalty)
        if (preg_match('/\b(was|were|been|being)\s+\w+ed\b/i', $text)) {
            $score -= 0.1;
        }
        
        // Check for direct style
        if (isset($voice['style']) && $voice['style'] === 'direct') {
            // Direct style uses short sentences
            $sentences = preg_split('/[.!?]+/', $text);
            $avg_words = array_sum(array_map('str_word_count', $sentences)) / max(1, count($sentences));
            if ($avg_words < 20) {
                $score += 0.2;
            }
        }
        
        return min(1.0, max(0.5, $score));
    }
    
    /**
     * Evaluate logical flow
     */
    private function evaluate_logical_flow($content, array $expected_flow): float {
        if (empty($expected_flow)) {
            return 0.85;
        }
        
        $text = strtolower($this->get_text_content($content));
        $score = 0.6;
        
        // Check for flow keywords
        $flow_markers = [
            'current_state' => ['current', 'today', 'present', 'now'],
            'challenge' => ['challenge', 'pressure', 'issue', 'problem'],
            'opportunity' => ['opportunity', 'potential', 'possibility'],
            'action' => ['must', 'should', 'need to', 'implement'],
            'evidence' => ['shows', 'indicates', 'demonstrates', 'reveals'],
            'conclusion' => ['therefore', 'thus', 'consequently', 'in conclusion']
        ];
        
        foreach ($expected_flow as $flow_element) {
            if (isset($flow_markers[$flow_element])) {
                foreach ($flow_markers[$flow_element] as $marker) {
                    if (stripos($text, $marker) !== false) {
                        $score += 0.1;
                        break;
                    }
                }
            }
        }
        
        return min(1.0, $score);
    }
    
    /**
     * Check quality markers
     */
    private function check_quality_markers($content, array $quality_markers): float {
        if (empty($quality_markers)) {
            return 0.9;
        }
        
        $text = $this->get_text_content($content);
        $markers_found = 0;
        
        foreach ($quality_markers as $marker) {
            // Simple keyword matching for markers
            if ($this->marker_present($text, $marker)) {
                $markers_found++;
            }
        }
        
        return $markers_found / count($quality_markers);
    }
    
    /**
     * Check for anti-patterns
     */
    private function check_anti_patterns($content, array $anti_patterns): float {
        if (empty($anti_patterns)) {
            return 0.0; // No penalty
        }
        
        $text = $this->get_text_content($content);
        $penalties = 0;
        
        foreach ($anti_patterns as $pattern) {
            if ($this->anti_pattern_detected($text, $pattern)) {
                $penalties += 0.1;
            }
        }
        
        return min(0.5, $penalties); // Cap penalty at 0.5
    }
    
    /**
     * Helper to check if a quality marker is present
     */
    private function marker_present(string $text, string $marker): bool {
        $text = strtolower($text);
        $marker = strtolower($marker);
        
        // Check for specific patterns
        if (strpos($marker, 'company name') !== false) {
            return preg_match('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*(?:\s+(?:Inc|Corp|LLC|Ltd))?\b/', $text);
        }
        if (strpos($marker, 'quantified') !== false || strpos($marker, 'metric') !== false) {
            return preg_match('/\b\d+(?:\.\d+)?[%$]?/', $text);
        }
        if (strpos($marker, 'timeline') !== false || strpos($marker, 'q1') !== false || strpos($marker, 'q2') !== false) {
            return preg_match('/\b(?:Q[1-4]|20\d{2}|\d+-month)\b/i', $text);
        }
        
        // Generic keyword check
        return stripos($text, $marker) !== false;
    }
    
    /**
     * Helper to detect anti-patterns
     */
    private function anti_pattern_detected(string $text, string $pattern): bool {
        $text = strtolower($text);
        $pattern = strtolower($pattern);
        
        // Check for hedge words
        if (strpos($pattern, 'may') !== false || strpos($pattern, 'might') !== false) {
            return preg_match('/\b(may|might|could|possibly|perhaps)\b/', $text) > 2; // More than 2 occurrences
        }
        
        // Check for jargon
        if (strpos($pattern, 'jargon') !== false) {
            $jargon_terms = ['synergies', 'leverage', 'paradigm', 'holistic', 'ecosystem'];
            foreach ($jargon_terms as $term) {
                if (stripos($text, $term) !== false) {
                    return true;
                }
            }
        }
        
        // Check for run-on sentences
        if (strpos($pattern, 'run-on') !== false) {
            $sentences = preg_split('/[.!?]+/', $text);
            foreach ($sentences as $sentence) {
                if (str_word_count($sentence) > 25) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Calculate overall pattern alignment score
     */
    private function calculate_overall_score(array $section_scores): float {
        if (empty($section_scores)) {
            return 0.8; // Default if no sections evaluated
        }
        
        $total_score = 0;
        $total_weight = 0;
        
        foreach ($section_scores as $section_name => $scores) {
            // Calculate section score using QA target weights
            $section_score = 0;
            $section_weight = 0;
            
            foreach ($this->qa_targets as $dimension => $target) {
                if (isset($scores[$dimension])) {
                    $section_score += $scores[$dimension] * $target;
                    $section_weight += $target;
                }
            }
            
            // Apply quality marker bonus
            if (isset($scores['quality_markers'])) {
                $section_score += $scores['quality_markers'] * 0.1;
                $section_weight += 0.1;
            }
            
            // Apply anti-pattern penalty
            if (isset($scores['anti_pattern_penalty'])) {
                $section_score -= $scores['anti_pattern_penalty'];
            }
            
            if ($section_weight > 0) {
                $normalized_score = $section_score / $section_weight;
                $total_score += $normalized_score;
                $total_weight += 1;
            }
        }
        
        return $total_weight > 0 ? min(1.0, max(0.0, $total_score / $total_weight)) : 0.8;
    }
    
    /**
     * Generate diagnostics for a section
     */
    private function generate_diagnostics(string $section_name, $content, array $scores): array {
        $text = $this->get_text_content($content);
        $word_count = str_word_count($text);
        
        $diagnostics = [
            'word_count' => $word_count,
            'scores' => $scores,
            'recommendations' => []
        ];
        
        // Generate recommendations based on scores
        foreach ($scores as $dimension => $score) {
            if ($dimension === 'anti_pattern_penalty') continue;
            
            $target = $this->qa_targets[$dimension] ?? 0.8;
            if ($score < $target) {
                $diagnostics['recommendations'][] = sprintf(
                    "Improve %s (current: %.2f, target: %.2f)",
                    $dimension,
                    $score,
                    $target
                );
            }
        }
        
        return $diagnostics;
    }
    
    /**
     * Helper to extract text content
     */
    private function get_text_content($content): string {
        if (is_string($content)) {
            return $content;
        }
        
        if (is_array($content)) {
            if (isset($content['text'])) {
                return $content['text'];
            }
            
            $text_parts = [];
            foreach ($content as $item) {
                if (is_string($item)) {
                    $text_parts[] = $item;
                } elseif (is_array($item)) {
                    if (isset($item['title'])) $text_parts[] = $item['title'];
                    if (isset($item['body'])) $text_parts[] = $item['body'];
                    if (isset($item['text'])) $text_parts[] = $item['text'];
                }
            }
            return implode(' ', $text_parts);
        }
        
        return '';
    }
    
    /**
     * Check if content is array of objects
     */
    private function is_array_of_objects($content): bool {
        if (!is_array($content)) {
            return false;
        }
        
        foreach ($content as $item) {
            if (!is_array($item) || !isset($item['title']) || !isset($item['body'])) {
                return false;
            }
        }
        
        return true;
    }
}