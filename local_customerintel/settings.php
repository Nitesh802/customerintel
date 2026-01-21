<?php
/**
 * Customer Intelligence Dashboard - Settings Registration
 * v17.1 Unified Artifact Compatibility System
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Only proceed if user has site config capability
if ($hassiteconfig) {
    
    // Create the main settings page
    $settings = new admin_settingpage(
        'local_customerintel_settings',
        get_string('pluginname', 'local_customerintel')
    );
    
    // === API Settings Section ===
    $settings->add(new admin_setting_heading(
        'local_customerintel/apisettings',
        get_string('apisettings', 'local_customerintel'),
        get_string('apisettings_desc', 'local_customerintel')
    ));
    
    // Perplexity API Key
    $settings->add(new admin_setting_configtext(
        'local_customerintel/perplexityapikey',
        get_string('perplexityapikey', 'local_customerintel'),
        get_string('perplexityapikey_desc', 'local_customerintel'),
        '',
        PARAM_TEXT
    ));
    
    // OpenAI API Key
    $settings->add(new admin_setting_configtext(
        'local_customerintel/openaiapikey',
        get_string('openaiapikey', 'local_customerintel'),
        get_string('openaiapikey_desc', 'local_customerintel'),
        '',
        PARAM_TEXT
    ));
    
    // === Feature Settings Section ===
    $settings->add(new admin_setting_heading(
        'local_customerintel/featuresettings',
        get_string('featuresettings', 'local_customerintel'),
        get_string('featuresettings_desc', 'local_customerintel')
    ));
    
    // Automatic Source Discovery
    $settings->add(new admin_setting_configcheckbox(
        'local_customerintel/automaticsourcediscovery',
        get_string('automaticsourcediscovery', 'local_customerintel'),
        get_string('automaticsourcediscovery_desc', 'local_customerintel'),
        0
    ));
    
    // Auto-generate synthesis on view
    $settings->add(new admin_setting_configcheckbox(
        'local_customerintel/auto_synthesis_on_view',
        get_string('autosynthesisonview', 'local_customerintel'),
        get_string('autosynthesisonview_help', 'local_customerintel'),
        1
    ));
    
    // Enable synthesis engine
    $settings->add(new admin_setting_configcheckbox(
        'local_customerintel/enable_synthesis',
        'Enable Synthesis Engine',
        'When enabled, the synthesis engine will build intelligence playbooks.',
        1
    ));
    
    // Enable assembler integration
    $settings->add(new admin_setting_configcheckbox(
        'local_customerintel/enable_assembler_integration',
        'Enable Assembler Integration',
        'When enabled, synthesis engine will use pre-assembled sections from the assembler instead of generating from raw NB data. Provides richer narrative content while maintaining full backward compatibility.',
        0
    ));
    
    // === Cost Controls Section ===
    $settings->add(new admin_setting_heading(
        'local_customerintel/costcontrols',
        get_string('costcontrols', 'local_customerintel'),
        get_string('costcontrols_desc', 'local_customerintel')
    ));
    
    // Cost Warning Threshold
    $settings->add(new admin_setting_configtext(
        'local_customerintel/costwarning',
        get_string('costwarning', 'local_customerintel'),
        get_string('costwarning_desc', 'local_customerintel'),
        '50',
        PARAM_FLOAT
    ));
    
    // Cost Hard Limit
    $settings->add(new admin_setting_configtext(
        'local_customerintel/costlimit',
        get_string('costlimit', 'local_customerintel'),
        get_string('costlimit_desc', 'local_customerintel'),
        '100',
        PARAM_FLOAT
    ));
    
    // === Domain Configuration Section ===
    $settings->add(new admin_setting_heading(
        'local_customerintel/domainsettings',
        get_string('domainsettings', 'local_customerintel'),
        get_string('domainsettings_desc', 'local_customerintel')
    ));
    
    // Allowed Domains
    $settings->add(new admin_setting_configtextarea(
        'local_customerintel/domainsallowlist',
        get_string('domainsallowlist', 'local_customerintel'),
        get_string('domainsallowlist_desc', 'local_customerintel'),
        ''
    ));
    
    // Denied Domains
    $settings->add(new admin_setting_configtextarea(
        'local_customerintel/domainsdenylist',
        get_string('domainsdenylist', 'local_customerintel'),
        get_string('domainsdenylist_desc', 'local_customerintel'),
        ''
    ));
    
    // === Data Freshness Section ===
    $settings->add(new admin_setting_heading(
        'local_customerintel/freshnesssettings',
        get_string('freshnesssettings', 'local_customerintel'),
        ''
    ));
    
    // Freshness Window
    $settings->add(new admin_setting_configtext(
        'local_customerintel/freshnesswindow',
        get_string('freshnesswindow', 'local_customerintel'),
        get_string('freshnesswindow_desc', 'local_customerintel'),
        '30',
        PARAM_INT
    ));
    
    // === Predictive Analytics Section ===
    $settings->add(new admin_setting_heading(
        'local_customerintel/predictiveanalytics',
        'Predictive Analytics & Anomaly Detection',
        'Configure predictive intelligence features for forward-looking insights and anomaly detection'
    ));
    
    // Enable Predictive Engine
    $settings->add(new admin_setting_configcheckbox(
        'local_customerintel/enable_predictive_engine',
        'Enable Predictive Engine',
        'Enable forecasting and predictive analytics features in the analytics dashboard',
        1
    ));
    
    // Enable Anomaly Alerts
    $settings->add(new admin_setting_configcheckbox(
        'local_customerintel/enable_anomaly_alerts',
        'Enable Anomaly Alerts',
        'Enable automated anomaly detection and alert logging',
        1
    ));
    
    // Forecast Horizon Days
    $settings->add(new admin_setting_configtext(
        'local_customerintel/forecast_horizon_days',
        'Forecast Horizon (Days)',
        'Default number of days to forecast into the future',
        '30',
        PARAM_INT
    ));
    
    // === Diagnostics & Performance Section ===
    $settings->add(new admin_setting_heading(
        'local_customerintel/diagnosticsperformance',
        'Diagnostics & Performance',
        'Configure analytics dashboard, telemetry, and performance settings'
    ));
    
    // Enable Analytics Dashboard
    $settings->add(new admin_setting_configcheckbox(
        'local_customerintel/enable_analytics_dashboard',
        'Enable Analytics Dashboard',
        'Enable the analytics dashboard with historical insights and trend visualization',
        1
    ));
    
    // Enable Telemetry Trends
    $settings->add(new admin_setting_configcheckbox(
        'local_customerintel/enable_telemetry_trends',
        'Enable Telemetry Trends',
        'Enable telemetry trend analysis and performance metrics visualization',
        1
    ));
    
    // Enable Safe Mode
    $settings->add(new admin_setting_configcheckbox(
        'local_customerintel/enable_safe_mode',
        'Enable Safe Mode',
        'Enable safe mode to limit data queries and disable heavy processing for faster responses',
        0
    ));
    
    // Enable Pipeline Safe Mode
    $settings->add(new admin_setting_configcheckbox(
        'local_customerintel/enable_pipeline_safe_mode',
        'Enable Pipeline Safe Mode',
        'Continue pipeline execution even when diversity/QA gates fail. Produces artifacts with warnings instead of blocking. For debugging and recovery purposes only.',
        0
    ));
    
    // Enable Trace Mode
    $settings->add(new admin_setting_configcheckbox(
        'local_customerintel/enable_trace_mode',
        'Enable Transparent Pipeline Tracing',
        'Enable transparent pipeline view that captures JSON artifacts from every phase of report generation (discovery, NB orchestration, assembler, synthesis, QA). This provides complete data lineage but increases storage requirements. Only enable for debugging or analysis purposes.',
        0
    ));
    
    // Enable Detailed Phase Logging
    $settings->add(new admin_setting_configcheckbox(
        'local_customerintel/enable_detailed_trace_logging',
        'Enable Trace Mode (Detailed Phase Logging)',
        'Enable detailed checkpoint logging for synthesis phase debugging. Shows entry/exit points for orchestration, normalization, rebalancing, validation, synthesis, drafting, and bundle creation. Logs are viewable through the web interface for each run.',
        0
    ));
    
    // v17.1 Compatibility System Info (Read-only)
    $settings->add(new admin_setting_heading(
        'local_customerintel/compatibilitysystem',
        'v17.1 Unified Artifact Compatibility System',
        'This system ensures permanent alignment between pipeline outputs and viewer expectations. ' .
        'All artifact operations flow through the compatibility adapter with automatic schema normalization, ' .
        'name aliasing, and Evidence Diversity Context preservation. Status: ✅ Active'
    ));
    
    // IMPORTANT: Add the settings page to the admin tree
    // This is the critical line that registers the settings with Moodle
    $ADMIN->add('localplugins', $settings);
    
    // Add navigation links for convenience
    // Dashboard link
    $ADMIN->add('localplugins', 
        new admin_externalpage(
            'local_customerintel_dashboard',
            get_string('dashboard', 'local_customerintel'),
            new moodle_url('/local/customerintel/dashboard.php'),
            'local/customerintel:view'
        )
    );
    
    // Reports link
    $ADMIN->add('localplugins', 
        new admin_externalpage(
            'local_customerintel_reports',
            get_string('viewreports', 'local_customerintel'),
            new moodle_url('/local/customerintel/reports.php'),
            'local/customerintel:viewreports'
        )
    );
    
    // Analytics dashboard link
    $ADMIN->add('localplugins', 
        new admin_externalpage(
            'local_customerintel_analytics',
            'Analytics Dashboard',
            new moodle_url('/local/customerintel/analytics.php'),
            'local/customerintel:view'
        )
    );
    
    // Sources management link
    $ADMIN->add('localplugins', 
        new admin_externalpage(
            'local_customerintel_sources',
            get_string('managesources', 'local_customerintel'),
            new moodle_url('/local/customerintel/sources.php'),
            'local/customerintel:managesources'
        )
    );
    
    // Company management link
    $ADMIN->add('localplugins', 
        new admin_externalpage(
            'local_customerintel_companies',
            get_string('managecompanies', 'local_customerintel'),
            new moodle_url('/local/customerintel/companies.php'),
            'local/customerintel:manage'
        )
    );
}