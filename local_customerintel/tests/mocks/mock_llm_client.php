<?php
/**
 * Mock LLM Client for Testing
 *
 * @package    local_customerintel
 * @category   test
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\tests\mocks;

defined('MOODLE_INTERNAL') || die();

/**
 * Mock LLM client that returns predictable JSON responses for testing
 */
class mock_llm_client {
    
    /** @var array Predefined responses for each NB */
    private static $nb_responses = [
        'NB1' => [
            'board_expectations' => [
                'Achieve 15% revenue growth YoY',
                'Expand into European markets',
                'Improve EBITDA margins by 200bps'
            ],
            'investor_commitments' => [
                'Deliver $500M in free cash flow',
                'Complete strategic acquisitions'
            ],
            'executive_mandates' => [
                'Digital transformation initiative',
                'Operational excellence program'
            ],
            'pressure_points' => [
                'Competitive pressure from new entrants',
                'Supply chain constraints'
            ]
        ],
        'NB2' => [
            'market_conditions' => [
                'Growing market at 8% CAGR',
                'Increasing regulatory requirements',
                'Shift to sustainable products'
            ],
            'competitive_landscape' => [
                'market_position' => 'Third largest player',
                'key_competitors' => ['CompetitorA', 'CompetitorB', 'CompetitorC'],
                'market_share' => '18%'
            ],
            'regulatory_environment' => [
                'compliance_status' => 'Fully compliant',
                'upcoming_regulations' => ['GDPR updates', 'ESG reporting']
            ]
        ],
        'NB3' => [
            'revenue_metrics' => [
                'current_revenue' => 2500000000,
                'growth_rate' => 12.5,
                'revenue_trend' => 'increasing'
            ],
            'profitability' => [
                'gross_margin' => 35.2,
                'operating_margin' => 18.5,
                'net_margin' => 12.3,
                'ebitda' => 462500000
            ],
            'cash_flow' => [
                'operating_cash_flow' => 380000000,
                'free_cash_flow' => 285000000,
                'cash_conversion' => 75
            ]
        ],
        'NB4' => [
            'strategic_priorities' => [
                'Digital transformation and automation',
                'Geographic expansion into Asia',
                'Product portfolio optimization',
                'Sustainability initiatives'
            ],
            'investment_areas' => [
                'technology' => 150000000,
                'infrastructure' => 80000000,
                'talent' => 45000000
            ],
            'timeline' => '3-5 year transformation'
        ],
        'NB5' => [
            'gross_margin_analysis' => [
                'current' => 35.2,
                'target' => 38.0,
                'trend' => 'improving',
                'drivers' => ['Mix shift', 'Pricing power', 'Cost reduction']
            ],
            'cost_structure' => [
                'cogs_percent' => 64.8,
                'sg_and_a_percent' => 16.7,
                'r_and_d_percent' => 3.5
            ],
            'optimization_opportunities' => [
                'Supply chain efficiency',
                'Automation potential',
                'Procurement savings'
            ]
        ],
        'NB6' => [
            'digital_maturity' => [
                'current_stage' => 'Digital Active',
                'maturity_score' => 3.5,
                'industry_benchmark' => 3.2
            ],
            'technology_stack' => [
                'erp' => 'SAP S/4HANA',
                'crm' => 'Salesforce',
                'analytics' => 'Tableau + PowerBI',
                'cloud_adoption' => '65%'
            ],
            'innovation_metrics' => [
                'it_spend_percent' => 4.2,
                'digital_revenue_percent' => 28,
                'automation_level' => 'Medium'
            ]
        ],
        'NB7' => [
            'operational_metrics' => [
                'oee' => 78.5,
                'first_pass_yield' => 94.2,
                'cycle_time_reduction' => 15
            ],
            'quality_metrics' => [
                'defect_rate' => 0.12,
                'customer_satisfaction' => 4.3,
                'nps_score' => 42
            ],
            'efficiency_initiatives' => [
                'Lean Six Sigma deployment',
                'Predictive maintenance',
                'Supply chain optimization'
            ]
        ],
        'NB8' => [
            'market_position' => [
                'rank' => 3,
                'market_share' => 18.5,
                'share_trend' => 'stable'
            ],
            'competitive_advantages' => [
                'Brand strength',
                'Distribution network',
                'Product quality',
                'Customer relationships'
            ],
            'competitive_threats' => [
                'Price competition',
                'New market entrants',
                'Technology disruption'
            ]
        ],
        'NB9' => [
            'growth_strategy' => [
                'organic_growth' => 8.5,
                'acquisition_targets' => 3,
                'new_markets' => ['Southeast Asia', 'Latin America']
            ],
            'expansion_plans' => [
                'geographic' => 'Enter 5 new countries',
                'product' => 'Launch 12 new SKUs',
                'channel' => 'Develop D2C capability'
            ],
            'growth_investment' => 250000000
        ],
        'NB10' => [
            'risk_assessment' => [
                'top_risks' => [
                    'Supply chain disruption',
                    'Cybersecurity threats',
                    'Regulatory changes',
                    'Market volatility',
                    'Talent retention'
                ],
                'risk_score' => 'Medium',
                'mitigation_status' => '75% addressed'
            ],
            'resilience_metrics' => [
                'business_continuity_score' => 4.1,
                'crisis_readiness' => 'High',
                'recovery_time_objective' => '24 hours'
            ]
        ],
        'NB11' => [
            'leadership_assessment' => [
                'ceo_tenure' => 3.5,
                'leadership_stability' => 'High',
                'succession_planning' => 'Mature'
            ],
            'culture_metrics' => [
                'employee_engagement' => 72,
                'culture_score' => 4.0,
                'values_alignment' => 'Strong'
            ],
            'talent_metrics' => [
                'turnover_rate' => 12.5,
                'key_talent_retention' => 88,
                'diversity_index' => 0.65
            ]
        ],
        'NB12' => [
            'stakeholder_map' => [
                'investors' => 'Supportive',
                'customers' => 'Satisfied',
                'employees' => 'Engaged',
                'regulators' => 'Cooperative',
                'communities' => 'Positive'
            ],
            'stakeholder_priorities' => [
                'investors' => ['Growth', 'Margins', 'Dividends'],
                'customers' => ['Quality', 'Innovation', 'Service'],
                'employees' => ['Compensation', 'Development', 'Culture']
            ]
        ],
        'NB13' => [
            'innovation_pipeline' => [
                'active_projects' => 24,
                'r_and_d_intensity' => 3.5,
                'patents_filed' => 18
            ],
            'innovation_metrics' => [
                'new_product_revenue' => 22,
                'time_to_market' => 18,
                'innovation_roi' => 2.3
            ],
            'innovation_culture' => [
                'score' => 3.8,
                'ideation_rate' => 145,
                'implementation_rate' => 18
            ]
        ],
        'NB14' => [
            'strategic_synthesis' => [
                'overall_health' => 'Strong',
                'strategic_coherence' => 'High',
                'execution_capability' => 'Good'
            ],
            'key_strengths' => [
                'Market position',
                'Financial performance',
                'Operational efficiency',
                'Leadership team'
            ],
            'key_challenges' => [
                'Digital transformation pace',
                'Talent acquisition',
                'Market saturation'
            ],
            'recommendations' => [
                'Accelerate digital initiatives',
                'Pursue strategic M&A',
                'Invest in innovation'
            ]
        ],
        'NB15' => [
            'inflection_points' => [
                'near_term' => [
                    'Digital tipping point in 6-12 months',
                    'Market consolidation opportunity'
                ],
                'medium_term' => [
                    'Sustainability regulation impact',
                    'Technology platform decision'
                ],
                'long_term' => [
                    'Business model evolution',
                    'Industry transformation'
                ]
            ],
            'strategic_options' => [
                'aggressive_growth' => 'High risk, high reward',
                'steady_expansion' => 'Moderate risk, stable returns',
                'transformation' => 'Disruptive but necessary'
            ],
            'recommended_path' => 'Balanced transformation with selective bets'
        ]
    ];
    
