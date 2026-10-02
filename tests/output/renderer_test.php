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
 * Unit tests for mod_naas\output\renderer.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\output;

use advanced_testcase;
use mod_naas\output\view_page;
use mod_naas\output\index_page;
use mod_naas\output\lti_launch_form;
use stdClass;

/**
 * Tests for mod_naas\output\renderer.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/).
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 * @covers \mod_naas\output\renderer
 */
class renderer_test extends advanced_testcase {
    /**
     * Test render_view_page.
     */
    public function test_render_view_page(): void {
        global $PAGE;
        $this->resetAfterTest();
        $renderer = $PAGE->get_renderer('mod_naas');

        $courseurl = new \moodle_url('/course/view.php', ['id' => 1]);
        $viewpage = new view_page($courseurl, 'Back to course', null, '<div>Widget</div>');

        // We use ob_start to avoid outputting HTML to the terminal.
        ob_start();
        $html = $renderer->render_view_page($viewpage);
        ob_end_clean();

        $this->assertStringContainsString('Back to course', $html);
        $this->assertStringContainsString('<div>Widget</div>', $html);
    }

    /**
     * Test render_index_page.
     */
    public function test_render_index_page(): void {
        global $PAGE;
        $this->resetAfterTest();
        $renderer = $PAGE->get_renderer('mod_naas');

        $courseurl = new \moodle_url('/course/view.php', ['id' => 1]);
        $indexpage = new index_page($courseurl, 'Back to course', 'Test Course', true, []);

        ob_start();
        $html = $renderer->render_index_page($indexpage);
        ob_end_clean();

        $this->assertStringContainsString('Test Course', $html);
        $this->assertStringContainsString('Back to course', $html);
    }

    /**
     * Test render_lti_launch_form.
     */
    public function test_render_lti_launch_form(): void {
        global $PAGE;
        $this->resetAfterTest();
        $renderer = $PAGE->get_renderer('mod_naas');

        $fields = [
            ['name' => 'lti_message_type', 'value' => 'basic-lti-launch-request'],
            ['name' => 'lti_version', 'value' => 'LTI-1p0'],
        ];
        $form = new lti_launch_form('https://Naas.example.com/launch', $fields);

        ob_start();
        $html = $renderer->render_lti_launch_form($form);
        ob_end_clean();

        $this->assertStringContainsString('https://Naas.example.com/launch', $html);
        $this->assertStringContainsString('lti_message_type', $html);
        $this->assertStringContainsString('basic-lti-launch-request', $html);
    }
}
