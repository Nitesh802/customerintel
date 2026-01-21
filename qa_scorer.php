<?php
/**
 * QA Scorer for V15 Intelligence Playbook - Gold Standard Alignment
 *
 * Implements weighted scoring metrics for synthesis quality assessment
 * aligned with Gold Standard Report requirements.
 *
 * @package    local_customerintel
 * @copyright  2024 CustomerIntel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

/**
 * QA Scorer Service
 * 
 * Calculates weighted quality metrics for synthesis output:
 * - Clarity (30%): Readability and simplicity
 * - Relevance (25%): Context alignment and focus
 * - Insight Depth (20%): Beyond-surface analysis
 * - Evidence Strength (15%): Citation quality
 * - Structural Consistency (10%): Flow and formatting
 */
class qa_scorer {

    /** Weight constants for metric aggregation */
    const WEIGHT_CLARITY = 0.30;
    const WEIGHT_RELEVANCE = 0.25;
    const WEIGHT_INSIGHT = 0.20;
    const WEIGHT_EVIDENCE = 0.15;
    const WEIGHT_STRUCTURE = 0.10;

    /** Threshold constants */
    const MIN_SCORE = 0.0;
    const MAX_SCORE = 1.0;
    const TARGET_SCORE = 0.75;

    /**
     * Calculate clarity score based on readability metrics
     * 
     * @param string $text Section text to analyze
     * @return float Score between 0.0 and 1.0
     */
    public function calculate_clarity_score(string $text): float {
        if (empty(trim($text))) {
            return self::MIN_SCORE;
        }

        $score = 1.0;
        
        // Sentence complexity check
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $avg_words = 0;
        if (count($sentences) > 0) {
            foreach ($sentences as $sentence) {
                $avg_words += str_word_count($sentence);
            }
            $avg_words = $avg_words / count($sentences);
            
            // Penalize very long sentences (>25 words)
            if ($avg_words > 25) {
                $score -= 0.2;
            } elseif ($avg_words > 20) {
                $score -= 0.1;
            }
        }
        
        // Jargon and complexity detection
        $jargon_terms = [
            'leverage', 'synergies', 'paradigm', 'bandwidth', 
            'mindshare', 'ecosystem', 'touchpoint', 'deliverables'
        ];
        $text_lower = strtolower($text);
        $jargon_count = 0;
        foreach ($jargon_terms as $term) {
            $jargon_count += substr_count($text_lower, $term);
        }
        
        // Penalize excessive jargon
        $word_count = str_word_count($text);
        if ($word_count > 0) {
            $jargon_ratio = $jargon_count / $word_count;
            $score -= min(0.3, $jargon_ratio * 10);
        }
        
        // Active voice preference
        $passive_indicators = ['was ', 'were ', 'been ', 'being ', 'are being', 'have been'];
        $passive_count = 0;
        foreach ($passive_indicators as $indicator) {
            $passive_count += substr_count($text_lower, $indicator);
        }
        if ($word_count > 0) {
            $passive_ratio = $passive_count / $word_count;
            $score -= min(0.2, $passive_ratio * 5);
        }
        
        return max(self::MIN_SCORE, min(self::MAX_SCORE, $score));
    }

    /**
     * Calculate relevance score based on context alignment
     * 
     * @param string $text Section text
     * @param array $context Context data including company names and focus areas
     * @return float Score between 0.0 and 1.0
     */
    public function calculate_relevance_score(string $text, array $context): float {
        if (empty(trim($text))) {
            return self::MIN_SCORE;
        }

        $score = 0.5; // Start at midpoint
        
        // Company name mentions
        $source_company = $context['source_company'] ?? '';
        $target_company = $context['target_company'] ?? '';
        
        if (!empty($source_company)) {
            $source_mentions = substr_count($text, $source_company);
            if ($source_mentions > 0) {
                $score += min(0.2, $source_mentions * 0.05);
            }
        }
        
        if (!empty($target_company)) {
            $target_mentions = substr_count($text, $target_company);
            if ($target_mentions > 0) {
                $score += min(0.2, $target_mentions * 0.05);
            }
        }
        
        // Key theme alignment
        $key_themes = $context['themes'] ?? [];
        $theme_matches = 0;
        foreach ($key_themes as $theme) {
            if (stripos($text, $theme) !== false) {
                $theme_matches++;
            }
        }
        if (count($key_themes) > 0) {
            $theme_ratio = $theme_matches / count($key_themes);
            $score += $theme_ratio * 0.3;
        }
        
        // Temporal relevance (current year/quarter mentions)
        $current_year = date('Y');
        $temporal_markers = [$current_year, 'Q1', 'Q2', 'Q3', 'Q4', '2024', '2025'];
        $temporal_count = 0;
        foreach ($temporal_markers as $marker) {
            if (strpos($text, $marker) !== false) {
                $temporal_count++;
            }
        }
        if ($temporal_count > 0) {
            $score += min(0.1, $temporal_count * 0.02);
        }
        
        return max(self::MIN_SCORE, min(self::MAX_SCORE, $score));
    }

