<?php
/**
 * Retrieval Scope Rebalancing Tool
 * 
 * Audits NB orchestration citations and generates rebalanced retrieval templates
 */

require_once(__DIR__ . '/../../config.php');

// Security
require_login();
$context = context_system::instance();
require_capability('local/customerintel:manage', $context);

echo "🔄 Retrieval Scope Rebalancing Analysis\n";
echo "======================================\n\n";

// Load existing analysis if available
$legacy_analysis_file = __DIR__ . '/citation_analysis.json';
$artifact_analysis_file = __DIR__ . '/artifact_citation_analysis.json';

$analysis_data = null;

if (file_exists($artifact_analysis_file)) {
    $analysis_data = json_decode(file_get_contents($artifact_analysis_file), true);
    echo "📊 Using artifact repository analysis data\n";
} elseif (file_exists($legacy_analysis_file)) {
    $analysis_data = json_decode(file_get_contents($legacy_analysis_file), true);
    echo "📊 Using legacy NB analysis data\n";
} else {
    echo "⚠️  No existing analysis found. Running citation extraction...\n\n";
    
    // Run citation analysis first
    $extraction_script = __DIR__ . '/analyze_citations.php';
    if (file_exists($extraction_script)) {
        echo "🔍 Running citation analysis...\n";
        include $extraction_script;
        echo "\n";
        
        if (file_exists($legacy_analysis_file)) {
            $analysis_data = json_decode(file_get_contents($legacy_analysis_file), true);
        }
    }
}

if (!$analysis_data || empty($analysis_data['domain_counts'])) {
    echo "❌ Could not load citation analysis data. Please run citation analysis first.\n";
    exit;
}

$domain_counts = $analysis_data['domain_counts'];
$total_citations = $analysis_data['total_citations'];

echo "📈 Analysis Summary:\n";
echo "   • Total Citations: {$total_citations}\n";
echo "   • Unique Domains: " . count($domain_counts) . "\n";
echo "   • Analysis Date: " . ($analysis_data['timestamp'] ?? 'Unknown') . "\n\n";

// Calculate percentages and identify problematic domains
arsort($domain_counts);
$problematic_domains = [];
$high_concentration_domains = [];

foreach ($domain_counts as $domain => $count) {
    $percentage = ($count / $total_citations) * 100;
    
    if ($percentage > 25) {
        $problematic_domains[] = [
            'domain' => $domain,
            'count' => $count,
            'percentage' => $percentage
        ];
    } elseif ($percentage > 15) {
        $high_concentration_domains[] = [
            'domain' => $domain,
            'count' => $count,
            'percentage' => $percentage
        ];
    }
}

echo "🚨 DOMAIN CONCENTRATION ANALYSIS:\n";
echo "=================================\n";

if (!empty($problematic_domains)) {
    echo "❌ Domains with >25% representation (CRITICAL):\n";
    foreach ($problematic_domains as $domain_data) {
        echo sprintf("   • %-30s %4d citations (%5.2f%%)\n", 
            $domain_data['domain'], $domain_data['count'], $domain_data['percentage']);
    }
    echo "\n";
} else {
    echo "✅ No domains exceed 25% threshold\n\n";
}

if (!empty($high_concentration_domains)) {
    echo "⚠️  Domains with 15-25% representation (HIGH):\n";
    foreach ($high_concentration_domains as $domain_data) {
        echo sprintf("   • %-30s %4d citations (%5.2f%%)\n", 
            $domain_data['domain'], $domain_data['count'], $domain_data['percentage']);
    }
    echo "\n";
}

// Categorize current domains
echo "📊 CURRENT DOMAIN CATEGORIZATION:\n";
echo "=================================\n";

