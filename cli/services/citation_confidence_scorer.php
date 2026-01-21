<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Citation Confidence Scorer
 * Calculates confidence scores and diversity metrics for citations
 *
 * @package    local_customerintel
 * @category   services
 * @copyright  2025 CustomerIntel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

/**
 * Citation Confidence Scorer
 * 
 * Provides confidence scoring based on:
 * - Source authority (40%)
 * - Recency (20%)
 * - Corroboration (25%)
 * - Relevance (15%)
 * 
 * Also calculates diversity metrics across citation corpus
 */
class citation_confidence_scorer {
    
    // Domain authority mappings (enhanced for dual-entity ViiV+Duke analysis)
    private const DOMAIN_AUTHORITY = [
        'sec.gov' => 1.0,
        'edgar.sec.gov' => 1.0,
        'fda.gov' => 1.0,
        'nih.gov' => 0.98,
        'duke.edu' => 0.95,
        'investor.gov' => 0.95,
        'bloomberg.com' => 0.95,
        'reuters.com' => 0.95,
        'wsj.com' => 0.95,
        'ft.com' => 0.90,
        'nejm.org' => 0.90,
        'jama.jamanetwork.com' => 0.90,
        'forbes.com' => 0.85,
        'fortune.com' => 0.85,
        'viivhealthcare.com' => 0.85,
        'gsk.com' => 0.85,
        'businesswire.com' => 0.80,
        'prnewswire.com' => 0.80,
        'medscape.com' => 0.80,
        'techcrunch.com' => 0.75,
        'crunchbase.com' => 0.75,
        'linkedin.com' => 0.70,
        'glassdoor.com' => 0.65
    ];
    
    // Source type patterns for categorization (enhanced for dual-entity analysis)
    private const SOURCE_TYPE_PATTERNS = [
        'regulatory' => ['sec.gov', 'edgar.sec', 'investor.gov', 'fdic.gov', 'occ.gov', 'fda.gov', 'nih.gov'],
        'news' => ['bloomberg', 'reuters', 'wsj', 'ft.com', 'forbes', 'fortune', 'cnbc', 'marketwatch'],
        'analyst' => ['gartner', 'forrester', 'idc.com', 'mckinsey', 'deloitte', 'pwc', 'bcg.com'],
        'company' => ['investor.', 'ir.', 'investors.', 'about.', 'newsroom.', 'viiv', 'gsk'],
        'industry' => ['trade', 'association', 'institute', 'society', 'foundation'],
        'academic' => ['duke.edu', 'edu', 'ac.uk', 'research', 'university', 'college'],
        'healthcare' => ['pharma', 'medscape', 'nejm', 'jama', 'healthcare', 'health']
    ];
    
    /**
     * Calculate confidence score for a single citation
     * 
     * @param array $citation Citation data
     * @param array $context Additional context (section, corroboration count, etc.)
     * @return float Confidence score 0.0-1.0
     */
    public function calculate_confidence(array $citation, array $context = []): float {
        $authority = $this->calculate_authority_score($citation);
        $recency = $this->calculate_recency_score($citation);
        $corroboration = $this->calculate_corroboration_score($context);
        $relevance = $this->calculate_relevance_score($citation, $context);
        
        // Weighted formula
        $confidence = ($authority * 0.40) +
                     ($recency * 0.20) +
                     ($corroboration * 0.25) +
                     ($relevance * 0.15);
        
        return round(min(1.0, max(0.0, $confidence)), 2);
    }
    
    /**
     * Calculate authority score based on domain reputation
     */
    private function calculate_authority_score(array $citation): float {
        $domain = $citation['domain'] ?? '';
        
        // Check exact domain match
        if (isset(self::DOMAIN_AUTHORITY[$domain])) {
            return self::DOMAIN_AUTHORITY[$domain];
        }
        
        // Check partial matches for subdomains
        foreach (self::DOMAIN_AUTHORITY as $auth_domain => $score) {
            if (strpos($domain, $auth_domain) !== false) {
                return $score * 0.95; // Slight penalty for subdomain
            }
        }
        
        // Check if it's a company domain (has investor relations indicators)
        if (preg_match('/investor|ir\.|investors/', $domain)) {
            return 0.75;
        }
        
        // Default for unknown domains
        return 0.40;
    }
    
