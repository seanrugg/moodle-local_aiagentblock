<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Unit tests for AI Agent Blocker detector
 *
 * @package    local_aiagentblock
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiagentblock;

defined('MOODLE_INTERNAL') || die();

/**
 * Test cases for the detector class
 *
 * @covers \local_aiagentblock\detector
 */
class detector_test extends \advanced_testcase {

    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        
        // Enable detection
        set_config('enabled', 1, 'local_aiagentblock');
        set_config('log_detections', 1, 'local_aiagentblock');
    }

    /**
     * Test ChatGPT user agent detection
     */
    public function test_detect_chatgpt_agent() {
        global $DB;
        
        $this->setAdminUser();
        
        // Simulate ChatGPT user agent
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; ChatGPT-User/1.0)';
        
        $detected = detector::is_ai_agent();
        
        $this->assertTrue($detected, 'ChatGPT agent should be detected');
        
        // Check if logged
        $logs = $DB->get_records('local_aiagentblock_log');
        $this->assertCount(1, $logs, 'Detection should be logged');
    }

    /**
     * Test Manus AI user agent detection
     */
    public function test_detect_manus_agent() {
        global $DB;
        
        $this->setAdminUser();
        
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Manus/1.0; +https://manus.ai)';
        
        $detected = detector::is_ai_agent();
        
        $this->assertTrue($detected, 'Manus AI agent should be detected');
    }

    /**
     * Test Perplexity Comet user agent detection
     */
    public function test_detect_perplexity_agent() {
        global $DB;
        
        $this->setAdminUser();
        
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Comet/1.0; Perplexity)';
        
        $detected = detector::is_ai_agent();
        
        $this->assertTrue($detected, 'Perplexity Comet agent should be detected');
    }

    /**
     * Test that normal browsers are not detected
     */
    public function test_normal_browser_not_detected() {
        global $DB;
        
        $this->setAdminUser();
        
        // Standard Chrome user agent
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip, deflate, br';
        
        $detected = detector::is_ai_agent();
        
        $this->assertFalse($detected, 'Normal Chrome browser should not be detected');
        
        // No logs should be created
        $logs = $DB->get_records('local_aiagentblock_log');
        $this->assertCount(0, $logs, 'No detection should be logged for normal browsers');
    }

    /**
     * Test detection when plugin is disabled
     */
    public function test_detection_disabled() {
        $this->setAdminUser();
        
        // Disable detection
        set_config('enabled', 0, 'local_aiagentblock');
        
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; ChatGPT-User/1.0)';
        
        $detected = detector::is_ai_agent();
        
        $this->assertFalse($detected, 'Detection should not occur when disabled');
    }

    /**
     * Test browser parsing
     */
    public function test_browser_parsing() {
        $reflection = new \ReflectionClass(detector::class);
        $method = $reflection->getMethod('parse_browser');
        $method->setAccessible(true);
        
        // Test ChatGPT detection
        $result = $method->invoke(null, 'Mozilla/5.0 (compatible; ChatGPT-User/1.0)');
        $this->assertEquals('ChatGPT Agent', $result);
        
        // Test Manus detection
        $result = $method->invoke(null, 'Mozilla/5.0 (compatible; Manus/1.0)');
        $this->assertEquals('Manus AI', $result);
        
        // Test Chrome browser
        $result = $method->invoke(null, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        $this->assertStringContainsString('Chrome', $result);
        
        // Test Firefox browser
        $result = $method->invoke(null, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/120.0');
        $this->assertStringContainsString('Firefox', $result);
    }

    /**
     * Test AI agent identification
     */
    public function test_identify_agent() {
        $agent = detector::identify_agent('Mozilla/5.0 (compatible; ChatGPT-User/1.0)');
        $this->assertNotEmpty($agent);
        
        $agent = detector::identify_agent('Mozilla/5.0 (compatible; Manus/1.0)');
        $this->assertNotEmpty($agent);
        
        $agent = detector::identify_agent('HeadlessChrome/120.0.0.0');
        $this->assertNotEmpty($agent);
    }

    /**
     * Test header detection
     */
    public function test_header_detection() {
        $this->setAdminUser();
        
        // Set webdriver header
        $_SERVER['HTTP_WEBDRIVER'] = 'true';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
        
        $detected = detector::is_ai_agent();
        
        $this->assertTrue($detected, 'Webdriver header should trigger detection');
        
        unset($_SERVER['HTTP_WEBDRIVER']);
    }

    /**
     * Clean up after tests
     */
    protected function tearDown(): void {
        // Clean up server variables
        unset($_SERVER['HTTP_USER_AGENT']);
        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
        unset($_SERVER['HTTP_WEBDRIVER']);
        
        parent::tearDown();
    }
}
