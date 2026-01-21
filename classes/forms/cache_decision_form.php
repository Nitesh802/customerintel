<?php
/**
 * Cache Decision Form - User interface for cache reuse decisions
 *
 * Implements Milestone 0: Interactive Intelligence Cache Manager
 * Allows users to choose between reusing cached NB data or performing full refresh.
 *
 * CORRECTED: Uses actual schema with companyid/targetcompanyid integers
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\forms;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Cache Decision Form class
 *
 * Presents cache availability information and decision options to user.
 * Milestone 0 implementation for intelligent cache management.
 */
class cache_decision_form extends \moodleform {

    /**
     * Form definition
     */
    protected function definition() {
        global $DB;

        $mform = $this->_form;

        // Get custom data passed from page
        $customdata = $this->_customdata;
        $companyid = $customdata['companyid'] ?? 0;
        $targetcompanyid = $customdata['targetcompanyid'] ?? null;
        $cache_info = $customdata['cache_info'] ?? [];

        // Get company names from database
        $company = $DB->get_record('local_ci_company', ['id' => $companyid], 'name');
        $company_name = $company ? $company->name : 'Unknown';

        $target_name = null;
        if ($targetcompanyid) {
            $target = $DB->get_record('local_ci_company', ['id' => $targetcompanyid], 'name');
            $target_name = $target ? $target->name : 'Unknown';
        }

        // Page title/header
        $mform->addElement('header', 'cache_decision_header',
            get_string('cache_decision_header', 'local_customerintel'));
        $mform->setExpanded('cache_decision_header', true);

        // Company pair information section
        $mform->addElement('static', 'companies_info',
            get_string('cache_decision_companies_header', 'local_customerintel'),
            $this->format_company_info($company_name, $target_name));

        // Cache availability information
        $cache_available = !empty($cache_info['available']);

        if ($cache_available) {
            // Cache is available - show details
            $cache_details = $this->format_cache_details($cache_info);

            $mform->addElement('static', 'cache_available_info',
                get_string('cache_available_info', 'local_customerintel'),
                $cache_details);

            // Decision radio buttons
            $radio_options = [];
            $radio_options[] = $mform->createElement('radio', 'cache_decision', '',
                get_string('cache_decision_reuse', 'local_customerintel'), 'reuse');
            $radio_options[] = $mform->createElement('radio', 'cache_decision', '',
                get_string('cache_decision_full', 'local_customerintel'), 'full');

            $mform->addGroup($radio_options, 'cache_decision_group',
                get_string('cache_decision_label', 'local_customerintel'), ['<br/>'], false);

            // Help buttons for each option
            $mform->addHelpButton('cache_decision_group', 'cache_decision_reuse', 'local_customerintel');

            // Set default to reuse (since cache is available)
            $mform->setDefault('cache_decision', 'reuse');

            // Add hidden field for cached runid
            $cached_runid = $cache_info['runid'] ?? 0;
            $mform->addElement('hidden', 'cached_runid', $cached_runid);
            $mform->setType('cached_runid', PARAM_INT);

        } else {
            // No cache available - inform user and auto-select full
            $mform->addElement('static', 'no_cache_info',
                get_string('cache_not_available', 'local_customerintel'),
                \html_writer::div(
                    get_string('cache_not_available', 'local_customerintel') .
                    ' ' . get_string('cache_decision_full_help', 'local_customerintel'),
                    'alert alert-info'
                ));

            // Hidden field for decision (auto-set to 'full')
            $mform->addElement('hidden', 'cache_decision', 'full');
            $mform->setType('cache_decision', PARAM_ALPHA);

            // No cached_runid
            $mform->addElement('hidden', 'cached_runid', 0);
            $mform->setType('cached_runid', PARAM_INT);
        }

        // Hidden fields for company IDs
        $mform->addElement('hidden', 'companyid', $companyid);
        $mform->setType('companyid', PARAM_INT);

        $mform->addElement('hidden', 'targetcompanyid', $targetcompanyid ?? 0);
        $mform->setType('targetcompanyid', PARAM_INT);

        // Action buttons
        $this->add_action_buttons(true, get_string('continue', 'core'));
    }

