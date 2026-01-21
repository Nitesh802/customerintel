<?php
namespace local_customerintel\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/customerintel/classes/services/log_service.php');

use local_customerintel\services\log_service;

class execute_run_task extends \core\task\adhoc_task {

    public function get_component(): string {
        return 'local_customerintel';
    }

    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $runid = isset($data->runid) ? (int)$data->runid : 0;
        
        // Safety guard: Check if runid is valid
        if (!$runid) {
            mtrace('local_customerintel: Missing runid in custom data.');
            log_service::error(null, 'Task executed with missing runid in custom data');
            return;
        }

        // Log task start
        log_service::info($runid, "Starting task execution for run {$runid}");

        try {
            // Load complete run record from database
            $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);
            log_service::info($runid, "Run {$runid} loaded successfully.");
            
            // Validate required fields
            if (empty($run->id)) {
                log_service::error($runid, "Missing ID in run record.");
                throw new \moodle_exception('Missing ID in run record.');
            }
            
            if (empty($run->companyid)) {
                log_service::error($runid, "Missing company ID in run record.");
                throw new \moodle_exception('Missing company ID in run record.');
            }

            // Mark run as running
            $now = time();
            log_service::info($runid, "Updating run status to 'running'");
            $DB->update_record('local_ci_run', (object)[
                'id' => $runid,
                'status' => 'running',
                'timestarted' => $now,
                'timemodified' => $now
            ]);

            // Log orchestrator start
            log_service::info($runid, "Initializing NB orchestrator");
            log_service::info($runid, "Passing run {$runid} to orchestrator.");
            
            // Orchestrate the full NB pipeline with complete run object
            $orchestrator = new \local_customerintel\services\nb_orchestrator();
            $orchestrator->execute_full_protocol($run);
            
            log_service::info($runid, "Run {$runid} execution completed successfully.");

            // Mark run as completed
            log_service::info($runid, "Updating run status to 'completed'");
            $DB->update_record('local_ci_run', (object)[
                'id' => $runid,
                'status' => 'completed',
                'timecompleted' => time(),
                'timemodified' => time()
            ]);
            
            log_service::info($runid, "Run {$runid} completed successfully");

        } catch (\Throwable $e) {
            // Log the error with full details
            $error_message = $e->getMessage();
            $error_trace = $e->getTraceAsString();
            
            log_service::error($runid, "Run failed with error: {$error_message}");
            log_service::debug($runid, "Stack trace: {$error_trace}");
            
            // Safety guard: Mark run as failed in database
            try {
                $DB->set_field('local_ci_run', 'status', 'failed', ['id' => $runid]);
                $DB->set_field('local_ci_run', 'error', substr($error_message, 0, 1000), ['id' => $runid]);
                $DB->set_field('local_ci_run', 'timemodified', time(), ['id' => $runid]);
                log_service::error($runid, "Run {$runid} failed and marked as failed in database");
            } catch (\Exception $db_error) {
                log_service::error($runid, "Failed to update run status in database: " . $db_error->getMessage());
            }
            
            // Re-throw so task logs show the exception (Moodle cron will handle retry according to fail delay)
            throw $e;
        }
    }
}