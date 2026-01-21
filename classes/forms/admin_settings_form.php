<?php
/**
 * Admin Settings Form
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\forms;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Admin settings form class
 * 
 * Implements all configuration options per PRD Section 8.8 and 19
 */
class admin_settings_form extends \moodleform {
    
    /**
     * Form definition
     */
    protected function definition() {
        $mform = $this->_form;
        
        // API Configuration Section - PRD Section 18
        $mform->addElement('header', 'apiconfig', get_string('apisettings', 'local_customerintel'));
        $mform->setExpanded('apiconfig', true);
        
        // Perplexity API Key - encrypted storage
        $mform->addElement('passwordunmask', 'perplexityapikey', 
            get_string('perplexityapikey', 'local_customerintel'),
            ['size' => 60]);
        $mform->setType('perplexityapikey', PARAM_RAW);
        $mform->addHelpButton('perplexityapikey', 'perplexityapikey', 'local_customerintel');
        $mform->addRule('perplexityapikey', get_string('required'), 'required', null, 'client');
        
        // LLM Provider Selection
        $providers = [
            'openai-gpt4' => 'OpenAI GPT-4',
            'openai-gpt35' => 'OpenAI GPT-3.5 Turbo',
            'anthropic-claude' => 'Anthropic Claude 3',
            'custom' => get_string('customprovider', 'local_customerintel')
        ];
        $mform->addElement('select', 'llm_provider', 
            get_string('llmprovider', 'local_customerintel'), $providers);
        $mform->setDefault('llm_provider', 'openai-gpt4');
        $mform->addHelpButton('llm_provider', 'llmprovider', 'local_customerintel');
        
        // LLM API Key - encrypted storage
        $mform->addElement('passwordunmask', 'llm_key', 
            get_string('llmkey', 'local_customerintel'),
            ['size' => 60]);
        $mform->setType('llm_key', PARAM_RAW);
        $mform->addHelpButton('llm_key', 'llmkey', 'local_customerintel');
        $mform->addRule('llm_key', get_string('required'), 'required', null, 'client');
        
        // Custom LLM Endpoint (for custom provider)
        $mform->addElement('text', 'llm_endpoint', 
            get_string('llmendpoint', 'local_customerintel'),
            ['size' => 60]);
        $mform->setType('llm_endpoint', PARAM_URL);
        $mform->hideIf('llm_endpoint', 'llm_provider', 'neq', 'custom');
        
        // Temperature setting - PRD Section 8.3
        $mform->addElement('text', 'llm_temperature', 
            get_string('llmtemperature', 'local_customerintel'),
            ['size' => 10]);
        $mform->setType('llm_temperature', PARAM_FLOAT);
        $mform->setDefault('llm_temperature', '0.2');
        $mform->addRule('llm_temperature', get_string('numeric', 'core_form'), 'numeric', null, 'client');
        $mform->addRule('llm_temperature', get_string('rangevalidation', 'local_customerintel', '0.0-1.0'), 
            'rangelength', [0.0, 1.0], 'client');
        $mform->addHelpButton('llm_temperature', 'llmtemperature', 'local_customerintel');
        
        // Domain Configuration Section - PRD Section 8.2
        $mform->addElement('header', 'domainconfig', get_string('domainsettings', 'local_customerintel'));
        $mform->setExpanded('domainconfig', true);
        
        // Allowed Domains
        $mform->addElement('textarea', 'domains_allow', 
            get_string('domainsallowlist', 'local_customerintel'),
            ['rows' => 10, 'cols' => 50]);
        $mform->setType('domains_allow', PARAM_RAW);
        $mform->addHelpButton('domains_allow', 'domainsallowlist', 'local_customerintel');
        $mform->setDefault('domains_allow', "# One domain per line\n# Example:\nwww.reuters.com\nwww.bloomberg.com\nfinance.yahoo.com\nwww.wsj.com\nwww.ft.com");
        
        // Denied Domains
        $mform->addElement('textarea', 'domains_deny', 
            get_string('domainsdenylist', 'local_customerintel'),
            ['rows' => 10, 'cols' => 50]);
        $mform->setType('domains_deny', PARAM_RAW);
        $mform->addHelpButton('domains_deny', 'domainsdenylist', 'local_customerintel');
        $mform->setDefault('domains_deny', "# Domains to block\n# One per line");
        
        // Cost Control Section - PRD Section 16
        $mform->addElement('header', 'costcontrol', get_string('costcontrol', 'local_customerintel'));
        $mform->setExpanded('costcontrol', true);
        
        // Cost Warning Threshold
        $mform->addElement('text', 'cost_warning', 
            get_string('costwarning', 'local_customerintel'),
            ['size' => 10]);
        $mform->setType('cost_warning', PARAM_FLOAT);
        $mform->setDefault('cost_warning', '50.00');
        $mform->addRule('cost_warning', get_string('required'), 'required', null, 'client');
        $mform->addRule('cost_warning', get_string('numeric', 'core_form'), 'numeric', null, 'client');
        $mform->addHelpButton('cost_warning', 'costwarning', 'local_customerintel');
        
        // Cost Hard Limit
        $mform->addElement('text', 'cost_limit', 
            get_string('costlimit', 'local_customerintel'),
            ['size' => 10]);
        $mform->setType('cost_limit', PARAM_FLOAT);
        $mform->setDefault('cost_limit', '100.00');
        $mform->addRule('cost_limit', get_string('required'), 'required', null, 'client');
        $mform->addRule('cost_limit', get_string('numeric', 'core_form'), 'numeric', null, 'client');
        $mform->addHelpButton('cost_limit', 'costlimit', 'local_customerintel');
        
        // Currency Selection
        $currencies = [
            'USD' => 'USD - US Dollar',
            'EUR' => 'EUR - Euro',
            'GBP' => 'GBP - British Pound',
            'CAD' => 'CAD - Canadian Dollar',
            'AUD' => 'AUD - Australian Dollar'
        ];
        $mform->addElement('select', 'currency', 
            get_string('currency', 'local_customerintel'), $currencies);
        $mform->setDefault('currency', 'USD');
        
        // Data Freshness Section - PRD Section 15
        $mform->addElement('header', 'freshness', get_string('freshnesssettings', 'local_customerintel'));
        $mform->setExpanded('freshness', true);
        
        // Freshness Window (days)
        $mform->addElement('text', 'freshness_window', 
            get_string('freshnesswindow', 'local_customerintel'),
            ['size' => 10]);
        $mform->setType('freshness_window', PARAM_INT);
        $mform->setDefault('freshness_window', '30');
        $mform->addRule('freshness_window', get_string('required'), 'required', null, 'client');
        $mform->addRule('freshness_window', get_string('numeric', 'core_form'), 'numeric', null, 'client');
        $mform->addRule('freshness_window', get_string('minvalue', 'local_customerintel', 1), 
            'minlength', 1, 'client');
        $mform->addHelpButton('freshness_window', 'freshnesswindow', 'local_customerintel');
        
        // Auto-refresh older data
        $mform->addElement('checkbox', 'auto_refresh', 
            get_string('autorefresh', 'local_customerintel'));
        $mform->setDefault('auto_refresh', 0);
        $mform->addHelpButton('auto_refresh', 'autorefresh', 'local_customerintel');
        
        // Advanced Settings Section - PRD Section 13
        $mform->addElement('header', 'advanced', get_string('advancedsettings', 'local_customerintel'));
        $mform->setExpanded('advanced', false);
        
        // JSON Strictness
        $mform->addElement('checkbox', 'json_strict', 
            get_string('jsonstrictness', 'local_customerintel'));
        $mform->setDefault('json_strict', 1);
        $mform->addHelpButton('json_strict', 'jsonstrictness', 'local_customerintel');
        
        // Max retry attempts - PRD Section 17
        $mform->addElement('text', 'max_retries', 
            get_string('maxretries', 'local_customerintel'),
            ['size' => 10]);
        $mform->setType('max_retries', PARAM_INT);
        $mform->setDefault('max_retries', '3');
        $mform->addRule('max_retries', get_string('numeric', 'core_form'), 'numeric', null, 'client');
        
        // Request timeout
        $mform->addElement('text', 'request_timeout', 
            get_string('requesttimeout', 'local_customerintel'),
            ['size' => 10]);
        $mform->setType('request_timeout', PARAM_INT);
        $mform->setDefault('request_timeout', '120');
        $mform->addRule('request_timeout', get_string('numeric', 'core_form'), 'numeric', null, 'client');
        $mform->addHelpButton('request_timeout', 'requesttimeout', 'local_customerintel');
        
        // Enable debug logging
        $mform->addElement('checkbox', 'debug_logging', 
            get_string('debuglogging', 'local_customerintel'));
        $mform->setDefault('debug_logging', 0);
        $mform->addHelpButton('debug_logging', 'debuglogging', 'local_customerintel');
        
        // Feature Flags Section - PRD Section 22
        $mform->addElement('header', 'features', get_string('featureflags', 'local_customerintel'));
        $mform->setExpanded('features', false);
        
        // Enable/Disable plugin
        $mform->addElement('checkbox', 'enabled', 
            get_string('enableplugin', 'local_customerintel'));
        $mform->setDefault('enabled', 1);
        
        // Enable PDF export (future)
        $mform->addElement('checkbox', 'enable_pdf_export', 
            get_string('enablepdfexport', 'local_customerintel'));
        $mform->setDefault('enable_pdf_export', 0);
        $mform->addHelpButton('enable_pdf_export', 'enablepdfexport', 'local_customerintel');
        
        // Enable NotebookLM integration (future)
        $mform->addElement('checkbox', 'enable_notebooklm', 
            get_string('enablenotebooklm', 'local_customerintel'));
        $mform->setDefault('enable_notebooklm', 0);
        $mform->addHelpButton('enable_notebooklm', 'enablenotebooklm', 'local_customerintel');
        
        // Auto-generate synthesis on view
        $mform->addElement('checkbox', 'auto_synthesis_on_view', 
            get_string('autosynthesisonview', 'local_customerintel'));
        $mform->setDefault('auto_synthesis_on_view', 1);
        $mform->addHelpButton('auto_synthesis_on_view', 'autosynthesisonview', 'local_customerintel');
        
        // Action buttons
        $this->add_action_buttons(false, get_string('savechanges'));
    }
    
