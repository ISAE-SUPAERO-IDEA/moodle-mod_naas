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
 * Tests for the mod_naas_refresh_cache web service.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\external;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\refresh_cache::class)]
/**
 * Tests for the cache refresh button of the plugin settings.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\external\refresh_cache
 */
final class refresh_cache_test extends \advanced_testcase {
    /**
     * Log in as an editing teacher of a fresh course.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('naas_endpoint', 'https://api.naas.example/api', 'naas');
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
    }

    /**
     * Only an administrator may refresh the caches.
     */
    public function test_refresh_cache_requires_the_admin_capability(): void {
        $this->expectException(\required_capability_exception::class);
        refresh_cache::execute();
    }

    /**
     * An administrator reaches the refresh, which refuses to run without a saved API URL.
     */
    public function test_refresh_cache_runs_the_refresh_for_an_admin(): void {
        $this->setAdminUser();
        set_config('naas_endpoint', '', 'naas');

        try {
            refresh_cache::execute();
            $this->fail('The refresh must refuse an empty API URL');
        } catch (\moodle_exception $e) {
            $this->assertSame('cache_refresh_failed', $e->errorcode);
        }
    }
}
