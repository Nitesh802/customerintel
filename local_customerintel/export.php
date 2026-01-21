<?php
/**
 * Export Customer Intelligence Report
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/services/assembler.php');

use local_customerintel\services\assembler;

// Parameters
$runid = required_param('runid', PARAM_INT);
$format = optional_param('format', 'html', PARAM_ALPHA);

// Security
require_login();
$context = context_system::instance();
require_capability('local/customerintel:exportreports', $context);

// Get run details
$run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);
$company = $DB->get_record('local_ci_company', ['id' => $run->companyid], '*', MUST_EXIST);

// Check user can export this report
if ($run->initiatedbyuserid != $USER->id && !has_capability('local/customerintel:exportallreports', $context)) {
    throw new moodle_exception('nopermission', 'local_customerintel');
}

// Initialize assembler
$assembler = new assembler();
$reportdata = $assembler->assemble_report($runid);

// Set filename
$filename = 'customerintel_' . $company->name . '_' . date('Y-m-d');
$filename = clean_filename($filename);

switch ($format) {
    case 'html':
        // Export as standalone HTML
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.html"');
        
        // Generate standalone HTML with embedded CSS
        echo '<!DOCTYPE html>';
        echo '<html lang="en">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Customer Intelligence Report - ' . htmlspecialchars($company->name) . '</title>';
        
        // Embed CSS
        echo '<style>';
        echo file_get_contents(__DIR__ . '/styles/report-export.css');
        echo '</style>';
        
        echo '</head>';
        echo '<body>';
        
        // Render report content
        echo render_html_report($reportdata);
        
        echo '</body>';
        echo '</html>';
        break;
        
    case 'markdown':
        // Export as Markdown
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.md"');
        
        echo render_markdown_report($reportdata);
        break;
        
    case 'json':
        // Export raw JSON data
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        
        echo json_encode($reportdata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        break;
        
    case 'synthesis_json':
        // Export synthesis JSON data
        $synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);
        if (!$synthesis || empty($synthesis->jsoncontent)) {
            throw new moodle_exception('nosynthesisdata', 'local_customerintel', '', null, 
                'No synthesis data available for run ' . $runid);
        }
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '_synthesis.json"');
        
        // Output the stored synthesis JSON content
        echo $synthesis->jsoncontent;
        break;
        
    default:
        throw new moodle_exception('invalidformat', 'local_customerintel');
}

/**
 * Render HTML report
 * 
 * @param array $data Report data
 * @return string HTML content
 */