    /** @var int Token counter for tracking usage */
    private $token_count = 0;
    
    /** @var array Citation templates */
    private static $citation_templates = [
        ['source_id' => 101, 'title' => 'Annual Report 2023', 'page' => 45],
        ['source_id' => 102, 'title' => 'Q3 Earnings Call', 'page' => 12],
        ['source_id' => 103, 'title' => 'Investor Presentation', 'page' => 28],
        ['source_id' => 104, 'title' => 'SEC 10-K Filing', 'page' => 67],
        ['source_id' => 105, 'title' => 'Industry Analysis Report', 'page' => 15]
    ];
    
    /**
     * Execute prompt and return mock response
     * 
     * @param string $prompt The prompt to execute
     * @param array $options Options for execution
     * @return array Response with payload and metadata
     */
    public function execute_prompt(string $prompt, array $options = []): array {
        // Extract NB code from prompt
        $nbcode = $this->extract_nb_code($prompt);
        
        if (!$nbcode || !isset(self::$nb_responses[$nbcode])) {
            throw new \Exception("Invalid or unrecognized NB code in prompt");
        }
        
        // Get predefined response
        $payload = self::$nb_responses[$nbcode];
        
        // Add some variability if requested
        if (!empty($options['add_noise'])) {
            $payload = $this->add_noise($payload);
        }
        
        // Generate citations
        $citations = $this->generate_citations($nbcode);
        
        // Calculate token usage (mock)
        $input_tokens = strlen($prompt) / 4; // Rough approximation
        $output_tokens = strlen(json_encode($payload)) / 4;
        $this->token_count += $input_tokens + $output_tokens;
        
        return [
            'success' => true,
            'payload' => $payload,
            'citations' => $citations,
            'tokens_used' => [
                'input' => (int)$input_tokens,
                'output' => (int)$output_tokens,
                'total' => (int)($input_tokens + $output_tokens)
            ],
            'duration_ms' => rand(500, 2000),
            'model' => 'mock-gpt-4',
            'nb_code' => $nbcode
        ];
    }
    
