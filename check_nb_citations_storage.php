<?php
/**
 * Check how citations are stored in local_ci_nb_result
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 190;

echo "<h1>NB Citations Storage Check</h1>";
echo "<p>Checking how citations are stored in local_ci_nb_result for Run {$runid}</p>";

$nbs = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode ASC', '*', 0, 3);

foreach ($nbs as $nb) {
    echo "<h2>{$nb->nbcode}</h2>";

    echo "<h3>Citations Column</h3>";
    if (!empty($nb->citations)) {
        $citations = json_decode($nb->citations, true);
        echo "<p><strong>Type:</strong> " . gettype($citations) . "</p>";
        echo "<p><strong>Count:</strong> " . (is_array($citations) ? count($citations) : 0) . "</p>";
        if (is_array($citations) && !empty($citations)) {
            echo "<pre>";
            print_r(array_slice($citations, 0, 2));
            echo "</pre>";
        } else {
            echo "<p><em>Empty or invalid</em></p>";
        }
    } else {
        echo "<p><strong>⚠️ Citations column is NULL or empty</strong></p>";
    }

    echo "<h3>JSON Payload → Citations</h3>";
    if (!empty($nb->jsonpayload)) {
        $payload = json_decode($nb->jsonpayload, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($payload['citations'])) {
                echo "<p><strong>✅ Found citations in payload!</strong></p>";
                echo "<p><strong>Count:</strong> " . count($payload['citations']) . "</p>";
                echo "<pre>";
                print_r(array_slice($payload['citations'], 0, 2));
                echo "</pre>";
            } else {
                echo "<p><strong>❌ No 'citations' key in payload</strong></p>";
                echo "<p>Payload keys:</p>";
                echo "<pre>";
                print_r(array_keys($payload));
                echo "</pre>";
            }
        } else {
            echo "<p>Failed to decode payload: " . json_last_error_msg() . "</p>";
        }
    } else {
        echo "<p>Payload is empty</p>";
    }

    echo "<hr>";
}

echo "<h2>Summary</h2>";
echo "<p>This diagnostic shows whether citations are stored in:</p>";
echo "<ul>";
echo "<li><strong>citations column</strong> - What canonical_builder expects</li>";
echo "<li><strong>jsonpayload → citations</strong> - Where they might actually be</li>";
echo "</ul>";

?>
