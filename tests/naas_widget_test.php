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
 * Unit tests for mod_naas\naas_widget.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/naas/lib.php');

use advanced_testcase;
use mod_naas\naas_widget;
use moodle_url;

/**
 * Tests for mod_naas\naas_widget.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\naas_widget
 */
class naas_widget_test extends advanced_testcase {

    /**
     * Extract the JSON object assigned to window.NAAS from rendered HTML (handles `;` inside strings).
     *
     * @param string $html
     * @return string
     */
    private function extract_naas_widget_json_string(string $html): string {
        if (!preg_match('/window\.NAAS\s*=\s*(\{)/s', $html, $m, PREG_OFFSET_CAPTURE)) {
            $this->fail('Rendered HTML did not contain window.NAAS assignment');
        }
        $start = $m[1][1];
        $depth = 0;
        $instring = false;
        $escape = false;
        $len = strlen($html);
        for ($i = $start; $i < $len; $i++) {
            $ch = $html[$i];
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($instring) {
                if ($ch === '\\') {
                    $escape = true;
                } else if ($ch === '"') {
                    $instring = false;
                }
                continue;
            }
            if ($ch === '"') {
                $instring = true;
                continue;
            }
            if ($ch === '{') {
                $depth++;
            } else if ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($html, $start, $i - $start + 1);
                }
            }
        }
        $this->fail('Unclosed JSON object in window.NAAS assignment');
    }

    /**
     * Test naas_get_grading_options.
     *
     * @covers \mod_naas\naas_widget::naas_get_grading_options
     */
    public function test_naas_get_grading_options(): void {
        $this->resetAfterTest(true);

        $options = naas_widget::naas_get_grading_options();

        $this->assertArrayHasKey(NAAS_GRADEHIGHEST, $options);
        $this->assertArrayHasKey(NAAS_ATTEMPTFIRST, $options);
        $this->assertArrayHasKey(NAAS_ATTEMPTLAST, $options);
        $this->assertCount(3, $options);
        foreach ($options as $label) {
            $this->assertIsString($label);
            $this->assertNotSame('', $label);
        }
    }

    /**
     * Test naas_widget_html builds config and renders the widget template.
     *
     * @covers \mod_naas\naas_widget::naas_widget_html
     */
    public function test_naas_widget_html(): void {
        global $CFG, $PAGE;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'name' => 'Widget test activity',
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);

        $PAGE->set_course($course);
        $PAGE->set_cm(get_fast_modinfo($course)->get_cm($naas->cmid));
        $PAGE->set_url(new moodle_url('/mod/naas/view.php', ['id' => $naas->cmid]));

        $nuggetid = 'nugget-test-abc';
        $component = 'NuggetView';

        ob_start();
        $html = naas_widget::naas_widget_html($nuggetid, $course->id, $naas->cmid, $component);
        ob_end_clean();

        $this->assertStringContainsString($nuggetid, $html);
        $this->assertStringContainsString($component, $html);
        $this->assertStringContainsString('naas_widget', $html);
        $this->assertStringContainsString('naas-widget-host', $html);
        $this->assertStringContainsString($CFG->wwwroot, $html);

        $json = $this->extract_naas_widget_json_string($html);
        $config = json_decode($json, true);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error(), json_last_error_msg());
        $this->assertIsArray($config);

        $this->assertSame($CFG->wwwroot, $config['moodle_url']);
        $this->assertSame('#naas_widget', $config['mount_point']);
        $this->assertSame($component, $config['component']);
        $this->assertSame($nuggetid, $config['nugget_id']);
        $this->assertSame($course->id, $config['courseId']);
        $this->assertSame($naas->cmid, $config['cm_id']);
        $this->assertArrayHasKey('require_activation_code', $config);
        $this->assertFalse($config['require_activation_code']);
        $this->assertArrayHasKey('catalogue_snapshot', $config);

        $labels = $config['labels'];
        $this->assertArrayHasKey('error_generic_user_message', $labels);
        $this->assertArrayHasKey('nugget_search_collecting', $labels);
        $this->assertArrayHasKey('insertion_code_title', $labels);
        $this->assertArrayHasKey('open_access', $labels);
        $this->assertArrayHasKey('all_nuggets', $labels);
        $this->assertArrayHasKey('by_producers', $labels);
        $this->assertArrayHasKey('view_as_cards', $labels);
        $this->assertArrayHasKey('view_as_list', $labels);
        $this->assertArrayHasKey('back_to_catalogue', $labels);
        $this->assertArrayHasKey('metadata', $labels);
        $this->assertArrayHasKey('preview', $labels['metadata']);
        $this->assertArrayHasKey('rating', $labels);
        $this->assertArrayHasKey('title', $labels['rating']);
        $this->assertArrayHasKey('learning_outcomes_desc', $labels);
        $this->assertArrayHasKey('complete_nugget', $labels);
    }
}