$domain_categories = [
    'financial_news' => [
        'patterns' => ['bloomberg.com', 'reuters.com', 'wsj.com', 'ft.com', 'marketwatch.com', 'cnbc.com'],
        'description' => 'Financial & Business News'
    ],
    'general_news' => [
        'patterns' => ['cnn.com', 'bbc.com', 'nytimes.com', 'washingtonpost.com', 'guardian.com'],
        'description' => 'General News Media'
    ],
    'business_media' => [
        'patterns' => ['forbes.com', 'fortune.com', 'businessinsider.com', 'inc.com', 'fastcompany.com'],
        'description' => 'Business & Entrepreneurship Media'
    ],
    'tech_media' => [
        'patterns' => ['techcrunch.com', 'wired.com', 'ars-technica.com', 'theverge.com', 'zdnet.com'],
        'description' => 'Technology Media'
    ],
    'company_sites' => [
        'patterns' => ['.com', '.org', '.net'],
        'description' => 'Company Websites & Corporate Sites',
        'exclude' => ['bloomberg.com', 'reuters.com', 'wsj.com', 'ft.com', 'cnbc.com', 'cnn.com', 'bbc.com']
    ],
    'social_media' => [
        'patterns' => ['twitter.com', 'linkedin.com', 'facebook.com', 'youtube.com', 'instagram.com'],
        'description' => 'Social Media Platforms'
    ],
    'government' => [
        'patterns' => ['sec.gov', 'fda.gov', 'ftc.gov', '.gov', 'europa.eu'],
        'description' => 'Government & Regulatory'
    ],
    'academic' => [
        'patterns' => ['.edu', 'research', 'university', 'academic'],
        'description' => 'Academic & Research Institutions'
    ],
    'industry_reports' => [
        'patterns' => ['gartner.com', 'forrester.com', 'idc.com', 'mckinsey.com', 'bcg.com', 'deloitte.com', 'pwc.com'],
        'description' => 'Industry Analysis & Consulting'
    ]
];

$categorized_domains = [];
$category_counts = array_fill_keys(array_keys($domain_categories), 0);

foreach ($domain_counts as $domain => $count) {
    $categorized = false;
    
    foreach ($domain_categories as $category => $config) {
        $patterns = $config['patterns'];
        $exclude = $config['exclude'] ?? [];
        
        // Check if domain should be excluded
        if (in_array($domain, $exclude)) {
            continue;
        }
        
        foreach ($patterns as $pattern) {
            if (strpos($domain, $pattern) !== false) {
                $categorized_domains[$category][] = ['domain' => $domain, 'count' => $count];
                $category_counts[$category] += $count;
                $categorized = true;
                break 2;
            }
        }
    }
    
    if (!$categorized) {
        $categorized_domains['other'][] = ['domain' => $domain, 'count' => $count];
        $category_counts['other'] = ($category_counts['other'] ?? 0) + $count;
    }
}

foreach ($category_counts as $category => $count) {
    if ($count > 0) {
        $percentage = round(($count / $total_citations) * 100, 2);
        $description = $domain_categories[$category]['description'] ?? ucwords(str_replace('_', ' ', $category));
        echo sprintf("%-30s: %4d citations (%5.2f%%)\n", $description, $count, $percentage);
    }
}

echo "\n";

// Generate diversification recommendations
echo "🎯 DIVERSIFICATION RECOMMENDATIONS:\n";
echo "===================================\n";

$diversification_domains = [
    'industry_analysts' => [
        'idc.com', 'gartner.com', 'forrester.com', 'frost.com', 'grandviewresearch.com',
        'mordorintelligence.com', 'technavio.com', 'researchandmarkets.com'
    ],
    'competitor_intelligence' => [
        'owler.com', 'similarweb.com', 'crunchbase.com', 'pitchbook.com', 'cbinsights.com',
        'tracxn.com', 'dealroom.co', 'venturebeat.com'
    ],
    'customer_partnership' => [
        'businesswire.com', 'prnewswire.com', 'globenewswire.com', 'marketscreener.com',
        'yahoo.com/finance', 'investing.com', 'seekingalpha.com'
    ],
    'investor_relations' => [
        'sec.gov', 'investor.*.com', 'ir.*.com', 'investors.*.com', 'earnings.com',
        'zacks.com', 'morningstar.com', 'fool.com'
    ],
    'government_academic' => [
        'nist.gov', 'census.gov', 'bls.gov', 'federalreserve.gov', 'treasury.gov',
        'mit.edu', 'stanford.edu', 'harvard.edu', 'berkeley.edu', 'ssrn.com'
    ],
    'trade_publications' => [
        'industryweek.com', 'supplychainmanagement.com', 'logisticsmgmt.com',
        'manufacturingnews.com', 'plantservices.com', 'automationworld.com'
    ],
    'international_sources' => [
        'economist.com', 'stratfor.com', 'euromonitor.com', 'export.gov',
        'trade.gov', 'wto.org', 'oecd.org', 'worldbank.org'
    ],
    'regulatory_compliance' => [
        'compliance.com', 'thomsonreuters.com', 'lexisnexis.com', 'westlaw.com',
        'sec.gov', 'finra.org', 'cftc.gov', 'federalregister.gov'
    ]
];

