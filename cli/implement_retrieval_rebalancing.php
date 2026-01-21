<?php
/**
 * Retrieval Rebalancing Implementation
 * 
 * Applies the rebalancing recommendations to the NB orchestration system
 */

require_once(__DIR__ . '/../../config.php');

// Security
require_login();
$context = context_system::instance();
require_capability('local/customerintel:manage', $context);

echo "🔧 Implementing Retrieval Scope Rebalancing\n";
echo "==========================================\n\n";

// Load the rebalancing patch
$patch_file = __DIR__ . '/retrieval_scope_rebalancing_patch.json';

if (!file_exists($patch_file)) {
    echo "❌ Rebalancing patch not found. Please run rebalance_retrieval_scope.php first.\n";
    exit;
}

$patch_data = json_decode(file_get_contents($patch_file), true);
if (!$patch_data) {
    echo "❌ Could not parse rebalancing patch file.\n";
    exit;
}

echo "📦 Loaded rebalancing patch:\n";
echo "   • Created: {$patch_data['metadata']['created_at']}\n";
echo "   • Analysis Source: {$patch_data['metadata']['analysis_source']}\n";
echo "   • Citations Analyzed: {$patch_data['metadata']['total_citations_analyzed']}\n";
echo "   • Problematic Domains: {$patch_data['metadata']['problematic_domains_count']}\n\n";

// 1. Create enhanced domain configuration
echo "1️⃣ Creating Enhanced Domain Configuration\n";
echo "=========================================\n";

$domain_config = [
    'version' => '1.0',
    'last_updated' => date('Y-m-d H:i:s'),
    'domain_limits' => $patch_data['diversification_strategy']['domain_weight_limits'],
    'target_distributions' => $patch_data['diversification_strategy']['target_distributions'],
    'domain_categories' => [
        'financial_news' => [
            'priority_domains' => ['bloomberg.com', 'reuters.com', 'wsj.com', 'ft.com'],
            'weight_multiplier' => 1.2,
            'max_results_per_domain' => 3
        ],
        'industry_analysts' => [
            'priority_domains' => ['gartner.com', 'forrester.com', 'idc.com', 'frost.com'],
            'weight_multiplier' => 1.5,
            'max_results_per_domain' => 4
        ],
        'government_regulatory' => [
            'priority_domains' => ['sec.gov', 'fda.gov', 'ftc.gov', 'federalreserve.gov'],
            'weight_multiplier' => 1.8,
            'max_results_per_domain' => 2
        ],
        'academic_research' => [
            'priority_domains' => ['mit.edu', 'stanford.edu', 'harvard.edu', 'ssrn.com'],
            'weight_multiplier' => 1.3,
            'max_results_per_domain' => 2
        ],
        'business_intelligence' => [
            'priority_domains' => ['crunchbase.com', 'pitchbook.com', 'cbinsights.com', 'owler.com'],
            'weight_multiplier' => 1.4,
            'max_results_per_domain' => 3
        ]
    ],
    'diversification_domains' => $patch_data['diversification_strategy']['recommended_new_domains']
];

$domain_config_file = __DIR__ . '/config/enhanced_domain_config.json';
@mkdir(dirname($domain_config_file), 0755, true);
file_put_contents($domain_config_file, json_encode($domain_config, JSON_PRETTY_PRINT));

echo "✅ Domain configuration saved to: {$domain_config_file}\n\n";

// 2. Generate enhanced query templates
echo "2️⃣ Generating Enhanced Query Templates\n";
echo "======================================\n";

$enhanced_templates = [];

foreach ($patch_data['enhanced_query_templates'] as $template_name => $template_data) {
    $enhanced_templates[$template_name] = [
        'name' => ucwords(str_replace('_', ' ', $template_name)),
        'base_query' => $template_data['base_query'],
        'domain_weights' => $template_data['domain_weights'],
        'additional_terms' => $template_data['additional_terms'],
        'search_strategy' => [
            'max_results_per_domain' => 3,
            'min_unique_domains' => 5,
            'prefer_recent_content' => true,
            'diversification_bonus' => 0.2
        ],
        'fallback_domains' => [
            'businesswire.com',
            'prnewswire.com',
            'yahoo.com/finance',
            'marketwatch.com'
        ]
    ];
}

