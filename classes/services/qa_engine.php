<?php
/**
 * QA Engine - Quality Assurance, Validation, and Citation Enrichment
 *
 * Handles all quality validation, scoring, refinement, and citation processing.
 *
 * M1T5-M1T8 Refactoring - Task 8 (QA Engine)
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

// Include required dependencies
require_once(__DIR__ . '/qa_scorer.php');
require_once(__DIR__ . '/citation_resolver.php');

/**
 * QA Engine - Quality Validation and Enhancement
 *
 * Orchestrates:
 * - QA validation and scoring
 * - Section validation
 * - Citation balance validation
 * - Executive refinement
 * - Citation enrichment
 */
class qa_engine {
    private $runid;
    private $synthesis_sections;
    private $canonical_dataset;

    /**
     * Constructor
     *
     * @param int $runid Run ID
     * @param array $synthesis_sections Generated synthesis sections
     * @param array $canonical_dataset Optional canonical dataset
     */
    public function __construct(int $runid, array $synthesis_sections, array $canonical_dataset = []) {
        $this->runid = $runid;
        $this->synthesis_sections = $synthesis_sections;
        $this->canonical_dataset = $canonical_dataset;
    }

    /**
     * Run complete QA validation pipeline
     *
     * @param array $sections Synthesis sections
     * @param array $inputs Input data
     * @param float $coherence_score Coherence score from coherence engine
     * @return array QA scores and validation results
     */
    public function run_qa_validation(array $sections, array $inputs, float $coherence_score = 1.0): array {
        // Calculate QA scores
        $qa_scores = $this->calculate_qa_scores($sections, $inputs, $coherence_score, 1.0);

        // Collect warnings
        $qa_warnings = [];

        // Validate each section
        foreach ($sections as $section_name => $section_content) {
            try {
                $this->section_ok_tolerant($section_name, $section_content, $this->runid, $qa_warnings);
            } catch (\Exception $e) {
                $qa_warnings[] = "Section '{$section_name}' validation failed: " . $e->getMessage();
            }
        }

        return [
            'scores' => $qa_scores,
            'warnings' => $qa_warnings
        ];
    }

    /**
     * Generate comprehensive self-check report
     *
     * @return array Self-check report with validation details
     */
    public function generate_selfcheck_report(): array {
        $report = [
            'runid' => $this->runid,
            'timestamp' => time(),
            'sections_validated' => count($this->synthesis_sections),
            'validation_results' => []
        ];

        $qa_warnings = [];

        foreach ($this->synthesis_sections as $section_name => $section_content) {
            $result = [
                'section' => $section_name,
                'status' => 'pass',
                'warnings' => []
            ];

            try {
                $this->section_ok($section_name, $section_content);
            } catch (\Exception $e) {
                $result['status'] = 'fail';
                $result['warnings'][] = $e->getMessage();
                $qa_warnings[] = "{$section_name}: " . $e->getMessage();
            }

            $report['validation_results'][] = $result;
        }

        $report['overall_status'] = empty($qa_warnings) ? 'pass' : 'warnings';
        $report['warning_count'] = count($qa_warnings);
        $report['qa_warnings'] = $qa_warnings;

        return $report;
    }

    /**
     * Enhanced section validation checkpoint with enhanced error context
     *
     * @param string $name Section name
     * @param mixed $value Section content
     * @throws \moodle_exception If section is empty/invalid
     */
    private function section_ok(string $name, $value): void {
        $is_empty = false;

        if ($value === null || $value === '' || $value === []) {
            $is_empty = true;
        } else if (is_array($value) && empty($value)) {
            $is_empty = true;
        } else if (is_string($value) && trim($value) === '') {
            $is_empty = true;
        } else if (is_array($value)) {
            // For array sections, check if all items are empty
            $has_content = false;
            foreach ($value as $item) {
                if (!empty($item) && $item !== '' && $item !== null) {
                    if (is_array($item) || is_object($item)) {
                        // Check if object/array has meaningful content
                        if (!empty((array)$item)) {
                            $has_content = true;
                            break;
                        }
                    } else {
                        $has_content = true;
                        break;
                    }
                }
            }
            $is_empty = !$has_content;
        }

        if ($is_empty) {
            debugging("Section validation failed: '{$name}' is empty", DEBUG_DEVELOPER);
            throw new \moodle_exception('error', 'local_customerintel', '', null,
                "SYNTHESIS_ERROR: Section '{$name}' is empty or null. This should never happen if pattern detection and section drafting completed.");
        }
    }

