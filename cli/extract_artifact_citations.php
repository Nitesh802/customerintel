<?php
/**
 * Extract Citations from Artifact Repository
 * 
 * Extracts citation data from the new artifact repository for analysis
 */

require_once(__DIR__ . '/../../config.php');

// Security
require_login();
$context = context_system::instance();
require_capability('local/customerintel:manage', $context);

echo "Artifact Repository Citation Extraction\n";
echo "======================================\n\n";

// Check if artifact table exists
$dbman = $DB->get_manager();
$table_exists = $dbman->table_exists(new xmldb_table('local_ci_artifact'));

if (!$table_exists) {
    echo "❌ Artifact table does not exist. Using legacy NB data analysis.\n";
    echo "💡 Run /local/customerintel/analyze_citations.php instead.\n";
    exit;
}

// Get artifacts with citation data
$artifacts = $DB->get_records_select(
    'local_ci_artifact',
    "phase IN ('nb_orchestration', 'synthesis', 'discovery')",
    [],
    'timecreated DESC',
    '*',
    0,
    10
);

if (empty($artifacts)) {
    echo "❌ No artifacts found. Enable trace mode and run some intelligence reports.\n";
    exit;
}

echo "📦 Found " . count($artifacts) . " artifacts to analyze\n\n";

$all_citations = [];
$domain_counts = [];
$total_citations = 0;

foreach ($artifacts as $artifact) {
    echo "🔍 Analyzing artifact {$artifact->id} (Run {$artifact->runid}, {$artifact->phase})...\n";
    
    $data = json_decode($artifact->jsondata, true);
    if (!$data) {
        echo "   ⚠️  Could not parse JSON data\n";
        continue;
    }
    
    $citations_found = 0;
    
    // Look for citations in different data structures
    $citation_sources = [];
    
    if (isset($data['citations'])) {
        $citation_sources[] = $data['citations'];
    }
    
    if (isset($data['sources'])) {
        $citation_sources[] = $data['sources'];
    }
    
    // Look for NB data with citations
    if (isset($data['nb'])) {
        foreach ($data['nb'] as $nb_data) {
            if (isset($nb_data['citations'])) {
                $citation_sources[] = $nb_data['citations'];
            }
        }
    }
    
    // Look for synthesis bundle citations
    if (isset($data['final_bundle']['citations'])) {
        $citation_sources[] = $data['final_bundle']['citations'];
    }
    
    foreach ($citation_sources as $citations) {
        if (!is_array($citations)) continue;
        
        foreach ($citations as $citation) {
            $url = null;
            
            // Handle different citation formats
            if (is_string($citation)) {
                $url = $citation;
            } elseif (isset($citation['url'])) {
                $url = $citation['url'];
            } elseif (isset($citation['source'])) {
                $url = $citation['source'];
            }
            
            if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                $domain = parse_url($url, PHP_URL_HOST);
                
                if ($domain) {
                    // Clean domain
                    $domain = preg_replace('/^www\./', '', strtolower($domain));
                    
                    $all_citations[] = [
                        'runid' => $artifact->runid,
                        'phase' => $artifact->phase,
                        'artifact_type' => $artifact->artifacttype,
                        'url' => $url,
                        'domain' => $domain,
                        'quote' => $citation['quote'] ?? '',
                        'title' => $citation['title'] ?? ''
                    ];
                    
                    if (!isset($domain_counts[$domain])) {
                        $domain_counts[$domain] = 0;
                    }
                    $domain_counts[$domain]++;
                    
                    $citations_found++;
                    $total_citations++;
                }
            }
        }
    }
    
    echo "   📊 Found {$citations_found} citations\n";
}

if ($total_citations == 0) {
    echo "❌ No citations found in artifact data.\n";
    echo "💡 This may indicate:\n";
    echo "   • Citations are stored in a different format\n";
    echo "   • Artifacts were created before citation tracking\n";
    echo "   • Need to run more reports with trace mode enabled\n";
    exit;
}

echo "\n📈 ARTIFACT CITATION ANALYSIS\n";
echo "=============================\n";
echo "Total Citations: {$total_citations}\n";
echo "Unique Domains: " . count($domain_counts) . "\n\n";

// Sort and display top domains
arsort($domain_counts);

echo "🏆 TOP DOMAINS:\n";
$rank = 1;
foreach ($domain_counts as $domain => $count) {
    $percentage = round(($count / $total_citations) * 100, 2);
    echo sprintf("%2d. %-30s %4d (%5.2f%%)\n", $rank, $domain, $count, $percentage);
    $rank++;
    if ($rank > 15) break;
}

// Save combined analysis
$artifact_analysis = [
    'timestamp' => date('Y-m-d H:i:s'),
    'source' => 'artifact_repository',
    'total_citations' => $total_citations,
    'domain_counts' => $domain_counts,
    'sample_citations' => array_slice($all_citations, 0, 50)
];

file_put_contents(__DIR__ . '/artifact_citation_analysis.json', json_encode($artifact_analysis, JSON_PRETTY_PRINT));
echo "\n💾 Analysis saved to artifact_citation_analysis.json\n";
?>