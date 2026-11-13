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
 * Tests the export of an empty approved context list by {@see \mod_naas\privacy\provider}.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\privacy\provider::class)]
/**
 * Exporting no context writes nothing, even for a user who has attempts.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_naas\privacy\provider
 */
final class provider_empty_export_test extends \core_privacy\tests\provider_testcase {
    /**
     * An approved list without context exports no data.
     */
    public function test_export_user_data_with_no_context_writes_nothing(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        /** @var \mod_naas_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $generator->create_completed_attempt($user->id, $naas->cmid, 0.5);
        $context = \context_module::instance($naas->cmid);

        provider::export_user_data(new approved_contextlist($user, 'mod_naas', []));

        $this->assertFalse(writer::with_context($context)->has_any_data());

        // The same user with the activity context approved does export the attempt.
        provider::export_user_data(new approved_contextlist($user, 'mod_naas', [$context->id]));
        $this->assertTrue(writer::with_context($context)->has_any_data());
    }
}
