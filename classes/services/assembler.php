<?php
/**
 * Assembler Service - Maps NB outputs to TSX-style HTML
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

/**
 * Assembler class
 * 
 * Maps NB JSON outputs into TSX-style HTML structure for report rendering.
 * PRD Section 11 - Architecture Overview / Key Services
 */
class assembler {
    
    /**
     * Assemble complete report from NB results
     * 
     * @param int $runid Run ID
     * @param int $comparisonid Optional comparison ID
     * @return array Report data for Mustache template
     */
    public function assemble_report(int $runid, int $comparisonid = null): array {
        global $DB, $OUTPUT;
        
        // Get run details with company info
        $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);
        $company = $DB->get_record('local_ci_company', ['id' => $run->companyid], '*', MUST_EXIST);
        
        // Get telemetry data
        $telemetry = $this->get_run_telemetry($runid);
        
        // Get all NB results for this run
        $nbresults = $DB->get_records('local_ci_nb_result', 
            ['runid' => $runid], 
            'nbcode ASC'
        );
        
        // Decode JSON payloads and citations
        foreach ($nbresults as $key => $result) {
            if (!empty($result->jsonpayload)) {
                $nbresults[$key]->data = json_decode($result->jsonpayload, true);
            }
            if (!empty($result->citations)) {
                $nbresults[$key]->citations = json_decode($result->citations, true);
            }
        }
        
        // Map to TSX phase structure
        $phases = $this->map_to_phases($nbresults);
        
        // Get comparison data if provided
        $comparison = null;
        $targetcompany = null;
        if ($comparisonid) {
            $comparison = $DB->get_record('local_ci_comparison', ['id' => $comparisonid]);
            if ($comparison) {
                $targetcompany = $DB->get_record('local_ci_company', 
                    ['id' => $comparison->targetcompanyid]
                );
            }
        }
        
        // Get snapshots for version selector
        $snapshots = $this->get_snapshots($run->companyid);
        
        // Calculate progress
        $totalnbs = 15;
        $completednbs = 0;
        foreach ($nbresults as $result) {
            if ($result->status === 'completed') {
                $completednbs++;
            }
        }
        
        // Build template data
        $templatedata = [
            'company' => $company,
            'targetcompany' => $targetcompany,
            'run' => $run,
            'phases' => $phases,
            'snapshots' => $snapshots,
            'currentsnapshotid' => $run->id,
            'progress' => [
                'completed' => $completednbs,
                'total' => $totalnbs,
                'percentage' => round(($completednbs / $totalnbs) * 100)
            ],
            'comparison' => $comparison,
            'generateddate' => userdate(time()),
            'runtime' => $this->format_runtime($run->timestarted ?? 0, $run->timecompleted ?? time()),
            'telemetry' => $telemetry,
            'exporturl' => new \moodle_url('/local/customerintel/export.php', ['runid' => $runid]),
            'dashboardurl' => new \moodle_url('/local/customerintel/dashboard.php'),
            'hasdiff' => false,
            'showdiffcontrol' => count($snapshots) > 1
        ];
        
