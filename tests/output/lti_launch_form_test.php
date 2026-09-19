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
 * Unit tests for mod_naas\output\lti_launch_form.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\tests\output;

defined('MOODLE_INTERNAL') || die();

use basic_testcase;
use mod_naas\output\lti_launch_form;
use stdClass;

/**
 * Tests for mod_naas\output\lti_launch_form.
 *
 * export_for_template() follows Moodle's templatable contract and accepts
 * renderer_base (including plugin renderers). The $output argument is unused.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\output\lti_launch_form
 */
class lti_launch_form_test extends basic_testcase {

    /** @var string Sample launch URL used across tests. */
    private const SAMPLE_URL = 'https://naas.example.com/lti/launch';

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Build a typical set of LTI form fields.
     *
     * @return array
     */
    private function sample_fields(): array {
        return [
            ['name' => 'lti_version',           'value' => 'LTI-1p0'],
            ['name' => 'lti_message_type',       'value' => 'basic-lti-launch-request'],
            ['name' => 'oauth_consumer_key',     'value' => 'consumer-key-123'],
            ['name' => 'oauth_signature_method', 'value' => 'HMAC-SHA1'],
            ['name' => 'oauth_signature',        'value' => 'abc123sig=='],
        ];
    }

    /**
     * Return a stub renderer_base (never called by export_for_template).
     *
     * @return \renderer_base
     */
    private function renderer(): \renderer_base {
        return $this->createMock(\renderer_base::class);
    }

    // -----------------------------------------------------------------------
    // Return type
    // -----------------------------------------------------------------------

    /**
     * export_for_template() must return a stdClass instance.
     */
    public function test_export_for_template_returns_stdclass(): void {
        $form = new lti_launch_form(self::SAMPLE_URL, []);
        $result = $form->export_for_template($this->renderer());
        $this->assertInstanceOf(stdClass::class, $result);
    }

    // -----------------------------------------------------------------------
    // launchurl
    // -----------------------------------------------------------------------

    /**
     * launchurl in context matches the constructor argument exactly.
     */
    public function test_launchurl_exported_unchanged(): void {
        $form = new lti_launch_form(self::SAMPLE_URL, []);
        $result = $form->export_for_template($this->renderer());
        $this->assertSame(self::SAMPLE_URL, $result->launchurl);
    }

    /**
     * launchurl with query-string is preserved verbatim.
     */
    public function test_launchurl_with_query_string_preserved(): void {
        $url = 'https://naas.example.com/lti/launch?context=demo&locale=fr';
        $form = new lti_launch_form($url, []);
        $result = $form->export_for_template($this->renderer());
        $this->assertSame($url, $result->launchurl);
    }

    // -----------------------------------------------------------------------
    // fields
    // -----------------------------------------------------------------------

    /**
     * An empty fields array is exported as an empty array.
     */
    public function test_empty_fields_exported_as_empty_array(): void {
        $form = new lti_launch_form(self::SAMPLE_URL, []);
        $result = $form->export_for_template($this->renderer());
        $this->assertIsArray($result->fields);
        $this->assertEmpty($result->fields);
    }

    /**
     * Fields array is returned verbatim (same reference, no transformation).
     */
    public function test_fields_exported_correctly(): void {
        $fields = $this->sample_fields();
        $form   = new lti_launch_form(self::SAMPLE_URL, $fields);
        $result = $form->export_for_template($this->renderer());
        $this->assertSame($fields, $result->fields);
    }

    /**
     * Field count in context matches the number of fields supplied.
     */
    public function test_fields_count_matches_input(): void {
        $fields = $this->sample_fields();
        $form   = new lti_launch_form(self::SAMPLE_URL, $fields);
        $result = $form->export_for_template($this->renderer());
        $this->assertCount(count($fields), $result->fields);
    }

    /**
     * Individual field name/value entries are intact in the exported context.
     */
    public function test_individual_field_values_intact(): void {
        $fields = [
            ['name' => 'oauth_signature', 'value' => 'test-sig'],
        ];
        $form   = new lti_launch_form(self::SAMPLE_URL, $fields);
        $result = $form->export_for_template($this->renderer());
        $this->assertSame('oauth_signature', $result->fields[0]['name']);
        $this->assertSame('test-sig',        $result->fields[0]['value']);
    }

    // -----------------------------------------------------------------------
    // renderer_base contract
    // -----------------------------------------------------------------------

    /**
     * Passing a renderer_base stub as $output must not throw a TypeError.
     */
    public function test_accepts_renderer_base(): void {
        $form   = new lti_launch_form(self::SAMPLE_URL, $this->sample_fields());
        $result = $form->export_for_template($this->renderer());
        $this->assertInstanceOf(stdClass::class, $result);
    }

    /**
     * The $output argument is never used inside the method — its value does
     * not affect the returned context in any way.
     */
    public function test_output_argument_does_not_affect_context(): void {
        $fields  = $this->sample_fields();
        $form    = new lti_launch_form(self::SAMPLE_URL, $fields);

        $result1 = $form->export_for_template($this->renderer());
        $result2 = $form->export_for_template($this->renderer());

        $this->assertSame($result1->launchurl, $result2->launchurl);
        $this->assertSame($result1->fields,    $result2->fields);
    }
}
