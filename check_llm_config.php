<?php
/**
 * Check LLM Configuration
 *
 * Quick diagnostic to verify llm_provider and llm_key are both set
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/local/customerintel/check_llm_config.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('LLM Configuration Check');

echo $OUTPUT->header();

?>
<style>
.config-check { max-width: 800px; margin: 20px auto; padding: 20px; }
.status-box { padding: 20px; margin: 15px 0; border-radius: 8px; font-size: 18px; }
.status-ready { background: #d4edda; border: 2px solid #28a745; color: #155724; }
.status-notready { background: #f8d7da; border: 2px solid #dc3545; color: #721c24; }
.config-item { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 5px; }
.config-label { font-weight: bold; color: #666; }
.config-value { font-size: 24px; margin: 10px 0; }
.value-set { color: #28a745; }
.value-notset { color: #dc3545; }
</style>

<div class="config-check">

<h1>🔧 LLM Configuration Check</h1>

<?php

$provider = get_config('local_customerintel', 'llm_provider');
$key = get_config('local_customerintel', 'llm_key');
$perplexity = get_config('local_customerintel', 'perplexityapikey');

echo "<div class='config-item'>";
echo "<div class='config-label'>LLM Provider:</div>";
if (empty($provider)) {
    echo "<div class='config-value value-notset'>❌ NOT SET</div>";
    echo "<p>This is required. Go to <a href='/local/customerintel/admin_settings.php'>admin settings</a> and select a provider.</p>";
} else {
    echo "<div class='config-value value-set'>✅ $provider</div>";

    // Show which endpoint this maps to
    $endpoints = [
        'openai-gpt4' => 'https://api.openai.com/v1/chat/completions',
        'openai-gpt35' => 'https://api.openai.com/v1/chat/completions',
        'anthropic-claude' => 'https://api.anthropic.com/v1/messages'
    ];
    if (isset($endpoints[$provider])) {
        echo "<p style='color: #666; font-size: 14px;'>Endpoint: {$endpoints[$provider]}</p>";
    }
}
echo "</div>";

echo "<div class='config-item'>";
echo "<div class='config-label'>LLM API Key:</div>";
if (empty($key)) {
    echo "<div class='config-value value-notset'>❌ NOT SET</div>";
    echo "<p>Required for OpenAI/Anthropic. Set in <a href='/local/customerintel/admin_settings.php'>admin settings</a>.</p>";
} else {
    $masked = substr($key, 0, 12) . '...' . substr($key, -6);
    echo "<div class='config-value value-set'>✅ $masked</div>";
    echo "<p style='color: #666; font-size: 14px;'>Length: " . strlen($key) . " characters</p>";
}
echo "</div>";

echo "<div class='config-item'>";
echo "<div class='config-label'>Perplexity API Key:</div>";
if (empty($perplexity)) {
    echo "<div class='config-value value-notset'>❌ NOT SET</div>";
    echo "<p>Optional. Only needed if using Perplexity for research.</p>";
} else {
    $masked = substr($perplexity, 0, 12) . '...' . substr($perplexity, -6);
    echo "<div class='config-value value-set'>✅ $masked</div>";
    echo "<p style='color: #666; font-size: 14px;'>Length: " . strlen($perplexity) . " characters</p>";
}
echo "</div>";

// Overall status
echo "<h2>Overall Status</h2>";
if (!empty($provider) && !empty($key)) {
    echo "<div class='status-box status-ready'>";
    echo "<strong style='font-size: 28px;'>✅ CONFIGURATION COMPLETE</strong><br><br>";
    echo "LLM client is properly configured and ready to use.<br><br>";
    echo "<strong>Next step:</strong> Run the NB generation test<br>";
    echo "<a href='/local/customerintel/test_nb_generation.php' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;'>";
    echo "▶️ Test NB Generation";
    echo "</a>";
    echo "</div>";
} else {
    echo "<div class='status-box status-notready'>";
    echo "<strong style='font-size: 28px;'>❌ CONFIGURATION INCOMPLETE</strong><br><br>";
    echo "Missing required settings:<br><ul>";
    if (empty($provider)) {
        echo "<li><strong>llm_provider</strong> - Select which LLM to use (OpenAI, Anthropic, etc.)</li>";
    }
    if (empty($key)) {
        echo "<li><strong>llm_key</strong> - API key for the selected provider</li>";
    }
    echo "</ul>";
    echo "<a href='/local/customerintel/admin_settings.php' style='background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;'>";
    echo "⚙️ Go to Admin Settings";
    echo "</a>";
    echo "</div>";
}

?>

<h2>📋 Configuration Guide</h2>

<div style="background: #e7f3ff; padding: 20px; border-radius: 8px; border-left: 5px solid #007bff;">
    <h3>If you have an OpenAI API key:</h3>
    <ol>
        <li>LLM Provider: Select <strong>"OpenAI GPT-4"</strong></li>
        <li>LLM API Key: Paste your OpenAI key (starts with sk-proj-...)</li>
        <li>Save settings</li>
    </ol>

    <h3>If you have an Anthropic API key:</h3>
    <ol>
        <li>LLM Provider: Select <strong>"Anthropic Claude 3"</strong></li>
        <li>LLM API Key: Paste your Anthropic key (starts with sk-ant-...)</li>
        <li>Save settings</li>
    </ol>
</div>

<h2>🔍 What Each Setting Does</h2>

<table style="width: 100%; border-collapse: collapse;">
    <tr style="background: #f0f0f0;">
        <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Setting</th>
        <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Purpose</th>
        <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Required?</th>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd; padding: 10px;"><strong>llm_provider</strong></td>
        <td style="border: 1px solid #ddd; padding: 10px;">Tells the system which AI service to use for NB generation</td>
        <td style="border: 1px solid #ddd; padding: 10px;"><span style="color: #dc3545;">✓ REQUIRED</span></td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd; padding: 10px;"><strong>llm_key</strong></td>
        <td style="border: 1px solid #ddd; padding: 10px;">API key for authentication with OpenAI or Anthropic</td>
        <td style="border: 1px solid #ddd; padding: 10px;"><span style="color: #dc3545;">✓ REQUIRED</span></td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd; padding: 10px;"><strong>perplexityapikey</strong></td>
        <td style="border: 1px solid #ddd; padding: 10px;">API key for Perplexity research queries (optional enhancement)</td>
        <td style="border: 1px solid #ddd; padding: 10px;"><span style="color: #666;">Optional</span></td>
    </tr>
</table>

</div>

<?php
echo $OUTPUT->footer();
?>