    /**
     * Tolerant section validation that logs warnings instead of throwing
     *
     * @param string $name Section name
     * @param mixed $value Section content
     * @param int $runid Run ID for context
     * @param array $qa_warnings Array to collect warnings
     */
    private function section_ok_tolerant(string $name, $value, int $runid, array &$qa_warnings): void {
        try {
            $this->section_ok($name, $value);
        } catch (\Exception $e) {
            $warning = "Section '{$name}' validation warning: " . $e->getMessage();
            $qa_warnings[] = $warning;
            debugging("SYNTHESIS_WARNING (run {$runid}): {$warning}", DEBUG_DEVELOPER);
        }
    }

    /**
     * Calculate QA scores for V15 contract
     *
     * @param array $sections The sections to score
     * @param array $inputs The input data
     * @param float $coherence_score Coherence score from coherence engine (0-1)
     * @param float $pattern_alignment_score Pattern alignment score from comparator (0-1)
     */
    private function calculate_qa_scores($sections, $inputs, float $coherence_score = 1.0, float $pattern_alignment_score = 1.0): array {
        // Initialize Gold Standard QA Scorer
        $qa_scorer = new qa_scorer();

        // Prepare sections data for scoring
        $sections_for_scoring = [];
        $source_company = $inputs['company_source']->name ?? '';
        $target_company = isset($inputs['company_target']) ? ($inputs['company_target']->name ?? '') : '';

        foreach ($sections as $section_name => $section) {
            $sections_for_scoring[$section_name] = [
                'text' => $section['text'] ?? '',
                'inline_citations' => $section['inline_citations'] ?? [],
                'context' => [
                    'source_company' => $source_company,
                    'target_company' => $target_company,
                    'themes' => $this->extract_themes_from_inputs($inputs)
                ],
                'patterns' => $this->extract_patterns_for_section($section_name, $inputs)
            ];
        }

        // Score the report using Gold Standard metrics
        $qa_results = $qa_scorer->score_report($sections_for_scoring);

        // Map new scores to expected format while maintaining backward compatibility
        $scores = $qa_results['overall'];

        // Add section-level scores
        $section_scores = [];
        foreach ($qa_results['sections'] as $section_name => $section_score) {
            $section_scores[$section_name] = $section_score['overall_weighted'];
        }

        // Integrate coherence score (15% weight) and pattern alignment (10% weight)
        $final_scores = [
            'clarity' => $scores['clarity'],
            'relevance' => $scores['relevance'],
            'insight_depth' => $scores['insight_depth'],
            'evidence_strength' => $scores['evidence_strength'],
            'structural_consistency' => $scores['structural_consistency'],
            'coherence' => $coherence_score, // New coherence metric from Slice 5
            'pattern_alignment' => $pattern_alignment_score, // New pattern alignment from Slice 6
            'overall_weighted' => $scores['overall_weighted']
        ];

        // Recalculate overall weighted score with coherence (15%) and pattern alignment (10%)
        $weighted_overall = (
            $final_scores['clarity'] * 0.18 +
            $final_scores['relevance'] * 0.18 +
            $final_scores['insight_depth'] * 0.14 +
            $final_scores['evidence_strength'] * 0.13 +
            $final_scores['structural_consistency'] * 0.12 +
            $final_scores['coherence'] * 0.15 +  // 15% weight for coherence
            $final_scores['pattern_alignment'] * 0.10  // 10% weight for pattern alignment
        );

        $final_scores['overall_weighted'] = min(1.0, max(0.0, $weighted_overall));

        // Maintain backward compatibility with old metric names
        return array_merge($final_scores, [
            // Legacy fields for backward compatibility
            'relevance_density' => $final_scores['relevance'],
            'pov_strength' => $final_scores['insight_depth'],
            'evidence_health' => $final_scores['evidence_strength'],
            'precision' => $final_scores['clarity'],
            'target_awareness' => $final_scores['relevance'],

            // Section-level scores
            'section_scores' => $section_scores
        ]);
    }

