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
 * Unit tests for mod_naas\output\widget.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\output;

use advanced_testcase;
use mod_naas\catalogue_cache;

/**
 * Tests for the widget configuration exported to window.NAAS.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\output\widget
 */
final class widget_test extends advanced_testcase {
    /**
     * Store a catalogue snapshot that export_for_widget() accepts.
     */
    private function seed_snapshot(): void {
        catalogue_cache::store([
            'fingerprint' => catalogue_cache::fingerprint((object) get_config('naas')),
            'producers' => [['structure_id' => 'isae', 'acronym' => 'ISAE', 'name' => 'ISAE']],
        ]);
        $this->assertNotNull(catalogue_cache::export_for_widget());
    }

    /**
     * Decoded window.NAAS configuration for a component.
     *
     * @param string $component
     * @return array
     */
    private function exported_config(string $component): array {
        global $PAGE;
        $widget = new widget('nugget-1', SITEID, null, $component);
        $data = $widget->export_for_template($PAGE->get_renderer('core'));
        $config = json_decode($data->config, true);
        $this->assertIsArray($config);
        return $config;
    }

    /**
     * The activity form gets the cached catalogue for its landing page.
     */
    public function test_search_widget_exports_the_catalogue_snapshot(): void {
        $this->resetAfterTest(true);
        $this->seed_snapshot();

        $config = $this->exported_config('NuggetSearchWidget');

        $this->assertIsArray($config['catalogue_snapshot']);
        $this->assertSame('ISAE', $config['catalogue_snapshot']['producers'][0]['acronym']);
    }

    /**
     * The learner view never receives the catalogue snapshot.
     */
    public function test_nugget_view_does_not_export_the_catalogue_snapshot(): void {
        $this->resetAfterTest(true);
        $this->seed_snapshot();

        $config = $this->exported_config('NuggetView');

        $this->assertNull($config['catalogue_snapshot']);
    }

    /**
     * The browser loads NaaS images through media.php: it no longer gets the NaaS API URL.
     */
    public function test_naas_endpoint_is_not_exported(): void {
        $this->resetAfterTest(true);
        set_config('naas_endpoint', 'https://api.naas.example/api', 'naas');

        foreach (['NuggetSearchWidget', 'NuggetView'] as $component) {
            $config = $this->exported_config($component);
            $this->assertArrayNotHasKey('naas_endpoint', $config);
        }
    }
}
