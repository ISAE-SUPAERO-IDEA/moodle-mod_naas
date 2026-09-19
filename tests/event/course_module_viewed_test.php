<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Unit tests for mod_naas\event\course_module_viewed.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\tests\event;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use mod_naas\event\course_module_viewed;

/**
 * Tests for course_module_viewed event.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\event\course_module_viewed
 */
class course_module_viewed_test extends advanced_testcase {

    /**
     * Test the event content and mapping.
     */
    public function test_event(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
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
