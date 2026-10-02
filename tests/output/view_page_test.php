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
 * Unit tests for mod_naas\output\view_page.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\output;

use basic_testcase;
use mod_naas\output\view_page;
use moodle_url;
use stdClass;

/**
 * Tests for mod_naas\output\view_page.
 *
 * All tests are pure-PHP: no database, no renderer call.
 * export_for_template() receives a mock renderer_base but never calls it.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/).
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 * @covers \mod_naas\output\view_page
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class view_page_test extends basic_testcase {
    // Helpers.

    /**
     * Build a minimal view_page instance for use in tests.
     *
     * @param stdClass|null $nextactivity  Pass a stdClass to exercise next-activity branch.
     * @param string        $widgethtml
     * @return view_page
     */
    private function make_page(?stdClass $nextactivity = null, string $widgethtml = ''): view_page {
        return new view_page(
            new moodle_url('/course/view.php', ['id' => 42]),
            'Back to course',
            $nextactivity,
            $widgethtml
        );
    }

    /**
     * Return a stub renderer_base (never called by export_for_template).
     *
     * @return \renderer_base
     */
    private function renderer(): \renderer_base {
        return $this->createMock(\renderer_base::class);
    }

    // Return type.

    /**
     * export_for_template() must return a stdClass instance.
     */
    public function test_export_for_template_returns_stdclass(): void {
        $result = $this->make_page()->export_for_template($this->renderer());
        $this->assertInstanceOf(stdClass::class, $result);
    }

    // Courseurl.

    /**
     * courseurl is serialised to a string (no ampersand encoding).
     */
    public function test_courseurl_exported_as_string(): void {
        $result = $this->make_page()->export_for_template($this->renderer());
        $this->assertIsString($result->courseurl);
    }

    /**
     * courseurl preserves the path and query parameters.
     */
    public function test_courseurl_contains_correct_params(): void {
        $result = $this->make_page()->export_for_template($this->renderer());
        $this->assertStringContainsString('/course/view.php', $result->courseurl);
        $this->assertStringContainsString('id=42', $result->courseurl);
    }

    /**
     * courseurl must not contain HTML-encoded ampersands.
     */
    public function test_courseurl_has_no_html_encoded_ampersand(): void {
        $page = new view_page(
            new moodle_url('/course/view.php', ['id' => 1, 'sesskey' => 'abc']),
            'Back',
            null,
            ''
        );
        $result = $page->export_for_template($this->renderer());
        $this->assertStringNotContainsString('&amp;', $result->courseurl);
    }

    // Backtocourse.

    /**
     * backtocourse string is passed through unchanged.
     */
    public function test_backtocourse_exported_unchanged(): void {
        $page = new view_page(
            new moodle_url('/course/view.php', ['id' => 1]),
            'Return to my course',
            null,
            ''
        );
        $result = $page->export_for_template($this->renderer());
        $this->assertSame('Return to my course', $result->backtocourse);
    }

    // Widgethtml.

    /**
     * widgethtml is passed through verbatim (pre-rendered HTML string).
     */
    public function test_widgethtml_exported_unchanged(): void {
        $html = '<div id="naas_widget" data-config="{}"></div>';
        $result = $this->make_page(null, $html)->export_for_template($this->renderer());
        $this->assertSame($html, $result->widgethtml);
    }

    // Hasnextactivity – no next activity.

    /**
     * When nextactivity is null, hasnextactivity is false.
     */
    public function test_no_next_activity_sets_flag_false(): void {
        $result = $this->make_page(null)->export_for_template($this->renderer());
        $this->assertFalse($result->hasnextactivity);
    }

    /**
     * When nextactivity is null, neither nextactivityname nor nextactivityurl are set.
     */
    public function test_no_next_activity_omits_activity_fields(): void {
        $result = $this->make_page(null)->export_for_template($this->renderer());
        $this->assertFalse(property_exists($result, 'nextactivityname'));
        $this->assertFalse(property_exists($result, 'nextactivityurl'));
    }

    // Hasnextactivity – with next activity.

    /**
     * When nextactivity is provided, hasnextactivity is true.
     */
    public function test_next_activity_sets_flag_true(): void {
        $next = new stdClass();
        $next->name = 'Next Nugget';
        $next->link = '/mod/naas/view.php?id=99';

        $result = $this->make_page($next)->export_for_template($this->renderer());
        $this->assertTrue($result->hasnextactivity);
    }

    /**
     * nextactivityname matches the name field of the next-activity object.
     */
    public function test_next_activity_name_exported(): void {
        $next = new stdClass();
        $next->name = 'Advanced Aerodynamics';
        $next->link = '/mod/naas/view.php?id=99';

        $result = $this->make_page($next)->export_for_template($this->renderer());
        $this->assertSame('Advanced Aerodynamics', $result->nextactivityname);
    }

    /**
     * nextactivityurl contains the forceview=1 query parameter.
     */
    public function test_next_activity_url_has_forceview_param(): void {
        $next = new stdClass();
        $next->name = 'Next';
        $next->link = '/mod/naas/view.php?id=99';

        $result = $this->make_page($next)->export_for_template($this->renderer());
        $this->assertStringContainsString('forceview=1', $result->nextactivityurl);
    }

    /**
     * nextactivityurl preserves the original activity id alongside forceview.
     */
    public function test_next_activity_url_preserves_original_id(): void {
        $next = new stdClass();
        $next->name = 'Next';
        $next->link = '/mod/naas/view.php?id=77';

        $result = $this->make_page($next)->export_for_template($this->renderer());
        $this->assertStringContainsString('id=77', $result->nextactivityurl);
    }

    /**
     * nextactivityurl must not contain HTML-encoded ampersands.
     */
    public function test_next_activity_url_has_no_html_ampersand(): void {
        $next = new stdClass();
        $next->name = 'Next';
        $next->link = '/mod/naas/view.php?id=77';

        $result = $this->make_page($next)->export_for_template($this->renderer());
        $this->assertStringNotContainsString('&amp;', $result->nextactivityurl);
    }
}