    /**
     * Calculate recency score based on publication date
     */
    private function calculate_recency_score(array $citation): float {
        $pub_date = $citation['publishedat'] ?? null;
        
        if (!$pub_date) {
            // No date available, use conservative score
            return 0.50;
        }
        
        $days_old = (time() - strtotime($pub_date)) / 86400;
        
        if ($days_old <= 30) {
            return 1.0;
        } elseif ($days_old <= 90) {
            return 0.85;
        } elseif ($days_old <= 180) {
            return 0.70;
        } elseif ($days_old <= 365) {
            return 0.55;
        } else {
            return 0.40;
        }
    }
    
    /**
     * Calculate corroboration score based on multiple sources
     */
    private function calculate_corroboration_score(array $context): float {
        $corroboration_count = $context['corroboration_count'] ?? 1;
        
        if ($corroboration_count >= 3) {
            return 1.0;
        } elseif ($corroboration_count == 2) {
            return 0.75;
        } else {
            return 0.50;
        }
    }
    
    /**
     * Calculate relevance score based on section context
     */
    private function calculate_relevance_score(array $citation, array $context): float {
        $section = $context['section'] ?? '';
        $snippet = strtolower($citation['snippet'] ?? '');
        
        // Section-specific keywords for relevance matching
        $section_keywords = [
            'executive_insight' => ['leadership', 'strategy', 'ceo', 'executive', 'vision'],
            'financial_trajectory' => ['revenue', 'growth', 'ebitda', 'margin', 'financial'],
            'margin_pressures' => ['cost', 'efficiency', 'pressure', 'expense', 'overhead'],
            'strategic_priorities' => ['priority', 'initiative', 'transformation', 'digital'],
            'growth_levers' => ['expansion', 'market', 'opportunity', 'potential', 'scale']
        ];
        
        if (!isset($section_keywords[$section])) {
            return 0.60; // Default medium relevance
        }
        
        $keywords = $section_keywords[$section];
        $match_count = 0;
        
        foreach ($keywords as $keyword) {
            if (strpos($snippet, $keyword) !== false) {
                $match_count++;
            }
        }
        
        $match_ratio = $match_count / count($keywords);
        
        if ($match_ratio >= 0.6) {
            return 1.0;
        } elseif ($match_ratio >= 0.4) {
            return 0.80;
        } elseif ($match_ratio >= 0.2) {
            return 0.60;
        } else {
            return 0.40;
        }
    }
    
