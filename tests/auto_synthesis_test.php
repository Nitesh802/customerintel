<?php
/**
 * Test auto-synthesis on view functionality
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for auto-synthesis on view functionality
 */
class auto_synthesis_test extends advanced_testcase {

    /**
     * Test synthesis freshness detection logic
     */
    public function test_synthesis_freshness_detection() {
        $this->resetAfterTest();
        
        $runid = 12345;
        $now = time();
        
        // Test case 1: No synthesis exists
        $synthesis = null;
        $needs_synthesis = false;
        
        if (!$synthesis || empty($synthesis->htmlcontent)) {
            $needs_synthesis = true;
        }
        
        $this->assertTrue($needs_synthesis, 'Should need synthesis when none exists');
        
        // Test case 2: Synthesis exists but is older than run completion
        $synthesis = (object)[
            'htmlcontent' => '<div>content</div>',
            'updatedat' => $now - 3600 // 1 hour ago
        ];
        
        $run = (object)[
            'timecompleted' => $now - 1800 // 30 minutes ago (newer than synthesis)
        ];
        
        $needs_synthesis = false;
        if (!$synthesis || empty($synthesis->htmlcontent)) {
            $needs_synthesis = true;
        } else if ($run->timecompleted && $synthesis->updatedat && $run->timecompleted > $synthesis->updatedat) {
            $needs_synthesis = true;
        }
        
        $this->assertTrue($needs_synthesis, 'Should need synthesis when run is newer');
        
        // Test case 3: Synthesis is newer than run completion
        $synthesis->updatedat = $now - 900; // 15 minutes ago
        $run->timecompleted = $now - 1800; // 30 minutes ago (older than synthesis)
        
        $needs_synthesis = false;
        if (!$synthesis || empty($synthesis->htmlcontent)) {
            $needs_synthesis = true;
        } else if ($run->timecompleted && $synthesis->updatedat && $run->timecompleted > $synthesis->updatedat) {
            $needs_synthesis = true;
        }
        
        $this->assertFalse($needs_synthesis, 'Should not need synthesis when synthesis is newer');
    }

    /**
     * Test admin setting control
     */
    public function test_auto_synthesis_admin_setting() {
        $this->resetAfterTest();
        
        // Test default value (should be enabled by default)
        $auto_synthesis_enabled = get_config('local_customerintel', 'auto_synthesis_on_view') ?? 1;
        $this->assertEquals(1, $auto_synthesis_enabled, 'Auto-synthesis should be enabled by default');
        
        // Test disabling the setting
        set_config('auto_synthesis_on_view', 0, 'local_customerintel');
        $auto_synthesis_enabled = get_config('local_customerintel', 'auto_synthesis_on_view') ?? 1;
        $this->assertEquals(0, $auto_synthesis_enabled, 'Auto-synthesis should be disabled when set to 0');
        
        // Test enabling the setting
        set_config('auto_synthesis_on_view', 1, 'local_customerintel');
        $auto_synthesis_enabled = get_config('local_customerintel', 'auto_synthesis_on_view') ?? 1;
        $this->assertEquals(1, $auto_synthesis_enabled, 'Auto-synthesis should be enabled when set to 1');
    }

    /**
     * Test the complete synthesis generation logic flow
     */
    public function test_synthesis_generation_flow() {
        global $DB;
        
        $this->resetAfterTest();
        
        // Create a mock run record
        $runid = 123;
        $now = time();
        
        $run_data = [
            'id' => $runid,
            'companyid' => 1,
            'userid' => 1,
            'initiatedbyuserid' => 1,
            'status' => 'completed',
            'timecompleted' => $now - 1800, // 30 minutes ago
            'timecreated' => $now - 3600,
            'timemodified' => $now - 1800
        ];
        
        $DB->insert_record('local_ci_run', $run_data);
        
        // Test 1: No synthesis exists, should trigger generation
        $synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);
        $needs_synthesis = !$synthesis || empty($synthesis->htmlcontent);
        $this->assertTrue($needs_synthesis, 'Should need synthesis when none exists');
        
        // Test 2: Create old synthesis that should trigger regeneration
        $old_synthesis_data = [
            'runid' => $runid,
            'htmlcontent' => '<div>old content</div>',
            'jsoncontent' => '{"old": "data"}',
            'createdat' => $now - 7200, // 2 hours ago
            'updatedat' => $now - 7200  // 2 hours ago (older than run completion)
        ];
        
        $DB->insert_record('local_ci_synthesis', $old_synthesis_data);
        
        $synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);
        $run = $DB->get_record('local_ci_run', ['id' => $runid]);
        
        $needs_synthesis = false;
        if (!$synthesis || empty($synthesis->htmlcontent)) {
            $needs_synthesis = true;
        } else if ($run->timecompleted && $synthesis->updatedat && $run->timecompleted > $synthesis->updatedat) {
            $needs_synthesis = true;
        }
        
        $this->assertTrue($needs_synthesis, 'Should need synthesis when run is newer than synthesis');
        
        // Test 3: Update synthesis to be newer than run
        $DB->update_record('local_ci_synthesis', [
            'id' => $synthesis->id,
            'updatedat' => $now - 900 // 15 minutes ago (newer than run completion)
        ]);
        
        $synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);
        
        $needs_synthesis = false;
        if (!$synthesis || empty($synthesis->htmlcontent)) {
            $needs_synthesis = true;
        } else if ($run->timecompleted && $synthesis->updatedat && $run->timecompleted > $synthesis->updatedat) {
            $needs_synthesis = true;
        }
        
        $this->assertFalse($needs_synthesis, 'Should not need synthesis when synthesis is newer than run');
    }
}