foreach ($diversification_domains as $category => $domains) {
    $description = ucwords(str_replace('_', ' ', $category));
    echo "\n📂 {$description}:\n";
    
    foreach ($domains as $domain) {
        $current_count = $domain_counts[$domain] ?? 0;
        $status = $current_count > 0 ? "({$current_count} current)" : "(new)";
        echo "   • {$domain} {$status}\n";
    }
}

// Generate query templates
echo "\n🔍 ENHANCED RETRIEVAL QUERY TEMPLATES:\n";
echo "=====================================\n";

$query_templates = [
    'industry_analysis' => [
        'base_query' => '{company_name} market analysis OR industry trends OR competitive landscape',
        'domain_weights' => [
            'gartner.com' => 3,
            'forrester.com' => 3,
            'idc.com' => 3,
            'mckinsey.com' => 2,
            'bcg.com' => 2,
            'deloitte.com' => 2
        ],
        'additional_terms' => ['market share', 'industry report', 'competitive analysis', 'market research']
    ],
    'financial_performance' => [
        'base_query' => '{company_name} earnings OR financial results OR revenue OR profits',
        'domain_weights' => [
            'sec.gov' => 4,
            'bloomberg.com' => 2,
            'reuters.com' => 2,
            'wsj.com' => 2,
            'seekingalpha.com' => 1
        ],
        'additional_terms' => ['quarterly earnings', 'annual report', '10-K', '10-Q', 'investor relations']
    ],
    'strategic_initiatives' => [
        'base_query' => '{company_name} strategy OR initiatives OR partnerships OR acquisitions',
        'domain_weights' => [
            'businesswire.com' => 2,
            'prnewswire.com' => 2,
            'forbes.com' => 1,
            'fortune.com' => 1,
            'crunchbase.com' => 2
        ],
        'additional_terms' => ['strategic partnership', 'merger', 'acquisition', 'joint venture', 'collaboration']
    ],
    'regulatory_compliance' => [
        'base_query' => '{company_name} regulatory OR compliance OR investigation OR enforcement',
        'domain_weights' => [
            'sec.gov' => 4,
            'fda.gov' => 3,
            'ftc.gov' => 3,
            'reuters.com' => 1,
            'wsj.com' => 1
        ],
        'additional_terms' => ['regulatory filing', 'compliance violation', 'investigation', 'enforcement action']
    ],
    'innovation_technology' => [
        'base_query' => '{company_name} innovation OR technology OR R&D OR patents OR research',
        'domain_weights' => [
            'techcrunch.com' => 2,
            'wired.com' => 1,
            'theverge.com' => 1,
            'mit.edu' => 3,
            'stanford.edu' => 3
        ],
        'additional_terms' => ['patent filing', 'research and development', 'innovation lab', 'technology breakthrough']
    ]
];

foreach ($query_templates as $template_name => $template) {
    echo "\n📋 " . ucwords(str_replace('_', ' ', $template_name)) . ":\n";
    echo "   Base Query: {$template['base_query']}\n";
    echo "   Domain Weights:\n";
    
    foreach ($template['domain_weights'] as $domain => $weight) {
        echo "      {$domain}: weight {$weight}\n";
    }
    
    echo "   Additional Terms: " . implode(', ', $template['additional_terms']) . "\n";
}