    /**
     * Validation
     * 
     * @param array $data Form data
     * @param array $files Files
     * @return array Validation errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        
        // Validate temperature range
        if (isset($data['llm_temperature'])) {
            $temp = floatval($data['llm_temperature']);
            if ($temp < 0.0 || $temp > 1.0) {
                $errors['llm_temperature'] = get_string('temperaturerange', 'local_customerintel');
            }
        }
        
        // Validate cost thresholds
        if (isset($data['cost_warning']) && isset($data['cost_limit'])) {
            $warning = floatval($data['cost_warning']);
            $limit = floatval($data['cost_limit']);
            
            if ($warning <= 0) {
                $errors['cost_warning'] = get_string('mustbepositive', 'local_customerintel');
            }
            if ($limit <= 0) {
                $errors['cost_limit'] = get_string('mustbepositive', 'local_customerintel');
            }
            if ($warning > $limit) {
                $errors['cost_warning'] = get_string('warningexceedslimit', 'local_customerintel');
            }
        }
        
        // Validate freshness window
        if (isset($data['freshness_window'])) {
            $days = intval($data['freshness_window']);
            if ($days < 1 || $days > 365) {
                $errors['freshness_window'] = get_string('freshnessrange', 'local_customerintel');
            }
        }
        
        // Validate custom endpoint if custom provider selected
        if (isset($data['llm_provider']) && $data['llm_provider'] === 'custom') {
            if (empty($data['llm_endpoint'])) {
                $errors['llm_endpoint'] = get_string('required');
            } elseif (!filter_var($data['llm_endpoint'], FILTER_VALIDATE_URL)) {
                $errors['llm_endpoint'] = get_string('invalidurl', 'local_customerintel');
            }
        }
        
        // Validate API keys format (basic check)
        if (!empty($data['perplexityapikey']) && strlen($data['perplexityapikey']) < 20) {
            $errors['perplexityapikey'] = get_string('apikeytooshort', 'local_customerintel');
        }
        if (!empty($data['llm_key']) && strlen($data['llm_key']) < 20) {
            $errors['llm_key'] = get_string('apikeytooshort', 'local_customerintel');
        }
        
        // Validate domain lists format
        if (!empty($data['domains_allow'])) {
            $domains = $this->parse_domain_list($data['domains_allow']);
            foreach ($domains as $domain) {
                if (!$this->is_valid_domain($domain)) {
                    $errors['domains_allow'] = get_string('invaliddomainformat', 'local_customerintel', $domain);
                    break;
                }
            }
        }
        
        if (!empty($data['domains_deny'])) {
            $domains = $this->parse_domain_list($data['domains_deny']);
            foreach ($domains as $domain) {
                if (!$this->is_valid_domain($domain)) {
                    $errors['domains_deny'] = get_string('invaliddomainformat', 'local_customerintel', $domain);
                    break;
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Parse domain list from textarea
     * 
     * @param string $text Domain list text
     * @return array Clean domain list
     */
    private function parse_domain_list($text) {
        $lines = explode("\n", $text);
        $domains = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip empty lines and comments
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            $domains[] = $line;
        }
        
        return $domains;
    }
    
    /**
     * Validate domain format
     * 
     * @param string $domain Domain to validate
     * @return bool Valid
     */
    private function is_valid_domain($domain) {
        // Basic domain validation
        return preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i', $domain);
    }
}