    /**
     * Extract NB code from prompt
     * 
     * @param string $prompt The prompt text
     * @return string|null NB code or null
     */
    private function extract_nb_code(string $prompt): ?string {
        // Look for NB code patterns
        if (preg_match('/NB-?(\d+)/i', $prompt, $matches)) {
            $nbnum = (int)$matches[1];
            return 'NB' . $nbnum;
        }
        
        // Check for specific NB keywords
        $keywords = [
            'executive pressure' => 'NB1',
            'operating environment' => 'NB2',
            'financial health' => 'NB3',
            'strategic priorities' => 'NB4',
            'margin.*cost' => 'NB5',
            'technology.*digital' => 'NB6',
            'operational excellence' => 'NB7',
            'competitive position' => 'NB8',
            'growth.*expansion' => 'NB9',
            'risk.*resilience' => 'NB10',
            'leadership.*culture' => 'NB11',
            'stakeholder' => 'NB12',
            'innovation' => 'NB13',
            'strategic synthesis' => 'NB14',
            'inflection' => 'NB15'
        ];
        
        foreach ($keywords as $pattern => $nbcode) {
            if (preg_match('/' . $pattern . '/i', $prompt)) {
                return $nbcode;
            }
        }
        
        return null;
    }
    
    /**
     * Add noise/variability to response
     * 
     * @param array $payload Original payload
     * @return array Modified payload
     */
    private function add_noise(array $payload): array {
        // Add slight variations to numeric values
        array_walk_recursive($payload, function(&$value) {
            if (is_numeric($value) && !is_int($value)) {
                // Add ±5% variation
                $variation = $value * (rand(-5, 5) / 100);
                $value = round($value + $variation, 2);
            } elseif (is_int($value) && $value > 100) {
                // Add ±10% variation for large integers
                $variation = $value * (rand(-10, 10) / 100);
                $value = (int)($value + $variation);
            }
        });
        
        return $payload;
    }
    
    /**
     * Generate mock citations for NB
     * 
     * @param string $nbcode NB code
     * @return array Citations
     */
    private function generate_citations(string $nbcode): array {
        // Return 2-4 random citations
        $count = rand(2, 4);
        $citations = [];
        
        $available = self::$citation_templates;
        shuffle($available);
        
        for ($i = 0; $i < $count && $i < count($available); $i++) {
            $citation = $available[$i];
            $citation['relevance'] = rand(70, 95) / 100;
            $citation['nb_code'] = $nbcode;
            $citations[] = $citation;
        }
        
        return $citations;
    }
    
    /**
     * Get total tokens used
     * 
     * @return int Total tokens
     */
    public function get_total_tokens(): int {
        return (int)$this->token_count;
    }
    
    /**
     * Reset token counter
     */
    public function reset_tokens(): void {
        $this->token_count = 0;
    }
    
    /**
     * Simulate an error response
     * 
     * @param string $error_type Type of error
     * @return array Error response
     */
    public function simulate_error(string $error_type = 'timeout'): array {
        $errors = [
            'timeout' => 'Request timed out after 30 seconds',
            'rate_limit' => 'Rate limit exceeded. Please try again later.',
            'invalid_json' => 'Failed to parse response as valid JSON',
            'api_error' => 'API returned error: Internal server error',
            'network' => 'Network error: Could not connect to API endpoint'
        ];
        
        return [
            'success' => false,
            'error' => $errors[$error_type] ?? $errors['api_error'],
            'error_type' => $error_type,
            'tokens_used' => ['input' => 0, 'output' => 0, 'total' => 0],
            'duration_ms' => 0
        ];
    }
    
    /**
     * Get mock response for specific NB (direct access for testing)
     * 
     * @param string $nbcode NB code
     * @return array|null Response data
     */
    public static function get_mock_response(string $nbcode): ?array {
        return self::$nb_responses[$nbcode] ?? null;
    }
}