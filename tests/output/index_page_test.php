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
 * Unit tests for mod_naas\output\index_page.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\output;

use basic_testcase;
use mod_naas\output\index_page;
use moodle_url;
use stdClass;

/**
 * Tests for mod_naas\output\index_page.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/).
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 * @covers \mod_naas\output\index_page
 */
class index_page_test extends basic_testcase {
    // Helpers.

    /**
     * Build an index page that does not group rows by section.
     *
     * @param array $rows
     * @return index_page
     */
    private function make_page(array $rows = []): index_page {
        return new index_page(
            new moodle_url('/course/view.php', ['id' => 10]),
            'Back to course',
            'Nugget Activities',
            false,
            $rows
        );
    }

    /**
     * Build an index page that groups rows by section.
     *
     * @param array $rows
     * @return index_page
     */
    private function make_sections_page(array $rows = []): index_page {
        return new index_page(
            new moodle_url('/course/view.php', ['id' => 10]),
            'Back to course',
            'Nugget Activities',
            true,
            $rows
        );
    }

    /**
     * Return a stub renderer_base.
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
     * courseurl must be a plain string without HTML-encoded ampersands.
     */
    public function test_courseurl_exported_as_string(): void {
        $result = $this->make_page()->export_for_template($this->renderer());
        $this->assertIsString($result->courseurl);
        $this->assertStringContainsString('id=10', $result->courseurl);
        $this->assertStringNotContainsString('&amp;', $result->courseurl);
    }

    // Backtocourse.

    /**
     * backtocourse is passed through unchanged.
     */
    public function test_backtocourse_exported_unchanged(): void {
        $page = new index_page(
            new moodle_url('/course/view.php', ['id' => 1]),
            'Go back',
            'Heading',
            false,
            []
        );
        $result = $page->export_for_template($this->renderer());
        $this->assertSame('Go back', $result->backtocourse);
    }

    // Heading.

    /**
     * heading is exported unchanged.
     */
    public function test_heading_exported_correctly(): void {
        $result = $this->make_page()->export_for_template($this->renderer());
        $this->assertSame('Nugget Activities', $result->heading);
    }

    // Usesections.

    /**
     * usesections=true is exported as boolean true.
     */
    public function test_usesections_flag_exported_true(): void {
        $result = $this->make_sections_page()->export_for_template($this->renderer());
        $this->assertTrue($result->usesections);
    }

    /**
     * usesections=false is exported as boolean false.
     */
    public function test_usesections_flag_exported_false(): void {
        $result = $this->make_page()->export_for_template($this->renderer());
        $this->assertFalse($result->usesections);
    }

    // Rows.

    /**
     * An empty rows array is exported as an empty array.
     */
    public function test_empty_rows_exported_as_empty_array(): void {
        $result = $this->make_page([])->export_for_template($this->renderer());
        $this->assertIsArray($result->rows);
        $this->assertEmpty($result->rows);
    }

    /**
     * Rows passed in are returned verbatim (same reference).
     */
    public function test_rows_exported_correctly(): void {
        $rows = [
            (object)['name' => 'Nugget A', 'section' => 'Section 1'],
            (object)['name' => 'Nugget B', 'section' => 'Section 2'],
        ];
        $result = $this->make_sections_page($rows)->export_for_template($this->renderer());
        $this->assertSame($rows, $result->rows);
    }

    /**
     * Row count in context matches the number of rows supplied.
     */
    public function test_row_count_matches_input(): void {
        $rows = [
            (object)['name' => 'One'],
            (object)['name' => 'Two'],
            (object)['name' => 'Three'],
        ];
        $result = $this->make_page($rows)->export_for_template($this->renderer());
        $this->assertCount(3, $result->rows);
    }

    /**
     * Individual row content is preserved.
     */
    public function test_row_content_preserved(): void {
        $rows = [(object)['name' => 'Test Nugget', 'intro' => 'An intro']];
        $result = $this->make_page($rows)->export_for_template($this->renderer());
        $this->assertSame('Test Nugget', $result->rows[0]->name);
        $this->assertSame('An intro', $result->rows[0]->intro);
    }
}
