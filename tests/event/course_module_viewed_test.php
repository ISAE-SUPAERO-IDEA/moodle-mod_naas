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
 * Unit tests for mod_naas\event\course_module_viewed.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\event;

use advanced_testcase;
use mod_naas\event\course_module_viewed;

/**
 * Tests for course_module_viewed event.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/).
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 * @covers \mod_naas\event\course_module_viewed
 */
class course_module_viewed_test extends advanced_testcase {
    /**
     * Test the event content and mapping.
     */
    public function test_event(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas   = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $context = \context_module::instance($naas->cmid);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event = course_module_viewed::create([
            'objectid' => $naas->id,
            'context'  => $context,
        ]);
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf(course_module_viewed::class, $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertSame('naas', $event->objecttable);
        $this->assertSame($naas->id, $event->objectid);
        $url = new \moodle_url('/mod/naas/view.php', ['id' => $naas->cmid]);
        $this->assertEquals($url, $event->get_url());
        $this->assertStringContainsString('naas', $event->get_description());
        $this->assertEventContextNotUsed($event);
    }
}