    /**
     * Format company pair information for display
     *
     * @param string $company_name Company name
     * @param string|null $target_name Target company name (null for single company analysis)
     * @return string HTML formatted company info
     */
    private function format_company_info(string $company_name, ?string $target_name): string {
        $html = \html_writer::start_div('card mb-3');
        $html .= \html_writer::start_div('card-body');

        // Source company
        $html .= \html_writer::div(
            \html_writer::tag('strong', 'Customer Company: ') .
            \html_writer::tag('span', $company_name, ['class' => 'badge badge-primary']),
            'mb-2'
        );

        // Target company (if specified)
        if ($target_name) {
            $html .= \html_writer::div(
                \html_writer::tag('strong', 'Target Company: ') .
                \html_writer::tag('span', $target_name, ['class' => 'badge badge-info']),
                ''
            );
        } else {
            $html .= \html_writer::div(
                \html_writer::tag('em', 'Single company analysis'),
                'text-muted'
            );
        }

        $html .= \html_writer::end_div(); // card-body
        $html .= \html_writer::end_div(); // card

        return $html;
    }

    /**
     * Format cache details for display
     *
     * @param array $cache_info Cache information from cache_manager
     * @return string HTML formatted cache details
     */
    private function format_cache_details(array $cache_info): string {
        // Defensive null checks
        $age_days = $cache_info['age_days'] ?? 0;
        $timecreated = $cache_info['timecreated'] ?? time();
        $nb_count = $cache_info['nb_count'] ?? 0;
        $runid = $cache_info['runid'] ?? 0;

        $html = \html_writer::start_div('alert alert-success');
        $html .= \html_writer::tag('h5', '✓ Cached Data Available', ['class' => 'alert-heading']);

        // Age information
        $age_text = ($age_days === 0) ? 'Today' : "{$age_days} day" . ($age_days > 1 ? 's' : '') . " ago";

        $html .= \html_writer::tag('p',
            \html_writer::tag('strong', 'Data Age: ') . $age_text,
            ['class' => 'mb-2']);

        // Date created
        $date_formatted = userdate($timecreated, get_string('strftimedatetime'));
        $html .= \html_writer::tag('p',
            \html_writer::tag('strong', 'Created: ') . $date_formatted,
            ['class' => 'mb-2']);

        // NB count
        $html .= \html_writer::tag('p',
            \html_writer::tag('strong', 'Neural Blocks Available: ') . $nb_count . '/15',
            ['class' => 'mb-2']);

        // Run ID reference
        $html .= \html_writer::tag('p',
            \html_writer::tag('strong', 'Source Run ID: ') . $runid,
            ['class' => 'mb-2']);

        // Time savings note
        $html .= \html_writer::empty_tag('hr');
        $html .= \html_writer::tag('p',
            \html_writer::tag('em', '⚡ Reusing cached data will reduce processing time from 8-10 minutes to ~10 seconds.'),
            ['class' => 'mb-0 text-muted']);

        $html .= \html_writer::end_div();

        return $html;
    }

    /**
     * Form validation
     *
     * @param array $data Submitted data
     * @param array $files Submitted files
     * @return array Validation errors
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        // Validate cache_decision value
        if (!isset($data['cache_decision']) || !in_array($data['cache_decision'], ['reuse', 'full'])) {
            $errors['cache_decision_group'] = get_string('error', 'core') . ': Invalid cache decision';
        }

        // If reuse selected, cached_runid must be present
        if (isset($data['cache_decision']) && $data['cache_decision'] === 'reuse') {
            if (empty($data['cached_runid']) || $data['cached_runid'] <= 0) {
                $errors['cache_decision_group'] = get_string('error', 'core') .
                    ': Cannot reuse cache - no cached run ID available';
            }
        }

        // Validate company ID
        if (empty($data['companyid']) || $data['companyid'] <= 0) {
            $errors['general'] = get_string('error', 'core') . ': Invalid company ID';
        }

        return $errors;
    }
}
