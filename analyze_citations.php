<?php
/**
 * Citation Analysis Script
 * 
 * Analyzes NB orchestration data to identify domain clustering and redundancy
 */

require_once(__DIR__ . '/../../config.php');

// Security
require_login();
$context = context_system::instance();
require_capability('local/customerintel:manage', $context);

echo "NB Orchestration Citation Analysis\n";
echo "=================================\n\n";

// Get recent completed runs with NB data
$recent_runs = $DB->get_records_sql(
    "SELECT r.id, r.companyid, r.targetcompanyid, r.status, r.timecompleted,
            c1.name as company_name, c2.name as target_name
     FROM {local_ci_run} r
     LEFT JOIN {local_ci_company} c1 ON r.companyid = c1.id
     LEFT JOIN {local_ci_company} c2 ON r.targetcompanyid = c2.id
     WHERE r.status = 'completed'
     ORDER BY r.timecompleted DESC
     LIMIT 10"
);

if (empty($recent_runs)) {
    echo "❌ No completed runs found. Please run some intelligence reports first.\n";
    exit;
}

echo "📊 Recent Completed Runs:\n";
foreach ($recent_runs as $run) {
    $target_info = $run->target_name ? " → {$run->target_name}" : " (discovery only)";
    echo "   • Run {$run->id}: {$run->company_name}{$target_info}\n";
}

echo "\n🔍 Analyzing citations from NB orchestration data...\n\n";

$all_citations = [];
$domain_counts = [];
$total_citations = 0;
$runs_analyzed = 0;

foreach ($recent_runs as $run) {
    echo "📈 Analyzing Run {$run->id} ({$run->company_name})...\n";
    
    // Get NB data for this run
    $nb_records = $DB->get_records('local_ci_nb', ['runid' => $run->id]);
    
    if (empty($nb_records)) {
        echo "   ⚠️  No NB data found\n";
        continue;
    }
    
    $run_citations = 0;
    $run_domains = [];
    
    foreach ($nb_records as $nb) {
        // Parse JSON data
        $nb_data = json_decode($nb->jsondata, true);
        
        if (!$nb_data || !isset($nb_data['citations'])) {
            continue;
        }
        
        foreach ($nb_data['citations'] as $citation) {
            if (isset($citation['url'])) {
                $url = $citation['url'];
                $domain = parse_url($url, PHP_URL_HOST);
                
                if ($domain) {
                    // Clean domain (remove www.)
                    $domain = preg_replace('/^www\./', '', $domain);
                    
                    $all_citations[] = [
                        'runid' => $run->id,
                        'nb_type' => $nb->nbtype,
                        'url' => $url,
                        'domain' => $domain,
                        'quote' => $citation['quote'] ?? '',
                        'source_id' => $citation['source_id'] ?? null
                    ];
                    
                    if (!isset($domain_counts[$domain])) {
                        $domain_counts[$domain] = 0;
                    }
                    $domain_counts[$domain]++;
                    
                    if (!isset($run_domains[$domain])) {
                        $run_domains[$domain] = 0;
                    }
                    $run_domains[$domain]++;
                    
                    $run_citations++;
                    $total_citations++;
                }
            }
        }
    }
    
    echo "   📊 Found {$run_citations} citations across " . count($run_domains) . " domains\n";
    
    if (!empty($run_domains)) {
        $top_domains = array_slice(arsort($run_domains) ? $run_domains : [], 0, 3, true);
        echo "   🔝 Top domains: ";
        foreach ($top_domains as $domain => $count) {
            $pct = round(($count / $run_citations) * 100, 1);
            echo "{$domain} ({$count}, {$pct}%) ";
        }
        echo "\n";
    }
    
    $runs_analyzed++;
    echo "\n";
}

if ($total_citations == 0) {
    echo "❌ No citations found in NB data. The format may have changed.\n";
    exit;
}

echo "📈 OVERALL ANALYSIS RESULTS\n";
echo "===========================\n";
echo "Total Citations Analyzed: {$total_citations}\n";
echo "Runs Analyzed: {$runs_analyzed}\n";
echo "Unique Domains: " . count($domain_counts) . "\n\n";

// Sort domains by frequency
arsort($domain_counts);

echo "🏆 TOP DOMAINS BY FREQUENCY:\n";
echo "----------------------------\n";

$rank = 1;
$problematic_domains = [];

foreach ($domain_counts as $domain => $count) {
    $percentage = round(($count / $total_citations) * 100, 2);
    
    echo sprintf("%2d. %-30s %4d citations (%5.2f%%)", $rank, $domain, $count, $percentage);
    
    if ($percentage > 25) {
        echo " ⚠️  OVER-REPRESENTED";
        $problematic_domains[] = [
            'domain' => $domain,
            'count' => $count,
            'percentage' => $percentage
        ];
    } elseif ($percentage > 15) {
        echo " 📊 HIGH";
    }
    
    echo "\n";
    
    $rank++;
    if ($rank > 20) break; // Show top 20
}

echo "\n";

if (!empty($problematic_domains)) {
    echo "🚨 DOMAINS WITH >25% REPRESENTATION:\n";
    echo "------------------------------------\n";
    foreach ($problematic_domains as $problem) {
        echo "• {$problem['domain']}: {$problem['count']} citations ({$problem['percentage']}%)\n";
    }
    echo "\n";
}

// Analyze domain types
echo "📊 DOMAIN TYPE ANALYSIS:\n";
echo "------------------------\n";

$domain_types = [
    'news_media' => ['reuters.com', 'bloomberg.com', 'wsj.com', 'ft.com', 'cnbc.com', 'cnn.com', 'bbc.com', 'forbes.com'],
    'company_sites' => [],
    'industry_reports' => ['gartner.com', 'forrester.com', 'idc.com', 'mckinsey.com', 'bcg.com', 'deloitte.com'],
    'social_media' => ['twitter.com', 'linkedin.com', 'facebook.com', 'youtube.com'],
    'government' => ['sec.gov', 'fda.gov', 'ftc.gov', 'europa.eu'],
    'academic' => ['edu'],
    'other' => []
];

$type_counts = array_fill_keys(array_keys($domain_types), 0);

foreach ($domain_counts as $domain => $count) {
    $categorized = false;
    
    foreach ($domain_types as $type => $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($domain, $pattern) !== false) {
                $type_counts[$type] += $count;
                $categorized = true;
                break 2;
            }
        }
    }
    
    if (!$categorized) {
        $type_counts['other'] += $count;
    }
}

foreach ($type_counts as $type => $count) {
    if ($count > 0) {
        $percentage = round(($count / $total_citations) * 100, 2);
        echo sprintf("%-15s: %4d citations (%5.2f%%)\n", ucwords(str_replace('_', ' ', $type)), $count, $percentage);
    }
}

echo "\n🎯 ANALYSIS COMPLETE\n";
echo "Data saved for further processing...\n";

// Save analysis data for the rebalancing script
$analysis_data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'total_citations' => $total_citations,
    'runs_analyzed' => $runs_analyzed,
    'domain_counts' => $domain_counts,
    'problematic_domains' => $problematic_domains,
    'type_counts' => $type_counts,
    'all_citations' => array_slice($all_citations, 0, 100) // Sample for review
];

file_put_contents(__DIR__ . '/citation_analysis.json', json_encode($analysis_data, JSON_PRETTY_PRINT));
echo "💾 Analysis saved to citation_analysis.json\n";
?>