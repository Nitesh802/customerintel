<?php
namespace local_customerintel\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class run_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;

        // Heading.
        $mform->addElement('header', 'runsettings', get_string('runsettings', 'local_customerintel'));

        // Company selection.
        $companies = $this->_customdata['companies'] ?? [];
        $mform->addElement('select', 'companyid', get_string('company', 'local_customerintel'), $companies);
        $mform->addRule('companyid', null, 'required', null, 'client');

        // Target selection.
        $targets = $this->_customdata['targets'] ?? [];
        $mform->addElement('select', 'targetcompanyid', get_string('target', 'local_customerintel'), $targets);
        $mform->addRule('targetcompanyid', null, 'required', null, 'client');

        // Notes field.
        $mform->addElement('textarea', 'notes', get_string('notes', 'local_customerintel'), 'wrap="virtual" rows="4" cols="50"');

        // Buttons.
        $this->add_action_buttons(true, get_string('submitrun', 'local_customerintel'));
    }
}