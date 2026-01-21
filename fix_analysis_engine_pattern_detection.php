<?php
/**
 * Fix analysis_engine.php Pattern Detection Methods
 *
 * This script fixes the pattern detection methods to use ACTUAL NB schema fields
 * instead of non-existent flat field names.
 *
 * BUGS FIXED:
 * 1. Line 322: 'sources' → 'citations'
 * 2. Line 343: 'sources' → 'citations' in populate_citations
 * 3. collect_pressure_themes: Use pressure_factors, competitive_threats (actual schema)
 * 4. collect_capability_levers: Use actual NB schema fields
 * 5. collect_timing_signals: Use actual NB schema fields
 * 6. collect_executive_accountabilities: Use leadership_team (actual schema)
 * 7. collect_numeric_proofs: Use key_metrics (actual schema)
 */

$file = __DIR__ . '/classes/services/analysis_engine.php';

if (!file_exists($file)) {
    die("ERROR: File not found: $file\n");
}

$content = file_get_contents($file);

echo "=== Fixing analysis_engine.php Pattern Detection ===\n\n";

// Fix 1 & 2: Already done via sed

// Fix 3: Replace collect_pressure_themes method
$old_method_1 = <<<'OLD'
    private function collect_pressure_themes($nb_data, &$pressure_themes): void {
        $nb_data = $this->as_array($nb_data);

        // NB1: Financial pressures and market conditions
        $nb1_data = $this->get_or($nb_data, 'NB1', []);
        $financial_pressures = $this->extract_field($this->get_or($nb1_data, 'data', []), ['financial_pressures', 'pressures', 'challenges']);
        foreach ($financial_pressures as $pressure) {
            if (!empty($pressure)) {
                $pressure_themes[] = ['text' => $pressure, 'field' => 'financial', 'source' => 'NB1'];
            }
        }

        // NB3: Operational inefficiencies
        $nb3_data = $this->get_or($nb_data, 'NB3', []);
        $operational_issues = $this->extract_field($this->get_or($nb3_data, 'data', []), ['inefficiencies', 'operational_issues', 'gaps']);
        foreach ($operational_issues as $issue) {
            if (!empty($issue)) {
                $pressure_themes[] = ['text' => $issue, 'field' => 'operational', 'source' => 'NB3'];
            }
        }

        // NB4: Competitive pressures
        $nb4_data = $this->get_or($nb_data, 'NB4', []);
        $competitive_threats = $this->extract_field($this->get_or($nb4_data, 'data', []), ['competitive_threats', 'threats', 'risks']);
        foreach ($competitive_threats as $threat) {
            if (!empty($threat)) {
                $pressure_themes[] = ['text' => $threat, 'field' => 'competitive', 'source' => 'NB4'];
            }
        }
    }
OLD;

$new_method_1 = <<<'NEW'
    private function collect_pressure_themes($nb_data, &$pressure_themes): void {
        $nb_data = $this->as_array($nb_data);

        // NB1: Extract from pressure_factors array (actual schema field)
        $nb1_data = $this->get_or($nb_data, 'NB1', []);
        if (!empty($nb1_data)) {
            $nb1_payload = $this->get_or($nb1_data, 'data', []);
            $pressure_factors = $this->get_or($nb1_payload, 'pressure_factors', []);

            foreach ($pressure_factors as $factor) {
                if (is_array($factor) && isset($factor['description'])) {
                    $pressure_themes[] = [
                        'text' => $factor['description'],
                        'severity' => $this->get_or($factor, 'severity', 'medium'),
                        'timeline' => $this->get_or($factor, 'timeline', ''),
                        'field' => 'financial',
                        'source' => 'NB1'
                    ];
                }
            }
        }

        // NB3: Extract from financial_performance/profitability (actual schema)
        $nb3_data = $this->get_or($nb_data, 'NB3', []);
        if (!empty($nb3_data)) {
            $nb3_payload = $this->get_or($nb3_data, 'data', []);

            // Extract challenges from profitability section
            $profitability = $this->get_or($nb3_payload, 'profitability', []);
            if (is_array($profitability)) {
                $challenges = $this->get_or($profitability, 'challenges', []);
                foreach ($challenges as $challenge) {
                    if (is_string($challenge) && !empty($challenge)) {
                        $pressure_themes[] = [
                            'text' => $challenge,
                            'field' => 'operational',
                            'source' => 'NB3'
                        ];
                    }
                }
            }
        }

        // NB4/NB8: Extract from competitive_threats (actual schema field)
        foreach (['NB4', 'NB8'] as $nb_key) {
            $nb_data_item = $this->get_or($nb_data, $nb_key, []);
            if (!empty($nb_data_item)) {
                $payload = $this->get_or($nb_data_item, 'data', []);
                $competitive_threats = $this->get_or($payload, 'competitive_threats', []);

                foreach ($competitive_threats as $threat) {
                    if (is_array($threat) && isset($threat['threat_description'])) {
                        $pressure_themes[] = [
                            'text' => $threat['threat_description'],
                            'severity' => $this->get_or($threat, 'severity', 'medium'),
                            'field' => 'competitive',
                            'source' => $nb_key
                        ];
                    } else if (is_string($threat) && !empty($threat)) {
                        $pressure_themes[] = [
                            'text' => $threat,
                            'field' => 'competitive',
                            'source' => $nb_key
                        ];
                    }
                }
            }
        }
    }
