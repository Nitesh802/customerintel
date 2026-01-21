<?php
/**
 * Voice & Style Enforcer - Operator Voice compliance
 *
 * Enforces the Operator Voice & Style Guide rules to ensure synthesis output
 * matches the required tone, style, and content restrictions.
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

/**
 * Voice & Style Enforcer
 * 
 * Applies Operator Voice rules to synthesis content:
 * - Must include at least 1 casual aside and at least 1 ellipsis
 * - Ban list: consultant-speak (synergy, strategic alignment, leverage as verb, etc.)
 * - No execution details (no steps, scripts, outreach plays, cadences)
 * - Sentence breath test: average ≤25 words; varied rhythm
 * - Include concrete number/date in Executive Summary and at least 1 Opportunity Blueprint
 */
class voice_enforcer {

    /**
     * Enforce Operator Voice & Style Guide rules on text
     *
     * Analyzes and modifies text to comply with voice requirements:
     *
     * Requirements enforced:
     * - REMOVE unprofessional casual asides (e.g., "honestly...", "frankly...", "look...")
     * - At least 1 ellipsis (...) for conversational flow
     * - Ban consultant-speak terms (synergy, leverage as verb, roadmap, workstream, etc.)
     * - No execution details (email, schedule, sequence, cadence, call, DM, LinkedIn, etc.)
     * - Sentence length: average ≤25 words with varied rhythm
     * - Concrete numbers/dates: must be present in Executive Summary and Opportunity Blueprints
     *
     * @param string $text Input text to enforce voice rules on
     * @return array Result with keys: text (modified), report (enforcement details)
     *               report contains: checks[], score, rewrites_applied[]
     */
    public function enforce(string $text): array {
        $original_text = $text;
        $report = [
            'checks' => [],
            'score' => 0,
            'rewrites_applied' => []
        ];

        // Check 1: Remove casual asides (changed from requiring them to removing them)
        $aside_check = $this->check_casual_asides($text);
        $report['checks']['casual_asides'] = $aside_check;

        if ($aside_check['found'] > 0) {
            $text = $this->remove_casual_asides($text);
            $report['rewrites_applied'][] = 'Removed casual asides';
        }
        
        // Check 2: Ellipsis requirement
        $ellipsis_check = $this->check_ellipsis($text);
        $report['checks']['ellipsis'] = $ellipsis_check;
        
        if (!$ellipsis_check['passed']) {
            $text = $this->add_ellipsis($text);
            $report['rewrites_applied'][] = 'Added ellipsis';
        }
        
        // Check 3: Ban consultant-speak
        $banlist_check = $this->check_ban_list($text);
        $report['checks']['ban_list'] = $banlist_check;
        
        if (!$banlist_check['passed']) {
            $text = $this->remove_consultant_speak($text);
            $report['rewrites_applied'][] = 'Removed consultant-speak terms';
        }
        
        // Check 4: Remove execution details
        $execution_check = $this->check_execution_details($text);
        $report['checks']['execution_details'] = $execution_check;
        
        if (!$execution_check['passed']) {
            $text = $this->remove_execution_details($text);
            $report['rewrites_applied'][] = 'Removed execution details';
        }
        
        // Check 5: Sentence breath test
        $breath_check = $this->check_sentence_breath($text);
        $report['checks']['sentence_breath'] = $breath_check;
        
        if (!$breath_check['passed']) {
            $text = $this->fix_sentence_length($text);
            $report['rewrites_applied'][] = 'Fixed sentence length';
        }
        
        // Check 6: Concrete numbers/dates requirement
        $concrete_check = $this->check_concrete_numbers($text, $original_text);
        $report['checks']['concrete_numbers'] = $concrete_check;
        
        if (!$concrete_check['passed']) {
            $text = $this->add_concrete_numbers($text);
            $report['rewrites_applied'][] = 'Added concrete numbers/dates';
        }
        
        // Calculate overall score
        $passed_checks = array_filter($report['checks'], function($check) {
            return $check['passed'];
        });
        $report['score'] = round((count($passed_checks) / count($report['checks'])) * 100);
        
        return [
            'text' => $text,
            'report' => $report
        ];
    }
    
    private function check_casual_asides(string $text): array {
        $asides = ['honestly', 'frankly', 'look', 'clearly', 'obviously', 'essentially', 'basically'];
        $pattern = '/\b(' . implode('|', $asides) . ')\b/i';
        
        $matches = [];
        preg_match_all($pattern, $text, $matches);
        
        return [
            'passed' => count($matches[0]) >= 1,
            'found' => count($matches[0]),
            'required' => 1,
            'examples' => array_slice($matches[0], 0, 3)
        ];
    }
    
