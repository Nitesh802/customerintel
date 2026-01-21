<?php
/**
 * Check if NB jsonpayload content was actually copied
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/local/customerintel/check_nb_content.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Check NB Content');

echo $OUTPUT->header();
echo $OUTPUT->heading('NB Content Check');

// Check one NB from each run
$runs = [103, 104, 105];

foreach ($runs as $runid) {
    echo html_writer::tag('h3', "Run {$runid} - NB-1 Content Check");

    $nb = $DB->get_record('local_ci_nb_result', ['runid' => $runid, 'nbcode' => 'NB-1']);

    if ($nb) {
        echo html_writer::tag('p', "ID: {$nb->id}");
        echo html_writer::tag('p', "NBCode: {$nb->nbcode}");
        echo html_writer::tag('p', "Status: {$nb->status}");

        $payload_length = strlen($nb->jsonpayload ?? '');
        $citations_length = strlen($nb->citations ?? '');

        echo html_writer::tag('p', "JSON Payload Length: <strong>{$payload_length} bytes</strong>" .
            ($payload_length > 0 ? ' ✅' : ' ❌ EMPTY!'));
        echo html_writer::tag('p', "Citations Length: <strong>{$citations_length} bytes</strong>" .
            ($citations_length > 0 ? ' ✅' : ' ❌ EMPTY!'));
        echo html_writer::tag('p', "Tokens Used: {$nb->tokensused}");

        if ($payload_length > 0) {
            // Show first 500 chars of payload
            $preview = substr($nb->jsonpayload, 0, 500);
            echo html_writer::tag('p', 'Payload Preview (first 500 chars):');
            echo html_writer::tag('pre', htmlspecialchars($preview));
        }
    } else {
        echo $OUTPUT->notification("NB-1 not found for Run {$runid}!", 'notifyproblem');
    }

    echo html_writer::empty_tag('hr');
}

echo $OUTPUT->footer();