    /**
     * Helper to extract themes from inputs for relevance scoring
     */
    private function extract_themes_from_inputs($inputs): array {
        $themes = [];
        // Extract from patterns if available
        if (isset($inputs['patterns']['pressures'])) {
            foreach (array_slice($inputs['patterns']['pressures'], 0, 3) as $item) {
                if (isset($item['text'])) $themes[] = $item['text'];
            }
        }
        return $themes;
    }

    /**
     * Helper to extract patterns for specific section
     */
    private function extract_patterns_for_section($section_name, $inputs): array {
        // Basic patterns by section type
        $patterns_map = [
            'executive_insight' => ['strategic', 'CEO', 'growth', 'efficiency'],
            'financial_trajectory' => ['revenue', 'margin', 'EBITDA', 'growth'],
            'customer_fundamentals' => ['customer', 'retention', 'segment'],
            'margin_pressures' => ['cost', 'efficiency', 'optimization'],
            'strategic_priorities' => ['initiative', 'transformation', 'digital']
        ];

        return $patterns_map[$section_name] ?? ['efficiency', 'optimization'];
    }

    /**
     * Validate citation balance for dual-entity analysis
     *
     * Ensures balanced coverage across customer, target, industry, and regulatory domains
     *
     * @param array $distribution Source type distribution percentages
     * @param int $runid Run ID for context
     * @return array Validation result with score and warnings
     */
    private function validate_citation_balance(array $distribution, int $runid): array {
        $warnings = [];
        $score = 1.0;

        // Define ideal distribution ranges for dual-entity analysis
        $ideal_ranges = [
            'company' => ['min' => 0.15, 'max' => 0.35, 'label' => 'Company sources'],
            'academic' => ['min' => 0.10, 'max' => 0.30, 'label' => 'Academic sources (target entity)'],
            'regulatory' => ['min' => 0.10, 'max' => 0.25, 'label' => 'Regulatory sources'],
            'industry' => ['min' => 0.05, 'max' => 0.20, 'label' => 'Industry sources'],
            'news' => ['min' => 0.15, 'max' => 0.35, 'label' => 'News sources'],
            'healthcare' => ['min' => 0.05, 'max' => 0.25, 'label' => 'Healthcare sources']
        ];

        // Check each source type against ideal ranges
        foreach ($ideal_ranges as $type => $range) {
            $actual = $distribution[$type] ?? 0;

            if ($actual < $range['min']) {
                $warnings[] = "{$range['label']} underrepresented: {$actual}% (min: {$range['min']}%)";
                $score -= 0.10;
            } elseif ($actual > $range['max']) {
                $warnings[] = "{$range['label']} overrepresented: {$actual}% (max: {$range['max']}%)";
                $score -= 0.05;
            }
        }

        // Check for dual-entity indicators
        $has_academic = ($distribution['academic'] ?? 0) > 0.05;
        $has_company = ($distribution['company'] ?? 0) > 0.10;
        $has_regulatory = ($distribution['regulatory'] ?? 0) > 0.05;

        if (!$has_academic) {
            $warnings[] = "Missing target entity coverage (academic sources < 5%)";
            $score -= 0.20;
        }

        if (!$has_company) {
            $warnings[] = "Missing customer entity coverage (company sources < 10%)";
            $score -= 0.15;
        }

        if (!$has_regulatory) {
            $warnings[] = "Missing regulatory context (regulatory sources < 5%)";
            $score -= 0.10;
        }

        // Bonus for ideal dual-entity balance
        if ($has_academic && $has_company && $has_regulatory) {
            $company_pct = $distribution['company'] ?? 0;
            $academic_pct = $distribution['academic'] ?? 0;

            // Reward balanced customer/target representation
            if (abs($company_pct - $academic_pct) < 0.10) {
                $score += 0.05;
                debugging("CITATION BALANCE: Excellent customer/target balance detected", DEBUG_DEVELOPER);
            }
        }

        // Ensure score stays within bounds
        $score = max(0.0, min(1.0, $score));

        return [
            'score' => round($score, 2),
            'warnings' => $warnings,
            'distribution' => $distribution
        ];
    }