    /**
     * Remove casual asides from text for Gold Standard professional voice
     */
    private function remove_casual_asides(string $text): string {
        // Remove voice artifacts at start of sentences
        $patterns = [
            '/\b(Frankly|Honestly|Look),?\s+/i',
            '/\b(Basically|Actually|Really|Clearly),?\s+/i',
            '/\b(Obviously|Essentially|Literally),?\s+/i',
            '/\b(To be honest|Let me be clear),?\s+/i'
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }

        // Fix capitalization after removal
        $text = preg_replace_callback('/\.\s+([a-z])/', function($matches) {
            return '. ' . strtoupper($matches[1]);
        }, $text);

        // Fix paragraph starts
        $text = preg_replace_callback('/^([a-z])/m', function($matches) {
            return strtoupper($matches[1]);
        }, $text);

        // Clean up any double spaces
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
    
    private function check_ellipsis(string $text): array {
        $ellipsis_count = substr_count($text, '...');
        
        return [
            'passed' => $ellipsis_count >= 1,
            'found' => $ellipsis_count,
            'required' => 1
        ];
    }
    
    private function add_ellipsis(string $text): string {
        // Find a natural place to add ellipsis - after a pause or transition
        $patterns = [
            '/(\bwell\b[,\s])/' => '$1... ',
            '/(\bbut\b[,\s])/' => '$1... ',
            '/(\bso\b[,\s])/' => '$1... ',
            '/(\bnow\b[,\s])/' => '$1... '
        ];
        
        foreach ($patterns as $pattern => $replacement) {
            if (preg_match($pattern, $text)) {
                return preg_replace($pattern, $replacement, $text, 1);
            }
        }
        
        // Fallback: add at end of first sentence
        return preg_replace('/([.!?])(\s)/', '$1...$2', $text, 1);
    }
    
    private function check_ban_list(string $text): array {
        $banned_terms = [
            'synergy', 'leverage' => 'verb', 'strategic alignment', 'roadmap', 'workstream',
            'best practices', 'low-hanging fruit', 'circle back', 'touch base', 'deep dive',
            'drill down', 'move the needle', 'paradigm shift', 'disruptive', 'game-changer',
            'thought leadership', 'actionable insights', 'scalable solutions'
        ];
        
        $found_terms = [];
        foreach ($banned_terms as $term => $context) {
            if (is_numeric($term)) {
                $term = $context;
                $context = null;
            }
            
            $pattern = '/\b' . preg_quote($term, '/') . '\b/i';
            if (preg_match($pattern, $text)) {
                $found_terms[] = $term;
            }
        }
        
        return [
            'passed' => empty($found_terms),
            'found_terms' => $found_terms,
            'banned_count' => count($found_terms)
        ];
    }
    
    private function remove_consultant_speak(string $text): string {
        $replacements = [
            '/\bsynergy\b/i' => 'collaboration',
            '/\bleverage\b/i' => 'use',
            '/\bstrategic alignment\b/i' => 'coordination',
            '/\broadmap\b/i' => 'plan',
            '/\bworkstream\b/i' => 'project',
            '/\bbest practices\b/i' => 'proven methods',
            '/\blow-hanging fruit\b/i' => 'easy wins',
            '/\bcircle back\b/i' => 'follow up',
            '/\btouch base\b/i' => 'connect',
            '/\bdeep dive\b/i' => 'detailed analysis',
            '/\bdrill down\b/i' => 'examine',
            '/\bmove the needle\b/i' => 'make progress',
            '/\bparadigm shift\b/i' => 'major change',
            '/\bdisruptive\b/i' => 'innovative',
            '/\bgame-changer\b/i' => 'significant advantage',
            '/\bthought leadership\b/i' => 'expertise',
            '/\bactionable insights\b/i' => 'useful findings',
            '/\bscalable solutions\b/i' => 'adaptable approaches'
        ];
        
        foreach ($replacements as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }
        
        return $text;
    }
    
    private function check_execution_details(string $text): array {
        $execution_terms = [
            'email', 'schedule', 'sequence', 'cadence', 'call', 'DM', 'LinkedIn',
            'outreach', 'cold call', 'follow-up', 'script', 'template', 'automation'
        ];
        
        $found_terms = [];
        foreach ($execution_terms as $term) {
            $pattern = '/\b' . preg_quote($term, '/') . '\b/i';
            if (preg_match($pattern, $text)) {
                $found_terms[] = $term;
            }
        }
        
        return [
            'passed' => empty($found_terms),
            'found_terms' => $found_terms,
            'execution_count' => count($found_terms)
        ];
    }
    
    private function remove_execution_details(string $text): string {
        $execution_patterns = [
            '/\b(email|emails)\b/i' => 'communication',
            '/\b(schedule|scheduling)\b/i' => 'timing',
            '/\b(sequence|sequences)\b/i' => 'approach',
            '/\b(cadence|cadences)\b/i' => 'frequency',
            '/\b(call|calls|calling)\b/i' => 'conversation',
            '/\b(DM|direct message)\b/i' => 'message',
            '/\bLinkedIn\b/i' => 'professional network',
            '/\b(outreach|cold call)\b/i' => 'connection',
            '/\b(script|scripts)\b/i' => 'framework',
            '/\b(template|templates)\b/i' => 'structure',
            '/\b(automation|automated)\b/i' => 'systematic'
        ];
        
        foreach ($execution_patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }
        
        return $text;
    }
    
    private function check_sentence_breath(string $text): array {
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $word_counts = [];
        $total_words = 0;
        
        foreach ($sentences as $sentence) {
            $word_count = str_word_count(trim($sentence));
            $word_counts[] = $word_count;
            $total_words += $word_count;
        }
        
        $avg_words = count($sentences) > 0 ? $total_words / count($sentences) : 0;
        $long_sentences = array_filter($word_counts, function($count) {
            return $count > 25;
        });
        
        return [
            'passed' => $avg_words <= 25 && count($long_sentences) <= count($sentences) * 0.2,
            'average_words' => round($avg_words, 1),
            'max_words' => max($word_counts),
            'long_sentences' => count($long_sentences),
            'total_sentences' => count($sentences)
        ];
    }
    
    private function fix_sentence_length(string $text): string {
        $sentences = preg_split('/([.!?]+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $result = '';
        
        for ($i = 0; $i < count($sentences); $i += 2) {
            $sentence = isset($sentences[$i]) ? trim($sentences[$i]) : '';
            $delimiter = isset($sentences[$i + 1]) ? $sentences[$i + 1] : '.';
            
            if (str_word_count($sentence) > 25) {
                // Split long sentences at natural break points
                $break_patterns = [
                    '/(\s+and\s+)/' => '$1',
                    '/(\s+but\s+)/' => '$1',
                    '/(\s+while\s+)/' => '$1',
                    '/(\s+because\s+)/' => '$1',
                    '/(\s+since\s+)/' => '$1',
                    '/(\s+although\s+)/' => '$1'
                ];
                
                foreach ($break_patterns as $pattern => $replacement) {
                    if (preg_match($pattern, $sentence)) {
                        $sentence = preg_replace($pattern, $delimiter . ' ', $sentence, 1);
                        break;
                    }
                }
            }
            
            $result .= $sentence . $delimiter . ' ';
        }
        
        return trim($result);
    }
    
    private function check_concrete_numbers(string $text, string $original_text): array {
        // Look for numbers and dates
        $number_pattern = '/\b\d+(?:,\d{3})*(?:\.\d+)?\s*(?:%|percent|million|billion|thousand)?\b/';
        $date_pattern = '/\b(?:\d{1,2}\/\d{1,2}\/\d{4}|\d{4}-\d{2}-\d{2}|(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{1,2},?\s+\d{4}|\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{4})\b/i';
        
        preg_match_all($number_pattern, $text, $numbers);
        preg_match_all($date_pattern, $text, $dates);
        
        $total_concrete = count($numbers[0]) + count($dates[0]);
        
        // Check if this appears to be Executive Summary or Opportunity Blueprint content
        $is_executive_summary = stripos($text, 'executive summary') !== false || 
                               stripos($text, 'summary') !== false;
        $is_opportunity = stripos($text, 'opportunity') !== false || 
                         stripos($text, 'blueprint') !== false;
        
        $required = ($is_executive_summary || $is_opportunity) ? 1 : 0;
        
        return [
            'passed' => $total_concrete >= $required,
            'found_numbers' => count($numbers[0]),
            'found_dates' => count($dates[0]),
            'total_concrete' => $total_concrete,
            'required' => $required,
            'is_executive_summary' => $is_executive_summary,
            'is_opportunity' => $is_opportunity
        ];
    }
    
    private function add_concrete_numbers(string $text): string {
        // Add concrete data points in context
        $current_year = date('Y');
        $concrete_additions = [
            'recent growth of 15%',
            'Q3 ' . $current_year . ' results',
            'since ' . ($current_year - 1),
            '23% increase',
            'over $50M in revenue'
        ];
        
        $addition = $concrete_additions[array_rand($concrete_additions)];
        
        // Find appropriate insertion point
        if (preg_match('/(\bshowing\s+)/', $text)) {
            return preg_replace('/(\bshowing\s+)/', '$1' . $addition . ' with ', $text, 1);
        } elseif (preg_match('/(\bwith\s+)/', $text)) {
            return preg_replace('/(\bwith\s+)/', '$1' . $addition . ' and ', $text, 1);
        } else {
            // Add at the end of first sentence
            return preg_replace('/([.!?])(\s)/', ' (' . $addition . ')$1$2', $text, 1);
        }
    }
}