    /**
     * Calculate insight depth based on analytical quality
     * 
     * @param string $text Section text
     * @param array $patterns Known patterns to detect
     * @return float Score between 0.0 and 1.0
     */
    public function calculate_insight_depth(string $text, array $patterns): float {
        if (empty(trim($text))) {
            return self::MIN_SCORE;
        }

        $score = 0.3; // Base score
        
        // Analytical indicators
        $analytical_phrases = [
            'this indicates', 'suggests that', 'demonstrates',
            'reveals', 'implies', 'underlying', 'root cause',
            'correlation', 'trend', 'pattern', 'driver'
        ];
        
        $text_lower = strtolower($text);
        $analytical_count = 0;
        foreach ($analytical_phrases as $phrase) {
            if (strpos($text_lower, $phrase) !== false) {
                $analytical_count++;
            }
        }
        $score += min(0.3, $analytical_count * 0.05);
        
        // Quantitative analysis
        preg_match_all('/\d+%|\$[\d,]+|\d+x|\d+\.\d+/', $text, $numbers);
        $numeric_count = count($numbers[0]);
        if ($numeric_count > 0) {
            $score += min(0.2, $numeric_count * 0.04);
        }
        
        // Causal relationships
        $causal_terms = ['because', 'therefore', 'thus', 'consequently', 'as a result', 'due to'];
        $causal_count = 0;
        foreach ($causal_terms as $term) {
            if (stripos($text, $term) !== false) {
                $causal_count++;
            }
        }
        $score += min(0.2, $causal_count * 0.05);
        
        // Pattern recognition from input
        $pattern_matches = 0;
        foreach ($patterns as $pattern) {
            if (is_string($pattern) && stripos($text, $pattern) !== false) {
                $pattern_matches++;
            }
        }
        if (count($patterns) > 0) {
            $pattern_ratio = $pattern_matches / count($patterns);
            $score += $pattern_ratio * 0.2;
        }
        
        return max(self::MIN_SCORE, min(self::MAX_SCORE, $score));
    }

    /**
     * Calculate evidence strength based on citation quality
     * 
     * @param array $citations Citation data for the section
     * @return float Score between 0.0 and 1.0
     */
    public function calculate_evidence_strength(array $citations): float {
        if (empty($citations)) {
            return 0.1; // Minimal score for no citations
        }

        $score = 0.3; // Base for having citations
        
        // Citation count (optimal 3-5 per section)
        $count = count($citations);
        if ($count >= 3 && $count <= 5) {
            $score += 0.3;
        } elseif ($count >= 2 && $count <= 8) {
            $score += 0.2;
        } elseif ($count > 8) {
            $score += 0.1; // Too many citations can be overwhelming
        }
        
        // Citation diversity (different sources)
        $unique_domains = [];
        foreach ($citations as $citation) {
            if (isset($citation['domain'])) {
                $unique_domains[$citation['domain']] = true;
            }
        }
        $diversity_ratio = count($unique_domains) / max(1, $count);
        $score += $diversity_ratio * 0.2;
        
        // Recency (if year data available)
        $current_year = (int)date('Y');
        $recent_count = 0;
        foreach ($citations as $citation) {
            if (isset($citation['year'])) {
                $year = (int)$citation['year'];
                if ($year >= $current_year - 2) {
                    $recent_count++;
                }
            }
        }
        if ($count > 0) {
            $recency_ratio = $recent_count / $count;
            $score += $recency_ratio * 0.2;
        }
        
        return max(self::MIN_SCORE, min(self::MAX_SCORE, $score));
    }