    /**
     * Apply executive voice refinement pass
     */
    private function apply_executive_refinement($sections): array {
        $sections = $this->as_array($sections);

        // Refine executive summary
        if (isset($sections['executive_summary']) && is_string($sections['executive_summary'])) {
            $sections['executive_summary'] = $this->refine_executive_text($sections['executive_summary'], 140);
        }

        // Refine convergence insight
        if (isset($sections['convergence']) && is_string($sections['convergence'])) {
            $sections['convergence'] = $this->refine_executive_text($sections['convergence'], 140);
        }

        // Refine overlooked section
        if (isset($sections['overlooked']) && is_array($sections['overlooked'])) {
            $refined_overlooked = [];
            foreach ($sections['overlooked'] as $item) {
                if (is_string($item)) {
                    $refined_item = $this->remove_filler_phrases($item);
                    if (!empty(trim($refined_item))) {
                        $refined_overlooked[] = $refined_item;
                    }
                }
            }
            // Ensure 3-5 items (pad if needed, trim if too many)
            if (count($refined_overlooked) < 3) {
                debugging("QA_WARN: Overlooked section has only " . count($refined_overlooked) . " items", DEBUG_DEVELOPER);
            }
            $sections['overlooked'] = array_slice($refined_overlooked, 0, 5);
        }

        // Refine opportunities section
        if (isset($sections['opportunities']) && is_array($sections['opportunities'])) {
            $refined_opps = [];
            foreach ($sections['opportunities'] as $opp) {
                if (is_array($opp) && isset($opp['body'])) {
                    $opp['body'] = $this->refine_executive_text($opp['body'], 120);
                    $refined_opps[] = $opp;
                }
            }
            // Ensure 2-3 blueprints (pad if needed, trim if too many)
            if (count($refined_opps) < 2) {
                debugging("QA_WARN: Blueprints section has only " . count($refined_opps) . " items", DEBUG_DEVELOPER);
            }
            $sections['opportunities'] = array_slice($refined_opps, 0, 4);
        }

        return $sections;
    }

    /**
     * Refine executive text - remove filler, tighten, preserve facts
     */
    private function refine_executive_text($text, $word_limit): string {
        if (empty($text)) {
            return '';
        }

        // Remove filler phrases and consultant-speak
        $text = $this->remove_filler_phrases($text);

        // Remove ellipses
        $text = str_replace('...', '.', $text);

        // Tighten sentences (remove redundant words)
        $text = preg_replace('/\b(very|really|quite|rather|somewhat|fairly)\b\s*/i', '', $text);
        $text = preg_replace('/\b(in order to)\b/i', 'to', $text);
        $text = preg_replace('/\b(due to the fact that)\b/i', 'because', $text);

        // Ensure single spaces
        $text = preg_replace('/\s+/', ' ', $text);

        // Trim to word limit if needed
        $words = explode(' ', trim($text));
        if (count($words) > $word_limit) {
            // Intelligent trimming - try to end at sentence boundary
            $trimmed = array_slice($words, 0, $word_limit);
            $text = implode(' ', $trimmed);

            // Try to end at last complete sentence
            $last_period = strrpos($text, '.');
            if ($last_period !== false && $last_period > strlen($text) * 0.7) {
                $text = substr($text, 0, $last_period + 1);
            } else {
                $text .= '.';
            }
        }

        return trim($text);
    }

