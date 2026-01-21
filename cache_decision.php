<?php
/**
 * Cache Decision Page - Interactive cache management interface
 *
 * Implements Milestone 0: Interactive Intelligence Cache Manager
 * Displays cache availability and allows users to choose between
 * reusing cached NB data or performing a full refresh.
 *
 * Flow:
 * - run.php (select companies) → cache_decision.php (this page) → execute_run_task OR direct to viewing
 *
 * CORRECTED: Uses actual schema with local_ci_* tables and companyid/targetcompanyid
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $PAGE, $OUTPUT, $USER;

// Set up page context and permissions
$context = context_system::instance();
require_capability('local/customerintel:run', $context);

// Get company IDs from URL parameters
$companyid = required_param('companyid', PARAM_INT);
$targetcompanyid = optional_param('targetcompanyid', null, PARAM_INT);

// Validate company IDs
if ($companyid <= 0) {
    throw new moodle_exception('error', 'local_customerintel', '', null, 'Invalid company ID');
}

// Verify company exists
$company = $DB->get_record('local_ci_company', ['id' => $companyid], '*', MUST_EXIST);

// Verify target company if specified
$target_company = null;
if ($targetcompanyid && $targetcompanyid > 0) {
    $target_company = $DB->get_record('local_ci_company', ['id' => $targetcompanyid], '*', MUST_EXIST);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/customerintel/cache_decision.php', [
    'companyid' => $companyid,
    'targetcompanyid' => $targetcompanyid
]));
$PAGE->set_title(get_string('cache_decision_title', 'local_customerintel'));
$PAGE->set_heading(get_string('cache_decision_title', 'local_customerintel'));
$PAGE->set_pagelayout('standard');

// Initialize cache manager
$cache_manager = new \local_customerintel\services\cache_manager();

// Check for cached data
$cache_info = $cache_manager->check_nb_cache($companyid, $targetcompanyid);

// Prepare custom data for form
$customdata = [
    'companyid' => $companyid,
    'targetcompanyid' => $targetcompanyid,
    'cache_info' => $cache_info
];

// Instantiate form
$mform = new \local_customerintel\forms\cache_decision_form(null, $customdata);

// Handle form cancellation
if ($mform->is_cancelled()) {
    // Redirect back to dashboard or run page
    redirect(new moodle_url('/local/customerintel/run.php'));
}

// Handle form submission
if ($data = $mform->get_data()) {
    try {
        // Start database transaction
        $transaction = $DB->start_delegated_transaction();

        // Create new run record
        $run = new stdClass();
        $run->companyid = $data->companyid;
        $run->targetcompanyid = ($data->targetcompanyid > 0) ? $data->targetcompanyid : null;
        $run->status = 'pending';
        $run->initiatedbyuserid = $USER->id;
        $run->userid = $USER->id;
        $run->mode = 'full'; // Can be enhanced later for partial modes
        $run->timecreated = time();
        $run->timemodified = time();

        // Milestone 1 Task 2: Set default prompt config (scaffolding for M2)
        $run->prompt_config = json_encode([
            'tone' => 'Default',
            'persona' => 'Consultative'
        ]);

        // Milestone 1 Task 2: Set default refresh config (scaffolding for M2)
        $run->refresh_config = json_encode([
            'force_nb_refresh' => false,
            'force_synthesis_refresh' => false,
            'refresh_source' => false,
            'refresh_target' => false
        ]);

        $new_runid = $DB->insert_record('local_ci_run', $run);

        if (!$new_runid) {
            throw new moodle_exception('error', 'local_customerintel', '', null,
                'Failed to create run record');
        }

        // Process cache decision
        $cached_runid = (!empty($data->cached_runid) && $data->cached_runid > 0) ? $data->cached_runid : null;
        $next_step = $cache_manager->process_cache_decision($data->cache_decision, $new_runid, $cached_runid);

        // Commit transaction
        $transaction->allow_commit();

        // Log the run creation
        require_once($CFG->dirroot . '/local/customerintel/classes/services/log_service.php');
        \local_customerintel\services\log_service::info($new_runid,
            "Run {$new_runid} created with cache strategy: {$data->cache_decision}");

        // Redirect based on cache decision
        if ($next_step === 'cached') {
            // NBs copied from cache - skip Stage 1
            // Update run status to completed since NBs are ready
            $DB->set_field('local_ci_run', 'status', 'completed', ['id' => $new_runid]);
            $DB->set_field('local_ci_run', 'timecompleted', time(), ['id' => $new_runid]);

            // Redirect to report viewing page (view_report.php uses synthesis artifacts)
            $redirect_url = new moodle_url('/local/customerintel/view_report.php', ['runid' => $new_runid]);
            redirect($redirect_url,
                'Intelligence data reused from cache. Report ready!',
                null,
                \core\output\notification::NOTIFY_SUCCESS);

        } else {
            // Full refresh - queue the execution task
            try {
                $task = new \local_customerintel\task\execute_run_task();
                $task->set_custom_data((object)['runid' => $new_runid]);
                $task->set_userid($USER->id);
                $task->set_component('local_customerintel');
                $task->set_fail_delay(60);
                \core\task\manager::queue_adhoc_task($task);

                // Redirect to dashboard with success message
                $redirect_url = new moodle_url('/local/customerintel/dashboard.php');
                redirect($redirect_url,
                    get_string('runqueued', 'local_customerintel'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS);

            } catch (Exception $task_error) {
                // If task queueing fails, log and show error
                $cache_manager->log_diagnostics($new_runid, 'task_queue_error', 'error',
                    'Failed to queue execution task: ' . $task_error->getMessage());

                throw new moodle_exception('error', 'local_customerintel', '', null,
                    'Failed to queue intelligence run: ' . $task_error->getMessage());
            }
        }

    } catch (Exception $e) {
        // Rollback transaction if it exists
        if (isset($transaction) && !$transaction->is_disposed()) {
            $transaction->rollback($e);
        }

        // Log error to diagnostics if we have a runid
        if (!empty($new_runid)) {
            try {
                $cache_manager->log_diagnostics($new_runid, 'cache_decision_error', 'error',
                    'Cache decision processing failed: ' . $e->getMessage());
            } catch (Exception $log_error) {
                // Silent failure for logging
                debugging('Failed to log diagnostic: ' . $log_error->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Display error to user
        echo $OUTPUT->header();
        echo $OUTPUT->notification('Error processing cache decision: ' . $e->getMessage(), 'error');
        echo $OUTPUT->continue_button(new moodle_url('/local/customerintel/run.php'));
        echo $OUTPUT->footer();
        die();
    }
}

// Output page
echo $OUTPUT->header();

// Display page heading with company info
$heading = get_string('cache_decision_title', 'local_customerintel');
$heading .= ' - ' . $company->name;
if ($target_company) {
    $heading .= ' → ' . $target_company->name;
}
echo $OUTPUT->heading($heading);

// Display form
$mform->display();

// Back to run page link
$run_url = new moodle_url('/local/customerintel/run.php');
echo html_writer::div(
    html_writer::link($run_url, '← Back to Run Selection'),
    'mt-3'
);

echo $OUTPUT->footer();
