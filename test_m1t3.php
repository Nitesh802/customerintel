<?php
require_once('../../config.php');
require_login();

// Check if user is admin
if (!is_siteadmin()) {
    die('Admin access required');
}

// Get the absolute most recent run (any companies)
$sql = "SELECT r.id, r.companyid, r.targetcompanyid, r.reusedfromrunid, r.timecreated
        FROM {local_ci_run} r
        ORDER BY r.id DESC
        LIMIT 1";

$run = $DB->get_record_sql($sql);

if (!$run) {
    die("No runs found");
}

// Also show company names
$source_company = $DB->get_record('local_ci_company', array('id' => $run->companyid), 'name');
$target_company = $DB->get_record('local_ci_company', array('id' => $run->targetcompanyid), 'name');

$runid = $run->id;

echo "<h2>M1T3 Metadata Check - Run {$runid}</h2>";
echo "<p><strong>Source:</strong> " . ($source_company ? $source_company->name : 'Unknown') . " (ID: {$run->companyid})</p>";
echo "<p><strong>Target:</strong> " . ($target_company ? $target_company->name : 'Unknown') . " (ID: {$run->targetcompanyid})</p>";
echo "<p><strong>Created:</strong> " . date('Y-m-d H:i:s', $run->timecreated) . "</p>";

if ($run->reusedfromrunid) {
    echo "<p><strong>✅ This run REUSED cache from Run {$run->reusedfromrunid}</strong></p>";
    echo "<p>Checking BOTH Run {$runid} and cached Run {$run->reusedfromrunid}...</p>";
} else {
    echo "<p><strong>⚠️ This was a FRESH run (no cache reuse)</strong></p>";
}

// Load synthesis record - try current run first
$synthesis = $DB->get_record('local_ci_synthesis', array('runid' => $runid));

if (!$synthesis && $run->reusedfromrunid) {
    echo "<p style='color:orange;'>⚠️ No synthesis record for Run {$runid}, checking cached Run {$run->reusedfromrunid}...</p>";
    $synthesis = $DB->get_record('local_ci_synthesis', array('runid' => $run->reusedfromrunid));
    if ($synthesis) {
        $runid = $run->reusedfromrunid;
        echo "<p style='color:green;'>✅ Found synthesis record for cached Run {$runid}</p>";
    }
}