    /**
     * Categorize source type based on domain and patterns
     */
    public function categorize_source_type(string $domain): string {
        $domain = strtolower($domain);
        
        foreach (self::SOURCE_TYPE_PATTERNS as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($domain, $pattern) !== false) {
                    return $type;
                }
            }
        }
        
        return 'industry'; // Default category
    }
    
    /**
     * Calculate diversity metrics for a set of citations
     * 
     * @param array $citations Array of citation objects
     * @return array Diversity metrics
     */
    public function calculate_diversity_metrics(array $citations): array {
        if (empty($citations)) {
            return [
                'unique_domains' => 0,
                'source_type_distribution' => [],
                'recency_mix' => [],
                'diversity_score' => 0.0
            ];
        }
        
        $domains = [];
        $source_types = [];
        $dates = [];
        
        foreach ($citations as $citation) {
            // Collect unique domains
            $domain = $citation['domain'] ?? 'unknown';
            $domains[$domain] = true;
            
            // Categorize and count source types
            $type = $this->categorize_source_type($domain);
            $source_types[$type] = ($source_types[$type] ?? 0) + 1;
            
            // Collect publication dates
            if (isset($citation['publishedat'])) {
                $dates[] = strtotime($citation['publishedat']);
            }
        }
        
        // Calculate domain variety score
        $unique_count = count($domains);
        $domain_variety = $this->calculate_domain_variety_score($unique_count);
        
        // Calculate source type distribution
        $type_distribution = $this->calculate_type_distribution($source_types, count($citations));
        $type_balance = $this->calculate_type_balance_score($type_distribution);
        
        // Calculate temporal spread
        $recency_mix = $this->calculate_recency_mix($dates);
        $temporal_score = $this->calculate_temporal_spread_score($dates);
        
        // Dual-entity analysis bonus
        $dual_entity_bonus = $this->calculate_dual_entity_bonus($domains, $type_distribution);
        
        // Composite diversity score (enhanced for dual-entity analysis)
        $diversity_score = ($domain_variety * 0.35) +
                          ($type_balance * 0.30) +
                          ($temporal_score * 0.25) +
                          ($dual_entity_bonus * 0.10);
        
        return [
            'unique_domains' => $unique_count,
            'source_type_distribution' => $type_distribution,
            'recency_mix' => $recency_mix,
            'diversity_score' => round($diversity_score, 2)
        ];
    }
    
    /**
     * Calculate domain variety score
     */
    private function calculate_domain_variety_score(int $unique_count): float {
        if ($unique_count >= 10) {
            return 1.0;
        } elseif ($unique_count >= 7) {
            return 0.80;
        } elseif ($unique_count >= 4) {
            return 0.60;
        } else {
            return 0.40;
        }
    }
    
    /**
     * Calculate type distribution percentages
     */
    private function calculate_type_distribution(array $source_types, int $total): array {
        $distribution = [];
        
        foreach ($source_types as $type => $count) {
            $distribution[$type] = round($count / $total, 2);
        }
        
        return $distribution;
    }
    
    /**
     * Calculate balance score for source type distribution
     */
    private function calculate_type_balance_score(array $distribution): float {
        $type_count = count($distribution);
        
        if ($type_count >= 4) {
            // Check for even distribution
            $variance = $this->calculate_distribution_variance($distribution);
            if ($variance < 0.1) {
                return 1.0;
            } elseif ($variance < 0.2) {
                return 0.85;
            } else {
                return 0.70;
            }
        } elseif ($type_count == 3) {
            return 0.75;
        } elseif ($type_count == 2) {
            return 0.50;
        } else {
            return 0.25;
        }
    }
    
    /**
     * Calculate variance in distribution
     */
    private function calculate_distribution_variance(array $distribution): float {
        $mean = array_sum($distribution) / count($distribution);
        $variance = 0;
        
        foreach ($distribution as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        return $variance / count($distribution);
    }
    
    /**
     * Calculate recency mix
     */
    private function calculate_recency_mix(array $dates): array {
        if (empty($dates)) {
            return [
                'current_month' => 0,
                'current_quarter' => 0,
                'current_year' => 0
            ];
        }
        
        $now = time();
        $month_ago = $now - (30 * 86400);
        $quarter_ago = $now - (90 * 86400);
        $year_ago = $now - (365 * 86400);
        
        $current_month = 0;
        $current_quarter = 0;
        $current_year = 0;
        
        foreach ($dates as $date) {
            if ($date >= $month_ago) {
                $current_month++;
            }
            if ($date >= $quarter_ago) {
                $current_quarter++;
            }
            if ($date >= $year_ago) {
                $current_year++;
            }
        }
        
        $total = count($dates);
        
        return [
            'current_month' => round($current_month / $total, 2),
            'current_quarter' => round($current_quarter / $total, 2),
            'current_year' => round($current_year / $total, 2)
        ];
    }
    
    /**
     * Calculate temporal spread score
     */
    private function calculate_temporal_spread_score(array $dates): float {
        if (count($dates) < 2) {
            return 0.25;
        }
        
        $min_date = min($dates);
        $max_date = max($dates);
        $spread_days = ($max_date - $min_date) / 86400;
        
        if ($spread_days >= 365) {
            return 1.0;
        } elseif ($spread_days >= 180) {
            return 0.75;
        } elseif ($spread_days >= 90) {
            return 0.50;
        } else {
            return 0.25;
        }
    }
    
    /**
     * Calculate bonus for dual-entity analysis coverage
     * Rewards citation sets that include both customer and target entity domains
     * 
     * @param array $domains Unique domains found
     * @param array $type_distribution Source type distribution
     * @return float Bonus score 0.0-1.0
     */
    private function calculate_dual_entity_bonus(array $domains, array $type_distribution): float {
        $bonus = 0.0;
        
        // Check for academic sources (indicates target entity coverage)
        if (isset($type_distribution['academic']) && $type_distribution['academic'] > 0) {
            $bonus += 0.30;
        }
        
        // Check for healthcare sources (indicates domain-specific coverage)
        if (isset($type_distribution['healthcare']) && $type_distribution['healthcare'] > 0) {
            $bonus += 0.25;
        }
        
        // Check for both company and academic sources (dual-entity indicator)
        if (isset($type_distribution['company']) && isset($type_distribution['academic']) && 
            $type_distribution['company'] > 0 && $type_distribution['academic'] > 0) {
            $bonus += 0.25;
        }
        
        // Reward regulatory + academic combination (comprehensive coverage)
        if (isset($type_distribution['regulatory']) && isset($type_distribution['academic']) &&
            $type_distribution['regulatory'] > 0 && $type_distribution['academic'] > 0) {
            $bonus += 0.20;
        }
        
        return min(1.0, $bonus);
    }
}