    /**
     * Calculate structural consistency across sections
     * 
     * @param array $sections All report sections for comparison
     * @return float Score between 0.0 and 1.0
     */
    public function calculate_structural_consistency(array $sections): float {
        if (empty($sections)) {
            return self::MIN_SCORE;
        }

        $score = 0.5; // Base score
        
        // Length consistency
        $lengths = [];
        foreach ($sections as $section) {
            $text = $section['text'] ?? '';
            $lengths[] = str_word_count($text);
        }
        
        if (count($lengths) > 1) {
            $avg_length = array_sum($lengths) / count($lengths);
            $std_dev = 0;
            foreach ($lengths as $length) {
                $std_dev += pow($length - $avg_length, 2);
            }
            $std_dev = sqrt($std_dev / count($lengths));
            
            // Lower variance is better
            $variance_ratio = $avg_length > 0 ? $std_dev / $avg_length : 1;
            if ($variance_ratio < 0.3) {
                $score += 0.2;
            } elseif ($variance_ratio < 0.5) {
                $score += 0.1;
            }
        }
        
        // Terminology consistency
        $all_text = '';
        foreach ($sections as $section) {
            $all_text .= ' ' . ($section['text'] ?? '');
        }
        
        // Check for consistent terminology usage
        $consistency_terms = ['strategy', 'objective', 'initiative', 'priority'];
        $term_variations = 0;
        foreach ($consistency_terms as $term) {
            $singular = substr_count(strtolower($all_text), $term);
            $plural = substr_count(strtolower($all_text), $term . 's');
            if ($singular > 0 && $plural > 0) {
                // Both forms used, check ratio
                $ratio = min($singular, $plural) / max($singular, $plural);
                if ($ratio > 0.3) {
                    $term_variations++;
                }
            }
        }
        $score += max(0, 0.3 - ($term_variations * 0.05));
        
        return max(self::MIN_SCORE, min(self::MAX_SCORE, $score));
    }

    /**
     * Aggregate individual scores with weights
     * 
     * @param array $scores Individual metric scores
     * @return array Weighted overall and breakdown
     */
    public function aggregate_scores(array $scores): array {
        $weighted_sum = 
            ($scores['clarity'] ?? 0) * self::WEIGHT_CLARITY +
            ($scores['relevance'] ?? 0) * self::WEIGHT_RELEVANCE +
            ($scores['insight_depth'] ?? 0) * self::WEIGHT_INSIGHT +
            ($scores['evidence_strength'] ?? 0) * self::WEIGHT_EVIDENCE +
            ($scores['structural_consistency'] ?? 0) * self::WEIGHT_STRUCTURE;
        
        return [
            'clarity' => $scores['clarity'] ?? 0,
            'relevance' => $scores['relevance'] ?? 0,
            'insight_depth' => $scores['insight_depth'] ?? 0,
            'evidence_strength' => $scores['evidence_strength'] ?? 0,
            'structural_consistency' => $scores['structural_consistency'] ?? 0,
            'overall_weighted' => round($weighted_sum, 3)
        ];
    }

    /**
     * Score a single section
     * 
     * @param array $section_data Section with text, citations, context
     * @param array $all_sections All sections for consistency check
     * @return array Detailed scores for the section
     */
    public function score_section(array $section_data, array $all_sections = []): array {
        $text = $section_data['text'] ?? '';
        $citations = $section_data['inline_citations'] ?? [];
        $context = $section_data['context'] ?? [];
        $patterns = $section_data['patterns'] ?? [];
        
        $scores = [
            'clarity' => $this->calculate_clarity_score($text),
            'relevance' => $this->calculate_relevance_score($text, $context),
            'insight_depth' => $this->calculate_insight_depth($text, $patterns),
            'evidence_strength' => $this->calculate_evidence_strength($citations),
            'structural_consistency' => $this->calculate_structural_consistency($all_sections)
        ];
        
        return $this->aggregate_scores($scores);
    }

    /**
     * Score entire report
     * 
     * @param array $report_sections All report sections
     * @return array Overall and per-section scores
     */
    public function score_report(array $report_sections): array {
        $section_scores = [];
        $aggregate_scores = [
            'clarity' => 0,
            'relevance' => 0,
            'insight_depth' => 0,
            'evidence_strength' => 0,
            'structural_consistency' => 0
        ];
        
        $structural_consistency = $this->calculate_structural_consistency($report_sections);
        
        foreach ($report_sections as $section_name => $section_data) {
            $section_score = $this->score_section($section_data, $report_sections);
            $section_score['structural_consistency'] = $structural_consistency;
            $section_scores[$section_name] = $section_score;
            
            // Accumulate for averaging
            foreach ($aggregate_scores as $metric => &$sum) {
                $sum += $section_score[$metric] ?? 0;
            }
        }
        
        // Average the accumulated scores
        $section_count = max(1, count($report_sections));
        foreach ($aggregate_scores as &$score) {
            $score = $score / $section_count;
        }
        
        return [
            'overall' => $this->aggregate_scores($aggregate_scores),
            'sections' => $section_scores
        ];
    }
}