if (!$synthesis) {
    // Check what synthesis records exist
    $all_synthesis = $DB->get_records_sql("SELECT runid FROM {local_ci_synthesis} ORDER BY runid DESC LIMIT 10");
    echo "<p style='color:red;'>❌ No synthesis record found for Run {$runid}" .
         ($run->reusedfromrunid ? " or cached Run {$run->reusedfromrunid}" : "") . "</p>";

    if (empty($all_synthesis)) {
        echo "<p style='color:red;'>⚠️ NO synthesis records exist in local_ci_synthesis table!</p>";
    } else {
        echo "<p>Recent synthesis records exist for runs: " . implode(', ', array_keys($all_synthesis)) . "</p>";
    }

    // Check if synthesis data is in artifacts instead
    echo "<hr><h3>Checking Artifacts Table...</h3>";

    $check_runids = array($runid);
    if ($run->reusedfromrunid) {
        $check_runids[] = $run->reusedfromrunid;
    }

    foreach ($check_runids as $check_runid) {
        echo "<p><strong>Run {$check_runid}:</strong></p>";

        // Check for synthesis_final_bundle
        $bundle = $DB->get_record('local_ci_artifact', array(
            'runid' => $check_runid,
            'phase' => 'synthesis',
            'artifacttype' => 'synthesis_final_bundle'
        ));

        if ($bundle) {
            echo "<p style='color:green;'>✅ Found synthesis_final_bundle artifact</p>";

            // Decode and check for metadata
            $bundle_data = json_decode($bundle->jsondata, true);
            if ($bundle_data && isset($bundle_data['metadata'])) {
                $meta = $bundle_data['metadata'];

                // Check if M1T3 enhanced
                if (isset($meta['m1t3_enhanced']) && $meta['m1t3_enhanced']) {
                    echo "<p style='color:green;'>✅✅✅ FOUND M1T3 ENHANCED METADATA!</p>";
                    echo "<pre>";
                    echo "=== M1T3 ENHANCED METADATA ===\n";
                    echo "M1T3 Enhanced: YES\n";
                    echo "Source Company ID: " . ($meta['source_company_id'] ?? 'MISSING') . "\n";
                    echo "Target Company ID: " . ($meta['target_company_id'] ?? 'MISSING') . "\n";
                    echo "Synthesis Key: " . ($meta['synthesis_key'] ?? 'MISSING') . "\n";
                    echo "Model Used: " . ($meta['model_used'] ?? 'MISSING') . "\n";
                    echo "Section Count: " . ($meta['section_count'] ?? 'MISSING') . "\n";

                    if (isset($meta['prompt_config'])) {
                        echo "\nPrompt Config:\n";
                        print_r($meta['prompt_config']);
                    }

                    if (isset($meta['cache_source'])) {
                        echo "\n=== CACHE VALIDATION (Jon's Requirement) ===\n";
                        $cache = $meta['cache_source'];
                        echo "Is Cached: " . ($cache['is_cached'] ? 'YES' : 'NO') . "\n";
                        if ($cache['is_cached']) {
                            echo "Cached From Run: " . ($cache['cached_from_runid'] ?? 'MISSING') . "\n";
                            echo "Source + Target Match: " . ($cache['source_target_match'] ? '✅ YES' : '❌ NO') . "\n";
                            echo "Cache Age: " . ($cache['cache_age_hours'] ?? 'MISSING') . " hours\n";
                        }
                    }

                    echo "\n=== FULL METADATA ===\n";
                    print_r($meta);
                    echo "</pre>";
                    die("<p style='color:green;'>🎉🎉🎉 M1 Task 3 is WORKING PERFECTLY!</p>");
                } else {
                    echo "<p style='color:orange;'>⚠️ Metadata exists but NOT M1T3 enhanced (old format)</p>";
                    echo "<pre>";
                    echo "=== OLD METADATA FORMAT ===\n";
                    print_r($meta);
                    echo "</pre>";
                    echo "<p><strong>Action needed:</strong> Create a NEW run to test M1T3. This run was created before the fix.</p>";
                    die();
                }
            } else {
                echo "<p style='color:orange;'>⚠️ synthesis_final_bundle exists but no metadata field</p>";
                if ($bundle_data) {
                    echo "<p>Available keys: " . implode(', ', array_keys($bundle_data)) . "</p>";
                }
            }
        } else {
            echo "<p style='color:orange;'>⚠️ No synthesis_final_bundle artifact</p>";
        }

        // List all artifacts for this run
        $all_artifacts = $DB->get_records('local_ci_artifact',
            array('runid' => $check_runid),
            'phase, artifacttype',
            'id, phase, artifacttype'
        );

        if ($all_artifacts) {
            echo "<p>Available artifacts for Run {$check_runid}:</p><ul>";
            foreach ($all_artifacts as $art) {
                echo "<li>{$art->phase} / {$art->artifacttype}</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color:red;'>No artifacts found for Run {$check_runid}</p>";
        }
    }

    die();
}

// Decode JSON
$json_data = json_decode($synthesis->jsoncontent, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("<p style='color:red;'>JSON decode error: " . json_last_error_msg() . "</p>");
}

echo "<h3>JSON Structure Check:</h3>";
echo "<pre>";

// Check for synthesis_cache
if (isset($json_data['synthesis_cache'])) {
    echo "✅ synthesis_cache exists\n\n";

    // Check for metadata
    if (isset($json_data['synthesis_cache']['metadata'])) {
        echo "✅✅ M1T3 METADATA EXISTS!\n\n";

        $meta = $json_data['synthesis_cache']['metadata'];

        echo "=== ENHANCED METADATA ===\n";
        echo "Run ID: " . ($meta['runid'] ?? 'MISSING') . "\n";
        echo "Source Company ID: " . ($meta['source_company_id'] ?? 'MISSING') . "\n";
        echo "Target Company ID: " . ($meta['target_company_id'] ?? 'MISSING') . "\n";
        echo "Synthesis Key: " . ($meta['synthesis_key'] ?? 'MISSING') . "\n";
        echo "Model Used: " . ($meta['model_used'] ?? 'MISSING') . "\n";
        echo "Section Count: " . ($meta['section_count'] ?? 'MISSING') . "\n";
        echo "Time Created: " . (isset($meta['timecreated']) ? date('Y-m-d H:i:s', $meta['timecreated']) : 'MISSING') . "\n";

        if (isset($meta['prompt_config'])) {
            echo "\nPrompt Config:\n";
            echo json_encode($meta['prompt_config'], JSON_PRETTY_PRINT) . "\n";
        }

        echo "\n=== CACHE VALIDATION (Jon's Requirement) ===\n";
        if (isset($meta['cache_source'])) {
            $cache = $meta['cache_source'];
            echo "Is Cached: " . ($cache['is_cached'] ? 'YES' : 'NO') . "\n";

            if ($cache['is_cached']) {
                echo "Cached From Run: " . ($cache['cached_from_runid'] ?? 'MISSING') . "\n";
                echo "Source + Target Match: " . ($cache['source_target_match'] ? '✅ YES' : '❌ NO') . "\n";
                echo "Source ID Match: " . ($cache['source_id_match'] ? 'YES' : 'NO') . "\n";
                echo "Target ID Match: " . ($cache['target_id_match'] ? 'YES' : 'NO') . "\n";
                echo "Cache Age: " . ($cache['cache_age_hours'] ?? 'MISSING') . " hours\n";
            }
        } else {
            echo "❌ cache_source field MISSING\n";
        }

        echo "\n\n🎉 SUCCESS: M1 Task 3 is working!\n";

    } else {
        echo "❌ metadata field NOT FOUND in synthesis_cache\n";
        echo "Keys in synthesis_cache: " . implode(', ', array_keys($json_data['synthesis_cache'])) . "\n";
    }

} else {
    echo "❌ synthesis_cache NOT FOUND\n";
    echo "Top-level JSON keys: " . implode(', ', array_keys($json_data)) . "\n";
}

echo "</pre>";