        return $templatedata;
    }
    
    /**
     * Map NB results to TSX phase structure
     * 
     * @param array $nbresults Array of NB results indexed by NB code
     * @return array Organized by TSX phases
     */
    protected function map_to_phases(array $nbresults): array {
        // Define NB to Phase mapping based on TSX structure
        $nbmapping = [
            'NB1' => ['phase' => 1, 'title' => 'Executive Pressure Profile'],
            'NB2' => ['phase' => 1, 'title' => 'Operating Environment'],
            'NB3' => ['phase' => 2, 'title' => 'Financial Health & Trajectory'],
            'NB4' => ['phase' => 4, 'title' => 'Strategic Priorities'],
            'NB5' => ['phase' => 2, 'title' => 'Margin & Cost Analysis'],
            'NB6' => ['phase' => 6, 'title' => 'Technology & Digital Maturity'],
            'NB7' => ['phase' => 5, 'title' => 'Operational Excellence'],
            'NB8' => ['phase' => 7, 'title' => 'Competitive Positioning'],
            'NB9' => ['phase' => 4, 'title' => 'Growth & Expansion'],
            'NB10' => ['phase' => 5, 'title' => 'Risk & Resilience'],
            'NB11' => ['phase' => 3, 'title' => 'Leadership & Culture'],
            'NB12' => ['phase' => 3, 'title' => 'Stakeholder Dynamics'],
            'NB13' => ['phase' => 4, 'title' => 'Innovation Capacity'],
            'NB14' => ['phase' => 8, 'title' => 'Strategic Synthesis'],
            'NB15' => ['phase' => 9, 'title' => 'Strategic Inflection Analysis']
        ];
        
        $phases = [
            [
                'phase_id' => 'phase1',
                'title' => 'Phase 1: Customer Fundamentals',
                'color' => 'bg-indigo-50 border-indigo-300',
                'header_color' => 'bg-indigo-600',
                'time_estimate' => '25 min',
                'items' => [],
                'expanded' => true
            ],
            [
                'phase_id' => 'phase2',
                'title' => 'Phase 2: Financial Performance & Pressures',
                'color' => 'bg-indigo-50 border-indigo-300',
                'header_color' => 'bg-indigo-700',
                'time_estimate' => '30 min',
                'items' => [],
                'expanded' => false
            ],
            [
                'phase_id' => 'phase3',
                'title' => 'Phase 3: Leadership & Decision-Makers',
                'color' => 'bg-indigo-50 border-indigo-300',
                'header_color' => 'bg-indigo-800',
                'time_estimate' => '35 min',
                'items' => [],
                'expanded' => false
            ],
            [
                'phase_id' => 'phase4',
                'title' => 'Phase 4: Strategic Initiatives & Expansion',
                'color' => 'bg-indigo-50 border-indigo-300',
                'header_color' => 'bg-indigo-900',
                'time_estimate' => '30 min',
                'items' => [],
                'expanded' => false
            ],
            [
                'phase_id' => 'phase5',
                'title' => 'Phase 5: Operational Challenges',
                'color' => 'bg-indigo-50 border-indigo-300',
                'header_color' => 'bg-indigo-950',
                'time_estimate' => '25 min',
                'items' => [],
                'expanded' => false
            ],
            [
                'phase_id' => 'phase6',
                'title' => 'Phase 6: Technology & Systems',
                'color' => 'bg-indigo-50 border-indigo-300',
                'header_color' => 'bg-purple-900',
                'time_estimate' => '20 min',
                'items' => [],
                'expanded' => false
            ],
            [
                'phase_id' => 'phase7',
                'title' => 'Phase 7: Competitive Dynamics',
                'color' => 'bg-indigo-50 border-indigo-300',
                'header_color' => 'bg-violet-900',
                'time_estimate' => '25 min',
                'items' => [],
                'expanded' => false
            ],
            [
                'phase_id' => 'phase8',
                'title' => 'Phase 8: Relationship with Target Company',
                'color' => 'bg-indigo-50 border-indigo-300',
                'header_color' => 'bg-blue-900',
                'time_estimate' => '30 min',
                'items' => [],
                'expanded' => false
            ],
            [
                'phase_id' => 'phase9',
                'title' => 'Phase 9: Timing & Catalysts',
                'color' => 'bg-indigo-50 border-indigo-300',
                'header_color' => 'bg-cyan-900',
                'time_estimate' => '20 min',
                'items' => [],
                'expanded' => false
            ]
        ];
        
        // Map NB results to appropriate phases
        foreach ($nbresults as $nbresult) {
            // Normalize NB code to handle both "NB-14" and "NB14" formats
            $normalizedcode = str_replace('-', '', $nbresult->nbcode);
            $nbresult->nbcode = $normalizedcode;
            
            if (isset($nbmapping[$normalizedcode])) {
                $mapping = $nbmapping[$normalizedcode];
                $phaseindex = $mapping['phase'] - 1;
                
                // Build item from NB result
                $response_html = $this->format_nb_response($nbresult);
                $prompt_text = $this->get_prompt_for_nb($normalizedcode);
                $citations = $nbresult->citations ?? [];

                $item = [
                    'item_id' => $normalizedcode,
                    'title' => $mapping['title'],
                    'prompt' => !empty($prompt_text) ? ['prompt_text' => $prompt_text] : null,
                    'response_html' => $response_html,
                    'has_response' => !empty($response_html),
                    'citations' => $citations,
                    'citation_count' => count($citations),
                    'has_citations' => !empty($citations),
                    'status' => $nbresult->status
                ];
                
                $phases[$phaseindex]['items'][] = $item;
            }
        }
        
        // Add item count to each phase
        foreach ($phases as &$phase) {
            $phase['item_count'] = count($phase['items']);
        }
        
        return $phases;
    }
    
    /**
     * Render phase section HTML
     * 
     * @param array $phase Phase data
     * @param int $phasenum Phase number
     * @return string HTML
     * 
     * TODO: Match TSX component structure
     */
    protected function render_phase(array $phase, int $phasenum): string {
        $html = '<div class="phase-section">';
        $html .= '<h2>' . $phase['title'] . '</h2>';
        
        foreach ($phase['items'] as $item) {
            $html .= $this->render_item($item);
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render individual item block
     * 
     * @param array $item Item data
     * @return string HTML
     * 
     * TODO: Include prompt, response, citations
     */
    protected function render_item(array $item): string {
        $html = '<div class="item-block">';
        $html .= '<h3>' . $item['title'] . '</h3>';
        $html .= '<div class="prompt">' . $item['prompt'] . '</div>';
        $html .= '<div class="response">' . $item['response'] . '</div>';
        
        if (!empty($item['citations'])) {
            $html .= $this->render_citations($item['citations']);
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render citations block
     * 
     * @param array $citations Citation data
     * @return string HTML
     * 
     * TODO: Implement per PRD Section 8.4
     */
    protected function render_citations(array $citations): string {
        $html = '<div class="citations">';
        $html .= '<h4>Sources</h4>';
        $html .= '<ul>';
        
        foreach ($citations as $citation) {
            $html .= '<li>' . $this->format_citation($citation) . '</li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate citation list for NB
     * 
     * @param string $nbcode NB code
     * @param array $citations Citation data
     * @return array Formatted citations for template
     */
    public function generate_citation_list(string $nbcode, array $citations): array {
        global $DB;
        
        $formattedcitations = [];
        
        foreach ($citations as $citation) {
            $sourceid = $citation['source_id'] ?? 0;
            
            // Get source details
            $source = null;
            if ($sourceid > 0) {
                $source = $DB->get_record('local_ci_source', ['id' => $sourceid]);
            }
            
            $formattedcitation = [
                'id' => 'cite_' . $nbcode . '_' . $sourceid,
                'sourceid' => $sourceid,
                'title' => $source ? $source->title : 'Unknown Source',
                'url' => $source ? $source->url : '#',
                'type' => $source ? $source->type : 'unknown',
                'quote' => $citation['quote'] ?? '',
                'page' => $citation['page'] ?? '',
                'hasurl' => !empty($source->url),
                'icon' => $this->get_source_icon($source ? $source->type : 'unknown')
            ];
            
            // Add publish date if available
            if ($source && $source->publishedat) {
                $formattedcitation['date'] = userdate($source->publishedat, get_string('strftimedateshort'));
            }
            
            $formattedcitations[] = $formattedcitation;
        }
        
        return $formattedcitations;
    }
    
    /**
     * Format single citation
     * 
     * @param array $citation Citation data
     * @return string Formatted citation
     */
    protected function format_citation(array $citation): string {
        $html = '<span class="citation-item">';
        
        if (!empty($citation['url'])) {
            $html .= '<a href="' . htmlspecialchars($citation['url']) . '" target="_blank">';
            $html .= htmlspecialchars($citation['title']);
            $html .= '</a>';
        } else {
            $html .= htmlspecialchars($citation['title']);
        }
        
        if (!empty($citation['page'])) {
            $html .= ' (p. ' . htmlspecialchars($citation['page']) . ')';
        }
        
        if (!empty($citation['date'])) {
            $html .= ', ' . htmlspecialchars($citation['date']);
        }
        
        $html .= '</span>';
        
        return $html;
    }
    
    /**
     * Render progress meter
     * 
     * @param int $completed Completed items
     * @param int $total Total items
     * @return string HTML
     * 
     * TODO: Match TSX progress component
     */
    protected function render_progress(int $completed, int $total): string {
        $percentage = round(($completed / $total) * 100);
        
        $html = '<div class="progress-meter">';
        $html .= '<div class="progress-bar" style="width: ' . $percentage . '%"></div>';
        $html .= '<span>' . $completed . '/' . $total . ' completed</span>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render version selector
     * 
     * @param array $versions Version history
     * @param int $current Current version ID
     * @return string HTML
     * 
     * TODO: Implement per PRD Section 8.5 (Versioning & Diffs)
     */
    protected function render_version_selector(array $versions, int $current): string {
        // TODO: Create dropdown for version selection
        // TODO: Add "Show changes" toggle
        
        return '';
    }
    
    /**
     * Highlight differences in content
     * 
     * @param array $diff Diff data
     * @param string $content Original content
     * @return string HTML with highlighted changes
     * 
     * TODO: Implement per PRD Section 8.5
     */
    protected function highlight_diff(array $diff, string $content): string {
        // TODO: Apply diff highlighting
        // TODO: Mark added/removed/changed sections
        
        return $content;
    }
    
    /**
     * Get prompt text for NB code
     * 
     * @param string $nbcode NB code
     * @return string Prompt text
     */
    protected function get_prompt_for_nb(string $nbcode): string {
        // Map NB codes to their prompts based on PRD
        $prompts = [
            'NB1' => 'Analyze the executive pressure profile including board expectations, investor commitments, and leadership mandates.',
            'NB2' => 'Examine the operating environment including industry dynamics, regulatory landscape, and market trends.',
            'NB3' => 'Assess financial health and trajectory including revenue trends, profitability, and capital structure.',
            'NB4' => 'Identify strategic priorities and initiatives for growth, transformation, and competitive positioning.',
            'NB5' => 'Analyze margin pressures and cost structure optimization opportunities.',
            'NB6' => 'Evaluate technology stack, digital maturity, and transformation initiatives.',
            'NB7' => 'Assess operational excellence including efficiency, quality, and scalability.',
            'NB8' => 'Analyze competitive positioning, market share, and differentiation.',
            'NB9' => 'Identify growth and expansion opportunities across markets and segments.',
            'NB10' => 'Evaluate risk factors and resilience capabilities.',
            'NB11' => 'Analyze leadership dynamics, culture, and organizational capabilities.',
            'NB12' => 'Map stakeholder relationships and decision-making dynamics.',
            'NB13' => 'Assess innovation capacity and R&D capabilities.',
            'NB14' => 'Synthesize strategic insights and key themes.',
            'NB15' => 'Identify strategic inflection points and timing considerations.'
        ];
        
        return $prompts[$nbcode] ?? 'Process and analyze the relevant data.';
    }
    
    /**
     * Format NB response for display
     * 
     * @param \stdClass $nbresult NB result record
     * @return string Formatted response HTML
     */
    protected function format_nb_response(\stdClass $nbresult): string {
        if (empty($nbresult->data)) {
            return '<p class="text-muted">Processing...</p>';
        }
        
        $html = '';
        $data = $nbresult->data;
        
        // Format based on NB type - each has different JSON structure
        switch ($nbresult->nbcode) {
            case 'NB1':
                $html = $this->format_executive_pressure($data);
                break;
            case 'NB2':
                $html = $this->format_operating_environment($data);
                break;
            case 'NB3':
                $html = $this->format_financial_health($data);
                break;
            case 'NB4':
                $html = $this->format_strategic_priorities($data);
                break;
            case 'NB5':
                $html = $this->format_margin_analysis($data);
                break;
            case 'NB6':
                $html = $this->format_technology_maturity($data);
                break;
            case 'NB7':
                $html = $this->format_operational_excellence($data);
                break;
            case 'NB8':
                $html = $this->format_competitive_positioning($data);
                break;
            case 'NB9':
                $html = $this->format_growth_expansion($data);
                break;
            case 'NB10':
                $html = $this->format_risk_resilience($data);
                break;
            case 'NB11':
                $html = $this->format_leadership_culture($data);
                break;
            case 'NB12':
                $html = $this->format_stakeholder_dynamics($data);
                break;
            case 'NB13':
                $html = $this->format_innovation_capacity($data);
                break;
            case 'NB14':
                $html = $this->format_strategic_synthesis($data);
                break;
            case 'NB15':
                $html = $this->format_inflection_analysis($data);
                break;
            default:
                $html = $this->format_generic_response($data);
        }
        
        return $html;
    }
    
    /**
     * Format executive pressure NB1 response
     * 
     * @param array $data NB1 data
     * @return string Formatted HTML
     */
    protected function format_executive_pressure(array $data): string {
        $html = '<div class="nb-response">';
        
        if (!empty($data['board_expectations'])) {
            $html .= '<h4>Board Expectations</h4>';
            $html .= '<ul>';
            foreach ($data['board_expectations'] as $expectation) {
                $html .= '<li>' . htmlspecialchars($expectation) . '</li>';
            }
            $html .= '</ul>';
        }
        
        if (!empty($data['investor_commitments'])) {
            $html .= '<h4>Investor Commitments</h4>';
            $html .= '<ul>';
            foreach ($data['investor_commitments'] as $commitment) {
                $html .= '<li>' . htmlspecialchars($commitment) . '</li>';
            }
            $html .= '</ul>';
        }
        
        if (!empty($data['executive_mandates'])) {
            $html .= '<h4>Executive Mandates</h4>';
            $html .= '<ul>';
            foreach ($data['executive_mandates'] as $mandate) {
                $html .= '<li>' . htmlspecialchars($mandate) . '</li>';
            }
            $html .= '</ul>';
        }
        
        if (!empty($data['pressure_points'])) {
            $html .= '<h4>Key Pressure Points</h4>';
            $html .= '<ul>';
            foreach ($data['pressure_points'] as $point) {
                $html .= '<li>' . htmlspecialchars($point) . '</li>';
            }
            $html .= '</ul>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Format generic response for any NB
     * 
     * @param array $data NB data
     * @return string Formatted HTML
     */
    protected function format_generic_response(array $data): string {
        $html = '<div class="nb-response">';
        
        // Look for common summary fields first
        if (isset($data['summary']) && is_string($data['summary'])) {
            $html .= '<div class="nb-summary">';
            $html .= '<p>' . htmlspecialchars($data['summary']) . '</p>';
            $html .= '</div>';
        }
        
        foreach ($data as $key => $value) {
            // Skip summary if already processed
            if ($key === 'summary') {
                continue;
            }
            
            $label = $this->format_field_label($key);
            
            if (is_array($value)) {
                // Handle nested objects and arrays
                if ($this->is_associative_array($value)) {
                    $html .= $this->format_nested_object($label, $value);
                } else {
                    $html .= $this->format_list_section($label, $value);
                }
            } else if (is_string($value) || is_numeric($value)) {
                // Only show non-empty values
                if (!empty($value) || $value === 0) {
                    $html .= '<div class="nb-field">';
                    $html .= '<h5>' . htmlspecialchars($label) . '</h5>';
                    $html .= '<p>' . htmlspecialchars($value) . '</p>';
                    $html .= '</div>';
                }
            }
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Format operating environment NB2 response
     */
    protected function format_operating_environment($data) {
        return $this->format_structured_response($data, [
            'industry_dynamics' => 'Industry Dynamics',
            'market_trends' => 'Market Trends',
            'regulatory_landscape' => 'Regulatory Environment',
            'competitive_forces' => 'Competitive Forces',
            'summary' => 'Environment Summary'
        ]);
    }
    
    /**
     * Format financial health NB3 response
     */
    protected function format_financial_health($data) {
        return $this->format_structured_response($data, [
            'revenue_trends' => 'Revenue Trends',
            'profitability' => 'Profitability Analysis',
            'capital_structure' => 'Capital Structure',
            'financial_ratios' => 'Key Financial Ratios',
            'cash_flow' => 'Cash Flow Analysis',
            'summary' => 'Financial Health Summary'
        ]);
    }
    
    /**
     * Format strategic priorities NB4 response
     */
    protected function format_strategic_priorities($data) {
        return $this->format_structured_response($data, [
            'strategic_initiatives' => 'Strategic Initiatives',
            'growth_priorities' => 'Growth Priorities',
            'transformation_goals' => 'Transformation Goals',
            'competitive_positioning' => 'Competitive Strategy',
            'summary' => 'Strategic Priorities Summary'
        ]);
    }
    
    /**
     * Format margin analysis NB5 response
     */
    protected function format_margin_analysis($data) {
        return $this->format_structured_response($data, [
            'margin_pressures' => 'Margin Pressures',
            'cost_structure' => 'Cost Structure',
            'optimization_opportunities' => 'Optimization Opportunities',
            'pricing_strategy' => 'Pricing Strategy',
            'summary' => 'Margin Analysis Summary'
        ]);
    }
    
    /**
     * Format technology maturity NB6 response
     */
    protected function format_technology_maturity($data) {
        return $this->format_structured_response($data, [
            'technology_stack' => 'Technology Stack',
            'digital_maturity' => 'Digital Maturity Level',
            'transformation_initiatives' => 'Digital Transformation',
            'infrastructure' => 'IT Infrastructure',
            'capabilities' => 'Technology Capabilities',
            'summary' => 'Technology Assessment Summary'
        ]);
    }
    
    /**
     * Format operational excellence NB7 response
     */
    protected function format_operational_excellence($data) {
        return $this->format_structured_response($data, [
            'operational_efficiency' => 'Operational Efficiency',
            'quality_metrics' => 'Quality Metrics',
            'scalability' => 'Scalability Assessment',
            'process_optimization' => 'Process Optimization',
            'summary' => 'Operational Excellence Summary'
        ]);
    }
    
    /**
     * Format competitive positioning NB8 response
     */
    protected function format_competitive_positioning($data) {
        return $this->format_structured_response($data, [
            'market_position' => 'Market Position',
            'competitive_advantages' => 'Competitive Advantages',
            'market_share' => 'Market Share Analysis',
            'differentiation' => 'Differentiation Strategy',
            'competitive_threats' => 'Competitive Threats',
            'summary' => 'Competitive Position Summary'
        ]);
    }
    
    /**
     * Format growth expansion NB9 response
     */
    protected function format_growth_expansion($data) {
        return $this->format_structured_response($data, [
            'growth_opportunities' => 'Growth Opportunities',
            'expansion_plans' => 'Expansion Plans',
            'market_expansion' => 'Market Expansion',
            'product_expansion' => 'Product/Service Expansion',
            'geographic_expansion' => 'Geographic Expansion',
            'summary' => 'Growth Strategy Summary'
        ]);
    }
    
    /**
     * Format risk resilience NB10 response
     */
    protected function format_risk_resilience($data) {
        return $this->format_structured_response($data, [
            'risk_factors' => 'Risk Factors',
            'resilience_capabilities' => 'Resilience Capabilities',
            'risk_mitigation' => 'Risk Mitigation Strategies',
            'business_continuity' => 'Business Continuity',
            'summary' => 'Risk & Resilience Summary'
        ]);
    }
    
    /**
     * Format leadership culture NB11 response
     */
    protected function format_leadership_culture($data) {
        return $this->format_structured_response($data, [
            'leadership_team' => 'Leadership Team',
            'organizational_culture' => 'Organizational Culture',
            'leadership_style' => 'Leadership Style',
            'cultural_initiatives' => 'Cultural Initiatives',
            'talent_management' => 'Talent Management',
            'summary' => 'Leadership & Culture Summary'
        ]);
    }
    
    /**
     * Format stakeholder dynamics NB12 response
     */
    protected function format_stakeholder_dynamics($data) {
        return $this->format_structured_response($data, [
            'key_stakeholders' => 'Key Stakeholders',
            'stakeholder_relationships' => 'Stakeholder Relationships',
            'decision_making_process' => 'Decision-Making Process',
            'influence_mapping' => 'Influence Mapping',
            'stakeholder_concerns' => 'Stakeholder Concerns',
            'summary' => 'Stakeholder Dynamics Summary'
        ]);
    }
    
    /**
     * Format innovation capacity NB13 response
     */
    protected function format_innovation_capacity($data) {
        return $this->format_structured_response($data, [
            'innovation_capabilities' => 'Innovation Capabilities',
            'rd_investments' => 'R&D Investments',
            'innovation_pipeline' => 'Innovation Pipeline',
            'innovation_culture' => 'Innovation Culture',
            'technology_adoption' => 'Technology Adoption',
            'summary' => 'Innovation Capacity Summary'
        ]);
    }
    
    /**
     * Format strategic synthesis NB14 response
     */
    protected function format_strategic_synthesis($data) {
        return $this->format_structured_response($data, [
            'key_insights' => 'Key Strategic Insights',
            'strategic_themes' => 'Strategic Themes',
            'critical_success_factors' => 'Critical Success Factors',
            'strategic_recommendations' => 'Strategic Recommendations',
            'synthesis' => 'Strategic Synthesis',
            'summary' => 'Overall Strategic Assessment'
        ]);
    }
    
    /**
     * Format inflection analysis NB15 response
     */
    protected function format_inflection_analysis($data) {
        return $this->format_structured_response($data, [
            'inflection_points' => 'Strategic Inflection Points',
            'timing_considerations' => 'Timing Considerations',
            'catalysts' => 'Market Catalysts',
            'decision_windows' => 'Decision Windows',
            'urgency_factors' => 'Urgency Factors',
            'summary' => 'Inflection Analysis Summary'
        ]);
    }
    
    /**
     * Get snapshots for version selector
     * 
     * @param int $companyid Company ID
     * @return array Snapshots
     */
    protected function get_snapshots(int $companyid): array {
        global $DB;
        
        $sql = "SELECT s.*, r.status as run_status
                FROM {local_ci_snapshot} s
                JOIN {local_ci_run} r ON s.runid = r.id
                WHERE s.companyid = :companyid
                ORDER BY s.timecreated DESC";
        
        $snapshots = $DB->get_records_sql($sql, ['companyid' => $companyid]);
        
        $versions = [];
        foreach ($snapshots as $snapshot) {
            $versions[] = [
                'id' => $snapshot->id,
                'runid' => $snapshot->runid,
                'date' => userdate($snapshot->timecreated, get_string('strftimedatetimeshort')),
                'status' => $snapshot->run_status
            ];
        }
        
        return $versions;
    }
    
    /**
     * Apply diff highlighting to report data
     * 
     * @param array $reportdata Report data
     * @param array $diff Diff data from versioning service
     * @return array Modified report data with diff highlights
     */
    public function apply_diff_highlighting(array $reportdata, array $diff): array {
        // Process each NB diff
        if (!empty($diff['nb_diffs'])) {
            foreach ($diff['nb_diffs'] as $nbdiff) {
                $nbcode = $nbdiff['nb_code'];
                
                // Find the corresponding phase/section in report data
                foreach ($reportdata['phases'] as &$phase) {
                    if (isset($phase['sections'])) {
                        foreach ($phase['sections'] as &$section) {
                            if (isset($section['nbcode']) && $section['nbcode'] === $nbcode) {
                                // Add diff metadata to section
                                $section['has_changes'] = true;
                                $section['diff'] = $nbdiff;
                                
                                // Mark specific changed fields
                                if (!empty($nbdiff['changed'])) {
                                    $section['changed_fields'] = array_keys($nbdiff['changed']);
                                }
                                if (!empty($nbdiff['added'])) {
                                    $section['added_fields'] = array_keys($nbdiff['added']);
                                }
                                if (!empty($nbdiff['removed'])) {
                                    $section['removed_fields'] = array_keys($nbdiff['removed']);
                                }
                                
                                // Add CSS classes for highlighting
                                $section['diff_class'] = $this->get_diff_class($nbdiff);
                            }
                        }
                    }
                }
            }
        }
        
        // Add summary of changes
        $reportdata['change_summary'] = $this->build_change_summary($diff);
        
        return $reportdata;
    }
    
    /**
     * Get CSS class for diff highlighting
     * 
     * @param array $nbdiff NB diff data
     * @return string CSS class name
     */
    protected function get_diff_class(array $nbdiff): string {
        $haschanges = !empty($nbdiff['changed']);
        $hasadditions = !empty($nbdiff['added']);
        $hasremovals = !empty($nbdiff['removed']);
        
        if ($haschanges && $hasadditions && $hasremovals) {
            return 'diff-major-changes';
        } else if ($haschanges || ($hasadditions && $hasremovals)) {
            return 'diff-moderate-changes';
        } else if ($hasadditions) {
            return 'diff-additions-only';
        } else if ($hasremovals) {
            return 'diff-removals-only';
        }
        
        return 'diff-minor-changes';
    }
    
    /**
     * Build change summary
     * 
     * @param array $diff Complete diff data
     * @return array Change summary
     */
    protected function build_change_summary(array $diff): array {
        $summary = [
            'total_nb_changes' => count($diff['nb_diffs'] ?? []),
            'changes_by_type' => [
                'added' => 0,
                'changed' => 0,
                'removed' => 0
            ],
            'affected_nbs' => []
        ];
        
        foreach ($diff['nb_diffs'] ?? [] as $nbdiff) {
            $summary['affected_nbs'][] = $nbdiff['nb_code'];
            
            if (!empty($nbdiff['added'])) {
                $summary['changes_by_type']['added'] += count($nbdiff['added']);
            }
            if (!empty($nbdiff['changed'])) {
                $summary['changes_by_type']['changed'] += count($nbdiff['changed']);
            }
            if (!empty($nbdiff['removed'])) {
                $summary['changes_by_type']['removed'] += count($nbdiff['removed']);
            }
        }
        
        return $summary;
    }
    
    /**
     * Render diff view comparing snapshots
     * 
     * @param int $currentsnapshotid Current snapshot ID
     * @param int $previoussnapshotid Previous snapshot ID
     * @return array Diff data for template
     */
    public function render_diff_view(int $currentsnapshotid, int $previoussnapshotid): array {
        global $DB;
        
        require_once(__DIR__ . '/versioning_service.php');
        $versioningservice = new versioning_service();
        
        // Get or create diff
        $diff = $versioningservice->get_or_create_diff($previoussnapshotid, $currentsnapshotid);
        
        if (!$diff) {
            return [];
        }
        
        // Parse diff JSON
        $diffjson = json_decode($diff->diffjson, true);
        
        // Format diff for display
        $formatteddiff = [
            'hasdiff' => true,
            'fromdate' => userdate($diff->fromsnapshotid, get_string('strftimedatetimeshort')),
            'todate' => userdate($diff->tosnapshotid, get_string('strftimedatetimeshort')),
            'summary' => $this->format_diff_summary($diffjson),
            'nbchanges' => $this->format_nb_changes($diffjson)
        ];
        
        return $formatteddiff;
    }
    
    /**
     * Format diff summary
     * 
     * @param array $diffjson Diff data
     * @return array Summary data
     */
    protected function format_diff_summary(array $diffjson): array {
        $added = 0;
        $changed = 0;
        $removed = 0;
        
        foreach ($diffjson['nb_diffs'] ?? [] as $nbdiff) {
            $added += count($nbdiff['added'] ?? []);
            $changed += count($nbdiff['changed'] ?? []);
            $removed += count($nbdiff['removed'] ?? []);
        }
        
        return [
            'total' => $added + $changed + $removed,
            'added' => $added,
            'changed' => $changed,
            'removed' => $removed,
            'haschanges' => ($added + $changed + $removed) > 0
        ];
    }
    
    /**
     * Format NB-level changes
     * 
     * @param array $diffjson Diff data
     * @return array Formatted NB changes
     */
    protected function format_nb_changes(array $diffjson): array {
        $changes = [];
        
        foreach ($diffjson['nb_diffs'] ?? [] as $nbdiff) {
            $nbcode = $nbdiff['nb_code'];
            
            $change = [
                'nbcode' => $nbcode,
                'title' => $this->get_nb_title($nbcode),
                'haschanges' => !empty($nbdiff['changed']) || !empty($nbdiff['added']) || !empty($nbdiff['removed']),
                'fields' => []
            ];
            
            // Format changed fields
            foreach ($nbdiff['changed'] ?? [] as $field => $values) {
                $change['fields'][] = [
                    'type' => 'changed',
                    'field' => $field,
                    'old' => $values['old'] ?? '',
                    'new' => $values['new'] ?? ''
                ];
            }
            
            // Format added fields
            foreach ($nbdiff['added'] ?? [] as $field => $value) {
                $change['fields'][] = [
                    'type' => 'added',
                    'field' => $field,
                    'value' => $value
                ];
            }
            
            // Format removed fields
            foreach ($nbdiff['removed'] ?? [] as $field => $value) {
                $change['fields'][] = [
                    'type' => 'removed',
                    'field' => $field,
                    'value' => $value
                ];
            }
            
            if ($change['haschanges']) {
                $changes[] = $change;
            }
        }
        
        return $changes;
    }
    
    /**
     * Get NB title
     * 
     * @param string $nbcode NB code
     * @return string NB title
     */
    protected function get_nb_title(string $nbcode): string {
        $nbmapping = [
            'NB1' => 'Executive Pressure Profile',
            'NB2' => 'Operating Environment',
            'NB3' => 'Financial Health & Trajectory',
            'NB4' => 'Strategic Priorities',
            'NB5' => 'Margin & Cost Analysis',
            'NB6' => 'Technology & Digital Maturity',
            'NB7' => 'Operational Excellence',
            'NB8' => 'Competitive Positioning',
            'NB9' => 'Growth & Expansion',
            'NB10' => 'Risk & Resilience',
            'NB11' => 'Leadership & Culture',
            'NB12' => 'Stakeholder Dynamics',
            'NB13' => 'Innovation Capacity',
            'NB14' => 'Strategic Synthesis',
            'NB15' => 'Strategic Inflection Analysis'
        ];
        
        return $nbmapping[$nbcode] ?? $nbcode;
    }
    
    /**
     * Get run telemetry
     * 
     * @param int $runid Run ID
     * @return array Telemetry data
     */
    protected function get_run_telemetry(int $runid): array {
        global $DB;
        
        // Get telemetry records
        $telemetryrecords = $DB->get_records('local_ci_telemetry', ['runid' => $runid]);
        
        $totaltokens = 0;
        $totalduration = 0;
        $totalcost = 0;
        
        foreach ($telemetryrecords as $record) {
            if (strpos($record->metrickey, '_tokens') !== false) {
                $totaltokens += $record->metricvaluenum;
            }
            if (strpos($record->metrickey, '_duration_ms') !== false) {
                $totalduration += $record->metricvaluenum;
            }
            if (strpos($record->metrickey, '_cost') !== false) {
                $totalcost += $record->metricvaluenum;
            }
        }
        
        return [
            'tokens' => number_format($totaltokens),
            'duration' => $this->format_duration($totalduration),
            'cost' => '$' . number_format($totalcost, 4),
            'records' => count($telemetryrecords)
        ];
    }
    
    /**
     * Format runtime
     * 
     * @param int $start Start timestamp
     * @param int $end End timestamp
     * @return string Formatted runtime
     */
    protected function format_runtime($start, $end): string {
        if (empty($start) || empty($end)) {
            return 'N/A';
        }
        
        $duration = $end - $start;
        return $this->format_duration($duration * 1000);
    }
    
    /**
     * Format duration in milliseconds
     * 
     * @param float $ms Duration in milliseconds
     * @return string Formatted duration
     */
    protected function format_duration(float $ms): string {
        if ($ms < 1000) {
            return round($ms) . 'ms';
        } elseif ($ms < 60000) {
            return round($ms / 1000, 1) . 's';
        } else {
            $minutes = floor($ms / 60000);
            $seconds = round(($ms % 60000) / 1000);
            return $minutes . 'm ' . $seconds . 's';
        }
    }
    
    /**
     * Get icon for source type
     * 
     * @param string $type Source type
     * @return string Icon HTML
     */
    protected function get_source_icon(string $type): string {
        switch ($type) {
            case 'url':
                return '<i class="fa fa-link"></i>';
            case 'file':
                return '<i class="fa fa-file"></i>';
            case 'manual_text':
                return '<i class="fa fa-edit"></i>';
            default:
                return '<i class="fa fa-circle"></i>';
        }
    }
    
    /**
     * Format field label for better readability
     * 
     * @param string $key Field key
     * @return string Formatted label
     */
    protected function format_field_label(string $key): string {
        // Convert snake_case to Title Case
        $label = str_replace('_', ' ', $key);
        $label = ucwords($label);
        
        // Handle common abbreviations
        $label = str_replace(['Rd', 'Id', 'Url'], ['R&D', 'ID', 'URL'], $label);
        
        return $label;
    }
    
    /**
     * Check if array is associative (has string keys)
     * 
     * @param array $array Array to check
     * @return bool True if associative
     */
    protected function is_associative_array(array $array): bool {
        if (empty($array)) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }
    
    /**
     * Format a list section with proper HTML structure
     * 
     * @param string $label Section label
     * @param array $items List items
     * @return string Formatted HTML
     */
    protected function format_list_section(string $label, array $items): string {
        if (empty($items)) {
            return '';
        }
        
        $html = '<div class="nb-section">';
        $html .= '<h5>' . htmlspecialchars($label) . '</h5>';
        
        $hasNonEmptyItems = false;
        $listHtml = '<ul>';
        
        foreach ($items as $item) {
            if (is_string($item) && !empty(trim($item))) {
                $listHtml .= '<li>' . htmlspecialchars($item) . '</li>';
                $hasNonEmptyItems = true;
            } elseif (is_array($item) && !empty($item)) {
                // Handle nested array items
                $itemText = $this->format_array_item($item);
                if (!empty($itemText)) {
                    $listHtml .= '<li>' . $itemText . '</li>';
                    $hasNonEmptyItems = true;
                }
            }
        }
        
        $listHtml .= '</ul>';
        
        if ($hasNonEmptyItems) {
            $html .= $listHtml;
        } else {
            $html .= '<p class="text-muted">No items available</p>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Format nested object with proper HTML structure
     * 
     * @param string $label Section label
     * @param array $object Nested object
     * @return string Formatted HTML
     */
    protected function format_nested_object(string $label, array $object): string {
        $html = '<div class="nb-section">';
        $html .= '<h5>' . htmlspecialchars($label) . '</h5>';
        
        foreach ($object as $key => $value) {
            $sublabel = $this->format_field_label($key);
            
            if (is_array($value)) {
                if ($this->is_associative_array($value)) {
                    $html .= $this->format_nested_object($sublabel, $value);
                } else {
                    $html .= $this->format_list_section($sublabel, $value);
                }
            } else if (is_string($value) || is_numeric($value)) {
                if (!empty($value) || $value === 0) {
                    $html .= '<div class="nb-subfield">';
                    $html .= '<strong>' . htmlspecialchars($sublabel) . ':</strong> ';
                    $html .= htmlspecialchars($value);
                    $html .= '</div>';
                }
            }
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Format array item for display
     * 
     * @param array $item Array item
     * @return string Formatted text
     */
    protected function format_array_item(array $item): string {
        if (isset($item['name']) && isset($item['description'])) {
            return '<strong>' . htmlspecialchars($item['name']) . '</strong>: ' . htmlspecialchars($item['description']);
        } elseif (isset($item['title']) && isset($item['details'])) {
            return '<strong>' . htmlspecialchars($item['title']) . '</strong>: ' . htmlspecialchars($item['details']);
        } elseif (count($item) === 1) {
            $key = array_keys($item)[0];
            $value = $item[$key];
            return htmlspecialchars($this->format_field_label($key) . ': ' . $value);
        }
        
        // Fallback: join all values
        $values = array_filter($item, function($v) {
            return is_string($v) || is_numeric($v);
        });
        return htmlspecialchars(implode(', ', $values));
    }
    
    /**
     * Format structured response with predefined field mapping
     * 
     * @param array $data Response data
     * @param array $fieldMapping Field key to label mapping
     * @return string Formatted HTML
     */
    protected function format_structured_response(array $data, array $fieldMapping): string {
        $html = '<div class="nb-response">';
        
        // Process summary first if it exists
        if (isset($data['summary']) && !empty($data['summary'])) {
            $html .= '<div class="nb-summary">';
            $html .= '<p>' . htmlspecialchars($data['summary']) . '</p>';
            $html .= '</div>';
        }
        
        // Process fields in the order defined by field mapping
        foreach ($fieldMapping as $fieldKey => $fieldLabel) {
            if ($fieldKey === 'summary') {
                continue; // Already processed
            }
            
            if (isset($data[$fieldKey]) && !empty($data[$fieldKey])) {
                $value = $data[$fieldKey];
                
                if (is_array($value)) {
                    if ($this->is_associative_array($value)) {
                        $html .= $this->format_nested_object($fieldLabel, $value);
                    } else {
                        $html .= $this->format_list_section($fieldLabel, $value);
                    }
                } else if (is_string($value) || is_numeric($value)) {
                    $html .= '<div class="nb-field">';
                    $html .= '<h5>' . htmlspecialchars($fieldLabel) . '</h5>';
                    $html .= '<p>' . htmlspecialchars($value) . '</p>';
                    $html .= '</div>';
                }
            }
        }
        
        // Process any remaining fields not in the mapping
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $fieldMapping) || $key === 'summary') {
                continue;
            }
            
            $label = $this->format_field_label($key);
            
            if (is_array($value)) {
                if ($this->is_associative_array($value)) {
                    $html .= $this->format_nested_object($label, $value);
                } else {
                    $html .= $this->format_list_section($label, $value);
                }
            } else if (is_string($value) || is_numeric($value)) {
                if (!empty($value) || $value === 0) {
                    $html .= '<div class="nb-field">';
                    $html .= '<h5>' . htmlspecialchars($label) . '</h5>';
                    $html .= '<p>' . htmlspecialchars($value) . '</p>';
                    $html .= '</div>';
                }
            }
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Get assembled sections for synthesis engine integration
     * 
     * This method bridges assembler output to synthesis engine input format,
     * transforming NB results into structured section drafts that synthesis_engine
     * can consume instead of raw normalized inputs.
     * 
     * @param int $runid Run ID
     * @return array|null Structured sections ready for synthesis, or null if not available
     */
    public static function get_synthesis_sections(int $runid): ?array {
        global $DB;
        
        // Get all NB results for this run
        $nbresults = $DB->get_records('local_ci_nb_result', 
            ['runid' => $runid], 
            'nbcode ASC'
        );
        
        if (empty($nbresults)) {
            return null;
        }
        
        // Decode JSON payloads
        $processed_nbs = [];
        foreach ($nbresults as $result) {
            if (!empty($result->jsonpayload) && $result->status === 'completed') {
                $data = json_decode($result->jsonpayload, true);
                if (json_last_error() === JSON_ERROR_NONE && !empty($data)) {
                    $processed_nbs[$result->nbcode] = $data;
                }
            }
        }
        
        // Require minimum NB coverage for synthesis
        if (count($processed_nbs) < 10) {
            return null;
        }
        
        // Transform NB data into synthesis-ready sections
        $sections = [];
        
        // Executive Summary - derived from NB1, NB14, NB15
        $sections['executive_summary'] = self::extract_executive_summary($processed_nbs);
        
        // Margin Pressures - derived from NB5, NB3, NB10
        $sections['margin_pressures'] = self::extract_margin_pressures($processed_nbs);
        
        // Growth Levers - derived from NB4, NB9, NB13, NB6
        $sections['growth_levers'] = self::extract_growth_levers($processed_nbs);
        
        // Opportunity Blueprints - derived from NB8, NB7, NB6, NB9
        $sections['opportunity_blueprints'] = self::extract_opportunity_blueprints($processed_nbs);
        
        // Convergence Insight - derived from strategic synthesis across multiple NBs
        $sections['convergence_insight'] = self::extract_convergence_insight($processed_nbs);
        
        // Filter out empty sections
        $sections = array_filter($sections, function($section) {
            return !empty($section) && isset($section['text']) && !empty(trim($section['text']));
        });
        
        // Require minimum section coverage
        if (count($sections) < 3) {
            return null;
        }
        
        return $sections;
    }
    
    /**
     * Extract executive summary from NB results
     */
    private static function extract_executive_summary(array $nbs): array {
        $text = '';
        
        // Primary sources: NB1 (Executive Pressure), NB14 (Strategic Synthesis)
        if (!empty($nbs['NB1']['summary'])) {
            $text .= $nbs['NB1']['summary'] . ' ';
        }
        
        if (!empty($nbs['NB14']['strategic_summary'])) {
            $text .= $nbs['NB14']['strategic_summary'] . ' ';
        } else if (!empty($nbs['NB14']['summary'])) {
            $text .= $nbs['NB14']['summary'] . ' ';
        }
        
        return ['text' => trim($text)];
    }
    
    /**
     * Extract margin pressures from NB results
     */
    private static function extract_margin_pressures(array $nbs): array {
        $pressures = [];
        
        // Primary source: NB5 (Margin & Cost Analysis)
        if (!empty($nbs['NB5']['margin_pressures'])) {
            if (is_array($nbs['NB5']['margin_pressures'])) {
                $pressures = array_merge($pressures, $nbs['NB5']['margin_pressures']);
            } else {
                $pressures[] = $nbs['NB5']['margin_pressures'];
            }
        }
        
        // Secondary sources: NB3 (Financial Health), NB10 (Risk & Resilience)
        if (!empty($nbs['NB3']['cost_pressures'])) {
            if (is_array($nbs['NB3']['cost_pressures'])) {
                $pressures = array_merge($pressures, $nbs['NB3']['cost_pressures']);
            }
        }
        
        return ['items' => array_slice($pressures, 0, 5)]; // Limit to 5 key pressures
    }
    
    /**
     * Extract growth levers from NB results
     */
    private static function extract_growth_levers(array $nbs): array {
        $levers = [];
        
        // Primary sources: NB4 (Strategic Priorities), NB9 (Growth & Expansion)
        if (!empty($nbs['NB9']['growth_opportunities'])) {
            if (is_array($nbs['NB9']['growth_opportunities'])) {
                $levers = array_merge($levers, $nbs['NB9']['growth_opportunities']);
            }
        }
        
        if (!empty($nbs['NB4']['strategic_initiatives'])) {
            if (is_array($nbs['NB4']['strategic_initiatives'])) {
                $levers = array_merge($levers, $nbs['NB4']['strategic_initiatives']);
            }
        }
        
        return ['items' => array_slice($levers, 0, 4)]; // Limit to 4 key levers
    }
    
    /**
     * Extract opportunity blueprints from NB results
     */
    private static function extract_opportunity_blueprints(array $nbs): array {
        $opportunities = [];
        
        // Combine insights from multiple NBs
        $sources = ['NB8', 'NB7', 'NB6', 'NB9'];
        
        foreach ($sources as $nb) {
            if (!empty($nbs[$nb]['opportunities'])) {
                if (is_array($nbs[$nb]['opportunities'])) {
                    $opportunities = array_merge($opportunities, $nbs[$nb]['opportunities']);
                }
            }
        }
        
        return ['items' => array_slice($opportunities, 0, 3)]; // Limit to 3 key opportunities
    }
    
    /**
     * Extract convergence insight from NB results
     */
    private static function extract_convergence_insight(array $nbs): array {
        $insight = '';
        
        // Primary source: NB15 (Strategic Inflection Analysis)
        if (!empty($nbs['NB15']['convergence_analysis'])) {
            $insight = $nbs['NB15']['convergence_analysis'];
        } else if (!empty($nbs['NB15']['summary'])) {
            $insight = $nbs['NB15']['summary'];
        }
        
        // Fallback to NB14 strategic synthesis
        if (empty($insight) && !empty($nbs['NB14']['strategic_insight'])) {
            $insight = $nbs['NB14']['strategic_insight'];
        }
        
        return ['text' => trim($insight)];
    }
}