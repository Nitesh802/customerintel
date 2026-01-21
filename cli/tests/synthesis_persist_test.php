<?php
/**
 * Test synthesis persistence functionality
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../classes/services/synthesis_engine.php');

use local_customerintel\services\synthesis_engine;

/**
 * Tests for synthesis persistence functionality
 */
class synthesis_persist_test extends advanced_testcase {

    /**
     * Test persist() method creates and updates synthesis records
     */
    public function test_persist_creates_and_updates_synthesis() {
        global $DB;
        
        $this->resetAfterTest();
        
        // Create test data
        $runid = 12345;
        $bundle = [
            'html' => '<div>Test HTML Content</div>',
            'json' => json_encode(['test' => 'data']),
            'voice_report' => json_encode(['voice_check' => 'passed']),
            'selfcheck_report' => json_encode(['pass' => true, 'violations' => []])
        ];
        
        $synthesis_engine = new synthesis_engine();
        
        // Test initial persist (insert)
        $synthesis_engine->persist($runid, $bundle);
        
        $record = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);
        $this->assertNotEmpty($record);
        $this->assertEquals($bundle['html'], $record->htmlcontent);
        $this->assertEquals($bundle['json'], $record->jsoncontent);
        $this->assertEquals($bundle['voice_report'], $record->voice_report);
        $this->assertEquals($bundle['selfcheck_report'], $record->selfcheck_report);
        $this->assertNotEmpty($record->createdat);
        $this->assertNotEmpty($record->updatedat);
        
        $original_createdat = $record->createdat;
        
        // Wait a moment to ensure timestamp difference
        sleep(1);
        
        // Test update persist
        $updated_bundle = [
            'html' => '<div>Updated HTML Content</div>',
            'json' => json_encode(['updated' => 'data']),
            'voice_report' => json_encode(['voice_check' => 'updated']),
            'selfcheck_report' => json_encode(['pass' => false, 'violations' => [['rule' => 'test']]])
        ];
        
        $synthesis_engine->persist($runid, $updated_bundle);
        
        $updated_record = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);
        $this->assertNotEmpty($updated_record);
        $this->assertEquals($updated_bundle['html'], $updated_record->htmlcontent);
        $this->assertEquals($updated_bundle['json'], $updated_record->jsoncontent);
        $this->assertEquals($updated_bundle['voice_report'], $updated_record->voice_report);
        $this->assertEquals($updated_bundle['selfcheck_report'], $updated_record->selfcheck_report);
        
        // Check that createdat didn't change but updatedat did
        $this->assertEquals($original_createdat, $updated_record->createdat);
        $this->assertGreaterThan($original_createdat, $updated_record->updatedat);
    }

    /**
     * Test that view_report.php correctly identifies synthesis data
     */
    public function test_view_report_checks_synthesis() {
        global $DB;
        
        $this->resetAfterTest();
        
        $runid = 12345;
        
        // Test with no synthesis data
        $synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);
        $has_synthesis = $synthesis && !empty($synthesis->htmlcontent);
        $this->assertFalse($has_synthesis);
        
        // Create synthesis data
        $DB->insert_record('local_ci_synthesis', [
            'runid' => $runid,
            'htmlcontent' => '<div>Test Content</div>',
            'jsoncontent' => json_encode(['test' => 'data']),
            'selfcheck_report' => json_encode(['pass' => true, 'violations' => []]),
            'createdat' => time(),
            'updatedat' => time()
        ]);
        
        // Test with synthesis data
        $synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);
        $has_synthesis = $synthesis && !empty($synthesis->htmlcontent);
        $this->assertTrue($has_synthesis);
    }
}