// Add new template types based on analysis
$enhanced_templates['customer_partnerships'] = [
    'name' => 'Customer Partnerships & Alliances',
    'base_query' => '{company_name} customer OR partnership OR alliance OR "strategic relationship"',
    'domain_weights' => [
        'businesswire.com' => 3,
        'prnewswire.com' => 3,
        'crunchbase.com' => 2,
        'linkedin.com' => 1,
        'yahoo.com/finance' => 1
    ],
    'additional_terms' => ['customer win', 'strategic partnership', 'alliance agreement', 'joint venture'],
    'search_strategy' => [
        'max_results_per_domain' => 2,
        'min_unique_domains' => 4,
        'prefer_recent_content' => true,
        'diversification_bonus' => 0.3
    ]
];

$enhanced_templates['competitive_intelligence'] = [
    'name' => 'Competitive Intelligence & Market Position',
    'base_query' => '{company_name} competitor OR "market share" OR "competitive advantage" OR rivalry',
    'domain_weights' => [
        'gartner.com' => 4,
        'forrester.com' => 4,
        'cbinsights.com' => 3,
        'owler.com' => 2,
        'similarweb.com' => 2
    ],
    'additional_terms' => ['competitive landscape', 'market position', 'competitor analysis', 'market leadership'],
    'search_strategy' => [
        'max_results_per_domain' => 3,
        'min_unique_domains' => 5,
        'prefer_recent_content' => true,
        'diversification_bonus' => 0.25
    ]
];

$template_file = __DIR__ . '/config/enhanced_query_templates.json';
file_put_contents($template_file, json_encode($enhanced_templates, JSON_PRETTY_PRINT));

echo "✅ Enhanced query templates saved to: {$template_file}\n";
echo "   • Total templates: " . count($enhanced_templates) . "\n";
echo "   • New templates added: 2 (customer partnerships, competitive intelligence)\n\n";

// 3. Create monitoring configuration
echo "3️⃣ Creating Monitoring Configuration\n";
echo "====================================\n";

$monitoring_config = [
    'version' => '1.0',
    'diversity_metrics' => [
        'domain_concentration_threshold' => 0.25, // Alert if any domain >25%
        'category_concentration_threshold' => 0.35, // Alert if any category >35%
        'minimum_unique_domains_per_run' => 15,
        'minimum_categories_represented' => 5
    ],
    'quality_metrics' => [
        'minimum_authority_score' => 0.6,
        'maximum_content_age_days' => 365,
        'minimum_relevance_score' => 0.7,
        'preferred_content_age_days' => 90
    ],
    'alerts' => [
        'domain_overrepresentation' => true,
        'low_diversity_score' => true,
        'new_domain_discovery' => true,
        'quality_threshold_violations' => true
    ],
    'reporting' => [
        'generate_diversity_report_per_run' => true,
        'track_domain_trends_over_time' => true,
        'alert_on_pattern_changes' => true
    ]
];

$monitoring_file = __DIR__ . '/config/retrieval_monitoring.json';
file_put_contents($monitoring_file, json_encode($monitoring_config, JSON_PRETTY_PRINT));

echo "✅ Monitoring configuration saved to: {$monitoring_file}\n\n";

// 4. Generate implementation code snippets
echo "4️⃣ Generating Implementation Code\n";
echo "=================================\n";