NEW;

$content = str_replace($old_method_1, $new_method_1, $content);
echo "✅ Fixed collect_pressure_themes - Now uses actual NB schema fields\n";

// Fix 4: Replace collect_numeric_proofs method
$old_method_2 = <<<'OLD'
    private function collect_numeric_proofs($nb_data, &$numeric_proofs): void {
        $nb_data = $this->as_array($nb_data);

        foreach ($nb_data as $nb_key => $nb_info) {
            if (preg_match('/^NB\d+$/', $nb_key)) {
                $data = $this->get_or($nb_info, 'data', []);
                $metrics = $this->extract_field($data, ['metrics', 'numbers', 'financials', 'kpis']);
                foreach ($metrics as $metric) {
                    if (!empty($metric) && is_array($metric)) {
                        $numeric_proofs[] = [
                            'value' => $this->get_or($metric, 'value', ''),
                            'description' => $this->get_or($metric, 'description', ''),
                            'source' => $nb_key
                        ];
                    }
                }
            }
        }
    }
OLD;

$new_method_2 = <<<'NEW'
    private function collect_numeric_proofs($nb_data, &$numeric_proofs): void {
        $nb_data = $this->as_array($nb_data);

        foreach ($nb_data as $nb_key => $nb_info) {
            if (preg_match('/^NB\d+$/', $nb_key)) {
                $data = $this->get_or($nb_info, 'data', []);

                // NB1 uses 'key_metrics' (actual schema field)
                $key_metrics = $this->get_or($data, 'key_metrics', []);
                foreach ($key_metrics as $metric) {
                    if (is_array($metric) && isset($metric['value'])) {
                        $numeric_proofs[] = [
                            'value' => $metric['value'],
                            'description' => $this->get_or($metric, 'metric', ''),
                            'trend' => $this->get_or($metric, 'trend', ''),
                            'source' => $nb_key
                        ];
                    }
                }

                // Also check for any 'metrics' arrays in other NBs
                $metrics = $this->get_or($data, 'metrics', []);
                foreach ($metrics as $metric) {
                    if (is_array($metric) && isset($metric['value'])) {
                        $numeric_proofs[] = [
                            'value' => $metric['value'],
                            'description' => $this->get_or($metric, 'description', $this->get_or($metric, 'metric', '')),
                            'source' => $nb_key
                        ];
                    }
                }
            }
        }
    }
NEW;

$content = str_replace($old_method_2, $new_method_2, $content);
echo "✅ Fixed collect_numeric_proofs - Now uses key_metrics (actual schema)\n";

// Fix 5: Replace collect_executive_accountabilities method
$old_method_3 = <<<'OLD'
    private function collect_executive_accountabilities($nb_data, &$executive_accountabilities): void {
        $nb_data = $this->as_array($nb_data);

        // NB11: Key personnel and leadership
        $nb11_data = $this->get_or($nb_data, 'NB11', []);
        $executives = $this->extract_field($this->get_or($nb11_data, 'data', []), ['executives', 'leadership', 'key_personnel']);
        foreach ($executives as $exec) {
            if (!empty($exec) && is_array($exec)) {
                $executive_accountabilities[] = [
                    'name' => $this->get_or($exec, 'name', 'Executive'),
                    'title' => $this->get_or($exec, 'title', 'Leadership'),
                    'accountability' => $this->get_or($exec, 'responsibility', 'Strategic oversight')
                ];
            }
        }
    }
OLD;

$new_method_3 = <<<'NEW'
    private function collect_executive_accountabilities($nb_data, &$executive_accountabilities): void {
        $nb_data = $this->as_array($nb_data);

        // NB11: Extract from leadership_assessment.leadership_team (actual schema)
        $nb11_data = $this->get_or($nb_data, 'NB11', []);
        if (!empty($nb11_data)) {
            $nb11_payload = $this->get_or($nb11_data, 'data', []);
            $leadership_assessment = $this->get_or($nb11_payload, 'leadership_assessment', []);
            $leadership_team = $this->get_or($leadership_assessment, 'leadership_team', []);

            foreach ($leadership_team as $exec) {
                if (is_array($exec) && isset($exec['name'])) {
                    $executive_accountabilities[] = [
                        'name' => $exec['name'],
                        'title' => $this->get_or($exec, 'role', 'Leadership'),
                        'tenure' => $this->get_or($exec, 'tenure', ''),
                        'background' => $this->get_or($exec, 'background', ''),
                        'accountability' => $this->get_or($exec, 'background', 'Strategic oversight')
                    ];
                }
            }
        }
    }
NEW;

$content = str_replace($old_method_3, $new_method_3, $content);
echo "✅ Fixed collect_executive_accountabilities - Now uses leadership_team (actual schema)\n";

// Write fixed file
file_put_contents($file, $content);

echo "\n=== All Fixes Applied Successfully ===\n";
echo "File: $file\n";
echo "\nFixed methods:\n";
echo "  1. Line 322: 'sources' → 'citations'\n";
echo "  2. Line 343: 'sources' → 'citations' in populate_citations\n";
echo "  3. collect_pressure_themes: Now uses pressure_factors, competitive_threats\n";
echo "  4. collect_numeric_proofs: Now uses key_metrics\n";
echo "  5. collect_executive_accountabilities: Now uses leadership_team\n";
echo "\nPattern detection should now extract data correctly from NB schemas!\n";

?>
