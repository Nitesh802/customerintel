<?php
/**
 * Customer Intelligence Dashboard - Language strings
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin name and description
$string['pluginname'] = 'Customer Intelligence Dashboard';
$string['plugindescription'] = 'Automated NB-1 through NB-15 research protocol for Customer and Target company analysis';

// Capabilities
$string['customerintel:runanalysis'] = 'Run intelligence analysis';
$string['customerintel:viewreports'] = 'View intelligence reports';
$string['customerintel:managesources'] = 'Manage data sources';
$string['customerintel:exportreports'] = 'Export reports';
$string['customerintel:viewcosts'] = 'View cost information';
$string['customerintel:configuresettings'] = 'Configure plugin settings';

// Navigation
$string['dashboard'] = 'Intelligence Dashboard';
$string['newreport'] = 'New Report';
$string['savedreports'] = 'Saved Reports';
$string['settings'] = 'Settings';

// Company types
$string['customer'] = 'Customer';
$string['target'] = 'Target';
$string['unknown'] = 'Unknown';

// Run statuses
$string['status:queued'] = 'Queued';
$string['status:running'] = 'Running';
$string['status:succeeded'] = 'Completed';
$string['status:failed'] = 'Failed';

// Forms
$string['selectcustomer'] = 'Select Customer Company';
$string['selecttarget'] = 'Select Target Company';
$string['runintelligence'] = 'Run Intelligence';
$string['estimatedcost'] = 'Estimated Cost';
$string['estimatedtokens'] = 'Estimated Tokens';
$string['forcerefresh'] = 'Force Refresh';

// Sources
$string['sources'] = 'Sources';
$string['addsource'] = 'Add Source';
$string['uploadfile'] = 'Upload File';
$string['addurl'] = 'Add URL';
$string['manualtext'] = 'Manual Text';
$string['approved'] = 'Approved';
$string['rejected'] = 'Rejected';

// NB Protocol sections - mapped to TSX phases
$string['nb1'] = 'Executive Pressure Profile';
$string['nb2'] = 'Operating Environment';
$string['nb3'] = 'Financial Health & Trajectory';
$string['nb4'] = 'Strategic Priorities';
$string['nb5'] = 'Margin & Cost Analysis';
$string['nb6'] = 'Technology & Digital Maturity';
$string['nb7'] = 'Operational Excellence';
$string['nb8'] = 'Competitive Positioning';
$string['nb9'] = 'Growth & Expansion';
$string['nb10'] = 'Risk & Resilience';
$string['nb11'] = 'Leadership & Culture';
$string['nb12'] = 'Stakeholder Dynamics';
$string['nb13'] = 'Innovation Capacity';
$string['nb14'] = 'Strategic Synthesis';
$string['nb15'] = 'Strategic Inflection Analysis';

// Progress indicators
$string['progress'] = 'Progress';
$string['completed'] = 'Completed';
$string['remaining'] = 'Remaining';
$string['estimatedtime'] = 'Estimated Time';

// Version management
$string['version'] = 'Version';
$string['viewhistory'] = 'View History';
$string['showchanges'] = 'Show Changes Since Previous';
$string['snapshot'] = 'Snapshot';
$string['diff'] = 'Changes';

// Settings page
$string['apisettings'] = 'API Settings';
$string['perplexitykey'] = 'Perplexity API Key';
$string['llmkey'] = 'LLM API Key';
$string['domainsallowlist'] = 'Allowed Domains';
$string['domainsdenylist'] = 'Denied Domains';
$string['freshnesswindow'] = 'Freshness Window (days)';
$string['costwarning'] = 'Cost Warning Threshold';
$string['costlimit'] = 'Cost Hard Limit';

// Errors
$string['error:nocompany'] = 'Please select both Customer and Target companies';
$string['error:apifailure'] = 'API request failed: {$a}';
$string['error:invalidjson'] = 'Invalid JSON response from NB processor';
$string['error:costexceeded'] = 'Cost limit exceeded';
$string['error:missingpermission'] = 'You do not have permission to perform this action';
$string['nosynthesisdata'] = 'No synthesis data available for this run';

// Success messages
$string['success:runstarted'] = 'Intelligence run started successfully';
$string['success:reportexported'] = 'Report exported successfully';
$string['success:settingssaved'] = 'Settings saved successfully';

// Admin Settings Form strings
$string['adminsettings'] = 'Admin Settings';
$string['adminsettingsdesc'] = 'Configure API keys, domain filters, cost controls, and other plugin settings.';
$string['llmprovider'] = 'LLM Provider';
$string['llmprovider_help'] = 'Select the Large Language Model provider for intelligence extraction';
$string['llmendpoint'] = 'Custom LLM Endpoint';
$string['llmendpoint_help'] = 'API endpoint URL for custom LLM provider';
$string['llmtemperature'] = 'LLM Temperature';
$string['llmtemperature_help'] = 'Controls randomness in LLM responses (0.0 = deterministic, 1.0 = creative). Recommended: 0.2 for extraction tasks';
$string['customprovider'] = 'Custom Provider';

// Domain settings
$string['domainsettings'] = 'Domain Configuration';
$string['domainsettings_desc'] = 'Configure allowed and blocked domains for source discovery';
$string['domainsallowlist_help'] = 'Enter allowed domains, one per line. Leave empty to allow all domains (except those in deny list). Lines starting with # are comments.';
$string['domainsdenylist_help'] = 'Enter blocked domains, one per line. These domains will be rejected even if in the allow list. Lines starting with # are comments.';

// Cost control
$string['costcontrol'] = 'Cost Control';
$string['costwarning_help'] = 'Users will be warned when estimated cost exceeds this threshold (in selected currency)';
$string['costlimit_help'] = 'Runs will be blocked if estimated cost exceeds this limit (in selected currency)';
$string['currency'] = 'Currency';
$string['currency_help'] = 'Currency for cost calculations and limits';

// Freshness settings
$string['freshnesssettings'] = 'Data Freshness';
$string['freshnesswindow_help'] = 'Number of days before company data is considered stale and needs refreshing';
$string['autorefresh'] = 'Auto-refresh stale data';
$string['autorefresh_help'] = 'Automatically refresh data older than the freshness window when running new analysis';

// Advanced settings
$string['advancedsettings'] = 'Advanced Settings';
$string['jsonstrictness'] = 'Strict JSON Validation';
$string['jsonstrictness_help'] = 'Enforce strict JSON schema validation for all NB outputs. Disable only for debugging.';
$string['maxretries'] = 'Maximum Retry Attempts';
$string['maxretries_help'] = 'Number of times to retry failed API calls before giving up';
$string['requesttimeout'] = 'Request Timeout (seconds)';
$string['requesttimeout_help'] = 'Maximum time to wait for API responses before timeout';
$string['debuglogging'] = 'Enable Debug Logging';
$string['debuglogging_help'] = 'Log detailed debug information for troubleshooting. Warning: May impact performance.';

// Feature flags
$string['featureflags'] = 'Feature Flags';
$string['enableplugin'] = 'Enable Plugin';
$string['enablepdfexport'] = 'Enable PDF Export (Beta)';
$string['enablepdfexport_help'] = 'Allow users to export reports as PDF documents. This feature is still in development.';
$string['enablenotebooklm'] = 'Enable NotebookLM Integration (Future)';
$string['enablenotebooklm_help'] = 'Enable integration with Google NotebookLM for advanced analysis. Coming soon.';
$string['autosynthesisonview'] = 'Generate synthesis automatically on view';
$string['autosynthesisonview_help'] = 'Automatically generate synthesis when viewing reports if no synthesis exists or if the run is newer than existing synthesis. When disabled, only manual synthesis generation is available.';

// Validation messages
$string['temperaturerange'] = 'Temperature must be between 0.0 and 1.0';
$string['mustbepositive'] = 'Value must be positive';
$string['warningexceedslimit'] = 'Warning threshold cannot exceed hard limit';
$string['freshnessrange'] = 'Freshness window must be between 1 and 365 days';
$string['invalidurl'] = 'Invalid URL format';
$string['apikeytooshort'] = 'API key appears to be invalid (too short)';
$string['invaliddomainformat'] = 'Invalid domain format: {$a}';
$string['minvalue'] = 'Minimum value is {$a}';
$string['rangevalidation'] = 'Value must be in range {$a}';

// Success messages
$string['settingssaved'] = 'All settings have been saved successfully and caches have been purged.';
$string['settingsfailed'] = 'Failed to save settings';
$string['llmprovidersetTo'] = 'LLM Provider set to: {$a}';
$string['costlimitsetTo'] = 'Cost limit set to: {$a}';
$string['freshnesssetTo'] = 'Data freshness window set to: {$a} days';

// API Status
$string['apistatus'] = 'API Status';
$string['configured'] = 'Configured';
$string['notconfigured'] = 'Not Configured';
$string['testing'] = 'Testing...';
$string['connected'] = 'Connected';
$string['connectionfailed'] = 'Connection Failed';

// Help texts
$string['helpheading'] = 'Configuration Help';
$string['help_apikeys'] = 'API keys are encrypted and stored securely. Never share your API keys.';
$string['help_domains'] = 'Domain filters help ensure data quality by controlling which sources are used for analysis.';
$string['help_costs'] = 'Cost controls prevent unexpected charges. Warning threshold prompts for confirmation, hard limit blocks execution.';
$string['help_freshness'] = 'The freshness window determines when cached data should be refreshed. Longer windows reduce costs but may use outdated information.';

// Help button strings
$string['perplexitykey_help'] = 'Your Perplexity API key for web search and discovery. Get it from perplexity.ai/settings/api';
$string['llmkey_help'] = 'API key for your chosen LLM provider (OpenAI, Anthropic, etc.)';

// Additional settings strings needed
$string['report'] = 'Report';

// Event strings
$string['eventsettingsupdated'] = 'Settings updated';
$string['customerintel:view'] = 'View Customer Intelligence Dashboard';
$string['customerintel:run'] = 'Run analyses in the Customer Intelligence Dashboard';
$string['customerintel:manage'] = 'Manage Customer Intelligence sources and plugin settings';

// Additional UI strings for templates
$string['intelligencedashboard'] = 'Intelligence Dashboard';
$string['newanalysisrun'] = 'New Analysis Run';
$string['viewreports'] = 'View Reports';
$string['managesources'] = 'Manage Sources';
$string['queued'] = 'Queued';
$string['running'] = 'Running';
$string['failed'] = 'Failed';
$string['recentruns'] = 'Recent Runs';
$string['recentcompanies'] = 'Recent Companies';
$string['systeminfo'] = 'System Info';
$string['noruns'] = 'No runs yet';
$string['view'] = 'View';
$string['status'] = 'Status';
$string['started'] = 'Started';
$string['backtodashboard'] = 'Back to Dashboard';
$string['addsourcedesc'] = 'Add a source (URL, file upload, or text)';
$string['existingsources'] = 'Existing Sources';
$string['title'] = 'Title';
$string['actions'] = 'Actions';
$string['toggleapprove'] = 'Toggle approval';
$string['nosources'] = 'No sources found';

// Additional source management strings
$string['selectcompany'] = 'Select a company';
$string['sourcetype'] = 'Source Type';
$string['sourceurl'] = 'Source URL';
$string['description'] = 'Description';
$string['addsource'] = 'Add Source';
$string['sourceadded'] = 'Source successfully added.';
$string['sourceadderror'] = 'Error adding source: {$a}';
$string['sourcetype_url'] = 'Website';
$string['sourcetype_file'] = 'File Upload';
$string['sourcetype_text'] = 'Manual Text';
$string['sourceurlhelp'] = 'Enter the full URL including https://';
$string['descriptionplaceholder'] = 'Enter a brief description of this source';
$string['created'] = 'Created';

// Admin Settings Strings (Legacy - kept for compatibility)
$string['openaiapikey'] = 'OpenAI API Key';
$string['openai_api_key_desc'] = 'Enter your OpenAI API key for LLM processing';
$string['auto_source_discovery'] = 'Automatic Source Discovery';
$string['auto_source_discovery_desc'] = 'Enable automatic discovery of data sources when adding companies';
$string['apisettings_desc'] = 'Configure API keys for external services';
$string['featuresettings'] = 'Feature Settings';
$string['featuresettings_desc'] = 'Enable or disable plugin features';
$string['costcontrols'] = 'Cost Controls';
$string['costcontrols_desc'] = 'Set limits and warnings for API usage costs';
$string['costwarning_desc'] = 'Warning threshold in USD - users will be warned when estimated costs exceed this amount';
$string['costlimit_desc'] = 'Hard limit in USD - operations will be blocked if estimated costs exceed this amount';
$string['domainsallowlist_desc'] = 'Enter allowed domains, one per line. Leave empty to allow all domains.';
$string['domainsdenylist_desc'] = 'Enter blocked domains, one per line. These domains will be rejected even if in the allow list.';
$string['freshnesswindow_desc'] = 'Number of days before cached data is considered stale and needs refreshing';

// Standard settings page strings
$string['pluginsettings'] = 'Customer Intelligence Settings';
$string['settingsinstructions'] = 'Use this page to configure API keys and automation options.';
$string['perplexityapikey'] = 'Perplexity API key';
$string['perplexityapikey_desc'] = 'Enter your Perplexity API key for automatic source discovery.';
$string['openaiapikey'] = 'OpenAI API key';
$string['openaiapikey_desc'] = 'Enter your OpenAI API key for analysis and report generation.';
$string['automaticsourcediscovery'] = 'Enable automatic source discovery';
$string['automaticsourcediscovery_desc'] = 'When enabled, the system automatically searches for new sources using LLM APIs.';
$string['runqueued'] = 'Analysis run has been queued. You can monitor progress on the dashboard.';
$string['runsettings'] = 'Analysis Run Settings';
$string['company'] = 'Customer Company';
$string['submitrun'] = 'Start Analysis';

// Logging strings
$string['viewlogs'] = 'View Logs';
$string['filterbyrun'] = 'Filter by Run ID';
$string['filterbylevel'] = 'Filter by Level';
$string['allruns'] = 'All Runs';
$string['alllevels'] = 'All Levels';
$string['info'] = 'Info';
$string['warning'] = 'Warning';
$string['error'] = 'Error';
$string['debug'] = 'Debug';
$string['filter'] = 'Filter';
$string['clearfilters'] = 'Clear Filters';
$string['logid'] = 'Log ID';
$string['runid'] = 'Run ID';
$string['level'] = 'Level';
$string['message'] = 'Message';
$string['timestamp'] = 'Timestamp';
$string['logsummary'] = 'Log Summary';
$string['showingxofy'] = 'Showing {$a->showing} of {$a->total} total logs';
$string['nologsfound'] = 'No logs found matching your criteria';