<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Unit tests for mod_naas\mod_util.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\tests;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use mod_naas\mod_util;

/**
 * Tests for mod_naas\mod_util.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_naas\mod_util
 * @covers ::get_next_activity_url
 */
class mod_util_test extends advanced_testcase {

    /**
     * Test get_next_activity_url.
     */
    public function test_get_next_activity_url(): void {
        global $PAGE;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $naas1 = $this->getDataGenerator()->create_module('naas', ['course' => $course->id, 'name' => 'N1']);
        $naas2 = $this->getDataGenerator()->create_module('naas', ['course' => $course->id, 'name' => 'N2']);
        
        $PAGE->set_course($course);
        $PAGE->set_cm(get_fast_modinfo($course)->get_cm($naas1->cmid));

        $next = mod_util::get_next_activity_url();
        
        $this->assertNotNull($next);
        $this->assertEquals('N2', $next->name);
        $this->assertStringContainsString('id=' . $naas2->cmid, $next->link->out());
    }

    /**
     * Test get_next_activity_url when there is no next activity.
     */
    public function test_get_next_activity_url_none(): void {
        global $PAGE;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $naas1 = $this->getDataGenerator()->create_module('naas', ['course' => $course->id, 'name' => 'N1']);
        
        $PAGE->set_course($course);
        $PAGE->set_cm(get_fast_modinfo($course)->get_cm($naas1->cmid));

        $next = mod_util::get_next_activity_url();
        
        $this->assertNull($next);
    }

    /**
     * Test get_next_activity_url skips activities in hidden sections.
     */
    public function test_get_next_activity_url_skips_hidden_sections(): void {
        global $PAGE, $DB;
        $this->resetAfterTest(true);

        // Enough sections so section 3 exists before we add activities (avoid a non-naas module
        // becoming the "next" link between N1 and N3).
        $course = $this->getDataGenerator()->create_course(['numsections' => 4]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        // Section 1: visible, contains N1.
        $naas1 = $this->getDataGenerator()->create_module('naas', ['course' => $course->id, 'section' => 1, 'name' => 'N1']);
        
        // Section 2: hidden, contains N2.
        $section2 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 2]);
        $DB->set_field('course_sections', 'visible', 0, ['id' => $section2->id]);
        $this->getDataGenerator()->create_module('naas', ['course' => $course->id, 'section' => 2, 'name' => 'N2']);
        
        // N3 alone in visible section 3 (section already created by course numsections).
        $this->getDataGenerator()->create_module('naas', ['course' => $course->id, 'section' => 3, 'name' => 'N3']);

        $PAGE->set_course($course);
        $PAGE->set_cm(get_fast_modinfo($course)->get_cm($naas1->cmid));

        $next = mod_util::get_next_activity_url();
        
        // It should skip N2 (hidden section) and go to N3.
        $this->assertNotNull($next);
        $this->assertEquals('N3', $next->name);
    }

    /**
     * Activities hidden on the course page must be skipped (uservisible false).
     */
    public function test_get_next_activity_url_skips_hidden_module(): void {
        global $PAGE, $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $naas1 = $this->getDataGenerator()->create_module('naas', ['course' => $course->id, 'name' => 'N1']);
        $naas2 = $this->getDataGenerator()->create_module('naas', ['course' => $course->id, 'name' => 'N2']);
        $naas3 = $this->getDataGenerator()->create_module('naas', ['course' => $course->id, 'name' => 'N3']);

        $DB->set_field('course_modules', 'visible', 0, ['id' => $naas2->cmid]);
        rebuild_course_cache($course->id, true);

        $PAGE->set_course($course);
        $PAGE->set_cm(get_fast_modinfo($course)->get_cm($naas1->cmid));

        $next = mod_util::get_next_activity_url();
        $this->assertNotNull($next);
        $this->assertEquals('N3', $next->name);
    }

    /**
     * When the course format reports a low numsections but modules exist beyond it,
     * iteration must stop (legacy navigation guard).
     *
     * Topics format does not persist numsections in course_format_options, so
     * get_last_section_number() would otherwise follow max(section). Force the
     * merged course object used by the format (same instance mod_util sees) to
     * carry numsections below an orphan section so the foreach hits break.
     */
    public function test_get_next_activity_url_breaks_when_section_exceeds_numsections(): void {
        global $PAGE;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(
            ['numsections' => 6, 'format' => 'topics'],
            ['createsections' => true]
        );
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $naas1 = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'N1',
        ]);
        $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'section' => 2,
            'name' => 'N2',
        ]);
        $this->getDataGenerator()->create_module('label', [
            'course' => $course->id,
            'section' => 5,
            'intro' => 'x',
            'introformat' => FORMAT_PLAIN,
            'name' => 'Orphan',
        ]);

        rebuild_course_cache($course->id, true);

        $format = \course_get_format($course->id);
        $merged = $format->get_course();
        $merged->numsections = 2;

        $PAGE->set_course(get_course($course->id));
        $PAGE->set_cm(get_fast_modinfo($course->id)->get_cm($naas1->cmid));

        $next = mod_util::get_next_activity_url();
        $this->assertNotNull($next);
        $this->assertSame('N2', $next->name);
    }

    /**
     * Two visible activities in the same section then another in the next section:
     * exercises the section transition branch where the current module is not the
     * last in its section (lastsection = previousmod).
     */
    public function test_get_next_activity_url_same_section_pair_then_next_section(): void {
        global $PAGE;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['numsections' => 3]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $naas1 = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'N1a',
        ]);
        $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'N1b',
        ]);
        $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'section' => 2,
            'name' => 'N2',
        ]);

        $PAGE->set_course($course);
        $PAGE->set_cm(get_fast_modinfo($course)->get_cm($naas1->cmid));

        $next = mod_util::get_next_activity_url();
        $this->assertNotNull($next);
        $this->assertSame('N1b', $next->name);
    }
}