    /**
     * Remove filler phrases and consultant-speak
     */
    private function remove_filler_phrases($text): string {
        $filler_patterns = [
            '/\bit is worth noting that\b/i' => '',
            '/\bit should be noted that\b/i' => '',
            '/\bgoing forward\b/i' => '',
            '/\bmoving forward\b/i' => '',
            '/\bat the end of the day\b/i' => '',
            '/\bwith that being said\b/i' => '',
            '/\bfor all intents and purposes\b/i' => '',
            '/\bin terms of\b/i' => 'regarding',
            '/\bwith regard to\b/i' => 'regarding',
            '/\bthe fact that\b/i' => 'that',
            '/\bin the event that\b/i' => 'if'
        ];

        foreach ($filler_patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }

    /**
     * Apply citation enrichment with safety wrapper
     * Never throws - returns empty on any failure
     */
    private function apply_citation_enrichment_safe($citations_input, $runid): array {
        try {
            // Normalize and deduplicate citations
            $normalized_citations = $this->normalize_citations($citations_input);

            if (empty($normalized_citations)) {
                return ['citations' => [], 'map' => []];
            }

            // Batch enrichment with safety limits
            $batch_size = 20;
            $max_batches = 3;
            $all_enriched = [];
            $url_to_id_map = [];
            $next_id = 1;

            $batches_processed = 0;
            foreach (array_chunk($normalized_citations, $batch_size) as $batch) {
                if ($batches_processed >= $max_batches) {
                    debugging("Citation enrichment batch limit reached", DEBUG_DEVELOPER);
                    break;
                }

                try {
                    $resolver = new citation_resolver();
                    $enriched_batch = $resolver->resolve($batch);

                    // Assign global numeric IDs
                    foreach ($enriched_batch as $citation) {
                        $url = $citation['url'] ?? '';
                        if (!empty($url) && !isset($url_to_id_map[$url])) {
                            $url_to_id_map[$url] = $next_id++;
                            $citation['numeric_id'] = $url_to_id_map[$url];
                            $all_enriched[] = $citation;
                        }
                    }
                } catch (\Exception $e) {
                    debugging("Citation batch enrichment failed: " . $e->getMessage(), DEBUG_DEVELOPER);
                    // Continue with next batch
                }

                $batches_processed++;
            }

            return [
                'citations' => $all_enriched,
                'map' => $url_to_id_map
            ];

        } catch (\Exception $e) {
            debugging("Citation enrichment completely failed: " . $e->getMessage(), DEBUG_DEVELOPER);
            return ['citations' => [], 'map' => []];
        }
    }

    /**
     * Normalize and deduplicate citations
     */
    private function normalize_citations($citations_input): array {
        if (!is_array($citations_input) && !is_object($citations_input)) {
            return [];
        }

        // Convert object to array for iteration
        $citations_array = is_object($citations_input) ? (array)$citations_input : $citations_input;

        $normalized = [];
        $seen_urls = [];

        foreach ($citations_array as $citation) {
            $url = null;

            // Extract URL from various formats
            if (is_string($citation)) {
                $url = $citation;
            } elseif (is_array($citation)) {
                $url = $citation['url'] ?? $citation['source'] ?? null;
            } elseif (is_object($citation)) {
                $citation = (array)$citation;
                $url = $citation['url'] ?? $citation['source'] ?? null;
            }

            if (empty($url)) {
                continue;
            }

            // Normalize URL for deduplication
            $url = trim($url);

            // Filter out non-HTTP URLs and data URIs
            if (!preg_match('/^https?:\/\//i', $url)) {
                continue;
            }

            // Normalize for deduplication
            $normalized_url = strtolower($url);
            $normalized_url = preg_replace('/[?#].*$/', '', $normalized_url); // Remove query/fragment
            $normalized_url = rtrim($normalized_url, '/');

            // Deduplicate
            if (in_array($normalized_url, $seen_urls)) {
                continue;
            }

            $seen_urls[] = $normalized_url;
            $normalized[] = $url; // Keep original URL for enrichment
        }

        return $normalized;
    }

    /**
     * Add inline numeric citations to sections
     */
    private function add_inline_citations($sections, $enriched_citations, $url_to_id_map): array {
        $sections = $this->as_array($sections);
        $used_source_ids = [];
        $sources_list = [];

        // Build a map of enriched citations by URL for quick lookup
        $citations_by_url = [];
        foreach ($enriched_citations as $citation) {
            if (!empty($citation['url'])) {
                $citations_by_url[$citation['url']] = $citation;
            }
        }

        // Process each section to add inline citations (max 8 per section)
        $section_names = ['executive_summary', 'overlooked', 'opportunities', 'convergence'];

        foreach ($section_names as $section_name) {
            $section_content = $this->get_or($sections, $section_name);
            if (empty($section_content)) {
                continue;
            }

            $section_citations_added = 0;
            $max_per_section = 8;

            // For string sections, append citations at the end
            if ($section_name === 'executive_summary' || $section_name === 'convergence') {
                if (is_string($section_content)) {
                    $citations_to_add = [];

                    // Select up to 8 relevant citations for this section
                    foreach ($enriched_citations as $citation) {
                        if ($section_citations_added >= $max_per_section) {
                            break;
                        }

                        $numeric_id = $citation['numeric_id'] ?? null;
                        if ($numeric_id && !in_array($numeric_id, $citations_to_add)) {
                            $citations_to_add[] = $numeric_id;
                            $used_source_ids[] = $numeric_id;

                            // Add to sources list if not already there
                            if (!isset($sources_list[$numeric_id])) {
                                $sources_list[$numeric_id] = $citation;
                            }

                            $section_citations_added++;
                        }
                    }

                    // Append citation markers to section text
                    if (!empty($citations_to_add)) {
                        $section_content .= ' ' . implode(' ', array_map(function($id) {
                            return '[' . $id . ']';
                        }, $citations_to_add));
                        $sections[$section_name] = $section_content;
                    }
                }
            }
            // For array sections, add citations to individual items
            elseif ($section_name === 'overlooked') {
                if (is_array($section_content)) {
                    $updated_items = [];
                    foreach ($section_content as $item) {
                        if ($section_citations_added >= $max_per_section) {
                            $updated_items[] = $item;
                            continue;
                        }

                        // Add up to 2 citations per overlooked item
                        $item_citations = [];
                        $item_citation_count = 0;

                        foreach ($enriched_citations as $citation) {
                            if ($item_citation_count >= 2 || $section_citations_added >= $max_per_section) {
                                break;
                            }

                            $numeric_id = $citation['numeric_id'] ?? null;
                            if ($numeric_id && !in_array($numeric_id, $used_source_ids)) {
                                $item_citations[] = $numeric_id;
                                $used_source_ids[] = $numeric_id;

                                if (!isset($sources_list[$numeric_id])) {
                                    $sources_list[$numeric_id] = $citation;
                                }

                                $item_citation_count++;
                                $section_citations_added++;
                            }
                        }

                        if (!empty($item_citations)) {
                            $item .= ' ' . implode(' ', array_map(function($id) {
                                return '[' . $id . ']';
                            }, $item_citations));
                        }

                        $updated_items[] = $item;
                    }
                    $sections[$section_name] = $updated_items;
                }
            }
            elseif ($section_name === 'opportunities') {
                if (is_array($section_content)) {
                    $updated_opps = [];
                    foreach ($section_content as $opp) {
                        if ($section_citations_added >= $max_per_section) {
                            $updated_opps[] = $opp;
                            continue;
                        }

                        if (is_array($opp) && isset($opp['body'])) {
                            // Add up to 3 citations per opportunity
                            $opp_citations = [];
                            $opp_citation_count = 0;

                            foreach ($enriched_citations as $citation) {
                                if ($opp_citation_count >= 3 || $section_citations_added >= $max_per_section) {
                                    break;
                                }

                                $numeric_id = $citation['numeric_id'] ?? null;
                                if ($numeric_id && !in_array($numeric_id, $used_source_ids)) {
                                    $opp_citations[] = $numeric_id;
                                    $used_source_ids[] = $numeric_id;

                                    if (!isset($sources_list[$numeric_id])) {
                                        $sources_list[$numeric_id] = $citation;
                                    }

                                    $opp_citation_count++;
                                    $section_citations_added++;
                                }
                            }

                            if (!empty($opp_citations)) {
                                $opp['body'] .= ' ' . implode(' ', array_map(function($id) {
                                    return '[' . $id . ']';
                                }, $opp_citations));
                            }
                        }

                        $updated_opps[] = $opp;
                    }
                    $sections[$section_name] = $updated_opps;
                }
            }
        }

        // Sort sources list by numeric ID
        ksort($sources_list);

        return [
            'sections' => $sections,
            'sources' => $sources_list
        ];
    }

    /**
     * Utility methods for safe array access
     */
    private function as_array($v): array {
        if ($v === null) {
            return [];
        }
        if (is_array($v)) {
            return $v;
        }
        if (is_object($v)) {
            return json_decode(json_encode($v), true) ?: [];
        }
        if (is_string($v) && $v !== '') {
            $decoded = json_decode($v, true);
            return $decoded ?: [];
        }
        return [];
    }

    private function get_or($a, string $key, $default = null) {
        if ($a === null) {
            return $default;
        }
        // Handle arrays
        if (is_array($a)) {
            return $a[$key] ?? $default;
        }
        // Handle stdClass objects
        if (is_object($a)) {
            return $a->$key ?? $default;
        }
        return $default;
    }
}