$implementation_code = [
    'domain_weight_function' => '
/**
 * Calculate domain weight based on rebalancing strategy
 */
function calculate_domain_weight($domain, $category, $current_count, $total_count) {
    $config = json_decode(file_get_contents(__DIR__ . "/config/enhanced_domain_config.json"), true);
    
    $base_weight = 1.0;
    $current_percentage = ($current_count / $total_count) * 100;
    
    // Penalize overrepresented domains
    if ($current_percentage > 25) {
        $base_weight *= 0.3; // Heavy penalty
    } elseif ($current_percentage > 15) {
        $base_weight *= 0.6; // Moderate penalty
    }
    
    // Apply category multiplier
    if (isset($config["domain_categories"][$category]["weight_multiplier"])) {
        $base_weight *= $config["domain_categories"][$category]["weight_multiplier"];
    }
    
    // Bonus for underrepresented high-value domains
    $priority_domains = $config["domain_categories"][$category]["priority_domains"] ?? [];
    if (in_array($domain, $priority_domains) && $current_percentage < 5) {
        $base_weight *= 1.5;
    }
    
    return max(0.1, min(3.0, $base_weight)); // Clamp between 0.1 and 3.0
}',
    
    'diversity_checker' => '
/**
 * Check if citation diversity meets requirements
 */
function check_citation_diversity($citations) {
    $domain_counts = [];
    $total = count($citations);
    
    foreach ($citations as $citation) {
        $domain = parse_url($citation["url"], PHP_URL_HOST);
        $domain = preg_replace("/^www\\./", "", strtolower($domain));
        $domain_counts[$domain] = ($domain_counts[$domain] ?? 0) + 1;
    }
    
    $diversity_score = 0;
    $unique_domains = count($domain_counts);
    $max_concentration = max($domain_counts) / $total * 100;
    
    // Base score from unique domain count
    $diversity_score += min(50, $unique_domains * 3);
    
    // Penalty for concentration
    if ($max_concentration > 25) {
        $diversity_score -= 30;
    } elseif ($max_concentration > 15) {
        $diversity_score -= 15;
    }
    
    // Bonus for good distribution
    if ($unique_domains >= 10 && $max_concentration < 15) {
        $diversity_score += 20;
    }
    
    return [
        "score" => max(0, min(100, $diversity_score)),
        "unique_domains" => $unique_domains,
        "max_concentration" => $max_concentration,
        "recommendations" => $max_concentration > 25 ? ["Reduce dependency on " . array_keys($domain_counts, max($domain_counts))[0]] : []
    ];
}',
    
    'query_diversifier' => '
/**
 * Diversify search query based on current domain distribution
 */
function diversify_search_query($base_query, $current_domain_counts, $target_category) {
    $templates = json_decode(file_get_contents(__DIR__ . "/config/enhanced_query_templates.json"), true);
    
    if (!isset($templates[$target_category])) {
        return $base_query;
    }
    
    $template = $templates[$target_category];
    $diversified_query = $base_query;
    
    // Add domain-specific modifiers
    $underrepresented_domains = [];
    foreach ($template["domain_weights"] as $domain => $weight) {
        $current_count = $current_domain_counts[$domain] ?? 0;
        if ($current_count < 2) { // Consider domains with <2 citations as underrepresented
            $underrepresented_domains[] = $domain;
        }
    }
    
    if (!empty($underrepresented_domains)) {
        $domain_modifier = "site:" . implode(" OR site:", array_slice($underrepresented_domains, 0, 3));
        $diversified_query = "({$base_query}) AND ({$domain_modifier})";
    }
    
    // Add additional terms for context
    if (!empty($template["additional_terms"])) {
        $additional_terms = array_slice($template["additional_terms"], 0, 2);
        $term_modifier = "(" . implode(" OR ", $additional_terms) . ")";
        $diversified_query .= " " . $term_modifier;
    }
    
    return $diversified_query;
}'
];

$code_file = __DIR__ . '/implementation_code_snippets.php';
file_put_contents($code_file, "<?php\n" . implode("\n\n", $implementation_code));

echo "✅ Implementation code snippets saved to: {$code_file}\n\n";

// 5. Create summary report
echo "5️⃣ Implementation Summary\n";
echo "========================\n";

$summary = [
    'files_created' => [
        $domain_config_file,
        $template_file,
        $monitoring_file,
        $code_file
    ],
    'key_improvements' => [
        'Domain weight limits: Max 10% per domain, 30% per category',
        'Enhanced query templates: ' . count($enhanced_templates) . ' total templates',
        'Diversification strategy: ' . array_sum(array_map('count', $domain_config['diversification_domains'])) . ' new domains recommended',
        'Monitoring system: Real-time diversity tracking and alerting',
        'Implementation code: Ready-to-use functions for NB orchestrator'
    ],
    'next_steps' => [
        'Integrate domain weight calculation into NB orchestrator',
        'Update search query generation to use enhanced templates',
        'Implement diversity monitoring in telemetry system',
        'Test rebalanced retrieval with pilot runs',
        'Monitor and adjust weights based on results'
    ]
];

foreach ($summary['files_created'] as $file) {
    echo "📁 Created: " . basename($file) . "\n";
}

echo "\n🎯 Key Improvements:\n";
foreach ($summary['key_improvements'] as $improvement) {
    echo "   • {$improvement}\n";
}

echo "\n📋 Next Steps:\n";
foreach ($summary['next_steps'] as $step) {
    echo "   • {$step}\n";
}

echo "\n💾 Saving implementation summary...\n";
$summary_file = __DIR__ . '/retrieval_rebalancing_summary.json';
file_put_contents($summary_file, json_encode($summary, JSON_PRETTY_PRINT));

echo "✅ IMPLEMENTATION COMPLETE\n";
echo "==========================\n";
echo "All configuration files and code snippets have been generated.\n";
echo "Ready for integration into the NB orchestration system.\n\n";

echo "🔧 To apply these changes:\n";
echo "1. Review generated configuration files\n";
echo "2. Integrate code snippets into nb_orchestrator.php\n";
echo "3. Update query generation logic\n";
echo "4. Enable diversity monitoring\n";
echo "5. Test with pilot intelligence runs\n";
?>