// Generate final JSON patch
$rebalancing_patch = [
    'metadata' => [
        'created_at' => date('Y-m-d H:i:s'),
        'analysis_source' => $analysis_data['source'] ?? 'legacy_nb_data',
        'total_citations_analyzed' => $total_citations,
        'unique_domains_found' => count($domain_counts),
        'problematic_domains_count' => count($problematic_domains)
    ],
    'current_analysis' => [
        'domain_distribution' => $category_counts,
        'problematic_domains' => $problematic_domains,
        'high_concentration_domains' => $high_concentration_domains,
        'top_10_domains' => array_slice($domain_counts, 0, 10, true)
    ],
    'diversification_strategy' => [
        'target_distributions' => [
            'financial_news' => ['target_percent' => 20, 'current_percent' => round(($category_counts['financial_news'] ?? 0) / $total_citations * 100, 2)],
            'industry_reports' => ['target_percent' => 25, 'current_percent' => round(($category_counts['industry_reports'] ?? 0) / $total_citations * 100, 2)],
            'government' => ['target_percent' => 15, 'current_percent' => round(($category_counts['government'] ?? 0) / $total_citations * 100, 2)],
            'academic' => ['target_percent' => 10, 'current_percent' => round(($category_counts['academic'] ?? 0) / $total_citations * 100, 2)],
            'business_media' => ['target_percent' => 15, 'current_percent' => round(($category_counts['business_media'] ?? 0) / $total_citations * 100, 2)],
            'other' => ['target_percent' => 15, 'current_percent' => round(($category_counts['other'] ?? 0) / $total_citations * 100, 2)]
        ],
        'recommended_new_domains' => $diversification_domains,
        'domain_weight_limits' => [
            'single_domain_max_percent' => 10,
            'domain_category_max_percent' => 30,
            'minimum_unique_domains_per_query' => 5
        ]
    ],
    'enhanced_query_templates' => $query_templates,
    'implementation_guidelines' => [
        'query_diversification' => [
            'rotate_domain_preferences_per_query',
            'limit_results_per_domain_per_search',
            'include_minimum_domains_per_search_type',
            'weight_newer_sources_higher',
            'penalize_overused_domains'
        ],
        'source_validation' => [
            'verify_domain_authority_scores',
            'check_content_freshness',
            'validate_content_relevance',
            'assess_source_credibility'
        ],
        'monitoring_metrics' => [
            'track_domain_distribution_per_run',
            'monitor_citation_diversity_scores',
            'alert_on_concentration_thresholds',
            'report_new_domain_discovery'
        ]
    ]
];

// Save the rebalancing patch
$patch_file = __DIR__ . '/retrieval_scope_rebalancing_patch.json';
file_put_contents($patch_file, json_encode($rebalancing_patch, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "\n💾 REBALANCING PATCH GENERATED:\n";
echo "==============================\n";
echo "File: {$patch_file}\n";
echo "Size: " . number_format(filesize($patch_file)) . " bytes\n\n";

echo "📊 Key Recommendations:\n";
echo "----------------------\n";

if (!empty($problematic_domains)) {
    echo "1. 🚨 CRITICAL: Reduce dependency on over-represented domains:\n";
    foreach ($problematic_domains as $domain_data) {
        $target_reduction = $domain_data['count'] - floor($total_citations * 0.10); // Target max 10%
        echo "   • {$domain_data['domain']}: Reduce by ~{$target_reduction} citations\n";
    }
    echo "\n";
}

echo "2. 🎯 DIVERSIFY: Add sources from underrepresented categories:\n";
foreach ($rebalancing_patch['diversification_strategy']['target_distributions'] as $category => $targets) {
    $gap = $targets['target_percent'] - $targets['current_percent'];
    if ($gap > 5) {
        echo "   • " . ucwords(str_replace('_', ' ', $category)) . ": Increase by ~{$gap}%\n";
    }
}

echo "\n3. 🔄 IMPLEMENT: Use enhanced query templates with domain weighting\n";
echo "4. 📈 MONITOR: Track diversity metrics with new telemetry\n";
echo "5. 🔧 CONFIGURE: Set domain concentration limits in retrieval system\n";

echo "\n✅ REBALANCING ANALYSIS COMPLETE\n";
echo "Ready for implementation in NB orchestration system.\n";
?>