function render_html_report($data) {
    $html = '<div class="customerintel-report">';
    
    // Header
    $html .= '<header>';
    $html .= '<h1>' . htmlspecialchars($data['company']->name) . '</h1>';
    if (isset($data['targetcompany'])) {
        $html .= '<h2>vs ' . htmlspecialchars($data['targetcompany']->name) . '</h2>';
    }
    $html .= '<p>Generated: ' . htmlspecialchars($data['generateddate']) . '</p>';
    $html .= '<p>Runtime: ' . htmlspecialchars($data['runtime']) . '</p>';
    
    // Telemetry summary
    if (!empty($data['telemetry'])) {
        $html .= '<div class="telemetry">';
        $html .= '<h3>Performance Metrics</h3>';
        $html .= '<p>Tokens: ' . $data['telemetry']['tokens'] . '</p>';
        $html .= '<p>Duration: ' . $data['telemetry']['duration'] . '</p>';
        $html .= '<p>Cost: ' . $data['telemetry']['cost'] . '</p>';
        $html .= '</div>';
    }
    
    // Progress
    $html .= '<div class="progress">';
    $html .= '<h3>Analysis Progress</h3>';
    $html .= '<p>' . $data['progress']['completed'] . '/' . $data['progress']['total'] . ' NBs Completed (' . $data['progress']['percentage'] . '%)</p>';
    $html .= '</div>';
    $html .= '</header>';
    
    // Phases
    $html .= '<main>';
    foreach ($data['phases'] as $phase) {
        $html .= '<section class="phase">';
        $html .= '<h2>' . htmlspecialchars($phase['title']) . '</h2>';
        $html .= '<p class="phase-meta">' . $phase['itemcount'] . ' items • ' . $phase['time'] . '</p>';
        
        foreach ($phase['items'] as $item) {
            $html .= '<article class="nb-item">';
            $html .= '<h3>' . htmlspecialchars($item['title']) . '</h3>';
            
            if (!empty($item['prompt'])) {
                $html .= '<div class="prompt">';
                $html .= '<h4>Prompt:</h4>';
                $html .= '<p>' . htmlspecialchars($item['prompt']) . '</p>';
                $html .= '</div>';
            }
            
            $html .= '<div class="response">';
            $html .= '<h4>Analysis:</h4>';
            $html .= $item['response'];
            $html .= '</div>';
            
            if (!empty($item['citations'])) {
                $html .= '<div class="citations">';
                $html .= '<h4>Sources (' . count($item['citations']) . '):</h4>';
                $html .= '<ul>';
                foreach ($item['citations'] as $citation) {
                    $html .= '<li>';
                    if (!empty($citation['url'])) {
                        $html .= '<a href="' . htmlspecialchars($citation['url']) . '" target="_blank">';
                        $html .= htmlspecialchars($citation['title']);
                        $html .= '</a>';
                    } else {
                        $html .= htmlspecialchars($citation['title']);
                    }
                    if (!empty($citation['page'])) {
                        $html .= ' (p. ' . htmlspecialchars($citation['page']) . ')';
                    }
                    if (!empty($citation['date'])) {
                        $html .= ' - ' . htmlspecialchars($citation['date']);
                    }
                    if (!empty($citation['quote'])) {
                        $html .= '<blockquote>' . htmlspecialchars($citation['quote']) . '</blockquote>';
                    }
                    $html .= '</li>';
                }
                $html .= '</ul>';
                $html .= '</div>';
            }
            
            $html .= '</article>';
        }
        
        $html .= '</section>';
    }
    $html .= '</main>';
    
    // Footer
    $html .= '<footer>';
    $html .= '<p>Customer Intelligence Report | ' . htmlspecialchars($data['company']->name);
    if (isset($data['targetcompany'])) {
        $html .= ' vs ' . htmlspecialchars($data['targetcompany']->name);
    }
    $html .= ' | Generated ' . htmlspecialchars($data['generateddate']) . '</p>';
    $html .= '</footer>';
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Render Markdown report
 * 
 * @param array $data Report data
 * @return string Markdown content
 */
function render_markdown_report($data) {
    $md = '# Customer Intelligence Report: ' . $data['company']->name . "\n\n";
    
    if (isset($data['targetcompany'])) {
        $md .= '## Comparison with: ' . $data['targetcompany']->name . "\n\n";
    }
    
    $md .= "**Generated:** " . $data['generateddate'] . "\n";
    $md .= "**Runtime:** " . $data['runtime'] . "\n\n";
    
    // Telemetry
    if (!empty($data['telemetry'])) {
        $md .= "## Performance Metrics\n\n";
        $md .= "- **Tokens:** " . $data['telemetry']['tokens'] . "\n";
        $md .= "- **Duration:** " . $data['telemetry']['duration'] . "\n";
        $md .= "- **Cost:** " . $data['telemetry']['cost'] . "\n\n";
    }
    
    // Progress
    $md .= "## Analysis Progress\n\n";
    $md .= $data['progress']['completed'] . "/" . $data['progress']['total'] . " NBs Completed (" . $data['progress']['percentage'] . "%)\n\n";
    
    $md .= "---\n\n";
    
    // Phases
    foreach ($data['phases'] as $phase) {
        $md .= "## " . $phase['title'] . "\n\n";
        $md .= "*" . $phase['itemcount'] . " items • " . $phase['time'] . "*\n\n";
        
        foreach ($phase['items'] as $item) {
            $md .= "### " . $item['title'] . "\n\n";
            
            if (!empty($item['prompt'])) {
                $md .= "**Prompt:** " . $item['prompt'] . "\n\n";
            }
            
            $md .= "**Analysis:**\n\n";
            // Convert HTML to Markdown (simplified)
            $response = strip_tags($item['response'], '<p><ul><ol><li><strong><em>');
            $response = str_replace(['<p>', '</p>'], ['', "\n\n"], $response);
            $response = str_replace(['<strong>', '</strong>'], ['**', '**'], $response);
            $response = str_replace(['<em>', '</em>'], ['*', '*'], $response);
            $response = str_replace(['<ul>', '</ul>', '<ol>', '</ol>'], ['', "\n", '', "\n"], $response);
            $response = str_replace(['<li>', '</li>'], ['- ', "\n"], $response);
            $md .= $response . "\n\n";
            
            if (!empty($item['citations'])) {
                $md .= "**Sources:**\n\n";
                foreach ($item['citations'] as $citation) {
                    $md .= "- ";
                    if (!empty($citation['url'])) {
                        $md .= "[" . $citation['title'] . "](" . $citation['url'] . ")";
                    } else {
                        $md .= $citation['title'];
                    }
                    if (!empty($citation['page'])) {
                        $md .= " (p. " . $citation['page'] . ")";
                    }
                    if (!empty($citation['date'])) {
                        $md .= " - " . $citation['date'];
                    }
                    if (!empty($citation['quote'])) {
                        $md .= "\n  > " . $citation['quote'];
                    }
                    $md .= "\n";
                }
                $md .= "\n";
            }
            
            $md .= "---\n\n";
        }
    }
    
    $md .= "\n---\n\n";
    $md .= "*Customer Intelligence Report | " . $data['company']->name;
    if (isset($data['targetcompany'])) {
        $md .= " vs " . $data['targetcompany']->name;
    }
    $md .= " | Generated " . $data['generateddate'] . "*\n";
    
    return $md;
}